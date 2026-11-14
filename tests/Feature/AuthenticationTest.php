<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Login is an admin-guard flow and can be blocked by subscription
        // enforcement. Tests cover credential handling, not billing gates.
        putenv('SUBSCRIPTION_ENFORCE=false');
        $_ENV['SUBSCRIPTION_ENFORCE'] = 'false';
        $_SERVER['SUBSCRIPTION_ENFORCE'] = 'false';
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('ziggy', ['location' => route('backend.auth.login2')]));
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $admin = Admin::create([
            'first_name' => 'Login',
            'last_name' => 'User',
            'email' => 'login.user@example.test',
            'phone' => '01710000999',
            'password' => 'password',
            'status' => 'Active',
        ]);

        $response = $this->post(route('backend.auth.login'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated('admin');
        $response->assertRedirect(route('backend.dashboard'));

        $this->get(route('backend.dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('flash.successMessage', 'Logged In Successfully')
            );

        $this->get(route('backend.dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('flash.successMessage', null)
            );
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $admin = Admin::create([
            'first_name' => 'Login',
            'last_name' => 'User',
            'email' => 'login.bad@example.test',
            'phone' => '01710000998',
            'password' => 'password',
            'status' => 'Active',
        ]);

        $this->post(route('backend.auth.login'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest('admin');
    }

    public function test_logout_clears_remember_cookie_and_does_not_reauthenticate(): void
    {
        $admin = Admin::create([
            'first_name' => 'Remember',
            'last_name' => 'User',
            'email' => 'remember.user@example.test',
            'phone' => '01710000997',
            'password' => 'password',
            'status' => 'Active',
        ]);
        $recallerCookieName = auth('admin')->getRecallerName();

        $loginResponse = $this->post(route('backend.auth.login'), [
            'email' => $admin->email,
            'password' => 'password',
            'remember' => 'on',
        ]);
        $loginResponse->assertRedirect(route('backend.dashboard'))
            ->assertCookieNotExpired($recallerCookieName);

        $recallerCookie = $loginResponse->getCookie($recallerCookieName, false);
        $this->assertNotNull($recallerCookie);

        $logoutResponse = $this->withCookie($recallerCookieName, $recallerCookie->getValue())
            ->post(route('backend.auth.logout'));

        $logoutResponse->assertRedirect(route('backend.auth.login2'))
            ->assertCookieExpired($recallerCookieName);
        $this->assertGuest('admin');

        $this->get(route('backend.auth.login2'))->assertOk();
        $this->assertGuest('admin');
    }
}
