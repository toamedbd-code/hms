<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$admin = App\Models\Admin::query()->first();
$menus = getSideMenus($admin);
var_dump($menus);
