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

        $categories = DB::table('testcategories')
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->get(['name']);

        foreach ($categories as $category) {
            $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($category->name))), '-');
            if ($slug === '') {
                continue;
            }

            $permissionName = 'dashboard-card-' . $slug . '-income';
            $permissionId = DB::table('permissions')
                ->where('name', $permissionName)
                ->where('guard_name', 'admin')
                ->value('id');

            if ($permissionId) {
                DB::table('permissions')
                    ->where('id', $permissionId)
                    ->update([
                        'parent_id' => $dashboardPermissionId,
                        'sorting' => 1,
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

        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Retain these permissions and role assignments to preserve custom role configuration.
    }
};
