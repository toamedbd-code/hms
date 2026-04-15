<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BkashSetting;
$s = BkashSetting::first();
if ($s) {
    echo json_encode($s->toArray());
} else {
    echo "null";
}
