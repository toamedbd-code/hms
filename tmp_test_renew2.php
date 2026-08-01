<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Mimic browser Accept header
$request = Illuminate\Http\Request::create(
    '/payment/bkash/renew?amount=3000&period=monthly',
    'GET',
    [],
    [],
    [],
    [
        'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
        'HTTP_HOST' => 'hms.test',
    ]
);

$response = $kernel->handle($request);
echo 'status=' . $response->getStatusCode() . PHP_EOL;
echo 'location=' . $response->headers->get('Location') . PHP_EOL;
echo 'payment_enabled=' . var_export(config('payment.enabled'), true) . PHP_EOL;
$setting = App\Models\BkashSetting::first();
echo 'bkash_enabled=' . var_export($setting?->is_enabled, true) . PHP_EOL;
echo 'flash_error=' . ($request->session()->get('errorMessage') ?? '-') . PHP_EOL;
echo 'flash_success=' . ($request->session()->get('successMessage') ?? '-') . PHP_EOL;
$kernel->terminate($request, $response);
