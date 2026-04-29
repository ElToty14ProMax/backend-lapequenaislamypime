<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OrderPaidMail;
use App\Models\Order;
use App\Models\Payment;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\PayPalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly PayPalService $paypal,
        private readonly InvoiceService $invoices,
    ) {
    }

    public function createOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'address_id' => ['required', 'integer', 'exists:addresses,id'],
            'currency' => ['nullable', 'in:USD,CUP'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_unless($request->user()->addresses()->whereKey($data['address_id'])->exists(), 422, 'Direccion invalida.');

        $order = $this->orders->createFromActiveCart(
            $request->user(),
            $data['address_id'],
            $data['currency'] ?? 'USD',
            $data['notes'] ?? null
        );

        return response()->json($order, 201);
    }

    public function createPaypalOrder(Order $order, Request $request): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        if ($order->payment_status !== 'pending') {
            throw ValidationException::withMessages(['order' => 'Este pedido ya no esta pendiente de pago.']);
        }

        $paypalOrder = $this->paypal->createOrder($order);

        $payment = $order->payments()->create([
            'provider' => 'paypal',
            'provider_order_id' => $paypalOrder['id'] ?? null,
            'status' => $paypalOrder['status'] ?? 'created',
            'currency' => 'USD',
            'amount_cents' => $order->total_cents,
            'payload' => $paypalOrder,
        ]);

        return response()->json([
            'payment' => $payment,
            'paypal' => $paypalOrder,
        ], 201);
    }

    public function capturePaypalOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'paypal_order_id' => ['required', 'string'],
        ]);

        $payment = Payment::where('provider_order_id', $data['paypal_order_id'])->firstOrFail();
        $order = $payment->order()->with(['user', 'items', 'address'])->firstOrFail();

        abort_unless($order->user_id === $request->user()->id, 404);

        $capture = $this->paypal->capture($data['paypal_order_id']);
        $captureUnit = data_get($capture, 'purchase_units.0.payments.captures.0');
        $captureStatus = strtoupper((string) data_get($captureUnit, 'status', $capture['status'] ?? ''));

        if ($captureStatus !== 'COMPLETED') {
            throw ValidationException::withMessages(['paypal' => 'PayPal aun no reporta el pago como COMPLETED.']);
        }

        DB::transaction(function () use ($payment, $order, $capture, $captureUnit): void {
            $payment->update([
                'provider_capture_id' => data_get($captureUnit, 'id'),
                'status' => data_get($captureUnit, 'status', $capture['status'] ?? 'captured'),
                'payload' => $capture,
                'paid_at' => now(),
            ]);

            $order->update([
                'payment_status' => 'paid',
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            $order->histories()->create([
                'user_id' => $order->user_id,
                'from_status' => 'pending',
                'to_status' => 'paid',
                'comment' => 'Pago confirmado por PayPal.',
            ]);

            $this->invoices->issueFor($order);
        });

        Mail::to($order->user)->queue(new OrderPaidMail($order->fresh(['invoice', 'items', 'address'])));

        return response()->json($order->fresh(['payments', 'invoice', 'items']));
    }
}
