<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\BkashSetting;

class BkashController extends Controller
{
    /** Working bKash Tokenized Checkout sandbox API root (Grant Token 0000). */
    protected const SANDBOX_BASE_URL = 'https://tokenized.sandbox.bka.sh/v1.2.0-beta';

    protected ?BkashSetting $bkashSetting = null;

    protected function bkashSetting(): ?BkashSetting
    {
        return $this->bkashSetting ??= BkashSetting::first();
    }

    protected function credential(string $key): ?string
    {
        $configValue = config("bkash.{$key}");
        if (is_string($configValue) && trim($configValue) !== '') {
            return trim($configValue);
        }

        $dbValue = $this->bkashSetting()?->{$key} ?? null;
        if (is_string($dbValue) && trim($dbValue) !== '') {
            return trim($dbValue);
        }

        return null;
    }

    protected function sandboxBaseUrl(): string
    {
        $configured = rtrim((string) config('bkash.sandbox_base_url', self::SANDBOX_BASE_URL), '/');
        $host = parse_url($configured, PHP_URL_HOST) ?: '';

        if ($host === 'bka.sh' || $host === 'sandbox.bka.sh' || $configured === '' || str_ends_with($configured, '/v2')) {
            return self::SANDBOX_BASE_URL;
        }

        return $configured;
    }

    protected function productionBaseUrl(): string
    {
        return rtrim((string) config('bkash.production_base_url', 'https://tokenized.pay.bka.sh/v1.2.0-beta'), '/');
    }

    protected function isLiveMode(): bool
    {
        if (filter_var(config('bkash.use_production', false), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        $setting = $this->bkashSetting();
        if ($setting && $setting->is_sandbox === false) {
            return true;
        }

        return app()->environment('production');
    }

    protected function activeBaseUrl(): string
    {
        if ($this->isLiveMode()) {
            return $this->productionBaseUrl();
        }

        return $this->sandboxBaseUrl();
    }

    protected function apiUrl(string $path): string
    {
        return rtrim($this->activeBaseUrl(), '/') . '/' . ltrim($path, '/');
    }

    /**
     * Public HTTPS callback URL. Local *.test / localhost hosts are rejected by bKash (2049).
     * When local, use a sandbox-accepted placeholder; Execute is completed via waiting-page poll.
     */
    protected function callbackUrl(?string $fallback = null): string
    {
        $configured = trim((string) config('bkash.callback_url', ''));
        if ($configured !== '') {
            return $configured;
        }

        $url = $fallback ?: url('/payment/bkash/callback');
        $host = parse_url($url, PHP_URL_HOST) ?: '';

        if ($this->isLocalCallbackHost($host)) {
            return 'https://merchant-callback.example.com/payment/bkash/callback';
        }

        return $url;
    }

    protected function usesLocalCallbackPlaceholder(): bool
    {
        $configured = trim((string) config('bkash.callback_url', ''));
        if ($configured !== '') {
            return false;
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: '';

        return $this->isLocalCallbackHost($host);
    }

    /**
     * When callback URL points to another host (e.g. production domain while testing on hms.test),
     * the app must confirm payment manually via the waiting page.
     */
    protected function needsManualPaymentConfirmation(): bool
    {
        if ($this->usesLocalCallbackPlaceholder()) {
            return true;
        }

        $appHost = strtolower((string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: ''));
        $callbackHost = strtolower((string) (parse_url($this->callbackUrl(), PHP_URL_HOST) ?: ''));

        return $appHost !== '' && $callbackHost !== '' && $appHost !== $callbackHost;
    }

    protected function isLocalCallbackHost(string $host): bool
    {
        $host = strtolower($host);

        return $host === ''
            || $host === 'localhost'
            || $host === '127.0.0.1'
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.localhost');
    }

    /**
     * HTTP client for bKash APIs (30s timeout required by PGW).
     */
    protected function httpClient()
    {
        $request = Http::acceptJson()
            ->asJson()
            ->timeout((int) config('bkash.http_timeout', 30));

        $verifySsl = config('bkash.http_verify_ssl');
        if ($verifySsl === null) {
            $verifySsl = $this->isLiveMode();
        }

        if (! filter_var($verifySsl, FILTER_VALIDATE_BOOLEAN)) {
            $request = $request->withoutVerifying();
        }

        return $request;
    }

    /**
     * Authorized JSON client for Tokenized Checkout APIs.
     * bKash expects raw id_token in Authorization (not "Bearer ...").
     */
    protected function authorizedClient(string $token, string $appKey)
    {
        return $this->httpClient()->withHeaders([
            'Authorization' => $token,
            'X-App-Key' => $appKey,
        ]);
    }

    /**
     * Grant Token for Tokenized Checkout — cached per app_key until expiry.
     *
     * @see https://developer.bka.sh/docs/grant-token-3
     */
    protected function grantToken(): ?string
    {
        $appKey = $this->credential('app_key');
        if (! $appKey) {
            return null;
        }

        $cacheKey = 'bkash_token_' . sha1($appKey);
        $cached = Cache::get($cacheKey);
        if (
            $cached
            && ! empty($cached['token'])
            && isset($cached['expires_at'])
            && now()->lt($cached['expires_at'])
        ) {
            return $cached['token'];
        }

        $username = $this->credential('username');
        $password = $this->credential('password');
        $appSecret = $this->credential('app_secret');
        if (! $username || ! $password || ! $appSecret) {
            return null;
        }

        $response = $this->httpClient()
            ->withHeaders([
                'username' => $username,
                'password' => $password,
            ])
            ->post($this->apiUrl(ltrim(config('bkash.token_endpoint'), '/')), [
                'app_key' => $appKey,
                'app_secret' => $appSecret,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        $token = data_get($data, 'id_token') ?: data_get($data, 'access_token');
        if (! $token) {
            return null;
        }

        $expiresIn = (int) (data_get($data, 'expires_in') ?: 3600);
        $ttlSeconds = max($expiresIn - 30, 30);

        Cache::put($cacheKey, [
            'token' => $token,
            'expires_at' => now()->addSeconds($ttlSeconds),
        ], $ttlSeconds);

        return $token;
    }

    /**
     * Create Payment via Tokenized Checkout sandbox API.
     *
     * @return array{redirect_url: ?string, payment_id: ?string, raw: array|null}
     *
     * @see https://developer.bka.sh/docs/create-payment-2
     */
    protected function createPayment(Payment $payment, ?string $callbackUrl = null): array
    {
        $token = $this->grantToken();
        if (! $token) {
            throw new \RuntimeException('Unable to obtain bKash auth token.');
        }

        $appKey = $this->credential('app_key');
        if (! $appKey) {
            throw new \RuntimeException('bKash app_key is not configured.');
        }

        $callbackUrl = $this->callbackUrl($callbackUrl);
        $payerReference = $this->credential('merchant_number');
        $payload = [
            'mode' => '0011',
            'callbackURL' => $callbackUrl,
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => (string) $payment->id,
        ];

        if ($payerReference) {
            $payload['payerReference'] = $payerReference;
        }

        $response = $this->authorizedClient($token, $appKey)
            ->post($this->apiUrl(ltrim(config('bkash.create_payment_endpoint'), '/')), $payload);

        $data = $response->json() ?? [];
        $statusCode = (string) data_get($data, 'statusCode', '');

        if (! $response->successful() || ($statusCode !== '' && $statusCode !== '0000')) {
            throw new \RuntimeException(
                'bKash create payment failed: ' . (data_get($data, 'statusMessage') ?: $response->body())
            );
        }

        $redirect = data_get($data, 'bkashURL')
            ?: data_get($data, 'redirectURL')
            ?: data_get($data, 'redirect_url');
        $paymentId = data_get($data, 'paymentID') ?: data_get($data, 'paymentId');

        if (! $redirect || ! $paymentId) {
            throw new \RuntimeException('bKash create payment returned no checkout URL.');
        }

        return [
            'redirect_url' => $redirect,
            'payment_id' => $paymentId,
            'raw' => $data,
        ];
    }

    /**
     * Execute Payment after the customer authorizes on bKash checkout.
     *
     * @return array{raw: array|null, successful: bool}
     *
     * @see https://developer.bka.sh/docs/execute-payment-2
     */
    protected function executePayment(string $paymentId): array
    {
        $token = $this->grantToken();
        if (! $token) {
            throw new \RuntimeException('Unable to obtain bKash auth token.');
        }

        $appKey = $this->credential('app_key');
        if (! $appKey) {
            throw new \RuntimeException('bKash app_key is not configured.');
        }

        $response = $this->authorizedClient($token, $appKey)
            ->post($this->apiUrl(ltrim(config('bkash.execute_payment_endpoint'), '/')), [
                'paymentID' => $paymentId,
            ]);

        $data = $response->json() ?? [];
        $statusCode = (string) data_get($data, 'statusCode', '');

        if (! $response->successful() || ($statusCode !== '' && $statusCode !== '0000')) {
            throw new \RuntimeException(
                'bKash execute payment failed: ' . (data_get($data, 'statusMessage') ?: $response->body())
            );
        }

        return [
            'raw' => $data,
            'successful' => true,
        ];
    }

    /**
     * Query Payment status (use when execute response is delayed or inconclusive).
     *
     * @return array{raw: array|null, payment_id: ?string, transaction_status: ?string, trx_id: ?string}
     */
    protected function queryPayment(string $paymentId): array
    {
        $token = $this->grantToken();
        if (! $token) {
            throw new \RuntimeException('Unable to obtain bKash auth token.');
        }

        $appKey = $this->credential('app_key');
        if (! $appKey) {
            throw new \RuntimeException('bKash app_key is not configured.');
        }

        $response = $this->authorizedClient($token, $appKey)
            ->post($this->apiUrl(ltrim(config('bkash.query_payment_endpoint'), '/')), [
                'paymentID' => $paymentId,
            ]);

        $data = $response->json() ?? [];
        $statusCode = (string) data_get($data, 'statusCode', '');

        if (! $response->successful() || ($statusCode !== '' && $statusCode !== '0000')) {
            throw new \RuntimeException(
                'bKash query payment failed: ' . (data_get($data, 'statusMessage') ?: $response->body())
            );
        }

        return [
            'raw' => $data,
            'payment_id' => data_get($data, 'paymentID') ?: data_get($data, 'paymentId'),
            'transaction_status' => data_get($data, 'transactionStatus'),
            'trx_id' => data_get($data, 'trxID') ?: data_get($data, 'trxId'),
        ];
    }

    protected function simulateCheckoutUrl(Payment $payment, ?string $simulateRouteName = null): string
    {
        $routeName = $simulateRouteName ?: 'payment.bkash.simulate.public.page';
        $candidates = [];
        if (! str_ends_with($routeName, '.public')) {
            $candidates[] = $routeName . '.public';
            $candidates[] = 'backend.' . $routeName . '.public';
        }
        $candidates[] = $routeName;
        $candidates[] = 'backend.' . $routeName;

        foreach (array_unique($candidates) as $candidate) {
            if (\Illuminate\Support\Facades\Route::has($candidate)) {
                return route($candidate, ['payment' => $payment->id]);
            }
        }

        return url('/payment/bkash/simulate-public/' . $payment->id);
    }

    protected function addGatewayFee(float $baseAmount): float
    {
        $feePercent = (float) config('subscription.gateway_fee_percent', 1.5);

        return round($baseAmount * (1 + ($feePercent / 100)), 2);
    }

    protected function activateSubscription(Payment $payment, ?string $providerPaymentId = null): Subscription
    {
        $period = data_get($payment->metadata, 'period', 'monthly');
        $months = max(1, (int) data_get($payment->metadata, 'months', 1));
        $sub = Subscription::ensureExists();
        $sub->extendForPeriod($period, $months);
        $sub->last_payment_id = $providerPaymentId ?: $payment->provider_payment_id;
        $sub->save();
        Subscription::clearCurrentCache();

        return $sub;
    }

    protected function invoiceUrl(Payment $payment): string
    {
        return url('/payment/bkash/invoice/' . $payment->id);
    }

    protected function isValidPendingPayment(?Payment $payment): bool
    {
        if (! $payment || $payment->provider !== 'bkash') {
            return false;
        }

        // Only consider a payment valid for reuse if it is in 'pending' state
        // (meaning createPayment returned a hosted checkout URL) and it has
        // a provider payment id. Do not reuse 'initiated' records because a
        // previously cancelled/abandoned checkout should start a fresh one.
        if ($payment->status !== 'pending') {
            return false;
        }

        if (empty($payment->provider_payment_id)) {
            return false;
        }

        if ($payment->created_at && $payment->created_at->lt(now()->subMinutes(30))) {
            return false;
        }

        return true;
    }

    protected function isBkashPaymentSuccessful(array $data): bool
    {
        $statusCode = (string) data_get($data, 'statusCode');
        $trxStatus = trim((string) (data_get($data, 'transactionStatus') ?: data_get($data, 'transaction_status')));
        $statusMessage = trim((string) (data_get($data, 'statusMessage') ?: data_get($data, 'status_message')));

        if ($trxStatus !== '') {
            return in_array(strtolower($trxStatus), ['completed', 'success', 'successful', 'paid', 'done'], true);
        }

        if ($statusCode === '0000') {
            return $statusMessage === '' || (bool) preg_match('/success|complete|paid|approved/i', $statusMessage);
        }

        return false;
    }

    public function initiate(Request $request)
    {
        $request->validate([
            'amount' => 'nullable|numeric|min:0',
        ]);

        $setting = BkashSetting::first();
        $amount = $request->input('amount', $setting?->monthly_amount ?? 0);
        $renewalMonths = 1;

        if (! config('payment.enabled')) {
            return redirect()->back()->with('errorMessage', 'Payments are disabled by system configuration.');
        }

        $bkashEnabled = config('payment.enabled') && ($setting ? ($setting->is_enabled ?? false) : true);
        if (! $bkashEnabled) {
            return redirect()->back()->with('errorMessage', 'bKash payments are not enabled.');
        }

        $subscription = Subscription::getCurrent();
        if ($subscription && ! $request->boolean('admin_test') && ! $subscription->canRenew()) {
            return redirect()->back()->with('errorMessage', 'Renewal is available after ' . $subscription->expires_at->toDateString() . '.');
        }
        $renewalMonths = $subscription ? $subscription->renewalMonthsDue() : 1;
        $baseAmount = (float) config('subscription.monthly_amount', $setting?->monthly_amount ?? 0) * $renewalMonths;
        $amount = $this->addGatewayFee($baseAmount);
        if ((float) $amount <= 0) {
            return redirect()->back()->with('errorMessage', 'Invalid amount.');
        }

        $payment = Payment::create([
            'provider' => 'bkash',
            'amount' => $amount,
            'payment_method' => 'bkash',
            'status' => 'initiated',
            'metadata' => [
                'period' => 'monthly',
                'months' => $renewalMonths,
                'base_amount' => round($baseAmount, 2),
                'gateway_fee_percent' => (float) config('subscription.gateway_fee_percent', 1.5),
                'approval_token' => \Illuminate\Support\Str::random(40),
            ],
        ]);

        try {
            $result = $this->createPayment($payment);

            if (! empty($result['payment_id'])) {
                $payment->provider_payment_id = $result['payment_id'];
                $meta = $payment->metadata ?? [];
                $meta['bkash_url'] = $result['redirect_url'] ?? null;
                $payment->metadata = $meta;
                $payment->status = 'pending';
                $payment->save();
            }

            if (! empty($result['redirect_url'])) {
                if ($this->needsManualPaymentConfirmation()) {
                    return redirect()->route('backend.payment.bkash.waiting', ['payment' => $payment->id]);
                }

                return redirect()->away($result['redirect_url']);
            }

            return redirect()->back()->with('errorMessage', 'No redirect URL returned from bKash service');
        } catch (\Exception $e) {
            $payment->status = 'failed';
            $payment->metadata = array_merge($payment->metadata ?? [], ['error' => $e->getMessage()]);
            $payment->save();

            return redirect()->back()->with('errorMessage', 'Payment initiation failed: ' . $e->getMessage());
        }
    }

    /**
     * Simulate approval (sandbox) — marks payment successful and activates subscription.
     */
    public function simulateApprove(Payment $payment)
    {
        if (! config('payment.enabled')) {
            return redirect()->route('settings.payment.bkash')->with('errorMessage', 'Payments are disabled by system configuration.');
        }

        if ($payment->status === 'success') {
            return redirect()->route('settings.payment.bkash')->with('successMessage', 'Payment already completed.');
        }

        $payment->status = 'success';
        $payment->provider_payment_id = $payment->provider_payment_id ?: ('SIM-' . $payment->id);
        $payment->save();

        $sub = Subscription::ensureExists();
        $months = max(1, (int) data_get($payment->metadata, 'months', 1));
        $sub->extendForPeriod('monthly', $months);
        $sub->last_payment_id = $payment->provider_payment_id;
        $sub->save();
        Subscription::clearCurrentCache();

        return redirect()->route('settings.payment.bkash')->with('successMessage', 'Simulated payment applied. Transaction ID: ' . $payment->provider_payment_id . '. Subscription active until ' . $sub->expires_at->toDateString());
    }

    /**
     * Public initiation endpoint for renewing subscription from login page (GET)
     * Example: /payment/bkash/renew?amount=100&email=admin@example.com
     */
    public function publicInitiate(Request $request)
    {
        $request->validate([
            'amount' => 'nullable|numeric|min:0',
        ]);

        $setting = BkashSetting::first();
        $period = $request->input('period', config('subscription.default_period', 'monthly'));
        $period = in_array($period, ['monthly', 'yearly'], true) ? $period : 'monthly';

        $pendingPaymentId = $request->session()->get('bkash_pending_payment_id');
        if ($pendingPaymentId) {
            $pendingPayment = Payment::find($pendingPaymentId);

            // A hosted checkout can become invalid after its tab is closed or
            // the user returns to Login. Always start a fresh session on Pay Now.
            if ($pendingPayment && in_array($pendingPayment->status, ['initiated', 'pending'], true)) {
                $pendingPayment->status = 'failed';
                $pendingPayment->metadata = array_merge($pendingPayment->metadata ?? [], [
                    'cancelled_reason' => 'replaced_by_new_checkout',
                ]);
                $pendingPayment->save();
            }
            $request->session()->forget('bkash_pending_payment_id');
        }

        if ($request->filled('amount')) {
            // Client amount is ignored — always compute server-side to prevent tampering.
        }

        $loginRoute = \Illuminate\Support\Facades\Route::has('backend.auth.login2') ? 'backend.auth.login2' : (\Illuminate\Support\Facades\Route::has('auth.login2') ? 'auth.login2' : 'home');

        if (! config('payment.enabled')) {
            return redirect()->route($loginRoute)->with('errorMessage', 'Payments are disabled by system configuration.');
        }

        $bkashEnabled = config('payment.enabled') && ($setting ? ($setting->is_enabled ?? false) : true);
        if (! $bkashEnabled) {
            return redirect()->route($loginRoute)->with('errorMessage', 'bKash payments are not enabled.');
        }

        // Block only when subscription is currently active (sandbox testing can pay when inactive).
        $subscription = Subscription::getCurrent();
        if ($subscription && ! $subscription->canRenew()) {
            return redirect()->route($loginRoute)->with(
                'successMessage',
                'Subscription is already active until ' . optional($subscription->expires_at)->toDateString() . '. You can log in now.'
            );
        }

        $renewalMonths = $period === 'monthly' && $subscription ? $subscription->renewalMonthsDue() : 1;
        $baseAmount = $period === 'yearly'
            ? (float) config('subscription.yearly_amount', $setting?->monthly_amount ?? 0)
            : (float) config('subscription.monthly_amount', $setting?->monthly_amount ?? 0) * $renewalMonths;
        $amount = $this->addGatewayFee($baseAmount);

        if ((float) $amount <= 0) {
            return redirect()->route($loginRoute)->with('errorMessage', 'Invalid amount.');
        }

        $payment = Payment::create([
            'provider' => 'bkash',
            'amount' => $amount,
            'payment_method' => 'bkash',
            'status' => 'initiated',
            'metadata' => [
                'period' => $period,
                'months' => $renewalMonths,
                'base_amount' => round($baseAmount, 2),
                'gateway_fee_percent' => (float) config('subscription.gateway_fee_percent', 1.5),
                'approval_token' => \Illuminate\Support\Str::random(40),
            ],
        ]);

        $request->session()->put('bkash_pending_payment_id', $payment->id);

        try {
            $result = $this->createPayment($payment);

            if (! empty($result['payment_id'])) {
                $payment->provider_payment_id = $result['payment_id'];
                $meta = $payment->metadata ?? [];
                $meta['bkash_url'] = $result['redirect_url'] ?? null;
                $payment->metadata = $meta;
                $payment->status = 'pending';
                $payment->save();
            }

            if (! empty($result['redirect_url'])) {
                if ($this->needsManualPaymentConfirmation()) {
                    $request->session()->put('bkash_pending_payment_id', $payment->id);

                    return redirect()->route('backend.payment.bkash.waiting', ['payment' => $payment->id]);
                }

                return redirect()->away($result['redirect_url']);
            }

            if (! $this->isLiveMode()) {
                $simulateRoute = Route::has('backend.payment.bkash.simulate.public.page')
                    ? 'backend.payment.bkash.simulate.public.page'
                    : (Route::has('payment.bkash.simulate.public.page') ? 'payment.bkash.simulate.public.page' : null);

                if ($simulateRoute) {
                    return redirect()->route($simulateRoute, ['payment' => $payment->id])
                        ->with('successMessage', 'bKash checkout is not available right now, so the local payment simulation page was opened instead.');
                }
            }

            return redirect()->route($loginRoute)->with('errorMessage', 'No redirect URL returned from bKash service');
        } catch (\Exception $e) {
            $payment->status = 'failed';
            $payment->metadata = array_merge($payment->metadata ?? [], ['error' => $e->getMessage()]);
            $payment->save();

            report($e);

            if (! $this->isLiveMode()) {
                $simulateRoute = Route::has('backend.payment.bkash.simulate.public.page')
                    ? 'backend.payment.bkash.simulate.public.page'
                    : (Route::has('payment.bkash.simulate.public.page') ? 'payment.bkash.simulate.public.page' : null);

                if ($simulateRoute) {
                    return redirect()->route($simulateRoute, ['payment' => $payment->id])
                        ->with('successMessage', 'bKash checkout could not be started, so the local payment simulation page was opened instead.');
                }
            }

            return redirect()->route($loginRoute)->with('errorMessage', 'Payment gateway could not be opened. Check bKash settings and try again.');
        }
    }

    /**
     * Confirm a pending sandbox payment started from the login page.
     */
    public function confirmPending(Request $request)
    {
        $loginRoute = \Illuminate\Support\Facades\Route::has('backend.auth.login2')
            ? 'backend.auth.login2'
            : 'auth.login2';

        $paymentId = $request->session()->get('bkash_pending_payment_id')
            ?: $request->input('payment_id');

        $payment = $paymentId ? Payment::find($paymentId) : null;
        if (! $payment || ! $this->isValidPendingPayment($payment)) {
            $request->session()->forget('bkash_pending_payment_id');
            $message = 'No pending bKash payment found. Click Pay Monthly again.';

            if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'ok' => false,
                    'done' => false,
                    'message' => $message,
                    'redirect' => route($loginRoute),
                ], 404);
            }

            return redirect()->route($loginRoute)->with('errorMessage', $message);
        }

        if ($payment->status === 'failed') {
            $request->session()->forget('bkash_pending_payment_id');
            $message = 'This payment was cancelled or failed. Please start a new payment.';

            if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'ok' => false,
                    'done' => false,
                    'message' => $message,
                    'redirect' => route($loginRoute),
                ], 409);
            }

            return redirect()->route($loginRoute)->with('errorMessage', $message);
        }

        $response = $this->waitingPoll($request->merge(['confirm' => 1]), $payment);
        $data = $response->getData(true);

        if (! empty($data['done'])) {
            $request->session()->forget('bkash_pending_payment_id');

            if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'ok' => true,
                    'done' => true,
                    'message' => $data['message'] ?? 'Payment successful. You can log in now.',
                    'invoiceUrl' => $data['invoiceUrl'] ?? null,
                    'redirect' => route($loginRoute),
                ]);
            }

            // Non-JSON: redirect to invoice if available, otherwise login
            if (! empty($data['invoiceUrl'])) {
                return redirect($data['invoiceUrl']);
            }

            return redirect()->route($loginRoute)->with('successMessage', $data['message'] ?? 'Payment successful. You can log in now.');
        }

        $message = $data['message'] ?? 'Payment not confirmed yet. Finish wallet/OTP/PIN on bKash, then confirm again.';

        if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'ok' => false,
                'done' => false,
                'message' => $message,
                'redirect' => route($loginRoute),
            ], 409);
        }

        return redirect()->route($loginRoute)->with('errorMessage', $message);
    }

    /**
     * Local/sandbox waiting page: open bKash checkout, then confirm manually.
     * Do NOT auto-execute — early execute causes "Invalid Payment State" on bKash UI.
     */
    public function waiting(Payment $payment)
    {
        $loginRoute = \Illuminate\Support\Facades\Route::has('backend.auth.login2')
            ? 'backend.auth.login2'
            : 'auth.login2';

        if ($payment->status === 'success') {
            // Show invoice instead of login page
            return redirect($this->invoiceUrl($payment));
        }

        if ($payment->status === 'failed') {
            session()->forget('bkash_pending_payment_id');
            return redirect()->route($loginRoute)->with('errorMessage', 'This payment was cancelled or failed. Please start a new payment.');
        }

        $bkashUrl = data_get($payment->metadata, 'bkash_url');
        if (! $bkashUrl || ! $payment->provider_payment_id) {
            return redirect()->route($loginRoute)->with('errorMessage', 'Missing bKash checkout session. Please try Pay Monthly again.');
        }

        return view('payment.bkash.waiting', [
            'payment' => $payment,
            'bkashUrl' => $bkashUrl,
            'confirmUrl' => route('backend.payment.bkash.waiting.poll', ['payment' => $payment->id, 'confirm' => 1]),
            'loginUrl' => route($loginRoute),
        ]);
    }

    /**
     * Confirm endpoint: call execute only after the customer finished on bKash.
     */
    public function waitingPoll(Request $request, Payment $payment)
    {
        if ($payment->status === 'success') {
            return response()->json([
                'ok' => true,
                'done' => true,
                'message' => 'Payment already completed.',
                'redirect' => route(
                    \Illuminate\Support\Facades\Route::has('backend.auth.login2') ? 'backend.auth.login2' : 'auth.login2'
                ),
            ]);
        }

        if ($payment->status === 'failed') {
            $request->session()->forget('bkash_pending_payment_id');
            return response()->json([
                'ok' => false,
                'done' => false,
                'message' => 'This payment was cancelled or failed. Please start a new payment.',
                'redirect' => route(\Illuminate\Support\Facades\Route::has('backend.auth.login2') ? 'backend.auth.login2' : 'auth.login2'),
            ], 409);
        }

        // Never auto-execute. Early execute invalidates the hosted checkout session.
        if (! $request->boolean('confirm')) {
            return response()->json([
                'ok' => true,
                'done' => false,
                'message' => 'Open bKash, finish payment, then click Confirm.',
            ]);
        }

        $paymentId = $payment->provider_payment_id;
        if (! $paymentId) {
            return response()->json(['ok' => false, 'done' => false, 'message' => 'Missing payment ID'], 422);
        }

        try {
            $exec = $this->executePayment($paymentId);
            $raw = $exec['raw'] ?? [];
        } catch (\Exception $e) {
            try {
                $query = $this->queryPayment($paymentId);
                $raw = $query['raw'] ?? [];
            } catch (\Exception $queryError) {
                return response()->json([
                    'ok' => false,
                    'done' => false,
                    'message' => 'Payment not confirmed yet. Finish wallet/OTP/PIN on bKash, then try Confirm again.',
                    'detail' => $e->getMessage(),
                ], 409);
            }
        }

        if (! $this->isBkashPaymentSuccessful($raw ?? [])) {
            return response()->json([
                'ok' => false,
                'done' => false,
                'message' => 'bKash has not completed this payment yet. Finish on bKash, then Confirm again.',
                'transactionStatus' => data_get($raw, 'transactionStatus'),
                'statusMessage' => data_get($raw, 'statusMessage'),
            ], 409);
        }

        $payment->status = 'success';
        $payment->provider_payment_id = data_get($raw, 'paymentID') ?: $paymentId;
        $invoiceNumber = 'TOAMED-INV-' . str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);
        $payment->metadata = array_merge($payment->metadata ?? [], [
            'trx_id' => data_get($raw, 'trxID') ?: data_get($raw, 'trxId'),
            'bkash_response' => $raw,
            'invoice_number' => $invoiceNumber,
        ]);
        $payment->save();

        $sub = $this->activateSubscription($payment, $payment->provider_payment_id);
        $loginRoute = \Illuminate\Support\Facades\Route::has('backend.auth.login2') ? 'backend.auth.login2' : 'auth.login2';

        return response()->json([
            'ok' => true,
            'done' => true,
            'message' => 'Payment successful. You can log in now.',
            'invoiceNumber' => $invoiceNumber,
            'invoiceUrl' => route('backend.payment.bkash.invoice', ['payment' => $payment->id]),
            'redirect' => route($loginRoute),
        ]);
    }

    /**
     * Public simulate approval endpoint (sandbox) — marks payment successful and activates subscription,
     * then redirects to login page with success message.
     */
    public function publicSimulateApprove(Request $request, Payment $payment)
    {
        $loginRoute = \Illuminate\Support\Facades\Route::has('backend.auth.login2') ? 'backend.auth.login2' : 'auth.login2';

        if (! config('payment.enabled')) {
            return redirect()->route($loginRoute)->with('errorMessage', 'Payments are disabled by system configuration.');
        }

        if ($payment->status === 'success') {
            $request->session()->forget('bkash_pending_payment_id');
            return redirect($this->invoiceUrl($payment));
        }

        $token = $request->input('approval_token');
        $stored = data_get($payment->metadata, 'approval_token');
        if (! $token || ! $stored || $token !== $stored) {
            return redirect()->route($loginRoute)->with('errorMessage', 'Invalid or missing approval token.');
        }

        $payment->status = 'success';
        $payment->provider_payment_id = $payment->provider_payment_id ?: ('SIM-' . $payment->id);
        $meta = $payment->metadata ?? [];
        unset($meta['approval_token']);
        $invoiceNumber = 'TOAMED-INV-' . str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);
        $meta['invoice_number'] = $invoiceNumber;
        $payment->metadata = $meta;
        $payment->save();

        $period = $payment->metadata['period'] ?? ($meta['period'] ?? 'monthly');
        $sub = Subscription::ensureExists();
        $months = max(1, (int) data_get($payment->metadata, 'months', 1));
        $sub->extendForPeriod($period, $months);
        $sub->last_payment_id = $payment->provider_payment_id;
        $sub->save();
        Subscription::clearCurrentCache();

        return redirect($this->invoiceUrl($payment));
    }

    public function publicUnsubscribe(Request $request)
    {
        $loginRoute = \Illuminate\Support\Facades\Route::has('backend.auth.login2') ? 'backend.auth.login2' : 'auth.login2';
        $sub = Subscription::ensureExists();

        $sub->is_active = false;
        $sub->expires_at = now();
        $sub->save();
        Subscription::clearCurrentCache();

        return redirect()->route($loginRoute)->with('successMessage', 'Subscription has been cancelled. You can renew anytime from the login page.');
    }

    public function publicSimulatePage(Payment $payment)
    {
        if (! config('payment.enabled')) {
            return redirect()->route('backend.auth.login2')->with('errorMessage', 'Payments are disabled by system configuration.');
        }

        if ($payment->status === 'success') {
            return redirect()->route('backend.auth.login2')->with('successMessage', 'Payment already completed. You can log in now.');
        }

        if ($payment->status === 'failed') {
            return redirect()->route('backend.auth.login2')->with('errorMessage', 'This payment was cancelled or failed. Please start a new payment.');
        }

        if (! in_array($payment->status, ['initiated', 'pending', null], true)) {
            return redirect()->route('backend.auth.login2')->with('successMessage', 'Payment already completed. You can log in now.');
        }

        return view('payment.bkash.simulate-public', [
            'payment' => $payment,
            'loginRoute' => \Illuminate\Support\Facades\Route::has('backend.auth.login2') ? 'backend.auth.login2' : 'auth.login2',
        ]);
    }

    public function paymentInvoice(Payment $payment)
    {
        $invoiceNumber = data_get($payment->metadata, 'invoice_number')
            ?: 'TOAMED-INV-' . str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);
        $transactionId = data_get($payment->metadata, 'trx_id')
            ?: data_get($payment->metadata, 'bkash_response.trxID')
            ?: data_get($payment->metadata, 'bkash_response.trxId');

        return view('payment.bkash.invoice', [
            'payment' => $payment,
            'invoiceNumber' => $invoiceNumber,
            'transactionId' => $transactionId,
            'loginUrl' => url('/login'),
        ]);
    }

    /**
     * bKash Tokenized Checkout callback — execute (or query) payment then activate subscription.
     */
    public function callback(Request $request)
    {
        $loginRoute = \Illuminate\Support\Facades\Route::has('backend.auth.login2')
            ? 'backend.auth.login2'
            : (\Illuminate\Support\Facades\Route::has('auth.login2') ? 'auth.login2' : 'home');

        $providerPaymentId = $request->input('paymentID') ?? $request->input('payment_id');
        $status = strtolower((string) $request->input('status', ''));
        $pendingPaymentId = $request->session()->get('bkash_pending_payment_id');
        $merchantInvoiceNumber = $request->input('merchantInvoiceNumber')
            ?? $request->input('merchant_invoice_number')
            ?? $request->input('merchantInvoice')
            ?? null;

        if (! $providerPaymentId && ! $merchantInvoiceNumber) {
            return redirect()->route($loginRoute)->with('errorMessage', 'Missing bKash payment ID.');
        }

        $payment = null;

        if ($providerPaymentId) {
            $payment = Payment::where('provider_payment_id', $providerPaymentId)->first();
        }

        if (! $payment && $merchantInvoiceNumber) {
            $payment = Payment::whereKey((int) $merchantInvoiceNumber)->first();
        }

        if (! $payment && $pendingPaymentId) {
            $payment = Payment::find($pendingPaymentId);
        }

        if (! $payment && $providerPaymentId) {
            $payment = Payment::where('id', $providerPaymentId)->first();
        }

        if (! $payment && $merchantInvoiceNumber) {
            $payment = Payment::whereJsonContains('metadata->invoice_number', (string) $merchantInvoiceNumber)
                ->first();
        }

        if (! $payment) {
            return redirect()->route($loginRoute)->with('errorMessage', 'Payment record not found.');
        }

        $request->session()->put('bkash_pending_payment_id', $payment->id);

        if ($payment->status === 'success') {
            $request->session()->forget('bkash_pending_payment_id');

            return redirect($this->invoiceUrl($payment));
        }

        if (in_array($status, ['cancel', 'failure', 'failed'], true)) {
            $payment->status = 'failed';
            $payment->metadata = array_merge($payment->metadata ?? [], ['bkash_callback' => $request->all()]);
            $payment->save();
            $request->session()->forget('bkash_pending_payment_id');

            return redirect()->route($loginRoute)->with('errorMessage', 'bKash payment was not completed.');
        }

        $raw = null;

        $executeId = $providerPaymentId ?: $payment->provider_payment_id;

        try {
            $exec = $this->executePayment((string) $executeId);
            $raw = $exec['raw'] ?? [];
        } catch (\Throwable $e) {
            try {
                $query = $this->queryPayment((string) $executeId);
                $raw = $query['raw'] ?? [];
            } catch (\Throwable $queryError) {
                report($queryError);
                $payment->metadata = array_merge($payment->metadata ?? [], [
                    'bkash_callback' => $request->all(),
                    'error' => $e->getMessage(),
                    'query_error' => $queryError->getMessage(),
                ]);
                $payment->save();

                return redirect()->route($loginRoute)->with('errorMessage', 'Unable to confirm bKash payment. Please contact support with Payment ID ' . $payment->id . '.');
            }
        }

        if (! $this->isBkashPaymentSuccessful($raw ?? [])) {
            $payment->status = 'failed';
            $payment->metadata = array_merge($payment->metadata ?? [], [
                'bkash_callback' => $request->all(),
                'bkash_response' => $raw,
            ]);
            $payment->save();

            return redirect()->route($loginRoute)->with('errorMessage', 'bKash payment was not successful.');
        } else {
            $payment->status = 'success';
            $payment->provider_payment_id = data_get($raw, 'paymentID') ?: $providerPaymentId;
            $invoiceNumber = 'TOAMED-INV-' . str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);
            $payment->metadata = array_merge($payment->metadata ?? [], [
                'trx_id' => data_get($raw, 'trxID') ?: data_get($raw, 'trxId'),
                'bkash_response' => $raw,
                'invoice_number' => $invoiceNumber,
            ]);
            $payment->save();

            $this->activateSubscription($payment, $payment->provider_payment_id);
            $request->session()->forget('bkash_pending_payment_id');

            // Redirect to invoice page. The invoice page notifies the opener (login tab)
            // to reload and auto-closes after print.
            return redirect($this->invoiceUrl($payment));
        }
    }

    /**
     * Webhook/callback endpoint for payment gateway to mark payment success.
     * Expects JSON with `provider_payment_id` (or `payment_id`) and `status`.
     */
    public function webhook(Request $request)
    {
        $providerPaymentId = $request->input('provider_payment_id') ?? $request->input('payment_id');
        $status = $request->input('status') ?? null;

        if (! $providerPaymentId) {
            return response('Missing payment id', 400);
        }

        $payment = Payment::where('provider_payment_id', $providerPaymentId)
            ->orWhere('id', $providerPaymentId)
            ->first();

        if (! $payment) {
            return response('Payment not found', 404);
        }

        if ($status === 'success') {
            $payment->status = 'success';
            $payment->save();

            $period = $payment->metadata['period'] ?? 'monthly';
            $months = max(1, (int) data_get($payment->metadata, 'months', 1));
            $sub = Subscription::ensureExists();
            $sub->extendForPeriod($period, $months);
            $sub->last_payment_id = $payment->provider_payment_id;
            $sub->save();

            return response('OK', 200);
        }

        // For other statuses, just persist metadata
        $payment->metadata = array_merge($payment->metadata ?? [], $request->all());
        $payment->save();

        return response('Ignored', 200);
    }

    /**
     * Admin/manual endpoint to mark a payment as ready (manual override)
     */
    public function markReady(Payment $payment)
    {
        if ($payment->status === 'success') {
            return back()->with('successMessage', 'Payment already marked as successful.');
        }

        $payment->status = 'success';
        $payment->save();

        $period = $payment->metadata['period'] ?? 'monthly';
        $months = max(1, (int) data_get($payment->metadata, 'months', 1));
        $sub = Subscription::ensureExists();
        $sub->extendForPeriod($period, $months);
        $sub->last_payment_id = $payment->provider_payment_id;
        $sub->save();

        return back()->with('successMessage', 'Payment applied and subscription activated until ' . $sub->expires_at->toDateString());
    }
}
