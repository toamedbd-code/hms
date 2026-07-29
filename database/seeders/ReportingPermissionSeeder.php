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

        // Department-specific reporting permissions
        $departmentPermissions = [
            'ultrasound-reporting',
            'xray-reporting',
            // Pathology reports were missing; add pathology-reporting
            'pathology-reporting',
        ];
        foreach ($children as $childName) {
            Permission::firstOrCreate(
                ['name' => $childName, 'guard_name' => 'admin'],
                ['parent_id' => $parent->id, 'sorting' => 1]
            );
        }

        // Create department-specific reporting permissions under the reporting parent
        foreach ($departmentPermissions as $permName) {
            Permission::firstOrCreate(
                ['name' => $permName, 'guard_name' => 'admin'],
                ['parent_id' => $parent->id, 'sorting' => 1]
            );
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Assign all new permissions to core roles (Admin + developer)
        $roles = Role::query()->whereIn('name', ['Admin', 'developer'])->where('guard_name', 'admin')->get();
        $allPermissions = Permission::pluck('name')->toArray();
        foreach ($roles as $role) {
            try {
                $role->syncPermissions($allPermissions);
            } catch (\Throwable $e) {
                // ignore assignment errors to keep seeder idempotent
            }
        }

        $this->command->info('Reporting permissions added successfully.');
    }
}
