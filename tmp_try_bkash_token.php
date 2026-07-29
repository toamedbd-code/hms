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
$endpoint = '/' . ltrim(config('bkash.token_endpoint'), '/');
$url = $base . $endpoint;

$payload = [
    'app_key' => $setting->app_key ?? config('bkash.app_key'),
    'app_secret' => $setting->app_secret ?? config('bkash.app_secret'),
    'username' => $setting->username ?? config('bkash.username'),
    'password' => $setting->password ?? config('bkash.password'),
];

echo "POSTing to: $url\n";
echo "Payload: " . json_encode($payload) . "\n";

try {
    $resp = Http::timeout(15)->post($url, $payload);
    echo "HTTP: " . $resp->status() . "\n";
    echo "Body: " . $resp->body() . "\n";
    if (method_exists($resp, 'headers')) {
        echo "Headers: " . json_encode($resp->headers()) . "\n";
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
