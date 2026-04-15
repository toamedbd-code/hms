<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Patient Portal Payment</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #111827;
            --muted: #6b7280;
            --line: #e5e7eb;
            --card: #ffffff;
            --brand: #0f766e;
            --warn-bg: #fffbeb;
            --warn-line: #fde68a;
            --warn-ink: #92400e;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Manrope", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(1000px 520px at -10% -10%, #fef3c7 0%, transparent 55%),
                radial-gradient(900px 500px at 110% 110%, #d1fae5 0%, transparent 55%),
                linear-gradient(160deg, #f8fafc, #eef2ff);
        }
        .wrap { max-width: 980px; margin: 20px auto; padding: 0 14px 24px; }
        .card {
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--card);
            padding: 16px;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
            margin-bottom: 12px;
        }
        .warn {
            border: 1px solid var(--warn-line);
            background: var(--warn-bg);
            color: var(--warn-ink);
            border-radius: 12px;
            padding: 10px 12px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        .title { margin: 0 0 8px; font-size: 22px; font-weight: 800; }
        .sub { margin: 0 0 12px; color: var(--muted); font-size: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            border-bottom: 1px solid var(--line);
            padding: 10px;
            text-align: left;
            font-size: 13px;
        }
        th { background: #f8fafc; font-size: 12px; font-weight: 800; color: #334155; }
        tr:last-child td { border-bottom: 0; }
        .amount { font-weight: 800; color: #92400e; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
            border: 1px solid transparent;
        }
        .btn-primary {
            background: linear-gradient(90deg, #0f766e, #0d9488);
            color: #ffffff;
            box-shadow: 0 10px 20px rgba(13, 148, 136, 0.2);
        }
        .btn-muted {
            background: #ffffff;
            border-color: #d1d5db;
            color: #374151;
        }
        .gateway-box {
            border: 1px dashed #d1d5db;
            border-radius: 12px;
            padding: 12px;
            margin-top: 12px;
            font-size: 13px;
            color: #475569;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1 class="title">Due Payment Gateway</h1>
        <p class="sub">Patient: {{ $patient->name }} (ID: {{ $patient->id }}) | Phone: {{ $patient->phone }}</p>

        <div class="warn">
            আপনার report download unlock করতে due payment complete করুন।
        </div>

        <table>
            <thead>
            <tr>
                <th>Bill No</th>
                <th>Date</th>
                <th>Due Amount</th>
            </tr>
            </thead>
            <tbody>
            @foreach($dueBills as $bill)
                <tr>
                    <td>{{ $bill->bill_number }}</td>
                    <td>{{ optional($bill->created_at)->format('d-M-Y h:i A') }}</td>
                    <td class="amount">Tk {{ number_format((float)$bill->due_amount, 2) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="2" style="font-weight: 800;">Total Due</td>
                <td class="amount">Tk {{ number_format((float)$totalDueAmount, 2) }}</td>
            </tr>
            </tbody>
        </table>

        <div class="gateway-box">
            @if($selectedBill)
                Selected Bill: {{ $selectedBill->bill_number }} | Payable Now: <strong>Tk {{ number_format((float)$payAmount, 2) }}</strong>
            @else
                Payable Now (Full Due): <strong>Tk {{ number_format((float)$payAmount, 2) }}</strong>
            @endif
        </div>

        <div class="actions">
            @if($paymentGatewayUrl !== '')
                <a class="btn btn-primary" href="{{ $paymentGatewayUrl }}" target="_blank" rel="noopener noreferrer">Proceed to Payment Gateway</a>
            @else
                <span class="btn btn-muted">Payment gateway URL is not configured yet</span>
            @endif
            <a class="btn btn-muted" href="{{ route('backend.patient.portal.dashboard') }}">Back to Dashboard</a>
        </div>

        @if($personalBkashNumber !== '' || $personalNagadNumber !== '')
            <div class="gateway-box" style="margin-top:14px; border-style:solid;">
                <div style="font-weight:800; color:#0f172a; margin-bottom:6px;">Personal Payment Numbers</div>
                @if($personalBkashNumber !== '')
                    <div><strong>bKash:</strong> {{ $personalBkashNumber }}</div>
                @endif
                @if($personalNagadNumber !== '')
                    <div style="margin-top:4px;"><strong>Nagad:</strong> {{ $personalNagadNumber }}</div>
                @endif
                <div style="margin-top:8px; font-size:12px; color:#64748b;">
                    পার্সোনাল নাম্বারে পেমেন্ট করলে transaction ID সহ admin-এর সাথে যোগাযোগ করুন।
                </div>
            </div>
        @endif
    </div>
</div>
</body>
</html>
