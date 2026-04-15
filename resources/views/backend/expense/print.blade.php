<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Expense Print</title>
    <style>
        @page { margin: 0mm; size: A4; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 30px; }
        .card { max-width: 720px; margin: 0 auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; }
        .title { font-size: 18px; font-weight: bold; margin-bottom: 12px; }
        .row { margin-bottom: 10px; font-size: 14px; }
        .label { color: #374151; font-weight: bold; margin-right: 6px; }
        .value-wrap { display: inline-block; vertical-align: top; max-width: 520px; word-break: break-word; }
        .signature { margin-top: 24px; display: flex; justify-content: flex-end; }
        .signature-box { text-align: center; min-width: 180px; }
        .signature-line { border-top: 1px solid #000; margin-top: 24px; }
        .signature-label { font-size: 12px; margin-top: 4px; }
        .print-time { margin-top: 16px; font-size: 12px; color: #6b7280; }

        @media print and (min-width: 149mm) {
            body { padding: 0; }
            .card { max-width: none; border-radius: 0; padding: 14px 16px; }
        }

        @media print and (max-width: 148mm), screen and (max-width: 148mm) {
            body { font-size: 11px; padding: 0; }
            .card { max-width: none; border-radius: 0; padding: 10px 10px; }
            .title { font-size: 15px; }
            .row { margin-bottom: 7px; font-size: 12px; }
            .print-time { font-size: 10px; }
        }

        @media print {
            body { background: #fff; padding: 0; }
            .card { border: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="title">Expense Details</div>
        <div class="row"><span class="label">Expense Head:</span>{{ $expense->expenseHead->name ?? 'N/A' }}</div>
        <div class="row"><span class="label">Invoice Number:</span>{{ $expense->bill_number ?? 'N/A' }}</div>
        <div class="row"><span class="label">Name:</span>{{ $expense->name ?? 'N/A' }}</div>
        <div class="row"><span class="label">Amount:</span>৳{{ number_format((float) ($expense->amount ?? 0), 2) }}</div>
        <div class="row"><span class="label">Date:</span>{{ !empty($expense->date) ? date('d M, Y', strtotime($expense->date)) : 'N/A' }}</div>
        <div class="row"><span class="label">Description:</span><span class="value-wrap">{{ $expense->description ?? 'N/A' }}</span></div>

        <div class="signature">
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Received Signature</div>
            </div>
        </div>

        <div class="print-time">Printed: {{ now()->format('d-M-Y h:i A') }}</div>
    </div>
    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>
</html>
