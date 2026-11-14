<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BkashSetting;
use App\Services\BkashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use App\Support\DefaultDeveloperManager;
use Inertia\Inertia;

class BkashSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware(function (Request $request, $next) {
            if (!DefaultDeveloperManager::isDeveloper($request->user('admin'))) {
                abort(403, 'Only the developer can access bKash settings.');
            }

            return $next($request);
        });
    }

    public function index()
    {
        $setting = BkashSetting::first();
        $monthlyAmount = $this->envAmount('SUBSCRIPTION_MONTHLY_AMOUNT', $setting?->monthly_amount ?? config('subscription.monthly_amount', 0));
        $yearlyAmount = $this->envAmount('SUBSCRIPTION_YEARLY_AMOUNT', $monthlyAmount * 12);
        $bkashEnv = [
            'merchant_account' => $this->envValue('BKASH_MERCHANT_ACCOUNT', ''),
            'sandbox_base_url' => $this->envValue('BKASH_SANDBOX_BASE_URL', config('bkash.sandbox_base_url', '')),
            'production_base_url' => $this->envValue('BKASH_PRODUCTION_BASE_URL', config('bkash.production_base_url', '')),
            'token_endpoint' => $this->envValue('BKASH_TOKEN_ENDPOINT', config('bkash.token_endpoint', '')),
            'create_payment_endpoint' => $this->envValue('BKASH_CREATE_PAYMENT_ENDPOINT', config('bkash.create_payment_endpoint', '')),
            'execute_payment_endpoint' => $this->envValue('BKASH_EXECUTE_PAYMENT_ENDPOINT', config('bkash.execute_payment_endpoint', '')),
            'query_payment_endpoint' => $this->envValue('BKASH_QUERY_PAYMENT_ENDPOINT', config('bkash.query_payment_endpoint', '')),
            'app_key' => $this->envValue('BKASH_APP_KEY', $setting?->app_key ?? ''),
            'app_secret' => $this->envValue('BKASH_APP_SECRET', $setting?->app_secret ?? ''),
            'username' => $this->envValue('BKASH_USERNAME', $setting?->username ?? ''),
            'password' => $this->envValue('BKASH_PASSWORD', $setting?->password ?? ''),
            'merchant_number' => $this->envValue('BKASH_MERCHANT_NUMBER', $setting?->merchant_number ?? ''),
            'callback_url' => $this->envValue('BKASH_CALLBACK_URL', config('bkash.callback_url', '')),
            'http_timeout' => $this->envValue('BKASH_HTTP_TIMEOUT', config('bkash.http_timeout', 30)),
            'http_verify_ssl' => $this->envValue('BKASH_HTTP_VERIFY_SSL', config('bkash.http_verify_ssl', false) ? 'true' : 'false'),
            'use_production' => $this->envValue('BKASH_USE_PRODUCTION', config('bkash.use_production', false) ? 'true' : 'false'),
        ];

        $subscription = Subscription::ensureExists();

        return Inertia::render('Backend/Settings/BkashSetting', [
            'setting' => $setting,
            'monthlyAmount' => $monthlyAmount,
            'yearlyAmount' => $yearlyAmount,
            'canInitiatePayment' => (bool) config('payment.enabled') && (bool) ($setting?->is_enabled ?? false),
            'bkashEnv' => $bkashEnv,
            'subscriptionActive' => $subscription && $subscription->isActive(),
            'subscriptionDurationDays' => $subscription?->duration_days ?? 30,
            'pageTitle' => 'bKash Settings',
        ]);
    }

    private function envValue(string $key, $fallback = ''): string
    {
        $envPath = base_path('.env');
        if (is_file($envPath) && is_readable($envPath)) {
            $content = file_get_contents($envPath);
            if (preg_match('/^[ \t]*' . preg_quote($key, '/') . '[ \t]*=[ \t]*(?:"([^"]*)"|([^\r\n]*?))[ \t]*\r?$/m', (string) $content, $matches)) {
                $value = ($matches[1] ?? '') !== '' ? $matches[1] : ($matches[2] ?? '');
                return trim((string) $value);
            }
        }

        return trim((string) $fallback);
    }

    private function envAmount(string $key, $fallback): float
    {
        $envPath = base_path('.env');
        if (is_file($envPath) && is_readable($envPath)) {
            $content = file_get_contents($envPath);
            if (preg_match('/^[ \t]*' . preg_quote($key, '/') . '[ \t]*=[ \t]*(?:"([^"]*)"|([^\r\n]*?))[ \t]*\r?$/m', (string) $content, $matches)) {
                $value = trim(($matches[1] ?? '') !== '' ? $matches[1] : ($matches[2] ?? ''));
                if (is_numeric($value)) {
                    return (float) $value;
                }
            }
        }

        return (float) $fallback;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'app_key' => 'nullable|string',
            'app_secret' => 'nullable|string',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
            'merchant_number' => 'nullable|string',
            'is_sandbox' => 'nullable',
            'is_enabled' => 'nullable',
            'monthly_amount' => 'nullable|numeric|min:0',
            'merchant_account' => 'nullable|string',
            'sandbox_base_url' => 'nullable|url',
            'production_base_url' => 'nullable|url',
            'token_endpoint' => 'nullable|string',
            'create_payment_endpoint' => 'nullable|string',
            'execute_payment_endpoint' => 'nullable|string',
            'query_payment_endpoint' => 'nullable|string',
            'callback_url' => 'nullable|url',
            'http_timeout' => 'nullable|integer|min:1|max:300',
            'http_verify_ssl' => 'nullable|boolean',
            'use_production' => 'nullable|boolean',
        ]);

        $existingMerchantNumber = optional(BkashSetting::first())->merchant_number;

        $setting = BkashSetting::first();

        // normalize values to avoid inserting NULL into NOT NULL columns
        $data['monthly_amount'] = isset($data['monthly_amount']) ? $data['monthly_amount'] : 0;
        $useProduction = $request->has('use_production')
            ? $request->boolean('use_production')
            : !$request->boolean('is_sandbox');
        $data['is_sandbox'] = !$useProduction;
        $data['is_enabled'] = $request->has('is_enabled') ? (bool) $request->input('is_enabled') : ($data['is_enabled'] ?? false);

        if ($setting) {
            // If the request explicitly provided the merchant_number field
            // allow clearing it (set to null) when the admin submits an
            // empty value. If the field was omitted entirely, preserve
            // the existing DB value by unsetting the key.
            if ($request->has('merchant_number')) {
                $mn = $request->input('merchant_number');
                $data['merchant_number'] = $mn === '' ? null : $mn;
            } else {
                unset($data['merchant_number']);
            }

            $setting->update($data);
        } else {
            // Normalize empty merchant_number to null when creating
            if (array_key_exists('merchant_number', $data) && $data['merchant_number'] === '') {
                $data['merchant_number'] = null;
            }
            $setting = BkashSetting::create($data);
        }

        $envWriteFailed = false;
        if (isset($data['monthly_amount'])) {
            $envWriteFailed = !$this->setEnvValue('SUBSCRIPTION_MONTHLY_AMOUNT', (string) $data['monthly_amount']);
            Config::set('subscription.monthly_amount', (float) $data['monthly_amount']);

            // Also calculate yearly amount and persist to .env and runtime config
            $yearly = round(((float) $data['monthly_amount']) * 12, 2);
            $envWriteFailed = !$this->setEnvValue('SUBSCRIPTION_YEARLY_AMOUNT', (string) $yearly) || $envWriteFailed;
            Config::set('subscription.yearly_amount', (float) $yearly);
        }

        $envValues = [
            'BKASH_MERCHANT_ACCOUNT' => $request->input('merchant_account', ''),
            'BKASH_SANDBOX_BASE_URL' => $request->input('sandbox_base_url', ''),
            'BKASH_PRODUCTION_BASE_URL' => $request->input('production_base_url', ''),
            'BKASH_TOKEN_ENDPOINT' => $request->input('token_endpoint', ''),
            'BKASH_CREATE_PAYMENT_ENDPOINT' => $request->input('create_payment_endpoint', ''),
            'BKASH_EXECUTE_PAYMENT_ENDPOINT' => $request->input('execute_payment_endpoint', ''),
            'BKASH_QUERY_PAYMENT_ENDPOINT' => $request->input('query_payment_endpoint', ''),
            'BKASH_APP_KEY' => $request->input('app_key', ''),
            'BKASH_APP_SECRET' => $request->input('app_secret', ''),
            'BKASH_USERNAME' => $request->input('username', ''),
            'BKASH_PASSWORD' => $request->input('password', ''),
            'BKASH_MERCHANT_NUMBER' => $request->input('merchant_number', ''),
            'BKASH_CALLBACK_URL' => $request->input('callback_url', ''),
            'BKASH_HTTP_TIMEOUT' => $request->input('http_timeout', 30),
            'BKASH_HTTP_VERIFY_SSL' => $request->boolean('http_verify_ssl') ? 'true' : 'false',
            'BKASH_USE_PRODUCTION' => $useProduction ? 'true' : 'false',
        ];

        foreach ($envValues as $key => $value) {
            $envWriteFailed = !$this->setEnvValue($key, (string) $value) || $envWriteFailed;
            $configValue = match ($key) {
                'BKASH_HTTP_TIMEOUT' => (int) $value,
                'BKASH_HTTP_VERIFY_SSL', 'BKASH_USE_PRODUCTION' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                default => $value,
            };
            Config::set('bkash.' . $this->configKeyFromEnv($key), $configValue);
        }

        // Clear config cache so the updated .env values take effect immediately
        // (cached config would otherwise return stale values like old subscription amounts)
        try {
            \Illuminate\Support\Facades\Artisan::call('config:clear');
        } catch (\Throwable $_) {
            // Non-fatal; continue even if cache clear fails
        }

        if ($envWriteFailed) {
            return redirect()
                ->route('backend.settings.payment.bkash')
                ->with('errorMessage', 'Settings saved, but the .env file could not be updated. Check file permissions.');
        }

        return redirect()
            ->route('backend.settings.payment.bkash')
            ->with('successMessage', 'bKash settings updated successfully.');
    }

    private function configKeyFromEnv(string $key): string
    {
        return match ($key) {
            'BKASH_SANDBOX_BASE_URL' => 'sandbox_base_url',
            'BKASH_PRODUCTION_BASE_URL' => 'production_base_url',
            'BKASH_TOKEN_ENDPOINT' => 'token_endpoint',
            'BKASH_CREATE_PAYMENT_ENDPOINT' => 'create_payment_endpoint',
            'BKASH_EXECUTE_PAYMENT_ENDPOINT' => 'execute_payment_endpoint',
            'BKASH_QUERY_PAYMENT_ENDPOINT' => 'query_payment_endpoint',
            'BKASH_APP_KEY' => 'app_key',
            'BKASH_APP_SECRET' => 'app_secret',
            'BKASH_USERNAME' => 'username',
            'BKASH_PASSWORD' => 'password',
            'BKASH_MERCHANT_NUMBER' => 'merchant_number',
            'BKASH_CALLBACK_URL' => 'callback_url',
            'BKASH_HTTP_TIMEOUT' => 'http_timeout',
            'BKASH_HTTP_VERIFY_SSL' => 'http_verify_ssl',
            'BKASH_USE_PRODUCTION' => 'use_production',
            default => strtolower($key),
        };
    }

    /**
     * Update a single key in the .env file.
     */
    private function setEnvValue(string $key, string $value): bool
    {
        $envPath = base_path('.env');
        if (! is_file($envPath) || ! is_writable($envPath)) {
            return false;
        }

        $content = file_get_contents($envPath);
        if ($content === false) {
            return false;
        }

        $escaped = str_replace('"', '\\"', $value);
        $newLine = $key . '="' . $escaped . '"';
        $lineEnding = str_contains($content, "\r\n") ? "\r\n" : PHP_EOL;

        if (preg_match('/^[ \t]*' . preg_quote($key, '/') . '[ \t]*=[^\r\n]*(?:\r?\n|$)/m', $content)) {
            $content = preg_replace(
            '/^[ \t]*' . preg_quote($key, '/') . '[ \t]*=[^\r\n]*(?:\r?\n|$)/m',
                $newLine . $lineEnding,
                $content
            );
        } else {
            $content = rtrim($content, "\r\n") . $lineEnding . $newLine . $lineEnding;
        }

        if (file_put_contents($envPath, $content, LOCK_EX) === false) {
            return false;
        }
        try {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        } catch (\Throwable $_) {
            // ignore runtime update failures
        }

        return true;
    }

    /**
     * Test bKash connection by attempting to obtain a token.
     */
    public function toggleSubscription(Request $request)
    {
        $mode = $request->input('mode', 'status');
        $durationDays = (int) $request->input('duration_days', 30);

        if ($mode === 'on') {
            $request->validate([
                'duration_days' => 'nullable|integer|min:1|max:3650',
            ]);
            $durationDays = $durationDays ?: 30;
        }

        $subscription = Subscription::ensureExists();

        if ($mode === 'off') {
            $subscription->is_active = false;
            $subscription->expires_at = Carbon::now()->subDay();
            $subscription->save();
            Subscription::clearCurrentCache();

            return redirect()
                ->route('backend.settings.payment.bkash')
                ->with('successMessage', 'Subscription set to inactive.');
        }

        if ($mode === 'on') {
            $subscription->is_active = true;
            $subscription->duration_days = $durationDays;
            $subscription->expires_at = Carbon::now()->addDays($durationDays);
            $subscription->save();
            Subscription::clearCurrentCache();

            return redirect()
                ->route('backend.settings.payment.bkash')
                ->with('successMessage', 'Subscription set to active.');
        }

        return redirect()
            ->route('backend.settings.payment.bkash')
            ->with('successMessage', 'Subscription toggle request ignored.');
    }

    public function test(Request $request, BkashService $service): JsonResponse
    {
        // simple permission check could be added here
        try {
            $token = $service->grantToken();
            if ($token) {
                $result = ['ok' => true, 'message' => 'Connection OK. Token obtained.'];
                Cache::put('bkash_test_last', $result, 3600);
                return response()->json($result);
            }

            // gather more debug info: probe all token endpoint candidates and return the raw responses
            $probeResults = $service->probeTokenEndpoints();
            $result = [
                'ok' => false,
                'message' => 'Unable to obtain a token from any candidate endpoint.',
                'results' => $probeResults,
            ];
            Cache::put('bkash_test_last', $result, 3600);
            return response()->json($result, 422);
        } catch (\Throwable $e) {
            $result = ['ok' => false, 'message' => 'Error: ' . $e->getMessage()];
            Cache::put('bkash_test_last', $result, 3600);
            return response()->json($result, 500);
        }
    }
}
