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
$data = [
    'attendance_device_enabled' => (bool)$ws->attendance_device_enabled,
    'attendance_device_type' => $ws->attendance_device_type,
    'attendance_device_identifier' => $ws->attendance_device_identifier,
    'attendance_device_ip' => $ws->attendance_device_ip,
    'attendance_device_port' => $ws->attendance_device_port,
    'attendance_device_secret_present' => !empty($ws->attendance_device_secret),
    'attendance_device_options' => $ws->attendance_device_options,
];

echo json_encode($data, JSON_PRETTY_PRINT);
