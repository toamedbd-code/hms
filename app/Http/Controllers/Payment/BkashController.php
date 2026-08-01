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
        $dbValue = $this->bkashSetting()?->{$key} ?? null;
        if (is_string($dbValue) && trim($dbValue) !== '') {
            return trim($dbValue);
        }

        $configValue = config("bkash.{$key}");
        if (is_string($configValue) && trim($configValue) !== '') {
            return trim($configValue);
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

    protected function apiUrl(string $path): string
    {
        return rtrim($this->sandboxBaseUrl(), '/') . '/' . ltrim($path, '/');
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

        if (! config('bkash.http_verify_ssl', false)) {
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

        $response = $this->authorizedClient($token, $appKey)
            ->post($this->apiUrl(ltrim(config('bkash.create_payment_endpoint'), '/')), [
                'mode' => '0011',
                'payerReference' => '01770618575',
                'callbackURL' => $callbackUrl,
                'amount' => number_format((float) $payment->amount, 2, '.', ''),
                'currency' => 'BDT',
                'intent' => 'sale',
                'merchantInvoiceNumber' => (string) $payment->id,
            ]);

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

    protected function activateSubscription(Payment $payment, ?string $providerPaymentId = null): Subscription
    {
        $period = data_get($payment->metadata, 'period', 'monthly');
        $sub = Subscription::ensureExists();
        $sub->is_active = true;
        $sub->expires_at = $period === 'yearly' ? now()->addYear() : now()->addMonth();
        $sub->last_payment_id = $providerPaymentId ?: $payment->provider_payment_id;
        $sub->save();
        Subscription::clearCurrentCache();

        return $sub;
    }

    protected function isBkashPaymentSuccessful(array $data): bool
    {
        $statusCode = data_get($data, 'statusCode');
        $trxStatus = data_get($data, 'transactionStatus');

        return $statusCode === '0000'
            || in_array($trxStatus, ['Completed', 'Success', 'Successful'], true);
    }

    public function initiate(Request $request)
    {
        $request->validate([
            'amount' => 'nullable|numeric|min:0',
        ]);

        $setting = BkashSetting::first();
        $amount = $request->input('amount', $setting?->monthly_amount ?? 0);

        if (! config('payment.enabled')) {
            return redirect()->back()->with('errorMessage', 'Payments are disabled by system configuration.');
        }

        $bkashEnabled = config('payment.enabled') && ($setting ? ($setting->is_enabled ?? false) : true);
        if (! $bkashEnabled) {
            return redirect()->back()->with('errorMessage', 'bKash payments are not enabled.');
        }

        $subscription = Subscription::getCurrent();
        if ($subscription && $subscription->expires_at && Carbon::now()->lt($subscription->expires_at)) {
            return redirect()->back()->with('errorMessage', 'Renewal is available after ' . $subscription->expires_at->toDateString() . '.');
        }
        if ((float) $amount <= 0) {
            return redirect()->back()->with('errorMessage', 'Invalid amount.');
        }

        $payment = Payment::create([
            'provider' => 'bkash',
            'amount' => $amount,
            'payment_method' => 'bkash',
            'status' => 'initiated',
            'metadata' => ['approval_token' => \Illuminate\Support\Str::random(40)],
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
                if ($this->usesLocalCallbackPlaceholder()) {
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
        $sub->is_active = true;
        $sub->expires_at = now()->addMonth();
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

        if ($request->filled('amount')) {
            $amount = $request->input('amount');
        } else {
            if ($period === 'yearly') {
                $amount = config('subscription.yearly_amount', $setting?->monthly_amount ?? 0);
            } else {
                $amount = config('subscription.monthly_amount', $setting?->monthly_amount ?? 0);
            }
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
        if ($subscription && $subscription->isActive()) {
            return redirect()->route($loginRoute)->with(
                'successMessage',
                'Subscription is already active until ' . optional($subscription->expires_at)->toDateString() . '. You can log in now.'
            );
        }

        if ((float) $amount <= 0) {
            return redirect()->route($loginRoute)->with('errorMessage', 'Invalid amount.');
        }

        $payment = Payment::create([
            'provider' => 'bkash',
            'amount' => $amount,
            'payment_method' => 'bkash',
            'status' => 'initiated',
            'metadata' => array_merge(['period' => $period], ['approval_token' => \Illuminate\Support\Str::random(40)]),
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
                // Local *.test cannot receive bKash callback — keep the pending payment id
                // and open the actual bKash checkout URL directly in the new tab.
                if ($this->usesLocalCallbackPlaceholder()) {
                    $request->session()->put('bkash_pending_payment_id', $payment->id);
                }

                return redirect()->away($result['redirect_url']);
            }

            $simulateRoute = Route::has('backend.payment.bkash.simulate.public.page')
                ? 'backend.payment.bkash.simulate.public.page'
                : (Route::has('payment.bkash.simulate.public.page') ? 'payment.bkash.simulate.public.page' : null);

            if ($simulateRoute) {
                return redirect()->route($simulateRoute, ['payment' => $payment->id])
                    ->with('successMessage', 'bKash checkout is not available right now, so the local payment simulation page was opened instead.');
            }

            return redirect()->route($loginRoute)->with('errorMessage', 'No redirect URL returned from bKash service');
        } catch (\Exception $e) {
            $payment->status = 'failed';
            $payment->metadata = array_merge($payment->metadata ?? [], ['error' => $e->getMessage()]);
            $payment->save();

            $simulateRoute = Route::has('backend.payment.bkash.simulate.public.page')
                ? 'backend.payment.bkash.simulate.public.page'
                : (Route::has('payment.bkash.simulate.public.page') ? 'payment.bkash.simulate.public.page' : null);

            if ($simulateRoute) {
                return redirect()->route($simulateRoute, ['payment' => $payment->id])
                    ->with('successMessage', 'bKash checkout could not be started, so the local payment simulation page was opened instead.');
            }

            return redirect()->route($loginRoute)->with('errorMessage', 'Payment initiation failed: ' . $e->getMessage());
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
        if (! $payment) {
            return redirect()->route($loginRoute)->with('errorMessage', 'No pending bKash payment found. Click Pay Monthly again.');
        }

        $response = $this->waitingPoll($request->merge(['confirm' => 1]), $payment);
        $data = $response->getData(true);

        if (! empty($data['done'])) {
            $request->session()->forget('bkash_pending_payment_id');

            return redirect()->route($loginRoute)->with('successMessage', $data['message'] ?? 'Payment successful. You can log in now.');
        }

        return redirect()->route($loginRoute)->with(
            'errorMessage',
            $data['message'] ?? 'Payment not confirmed yet. Finish wallet/OTP/PIN on bKash, then confirm again.'
        );
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
            return redirect()->route($loginRoute)->with('successMessage', 'Payment already completed. You can log in now.');
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
        $payment->metadata = array_merge($payment->metadata ?? [], [
            'trx_id' => data_get($raw, 'trxID') ?: data_get($raw, 'trxId'),
            'bkash_response' => $raw,
        ]);
        $payment->save();

        $sub = $this->activateSubscription($payment, $payment->provider_payment_id);

        return response()->json([
            'ok' => true,
            'done' => true,
            'message' => 'Payment successful. Subscription active until ' . $sub->expires_at->toDateString() . '.',
            'redirect' => route(
                \Illuminate\Support\Facades\Route::has('backend.auth.login2') ? 'backend.auth.login2' : 'auth.login2'
            ),
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
            return redirect()->route($loginRoute)->with('successMessage', 'Payment already completed.');
        }

        $token = $request->input('approval_token');
        $stored = data_get($payment->metadata, 'approval_token');
        if (! $token || ! $stored || $token !== $stored) {
            return redirect()->route($loginRoute)->with('errorMessage', 'Invalid or missing approval token.');
        }

        $payment->status = 'success';
        $payment->provider_payment_id = $payment->provider_payment_id ?: ('SIM-' . $payment->id);
        // remove token to avoid re-use
        $meta = $payment->metadata ?? [];
        unset($meta['approval_token']);
        $payment->metadata = $meta;
        $payment->save();

        $period = $payment->metadata['period'] ?? ($meta['period'] ?? 'monthly');
        $sub = Subscription::ensureExists();
        $sub->is_active = true;
        $sub->expires_at = $period === 'yearly' ? now()->addYear() : now()->addMonth();
        $sub->last_payment_id = $payment->provider_payment_id;
        $sub->save();
        Subscription::clearCurrentCache();

        return redirect()->route($loginRoute)->with('successMessage', 'Simulated payment applied. Transaction ID: ' . $payment->provider_payment_id . '. Subscription active until ' . $sub->expires_at->toDateString());
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

        if (! in_array($payment->status, ['initiated', 'pending', null], true)) {
            return redirect()->route('backend.auth.login2')->with('successMessage', 'This payment has already been processed.');
        }

        return view('payment.bkash.simulate-public', [
            'payment' => $payment,
            'loginRoute' => \Illuminate\Support\Facades\Route::has('backend.auth.login2') ? 'backend.auth.login2' : 'auth.login2',
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

        if (! $providerPaymentId) {
            return redirect()->route($loginRoute)->with('errorMessage', 'Missing bKash payment ID.');
        }

        $payment = Payment::where('provider_payment_id', $providerPaymentId)->first();
        if (! $payment) {
            $payment = Payment::find($request->input('merchantInvoiceNumber'));
        }

        if (! $payment) {
            return redirect()->route($loginRoute)->with('errorMessage', 'Payment record not found.');
        }

        if ($payment->status === 'success') {
            return redirect()->route($loginRoute)->with('successMessage', 'Payment already completed.');
        }

        if (in_array($status, ['cancel', 'failure', 'failed'], true)) {
            $payment->status = 'failed';
            $payment->metadata = array_merge($payment->metadata ?? [], ['bkash_callback' => $request->all()]);
            $payment->save();

            return redirect()->route($loginRoute)->with('errorMessage', 'bKash payment was not completed.');
        }

        $raw = null;

        try {
            $exec = $this->executePayment($providerPaymentId);
            $raw = $exec['raw'] ?? [];
        } catch (\Exception $e) {
            try {
                $query = $this->queryPayment($providerPaymentId);
                $raw = $query['raw'] ?? [];
            } catch (\Exception $queryError) {
                $payment->status = 'failed';
                $payment->metadata = array_merge($payment->metadata ?? [], [
                    'bkash_callback' => $request->all(),
                    'error' => $e->getMessage(),
                    'query_error' => $queryError->getMessage(),
                ]);
                $payment->save();

                return redirect()->route($loginRoute)->with('errorMessage', 'Unable to confirm bKash payment: ' . $e->getMessage());
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
            $payment->metadata = array_merge($payment->metadata ?? [], [
                'trx_id' => data_get($raw, 'trxID') ?: data_get($raw, 'trxId'),
                'bkash_response' => $raw,
            ]);
            $payment->save();

            $sub = $this->activateSubscription($payment, $payment->provider_payment_id);

            return redirect()->route($loginRoute)->with(
                'successMessage',
                'Payment successful. Transaction ID: ' . ($payment->metadata['trx_id'] ?? $payment->provider_payment_id)
                    . '. Subscription active until ' . $sub->expires_at->toDateString() . '.'
            );
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
            $sub = Subscription::ensureExists();
            $sub->is_active = true;
            $sub->expires_at = $period === 'yearly' ? now()->addYear() : now()->addMonth();
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
        $sub = Subscription::ensureExists();
        $sub->is_active = true;
        $sub->expires_at = $period === 'yearly' ? now()->addYear() : now()->addMonth();
        $sub->last_payment_id = $payment->provider_payment_id;
        $sub->save();

        return back()->with('successMessage', 'Payment applied and subscription activated until ' . $sub->expires_at->toDateString());
    }
}
