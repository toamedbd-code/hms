<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Admin;

echo "== User permissions and sideMenus debug ==\n";

$email = $argv[1] ?? env('SINGLE_DEV_EMAIL', 'toamedbd@gmail.com');
$admin = Admin::where('email', $email)->first();
if ($admin) {
    echo "User: " . ($admin->email ?? $admin->name ?? 'unknown') . " (id={$admin->id})\n";

    try {
        $perms = $admin->getAllPermissions()->pluck('name')->toArray();
    } catch (\Throwable $e) {
        $perms = [];
    }

    echo "Permissions:\n";
    print_r($perms);

    echo "\ngetSideMenus output (top-level names):\n";
    try {
        $menus = getSideMenus($admin);
        $names = collect($menus)->map(fn($m) => data_get($m, 'name'))->toArray();
        print_r($names);
    } catch (\Throwable $e) {
        echo "Error getting side menus: " . $e->getMessage() . "\n";
    }

} else {
    echo "No user with email {$email} found.\n";
}

echo "Done.\n";
