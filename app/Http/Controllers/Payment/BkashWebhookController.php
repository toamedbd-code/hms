<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\BkashSetting;

class BkashWebhookController extends Controller
{
    /**
     * Handle incoming bKash payment webhook/callback.
     * Expected minimal payload: {"status":"success","transaction_id":"...","amount":123.45}
     */
    public function handle(Request $request)
    {
        $payload = $request->all();

        $status = strtolower((string) data_get($payload, 'status', ''));
        $txId = data_get($payload, 'provider_payment_id') ?: data_get($payload, 'transaction_id') ?: data_get($payload, 'trx_id') ?: data_get($payload, 'payment_id');
        $amount = (float) data_get($payload, 'amount', 0);
        $period = strtolower((string) data_get($payload, 'period', 'monthly'));
        $period = in_array($period, ['yearly', 'monthly']) ? $period : 'monthly';

        if (! in_array($status, ['success', 'completed', 'done', 'paid'])) {
            return response()->json(['ok' => false, 'reason' => 'invalid_status'], 400);
        }

        $bk = BkashSetting::first();
        if ($bk && $bk->monthly_amount > 0 && $amount > 0 && round($amount, 2) < round($bk->monthly_amount, 2)) {
            return response()->json(['ok' => false, 'reason' => 'amount_too_small'], 400);
        }

        if ($txId) {
            $payment = Payment::where('provider_payment_id', $txId)
                ->orWhere('provider_payment_id', 'SIM-' . $txId)
                ->first();

            if (! $payment) {
                $payment = Payment::create([
                    'provider' => 'bkash',
                    'amount' => $amount,
                    'payment_method' => 'bkash',
                    'status' => 'success',
                    'provider_payment_id' => $txId,
                    'metadata' => ['period' => $period],
                ]);
            } else {
                $payment->status = 'success';
                $payment->amount = $payment->amount ?: $amount;
                $payment->metadata = array_merge((array) $payment->metadata, ['period' => $period]);
                $payment->save();
            }
        }

        $sub = Subscription::ensureExists();
        $sub->is_active = true;
        $sub->expires_at = $period === 'yearly' ? now()->addYear() : now()->addMonth();
        $sub->last_payment_id = $txId;
        $sub->save();
        Subscription::clearCurrentCache();

        return response()->json([
            'ok' => true,
            'expires_at' => $sub->expires_at->toDateString(),
            'period' => $period,
        ]);
    }
}
