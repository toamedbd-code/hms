<?php
function getSignatureKey($key, $dateStamp, $regionName, $serviceName)
{
    $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $key, true);
    $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
    $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
    return hash_hmac('sha256', 'aws4_request', $kService, true);
}

$accessKey = '0vWQuCRGiUX7EPVjQDr0EUAYtc';
$secretKey = 'jcUNPBgbcqEDedNKdvE4G1cAK7D3hCjmJccNPZZBq96QIxxwAMEx';
$url = 'https://tokenized.sandbox.bka.sh/v2/tokenized/checkout/token';
$method = 'POST';
$payload = json_encode([
    'app_key' => $accessKey,
    'app_secret' => $secretKey,
    'username' => '01770618567',
    'password' => 'D7DaC<*E*eG',
]);

$parsed = parse_url($url);
$host = $parsed['host'] ?? '';
$path = $parsed['path'] ?? '/';
$queryString = $parsed['query'] ?? '';
$amzDate = gmdate('Ymd\THis\Z');
$dateStamp = gmdate('Ymd');
$region = 'us-east-1';
$service = 'execute-api';
$payloadHash = hash('sha256', $payload);
$canonicalHeaders = "content-type:application/json\nhost:$host\nx-amz-content-sha256:$payloadHash\nx-amz-date:$amzDate\n";
$signedHeaders = 'content-type;host;x-amz-content-sha256;x-amz-date';
$canonicalRequest = implode("\n", [
    $method,
    $path,
    $queryString,
    $canonicalHeaders,
    $signedHeaders,
    $payloadHash,
]);
$credentialScope = "$dateStamp/$region/$service/aws4_request";
$stringToSign = implode("\n", [
    'AWS4-HMAC-SHA256',
    $amzDate,
    $credentialScope,
    hash('sha256', $canonicalRequest),
]);
$signingKey = getSignatureKey($secretKey, $dateStamp, $region, $service);
$signature = hash_hmac('sha256', $stringToSign, $signingKey);
$authorization = sprintf(
    'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
    $accessKey,
    $credentialScope,
    $signedHeaders,
    $signature
);
$headers = [
    'Content-Type: application/json',
    'Accept: application/json',
    'Host: ' . $host,
    'x-amz-date: ' . $amzDate,
    'x-amz-content-sha256: ' . $payloadHash,
    'Authorization: ' . $authorization,
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
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
