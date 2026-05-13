<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\LedgerTransaction;
use App\Models\MedicinePurchase;
use App\Services\LedgerService;
use Illuminate\Console\Command;

class SyncPurchaseLedgerCommand extends Command
{
    protected $signature = 'accounts:sync-purchase-ledger {--from=} {--to=}';

    protected $description = 'Sync medicine purchases into ledger transactions for complete asset and payable tracking';

    public function handle(LedgerService $ledgerService): int
    {
        $from = $this->option('from');
        $to = $this->option('to');

        $query = MedicinePurchase::query();

        if (!empty($from)) {
            $query->whereDate('purchase_date', '>=', $from);
        }

        if (!empty($to)) {
            $query->whereDate('purchase_date', '<=', $to);
        }

        $purchases = $query->orderBy('purchase_date')->orderBy('id')->get();
        $this->info('Found purchases: ' . $purchases->count());

        $synced = 0;
        $skipped = 0;

        foreach ($purchases as $purchase) {
            $totalAmount = round((float) ($purchase->total_amount ?? 0), 2);
            if ($totalAmount <= 0) {
                $skipped++;
                continue;
            }

            $existing = LedgerTransaction::query()
                ->where('reference_type', 'MedicinePurchase')
                ->where('reference_id', $purchase->id)
                ->first();

            if ($existing) {
                $ledgerService->deleteTransaction($existing->id);
            }

            $paidAmount = min(max(round((float) ($purchase->paid_amount ?? 0), 2), 0), $totalAmount);
            $dueAmount = round(max(0, $totalAmount - $paidAmount), 2);

            $inventoryCode = $this->ensureSystemAccount('PHARM_INV', 'Pharmacy Inventory', 'asset', 'ASSET', false);
            $cashCode = $this->ensureSystemAccount('CASH', 'Cash', 'asset', 'ASSET', false);
            $payableCode = $this->ensureSystemAccount('AP_SUPPLIER', 'Supplier Payable', 'liability', 'LIABILITY', false);

            $lines = [
                ['account_code' => $inventoryCode, 'entry_type' => 'debit', 'amount' => $totalAmount],
            ];

            if ($paidAmount > 0) {
                $lines[] = ['account_code' => $cashCode, 'entry_type' => 'credit', 'amount' => $paidAmount];
            }

            if ($dueAmount > 0) {
                $lines[] = ['account_code' => $payableCode, 'entry_type' => 'credit', 'amount' => $dueAmount];
            }

            $ledgerService->recordTransaction(
                $lines,
                'Medicine purchase ' . ((string) ($purchase->purchase_number ?? $purchase->id)),
                $purchase->purchase_date ? date('Y-m-d', strtotime((string) $purchase->purchase_date)) : now()->toDateString(),
                'MedicinePurchase',
                $purchase->id,
                $purchase->created_by ?? null
            );

            $synced++;
        }

        $this->info('Synced: ' . $synced . ', Skipped: ' . $skipped);

        return self::SUCCESS;
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
