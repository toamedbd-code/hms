<?php

return [
    /*
    |--------------------------------------------------------------------------
    | bKash Tokenized Checkout (PGW)
    |--------------------------------------------------------------------------
    |
    | Product demo (v2 UI):
    |   https://merchantdemo.sandbox.bka.sh/tokenized-checkout/version/v2
    |
    | Working sandbox API context root (Grant Token returns 0000 here):
    |   https://tokenized.sandbox.bka.sh/v1.2.0-beta
    |
    | Note: PGW onboarding email may list .../v2/ as context root, but the
    | live sandbox Grant Token endpoint currently responds on v1.2.0-beta.
    |
    | All API calls must use a 30 second timeout.
    |
    */

    'sandbox_base_url' => env(
        'BKASH_SANDBOX_BASE_URL',
        'https://tokenized.sandbox.bka.sh/v1.2.0-beta'
    ),

    'production_base_url' => env(
        'BKASH_PRODUCTION_BASE_URL',
        env('BKASH_API_URL', 'https://tokenized.pay.bka.sh/v1.2.0-beta')
    ),

    // Official Tokenized Checkout endpoint paths (appended to base URL)
    'token_endpoint' => env('BKASH_TOKEN_ENDPOINT', '/tokenized/checkout/token/grant'),
    'create_payment_endpoint' => env('BKASH_CREATE_PAYMENT_ENDPOINT', '/tokenized/checkout/create'),
    'execute_payment_endpoint' => env('BKASH_EXECUTE_PAYMENT_ENDPOINT', '/tokenized/checkout/execute'),
    'query_payment_endpoint' => env('BKASH_QUERY_PAYMENT_ENDPOINT', '/tokenized/checkout/payment/status'),

    // Credentials (fallback when not stored in bkash_settings table)
    'app_key' => env('BKASH_APP_KEY'),
    'app_secret' => env('BKASH_APP_SECRET'),
    'username' => env('BKASH_USERNAME'),
    'password' => env('BKASH_PASSWORD'),

    /*
    | Public HTTPS callback URL required by bKash (local .test hosts are rejected).
    | Example with ngrok: https://xxxx.ngrok-free.app/payment/bkash/callback
    */
    'callback_url' => env('BKASH_CALLBACK_URL'),

    // bKash requires 30s timeout for all APIs
    'http_timeout' => (int) env('BKASH_HTTP_TIMEOUT', 30),
    'http_verify_ssl' => filter_var(env('BKASH_HTTP_VERIFY_SSL', false), FILTER_VALIDATE_BOOLEAN),
];
