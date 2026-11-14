<?php

namespace App\Services;

use App\Models\CashCounterSession;

class CashCounterLedgerService
{
    public function __construct(private LedgerService $ledgerService)
    {
    }

    public function postCloseAdjustment(CashCounterSession $session): ?int
    {
        if (!empty($session->ledger_close_transaction_id)) {
            return (int) $session->ledger_close_transaction_id;
        }

        $difference = round((float) ($session->difference_amount ?? 0), 2);
        if (abs($difference) <= 0.009) {
            return null;
        }

        $label = trim((string) ($session->counter_name ?? 'Counter')) . ' #' . $session->id;
        $date = optional($session->closed_at)->toDateString() ?? now()->toDateString();
        $createdBy = $session->created_by;

        if ($difference > 0) {
            $transaction = $this->ledgerService->recordTransaction([
                ['account_code' => 'CASH', 'entry_type' => 'debit', 'amount' => $difference],
                ['account_code' => 'SALES', 'entry_type' => 'credit', 'amount' => $difference],
            ], 'Cash counter surplus for ' . $label, $date, 'CashCounterSession', $session->id, $createdBy);
        } else {
            $amount = abs($difference);
            $transaction = $this->ledgerService->recordExpense(
                'ADMIN_EXP',
                'CASH',
                $amount,
                'Cash counter shortage for ' . $label,
                $date,
                'CashCounterSession',
                $session->id,
                $createdBy
            );
        }

        $session->ledger_close_transaction_id = $transaction->id;
        $session->save();

        return (int) $transaction->id;
    }
}
