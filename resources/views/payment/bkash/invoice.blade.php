<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ToaMed Payment Invoice</title>
    <style>
        body {
            margin: 0;
            padding: 24px;
            font-family: Arial, Helvetica, sans-serif;
            background: #f6f3ee;
            color: #1f2937;
        }
        .invoice {
            max-width: 760px;
            margin: 0 auto;
            background: white;
            border-radius: 18px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.12);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #1f5f5b, #0f3a37);
            color: white;
            padding: 28px 32px;
        }
        .header h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }
        .meta {
            padding: 28px 32px;
        }
        .row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .label {
            color: #6b7280;
            font-weight: 600;
        }
        .value {
            font-weight: 700;
        }
        .amount {
            color: #e2136e;
            font-size: 24px;
        }
        .footer {
            padding: 24px 32px 32px;
        }
        .print-btn {
            display: inline-block;
            background: #e2136e;
            color: white;
            padding: 12px 18px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
        }
        @media print {
            .print-btn { display: none; }
        }
    </style>
</head>
<body>
    <div class="invoice">
        <div class="header">
            <h1>ToaMed Payment Invoice</h1>
            <div>Subscription renewal payment receipt</div>
        </div>

        <div class="meta">
            <div class="row">
                <div class="label">Invoice No</div>
                <div class="value">{{ $invoiceNumber }}</div>
            </div>
            <div class="row">
                <div class="label">Payment ID</div>
                <div class="value">{{ $payment->provider_payment_id ?: $payment->id }}</div>
            </div>
            <div class="row">
                <div class="label">Status</div>
                <div class="value">{{ ucfirst($payment->status) }}</div>
            </div>
            <div class="row">
                <div class="label">Amount</div>
                <div class="value amount">৳ {{ number_format((float) $payment->amount, 2) }}</div>
            </div>
            <div class="row">
                <div class="label">Created At</div>
                <div class="value">{{ $payment->created_at ? $payment->created_at->format('d M Y, h:i A') : '-' }}</div>
            </div>
            <div class="row">
                <div class="label">Customer</div>
                <div class="value">ToaMed</div>
            </div>
        </div>

        <div class="footer">
            <a class="print-btn" href="javascript:window.print()">Print Invoice</a>
            <p style="margin-top: 14px; color: #6b7280;">This payment has been completed successfully and the subscription is now active.</p>
        </div>
    </div>
</body>
</html>
