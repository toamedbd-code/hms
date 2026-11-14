<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminDetail;
use App\Models\Billing;
use App\Models\BillItem;
use App\Models\Department;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\Payment;
use App\Models\PharmacyBill;
use App\Models\ProductReturn;
use App\Models\Test;
use App\Models\TestCategory;
use App\Services\DashboardService;
use App\Services\TestCategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardPermissionVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_shared_with_main_category_is_listed_and_its_dashboard_card_is_shown(): void
    {
        $mainCategoryId = DB::table('main_categories')->insertGetId([
            'name' => 'Ultrasound',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $category = TestCategory::query()->create(['name' => 'Ultrasound', 'status' => 'Active']);
        $test = Test::query()->create([
            'category_type' => 'Radiology',
            'test_name' => 'Ultrasound Test',
            'test_category_id' => $category->id,
            'main_category_id' => $mainCategoryId,
            'standard_charge' => 100,
            'amount' => 100,
            'status' => 'Active',
        ]);

        $role = Role::query()->create([
            'name' => 'ultrasound-dashboard-role',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);
        $role->givePermissionTo([
            Permission::query()->create([
                'name' => 'test-category-list',
                'guard_name' => 'admin',
            ]),
            Permission::query()->firstOrCreate([
                'name' => 'dashboard-card-ultrasound-income',
                'guard_name' => 'admin',
            ]),
        ]);

        $admin = Admin::query()->create([
            'first_name' => 'Ultrasound',
            'last_name' => 'User',
            'email' => 'ultrasound-dashboard@example.test',
            'phone' => '01710000021',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $role->id,
        ]);
        $admin->assignRole($role);

        $today = now();
        $service = \Mockery::mock(DashboardService::class)->makePartial();
        $service->shouldReceive('addDiscountRoundingToDiagnosticIncomes')
            ->andReturnUsing(fn ($pathology, $radiology) => [$pathology, $radiology]);
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
            ->with('Ultrasound', \Mockery::any(), ['Ultrasound'])
            ->andReturn(80);
        $service->shouldReceive('countDiagnosticIncome')
            ->with('Radiology', \Mockery::any(), \Mockery::any())
            ->andReturn(80);
        $this->app->instance(DashboardService::class, $service);

        $this->actingAs($admin, 'admin')
            ->get(route('backend.pathologycategory.index', ['numOfData' => 500]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('datas.data', fn ($categories) => collect($categories)
                    ->contains(fn ($category) => $category['category'] === 'Ultrasound'))
            );

        $this->get(route('backend.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dashboardCardPermissions.custom-category-ultrasound', true)
                ->where('dashboardCardPermissions.main-category-ultrasound', false)
                ->where('dashboardCardPermissions.radiologyIncome', false)
                ->where('dashboardData.categoryIncomes.0.name', 'Ultrasound')
                ->where('dashboardData.categoryIncomes.0.amount', 80)
                ->missing('dashboardData.radiologyIncome')
            );
    }

    public function test_dashboard_handles_cyclic_test_category_parent_links(): void
    {
        $firstCategory = TestCategory::query()->create(['name' => 'Cycle A', 'status' => 'Active']);
        $secondCategory = TestCategory::query()->create(['name' => 'Cycle B', 'status' => 'Active']);
        DB::table('testcategories')->where('id', $firstCategory->id)->update(['parent_id' => $secondCategory->id]);
        DB::table('testcategories')->where('id', $secondCategory->id)->update(['parent_id' => $firstCategory->id]);
        Test::query()->create([
            'category_type' => 'Pathology',
            'test_name' => 'Cycle Test',
            'test_category_id' => $firstCategory->id,
            'standard_charge' => 100,
            'amount' => 100,
            'status' => 'Active',
        ]);

        $role = Role::query()->create([
            'name' => 'cyclic-category-dashboard-role',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);
        $role->givePermissionTo(Permission::query()->firstOrCreate([
            'name' => 'dashboard',
            'guard_name' => 'admin',
        ]));

        $admin = Admin::query()->create([
            'first_name' => 'Cyclic Category',
            'last_name' => 'User',
            'email' => 'cyclic-category-dashboard@example.test',
            'phone' => '01710000024',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $role->id,
        ]);
        $admin->assignRole($role);

        $this->actingAs($admin, 'admin')
            ->get(route('backend.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dashboardData.categoryIncomes', [])
            );
    }

    public function test_main_category_card_takes_precedence_over_a_same_named_item_category_card(): void
    {
        $radiologyMainCategoryId = DB::table('main_categories')->insertGetId([
            'name' => 'Ultrasound',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $pathologyMainCategoryId = DB::table('main_categories')->insertGetId([
            'name' => 'Biochemistry',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $radiologyCategory = TestCategory::query()->create(['name' => 'Ultrasound', 'status' => 'Active']);
        $pathologyCategory = TestCategory::query()->create(['name' => 'Biochemistry', 'status' => 'Active']);
        $radiologyTest = Test::query()->create([
            'category_type' => 'Radiology',
            'test_name' => 'Ultrasound Scan',
            'test_category_id' => $radiologyCategory->id,
            'main_category_id' => null,
            'standard_charge' => 100,
            'amount' => 100,
            'status' => 'Active',
        ]);
        $pathologyTest = Test::query()->create([
            'category_type' => 'Pathology',
            'test_name' => 'Biochemistry Test',
            'test_category_id' => $pathologyCategory->id,
            'main_category_id' => $pathologyMainCategoryId,
            'standard_charge' => 100,
            'amount' => 100,
            'status' => 'Active',
        ]);

        $role = Role::query()->create([
            'name' => 'ultrasound-main-category-role',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);
        $role->givePermissionTo([
            Permission::query()->firstOrCreate(['name' => 'dashboard', 'guard_name' => 'admin']),
            Permission::query()->firstOrCreate(['name' => 'dashboard-card-ultrasound-income', 'guard_name' => 'admin']),
            Permission::query()->firstOrCreate(['name' => 'dashboard-card-pathology-income', 'guard_name' => 'admin']),
            Permission::query()->firstOrCreate(['name' => 'dashboard-main-category-ultrasound-income', 'guard_name' => 'admin']),
            Permission::query()->firstOrCreate(['name' => 'dashboard-main-category-biochemistry-income', 'guard_name' => 'admin']),
            Permission::query()->firstOrCreate(['name' => 'dashboard-chart-income-by-department', 'guard_name' => 'admin']),
            Permission::query()->firstOrCreate(['name' => 'dashboard-chart-income-distribution', 'guard_name' => 'admin']),
        ]);

        $admin = Admin::query()->create([
            'first_name' => 'Main Category',
            'last_name' => 'User',
            'email' => 'main-category-dashboard@example.test',
            'phone' => '01710000022',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $role->id,
        ]);
        $admin->assignRole($role);

        $today = now();
        $service = \Mockery::mock(DashboardService::class)->makePartial();
        $service->shouldReceive('addDiscountRoundingToDiagnosticIncomes')
            ->andReturnUsing(fn ($pathology, $radiology) => [$pathology, $radiology]);
        $service->shouldReceive('resolveDashboardFilter')->andReturn([
            'type' => 'daily',
            'from' => $today->toDateString(),
            'to' => $today->toDateString(),
            'dbRange' => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            'appRange' => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
        ]);
        $service->shouldReceive('countActiveUser')->andReturn(0);
        $service->shouldReceive('countInActiveUser')->andReturn(0);
        $service->shouldReceive('countDiagnosticIncome')
            ->with('Radiology', \Mockery::any(), \Mockery::any())
            ->andReturn(123.45);
        $service->shouldReceive('countDiagnosticIncome')
            ->with('Pathology', \Mockery::any(), \Mockery::any())
            ->andReturn(67.89);
        $service->shouldReceive('countCategoryIncome')
            ->andReturnUsing(fn ($category) => strtolower($category) === 'ultrasound' ? 123.45 : 67.89);
        $service->shouldReceive('countNetIncome')->andReturn(123.45);
        $service->shouldReceive('countPharmacyIncome')->andReturn(0);
        $service->shouldReceive('countOpdIncome')->andReturn(0);
        $service->shouldReceive('countIpdIncome')->andReturn(0);
        $service->shouldReceive('countDisposableIncome')->andReturn(0);
        $service->shouldReceive('countPendingIncome')->andReturn(0);
        $service->shouldReceive('countExpense')->andReturn(0);
        $service->shouldReceive('countMainCategoryTypeIncome')
            ->with('Pathology', \Mockery::any())
            ->andReturn(67.89);
        $service->shouldReceive('countMainCategoryTypeIncome')
            ->with('Radiology', \Mockery::any())
            ->andReturn(123.45);
        $service->shouldReceive('countMainCategoryIncome')
            ->andReturnUsing(fn ($id) => (int) $id === $radiologyMainCategoryId ? 123.45 : 67.89);
        $service->shouldReceive('getPathologyIncomeSummary')
            ->andReturn(['total' => 70.0, 'discount' => 2.11, 'net' => 67.89]);
        $this->app->instance(DashboardService::class, $service);

        $this->actingAs($admin, 'admin')
            ->get(route('backend.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dashboardCardPermissions.custom-category-ultrasound', true)
                ->where('dashboardCardPermissions.main-category-ultrasound', true)
                ->where('dashboardCardPermissions.chartIncomeByDepartment', true)
                ->where('dashboardCardPermissions.chartIncomeDistribution', true)
                ->where('dashboardCardPermissions.pathologyIncome', true)
                ->where('dashboardCardPermissions.radiologyIncome', false)
                ->where('dashboardData.categoryIncomes', [])
                ->where('dashboardData.chartIncomeItems', fn ($items) => collect($items)
                    ->contains(fn ($item) => $item['name'] === 'Ultrasound Income' && $item['amount'] === 123.45)
                    && collect($items)->contains(fn ($item) => $item['name'] === 'Biochemistry Income' && $item['amount'] === 67.89))
                ->where('dashboardData.mainCategoryIncomes.0.name', 'Biochemistry')
                ->where('dashboardData.mainCategoryIncomes.0.amount', 67.89)
                ->where('dashboardData.mainCategoryIncomes.1.name', 'Ultrasound')
                ->where('dashboardData.mainCategoryIncomes.1.amount', 123.45)
                ->where('dashboardData.pathologyIncome', 67.89)
                ->where('dashboardData.pathologyTotalAmount', 70)
                ->where('dashboardData.pathologyDiscountAmount', 2.11)
                ->where('dashboardData.radiologyIncome', 123.45)
            );
    }

    public function test_main_category_income_uses_billed_tests_assigned_to_that_main_category(): void
    {
        $mainCategoryId = DB::table('main_categories')->insertGetId([
            'name' => 'Ultrasound',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $testCategory = TestCategory::query()->create(['name' => 'Radiology', 'status' => 'Active']);
        $test = Test::query()->create([
            'category_type' => 'Radiology',
            'test_name' => 'Ultrasound Scan',
            'test_category_id' => $testCategory->id,
            'main_category_id' => $mainCategoryId,
            'standard_charge' => 100,
            'amount' => 100,
            'status' => 'Active',
        ]);

        $role = Role::query()->create([
            'name' => 'ecg-main-category-dashboard-role',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);
        $role->givePermissionTo([
            Permission::query()->firstOrCreate(['name' => 'dashboard', 'guard_name' => 'admin']),
            Permission::query()->firstOrCreate(['name' => 'dashboard-main-category-ecg-income', 'guard_name' => 'admin']),
        ]);

        $admin = Admin::query()->create([
            'first_name' => 'Main Category',
            'last_name' => 'Billing',
            'email' => 'main-category-billing@example.test',
            'phone' => '01710000023',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $role->id,
        ]);
        $admin->assignRole($role);
        $billing = $this->createDepartmentBilling($admin, 'MAIN-CAT-001', 100, 100, 0);
        BillItem::query()->create([
            'billing_id' => $billing->id,
            'item_id' => $test->id,
            'item_name' => 'Ultrasound Scan',
            'category' => 'Radiology',
            'unit_price' => 100,
            'quantity' => 1,
            'total_amount' => 100,
            'discount' => 10,
            'net_amount' => 90,
            'status' => 'Active',
        ]);

        $ecgTest = Test::query()->create([
            'category_type' => 'ECG',
            'test_name' => 'ECG Test',
            'test_category_id' => $testCategory->id,
            'main_category_id' => null,
            'standard_charge' => 2439.41,
            'amount' => 2439.41,
            'status' => 'Active',
        ]);
        $ecgMainCategoryId = DB::table('main_categories')->insertGetId([
            'name' => 'ECG',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $ecgBilling = $this->createDepartmentBilling($admin, 'MAIN-CAT-ECG-001', 2439.41, 2439.41, 0);
        BillItem::query()->create([
            'billing_id' => $ecgBilling->id,
            'item_id' => $ecgTest->id,
            'item_name' => 'ECG Test',
            'category' => 'Radiology',
            'unit_price' => 2439.41,
            'quantity' => 1,
            'total_amount' => 2439.41,
            'discount' => 0,
            'net_amount' => 2439.41,
            'status' => 'Active',
        ]);

        $now = now();
        $service = app(DashboardService::class);
        $dbRange = [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
        $this->assertSame(90.0, $service->countMainCategoryIncome($mainCategoryId, $dbRange));
        $this->assertSame(90.0, $service->countMainCategoryIncome($mainCategoryId, $dbRange, 'Radiology'));
        $this->assertSame(2439.41, $service->countMainCategoryIncome($ecgMainCategoryId, $dbRange));
        $this->assertSame(2439.41, $service->countCategoryIncome('ECG', $dbRange));
        $this->assertSame(90.0, $service->countMainCategoryTypeIncome('Radiology', $dbRange));
        $this->assertSame(0.0, $service->countMainCategoryIncome($mainCategoryId, $dbRange, 'Pathology'));

        $this->actingAs($admin, 'admin')
            ->get(route('backend.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dashboardData.mainCategoryIncomes.0.name', 'ECG')
                ->where('dashboardData.mainCategoryIncomes.0.amount', 2439.41)
            );
    }

    public function test_disposable_category_income_does_not_receive_a_share_of_bill_extra_discount(): void
    {
        $admin = Admin::query()->create([
            'first_name' => 'Disposable',
            'last_name' => 'Income',
            'email' => 'disposable-income-test@example.test',
            'phone' => '01710000099',
            'password' => 'password',
            'status' => 'Active',
        ]);

        $billing = $this->createDepartmentBilling($admin, 'DISPOSABLE-INCOME-001', 1100, 1000, 100);
        $billing->extra_flat_discount = 100;
        $billing->save();
        BillItem::query()->create([
            'billing_id' => $billing->id,
            'item_id' => 1,
            'item_name' => 'Disposable Gloves',
            'category' => 'Disposable',
            'unit_price' => 500,
            'quantity' => 1,
            'total_amount' => 500,
            'discount' => 0,
            'net_amount' => 500,
            'status' => 'Active',
        ]);
        BillItem::query()->create([
            'billing_id' => $billing->id,
            'item_id' => 2,
            'item_name' => 'Blood Test',
            'category' => 'Pathology',
            'unit_price' => 500,
            'quantity' => 1,
            'total_amount' => 500,
            'discount' => 0,
            'net_amount' => 500,
            'status' => 'Active',
        ]);
        BillItem::query()->create([
            'billing_id' => $billing->id,
            'item_id' => 3,
            'item_name' => 'Disposable Tube',
            'category' => 'Pathology',
            'unit_price' => 100,
            'quantity' => 1,
            'total_amount' => 100,
            'discount' => 20,
            'net_amount' => 80,
            'status' => 'Active',
        ]);

        $dbRange = [now()->copy()->startOfDay(), now()->copy()->endOfDay()];
        $service = app(DashboardService::class);

        $this->assertSame(500.0, $service->countCategoryIncome('Disposable', $dbRange));
        $this->assertSame(500.0, $service->countCategoryIncome('Pathology', $dbRange));
        $this->assertSame(500.0, $service->countDisposableIncome($dbRange, $dbRange));
    }

    public function test_creating_a_main_category_creates_its_dashboard_permission(): void
    {
        Permission::query()->firstOrCreate([
            'name' => 'dashboard',
            'guard_name' => 'admin',
        ]);
        $adminRole = Role::query()->firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'admin',
        ], ['is_private' => true]);
        app(\Spatie\Permission\PermissionRegistrar::class)->getPermissions();

        $this->withoutMiddleware()
            ->post(route('backend.maincategory.store'), ['name' => 'Ultrasound'])
            ->assertRedirect(route('backend.maincategory.index'));

        $permission = Permission::query()
            ->where('name', 'dashboard-main-category-ultrasound-income')
            ->where('guard_name', 'admin')
            ->firstOrFail();
        $this->assertSame(
            Permission::query()->where('name', 'dashboard')->where('guard_name', 'admin')->value('id'),
            $permission->parent_id
        );
        $this->assertTrue($adminRole->fresh()->hasPermissionTo($permission));
    }

    public function test_soft_deleted_main_category_name_can_be_reused(): void
    {
        $deletedCategory = \App\Models\MainCategory::query()->create([
            'name' => 'Ultrasound',
            'status' => 'Active',
        ]);
        $deletedCategory->delete();

        $this->withoutMiddleware()
            ->post(route('backend.maincategory.store'), ['name' => 'Ultrasound'])
            ->assertRedirect(route('backend.maincategory.index'));

        $this->assertSame(
            1,
            \App\Models\MainCategory::query()->where('name', 'Ultrasound')->count()
        );
        $this->assertDatabaseHas('main_categories', [
            'id' => $deletedCategory->id,
            'deleted_at' => $deletedCategory->fresh()->deleted_at,
        ]);

        $this->withoutMiddleware()
            ->from(route('backend.maincategory.create'))
            ->post(route('backend.maincategory.store'), ['name' => 'Ultrasound'])
            ->assertSessionHasErrors('name');
    }

    public function test_dashboard_adds_discount_rounding_to_diagnostic_card_amounts(): void
    {
        $role = Role::query()->create([
            'name' => 'dashboard-rounding-role',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);
        $permissionNames = [
            'dashboard',
            'dashboard-card-net-income',
            'dashboard-card-expenses',
            'dashboard-card-refunds',
            'dashboard-card-pathology-income',
            'dashboard-card-radiology-income',
            'dashboard-card-hormones-income',
        ];
        $permissions = collect($permissionNames)->map(fn (string $name) => Permission::query()->firstOrCreate([
            'name' => $name,
            'guard_name' => 'admin',
        ]));
        $hormonesPermission = Permission::query()->firstOrCreate([
            'name' => 'dashboard-card-hormones-income',
            'guard_name' => 'admin',
        ]);
        $role->givePermissionTo($permissions);

        $admin = Admin::query()->create([
            'first_name' => 'Rounding',
            'last_name' => 'User',
            'email' => 'dashboard-rounding@example.test',
            'phone' => '01710000020',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $role->id,
        ]);
        $admin->assignRole($role);
        $role->givePermissionTo($hormonesPermission);
        TestCategory::query()->create(['name' => 'Hormones', 'status' => 'Active']);

        $today = now();
        $service = \Mockery::mock(DashboardService::class)->makePartial();
        $service->shouldReceive('addDiscountRoundingToDiagnosticIncomes')
            ->andReturn([32317.69, 22852.33]);
        $service->shouldReceive('resolveDashboardFilter')->andReturn([
            'type' => 'daily',
            'from' => $today->toDateString(),
            'to' => $today->toDateString(),
            'dbRange' => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            'appRange' => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
        ]);
        $service->shouldReceive('countCategoryIncome')->with('Hormones', \Mockery::any(), \Mockery::any())->andReturn(449.97);
        $service->shouldReceive('countActiveUser')->andReturn(0);
        $service->shouldReceive('countInActiveUser')->andReturn(0);
        $service->shouldReceive('countNetIncome')->andReturn(665130.00);
        $service->shouldReceive('getPathologyIncomeSummary')->andReturn([
            'total' => 32317.67,
            'discount' => 0,
            'net' => 32317.67,
        ]);
        $service->shouldReceive('countRadiologyIncome')->andReturn(22852.33);
        $service->shouldReceive('countExpense')->andReturn(10);
        $service->shouldReceive('countRefunds')->andReturn(5);
        $this->app->instance(DashboardService::class, $service);
        $categoryService = \Mockery::mock(TestCategoryService::class)->makePartial();
        $categoryService->shouldReceive('activeNames')->andReturn(['Hormones']);
        $this->app->instance(TestCategoryService::class, $categoryService);

        $this->actingAs($admin, 'admin')
            ->get(route('backend.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dashboardData.netIncome', 665130)
                ->where('dashboardData.expenses', 10)
                ->where('dashboardData.refunds', 5)
                ->where('dashboardCardPermissions.pharmacyIncome', false)
                ->where('dashboardCardPermissions.doctorVisitAppointment', false)
                ->where('dashboardData.pathologyIncome', 32317.69)
                ->where('dashboardData.radiologyIncome', 22852.33)
                ->where('dashboardData.categoryIncomes.0.amount', 449.97)
                ->where('dashboardData.categoryIncomes.0.name', 'Hormones')
            );
    }

    public function test_refund_card_is_hidden_when_only_general_dashboard_permission_is_available(): void
    {
        $role = Role::query()->create([
            'name' => 'dashboard-role',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);

        $dashboardPermission = Permission::query()->firstOrCreate([
            'name' => 'dashboard',
            'guard_name' => 'admin',
        ]);

        $role->givePermissionTo($dashboardPermission);

        $admin = Admin::query()->create([
            'first_name' => 'Dashboard',
            'last_name' => 'User',
            'email' => 'dashboard-user@example.test',
            'phone' => '01710000010',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $role->id,
        ]);
        $admin->assignRole($role);

        $this->actingAs($admin, 'admin');

        $response = $this->get(route('backend.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('dashboardCardPermissions.refunds', false)
            ->where('dashboardCardPermissions.doctorVisitAppointment', false)
        );
    }

    public function test_doctor_visit_appointment_card_uses_its_own_permission(): void
    {
        $role = Role::query()->create([
            'name' => 'doctor-visit-dashboard-role',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);

        $dashboardPermission = Permission::query()->firstOrCreate([
            'name' => 'dashboard',
            'guard_name' => 'admin',
        ]);
        $doctorVisitPermission = Permission::query()->firstOrCreate([
            'name' => 'dashboard-card-doctor-visit-appointment',
            'guard_name' => 'admin',
        ], [
            'parent_id' => $dashboardPermission->id,
        ]);

        $role->givePermissionTo([$dashboardPermission, $doctorVisitPermission]);

        $admin = Admin::query()->create([
            'first_name' => 'Doctor Visit',
            'last_name' => 'User',
            'email' => 'doctor-visit-dashboard@example.test',
            'phone' => '01710000012',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $role->id,
        ]);
        $admin->assignRole($role);

        $this->actingAs($admin, 'admin');

        $response = $this->get(route('backend.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('dashboardCardPermissions.doctorVisitAppointment', true)
            ->where('dashboardCardPermissions.opdIncome', false)
        );
    }

    public function test_pharmacy_user_sees_assigned_dashboard_cards_while_reports_remain_scoped(): void
    {
        $role = Role::query()->create([
            'name' => 'pharmacist',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);

        $permissionNames = [
            'dashboard',
            'dashboard-card-opd-income',
            'dashboard-card-pharmacy-income',
            'dashboard-card-net-income',
            'dashboard-card-total-income',
            'dashboard-card-pathology-income',
            'dashboard-card-radiology-income',
            'dashboard-card-expenses',
            'dashboard-card-pending-income',
            'product-return-list',
        ];
        $permissions = collect($permissionNames)->map(function (string $name) {
            return Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'admin',
            ]);
        });
        $role->givePermissionTo($permissions);

        $admin = Admin::query()->create([
            'first_name' => 'Pharmacy',
            'last_name' => 'User',
            'email' => 'pharmacy-dashboard@example.test',
            'phone' => '01710000013',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $role->id,
        ]);
        $admin->assignRole($role);

        PharmacyBill::query()->create([
            'pharmacy_no' => 'PH-DASH-TEST-001',
            'bill_no' => 'BILL-DASH-TEST-001',
            'date' => now()->toDateString(),
            'products' => json_encode([]),
            'subtotal' => 40,
            'discount_amount' => 5,
            'extra_discount' => 0,
            'net_amount' => 35,
            'payment_amount' => 20,
            'created_by' => $admin->id,
            'status' => 'Active',
        ]);
        ProductReturn::query()->create([
            'return_number' => 'RETURN-DASH-TEST-001',
            'return_type' => 'customer',
            'source_bill_no' => 'BILL-DASH-TEST-001',
            'return_date' => now()->toDateString(),
            'total_amount' => 5,
            'status' => 'approved',
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->get(route('backend.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('departmentScope', 'pharmacy')
            ->where('dashboardCardPermissions.opdIncome', false)
            ->missing('dashboardData.opdIncome')
            ->where('dashboardCardPermissions.pharmacyIncome', true)
            ->where('dashboardCardPermissions.netIncome', true)
            ->where('dashboardCardPermissions.totalIncome', false)
            ->where('dashboardCardPermissions.pathologyIncome', false)
            ->where('dashboardCardPermissions.radiologyIncome', false)
            ->where('dashboardCardPermissions.expenses', true)
            ->where('dashboardCardPermissions.pendingIncome', true)
            ->where('dashboardCardPermissions.productReturns', false)
            ->has('dashboardData.pharmacyIncome')
            ->missing('dashboardData.productReturns')
            ->has('dashboardData.netIncome')
            ->where('dashboardData.pharmacyIncome', 30)
            ->where('dashboardData.netIncome', 30)
            ->missing('dashboardData.totalIncome')
            ->where('dashboardData.pendingIncome', 10)
            ->where('dashboardData.expenses', 0)
            ->missing('dashboardData.pathologyIncome')
            ->missing('dashboardData.radiologyIncome')
        );

        $productReturnsPermission = Permission::query()->firstOrCreate([
            'name' => 'dashboard-card-product-returns',
            'guard_name' => 'admin',
        ]);
        $role->givePermissionTo($productReturnsPermission);
        $this->actingAs($admin->fresh(), 'admin')
            ->get(route('backend.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dashboardCardPermissions.productReturns', true)
                ->where('dashboardData.productReturns', 5)
            );

        $pharmacyReport = $this->get(route('backend.finance-report.index', [
            'report_type' => 'pharmacy-transaction',
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
        ]));
        $pharmacyReport->assertOk();
        $pharmacyReport->assertInertia(fn ($page) => $page
            ->where('reportType', 'pharmacy-transaction')
            ->where('availableReportTypes', ['pharmacy-transaction'])
            ->where('reportData.0.net_income_raw', 30)
            ->where('footerTotals.net_income', 30)
        );

        $this->get(route('backend.finance-report.index', [
            'report_type' => 'income',
        ]))->assertForbidden();

        $this->get(route('backend.finance.report.pdf', [
            'report_type' => 'income',
            'date_from' => '2026-10-01',
            'date_to' => '2026-10-31',
        ]))->assertForbidden();
    }

    public function test_pathology_user_sees_assigned_dashboard_cards_while_reports_remain_scoped(): void
    {
        $department = Department::query()->create(['name' => 'Pathology', 'status' => 'Active']);
        $radiologyDepartment = Department::query()->create(['name' => 'Radiology', 'status' => 'Active']);
        $role = Role::query()->create([
            'name' => 'pathology',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);
        $permissions = collect([
            'dashboard',
            'dashboard-card-pathology-income',
            'dashboard-card-radiology-income',
            'dashboard-card-net-income',
            'dashboard-card-total-income',
            'dashboard-card-expenses',
            'dashboard-card-pending-income',
            'dashboard-card-total-discount',
            'dashboard-card-refunds',
        ])->map(fn (string $name) => Permission::query()->firstOrCreate([
            'name' => $name,
            'guard_name' => 'admin',
        ]));
        $role->givePermissionTo($permissions);

        $admin = Admin::query()->create([
            'first_name' => 'Pathology',
            'last_name' => 'User',
            'email' => 'pathology-dashboard@example.test',
            'phone' => '01710000015',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $role->id,
        ]);
        $admin->assignRole($role);
        AdminDetail::query()->create([
            'admin_id' => $admin->id,
            'department_id' => $department->id,
            'status' => 'Active',
        ]);

        $pathologyBilling = $this->createDepartmentBilling($admin, 'PATH-DASH-001', 100, 90, 30);
        Payment::query()->create([
            'billing_id' => $pathologyBilling->id,
            'amount' => 60,
            'payment_method' => 'Cash',
            'received_by' => $admin->id,
            'payment_status' => 'Partial',
        ]);
        BillItem::query()->create([
            'billing_id' => $pathologyBilling->id,
            'department_id' => $department->id,
            'item_id' => 1,
            'item_name' => 'Pathology Test',
            'category' => 'Pathology',
            'unit_price' => 100,
            'quantity' => 1,
            'total_amount' => 100,
            'discount' => 10,
            'rugound' => 0,
            'net_amount' => 90,
            'status' => 'Active',
        ]);

        $radiologyBilling = $this->createDepartmentBilling($admin, 'RAD-DASH-001', 200, 200, 0);
        Payment::query()->create([
            'billing_id' => $radiologyBilling->id,
            'amount' => 200,
            'payment_method' => 'Cash',
            'received_by' => $admin->id,
            'payment_status' => 'Paid',
        ]);
        BillItem::query()->create([
            'billing_id' => $radiologyBilling->id,
            'department_id' => $radiologyDepartment->id,
            'item_id' => 2,
            'item_name' => 'Radiology Test',
            'category' => 'Pathology',
            'unit_price' => 200,
            'quantity' => 1,
            'total_amount' => 200,
            'discount' => 0,
            'rugound' => 0,
            'net_amount' => 200,
            'status' => 'Active',
        ]);

        $expenseHead = ExpenseHead::query()->create(['name' => 'Pathology Supplies', 'status' => 'Active']);
        Expense::query()->create([
            'expense_header_id' => $expenseHead->id,
            'department_id' => $department->id,
            'name' => 'Pathology supplies',
            'amount' => 20,
            'date' => now()->toDateString(),
            'status' => 'Active',
        ]);
        Expense::query()->create([
            'expense_header_id' => $expenseHead->id,
            'department_id' => $radiologyDepartment->id,
            'name' => 'Radiology supplies',
            'amount' => 50,
            'date' => now()->toDateString(),
            'status' => 'Active',
        ]);

        $this->actingAs($admin, 'admin');

        $this->get(route('backend.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('departmentScope', null)
                ->where('dashboardCardPermissions.pathologyIncome', true)
                ->where('dashboardCardPermissions.radiologyIncome', true)
                ->has('dashboardData.pathologyIncome')
                ->where('dashboardData.totalIncome', 260)
                ->has('dashboardData.netIncome')
                ->where('dashboardData.netIncome', 190)
                ->where('dashboardData.expenses', 70)
                ->where('dashboardData.pendingIncome', 30)
                ->where('dashboardData.totalDiscountAmount', 10)
                ->has('dashboardData.radiologyIncome')
            );

        $this->get(route('backend.finance-report.index', [
            'report_type' => 'department-transaction',
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('availableReportTypes', ['department-transaction'])
                ->has('reportData', 1)
                ->where('reportData.0.bill_number', 'PATH-DASH-001')
                ->where('reportData.0.net_income_raw', 90)
                ->where('footerTotals.net_income', 90)
            );

        $this->get(route('backend.finance-report.index', ['report_type' => 'pharmacy-transaction']))
            ->assertForbidden();
    }

    public function test_radiology_dashboard_and_report_are_scoped_to_radiology_department(): void
    {
        $department = Department::query()->create(['name' => 'Radiology', 'status' => 'Active']);
        $role = Role::query()->create([
            'name' => 'radiologist',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);
        $role->givePermissionTo(collect([
            'dashboard',
            'dashboard-card-radiology-income',
            'dashboard-card-pathology-income',
            'dashboard-card-net-income',
            'dashboard-card-total-income',
            'dashboard-card-pending-income',
        ])->map(fn (string $name) => Permission::query()->firstOrCreate([
            'name' => $name,
            'guard_name' => 'admin',
        ])));

        $admin = Admin::query()->create([
            'first_name' => 'Radiology',
            'last_name' => 'User',
            'email' => 'radiology-dashboard@example.test',
            'phone' => '01710000016',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $role->id,
        ]);
        $admin->assignRole($role);
        AdminDetail::query()->create([
            'admin_id' => $admin->id,
            'department_id' => $department->id,
            'status' => 'Active',
        ]);

        $billing = $this->createDepartmentBilling($admin, 'RAD-DASH-002', 50, 50, 0);
        Payment::query()->create([
            'billing_id' => $billing->id,
            'amount' => 50,
            'payment_method' => 'Cash',
            'received_by' => $admin->id,
            'payment_status' => 'Paid',
        ]);
        BillItem::query()->create([
            'billing_id' => $billing->id,
            'department_id' => $department->id,
            'item_id' => 3,
            'item_name' => 'Radiology Scan',
            'category' => 'Radiology',
            'unit_price' => 50,
            'quantity' => 1,
            'total_amount' => 50,
            'discount' => 0,
            'rugound' => 0,
            'net_amount' => 50,
            'status' => 'Active',
        ]);

        $this->actingAs($admin, 'admin');
        $this->get(route('backend.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('departmentScope', null)
                ->where('dashboardCardPermissions.radiologyIncome', true)
                ->where('dashboardCardPermissions.pathologyIncome', true)
                ->has('dashboardData.radiologyIncome')
                ->where('dashboardData.totalIncome', 50)
                ->where('dashboardData.netIncome', 50)
                ->has('dashboardData.pathologyIncome')
            );

        $this->get(route('backend.finance-report.index', [
            'report_type' => 'department-transaction',
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('availableReportTypes', ['department-transaction'])
                ->where('reportData.0.bill_number', 'RAD-DASH-002')
                ->where('footerTotals.net_income', 50)
            );
    }

    private function createDepartmentBilling(Admin $admin, string $billNumber, float $total, float $payable, float $due): Billing
    {
        return Billing::query()->create([
            'invoice_number' => 'INV-' . $billNumber,
            'bill_number' => $billNumber,
            'case_number' => 'CASE-' . $billNumber,
            'patient_mobile' => '01710000000',
            'gender' => 'Male',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => $total,
            'discount' => 0,
            'discount_type' => 'flat',
            'payable_amount' => $payable,
            'paid_amt' => $payable - $due,
            'receiving_amt' => $payable - $due,
            'due_amount' => $due,
            'created_by' => $admin->id,
            'status' => 'Active',
        ]);
    }

    public function test_admin_role_only_sees_dashboard_cards_that_are_assigned(): void
    {
        $role = Role::query()->create([
            'name' => 'Admin',
            'guard_name' => 'admin',
            'is_private' => true,
        ]);
        $dashboardPermission = Permission::query()->firstOrCreate([
            'name' => 'dashboard',
            'guard_name' => 'admin',
        ]);
        $role->givePermissionTo($dashboardPermission);

        $admin = Admin::query()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin-dashboard@example.test',
            'phone' => '01710000014',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $role->id,
        ]);
        $admin->assignRole($role);

        $this->actingAs($admin, 'admin');

        $response = $this->get(route('backend.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('dashboardCardPermissions.pharmacyIncome', false)
            ->where('dashboardCardPermissions.pathologyIncome', false)
            ->where('dashboardCardPermissions.radiologyIncome', false)
            ->where('dashboardCardPermissions.netIncome', false)
            ->where('dashboardCardPermissions.totalIncome', false)
            ->missing('dashboardData.pathologyIncome')
            ->missing('dashboardData.radiologyIncome')
        );
    }

    public function test_developer_dashboard_only_shows_assigned_cards_and_charts(): void
    {
        $role = Role::query()->create([
            'name' => 'developer',
            'guard_name' => 'admin',
            'is_private' => true,
        ]);

        $dashboardPermission = Permission::query()->firstOrCreate([
            'name' => 'dashboard',
            'guard_name' => 'admin',
        ]);

        $role->givePermissionTo([
            $dashboardPermission,
            Permission::query()->firstOrCreate([
                'name' => 'dashboard-card-opd-income',
                'guard_name' => 'admin',
            ]),
            Permission::query()->firstOrCreate([
                'name' => 'dashboard-chart-income-by-department',
                'guard_name' => 'admin',
            ]),
        ]);

        $admin = Admin::query()->create([
            'first_name' => 'Developer',
            'last_name' => 'User',
            'email' => 'developer-dashboard@example.test',
            'phone' => '01710000011',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $role->id,
        ]);
        $admin->assignRole($role);

        $this->actingAs($admin, 'admin');

        $response = $this->get(route('backend.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('dashboardCardPermissions.opdIncome', true)
            ->where('dashboardCardPermissions.doctorVisitAppointment', false)
            ->where('dashboardCardPermissions.totalDiscountAmount', false)
            ->where('dashboardCardPermissions.chartIncomeByDepartment', true)
            ->where('dashboardCardPermissions.chartIncomeDistribution', false)
        );
    }
}
