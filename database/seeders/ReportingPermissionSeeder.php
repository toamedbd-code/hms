<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ReportingPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Ensure parent 'reporting' permission exists
        $parent = Permission::firstOrCreate(
            ['name' => 'reporting', 'guard_name' => 'admin'],
            ['parent_id' => null, 'sorting' => 1]
        );

        // Standard child action permissions for reporting module
        $children = [
            'reporting-status',
            'reporting-create',
            'reporting-edit',
            'reporting-delete',
        ];

        foreach ($children as $childName) {
            Permission::firstOrCreate(
                ['name' => $childName, 'guard_name' => 'admin'],
                ['parent_id' => $parent->id, 'sorting' => 1]
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Assign all new permissions to Admin role
        $adminRole = Role::where('name', 'Admin')->where('guard_name', 'admin')->first();
        if ($adminRole) {
            $allPermissions = Permission::pluck('name')->toArray();
            $adminRole->syncPermissions($allPermissions);
        }

        $this->command->info('Reporting permissions added successfully.');
    }
}
