<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WebSetting;

$ws = WebSetting::first();
if (!$ws) {
    echo "No WebSetting row found.\n";
    exit(1);
}

$update = [
    'attendance_device_enabled' => true,
    'attendance_device_type' => 'face',
    'attendance_device_identifier' => 'face01',
    'attendance_device_ip' => '192.168.1.50',
    'attendance_device_port' => '8000',
    'attendance_device_secret' => 'secret123',
    'attendance_device_options' => json_encode(['timeout' => 30]),
];

$ws->update($update);
$ws = $ws->fresh();

echo json_encode($ws->toArray(), JSON_PRETTY_PRINT);
