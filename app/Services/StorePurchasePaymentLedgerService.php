<?php

namespace App\Services;

use App\Models\Account;
use App\Models\LedgerTransaction;
use App\Models\StorePurchasePayment;
use Illuminate\Support\Facades\DB;

class StorePurchasePaymentLedgerService
{
    public function __construct(private LedgerService $ledgerService)
    {
    }

    public function sync(StorePurchasePayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            LedgerTransaction::query()
                ->where('reference_type', 'StorePurchasePayment')
                ->where('reference_id', $payment->id)
                ->get()
                ->each(fn (LedgerTransaction $transaction) => $this->ledgerService->deleteTransaction($transaction->id));

            $amount = round((float) $payment->amount, 2);
            if ($amount <= 0) {
                return;
            }

            $inventoryCode = $this->ensureAccount('INV', 'Inventory', 'asset', 'ASSET', false);
            $expenseCode = $this->ensureAccount('STORE_PURCHASE_EXP', 'Store Purchase Expense', 'expense', 'EXPENSE', true);
            $payableCode = $this->ensureAccount('AP_SUPPLIER', 'Supplier Payable', 'liability', 'LIABILITY', false);
            $paymentAccountId = $payment->payment_account_id;
            if (!$paymentAccountId) {
                $paymentAccountId = Account::query()->where('code', 'CASH')->value('id');
            }
            if (!$paymentAccountId) {
                throw new \RuntimeException('A cash or bank account is required to post a store supplier payment.');
            }

            $this->ledgerService->recordTransaction(
                [
                    ['account_code' => $inventoryCode, 'entry_type' => 'credit', 'amount' => $amount],
                    ['account_code' => $expenseCode, 'entry_type' => 'debit', 'amount' => $amount],
                    ['account_code' => $payableCode, 'entry_type' => 'debit', 'amount' => $amount],
                    ['account_id' => $paymentAccountId, 'entry_type' => 'credit', 'amount' => $amount],
                ],
                'Store purchase payment ' . ($payment->purchaseOrder?->purchase_order_no ?? $payment->store_purchase_order_id),
                $payment->payment_date?->format('Y-m-d') ?? now()->toDateString(),
                'StorePurchasePayment',
                $payment->id,
                $payment->created_by
            );
        });
    }

    private function ensureAccount(string $code, string $name, string $type, string $group, bool $isProfitLoss): string
    {
        Account::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'type' => $type,
                'account_group' => $group,
                'is_profit_loss' => $isProfitLoss,
                'is_active' => true,
            ]
        );

        return $code;
    }
}
