<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $dashboardPermissionId = DB::table('permissions')
            ->where('name', 'dashboard')
            ->where('guard_name', 'admin')
            ->value('id');

        if (!$dashboardPermissionId) {
            $dashboardPermissionId = DB::table('permissions')->insertGetId([
                'name' => 'dashboard',
                'guard_name' => 'admin',
                'parent_id' => null,
                'sorting' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $roleIds = DB::table('roles')
            ->where('guard_name', 'admin')
            ->whereIn('name', ['Admin', 'developer'])
            ->pluck('id');

        $mainCategories = DB::table('main_categories')
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->get(['name']);

        foreach ($mainCategories as $category) {
            $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($category->name))), '-');
            if ($slug === '') {
                continue;
            }

            $permissionName = 'dashboard-main-category-' . $slug . '-income';
            $permissionId = DB::table('permissions')
                ->where('name', $permissionName)
                ->where('guard_name', 'admin')
                ->value('id');

            if ($permissionId) {
                DB::table('permissions')
                    ->where('id', $permissionId)
                    ->update([
                        'parent_id' => $dashboardPermissionId,
                        'updated_at' => now(),
                    ]);
            } else {
                $permissionId = DB::table('permissions')->insertGetId([
                    'name' => $permissionName,
                    'guard_name' => 'admin',
                    'parent_id' => $dashboardPermissionId,
                    'sorting' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'admin')
            ->where('name', 'like', 'dashboard-main-category-%-income')
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
