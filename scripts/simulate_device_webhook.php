<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AttendanceDevice;
use App\Services\AttendanceDeviceService;
use Carbon\Carbon;

$identifier = $argv[1] ?? 'sim-device-1';
$employee_code = $argv[2] ?? 'test_emp_1';
$eventType = $argv[3] ?? 'in'; // 'in' or 'out'
$ts = $argv[4] ?? Carbon::now()->toDateTimeString();

echo "Ensuring device {$identifier} exists...\n";
$device = AttendanceDevice::firstOrCreate([
    'identifier' => $identifier,
], [
    'name' => 'Simulated Device',
    'type' => 'fingerprint',
    'secret' => null,
    'status' => 'Active',
]);

echo "Invoking AttendanceDeviceService->processAttendanceEvent()...\n";
$service = new AttendanceDeviceService();
$payload = [
    'device_id' => $identifier,
    'employee_code' => $employee_code,
    'type' => $eventType,
    'timestamp' => $ts,
];

$ok = $service->processAttendanceEvent($payload);
echo "Result: " . ($ok ? 'success' : 'failure') . "\n";
