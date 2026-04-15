<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\BkashSetting;
use App\Services\BkashService;

class BkashController extends Controller
{
    public function initiate(Request $request, BkashService $service)
    {
        $request->validate([
            'amount' => 'nullable|numeric|min:0',
        ]);

        $setting = BkashSetting::first();
        $amount = $request->input('amount', $setting?->monthly_amount ?? 0);

        if (! config('payment.enabled')) {
            return redirect()->back()->with('errorMessage', 'Payments are disabled by system configuration.');
        }

        if (empty($setting) || ! $setting->is_enabled) {
            return redirect()->back()->with('errorMessage', 'bKash payments are not enabled.');
        }

        if ((float) $amount <= 0) {
            return redirect()->back()->with('errorMessage', 'Invalid amount.');
        }

        $payment = Payment::create([
            'provider' => 'bkash',
            'amount' => $amount,
            'payment_method' => 'bkash',
            'status' => 'initiated',
        ]);

        try {
            $result = $service->createCheckout($payment);

            if (! empty($result['payment_id'])) {
                $payment->provider_payment_id = $result['payment_id'];
                $payment->save();
            }

            if (! empty($result['redirect_url'])) {
                return redirect($result['redirect_url']);
            }

            return redirect()->back()->with('errorMessage', 'No redirect URL returned from bKash service');
        } catch (\Exception $e) {
            $payment->status = 'failed';
            $payment->metadata = ['error' => $e->getMessage()];
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

        return redirect()->route('settings.payment.bkash')->with('successMessage', 'Simulated payment applied. Subscription active until ' . $sub->expires_at->toDateString());
    }

    /**
     * Public initiation endpoint for renewing subscription from login page (GET)
     * Example: /payment/bkash/renew?amount=100&email=admin@example.com
     */
    public function publicInitiate(Request $request, BkashService $service)
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

        if (empty($setting) || ! $setting->is_enabled) {
            return redirect()->route($loginRoute)->with('errorMessage', 'bKash payments are not enabled.');
        }

        if ((float) $amount <= 0) {
            return redirect()->route($loginRoute)->with('errorMessage', 'Invalid amount.');
        }

        $payment = Payment::create([
            'provider' => 'bkash',
            'amount' => $amount,
            'payment_method' => 'bkash',
            'status' => 'initiated',
            'metadata' => ['period' => $period],
        ]);

        try {
            $result = $service->createCheckout($payment, 'payment.bkash.simulate.approve');

            if (! empty($result['payment_id'])) {
                $payment->provider_payment_id = $result['payment_id'];
                $payment->save();
            }

            if (! empty($result['redirect_url'])) {
                return redirect($result['redirect_url']);
            }

            return redirect()->route($loginRoute)->with('errorMessage', 'No redirect URL returned from bKash service');
        } catch (\Exception $e) {
            $payment->status = 'failed';
            $payment->metadata = ['error' => $e->getMessage()];
            $payment->save();

            return redirect()->route($loginRoute)->with('errorMessage', 'Payment initiation failed: ' . $e->getMessage());
        }
    }

    /**
     * Public simulate approval endpoint (sandbox) — marks payment successful and activates subscription,
     * then redirects to login page with success message.
     */
    public function publicSimulateApprove(Payment $payment)
    {
        $loginRoute = \Illuminate\Support\Facades\Route::has('backend.auth.login2') ? 'backend.auth.login2' : 'auth.login2';

        if (! config('payment.enabled')) {
            return redirect()->route($loginRoute)->with('errorMessage', 'Payments are disabled by system configuration.');
        }

        if ($payment->status === 'success') {
            return redirect()->route($loginRoute)->with('successMessage', 'Payment already completed.');
        }

        $payment->status = 'success';
        $payment->provider_payment_id = $payment->provider_payment_id ?: ('SIM-' . $payment->id);
        $payment->save();

        $sub = Subscription::ensureExists();
        $sub->is_active = true;
        $sub->expires_at = now()->addMonth();
        $sub->last_payment_id = $payment->provider_payment_id;
        $sub->save();

        $loginRoute = \Illuminate\Support\Facades\Route::has('backend.auth.login2') ? 'backend.auth.login2' : 'auth.login2';
        return redirect()->route($loginRoute)->with('successMessage', 'Simulated payment applied. Subscription active until ' . $sub->expires_at->toDateString());
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
