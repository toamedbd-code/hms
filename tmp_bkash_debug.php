<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$payment = App\Models\Payment::find(895);
if (! $payment) {
    echo "PAYMENT MISSING\n";
    exit(1);
}
$service = new App\Services\BkashService();
$result = $service->createPayment($payment);
var_dump($result);
$setting = App\Models\BkashSetting::first();
var_dump([ 'is_sandbox' => $setting?->is_sandbox, 'app_key' => $setting?->app_key, 'app_secret' => $setting?->app_secret, 'username' => $setting?->username, 'password' => $setting?->password, 'config_app_key' => config('bkash.app_key'), 'config_token' => config('bkash.token_endpoint'), 'config_base' => config('bkash.sandbox_base_url') ]);
