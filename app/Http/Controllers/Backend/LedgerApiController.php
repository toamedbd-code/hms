<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LedgerTransaction;
use App\Services\AccountingService;

class LedgerApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:ledger');
    }

    public function index(Request $request)
    {
        $query = LedgerTransaction::with(['entries.account'])->orderByDesc('date');

        if ($request->filled('account_id')) {
            $query->whereHas('entries', function ($q) use ($request) {
                $q->where('account_id', $request->account_id);
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->to);
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->q);
            $query->where(function ($sq) use ($q) {
                $sq->where('description', 'like', '%' . $q . '%')
                    ->orWhere('uuid', $q)
                    ->orWhere('reference_type', 'like', '%' . $q . '%');
            });
        }

        $perPage = (int) ($request->numOfData ?? 20);
        $datas = $query->paginate($perPage)->withQueryString();

        $formated = $datas->getCollection()->map(function ($tx) {
            return (object) [
                'id' => $tx->id,
                'uuid' => $tx->uuid,
                'date' => $tx->date,
                'description' => $tx->description,
                'reference_type' => $tx->reference_type,
                'reference_id' => $tx->reference_id,
                'entries' => $tx->entries->map(function ($e) {
                    return [
                        'id' => $e->id,
                        'account_id' => $e->account_id,
                        'account' => $e->account ? ['id' => $e->account->id, 'code' => $e->account->code, 'name' => $e->account->name] : null,
                        'amount' => (float) $e->amount,
                        'entry_type' => $e->entry_type,
                        'narration' => $e->narration ?? null,
                    ];
                })->toArray(),
            ];
        });

        return response()->json(regeneratePagination($formated, $datas->total(), $datas->perPage(), $datas->currentPage()));
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = LedgerTransaction::with(['entries.account'])->orderByDesc('date');

        if (!empty($validated['account_id'])) {
            $accountId = (int) $validated['account_id'];
            $query->whereHas('entries', fn ($q) => $q->where('account_id', $accountId));
        }

        if (!empty($validated['from'])) {
            $query->whereDate('date', '>=', $validated['from']);
        }

        if (!empty($validated['to'])) {
            $query->whereDate('date', '<=', $validated['to']);
        }

        if (!empty($validated['q'])) {
            $keyword = trim((string) $validated['q']);
            $query->where(function ($sq) use ($keyword) {
                $sq->where('description', 'like', '%' . $keyword . '%')
                    ->orWhere('uuid', $keyword)
                    ->orWhere('reference_type', 'like', '%' . $keyword . '%');
            });
        }

        $transactions = $query->limit(5000)->get();
        $filename = 'ledger-export-' . now()->format('Ymd-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($transactions) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Transaction UUID', 'Description', 'Reference Type', 'Reference ID', 'Account Code', 'Account Name', 'Entry Type', 'Amount', 'Narration']);

            foreach ($transactions as $tx) {
                foreach ($tx->entries as $entry) {
                    fputcsv($out, [
                        $tx->date,
                        $tx->uuid,
                        $tx->description,
                        $tx->reference_type,
                        $tx->reference_id,
                        $entry->account?->code,
                        $entry->account?->name,
                        $entry->entry_type,
                        number_format((float) $entry->amount, 2, '.', ''),
                        $entry->narration,
                    ]);
                }
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function show($id)
    {
        $tx = LedgerTransaction::with(['entries.account'])->findOrFail($id);
        return response()->json($tx);
    }

    public function trialBalance(Request $request, AccountingService $accountingService)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:all,asset,liability,equity,income,expense'],
            'format' => ['nullable', 'in:json,csv'],
        ]);

        $report = $accountingService->trialBalance();
        $rows = collect($report['rows'] ?? []);

        if (!empty($validated['q'])) {
            $q = mb_strtolower(trim((string) $validated['q']));
            $rows = $rows->filter(function ($row) use ($q) {
                $code = mb_strtolower((string) ($row['code'] ?? ''));
                $name = mb_strtolower((string) ($row['name'] ?? ''));
                return str_contains($code, $q) || str_contains($name, $q);
            })->values();
        }

        if (($validated['type'] ?? 'all') !== 'all') {
            $type = $validated['type'];
            $rows = $rows->filter(fn ($row) => ($row['type'] ?? null) === $type)->values();
        }

        $totals = [
            'debit' => (float) $rows->sum('debit'),
            'credit' => (float) $rows->sum('credit'),
        ];

        if (($validated['format'] ?? 'json') === 'csv') {
            $filename = 'trial-balance-' . now()->format('Ymd-His') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function () use ($rows, $totals) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Code', 'Name', 'Type', 'Debit', 'Credit', 'Net']);
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row['code'] ?? '',
                        $row['name'] ?? '',
                        $row['type'] ?? '',
                        number_format((float) ($row['debit'] ?? 0), 2, '.', ''),
                        number_format((float) ($row['credit'] ?? 0), 2, '.', ''),
                        number_format((float) (($row['debit'] ?? 0) - ($row['credit'] ?? 0)), 2, '.', ''),
                    ]);
                }
                fputcsv($out, []);
                fputcsv($out, [
                    'TOTAL',
                    '',
                    '',
                    number_format((float) $totals['debit'], 2, '.', ''),
                    number_format((float) $totals['credit'], 2, '.', ''),
                    number_format((float) ($totals['debit'] - $totals['credit']), 2, '.', ''),
                ]);
                fclose($out);
            };

            return response()->stream($callback, 200, $headers);
        }

        return response()->json([
            'ok' => true,
            'rows' => $rows->values(),
            'totals' => [
                'debit' => round((float) $totals['debit'], 2),
                'credit' => round((float) $totals['credit'], 2),
                'difference' => round((float) ($totals['debit'] - $totals['credit']), 2),
            ],
        ]);
    }

    public function profitLoss(Request $request, AccountingService $accountingService)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'format' => ['nullable', 'in:json,csv'],
        ]);

        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? now()->toDateString();
        $report = $accountingService->profitLoss($from, $to);

        if (($validated['format'] ?? 'json') === 'csv') {
            $filename = 'profit-loss-' . now()->format('Ymd-His') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function () use ($report, $from, $to) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Profit & Loss']);
                fputcsv($out, ['From', $from ?: 'Beginning']);
                fputcsv($out, ['To', $to ?: 'Today']);
                fputcsv($out, []);

                fputcsv($out, ['Income']);
                fputcsv($out, ['Code', 'Name', 'Amount']);
                foreach (($report['income_rows'] ?? []) as $row) {
                    fputcsv($out, [$row['code'] ?? '', $row['name'] ?? '', number_format((float) ($row['amount'] ?? 0), 2, '.', '')]);
                }

                fputcsv($out, []);
                fputcsv($out, ['Expenses']);
                fputcsv($out, ['Code', 'Name', 'Amount']);
                foreach (($report['expense_rows'] ?? []) as $row) {
                    fputcsv($out, [$row['code'] ?? '', $row['name'] ?? '', number_format((float) ($row['amount'] ?? 0), 2, '.', '')]);
                }

                fputcsv($out, []);
                fputcsv($out, ['Total Income', number_format((float) ($report['totals']['income'] ?? 0), 2, '.', '')]);
                fputcsv($out, ['Total Expense', number_format((float) ($report['totals']['expense'] ?? 0), 2, '.', '')]);
                fputcsv($out, ['Net Profit', number_format((float) ($report['totals']['net_profit'] ?? 0), 2, '.', '')]);
                fclose($out);
            };

            return response()->stream($callback, 200, $headers);
        }

        return response()->json([
            'ok' => true,
            ...$report,
        ]);
    }

    public function balanceSheet(Request $request, AccountingService $accountingService)
    {
        $validated = $request->validate([
            'as_of' => ['nullable', 'date'],
            'format' => ['nullable', 'in:json,csv'],
        ]);

        $asOf = $validated['as_of'] ?? now()->toDateString();
        $report = $accountingService->balanceSheet($asOf);

        if (($validated['format'] ?? 'json') === 'csv') {
            $filename = 'balance-sheet-' . now()->format('Ymd-His') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function () use ($report, $asOf) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Balance Sheet']);
                fputcsv($out, ['As Of', $asOf]);
                fputcsv($out, []);

                fputcsv($out, ['Assets']);
                fputcsv($out, ['Code', 'Name', 'Amount']);
                foreach (($report['assets'] ?? []) as $row) {
                    fputcsv($out, [$row['code'] ?? '', $row['name'] ?? '', number_format((float) ($row['amount'] ?? 0), 2, '.', '')]);
                }
                fputcsv($out, ['Total Assets', '', number_format((float) ($report['totals']['assets'] ?? 0), 2, '.', '')]);

                fputcsv($out, []);
                fputcsv($out, ['Liabilities']);
                fputcsv($out, ['Code', 'Name', 'Amount']);
                foreach (($report['liabilities'] ?? []) as $row) {
                    fputcsv($out, [$row['code'] ?? '', $row['name'] ?? '', number_format((float) ($row['amount'] ?? 0), 2, '.', '')]);
                }
                fputcsv($out, ['Total Liabilities', '', number_format((float) ($report['totals']['liabilities'] ?? 0), 2, '.', '')]);

                fputcsv($out, []);
                fputcsv($out, ['Equity']);
                fputcsv($out, ['Code', 'Name', 'Amount']);
                foreach (($report['equity'] ?? []) as $row) {
                    fputcsv($out, [$row['code'] ?? '', $row['name'] ?? '', number_format((float) ($row['amount'] ?? 0), 2, '.', '')]);
                }
                fputcsv($out, ['Total Equity', '', number_format((float) ($report['totals']['equity'] ?? 0), 2, '.', '')]);

                fputcsv($out, []);
                fputcsv($out, ['Total Liabilities + Equity', '', number_format((float) ($report['totals']['liabilities_and_equity'] ?? 0), 2, '.', '')]);
                fputcsv($out, ['Difference (Assets - L+E)', '', number_format((float) ($report['totals']['difference'] ?? 0), 2, '.', '')]);
                fclose($out);
            };

            return response()->stream($callback, 200, $headers);
        }

        return response()->json([
            'ok' => true,
            ...$report,
        ]);
    }

    public function cashFlow(Request $request, AccountingService $accountingService)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'format' => ['nullable', 'in:json,csv'],
        ]);

        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? now()->toDateString();
        $report = $accountingService->cashFlow($from, $to);

        if (($validated['format'] ?? 'json') === 'csv') {
            $filename = 'cash-flow-' . now()->format('Ymd-His') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function () use ($report, $from, $to) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Cash Flow']);
                fputcsv($out, ['From', $from ?: 'Beginning']);
                fputcsv($out, ['To', $to ?: 'Today']);
                fputcsv($out, []);
                fputcsv($out, ['Date', 'UUID', 'Description', 'Reference Type', 'Inflow', 'Outflow', 'Net']);

                foreach (($report['rows'] ?? []) as $row) {
                    fputcsv($out, [
                        $row['date'] ?? '',
                        $row['uuid'] ?? '',
                        $row['description'] ?? '',
                        $row['reference_type'] ?? '',
                        number_format((float) ($row['inflow'] ?? 0), 2, '.', ''),
                        number_format((float) ($row['outflow'] ?? 0), 2, '.', ''),
                        number_format((float) ($row['net'] ?? 0), 2, '.', ''),
                    ]);
                }

                fputcsv($out, []);
                fputcsv($out, [
                    'TOTAL',
                    '',
                    '',
                    '',
                    number_format((float) ($report['totals']['inflow'] ?? 0), 2, '.', ''),
                    number_format((float) ($report['totals']['outflow'] ?? 0), 2, '.', ''),
                    number_format((float) ($report['totals']['net'] ?? 0), 2, '.', ''),
                ]);
                fclose($out);
            };

            return response()->stream($callback, 200, $headers);
        }

        return response()->json([
            'ok' => true,
            ...$report,
        ]);
    }

    public function financialSummary(Request $request, AccountingService $accountingService)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'as_of' => ['nullable', 'date'],
        ]);

        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? now()->toDateString();
        $asOf = $validated['as_of'] ?? $to;

        $report = $accountingService->financialSummary($from, $to, $asOf);

        return response()->json([
            'ok' => true,
            ...$report,
        ]);
    }
}
