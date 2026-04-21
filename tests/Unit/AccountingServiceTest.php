<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Account;
use App\Services\AccountingService;

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
}
