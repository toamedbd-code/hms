<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OPD Prescription</title>
    <style>
        @font-face {
            font-family: 'NotoSansBengali';
            src: url('{{ $banglaFontPath }}') format('truetype');
            font-style: normal;
            font-weight: 400;
        }

        @page { size: A4; margin: 0; }

        body {
            margin: 0;
            font-family: 'NotoSansBengali', "DejaVu Sans", "Noto Sans Bengali", "Hind Siliguri", "SolaimanLipi", "Segoe UI", Arial, sans-serif;
            color: #1f2937;
            font-size: 12px;
            line-height: 1.35;
            background: #fff;
        }

        .sheet {
            border: 0;
            padding: 6px 6mm 92px 12.7mm;
        }

        .toolbar {
            margin-bottom: 10px;
        }

        .btn-print {
            border: 1px solid #1d4ed8;
            background: #2563eb;
            color: #fff;
            font-size: 12px;
            padding: 5px 10px;
            cursor: pointer;
        }

        .divider {
            display: none;
        }

        .section {
            margin: 0 0 5px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .split-layout {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 4px;
        }

        .split-layout td {
            vertical-align: top;
            padding: 0;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .left-pane {
            width: 30%;
            padding-right: 8px !important;
        }

        .right-pane {
            width: 70%;
            padding-left: 8px !important;
            border-left: 1px solid #cbd5e1;
        }

        .section-title {
            font-size: 12px;
            font-weight: 700;
            margin: 0 0 3px;
            text-transform: none;
            color: #1e3a8a;
        }

        .top-meta {
            display: none;
        }

        .doctor-line,
        .patient-line {
            margin: 1px 0;
            font-size: 12px;
        }

        .patient-table {
            width: 100%;
            border-collapse: collapse;
        }

        .patient-table td {
            border: 1px solid #9ca3af;
            padding: 3px 5px;
            vertical-align: top;
            font-size: 12px;
        }

        .label {
            font-weight: 700;
            white-space: nowrap;
        }

        .text-box {
            border: 1px solid #9ca3af;
            min-height: 30px;
            padding: 4px 5px;
            white-space: pre-wrap;
            background: #f9fafb;
        }

        .bullets {
            margin: 0;
            padding-left: 16px;
            font-size: 12px;
        }

        .bullets li {
            margin: 0;
        }

        .rx-list {
            margin: 0;
            padding-left: 18px;
            font-size: 12px;
            page-break-inside: auto;
            break-inside: auto;
        }

        .investigation-list {
            margin: 0;
            padding-left: 18px;
            font-size: 12px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .investigation-list li {
            margin: 2px 0;
        }

        .rx-item {
            margin-bottom: 7px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .medicine-name {
            font-weight: 700;
            color: #0f172a;
        }

        .rx-meta-line {
            margin-left: 6px;
        }

        .rx-one-line {
            margin-left: 6px;
            white-space: normal;
        }

        .footer-row {
            display: table;
            width: 100%;
            margin-top: 6px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .followup-col,
        .signature-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .printed-col {
            text-align: left;
            font-size: 11px;
            padding-top: 28px;
        }

        .signature-col {
            text-align: right;
            width: 50%;
        }

        .sign-wrap {
            display: inline-block;
            width: 340px;
            padding-top: 3px;
        }

        .sign-row {
            display: flex;
            width: 100%;
            gap: 16px;
            align-items: flex-end;
            justify-content: space-between;
        }

        .sign-box {
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: center;
            width: 160px;
            min-height: 82px;
        }

        .sign-box.seal-box {
            width: 120px;
        }

        .sign-box.signature-box {
            width: 200px;
        }

        .sign-designation {
            margin-top: 2px;
            font-size: 10.5px;
            font-weight: 600;
            color: #334155;
            text-align: center;
            max-width: 195px;
            white-space: pre-line;
            line-height: 1.2;
            display: block;
        }

        .stamp-line,
        .signature-line {
            display: inline-block;
            padding-top: 0;
            text-align: center;
            font-size: 11px;
            width: 100%;
        }

        .stamp-line {
            width: 100%;
        }

        .signature-line {
            width: 100%;
        }

        .content-section {
            padding-bottom: calc(var(--report-footer-height, 70px) + 40px);
        }

        .footer-section {
            position: static;
            width: 100%;
            text-align: center;
            padding-left: 0;
            padding-right: 0;
            min-height: var(--report-footer-height, 70px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
        }

        .footer-placeholder { width: 100%; height: var(--report-footer-height, 70px); visibility: hidden; }

        .footer-image { width: 100%; height: auto; max-height: var(--report-footer-height, 70px); object-fit: contain; display: block; z-index: 10; }

        .footer-content { text-align: center; width: 100%; font-size:11px; color: #334155; z-index: 60; }

        .header-image,
        .footer-image {
            width: 100%;
            display: block;
            margin-top: 4px;
            height: auto;
            object-fit: contain;
        }

        .header-placeholder { width: 100%; height: var(--report-header-height, 115px); margin-top: 4px; }
        .footer-placeholder { width: 100%; height: var(--report-footer-height, 70px); margin-top: 4px; }

        .doctor-sign-image {
            display: block;
            max-height: 52px;
            max-width: 180px;
            object-fit: contain;
            margin-top: 8px;
            margin-left: auto;
            margin-right: auto;
        }

        .doctor-seal-image {
            display: block;
            max-height: 52px;
            max-width: 110px;
            object-fit: contain;
            margin-top: 8px;
            margin-left: auto;
            margin-right: auto;
        }

        @media print and (min-width: 149mm) {
            .header-image,
            .footer-image {
                max-height: 84px;
            }

            .header-image {
                height: 1.2in;
            }

            .header-placeholder,
            .footer-placeholder {
                height: 1.2in;
            }
        }

        @media print and (max-width: 148mm), screen and (max-width: 148mm) {
            body {
                font-size: 11px;
            }

            .sheet {
                padding: 4px 5px 78px;
            }

            .header-image,
            .footer-image {
                max-height: 58px;
            }

            .header-image {
                height: 1.2in;
                max-height: 1.2in;
            }

            .header-placeholder,
            .footer-placeholder {
                height: 1.2in;
            }
        }

        .footer-image.fixed-footer,
        .footer-placeholder.fixed-footer,
        .header-image.fixed-header,
        .header-placeholder.fixed-header {
            position: static;
            left: auto;
            right: auto;
            top: auto;
            bottom: auto;
            margin-top: 4px;
            width: 100%;
        }

        .rx-header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 2px;
        }

        .rx-meta {
            font-size: 11px;
            text-align: right;
        }

        .qr-bottom {
            margin-top: 6px;
            display: flex;
            justify-content: center;
        }

        .qr-box {
            border: 0;
            padding: 0;
            width: 58px;
            height: 58px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .qr-box img {
            width: 52px;
            height: 52px;
            display: block;
        }

        .id-barcode-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 6px;
            width: 100%;
        }

        .id-barcode-row .left-id,
        .id-barcode-row .right-id {
            width: 50%;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .id-barcode-row .left-id {
            text-align: left;
            align-items: flex-start;
        }

        .id-barcode-row .right-id {
            text-align: right;
            align-items: flex-end;
        }

        .id-label {
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .id-barcode {
            border: 0;
            padding: 0;
            display: inline-block;
            background: #fff;
            min-height: 0;
            line-height: 0;
        }

        .id-barcode img {
            width: 190px;
            max-width: 100%;
            height: auto;
            display: block;
        }

        .id-barcode-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .id-barcode-table td {
            width: 50%;
            vertical-align: top;
        }

        .id-barcode-table td.right-id {
            text-align: right;
        }

        .prescription-title {
            display: inline-block;
            font-size: 24px;
            font-weight: 800;
            padding: 2px 8px;
            color: #000;
            letter-spacing: 1px;
            position: relative;
            z-index: 12;
        }

        .advice-plain {
            min-height: 0;
            border: 0;
            padding: 0;
            background: transparent;
        }

        @media print {
            @page {
                size: A4;
                margin: 0;
            }

            .toolbar {
                display: none;
            }

            body {
                margin: 0;
                font-size: 12px;
                line-height: 1.35;
                color: #1f2937;
                background: #ffffff;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .sheet {
                border: 0;
                padding: calc(var(--report-header-height, 115px) + 12px) 12.7mm calc(var(--report-footer-height, 70px) + 12px) 12.7mm; /* reserve space for fixed header/footer */
            }

            .id-barcode-table {
                width: 100%;
                table-layout: fixed;
                border-collapse: collapse;
            }

            .id-barcode-table td {
                padding: 0 4mm;
                vertical-align: middle;
            }

            .id-barcode-row div {
                padding: 0 4mm;
                vertical-align: middle;
            }

            .id-barcode-table img,
            .id-barcode-row img {
                max-height: 64px;
                max-width: 100%;
                height: auto;
                display: block;
            }

            .prescription-title {
                font-size: 24px;
                color: #000;
                font-weight: 700;
                margin: 0;
            }

            .patient-table td,
            .footer-content,
            .followup-col,
            .signature-col {
                font-size: 11px;
            }

            .rx-item,
            .section,
            .split-layout,
            .split-layout tr,
            .split-layout td,
            .footer-row,
            .text-box,
            .patient-table,
            .patient-table tr,
            .patient-table td {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            .qr-bottom,
            .qr-box {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            /* Fixed header at top of page in print */
            .header-image.fixed-header,
            .header-placeholder.fixed-header {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                width: 100%;
                z-index: 50;
                height: var(--report-header-height, 115px);
            }

            .header-image { height: var(--report-header-height, 115px); object-fit: cover; }

            /* Fixed footer at bottom of page in print */
            .footer-section { position: fixed; bottom: 0; left: 0; right: 0; width: 100%; z-index: 10; }

            .footer-image { max-height: var(--report-footer-height, 70px); object-fit: contain; z-index: 10; }

            .footer-content {
                position: fixed;
                bottom: calc(var(--report-footer-height, 70px) / 2);
                left: 0;
                right: 0;
                margin: 0 auto;
                font-size: 14px;
                text-align: center;
                padding: 0 12px;
                width: 100%;
                z-index: 60; /* above footer image */
                background: transparent;
            }

            .content-section { padding-bottom: calc(var(--report-footer-height, 70px) * 2); }

            /* hide placeholders in print */
            .header-placeholder.fixed-header,
            .footer-placeholder { display: none; }

            .header-image,
            .footer-image {
                object-fit: contain;
                display: block;
            }
        }
    </style>
</head>

@php
    $__ws = function_exists('get_cached_web_setting') ? get_cached_web_setting() : null;
    $__attendance = is_array($__ws?->attendance_device_options) ? $__ws->attendance_device_options : (is_string($__ws?->attendance_device_options) && trim($__ws->attendance_device_options) !== '' ? json_decode($__ws->attendance_device_options, true) : []);
    $__layout = is_array($__attendance) ? data_get($__attendance, 'reporting.layout', []) : [];
    $reportHeaderHeight = max((int) ($header_height ?? $__layout['header_height'] ?? 115), 0);
    $reportFooterHeight = max((int) ($footer_height ?? $__layout['footer_height'] ?? 70), 0);
@endphp

<body style="--report-header-height: {{ $reportHeaderHeight }}px; --report-footer-height: {{ $reportFooterHeight }}px;">
    @if (empty($forPdf) || !$forPdf)
        <div class="toolbar">
            <button type="button" class="btn-print" onclick="window.print()">Print Prescription</button>
        </div>
    @endif

    <div class="sheet">
        <div class="content-section">
        @if (!empty($headerImage))
            <img src="{{ $headerImage }}" class="header-image fixed-header" alt="Header">
            <div class="divider"></div>
        @else
            <div class="header-placeholder fixed-header"></div>
        @endif

        @if (!empty($forPdf) && $forPdf)
            <table class="id-barcode-table">
                <tr>
                    <td style="width:25%; vertical-align: middle; text-align:left;">
                        @if (!empty($patientBarcodeImage))
                            <img src="{{ $patientBarcodeImage }}" alt="Patient ID Barcode" style="max-height:64px;">
                        @endif
                    </td>
                    <td style="width:50%; vertical-align: middle; text-align:center;">
                        <div class="prescription-title">PRESCRIPTION</div>
                    </td>
                    <td style="width:25%; vertical-align: middle; text-align:right;">
                        @if (!empty($rxBarcodeImage))
                            <img src="{{ $rxBarcodeImage }}" alt="RX ID Barcode" style="max-height:64px;">
                        @endif
                    </td>
                </tr>
            </table>
        @else
            <div class="id-barcode-row">
                <div style="width:25%; display:inline-block; vertical-align:middle; text-align:left;">
                    @if (!empty($patientBarcodeImage))
                        <img src="{{ $patientBarcodeImage }}" alt="Patient ID Barcode" style="max-height:64px;">
                    @endif
                </div>
                <div style="width:50%; display:inline-block; vertical-align:middle; text-align:center;">
                    <div class="prescription-title">PRESCRIPTION</div>
                </div>
                <div style="width:25%; display:inline-block; vertical-align:middle; text-align:right;">
                    @if (!empty($rxBarcodeImage))
                        <img src="{{ $rxBarcodeImage }}" alt="RX ID Barcode" style="max-height:64px;">
                    @endif
                </div>
            </div>
        @endif

        <div class="section">
            <p class="section-title">Patient Information:</p>
            <table class="patient-table">
                <tr>
                    <td class="label">Patient Name</td>
                    <td>{{ $patientName ?? 'N/A' }}</td>
                    <td class="label">Patient ID</td>
                    <td>{{ $patientCode ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Age</td>
                    <td>{{ $patientAge ?? 'N/A' }}</td>
                    <td class="label">Gender</td>
                    <td>{{ $patientGender ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Date</td>
                    <td>{{ $prescriptionDate ?? 'N/A' }}</td>
                    <td class="label">RX ID</td>
                    <td>{{ $prescriptionCode ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">NIBP</td>
                    <td>{{ $opdpatient?->nibp ?? 'N/A' }}</td>
                        <td class="label">Final Income</td>
                    <td><b>Tk {{ number_format(525, 2) }}</b></td>
                </tr>
            </table>
        </div>

        <table class="split-layout">
            <tr>
                <td class="left-pane">
                    <div class="section">
                        <p class="section-title">Doctor Information:</p>
                        <p class="doctor-line">{{ $doctorName ?? 'N/A' }}</p>
                        <p class="doctor-line">{{ $doctorDegree ?? 'N/A' }}</p>
                        <p class="doctor-line">{{ $doctorDesignation ?? 'N/A' }}</p>
                    </div>

                    <div class="section">
                        <p class="section-title">Chief Complaints:</p>
                        @php
                            $complaintSource = trim(($chiefComplaints ?? '') . "\n" . ($symptomDescription ?? ''));
                            $complaintLines = preg_split('/\r\n|\r|\n|,/', $complaintSource);
                            $complaintLines = array_values(array_filter(array_map('trim', $complaintLines), fn($line) => $line !== '' && strtoupper($line) !== 'N/A'));
                        @endphp
                        @if(!empty($complaintLines))
                            <ul class="bullets">
                                @foreach($complaintLines as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                        @else
                            <div class="text-box">N/A</div>
                        @endif
                    </div>

                    <div class="section">
                        <p class="section-title">Diagnosis:</p>
                        @php
                            $diagnosisLines = preg_split('/\r\n|\r|\n|,/', (string)($diagnosis ?? ''));
                            $diagnosisLines = array_values(array_filter(array_map('trim', $diagnosisLines), fn($line) => $line !== '' && strtoupper($line) !== 'N/A'));
                        @endphp
                        @if(!empty($diagnosisLines))
                            <ul class="bullets">
                                @foreach($diagnosisLines as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                        @else
                            <div class="text-box">N/A</div>
                        @endif
                    </div>

                    <div class="section">
                        <p class="section-title">Investigations / Recommended Tests:</p>
                        @if(!empty($investigationItems ?? []))
                            <ol class="investigation-list">
                                @foreach(($investigationItems ?? []) as $test)
                                    <li>{{ $test }}</li>
                                @endforeach
                            </ol>
                        @else
                            <div class="text-box">No investigations recommended.</div>
                        @endif
                    </div>
                </td>

                <td class="right-pane">
                    <div class="section">
                        <div class="rx-header-row">
                            <p class="section-title" style="margin:0;">Rx (Medicines):</p>
                            <div class="rx-meta"></div>
                        </div>

                        <ol class="rx-list">
                            @forelse (($medicineItems ?? []) as $index => $item)
                                <li class="rx-item">
                                    <div class="rx-one-line">
                                        <span class="medicine-name">{{ $item['medicine_name'] ?? 'N/A' }}</span>,
                                        Dose: {{ $item['dose'] ?? 'N/A' }},
                                        Instructions: {{ $item['instructions'] ?? 'N/A' }}
                                        @if (!empty($item['frequency']))
                                            ({{ $item['frequency'] }})
                                        @endif,
                                        Duration: {{ $item['duration'] ?? 'N/A' }}
                                    </div>
                                </li>
                            @empty
                                <li class="rx-item">No medicine items available.</li>
                            @endforelse
                        </ol>
                    </div>

                    <div class="section">
                        <p class="section-title">Advice:</p>
                        @php
                            $adviceLines = preg_split('/\r\n|\r|\n|,/', (string)($adviceNotes ?? ''));
                            $adviceLines = array_values(array_filter(array_map('trim', $adviceLines), fn($line) => $line !== '' && strtoupper($line) !== 'N/A'));
                        @endphp
                        @if(!empty($adviceLines))
                            <ul class="bullets">
                                @foreach($adviceLines as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                        @else
                            <div class="advice-plain">N/A</div>
                        @endif
                    </div>

                    <div class="section">
                        <p class="section-title">Follow-up Date:</p>
                        <div>{{ !empty($followUpDate) && $followUpDate !== 'N/A' ? $followUpDate : 'After 7 days' }}</div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="footer-row">
            <div class="followup-col printed-col">
                Printed: {{ $printedAt ?? 'N/A' }}
            </div>
            <div class="signature-col">
                <div class="sign-wrap">
                    <div class="sign-row">
                        <span class="sign-box seal-box">
                            <span class="stamp-line">
                                @if(!empty($doctorSealImage))
                                    <img src="{{ $doctorSealImage }}" alt="Doctor Seal" class="doctor-seal-image">
                                @else
                                    Seal
                                @endif
                            </span>
                        </span>
                        <span class="sign-box signature-box">
                        <span class="signature-line">
                            @if(!empty($doctorSignatureImage))
                                <img src="{{ $doctorSignatureImage }}" alt="Doctor Signature" class="doctor-sign-image">
                            @else
                                Doctor Signature
                            @endif
                        </span>
                        <span class="sign-designation">{{ trim((string) ($doctorDesignation ?? '')) !== '' ? $doctorDesignation : 'Consultant' }}</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        @if (!empty($qrCodeImage))
            <div class="qr-bottom">
                <div class="qr-box">
                    <img src="{{ $qrCodeImage }}" alt="QR">
                </div>
            </div>
        @endif

        <div class="footer-placeholder"></div>
        </div>
    </div>

    <div class="footer-section">
        @php
            $invoiceFooterFallback = trim((string) ($invoiceFooterFallback ?? config('app.invoice_footer_fallback_line', '')));
        @endphp

        @if (!empty($footerImage))
            @if (!empty($footerContent))
                <div class="footer-content">{!! $footerContent !!}</div>
            @elseif(!empty($invoiceFooterFallback))
                <div class="footer-content">{{ $invoiceFooterFallback }}@if(!empty($printedAt)) , Printing Date: {{ $printedAt }}@endif</div>
            @endif
            <img src="{{ $footerImage }}" class="footer-image" alt="Footer">
        @else
            @if (!empty($footerContent))
                <div class="footer-content">{!! $footerContent !!}</div>
            @elseif(!empty($invoiceFooterFallback))
                <div class="footer-content">{{ $invoiceFooterFallback }}@if(!empty($printedAt)) , Printing Date: {{ $printedAt }}@endif</div>
            @else
                <div class="footer-placeholder"></div>
            @endif
        @endif
    </div>
</body>

@if (empty($forPdf) || !$forPdf)
<script>
    window.addEventListener('load', function () {
        setTimeout(function () {
            window.print();
        }, 180);
    });
</script>
@endif

</html>
