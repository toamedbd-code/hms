<?php
$base = 'https://tokenized.sandbox.bka.sh';
$paths = [
    '/tokenized/checkout/token',
    '/v2/tokenized/checkout/token',
    '/v1.0.0-beta/tokenized/checkout/token',
    '/checkout/token',
    '/token',
];
$payloads = [
    ['app_key' => '0vWQuCRGiUX7EPVjQDr0EUAYtc','app_secret' => 'jcUNPBgbcqEDedNKdvE4G1cAK7D3hCjmJccNPZZBq96QlxxwAMEx'],
    ['app_key' => '0vWQuCRGiUX7EPVjQDr0EUAYtc','app_secret' => 'jcUNPBgbcqEDedNKdvE4G1cAK7D3hCjmJccNPZZBq96QlxxwAMEx','username' => '01770618567','password' => 'D7DaC<*E*eG'],
];
$headersVariants = [
    ['Content-Type' => 'application/json'],
    ['Content-Type' => 'application/json','Accept' => 'application/json'],
    ['Content-Type' => 'application/x-www-form-urlencoded'],
    ['Content-Type' => 'application/json','X-App-Key' => '0vWQuCRGiUX7EPVjQDr0EUAYtc','X-App-Secret' => 'jcUNPBgbcqEDedNKdvE4G1cAK7D3hCjmJccNPZZBq96QlxxwAMEx'],
    ['Content-Type' => 'application/json','Authorization' => 'Basic ' . base64_encode('0vWQuCRGiUX7EPVjQDr0EUAYtc:jcUNPBgbcqEDedNKdvE4G1cAK7D3hCjmJccNPZZBq96QlxxwAMEx')],
];
foreach ($paths as $path) {
    foreach ($payloads as $payload) {
        foreach ($headersVariants as $headers) {
            $url = rtrim($base, '/') . '/' . ltrim($path, '/');
            $body = isset($headers['Content-Type']) && $headers['Content-Type'] === 'application/x-www-form-urlencoded'
                ? http_build_query($payload)
                : json_encode($payload);
            echo "URL: $url\n";
            echo "HEADERS: " . json_encode($headers) . "\n";
            echo "PAYLOAD: " . json_encode($payload) . "\n";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function ($k, $v) { return "$k: $v"; }, array_keys($headers), $headers));
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $resp = curl_exec($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
            if ($resp === false) {
                echo "ERROR: failed to execute\n";
            } else {
                echo "HTTP: " . $info['http_code'] . "\n";
                echo "RESPONSE: " . substr($resp, $info['header_size']) . "\n";
            }
            echo "=======================================\n";
        }
    }
}
