<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment feature toggles
    |--------------------------------------------------------------------------
    |
    | Use `PAYMENT_ENABLED` in your .env to quickly enable/disable all
    | payment-related public and admin flows. Defaults to true.
    |
    */
    'enabled' => env('PAYMENT_ENABLED', true),
    'mode' => env('PAYMENT_MODE', 'on'),
];
