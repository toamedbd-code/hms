<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class AccountAuditController extends Controller
{
    public function index()
    {
        return Inertia::render('Backend/Account/Audit', [
            'pageTitle' => 'Account Audit Log'
        ]);
    }
}
