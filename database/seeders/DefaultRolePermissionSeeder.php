<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DefaultRolePermissionSeeder extends Seeder
{
    public function run()
    {
        // Ensure permission cache cleared
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles (guard 'admin' to match Permission guard)
        // Each role is created with NO default permissions
        // Permissions must be assigned manually via the admin panel
        $roles = ['reception','radiology','pathology','ultrasound','xray','administration','super-admin'];

        foreach ($roles as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'admin']);
        }

        // Only Admin (administration), Developer, and Super-admin get all permissions by default
        // This allows them to manage other roles and permissions
        $all = Permission::pluck('name')->toArray();

        $adminRole = Role::where('name', 'administration')->where('guard_name','admin')->first();
        if ($adminRole) {
            $adminRole->syncPermissions($all);
        }

        $developerRole = Role::where('name', 'developer')->where('guard_name','admin')->first();
        if ($developerRole) {
            $developerRole->syncPermissions($all);
        }

        $super = Role::where('name', 'super-admin')->where('guard_name','admin')->first();
        if ($super) {
            $super->syncPermissions($all);
        }

        // All other roles (reception, radiology, pathology, ultrasound, xray) have NO default permissions
        // They must have permissions assigned manually via the admin panel

        // Refresh cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
