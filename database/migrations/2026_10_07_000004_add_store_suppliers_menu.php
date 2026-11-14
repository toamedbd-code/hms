<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        $parentMenu = DB::table('menus')
            ->whereNull('parent_id')
            ->where('permission_name', 'store-management')
            ->first();
        if (!$parentMenu) {
            $parentMenuId = DB::table('menus')->insertGetId([
                'name' => 'Store Management',
                'icon' => 'archive',
                'route' => null,
                'module_slug' => 'inventory',
                'description' => null,
                'sorting' => 26,
                'parent_id' => null,
                'permission_name' => 'store-management',
                'status' => 'Active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $parentMenu = (object) ['id' => $parentMenuId];
        }

        $parentPermission = DB::table('permissions')
            ->where('name', 'store-management')
            ->where('guard_name', 'admin')
            ->first();
        if (!$parentPermission) {
            $parentPermissionId = DB::table('permissions')->insertGetId([
                'name' => 'store-management',
                'guard_name' => 'admin',
                'parent_id' => null,
                'sorting' => 26,
                'module_slug' => 'inventory',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $parentPermission = (object) ['id' => $parentPermissionId];
        }

        DB::table('menus')
            ->where('route', 'like', 'backend.stock.%')
            ->update(['parent_id' => $parentMenu->id, 'updated_at' => $now]);

        $menu = DB::table('menus')->where('route', 'backend.storesupplier.index')->first();
        $menuValues = [
            'name' => 'Store Suppliers',
            'icon' => 'users',
            'route' => 'backend.storesupplier.index',
            'module_slug' => 'inventory',
            'description' => 'Suppliers used by Store Management purchase orders',
            'sorting' => 14,
            'parent_id' => $parentMenu->id ?? null,
            'permission_name' => 'store-suppliers-list',
            'status' => 'Active',
            'updated_at' => $now,
        ];

        if ($menu) {
            DB::table('menus')->where('id', $menu->id)->update($menuValues);
        } else {
            $menuValues['created_at'] = $now;
            DB::table('menus')->insert($menuValues);
        }

        $permission = DB::table('permissions')
            ->where('name', 'store-suppliers-list')
            ->where('guard_name', 'admin')
            ->first();
        if (!$permission) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'store-suppliers-list',
                'guard_name' => 'admin',
                'parent_id' => $parentPermission->id ?? null,
                'sorting' => 14,
                'module_slug' => 'inventory',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $permissionId = $permission->id;
            DB::table('permissions')->where('id', $permissionId)->update([
                'parent_id' => $parentPermission->id,
                'sorting' => 14,
                'module_slug' => 'inventory',
                'updated_at' => $now,
            ]);
        }

        $roles = DB::table('roles')
            ->leftJoin('role_has_permissions', 'role_has_permissions.role_id', '=', 'roles.id')
            ->leftJoin('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('roles.guard_name', 'admin')
            ->where(function ($query) {
                $query->where('roles.name', 'Admin')
                    ->orWhereIn('permissions.name', ['stock-report-list', 'stock-report-list-create']);
            })
            ->distinct()
            ->pluck('roles.id');

        $storePermissionNames = [
            'store-management',
            'store-item-setup',
            'stock-management',
            'department-requisitions',
            'grn-receive',
            'store-adjustments',
            'stock-in-out-entry',
            'low-stock-report',
            'stock-movement-report',
            'monthly-closing',
            'reagent-management',
            'department-reagent-stock',
            'store-purchase-orders',
            'store-purchase-payments',
            'store-suppliers-list',
        ];

        DB::table('permissions')
            ->where('guard_name', 'admin')
            ->whereIn('name', array_diff($storePermissionNames, ['store-management']))
            ->update(['parent_id' => $parentPermission->id, 'module_slug' => 'inventory', 'updated_at' => $now]);

        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'admin')
            ->whereIn('name', $storePermissionNames)
            ->pluck('id');

        foreach ($roles as $roleId) {
            foreach ($permissionIds as $rolePermissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $rolePermissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permission = DB::table('permissions')
            ->where('name', 'store-suppliers-list')
            ->where('guard_name', 'admin')
            ->first();
        if ($permission) {
            DB::table('role_has_permissions')->where('permission_id', $permission->id)->delete();
            DB::table('permissions')->where('id', $permission->id)->delete();
        }

        DB::table('menus')->where('route', 'backend.storesupplier.index')->delete();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
