<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\AccountBalance;
use App\Models\CashCounterSession;
use App\Services\BillingLedgerService;
use App\Services\CashCounterLedgerService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HospitalAccountingIntegrationsTest extends TestCase
{
    use RefreshDatabase;

    private function seedCoreAccounts(): void
    {
        foreach ([
            ['code' => 'CASH', 'name' => 'Cash on Hand', 'type' => 'asset'],
            ['code' => 'BANK', 'name' => 'Bank Accounts', 'type' => 'asset'],
            ['code' => 'AR', 'name' => 'Accounts Receivable', 'type' => 'asset'],
            ['code' => 'OPD_INC', 'name' => 'OPD Income', 'type' => 'income'],
            ['code' => 'PATH_INC', 'name' => 'Pathology Income', 'type' => 'income'],
            ['code' => 'SALES', 'name' => 'Sales Income', 'type' => 'income'],
            ['code' => 'ADMIN_EXP', 'name' => 'Administrative Expense', 'type' => 'expense'],
            ['code' => 'SALARY_EXP', 'name' => 'Salary Expense', 'type' => 'expense'],
        ] as $definition) {
            $account = Account::create($definition);
            AccountBalance::create(['account_id' => $account->id, 'balance' => 0, 'profit' => 0, 'loss' => 0]);
        }
    }

    public function test_billing_ledger_service_splits_income_by_category(): void
    {
        $this->seedCoreAccounts();
        $service = app(BillingLedgerService::class);

        $service->recordNewBill(
            [
                ['category' => 'OPD', 'net_amount' => 300],
                ['category' => 'Pathology', 'net_amount' => 200],
            ],
            400,
            100,
            'Cash',
            'BILL-1001',
            now()->toDateString(),
            1001,
            1
        );

        $opd = Account::where('code', 'OPD_INC')->firstOrFail();
        $path = Account::where('code', 'PATH_INC')->firstOrFail();
        $cash = Account::where('code', 'CASH')->firstOrFail();
        $ar = Account::where('code', 'AR')->firstOrFail();

        $this->assertDatabaseHas('ledger_entries', ['account_id' => $cash->id, 'entry_type' => 'debit', 'amount' => 400]);
        $this->assertDatabaseHas('ledger_entries', ['account_id' => $ar->id, 'entry_type' => 'debit', 'amount' => 100]);
        $this->assertDatabaseHas('ledger_entries', ['account_id' => $opd->id, 'entry_type' => 'credit', 'amount' => 300]);
        $this->assertDatabaseHas('ledger_entries', ['account_id' => $path->id, 'entry_type' => 'credit', 'amount' => 200]);
    }

    public function test_cash_counter_close_posts_shortage_to_admin_expense(): void
    {
        $this->seedCoreAccounts();
        $service = app(CashCounterLedgerService::class);

        $session = CashCounterSession::create([
            'counter_name' => 'Billing Counter',
            'user_name' => 'Cashier',
            'shift_name' => 'Morning',
            'opening_amount' => 1000,
            'expected_amount' => 5000,
            'closing_amount' => 4950,
            'difference_amount' => -50,
            'status' => 'closed',
            'opened_at' => now()->subHours(8),
            'closed_at' => now(),
            'created_by' => 1,
        ]);

        $transactionId = $service->postCloseAdjustment($session);

        $this->assertNotNull($transactionId);
        $adminExp = Account::where('code', 'ADMIN_EXP')->firstOrFail();
        $cash = Account::where('code', 'CASH')->firstOrFail();
        $this->assertDatabaseHas('ledger_entries', ['account_id' => $adminExp->id, 'entry_type' => 'debit', 'amount' => 50]);
        $this->assertDatabaseHas('ledger_entries', ['account_id' => $cash->id, 'entry_type' => 'credit', 'amount' => 50]);
    }

    public function test_payroll_expense_uses_bank_for_card_payment(): void
    {
        $this->seedCoreAccounts();
        $ledger = app(LedgerService::class);

        $ledger->recordExpense('SALARY_EXP', $ledger->resolvePaymentAccountCode('Card'), 1500, 'Salary test');

        $bank = Account::where('code', 'BANK')->firstOrFail();
        $salary = Account::where('code', 'SALARY_EXP')->firstOrFail();
        $this->assertDatabaseHas('ledger_entries', ['account_id' => $salary->id, 'entry_type' => 'debit', 'amount' => 1500]);
        $this->assertDatabaseHas('ledger_entries', ['account_id' => $bank->id, 'entry_type' => 'credit', 'amount' => 1500]);
    }
}
