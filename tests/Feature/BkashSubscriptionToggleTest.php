<?php

namespace Tests\Feature;

use App\Models\Subscription;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BkashSubscriptionToggleTest extends TestCase
{
    use WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function ($table) {
                $table->id();
                $table->boolean('is_active')->default(false);
                $table->timestamp('expires_at')->nullable();
                $table->string('last_payment_id')->nullable();
                $table->timestamps();
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
        $this->assertTrue($subscription->expires_at->greaterThan(now()));

        $this->post(route('backend.settings.payment.bkash.subscription.toggle'), ['mode' => 'off'])
            ->assertRedirect();

        $subscription->refresh();
        $this->assertFalse((bool) $subscription->is_active);
        $this->assertTrue($subscription->expires_at->lessThan(now()));
    }
}
