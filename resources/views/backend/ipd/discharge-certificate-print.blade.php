<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IPD Discharge Certificate</title>
    <style>
        @font-face {
            font-family: 'NotoSansBengali';
            src: url('{{ $banglaFontPath }}') format('truetype');
            font-style: normal;
            font-weight: 400;
        }

        @page {
            size: A4;
            margin: 0;
        }

        body {
            margin: 0;
            font-family: 'NotoSansBengali', "DejaVu Sans", "Noto Sans Bengali", "Hind Siliguri", "SolaimanLipi", "Segoe UI", Arial, sans-serif;
            font-size: 12px;
            color: #111827;
        }

        .toolbar {
            margin: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #f8fafc;
        }

        .toolbar-left {
            font-size: 12px;
            color: #374151;
        }

        .toolbar-actions {
            display: flex;
            gap: 8px;
        }

        .btn {
            border: 1px solid #2563eb;
            background: #2563eb;
            color: #fff;
            padding: 6px 10px;
            font-size: 12px;
            cursor: pointer;
            border-radius: 6px;
        }

        .btn-secondary {
            border-color: #94a3b8;
            background: #ffffff;
            color: #111827;
        }

        .sheet {
            width: 100%;
        }

        .header-section {
            width: 100%;
            height: 1.2in;
            text-align: center;
        }

        .content-section {
            padding: 0 15px 110px 15px;
        }

        .header-img,
        .footer-img {
            width: 100%;
            display: block;
            height: 100%;
            object-fit: fill;
        }

        .header-placeholder,
        .footer-placeholder {
            width: 100%;
            height: 1.2in;
            display: block;
            visibility: hidden;
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
            padding-bottom: 0;
        }

        .footer-img {
            height: auto;
            object-fit: contain;
            max-height: 80px;
        }

        .hospital {
            text-align: center;
            margin: 8px 0 4px;
        }

        .hospital .name {
            font-size: 18px;
            font-weight: 800;
        }

        .hospital .meta {
            font-size: 11px;
            color: #374151;
        }

        .title {
            text-align: center;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.8px;
            margin: 10px 0 8px;
            text-transform: uppercase;
        }

        .sub-title {
            text-align: center;
            font-size: 11px;
            color: #374151;
            margin-top: -4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .info td {
            border: 1px solid #9ca3af;
            padding: 5px 7px;
            vertical-align: top;
        }

        .label {
            font-weight: 700;
            white-space: nowrap;
        }

        .id-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin: 8px 0;
        }

        .id-box {
            width: 50%;
        }

        .id-title {
            font-weight: 700;
            font-size: 11px;
            margin-bottom: 3px;
        }

        .barcode img {
            width: 210px;
            max-width: 100%;
            height: auto;
            display: block;
        }

        .section {
            margin-top: 10px;
        }

        .section-title {
            font-weight: 800;
            color: #1e3a8a;
            margin: 0 0 4px;
        }

        ul,
        ol {
            margin: 0;
            padding-left: 18px;
        }

        .para {
            line-height: 1.55;
            text-align: justify;
        }

        .footer-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 18px;
        }

        .signature-line {
            border-top: 1px solid #475569;
            width: 200px;
            text-align: center;
            padding-top: 3px;
            font-size: 11px;
        }

        .qr {
            margin-top: 10px;
            text-align: center;
        }

        .qr img {
            width: 62px;
            height: 62px;
        }

        @media print and (min-width: 149mm) {
            .header-placeholder,
            .footer-placeholder { height: 1.2in; }

            .content-section { padding: 0 15px 110px 15px; }
        }

        @media print and (max-width: 148mm), screen and (max-width: 148mm) {
            body { font-size: 11px; }

            .header-placeholder,
            .footer-placeholder { height: 1.2in; }

            .content-section { padding: 0 10px 90px 10px; }
        }

        @media print {
            .toolbar {
                display: none;
            }
        }
    </style>
</head>

<body>
    @if (empty($forPdf) || !$forPdf)
        <div class="toolbar">
            <div class="toolbar-left">Print setup: Paper A4, Margins Default, Scale 100%</div>
            <div class="toolbar-actions">
                <button type="button" class="btn" onclick="window.print()">Print Discharge Certificate</button>
                <button type="button" class="btn btn-secondary" onclick="window.close(); if (!window.closed) { history.back(); }">Close</button>
            </div>
        </div>
    @endif

    <div class="sheet">
        <div class="header-section">
            @if (!empty($headerImage))
                <img src="{{ $headerImage }}" class="header-img" alt="Header">
            @else
                <div class="header-placeholder"></div>
            @endif
        </div>

        <div class="content-section">
            <div class="title">Discharge Certificate</div>
            <div class="sub-title">Certificate No: <strong>{{ $certificateCode ?? 'N/A' }}</strong></div>

        <div class="id-row">
            <div class="id-box">
                <div class="id-title">Patient ID: {{ $patientCode ?? 'N/A' }}</div>
                @if (!empty($patientBarcodeImage))
                    <div class="barcode"><img src="{{ $patientBarcodeImage }}" alt="Patient Barcode"></div>
                @endif
            </div>
            <div class="id-box" style="text-align:right;">
                <div class="id-title">Certificate ID: {{ $certificateCode ?? 'N/A' }}</div>
                @if (!empty($certificateBarcodeImage))
                    <div class="barcode" style="display:flex;justify-content:flex-end;"><img src="{{ $certificateBarcodeImage }}" alt="Certificate Barcode"></div>
                @endif
            </div>
        </div>

        <div class="section">
            <div class="section-title">Patient & Admission Details</div>
            <table class="info">
                <tr>
                    <td class="label">Patient Name</td>
                    <td>{{ $patientName ?? 'N/A' }}</td>
                    <td class="label">Gender</td>
                    <td>{{ $patientGender ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Age</td>
                    <td>{{ $patientAge ?? 'N/A' }}</td>
                    <td class="label">Phone</td>
                    <td>{{ $patientPhone ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Address</td>
                    <td colspan="3">{{ $patientAddress ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">IPD ID</td>
                    <td>{{ $ipdpatient?->id ?? 'N/A' }}</td>
                    <td class="label">Bed</td>
                    <td>{{ $bedName ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Admission Date</td>
                    <td>{{ $admissionDate ?? 'N/A' }}</td>
                    <td class="label">Discharge Date</td>
                    <td>{{ $dischargeDate ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Consultant</td>
                    <td colspan="3">{{ $doctorName ?? 'N/A' }} {{ !empty($doctorDegree) && $doctorDegree !== 'N/A' ? '(' . $doctorDegree . ')' : '' }} {{ !empty($doctorDesignation) && $doctorDesignation !== 'N/A' ? ' - ' . $doctorDesignation : '' }}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Certificate Statement</div>
            <div class="para">
                This is to certify that <strong>{{ $patientName ?? 'N/A' }}</strong> (IPD ID: <strong>{{ $ipdpatient?->id ?? 'N/A' }}</strong>)
                was admitted on <strong>{{ $admissionDate ?? 'N/A' }}</strong> and discharged on
                <strong>{{ $dischargeDate ?? 'N/A' }}</strong> under the care of <strong>{{ $doctorName ?? 'N/A' }}</strong>.
            </div>
        </div>

        <div class="section">
            <div class="section-title">Diagnosis</div>
            @php
                $diagnosisLines = preg_split('/\r\n|\r|\n|,/', (string)($diagnosis ?? ''));
                $diagnosisLines = array_values(array_filter(array_map('trim', $diagnosisLines), fn($line) => $line !== '' && strtoupper($line) !== 'N/A'));
            @endphp
            @if (!empty($diagnosisLines))
                <ul>
                    @foreach ($diagnosisLines as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            @else
                <div>N/A</div>
            @endif
        </div>

        <div class="section">
            <div class="section-title">Medicines / Treatment (as per latest prescription)</div>
            <ol>
                @forelse (($medicineItems ?? []) as $item)
                    <li>
                        <strong>{{ $item['medicine_name'] ?? 'N/A' }}</strong>
                        @if (!empty($item['dose']) && $item['dose'] !== 'N/A')
                            , Dose: {{ $item['dose'] }}
                        @endif
                        @if (!empty($item['frequency']))
                            ({{ $item['frequency'] }})
                        @endif
                        @if (!empty($item['duration']) && $item['duration'] !== 'N/A')
                            , Duration: {{ $item['duration'] }}
                        @endif
                        @if (!empty($item['instructions']) && $item['instructions'] !== 'N/A')
                            , Instructions: {{ $item['instructions'] }}
                        @endif
                    </li>
                @empty
                    <li>No medicine items available.</li>
                @endforelse
            </ol>
        </div>

        <div class="section">
            <div class="section-title">Advice</div>
            @php
                $adviceLines = preg_split('/\r\n|\r|\n|,/', (string)($adviceNotes ?? ''));
                $adviceLines = array_values(array_filter(array_map('trim', $adviceLines), fn($line) => $line !== '' && strtoupper($line) !== 'N/A'));
            @endphp
            @if (!empty($adviceLines))
                <ul>
                    @foreach ($adviceLines as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            @else
                <div>N/A</div>
            @endif
        </div>

        <div class="section">
            <div class="section-title">Follow-up</div>
            <div>{{ !empty($followUpDate) && $followUpDate !== 'N/A' ? $followUpDate : 'As advised' }}</div>
        </div>

        <div class="footer-row">
            <div style="font-size:11px;">Printed: {{ $printedAt ?? 'N/A' }}</div>
            <div class="signature-line">Authorized Signature</div>
        </div>

        @if (!empty($qrCodeImage))
            <div class="qr">
                <img src="{{ $qrCodeImage }}" alt="QR">
            </div>
        @endif

            @php
                $footerFallbackLine = trim((string) config('app.invoice_footer_fallback_line', ''));
            @endphp

            @if (!empty($footerImage))
                @if (!empty($footerContent))
                    <div style="position:relative; z-index:11; margin-top:6px;font-size:11px;border-top:1px solid #cbd5e1;padding-top:4px;">{{ $footerContent }}</div>
                @elseif(!empty($footerFallbackLine))
                    <div style="position:relative; z-index:11; margin-top:6px;font-size:11px;border-top:1px solid #cbd5e1;padding-top:4px;">{{ $footerFallbackLine }}@if(!empty($printedAt)) , Printing Date: {{ $printedAt }}@endif</div>
                @endif
            @else
                <div class="footer-placeholder"></div>
                @if (!empty($footerContent))
                    <div style="margin-top:6px;font-size:11px;border-top:1px solid #cbd5e1;padding-top:4px;">{{ $footerContent }}</div>
                @elseif(!empty($footerFallbackLine))
                    <div style="margin-top:6px;font-size:11px;border-top:1px solid #cbd5e1;padding-top:4px;">{{ $footerFallbackLine }}@if(!empty($printedAt)) , Printing Date: {{ $printedAt }}@endif</div>
                @endif
            @endif
        </div>
    </div>
    @if ((empty($forPdf) || !$forPdf) && !empty($autoPrint))
    <script>
        window.addEventListener('load', function () {
            setTimeout(function () {
                window.print();
            }, 180);
        });
    </script>
    @endif
</body>

</html>
