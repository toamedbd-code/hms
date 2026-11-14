<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Product Return List</title>
    <style>
        body {
            margin: 0;
            color: #333;
            font-family: helvetica, Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.2;
        }

        .header {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #333;
            text-align: center;
        }

        .hospital-name {
            margin-bottom: 3px;
            color: #666;
            font-size: 10pt;
            font-weight: bold;
        }

        .hospital-address,
        .report-meta {
            margin-bottom: 3px;
            color: #666;
            font-size: 9pt;
        }

        .report-title {
            margin-bottom: 5px;
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .summary {
            margin: 10px 0;
            padding: 8px;
            border: 1px solid #e5e7eb;
            background: #f8f9fa;
            font-size: 9pt;
        }

        table {
            width: 100%;
            margin: 10px 0;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 7pt;
        }

        thead {
            display: table-header-group;
        }

        th,
        td {
            padding: 4px 2px;
            border: 1px solid #dee2e6;
            vertical-align: middle;
            text-align: center;
        }

        th {
            background: #f8f9fa;
            font-size: 7pt;
            font-weight: bold;
            text-align: center;
        }

        td {
            word-wrap: break-word;
        }

        .date,
        .number,
        .amount {
            white-space: nowrap;
        }

        .reference-bill {
            overflow-wrap: anywhere;
            word-break: break-all;
        }

        .amount {
            white-space: nowrap;
        }

        .items {
            margin: 0;
            padding: 0;
            list-style: none;
            font-size: 7pt;
        }

        .items li {
            margin: 0 0 2px;
        }

        .status-name {
            font-weight: bold;
        }

        .total-row {
            background: #e1e5ea;
            font-weight: bold;
        }

        .summary-table {
            width: 48%;
            margin: 8px 0 0 auto;
            font-size: 9pt;
        }

        .summary-table td {
            padding: 5px 6px;
            white-space: nowrap;
        }

        .summary-label {
            font-weight: bold;
        }

        .summary-table .amount {
            text-align: right;
        }

        .empty {
            padding: 12px;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $totalAmount = (float) $returns->sum(fn ($return) => (float) $return->total_amount);
        $paidAmount = (float) $returns->sum(fn ($return) => (float) ($return->paid_amount ?? 0));
        $dueAmount = (float) $returns->sum(fn ($return) => max(0, (float) $return->total_amount - (float) ($return->paid_amount ?? 0)));
        $amountWidths = [8.0, 8.0, 8.0];

        foreach ($returns as $return) {
            $rowPaid = (float) ($return->paid_amount ?? 0);
            $rowAmounts = [
                (float) $return->total_amount,
                $rowPaid,
                max(0, (float) $return->total_amount - $rowPaid),
            ];

            foreach ($rowAmounts as $index => $amount) {
                $amountWidths[$index] = max(
                    $amountWidths[$index],
                    strlen(number_format($amount, 2)) * 0.9
                );
            }
        }

        foreach ([$totalAmount, $paidAmount, $dueAmount] as $index => $amount) {
            $amountWidths[$index] = max(
                $amountWidths[$index],
                strlen(number_format($amount, 2)) * 0.9
            );
        }

        $amountWidthTotal = array_sum($amountWidths);
        if ($amountWidthTotal > 44) {
            $amountWidths = array_map(
                fn ($width) => $width * 44 / $amountWidthTotal,
                $amountWidths
            );
            $amountWidthTotal = 44;
        }

        $nonAmountWidths = [12, 8, 14, 11, 18, 13];
        $nonAmountScale = (100 - $amountWidthTotal) / array_sum($nonAmountWidths);
        $columnWidths = array_map(
            fn ($width) => number_format($width * $nonAmountScale, 2, '.', ''),
            $nonAmountWidths
        );
        $amountWidths = array_map(
            fn ($width) => number_format($width, 2, '.', ''),
            $amountWidths
        );
    @endphp

    <div class="header">
        <div class="hospital-name">{{ $hospitalName }}</div>
        @if ($hospitalAddress !== '')
            <div class="hospital-address">{{ $hospitalAddress }}</div>
        @endif
        <div class="report-title">
            Product Return List{{ $returnType !== 'all' ? ' - ' . ucfirst($returnType) : '' }}
        </div>
        <div class="report-meta">
            Generated on: {{ $generatedAt->format('d/m/Y H:i') }}
        </div>
        <div class="report-meta">
            Date Range:
            {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : 'All dates' }}
            to {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y') : 'All dates' }}
        </div>
    </div>

    <div class="summary">
        <strong>Total Records:</strong> {{ $returns->count() }}
    </div>

    <table>
        <colgroup>
            <col style="width: {{ $columnWidths[0] }}%;">
            <col style="width: {{ $columnWidths[1] }}%;">
            <col style="width: {{ $columnWidths[2] }}%;">
            <col style="width: {{ $columnWidths[3] }}%;">
            <col style="width: {{ $columnWidths[4] }}%;">
            <col style="width: {{ $amountWidths[0] }}%;">
            <col style="width: {{ $amountWidths[1] }}%;">
            <col style="width: {{ $amountWidths[2] }}%;">
            <col style="width: {{ $columnWidths[5] }}%;">
        </colgroup>
        <thead>
            <tr>
                <th>Return No</th>
                <th>Date</th>
                <th>Supplier / Customer</th>
                <th>Reference Bill</th>
                <th>Returned Items</th>
                <th>Total</th>
                <th>Paid</th>
                <th>Due</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($returns as $return)
                @php
                    $rowPaid = (float) ($return->paid_amount ?? 0);
                    $rowDue = max(0, (float) $return->total_amount - $rowPaid);
                    $partyName = $return->return_type === 'customer'
                        ? ($return->customer_name ?: 'N/A')
                        : ($return->supplier?->name ?: 'N/A');
                @endphp
                <tr>
                    <td class="number">{{ $return->return_number }}</td>
                    <td class="date">{{ $return->return_date?->format('d/m/Y') ?: '-' }}</td>
                    <td>
                        {{ $partyName }}
                    </td>
                    <td class="reference-bill">{{ $return->source_bill_no ?: '-' }}</td>
                    <td>
                        <ul class="items">
                            @forelse ($return->returnItems as $item)
                                <li>{{ $item->medicineInventory?->medicine_name ?: 'N/A' }} — {{ $item->quantity }} × {{ number_format((float) $item->unit_price, 2) }}</li>
                            @empty
                                <li>-</li>
                            @endforelse
                        </ul>
                    </td>
                    <td class="amount">{{ number_format((float) $return->total_amount, 2) }}</td>
                    <td class="amount">{{ number_format($rowPaid, 2) }}</td>
                    <td class="amount">{{ number_format($rowDue, 2) }}</td>
                    <td class="status">
                        <div class="status-name">{{ ucfirst($return->status) }}</div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="empty">No product returns found for the selected filters.</td>
                </tr>
            @endforelse
            @if ($returns->isNotEmpty())
                <tr class="total-row">
                    <td colspan="5">GRAND TOTAL</td>
                    <td class="amount">{{ number_format($totalAmount, 2) }}</td>
                    <td class="amount">{{ number_format($paidAmount, 2) }}</td>
                    <td class="amount">{{ number_format($dueAmount, 2) }}</td>
                    <td class="status">—</td>
                </tr>
            @endif
        </tbody>
    </table>

    @if ($returns->isNotEmpty())
        <table class="summary-table">
            <tr>
                <td class="summary-label">Total Returns</td>
                <td class="amount">{{ $returns->count() }}</td>
            </tr>
            <tr>
                <td class="summary-label">Total Return Amount</td>
                <td class="amount">TK. {{ number_format($totalAmount, 2) }}</td>
            </tr>
            <tr>
                <td class="summary-label">Total Paid</td>
                <td class="amount">TK. {{ number_format($paidAmount, 2) }}</td>
            </tr>
            <tr>
                <td class="summary-label">Total Due</td>
                <td class="amount">TK. {{ number_format($dueAmount, 2) }}</td>
            </tr>
        </table>
    @endif
</body>
</html>
