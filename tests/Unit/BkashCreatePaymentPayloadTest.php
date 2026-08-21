<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BkashCreatePaymentPayloadTest extends TestCase
{
    public function test_create_payment_includes_payer_reference_for_live_gateway(): void
    {
        config([
            'bkash.app_key' => 'app-key',
            'bkash.app_secret' => 'app-secret',
            'bkash.username' => 'merchant-user',
            'bkash.password' => 'merchant-pass',
            'bkash.merchant_number' => '01410592638',
            'bkash.token_endpoint' => '/tokenized/checkout/token/grant',
            'bkash.create_payment_endpoint' => '/tokenized/checkout/create',
            'bkash.production_base_url' => 'https://tokenized.pay.bka.sh/v1.2.0-beta',
            'bkash.use_production' => true,
        ]);

        Http::fake([
            'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant' => Http::response([
                'id_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout/create' => Http::response([
                'statusCode' => '0000',
                'statusMessage' => 'Successful',
                'paymentID' => 'PAY-1',
                'bkashURL' => 'https://tokenized.pay.bka.sh/checkout/redirect',
            ], 200),
        ]);

        $payment = new \App\Models\Payment([
            'id' => 123,
            'amount' => 100,
        ]);

        $controller = new class extends \App\Http\Controllers\Payment\BkashController {
            public function exposedCreatePayment($payment): array
            {
                return $this->createPayment($payment);
            }
        };

        $result = $controller->exposedCreatePayment($payment);

        $this->assertSame('https://tokenized.pay.bka.sh/checkout/redirect', $result['redirect_url']);
        $this->assertSame('PAY-1', $result['payment_id']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout/create'
                && ($body['payerReference'] ?? null) === '01410592638';
        });
    }
}
