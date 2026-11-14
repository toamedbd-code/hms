<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LegacyPermissionAliasMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_roles_keep_access_to_report_settings_and_item_charge(): void
    {
        $role = Role::query()->create([
            'name' => 'Legacy Settings and Item Charge',
            'guard_name' => 'admin',
        ]);

        foreach ([
            'websetting-add',
            'report-settings',
            'item-charge',
            'hospital-test',
            'charge-list',
        ] as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'admin',
            ]);
        }

        $role->givePermissionTo(['websetting-add', 'item-charge', 'hospital-test']);

        $migration = require database_path(
            'migrations/2026_11_13_000001_sync_legacy_report_and_item_charge_permissions.php'
        );
        $migration->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionIds = Permission::query()
            ->whereIn('name', ['report-settings', 'charge-list'])
            ->where('guard_name', 'admin')
            ->pluck('id');

        $this->assertSame(
            2,
            DB::table('role_has_permissions')
                ->where('role_id', $role->id)
                ->whereIn('permission_id', $permissionIds)
                ->count()
        );

        $this->assertTrue($role->fresh()->hasPermissionTo('report-settings', 'admin'));
        $this->assertTrue($role->fresh()->hasPermissionTo('charge-list', 'admin'));
    }
}
