<?php

namespace Tests\Feature;

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InertiaSessionExpiryTest extends TestCase
{
    public function test_inertia_session_expiry_redirects_to_login_instead_of_returning_plain_json(): void
    {
        Route::post('/_test/inertia-session-expired', function () {
            throw new TokenMismatchException();
        });

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Inertia' => 'true',
        ])->post('/_test/inertia-session-expired')
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('backend.auth.login2'))
            ->assertDontSee('Session expired. Please refresh the page and try again.');
    }
}
