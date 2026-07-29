<?php
require __DIR__ . '/vendor/autoload.php';
use Illuminate\Support\Facades\Http;
$payload = [
    'app_key' => '0vWQuCRGiUX7EPVjQDr0EUAYtc',
    'app_secret' => 'jcUNPBgbcqEDedNKdvE4G1cAK7D3hCjmJccNPZZBq96QlxxwAMEx',
    'username' => '01770618567',
    'password' => 'D7DaC<*E*eG',
];
$urls = [
    'https://tokenized.sandbox.bka.sh/v2/tokenized/checkout/token',
    'https://tokenized.sandbox.bka.sh/tokenized/checkout/token',
];
$headersList = [
    [],
    ['Accept' => 'application/json'],
    ['X-App-Key' => $payload['app_key'], 'X-App-Secret' => $payload['app_secret']],
    ['x-app-key' => $payload['app_key'], 'x-app-secret' => $payload['app_secret']],
    ['Authorization' => 'Basic ' . base64_encode($payload['app_key'] . ':' . $payload['app_secret'])],
    ['Authorization' => 'Basic ' . base64_encode($payload['username'] . ':' . $payload['password'])],
    ['X-App-Key' => $payload['app_key'], 'X-App-Secret' => $payload['app_secret'], 'Accept' => 'application/json'],
    ['Authorization' => 'Basic ' . base64_encode($payload['app_key'] . ':' . $payload['app_secret']), 'Accept' => 'application/json'],
];
$idx=0;
foreach ($urls as $url) {
    foreach ($headersList as $headers) {
        $idx++;
        echo "=== Test #$idx ===\n";
        echo "URL: $url\n";
        echo "HEADERS: " . json_encode($headers) . "\n";
        try {
            $req = Http::withHeaders($headers)->timeout(15);
            $resp = $req->post($url, $payload);
            echo "STATUS: " . $resp->status() . "\n";
            echo "BODY: " . $resp->body() . "\n";
            echo "JSON: " . json_encode($resp->json()) . "\n";
            echo "HDRS: " . json_encode(method_exists($resp,'headers') ? $resp->headers() : []) . "\n";
        } catch (Throwable $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
}
