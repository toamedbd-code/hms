<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionFixSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::where('name', 'Admin')
            ->where('guard_name', 'admin')
            ->first();

        if (!$role) {
            return;
        }

        $parentPermission = Permission::findOrCreate('pharmacy-management', 'admin');
        $hrHubParent = Permission::findOrCreate('hr-hub-management', 'admin');

        $permissionNames = [
            'pharmacy-bill-list',
            'pharmacy-bill-create',
            'pharmacy-bill-edit',
            'pharmacy-bill-status',
            'pharmacy-bill-invoice',
        ];

        $extraPermissions = [
            'sample-collection',
            'reporting',
            'report-delivery',
            'report-settings',
            'doctor-portal',
            'website-inbox',
            'attendance-settings',
            'face-attendance',
            'staff-attendance-list',
            'dutyroaster-list',
            'salary-sheet',
            'salary-sheet-pay',
        ];

        $opdParent = Permission::where('name', 'opd-patient-list')->first();
        $appointmentParent = Permission::where('name', 'appoinment-list')->first();

        if ($opdParent) {
            $doctorPortalPermission = Permission::findOrCreate('doctor-portal', 'admin');
            $doctorPortalPermission->parent_id = $opdParent->id;
            $doctorPortalPermission->save();
        }

        if ($appointmentParent) {
            $websiteInboxPermission = Permission::findOrCreate('website-inbox', 'admin');
            $websiteInboxPermission->parent_id = $appointmentParent->id;
            $websiteInboxPermission->save();
        }

        $listPermission = Permission::findOrCreate('pharmacy-bill-list', 'admin');
        $listPermission->parent_id = $parentPermission->id;
        $listPermission->save();

        foreach ($permissionNames as $name) {
            Permission::findOrCreate($name, 'admin');
        }

        foreach ($extraPermissions as $name) {
            Permission::findOrCreate($name, 'admin');
        }

        $hrHubPermissions = [
            'attendance-settings',
            'face-attendance',
            'staff-attendance-list',
            'dutyroaster-list',
            'salary-sheet',
            'salary-sheet-pay',
        ];

        $hrPermissions = Permission::whereIn('name', $hrHubPermissions)->get();
        foreach ($hrPermissions as $permission) {
            $permission->parent_id = $hrHubParent->id;
            $permission->save();
        }

        $actionPermissions = Permission::whereIn('name', [
            'pharmacy-bill-create',
            'pharmacy-bill-edit',
            'pharmacy-bill-status',
            'pharmacy-bill-invoice',
        ])->get();

        foreach ($actionPermissions as $permission) {
            $permission->parent_id = $listPermission->id;
            $permission->save();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role->givePermissionTo(array_merge([$hrHubParent->name], $permissionNames, $extraPermissions));
    }
}
