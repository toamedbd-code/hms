<?php

namespace App\Console\Commands;

use App\Models\StorePurchasePayment;
use App\Services\StorePurchasePaymentLedgerService;
use Illuminate\Console\Command;

class SyncStorePurchasePaymentLedgerCommand extends Command
{
    protected $signature = 'accounts:sync-store-purchase-payments';

    protected $description = 'Rebuild store supplier payment ledger entries with paid purchase expenses';

    public function handle(StorePurchasePaymentLedgerService $ledgerService): int
    {
        $payments = StorePurchasePayment::query()
            ->with('purchaseOrder:id,purchase_order_no')
            ->orderBy('id')
            ->get();

        foreach ($payments as $payment) {
            $ledgerService->sync($payment);
        }

        $this->info('Synchronized store supplier payments: ' . $payments->count());

        return self::SUCCESS;
    }
}
