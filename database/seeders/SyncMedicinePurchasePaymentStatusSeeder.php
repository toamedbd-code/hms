<?php

namespace Database\Seeders;

use App\Models\MedicinePurchase;
use App\Models\SupplierPayment;
use Illuminate\Database\Seeder;

class SyncMedicinePurchasePaymentStatusSeeder extends Seeder
{
    public function run(): void
    {
        $purchases = MedicinePurchase::query()->get();
        $updated = 0;

        foreach ($purchases as $purchase) {
            $linkedPayment = SupplierPayment::query()
                ->where('notes', 'like', 'Initial payment from purchase ' . $purchase->purchase_number . '%')
                ->latest('id')
                ->first();

            $paidAmount = (float) ($linkedPayment?->paid_amount ?? 0);
            $dueAmount = max(0, (float) $purchase->total_amount - $paidAmount);

            if ((float) $purchase->paid_amount !== $paidAmount || (float) $purchase->due_amount !== $dueAmount) {
                $purchase->update([
                    'paid_amount' => $paidAmount,
                    'due_amount' => $dueAmount,
                ]);

                $updated++;
            }
        }

        $this->command?->info('Medicine purchase payment sync completed.');
        $this->command?->line('purchases=' . $purchases->count());
        $this->command?->line('updated=' . $updated);
    }
}
