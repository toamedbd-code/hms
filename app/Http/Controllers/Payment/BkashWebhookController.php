<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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

        // Simple acceptance criteria: status == success
        $status = strtolower((string) data_get($payload, 'status', ''));
        $txId = data_get($payload, 'transaction_id') ?: data_get($payload, 'trx_id') ?: data_get($payload, 'payment_id');
        $amount = (float) data_get($payload, 'amount', 0);

        if ($status === 'success' || $status === 'completed' || $status === 'done') {
            $bk = BkashSetting::first();

            // optional: validate amount matches configured monthly_amount (if set and > 0)
            if ($bk && $bk->monthly_amount > 0 && $amount > 0 && round($amount, 2) < round($bk->monthly_amount, 2)) {
                // amount is less than expected monthly amount, ignore
                return response()->json(['ok' => false, 'reason' => 'amount_too_small'], 400);
            }

            $sub = Subscription::first();
            if (! $sub) {
                $sub = Subscription::create([
                    'is_active' => true,
                    'expires_at' => now()->addMonth(),
                    'last_payment_id' => $txId,
                ]);
            } else {
                $sub->is_active = true;
                $sub->expires_at = now()->addMonth();
                $sub->last_payment_id = $txId;
                $sub->save();
            }

            return response()->json(['ok' => true]);
        }

        return response()->json(['ok' => false], 400);
    }
}
