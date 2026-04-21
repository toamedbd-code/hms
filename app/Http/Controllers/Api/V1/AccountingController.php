<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\LedgerTransaction;
use App\Services\AccountingService;

class AccountingController extends Controller
{
    protected AccountingService $service;

    public function __construct(AccountingService $service)
    {
        $this->service = $service;
    }

    public function accounts(Request $request)
    {
        $accounts = Account::with('children')->orderBy('code')->get();
        return response()->json($accounts);
    }

    public function storeAccount(Request $request)
    {
        $data = $request->validate([
            'code' => 'nullable|string|max:50|unique:accounts,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,income,expense',
            'parent_id' => 'nullable|exists:accounts,id',
            'description' => 'nullable|string',
        ]);

        $account = Account::create($data);
        return response()->json($account, 201);
    }

    public function journals(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);
        $list = LedgerTransaction::with('entries.account')->orderBy('date', 'desc')->paginate($perPage);
        return response()->json($list);
    }

    public function createJournal(Request $request)
    {
        $data = $request->validate([
            'date' => 'nullable|date',
            'description' => 'nullable|string',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
            'created_by' => 'nullable|integer',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id',
            'lines.*.entry_type' => 'required|in:debit,credit',
            'lines.*.amount' => 'required|numeric|min:0.01',
        ]);

        try {
            $tx = $this->service->createJournal($data);
            return response()->json($tx, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function trialBalance()
    {
        $report = $this->service->trialBalance();
        return response()->json($report);
    }
}
