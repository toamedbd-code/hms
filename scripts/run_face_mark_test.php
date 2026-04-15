<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FaceEncoding;
use App\Models\Attendance;
use Carbon\Carbon;

$employee_code = $argv[1] ?? 'test_emp_1';
$descriptor = [0,0,0,0,0];

echo "Creating FaceEncoding for {$employee_code}...\n";
$fe = FaceEncoding::create([
    'employee_code' => $employee_code,
    'descriptor' => $descriptor,
]);
echo "FaceEncoding created id={$fe->id}\n";

echo "Recording 'in' attendance...\n";
$att = Attendance::create([
    'device_id' => null,
    'employee_code' => $employee_code,
    'type' => 'in',
    'recorded_at' => Carbon::now(),
    'source' => 'script',
    'meta' => ['note' => 'automated test script'],
]);
echo "Attendance created id={$att->id}\n";

// Optionally create an 'out' record 1 hour later to compute duration
$outTs = Carbon::now()->addHour();
$attOut = Attendance::create([
    'employee_code' => $employee_code,
    'type' => 'out',
    'recorded_at' => $outTs->toDateTimeString(),
    'recorded_out' => $outTs->toDateTimeString(),
    'device_id' => null,
    'source' => 'script',
]);
echo "Out attendance created id={$attOut->id}\n";

// Try to pair and update duration using service logic (optional)
echo "Done.\n";
