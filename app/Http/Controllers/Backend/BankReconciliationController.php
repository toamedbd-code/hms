<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountBalance;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationItem;
use App\Models\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BankReconciliationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:bank-reconciliation');
    }

    public function index(Request $request)
    {
        $bankAccounts = Account::query()
            ->where('code', 'BANK')
            ->orWhere('type', 'asset')
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $selectedAccountId = (int) $request->get('account_id', $bankAccounts->firstWhere('code', 'BANK')?->id ?? 0);

        return Inertia::render('Backend/Account/BankReconciliation', [
            'bankAccounts' => $bankAccounts,
            'selectedAccountId' => $selectedAccountId,
            'history' => fn () => BankReconciliation::query()
                ->with('account:id,code,name')
                ->when($selectedAccountId > 0, fn ($query) => $query->where('account_id', $selectedAccountId))
                ->latest('statement_date')
                ->limit(20)
                ->get(),
        ]);
    }

    public function entries(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|integer|exists:accounts,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $accountId = (int) $validated['account_id'];
        $bookBalance = (float) (AccountBalance::where('account_id', $accountId)->value('balance') ?? 0);

        $query = LedgerEntry::query()
            ->with(['transaction:id,date,description,reference_type,reference_id'])
            ->where('account_id', $accountId)
            ->orderByDesc('id');

        if (!empty($validated['from'])) {
            $query->whereHas('transaction', fn ($tx) => $tx->whereDate('date', '>=', $validated['from']));
        }

        if (!empty($validated['to'])) {
            $query->whereHas('transaction', fn ($tx) => $tx->whereDate('date', '<=', $validated['to']));
        }

        $entries = $query->limit(200)->get()->map(function (LedgerEntry $entry) {
            return [
                'id' => $entry->id,
                'date' => optional($entry->transaction)->date,
                'description' => optional($entry->transaction)->description,
                'reference_type' => optional($entry->transaction)->reference_type,
                'reference_id' => optional($entry->transaction)->reference_id,
                'entry_type' => $entry->entry_type,
                'amount' => (float) $entry->amount,
                'signed_amount' => $entry->entry_type === 'debit' ? (float) $entry->amount : -1 * (float) $entry->amount,
            ];
        });

        return response()->json([
            'book_balance' => $bookBalance,
            'entries' => $entries,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|integer|exists:accounts,id',
            'statement_date' => 'required|date',
            'statement_balance' => 'required|numeric',
            'notes' => 'nullable|string|max:1000',
            'cleared_entry_ids' => 'nullable|array',
            'cleared_entry_ids.*' => 'integer|exists:ledger_entries,id',
        ]);

        $accountId = (int) $validated['account_id'];
        $clearedIds = collect($validated['cleared_entry_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $bookBalance = (float) (AccountBalance::where('account_id', $accountId)->value('balance') ?? 0);

        $clearedEntries = LedgerEntry::query()
            ->where('account_id', $accountId)
            ->whereIn('id', $clearedIds)
            ->get();

        $clearedBalance = round($clearedEntries->sum(function (LedgerEntry $entry) {
            return $entry->entry_type === 'debit'
                ? (float) $entry->amount
                : -1 * (float) $entry->amount;
        }), 2);

        $statementBalance = round((float) $validated['statement_balance'], 2);
        $difference = round($statementBalance - $clearedBalance, 2);

        $reconciliation = DB::transaction(function () use ($validated, $accountId, $bookBalance, $clearedBalance, $statementBalance, $difference, $clearedEntries) {
            $record = BankReconciliation::create([
                'account_id' => $accountId,
                'statement_date' => $validated['statement_date'],
                'statement_balance' => $statementBalance,
                'book_balance' => $bookBalance,
                'cleared_balance' => $clearedBalance,
                'difference' => $difference,
                'notes' => $validated['notes'] ?? null,
                'status' => 'completed',
                'created_by' => auth('admin')->id(),
                'completed_at' => now(),
            ]);

            foreach ($clearedEntries as $entry) {
                BankReconciliationItem::create([
                    'bank_reconciliation_id' => $record->id,
                    'ledger_entry_id' => $entry->id,
                    'amount' => (float) $entry->amount,
                    'entry_type' => $entry->entry_type,
                    'is_cleared' => true,
                ]);
            }

            return $record;
        });

        return redirect()
            ->route('backend.accounts.bank-reconciliation.index', ['account_id' => $accountId])
            ->with('successMessage', 'Bank reconciliation saved. Difference: ' . number_format($difference, 2));
    }
}
