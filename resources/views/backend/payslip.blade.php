<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payslip #{{ $payslip->id }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; border: 1px solid #ddd; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Company Name</h2>
            <p>Payslip for period {{ $payslip->period_start }} - {{ $payslip->period_end }}</p>
        </div>

        <table>
            <tr>
                <th>Employee</th>
                <td>{{ $payslip->employee->first_name }} {{ $payslip->employee->last_name }}</td>
                <th>Employee ID</th>
                <td>{{ $payslip->employee->employee_id }}</td>
            </tr>
            <tr>
                <th>Gross</th>
                <td class="right">{{ number_format($payslip->gross, 2) }}</td>
                <th>Deductions</th>
                <td class="right">{{ number_format($payslip->deductions, 2) }}</td>
            </tr>
            <tr>
                <th>Net</th>
                <td class="right">{{ number_format($payslip->net, 2) }}</td>
                <th>Days Present</th>
                <td class="right">{{ $payslip->days_present }}</td>
            </tr>
        </table>

        <p style="margin-top:20px">Generated at {{ now() }}</p>
    </div>
</body>
</html>
