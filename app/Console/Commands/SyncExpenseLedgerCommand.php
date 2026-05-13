<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Expense;
use App\Models\LedgerTransaction;
use App\Services\LedgerService;
use Illuminate\Console\Command;

class SyncExpenseLedgerCommand extends Command
{
    protected $signature = 'accounts:sync-expense-ledger {--from=} {--to=}';

    protected $description = 'Sync active expenses into ledger transactions for accounting reports';

    public function handle(LedgerService $ledgerService): int
    {
        $from = $this->option('from');
        $to = $this->option('to');

        $query = Expense::query()->with('expenseHead')->where('status', 'Active');

        if (!empty($from)) {
            $query->whereDate('date', '>=', $from);
        }

        if (!empty($to)) {
            $query->whereDate('date', '<=', $to);
        }

        $expenses = $query->orderBy('date')->get();
        $this->info('Found active expenses: ' . $expenses->count());

        $synced = 0;
        $skipped = 0;

        foreach ($expenses as $expense) {
            $amount = round((float) ($expense->amount ?? 0), 2);
            if ($amount <= 0) {
                $skipped++;
                continue;
            }

            $existing = LedgerTransaction::query()
                ->where('reference_type', 'Expense')
                ->where('reference_id', $expense->id)
                ->first();

            if ($existing) {
                $ledgerService->deleteTransaction($existing->id);
            }

            $cashCode = $this->ensureSystemAccount('CASH', 'Cash', 'asset', 'ASSET', false);
            $expenseCode = $this->ensureExpenseAccountForHead($expense);

            $description = trim((string) ($expense->description ?? ''));
            if ($description === '') {
                $description = 'Expense #' . $expense->id . ' - ' . (($expense->name ?? '') ?: 'General Expense');
            }

            $date = $expense->date ? date('Y-m-d', strtotime((string) $expense->date)) : now()->toDateString();

            $ledgerService->recordExpense(
                $expenseCode,
                $cashCode,
                $amount,
                $description,
                $date,
                'Expense',
                $expense->id,
                $expense->created_by
            );

            $synced++;
        }

        $this->info('Synced: ' . $synced . ', Skipped: ' . $skipped);

        return self::SUCCESS;
    }

    private function ensureExpenseAccountForHead(Expense $expense): string
    {
        $headId = (int) ($expense->expense_header_id ?? 0);
        $headName = trim((string) ($expense->expenseHead?->name ?? 'General Expense'));

        $baseCode = $headId > 0 ? ('EXP_HEAD_' . $headId) : 'GEN_EXP';
        $baseCode = strtoupper((string) preg_replace('/[^A-Z0-9_]/', '_', $baseCode));
        $code = substr($baseCode, 0, 50);
        $name = $headName !== '' ? ('Expense - ' . $headName) : 'General Expense';

        return $this->ensureSystemAccount($code, $name, 'expense', 'EXPENSE', true);
    }

    private function ensureSystemAccount(string $code, string $name, string $type, string $group, bool $isProfitLoss): string
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
