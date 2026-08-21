<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\Admin::query()->first();
$menus = getSideMenus($user);
echo 'sidebar_items=' . count($menus) . PHP_EOL;
foreach ($menus as $menu) {
    echo $menu['name'] . PHP_EOL;
}
