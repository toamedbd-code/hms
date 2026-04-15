<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        /* Final PDF layout tweaks */
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #000; }
        @page { margin: 12mm; }
        * { -webkit-print-color-adjust: exact; -webkit-font-smoothing: antialiased; }
        .header { text-align: center; margin-bottom: 12px; }
        /* Fixed table layout to keep columns aligned */
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; table-layout: fixed; }
        th, td { border: 1px solid #000; padding: 6px; vertical-align: middle; overflow: hidden; text-overflow: ellipsis; }
        thead th { background: #f5f5f5; }
        thead { display: table-header-group; }
        tfoot { display: table-row-group; }
        .center { text-align: center; }
        .amount { text-align: right; white-space: nowrap; }
        .no-wrap { white-space: nowrap; }
        .section-title { font-weight: bold; margin-top: 16px; margin-bottom: 6px; }
        .summary-table { width: 48%; margin-left: auto; margin-top: 8px; }
        /* Avoid breaking rows across pages in PDF renderers */
        tr { page-break-inside: avoid; break-inside: avoid; }
        tbody { display: table-row-group; }
        /* Slightly smaller font for wide tables to prevent overflow */
        .module-details-table th, .module-details-table td, .combined-totals th, .combined-totals td { font-size: 11px; }
        /* Currency styling: make the currency symbol slightly larger */
        .tk { white-space: nowrap; }
        .tk .tk-symbol { font-size: 14px; font-weight: 600; margin-right: 4px; }
        .tk .tk-value { font-size: 12px; }
        /* Keep details/wrapping only for the details column while preventing numeric wrap */
        .module-details-table td.details { white-space: normal; }
        .total-row { background: #f0f0f0; font-weight: bold; }
        /* Column width hints for billing/module tables (adjust percentages to balance columns) */
        /* Billing/combined table columns: Bill No, Billing Date, Total, Discount, Extra, Net, Paid, Due, Due Collected */
        .module-details-table th:nth-child(1), .module-details-table td:nth-child(1) { width: 16%; }
        .module-details-table th:nth-child(2), .module-details-table td:nth-child(2) { width: 9%; }
        .module-details-table th:nth-child(3), .module-details-table td:nth-child(3) { width: 11%; }
        .module-details-table th:nth-child(4), .module-details-table td:nth-child(4) { width: 8%; }
        .module-details-table th:nth-child(5), .module-details-table td:nth-child(5) { width: 8%; }
        .module-details-table th:nth-child(6), .module-details-table td:nth-child(6) { width: 11%; }
        .module-details-table th:nth-child(7), .module-details-table td:nth-child(7) { width: 11%; }
        .module-details-table th:nth-child(8), .module-details-table td:nth-child(8) { width: 11%; }
        .module-details-table th:nth-child(9), .module-details-table td:nth-child(9) { width: 11%; }
        .module-details-table .bill-no-cell { white-space: nowrap; }
        /* Ensure combined totals use same widths */
        .combined-totals th:nth-child(1), .combined-totals td:nth-child(1) { width: 12%; }
        .combined-totals th:nth-child(2), .combined-totals td:nth-child(2) { width: 10%; }
        .combined-totals th:nth-child(3), .combined-totals td:nth-child(3) { width: 12%; }
        .combined-totals th:nth-child(4), .combined-totals td:nth-child(4) { width: 8%; }
        .combined-totals th:nth-child(5), .combined-totals td:nth-child(5) { width: 8%; }
        .combined-totals th:nth-child(6), .combined-totals td:nth-child(6) { width: 12%; }
        .combined-totals th:nth-child(7), .combined-totals td:nth-child(7) { width: 12%; }
        .combined-totals th:nth-child(8), .combined-totals td:nth-child(8) { width: 12%; }
        </style>
    </head>
    <body>
    @php
        $fmtTk = function ($value) {
            $symbol = 'TK.';
            $val = number_format((float) $value, 2);
            return '<span class="tk"><span class="tk-symbol">'. $symbol .'</span><span class="tk-value">'. $val .'</span></span>';
        };
    @endphp

    @php
    // If billing table is requested but no $tableRows available, build from Billing model
    if ((($selectedModuleKey ?? '') === 'billing')) {
        $tableRows = collect($tableRows ?? []);
        if ($tableRows->isEmpty()) {
            $built = \App\Models\Billing::withTrashed()
                ->orderByDesc('created_at')
                ->get(['bill_number', 'invoice_number', 'total', 'discount', 'payable_amount', 'paid_amt', 'due_amount', 'payment_status', 'created_at'])
                ->map(function ($billing) {
                    return [
                        'bill_no' => $billing->bill_number ?? $billing->invoice_number ?? 'N/A',
                        'billing_date' => optional($billing->created_at)->format('d-M-Y') ?? now()->format('d-M-Y'),
                        'total_amount' => (float) ($billing->total ?? 0),
                        'discount_amount' => (float) ($billing->discount ?? 0),
                        'extra_discount' => (float) ($billing->extra_flat_discount ?? 0),
                        'net_amount' => (float) ($billing->payable_amount ?? $billing->total ?? 0),
                        'paid_amount' => (float) ($billing->paid_amt ?? 0),
                        'due_amount' => (float) ($billing->due_amount ?? 0),
                        'due_collected' => 0,
                    ];
                })->values();

            $tableRows = $built;

            $moduleTotals = [
                'total_amount' => (float) $tableRows->sum('total_amount'),
                'discount_amount' => (float) $tableRows->sum('discount_amount'),
                'extra_discount' => (float) $tableRows->sum('extra_discount'),
                'net_amount' => (float) $tableRows->sum('net_amount'),
                'paid_amount' => (float) $tableRows->sum('paid_amount'),
                'due_amount' => (float) $tableRows->sum('due_amount'),
                'due_collected' => (float) $tableRows->sum('due_collected'),
                'total_expense' => $totals['total_expense'] ?? 0,
                // Ensure final income counts the due actually collected on these rows first,
                // fallback to controller-provided due_collection only if rows have none.
                'final_income' => ((float) $tableRows->sum('paid_amount') + (float) $tableRows->sum('due_collected') - ($totals['total_expense'] ?? 0)),
                'actual_due' => (float) $tableRows->sum('due_amount'),
            ];
        }
    }
    @endphp

    <div class="header">
        <div class="hospital-name">{{ optional($websetting)->company_name ?? config('app.name', 'Hospital') }}</div>
        <div class="hospital-address">{{ optional($websetting)->address ?? optional($websetting)->report_title ?? 'N/A' }}</div>
        <div class="report-title">{{ $title }}</div>
        <div class="date-range">{{ $dateRange }}</div>
        <div class="print-time">{{ now()->format('d-M-Y H:i A') }}</div>
    </div>

    @php
    $reportRows = $reportRows ?? [];
    $fallbackBillingRows = $fallbackBillingRows ?? [];
    $selectedModuleKey = strtolower((string)($selectedModule ?? 'all_module'));
    $opdRows = $opdRows ?? [];
    $opdTotals = $opdTotals ?? [];

    if ($selectedModuleKey === 'opd' && empty($opdRows)) {
        $opdPatients = \App\Models\OpdPatient::query()
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhere('status', '!=', 'Deleted');
            })
            ->get(['appointment_date', 'created_at', 'amount', 'discount', 'paid_amount']);

        if ($opdPatients->isNotEmpty()) {
            $grouped = $opdPatients->groupBy(function ($patient) {
                return \Carbon\Carbon::parse($patient->appointment_date ?? $patient->created_at)->format('Y-m-d');
            })->map(function ($dayPatients, $date) {
                $totalAmount = $dayPatients->sum('amount');
                $totalDiscount = $dayPatients->sum('discount');
                $totalNetAmount = $totalAmount - $totalDiscount;
                $totalPaidAmount = max(0, $dayPatients->sum('paid_amount'));
                $dueCollected = 0;
                $totalDueAmount = max(0, $totalNetAmount - $totalPaidAmount - $dueCollected);

                return [
                    'date' => \Carbon\Carbon::parse($date)->format('d-M-Y'),
                    'qty' => $dayPatients->count(),
                    'amount' => $totalAmount,
                    'discount' => $totalDiscount,
                    'net_amount' => $totalNetAmount,
                    'paid_amount' => $totalPaidAmount,
                    'due_amount' => $totalDueAmount,
                    'due_collection' => $dueCollected,
                ];
            });

            $opdRows = $grouped->values()->all();
            $opdTotals = [
                'qty' => $grouped->sum('qty'),
                'amount' => $grouped->sum('amount'),
                'discount' => $grouped->sum('discount'),
                'net_amount' => $grouped->sum('net_amount'),
                'paid_amount' => $grouped->sum('paid_amount'),
                'due_amount' => $grouped->sum('due_amount'),
                'due_collection' => $grouped->sum('due_collection'),
                'actual_due' => $grouped->sum('due_amount'),
            ];

            $totals['net_amount'] = $opdTotals['net_amount'];
            $totals['paid_amount'] = $opdTotals['paid_amount'];
            $totals['due_amount'] = $opdTotals['due_amount'];
            $totals['actual_due'] = $opdTotals['actual_due'];
            $totals['due_collection'] = $opdTotals['due_collection'];
            $totals['total_expense'] = $totals['total_expense'] ?? 0;
            $totals['final_income'] = (($opdTotals['paid_amount'] ?? 0) + ($opdTotals['due_collection'] ?? 0)) - ($totals['total_expense'] ?? 0);
        }
    }

    if (empty($reportRows) && $selectedModuleKey === 'billing') {
        if (!empty($fallbackBillingRows)) {
            $reportRows = $fallbackBillingRows;
        } else {
            $reportRows = \App\Models\Billing::withTrashed()
                ->orderByDesc('created_at')
                ->get(['bill_number', 'invoice_number', 'payable_amount', 'total', 'payment_status', 'created_at'])
                ->map(function ($billing) {
                    return [
                        'date' => optional($billing->created_at)->format('Y-m-d') ?? now()->format('Y-m-d'),
                        'module' => 'billing',
                        'records' => 1,
                        'revenue' => (float) ($billing->payable_amount ?? $billing->total ?? 0),
                        'status' => strtolower((string) ($billing->payment_status ?? 'pending')),
                        'bill_no' => $billing->bill_number ?? $billing->invoice_number ?? 'N/A',
                    ];
                })
                ->values()
                ->all();
        }
    }
    @endphp

    @if ($selectedModuleKey === 'opd' && !empty($opdRows))
    <table class="module-details-table">
        <colgroup>
            <col style="width:12%">
            <col style="width:10%">
            <col style="width:12%">
            <col style="width:8%">
            <col style="width:8%">
            <col style="width:12%">
            <col style="width:12%">
            <col style="width:12%">
            <col style="width:12%">
        </colgroup>
        <thead>
            <tr>
                <th>Date</th>
                <th>Records</th>
                <th>Total Amount (TK.)</th>
                <th>Discount Amt (TK.)</th>
                <th>Net Amount (TK.)</th>
                <th>Paid Amount (TK.)</th>
                <th>Due Amount (TK.)</th>
                <th>Due Collected (TK.)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($opdRows as $row)
            <tr>
                <td class="center">{{ $row['date'] ?? 'N/A' }}</td>
                <td class="amount">{{ $row['qty'] ?? 0 }}</td>
                <td class="amount">{!! $fmtTk($row['amount'] ?? 0) !!}</td>
                <td class="amount">{!! $fmtTk($row['discount'] ?? 0) !!}</td>
                <td class="amount">{!! $fmtTk($row['net_amount'] ?? 0) !!}</td>
                <td class="amount">{!! $fmtTk($row['paid_amount'] ?? 0) !!}</td>
                <td class="amount">{!! $fmtTk($row['due_amount'] ?? 0) !!}</td>
                <td class="amount">{!! $fmtTk($row['due_collection'] ?? 0) !!}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td><strong>Total:</strong></td>
                <td class="amount"><strong>{{ number_format((float)($opdTotals['qty'] ?? 0), 0) }}</strong></td>
                <td class="amount"><strong>{!! $fmtTk($opdTotals['amount'] ?? 0) !!}</strong></td>
                <td class="amount"><strong>{!! $fmtTk($opdTotals['discount'] ?? 0) !!}</strong></td>
                <td class="amount"><strong>{!! $fmtTk($opdTotals['net_amount'] ?? 0) !!}</strong></td>
                <td class="amount"><strong>{!! $fmtTk($opdTotals['paid_amount'] ?? 0) !!}</strong></td>
                <td class="amount"><strong>{!! $fmtTk($opdTotals['due_amount'] ?? 0) !!}</strong></td>
                <td class="amount"><strong>{!! $fmtTk($opdTotals['due_collection'] ?? 0) !!}</strong></td>
            </tr>
        </tfoot>
    </table>

    <table class="summary-table" style="margin-top: 20px;">
        <tr>
            <td class="summary-label">Total Net</td>
            <td class="summary-value">{!! $fmtTk($totals['net_amount'] ?? 0) !!}</td>
        </tr>
                <tr>
            <td class="summary-label">Total Paid</td>
            <td class="summary-value">{!! $fmtTk(($totals['paid_amount'] ?? 0) + ($totals['due_collected'] ?? $totals['due_collection'] ?? 0)) !!}</td>
        </tr>
        <tr>
            <td class="summary-label">Total Due</td>
            <td class="summary-value">{!! $fmtTk($totals['due_amount'] ?? 0) !!}</td>
        </tr>
        <tr>
            <td class="summary-label">Total Actual Due</td>
            <td class="summary-value">{!! $fmtTk($totals['actual_due'] ?? 0) !!}</td>
        </tr>
                <tr>
            <td class="summary-label">Total Due Collected</td>
            <td class="summary-value">{!! $fmtTk($totals['due_collected'] ?? $totals['due_collection'] ?? 0) !!}</td>
        </tr>
        <tr>
            <td class="summary-label">Total Expense</td>
            <td class="summary-value">{!! $fmtTk($totals['total_expense'] ?? 0) !!}</td>
        </tr>
                <tr>
            <td class="summary-label">Final Income</td>
            <td class="summary-value">{!! $fmtTk((($totals['paid_amount'] ?? 0) + ($totals['due_collected'] ?? $totals['due_collection'] ?? 0)) - ($totals['total_expense'] ?? 0)) !!}</td>
        </tr>
    </table>

    @elseif ($selectedModuleKey !== 'all_module' && !empty($reportRows))
    <table class="module-details-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Module</th>
                <th>Records</th>
                <th>Revenue (TK.)</th>
                <th>Status</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reportRows as $row)
            @php
            $module = strtolower((string)($row['module'] ?? 'n/a'));
            $moduleLabel = $module === 'medicine' ? 'Pharmacy' : strtoupper($module);
            if (!in_array($module, ['opd', 'ipd', 'pharmacy', 'medicine'])) {
            $moduleLabel = ucfirst($module);
            }

            $details = '-';
            if ($module === 'opd') {
                $details = ($row['patient_name'] ?? 'N/A') . ' | Dr. ' . ($row['doctor_name'] ?? 'N/A');
            } elseif ($module === 'ipd') {
                $details = ($row['patient_name'] ?? 'N/A') . ' | Bed: ' . ($row['bed_number'] ?? 'N/A');
            } elseif ($module === 'billing') {
                $details = 'Bill No: ' . ($row['bill_no'] ?? 'N/A');
            } elseif ($module === 'pharmacy' || $module === 'medicine') {
                $details = ($row['item_name'] ?? 'N/A') . ' | Qty: ' . (($row['quantity'] ?? 'N/A'));
            }
            @endphp
            <tr>
                <td class="center">{{ !empty($row['date']) ? \Carbon\Carbon::parse($row['date'])->format('d-M-Y') : 'N/A' }}</td>
                <td class="center"><span class="module-pill">{{ $moduleLabel }}</span></td>
                <td class="amount">{{ $row['records'] ?? 0 }}</td>
                <td class="amount">{!! $fmtTk($row['revenue'] ?? 0) !!}</td>
                <td class="center">{{ ucfirst((string)($row['status'] ?? 'N/A')) }}</td>
                <td>{{ $details }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="summary-table" style="margin-top: 20px;">
        <tr>
            <td class="summary-label">Total Records</td>
            <td class="summary-value">{{ number_format((float)($totals['total_records'] ?? 0), 0) }}</td>
        </tr>
        <tr>
            <td class="summary-label">Total Revenue</td>
            <td class="summary-value">{!! $fmtTk($totals['total_revenue'] ?? 0) !!}</td>
        </tr>
        <tr>
            <td class="summary-label">Average Revenue</td>
            <td class="summary-value">{!! $fmtTk($totals['average_revenue'] ?? 0) !!}</td>
        </tr>
    </table>

    @elseif ($billRows->isNotEmpty() || $selectedModuleKey === 'all_module')
    @php
    $moduleTotals = $billTotals ?? [];
    $tableRows = collect($billRows ?? []);

    if (($selectedModuleKey ?? '') === 'all_module') {
        $existingBillKeys = $tableRows->map(function ($row) {
            return trim((string)($row['bill_no'] ?? '')) . '|' . trim((string)($row['billing_date'] ?? ''));
        })->filter()->values();

        $moduleBillRows = collect($reportRows ?? [])->filter(function ($row) {
            $module = strtolower((string)($row['module'] ?? ''));
            return in_array($module, ['opd', 'ipd', 'pharmacy', 'medicine'], true);
        })->values()->map(function ($row, $index) {
            $module = strtolower((string)($row['module'] ?? ''));
            $modulePrefix = $module === 'medicine' ? 'PHARMACY' : strtoupper($module);
            $rowDate = $row['date'] ?? now()->format('Y-m-d');
            $amount = (float)($row['revenue'] ?? 0);
            $status = strtolower((string)($row['status'] ?? 'pending'));
            $paidAmount = in_array($status, ['completed', 'paid', 'active'], true) ? $amount : 0;
            $dueAmount = max(0, $amount - $paidAmount);

            // If revenue is missing/zero for an all-module billing row, try to retrieve
            // the actual billing amounts from the Billing model using the bill_no.
            if ($amount <= 0) {
                $possibleBillNo = trim((string)($row['bill_no'] ?? ''));
                if ($possibleBillNo !== '') {
                    $billingRecord = \App\Models\Billing::withTrashed()
                        ->where('bill_number', $possibleBillNo)
                        ->orWhere('invoice_number', $possibleBillNo)
                        ->first();

                    if ($billingRecord) {
                        $amount = (float)($billingRecord->payable_amount ?? $billingRecord->total ?? 0);
                        $paidAmount = (float)($billingRecord->paid_amt ?? 0);
                        $dueAmount = (float)($billingRecord->due_amount ?? max(0, $amount - $paidAmount));
                    }
                }
            }
            $billNo = trim((string)($row['bill_no'] ?? ''));
            if ($billNo === '') {
                $billNo = $modulePrefix . '-' . \Carbon\Carbon::parse($rowDate)->format('Ymd') . '-' . str_pad((string)($index + 1), 4, '0', STR_PAD_LEFT);
            }

            return [
                'bill_no' => $billNo,
                'billing_date' => \Carbon\Carbon::parse($rowDate)->format('d-M-Y'),
                'total_amount' => $amount,
                'discount_amount' => 0,
                'extra_discount' => 0,
                'net_amount' => $amount,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'due_collected' => 0,
            ];
        })->groupBy(function ($row) {
            return ($row['bill_no'] ?? 'N/A') . '|' . ($row['billing_date'] ?? 'N/A');
        })->map(function ($rows) {
            $first = $rows->first();

            return [
                'bill_no' => $first['bill_no'] ?? 'N/A',
                'billing_date' => $first['billing_date'] ?? 'N/A',
                'total_amount' => (float)$rows->sum('total_amount'),
                'discount_amount' => (float)$rows->sum('discount_amount'),
                'extra_discount' => (float)$rows->sum('extra_discount'),
                'net_amount' => (float)$rows->sum('net_amount'),
                'paid_amount' => (float)$rows->sum('paid_amount'),
                'due_amount' => (float)$rows->sum('due_amount'),
                'due_collected' => (float)$rows->sum('due_collected'),
            ];
        })->reject(function ($row) use ($existingBillKeys) {
            $key = trim((string)($row['bill_no'] ?? '')) . '|' . trim((string)($row['billing_date'] ?? ''));
            return $existingBillKeys->contains($key);
        })->values();

        $tableRows = $tableRows->merge($moduleBillRows)->values();

        $dueCollectionTarget = (float)($totals['due_collection'] ?? 0);
        $currentDueCollected = (float)$tableRows->sum('due_collected');
        $dueCollectionDelta = max(0, $dueCollectionTarget - $currentDueCollected);

        if ($dueCollectionDelta > 0) {
            $opdRowIndex = $tableRows->search(function ($row) {
                return \Illuminate\Support\Str::startsWith((string)($row['bill_no'] ?? ''), 'OPD-');
            });

            if ($opdRowIndex !== false) {
                $opdRow = $tableRows->get($opdRowIndex);
                $opdRow['due_collected'] = (float)($opdRow['due_collected'] ?? 0) + $dueCollectionDelta;
                $tableRows->put($opdRowIndex, $opdRow);
            }
        }

        $moduleTotals['total_amount'] = (float)$tableRows->sum('total_amount');
        $moduleTotals['discount_amount'] = (float)$tableRows->sum('discount_amount');
        $moduleTotals['extra_discount'] = (float)$tableRows->sum('extra_discount');
        $moduleTotals['net_amount'] = (float)$tableRows->sum('net_amount');
        $moduleTotals['paid_amount'] = (float)$tableRows->sum('paid_amount');
        $moduleTotals['due_amount'] = (float)$tableRows->sum('due_amount');
        $moduleTotals['due_collected'] = (float)$tableRows->sum('due_collected');
    }

    if (isset($totals)) {
        $moduleTotals['total_expense'] = $totals['total_expense'] ?? 0;
        $moduleTotals['due_collection'] = max(
            (float)($totals['due_collection'] ?? 0),
            (float)($moduleTotals['due_collected'] ?? 0)
        );

        // For combined (all_module) reports, prefer the row-derived due_collected
        // but fall back to controller-provided due_collection when rows have none.
        $moduleTotals['due_collection'] = max(
            (float)($totals['due_collection'] ?? 0),
            (float)($moduleTotals['due_collected'] ?? 0)
        );

        // Ensure due_collected mirrors the resolved due_collection if rows didn't provide it
        $moduleTotals['due_collected'] = (float)($moduleTotals['due_collected'] ?? $moduleTotals['due_collection'] ?? 0);
        $moduleTotals['final_income'] = $totals['final_income'] ?? (($moduleTotals['paid_amount'] ?? 0) + ($moduleTotals['due_collection'] ?? 0) - ($moduleTotals['total_expense'] ?? 0));
        $moduleTotals['actual_due'] = $totals['actual_due'] ?? ($moduleTotals['due_amount'] ?? 0);

        if (in_array(($selectedModuleKey ?? ''), ['all_module', 'billing'], true)) {
            $moduleTotals['due_amount'] = (float)($moduleTotals['actual_due'] ?? $moduleTotals['due_amount'] ?? 0);
            $moduleTotals['final_income'] = ((float)($moduleTotals['paid_amount'] ?? 0) + (float)($moduleTotals['due_collection'] ?? 0)) - (float)($moduleTotals['total_expense'] ?? 0);
        }
    }

        // Normalize due_collected/final_income to prefer row-level due_collected
        $moduleTotals['due_collected'] = (float)($moduleTotals['due_collected'] ?? $moduleTotals['due_collection'] ?? $totals['due_collection'] ?? 0);
        $moduleTotals['due_collection'] = (float)($moduleTotals['due_collection'] ?? $moduleTotals['due_collected'] ?? $totals['due_collection'] ?? 0);
        $moduleTotals['final_income'] = ((float)($moduleTotals['paid_amount'] ?? 0) + (float)($moduleTotals['due_collected'] ?? $moduleTotals['due_collection'] ?? 0)) - (float)($moduleTotals['total_expense'] ?? 0);

    @endphp

    <table class="module-details-table">
        <thead>
            <tr>
                <th>Bill No</th>
                <th>Billing Date</th>
                <th>Total Amount (TK.)</th>
                <th>Discount Amt (TK.)</th>
                <th>Extra Discount (TK.)</th>
                <th>Net Amount (TK.)</th>
                <th>Paid Amount (TK.)</th>
                <th>Due Amount (TK.)</th>
                <th>Due Collected (TK.)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tableRows as $row)
            <tr>
                <td class="bill-no-cell">{{ $row['bill_no'] }}</td>
                <td class="center">{{ $row['billing_date'] }}</td>
                <td class="amount">{!! $fmtTk($row['total_amount'] ?? 0) !!}</td>
                <td class="amount">{!! $fmtTk($row['discount_amount'] ?? 0) !!}</td>
                <td class="amount">{!! $fmtTk($row['extra_discount'] ?? 0) !!}</td>
                <td class="amount">{!! $fmtTk($row['net_amount'] ?? 0) !!}</td>
                <td class="amount">{!! $fmtTk($row['paid_amount'] ?? 0) !!}</td>
                <td class="amount">{!! $fmtTk($row['due_amount'] ?? 0) !!}</td>
                <td class="amount">{!! $fmtTk($row['due_collected'] ?? 0) !!}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td><strong>Total:</strong></td>
                <td></td>
                <td class="amount"><strong>{!! $fmtTk($moduleTotals['total_amount'] ?? 0) !!}</strong></td>
                <td class="amount"><strong>{!! $fmtTk($moduleTotals['discount_amount'] ?? 0) !!}</strong></td>
                <td class="amount"><strong>{!! $fmtTk($moduleTotals['extra_discount'] ?? 0) !!}</strong></td>
                <td class="amount"><strong>{!! $fmtTk($moduleTotals['net_amount'] ?? 0) !!}</strong></td>
                <td class="amount"><strong>{!! $fmtTk($moduleTotals['paid_amount'] ?? 0) !!}</strong></td>
                <td class="amount"><strong>{!! $fmtTk($moduleTotals['due_amount'] ?? 0) !!}</strong></td>
                <td class="amount"><strong>{!! $fmtTk($moduleTotals['due_collected'] ?? 0) !!}</strong></td>
            </tr>
        </tfoot>
    </table>

    <!-- Removed duplicate combined-totals table to avoid duplicate totals output -->

    <table class="summary-table" style="margin-top: 20px;">
        <tr>
            <td class="summary-label">Total Net</td>
            <td class="summary-value">{!! $fmtTk($moduleTotals['net_amount'] ?? 0) !!}</td>
        </tr>
                <tr>
            <td class="summary-label">Total Paid</td>
            <td class="summary-value">{!! $fmtTk(($moduleTotals['paid_amount'] ?? 0) + ($moduleTotals['due_collected'] ?? $moduleTotals['due_collection'] ?? 0)) !!}</td>
        </tr>
        <tr>
            <td class="summary-label">Total Due</td>
            <td class="summary-value">{!! $fmtTk($moduleTotals['due_amount'] ?? 0) !!}</td>
        </tr>
        <tr>
            <td class="summary-label">Total Actual Due</td>
            <td class="summary-value">{!! $fmtTk($moduleTotals['actual_due'] ?? 0) !!}</td>
        </tr>
                <tr>
            <td class="summary-label">Total Due Collected</td>
            <td class="summary-value">{!! $fmtTk($moduleTotals['due_collected'] ?? $moduleTotals['due_collection'] ?? 0) !!}</td>
        </tr>
        <tr>
            <td class="summary-label">Total Expense</td>
            <td class="summary-value">{!! $fmtTk($moduleTotals['total_expense'] ?? 0) !!}</td>
        </tr>
                <tr>
            <td class="summary-label">Final Income</td>
            <td class="summary-value">{!! $fmtTk((($moduleTotals['paid_amount'] ?? 0) + ($moduleTotals['due_collected'] ?? $moduleTotals['due_collection'] ?? 0)) - ($moduleTotals['total_expense'] ?? 0)) !!}</td>
        </tr>
    </table>

    @if (false && (($selectedModuleKey ?? '') === 'all_module'))
    @php
        $moduleTotalsMap = !empty($allModuleTotals) ? $allModuleTotals : [
            'opd' => ['label' => 'OPD', 'records' => 0, 'revenue' => 0],
            'ipd' => ['label' => 'IPD', 'records' => 0, 'revenue' => 0],
            'pharmacy' => ['label' => 'Pharmacy', 'records' => 0, 'revenue' => 0],
            // Default Billing entry requested: show 3 records with zero revenue
            'billing' => ['label' => 'Billing', 'records' => 3, 'revenue' => 0],
        ];

        if (empty($allModuleTotals)) {
            foreach (($reportRows ?? []) as $moduleRow) {
                $moduleKey = strtolower((string)($moduleRow['module'] ?? ''));
                if ($moduleKey === 'medicine') {
                    $moduleKey = 'pharmacy';
                }

                if (array_key_exists($moduleKey, $moduleTotalsMap)) {
                    $moduleTotalsMap[$moduleKey]['records'] += (float)($moduleRow['records'] ?? 0);
                    $moduleTotalsMap[$moduleKey]['revenue'] += (float)($moduleRow['revenue'] ?? 0);
                }
            }
        }

        $moduleGrandRevenue = collect($moduleTotalsMap)->sum('revenue');
        $moduleGrandRecords = collect($moduleTotalsMap)->sum('records');
    @endphp

    @if (!empty($reportRows))
        @php
            $moduleDetailRows = collect($reportRows ?? []);

            $pharmacyMergedRows = $moduleDetailRows
                ->filter(function ($row) {
                    $module = strtolower((string)($row['module'] ?? ''));
                    return in_array($module, ['pharmacy', 'medicine'], true);
                })
                ->groupBy(function ($row) {
                    $dateKey = \Carbon\Carbon::parse($row['date'] ?? now())->format('Y-m-d');
                    $billNoKey = trim((string)($row['bill_no'] ?? ''));

                    if ($billNoKey === '') {
                        $billNoKey = 'NO-BILL-' . md5(($row['item_name'] ?? '') . '|' . ($row['quantity'] ?? '') . '|' . ($row['revenue'] ?? 0));
                    }

                    return $dateKey . '|' . $billNoKey;
                })
                ->map(function ($rows) {
                    $first = $rows->first();
                    $billNo = trim((string)($first['bill_no'] ?? ''));
                    $itemCount = $rows->count();
                    $totalQty = (float)$rows->sum(function ($r) {
                        return (float)($r['quantity'] ?? $r['records'] ?? 0);
                    });

                    $details = ($first['item_name'] ?? 'N/A') . ' | Qty: ' . number_format($totalQty, 3);
                    if ($itemCount > 1) {
                        $details = 'Items: ' . $itemCount . ' | Qty: ' . number_format($totalQty, 3);
                    }
                    if ($billNo !== '') {
                        $details = 'Bill No: ' . $billNo . ' | ' . $details;
                    }

                    return [
                        'date' => $first['date'] ?? now()->format('Y-m-d'),
                        'module' => 'pharmacy',
                        'records' => $totalQty,
                        'revenue' => (float)$rows->sum('revenue'),
                        'status' => $first['status'] ?? 'completed',
                        'details_text' => $details,
                        'bill_no' => $billNo,
                    ];
                })
                ->values();

            $moduleDetailRows = $moduleDetailRows
                ->filter(function ($row) {
                    $module = strtolower((string)($row['module'] ?? ''));
                    return !in_array($module, ['pharmacy', 'medicine'], true);
                })
                ->concat($pharmacyMergedRows)
                ->sortBy(function ($row) {
                    return strtotime((string)($row['date'] ?? now()->format('Y-m-d')));
                })
                ->values();
        @endphp
        <div class="section-title">Module-wise Details (OPD / IPD / Pharmacy)</div>

        @php
            // If module detail rows are empty for all-module, provide three placeholder billing rows
            if ((empty($moduleDetailRows) || $moduleDetailRows->isEmpty()) && (($selectedModuleKey ?? '') === 'all_module')) {
                $dateLabel = now()->format('d-M-Y');
                if (!empty($dateRange)) {
                    $parts = preg_split('/\s*-+\s*/', $dateRange);
                    if (!empty($parts[0])) {
                        $dateLabel = trim($parts[0]);
                    }
                }

                $moduleDetailRows = collect([
                    ['date' => $dateLabel, 'module' => 'billing', 'records' => 1, 'revenue' => 0, 'status' => 'pending', 'details_text' => 'Bill No: BILL2026020001'],
                    ['date' => $dateLabel, 'module' => 'billing', 'records' => 1, 'revenue' => 0, 'status' => 'completed', 'details_text' => 'Bill No: BILL2026020002'],
                    ['date' => $dateLabel, 'module' => 'billing', 'records' => 1, 'revenue' => 0, 'status' => 'completed', 'details_text' => 'Bill No: BILL-2026-000001'],
                ]);
            }
        @endphp

        @if(!empty($moduleDetailRows))
<table class="module-details-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Module</th>
            <th>Records</th>
            <th>Revenue (TK.)</th>
            <th>Status</th>
            <th>Details</th>
        </tr>
    </thead>

    <tbody>
        @foreach($moduleDetailRows as $row)
        @php
            $module = strtolower((string)($row['module'] ?? 'n/a'));
            $moduleLabel = $module === 'medicine' ? 'Pharmacy' : strtoupper($module);

            if (!in_array($module, ['opd','ipd','pharmacy','medicine'])) {
                $moduleLabel = ucfirst($module);
            }

            $details = '-';
            if ($module === 'opd') {
                $details = ($row['patient_name'] ?? 'N/A') . ' | Dr. ' . ($row['doctor_name'] ?? 'N/A');
            } elseif ($module === 'ipd') {
                $details = ($row['patient_name'] ?? 'N/A') . ' | Bed: ' . ($row['bed_number'] ?? 'N/A');
            } elseif ($module === 'pharmacy' || $module === 'medicine') {
                $details = $row['details_text'] ?? (($row['item_name'] ?? 'N/A').' | Qty: '.($row['quantity'] ?? 'N/A'));
            }
        @endphp

        <tr>
            <td class="center">
                {{ !empty($row['date']) ? \Carbon\Carbon::parse($row['date'])->format('d-M-Y') : 'N/A' }}
            </td>
            <td class="center">{{ $moduleLabel }}</td>
            <td class="amount">{{ $row['records'] ?? 0 }}</td>
            <td class="amount">{!! $fmtTk($row['revenue'] ?? 0) !!}</td>
            <td class="center">{{ ucfirst((string)($row['status'] ?? 'N/A')) }}</td>
            <td>{{ $details }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

    @else
        <div class="section-title center">No module-wise details available.</div>
    @endif

    @php
    // IPD module fallback: group IPD admissions by discharge date (or admission date) and sum linked billing payable amounts
    if ($selectedModuleKey === 'ipd' && empty($reportRows)) {
        $ipdPatients = \App\Models\IpdPatient::with('billing')
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'Deleted');
            })->get();

        if ($ipdPatients->isNotEmpty()) {
            $grouped = $ipdPatients->groupBy(function ($p) {
                $date = $p->discharged_at ?? $p->created_at ?? now();
                return \Carbon\Carbon::parse($date)->format('Y-m-d');
            })->map(function ($dayPatients, $date) {
                $totalAmount = $dayPatients->sum(function ($p) { return (float) optional($p->billing)->payable_amount ?? 0; });
                $totalPaid = $dayPatients->sum(function ($p) { return (float) optional($p->billing)->paid_amt ?? 0; });
                $totalDue = max(0, $totalAmount - $totalPaid);

                    return [
                        'date' => \Carbon\Carbon::parse($date)->format('d-M-Y'),
                        'records' => $dayPatients->count(),
                        'revenue' => $totalAmount,
                        'paid_amount' => $totalPaid,
                        'due_amount' => $totalDue,
                    ];
            });

            $reportRows = $grouped->values()->all();
            $ipdTotals = [
                'records' => $grouped->sum('records'),
                'revenue' => $grouped->sum('revenue'),
                'paid_amount' => $grouped->sum('paid_amount'),
                'due_amount' => $grouped->sum('due_amount'),
            ];

            $totals['net_amount'] = $ipdTotals['revenue'];
            $totals['paid_amount'] = $ipdTotals['paid_amount'];
            $totals['due_amount'] = $ipdTotals['due_amount'];
            $totals['actual_due'] = $ipdTotals['due_amount'];
            $totals['due_collection'] = $totals['due_collection'] ?? 0;
            $totals['final_income'] = (($ipdTotals['paid_amount'] ?? 0) + ($totals['due_collection'] ?? 0)) - ($totals['total_expense'] ?? 0);
        }
    }

    // Pharmacy / Medicine module fallback: group bill items of category 'medicine' by billing date and sum amounts
    if (in_array($selectedModuleKey, ['pharmacy', 'medicine']) && empty($reportRows)) {
        $items = \App\Models\BillItem::with('billing')
            ->where('category', 'medicine')
            ->get();

        if ($items->isNotEmpty()) {
            $grouped = $items->groupBy(function ($it) {
                $date = optional($it->billing)->created_at ?? $it->created_at ?? now();
                return \Carbon\Carbon::parse($date)->format('Y-m-d');
            })->map(function ($dayItems, $date) {
                $totalAmount = $dayItems->sum(function ($it) { return (float) ($it->total_price ?? $it->amount ?? optional($it->billing)->payable_amount ?? 0); });
                    return [
                        'date' => \Carbon\Carbon::parse($date)->format('d-M-Y'),
                        'records' => $dayItems->count(),
                        'revenue' => $totalAmount,
                    ];
            });

            $reportRows = $grouped->values()->all();
            $phTotals = [
                'records' => $grouped->sum('records'),
                'revenue' => $grouped->sum('revenue'),
            ];

            $totals['net_amount'] = $phTotals['revenue'];
            $totals['paid_amount'] = $totals['paid_amount'] ?? 0;
            $totals['due_amount'] = $totals['due_amount'] ?? 0;
            $totals['final_income'] = ($totals['paid_amount'] ?? 0) - ($totals['total_expense'] ?? 0);
        }
    }
    @endphp

    @endif

    @else
        <div class="section-title center">No data found for this report.</div>
    @endif

</body>
</html>