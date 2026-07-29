<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$val = app('cache')->get('bkash_test_last');
echo json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
