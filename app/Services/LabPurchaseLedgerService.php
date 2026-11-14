<?php

namespace App\Services;

use App\Models\Account;
use App\Models\LedgerTransaction;

class LabPurchaseLedgerService
{
    public function __construct(private LedgerService $ledgerService)
    {
    }

    public function recordReceipt(int $receiptId, float $amount, string $date, ?int $createdBy): void
    {
        if ($amount <= 0) {
            return;
        }
        $this->assertNotPosted('LabPurchaseReceipt', $receiptId);
        $this->ledgerService->recordTransaction(
            [
                ['account_code' => $this->ensureAccount('LAB_INV', 'Laboratory Inventory', 'asset', 'ASSET', false), 'entry_type' => 'debit', 'amount' => $amount],
                ['account_code' => $this->ensureAccount('AP_SUPPLIER', 'Supplier Payable', 'liability', 'LIABILITY', false), 'entry_type' => 'credit', 'amount' => $amount],
            ],
            'Lab goods receipt ' . $receiptId,
            $date,
            'LabPurchaseReceipt',
            $receiptId,
            $createdBy
        );
    }

    public function recordPayment(int $paymentId, int $orderId, float $amount, int $paymentAccountId, string $date, ?int $createdBy): void
    {
        $this->assertNotPosted('LabPurchasePayment', $paymentId);
        $this->ledgerService->recordTransaction(
            [
                ['account_code' => $this->ensureAccount('LAB_INV', 'Laboratory Inventory', 'asset', 'ASSET', false), 'entry_type' => 'credit', 'amount' => $amount],
                ['account_code' => $this->ensureAccount('LAB_PURCHASE_EXP', 'Laboratory Purchase Expense', 'expense', 'EXPENSE', true), 'entry_type' => 'debit', 'amount' => $amount],
                ['account_code' => $this->ensureAccount('AP_SUPPLIER', 'Supplier Payable', 'liability', 'LIABILITY', false), 'entry_type' => 'debit', 'amount' => $amount],
                ['account_id' => $paymentAccountId, 'entry_type' => 'credit', 'amount' => $amount],
            ],
            'Lab purchase payment for order ' . $orderId,
            $date,
            'LabPurchasePayment',
            $paymentId,
            $createdBy
        );
    }

    private function assertNotPosted(string $referenceType, int $referenceId): void
    {
        if (LedgerTransaction::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->exists()) {
            throw new \LogicException('A ledger transaction already exists for this Lab procurement record.');
        }
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
