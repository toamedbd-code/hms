<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Patient Portal Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg1: #fff7ed;
            --bg2: #ffedd5;
            --ink: #1f2937;
            --muted: #6b7280;
            --panel: #ffffff;
            --line: #e5e7eb;
            --brand: #0f766e;
            --brand-2: #0d9488;
            --danger-bg: #fef2f2;
            --danger-ink: #991b1b;
            --danger-line: #fecaca;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Manrope", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(1200px 600px at -5% -10%, #fde68a 0%, transparent 55%),
                radial-gradient(900px 500px at 110% 110%, #86efac 0%, transparent 55%),
                linear-gradient(140deg, var(--bg1), var(--bg2));
            min-height: 100vh;
        }
        .wrap {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }
        .card {
            width: 100%;
            max-width: 460px;
            border: 1px solid rgba(255, 255, 255, 0.75);
            background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(255,255,255,0.9));
            border-radius: 22px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18);
            padding: 24px;
            animation: rise .45s ease-out;
            backdrop-filter: blur(4px);
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #115e59;
            background: #ccfbf1;
            border: 1px solid #99f6e4;
            border-radius: 999px;
            padding: 6px 10px;
            margin-bottom: 14px;
        }
        h1 { margin: 0 0 6px; font-size: 28px; line-height: 1.15; }
        p { margin: 0 0 18px; color: var(--muted); font-size: 14px; }
        label {
            display: block;
            margin: 0 0 6px;
            font-size: 13px;
            font-weight: 700;
            color: #374151;
        }
        input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 11px 12px;
            margin-bottom: 14px;
            font-size: 14px;
            background: var(--panel);
            transition: border-color .15s, box-shadow .15s;
        }
        input:focus {
            outline: 0;
            border-color: #14b8a6;
            box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.16);
        }
        button {
            width: 100%;
            border: 0;
            border-radius: 12px;
            background: linear-gradient(90deg, var(--brand), var(--brand-2));
            color: #fff;
            font-weight: 800;
            padding: 11px 14px;
            font-size: 14px;
            cursor: pointer;
            transition: transform .12s ease, box-shadow .12s ease;
            box-shadow: 0 10px 20px rgba(13, 148, 136, 0.22);
        }
        button:hover { transform: translateY(-1px); }
        .err {
            background: var(--danger-bg);
            color: var(--danger-ink);
            border: 1px solid var(--danger-line);
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 12px;
            font-size: 13px;
            font-weight: 600;
        }
        @keyframes rise {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="badge">Secure Access</div>
        <h1>Patient Portal</h1>
        <p>Phone number এবং Patient ID বা Bill No দিয়ে লগইন করুন।</p>

        @if(session('error'))
            <div class="err">{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="err">{{ $errors->first() }}</div>
        @endif

        @if(session('success'))
            <div style="background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; border-radius:10px; padding:10px; margin-bottom:12px; font-size:13px; font-weight:700;">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('backend.patient.portal.login.post') }}">
            @csrf
            <input type="hidden" name="token" value="{{ old('token', $prefill['token'] ?? '') }}">

            <label for="patient_id">Patient ID / Bill No</label>
            <input id="patient_id" name="patient_id" type="text" value="{{ old('patient_id', $prefill['patient_id'] ?? '') }}" placeholder="Patient ID বা BILL2026030005" required>

            <label for="phone">Phone</label>
            <input id="phone" name="phone" type="text" value="{{ old('phone', $prefill['phone'] ?? '') }}" placeholder="01XXXXXXXXX" required>

            <button type="submit">Login</button>
        </form>
    </div>
</div>
</body>
</html>
