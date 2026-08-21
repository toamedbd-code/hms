<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BkashPendingPaymentReuseTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_initiate_reuses_existing_pending_payment_instead_of_creating_a_new_one(): void
    {
        config(['payment.enabled' => true]);
        config(['subscription.monthly_amount' => 100]);
        config(['bkash.app_key' => 'test-key']);
        config(['bkash.app_secret' => 'test-secret']);
        config(['bkash.username' => 'test-user']);
        config(['bkash.password' => 'test-pass']);

        Subscription::ensureExists();

        $existingPayment = Payment::create([
            'provider' => 'bkash',
            'amount' => 100,
            'payment_method' => 'bkash',
            'status' => 'pending',
            'provider_payment_id' => 'PAY-EXISTING',
            'metadata' => ['period' => 'monthly'],
        ]);

        $this->withSession([
            'bkash_pending_payment_id' => $existingPayment->id,
        ])->get('/payment/bkash/renew?amount=100&period=monthly')
            ->assertRedirectContains('/payment/bkash/waiting/' . $existingPayment->id);

        $this->assertSame(1, Payment::query()->count());
    }

    public function test_public_simulate_page_redirects_completed_payment_to_login_with_clear_message(): void
    {
        config(['payment.enabled' => true]);

        $payment = Payment::create([
            'provider' => 'bkash',
            'amount' => 100,
            'payment_method' => 'bkash',
            'status' => 'success',
            'provider_payment_id' => 'PAY-COMPLETED',
            'metadata' => ['period' => 'monthly'],
        ]);

        $response = $this->get('/payment/bkash/simulate-public/' . $payment->id);

        $response->assertRedirect();
        $response->assertSessionHas('successMessage', 'Payment already completed. You can log in now.');
    }

    public function test_callback_can_resume_payment_from_session_pending_payment_id_when_provider_payment_id_is_missing(): void
    {
        config(['payment.enabled' => true]);
        config(['subscription.monthly_amount' => 100]);

        $payment = Payment::create([
            'provider' => 'bkash',
            'amount' => 100,
            'payment_method' => 'bkash',
            'status' => 'pending',
            'provider_payment_id' => 'PAY-SESSION',
            'metadata' => ['period' => 'monthly'],
        ]);

        $this->withSession([
            'bkash_pending_payment_id' => $payment->id,
        ])->get('/payment/bkash/callback?paymentID=PAY-UNRELATED&status=cancel')
            ->assertRedirect();

        $payment->refresh();
        $this->assertSame('failed', $payment->status);
    }
}
