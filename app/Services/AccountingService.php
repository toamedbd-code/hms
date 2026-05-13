<?php

namespace App\Services;

use App\Models\LedgerTransaction;
use App\Models\LedgerEntry;
use App\Models\AccountBalance;
use App\Models\Account;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Services\LedgerService;

class AccountingService
{
    protected LedgerService $ledgerService;

    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

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
        // Delegate ledger write logic to the canonical LedgerService which
        // enforces balancing, resolves account codes, creates entries and
        // updates cached balances.
        $lines = array_map(function ($l) {
            return [
                'account_id' => $l['account_id'] ?? null,
                'account_code' => $l['account_code'] ?? null,
                'entry_type' => strtolower($l['entry_type'] ?? ''),
                'amount' => (float) ($l['amount'] ?? 0),
                'narration' => $l['narration'] ?? null,
            ];
        }, $data['lines']);

        return $this->ledgerService->recordTransaction(
            $lines,
            $data['description'] ?? null,
            $data['date'] ?? null,
            $data['reference_type'] ?? null,
            $data['reference_id'] ?? null,
            $data['created_by'] ?? null
        );
    }

    /**
     * Build a simple trial balance: per-account debit and credit totals and net balance.
     */
    public function trialBalance(?string $from = null, ?string $to = null)
    {
        $rowsQuery = DB::table('ledger_entries as le')
            ->join('ledger_transactions as lt', 'lt.id', '=', 'le.transaction_id')
            ->select('le.account_id', 'le.entry_type', DB::raw('SUM(le.amount) as total'))
            ->groupBy('le.account_id', 'le.entry_type');

        if (!empty($from)) {
            $rowsQuery->whereDate('lt.date', '>=', $from);
        }

        if (!empty($to)) {
            $rowsQuery->whereDate('lt.date', '<=', $to);
        }

        $rows = $rowsQuery
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
            $type = strtolower((string) ($account->type ?? ''));
            $normalSide = in_array($type, ['liability', 'equity', 'income'], true) ? 'credit' : 'debit';

            $net = $normalSide === 'credit'
                ? round($vals['credit'] - $vals['debit'], 2)
                : round($vals['debit'] - $vals['credit'], 2);

            $result[] = [
                'account_id' => $acctId,
                'code' => $account->code ?? null,
                'name' => $account->name ?? null,
                'type' => $account->type ?? null,
                'account_group' => $account->account_group ?? null,
                'is_profit_loss' => (bool) ($account->is_profit_loss ?? false),
                'normal_side' => $normalSide,
                'debit' => $vals['debit'],
                'credit' => $vals['credit'],
                'net' => $net,
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

    public function profitLoss(?string $from = null, ?string $to = null): array
    {
        $trial = $this->trialBalance($from, $to);
        $rows = collect($trial['rows'] ?? []);

        $incomeRows = $rows
            ->filter(fn ($row) => $this->isIncomeLike($row))
            ->map(function ($row) {
                $amount = round((float) ($row['credit'] ?? 0) - (float) ($row['debit'] ?? 0), 2);
                return [
                    'account_id' => $row['account_id'],
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'amount' => $amount,
                ];
            })
            ->filter(fn ($row) => abs((float) $row['amount']) > 0)
            ->values();

        $expenseRows = $rows
            ->filter(fn ($row) => $this->isExpenseLike($row))
            ->map(function ($row) {
                $amount = round((float) ($row['debit'] ?? 0) - (float) ($row['credit'] ?? 0), 2);
                return [
                    'account_id' => $row['account_id'],
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'amount' => $amount,
                ];
            })
            ->filter(fn ($row) => abs((float) $row['amount']) > 0)
            ->values();

        $totalIncome = round((float) $incomeRows->sum('amount'), 2);
        $totalExpense = round((float) $expenseRows->sum('amount'), 2);
        $netProfit = round($totalIncome - $totalExpense, 2);

        return [
            'income_rows' => $incomeRows->all(),
            'expense_rows' => $expenseRows->all(),
            'totals' => [
                'income' => $totalIncome,
                'expense' => $totalExpense,
                'net_profit' => $netProfit,
            ],
            'period' => [
                'from' => $from,
                'to' => $to,
            ],
        ];
    }

    public function balanceSheet(?string $asOf = null): array
    {
        $asOf = $asOf ?: now()->toDateString();
        $trial = $this->trialBalance(null, $asOf);
        $rows = collect($trial['rows'] ?? []);

        $assets = $rows
            ->filter(fn ($row) => ($row['type'] ?? null) === 'asset')
            ->map(function ($row) {
                $balance = round((float) ($row['debit'] ?? 0) - (float) ($row['credit'] ?? 0), 2);
                return [
                    'account_id' => $row['account_id'],
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'amount' => $balance,
                ];
            })
            ->filter(fn ($row) => abs((float) $row['amount']) > 0)
            ->values();

        $liabilities = $rows
            ->filter(fn ($row) => ($row['type'] ?? null) === 'liability')
            ->map(function ($row) {
                $balance = round((float) ($row['credit'] ?? 0) - (float) ($row['debit'] ?? 0), 2);
                return [
                    'account_id' => $row['account_id'],
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'amount' => $balance,
                ];
            })
            ->filter(fn ($row) => abs((float) $row['amount']) > 0)
            ->values();

        $equityRows = $rows
            ->filter(fn ($row) => ($row['type'] ?? null) === 'equity')
            ->map(function ($row) {
                $balance = round((float) ($row['credit'] ?? 0) - (float) ($row['debit'] ?? 0), 2);
                return [
                    'account_id' => $row['account_id'],
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'amount' => $balance,
                ];
            })
            ->filter(fn ($row) => abs((float) $row['amount']) > 0)
            ->values();

        $incomeTotal = round((float) $rows
            ->filter(fn ($row) => $this->isIncomeLike($row))
            ->sum(fn ($row) => (float) ($row['credit'] ?? 0) - (float) ($row['debit'] ?? 0)), 2);

        $expenseTotal = round((float) $rows
            ->filter(fn ($row) => $this->isExpenseLike($row))
            ->sum(fn ($row) => (float) ($row['debit'] ?? 0) - (float) ($row['credit'] ?? 0)), 2);

        $currentEarnings = round($incomeTotal - $expenseTotal, 2);
        if ($currentEarnings != 0.0) {
            $equityRows->push([
                'account_id' => null,
                'code' => 'CURR_EARN',
                'name' => 'Current Period Earnings',
                'amount' => $currentEarnings,
            ]);
        }

        $totalAssets = round((float) $assets->sum('amount'), 2);
        $totalLiabilities = round((float) $liabilities->sum('amount'), 2);
        $totalEquity = round((float) $equityRows->sum('amount'), 2);

        return [
            'assets' => $assets->all(),
            'liabilities' => $liabilities->all(),
            'equity' => $equityRows->values()->all(),
            'totals' => [
                'assets' => $totalAssets,
                'liabilities' => $totalLiabilities,
                'equity' => $totalEquity,
                'liabilities_and_equity' => round($totalLiabilities + $totalEquity, 2),
                'difference' => round($totalAssets - ($totalLiabilities + $totalEquity), 2),
            ],
            'as_of' => $asOf,
        ];
    }

    public function cashFlow(?string $from = null, ?string $to = null): array
    {
        $to = $to ?: now()->toDateString();

        $cashAccountIds = Account::query()
            ->where('type', 'asset')
            ->where(function ($q) {
                $q->where('code', 'like', '%CASH%')
                    ->orWhere('code', 'like', '%BANK%')
                    ->orWhere('name', 'like', '%cash%')
                    ->orWhere('name', 'like', '%bank%');
            })
            ->pluck('id')
            ->all();

        if (empty($cashAccountIds)) {
            return [
                'rows' => [],
                'totals' => ['inflow' => 0.0, 'outflow' => 0.0, 'net' => 0.0],
                'period' => ['from' => $from, 'to' => $to],
            ];
        }

        $query = DB::table('ledger_entries as le')
            ->join('ledger_transactions as lt', 'lt.id', '=', 'le.transaction_id')
            ->whereIn('le.account_id', $cashAccountIds)
            ->select(
                'lt.id as transaction_id',
                'lt.uuid',
                'lt.date',
                'lt.description',
                'lt.reference_type',
                DB::raw("SUM(CASE WHEN le.entry_type = 'debit' THEN le.amount ELSE 0 END) as inflow"),
                DB::raw("SUM(CASE WHEN le.entry_type = 'credit' THEN le.amount ELSE 0 END) as outflow")
            )
            ->groupBy('lt.id', 'lt.uuid', 'lt.date', 'lt.description', 'lt.reference_type')
            ->orderBy('lt.date');

        if (!empty($from)) {
            $query->whereDate('lt.date', '>=', $from);
        }

        if (!empty($to)) {
            $query->whereDate('lt.date', '<=', $to);
        }

        $rows = $query->get()->map(function ($row) {
            $inflow = round((float) ($row->inflow ?? 0), 2);
            $outflow = round((float) ($row->outflow ?? 0), 2);

            return [
                'transaction_id' => $row->transaction_id,
                'uuid' => $row->uuid,
                'date' => $row->date,
                'description' => $row->description,
                'reference_type' => $row->reference_type,
                'inflow' => $inflow,
                'outflow' => $outflow,
                'net' => round($inflow - $outflow, 2),
            ];
        })->values();

        $totalInflow = round((float) $rows->sum('inflow'), 2);
        $totalOutflow = round((float) $rows->sum('outflow'), 2);

        return [
            'rows' => $rows->all(),
            'totals' => [
                'inflow' => $totalInflow,
                'outflow' => $totalOutflow,
                'net' => round($totalInflow - $totalOutflow, 2),
            ],
            'period' => ['from' => $from, 'to' => $to],
        ];
    }

    public function financialSummary(?string $from = null, ?string $to = null, ?string $asOf = null): array
    {
        $to = $to ?: now()->toDateString();
        $asOf = $asOf ?: $to;

        $balanceSheet = $this->balanceSheet($asOf);
        $profitLoss = $this->profitLoss($from, $to);
        $cashFlow = $this->cashFlow($from, $to);

        return [
            'totals' => [
                'assets' => round((float) ($balanceSheet['totals']['assets'] ?? 0), 2),
                'liabilities' => round((float) ($balanceSheet['totals']['liabilities'] ?? 0), 2),
                'equity' => round((float) ($balanceSheet['totals']['equity'] ?? 0), 2),
                'income' => round((float) ($profitLoss['totals']['income'] ?? 0), 2),
                'expense' => round((float) ($profitLoss['totals']['expense'] ?? 0), 2),
                'net_profit' => round((float) ($profitLoss['totals']['net_profit'] ?? 0), 2),
                'cash_inflow' => round((float) ($cashFlow['totals']['inflow'] ?? 0), 2),
                'cash_outflow' => round((float) ($cashFlow['totals']['outflow'] ?? 0), 2),
                'cash_net' => round((float) ($cashFlow['totals']['net'] ?? 0), 2),
            ],
            'period' => [
                'from' => $from,
                'to' => $to,
                'as_of' => $asOf,
            ],
        ];
    }

    private function isExpenseLike(array $row): bool
    {
        $type = strtolower((string) ($row['type'] ?? ''));
        $group = strtoupper((string) ($row['account_group'] ?? ''));
        $code = strtoupper((string) ($row['code'] ?? ''));
        $name = strtolower((string) ($row['name'] ?? ''));

        if ($type === 'expense') {
            return true;
        }

        if (str_contains($group, 'EXPENSE')) {
            return true;
        }

        if (str_contains($code, '_EXP') || str_contains($name, 'expense')) {
            return true;
        }

        return false;
    }

    private function isIncomeLike(array $row): bool
    {
        $type = strtolower((string) ($row['type'] ?? ''));
        $group = strtoupper((string) ($row['account_group'] ?? ''));
        $code = strtoupper((string) ($row['code'] ?? ''));
        $name = strtolower((string) ($row['name'] ?? ''));

        if ($type === 'income') {
            return true;
        }

        if (str_contains($group, 'INCOME') || str_contains($group, 'REVENUE')) {
            return true;
        }

        if (str_contains($code, '_INC') || str_contains($name, 'income') || str_contains($name, 'revenue')) {
            return true;
        }

        return false;
    }
}
