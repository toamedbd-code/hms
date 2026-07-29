<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Commission Payment</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #000; margin: 0; padding: 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 7px; table-layout: fixed; }
        th, td { border: 1px solid #000; padding: 4px; vertical-align: middle; }
        thead th { background: #f5f5f5; }
        .center { text-align: center; }
        .right { text-align: right; }
        .title-wrap { text-align: center; margin-bottom: 7px; }
        .hospital-name { font-size: 15px; font-weight: 700; }
        .report-title { font-size: 12px; font-weight: 700; margin-top: 2px; }
        .meta-line { margin-top: 1px; font-size: 9px; }
        .summary-box { width: 100%; margin-top: 6px; border-collapse: collapse; }
        .summary-box td { font-size: 10px; padding: 4px; }
        .summary-label { font-weight: 700; width: 20%; }
        .summary-value { width: 30%; }
        .section-title { font-size: 11px; font-weight: 700; margin: 7px 0 4px; }
        .total-row td { font-weight: 700; background: #f0f0f0; }
        .signatures { margin-top: 18px; width: 100%; border-collapse: collapse; }
        .signatures td { border: none; text-align: center; padding-top: 18px; }
        .sign-line { border-top: 1px solid #000; padding-top: 4px; display: inline-block; min-width: 100px; }
        @media print {
            body { padding: 0; }
        }
    </style>
</head>
<body>
    @includeIf('prints.partials._header')

    <div class="title-wrap">
        <div class="hospital-name">Referral Commission Payment</div>
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
        <table>
            <thead>
                <tr>
                    <th style="width: 4%;" class="center">#</th>
                    <th style="width: 24%;">Bill No</th>
                    <th style="width: 14%;" class="center">Date</th>
                    <th style="width: 16%;" class="right">Commission</th>
                    <th style="width: 10%;" class="right">Paid</th>
                    <th style="width: 20%;">Paid Date & Time</th>
                    <th style="width: 12%;" class="right">Pending</th>
                </tr>
            </thead>
            <tbody>
                @foreach($billRows as $index => $row)
                    <tr>
                        <td class="center">{{ $index + 1 }}</td>
                        <td>{{ $row['bill_no'] }}</td>
                        <td class="center">{{ $row['date'] }}</td>
                        <td class="right">৳{{ number_format($row['commission'] ?? 0, 2) }}</td>
                        <td class="right">৳{{ number_format($row['paid'] ?? 0, 2) }}</td>
                        <td>{{ $row['paid_date_time'] }}</td>
                        <td class="right">৳{{ number_format($row['pending'] ?? 0, 2) }}</td>
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
