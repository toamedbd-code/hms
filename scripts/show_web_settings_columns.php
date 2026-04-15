<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$cols = \Illuminate\Support\Facades\DB::select(\Illuminate\Support\Facades\DB::raw('SHOW COLUMNS FROM web_settings'));
echo json_encode($cols, JSON_PRETTY_PRINT);
