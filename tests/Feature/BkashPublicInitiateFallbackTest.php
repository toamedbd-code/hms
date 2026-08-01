<?php

namespace Tests\Feature;

use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BkashPublicInitiateFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_initiate_falls_back_to_simulate_page_when_bkash_checkout_cannot_be_started(): void
    {
        config(['payment.enabled' => true]);
        config(['subscription.monthly_amount' => 100]);
        config(['bkash.app_key' => null]);
        config(['bkash.app_secret' => null]);
        config(['bkash.username' => null]);
        config(['bkash.password' => null]);

        $response = $this->get('/payment/bkash/renew?amount=100&period=monthly');

        $response->assertRedirect();

        $payment = Payment::latest('id')->first();
        $this->assertNotNull($payment);
        $this->assertStringContainsString('/payment/bkash/simulate-public/' . $payment->id, $response->getTargetUrl());
    }
}
