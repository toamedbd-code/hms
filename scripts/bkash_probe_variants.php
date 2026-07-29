<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\BkashService;

// Variants to try
$services = [
    'tokenized',
    'tokenized.sandbox',
    'sandbox',
    'execute-api',
];

$regions = [
    'sandbox',
    'bd',
    'ap-south-1',
    'us-east-1',
];

$algorithms = [
    'TOKENIZED4-HMAC-SHA256',
    'AWS4-HMAC-SHA256',
];

$keyPrefixes = [
    'TOKENIZED4',
    'AWS4',
];

$credentialSuffixes = [
    'tokenized4_request',
    'aws4_request',
];

$out = [];
foreach ($services as $service) {
    foreach ($regions as $region) {
        foreach ($algorithms as $algo) {
            foreach ($keyPrefixes as $kp) {
                foreach ($credentialSuffixes as $cs) {
                    // set runtime config for BkashService signing
                    config(['bkash.signature_service' => $service]);
                    config(['bkash.signature_region' => $region ?: 'sandbox']);
                    config(['bkash.signature_algorithm' => $algo]);
                    config(['bkash.signature_key_prefix' => $kp]);
                    config(['bkash.credential_scope_suffix' => $cs]);

                    $label = implode(' | ', [$service, $region ?: 'sandbox', $algo, $kp, $cs]);
                    echo "\n==> Testing: $label\n";

                    $svc = new BkashService();
                    $results = $svc->probeTokenEndpoints();

                    // Summarize
                    $summary = [
                        'variant' => $label,
                        'checked' => count($results),
                        'samples' => [],
                    ];

                    $non403 = 0;
                    foreach ($results as $r) {
                        $status = $r['status'] ?? null;
                        if ($status !== 403 && $status !== null) $non403++;
                        $summary['samples'][] = [
                            'url' => $r['url'] ?? null,
                            'status' => $status,
                            'message' => isset($r['json']['message']) ? $r['json']['message'] : (isset($r['body']) ? substr($r['body'], 0, 200) : null),
                        ];
                    }

                    $summary['non403_count'] = $non403;
                    echo "Checked: {$summary['checked']}, Non-403: {$summary['non403_count']}\n";
                    foreach (array_slice($summary['samples'], 0, 10) as $s) {
                        echo " - [{$s['status']}] {$s['url']} => {$s['message']}\n";
                    }

                    $out[] = $summary;
                    // small pause to be polite
                    usleep(200000);
                }
            }
        }
    }
}

$path = __DIR__ . '/../storage/logs/bkash-probe-'.date('Ymd_His').'.json';
file_put_contents($path, json_encode($out, JSON_PRETTY_PRINT));
echo "\nSaved full results to: $path\n";

return 0;
