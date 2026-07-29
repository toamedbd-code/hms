<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\BkashSetting;
use App\Models\Payment;
use Illuminate\Support\Facades\Cache;

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

        $base = rtrim(config('bkash.sandbox_base_url'), '/');
        $parsed = parse_url($base);
        $host = $parsed['host'] ?? '';
        $scheme = $parsed['scheme'] ?? 'https';

        // Official sandbox API host is tokenized.sandbox.bka.sh.
        // The public bka.sh site is only a landing page and should not be used
        // as the API base for token/create calls.
        if ($host === 'bka.sh' || $host === 'sandbox.bka.sh') {
            return "$scheme://tokenized.sandbox.bka.sh";
        }

        return $base;
    }

    protected function shouldUseSandbox(): bool
    {
        if (app()->environment('production')) {
            return false;
        }

        return true;
    }

    protected function tokenEndpoint()
    {
        return config('bkash.token_endpoint');
    }

    protected function tokenEndpointCandidates(): array
    {
        $configured = $this->tokenEndpoint();
        $candidates = [
            $configured,
            '/tokenized/checkout/token',
            '/tokenized/checkout/token/grant',
            '/checkout/token',
            '/checkout/token/grant',
            '/token',
            '/v2/tokenized/checkout/token',
            '/v2/tokenized/checkout/token/grant',
            '/v2/checkout/token',
            '/v2/checkout/token/grant',
            '/v2/token',
            '/v1.0.0-beta/tokenized/checkout/token',
            '/v1.0.0-beta/tokenized/checkout/token/grant',
        ];

        return array_values(array_unique(array_filter(array_map(function ($endpoint) {
            if (! is_string($endpoint) || trim($endpoint) === '') {
                return null;
            }

            return '/' . ltrim(trim($endpoint), '/');
        }, $candidates))));
    }

    protected function buildApiBaseCandidates(): array
    {
        $base = rtrim($this->baseUrl(), '/');
        $candidates = [$base];

        $parsed = parse_url($base);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';

        if (str_ends_with($host, 'bka.sh')) {
            $variants = [];
            if ($host === 'bka.sh' || $host === 'sandbox.bka.sh' || $host === 'tokenized.sandbox.bka.sh') {
                $variants = [
                    "$scheme://tokenized.sandbox.bka.sh",
                    "$scheme://tokenized.sandbox.bka.sh/v2",
                ];
            }

            foreach ($variants as $variant) {
                if (! in_array($variant, $candidates, true)) {
                    $candidates[] = $variant;
                }
            }
        }

        if (str_ends_with($base, '/v2')) {
            $candidates[] = rtrim($base, '/v2');
        }

        if (str_ends_with($base, '/v1.0.0-beta')) {
            $candidates[] = rtrim($base, '/v1.0.0-beta');
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    protected function getEndpointUrlCandidates(string $endpoint): array
    {
        $urls = [];
        foreach ($this->buildApiBaseCandidates() as $candidateBase) {
            // Avoid duplicating a leading /v2 if the base already contains /v2
            if (str_ends_with(rtrim($candidateBase, '/'), '/v2') && str_starts_with('/' . ltrim($endpoint, '/'), '/v2')) {
                $normalizedEndpoint = preg_replace('#^/v2#', '', '/' . ltrim($endpoint, '/'));
                $urls[] = rtrim($candidateBase, '/') . '/' . ltrim($normalizedEndpoint, '/');
            } else {
                $urls[] = rtrim($candidateBase, '/') . '/' . ltrim($endpoint, '/');
            }
        }

        return array_values(array_unique($urls));
    }

    public function getTokenEndpointCandidates(): array
    {
        $urls = [];
        foreach ($this->buildApiBaseCandidates() as $candidateBase) {
            foreach ($this->tokenEndpointCandidates() as $candidateEndpoint) {
                // avoid producing /v2/v2 when base already contains /v2 and endpoint starts with /v2
                if (str_ends_with(rtrim($candidateBase, '/'), '/v2') && str_starts_with('/' . ltrim($candidateEndpoint, '/'), '/v2')) {
                    $normalized = preg_replace('#^/v2#', '', '/' . ltrim($candidateEndpoint, '/'));
                    $urls[] = rtrim($candidateBase, '/') . '/' . ltrim($normalized, '/');
                } else {
                    $urls[] = rtrim($candidateBase, '/') . '/' . ltrim($candidateEndpoint, '/');
                }
            }
        }

        return array_values(array_unique($urls));
    }

    public function probeTokenEndpoints(): array
    {
        $payload = [
            'app_key' => $this->credential('app_key'),
            'app_secret' => $this->credential('app_secret'),
            'username' => $this->credential('username'),
            'password' => $this->credential('password'),
        ];

        $results = [];
        foreach ($this->getTokenEndpointCandidates() as $url) {
            try {
                $request = Http::acceptJson()->asJson()->timeout(15);
                if ($this->shouldUseAwsSignatureV4($url)) {
                    $headers = $this->awsSignatureHeaders('POST', $url, json_encode($payload));
                    $request = $request->withHeaders($headers);
                }

                $response = $request->post($url, $payload);
                $body = $response->body();
                $json = null;
                try {
                    $json = $response->json();
                } catch (\Throwable $e) {
                }

                $results[] = [
                    'url' => $url,
                    'status' => $response->status(),
                    'successful' => $response->successful(),
                    'body' => $body,
                    'json' => $json,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'url' => $url,
                    'successful' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    protected function createPaymentEndpoint()
    {
        return config('bkash.create_payment_endpoint');
    }

    protected function credential(string $key): ?string
    {
        $dbValue = $this->setting?->{$key} ?? null;
        if (is_string($dbValue) && trim($dbValue) !== '') {
            return trim($dbValue);
        }

        $configValue = config("bkash.{$key}");
        if (is_string($configValue) && trim($configValue) !== '') {
            return trim($configValue);
        }

        return null;
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
        // Prefer to perform a real createPayment call against the configured
        // base URL (sandbox or production) if credentials are available. This
        // lets us use the official bKash sandbox endpoints rather than the
        // internal simulate page when the operator has valid sandbox creds.
        try {
            $result = $this->createPayment($payment);
            if (! empty($result['redirect_url'])) {
                return $result;
            }
        } catch (\Throwable $e) {
            // ignore and fall back to simulate page below
        }

        // Fallback: internal simulate page (public) so local testing still works
        // when the external API is not available.
        $routeName = $simulateRouteName ?: 'payment.bkash.simulate.public.page';
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
            $paths = [
                '/payment/bkash/simulate-public/' . $payment->id,
                '/payment/bkash/simulate/' . $payment->id,
            ];

            $url = url($paths[0]);
        }

        return [
            'redirect_url' => $url,
            'payment_id' => 'SIM-'.$payment->id,
        ];
    }

    /**
     * Grant a bKash auth token and cache it for reuse.
     */
    public function grantToken(): ?string
    {
        // Cache key should vary by configured app_key to avoid cross-tenant collisions
        // and to prevent repeated Grant Token API calls within the same hour.
        $appKey = $this->setting->app_key ?? config('bkash.app_key');
        $cacheKey = 'bkash_token_' . sha1((string) $appKey);
        $cached = Cache::get($cacheKey);
        if ($cached && ! empty($cached['token']) && isset($cached['expires_at']) && now()->lt($cached['expires_at'])) {
            return $cached['token'];
        }

        $payload = [
            'app_key' => $this->credential('app_key'),
            'app_secret' => $this->credential('app_secret'),
            'username' => $this->credential('username'),
            'password' => $this->credential('password'),
        ];

        $candidates = $this->getTokenEndpointCandidates();

        foreach ($candidates as $url) {
            try {
                $request = Http::acceptJson()->asJson()->timeout(30);
                if ($this->shouldUseAwsSignatureV4($url)) {
                    $headers = $this->awsSignatureHeaders('POST', $url, json_encode($payload));
                    $request = $request->withHeaders($headers);
                }

                $resp = $request->post($url, $payload);

                if (! $resp->successful()) {
                    continue;
                }

                $data = $resp->json();
                $token = data_get($data, 'id_token') ?: data_get($data, 'access_token');
                $expiresIn = (int) (data_get($data, 'expires_in') ?: 3600);
                if ($token) {
                    $ttlSeconds = max($expiresIn - 30, 30);
                    Cache::put($cacheKey, [
                        'token' => $token,
                        'expires_at' => now()->addSeconds($ttlSeconds),
                    ], $ttlSeconds);
                    return $token;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    protected function shouldUseAwsSignatureV4(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $normalized = strtolower(trim($path, '/'));

        return str_ends_with($normalized, 'token')
            || str_ends_with($normalized, 'token/grant')
            || str_contains($normalized, 'tokenized/checkout/token');
    }

    protected function awsSignatureHeaders(string $method, string $url, string $payload): array
    {
        $accessKey = $this->credential('app_key');
        $secretKey = $this->credential('app_secret');
        if (! $accessKey || ! $secretKey) {
            return [];
        }

        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '/';
        $queryString = $parsed['query'] ?? '';
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        // RFC1123 Date for `Date` header
        $rfcDate = gmdate('D, d M Y H:i:s \G\M\T');
        $region = config('bkash.signature_region', 'sandbox');
        $service = config('bkash.signature_service', 'tokenized');
        $algorithm = config('bkash.signature_algorithm', 'TOKENIZED4-HMAC-SHA256');
        $credentialScopeSuffix = config('bkash.credential_scope_suffix', 'tokenized4_request');
        $keyPrefix = config('bkash.signature_key_prefix', 'AWS4');
        $payloadHash = hash('sha256', $payload);

        $canonicalHeaders = "accept:application/json\ncontent-type:application/json\ndate:$rfcDate\nhost:$host\nx-amz-content-sha256:$payloadHash\nx-amz-date:$amzDate\nx-sandbox-date:$amzDate\n";
        $signedHeaders = 'accept;content-type;date;host;x-amz-content-sha256;x-amz-date;x-sandbox-date';
        $canonicalRequest = implode("\n", [
            strtoupper($method),
            $path,
            $queryString,
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        $credentialScope = "$dateStamp/$region/$service/{$credentialScopeSuffix}";
        $stringToSign = implode("\n", [
            $algorithm,
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $signingKey = $this->getSignatureKeyGeneric($secretKey, $dateStamp, $region, $service, $keyPrefix, $credentialScopeSuffix);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);
        $authorization = sprintf(
            '%s Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $algorithm,
            $accessKey,
            $credentialScope,
            $signedHeaders,
            $signature
        );

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Date' => $rfcDate,
            'x-amz-date' => $amzDate,
            'x-amz-content-sha256' => $payloadHash,
            'x-sandbox-date' => $amzDate,
            'Authorization' => $authorization,
        ];

        $sessionToken = config('bkash.aws_session_token');
        if ($sessionToken) {
            $headers['x-amz-security-token'] = $sessionToken;
            $headers['x-sandbox-security-token'] = $sessionToken;
        }

        return $headers;
    }

    protected function getSignatureKey(string $key, string $dateStamp, string $regionName, string $serviceName): string
    {
        // Legacy single-purpose method; delegate to generalized implementation
        return $this->getSignatureKeyGeneric($key, $dateStamp, $regionName, $serviceName, 'AWS4', 'aws4_request');
    }

    protected function getSignatureKeyGeneric(string $key, string $dateStamp, string $regionName, string $serviceName, string $keyPrefix, string $credentialScopeSuffix): string
    {
        $kDate = hash_hmac('sha256', $dateStamp, $keyPrefix . $key, true);
        $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
        $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
        return hash_hmac('sha256', $credentialScopeSuffix, $kService, true);
    }

    

    /**
     * Create a payment via bKash API (production flow). Returns ['redirect_url'=>..., 'payment_id'=>...]
     * On failure will fallback to local sandbox createCheckout behaviour.
     */
    public function createPayment(Payment $payment): array
    {
        // try to obtain token
        $token = $this->grantToken();
        if (! $token) {
            // If we can't obtain a token, fall back to a publicly visible
            // simulation URL instead of recursing into createCheckout().
            return [
                'redirect_url' => url('/payment/bkash/simulate-public/' . $payment->id),
                'payment_id' => 'SIM-'.$payment->id,
            ];
        }

        $endpoints = $this->getEndpointUrlCandidates($this->createPaymentEndpoint());

        foreach ($endpoints as $url) {
            try {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->asJson()
                    ->timeout(30)
                    ->post($url, [
                        'amount' => (string) $payment->amount,
                        'merchantInvoiceNumber' => (string) $payment->id,
                    ]);

                $data = $response->json();
                $paymentId = data_get($data, 'paymentID') ?: data_get($data, 'paymentId') ?: data_get($data, 'payment_id');
                $redirect = data_get($data, 'bkashURL')
                    ?: data_get($data, 'redirectURL')
                    ?: data_get($data, 'checkout_url')
                    ?: data_get($data, 'redirect_url')
                    ?: data_get($data, 'url')
                    ?: data_get($data, 'payment_url');

                if ($response->successful() && ! empty($redirect)) {
                    return [
                        'redirect_url' => $redirect,
                        'payment_id' => $paymentId,
                    ];
                }

                if (! empty($paymentId)) {
                    $query = $this->queryPayment($paymentId);
                    if (! empty($query['redirect_url'])) {
                        return $query;
                    }
                }

                if ($response->successful()) {
                    return [
                        'redirect_url' => url('/payment/bkash/simulate-public/' . $payment->id),
                        'payment_id' => $paymentId ?: 'SIM-'.$payment->id,
                    ];
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return [
            'redirect_url' => url('/payment/bkash/simulate-public/' . $payment->id),
            'payment_id' => 'SIM-'.$payment->id,
        ];
    }

    /**
     * Query payment status from bKash (useful as a fallback when create/execute
     * responses are delayed). Returns an array similar to createPayment.
     */
    public function queryPayment(string|int|null $paymentId): array
    {
        if (empty($paymentId)) {
            return [];
        }

        $token = $this->grantToken();
        if (! $token) {
            return [];
        }

        foreach ($this->getEndpointUrlCandidates(config('bkash.query_payment_endpoint')) as $url) {
            try {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->asJson()
                    ->timeout(30)
                    ->post($url, [
                        'paymentID' => (string) $paymentId,
                    ]);

                if (! $response->successful()) {
                    continue;
                }

                $data = $response->json();
                $redirect = data_get($data, 'bkashURL')
                    ?: data_get($data, 'redirectURL')
                    ?: data_get($data, 'checkout_url')
                    ?: data_get($data, 'redirect_url')
                    ?: data_get($data, 'url')
                    ?: data_get($data, 'payment_url');
                $paymentId = data_get($data, 'paymentID') ?: data_get($data, 'paymentId') ?: data_get($data, 'payment_id');

                return [
                    'redirect_url' => $redirect,
                    'payment_id' => $paymentId,
                    'raw' => $data,
                ];
            } catch (\Throwable $e) {
                continue;
            }
        }

        return [];
    }

    /**
     * Execute a payment previously created on bKash.
     * Returns array similar to queryPayment with raw response when successful.
     */
    public function executePayment(string|int|null $paymentId): array
    {
        if (empty($paymentId)) {
            return [];
        }

        $token = $this->grantToken();
        if (! $token) {
            return [];
        }

        foreach ($this->getEndpointUrlCandidates(config('bkash.execute_payment_endpoint')) as $url) {
            try {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->asJson()
                    ->timeout(30)
                    ->post($url, [
                        'paymentID' => (string) $paymentId,
                    ]);

                if (! $response->successful()) {
                    continue;
                }

                $data = $response->json();

                return [
                    'raw' => $data,
                    'successful' => true,
                ];
            } catch (\Throwable $e) {
                continue;
            }
        }

        return [];
    }

    /**
     * Search transaction by trxID (best-effort - tries common search endpoints).
     */
    public function searchTransaction(string $trxId): array
    {
        if (empty($trxId)) {
            return [];
        }

        $token = $this->grantToken();
        if (! $token) {
            return [];
        }

        $candidateEndpoints = [
            '/checkout/payment/search',
            '/checkout/payment/searchTransaction',
            '/checkout/payment/status',
        ];

        foreach ($candidateEndpoints as $endpoint) {
            foreach ($this->getEndpointUrlCandidates($endpoint) as $url) {
                try {
                    $response = Http::withToken($token)
                        ->acceptJson()
                        ->asJson()
                        ->timeout(30)
                        ->post($url, [ 'trxID' => $trxId ]);

                    if (! $response->successful()) {
                        continue;
                    }

                    return [ 'raw' => $response->json(), 'url' => $url ];
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }

        return [];
    }

}
