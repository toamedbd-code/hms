<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\SupplierPayment;
use Illuminate\Database\Seeder;

class SupplierPaymentExpenseBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $expenseHead = ExpenseHead::firstOrCreate(
            ['name' => 'Supplier Payment'],
            ['status' => 'Active']
        );

        $payments = SupplierPayment::with('supplier')->get();
        $upserted = 0;
        $deleted = 0;

        foreach ($payments as $payment) {
            $billNumber = 'SPAY-' . $payment->id;
            $paidAmount = (float) ($payment->paid_amount ?? 0);

            if ($paidAmount <= 0) {
                $deleted += Expense::where('bill_number', $billNumber)->delete();
                continue;
            }

            Expense::updateOrCreate(
                ['bill_number' => $billNumber],
                [
                    'expense_header_id' => $expenseHead->id,
                    'bill_number' => $billNumber,
                    'case_id' => null,
                    'name' => (string) (optional($payment->supplier)->name ?? 'Supplier Payment'),
                    'description' => 'Supplier payment expense (Payment #' . $payment->id . ')',
                    'amount' => $paidAmount,
                    'date' => optional($payment->payment_date)->format('Y-m-d') ?? now()->toDateString(),
                    'status' => 'Active',
                    'updated_by' => null,
                    'created_by' => null,
                ]
            );

            $upserted++;
        }

        $this->command?->info('Supplier payment expense backfill completed.');
        $this->command?->line('supplier_payments=' . $payments->count());
        $this->command?->line('upserted=' . $upserted);
        $this->command?->line('deleted=' . $deleted);
    }
}
