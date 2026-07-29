<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use App\Models\BkashSetting;

$setting = BkashSetting::first();
$baseCandidates = [];
$base = ($setting && isset($setting->is_sandbox) && ! $setting->is_sandbox)
    ? rtrim(config('bkash.production_base_url'), '/')
    : rtrim(config('bkash.sandbox_base_url'), '/');

$tokenEndpoint = '/' . ltrim(config('bkash.token_endpoint'), '/');

$baseCandidates[] = $base; // configured
// also try with/without trailing segment variations
if (str_ends_with($base, '/v2')) {
    $baseCandidates[] = rtrim($base, '/v2');
}
$baseCandidates[] = $base . '/v2';

$pathCandidates = [
    $tokenEndpoint,
    '/tokenized/checkout/token',
    '/v2/tokenized/checkout/token',
    '/token',
];

$payload = [
    'app_key' => $setting->app_key ?? config('bkash.app_key'),
    'app_secret' => $setting->app_secret ?? config('bkash.app_secret'),
    'username' => $setting->username ?? config('bkash.username'),
    'password' => $setting->password ?? config('bkash.password'),
];

$headersVariants = [
    [],
    ['Accept' => 'application/json'],
    ['X-App-Key' => $payload['app_key']],
    ['X-Api-Key' => env('BKASH_API_KEY') ?: ''],
];

$formTypes = ['json', 'form'];

$results = [];

foreach ($baseCandidates as $b) {
    foreach ($pathCandidates as $p) {
        $url = rtrim($b, '/') . '/' . ltrim($p, '/');
        foreach ($headersVariants as $hv) {
            foreach ($formTypes as $form) {
                $label = implode('|', [parse_url($url, PHP_URL_PATH), $form, json_encode($hv)]);
                try {
                    $req = Http::timeout(15);
                    if (!empty($hv)) $req = $req->withHeaders($hv);
                    if ($form === 'json') {
                        $resp = $req->post($url, $payload);
                    } else {
                        $resp = $req->asForm()->post($url, $payload);
                    }

                    $results[] = [
                        'url' => $url,
                        'form' => $form,
                        'headers_sent' => $hv,
                        'status' => $resp->status(),
                        'body' => $resp->body(),
                        'response_headers' => method_exists($resp, 'headers') ? $resp->headers() : null,
                    ];
                } catch (\Throwable $e) {
                    $results[] = [
                        'url' => $url,
                        'form' => $form,
                        'headers_sent' => $hv,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }
    }
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
