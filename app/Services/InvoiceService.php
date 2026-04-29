<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;

class InvoiceService
{
    public function issueFor(Order $order): Invoice
    {
        return Invoice::firstOrCreate(
            ['order_id' => $order->id],
            [
                'number' => $this->nextInvoiceNumber(),
                'currency' => $order->currency,
                'subtotal_cents' => $order->subtotal_cents,
                'discount_cents' => $order->discount_cents,
                'shipping_cents' => $order->shipping_cents,
                'tax_cents' => $order->tax_cents,
                'total_cents' => $order->total_cents,
                'issued_at' => now(),
                'billing_snapshot' => [
                    'customer' => $order->user?->only(['id', 'name', 'email', 'phone']),
                    'address' => $order->address?->toArray(),
                    'items' => $order->items->map(fn ($item) => $item->only([
                        'product_name',
                        'sku',
                        'quantity',
                        'unit_price_cents',
                        'discount_cents',
                        'total_cents',
                        'currency',
                    ]))->values()->all(),
                ],
            ]
        );
    }

    private function nextInvoiceNumber(): string
    {
        return 'FAC-'.now()->format('YmdHis').'-'.random_int(100, 999);
    }
}
