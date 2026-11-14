<?php

namespace App\Services;

use App\Models\Account;
use App\Models\LedgerTransaction;
use Illuminate\Support\Facades\DB;

class LabAccountingService
{
    public function __construct(private LedgerService $ledgerService)
    {
    }

    public function recordPayment(
        int $paymentId,
        int $orderId,
        float $amount,
        string $paymentMethod,
        string $paymentDate,
        ?int $createdBy
    ): void {
        DB::transaction(function () use ($paymentId, $orderId, $amount, $paymentMethod, $paymentDate, $createdBy) {
            if (LedgerTransaction::query()
                ->where('reference_type', 'LabPayment')
                ->where('reference_id', $paymentId)
                ->exists()) {
                throw new \LogicException('A ledger transaction already exists for this Lab payment.');
            }

            $paymentAccountCode = $this->ledgerService->resolvePaymentAccountCode($paymentMethod);
            $this->ensureAccount(
                $paymentAccountCode,
                match ($paymentAccountCode) {
                    'BANK' => 'Bank',
                    'MOBILE_BANK' => 'Mobile Bank',
                    default => 'Cash',
                },
                'asset',
                'ASSET',
                false
            );
            $this->ensureAccount('LAB_INC', 'Laboratory Service Income', 'income', 'INCOME', true);

            $this->ledgerService->recordTransaction(
                [
                    ['account_code' => $paymentAccountCode, 'entry_type' => 'debit', 'amount' => $amount],
                    ['account_code' => 'LAB_INC', 'entry_type' => 'credit', 'amount' => $amount],
                ],
                'Lab order payment ' . $orderId,
                $paymentDate,
                'LabPayment',
                $paymentId,
                $createdBy
            );
        });
    }

    private function ensureAccount(string $code, string $name, string $type, string $group, bool $isProfitLoss): void
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
    }
}
