<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\Http;
use App\Models\BkashSetting;
$setting = BkashSetting::first();
$base = ($setting && isset($setting->is_sandbox) && ! $setting->is_sandbox)
    ? rtrim(config('bkash.production_base_url'), '/')
    : rtrim(config('bkash.sandbox_base_url'), '/');
$endpoint = config('bkash.token_endpoint');
$full = rtrim($base, '/') . '/' . ltrim($endpoint, '/');
$payload = [
    'app_key' => $setting->app_key ?? config('bkash.app_key'),
    'app_secret' => $setting->app_secret ?? config('bkash.app_secret'),
    'username' => $setting->username ?? config('bkash.username'),
    'password' => $setting->password ?? config('bkash.password'),
];
var_dump(['base' => $base, 'endpoint' => $endpoint, 'full' => $full, 'payload' => $payload]);
try {
    $resp = Http::timeout(15)->post($full, $payload);
    var_dump(['status' => $resp->status(), 'body' => $resp->body(), 'json' => $resp->json()]);
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}
