<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Account;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Services\AccountingService;
use App\Services\ReportAccountingService;

class AccountingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_journal_balances_and_updates_account_balances()
    {
        $cash = Account::create(['code' => '1000', 'name' => 'Cash', 'type' => 'asset']);
        $sales = Account::create(['code' => '4000', 'name' => 'Sales', 'type' => 'income']);

        $service = app(AccountingService::class);

        $tx = $service->createJournal([
            'date' => now()->toDateString(),
            'description' => 'Test sale',
            'lines' => [
                ['account_id' => $cash->id, 'entry_type' => 'debit', 'amount' => 150.50],
                ['account_id' => $sales->id, 'entry_type' => 'credit', 'amount' => 150.50],
            ],
        ]);

        $this->assertDatabaseHas('ledger_transactions', ['id' => $tx->id]);
        $this->assertDatabaseHas('ledger_entries', ['transaction_id' => $tx->id, 'account_id' => $cash->id, 'entry_type' => 'debit', 'amount' => 150.50]);
        $this->assertDatabaseHas('account_balances', ['account_id' => $cash->id, 'balance' => 150.50]);
        $this->assertDatabaseHas('account_balances', ['account_id' => $sales->id, 'balance' => -150.50]);
    }

    public function test_create_journal_throws_on_unbalanced_entries()
    {
        $cash = Account::create(['code' => '1000', 'name' => 'Cash', 'type' => 'asset']);
        $sales = Account::create(['code' => '4000', 'name' => 'Sales', 'type' => 'income']);

        $service = app(AccountingService::class);

        $this->expectException(\InvalidArgumentException::class);

        $service->createJournal([
            'date' => now()->toDateString(),
            'description' => 'Unbalanced',
            'lines' => [
                ['account_id' => $cash->id, 'entry_type' => 'debit', 'amount' => 100],
                ['account_id' => $sales->id, 'entry_type' => 'credit', 'amount' => 90],
            ],
        ]);
    }

    public function test_calculate_final_income_totals_subtracts_return_amount_from_income()
    {
        $service = app(ReportAccountingService::class);

        $billRows = collect([
            [
                'paid_amount' => 1000,
                'due_collected' => 0,
                'return_amount' => 100,
            ],
            [
                'paid_amount' => 500,
                'due_collected' => 0,
                'return_amount' => 0,
            ],
        ]);

        $expenseHead = ExpenseHead::create([
            'name' => 'Test Expense Head',
        ]);

        Expense::create([
            'expense_header_id' => $expenseHead->id,
            'name' => 'Test expense',
            'date' => now()->toDateString(),
            'description' => 'Test expense',
            'amount' => 100,
            'status' => 'Active',
        ]);

        $totals = $service->calculateFinalIncomeTotals($billRows, [
            'single_date_range' => [now()->subDay()->startOfDay(), now()->endOfDay()],
        ]);

        $this->assertSame(1500, $totals['total_paid']);
        $this->assertSame('100.00', (string) $totals['total_expense']);
        $this->assertSame(1300.0, $totals['final_income']);
        $this->assertSame('100', (string) $totals['total_return_amount']);
    }

    public function test_calculate_final_income_totals_accepts_single_date_conditions_without_single_date_range()
    {
        $service = app(ReportAccountingService::class);

        $billRows = collect([
            [
                'paid_amount' => 1000,
                'due_collected' => 0,
            ],
            [
                'paid_amount' => 500,
                'due_collected' => 0,
            ],
        ]);

        $expenseHead = ExpenseHead::create([
            'name' => 'Test Expense Head',
        ]);

        Expense::create([
            'expense_header_id' => $expenseHead->id,
            'name' => 'Test expense',
            'date' => now()->toDateString(),
            'description' => 'Test expense',
            'amount' => 100,
            'status' => 'Active',
        ]);

        $totals = $service->calculateFinalIncomeTotals($billRows, [
            'single_date' => now(),
        ]);

        $this->assertSame(1500, $totals['total_paid']);
        $this->assertSame('100.00', (string) $totals['total_expense']);
        $this->assertSame(1400.0, $totals['final_income']);
        $this->assertSame('0', (string) $totals['total_return_amount']);
    }

    public function test_get_bill_rows_by_date_handles_missing_return_amt_column_gracefully()
    {
        $service = app(ReportAccountingService::class);

        $this->assertTrue(method_exists($service, 'getBillRowsByDate'));

        $result = $service->getBillRowsByDate([
            'single_date_range' => [now()->subDay()->startOfDay(), now()->endOfDay()],
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
    }

    public function test_get_bill_rows_by_date_includes_edited_billing_with_return_amount_in_selected_range()
    {
        $service = app(ReportAccountingService::class);

        $admin = \App\Models\Admin::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '01700000001',
            'password' => bcrypt('password'),
            'role_id' => null,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $billing = \App\Models\Billing::create([
            'patient_mobile' => '01700000000',
            'gender' => 'Male',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 1000,
            'payable_amount' => 1000,
            'paid_amt' => 0,
            'invoice_amount' => 0,
            'receiving_amt' => 0,
            'return_amt' => 1000,
            'due_amount' => 0,
            'created_by' => $admin->id,
            'payment_status' => 'Paid',
            'status' => 'Active',
            'created_at' => now()->subDays(2)->startOfDay(),
            'updated_at' => now()->startOfDay()->addHours(12),
        ]);

        $result = $service->getBillRowsByDate([
            'single_date_range' => [now()->startOfDay(), now()->endOfDay()],
        ]);

        $this->assertCount(1, $result);
        $this->assertSame(1000.0, $result->first()['return_amount']);
    }

    public function test_get_bill_rows_by_date_only_counts_payments_and_due_collections_from_selected_range()
    {
        $service = app(ReportAccountingService::class);

        $admin = \App\Models\Admin::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin2@example.com',
            'phone' => '01700000002',
            'password' => bcrypt('password'),
            'role_id' => null,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $billing = \App\Models\Billing::create([
            'patient_mobile' => '01700000000',
            'gender' => 'Male',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 1000,
            'payable_amount' => 1000,
            'paid_amt' => 0,
            'invoice_amount' => 0,
            'receiving_amt' => 0,
            'return_amt' => 0,
            'due_amount' => 1000,
            'created_by' => $admin->id,
            'payment_status' => 'Pending',
            'status' => 'Active',
            'created_at' => now()->subHour(),
            'updated_at' => now(),
        ]);

        \App\Models\Payment::create([
            'billing_id' => $billing->id,
            'amount' => 300,
            'payment_method' => 'Cash',
            'received_by' => $admin->id,
            'payment_status' => 'Partial',
            'status' => 'Active',
            'created_at' => now()->subDay(),
        ]);

        \App\Models\DueCollection::create([
            'billing_id' => $billing->id,
            'collected_amount' => 200,
            'collected_at' => now()->subHour(),
            'created_by' => $admin->id,
        ]);

        $result = $service->getBillRowsByDate([
            'single_date_range' => [now()->startOfDay(), now()->endOfDay()],
        ]);

        $this->assertCount(1, $result);
        $this->assertSame(0.0, (float) $result->first()['paid_amount']);
        $this->assertSame(200.0, (float) $result->first()['due_collected']);
    }

    public function test_all_module_pdf_totals_prefer_filtered_due_collection_over_historical_billing_state()
    {
        $service = app(ReportAccountingService::class);

        $admin = \App\Models\Admin::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin3@example.com',
            'phone' => '01700000003',
            'password' => bcrypt('password'),
            'role_id' => null,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $billing = \App\Models\Billing::create([
            'patient_mobile' => '01700000000',
            'gender' => 'Male',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 1000,
            'payable_amount' => 1000,
            'paid_amt' => 500,
            'invoice_amount' => 0,
            'receiving_amt' => 0,
            'return_amt' => 0,
            'due_amount' => 500,
            'created_by' => $admin->id,
            'payment_status' => 'Partial',
            'status' => 'Active',
            'created_at' => now()->subHour(),
            'updated_at' => now(),
        ]);

        \App\Models\Payment::create([
            'billing_id' => $billing->id,
            'amount' => 500,
            'payment_method' => 'Cash',
            'received_by' => $admin->id,
            'payment_status' => 'Partial',
            'status' => 'Active',
            'created_at' => now()->subDay(),
        ]);

        \App\Models\DueCollection::create([
            'billing_id' => $billing->id,
            'collected_amount' => 50,
            'collected_at' => now()->startOfDay()->addHours(6),
            'created_by' => $admin->id,
        ]);

        $result = $service->getBillRowsByDate([
            'single_date' => now()->startOfDay(),
        ]);

        $this->assertCount(1, $result);
        $this->assertSame(50.0, (float) $result->first()['due_collected']);
        $this->assertSame(0.0, (float) $result->first()['paid_amount']);
    }
}
