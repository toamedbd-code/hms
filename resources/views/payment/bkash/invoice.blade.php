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
        .close-btn {
            background: #1f5f5b;
        }
        .login-btn {
            display: inline-block;
            background: #6b7280;
            color: white;
            padding: 12px 18px;
            border-radius: 10px;
            font-weight: 700;
            text-decoration: none;
            margin-left: 10px;
        }
        @media print {
            .print-btn, .close-btn, .login-btn, .no-print { display: none !important; }
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
                <div class="label">Transaction ID</div>
                <div class="value">{{ $transactionId ?: '-' }}</div>
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
            <button class="print-btn" onclick="printInvoice()" style="border:none; cursor:pointer;">🖨️ Print Invoice</button>
            <button class="close-btn" onclick="finishInvoice()" style="display:inline-block; color:white; padding:12px 18px; border-radius:10px; font-weight:700; border:none; cursor:pointer; margin-left:10px;">✕ Close Tab</button>
            <a id="login-link" class="login-btn no-print" href="{{ $loginUrl ?? url('/login') }}">← Back to Login</a>
            <p style="margin-top: 14px; color: #6b7280;">Payment completed successfully. Subscription is now active.</p>
        </div>
    </div>

    <script>
        var loginUrl = document.getElementById('login-link').href;

        var invoiceFinalized = false;
        var printStarted = false;

        function printInvoice() {
            printStarted = true;
            window.print();
        }

        window.onafterprint = function () {
            if (printStarted) {
                finishInvoice();
            }
        };

        window.addEventListener('focus', function () {
            if (printStarted && !invoiceFinalized) {
                window.setTimeout(finishInvoice, 300);
            }
        });

        if (window.matchMedia) {
            window.matchMedia('print').addEventListener('change', function (event) {
                if (printStarted && !event.matches) {
                    finishInvoice();
                }
            });
        }

        function finishInvoice() {
            if (invoiceFinalized) {
                return;
            }

            invoiceFinalized = true;
            try {
                if (window.opener && !window.opener.closed) {
                    var refreshUrl = new URL(loginUrl, window.location.origin);
                    refreshUrl.searchParams.set('subscription_refresh', Date.now().toString());
                    window.opener.location.replace(refreshUrl.toString());
                }
            } catch (e) {}

            try { window.close(); } catch (e) {}

            setTimeout(function () {
                if (!window.closed) {
                    var fallbackUrl = new URL(loginUrl, window.location.origin);
                    fallbackUrl.searchParams.set('subscription_refresh', Date.now().toString());
                    window.location.replace(fallbackUrl.toString());
                }
            }, 600);
        }
    </script>
</body>
</html>
