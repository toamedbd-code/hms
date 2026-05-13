<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;
use App\Models\Permission;

$menuPermissions = Menu::query()
    ->whereNull('deleted_at')
    ->where('status', 'Active')
    ->pluck('permission_name')
    ->filter()
    ->map(function ($name) {
        return strtolower(trim((string) $name));
    })
    ->unique()
    ->values();

$permissionNames = Permission::query()
    ->where('guard_name', 'admin')
    ->pluck('name')
    ->filter()
    ->map(function ($name) {
        return strtolower(trim((string) $name));
    })
    ->unique()
    ->values();

$missing = $menuPermissions->diff($permissionNames)->values();

echo 'Active menu permission names: ' . $menuPermissions->count() . PHP_EOL;
echo 'Admin permissions: ' . $permissionNames->count() . PHP_EOL;
echo 'Missing menu-permission mappings: ' . $missing->count() . PHP_EOL;

if ($missing->isNotEmpty()) {
    foreach ($missing as $name) {
        echo '- ' . $name . PHP_EOL;
    }
}

echo 'Done' . PHP_EOL;
