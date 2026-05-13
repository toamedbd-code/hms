<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountBalance;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    /**
     * Record a balanced ledger transaction.
     *
     * $lines = [
     *   ['account_id' => 1, 'entry_type' => 'debit', 'amount' => 100.00],
     *   ['account_code' => 'DIAG_INC', 'entry_type' => 'credit', 'amount' => 100.00],
     * ];
     *
     * @throws \Exception when debits != credits or missing account
     */
    public function recordTransaction(array $lines, string $description = null, $date = null, string $referenceType = null, $referenceId = null, $createdBy = null): LedgerTransaction
    {
        if (count($lines) < 2) {
            throw new \InvalidArgumentException('Transaction must contain at least two ledger lines.');
        }

        $debits = 0.0;
        $credits = 0.0;

        // Normalize and validate
        foreach ($lines as &$line) {
            if (!isset($line['amount']) || !is_numeric($line['amount']) || (float) $line['amount'] <= 0) {
                throw new \InvalidArgumentException('Each ledger line requires a positive numeric amount.');
            }

            if (empty($line['entry_type']) || !in_array($line['entry_type'], ['debit', 'credit'], true)) {
                throw new \InvalidArgumentException('Each ledger line requires entry_type debit or credit.');
            }

            $amount = (float) $line['amount'];
            if ($line['entry_type'] === 'debit') {
                $debits += $amount;
            } else {
                $credits += $amount;
            }

            // resolve account id by code if needed
            if (empty($line['account_id']) && !empty($line['account_code'])) {
                $acc = Account::where('code', trim((string) $line['account_code']))->first();
                if (!$acc) {
                    throw new \InvalidArgumentException('Account code not found: ' . $line['account_code']);
                }
                $line['account_id'] = $acc->id;
            }

            if (empty($line['account_id'])) {
                throw new \InvalidArgumentException('Each ledger line must include account_id or account_code.');
            }
        }

        if (bccomp((string) $debits, (string) $credits, 2) !== 0) {
            throw new \InvalidArgumentException('Debits and credits must be equal.');
        }

        return DB::transaction(function () use ($lines, $description, $date, $referenceType, $referenceId, $createdBy) {
            $tx = LedgerTransaction::create([
                'uuid' => (string) Str::uuid(),
                'date' => $date ? $date : now()->toDateString(),
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'journal_entry_id' => $referenceType === 'journal_entry' ? $referenceId : null,
                'created_by' => $createdBy,
            ]);

            foreach ($lines as $line) {
                /** @var LedgerEntry $entry */
                $entry = LedgerEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $line['account_id'],
                    'amount' => (float) $line['amount'],
                    'entry_type' => $line['entry_type'],
                    'narration' => $line['narration'] ?? null,
                ]);

                $balance = AccountBalance::firstOrCreate(['account_id' => $line['account_id']], ['balance' => 0, 'profit' => 0, 'loss' => 0]);

                // Type-aware balance delta using normal accounting side
                $acc = Account::find($line['account_id']);
                $accType = strtolower((string) ($acc->type ?? ''));
                $normalSide = in_array($accType, ['liability', 'equity', 'income'], true) ? 'credit' : 'debit';
                $delta = $line['entry_type'] === $normalSide
                    ? (float) $line['amount']
                    : -1 * (float) $line['amount'];
                $balance->balance = (float) $balance->balance + $delta;

                // Update profit/loss for income/expense accounts
                if ($acc) {
                    // Income accounts: credits increase profit, debits increase loss
                    if ($acc->type === 'income') {
                        if ($line['entry_type'] === 'credit') {
                            $balance->profit = (float) $balance->profit + (float) $line['amount'];
                        } else {
                            $balance->loss = (float) $balance->loss + (float) $line['amount'];
                        }
                    }

                    // Expense accounts: debits increase loss, credits increase profit (contra)
                    if ($acc->type === 'expense') {
                        if ($line['entry_type'] === 'debit') {
                            $balance->loss = (float) $balance->loss + (float) $line['amount'];
                        } else {
                            $balance->profit = (float) $balance->profit + (float) $line['amount'];
                        }
                    }
                }

                $balance->save();
            }

            return $tx->load('entries');
        });
    }

    /**
     * Convenience: record a simple income (debit counter account, credit income account)
     */
    public function recordIncome(string $incomeAccountCode, string $counterAccountCode, float $amount, string $description = null, $date = null, string $referenceType = null, $referenceId = null, $createdBy = null): LedgerTransaction
    {
        $lines = [
            ['account_code' => $counterAccountCode, 'entry_type' => 'debit', 'amount' => $amount],
            ['account_code' => $incomeAccountCode, 'entry_type' => 'credit', 'amount' => $amount],
        ];

        return $this->recordTransaction($lines, $description, $date, $referenceType, $referenceId, $createdBy);
    }

    /**
     * Convenience: record an expense (debit expense account, credit counter account)
     */
    public function recordExpense(string $expenseAccountCode, string $counterAccountCode, float $amount, string $description = null, $date = null, string $referenceType = null, $referenceId = null, $createdBy = null): LedgerTransaction
    {
        $lines = [
            ['account_code' => $expenseAccountCode, 'entry_type' => 'debit', 'amount' => $amount],
            ['account_code' => $counterAccountCode, 'entry_type' => 'credit', 'amount' => $amount],
        ];

        return $this->recordTransaction($lines, $description, $date, $referenceType, $referenceId, $createdBy);
    }

    /**
     * Delete (reverse) a previously recorded transaction and adjust balances.
     * Returns true on success, false if transaction not found.
     */
    public function deleteTransaction($transactionId)
    {
        return DB::transaction(function () use ($transactionId) {
            $tx = LedgerTransaction::with('entries')->find($transactionId);
            if (! $tx) {
                return false;
            }

            foreach ($tx->entries as $entry) {
                $balance = AccountBalance::firstOrCreate(['account_id' => $entry->account_id], ['balance' => 0, 'profit' => 0, 'loss' => 0]);

                $acc = Account::find($entry->account_id);

                // Reverse type-aware delta using normal accounting side
                $accType = strtolower((string) ($acc->type ?? ''));
                $normalSide = in_array($accType, ['liability', 'equity', 'income'], true) ? 'credit' : 'debit';
                $delta = $entry->entry_type === $normalSide
                    ? (float) $entry->amount
                    : -1 * (float) $entry->amount;
                $balance->balance = (float) $balance->balance - $delta;

                // Reverse profit/loss adjustments if applicable
                if ($acc) {
                    if ($acc->type === 'income') {
                        if ($entry->entry_type === 'credit') {
                            $balance->profit = (float) $balance->profit - (float) $entry->amount;
                        } else {
                            $balance->loss = (float) $balance->loss - (float) $entry->amount;
                        }
                    }

                    if ($acc->type === 'expense') {
                        if ($entry->entry_type === 'debit') {
                            $balance->loss = (float) $balance->loss - (float) $entry->amount;
                        } else {
                            $balance->profit = (float) $balance->profit - (float) $entry->amount;
                        }
                    }
                }

                $balance->save();
            }

            // Remove entries and transaction
            $tx->entries()->delete();
            $tx->delete();

            return true;
        });
    }
}
