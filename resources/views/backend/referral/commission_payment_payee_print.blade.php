<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Referral Commission Payment</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 30px; }
        .card { max-width: 520px; margin: 0 auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; }
        .title { font-size: 18px; font-weight: bold; margin-bottom: 12px; }
        .row { margin-bottom: 10px; font-size: 14px; }
        .label { color: #374151; font-weight: bold; margin-right: 6px; }
        .value-wrap { display: inline-block; vertical-align: top; max-width: 360px; word-break: break-word; }
        .table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 13px; }
        .table th, .table td { border: 1px solid #d1d5db; padding: 6px; text-align: left; }
        .table th { background: #f3f4f6; }
        .amount { text-align: right; white-space: nowrap; }
        .print-time { margin-top: 16px; font-size: 12px; color: #6b7280; }
        @media print {
            body { background: #fff; padding: 0; }
            .card { border: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="title">Referral Commission Payment</div>
        <div class="row"><span class="label">Payee:</span>{{ $payee->name ?? 'N/A' }}</div>
        <div class="row"><span class="label">Phone:</span>{{ $payee->phone ?? 'N/A' }}</div>
        <div class="row"><span class="label">Bill List:</span><span class="value-wrap">{{ $billList ?: 'N/A' }}</span></div>
        <div class="row"><span class="label">Bill Date Range:</span>{{ $billDateRange ?? 'N/A' }}</div>
        <div class="row"><span class="label">Total কমিশন:</span>৳{{ number_format($totalCommission ?? 0, 2) }}</div>
        <div class="row"><span class="label">Already Paid:</span>৳{{ number_format($paidAmount ?? 0, 2) }}</div>
        <div class="row"><span class="label">Pending:</span>৳{{ number_format($pendingAmount ?? 0, 2) }}</div>

        @if(!empty($billRows) && count($billRows))
            <table class="table">
                <thead>
                    <tr>
                        <th>Bill No</th>
                        <th>Date</th>
                        <th class="amount">Commission</th>
                        <th class="amount">Paid</th>
                        <th class="amount">Pending</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($billRows as $row)
                        <tr>
                            <td>{{ $row['bill_no'] }}</td>
                            <td>{{ $row['date'] }}</td>
                            <td class="amount">৳{{ number_format($row['commission'] ?? 0, 2) }}</td>
                            <td class="amount">৳{{ number_format($row['paid'] ?? 0, 2) }}</td>
                            <td class="amount">৳{{ number_format($row['pending'] ?? 0, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        <div class="print-time">Printed: {{ now()->format('d-M-Y h:i A') }}</div>
    </div>
    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>
</html>
