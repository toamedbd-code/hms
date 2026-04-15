<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Birth/Death Certificate - {{ $record->name ?? 'Record' }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { size: A4; margin: 0; }
        body {
            font-family: Arial, sans-serif;
            color: #1f2937;
            margin: 0;
            background: #f3f4f6;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .toolbar {
            max-width: 210mm;
            margin: 14px auto 10px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 10px;
            background: #ffffff;
            border: 1px solid #d1d5db;
        }
        .toolbar-title { font-size: 13px; color: #374151; }
        .toolbar-actions { display: flex; gap: 8px; }
        .btn {
            border: 1px solid #9ca3af;
            border-radius: 8px;
            background: #fff;
            color: #111827;
            font-size: 12px;
            font-weight: 600;
            padding: 8px 12px;
            cursor: pointer;
        }
        .btn-primary {
            background: #0f766e;
            color: #fff;
            border-color: #0f766e;
        }
        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 18px auto;
            background: #fff;
            position: relative;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
        }
        .header-section {
            width: 100%;
            height: 1.2in;
            text-align: center;
        }
        .header-image { width: 100%; height: 100%; object-fit: fill; display: block; }
        .header-placeholder { width: 100%; height: 1.2in; visibility: hidden; }
        .content {
            padding: 16px 22px 125px 22px;
        }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; gap: 12px; }
        .title { font-size: 24px; font-weight: 700; margin: 0; }
        .subtitle { font-size: 14px; color: #4b5563; margin-top: 4px; }
        .meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            font-size: 13px;
        }
        .meta-row .left { text-align: left; }
        .meta-row .right { text-align: right; }
        .certificate-title {
            margin: 4px 0 14px;
            font-size: 30px;
            font-weight: 800;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            text-align: center;
            color: #0f172a;
            background: #f8fafc;
            border: 1px solid #dbeafe;
            border-radius: 8px;
            padding: 8px 10px;
        }
        .section-title {
            margin: 16px 0 12px;
            font-size: 17px;
            font-weight: 700;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 7px;
        }
        .grid { display: grid; grid-template-columns: 190px 1fr; gap: 9px 12px; }
        .label { font-weight: 600; color: #374151; }
        .value { color: #111827; white-space: pre-line; }
        .signature-row { margin-top: 36px; display: flex; justify-content: flex-end; }
        .sign {
            border-top: 1px dashed #9ca3af;
            width: 220px;
            text-align: center;
            padding-top: 8px;
            color: #4b5563;
            font-size: 12px;
        }
        .footer-section {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            text-align: center;
            min-height: 70px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
        }
        .footer-image { width: 100%; max-height: 80px; object-fit: contain; display: block; }
        .footer-text {
            font-size: 12px;
            color: #4b5563;
            line-height: 1.4;
            padding: 6px 18px 10px 18px;
            white-space: pre-line;
        }
        .footer-date-time {
            font-size: 12px;
            color: #6b7280;
            width: 100%;
            text-align: right;
            padding: 0 18px 6px 18px;
        }
        @media print {
            body { margin: 0; background: #fff; }
            .toolbar { display: none !important; }
            .sheet {
                width: auto;
                min-height: 297mm;
                margin: 0;
                box-shadow: none;
            }
        }
        @media screen and (max-width: 900px) {
            .toolbar { margin: 10px; }
            .sheet {
                width: 100%;
                min-height: auto;
                margin: 0;
            }
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div class="toolbar-title">Print setup: Paper A4, Margins Default, Scale 100%</div>
        <div class="toolbar-actions">
            <button type="button" class="btn btn-primary" onclick="window.print()">Print Birth/Death Certificate</button>
            <button type="button" class="btn" onclick="window.close()">Close</button>
        </div>
    </div>

    <div class="sheet">
        <div class="header-section">
            @if(!empty($header_image))
                <img src="{{ $header_image }}" alt="Header" class="header-image">
            @else
                <div class="header-placeholder"></div>
            @endif
        </div>

        <div class="content">
            <div class="meta-row">
                <div class="left"><strong>Certificate No:</strong> BDR-{{ str_pad((string) $record->id, 6, '0', STR_PAD_LEFT) }}</div>
                <div class="right"><strong>Issue Date:</strong> {{ now()->format('d M Y') }}</div>
            </div>

            <div class="certificate-title">{{ ($record->record_type ?? 'Birth/Death') }} Certificate</div>

            <div class="grid">
                <div class="label">Name</div><div class="value">{{ $record->name ?? '-' }}</div>
                <div class="label">Record Type</div><div class="value">{{ $record->record_type ?? '-' }}</div>
                <div class="label">Record Date</div><div class="value">{{ $record->record_date ? \Illuminate\Support\Carbon::parse($record->record_date)->format('d M Y') : '-' }}</div>
                <div class="label">Guardian Name</div><div class="value">{{ $record->guardian_name ?? '-' }}</div>
                <div class="label">Gender</div><div class="value">{{ $record->gender ?? '-' }}</div>
                <div class="label">Email</div><div class="value">{{ $record->email ?? '-' }}</div>
                <div class="label">Phone</div><div class="value">{{ $record->phone ?? '-' }}</div>
                <div class="label">Address</div><div class="value">{{ $record->address ?? '-' }}</div>
                <div class="label">Notes</div><div class="value">{{ $record->notes ?? '-' }}</div>
            </div>

            <div class="signature-row">
                <div class="sign">Authorized Signature</div>
            </div>
        </div>

        <div class="footer-section">
            <div class="footer-date-time">Printed: {{ now()->format('d M Y h:i A') }}</div>
            @if(!empty($footer_content))
                <div class="footer-text">{{ $footer_content }}</div>
            @elseif(!empty($footer_image))
                <img src="{{ $footer_image }}" alt="Footer" class="footer-image">
            @endif
        </div>
    </div>

    @if(!empty($autoPrint))
    <script>
        window.addEventListener('load', function () {
            setTimeout(function () {
                window.print();
            }, 150);
        });
    </script>
    @endif
</body>
</html>
