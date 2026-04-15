<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FaceEncoding;
use App\Models\Attendance;

echo "Last 10 FaceEncodings:\n";
$encs = FaceEncoding::orderBy('id','desc')->take(10)->get();
foreach($encs as $e){
    echo "id={$e->id} code={$e->employee_code} descriptor_len=" . (is_array($e->descriptor)?count($e->descriptor):0) . " created_at={$e->created_at}\n";
}

echo "\nLast 10 Attendances:\n";
$atts = Attendance::orderBy('id','desc')->take(10)->get();
foreach($atts as $a){
    echo "id={$a->id} code={$a->employee_code} type={$a->type} recorded_at={$a->recorded_at} source={$a->source}\n";
}
