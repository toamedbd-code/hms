<?php

namespace Tests\Feature;

use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BkashSubscriptionToggleTest extends TestCase
{
    use WithoutMiddleware;

    public function test_expired_subscription_includes_current_and_missed_months(): void
    {
        $subscription = new Subscription([
            'expires_at' => Carbon::now()->subDays(31),
        ]);

        $this->assertSame(2, $subscription->renewalMonthsDue());
    }

    public function test_active_subscription_can_renew_only_with_five_or_fewer_days_remaining(): void
    {
        $subscription = new Subscription([
            'is_active' => true,
            'expires_at' => Carbon::now()->addDays(5),
        ]);

        $this->assertTrue($subscription->canRenew());

        $subscription->expires_at = Carbon::now()->addDays(6);

        $this->assertFalse($subscription->canRenew());
    }

    public function test_renewal_keeps_remaining_days_and_adds_thirty_days(): void
    {
        $subscription = new Subscription([
            'is_active' => true,
            'expires_at' => Carbon::now()->addDays(5),
        ]);

        $originalExpiry = $subscription->expires_at->copy();
        $subscription->extendForPeriod();

        $this->assertTrue($subscription->expires_at->between(
            $originalExpiry->copy()->addDays(29),
            $originalExpiry->copy()->addDays(31),
        ));
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function ($table) {
                $table->id();
                $table->boolean('is_active')->default(false);
                $table->unsignedSmallInteger('duration_days')->default(30);
                $table->timestamp('expires_at')->nullable();
                $table->string('last_payment_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('subscriptions', 'duration_days')) {
            Schema::table('subscriptions', function ($table) {
                $table->unsignedSmallInteger('duration_days')->default(30)->after('is_active');
            });
        }
    }

    public function test_subscription_can_be_toggled_from_admin_settings_page(): void
    {
        Subscription::truncate();

        $this->post(route('backend.settings.payment.bkash.subscription.toggle'), ['mode' => 'on'])
            ->assertRedirect();

        $subscription = Subscription::first();
        $this->assertNotNull($subscription);
        $this->assertTrue((bool) $subscription->is_active);
        $this->assertNotNull($subscription->expires_at);
        $this->assertTrue($subscription->expires_at->between(
            Carbon::now()->addDays(29),
            Carbon::now()->addDays(31),
        ));

        $this->post(route('backend.settings.payment.bkash.subscription.toggle'), ['mode' => 'off'])
            ->assertRedirect();

        $subscription->refresh();
        $this->assertFalse((bool) $subscription->is_active);
        $this->assertTrue($subscription->expires_at->lessThan(now()));

        $startedAt = Carbon::now();
        $this->post(route('backend.settings.payment.bkash.subscription.toggle'), [
            'mode' => 'on',
            'duration_days' => 5,
        ])->assertRedirect();

        $subscription->refresh();
        $this->assertTrue((bool) $subscription->is_active);
        $this->assertSame(5, (int) $subscription->duration_days);
        $this->assertTrue($subscription->expires_at->between(
            $startedAt->copy()->addDays(4),
            $startedAt->copy()->addDays(6),
        ));
    }
}
