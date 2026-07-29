<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$admin = App\Models\Admin::where('email', 'toamedbd@gmail.com')->first();
if (!$admin) {
    echo "not found\n";
    exit(1);
}
var_dump($admin->roles->pluck('name')->all());
var_dump(method_exists($admin, 'hasRole'));
var_dump($admin->hasRole('developer'));
$menus = getSideMenus($admin);
echo "menu count=" . count($menus) . "\n";
foreach ($menus as $menu) {
    echo $menu['id'] . ' ' . $menu['name'] . ' route=' . ($menu['route'] ?? 'NULL') . '\n';
}
