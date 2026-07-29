<?php
$urls = [
    'https://tokenized.sandbox.bka.sh/tokenized/checkout/token',
    'https://tokenized.sandbox.bka.sh/v2/tokenized/checkout/token',
    'https://tokenized.sandbox.bka.sh/checkout/token',
    'https://tokenized.sandbox.bka.sh/checkout/token/grant',
    'https://tokenized.sandbox.bka.sh/tokenized/checkout/token/grant',
];
$payload = [
    'app_key' => '0vWQuCRGiUX7EPVjQDr0EUAYtc',
    'app_secret' => 'jcUNPBgbcqEDedNKdvE4G1cAK7D3hCjmJccNPZZBq96QlxxwAMEx',
    'username' => '01770618567',
    'password' => 'D7DaC<*E*eG',
];
$headerCombos = [
    ['Content-Type' => 'application/json'],
    ['Content-Type' => 'application/json', 'X-App-Key' => $payload['app_key'], 'X-App-Secret' => $payload['app_secret'], 'username' => $payload['username'], 'password' => $payload['password']],
    ['Content-Type' => 'application/json', 'X-App-Key' => $payload['app_key'], 'X-App-Secret' => $payload['app_secret']],
    ['Content-Type' => 'application/json', 'X-App-Key' => $payload['app_key'], 'X-App-Secret' => $payload['app_secret'], 'accept' => 'application/json'],
];
foreach ($urls as $url) {
    foreach ($headerCombos as $headers) {
        echo "URL: $url\n";
        echo "HEADERS: " . json_encode($headers) . "\n";
        $headerLines = [];
        foreach ($headers as $k => $v) {
            $headerLines[] = "$k: $v";
        }
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headerLines) . "\r\n",
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
        echo 'Status: ' . ($status ?? 'unknown') . "\n";
        echo 'Body: ' . ($body === false ? '(failed)' : $body) . "\n\n";
    }
}
