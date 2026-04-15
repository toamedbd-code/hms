<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FaceEncoding;

$items = FaceEncoding::orderBy('id','desc')->take(10)->get();
foreach ($items as $it) {
    echo "id={$it->id} code={$it->employee_code} len=" . count($it->descriptor) . " created_at={$it->created_at}\n";
}
