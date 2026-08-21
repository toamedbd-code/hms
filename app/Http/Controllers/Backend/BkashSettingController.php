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

class BkashSettingController extends Controller
{
    public function index()
    {
        $setting = BkashSetting::first();

        return view('backend.settings.bkash', [
            'setting' => $setting,
        ]);
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
        ]);

        $existingMerchantNumber = optional(BkashSetting::first())->merchant_number;

        $setting = BkashSetting::first();

        // normalize values to avoid inserting NULL into NOT NULL columns
        $data['monthly_amount'] = isset($data['monthly_amount']) ? $data['monthly_amount'] : 0;
        $data['is_sandbox'] = $request->has('is_sandbox') ? (bool) $request->input('is_sandbox') : ($data['is_sandbox'] ?? true);
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

        if (isset($data['monthly_amount'])) {
            $this->setEnvValue('SUBSCRIPTION_MONTHLY_AMOUNT', (string) $data['monthly_amount']);
            Config::set('subscription.monthly_amount', (float) $data['monthly_amount']);

            // Also calculate yearly amount and persist to .env and runtime config
            $yearly = round(((float) $data['monthly_amount']) * 12, 2);
            $this->setEnvValue('SUBSCRIPTION_YEARLY_AMOUNT', (string) $yearly);
            Config::set('subscription.yearly_amount', (float) $yearly);
        }

        return redirect()
            ->route('backend.settings.payment.bkash')
            ->with('success', 'bKash settings updated.');
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
        $escaped = str_replace('"', '\\"', $value);
        $newLine = $key . '="' . $escaped . '"';

        if (preg_match('/^' . preg_quote($key, '/') . '=.*/m', $content)) {
            $content = preg_replace('/^' . preg_quote($key, '/') . '=.*/m', $newLine, $content);
        } else {
            $content = rtrim($content, "\n") . PHP_EOL . $newLine . PHP_EOL;
        }

        file_put_contents($envPath, $content);
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
        $subscription = Subscription::ensureExists();

        if ($mode === 'off') {
            $subscription->is_active = false;
            $subscription->expires_at = Carbon::now()->subDay();
            $subscription->save();
            Subscription::clearCurrentCache();

            return redirect()
                ->route('backend.settings.payment.bkash')
                ->with('success', 'Subscription set to inactive.');
        }

        if ($mode === 'on') {
            $subscription->is_active = true;
            $subscription->expires_at = Carbon::now()->addYear();
            $subscription->save();
            Subscription::clearCurrentCache();

            return redirect()
                ->route('backend.settings.payment.bkash')
                ->with('success', 'Subscription set to active.');
        }

        return redirect()
            ->route('backend.settings.payment.bkash')
            ->with('success', 'Subscription toggle request ignored.');
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
