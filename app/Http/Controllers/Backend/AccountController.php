<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use App\Models\Account;

class AccountController extends Controller
{
    public function index()
    {
        return Inertia::render('Backend/Account/Index', [
            'pageTitle' => 'Chart of Accounts',
            'accounts' => fn() => Account::with([
                'balance',
                'children.balance',
                'children.children.balance',
                'children.children.children.balance',
            ])->whereNull('parent_id')->orderBy('code')->get(),
        ]);
    }

    public function balances()
    {
        return Inertia::render('Backend/Account/Balances', [
            'pageTitle' => 'Account Balances'
        ]);
    }

    public function trialBalance()
    {
        return Inertia::render('Backend/Account/TrialBalance', [
            'pageTitle' => 'Trial Balance'
        ]);
    }

    public function profitLoss()
    {
        return Inertia::render('Backend/Account/ProfitLoss', [
            'pageTitle' => 'Profit & Loss'
        ]);
    }

    public function balanceSheet()
    {
        return Inertia::render('Backend/Account/BalanceSheet', [
            'pageTitle' => 'Balance Sheet'
        ]);
    }

    public function cashFlow()
    {
        return Inertia::render('Backend/Account/CashFlow', [
            'pageTitle' => 'Cash Flow'
        ]);
    }
}
