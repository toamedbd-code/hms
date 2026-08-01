<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>bKash Payment</title>
    <style>
        body { font-family: Inter, system-ui, sans-serif; background: radial-gradient(circle at top, #f9c1d0, #f4f5f8 34%, #eef2f7 100%); color: #111827; margin: 0; padding: 0; }
        .page { max-width: 560px; margin: 2.5rem auto; background: #ffffff; border-radius: 32px; overflow: hidden; box-shadow: 0 36px 100px rgba(15, 23, 42, 0.16); }
        .header { background: #d61260; color: #ffffff; padding: 34px 32px 22px; text-align: center; position: relative; }
        .header::after { content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 36px; background: radial-gradient(circle at center top, rgba(255,255,255,0.24), transparent 66%); }
        .header img { height: 44px; margin-bottom: 16px; filter: drop-shadow(0 10px 20px rgba(0, 0, 0, 0.12)); }
        .header h1 { margin: 0; font-size: 2rem; letter-spacing: -0.03em; line-height: 1.1; }
        .header p { margin: 14px auto 0; max-width: 420px; color: rgba(255,255,255,0.92); font-size: 0.98rem; line-height: 1.7; }
        .body { padding: 28px; }
        .badge { display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; border-radius: 999px; background: rgba(255,255,255,0.18); font-size: 0.78rem; letter-spacing: 0.08em; text-transform: uppercase; margin-bottom: 18px; }
        .summary { background: #fff0f6; border: 1px solid #f5d1e1; border-radius: 26px; padding: 24px; margin-bottom: 22px; }
        .summary .row { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .summary .label { font-size: 0.92rem; color: #7c6a7d; margin-bottom: 6px; }
        .summary .value { font-size: 1.18rem; font-weight: 700; color: #111827; }
        .amount { font-size: 1.95rem; font-weight: 800; color: #d61260; }
        .status-pill { display: inline-flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 999px; background: #fde8f1; color: #a11d53; font-size: 0.94rem; font-weight: 600; margin-top: 16px; }
        .notice { display: grid; grid-template-columns: auto 1fr; gap: 18px; background: #fff5f9; border: 1px solid #f7d3e1; border-radius: 24px; padding: 20px; margin-bottom: 24px; }
        .notice-icon { width: 46px; height: 46px; border-radius: 50%; background: #ffffff; display: grid; place-items: center; color: #d61260; font-weight: 700; font-size: 1.2rem; }
        .notice p { margin: 0; color: #5f4b69; line-height: 1.75; }
        .form-card { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 28px; padding: 26px; }
        .gateway-step + .gateway-step { margin-top: 28px; }
        .gateway-step h2 { margin: 0 0 12px; font-size: 1.05rem; color: #111827; }
        .gateway-step p { margin: 0 0 16px; color: #6b7280; font-size: 0.95rem; line-height: 1.7; }
        .input-field { width: 100%; padding: 16px 18px; border-radius: 18px; border: 1px solid #d1d5db; font-size: 1rem; color: #111827; outline: none; background: #f9fafb; }
        .input-field:focus { border-color: #d61260; box-shadow: 0 0 0 3px rgba(214,18,96,0.14); }
        .field-row { display: grid; grid-template-columns: 1fr 150px; gap: 14px; margin-top: 14px; }
        .button { display: inline-flex; justify-content: center; align-items: center; width: 100%; padding: 16px 18px; border-radius: 18px; border: none; font-size: 1rem; font-weight: 700; cursor: pointer; text-decoration: none; }
        .button-primary { background: linear-gradient(135deg, #d61260 0%, #b40f52 100%); color: #ffffff; box-shadow: 0 16px 34px rgba(214,18,96,0.2); }
        .button-primary:hover { opacity: 0.98; }
        .button-secondary { background: #f4f5f8; color: #111827; }
        .button-secondary:hover { background: #e5e7eb; }
        .otp-sent { margin-top: 12px; color: #4b5563; }
        .footer-actions { margin-top: 26px; }
        .footer-actions .button { max-width: 240px; }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <img src="https://www.bkash.com/sites/default/files/2021-06/bKash_Logo.png" alt="bKash" />
            <div class="badge">bKash Payment</div>
            <h1>Payment for Subscription</h1>
            <p>This page shows the bKash checkout flow for subscription renewal. Follow the steps to complete your payment.</p>
        </div>

        <div class="body">
            <div class="summary">
                <div class="row">
                    <div>
                        <div class="label">Payment ID</div>
                        <div class="value">#{{ $payment->id }}</div>
                    </div>
                    <div class="amount">৳ {{ number_format($payment->amount, 2) }}</div>
                </div>
                <div class="status-pill">{{ ucfirst($payment->status ?: 'Initiated') }}</div>
            </div>

            <div class="notice">
                <div class="notice-icon">i</div>
                <div>
                    <p>Use your bKash wallet mobile number and OTP to authorize this payment. This page mimics the real bKash payment gateway flow.</p>
                </div>
            </div>

            <div class="form-card">
                @php
                    $approveRoute = null;
                    if (\Illuminate\Support\Facades\Route::has('backend.payment.bkash.simulate.approve.public')) {
                        $approveRoute = route('backend.payment.bkash.simulate.approve.public', ['payment' => $payment->id]);
                    } elseif (\Illuminate\Support\Facades\Route::has('payment.bkash.simulate.approve.public')) {
                        $approveRoute = route('payment.bkash.simulate.approve.public', ['payment' => $payment->id]);
                    } else {
                        $approveRoute = url('/payment/bkash/simulate-public/' . $payment->id . '/approve');
                    }
                    $approvalToken = data_get($payment->metadata, 'approval_token');
                @endphp

                <div id="step-1" class="gateway-step">
                    <h2>1. Enter your bKash mobile</h2>
                    <p>Enter the mobile number linked to your bKash wallet.</p>
                    <input id="mobile" type="text" placeholder="01XXXXXXXXX" class="input-field" />
                    <button id="send-otp" class="button button-primary" style="margin-top: 18px;">Send OTP</button>
                </div>

                <div id="step-2" class="gateway-step" style="display:none;">
                    <h2>2. Enter OTP & PIN</h2>
                    <p>Enter the OTP you received and your bKash PIN to confirm the payment.</p>
                    <div class="field-row">
                        <input id="otp" type="text" placeholder="OTP" class="input-field" />
                        <input id="pin" type="password" maxlength="6" placeholder="bKash PIN" class="input-field" />
                    </div>

                    <form id="approve-form" method="POST" action="{{ $approveRoute }}" style="margin-top: 20px;">
                        @csrf
                        <input type="hidden" name="approval_token" value="{{ $approvalToken }}" />
                        <input type="hidden" name="otp_value" id="otp_value" value="" />
                        <input type="hidden" name="pin_value" id="pin_value" value="" />
                        <button id="confirm-payment" type="submit" class="button button-primary" disabled>Confirm Payment</button>
                    </form>
                    <p id="otp-sent" class="hint otp-sent"></p>
                </div>
            </div>

            <div class="footer-actions">
                <a href="{{ route($loginRoute) }}" class="button button-secondary">Back to Login</a>
            </div>
        </div>
    </div>

    <script>
        (function(){
            const sendBtn = document.getElementById('send-otp');
            const mobileInput = document.getElementById('mobile');
            const step1 = document.getElementById('step-1');
            const step2 = document.getElementById('step-2');
            const otpInput = document.getElementById('otp');
            const pinInput = document.getElementById('pin');
            const confirmBtn = document.getElementById('confirm-payment');
            const otpSentText = document.getElementById('otp-sent');
            const otpValueField = document.getElementById('otp_value');
            const pinValueField = document.getElementById('pin_value');
            let currentOtp = null;

            function genOtp(){
                return Math.floor(100000 + Math.random() * 900000).toString();
            }

            sendBtn.addEventListener('click', function(e){
                e.preventDefault();
                const mobile = mobileInput.value.trim();
                if (!mobile.match(/^01[0-9]{9}$/)) {
                    alert('দয়া করে সঠিক মোবাইল নম্বর দিন (01XXXXXXXXX)');
                    return;
                }
                currentOtp = genOtp();
                step1.style.display = 'none';
                step2.style.display = '';
                otpSentText.style.display = '';
                otpSentText.textContent = 'OTP: ' + currentOtp + ' (use this to confirm)';
                otpInput.focus();
            });

            function updateConfirmState(){
                const otpVal = otpInput.value.trim();
                const pinVal = pinInput.value.trim();
                confirmBtn.disabled = !(otpVal !== '' && pinVal.length >= 4 && otpVal === currentOtp);
            }

            otpInput.addEventListener('input', updateConfirmState);
            pinInput.addEventListener('input', updateConfirmState);

            document.getElementById('approve-form').addEventListener('submit', function(){
                otpValueField.value = otpInput.value.trim();
                pinValueField.value = pinInput.value.trim();
            });
        })();
    </script>
</body>
</html>
