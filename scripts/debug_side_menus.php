<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = \App\Models\Admin::first();
$menus = function_exists('getSideMenus') ? getSideMenus($admin) : null;

if ($menus instanceof Illuminate\Support\Collection) {
    $arr = $menus->map(function ($m) {
        return is_array($m) ? $m : $m->toArray();
    })->values()->toArray();
} elseif (is_array($menus)) {
    $arr = $menus;
} else {
    $arr = null;
}

echo json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
