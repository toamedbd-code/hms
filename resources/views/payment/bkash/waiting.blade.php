<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>bKash Payment</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: Georgia, "Times New Roman", serif;
            background:
                radial-gradient(circle at top left, rgba(226, 19, 110, 0.18), transparent 40%),
                radial-gradient(circle at bottom right, rgba(31, 95, 91, 0.16), transparent 35%),
                #f6f3ee;
            color: #1d2b28;
        }
        .card {
            width: min(460px, calc(100vw - 2rem));
            background: rgba(255,255,255,0.95);
            border: 1px solid #e4d7c8;
            border-radius: 24px;
            padding: 1.75rem;
            box-shadow: 0 24px 60px -36px rgba(20,33,31,0.55);
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            font-size: .72rem;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: #e2136e;
            font-weight: 700;
        }
        .dot { width: 10px; height: 10px; border-radius: 999px; background: #e2136e; }
        h1 { margin: .85rem 0 .4rem; font-size: 1.65rem; }
        p { margin: 0 0 1rem; line-height: 1.5; color: #4f5e5b; font-size: .95rem; }
        ol { margin: 0 0 1rem 1.1rem; color: #4f5e5b; font-size: .92rem; line-height: 1.55; }
        .actions { display: grid; gap: .7rem; }
        a.btn, button.btn {
            display: inline-flex; justify-content: center; align-items: center;
            border: 0; border-radius: 14px; padding: .85rem 1rem;
            font-weight: 700; text-decoration: none; cursor: pointer;
        }
        .primary { background: #e2136e; color: #fff; }
        .confirm { background: #1f5f5b; color: #fff; }
        .ghost { background: #fff; color: #1f5f5b; border: 1px solid #c9d7d5; }
        .status {
            margin-top: 1rem; padding: .75rem .9rem; border-radius: 12px;
            background: #fff7f9; border: 1px solid #f0c9d6; color: #7a4a5c; font-size: .88rem;
        }
        .hint { font-size: .8rem; color: #7a4a5c; margin-top: .85rem; }
        button.btn:disabled { opacity: .6; cursor: not-allowed; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge"><span class="dot"></span> bKash Sandbox Checkout</div>
        <h1>Complete your payment</h1>
        <p>Amount: <strong>৳{{ number_format((float) $payment->amount, 2) }}</strong></p>
        <ol>
            <li>bKash payment opens in this tab/window.</li>
            <li>Enter sandbox wallet, OTP and PIN.</li>
            <li>Return to the Login tab and click <strong>Confirm Payment</strong> below if this page stays open — or use the button after coming back.</li>
        </ol>
        <div class="actions">
            <a class="btn primary" id="open-bkash" href="{{ $bkashUrl }}" target="_blank" rel="noopener">
                Open bKash Payment
            </a>
            <button class="btn confirm" id="confirm-btn" type="button">
                Confirm Payment
            </button>
            <a class="btn ghost" href="{{ $loginUrl }}">Back to Login</a>
        </div>
        <div class="status" id="status">Opening bKash checkout…</div>
        <p class="hint">
            Sandbox: wallet <strong>01770618575</strong>, PIN <strong>12121</strong>, OTP <strong>123456</strong>.
            If you see “Invalid Payment State”, go back to Login and start a <strong>new</strong> Pay Monthly.
        </p>
    </div>

    <script>
        const confirmUrl = @json($confirmUrl);
        const loginUrl = @json($loginUrl);
        const bkashUrl = @json($bkashUrl);
        const statusEl = document.getElementById('status');
        const confirmBtn = document.getElementById('confirm-btn');

        // Open hosted checkout immediately (no execute until Confirm).
        const opened = window.open(bkashUrl, '_blank', 'noopener');
        if (! opened) {
            statusEl.textContent = 'Popup blocked. Click “Open bKash Payment”.';
        } else {
            statusEl.textContent = 'Finish payment in the bKash tab, then click Confirm Payment here.';
        }

        confirmBtn.addEventListener('click', async () => {
            confirmBtn.disabled = true;
            statusEl.textContent = 'Confirming with bKash…';
            try {
                const res = await fetch(confirmUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const data = await res.json();
                statusEl.textContent = data.message || 'Done';
                if (data.done) {
                    window.location.href = data.redirect || loginUrl;
                    return;
                }
            } catch (e) {
                statusEl.textContent = 'Could not confirm yet. Finish payment on bKash, then try again.';
            }
            confirmBtn.disabled = false;
        });
    </script>
</body>
</html>
