<?php

return [
    'paypal' => [
        'mode' => env('PAYPAL_MODE', 'sandbox'),
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'sandbox_url' => 'https://api-m.sandbox.paypal.com',
        'live_url' => 'https://api-m.paypal.com',
    ],
    'maps' => [
        'google_key' => env('GOOGLE_MAPS_API_KEY'),
    ],
];
