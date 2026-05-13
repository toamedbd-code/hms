<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

DB::beginTransaction();

try {
    $parent = Menu::query()
        ->where('name', 'Payroll')
        ->orWhere('permission_name', 'payroll-management')
        ->first();

    if (!$parent) {
        $parent = new Menu();
        $parent->name = 'Payroll';
        $parent->icon = 'payroll';
        $parent->route = null;
        $parent->permission_name = 'payroll-management';
        $parent->status = 'Active';
        $parent->sorting = 999;
        $parent->save();
        echo "[CREATED] Payroll parent menu" . PHP_EOL;
    }

    $existing = Menu::query()->where('route', 'backend.staffattendance.duty-roster')->first();
    $maxSort = (int) (Menu::query()->where('parent_id', $parent->id)->max('sorting') ?? 0);
    $targetSorting = $existing ? (int) ($existing->sorting ?? 5) : ($maxSort > 0 ? $maxSort + 1 : 5);

    $menu = $existing ?: new Menu();
    $menu->name = 'Duty Roster';
    $menu->icon = 'calendar';
    $menu->route = 'backend.staffattendance.duty-roster';
    $menu->permission_name = 'dutyroaster-list';
    $menu->status = 'Active';
    $menu->sorting = $targetSorting;
    $menu->parent_id = $parent->id;
    $menu->save();

    $permission = Permission::query()->firstOrCreate(
        ['name' => 'dutyroaster-list', 'guard_name' => 'admin'],
        ['parent_id' => null, 'sorting' => 1]
    );

    DB::commit();

    try {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    } catch (\Throwable $e) {
        // ignore cache reset failures
    }

    echo sprintf(
        '[OK] Duty Roster menu ensured | route=%s | parent=%s | sorting=%d | permission=%s',
        (string) $menu->route,
        (string) $parent->name,
        (int) ($menu->sorting ?? 0),
        (string) $permission->name
    ) . PHP_EOL;
    echo 'Done' . PHP_EOL;
} catch (Throwable $e) {
    DB::rollBack();
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
