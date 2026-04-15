<?php
$payload = file_get_contents(__DIR__ . '/payload.json');
$sig = hash_hmac('sha256', $payload, 'DEVICE_SECRET');
$opts = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nX-Device-Signature: sha256={$sig}\r\n",
        'content' => $payload,
        'ignore_errors' => true,
    ],
];
$context = stream_context_create($opts);
$candidates = [
    'http://localhost/api/attendance/device/webhook',
    'http://localhost/hms/api/attendance/device/webhook',
    'http://localhost/hms/public/api/attendance/device/webhook',
    'http://127.0.0.1/api/attendance/device/webhook',
    'http://127.0.0.1:8000/api/attendance/device/webhook',
];

foreach ($candidates as $url) {
    $response = @file_get_contents($url, false, $context);
    // Check response code from $http_response_header if present
    $statusOk = false;
    if (isset($http_response_header) && is_array($http_response_header)) {
        $statusLine = $http_response_header[0] ?? '';
        if (preg_match('#HTTP/\d\.\d\s+(\d{3})#', $statusLine, $m)) {
            $code = intval($m[1]);
            $statusOk = ($code >= 200 && $code < 300);
        }
    }

    if ($response !== false && $statusOk) {
        echo "URL: $url\n";
        echo $response . PHP_EOL;
        exit(0);
    } else {
        echo "Tried $url — response code not 2xx or no response.\n";
    }
}

$err = error_get_last();
echo "All attempts failed. Last error: " . ($err['message'] ?? 'unknown') . PHP_EOL;
exit(1);
