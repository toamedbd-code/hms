<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 20px;
            color: #000;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .hospital-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .report-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .date-range {
            font-size: 12px;
            margin-bottom: 5px;
        }

        .print-time {
            font-size: 10px;
            color: #666;
            margin-bottom: 20px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 11px;
        }

        .data-table th {
            background-color: #f5f5f5;
            border: 1px solid #000;
            padding: 8px 4px;
            text-align: center;
            font-weight: bold;
        }

        .data-table td {
            border: 1px solid #000;
            padding: 6px 4px;
            text-align: right;
        }

        .data-table td:first-child {
            text-align: center;
        }

        .total-row {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        .summary-table {
            width: 300px;
            margin: 20px auto;
            border-collapse: collapse;
            font-size: 11px;
            margin-top: 20px;
        }

        .summary-table td {
            padding: 4px 8px;
            border: 1px solid #000;
        }

        .summary-label {
            background-color: #f5f5f5;
            font-weight: bold;
            text-align: right;
            width: 150px;
        }

        .summary-value {
            text-align: right;
            width: 100px;
        }

        .page-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: right;
            margin-top: 30px;
            font-size: 10px;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
        }

        .page-count {
            font-weight: bold;
        }

        .amount {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        /* Module details table */
        .module-details-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 11px;
        }

        .module-details-table th,
        .module-details-table td {
            border: 1px solid #000;
            padding: 6px 4px;
            text-align: right;
        }

        .module-details-table th {
            background-color: #e0e0e0;
            font-weight: bold;
            text-align: center;
        }

        .module-details-table td:first-child {
            text-align: left;
            font-weight: bold;
        }

        .section-title {
            font-size: 13px;
            font-weight: bold;
            margin-top: 25px;
            margin-bottom: 10px;
            text-align: left;
        }

        /* Summary statistics */
        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 11px;
        }

        .stats-table th,
        .stats-table td {
            border: 1px solid #000;
            padding: 6px 4px;
            text-align: left;
        }

        .stats-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }

        .stats-table .value {
            text-align: right;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="hospital-name">{{ $websetting->report_title ?? 'N/A' }}</div>
        <div class="report-title">{{ $title }}</div>
        <div class="date-range">{{ $dateRange }}</div>
        <div class="print-time">{{ now()->format('d-M-Y H:i A') }}</div>
    </div>

    @if ($selectedModule === 'all_module' && !empty($moduleDetails))
    @php
    $moduleTotals = [
    'qty' => array_sum(array_column($moduleDetails, 'qty')),
    'amount' => array_sum(array_column($moduleDetails, 'amount')),
    'discount' => array_sum(array_column($moduleDetails, 'discount')),
    'net_amount' => array_sum(array_column($moduleDetails, 'net_amount')),
    'paid_amount' => array_sum(array_column($moduleDetails, 'paid_amount')),
    'due_amount' => array_sum(array_column($moduleDetails, 'due_amount')),
    'due_collection' => array_sum(array_column($moduleDetails, 'due_collection'))
    ];
    @endphp

    <table class="module-details-table">
        <thead>
            <tr>
                <th>Module</th>
                <th>Qty.</th>
                <th>Amount</th>
                <th>Dscnt. Amt</th>
                <th>Net Amt</th>
                <th>Paid Amt</th>
                <th>Due Amt</th>
                <th>Due Collection</th>
            </tr>
        </thead>
        <tbody>
            @foreach($moduleDetails as $moduleName => $details)
            <tr>
                <td>{{ ucfirst($moduleName) }}</td>
                <td class="amount">{{ number_format($details['qty']) }}</td>
                <td class="amount">{{ number_format($details['amount'], 2) }}</td>
                <td class="amount">{{ number_format($details['discount'], 2) }}</td>
                <td class="amount">{{ number_format($details['net_amount'], 2) }}</td>
                <td class="amount">{{ number_format($details['paid_amount'], 2) }}</td>
                <td class="amount">{{ number_format($details['due_amount'], 2) }}</td>
                <td class="amount">{{ number_format($details['due_collection'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td><strong>Total:</strong></td>
                <td class="amount"><strong>{{ number_format($moduleTotals['qty']) }}</strong></td>
                <td class="amount"><strong>{{ number_format($moduleTotals['amount'], 2) }}</strong></td>
                <td class="amount"><strong>{{ number_format($moduleTotals['discount'], 2) }}</strong></td>
                <td class="amount"><strong>{{ number_format($moduleTotals['net_amount'], 2) }}</strong></td>
                <td class="amount"><strong>{{ number_format($moduleTotals['paid_amount'], 2) }}</strong></td>
                <td class="amount"><strong>{{ number_format($moduleTotals['due_amount'], 2) }}</strong></td>
                <td class="amount"><strong>{{ number_format($moduleTotals['due_collection'], 2) }}</strong></td>
            </tr>
        </tfoot>
    </table>

    <table class="summary-table" style="margin-top: 20px;">
        <tr>
            <td class="summary-label">Total Net</td>
            <td class="summary-value">{{ number_format($moduleTotals['net_amount'], 2) }}</td>
        </tr>
        <tr>
            <td class="summary-label">Total Paid</td>
            <td class="summary-value">{{ number_format($moduleTotals['paid_amount'], 2) }}</td>
        </tr>
        <tr>
            <td class="summary-label">Total Due</td>
            <td class="summary-value">{{ number_format($moduleTotals['due_amount'], 2) }}</td>
        </tr>
        <tr>
            <td class="summary-label">Total Due Collected</td>
            <td class="summary-value">{{ number_format($moduleTotals['due_collection'], 2) }}</td>
        </tr>
        <tr>
            <td class="summary-label">Total Actual Due</td>
            <td class="summary-value">{{ number_format($moduleTotals['due_amount'] - $moduleTotals['due_collection'], 2) }}</td>
        </tr>
    </table>

    @else

    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Qty.</th>
                <th>Amount</th>
                <th>Dscnt. Amt</th>
                <th>Net Amt</th>
                <th>Paid Amt</th>
                <th>Due Amt</th>
                <th>Due Collection</th>
            </tr>
        </thead>
        <tbody>
            @foreach($dailyData as $row)
            <tr>
                <td class="center">{{ $row['date'] }}</td>
                <td class="amount">{{ number_format($row['qty']) }}</td>
                <td class="amount">{{ number_format($row['amount'], 2) }}</td>
                <td class="amount">{{ number_format($row['discount'], 2) }}</td>
                <td class="amount">{{ number_format($row['net_amount'], 2) }}</td>
                <td class="amount">{{ number_format($row['paid_amount'], 2) }}</td>
                <td class="amount">{{ number_format($row['due_amount'], 2) }}</td>
                <td class="amount">{{ number_format($row['due_collection'], 2) }}</td>
            </tr>
            @endforeach

            <tr class="total-row">
                <td class="center"><strong>Total:</strong></td>
                <td class="amount"><strong>{{ number_format($totals['qty']) }}</strong></td>
                <td class="amount"><strong>{{ number_format($totals['amount'], 2) }}</strong></td>
                <td class="amount"><strong>{{ number_format($totals['discount'], 2) }}</strong></td>
                <td class="amount"><strong>{{ number_format($totals['net_amount'], 2) }}</strong></td>
                <td class="amount"><strong>{{ number_format($totals['paid_amount'], 2) }}</strong></td>
                <td class="amount"><strong>{{ number_format($totals['due_amount'], 2) }}</strong></td>
                <td class="amount"><strong>{{ number_format($totals['due_collection'], 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <table class="summary-table">
        <tr>
            <td class="summary-label">Total Net</td>
            <td class="summary-value">{{ number_format($totals['net_amount'], 2) }}</td>
        </tr>
        <tr>
            <td class="summary-label">Total Paid</td>
            <td class="summary-value">{{ number_format($totals['paid_amount'], 2) }}</td>
        </tr>
        <tr>
            <td class="summary-label">Total Due</td>
            <td class="summary-value">{{ number_format($totals['due_amount'], 2) }}</td>
        </tr>
        <tr>
            <td class="summary-label">Total Due Collected</td>
            <td class="summary-value">{{ number_format($totals['due_collection'], 2) }}</td>
        </tr>
        <tr>
            <td class="summary-label">Total Actual Due</td>
            <td class="summary-value">{{ number_format($totals['due_amount'] - $totals['due_collection'], 2) }}</td>
        </tr>
    </table>

    @endif

    <div class="page-footer">
        <span class="page-count">Page {PAGENO} of {nbpg}</span>
    </div>
</body>
</html>