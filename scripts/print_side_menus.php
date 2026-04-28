<?php
// Usage: php scripts/print_side_menus.php [email]
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$email = $argv[1] ?? 'toamedbd@gmail.com';
$admin = \App\Models\Admin::where('email', $email)->first();
if (!$admin) {
    echo "Admin not found: $email\n";
    exit(1);
}

$menus = getSideMenus($admin);
echo json_encode($menus, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
