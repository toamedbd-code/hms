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
        $parentPermission = DB::table('permissions')
            ->where('name', 'store-management')
            ->where('guard_name', 'admin')
            ->first();

        $menus = [
            [
                'name' => 'Purchase Orders',
                'icon' => 'shopping-cart',
                'route' => 'backend.stock.purchase-orders',
                'permission_name' => 'store-purchase-orders',
                'sorting' => 12,
            ],
            [
                'name' => 'Purchase Payments',
                'icon' => 'credit-card',
                'route' => 'backend.stock.purchase-payments',
                'permission_name' => 'store-purchase-payments',
                'sorting' => 13,
            ],
        ];

        foreach ($menus as $menu) {
            $existingMenu = DB::table('menus')->where('route', $menu['route'])->first();
            $menuValues = [
                'name' => $menu['name'],
                'icon' => $menu['icon'],
                'route' => $menu['route'],
                'module_slug' => 'inventory',
                'description' => null,
                'sorting' => $menu['sorting'],
                'parent_id' => $parentMenu->id ?? null,
                'permission_name' => $menu['permission_name'],
                'status' => 'Active',
                'updated_at' => $now,
            ];

            if ($existingMenu) {
                DB::table('menus')->where('id', $existingMenu->id)->update($menuValues);
            } else {
                $menuValues['created_at'] = $now;
                DB::table('menus')->insert($menuValues);
            }

            $permission = DB::table('permissions')
                ->where('name', $menu['permission_name'])
                ->where('guard_name', 'admin')
                ->first();

            if (!$permission) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'name' => $menu['permission_name'],
                    'guard_name' => 'admin',
                    'parent_id' => $parentPermission->id ?? null,
                    'sorting' => $menu['sorting'],
                    'module_slug' => 'inventory',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $permissionId = $permission->id;
            }

            $eligibleRoles = DB::table('roles')
                ->leftJoin('role_has_permissions', 'role_has_permissions.role_id', '=', 'roles.id')
                ->leftJoin('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('roles.guard_name', 'admin')
                ->where(function ($query) {
                    $query->where('roles.name', 'Admin')
                        ->orWhereIn('permissions.name', ['stock-report-list', 'stock-report-list-create', 'pharmacy-management']);
                })
                ->distinct()
                ->pluck('roles.id');
            foreach ($eligibleRoles as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionNames = ['store-purchase-orders', 'store-purchase-payments'];
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

        DB::table('menus')->whereIn('route', [
            'backend.stock.purchase-orders',
            'backend.stock.purchase-payments',
        ])->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
