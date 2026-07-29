<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IPD Prescription</title>
    <style>
        @if(!empty($banglaFontUrl))
        @font-face {
            font-family: 'NotoSansBengali';
            src: url('{{ $banglaFontUrl }}') format('truetype');
            font-style: normal;
            font-weight: 400;
        }
        @endif

        @page { size: A4; margin: 0; }

        body {
            --report-header-height: 1.5in; /* make header 1.5 inches high */
            --report-footer-height: 1.2in; /* footer area height */
            margin: 0;
            font-family: 'NotoSansBengali', "DejaVu Sans", "Noto Sans Bengali", "Hind Siliguri", "SolaimanLipi", "Segoe UI", Arial, sans-serif;
            font-size: 12px;
            color: #111827;
        }

        .toolbar {
            margin: 10px;
        }

        .btn {
            border: 1px solid #2563eb;
            background: #2563eb;
            color: #fff;
            padding: 6px 10px;
            font-size: 12px;
            cursor: pointer;
        }

        .sheet {
            padding: 0 6mm 90px 12.7mm;
        }

        .header-img,
        .footer-img {
            width: 100%;
            display: block;
            height: auto;
            object-fit: contain;
        }

        .header-placeholder,
        .footer-placeholder {
            width: 100%;
            height: 1.2in;
            display: block;
        }

        .doctor-sign-image {
            display: block;
            max-height: 50px;
            max-width: 175px;
            object-fit: contain;
            margin: 8px auto 0;
        }

        .content-section {
            padding-bottom: calc(var(--report-footer-height, 70px) + 40px);
        }

        .footer-section {
            position: fixed;
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

        .footer-img, .footer-image { width: 100%; height: auto; max-height: var(--report-footer-height, 70px); object-fit: contain; display: block; z-index: 10; }
        .footer-content { text-align: center; width: 100%; font-size:11px; color: #334155; z-index: 60; }

        .header-img.fixed,
        .header-placeholder.fixed {
            position: fixed;
            top: -2in;
            left: 0;
            right: 0;
            width: 100%;
            z-index: 50;
            height: calc(var(--report-header-height, 115px) + 2in);
            margin-top: 0;
            overflow: hidden;
        }

        .header-img {
            height: calc(var(--report-header-height, 115px) + 2in);
            object-fit: cover;
            object-position: top center;
        }

        .header-placeholder { width: 100%; height: var(--report-header-height, 115px); display: block; }
        .footer-placeholder { width: 100%; height: var(--report-footer-height, 70px); display: block; }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .meta td {
            border: 1px solid #9ca3af;
            padding: 4px 6px;
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
            width: 200px;
            max-width: 100%;
            height: auto;
            display: block;
        }

        .cols {
            display: flex;
            gap: 12px;
            margin-top: 10px;
        }

        .col-left {
            width: 32%;
        }

        .col-right {
            width: 68%;
            border-left: 1px solid #cbd5e1;
            padding-left: 10px;
        }

        .section {
            margin-bottom: 10px;
        }

        .section-title {
            overflow: visible;
            font-weight: 700;
            color: #1e3a8a;
            margin: 0 0 4px;
        }

        ul,
            position: absolute;
            left: 0;
            bottom: 0;
        ol {
            margin: 0;
            padding-left: 18px;
        }

        .rx-item {
            margin-bottom: 6px;
        }

            position: relative;
            z-index: 20; /* show above footer image */
        .footer-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 15px;
        }

        .signature-line {
            width: 100%;
            text-align: center;
            padding-top: 0;
            font-size: 11px;
        }

        .stamp-line {
            width: 100%;
            text-align: center;
            padding-top: 0;
            font-size: 11px;
        }

        .sign-wrap {
            display: inline-block;
            width: 340px;
            padding-top: 3px;
        }

        .sign-row {
            display: flex;
            width: 100%;
            align-items: flex-end;
            gap: 16px;
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

        .doctor-seal-image {
            display: block;
            max-height: 52px;
            max-width: 110px;
            object-fit: contain;
            margin: 8px auto 0;
        }

        .qr {
            margin-top: 8px;
            text-align: center;
        }

        .qr img {
            width: 58px;
            height: 58px;
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

        @media print and (min-width: 149mm) {
            .header-img,
            .footer-img {
                max-height: 80px;
            }

            .header-img {
                height: 1.2in;
                max-height: 1.2in;
            }

            .header-placeholder,
            .footer-placeholder {
                height: 1.2in;
            }

            .sheet {
                padding: 0 10px 90px;
            }
        }

        @media print and (max-width: 148mm), screen and (max-width: 148mm) {
            body {
                font-size: 11px;
            }

            .header-img,
            .footer-img {
                max-height: 58px;
            }

            .header-img {
                height: 1.2in;
                max-height: 1.2in;
            }

            .header-placeholder,
            .footer-placeholder {
                height: 1.2in;
            }

            .sheet {
                padding: 0 6px 74px;
            }
        }

        @media print {
            @page { size: A4; margin: 0mm; }

            /* Hide toolbar on print */
            .toolbar { display: none; }

            /* Reserve header/footer space via body padding using CSS variables set on the body element */
            body { padding-top: var(--report-header-height); padding-bottom: var(--report-footer-height); }

            /* Layout: reduce left/right printable margins to 0.5in and align content */
            .sheet { padding: calc(var(--report-header-height) + 12px) 0.5in calc(var(--report-footer-height) + 12px) 0.5in; }

            /* ID/barcode row: remove extra inline paddings and center items vertically */
            .id-row > div { padding: 0; vertical-align: middle; display: inline-block; }

            /* Ensure barcode images are visible and scale within the side gutters */
            .id-row img, .barcode img { max-height: 64px !important; max-width: 100% !important; height: auto !important; display: block !important; margin: 0 auto !important; }

            /* Fix header to top like IPD invoice */
            .header-image,
            .header-placeholder,
            .print-shared-header { position: fixed; top: 0; left: 0; right: 0; width: 100%; z-index: 50; }

            /* Fix footer to bottom like IPD invoice */
            .footer-image,
            .footer-placeholder,
            .footer-wrapper,
            .footer { position: fixed; bottom: 0; left: 0; right: 0; width: 100%; max-height: calc(var(--report-footer-height) + 10px); object-fit: contain; z-index: 10; display: block; }

            /* Keep footer content above the footer image */
            .footer-content { position: relative; left: 0; right: 0; width: 100%; text-align: center; z-index: 60; padding-top: 0; background: transparent; }

            /* Hide fallback placeholders (we use fixed header/footer) */
            .header-placeholder, .footer-placeholder { display: none; }

            /* Ensure content reserves space for footer so nothing overlaps */
            .content-section { padding-bottom: calc(var(--report-footer-height) * 2); }

            /* Keep header/footer images visible and contained */
            .header-img, .footer-img { object-fit: contain; display: block; }
        }
    </style>
</head>
@php
    $__ws = function_exists('get_cached_web_setting') ? get_cached_web_setting() : null;
    $__attendance = is_array($__ws?->attendance_device_options) ? $__ws->attendance_device_options : (is_string($__ws?->attendance_device_options) && trim($__ws?->attendance_device_options) !== '' ? json_decode($__ws?->attendance_device_options, true) : []);
    $__reporting = is_array($__attendance) ? data_get($__attendance, 'reporting', []) : [];
    $__settingShowHeader = array_key_exists('show_header', $__reporting) ? (bool) $__reporting['show_header'] : null;
    $__settingShowFooter = array_key_exists('show_footer', $__reporting) ? (bool) $__reporting['show_footer'] : null;
    if ($__settingShowHeader !== null || $__settingShowFooter !== null) {
        $showHeaderFooter = ($__settingShowHeader ?? true) && ($__settingShowFooter ?? true);
    } else {
        $showHeaderFooter = array_key_exists('show_header_footer', $__reporting) ? (bool) $__reporting['show_header_footer'] : (isset($showHeaderFooter) ? (bool) $showHeaderFooter : true);
    }
    $__layout = data_get($__reporting, 'layout', []);

    $reportHeaderHeight = max((int) ($header_height ?? ($reportHeaderHeight ?? $__layout['header_height'] ?? 115)), 0);
    $reportFooterHeight = max((int) ($footer_height ?? ($reportFooterHeight ?? $__layout['footer_height'] ?? 70)), 0);

    if (! $showHeaderFooter) {
        $reportHeaderHeight = 0;
        $reportFooterHeight = 0;
    }

    // Prescription print: align header/footer sizes with IPD invoice defaults to avoid page overflow
    if ($showHeaderFooter) {
        // IPD defaults: header ~115px, footer ~70px
        $reportHeaderHeight = 115;
        $reportFooterHeight = 70;
    }
@endphp

<body style="--report-header-height: {{ $reportHeaderHeight }}px; --report-footer-height: {{ $reportFooterHeight }}px;">
    @if (empty($forPdf) || !$forPdf)
        <div class="toolbar">
            <button type="button" class="btn" onclick="history.back()">Back</button>
            <button type="button" class="btn" onclick="window.print()">Print Prescription</button>
        </div>
    @endif

    @if (empty($forPdf) || !$forPdf)
        <button id="presc-back-btn-ipd" onclick="history.back()" style="position:fixed;top:8px;left:8px;z-index:99999;padding:6px 10px;background:#2563eb;color:#fff;border:none;border-radius:4px;cursor:pointer">Back</button>
        <style>@media print { #presc-back-btn-ipd { display: none !important; } }</style>
    @endif

    <div class="sheet">
        <div class="content-section">
        @include('prints.partials._header', ['showHeaderFooter' => $showHeaderFooter])

        <div class="id-row">
            <div style="width:25%; display:inline-block; vertical-align:middle; text-align:left;">
                @if (!empty($patientBarcodeImage))
                    <img src="{{ $patientBarcodeImage }}" alt="Patient Barcode" style="max-height:64px;">
                @endif
            </div>
            <div style="width:50%; display:inline-block; vertical-align:middle; text-align:center;">
                <div class="prescription-title">PRESCRIPTION</div>
            </div>
            <div style="width:25%; display:inline-block; vertical-align:middle; text-align:right;">
                @if (!empty($rxBarcodeImage))
                    <img src="{{ $rxBarcodeImage }}" alt="RX Barcode" style="max-height:64px;">
                @endif
            </div>
        </div>

        <div class="section">
            <div class="section-title">Patient Information</div>
            <table class="meta">
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
                    <td class="label">IPD ID</td>
                    <td>{{ $ipdpatient?->id ?? 'N/A' }}</td>
                    <td class="label">Bed</td>
                    <td>{{ $bedName ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Admission Date</td>
                    <td>{{ $admissionDate ?? 'N/A' }}</td>
                    <td class="label">Prescription Date</td>
                    <td>{{ $prescriptionDate ?? 'N/A' }}</td>
                </tr>
            </table>
        </div>

        <div class="cols">
            <div class="col-left">
                <div class="section">
                    <div class="section-title">Doctor</div>
                    <div>{{ $doctorName ?? 'N/A' }}</div>
                    <div>{{ $doctorDegree ?? 'N/A' }}</div>
                    <div>{{ $doctorDesignation ?? 'N/A' }}</div>
                </div>

                <div class="section">
                    <div class="section-title">Complaints</div>
                    @php
                        $complaintLines = preg_split('/\r\n|\r|\n|,/', (string)($complaints ?? ''));
                        $complaintLines = array_values(array_filter(array_map('trim', $complaintLines), fn($line) => $line !== '' && strtoupper($line) !== 'N/A'));
                    @endphp
                    @if (!empty($complaintLines))
                        <ul>
                            @foreach ($complaintLines as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    @else
                        <div>N/A</div>
                    @endif
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
                    <div class="section-title">Recommended Items</div>
                    @if (!empty($investigationItems ?? []))
                        <ol>
                            @foreach (($investigationItems ?? []) as $test)
                                <li>{{ $test }}</li>
                            @endforeach
                        </ol>
                    @else
                        <div>No items recommended.</div>
                    @endif
                </div>
            </div>

            <div class="col-right">
                <div class="section">
                    <div class="section-title">Rx (Medicines)</div>
                    <ol>
                        @forelse (($medicineItems ?? []) as $item)
                            <li class="rx-item">
                                <strong>{{ $item['medicine_name'] ?? 'N/A' }}</strong>,
                                Dose: {{ $item['dose'] ?? 'N/A' }},
                                Instructions: {{ $item['instructions'] ?? 'N/A' }}
                                @if (!empty($item['frequency']))
                                    ({{ $item['frequency'] }})
                                @endif
                                , Duration: {{ $item['duration'] ?? 'N/A' }}
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
                    <div class="section-title">Follow-up Date</div>
                    <div>{{ !empty($followUpDate) && $followUpDate !== 'N/A' ? $followUpDate : 'After 7 days' }}</div>
                </div>
            </div>
        </div>

        <div class="footer-row">
            <div style="font-size:11px;">Printed: <span class="print-datetime">{{ $printedAt ?? '' }}</span></div>
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

        @if (!empty($qrCodeImage))
            <div class="qr">
                <img src="{{ $qrCodeImage }}" alt="QR">
            </div>
        @endif

        @php
            $footerFallbackLine = trim((string) config('app.invoice_footer_fallback_line', ''));
        @endphp

        <div class="footer-placeholder"></div>
        </div>
    </div>

    @if (!empty($showHeaderFooter) && $showHeaderFooter)
        @include('prints.partials._footer')
    @endif
</body>

@if (empty($forPdf) || !$forPdf)
<script>
    function _formatPrintDate(d) {
        try {
            const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            const pad = (n) => (n < 10 ? '0'+n : n);
            const dd = pad(d.getDate());
            const mon = months[d.getMonth()];
            const yyyy = d.getFullYear();
            let hrs = d.getHours();
            const mins = pad(d.getMinutes());
            const ampm = hrs >= 12 ? 'PM' : 'AM';
            hrs = hrs % 12; hrs = hrs ? hrs : 12;
            const hh = pad(hrs);
            return `${dd}-${mon}-${yyyy} ${hh}:${mins} ${ampm}`;
        } catch (e) {
            return '';
        }
    }

    function _injectCurrentPrintDate() {
        try {
            const nowStr = _formatPrintDate(new Date());
            document.querySelectorAll('.print-datetime').forEach(el => { el.textContent = nowStr; });
        } catch (e) {
            // ignore
        }
    }

    window.addEventListener('load', function () {
        // allow more time for large/base64 images to render before print
        setTimeout(function () {
            _injectCurrentPrintDate();
            window.print();
        }, 800);
    });

    if (window.matchMedia) {
        try {
            const mql = window.matchMedia('print');
            if (typeof mql.addListener === 'function') {
                mql.addListener(function (m) { if (m.matches) _injectCurrentPrintDate(); });
            } else if (typeof mql.addEventListener === 'function') {
                mql.addEventListener('change', function (ev) { if (ev.matches) _injectCurrentPrintDate(); });
            }
        } catch (e) { /* ignore */ }
    }

    if (typeof window.onbeforeprint === 'function') {
        const prev = window.onbeforeprint;
        window.onbeforeprint = function () { _injectCurrentPrintDate(); try { prev(); } catch (e) {} };
    } else {
        window.onbeforeprint = _injectCurrentPrintDate;
    }
</script>
@endif

</html>
