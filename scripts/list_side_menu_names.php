<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = \App\Models\Admin::first();
$menus = getSideMenus($admin);
$names = [];
if (is_array($menus)) {
    foreach ($menus as $m) $names[] = $m['name'] ?? null;
} else {
    foreach ($menus as $m) $names[] = is_array($m) ? ($m['name'] ?? null) : ($m->name ?? null);
}
foreach ($names as $n) echo $n . "\n";
