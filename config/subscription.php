<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Subscription amounts and defaults
    |--------------------------------------------------------------------------
    |
    | Define monthly/yearly subscription amounts here. These are read from
    | environment variables so they can be changed without code edits.
    |
    */
    'monthly_amount' => env('SUBSCRIPTION_MONTHLY_AMOUNT', 2000),
    'yearly_amount' => env('SUBSCRIPTION_YEARLY_AMOUNT', 20000),
    'default_period' => env('SUBSCRIPTION_DEFAULT_PERIOD', 'monthly'),
];
