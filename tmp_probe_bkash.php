<?php
$data = json_encode([
    'app_key' => '0vWQuCRGiUX7EPVjQDr0EUAYtc',
    'app_secret' => 'jcUNPBgbcqEDedNKdvE4G1cAK7D3hCjmJccNPZZBq96QIxxwAMEx',
    'username' => '01770618567',
    'password' => 'D7DaC<*E*eG',
]);
$urls = [
    'https://tokenized.sandbox.bka.sh/v2/tokenized/checkout/token',
    'https://tokenized.sandbox.bka.sh/v2/tokenized/checkout/token/grant',
    'https://tokenized.sandbox.bka.sh/tokenized/checkout/token',
    'https://tokenized.sandbox.bka.sh/tokenized/checkout/token/grant',
];
foreach ($urls as $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $body = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err = curl_error($ch);
    curl_close($ch);

    echo "URL: $url\n";
    echo "HTTP_CODE: " . ($info['http_code'] ?? 'N/A') . "\n";
    echo "ERROR: $err\n";
    echo "BODY: $body\n";
    echo str_repeat('-', 80) . "\n";
}
