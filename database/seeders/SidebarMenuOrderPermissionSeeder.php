<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class SidebarMenuOrderPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $webSettingPermission = Permission::firstOrCreate([
            'name' => 'websetting-add',
            'guard_name' => 'admin',
        ]);

        $sidebarPermission = Permission::firstOrCreate([
            'name' => 'sidebar-setting',
            'guard_name' => 'admin',
        ]);

        if (!$sidebarPermission->parent_id) {
            $sidebarPermission->parent_id = $webSettingPermission->id;
            $sidebarPermission->save();
        }

        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // ignore cache-reset errors
        }

        $roles = Role::query()
            ->where('guard_name', 'admin')
            ->whereIn('name', ['Admin', 'developer'])
            ->get();

        foreach ($roles as $role) {
            $role->givePermissionTo($sidebarPermission);
        }
    }
}
