<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$s = App\Models\BkashSetting::first();
var_export([
    'db' => [
        'is_sandbox' => $s?->is_sandbox,
        'is_enabled' => $s?->is_enabled,
        'app_key' => $s?->app_key,
        'app_secret' => $s?->app_secret,
        'username' => $s?->username,
        'password' => $s?->password,
    ],
    'config' => [
        'use_production' => config('bkash.use_production'),
        'production_base_url' => config('bkash.production_base_url'),
        'token_endpoint' => config('bkash.token_endpoint'),
        'payment.enabled' => config('payment.enabled'),
    ],
    'subscription' => [
        'current' => App\Models\Subscription::getCurrent()?->toArray(),
    ],
]);
