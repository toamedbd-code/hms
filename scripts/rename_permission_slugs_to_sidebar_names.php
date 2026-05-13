<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

$renameMap = [
    'hospital-test' => 'item-charge',
    'user-management' => 'human-resource',
];

DB::beginTransaction();

try {
    $permissionRenamed = 0;
    $menuUpdated = 0;
    $skipped = [];

    foreach ($renameMap as $old => $new) {
        $oldPermission = Permission::query()->where('name', $old)->first();
        $newPermission = Permission::query()->where('name', $new)->first();

        if ($oldPermission && $newPermission && (int) $oldPermission->id !== (int) $newPermission->id) {
            $skipped[] = "permission collision: {$old} -> {$new}";
        } elseif ($oldPermission && !$newPermission) {
            $oldPermission->name = $new;
            $oldPermission->save();
            $permissionRenamed++;
        }

        $menuUpdated += Menu::query()
            ->where('permission_name', $old)
            ->update(['permission_name' => $new]);
    }

    DB::commit();

    try {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    } catch (\Throwable $e) {
        // ignore
    }

    echo "Permissions renamed: {$permissionRenamed}\n";
    echo "Menus updated: {$menuUpdated}\n";
    if (!empty($skipped)) {
        echo "Skipped:\n";
        foreach ($skipped as $line) {
            echo "- {$line}\n";
        }
    }
    echo "Done\n";
} catch (Throwable $e) {
    DB::rollBack();
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
