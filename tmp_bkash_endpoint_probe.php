<?php
$payload = [
    'app_key' => '0vWQuCRGiUX7EPVjQDr0EUAYtc',
    'app_secret' => 'jcUNPBgbcqEDedNKdvE4G1cAK7D3hCjmJccNPZZBq96QlxxwAMEx',
    'username' => '01770618567',
    'password' => 'D7DaC<*E*eG',
];
$baseUrls = [
    'https://tokenized.sandbox.bka.sh',
    'https://tokenized.sandbox.bka.sh/v2',
    'https://tokenized.sandbox.bka.sh/v1',
    'https://tokenized.sandbox.bka.sh/v1.0.0-beta',
    'https://tokenized.sandbox.bka.sh/v1.1.0-beta',
    'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
    'https://sandbox.bkash.com',
    'https://sandbox.bkash.com/v1.0.0-beta',
];
$paths = [
    '/tokenized/checkout/token',
    '/checkout/token',
    '/token',
    '/tokenized/checkout/token/',
    '/checkout/token/',
    '/v2/tokenized/checkout/token',
    '/v1/tokenized/checkout/token',
    '/v1.0.0-beta/tokenized/checkout/token',
    '/tokenized/checkout/token/v1',
];
$headersList = [
    [
        'Content-Type' => 'application/json',
    ],
    [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ],
];

foreach ($baseUrls as $baseUrl) {
    foreach ($paths as $path) {
        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        echo "URL: $url\n";
        foreach ($headersList as $headers) {
            echo "HEADERS: " . json_encode($headers) . "\n";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function ($k, $v) { return "$k: $v"; }, array_keys($headers), $headers));
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $resp = curl_exec($ch);
            $info = curl_getinfo($ch);
            if ($resp === false) {
                echo "CURL ERROR: " . curl_error($ch) . "\n";
            } else {
                echo "HTTP: " . $info['http_code'] . "\n";
                $body = substr($resp, $info['header_size']);
                echo "BODY: " . trim($body) . "\n";
            }
            curl_close($ch);
            echo "---\n";
        }
        echo "====\n";
    }
}
