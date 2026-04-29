<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PayPalService
{
    public function __construct(private readonly MoneyService $money)
    {
    }

    public function createOrder(Order $order): array
    {
        if ($order->currency !== 'USD') {
            throw ValidationException::withMessages([
                'currency' => 'PayPal se procesa en USD. Cree el pedido en USD.',
            ]);
        }

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->post($this->baseUrl().'/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $order->number,
                    'amount' => [
                        'currency_code' => 'USD',
                        'value' => $this->money->decimalString($order->total_cents),
                    ],
                ]],
            ]);

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'paypal' => $response->json('message', 'No se pudo crear la orden en PayPal.'),
            ]);
        }

        return $response->json();
    }

    public function capture(string $providerOrderId): array
    {
        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->withHeaders(['PayPal-Request-Id' => (string) str()->uuid()])
            ->post($this->baseUrl()."/v2/checkout/orders/{$providerOrderId}/capture");

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'paypal' => $response->json('message', 'No se pudo capturar el pago en PayPal.'),
            ]);
        }

        return $response->json();
    }

    public function verifyWebhook(array $headers, array $payload): bool
    {
        $webhookId = config('services.paypal.webhook_id');

        if (! $webhookId) {
            return false;
        }

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->post($this->baseUrl().'/v1/notifications/verify-webhook-signature', [
                'auth_algo' => $headers['paypal-auth-algo'] ?? null,
                'cert_url' => $headers['paypal-cert-url'] ?? null,
                'transmission_id' => $headers['paypal-transmission-id'] ?? null,
                'transmission_sig' => $headers['paypal-transmission-sig'] ?? null,
                'transmission_time' => $headers['paypal-transmission-time'] ?? null,
                'webhook_id' => $webhookId,
                'webhook_event' => $payload,
            ]);

        return $response->ok() && $response->json('verification_status') === 'SUCCESS';
    }

    private function accessToken(): string
    {
        $clientId = config('services.paypal.client_id');
        $secret = config('services.paypal.client_secret');

        if (! $clientId || ! $secret) {
            throw ValidationException::withMessages([
                'paypal' => 'Faltan PAYPAL_CLIENT_ID y PAYPAL_CLIENT_SECRET.',
            ]);
        }

        $response = Http::asForm()
            ->withBasicAuth($clientId, $secret)
            ->post($this->baseUrl().'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'paypal' => 'No se pudo autenticar con PayPal.',
            ]);
        }

        return (string) $response->json('access_token');
    }

    private function baseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? config('services.paypal.live_url')
            : config('services.paypal.sandbox_url');
    }
}
