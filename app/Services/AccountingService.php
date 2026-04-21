<?php

namespace App\Services;

use App\Models\LedgerTransaction;
use App\Models\LedgerEntry;
use App\Models\AccountBalance;
use App\Models\Account;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class AccountingService
{
    /**
     * Create a ledger transaction (journal) and update balances.
     *
     * $data = [
     *   'date' => 'YYYY-mm-dd',
     *   'description' => '',
     *   'reference_type' => null,
     *   'reference_id' => null,
     *   'created_by' => null,
     *   'lines' => [ ['account_id'=>1,'entry_type'=>'debit','amount'=>100], ... ]
     * ]
     */
    public function createJournal(array $data)
    {
        // Validate balancing
        $debits = 0;
        $credits = 0;
        foreach ($data['lines'] as $l) {
            if (strtolower($l['entry_type']) === 'debit') {
                $debits += (float) $l['amount'];
            } else {
                $credits += (float) $l['amount'];
            }
        }

        if (round($debits, 2) !== round($credits, 2)) {
            throw new \InvalidArgumentException('Debits and credits must balance.');
        }

        return DB::transaction(function () use ($data) {
            $tx = LedgerTransaction::create([
                'uuid' => Str::uuid()->toString(),
                'date' => $data['date'] ?? now()->toDateString(),
                'description' => $data['description'] ?? null,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            foreach ($data['lines'] as $line) {
                $entry = LedgerEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $line['account_id'],
                    'amount' => $line['amount'],
                    'entry_type' => strtolower($line['entry_type']),
                ]);

                // Ensure balance row exists
                $balance = AccountBalance::firstOrCreate([
                    'account_id' => $line['account_id']
                ], ['balance' => 0]);

                // Simple balance update: debit increases, credit decreases
                if ($entry->entry_type === 'debit') {
                    $balance->balance = bcadd((string)$balance->balance, (string)$entry->amount, 2);
                } else {
                    $balance->balance = bcsub((string)$balance->balance, (string)$entry->amount, 2);
                }

                $balance->save();
            }

            return $tx->load('entries');
        });
    }

    /**
     * Build a simple trial balance: per-account debit and credit totals and net balance.
     */
    public function trialBalance()
    {
        $rows = DB::table('ledger_entries')
            ->select('account_id', 'entry_type', DB::raw('SUM(amount) as total'))
            ->groupBy('account_id', 'entry_type')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $acct = $r->account_id;
            if (!isset($map[$acct])) {
                $map[$acct] = ['debit' => 0.0, 'credit' => 0.0];
            }
            $map[$acct][$r->entry_type] = (float) $r->total;
        }

        $accounts = Account::whereIn('id', array_keys($map))->get()->keyBy('id');

        $result = [];
        foreach ($map as $acctId => $vals) {
            $account = $accounts->get($acctId);
            $result[] = [
                'account_id' => $acctId,
                'code' => $account->code ?? null,
                'name' => $account->name ?? null,
                'debit' => $vals['debit'],
                'credit' => $vals['credit'],
                'net' => round($vals['debit'] - $vals['credit'], 2),
            ];
        }

        // Totals
        $totals = ['debit' => 0.0, 'credit' => 0.0];
        foreach ($result as $r) {
            $totals['debit'] += $r['debit'];
            $totals['credit'] += $r['credit'];
        }

        return ['rows' => $result, 'totals' => $totals];
    }
}
