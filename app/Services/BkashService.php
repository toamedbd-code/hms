<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\BkashSetting;
use App\Models\Payment;
use Exception;
use Illuminate\Support\Facades\Route;

class BkashService
{
    protected $setting;

    public function __construct()
    {
        $this->setting = BkashSetting::first();
    }

    protected function baseUrl()
    {
        if ($this->setting && ! $this->setting->is_sandbox) {
            return config('bkash.production_base_url');
        }

        return config('bkash.sandbox_base_url');
    }

    protected function tokenEndpoint()
    {
        return config('bkash.token_endpoint');
    }

    protected function createPaymentEndpoint()
    {
        return config('bkash.create_payment_endpoint');
    }

    /**
     * Create a checkout session. In sandbox mode this returns an internal simulate URL.
     * In production it will attempt to call bKash endpoints (placeholder: requires real endpoints).
     *
     * @param Payment $payment
     * @return array ['redirect_url' => string, 'payment_id' => string|null]
     */
    public function createCheckout(Payment $payment, ?string $simulateRouteName = null): array
    {
        // Sandbox short-circuit: provide an internal simulate URL so local testing works.
        if ($this->setting && $this->setting->is_sandbox) {
            // Resolve a usable simulate route name — routes may be registered under a
            // backend name prefix (see RouteServiceProvider) or have a `.public` variant.
            $routeName = $simulateRouteName ?: 'payment.bkash.simulate.approve';

            // Prefer any public variant first (so unauthenticated public flows hit
            // the `.public` route if present), then fall back to admin variants.
            $candidates = [];
            if (! str_ends_with($routeName, '.public')) {
                $public = $routeName . '.public';
                $candidates[] = $public;
                $candidates[] = 'backend.' . $public;
            }
            $candidates[] = $routeName;
            $candidates[] = 'backend.' . $routeName;

            $candidates = array_values(array_unique($candidates));

            $found = null;
            foreach ($candidates as $c) {
                if (\Illuminate\Support\Facades\Route::has($c)) {
                    $found = $c;
                    break;
                }
            }

            if ($found) {
                $url = route($found, ['payment' => $payment->id]);
            } else {
                // Fallback to commonly declared paths in this app so sandbox still works.
                $paths = [
                    '/payment/bkash/simulate-public/' . $payment->id . '/approve',
                    '/payment/bkash/simulate/' . $payment->id . '/approve',
                ];

                $url = url($paths[0]);
            }

            return [
                'redirect_url' => $url,
                'payment_id' => 'SIM-'.$payment->id,
            ];
        }

        // Production: obtain token then create payment (these endpoints are configurable in config/bkash.php)
        $token = $this->getAuthToken();
        if (! $token) {
            throw new Exception('Failed to obtain bKash auth token. Check configuration.');
        }

        $url = rtrim($this->baseUrl(), '/') . '/' . ltrim($this->createPaymentEndpoint(), '/');

        $response = Http::withToken($token)
            ->post($url, [
                'amount' => (string) $payment->amount,
                'merchantInvoiceNumber' => (string) $payment->id,
            ]);

        if (! $response->successful()) {
            throw new Exception('bKash create payment failed: ' . $response->body());
        }

        $data = $response->json();

        // Attempt common keys - adapt to bKash API response structure
        $paymentId = data_get($data, 'paymentID') ?: data_get($data, 'paymentID');
        $redirect = data_get($data, 'bkashURL') ?: data_get($data, 'redirectURL');

        return [
            'redirect_url' => $redirect,
            'payment_id' => $paymentId,
        ];
    }

    protected function getAuthToken(): ?string
    {
        $base = rtrim($this->baseUrl(), '/');
        $endpoint = '/' . ltrim($this->tokenEndpoint(), '/');
        $url = $base . $endpoint;

        // Many bKash flows require headers/auth specifics; this is a placeholder
        $payload = [
            'app_key' => $this->setting->app_key ?? null,
            'app_secret' => $this->setting->app_secret ?? null,
        ];

        $resp = Http::post($url, $payload);

        if (! $resp->successful()) {
            return null;
        }

        $data = $resp->json();
        return data_get($data, 'id_token') ?: data_get($data, 'access_token');
    }
}
