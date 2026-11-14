<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\LedgerTransaction;
use App\Models\StorePurchaseOrder;
use App\Models\StorePurchasePayment;
use App\Models\StoreSupplier;
use App\Services\StorePurchasePaymentLedgerService;
use App\Services\StorePurchasePaymentService;
use App\Services\AccountingService;
use App\Services\ReportAccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StorePurchasePaymentLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_supplier_payment_expenses_paid_amount_and_sync_is_idempotent(): void
    {
        $cash = Account::create([
            'code' => 'CASH',
            'name' => 'Cash',
            'type' => 'asset',
            'account_group' => 'ASSET',
            'is_active' => true,
        ]);
        $supplier = StoreSupplier::create(['name' => 'Test Store Supplier']);
        $order = StorePurchaseOrder::create([
            'purchase_order_no' => 'PO-TEST-001',
            'store_supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'total_amount' => 150,
            'status' => 'Received',
        ]);
        $payment = StorePurchasePayment::create([
            'store_purchase_order_id' => $order->id,
            'payment_account_id' => $cash->id,
            'amount' => 100,
            'payment_date' => now()->toDateString(),
        ]);

        $service = app(StorePurchasePaymentLedgerService::class);
        $service->sync($payment);
        $service->sync($payment);

        $expense = Account::where('code', 'STORE_PURCHASE_EXP')->firstOrFail();
        $inventory = Account::where('code', 'INV')->firstOrFail();
        $payable = Account::where('code', 'AP_SUPPLIER')->firstOrFail();
        $entries = LedgerTransaction::query()
            ->where('reference_type', 'StorePurchasePayment')
            ->where('reference_id', $payment->id)
            ->with('entries')
            ->get();

        $this->assertCount(1, $entries);
        $this->assertCount(4, $entries->first()->entries);
        $this->assertDatabaseHas('ledger_entries', [
            'transaction_id' => $entries->first()->id,
            'account_id' => $expense->id,
            'entry_type' => 'debit',
            'amount' => 100,
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'transaction_id' => $entries->first()->id,
            'account_id' => $inventory->id,
            'entry_type' => 'credit',
            'amount' => 100,
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'transaction_id' => $entries->first()->id,
            'account_id' => $payable->id,
            'entry_type' => 'debit',
            'amount' => 100,
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'transaction_id' => $entries->first()->id,
            'account_id' => $cash->id,
            'entry_type' => 'credit',
            'amount' => 100,
        ]);

        $profitLoss = app(AccountingService::class)->profitLoss(
            now()->toDateString(),
            now()->toDateString()
        );
        $this->assertSame(100.0, (float) $profitLoss['totals']['expense']);
        $this->assertSame(100.0, app(ReportAccountingService::class)->getExpenseTotal([
            'single_date_range' => [
                now()->startOfDay()->toDateTimeString(),
                now()->endOfDay()->toDateTimeString(),
            ],
        ]));
        $reportTotals = app(ReportAccountingService::class)->calculateFinalIncomeTotals(
            collect([['paid_amount' => 700, 'due_collected' => 500]]),
            ['single_date' => now()]
        );
        $this->assertSame(100.0, (float) $reportTotals['total_expense']);
        $this->assertSame(1100.0, (float) $reportTotals['final_income']);
    }

    public function test_store_supplier_payment_rejects_non_cash_and_bank_accounts(): void
    {
        $inventory = Account::create([
            'code' => 'INV',
            'name' => 'Inventory',
            'type' => 'asset',
            'account_group' => 'ASSET',
            'is_active' => true,
        ]);
        $supplier = StoreSupplier::create(['name' => 'Test Store Supplier']);
        $order = StorePurchaseOrder::create([
            'purchase_order_no' => 'PO-TEST-002',
            'store_supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'total_amount' => 150,
            'status' => 'Received',
        ]);

        $this->expectException(ValidationException::class);

        app(StorePurchasePaymentService::class)->record($order->id, [
            'payment_account_id' => $inventory->id,
            'amount' => 1,
            'payment_date' => now()->toDateString(),
        ], null);
    }

    public function test_lab_supplier_payments_are_included_in_shared_expense_totals(): void
    {
        $supplierId = DB::table('lab_suppliers')->insertGetId([
            'name' => 'Lab Supplier',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = DB::table('lab_purchase_orders')->insertGetId([
            'order_no' => 'LAB-PO-EXPENSE-001',
            'lab_supplier_id' => $supplierId,
            'order_date' => now()->toDateString(),
            'total_amount' => 100,
            'status' => 'Received',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('lab_purchase_payments')->insert([
            'lab_purchase_order_id' => $orderId,
            'amount' => 25,
            'payment_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $expenseTotal = app(ReportAccountingService::class)->getExpenseTotal([
            'single_date_range' => [
                now()->startOfDay()->toDateTimeString(),
                now()->endOfDay()->toDateTimeString(),
            ],
        ]);

        $this->assertSame(25.0, $expenseTotal);
    }
}
