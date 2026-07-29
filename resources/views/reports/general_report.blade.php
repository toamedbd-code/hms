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
        .summary-table { width: 48%; margin-left: auto; margin-top: 8px; table-layout: auto; font-size: 12px; }
        .summary-table td { white-space: nowrap; padding: 5px 6px; line-height: 1.3; font-size: 12px; }
        .summary-table .summary-label { text-align: left; }
        .summary-table .summary-value { text-align: right; }
        .module-details-table { table-layout: fixed; width: 100%; }
        .module-details-table thead th {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding: 6px 8px;
            font-size: 16px;
            vertical-align: middle;
            line-height: 1.1;
            text-align: center;
        }
        .module-details-table td {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding: 6px 8px;
            font-size: 16px;
            vertical-align: middle;
        }
        .module-details-table td.details { white-space: normal; }
        .module-details-table tfoot td { white-space: nowrap; }
        /* Avoid breaking rows across pages in PDF renderers */
        tr { page-break-inside: avoid; break-inside: avoid; }
        tbody { display: table-row-group; }
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

        @if(!isset($show_header_footer) || $show_header_footer)
            @includeIf('prints.partials._header', ['show_header_footer' => true])
        @endif

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
            <td class="summary-label"><strong>Total Net</strong></td>
            <td class="summary-value"><strong>{!! $fmtTk($totals['net_amount'] ?? 0) !!}</strong></td>
        </tr>
                <tr>
            <td class="summary-label"><strong>Total Paid</strong></td>
            <td class="summary-value"><strong>{!! $fmtTk($totals['paid_amount'] ?? 0) !!}</strong></td>
        </tr>
        <tr>
            <td class="summary-label"><strong>Total Due</strong></td>
            <td class="summary-value"><strong>{!! $fmtTk($totals['due_amount'] ?? 0) !!}</strong></td>
        </tr>
        <tr>
            <td class="summary-label"><strong>Total Actual Due</strong></td>
            <td class="summary-value"><strong>{!! $fmtTk($totals['actual_due'] ?? 0) !!}</strong></td>
        </tr>
                <tr>
            <td class="summary-label"><strong>Total Due Collected</strong></td>
            <td class="summary-value"><strong>{!! $fmtTk($totals['due_collected'] ?? $totals['due_collection'] ?? 0) !!}</strong></td>
        </tr>
        <tr>
            <td class="summary-label"><strong>Total Refund</strong></td>
            <td class="summary-value"><strong>{!! $fmtTk($totals['total_return_amount'] ?? 0) !!}</strong></td>
        </tr>
        <tr>
            <td class="summary-label"><strong>Total Expense</strong></td>
            <td class="summary-value"><strong>{!! $fmtTk($totals['total_expense'] ?? 0) !!}</strong></td>
        </tr>
                <tr>
            <td class="summary-label"><strong>Final Income</strong></td>
            <td class="summary-value"><strong>{!! $fmtTk((($totals['paid_amount'] ?? 0) + ($totals['due_collected'] ?? $totals['due_collection'] ?? 0)) - ($totals['total_expense'] ?? 0) - ($totals['total_return_amount'] ?? 0)) !!}</strong></td>
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
        $moduleTotals['total_return_amount'] = $totals['total_return_amount'] ?? 0;
        $moduleTotals['due_collection'] = max(
            (float)($totals['due_collection'] ?? 0),
            (float)($moduleTotals['due_collected'] ?? 0)
        );

        // Ensure due_collected mirrors the resolved due_collection if rows didn't provide it
        $moduleTotals['due_collected'] = (float)($moduleTotals['due_collected'] ?? $moduleTotals['due_collection'] ?? 0);
        $moduleTotals['final_income'] = $totals['final_income'] ?? (((float)($moduleTotals['paid_amount'] ?? 0) + (float)($moduleTotals['due_collection'] ?? 0)) - ((float)($moduleTotals['total_expense'] ?? 0) + (float)($moduleTotals['total_return_amount'] ?? 0)));
        $moduleTotals['actual_due'] = $totals['actual_due'] ?? ($moduleTotals['due_amount'] ?? 0);
    }

        // Normalize due_collected/final_income to prefer row-level due_collected
        $moduleTotals['due_collected'] = (float)($moduleTotals['due_collected'] ?? $moduleTotals['due_collection'] ?? $totals['due_collection'] ?? 0);
        $moduleTotals['due_collection'] = (float)($moduleTotals['due_collection'] ?? $moduleTotals['due_collected'] ?? $totals['due_collection'] ?? 0);
        $moduleTotals['final_income'] = ((float)($moduleTotals['paid_amount'] ?? 0) + (float)($moduleTotals['due_collected'] ?? $moduleTotals['due_collection'] ?? 0)) - ((float)($moduleTotals['total_expense'] ?? 0) + (float)($moduleTotals['total_return_amount'] ?? 0));

    @endphp

    <table class="module-details-table">
        <colgroup>
            <col style="width:16%">
            <col style="width:9%">
            <col style="width:11%">
            <col style="width:8%">
            <col style="width:8%">
            <col style="width:11%">
            <col style="width:11%">
            <col style="width:11%">
            <col style="width:11%">
        </colgroup>
        <thead>
            <tr>
                <th>Bill No</th>
                <th>Billing Date</th>
                <th>Total Amount</th>
                <th>Discount</th>
                <th>Extra Discount</th>
                <th>Net Amount</th>
                <th>Paid Amount</th>
                <th>Due Amount</th>
                <th>Due Collected</th>
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
            <td class="summary-label"><strong>Total Net</strong></td>
            <td class="summary-value"><strong>{!! $fmtTk($moduleTotals['net_amount'] ?? 0) !!}</strong></td>
        </tr>
                <tr>
            <td class="summary-label"><strong>Total Paid</strong></td>
            <td class="summary-value"><strong>{!! $fmtTk($moduleTotals['paid_amount'] ?? 0) !!}</strong></td>
        </tr>
        <tr>
            <td class="summary-label"><strong>Total Due</strong></td>
            <td class="summary-value"><strong>{!! $fmtTk($moduleTotals['due_amount'] ?? 0) !!}</strong></td>
        </tr>
        <tr>
            <td class="summary-label"><strong>Total Actual Due</strong></td>
            <td class="summary-value"><strong>{!! $fmtTk($moduleTotals['actual_due'] ?? 0) !!}</strong></td>
        </tr>
                <tr>
            <td class="summary-label"><strong>Total Due Collected</strong></td>
            <td class="summary-value"><strong>{!! $fmtTk($moduleTotals['due_collected'] ?? $moduleTotals['due_collection'] ?? 0) !!}</strong></td>
        </tr>
        <tr>
            <td class="summary-label"><strong>Total Refund</strong></td>
            <td class="summary-value"><strong>{!! $fmtTk($moduleTotals['total_return_amount'] ?? 0) !!}</strong></td>
        </tr>
        <tr>
            <td class="summary-label"><strong>Total Expense</strong></td>
            <td class="summary-value"><strong>{!! $fmtTk($moduleTotals['total_expense'] ?? 0) !!}</strong></td>
        </tr>
                <tr>
            <td class="summary-label"><strong>Final Income</strong></td>
            <td class="summary-value"><strong>{!! $fmtTk($moduleTotals['final_income'] ?? 0) !!}</strong></td>
        </tr>
    </table>

    {{-- Module-wise details removed per user request --}}

    @else
        <div class="section-title center">No data found for this report.</div>
    @endif

    @if(!isset($show_header_footer) || $show_header_footer)
        <div class="page-footer">
            <span class="page-count">Page {PAGENO} of {nbpg}</span>
        </div>
    @endif

</body>
</html>