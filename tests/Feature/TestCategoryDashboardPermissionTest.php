<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Test;
use App\Services\DashboardService;
use App\Services\PermissionService;
use App\Services\TestCategoryService;
use Database\Seeders\DashboardCategoryPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TestCategoryDashboardPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_created_category_permission_is_listed_without_automatic_role_assignment(): void
    {
        $dashboardPermission = Permission::query()->firstOrCreate([
            'name' => 'dashboard',
            'guard_name' => 'admin',
        ]);
        $orphanedCategoryPermission = Permission::query()->firstOrCreate([
            'name' => 'dashboard-card-ultrasound-income',
            'guard_name' => 'admin',
        ]);
        $orphanedCategoryPermission->parent_id = null;
        $orphanedCategoryPermission->save();
        $developerRole = Role::query()->firstOrCreate([
            'name' => 'developer',
            'guard_name' => 'admin',
        ], [
            'is_private' => true,
        ]);
        $adminRole = Role::query()->firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'admin',
        ], [
            'is_private' => true,
        ]);

        $category = app(TestCategoryService::class)->create([
            'name' => 'Ultrasound',
            'status' => 'Active',
        ]);

        $syncedPermission = Permission::query()
            ->where('name', 'dashboard-card-ultrasound-income')
            ->where('guard_name', 'admin')
            ->firstOrFail();

        $this->assertSame($orphanedCategoryPermission->id, $syncedPermission->id);
        $this->assertSame($dashboardPermission->id, $syncedPermission->parent_id);
        $this->assertFalse($developerRole->fresh()->hasPermissionTo($syncedPermission->name));
        $this->assertFalse($adminRole->fresh()->hasPermissionTo($syncedPermission->name));

        $dashboard = app(PermissionService::class)->listWithAllChild()->firstWhere('name', 'dashboard');
        $this->assertContains(
            $syncedPermission->name,
            collect($dashboard->child)->pluck('name')->all()
        );
        $this->assertSame('Ultrasound', $category->name);
    }

    public function test_deleted_category_permissions_are_removed_and_recreated_when_category_is_added_again(): void
    {
        $role = Role::query()->firstOrCreate([
            'name' => 'Category manager',
            'guard_name' => 'admin',
        ]);
        $category = app(TestCategoryService::class)->create([
            'name' => 'Lifecycle Test Category',
            'status' => 'Active',
        ]);
        $permission = Permission::query()
            ->where('name', 'dashboard-card-lifecycle-test-category-income')
            ->where('guard_name', 'admin')
            ->firstOrFail();
        $role->givePermissionTo($permission->name);

        $legacyOrphan = Permission::query()->create([
            'name' => 'dashboard-card-deleted-category-income',
            'guard_name' => 'admin',
            'parent_id' => $permission->parent_id,
        ]);
        $role->givePermissionTo($legacyOrphan->name);

        $this->assertTrue(app(TestCategoryService::class)->delete($category->id));

        $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
        $this->assertDatabaseMissing('role_has_permissions', ['permission_id' => $permission->id]);
        $this->assertDatabaseMissing('permissions', ['id' => $legacyOrphan->id]);
        $dashboard = app(PermissionService::class)->listWithAllChild()->firstWhere('name', 'dashboard');
        $this->assertNotContains(
            'dashboard-card-lifecycle-test-category-income',
            collect($dashboard->child)->pluck('name')->all()
        );

        $recreatedCategory = app(TestCategoryService::class)->create([
            'name' => 'Lifecycle Test Category',
            'status' => 'Active',
        ]);
        $recreatedPermission = Permission::query()
            ->where('name', 'dashboard-card-lifecycle-test-category-income')
            ->where('guard_name', 'admin')
            ->firstOrFail();
        $dashboard = app(PermissionService::class)->listWithAllChild()->firstWhere('name', 'dashboard');

        $this->assertSame('Lifecycle Test Category', $recreatedCategory->name);
        $this->assertNotSame($permission->id, $recreatedPermission->id);
        $this->assertContains(
            'dashboard-card-lifecycle-test-category-income',
            collect($dashboard->child)->pluck('name')->all()
        );
    }

    public function test_migration_backfills_dashboard_permissions_for_existing_item_categories(): void
    {
        $dashboardPermission = Permission::query()->firstOrCreate([
            'name' => 'dashboard',
            'guard_name' => 'admin',
        ]);
        $adminRole = Role::query()->firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'admin',
        ]);
        $developerRole = Role::query()->firstOrCreate([
            'name' => 'developer',
            'guard_name' => 'admin',
        ]);
        $orphanedPermission = Permission::query()->firstOrCreate([
            'name' => 'dashboard-card-ultrasound-income',
            'guard_name' => 'admin',
        ]);
        $orphanedPermission->parent_id = null;
        $orphanedPermission->save();
        app(\App\Models\TestCategory::class)->newQuery()->create([
            'name' => 'Ultrasound',
            'status' => 'Active',
        ]);

        $migration = require database_path('migrations/2026_10_16_000002_sync_dashboard_item_category_permissions.php');
        $migration->up();

        $syncedPermission = Permission::query()
            ->where('name', 'dashboard-card-ultrasound-income')
            ->where('guard_name', 'admin')
            ->firstOrFail();

        $this->assertSame($dashboardPermission->id, $syncedPermission->parent_id);
        $this->assertFalse($adminRole->fresh()->hasPermissionTo($syncedPermission->name));
        $this->assertFalse($developerRole->fresh()->hasPermissionTo($syncedPermission->name));
    }

    public function test_dashboard_category_seeder_lists_permissions_without_granting_them_to_default_roles(): void
    {
        $dashboardPermission = Permission::query()->firstOrCreate([
            'name' => 'dashboard',
            'guard_name' => 'admin',
        ]);
        $adminRole = Role::query()->firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'admin',
        ]);
        $developerRole = Role::query()->firstOrCreate([
            'name' => 'developer',
            'guard_name' => 'admin',
        ]);
        app(\App\Models\TestCategory::class)->newQuery()->create([
            'name' => 'Ultrasound',
            'status' => 'Active',
        ]);

        app(DashboardCategoryPermissionSeeder::class)->run();

        $categoryPermission = Permission::query()
            ->where('name', 'dashboard-card-ultrasound-income')
            ->where('guard_name', 'admin')
            ->firstOrFail();
        $this->assertSame($dashboardPermission->id, $categoryPermission->parent_id);
        $this->assertFalse($adminRole->fresh()->hasPermissionTo($categoryPermission->name));
        $this->assertFalse($developerRole->fresh()->hasPermissionTo($categoryPermission->name));
    }

    public function test_automatic_category_grants_are_removed_from_admin_and_developer_roles(): void
    {
        $category = app(\App\Models\TestCategory::class)->newQuery()->create([
            'name' => 'Ultrasound',
            'status' => 'Active',
        ]);
        $permission = Permission::query()->firstOrCreate([
            'name' => 'dashboard-card-ultrasound-income',
            'guard_name' => 'admin',
        ]);
        $roles = collect(['Admin', 'developer'])->map(function (string $name) use ($permission) {
            $role = Role::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'admin',
            ]);
            $role->givePermissionTo($permission->name);

            return $role;
        });

        $migration = require database_path('migrations/2026_10_16_000003_revoke_automatic_item_category_dashboard_permissions.php');
        $migration->up();

        foreach ($roles as $role) {
            $this->assertFalse($role->fresh()->hasPermissionTo($permission->name));
        }
        $this->assertSame('Ultrasound', $category->name);
    }

    public function test_new_category_appears_as_a_dashboard_card_and_chart_income_item(): void
    {
        $dashboardPermission = Permission::query()->firstOrCreate([
            'name' => 'dashboard',
            'guard_name' => 'admin',
        ]);
        $role = Role::query()->firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'admin',
        ], [
            'is_private' => true,
        ]);
        $chartPermissionNames = [
            $dashboardPermission->name,
            Permission::query()->firstOrCreate([
                'name' => 'dashboard-chart-income-by-department',
                'guard_name' => 'admin',
            ], [
                'parent_id' => $dashboardPermission->id,
            ])->name,
            Permission::query()->firstOrCreate([
                'name' => 'dashboard-chart-income-distribution',
                'guard_name' => 'admin',
            ], [
                'parent_id' => $dashboardPermission->id,
            ])->name,
        ];
        $role->givePermissionTo($chartPermissionNames);
        $category = app(TestCategoryService::class)->create([
            'name' => 'Ultrasound',
            'status' => 'Active',
        ]);
        $test = Test::query()->create([
            'category_type' => 'Radiology',
            'test_name' => 'Ultrasound Scan',
            'test_category_id' => $category->id,
            'standard_charge' => 100,
            'amount' => 100,
            'status' => 'Active',
        ]);
        $admin = Admin::query()->create([
            'first_name' => 'Dashboard',
            'last_name' => 'Developer',
            'email' => 'category-dashboard-developer@example.test',
            'phone' => '01710000771',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $role->id,
        ]);
        $admin->assignRole($role);

        $today = now();
        $service = \Mockery::mock(DashboardService::class)->makePartial();
        $service->shouldReceive('resolveDashboardFilter')->andReturn([
            'type' => 'daily',
            'from' => $today->toDateString(),
            'to' => $today->toDateString(),
            'dbRange' => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            'appRange' => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
        ]);
        $service->shouldReceive('countActiveUser')->andReturn(0);
        $service->shouldReceive('countInActiveUser')->andReturn(0);
        $service->shouldReceive('countCategoryIncome')
            ->with('Ultrasound', \Mockery::any(), \Mockery::on(
                fn ($categories) => in_array('Ultrasound', $categories, true)
            ))
            ->andReturn(80);
        $service->shouldReceive('countDiagnosticIncome')
            ->with('Radiology', [$test->id], \Mockery::any())
            ->andReturn(80);
        $service->shouldReceive('getPathologyIncomeSummary')
            ->andReturn(['total' => 0, 'discount' => 0, 'net' => 0]);
        $service->shouldReceive('countNetIncome')->andReturn(80);
        $service->shouldReceive('countOpdIncome')->andReturn(0);
        $service->shouldReceive('countIpdIncome')->andReturn(0);
        $service->shouldReceive('countPharmacyIncome')->andReturn(0);
        $service->shouldReceive('countBloodBankIncome')->andReturn(0);
        $service->shouldReceive('countDisposableIncome')->andReturn(0);
        $service->shouldReceive('countPendingIncome')->andReturn(0);
        $service->shouldReceive('countTotalIncome')->andReturn(80);
        $service->shouldReceive('countTotalDiscount')->andReturn(0);
        $service->shouldReceive('countExpense')->andReturn(0);
        $service->shouldReceive('countRefunds')->andReturn(0);
        $service->shouldReceive('countRadiologyIncome')->andReturn(0);
        $this->app->instance(DashboardService::class, $service);

        $this->actingAs($admin, 'admin')
            ->get(route('backend.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dashboardCardPermissions.custom-category-ultrasound', false)
                ->where('dashboardCardPermissions.chartIncomeByDepartment', true)
                ->where('dashboardCardPermissions.chartIncomeDistribution', true)
                ->where('dashboardData.categoryIncomes', [])
                ->where('dashboardData.chartIncomeItems', fn ($items) => !collect($items)
                    ->contains(fn ($item) => $item['name'] === 'Ultrasound Income'))
            );

        $role->givePermissionTo('dashboard-card-ultrasound-income');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->get(route('backend.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dashboardCardPermissions.custom-category-ultrasound', true)
                ->where('dashboardData.categoryIncomes.0.name', 'Ultrasound')
                ->where('dashboardData.categoryIncomes.0.amount', 80)
                ->where('dashboardData.chartIncomeItems', fn ($items) => collect($items)
                    ->contains(fn ($item) => $item['name'] === 'Ultrasound Income' && $item['amount'] === 80))
            );
    }
}
