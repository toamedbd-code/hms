<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use App\Models\AttendanceDevice;
$device = AttendanceDevice::where('identifier', 'test-device-1')->first();
if ($device) {
    echo "Found device:\n";
    print_r($device->toArray());
} else {
    echo "Device not found\n";
}
