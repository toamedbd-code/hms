<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Patient Portal Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #111827;
            --muted: #6b7280;
            --line: #e5e7eb;
            --panel: #ffffff;
            --brand: #065f46;
            --danger: #dc2626;
            --ok: #047857;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Manrope", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(1100px 520px at -10% -15%, #fef3c7 0%, transparent 55%),
                radial-gradient(900px 520px at 110% 110%, #d1fae5 0%, transparent 55%),
                linear-gradient(160deg, #f8fafc, #f1f5f9);
        }
        .top {
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 1px solid rgba(255,255,255,0.6);
            background: linear-gradient(90deg, rgba(6,95,70,0.95), rgba(15,118,110,0.95));
            backdrop-filter: blur(5px);
            color: #fff;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .top h1 { font-size: 18px; margin: 0; font-weight: 800; }
        .top small { opacity: 0.9; }
        .top form { margin: 0; }
        .top button {
            border: 0;
            background: #fff;
            color: var(--danger);
            border-radius: 10px;
            padding: 8px 12px;
            font-weight: 800;
            cursor: pointer;
        }
        .container { max-width: 1240px; margin: 16px auto; padding: 0 14px 28px; }
        .hero {
            background: linear-gradient(120deg, #ffffff, #f8fafc);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 16px;
            margin-bottom: 14px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
            animation: rise .4s ease-out;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        .card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 12px;
        }
        .card .label { font-size: 12px; color: var(--muted); font-weight: 700; letter-spacing: 0.02em; }
        .card .value { font-size: 20px; font-weight: 800; margin-top: 6px; }
        .grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
        }
        .block {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05);
        }
        .block h2 {
            margin: 0;
            padding: 12px 14px;
            font-size: 15px;
            font-weight: 800;
            border-bottom: 1px solid var(--line);
            background: #f8fafc;
        }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 620px; }
        th, td {
            padding: 10px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            font-size: 13px;
            white-space: nowrap;
        }
        th { color: #374151; background: #f8fafc; font-weight: 800; }
        tr:last-child td { border-bottom: 0; }
        .text-ok { color: var(--ok); font-weight: 700; }
        .text-due { color: #b45309; font-weight: 700; }
        .text-muted { color: var(--muted); }
        @media (max-width: 768px) {
            .top { align-items: flex-start; flex-direction: column; }
            .top button { width: 100%; }
            .hero { padding: 14px; }
        }
        @keyframes rise {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
<div class="top">
    <div>
        <h1>Patient Portal Dashboard</h1>
        <small>স্বাগতম, {{ $patient->name }}</small>
    </div>
    <form method="POST" action="{{ route('backend.patient.portal.logout') }}">
        @csrf
        <button type="submit">Logout</button>
    </form>
</div>

<div class="container">
    @php
        $hasOutstandingDue = (float)($billingSummary->total_due ?? 0) > 0;
    @endphp

    @if(session('error'))
        <div style="margin-bottom:12px; border:1px solid #fecaca; background:#fef2f2; color:#991b1b; border-radius:12px; padding:10px 12px; font-size:13px; font-weight:700;">
            {{ session('error') }}
        </div>
    @endif

    @if(session('success'))
        <div style="margin-bottom:12px; border:1px solid #bbf7d0; background:#f0fdf4; color:#166534; border-radius:12px; padding:10px 12px; font-size:13px; font-weight:700;">
            {{ session('success') }}
        </div>
    @endif

    <div class="hero">
        <div style="font-size:14px; color:#475569;">Patient ID: #{{ $patient->id }} | Phone: {{ $patient->phone }}</div>
        <div style="margin-top:6px; font-weight:800; font-size:18px;">Health & Billing Snapshot</div>

        @if($hasOutstandingDue)
            <div style="margin-top:12px; border:1px solid #fde68a; background:#fffbeb; color:#92400e; border-radius:12px; padding:10px 12px; font-size:13px; font-weight:700;">
                আপনার due আছে (Tk {{ number_format((float)($billingSummary->total_due ?? 0), 2) }})।
                Report download unlock করতে আগে payment complete করুন।
                <a href="{{ route('backend.patient.portal.payment') }}" style="margin-left:8px; color:#7c2d12; text-decoration:underline; font-weight:800;">Pay Now</a>
            </div>
        @endif

        <form method="GET" action="{{ route('backend.patient.portal.dashboard') }}" style="margin-top:14px; display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:10px; align-items:end;">
            <div>
                <label style="display:block; margin-bottom:6px; font-size:12px; font-weight:700; color:#4b5563;">From Date</label>
                <input type="date" name="from_date" value="{{ $filters['from_date'] ?? '' }}" style="width:100%; border:1px solid #d1d5db; border-radius:10px; padding:9px 10px;" />
            </div>
            <div>
                <label style="display:block; margin-bottom:6px; font-size:12px; font-weight:700; color:#4b5563;">To Date</label>
                <input type="date" name="to_date" value="{{ $filters['to_date'] ?? '' }}" style="width:100%; border:1px solid #d1d5db; border-radius:10px; padding:9px 10px;" />
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button type="submit" style="border:0; background:#0f766e; color:#fff; border-radius:10px; padding:9px 12px; font-weight:700; cursor:pointer;">Apply Filter</button>
                <a href="{{ route('backend.patient.portal.dashboard') }}" style="display:inline-flex; align-items:center; justify-content:center; border:1px solid #d1d5db; background:#fff; color:#374151; border-radius:10px; padding:9px 12px; font-weight:700; text-decoration:none;">Reset</a>
                <a href="{{ route('backend.patient.portal.dashboard', array_merge(request()->query(), ['export' => 'csv'])) }}" style="display:inline-flex; align-items:center; justify-content:center; border:0; background:#1d4ed8; color:#fff; border-radius:10px; padding:9px 12px; font-weight:700; text-decoration:none;">Export CSV</a>
            </div>
        </form>
    </div>

    <div class="summary">
        <div class="card">
            <div class="label">Patient Name</div>
            <div class="value">{{ $patient->name }}</div>
        </div>
        <div class="card">
            <div class="label">Phone</div>
            <div class="value">{{ $patient->phone }}</div>
        </div>
        <div class="card">
            <div class="label">Appointments</div>
            <div class="value">{{ $appointments->count() }}</div>
        </div>
        <div class="card">
            <div class="label">Bills</div>
            <div class="value">{{ $billings->count() }}</div>
        </div>
        <div class="card">
            <div class="label">Total Bill</div>
            <div class="value">{{ number_format((float)($billingSummary->total_amount ?? 0), 2) }}</div>
        </div>
        <div class="card">
            <div class="label">Total Due</div>
            <div class="value text-due">{{ number_format((float)($billingSummary->total_due ?? 0), 2) }}</div>
        </div>
    </div>

    <div class="grid">
        <section class="block">
            <h2>Recent Appointments</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Slot</th>
                        <th>Status</th>
                        <th>Fee</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($appointments as $item)
                        <tr>
                            <td>{{ $item->id }}</td>
                            <td>{{ $item->appoinment_date }}</td>
                            <td>{{ $item->slot }}</td>
                            <td>{{ $item->appoinment_status }}</td>
                            <td>{{ number_format((float)$item->doctor_fee, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-muted" colspan="5">No data found</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="block">
            <h2>Recent Bills</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Bill</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Due</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($billings as $item)
                        <tr>
                            <td>{{ $item->bill_number }}</td>
                            <td>{{ number_format((float)$item->total, 2) }}</td>
                            <td class="text-ok">{{ number_format((float)$item->paid_amt, 2) }}</td>
                            <td class="text-due">{{ number_format((float)$item->due_amount, 2) }}</td>
                            <td>{{ $item->payment_status }}</td>
                            <td>
                                @if((float)$item->due_amount > 0 || $hasOutstandingDue)
                                    <a href="{{ route('backend.patient.portal.payment', ['billing' => $item->id]) }}" style="display:inline-flex; align-items:center; justify-content:center; border:1px solid #f59e0b; background:#fffbeb; color:#92400e; border-radius:8px; padding:6px 10px; font-size:12px; font-weight:800; text-decoration:none;">Pay Now</a>
                                @else
                                    <a href="{{ route('backend.patient.portal.report.download', ['billing' => $item->id]) }}" style="display:inline-flex; align-items:center; justify-content:center; border:1px solid #10b981; background:#ecfdf5; color:#065f46; border-radius:8px; padding:6px 10px; font-size:12px; font-weight:800; text-decoration:none;">Download Report</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td class="text-muted" colspan="6">No data found</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="block">
            <h2>Recent OPD Records</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Visit Ref</th>
                        <th>Problem / Note</th>
                        <th>Symptom Type</th>
                        <th>Date</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($opdVisits as $item)
                        <tr>
                            <td>{{ $item->opd_no }}</td>
                            <td>{{ $item->problem }}</td>
                            <td>{{ $item->symptoms_type }}</td>
                            <td>{{ $item->created_at }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-muted" colspan="4">No data found</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="block">
            <h2>Recent IPD Records</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Admission Ref</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Created At</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($ipdAdmissions as $item)
                        <tr>
                            <td>{{ $item->ipd_no }}</td>
                            <td>{{ $item->discharge_date }}</td>
                            <td>{{ $item->discharge_status }}</td>
                            <td>{{ $item->created_at }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-muted" colspan="4">No data found</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
</body>
</html>
