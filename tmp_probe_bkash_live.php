<?php
$creds = [
    'app_key' => '5ET8KfYGKWvy4f1qjocFIdGatc',
    'app_secret' => 'wW4Od4d0LCLffa1SgslExTMzxG9mNpwXvacvf7W3JAN8SdAeulXf',
    'username' => '01410592638',
    'password' => '8{?uSM}(@K4',
];

$urls = [
    'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant',
    'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout/token',
    'https://tokenized.pay.bka.sh/v1.2.0-beta/checkout/token/grant',
    'https://tokenized.pay.bka.sh/v1.2.0-beta/checkout/token',
];

$variants = [
    ['type' => 'json_body', 'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'], 'body' => ['app_key' => $creds['app_key'], 'app_secret' => $creds['app_secret']]],
    ['type' => 'json_body_with_auth', 'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json', 'username' => $creds['username'], 'password' => $creds['password']], 'body' => ['app_key' => $creds['app_key'], 'app_secret' => $creds['app_secret']]],
    ['type' => 'form_body_with_auth', 'headers' => ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json', 'username' => $creds['username'], 'password' => $creds['password']], 'body' => ['app_key' => $creds['app_key'], 'app_secret' => $creds['app_secret']]],
    ['type' => 'header_basic', 'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json', 'Authorization' => 'Basic ' . base64_encode($creds['app_key'] . ':' . $creds['app_secret'])], 'body' => ['app_key' => $creds['app_key'], 'app_secret' => $creds['app_secret']]],
    ['type' => 'header_basic_userpass', 'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json', 'Authorization' => 'Basic ' . base64_encode($creds['username'] . ':' . $creds['password'])], 'body' => ['app_key' => $creds['app_key'], 'app_secret' => $creds['app_secret']]],
    ['type' => 'headers_xapp', 'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json', 'X-App-Key' => $creds['app_key'], 'X-App-Secret' => $creds['app_secret']], 'body' => ['app_key' => $creds['app_key'], 'app_secret' => $creds['app_secret']]],
];

foreach ($urls as $url) {
    foreach ($variants as $variant) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_POST, true);
        $headers = [];
        foreach ($variant['headers'] as $k => $v) {
            $headers[] = "$k: $v";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($variant['type'] === 'form_body_with_auth') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($variant['body']));
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($variant['body']));
        }
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        echo "URL=$url\n";
        echo "VARIANT={$variant['type']}\n";
        echo "HTTP=$httpCode\n";
        echo "BODY=$resp\n";
        echo "---\n";
        curl_close($ch);
    }
}
