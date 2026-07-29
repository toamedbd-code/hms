<?php
$payloads = [
    'json' => json_encode([
        'app_key' => '0vWQuCRGiUX7EPVjQDr0EUAYtc',
        'app_secret' => 'jcUNPBgbcqEDedNKdvE4G1cAK7D3hCjmJccNPZZBq96QlxxwAMEx',
        'username' => '01770618567',
        'password' => 'D7DaC<*E*eG',
    ]),
    'form' => http_build_query([
        'app_key' => '0vWQuCRGiUX7EPVjQDr0EUAYtc',
        'app_secret' => 'jcUNPBgbcqEDedNKdvE4G1cAK7D3hCjmJccNPZZBq96QlxxwAMEx',
        'username' => '01770618567',
        'password' => 'D7DaC<*E*eG',
    ]),
];
$bases = [
    'https://tokenized.sandbox.bka.sh',
    'https://tokenized.sandbox.bkash.com',
    'https://sandbox.bkash.com',
    'https://api.bkash.com',
    'https://tokenized.bkash.com',
    'https://sandbox.bka.sh',
    'https://bka.sh',
];
$paths = [
    '/v1.0.0-beta/tokenized/checkout/token',
    '/v1.0.0-beta/checkout/token',
    '/v1.0.0-beta/token',
    '/v1.0.0-beta/tokenized/checkout/token/',
    '/v1.0.0-beta/checkout/token/',
    '/tokenized/checkout/token',
    '/checkout/token',
    '/token',
    '/tokenized/checkout/token/',
    '/checkout/token/',
    '/v2/tokenized/checkout/token',
    '/v2/checkout/token',
    '/v2/token',
];
$headersList = [
    ['Content-Type' => 'application/json'],
    ['Content-Type' => 'application/x-www-form-urlencoded'],
    ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
    ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json'],
    ['Content-Type' => 'application/json', 'X-App-Key' => '0vWQuCRGiUX7EPVjQDr0EUAYtc', 'X-App-Secret' => 'jcUNPBgbcqEDedNKdvE4G1cAK7D3hCjmJccNPZZBq96QlxxwAMEx'],
    ['Content-Type' => 'application/json', 'X-App-Key' => '0vWQuCRGiUX7EPVjQDr0EUAYtc', 'X-App-Secret' => 'jcUNPBgbcqEDedNKdvE4G1cAK7D3hCjmJccNPZZBq96QlxxwAMEx', 'Accept' => 'application/json'],
];
$comboLimit = 30;
$count = 0;
foreach ($bases as $base) {
    foreach ($paths as $path) {
        foreach ($payloads as $type => $body) {
            foreach ($headersList as $headers) {
                if (++$count > $comboLimit) {
                    echo "Stopped after $comboLimit tests.\n";
                    exit(0);
                }
                $url = rtrim($base, '/') . '/' . ltrim($path, '/');
                echo "--- Test #$count ---\n";
                echo "URL: $url\n";
                echo "BODY_TYPE: $type\n";
                echo "HEADERS: " . json_encode($headers) . "\n";
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function ($k, $v) { return "$k: $v"; }, array_keys($headers), $headers));
                curl_setopt($ch, CURLOPT_HEADER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                $resp = curl_exec($ch);
                $info = curl_getinfo($ch);
                if ($resp === false) {
                    echo "CURL ERROR: " . curl_error($ch) . "\n";
                } else {
                    echo "HTTP: " . $info['http_code'] . "\n";
                    $bodyText = substr($resp, $info['header_size']);
                    echo "BODY: " . trim($bodyText) . "\n";
                }
                curl_close($ch);
                echo "\n";
            }
        }
    }
}
