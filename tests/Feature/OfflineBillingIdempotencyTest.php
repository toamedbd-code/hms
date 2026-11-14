<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineBillingIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/_test/offline-billing-idempotency', function () {
            $count = Cache::increment('offline-billing-idempotency-test-count');

            return response()->json(['count' => $count], 201);
        })->middleware('offline.idempotent');
    }

    public function test_replaying_an_offline_request_returns_the_original_response_without_repeating_work(): void
    {
        $adminId = $this->createAdmin('offline-idempotency@example.test');
        $this->actingAs(new GenericUser(['id' => $adminId]), 'admin');

        $requestId = (string) Str::uuid();
        $headers = [
            'X-Offline-Request-Id' => $requestId,
            'X-Offline-Admin-Id' => (string) $adminId,
        ];

        $this->postJson('/_test/offline-billing-idempotency', ['amount' => 50], $headers)
            ->assertStatus(201)
            ->assertJson(['count' => 1]);

        $this->postJson('/_test/offline-billing-idempotency', ['amount' => 50], $headers)
            ->assertStatus(201)
            ->assertJson(['count' => 1]);

        $this->assertSame(1, Cache::get('offline-billing-idempotency-test-count'));

        $this->postJson('/_test/offline-billing-idempotency', ['amount' => 60], $headers)
            ->assertStatus(409);
    }

    public function test_offline_request_cannot_be_replayed_by_a_different_signed_in_admin(): void
    {
        $ownerId = $this->createAdmin('offline-owner@example.test');
        $otherAdminId = $this->createAdmin('offline-other@example.test');
        $this->actingAs(new GenericUser(['id' => $otherAdminId]), 'admin');

        $this->postJson('/_test/offline-billing-idempotency', ['amount' => 50], [
            'X-Offline-Request-Id' => (string) Str::uuid(),
            'X-Offline-Admin-Id' => (string) $ownerId,
        ])->assertStatus(403);

        $this->assertNull(Cache::get('offline-billing-idempotency-test-count'));
    }

    private function createAdmin(string $email): int
    {
        return DB::table('admins')->insertGetId([
            'first_name' => 'Offline',
            'last_name' => 'Test',
            'email' => $email,
            'phone' => '01700000000',
            'password' => bcrypt('password'),
            'role_id' => null,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
