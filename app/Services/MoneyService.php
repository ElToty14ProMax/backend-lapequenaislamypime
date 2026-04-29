<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\ExchangeRate;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class MoneyService
{
    public function priceFor(Product $product, string $currency): int
    {
        $currency = strtoupper($currency);
        $direct = $currency === 'USD' ? $product->price_usd_cents : $product->price_cup_cents;

        if ($direct !== null) {
            return (int) $direct;
        }

        if ($currency === 'USD' && $product->price_cup_cents !== null) {
            return $this->convertCents((int) $product->price_cup_cents, 'CUP', 'USD');
        }

        if ($currency === 'CUP' && $product->price_usd_cents !== null) {
            return $this->convertCents((int) $product->price_usd_cents, 'USD', 'CUP');
        }

        throw ValidationException::withMessages([
            'currency' => 'El producto no tiene precio disponible para '.$currency.'.',
        ]);
    }

    public function convertCents(int $amountCents, string $from, string $to): int
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return $amountCents;
        }

        $rate = $this->latestRate('USD', 'CUP');
        $amount = $amountCents / 100;

        if ($from === 'USD' && $to === 'CUP') {
            return (int) round($amount * $rate * 100);
        }

        if ($from === 'CUP' && $to === 'USD') {
            return (int) round(($amount / $rate) * 100);
        }

        throw ValidationException::withMessages([
            'currency' => "Conversion no soportada: {$from}->{$to}.",
        ]);
    }

    public function latestRate(string $base, string $quote): float
    {
        $rate = ExchangeRate::query()
            ->where('base_currency', strtoupper($base))
            ->where('quote_currency', strtoupper($quote))
            ->where('valid_from', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('valid_to')->orWhere('valid_to', '>', now());
            })
            ->latest('valid_from')
            ->first();

        if (! $rate) {
            throw ValidationException::withMessages([
                'exchange_rate' => "No existe tasa activa {$base}/{$quote}.",
            ]);
        }

        return (float) $rate->rate;
    }

    public function discountFor(Product $product, int $unitPriceCents, int $quantity, ?string $code = null): int
    {
        $now = Carbon::now();

        $discount = Discount::query()
            ->where('is_active', true)
            ->where(function ($query) use ($product): void {
                $query->where('product_id', $product->id)
                    ->orWhere('category_id', $product->category_id);
            })
            ->when($code, fn ($query) => $query->where('code', strtoupper($code)))
            ->where(function ($query) use ($now): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->where(function ($query): void {
                $query->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->orderByRaw("CASE WHEN product_id IS NOT NULL THEN 0 ELSE 1 END")
            ->latest()
            ->first();

        if (! $discount) {
            return 0;
        }

        $lineTotal = $unitPriceCents * $quantity;

        return match ($discount->type) {
            'percent' => min($lineTotal, (int) round($lineTotal * ((float) $discount->value / 100))),
            'fixed' => min($lineTotal, (int) round((float) $discount->value * 100)),
            default => 0,
        };
    }

    public function decimalString(int $amountCents): string
    {
        return number_format($amountCents / 100, 2, '.', '');
    }
}
