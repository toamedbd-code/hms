<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\LedgerEntry;

class AccountApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:chart-of-accounts');
        $this->middleware('permission:chart-of-accounts-create', ['only' => ['store']]);
        $this->middleware('permission:chart-of-accounts-edit', ['only' => ['update']]);
        $this->middleware('permission:chart-of-accounts-delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        $query = Account::with(['parent', 'children', 'balance']);

        if ($request->filled('q')) {
            $q = trim((string) $request->q);
            $query->where(function ($sq) use ($q) {
                $sq->where('name', 'like', $q . '%')
                    ->orWhere('code', 'like', $q . '%');
            });
        }

        if ($request->filled('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        $perPage = (int) ($request->numOfData ?? 20);
        $datas = $query->orderBy('code')->paginate($perPage)->withQueryString();

        $formated = $datas->getCollection()->map(function ($acc, $index) {
            return (object) [
                'index' => $index + 1,
                'id' => $acc->id,
                'code' => $acc->code,
                'name' => $acc->name,
                'type' => $acc->type,
                'parent' => $acc->parent ? ['id' => $acc->parent->id, 'name' => $acc->parent->name] : null,
                'children_count' => $acc->children->count(),
                'balance' => $acc->balance ? (float) $acc->balance->balance : 0.0,
            ];
        });

        return response()->json(regeneratePagination($formated, $datas->total(), $datas->perPage(), $datas->currentPage()));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|unique:accounts,code',
            'name' => 'required|string',
            'type' => 'required|in:asset,liability,income,expense',
            'parent_id' => 'nullable|exists:accounts,id',
        ]);

        $account = Account::create($data);

        return response()->json(['ok' => true, 'account' => $account], 201);
    }

    public function show($id)
    {
        $account = Account::with(['parent', 'children', 'balance'])->findOrFail($id);
        return response()->json($account);
    }

    public function update(Request $request, $id)
    {
        $account = Account::findOrFail($id);

        $data = $request->validate([
            'code' => 'required|string|unique:accounts,code,' . $account->id,
            'name' => 'required|string',
            'type' => 'required|in:asset,liability,income,expense',
            'parent_id' => 'nullable|exists:accounts,id',
        ]);

        $account->update($data);

        return response()->json(['ok' => true, 'account' => $account]);
    }

    public function destroy($id)
    {
        $account = Account::findOrFail($id);

        // Prevent deletion if there are ledger entries or child accounts
        $hasEntries = LedgerEntry::where('account_id', $account->id)->exists();
        $hasChildren = $account->children()->exists();

        if ($hasEntries || $hasChildren) {
            return response()->json(['ok' => false, 'message' => 'Account has entries or children and cannot be deleted.'], 422);
        }

        $account->delete();

        return response()->json(['ok' => true]);
    }
}
