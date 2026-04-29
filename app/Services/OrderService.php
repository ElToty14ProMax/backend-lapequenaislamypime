<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private readonly MoneyService $money)
    {
    }

    public function createFromActiveCart(User $user, int $addressId, string $currency = 'USD', ?string $notes = null): Order
    {
        $currency = strtoupper($currency);

        return DB::transaction(function () use ($user, $addressId, $currency, $notes): Order {
            $cart = Cart::query()
                ->with('items.product.category')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $cart || $cart->items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'El carrito esta vacio.']);
            }

            $subtotal = 0;
            $discount = 0;
            $items = [];

            foreach ($cart->items as $cartItem) {
                $product = $cartItem->product;

                if ($product->status !== 'active') {
                    throw ValidationException::withMessages(['product' => "{$product->name} no esta disponible."]);
                }

                if ($product->track_inventory && $product->stock < $cartItem->quantity) {
                    throw ValidationException::withMessages(['stock' => "Stock insuficiente para {$product->name}."]);
                }

                $unitPrice = $this->money->priceFor($product, $currency);
                $lineDiscount = $this->money->discountFor($product, $unitPrice, $cartItem->quantity);
                $lineTotal = ($unitPrice * $cartItem->quantity) - $lineDiscount;

                $subtotal += $unitPrice * $cartItem->quantity;
                $discount += $lineDiscount;

                $items[] = compact('product', 'cartItem', 'unitPrice', 'lineDiscount', 'lineTotal');
            }

            $shipping = 0;
            $tax = 0;
            $total = max(0, $subtotal - $discount + $shipping + $tax);

            $order = Order::create([
                'number' => $this->nextOrderNumber(),
                'user_id' => $user->id,
                'address_id' => $addressId,
                'status' => 'pending',
                'payment_status' => 'pending',
                'currency' => $currency,
                'subtotal_cents' => $subtotal,
                'discount_cents' => $discount,
                'shipping_cents' => $shipping,
                'tax_cents' => $tax,
                'total_cents' => $total,
                'exchange_rate_snapshot' => [
                    'USD_CUP' => $this->money->latestRate('USD', 'CUP'),
                    'captured_at' => now()->toISOString(),
                ],
                'notes' => $notes,
            ]);

            foreach ($items as $item) {
                $product = $item['product'];
                $cartItem = $item['cartItem'];

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => $cartItem->quantity,
                    'unit_price_cents' => $item['unitPrice'],
                    'discount_cents' => $item['lineDiscount'],
                    'total_cents' => $item['lineTotal'],
                    'currency' => $currency,
                ]);

                if ($product->track_inventory) {
                    $product->decrement('stock', $cartItem->quantity);
                }
            }

            $order->histories()->create([
                'user_id' => $user->id,
                'to_status' => 'pending',
                'comment' => 'Pedido creado desde carrito.',
            ]);

            $cart->update(['status' => 'checked_out']);

            return $order->fresh(['items', 'address', 'payments']);
        });
    }

    private function nextOrderNumber(): string
    {
        return 'LPI-'.now()->format('Ymd').'-'.str_pad((string) (Order::whereDate('created_at', today())->count() + 1), 5, '0', STR_PAD_LEFT);
    }
}
