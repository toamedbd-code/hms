<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$count = App\Models\Admin::query()->count();
echo 'admin_count=' . $count . PHP_EOL;
