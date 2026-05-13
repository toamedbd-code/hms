<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

function ensure_permission(string $name, ?string $parentName, int $sorting, int &$created, int &$updated): Permission
{
    $permission = Permission::query()->firstOrCreate(
        ['name' => $name, 'guard_name' => 'admin'],
        ['parent_id' => null, 'sorting' => $sorting]
    );

    if (!$permission->wasRecentlyCreated) {
        $parentId = null;
        if ($parentName) {
            $parent = Permission::query()->where('guard_name', 'admin')->where('name', $parentName)->first();
            $parentId = $parent ? (int) $parent->id : null;
        }

        $needsUpdate = ((int) ($permission->parent_id ?? 0) !== (int) ($parentId ?? 0))
            || ((int) ($permission->sorting ?? 0) !== $sorting);

        if ($needsUpdate) {
            $permission->parent_id = $parentId;
            $permission->sorting = $sorting;
            $permission->save();
            $updated++;
        }
    } else {
        $created++;

        if ($parentName) {
            $parent = Permission::query()->where('guard_name', 'admin')->where('name', $parentName)->first();
            if ($parent) {
                $permission->parent_id = $parent->id;
                $permission->sorting = $sorting;
                $permission->save();
            }
        }
    }

    return $permission;
}

function copy_role_permissions(string $fromPermission, string $toPermission, int &$copied): void
{
    $from = Permission::query()->where('guard_name', 'admin')->where('name', $fromPermission)->first();
    $to = Permission::query()->where('guard_name', 'admin')->where('name', $toPermission)->first();

    if (!$from || !$to || (int) $from->id === (int) $to->id) {
        return;
    }

    $roleIds = DB::table('role_has_permissions')
        ->where('permission_id', $from->id)
        ->pluck('role_id');

    foreach ($roleIds as $roleId) {
        $exists = DB::table('role_has_permissions')
            ->where('permission_id', $to->id)
            ->where('role_id', $roleId)
            ->exists();

        if (!$exists) {
            DB::table('role_has_permissions')->insert([
                'permission_id' => $to->id,
                'role_id' => $roleId,
            ]);
            $copied++;
        }
    }
}

DB::beginTransaction();

try {
    $menuUpdated = 0;
    $menuDisabled = 0;
    $permissionCreated = 0;
    $permissionUpdated = 0;
    $rolePermissionCopied = 0;

    $permissionParents = [
        'settings-management' => 1,
        'account-management' => 1,
        'pharmacy-management' => 1,
        'report-management' => 1,
    ];

    foreach ($permissionParents as $parentName => $sorting) {
        ensure_permission($parentName, null, $sorting, $permissionCreated, $permissionUpdated);
    }

    $settingsMap = [
        'backend.websetting.section.cms' => ['cms-setting', 1],
        'backend.websetting.section.general' => ['general-setting', 2],
        'backend.websetting.section.prefix' => ['prefix-setting', 3],
        'backend.websetting.section.sms' => ['sms-setting', 4],
        'backend.websetting.section.other' => ['other-setting', 5],
        'backend.settings.payment.bkash' => ['b-kash-settings', 6],
        'backend.activity-logs.index' => ['activity-logs', 7],
        'backend.activity-logs.print' => ['activity-logs-print', 8],
    ];

    foreach ($settingsMap as $route => [$permissionName, $sorting]) {
        ensure_permission($permissionName, 'settings-management', $sorting, $permissionCreated, $permissionUpdated);

        $menus = Menu::query()
            ->where('route', $route)
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->get();

        foreach ($menus as $menu) {
            if ((string) $menu->permission_name !== $permissionName) {
                $menu->permission_name = $permissionName;
                $menu->save();
                $menuUpdated++;
            }
        }
    }

    ensure_permission('vendor-payment-list', 'account-management', 2, $permissionCreated, $permissionUpdated);
    $vendorMenus = Menu::query()
        ->where('route', 'backend.accounts.vendor-payment.index')
        ->where('name', 'Vendor Payment')
        ->where('status', 'Active')
        ->whereNull('deleted_at')
        ->get();
    foreach ($vendorMenus as $menu) {
        if ((string) $menu->permission_name !== 'vendor-payment-list') {
            $menu->permission_name = 'vendor-payment-list';
            $menu->save();
            $menuUpdated++;
        }
    }

    ensure_permission('supplier-payment-list', 'pharmacy-management', 5, $permissionCreated, $permissionUpdated);
    ensure_permission('activity-log-view', 'account-management', 1, $permissionCreated, $permissionUpdated);
    ensure_permission('report-summary', 'report-management', 4, $permissionCreated, $permissionUpdated);

    $reportSummaryMenus = Menu::query()
        ->where('route', 'backend.report-summary.index')
        ->where('status', 'Active')
        ->whereNull('deleted_at')
        ->get();
    foreach ($reportSummaryMenus as $menu) {
        if ((string) $menu->permission_name !== 'report-summary') {
            $menu->permission_name = 'report-summary';
            $menu->save();
            $menuUpdated++;
        }
    }

    $pharmacyParent = Menu::query()
        ->whereNull('parent_id')
        ->where('name', 'Pharmacy')
        ->where('permission_name', 'pharmacy-bill-list')
        ->where('status', 'Active')
        ->whereNull('deleted_at')
        ->first();

    if ($pharmacyParent) {
        if ((string) $pharmacyParent->route !== 'backend.pharmacybill.index') {
            $pharmacyParent->route = 'backend.pharmacybill.index';
            $pharmacyParent->save();
            $menuUpdated++;
        }

        $duplicateChildren = Menu::query()
            ->where('parent_id', $pharmacyParent->id)
            ->whereIn('route', [
                'backend.pharmacybill.index',
                'backend.accounts.audit',
                'backend.accounts.vendor-payment.index',
            ])
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->get();

        foreach ($duplicateChildren as $child) {
            $child->status = 'Inactive';
            $child->save();
            $menuDisabled++;
        }
    }

    copy_role_permissions('websetting-add', 'cms-setting', $rolePermissionCopied);
    copy_role_permissions('websetting-add', 'general-setting', $rolePermissionCopied);
    copy_role_permissions('websetting-add', 'prefix-setting', $rolePermissionCopied);
    copy_role_permissions('websetting-add', 'sms-setting', $rolePermissionCopied);
    copy_role_permissions('websetting-add', 'other-setting', $rolePermissionCopied);
    copy_role_permissions('websetting-add', 'b-kash-settings', $rolePermissionCopied);
    copy_role_permissions('activity-log-view', 'activity-logs', $rolePermissionCopied);
    copy_role_permissions('activity-log-view', 'activity-logs-print', $rolePermissionCopied);
    copy_role_permissions('supplier-payment-list', 'vendor-payment-list', $rolePermissionCopied);
    copy_role_permissions('report-management', 'report-summary', $rolePermissionCopied);

    DB::commit();

    try {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    } catch (Throwable $e) {
        // ignore
    }

    echo "Menu updates: {$menuUpdated}" . PHP_EOL;
    echo "Menu disabled: {$menuDisabled}" . PHP_EOL;
    echo "Permission created: {$permissionCreated}" . PHP_EOL;
    echo "Permission updated: {$permissionUpdated}" . PHP_EOL;
    echo "Role-permission copies: {$rolePermissionCopied}" . PHP_EOL;
    echo "Done" . PHP_EOL;
} catch (Throwable $e) {
    DB::rollBack();
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
