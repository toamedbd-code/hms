<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $dashboardPermission = Permission::firstOrCreate(
            ['name' => 'dashboard', 'guard_name' => 'admin'],
            ['parent_id' => null, 'sorting' => 1]
        );

        $permission = Permission::firstOrCreate(
            ['name' => 'dashboard-card-total-income', 'guard_name' => 'admin'],
            ['parent_id' => $dashboardPermission->id, 'sorting' => 1]
        );
        if ((int) ($permission->parent_id ?? 0) !== (int) $dashboardPermission->id) {
            $permission->parent_id = $dashboardPermission->id;
            $permission->save();
        }

        $adminRole = Role::query()
            ->where('name', 'Admin')
            ->where('guard_name', 'admin')
            ->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Retain the permission to avoid removing role assignments.
    }
};
