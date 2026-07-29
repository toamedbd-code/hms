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
$service = 'execute-api';
$regions = [
    'us-east-1',
    'us-east-2',
    'us-west-1',
    'us-west-2',
    'ap-south-1',
    'ap-southeast-1',
    'ap-southeast-2',
    'ap-northeast-1',
    'ap-northeast-2',
    'eu-west-1',
    'eu-west-2',
    'eu-central-1',
    'sa-east-1',
];

foreach ($regions as $region) {
    $amzDate = gmdate('Ymd\THis\Z');
    $dateStamp = gmdate('Ymd');
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

    echo "REGION: $region\n";
    echo "HTTP_CODE: " . ($info['http_code'] ?? 'N/A') . "\n";
    echo "ERROR: $err\n";
    echo "BODY: $body\n";
    echo str_repeat('-', 90) . "\n";
}
