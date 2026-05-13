<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Account;
use App\Models\AccountBalance;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            // Assets
            ['code' => '1000', 'name' => 'Assets', 'type' => 'asset', 'parent_code' => null, 'account_group' => 'ASSET'],
            ['code' => '1100', 'name' => 'Current Assets', 'type' => 'asset', 'parent_code' => '1000', 'account_group' => 'ASSET'],
            ['code' => 'CASH', 'name' => 'Cash on Hand', 'type' => 'asset', 'parent_code' => '1100', 'account_group' => 'ASSET'],
            ['code' => 'BANK', 'name' => 'Bank Accounts', 'type' => 'asset', 'parent_code' => '1100', 'account_group' => 'ASSET'],
            ['code' => 'AR', 'name' => 'Accounts Receivable', 'type' => 'asset', 'parent_code' => '1100', 'account_group' => 'ASSET'],
            ['code' => 'INV', 'name' => 'Inventory', 'type' => 'asset', 'parent_code' => '1100', 'account_group' => 'ASSET'],
            ['code' => 'ADV_STAFF', 'name' => 'Staff Advance', 'type' => 'asset', 'parent_code' => '1100', 'account_group' => 'ASSET'],
            ['code' => '1200', 'name' => 'Non Current Assets', 'type' => 'asset', 'parent_code' => '1000', 'account_group' => 'ASSET'],
            ['code' => 'FA', 'name' => 'Fixed Assets', 'type' => 'asset', 'parent_code' => '1200', 'account_group' => 'ASSET'],
            ['code' => 'ACC_DEPR', 'name' => 'Accumulated Depreciation', 'type' => 'asset', 'parent_code' => '1200', 'account_group' => 'ASSET'],

            // Liabilities
            ['code' => '2000', 'name' => 'Liabilities', 'type' => 'liability', 'parent_code' => null, 'account_group' => 'LIABILITY'],
            ['code' => '2100', 'name' => 'Current Liabilities', 'type' => 'liability', 'parent_code' => '2000', 'account_group' => 'LIABILITY'],
            ['code' => 'AP', 'name' => 'Accounts Payable', 'type' => 'liability', 'parent_code' => '2100', 'account_group' => 'LIABILITY'],
            ['code' => 'ACCRUED_EXP', 'name' => 'Accrued Expenses', 'type' => 'liability', 'parent_code' => '2100', 'account_group' => 'LIABILITY'],
            ['code' => 'TAX_PAY', 'name' => 'Tax Payable', 'type' => 'liability', 'parent_code' => '2100', 'account_group' => 'LIABILITY'],
            ['code' => 'LOAN', 'name' => 'Loans & Borrowings', 'type' => 'liability', 'parent_code' => '2000', 'account_group' => 'LIABILITY'],

            // Equity
            ['code' => '3000', 'name' => 'Equity', 'type' => 'equity', 'parent_code' => null, 'account_group' => 'EQUITY'],
            ['code' => 'OWNER_CAP', 'name' => "Owner's Capital", 'type' => 'equity', 'parent_code' => '3000', 'account_group' => 'EQUITY'],
            ['code' => 'RETAINED', 'name' => 'Retained Earnings', 'type' => 'equity', 'parent_code' => '3000', 'account_group' => 'EQUITY'],

            // Income
            ['code' => '4000', 'name' => 'Income', 'type' => 'income', 'parent_code' => null, 'account_group' => 'INCOME', 'is_profit_loss' => true],
            ['code' => 'OPD_INC', 'name' => 'OPD Income', 'type' => 'income', 'parent_code' => '4000', 'account_group' => 'INCOME', 'is_profit_loss' => true],
            ['code' => 'IPD_INC', 'name' => 'IPD Income', 'type' => 'income', 'parent_code' => '4000', 'account_group' => 'INCOME', 'is_profit_loss' => true],
            ['code' => 'PATH_INC', 'name' => 'Pathology Income', 'type' => 'income', 'parent_code' => '4000', 'account_group' => 'INCOME', 'is_profit_loss' => true],
            ['code' => 'RAD_INC', 'name' => 'Radiology Income', 'type' => 'income', 'parent_code' => '4000', 'account_group' => 'INCOME', 'is_profit_loss' => true],
            ['code' => 'PHARMACY_SALES', 'name' => 'Pharmacy Sales', 'type' => 'income', 'parent_code' => '4000', 'account_group' => 'INCOME', 'is_profit_loss' => true],
            ['code' => 'DIAG_INC', 'name' => 'Diagnostic Income', 'type' => 'income', 'parent_code' => '4000', 'account_group' => 'INCOME', 'is_profit_loss' => true],
            ['code' => 'SALES', 'name' => 'Sales Income', 'type' => 'income', 'parent_code' => '4000', 'account_group' => 'INCOME', 'is_profit_loss' => true],

            // Expenses
            ['code' => '5000', 'name' => 'Expenses', 'type' => 'expense', 'parent_code' => null, 'account_group' => 'EXPENSE', 'is_profit_loss' => true],
            ['code' => 'SALARY_EXP', 'name' => 'Salary Expense', 'type' => 'expense', 'parent_code' => '5000', 'account_group' => 'EXPENSE', 'is_profit_loss' => true],
            ['code' => 'MED_SUP_EXP', 'name' => 'Medical Supplies Expense', 'type' => 'expense', 'parent_code' => '5000', 'account_group' => 'EXPENSE', 'is_profit_loss' => true],
            ['code' => 'UTIL_EXP', 'name' => 'Utilities Expense', 'type' => 'expense', 'parent_code' => '5000', 'account_group' => 'EXPENSE', 'is_profit_loss' => true],
            ['code' => 'RENT_EXP', 'name' => 'Rent Expense', 'type' => 'expense', 'parent_code' => '5000', 'account_group' => 'EXPENSE', 'is_profit_loss' => true],
            ['code' => 'MAINT_EXP', 'name' => 'Maintenance Expense', 'type' => 'expense', 'parent_code' => '5000', 'account_group' => 'EXPENSE', 'is_profit_loss' => true],
            ['code' => 'ADMIN_EXP', 'name' => 'Administrative Expense', 'type' => 'expense', 'parent_code' => '5000', 'account_group' => 'EXPENSE', 'is_profit_loss' => true],
            ['code' => 'FIN_EXP', 'name' => 'Financial Charges', 'type' => 'expense', 'parent_code' => '5000', 'account_group' => 'EXPENSE', 'is_profit_loss' => true],
            ['code' => 'DEP_EXP', 'name' => 'Depreciation Expense', 'type' => 'expense', 'parent_code' => '5000', 'account_group' => 'EXPENSE', 'is_profit_loss' => true],
            ['code' => 'EXP', 'name' => 'General Expenses', 'type' => 'expense', 'parent_code' => '5000', 'account_group' => 'EXPENSE', 'is_profit_loss' => true],
        ];

        $codeToId = Account::query()->pluck('id', 'code')->toArray();

        foreach ($accounts as $definition) {
            $parentId = null;
            if (!empty($definition['parent_code']) && isset($codeToId[$definition['parent_code']])) {
                $parentId = $codeToId[$definition['parent_code']];
            }

            $account = Account::updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'type' => $definition['type'],
                    'parent_id' => $parentId,
                    'description' => $definition['name'],
                    'opening_balance' => 0,
                    'opening_balance_type' => in_array($definition['type'], ['asset', 'expense'], true) ? 'debit' : 'credit',
                    'account_group' => $definition['account_group'] ?? strtoupper($definition['type']),
                    'is_profit_loss' => (bool) ($definition['is_profit_loss'] ?? in_array($definition['type'], ['income', 'expense'], true)),
                    'is_active' => true,
                ]
            );

            $codeToId[$definition['code']] = $account->id;

            AccountBalance::updateOrCreate(
                ['account_id' => $account->id],
                ['balance' => 0, 'profit' => 0, 'loss' => 0]
            );
        }
    }
}


