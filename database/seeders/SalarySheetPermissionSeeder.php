<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SalarySheetPermissionSeeder extends Seeder
{
    public function run()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::where('name', 'Admin')->where('guard_name', 'admin')->first();

        // Parent permission for salary management
        $parent = Permission::findOrCreate('salary-management', 'admin');

        // Salary sheet base permission
        $list = Permission::findOrCreate('salary-sheet', 'admin');
        $list->parent_id = $parent->id;
        $list->save();

        // Specific action permissions
        $actions = [
            'salary-sheet-pay',
        ];

        foreach ($actions as $name) {
            $p = Permission::findOrCreate($name, 'admin');
            $p->parent_id = $list->id;
            $p->save();
        }

        if ($role) {
            $role->givePermissionTo(array_merge([$parent->name, $list->name], $actions));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
