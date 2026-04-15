<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FaceEncoding;
use App\Models\Attendance;
use Carbon\Carbon;

function euclideanDistance(array $a, array $b) : float {
    $sum = 0.0;
    $n = min(count($a), count($b));
    for ($i = 0; $i < $n; $i++) {
        $d = $a[$i] - $b[$i];
        $sum += $d * $d;
    }
    return sqrt($sum);
}

echo "Starting automated face E2E test...\n";

// 1) Register a face encoding
$employee = 'AUTO' . rand(1000,9999);
$baseDescriptor = [];
for ($i = 0; $i < 128; $i++) $baseDescriptor[] = round((sin($i/10) + 1) * 0.01, 6);
$fe = FaceEncoding::create([
    'employee_code' => $employee,
    'descriptor' => $baseDescriptor,
]);
echo "Registered FaceEncoding id={$fe->id} employee_code={$fe->employee_code}\n";

// 2) Simulate a detection by adding small noise to descriptor
$detected = [];
for ($i = 0; $i < 128; $i++) {
    $noise = (mt_rand(-5,5) / 1000); // small noise
    $detected[] = $baseDescriptor[$i] + $noise;
}

// 3) Find best match among stored encodings
$encodings = FaceEncoding::all();
$best = null; $bestDist = PHP_FLOAT_MAX; $bestEmp = null;
foreach ($encodings as $enc) {
    $dist = euclideanDistance($detected, $enc->descriptor ?? []);
    if ($dist < $bestDist) { $bestDist = $dist; $best = $enc; $bestEmp = $enc->employee_code; }
}

echo "Best match: employee={$bestEmp} distance={$bestDist}\n";

$threshold = 0.6;
if ($best && $bestDist <= $threshold) {
    $att = Attendance::create([
        'device_id' => null,
        'employee_code' => $bestEmp,
        'type' => 'in',
        'recorded_at' => Carbon::now(),
        'source' => 'automated-e2e',
        'meta' => ['distance' => $bestDist],
    ]);
    echo "Attendance created id={$att->id} for {$bestEmp}\n";
} else {
    echo "No match within threshold ({$threshold}). No attendance created.\n";
}

echo "E2E script finished.\n";
