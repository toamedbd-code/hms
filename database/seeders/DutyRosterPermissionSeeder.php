<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class DutyRosterPermissionSeeder extends Seeder
{
    public function run()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::where('name', 'Admin')->where('guard_name', 'admin')->first();

        // Create parent permission
        $parent = Permission::findOrCreate('dutyroaster-management', 'admin');

        // Base list permission
        $list = Permission::findOrCreate('dutyroaster-list', 'admin');
        $list->parent_id = $parent->id;
        $list->save();

        // Action permissions
        $actions = [
            'dutyroaster-add',
            'dutyroaster-edit',
            'dutyroaster-delete',
            'dutyroaster-status',
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
