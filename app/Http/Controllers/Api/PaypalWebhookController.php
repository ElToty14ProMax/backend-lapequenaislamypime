<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OrderPaidMail;
use App\Models\Payment;
use App\Services\InvoiceService;
use App\Services\PayPalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PaypalWebhookController extends Controller
{
    public function __construct(
        private readonly PayPalService $paypal,
        private readonly InvoiceService $invoices,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();
        $headers = collect($request->headers->all())
            ->map(fn (array $value) => $value[0] ?? null)
            ->all();

        abort_unless($this->paypal->verifyWebhook($headers, $payload), 403, 'Firma PayPal invalida.');

        if (($payload['event_type'] ?? null) !== 'PAYMENT.CAPTURE.COMPLETED') {
            return response()->json(['ignored' => true]);
        }

        $providerOrderId = data_get($payload, 'resource.supplementary_data.related_ids.order_id');
        $captureId = data_get($payload, 'resource.id');

        if (! $providerOrderId && ! $captureId) {
            return response()->json(['ignored' => true, 'reason' => 'missing_payment_ids']);
        }

        $payment = Payment::query()
            ->when($providerOrderId, fn ($query) => $query->where('provider_order_id', $providerOrderId))
            ->when(! $providerOrderId && $captureId, fn ($query) => $query->where('provider_capture_id', $captureId))
            ->first();

        if (! $payment) {
            return response()->json(['ignored' => true, 'reason' => 'payment_not_found']);
        }

        $order = $payment->order()->with(['user', 'items', 'address'])->firstOrFail();

        DB::transaction(function () use ($payment, $order, $payload, $captureId): void {
            $previousStatus = $order->status;

            $payment->update([
                'provider_capture_id' => $captureId,
                'status' => 'COMPLETED',
                'payload' => $payload,
                'paid_at' => $payment->paid_at ?? now(),
            ]);

            if ($order->payment_status !== 'paid') {
                $order->update([
                    'payment_status' => 'paid',
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

                $order->histories()->create([
                    'from_status' => $previousStatus,
                    'to_status' => 'paid',
                    'comment' => 'Pago confirmado por webhook PayPal.',
                ]);
            }

            $this->invoices->issueFor($order);
        });

        Mail::to($order->user)->queue(new OrderPaidMail($order->fresh(['invoice', 'items', 'address'])));

        return response()->json(['ok' => true]);
    }
}
