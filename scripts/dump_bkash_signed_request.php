<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\BkashService;

echo "Preparing signed-request dump for bKash token endpoint...\n";

$svc = new BkashService();
$candidates = $svc->getTokenEndpointCandidates();
if (empty($candidates)) {
    echo "No token endpoint candidates found in configuration.\n";
    exit(1);
}

$url = $candidates[0];

$payload = [
    'app_key' => env('BKASH_APP_KEY'),
    'app_secret' => env('BKASH_APP_SECRET'),
    'username' => env('BKASH_USERNAME'),
    'password' => env('BKASH_PASSWORD'),
];

$payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);

$parsed = parse_url($url);
$host = $parsed['host'] ?? '';
$path = $parsed['path'] ?? '/';
$queryString = $parsed['query'] ?? '';

$amzDate = gmdate('Ymd\THis\Z');
$dateStamp = gmdate('Ymd');
$rfcDate = gmdate('D, d M Y H:i:s \G\M\T');

$region = config('bkash.signature_region', 'sandbox');
$serviceName = config('bkash.signature_service', 'tokenized');
$algorithm = config('bkash.signature_algorithm', 'TOKENIZED4-HMAC-SHA256');
$credentialScopeSuffix = config('bkash.credential_scope_suffix', 'tokenized4_request');
$keyPrefix = config('bkash.signature_key_prefix', 'AWS4');

$payloadHash = hash('sha256', $payloadJson);

$canonicalHeaders = "accept:application/json\ncontent-type:application/json\ndate:$rfcDate\nhost:$host\nx-amz-content-sha256:$payloadHash\nx-amz-date:$amzDate\nx-sandbox-date:$amzDate\n";
$signedHeaders = 'accept;content-type;date;host;x-amz-content-sha256;x-amz-date;x-sandbox-date';

$canonicalRequest = implode("\n", [
    'POST',
    $path,
    $queryString,
    $canonicalHeaders,
    $signedHeaders,
    $payloadHash,
]);

$credentialScope = "$dateStamp/$region/$serviceName/{$credentialScopeSuffix}";
$stringToSign = implode("\n", [
    $algorithm,
    $amzDate,
    $credentialScope,
    hash('sha256', $canonicalRequest),
]);

// derive signing key (binary)
function hmac_bin($data, $key) { return hash_hmac('sha256', $data, $key, true); }

$secretKey = env('BKASH_APP_SECRET');
$kDate = hmac_bin($dateStamp, $keyPrefix . $secretKey);
$kRegion = hmac_bin($region, $kDate);
$kService = hmac_bin($serviceName, $kRegion);
$kSigning = hmac_bin($credentialScopeSuffix, $kService);

$signature = hash_hmac('sha256', $stringToSign, $kSigning);

$authorization = sprintf(
    '%s Credential=%s/%s, SignedHeaders=%s, Signature=%s',
    $algorithm,
    env('BKASH_APP_KEY'),
    $credentialScope,
    $signedHeaders,
    $signature
);

$headers = [
    'Content-Type' => 'application/json',
    'Accept' => 'application/json',
    'Date' => $rfcDate,
    'x-amz-date' => $amzDate,
    'x-amz-content-sha256' => $payloadHash,
    'x-sandbox-date' => $amzDate,
    'Authorization' => $authorization,
];

// Mask secrets for saved dump
function mask($s) {
    if (! $s) return null;
    $len = strlen($s);
    if ($len <= 6) return str_repeat('*', $len);
    return substr($s, 0, 3) . str_repeat('*', max(0, $len-6)) . substr($s, -3);
}

$dump = [
    'timestamp' => date('c'),
    'endpoint' => $url,
    'request' => [
        'method' => 'POST',
        'path' => $path,
        'query' => $queryString,
        'headers' => $headers,
        'payload' => $payload,
        'payload_json' => $payloadJson,
    ],
    'canonical' => [
        'canonical_request' => $canonicalRequest,
        'string_to_sign' => $stringToSign,
        'credential_scope' => $credentialScope,
    ],
    'computed' => [
        'signature' => $signature,
        'authorization_header' => $authorization,
    ],
    'secrets_masked' => [
        'app_key' => mask(env('BKASH_APP_KEY')),
        'app_secret' => mask(env('BKASH_APP_SECRET')),
        'username' => mask(env('BKASH_USERNAME')),
        'password' => mask(env('BKASH_PASSWORD')),
    ],
];

$pathOut = __DIR__ . '/../storage/logs/bkash-signed-dump-'.date('Ymd_His').'.json';
file_put_contents($pathOut, json_encode($dump, JSON_PRETTY_PRINT));
echo "Signed-request dump saved to: $pathOut\n";
echo "Do NOT share the dump publicly; provide it to bKash support if requested.\n";

return 0;
