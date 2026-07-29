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
        $roles = ['reception','radiology','pathology','ultrasound','xray','administration','super-admin'];

        foreach ($roles as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'admin']);
        }

        // Assign sensible defaults (only existing permissions will be synced)
        $reception = Role::where('name', 'reception')->where('guard_name','admin')->first();
        if ($reception) {
            $receptionPerms = Permission::whereIn('name', [
                'frontoffice-list', 'opd-patient-list', 'appoinment-list', 'billing', 'cash-counter', 'pharmacy-bill-list'
            ])->pluck('name')->toArray();
            $reception->syncPermissions($receptionPerms);
        }

        $radiology = Role::where('name', 'radiology')->where('guard_name','admin')->first();
        if ($radiology) {
            $radiologyPerms = Permission::where('name', 'like', '%radiology%')->pluck('name')->toArray();
            $radiology->syncPermissions($radiologyPerms);
        }

        $pathology = Role::where('name', 'pathology')->where('guard_name','admin')->first();
        if ($pathology) {
            $pathologyPerms = Permission::where('name', 'like', '%pathology%')->pluck('name')->toArray();
            $pathology->syncPermissions($pathologyPerms);
        }

        $ultrasound = Role::where('name', 'ultrasound')->where('guard_name','admin')->first();
        if ($ultrasound) {
            $ultrasoundPerms = Permission::where('name', 'like', '%ultrasound%')->pluck('name')->toArray();
            $ultrasound->syncPermissions($ultrasoundPerms);
        }

        $xray = Role::where('name', 'xray')->where('guard_name','admin')->first();
        if ($xray) {
            $xrayPerms = Permission::where('name', 'like', '%xray%')->pluck('name')->toArray();
            $xray->syncPermissions($xrayPerms);
        }

        // Administration and Super-admin -> all permissions
        $all = Permission::pluck('name')->toArray();

        $adminRole = Role::where('name', 'administration')->where('guard_name','admin')->first();
        if ($adminRole) {
            $adminRole->syncPermissions($all);
        }

        $super = Role::where('name', 'super-admin')->where('guard_name','admin')->first();
        if ($super) {
            $super->syncPermissions($all);
        }

        // Refresh cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
