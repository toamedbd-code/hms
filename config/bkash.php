<?php

return [
    // Base URLs - override via environment variables for real endpoints
    // The official bKash sandbox UAT API context root is https://tokenized.sandbox.bka.sh/v2.
    // When using the public sandbox site (https://bka.sh), this service will also
    // probe the tokenized sandbox host and the /v2 API context root.
    'sandbox_base_url' => env('BKASH_SANDBOX_BASE_URL', 'https://bka.sh'),
    'production_base_url' => env('BKASH_PRODUCTION_BASE_URL', env('BKASH_API_URL', 'https://api.bkash.example.com')),
    'aws_region' => env('BKASH_AWS_REGION', 'ap-southeast-1'),
    'aws_session_token' => env('BKASH_AWS_SESSION_TOKEN', null),
    'signature_region' => env('BKASH_SIGNATURE_REGION', 'sandbox'),
    // Use 'execute-api' and AWS4 defaults which the sandbox appears to expect.
    'signature_service' => env('BKASH_SIGNATURE_SERVICE', 'execute-api'),
    'signature_algorithm' => env('BKASH_SIGNATURE_ALGORITHM', 'AWS4-HMAC-SHA256'),
    // Credential scope terminator (terminator string used to derive signing key)
    'credential_scope_suffix' => env('BKASH_CREDENTIAL_SCOPE_SUFFIX', 'aws4_request'),
    // Key prefix used when deriving signing key (typically 'AWS4')
    'signature_key_prefix' => env('BKASH_SIGNATURE_KEY_PREFIX', 'AWS4'),

    // Endpoint paths (append to base url)
    'token_endpoint' => env('BKASH_TOKEN_ENDPOINT', '/tokenized/checkout/token'),
    'create_payment_endpoint' => env('BKASH_CREATE_PAYMENT_ENDPOINT', '/checkout/payment/create'),
    'execute_payment_endpoint' => env('BKASH_EXECUTE_PAYMENT_ENDPOINT', '/checkout/payment/execute'),
    // Optional query endpoint to check payment status when create/execute responses
    // are delayed or inconclusive.
    'query_payment_endpoint' => env('BKASH_QUERY_PAYMENT_ENDPOINT', '/checkout/payment/query'),

    // Fallback credentials when bKash settings are not stored in the database
    'app_key' => env('BKASH_APP_KEY', null),
    'app_secret' => env('BKASH_APP_SECRET', null),
    'username' => env('BKASH_USERNAME', null),
    'password' => env('BKASH_PASSWORD', null),
];
