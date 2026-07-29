<?php
$dataJson = [
    'app_key' => '0vWQuCRGiUX7EPVjQDr0EUAYtc',
    'app_secret' => 'jcUNPBgbcqEDedNKdvE4G1cAK7D3hCjmJccNPZZBq96QIxxwAMEx',
    'username' => '01770618567',
    'password' => 'D7DaC<*E*eG',
];
$dataJsonAlt = [
    'appKey' => $dataJson['app_key'],
    'appSecret' => $dataJson['app_secret'],
    'userName' => $dataJson['username'],
    'password' => $dataJson['password'],
];
$dataForm = http_build_query($dataJson);
$authBasic = base64_encode($dataJson['app_key'] . ':' . $dataJson['app_secret']);
$headersCommon = ['Accept: application/json'];
$variants = [
    ['name' => 'json-body', 'headers' => array_merge($headersCommon, ['Content-Type: application/json']), 'body' => json_encode($dataJson)],
    ['name' => 'json-body-alt-keys', 'headers' => array_merge($headersCommon, ['Content-Type: application/json']), 'body' => json_encode($dataJsonAlt)],
    ['name' => 'form-body', 'headers' => array_merge($headersCommon, ['Content-Type: application/x-www-form-urlencoded']), 'body' => $dataForm],
    ['name' => 'basic-auth-json', 'headers' => array_merge($headersCommon, ['Content-Type: application/json', 'Authorization: Basic ' . $authBasic]), 'body' => json_encode($dataJson)],
    ['name' => 'basic-auth-form', 'headers' => array_merge($headersCommon, ['Content-Type: application/x-www-form-urlencoded', 'Authorization: Basic ' . $authBasic]), 'body' => $dataForm],
    ['name' => 'x-header-json', 'headers' => array_merge($headersCommon, ['Content-Type: application/json', 'x-app-key: ' . $dataJson['app_key'], 'x-app-secret: ' . $dataJson['app_secret'], 'x-username: ' . $dataJson['username'], 'x-password: ' . $dataJson['password']]), 'body' => json_encode($dataJson)],
];
$urls = [
    'https://tokenized.sandbox.bka.sh/v2/tokenized/checkout/token',
    'https://tokenized.sandbox.bka.sh/v2/tokenized/checkout/token/grant',
];
foreach ($urls as $url) {
    foreach ($variants as $variant) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $variant['headers']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $variant['body']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $body = curl_exec($ch);
        $info = curl_getinfo($ch);
        $err = curl_error($ch);
        curl_close($ch);

        echo "URL: $url\n";
        echo "Variant: {$variant['name']}\n";
        echo "HTTP_CODE: " . ($info['http_code'] ?? 'N/A') . "\n";
        echo "ERROR: $err\n";
        echo "BODY: $body\n";
        echo str_repeat('=', 90) . "\n";
    }
}
