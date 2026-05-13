<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\OpeningBalancePosting;
use App\Services\LedgerService;
use Illuminate\Support\Facades\DB;

class AccountApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:chart-of-accounts');
        $this->middleware('permission:chart-of-accounts-create', ['only' => ['store']]);
        $this->middleware('permission:chart-of-accounts-edit', ['only' => ['update']]);
        $this->middleware('permission:chart-of-accounts-delete', ['only' => ['destroy']]);
        $this->middleware('permission:chart-of-accounts-edit', ['only' => ['saveOpeningBalances', 'postOpeningBalances', 'openingBalanceStatus']]);
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
                'profit' => $acc->balance ? (float) $acc->balance->profit : 0.0,
                'loss' => $acc->balance ? (float) $acc->balance->loss : 0.0,
            ];
        });

        return response()->json(regeneratePagination($formated, $datas->total(), $datas->perPage(), $datas->currentPage()));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|unique:accounts,code',
            'name' => 'required|string',
            'type' => 'required|in:asset,liability,equity,income,expense',
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

    public function openingBalanceStatus()
    {
        $latestPosting = $this->openingPostingHistoryQuery()->first();
        $historyPreview = $this->openingPostingHistoryQuery()->limit(5)->get()
            ->map(fn (OpeningBalancePosting $posting) => $this->transformOpeningPosting($posting))
            ->values();

        $latestOpeningTx = $this->latestOpeningBalanceTransaction();

        if (!$latestOpeningTx) {
            return response()->json([
                'ok' => true,
                'is_posted' => false,
                'locked' => false,
                'can_edit_without_repost' => true,
                'last_posting' => null,
                'history_preview' => $historyPreview,
            ]);
        }

        return response()->json([
            'ok' => true,
            'is_posted' => true,
            'locked' => true,
            'can_edit_without_repost' => false,
            'last_posting' => $latestPosting
                ? $this->transformOpeningPosting($latestPosting)
                : [
                    'transaction_id' => $latestOpeningTx->id,
                    'uuid' => $latestOpeningTx->uuid,
                    'posting_date' => $latestOpeningTx->date,
                    'description' => $latestOpeningTx->description,
                    'journal_entry_id' => $latestOpeningTx->journal_entry_id,
                    'created_at' => optional($latestOpeningTx->created_at)->toDateTimeString(),
                    'is_repost' => false,
                ],
            'history_preview' => $historyPreview,
        ]);
    }

    public function openingBalanceHistory(Request $request)
    {
        $validated = $request->validate([
            'numOfData' => ['nullable', 'integer', 'min:5', 'max:50'],
            'posting_type' => ['nullable', 'in:all,initial,repost'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:newest,oldest'],
            'format' => ['nullable', 'in:json,csv'],
        ]);

        $query = $this->applyOpeningPostingHistoryFilters($this->openingPostingHistoryQuery(), $request);

        if (($validated['format'] ?? 'json') === 'csv') {
            return $this->downloadOpeningPostingHistoryCsv($query);
        }

        $perPage = max(5, min(50, (int) ($validated['numOfData'] ?? 10)));
        $datas = $query->paginate($perPage)->withQueryString();

        $formatted = $datas->getCollection()->map(function (OpeningBalancePosting $posting) {
            return (object) $this->transformOpeningPosting($posting);
        });

        return response()->json(regeneratePagination($formatted, $datas->total(), $datas->perPage(), $datas->currentPage()));
    }

    public function saveOpeningBalances(Request $request)
    {
        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'rows.*.opening_balance' => ['nullable', 'numeric', 'min:0'],
            'rows.*.opening_balance_type' => ['nullable', 'in:debit,credit'],
            'repost' => ['nullable', 'boolean'],
        ]);

        $repost = (bool) ($data['repost'] ?? false);
        $latestOpeningTx = $this->latestOpeningBalanceTransaction();
        if ($latestOpeningTx && !$repost) {
            return response()->json([
                'ok' => false,
                'message' => 'Opening balances are locked after posting. Enable repost mode to edit.',
            ], 422);
        }

        DB::transaction(function () use ($data) {
            foreach ($data['rows'] as $row) {
                $account = Account::find($row['account_id']);
                if (!$account) {
                    continue;
                }

                $openingBalance = round((float) ($row['opening_balance'] ?? 0), 2);
                $openingType = $row['opening_balance_type'] ?? null;

                if (!$openingType) {
                    $openingType = in_array($account->type, ['asset', 'expense'], true) ? 'debit' : 'credit';
                }

                $account->opening_balance = $openingBalance;
                $account->opening_balance_type = $openingBalance > 0 ? $openingType : null;
                $account->save();
            }
        });

        return response()->json([
            'ok' => true,
            'message' => 'Opening balances saved successfully.',
            'repost_mode' => $repost,
        ]);
    }

    public function postOpeningBalances(Request $request, LedgerService $ledgerService)
    {
        $data = $request->validate([
            'posting_date' => ['nullable', 'date'],
            'repost' => ['nullable', 'boolean'],
        ]);

        $postingDate = $data['posting_date'] ?? now()->toDateString();
        $repost = (bool) ($data['repost'] ?? false);

        $existingOpeningTransactions = LedgerTransaction::query()
            ->where('reference_type', 'opening_balance')
            ->get();

        if ($existingOpeningTransactions->isNotEmpty() && !$repost) {
            return response()->json([
                'ok' => false,
                'message' => 'Opening balances are already posted. Use repost option to replace existing opening transaction.',
            ], 422);
        }

        if ($existingOpeningTransactions->isNotEmpty() && $repost) {
            foreach ($existingOpeningTransactions as $existingTransaction) {
                $ledgerService->deleteTransaction($existingTransaction->id);
            }
        }

        $accounts = Account::query()
            ->where('is_active', true)
            ->whereNotNull('opening_balance')
            ->where('opening_balance', '>', 0)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'opening_balance', 'opening_balance_type']);

        if ($accounts->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => 'No opening balances found to post. Save opening balances first.',
            ], 422);
        }

        $lines = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($accounts as $account) {
            $amount = round((float) $account->opening_balance, 2);
            if ($amount <= 0) {
                continue;
            }

            $entryType = $account->opening_balance_type;
            if (!$entryType) {
                $entryType = in_array($account->type, ['asset', 'expense'], true) ? 'debit' : 'credit';
            }

            $lines[] = [
                'account_id' => $account->id,
                'account_code' => $account->code,
                'account_name' => $account->name,
                'entry_type' => $entryType,
                'amount' => $amount,
                'narration' => 'Opening balance',
            ];

            if ($entryType === 'debit') {
                $totalDebit += $amount;
            } else {
                $totalCredit += $amount;
            }
        }

        if (empty($lines)) {
            return response()->json([
                'ok' => false,
                'message' => 'No valid opening balance rows found for posting.',
            ], 422);
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            $equityAccount = Account::query()
                ->whereIn('code', ['OWNER_CAP', 'EQUITY', 'OWNER_EQUITY'])
                ->orderByRaw("CASE WHEN code = 'OWNER_CAP' THEN 0 WHEN code = 'EQUITY' THEN 1 ELSE 2 END")
                ->first();

            if (!$equityAccount) {
                $equityAccount = Account::create([
                    'code' => 'OWNER_CAP',
                    'name' => "Owner's Capital",
                    'type' => 'equity',
                    'description' => 'Auto-created balancing equity account for opening entries',
                    'opening_balance' => 0,
                    'opening_balance_type' => 'credit',
                    'account_group' => 'EQUITY',
                    'is_profit_loss' => false,
                    'is_active' => true,
                ]);
            }

            $difference = round(abs($totalDebit - $totalCredit), 2);

            if ($totalDebit > $totalCredit) {
                $lines[] = [
                    'account_id' => $equityAccount->id,
                    'account_code' => $equityAccount->code,
                    'account_name' => $equityAccount->name,
                    'entry_type' => 'credit',
                    'amount' => $difference,
                    'narration' => 'Opening balance auto balancing',
                ];
                $totalCredit += $difference;
            } else {
                $lines[] = [
                    'account_id' => $equityAccount->id,
                    'account_code' => $equityAccount->code,
                    'account_name' => $equityAccount->name,
                    'entry_type' => 'debit',
                    'amount' => $difference,
                    'narration' => 'Opening balance auto balancing',
                ];
                $totalDebit += $difference;
            }
        }

        $adminId = auth('admin')->id();

        $journalEntry = DB::transaction(function () use ($lines, $totalDebit, $totalCredit, $postingDate, $adminId, $ledgerService, $repost) {
            $journalEntry = JournalEntry::create([
                'entry_date' => $postingDate,
                'reference' => 'OPENING-' . now()->format('Ymd-His'),
                'description' => 'Opening balance posting',
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'posted' => true,
                'status' => 'Posted',
                'created_by' => $adminId,
                'posted_by' => $adminId,
                'posted_at' => now(),
            ]);

            foreach ($lines as $line) {
                $journalEntry->lines()->create([
                    'account_id' => $line['account_id'],
                    'debit' => $line['entry_type'] === 'debit' ? $line['amount'] : 0,
                    'credit' => $line['entry_type'] === 'credit' ? $line['amount'] : 0,
                    'narration' => $line['narration'] ?? null,
                ]);
            }

            $tx = $ledgerService->recordTransaction(
                $lines,
                'Opening balance posting',
                $postingDate,
                'opening_balance',
                $journalEntry->id,
                $adminId
            );

            $tx->journal_entry_id = $journalEntry->id;
            $tx->save();

            OpeningBalancePosting::create([
                'journal_entry_id' => $journalEntry->id,
                'ledger_transaction_id' => $tx->id,
                'posted_by' => $adminId,
                'is_repost' => $repost,
                'posting_date' => $postingDate,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'line_count' => count($lines),
                'snapshot' => collect($lines)->map(function ($line) {
                    return [
                        'account_id' => $line['account_id'] ?? null,
                        'account_code' => $line['account_code'] ?? null,
                        'account_name' => $line['account_name'] ?? null,
                        'entry_type' => $line['entry_type'] ?? null,
                        'amount' => round((float) ($line['amount'] ?? 0), 2),
                    ];
                })->values()->all(),
                'notes' => $repost ? 'Opening balances reposted' : 'Initial opening balance posting',
            ]);

            return $journalEntry;
        });

        return response()->json([
            'ok' => true,
            'message' => 'Opening balances posted successfully.',
            'journal_entry_id' => $journalEntry->id,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'lines' => count($lines),
            'repost_mode' => $repost,
        ]);
    }

    public function update(Request $request, $id)
    {
        $account = Account::findOrFail($id);

        $data = $request->validate([
            'code' => 'required|string|unique:accounts,code,' . $account->id,
            'name' => 'required|string',
            'type' => 'required|in:asset,liability,equity,income,expense',
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

    protected function latestOpeningBalanceTransaction(): ?LedgerTransaction
    {
        return LedgerTransaction::query()
            ->where('reference_type', 'opening_balance')
            ->latest('id')
            ->first();
    }

    protected function openingPostingHistoryQuery()
    {
        return OpeningBalancePosting::query()
            ->with([
                'journalEntry:id,reference,entry_date,status',
                'ledgerTransaction:id,uuid,date',
                'postedByAdmin:id,first_name,last_name,email',
            ])
            ->latest('id');
    }

    protected function applyOpeningPostingHistoryFilters($query, Request $request)
    {
        $postingType = (string) $request->query('posting_type', 'all');
        if ($postingType === 'initial') {
            $query->where('is_repost', false);
        } elseif ($postingType === 'repost') {
            $query->where('is_repost', true);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('posting_date', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('posting_date', '<=', $request->query('to_date'));
        }

        if ($request->filled('q')) {
            $keyword = trim((string) $request->query('q'));
            if ($keyword !== '') {
                $query->where(function ($sq) use ($keyword) {
                    $sq->where('notes', 'like', '%' . $keyword . '%')
                        ->orWhereHas('journalEntry', function ($jq) use ($keyword) {
                            $jq->where('reference', 'like', '%' . $keyword . '%')
                                ->orWhere('description', 'like', '%' . $keyword . '%');
                        })
                        ->orWhereHas('ledgerTransaction', function ($lq) use ($keyword) {
                            $lq->where('uuid', 'like', '%' . $keyword . '%')
                                ->orWhere('description', 'like', '%' . $keyword . '%');
                        })
                        ->orWhereHas('postedByAdmin', function ($aq) use ($keyword) {
                            $aq->where('first_name', 'like', '%' . $keyword . '%')
                                ->orWhere('last_name', 'like', '%' . $keyword . '%')
                                ->orWhere('email', 'like', '%' . $keyword . '%');
                        });
                });
            }
        }

        $sort = (string) $request->query('sort', 'newest');
        if ($sort === 'oldest') {
            $query->reorder('id', 'asc');
        } else {
            $query->reorder('id', 'desc');
        }

        return $query;
    }

    protected function downloadOpeningPostingHistoryCsv($query)
    {
        $fileName = 'opening-balance-history-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'id',
                'created_at',
                'posting_type',
                'posting_date',
                'journal_entry_id',
                'journal_reference',
                'ledger_transaction_id',
                'ledger_uuid',
                'total_debit',
                'total_credit',
                'line_count',
                'posted_by',
                'notes',
                'snapshot_json',
            ]);

            foreach ($query->cursor() as $posting) {
                $row = $this->transformOpeningPosting($posting);

                fputcsv($handle, [
                    $row['id'],
                    $row['created_at'],
                    $row['is_repost'] ? 'repost' : 'initial',
                    $row['posting_date'],
                    $row['journal_entry_id'],
                    $row['journal_reference'],
                    $row['transaction_id'],
                    $row['uuid'],
                    number_format((float) $row['total_debit'], 2, '.', ''),
                    number_format((float) $row['total_credit'], 2, '.', ''),
                    $row['line_count'],
                    $row['posted_by'],
                    $row['notes'],
                    json_encode($row['snapshot'] ?? []),
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function transformOpeningPosting(OpeningBalancePosting $posting): array
    {
        $postedBy = null;
        if ($posting->postedByAdmin) {
            $postedBy = trim(((string) ($posting->postedByAdmin->first_name ?? '')) . ' ' . ((string) ($posting->postedByAdmin->last_name ?? '')));
            if ($postedBy === '') {
                $postedBy = $posting->postedByAdmin->email;
            }
        }

        return [
            'id' => $posting->id,
            'is_repost' => (bool) $posting->is_repost,
            'posting_date' => optional($posting->posting_date)->toDateString(),
            'total_debit' => (float) $posting->total_debit,
            'total_credit' => (float) $posting->total_credit,
            'line_count' => (int) $posting->line_count,
            'notes' => $posting->notes,
            'journal_entry_id' => $posting->journal_entry_id,
            'journal_reference' => $posting->journalEntry?->reference,
            'transaction_id' => $posting->ledger_transaction_id,
            'uuid' => $posting->ledgerTransaction?->uuid,
            'created_at' => optional($posting->created_at)->toDateTimeString(),
            'posted_by' => $postedBy,
            'snapshot' => collect($posting->snapshot ?? [])->map(function ($line) {
                return [
                    'account_id' => isset($line['account_id']) ? (int) $line['account_id'] : null,
                    'account_code' => isset($line['account_code']) ? (string) $line['account_code'] : null,
                    'account_name' => isset($line['account_name']) ? (string) $line['account_name'] : null,
                    'entry_type' => isset($line['entry_type']) ? (string) $line['entry_type'] : null,
                    'amount' => round((float) ($line['amount'] ?? 0), 2),
                ];
            })->values()->all(),
        ];
    }
}
