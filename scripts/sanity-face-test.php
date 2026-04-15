<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FaceEncoding;
use App\Models\Attendance;
use Carbon\Carbon;

echo "Creating test face encoding...\n";
$descriptor = array_fill(0, 128, 0.01);
$fe = FaceEncoding::create([
    'employee_code' => 'TEST001',
    'descriptor' => $descriptor,
]);
echo "FaceEncoding id={$fe->id} employee_code={$fe->employee_code}\n";

echo "Creating attendance record...\n";
$att = Attendance::create([
    'device_id' => null,
    'employee_code' => 'TEST001',
    'type' => 'in',
    'recorded_at' => Carbon::now(),
    'source' => 'sanity-script',
    'meta' => ['note' => 'sanity test'],
]);
echo "Attendance id={$att->id} employee_code={$att->employee_code} recorded_at={$att->recorded_at}\n";

echo "Done.\n";
