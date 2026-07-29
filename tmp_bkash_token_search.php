<?php
$bases = [
    'https://tokenized.sandbox.bka.sh',
    'https://tokenized.sandbox.bka.sh/v2',
    'https://tokenized.sandbox.bka.sh/v1',
    'https://tokenized.sandbox.bka.sh/v3',
];
$paths = [
    '/tokenized/checkout/token',
    '/checkout/token',
    '/token',
    '/tokenized/checkout/token/',
    '/checkout/token/',
    '/v2/tokenized/checkout/token',
    '/v1/tokenized/checkout/token',
    '/v2/checkout/token',
    '/v2/token',
    '/v1/token',
    '/tokenized/checkout/token/v2',
    '/checkout/token/v2',
    '/v2/checkout/token',
];
$payload = [
    'app_key' => '0vWQuCRGiUX7EPVjQDr0EUAYtc',
    'app_secret' => 'jcUNPBgbcqEDedNKdvE4G1cAK7D3hCjmJccNPZZBq96QlxxwAMEx',
    'username' => '01770618567',
    'password' => 'D7DaC<*E*eG',
];
foreach ($bases as $base) {
    foreach ($paths as $path) {
        $url = rtrim($base, '/') . '/' . ltrim($path, '/');
        echo "URL: $url\n";
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode($payload),
                'ignore_errors' => true,
                'timeout' => 15,
            ],
        ];
        $context = stream_context_create($opts);
        $body = @file_get_contents($url, false, $context);
        $status = null;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $hdr) {
                if (preg_match('#HTTP/\d\.\d\s+(\d+)#', $hdr, $m)) {
                    $status = $m[1]; break;
                }
            }
        }
        echo 'Status: ' . ($status ?? 'unknown') . "\n";
        echo 'Body: ' . ($body === false ? '(failed)' : $body) . "\n\n";
    }
}
