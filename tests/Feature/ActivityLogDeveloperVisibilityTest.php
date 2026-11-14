<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Services\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActivityLogDeveloperVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('SUBSCRIPTION_ENFORCE=false');
        $_ENV['SUBSCRIPTION_ENFORCE'] = 'false';
        $_SERVER['SUBSCRIPTION_ENFORCE'] = 'false';
    }

    public function test_developer_login_and_logout_are_not_logged_but_other_users_are(): void
    {
        $developerRole = Role::query()->create([
            'name' => 'developer',
            'guard_name' => 'admin',
        ]);
        $developer = $this->createAdmin('developer@example.test', $developerRole->id);
        $regularUser = $this->createAdmin('regular@example.test');

        $this->post(route('backend.auth.login'), [
            'email' => $developer->email,
            'password' => 'password',
        ])->assertRedirect(route('backend.dashboard'));

        $this->assertDatabaseMissing('activity_logs', [
            'user_id' => $developer->id,
            'module' => 'AUTH',
            'action' => 'LOGIN',
        ]);

        $this->post(route('backend.auth.logout'));

        $this->assertDatabaseMissing('activity_logs', [
            'user_id' => $developer->id,
            'module' => 'AUTH',
            'action' => 'LOGOUT',
        ]);

        $this->post(route('backend.auth.login'), [
            'email' => $regularUser->email,
            'password' => 'password',
        ])->assertRedirect(route('backend.dashboard'));
        $this->post(route('backend.auth.logout'));

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $regularUser->id,
            'module' => 'AUTH',
            'action' => 'LOGIN',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $regularUser->id,
            'module' => 'AUTH',
            'action' => 'LOGOUT',
        ]);
    }

    public function test_activity_log_queries_hide_all_developer_activity_and_keep_other_users(): void
    {
        $developerRole = Role::query()->create([
            'name' => 'developer',
            'guard_name' => 'admin',
        ]);
        $developer = $this->createAdmin('developer-logs@example.test', $developerRole->id);
        $regularUser = $this->createAdmin('regular-logs@example.test');

        $developerLogin = $this->createLog($developer, 'AUTH', 'LOGIN');
        $developerLogout = $this->createLog($developer, 'AUTH', 'LOGOUT');
        $developerUpdate = $this->createLog($developer, 'Billing', 'UPDATE');
        $developerDelete = $this->createLog($developer, 'Patient', 'DELETE');
        $regularLogin = $this->createLog($regularUser, 'AUTH', 'LOGIN');
        $regularLogout = $this->createLog($regularUser, 'AUTH', 'LOGOUT');
        $regularDelete = $this->createLog($regularUser, 'Patient', 'DELETE');

        $visibleIds = ActivityLog::query()->pluck('id')->all();

        $this->assertNotContains($developerLogin->id, $visibleIds);
        $this->assertNotContains($developerLogout->id, $visibleIds);
        $this->assertNotContains($developerUpdate->id, $visibleIds);
        $this->assertNotContains($developerDelete->id, $visibleIds);
        $this->assertContains($regularLogin->id, $visibleIds);
        $this->assertContains($regularLogout->id, $visibleIds);
        $this->assertContains($regularDelete->id, $visibleIds);
    }

    public function test_activity_log_service_does_not_store_developer_actions(): void
    {
        $developerRole = Role::query()->create([
            'name' => 'developer',
            'guard_name' => 'admin',
        ]);
        $developer = $this->createAdmin('developer-actions@example.test', $developerRole->id);

        $this->actingAs($developer, 'admin');
        $unsavedLog = ActivityLogService::logDelete('Patient', 123, 'Patient#123');

        $this->assertFalse($unsavedLog->exists);
        $this->assertDatabaseMissing('activity_logs', [
            'user_id' => $developer->id,
            'module' => 'Patient',
            'action' => 'DELETE',
        ]);
    }

    public function test_activity_log_alerts_are_cached_and_return_recent_activity(): void
    {
        $admin = $this->createAdmin('activity-alerts@example.test');
        $activityLog = $this->createLog($admin, 'Billing', 'CREATE');
        Cache::forget('activity.log.alerts.' . now()->toDateString());

        $this->actingAs($admin, 'admin')
            ->getJson(route('backend.activity-logs.alerts'))
            ->assertOk()
            ->assertJsonPath('today_count', 1)
            ->assertJsonPath('recent.0.id', $activityLog->id)
            ->assertJsonPath('recent.0.module', 'Billing');

        $this->createLog($admin, 'Patient', 'UPDATE');

        $this->actingAs($admin, 'admin')
            ->getJson(route('backend.activity-logs.alerts'))
            ->assertOk()
            ->assertJsonPath('today_count', 1);
    }

    private function createAdmin(string $email, ?int $roleId = null): Admin
    {
        return Admin::query()->create([
            'first_name' => 'Activity',
            'last_name' => 'Log User',
            'email' => $email,
            'phone' => '01700000001',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $roleId,
        ]);
    }

    private function createLog(Admin $admin, string $module, string $action): ActivityLog
    {
        return ActivityLog::withoutGlobalScopes()->create([
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'module' => $module,
            'action' => $action,
            'description' => "{$module} {$action}",
            'status' => 'success',
        ]);
    }
}
