<?php

namespace Tests\Unit;

use Tests\TestCase;

class BkashControllerBaseUrlTest extends TestCase
{
    public function test_api_uses_production_base_url_when_production_mode_is_enabled(): void
    {
        config([
            'bkash.sandbox_base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            'bkash.production_base_url' => 'https://tokenized.pay.bka.sh/v1.2.0-beta',
            'bkash.use_production' => true,
        ]);

        $controller = new class extends \App\Http\Controllers\Payment\BkashController {
            public function exposedApiUrl(string $path): string
            {
                return $this->apiUrl($path);
            }
        };

        $this->assertSame(
            'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout/create',
            $controller->exposedApiUrl('/tokenized/checkout/create')
        );
    }
}
