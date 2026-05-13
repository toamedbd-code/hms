<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddSitePurchaseMenuAndPermission extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        $parentMenu = DB::table('menus')
            ->whereNull('parent_id')
            ->where('permission_name', 'inventory-management')
            ->first();

        $parentMenuId = $parentMenu->id ?? null;

        $existingMenu = DB::table('menus')
            ->where('route', 'backend.sitepurchase.index')
            ->first();

        if (!$existingMenu) {
            DB::table('menus')->insert([
                'name' => 'Site Purchase',
                'icon' => 'shopping-cart',
                'route' => 'backend.sitepurchase.index',
                'module_slug' => 'inventory',
                'description' => 'All site procurement and purchase tracking',
                'sorting' => 2,
                'parent_id' => $parentMenuId,
                'permission_name' => 'site-purchase-list',
                'status' => 'Active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $parentPermission = DB::table('permissions')
            ->where('name', 'inventory-management')
            ->where('guard_name', 'admin')
            ->first();

        $parentPermissionId = $parentPermission->id ?? null;

        $permissionNames = [
            'site-purchase-list',
            'site-purchase-list-create',
            'site-purchase-list-edit',
            'site-purchase-list-delete',
        ];

        foreach ($permissionNames as $permissionName) {
            $existingPermission = DB::table('permissions')
                ->where('name', $permissionName)
                ->where('guard_name', 'admin')
                ->first();

            if (!$existingPermission) {
                DB::table('permissions')->insert([
                    'name' => $permissionName,
                    'guard_name' => 'admin',
                    'parent_id' => $parentPermissionId,
                    'sorting' => 2,
                    'module_slug' => 'inventory',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $existingPermission = DB::table('permissions')
                    ->where('name', $permissionName)
                    ->where('guard_name', 'admin')
                    ->first();
            }

            $adminRole = DB::table('roles')
                ->where('name', 'Admin')
                ->where('guard_name', 'admin')
                ->first();

            if ($adminRole && $existingPermission) {
                $existsInRole = DB::table('role_has_permissions')
                    ->where('permission_id', $existingPermission->id)
                    ->where('role_id', $adminRole->id)
                    ->exists();

                if (!$existsInRole) {
                    DB::table('role_has_permissions')->insert([
                        'permission_id' => $existingPermission->id,
                        'role_id' => $adminRole->id,
                    ]);
                }
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissionNames = [
            'site-purchase-list-delete',
            'site-purchase-list-edit',
            'site-purchase-list-create',
            'site-purchase-list',
        ];

        foreach ($permissionNames as $permissionName) {
            $permission = DB::table('permissions')
                ->where('name', $permissionName)
                ->where('guard_name', 'admin')
                ->first();

            if ($permission) {
                DB::table('role_has_permissions')->where('permission_id', $permission->id)->delete();
                DB::table('permissions')->where('id', $permission->id)->delete();
            }
        }

        DB::table('menus')->where('route', 'backend.sitepurchase.index')->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
