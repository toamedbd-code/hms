<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\AccountBalance;
use App\Services\LedgerService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BillingLedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedCoreAccounts(): void
    {
        foreach ([
            ['code' => 'CASH', 'name' => 'Cash on Hand', 'type' => 'asset'],
            ['code' => 'BANK', 'name' => 'Bank Accounts', 'type' => 'asset'],
            ['code' => 'AR', 'name' => 'Accounts Receivable', 'type' => 'asset'],
            ['code' => 'DIAG_INC', 'name' => 'Diagnostic Income', 'type' => 'income'],
        ] as $definition) {
            $account = Account::create($definition);
            AccountBalance::create(['account_id' => $account->id, 'balance' => 0, 'profit' => 0, 'loss' => 0]);
        }
    }

    public function test_resolve_payment_account_code_maps_hospital_payment_modes(): void
    {
        $this->seedCoreAccounts();
        $service = app(LedgerService::class);

        $this->assertSame('CASH', $service->resolvePaymentAccountCode('Cash'));
        $this->assertSame('BANK', $service->resolvePaymentAccountCode('Card'));
        $this->assertSame('BANK', $service->resolvePaymentAccountCode('Bank'));
        $this->assertSame('BANK', $service->resolvePaymentAccountCode('Mobile Banking'));
    }

    public function test_record_ar_collection_posts_cash_against_receivable(): void
    {
        $this->seedCoreAccounts();
        $service = app(LedgerService::class);

        $service->recordArCollection('CASH', 250.00, 'Due collection test');

        $cash = Account::where('code', 'CASH')->firstOrFail();
        $ar = Account::where('code', 'AR')->firstOrFail();

        $this->assertDatabaseHas('ledger_entries', [
            'account_id' => $cash->id,
            'entry_type' => 'debit',
            'amount' => 250.00,
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'account_id' => $ar->id,
            'entry_type' => 'credit',
            'amount' => 250.00,
        ]);
    }

    public function test_record_income_refund_reverses_patient_payment(): void
    {
        $this->seedCoreAccounts();
        $service = app(LedgerService::class);

        $service->recordIncomeRefund('DIAG_INC', 'CASH', 100.00, 'Refund test');

        $cash = Account::where('code', 'CASH')->firstOrFail();
        $income = Account::where('code', 'DIAG_INC')->firstOrFail();

        $this->assertDatabaseHas('ledger_entries', [
            'account_id' => $income->id,
            'entry_type' => 'debit',
            'amount' => 100.00,
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'account_id' => $cash->id,
            'entry_type' => 'credit',
            'amount' => 100.00,
        ]);
    }
}
