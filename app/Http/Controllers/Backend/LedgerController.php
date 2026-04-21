<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class LedgerController extends Controller
{
    public function index()
    {
        return Inertia::render('Backend/Account/Ledger', [
            'pageTitle' => 'Ledger'
        ]);
    }
}
