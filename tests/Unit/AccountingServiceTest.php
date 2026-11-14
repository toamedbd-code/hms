<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Account;
use App\Models\Appoinment;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\OpdPatient;
use App\Models\Patient;
use App\Models\DueCollection;
use App\Services\AccountingService;
use App\Services\DashboardService;
use App\Services\ReportAccountingService;
use Illuminate\Support\Facades\DB;

class AccountingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(string $email, string $phone): \App\Models\Admin
    {
        return \App\Models\Admin::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => $email,
            'phone' => $phone,
            'password' => bcrypt('password'),
            'role_id' => null,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeBilling(array $attributes = []): \App\Models\Billing
    {
        $admin = $this->makeAdmin(
            'refund.' . uniqid() . '@example.com',
            '0171' . random_int(1000000, 9999999)
        );

        return \App\Models\Billing::create(array_merge([
            'patient_mobile' => '01700000000',
            'gender' => 'Male',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 1000,
            'payable_amount' => 1000,
            'paid_amt' => 1000,
            'invoice_amount' => 0,
            'receiving_amt' => 0,
            'return_amt' => 0,
            'due_amount' => 0,
            'created_by' => $admin->id,
            'payment_status' => 'Paid',
            'status' => 'Active',
        ], $attributes));
    }

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

        // Balances are kept on each account's normal side: an asset grows when
        // debited and an income account grows when credited, so both are positive.
        $this->assertDatabaseHas('account_balances', ['account_id' => $cash->id, 'balance' => 150.50]);
        $this->assertDatabaseHas('account_balances', ['account_id' => $sales->id, 'balance' => 150.50]);
        $this->assertDatabaseHas('account_balances', ['account_id' => $sales->id, 'profit' => 150.50]);
    }

    public function test_create_journal_reduces_balance_when_posted_against_the_normal_side()
    {
        $cash = Account::create(['code' => '1000', 'name' => 'Cash', 'type' => 'asset']);
        $sales = Account::create(['code' => '4000', 'name' => 'Sales', 'type' => 'income']);

        $service = app(AccountingService::class);

        // Refunding a patient reverses income: the income account is debited.
        $service->createJournal([
            'date' => now()->toDateString(),
            'description' => 'Refund',
            'lines' => [
                ['account_id' => $sales->id, 'entry_type' => 'debit', 'amount' => 40],
                ['account_id' => $cash->id, 'entry_type' => 'credit', 'amount' => 40],
            ],
        ]);

        $this->assertDatabaseHas('account_balances', ['account_id' => $cash->id, 'balance' => -40]);
        $this->assertDatabaseHas('account_balances', ['account_id' => $sales->id, 'balance' => -40]);
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

    public function test_calculate_final_income_totals_subtracts_processed_refunds_from_income()
    {
        $service = app(ReportAccountingService::class);

        // `return_amount` on a bill row is only a pending refundable balance. It
        // must not reduce net income until it is paid out from the Refund list.
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

        $dateConditions = [
            'single_date_range' => [now()->subDay()->startOfDay(), now()->endOfDay()],
        ];

        $pendingOnly = $service->calculateFinalIncomeTotals($billRows, $dateConditions);

        $this->assertSame(1500, $pendingOnly['total_paid']);
        $this->assertSame(100.0, (float) $pendingOnly['total_expense']);
        $this->assertSame(0.0, (float) $pendingOnly['total_return_amount']);
        $this->assertSame(1400.0, $pendingOnly['final_income']);

        DB::table('refund_transactions')->insert([
            'billing_id' => $this->makeBilling()->id,
            'refund_amount' => 100,
            'payment_method' => 'cash',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $afterRefund = $service->calculateFinalIncomeTotals($billRows, $dateConditions);

        $this->assertSame(100.0, (float) $afterRefund['total_return_amount']);
        $this->assertSame(1300.0, $afterRefund['final_income']);
    }

    public function test_calculate_final_income_totals_ignores_deleted_refund_transactions()
    {
        $service = app(ReportAccountingService::class);

        $billRows = collect([
            ['paid_amount' => 1000, 'due_collected' => 0],
        ]);

        DB::table('refund_transactions')->insert([
            'billing_id' => $this->makeBilling()->id,
            'refund_amount' => 250,
            'payment_method' => 'cash',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => now(),
        ]);

        $totals = $service->calculateFinalIncomeTotals($billRows, [
            'single_date_range' => [now()->subDay()->startOfDay(), now()->endOfDay()],
        ]);

        $this->assertSame(0.0, (float) $totals['total_return_amount']);
        $this->assertSame(1000.0, $totals['final_income']);
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
        $this->assertSame(100.0, (float) $totals['total_expense']);
        $this->assertSame(1400.0, $totals['final_income']);
        $this->assertSame('0', (string) $totals['total_return_amount']);
    }

    public function test_calculate_final_income_totals_keeps_cash_basis_arithmetic_exact_to_the_cent()
    {
        $service = \Mockery::mock(ReportAccountingService::class)->makePartial();
        $service->shouldReceive('getDueCollectionTotal')->once()->andReturn(0.02);
        $service->shouldReceive('getInitialAppointmentReceipts')->once()->andReturn(0.03);
        $service->shouldReceive('getLabPaymentTotal')->once()->andReturn(0.04);
        $service->shouldReceive('getExpenseTotal')->once()->andReturn(0.05);
        $service->shouldReceive('getRefundTotal')->once()->andReturn(0.06);

        $totals = $service->calculateFinalIncomeTotals(collect([
            ['paid_amount' => '3.10'],
            ['paid_amount' => '2.20'],
            ['paid_amount' => '0.03'],
        ]), []);

        $this->assertSame(5.31, $totals['final_income']);
        $this->assertSame(5.36, $totals['income_before_expenses']);
    }

    public function test_dashboard_total_discount_is_display_only_and_not_deducted_twice_from_net_income(): void
    {
        $accountingService = \Mockery::mock(ReportAccountingService::class)->makePartial();
        $accountingService->shouldReceive('getBillRowsByDate')->twice()->andReturn(collect([
            [
                'total_amount' => 170061.98,
                'net_amount' => 118035.00,
                'vat_amount' => 0,
                'discount_amount' => 52026.98,
                'calculated_discount_amount' => 52027.00,
                'extra_discount' => 0,
            ],
        ]));
        $accountingService->shouldReceive('calculateFinalIncomeTotals')->once()->andReturn([
            'income_before_expenses' => 170061.98,
            'total_expense' => 0.04,
            'final_income' => 170061.94,
        ]);
        $accountingService->shouldReceive('getExpenseTotal')->once()->andReturn(0.04);

        $dashboardService = new DashboardService($accountingService);

        $totalIncome = $dashboardService->countTotalIncome([], []);
        $expense = $dashboardService->countExpense([]);

        $this->assertSame(52027.0, $dashboardService->countTotalDiscount([]));
        $this->assertSame(170061.98, $totalIncome);
        $this->assertSame(0.04, $expense);
        $this->assertSame(170061.94, $totalIncome - $expense);
        $this->assertSame(
            [80.02, 20.0],
            $dashboardService->addDiscountRoundingToDiagnosticIncomes(80, 20, [])
        );
    }

    public function test_report_net_income_includes_initial_appointment_receipts_without_double_counting_opd_due(): void
    {
        $admin = $this->makeAdmin(
            'appointment.' . uniqid() . '@example.com',
            '0171' . random_int(1000000, 9999999)
        );
        $patient = Patient::create([
            'name' => 'Appointment Test Patient',
            'phone' => '018' . random_int(10000000, 99999999),
            'status' => 'Active',
        ]);
        $transactionId = 'APP-' . strtoupper(uniqid());
        Appoinment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $admin->id,
            'doctor_fee' => 300,
            'appoinment_date' => now(),
            'transaction_id' => $transactionId,
            'discount_percentage' => 0,
            'status' => 'Active',
        ]);
        $opdPatient = OpdPatient::create([
            'patient_id' => $patient->id,
            'appointment_date' => now()->toDateString(),
            'reference' => $transactionId,
            'consultant_doctor_id' => $admin->id,
            'amount' => 300,
            'paid_amount' => 300,
            'balance_amount' => 0,
            'status' => 'Active',
        ]);
        DueCollection::create([
            'billing_id' => null,
            'collected_amount' => 100,
            'collected_at' => now(),
            'payment_method' => 'opd',
            'note' => 'OPD due collected for opd_patient_id:' . $opdPatient->id,
        ]);

        $service = app(ReportAccountingService::class);
        $dateConditions = [
            'single_date_range' => [now()->startOfDay(), now()->endOfDay()],
        ];
        $totals = $service->calculateFinalIncomeTotals(
            collect([['paid_amount' => 0, 'due_collected' => 100]]),
            $dateConditions
        );

        $this->assertSame(200.0, (float) $totals['total_appointment_receipts']);
        $this->assertSame(300.0, (float) $totals['final_income']);
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

    public function test_percentage_discount_total_includes_non_disposable_pathology_items()
    {
        $billing = $this->makeBilling([
            'total' => 1000,
            'discount_type' => 'percentage',
            'discount' => 10,
            'payable_amount' => 950,
        ]);

        \App\Models\BillItem::create([
            'billing_id' => $billing->id,
            'item_id' => 1,
            'item_name' => 'Blood Test',
            'category' => 'Pathology',
            'unit_price' => 500,
            'quantity' => 1,
            'total_amount' => 500,
            'discount' => 0,
            'net_amount' => 500,
            'status' => 'Active',
        ]);
        \App\Models\BillItem::create([
            'billing_id' => $billing->id,
            'item_id' => 2,
            'item_name' => 'Disposable Tube',
            'category' => 'Pathology',
            'unit_price' => 500,
            'quantity' => 1,
            'total_amount' => 500,
            'discount' => 0,
            'net_amount' => 500,
            'status' => 'Active',
        ]);

        $billRows = app(ReportAccountingService::class)->getBillRowsByDate([
            'single_date_range' => [now()->startOfDay(), now()->endOfDay()],
        ]);
        $totals = app(ReportAccountingService::class)->calculateBillTotals($billRows);

        $this->assertSame(50.0, (float) $totals['total_discount']);
    }

    public function test_get_bill_rows_by_date_keeps_an_older_billing_out_of_the_range_when_it_is_only_edited()
    {
        $service = app(ReportAccountingService::class);

        // Editing an old bill creates a pending refundable balance but no money
        // moves today, so the bill must not be pulled into today's report.
        $billing = $this->makeBilling([
            'paid_amt' => 0,
            'return_amt' => 1000,
            'created_at' => now()->subDays(2)->startOfDay(),
            'updated_at' => now()->startOfDay()->addHours(12),
        ]);

        $result = $service->getBillRowsByDate([
            'single_date_range' => [now()->startOfDay(), now()->endOfDay()],
        ]);

        $this->assertCount(0, $result);

        // It belongs to the day it was raised on.
        $originalDay = $service->getBillRowsByDate([
            'single_date_range' => [now()->subDays(2)->startOfDay(), now()->subDays(2)->endOfDay()],
        ]);

        $this->assertCount(1, $originalDay);
        $this->assertSame($billing->id, $originalDay->first()['billing_id']);
        $this->assertSame(1000.0, (float) $originalDay->first()['return_amt']);
    }

    public function test_get_bill_rows_by_date_pulls_in_an_older_billing_when_due_is_collected_in_range()
    {
        $service = app(ReportAccountingService::class);

        $billing = $this->makeBilling([
            'paid_amt' => 0,
            'due_amount' => 1000,
            'payment_status' => 'Pending',
            'created_at' => now()->subDays(2)->startOfDay(),
            'updated_at' => now()->subDays(2)->startOfDay(),
        ]);

        \App\Models\DueCollection::create([
            'billing_id' => $billing->id,
            'collected_amount' => 400,
            'collected_at' => now()->startOfDay()->addHours(10),
            'created_by' => $billing->created_by,
        ]);

        $result = $service->getBillRowsByDate([
            'single_date_range' => [now()->startOfDay(), now()->endOfDay()],
        ]);

        $this->assertCount(1, $result);
        $this->assertSame(400.0, (float) $result->first()['due_collected']);
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
