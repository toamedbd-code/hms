<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Account;
use App\Services\AccountingService;

class ChartOfAccountsSeeder extends Seeder
{
    public function run()
    {
        $accounts = [
            ['code' => '1000', 'name' => 'Cash', 'type' => 'asset'],
            ['code' => '1100', 'name' => 'Bank', 'type' => 'asset'],
            ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability'],
            ['code' => '3000', 'name' => 'Equity', 'type' => 'equity'],
            ['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'income'],
            ['code' => '5000', 'name' => 'Expenses', 'type' => 'expense'],
        ];

        foreach ($accounts as $a) {
            Account::firstOrCreate(['code' => $a['code']], $a);
        }

        // Insert a sample balanced journal entry (cash sale)
        $cash = Account::where('code', '1000')->first();
        $sales = Account::where('code', '4000')->first();

        if ($cash && $sales) {
            $service = app(AccountingService::class);
            try {
                $service->createJournal([
                    'date' => now()->toDateString(),
                    'description' => 'Sample sale - cash received',
                    'lines' => [
                        ['account_id' => $cash->id, 'entry_type' => 'debit', 'amount' => 1000.00],
                        ['account_id' => $sales->id, 'entry_type' => 'credit', 'amount' => 1000.00],
                    ],
                ]);
            } catch (\Throwable $e) {
                // ignore failures in seeder
            }
        }
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Account;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaults = [
            ['code' => 'CASH', 'name' => 'Cash', 'type' => 'asset'],
            ['code' => 'AR', 'name' => 'Accounts Receivable', 'type' => 'asset'],
            ['code' => 'DIAG_INC', 'name' => 'Diagnostic Income', 'type' => 'income'],
            ['code' => 'DIAG_EXP', 'name' => 'Diagnostic Expense', 'type' => 'expense'],
            ['code' => 'COMMISSION_EXP', 'name' => 'Commission Expense', 'type' => 'expense'],
        ];

        foreach ($defaults as $d) {
            Account::firstOrCreate(['code' => $d['code']], $d);
        }
    }
}
