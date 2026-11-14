<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminDetail;
use App\Models\Department;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\PharmacyBill;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PharmacyDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_pharmacy_net_income_subtracts_only_pharmacy_expenses(): void
    {
        $pharmacy = Department::query()->create([
            'name' => 'Pharmacy',
            'status' => 'Active',
        ]);
        $radiology = Department::query()->create([
            'name' => 'Radiology',
            'status' => 'Active',
        ]);
        $expenseHead = ExpenseHead::query()->create(['name' => 'Operating expense']);
        Expense::query()->create([
            'expense_header_id' => $expenseHead->id,
            'department_id' => $pharmacy->id,
            'name' => 'Pharmacy expense',
            'amount' => 30,
            'date' => now()->toDateString(),
            'status' => 'Active',
        ]);
        Expense::query()->create([
            'expense_header_id' => $expenseHead->id,
            'department_id' => $radiology->id,
            'name' => 'Radiology expense',
            'amount' => 200,
            'date' => now()->toDateString(),
            'status' => 'Active',
        ]);
        $admin = Admin::query()->create([
            'first_name' => 'Pharmacy',
            'last_name' => 'Seller',
            'email' => 'pharmacy-dashboard-seller@example.test',
            'phone' => '01710000032',
            'password' => 'password',
            'status' => 'Active',
        ]);
        PharmacyBill::query()->create([
            'pharmacy_no' => 'PHN-DASHBOARD-1',
            'bill_no' => 'PHB-DASHBOARD-1',
            'date' => now()->toDateString(),
            'products' => json_encode([]),
            'subtotal' => 100,
            'discount_amount' => 10,
            'extra_discount' => 0,
            'net_amount' => 90,
            'payment_amount' => 90,
            'created_by' => $admin->id,
            'status' => 'Active',
        ]);

        $now = now();
        $summary = app(DashboardService::class)->getDepartmentDashboardSummary(
            'pharmacy',
            $pharmacy->id,
            [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            [$now->copy()->startOfDay(), $now->copy()->endOfDay()]
        );

        $this->assertSame(90.0, $summary['income']);
        $this->assertSame(30.0, $summary['expenses']);
        $this->assertSame(10.0, $summary['totalDiscountAmount']);
        $this->assertSame(60.0, $summary['netIncome']);
    }

    public function test_pharmacy_users_only_receive_pharmacy_dashboard_metrics(): void
    {
        $department = Department::query()->create([
            'name' => 'Pharmacy',
            'status' => 'Active',
        ]);
        $role = Role::query()->create([
            'name' => 'pharmacy staff',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);
        $role->givePermissionTo(collect([
            'dashboard',
            'dashboard-card-pharmacy-income',
            'dashboard-card-expenses',
            'dashboard-card-total-discount',
            'dashboard-card-net-income',
        ])->map(fn ($name) => Permission::query()->firstOrCreate([
            'name' => $name,
            'guard_name' => 'admin',
        ])));

        $admin = Admin::query()->create([
            'first_name' => 'Pharmacy',
            'last_name' => 'User',
            'email' => 'pharmacy-dashboard@example.test',
            'phone' => '01710000031',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $role->id,
        ]);
        $admin->assignRole($role);
        AdminDetail::query()->create([
            'admin_id' => $admin->id,
            'department_id' => $department->id,
        ]);

        $now = now();
        $dashboardService = \Mockery::mock(DashboardService::class)->makePartial();
        $dashboardService->shouldReceive('resolveDashboardFilter')->once()->andReturn([
            'type' => 'daily',
            'from' => $now->toDateString(),
            'to' => $now->toDateString(),
            'dbRange' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'appRange' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        ]);
        $dashboardService->shouldReceive('getDepartmentDashboardSummary')
            ->once()
            ->with('pharmacy', $department->id, \Mockery::any(), \Mockery::any())
            ->andReturn([
                'income' => 120,
                'expenses' => 30,
                'totalDiscountAmount' => 10,
                'netIncome' => 90,
                'pendingIncome' => 0,
                'refunds' => 0,
            ]);
        $this->app->instance(DashboardService::class, $dashboardService);

        $this->actingAs($admin, 'admin')
            ->get(route('backend.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('departmentScope', 'pharmacy')
                ->where('dashboardData.pharmacyIncome', 120)
                ->where('dashboardData.expenses', 30)
                ->where('dashboardData.totalDiscountAmount', 10)
                ->where('dashboardData.netIncome', 90)
                ->missing('dashboardData.opdIncome')
                ->missing('dashboardData.pathologyIncome')
                ->missing('dashboardData.totalIncome')
                ->where('dashboardCardPermissions.pharmacyIncome', true)
                ->where('dashboardCardPermissions.opdIncome', false)
                ->where('dashboardCardPermissions.chartIncomeDistribution', false)
            );
    }
}
