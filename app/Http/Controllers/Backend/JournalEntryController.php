<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\JournalEntry;
use App\Models\LedgerTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\LedgerService;

class JournalEntryController extends Controller
{
    public function index(Request $request)
    {
        if ($request->wantsJson()) {
            $entries = JournalEntry::with('lines')->latest()->paginate(20);
            return response()->json($entries);
        }

        return Inertia::render('Backend/Accounting/JournalEntries/Index');
    }

    public function create()
    {
        return Inertia::render('Backend/Accounting/JournalEntries/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'entry_date' => ['required','date'],
            'reference' => ['nullable','string'],
            'description' => ['nullable','string'],
            'lines' => ['required','array','min:1'],
            'lines.*.account_id' => ['nullable','integer','exists:accounts,id'],
            'lines.*.debit' => ['nullable','numeric'],
            'lines.*.credit' => ['nullable','numeric'],
            'lines.*.narration' => ['nullable','string'],
            'status' => ['nullable', Rule::in(['Draft','Posted','Cancelled'])],
        ]);

        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($data['lines'] as $line) {
            $totalDebit += floatval($line['debit'] ?? 0);
            $totalCredit += floatval($line['credit'] ?? 0);
        }

        $isPosted = ($data['status'] ?? 'Draft') === 'Posted';
        if ($isPosted) {
            $postingError = $this->validateLinesForPosting($data['lines'], $totalDebit, $totalCredit);
            if ($postingError) {
                return $this->validationErrorResponse($request, $postingError);
            }
        }

        $adminId = auth('admin')->id() ?? Auth::id();

        try {
            $entry = DB::transaction(function () use ($data, $totalDebit, $totalCredit, $isPosted, $adminId) {
                $entry = JournalEntry::create([
                    'entry_date' => $data['entry_date'],
                    'reference' => $data['reference'] ?? null,
                    'description' => $data['description'] ?? null,
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                    'posted' => $isPosted,
                    'status' => $data['status'] ?? 'Draft',
                    'created_by' => $adminId,
                ]);

                foreach ($data['lines'] as $line) {
                    $entry->lines()->create([
                        'account_id' => $line['account_id'] ?? null,
                        'debit' => $line['debit'] ?? 0,
                        'credit' => $line['credit'] ?? 0,
                        'narration' => $line['narration'] ?? null,
                    ]);
                }

                // If the entry is posted, record ledger transaction
                if ($isPosted) {
                    $ledgerService = app(LedgerService::class);

                    $linesForLedger = [];
                    $entry->load('lines');
                    foreach ($entry->lines as $l) {
                        if ((float) $l->debit > 0) {
                            $linesForLedger[] = ['account_id' => $l->account_id, 'entry_type' => 'debit', 'amount' => (float) $l->debit, 'narration' => $l->narration ?? null];
                        }
                        if ((float) $l->credit > 0) {
                            $linesForLedger[] = ['account_id' => $l->account_id, 'entry_type' => 'credit', 'amount' => (float) $l->credit, 'narration' => $l->narration ?? null];
                        }
                    }

                    // remove any previous ledger transaction for this reference
                    $existing = LedgerTransaction::where('reference_type', 'journal_entry')->where('reference_id', $entry->id)->first();
                    if ($existing) {
                        $ledgerService->deleteTransaction($existing->id);
                    }

                    if (! empty($linesForLedger)) {
                        $ledgerService->recordTransaction($linesForLedger, $entry->description, $entry->entry_date ?? now()->toDateString(), 'journal_entry', $entry->id, $adminId);

                        // mark posted
                        $entry->update([
                            'posted_by' => $adminId,
                            'posted_at' => now(),
                        ]);
                    }
                }

                return $entry;
            });
        } catch (\InvalidArgumentException $e) {
            return $this->validationErrorResponse($request, $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json($entry->load('lines'), 201);
        }

        return redirect()->route('backend.journal-entry.index')->with('successMessage', 'Journal entry created.');
    }

    public function show(Request $request, $id)
    {
        $entry = JournalEntry::with('lines')->findOrFail($id);

        if ($request->wantsJson()) {
            return response()->json($entry);
        }

        return Inertia::render('Backend/Accounting/JournalEntries/Show', ['entry' => $entry]);
    }

    public function edit($id)
    {
        $entry = JournalEntry::with('lines')->findOrFail($id);
        return Inertia::render('Backend/Accounting/JournalEntries/Edit', ['entry' => $entry]);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'entry_date' => ['required','date'],
            'reference' => ['nullable','string'],
            'description' => ['nullable','string'],
            'lines' => ['required','array','min:1'],
            'lines.*.account_id' => ['nullable','integer','exists:accounts,id'],
            'lines.*.debit' => ['nullable','numeric'],
            'lines.*.credit' => ['nullable','numeric'],
            'lines.*.narration' => ['nullable','string'],
            'status' => ['nullable', Rule::in(['Draft','Posted','Cancelled'])],
        ]);

        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($data['lines'] as $line) {
            $totalDebit += floatval($line['debit'] ?? 0);
            $totalCredit += floatval($line['credit'] ?? 0);
        }

        $isPosted = ($data['status'] ?? 'Draft') === 'Posted';
        if ($isPosted) {
            $postingError = $this->validateLinesForPosting($data['lines'], $totalDebit, $totalCredit);
            if ($postingError) {
                return $this->validationErrorResponse($request, $postingError);
            }
        }

        $adminId = auth('admin')->id() ?? Auth::id();

        try {
            $entry = DB::transaction(function () use ($id, $data, $totalDebit, $totalCredit, $isPosted, $adminId) {
                $entry = JournalEntry::findOrFail($id);

                $entry->update([
                    'entry_date' => $data['entry_date'],
                    'reference' => $data['reference'] ?? null,
                    'description' => $data['description'] ?? null,
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                    'posted' => $isPosted,
                    'status' => $data['status'] ?? 'Draft',
                ]);

                // replace lines
                $entry->lines()->delete();
                foreach ($data['lines'] as $line) {
                    $entry->lines()->create([
                        'account_id' => $line['account_id'] ?? null,
                        'debit' => $line['debit'] ?? 0,
                        'credit' => $line['credit'] ?? 0,
                        'narration' => $line['narration'] ?? null,
                    ]);
                }

                // Ensure ledger transaction state matches posted flag
                $ledgerService = app(LedgerService::class);

                // If now not posted, remove any existing ledger transaction and clear posted metadata
                if (! ($entry->posted)) {
                    $existing = LedgerTransaction::where('reference_type', 'journal_entry')->where('reference_id', $entry->id)->first();
                    if ($existing) {
                        $ledgerService->deleteTransaction($existing->id);
                    }

                    $entry->update([
                        'posted_by' => null,
                        'posted_at' => null,
                    ]);
                }

                // If posted, (re)post to ledger
                if ($isPosted) {

                    $linesForLedger = [];
                    $entry->load('lines');
                    foreach ($entry->lines as $l) {
                        if ((float) $l->debit > 0) {
                            $linesForLedger[] = ['account_id' => $l->account_id, 'entry_type' => 'debit', 'amount' => (float) $l->debit];
                        }
                        if ((float) $l->credit > 0) {
                            $linesForLedger[] = ['account_id' => $l->account_id, 'entry_type' => 'credit', 'amount' => (float) $l->credit];
                        }
                    }

                    $existing = LedgerTransaction::where('reference_type', 'journal_entry')->where('reference_id', $entry->id)->first();
                    if ($existing) {
                        $ledgerService->deleteTransaction($existing->id);
                    }

                    if (! empty($linesForLedger)) {
                        $ledgerService->recordTransaction($linesForLedger, $entry->description, $entry->entry_date ?? now()->toDateString(), 'journal_entry', $entry->id, $adminId);

                        // mark posted
                        $entry->update([
                            'posted_by' => $adminId,
                            'posted_at' => now(),
                        ]);
                    }
                }

                return $entry;
            });
        } catch (\InvalidArgumentException $e) {
            return $this->validationErrorResponse($request, $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json($entry->load('lines'));
        }

        return redirect()->route('backend.journal-entry.index')->with('successMessage', 'Journal entry updated.');
    }

    public function destroy(Request $request, $id)
    {
        $entry = JournalEntry::findOrFail($id);
        // remove related ledger transaction if exists
        $existing = LedgerTransaction::where('reference_type', 'journal_entry')->where('reference_id', $entry->id)->first();
        if ($existing) {
            app(\App\Services\LedgerService::class)->deleteTransaction($existing->id);
        }

        $entry->delete();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('backend.journal-entry.index')->with('successMessage', 'Journal entry deleted.');
    }

    private function validateLinesForPosting(array $lines, float $totalDebit, float $totalCredit): ?string
    {
        if (function_exists('bccomp')) {
            $totalsMatch = bccomp((string) $totalDebit, (string) $totalCredit, 2) === 0;
        } else {
            $totalsMatch = abs(round($totalDebit, 2) - round($totalCredit, 2)) < 0.00001;
        }

        if (! $totalsMatch) {
            return 'Debits and credits must be equal to post a journal entry.';
        }

        $hasDebit = false;
        $hasCredit = false;

        foreach ($lines as $line) {
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            if ($debit > 0) {
                $hasDebit = true;
                if (empty($line['account_id'])) {
                    return 'Each debit line must have an account selected.';
                }
            }

            if ($credit > 0) {
                $hasCredit = true;
                if (empty($line['account_id'])) {
                    return 'Each credit line must have an account selected.';
                }
            }
        }

        if (! $hasDebit || ! $hasCredit) {
            return 'Posted journal entry must include at least one debit line and one credit line.';
        }

        return null;
    }

    private function validationErrorResponse(Request $request, string $message)
    {
        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'errors' => ['lines' => [$message]],
            ], 422);
        }

        return back()->withErrors(['lines' => $message])->withInput();
    }
}
