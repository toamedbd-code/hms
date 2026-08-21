<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardPermissionVisibilityTest extends TestCase
{
    use RefreshDatabase;

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
        $response->assertDontSee('Total Refunds');
    }
}
