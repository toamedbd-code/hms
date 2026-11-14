<?php

use App\Models\Permission;
use App\Models\TestCategory;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $dashboardPermission = Permission::where('name', 'dashboard')
            ->where('guard_name', 'admin')
            ->first();

        if (!$dashboardPermission) {
            return;
        }

        $adminRole = Role::where('name', 'Admin')->where('guard_name', 'admin')->first();

        TestCategory::query()
            ->whereNull('parent_id')
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->get(['name'])
            ->each(function ($category) use ($dashboardPermission, $adminRole) {
                $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($category->name))), '-');
                if ($slug === '') {
                    return;
                }

                $permission = Permission::firstOrCreate(
                    ['name' => 'dashboard-card-' . $slug . '-income', 'guard_name' => 'admin'],
                    ['parent_id' => $dashboardPermission->id, 'sorting' => 1]
                );

                if ($adminRole) {
                    $adminRole->givePermissionTo($permission);
                }
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Category dashboard permissions are retained to avoid removing custom role assignments.
    }
};