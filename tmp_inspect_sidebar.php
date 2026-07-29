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
$menus = getSideMenus($admin);
echo json_encode($menus, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
