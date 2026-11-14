<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Commission Payment</title>
    <style>
        @page { size: A4 portrait; margin: 8mm; }
        html { background: #eee; }
        body {
            position: relative;
            width: 210mm;
            min-height: 297mm;
            box-sizing: border-box;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #000;
            margin: 16px auto;
            padding: 8mm;
            background: #fff;
            box-shadow: 0 1px 6px rgba(0, 0, 0, 0.25);
        }
        @media screen {
            body.referral-commission-print .footer-wrapper.institution-pad-footer {
                position: absolute !important;
                right: 0 !important;
                bottom: 0 !important;
                left: 0 !important;
            }
        }
        table { width: 100%; border-collapse: collapse; margin-bottom: 7px; table-layout: fixed; }
        th, td { border: 1px solid #000; padding: 4px; vertical-align: middle; }
        thead th { background: #f5f5f5; }
        .center { text-align: center; }
        .right { text-align: right; }
        .title-wrap { text-align: center; margin-bottom: 7px; }
        .hospital-name { font-size: 15px; font-weight: 700; }
        .hospital-address { font-size: 10px; }
        .report-title { font-size: 12px; font-weight: 700; margin-top: 2px; }
        .meta-line { margin-top: 1px; font-size: 9px; }
        .summary-box { width: 100%; margin-top: 6px; border-collapse: collapse; }
        .summary-box td { font-size: 10px; padding: 4px; }
        .summary-label { font-weight: 700; width: 20%; }
        .summary-value { width: 30%; }
        .section-title { font-size: 11px; font-weight: 700; margin: 7px 0 4px; }
        .total-row td { font-weight: 700; background: #f0f0f0; }
        .bill-list { table-layout: fixed; font-size: 7px; }
        .bill-list th, .bill-list td {
            box-sizing: border-box;
            padding: 1px;
            overflow-wrap: anywhere;
            vertical-align: middle;
        }
        .bill-list td:nth-child(2) { word-break: break-all; }
        .bill-list .amount-cell { white-space: nowrap; overflow-wrap: normal; }
        .bill-list .patient-name { overflow-wrap: anywhere; word-break: normal; }
        .bill-list .item-names { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .bill-list .date-cell { white-space: nowrap; }
        .bill-list .date-time-cell { overflow-wrap: anywhere; }
        .bill-list th { text-align: center; }
        .bill-list th:nth-child(2), .bill-list th:nth-child(3) { text-align: left; }
        .bill-list th.right, .bill-list td.right { text-align: right; }
        .bill-list tr { page-break-inside: avoid; break-inside: avoid; }
        .signatures { margin-top: 18px; width: 100%; border-collapse: collapse; }
        .signatures td { border: none; text-align: center; padding-top: 18px; }
        .sign-line { border-top: 1px solid #000; padding-top: 4px; display: inline-block; min-width: 100px; }
        @media print {
            html { background: #fff; }
            body {
                width: auto;
                min-height: 0;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
            .bill-list .screen-only { display: none !important; }
            .bill-list th, .bill-list td {
                border: 0.5px solid #555 !important;
                padding: 1px 2px !important;
                font-size: 6.5px !important;
            }
            .bill-list th:nth-child(1), .bill-list td:nth-child(1) { width: 5% !important; }
            .bill-list th:nth-child(2), .bill-list td:nth-child(2) { width: 12% !important; }
            .bill-list th:nth-child(3), .bill-list td:nth-child(3) { width: 11% !important; }
            .bill-list th:nth-child(5), .bill-list td:nth-child(5) { width: 11% !important; }
            .bill-list th:nth-child(6), .bill-list td:nth-child(6) { width: 12% !important; }
            .bill-list th:nth-child(7), .bill-list td:nth-child(7) { width: 13% !important; }
            .bill-list th:nth-child(8), .bill-list td:nth-child(8) { width: 8% !important; }
            .bill-list th:nth-child(9), .bill-list td:nth-child(9) { width: 16% !important; }
            .bill-list th:nth-child(10), .bill-list td:nth-child(10) { width: 12% !important; }
            .bill-list .date-cell { white-space: nowrap !important; }
            .bill-list .date-time-cell { white-space: normal !important; }
            .bill-list .amount-cell { white-space: nowrap !important; }
        }
    </style>
</head>
<body class="referral-commission-print">
    <div class="title-wrap">
        <div class="hospital-name">{{ $websetting?->company_name ?? config('app.name', 'Hospital') }}</div>
        <div class="hospital-address">{{ $websetting?->address ?? $websetting?->report_title ?? 'N/A' }}</div>
        <div class="report-title">Referral Commission Payment</div>
        <div class="meta-line">Payee: {{ $payee->name ?? 'N/A' }}</div>
        <div class="meta-line">Phone: {{ $payee->phone ?? 'N/A' }}</div>
        <div class="meta-line">Bill Date Range: {{ $billDateRange ?? 'N/A' }}</div>
    </div>

    <table class="summary-box">
        <tr>
            <td class="summary-label">Total Commission</td>
            <td class="summary-value right">৳{{ number_format($totalCommission ?? 0, 2) }}</td>
            <td class="summary-label">Already Paid</td>
            <td class="summary-value right">৳{{ number_format($paidAmount ?? 0, 2) }}</td>
        </tr>
        <tr>
            <td class="summary-label">Pending</td>
            <td class="summary-value right">৳{{ number_format($pendingAmount ?? 0, 2) }}</td>
            <td class="summary-label">Printed</td>
            <td class="summary-value right">{{ now()->format('d-M-Y h:i A') }}</td>
        </tr>
    </table>

    <div class="section-title">Referral Bill List</div>
    @if(!empty($billRows) && count($billRows))
        <table class="bill-list">
            <thead>
                <tr>
                    <th style="width: 4%;" class="center">S/N</th>
                    <th style="width: 11%;">Bill No</th>
                    <th style="width: 10%;">Patient Name</th>
                    <th style="width: 27%;" class="screen-only">Item Name</th>
                    <th style="width: 6%;">Date</th>
                    <th style="width: 9%;" class="right">Bill Amount</th>
                    <th style="width: 9%;" class="right">Commission</th>
                    <th style="width: 6%;" class="right">Paid</th>
                    <th style="width: 10%;">Paid Date & Time</th>
                    <th style="width: 8%;" class="right">Pending</th>
                </tr>
            </thead>
            <tbody>
                @foreach($billRows as $index => $row)
                    <tr>
                        <td class="center">{{ $index + 1 }}</td>
                        <td>{{ $row['bill_no'] }}</td>
                        <td class="patient-name">{{ $row['patient_name'] }}</td>
                        <td class="item-names screen-only" title="{{ $row['item_names'] }}">{{ $row['item_names'] }}</td>
                        <td class="center date-cell">{{ $row['date'] }}</td>
                        <td class="right amount-cell">৳{{ number_format($row['bill_amount'] ?? 0, 2) }}</td>
                        <td class="right amount-cell">৳{{ number_format($row['commission'] ?? 0, 2) }}</td>
                        <td class="right amount-cell">৳{{ number_format($row['paid'] ?? 0, 2) }}</td>
                        <td class="date-time-cell">{{ $row['paid_date_time'] }}</td>
                        <td class="right amount-cell">৳{{ number_format($row['pending'] ?? 0, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="meta-line">No referral bills found for printing.</div>
    @endif

    <table class="signatures">
        <tr>
            <td><span class="sign-line">Prepared By</span></td>
            <td><span class="sign-line">Checked By</span></td>
            <td><span class="sign-line">Received By</span></td>
        </tr>
    </table>

    @includeIf('prints.partials._footer')
    <style>
        @media print {
            body.referral-commission-print .footer-content-area.above {
                padding: 2px 2mm !important;
            }
            body.referral-commission-print .footer-content-area.above .footer-content-row {
                gap: 2px 4px !important;
                padding-right: 0 !important;
            }
            body.referral-commission-print .footer-content-area.above .footer-content-main {
                overflow: visible !important;
                padding-right: 0 !important;
                font-size: 7px !important;
            }
            body.referral-commission-print .footer-content-area.above .footer-content-main * {
                font-size: 7px !important;
            }
            body.referral-commission-print .footer-content-area.above .footer-printed-at {
                margin-left: 0 !important;
                padding-right: 0 !important;
                font-size: 7px !important;
            }
        }
    </style>

    <script>
        const shouldAutoPrint = new URLSearchParams(window.location.search).get('preview') !== '1';
        if (shouldAutoPrint) {
            window.addEventListener('load', function () {
                window.print();
            });
        }
    </script>
</body>
</html>
