<?php

return [
    // Base URLs - override via environment variables for real endpoints
    'sandbox_base_url' => env('BKASH_SANDBOX_BASE_URL', 'https://sandbox-bkash.example.com'),
    'production_base_url' => env('BKASH_PRODUCTION_BASE_URL', 'https://api.bkash.example.com'),

    // Endpoint paths (append to base url)
    'token_endpoint' => env('BKASH_TOKEN_ENDPOINT', '/tokenized/checkout/token'),
    'create_payment_endpoint' => env('BKASH_CREATE_PAYMENT_ENDPOINT', '/checkout/payment/create'),
    'execute_payment_endpoint' => env('BKASH_EXECUTE_PAYMENT_ENDPOINT', '/checkout/payment/execute'),
];
