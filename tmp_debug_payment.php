<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Subscription;
use App\Models\BkashSetting;

$sub = Subscription::getCurrent();
echo 'sub_exists=' . ($sub ? 'yes' : 'no') . PHP_EOL;
if ($sub) {
    echo 'sub_active=' . ($sub->is_active ? 'yes' : 'no') . PHP_EOL;
    echo 'expires=' . ($sub->expires_at ? $sub->expires_at->toDateTimeString() : 'null') . PHP_EOL;
    echo 'sub_isActiveMethod=' . ($sub->isActive() ? 'yes' : 'no') . PHP_EOL;
}
$setting = BkashSetting::first();
echo 'setting_exists=' . ($setting ? 'yes' : 'no') . PHP_EOL;
if ($setting) {
    echo 'setting_enabled=' . ($setting->is_enabled ? 'yes' : 'no') . PHP_EOL;
    echo 'is_sandbox=' . ($setting->is_sandbox ? 'yes' : 'no') . PHP_EOL;
    echo 'monthly_amount=' . $setting->monthly_amount . PHP_EOL;
    echo 'yearly_amount=' . $setting->yearly_amount . PHP_EOL;
}
echo 'env_PAYMENT_ENABLED=' . (getenv('PAYMENT_ENABLED') ?: 'null') . PHP_EOL;
echo 'config_payment_enabled=' . (bool) config('payment.enabled') . PHP_EOL;
