<?php
$urls = [
    'https://tokenized.sandbox.bka.sh/tokenized/checkout/token',
    'https://tokenized.sandbox.bka.sh/v2/tokenized/checkout/token',
    'https://tokenized.sandbox.bka.sh/v2/tokenized/checkout/token/',
    'https://tokenized.sandbox.bka.sh/token',
    'https://tokenized.sandbox.bka.sh/v2/token',
];
$payload = [
    'app_key' => '0vWQuCRGiUX7EPVjQDr0EUAYtc',
    'app_secret' => 'jcUNPBgbcqEDedNKdvE4G1cAK7D3hCjmJccNPZZBq96QlxxwAMEx',
    'username' => '01770618567',
    'password' => 'D7DaC<*E*eG',
];
foreach ($urls as $url) {
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
                $status = $m[1];
                break;
            }
        }
    }
    echo "Status: " . ($status ?? 'unknown') . "\n";
    echo "Body: " . ($body === false ? '(failed)' : $body) . "\n\n";
}
