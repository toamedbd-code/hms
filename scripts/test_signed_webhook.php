<?php
require __DIR__ . '/../vendor/autoload.php';

$baseUrl = rtrim($argv[1] ?? 'http://hms.test', '/');
$identifier = $argv[2] ?? 'zkteco-k40-1';
$secret = $argv[3] ?? 'change-me-secret';
$employeeCode = $argv[4] ?? 'EMP001';
$type = strtolower($argv[5] ?? 'in');
$timestamp = $argv[6] ?? date('c');

if (!in_array($type, ['in', 'out'], true)) {
    fwrite(STDERR, "Invalid type. Use in or out.\n");
    exit(1);
}

$payload = [
    'device_id' => $identifier,
    'employee_code' => $employeeCode,
    'type' => $type,
    'timestamp' => $timestamp,
];

$body = json_encode($payload, JSON_UNESCAPED_SLASHES);
$signature = 'sha256=' . hash_hmac('sha256', $body, $secret);
$url = $baseUrl . '/api/attendance/device/webhook';

$headers = [
    'Content-Type: application/json',
    'Accept: application/json',
    'X-Device-Signature: ' . $signature,
];

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers),
        'content' => $body,
        'ignore_errors' => true,
        'timeout' => 15,
    ],
]);

$response = file_get_contents($url, false, $context);
$statusLine = $http_response_header[0] ?? 'HTTP/1.1 000';
$code = 0;
if (preg_match('/\s(\d{3})\s/', $statusLine, $m)) {
    $code = (int) $m[1];
}

echo "POST {$url}\n";
echo "Payload: {$body}\n";
echo "HTTP: {$statusLine}\n";
echo "Response: " . ($response !== false ? $response : '') . "\n";

exit(($code >= 200 && $code < 300) ? 0 : 2);
