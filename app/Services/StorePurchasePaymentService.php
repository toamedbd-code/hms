<?php

namespace App\Services;

use App\Models\Account;
use App\Models\StoreGrnItem;
use App\Models\StorePurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StorePurchasePaymentService
{
    public function __construct(private StorePurchasePaymentLedgerService $ledgerService)
    {
    }

    public function record(int $orderId, array $data, ?int $createdBy)
    {
        return DB::transaction(function () use ($orderId, $data, $createdBy) {
            $order = StorePurchaseOrder::query()->lockForUpdate()->findOrFail($orderId);
            $account = Account::query()->whereKey($data['payment_account_id'])
                ->where('is_active', true)
                ->where('type', 'asset')
                ->whereIn('code', ['CASH', 'BANK'])
                ->first();
            if (!$account) {
                throw ValidationException::withMessages([
                    'payment_account_id' => 'Select an active cash or bank account.',
                ]);
            }

            $receivedAmount = (float) StoreGrnItem::query()
                ->whereHas('grn', fn ($query) => $query->where('store_purchase_order_id', $order->id))
                ->sum('line_total');
            $paidAmount = (float) $order->payments()->sum('amount');
            $amount = round((float) $data['amount'], 2);
            $dueAmount = round($receivedAmount - $paidAmount, 2);

            if ($receivedAmount <= 0 || $amount <= 0 || $amount > $dueAmount) {
                throw ValidationException::withMessages([
                    'amount' => 'Enter a valid payment not exceeding the received goods balance of ' . number_format(max(0, $dueAmount), 2) . '.',
                ]);
            }

            $payment = $order->payments()->create([
                'payment_account_id' => $account->id,
                'amount' => $amount,
                'payment_date' => $data['payment_date'],
                'reference_no' => $data['reference_no'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $createdBy,
            ]);

            $payment->setRelation('purchaseOrder', $order);
            $this->ledgerService->sync($payment);

            return $payment;
        });
    }
}
