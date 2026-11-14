<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BkashPendingPaymentReuseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*tokenized/checkout/token/grant' => Http::response([
                'statusCode' => '0000',
                'id_token' => 'test-token',
            ], 200),
            '*tokenized/checkout/create' => Http::response([
                'statusCode' => '0000',
                'bkashURL' => 'https://sandbox.bka.sh/checkout',
                'paymentID' => 'PAY-FAKE',
            ], 200),
            '*tokenized/checkout/execute' => Http::response([
                'statusCode' => '0000',
                'transactionStatus' => 'Completed',
            ], 200),
            '*tokenized/checkout/payment/status' => Http::response([
                'statusCode' => '0000',
                'transactionStatus' => 'Completed',
            ], 200),
        ]);
    }

    public function test_public_initiate_replaces_existing_pending_payment_with_a_fresh_checkout(): void
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

        $response = $this->withSession([
            'bkash_pending_payment_id' => $existingPayment->id,
        ])->get('/payment/bkash/renew?amount=100&period=monthly');

        $this->assertSame('failed', $existingPayment->fresh()->status);
        $this->assertSame(2, Payment::query()->count());

        $newPayment = Payment::query()->where('id', '!=', $existingPayment->id)->first();
        $this->assertNotNull($newPayment);
        $response->assertRedirectContains('/payment/bkash/waiting/' . $newPayment->id);
    }

    public function test_public_renewal_applies_the_gateway_fee_to_the_payment_amount(): void
    {
        config(['payment.enabled' => true]);
        config(['subscription.monthly_amount' => 2000]);
        config(['subscription.gateway_fee_percent' => 1.5]);
        config(['bkash.app_key' => 'test-key']);
        config(['bkash.app_secret' => 'test-secret']);
        config(['bkash.username' => 'test-user']);
        config(['bkash.password' => 'test-pass']);

        $this->get('/payment/bkash/renew?period=monthly')
            ->assertRedirect();

        $this->assertSame(2030.0, (float) Payment::query()->latest('id')->value('amount'));
    }

    public function test_waiting_page_opens_the_hosted_checkout_in_the_current_tab(): void
    {
        $payment = Payment::create([
            'provider' => 'bkash',
            'amount' => 2030,
            'payment_method' => 'bkash',
            'status' => 'pending',
            'provider_payment_id' => 'PAY-WAITING',
            'metadata' => [
                'bkash_url' => 'https://sandbox.bka.sh/checkout',
            ],
        ]);

        $this->get('/payment/bkash/waiting/' . $payment->id)
            ->assertOk()
            ->assertSee('window.location.replace(bkashUrl)', false)
            ->assertSee('https://sandbox.bka.sh/checkout');
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
