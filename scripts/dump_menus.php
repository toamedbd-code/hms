<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;

$menus = Menu::whereNull('parent_id')->with('childrens')->orderBy('sorting','asc')->get();

echo "Top-level menus: " . $menus->count() . "\n\n";
foreach ($menus as $m) {
    echo "Name: " . ($m->name ?? '') . " | permission: " . ($m->permission_name ?? '') . " | route: " . ($m->route ?? '') . " | module: " . ($m->module_slug ?? '') . "\n";
    if ($m->childrens && $m->childrens->isNotEmpty()) {
        foreach ($m->childrens as $c) {
            echo "  - Child: " . ($c->name ?? '') . " | permission: " . ($c->permission_name ?? '') . " | route: " . ($c->route ?? '') . "\n";
        }
    }
    echo "\n";
}

echo "Done.\n";
