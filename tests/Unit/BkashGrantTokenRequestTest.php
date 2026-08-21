<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BkashGrantTokenRequestTest extends TestCase
{
    public function test_grant_token_sends_username_and_password_in_request_body(): void
    {
        config([
            'bkash.app_key' => 'app-key',
            'bkash.app_secret' => 'app-secret',
            'bkash.username' => 'merchant-user',
            'bkash.password' => 'merchant-pass',
            'bkash.token_endpoint' => '/tokenized/checkout/token/grant',
            'bkash.production_base_url' => 'https://tokenized.pay.bka.sh/v1.2.0-beta',
            'bkash.use_production' => true,
        ]);

        Http::fake([
            'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant' => Http::response([
                'id_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
        ]);

        $controller = new class extends \App\Http\Controllers\Payment\BkashController {
            public function exposedGrantToken(): ?string
            {
                return $this->grantToken();
            }
        };

        $this->assertSame('test-token', $controller->exposedGrantToken());

        Http::assertSent(function ($request) {
            $body = $request->data();
            $headers = $request->hasHeader('username') ? $request->header('username') : [];

            return $request->url() === 'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant'
                && ($body['app_key'] ?? null) === 'app-key'
                && ($body['app_secret'] ?? null) === 'app-secret'
                && $headers[0] === 'merchant-user'
                && $request->hasHeader('password')
                && $request->header('password')[0] === 'merchant-pass';
        });
    }
}
