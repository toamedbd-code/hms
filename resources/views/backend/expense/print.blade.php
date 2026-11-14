<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Expense Print</title>
    <style>
        @page { size: A4 portrait; margin: 8mm; }
        * { box-sizing: border-box; }
        html { background: #eee; }
        body {
            position: relative;
            width: 210mm;
            min-height: 297mm;
            margin: 16px auto;
            padding: 8mm;
            color: #111827;
            background: #fff;
            font-family: Arial, sans-serif;
            box-shadow: 0 1px 6px rgba(0, 0, 0, 0.25);
        }
        .card { width: 100%; margin: 0 auto; padding: 16px; }
        .hospital-name { font-weight: 700; text-align: center; }
        .hospital-address { margin-bottom: 4px; font-size: 10px; text-align: center; }
        .title { margin: 0 0 8px; font-weight: 700; text-align: center; }
        .print-date { margin: 0 0 28px; color: #4b5563; font-size: 12px; text-align: center; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; border: 1px solid #d1d5db; text-align: left; vertical-align: top; }
        th { width: 34%; font-weight: 600; }
        td { overflow-wrap: anywhere; }
        .grand-total th, .grand-total td { border-top: 2px solid #111827; font-size: 18px; font-weight: 700; }
        .grand-total td { text-align: right; white-space: nowrap; }
        .signature { display: flex; justify-content: flex-end; margin-top: 48px; }
        .signature-box { width: 190px; text-align: center; }
        .signature-line { border-top: 1px solid #111827; padding-top: 6px; font-size: 12px; }
        .print-time { margin-top: 22px; color: #4b5563; font-size: 11px; text-align: center; }

        @media screen {
            body.expense-print .footer-wrapper.institution-pad-footer {
                position: absolute !important;
                right: 0 !important;
                bottom: 0 !important;
                left: 0 !important;
            }
        }

        @media print {
            html { background: #fff; }
            body {
                width: auto;
                min-height: 0;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
            .card { padding: 16px; break-inside: avoid; page-break-inside: avoid; }
            table, tr { break-inside: avoid; page-break-inside: avoid; }
        }
    </style>
</head>
<body class="expense-print">
    <main class="card">
        @php
            $institutionName = $websetting?->company_name ?? config('app.name', 'Hospital');
            $institutionNameLength = max(1, \Illuminate\Support\Str::length($institutionName));
            $institutionNameFontSize = max(13, min(24, (int) floor(720 / $institutionNameLength)));
        @endphp
        <div class="hospital-name" style="font-size: 24px;">{{ $institutionName }}</div>
        <div class="hospital-address">{{ $websetting?->address ?? $websetting?->report_title ?? 'N/A' }}</div>
        <h1 class="title" style="font-size: {{ $institutionNameFontSize }}px;">Expense Summary</h1>
        <p class="print-date">Printed: {{ now()->format('d M, Y h:i A') }}</p>

        <table>
            <tbody>
                <tr>
                    <th>Expense Head</th>
                    <td>{{ $expense->expenseHead->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Department</th>
                    <td>{{ $expense->department->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Invoice Number</th>
                    <td>{{ $expense->bill_number ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Name</th>
                    <td>{{ $expense->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Date</th>
                    <td>{{ !empty($expense->date) ? date('d M, Y', strtotime($expense->date)) : 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Description</th>
                    <td>{{ $expense->description ?: 'N/A' }}</td>
                </tr>
                <tr class="grand-total">
                    <th>Total Expense</th>
                    <td>৳ {{ number_format((float) ($expense->amount ?? 0), 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="signature">
            <div class="signature-box">
                <div class="signature-line">Received Signature</div>
            </div>
        </div>
    </main>

    @includeIf('prints.partials._footer')

    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>
</html>
