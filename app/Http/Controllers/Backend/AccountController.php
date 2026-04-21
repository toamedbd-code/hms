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
            'accounts' => fn() => Account::with('children')->whereNull('parent_id')->orderBy('code')->get(),
        ]);
    }

    public function balances()
    {
        return Inertia::render('Backend/Account/Balances', [
            'pageTitle' => 'Account Balances'
        ]);
    }
}
