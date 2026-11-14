<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\LedgerTransaction;
use App\Models\MedicinePurchase;
use App\Models\SupplierPayment;
use App\Services\LedgerService;
use Illuminate\Console\Command;

class SyncPurchaseLedgerCommand extends Command
{
    protected $signature = 'accounts:sync-purchase-ledger {--from=} {--to=}';

    protected $description = 'Sync medicine purchases, supplier payments, inventory, payables and purchase expenses into the ledger';

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

            $expenseCode = $this->ensureSystemAccount('PHARM_PURCHASE_EXP', 'Pharmacy Purchase Expense', 'expense', 'EXPENSE', true);
            $lines = [];
            if ($dueAmount > 0) {
                $lines[] = ['account_code' => $inventoryCode, 'entry_type' => 'debit', 'amount' => $dueAmount];
                $lines[] = ['account_code' => $payableCode, 'entry_type' => 'credit', 'amount' => $dueAmount];
            }
            if ($paidAmount > 0) {
                $lines[] = ['account_code' => $payableCode, 'entry_type' => 'debit', 'amount' => $paidAmount];
                $lines[] = ['account_code' => $expenseCode, 'entry_type' => 'debit', 'amount' => $paidAmount];
                $lines[] = ['account_code' => $inventoryCode, 'entry_type' => 'credit', 'amount' => $paidAmount];
                $lines[] = ['account_code' => $cashCode, 'entry_type' => 'credit', 'amount' => $paidAmount];
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

        $supplierPayments = SupplierPayment::query()
            ->where('paid_amount', '>', 0)
            ->when(!empty($from), fn ($query) => $query->whereDate('payment_date', '>=', $from))
            ->when(!empty($to), fn ($query) => $query->whereDate('payment_date', '<=', $to))
            ->where(function ($query) {
                $query->whereNull('notes')
                    ->orWhere(function ($notesQuery) {
                        $notesQuery->where('notes', 'not like', 'Initial payment from purchase %')
                            ->where('notes', 'not like', 'Initial payment from site purchase %');
                    });
            })
            ->orderBy('id')
            ->get();

        $directPaymentCount = 0;
        foreach ($supplierPayments as $payment) {
            $existing = LedgerTransaction::query()
                ->where('reference_type', 'SupplierPayment')
                ->where('reference_id', $payment->id)
                ->first();
            if ($existing) {
                $ledgerService->deleteTransaction($existing->id);
            }

            $paidAmount = round((float) $payment->paid_amount, 2);
            if ($paidAmount <= 0) {
                continue;
            }

            $expenseCode = $this->ensureSystemAccount('PHARM_PURCHASE_EXP', 'Pharmacy Purchase Expense', 'expense', 'EXPENSE', true);
            $cashAccountCode = Account::query()->whereKey($payment->payment_account_id)->value('code');
            $cashCode = $cashAccountCode ?: $this->ensureSystemAccount('CASH', 'Cash', 'asset', 'ASSET', false);

            $ledgerService->recordTransaction(
                [
                    ['account_code' => $expenseCode, 'entry_type' => 'debit', 'amount' => $paidAmount],
                    ['account_code' => $cashCode, 'entry_type' => 'credit', 'amount' => $paidAmount],
                ],
                'Supplier payment #' . $payment->id,
                optional($payment->payment_date)->format('Y-m-d') ?? now()->toDateString(),
                'SupplierPayment',
                $payment->id,
                null
            );
            $directPaymentCount++;
        }

        $this->info('Synced purchases: ' . $synced . ', direct supplier payments: ' . $directPaymentCount . ', skipped: ' . $skipped);

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
