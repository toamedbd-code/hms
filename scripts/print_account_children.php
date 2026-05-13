<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = \App\Models\Admin::first();
if (!$admin) { echo "No admin user\n"; exit(1); }

$menus = getSideMenus($admin);
$found = false;
foreach ($menus as $m) {
    $name = is_array($m) ? ($m['name'] ?? '') : ($m->name ?? '');
    if (stripos($name, 'account') !== false) {
        $found = true;
        echo "Parent: " . $name . "\n";
        $children = is_array($m) ? ($m['childrens'] ?? []) : ($m->childrens ?? collect());
        if (is_array($children)) {
            foreach ($children as $c) {
                echo " - " . ($c['name'] ?? '') . " | perm:" . ($c['permission_name'] ?? $c['permission'] ?? '') . " | route:" . ($c['route'] ?? '') . "\n";
            }
        } else {
            foreach ($children as $c) {
                echo " - " . ($c->name ?? '') . " | perm:" . ($c->permission_name ?? $c->permission ?? '') . " | route:" . ($c->route ?? '') . "\n";
            }
        }
    }
}
if (!$found) echo "No Account Management parent found\n";
