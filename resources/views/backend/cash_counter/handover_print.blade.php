<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Counter User Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #000; margin: 0; padding: 8px; }
        @page { size: A4 portrait; margin: 8mm; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 7px; table-layout: fixed; }
        th, td { border: 1px solid #000; padding: 4px; vertical-align: middle; }
        thead th { background: #f5f5f5; }
        .center { text-align: center; }
        .right { text-align: right; }
        .title-wrap { text-align: center; margin-bottom: 7px; }
        .hospital-name { font-size: 15px; font-weight: 700; }
        .report-title { font-size: 12px; font-weight: 700; margin-top: 2px; }
        .meta-line { margin-top: 1px; font-size: 9px; }
        .summary-box { margin-top: 6px; }
        .summary-box td { font-size: 10px; }
        .summary-label { font-weight: 700; width: 26%; }
        .summary-value { width: 24%; }
        .section-title { font-size: 11px; font-weight: 700; margin: 7px 0 4px; }
        .total-row td { font-weight: 700; background: #f0f0f0; }
        .signatures { margin-top: 18px; }
        .signatures td { border: none; text-align: center; padding-top: 18px; }
        .sign-line { border-top: 1px solid #000; padding-top: 4px; display: inline-block; min-width: 100px; }

        @media print {
            body { padding: 0; }
        }
    </style>
</head>
<body>
    @php
        $webSetting = function_exists('get_cached_web_setting') ? get_cached_web_setting() : null;
        $hospitalName = $webSetting?->company_name ?? config('app.name', 'Hospital');
        $hospitalAddress = $webSetting?->address ?? '';
        $periodFrom = optional($period['from'])->format('d-M-Y h:i A');
        $periodTo = optional($period['to'])->format('d-M-Y h:i A');
        $userSummaryCount = isset($userSummaries) ? count($userSummaries) : 0;
        $isSingleUser = $userSummaryCount === 1;
        $headerUserName = $isSingleUser
            ? ($userSummaries[0]['user_name'] ?? ($session->user_name ?? 'N/A'))
            : (($userSummaryCount === 0 && !empty($session->user_name)) ? $session->user_name : 'All Users');
        $headerCounterName = $session->counter_name ?? 'N/A';
        $headerTotalAmount = (float) ($totals['total_collection'] ?? 0);
        $billingSources = $sourceBreakdown['billing'] ?? collect();
        $dueSources = $sourceBreakdown['due'] ?? collect();
        $detailedBillingRows = $detailedBillingRows ?? collect();
        $detailedTotals = $detailedTotals ?? [];
    @endphp

    <div class="title-wrap">
        <div class="hospital-name">{{ $hospitalName }}</div>
        @if($hospitalAddress !== '')
            <div class="meta-line">{{ $hospitalAddress }}</div>
        @endif
        <div class="report-title">Cash Counter Close Report</div>
        <div class="meta-line">Session #{{ $session->id }} | Shift: {{ $session->shift_name ?: 'N/A' }}</div>
        <div class="meta-line">Period: {{ $periodFrom }} - {{ $periodTo }}</div>
    </div>

    <table class="summary-box">
        <tr>
            <td class="summary-label">User Name</td>
            <td class="summary-value">{{ $headerUserName }}</td>
            <td class="summary-label">Counter</td>
            <td class="summary-value">{{ $headerCounterName }}</td>
        </tr>
        <tr>
            <td class="summary-label">Total Amount</td>
            <td class="summary-value right" colspan="3">{{ number_format($headerTotalAmount, 2) }}</td>
        </tr>
    </table>

    <div class="section-title">Bill-wise Report List</div>
    <table>
        <thead>
            <tr>
                <th style="width: 4%;" class="center">#</th>
                <th style="width: 12%;">Bill No</th>
                <th style="width: 10%;" class="center">Date</th>
                <th style="width: 14%;">User</th>
                <th style="width: 9%;" class="right">Total</th>
                <th style="width: 8%;" class="right">Discount</th>
                <th style="width: 8%;" class="right">Extra</th>
                <th style="width: 9%;" class="right">Net</th>
                <th style="width: 9%;" class="right">Paid</th>
                <th style="width: 9%;" class="right">Due</th>
                <th style="width: 8%;" class="right">Due Collected</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($detailedBillingRows as $index => $row)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $row['bill_number'] ?? 'N/A' }}</td>
                    <td class="center">{{ !empty($row['billing_date']) ? \Carbon\Carbon::parse($row['billing_date'])->format('d-M-Y') : 'N/A' }}</td>
                    <td>{{ $row['user_name'] ?? 'N/A' }}</td>
                    <td class="right">{{ number_format($row['total_amount'] ?? 0, 2) }}</td>
                    <td class="right">{{ number_format($row['discount_amount'] ?? 0, 2) }}</td>
                    <td class="right">{{ number_format($row['extra_discount'] ?? 0, 2) }}</td>
                    <td class="right">{{ number_format($row['net_amount'] ?? 0, 2) }}</td>
                    <td class="right">{{ number_format($row['paid_amount'] ?? 0, 2) }}</td>
                    <td class="right">{{ number_format($row['due_amount'] ?? 0, 2) }}</td>
                    <td class="right">{{ number_format($row['due_collected'] ?? 0, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="center">No bill-wise data found for this close session.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="4">Bill-wise Total</td>
                <td class="right">{{ number_format($detailedTotals['total_amount'] ?? 0, 2) }}</td>
                <td class="right">{{ number_format($detailedTotals['discount_amount'] ?? 0, 2) }}</td>
                <td class="right">{{ number_format($detailedTotals['extra_discount'] ?? 0, 2) }}</td>
                <td class="right">{{ number_format($detailedTotals['net_amount'] ?? 0, 2) }}</td>
                <td class="right">{{ number_format($detailedTotals['paid_amount'] ?? 0, 2) }}</td>
                <td class="right">{{ number_format($detailedTotals['due_amount'] ?? 0, 2) }}</td>
                <td class="right">{{ number_format($detailedTotals['due_collected'] ?? 0, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="section-title">User-wise Collection List</div>
    <table>
        <thead>
            <tr>
                <th style="width: 4%;" class="center">#</th>
                <th style="width: 18%;">User</th>
                <th style="width: 10%;" class="right">Bill IDs</th>
                <th style="width: 12%;" class="right">Due Bill IDs</th>
                <th style="width: 12%;" class="right">Due Collections</th>
                <th style="width: 14%;" class="right">Billing Amount</th>
                <th style="width: 14%;" class="right">Due Amount</th>
                <th style="width: 16%;" class="right">Total Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($userSummaries as $index => $row)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $row['user_name'] }}</td>
                    <td class="right">{{ number_format($row['bill_count']) }}</td>
                    <td class="right">{{ number_format($row['due_bill_id_count']) }}</td>
                    <td class="right">{{ number_format($row['due_collection_count']) }}</td>
                    <td class="right">{{ number_format($row['billing_paid_total'], 2) }}</td>
                    <td class="right">{{ number_format($row['due_collection_total'], 2) }}</td>
                    <td class="right">{{ number_format($row['total_collection'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="center">No collection data found.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="2">Grand Total</td>
                <td class="right">{{ number_format($totals['bill_count']) }}</td>
                <td class="right">{{ number_format($totals['due_bill_id_count']) }}</td>
                <td class="right">{{ number_format($totals['due_collection_count']) }}</td>
                <td class="right">{{ number_format($totals['billing_paid_total'], 2) }}</td>
                <td class="right">{{ number_format($totals['due_collection_total'], 2) }}</td>
                <td class="right">{{ number_format($totals['total_collection'], 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="section-title">Collection Source Breakdown</div>
    <table>
        <thead>
            <tr>
                <th style="width: 34%;">Source Type</th>
                <th style="width: 30%;" class="right">Count</th>
                <th style="width: 36%;" class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($billingSources as $row)
                <tr>
                    <td>Billing - {{ ucfirst($row['source']) }}</td>
                    <td class="right">{{ number_format($row['item_count']) }}</td>
                    <td class="right">{{ number_format($row['amount_total'], 2) }}</td>
                </tr>
            @empty
            @endforelse

            @forelse ($dueSources as $row)
                <tr>
                    <td>Due - {{ ucfirst($row['source']) }}</td>
                    <td class="right">{{ number_format($row['item_count']) }}</td>
                    <td class="right">{{ number_format($row['amount_total'], 2) }}</td>
                </tr>
            @empty
            @endforelse

            @if(count($billingSources) === 0 && count($dueSources) === 0)
                <tr>
                    <td colspan="3" class="center">No source breakdown data found.</td>
                </tr>
            @endif
        </tbody>
    </table>

    <table>
        <thead>
            <tr>
                <th class="center">Opening</th>
                <th class="center">Expected</th>
                <th class="center">Closing</th>
                <th class="center">Handover In</th>
                <th class="center">Handover Out</th>
                <th class="center">Difference</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="right">{{ number_format($counterTotals['opening_amount'], 2) }}</td>
                <td class="right">{{ number_format($counterTotals['expected_amount'], 2) }}</td>
                <td class="right">{{ number_format($counterTotals['closing_amount'], 2) }}</td>
                <td class="right">{{ number_format($counterTotals['handover_in_amount'], 2) }}</td>
                <td class="right">{{ number_format($counterTotals['handover_out_amount'], 2) }}</td>
                <td class="right">{{ number_format($counterTotals['difference_amount'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td><span class="sign-line">Prepared By</span></td>
            <td><span class="sign-line">Checked By</span></td>
            <td><span class="sign-line">Received By</span></td>
        </tr>
    </table>

    <script>
        window.print();
    </script>
</body>
</html>
