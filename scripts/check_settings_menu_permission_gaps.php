<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;
use App\Models\Permission;

$settingMenuPermissions = Menu::query()
    ->whereNull('deleted_at')
    ->where('status', 'Active')
    ->where(function ($q) {
        $q->where('name', 'like', '%Setting%')
            ->orWhere('name', 'like', '%Settings%')
            ->orWhere('route', 'like', '%setting%')
            ->orWhere('permission_name', 'like', '%setting%');
    })
    ->pluck('permission_name')
    ->filter()
    ->map(function ($name) {
        return strtolower(trim((string) $name));
    })
    ->unique()
    ->values();

$adminPermissionNames = Permission::query()
    ->where('guard_name', 'admin')
    ->pluck('name')
    ->filter()
    ->map(function ($name) {
        return strtolower(trim((string) $name));
    })
    ->unique()
    ->values();

$missing = $settingMenuPermissions->diff($adminPermissionNames)->values();

echo 'Settings menu permission names: ' . $settingMenuPermissions->count() . PHP_EOL;
echo 'Missing settings menu permissions: ' . $missing->count() . PHP_EOL;

if ($missing->isNotEmpty()) {
    foreach ($missing as $name) {
        echo '- ' . $name . PHP_EOL;
    }
}

echo 'Done' . PHP_EOL;
