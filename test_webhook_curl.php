<?php
$payload = file_get_contents(__DIR__ . '/payload.json');
$sig = hash_hmac('sha256', $payload, 'DEVICE_SECRET');
$ch = curl_init('http://127.0.0.1:8000/api/attendance/device/webhook');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    "X-Device-Signature: sha256={$sig}",
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);
echo "HTTP code: " . ($info['http_code'] ?? 'n/a') . PHP_EOL;
echo $response . PHP_EOL;
