<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IPD Prescription</title>
    <style>
        <?php if(!empty($banglaFontUrl)): ?>
        @font-face {
            font-family: 'NotoSansBengali';
            src: url('<?php echo e($banglaFontUrl); ?>') format('truetype');
            font-style: normal;
            font-weight: 400;
        }
        <?php endif; ?>

        @page { size: A4; margin: 0; }

        body {
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
            position: static;
            left: auto;
            right: auto;
            top: auto;
            width: 100%;
            margin-top: 4px;
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
            font-weight: 700;
            color: #1e3a8a;
            margin: 0 0 4px;
        }

        ul,
        ol {
            margin: 0;
            padding-left: 18px;
        }

        .rx-item {
            margin-bottom: 6px;
        }

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
            @page {
                size: A4;
                margin: 0;
            }

            .toolbar {
                display: none;
            }

            .sheet {
                padding: calc(var(--report-header-height, 115px) + 12px) 12.7mm calc(var(--report-footer-height, 70px) + 12px) 12.7mm; /* reserve space for fixed header/footer */
            }

            .id-row > div {
                padding: 0 4mm;
                vertical-align: middle;
            }

            .id-row img {
                max-height: 64px;
                max-width: 100%;
                height: auto;
                display: block;
            }

            .prescription-title {
                margin: 0;
                font-size: 24px;
                color: #000;
                font-weight: 800;
            }

            /* Fixed header at top */
            .header-img.fixed,
            .header-placeholder.fixed {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                width: 100%;
                z-index: 50;
                height: var(--report-header-height, 115px);
            }

            .header-img { height: var(--report-header-height, 115px); object-fit: cover; }

            /* Fixed footer at bottom */
            .footer-section { position: fixed; bottom: 0; left: 0; right: 0; width: 100%; z-index: 10; }

            .footer-img { max-height: var(--report-footer-height, 70px); object-fit: contain; z-index: 10; }

            .footer-content {
                text-align: center;
                width: 100%;
                font-size: 14px;
                padding: 0 12px;
                margin: 0;
                background: transparent;
            }

            .content-section { padding-bottom: calc(var(--report-footer-height, 70px) * 2); }

            .header-placeholder.fixed,
            .footer-placeholder { display: none; }

            .header-img,
            .footer-img {
                object-fit: contain;
                display: block;
            }
        }
    </style>
</head>
<?php
    $__ws = function_exists('get_cached_web_setting') ? get_cached_web_setting() : null;
    $__attendance = is_array($__ws?->attendance_device_options) ? $__ws->attendance_device_options : (is_string($__ws?->attendance_device_options) && trim($__ws->attendance_device_options) !== '' ? json_decode($__ws->attendance_device_options, true) : []);
    $__layout = is_array($__attendance) ? data_get($__attendance, 'reporting.layout', []) : [];
    $reportHeaderHeight = max((int) ($header_height ?? $__layout['header_height'] ?? 115), 0);
    $reportFooterHeight = max((int) ($footer_height ?? $__layout['footer_height'] ?? 70), 0);
?>

<body style="--report-header-height: <?php echo e($reportHeaderHeight); ?>px; --report-footer-height: <?php echo e($reportFooterHeight); ?>px;">
    <?php if(empty($forPdf) || !$forPdf): ?>
        <div class="toolbar">
            <button type="button" class="btn" onclick="window.print()">Print Prescription</button>
        </div>
    <?php endif; ?>

    <div class="sheet">
        <div class="content-section">
        <?php echo $__env->make('prints.partials._header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <div class="id-row">
            <div style="width:25%; display:inline-block; vertical-align:middle; text-align:left;">
                <?php if(!empty($patientBarcodeImage)): ?>
                    <img src="<?php echo e($patientBarcodeImage); ?>" alt="Patient Barcode" style="max-height:64px;">
                <?php endif; ?>
            </div>
            <div style="width:50%; display:inline-block; vertical-align:middle; text-align:center;">
                <div class="prescription-title">PRESCRIPTION</div>
            </div>
            <div style="width:25%; display:inline-block; vertical-align:middle; text-align:right;">
                <?php if(!empty($rxBarcodeImage)): ?>
                    <img src="<?php echo e($rxBarcodeImage); ?>" alt="RX Barcode" style="max-height:64px;">
                <?php endif; ?>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Patient Information</div>
            <table class="meta">
                <tr>
                    <td class="label">Patient Name</td>
                    <td><?php echo e($patientName ?? 'N/A'); ?></td>
                    <td class="label">Patient ID</td>
                    <td><?php echo e($patientCode ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td class="label">Age</td>
                    <td><?php echo e($patientAge ?? 'N/A'); ?></td>
                    <td class="label">Gender</td>
                    <td><?php echo e($patientGender ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td class="label">IPD ID</td>
                    <td><?php echo e($ipdpatient?->id ?? 'N/A'); ?></td>
                    <td class="label">Bed</td>
                    <td><?php echo e($bedName ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td class="label">Admission Date</td>
                    <td><?php echo e($admissionDate ?? 'N/A'); ?></td>
                    <td class="label">Prescription Date</td>
                    <td><?php echo e($prescriptionDate ?? 'N/A'); ?></td>
                </tr>
            </table>
        </div>

        <div class="cols">
            <div class="col-left">
                <div class="section">
                    <div class="section-title">Doctor</div>
                    <div><?php echo e($doctorName ?? 'N/A'); ?></div>
                    <div><?php echo e($doctorDegree ?? 'N/A'); ?></div>
                    <div><?php echo e($doctorDesignation ?? 'N/A'); ?></div>
                </div>

                <div class="section">
                    <div class="section-title">Complaints</div>
                    <?php
                        $complaintLines = preg_split('/\r\n|\r|\n|,/', (string)($complaints ?? ''));
                        $complaintLines = array_values(array_filter(array_map('trim', $complaintLines), fn($line) => $line !== '' && strtoupper($line) !== 'N/A'));
                    ?>
                    <?php if(!empty($complaintLines)): ?>
                        <ul>
                            <?php $__currentLoopData = $complaintLines; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li><?php echo e($line); ?></li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </ul>
                    <?php else: ?>
                        <div>N/A</div>
                    <?php endif; ?>
                </div>

                <div class="section">
                    <div class="section-title">Diagnosis</div>
                    <?php
                        $diagnosisLines = preg_split('/\r\n|\r|\n|,/', (string)($diagnosis ?? ''));
                        $diagnosisLines = array_values(array_filter(array_map('trim', $diagnosisLines), fn($line) => $line !== '' && strtoupper($line) !== 'N/A'));
                    ?>
                    <?php if(!empty($diagnosisLines)): ?>
                        <ul>
                            <?php $__currentLoopData = $diagnosisLines; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li><?php echo e($line); ?></li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </ul>
                    <?php else: ?>
                        <div>N/A</div>
                    <?php endif; ?>
                </div>

                <div class="section">
                    <div class="section-title">Recommended Tests</div>
                    <?php if(!empty($investigationItems ?? [])): ?>
                        <ol>
                            <?php $__currentLoopData = ($investigationItems ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $test): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li><?php echo e($test); ?></li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </ol>
                    <?php else: ?>
                        <div>No tests recommended.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-right">
                <div class="section">
                    <div class="section-title">Rx (Medicines)</div>
                    <ol>
                        <?php $__empty_1 = true; $__currentLoopData = ($medicineItems ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <li class="rx-item">
                                <strong><?php echo e($item['medicine_name'] ?? 'N/A'); ?></strong>,
                                Dose: <?php echo e($item['dose'] ?? 'N/A'); ?>,
                                Instructions: <?php echo e($item['instructions'] ?? 'N/A'); ?>

                                <?php if(!empty($item['frequency'])): ?>
                                    (<?php echo e($item['frequency']); ?>)
                                <?php endif; ?>
                                , Duration: <?php echo e($item['duration'] ?? 'N/A'); ?>

                            </li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <li>No medicine items available.</li>
                        <?php endif; ?>
                    </ol>
                </div>

                <div class="section">
                    <div class="section-title">Advice</div>
                    <?php
                        $adviceLines = preg_split('/\r\n|\r|\n|,/', (string)($adviceNotes ?? ''));
                        $adviceLines = array_values(array_filter(array_map('trim', $adviceLines), fn($line) => $line !== '' && strtoupper($line) !== 'N/A'));
                    ?>
                    <?php if(!empty($adviceLines)): ?>
                        <ul>
                            <?php $__currentLoopData = $adviceLines; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li><?php echo e($line); ?></li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </ul>
                    <?php else: ?>
                        <div>N/A</div>
                    <?php endif; ?>
                </div>

                <div class="section">
                    <div class="section-title">Follow-up Date</div>
                    <div><?php echo e(!empty($followUpDate) && $followUpDate !== 'N/A' ? $followUpDate : 'After 7 days'); ?></div>
                </div>
            </div>
        </div>

        <div class="footer-row">
            <div style="font-size:11px;">Printed: <span class="print-datetime"><?php echo e($printedAt ?? ''); ?></span></div>
            <div class="sign-wrap">
                <div class="sign-row">
                    <span class="sign-box seal-box">
                        <span class="stamp-line">
                            <?php if(!empty($doctorSealImage)): ?>
                                <img src="<?php echo e($doctorSealImage); ?>" alt="Doctor Seal" class="doctor-seal-image">
                            <?php else: ?>
                                Seal
                            <?php endif; ?>
                        </span>
                    </span>
                    <span class="sign-box signature-box">
                        <span class="signature-line">
                            <?php if(!empty($doctorSignatureImage)): ?>
                                <img src="<?php echo e($doctorSignatureImage); ?>" alt="Doctor Signature" class="doctor-sign-image">
                            <?php else: ?>
                                Doctor Signature
                            <?php endif; ?>
                        </span>
                        <span class="sign-designation"><?php echo e(trim((string) ($doctorDesignation ?? '')) !== '' ? $doctorDesignation : 'Consultant'); ?></span>
                    </span>
                </div>
            </div>
        </div>

        <?php if(!empty($qrCodeImage)): ?>
            <div class="qr">
                <img src="<?php echo e($qrCodeImage); ?>" alt="QR">
            </div>
        <?php endif; ?>

        <?php
            $footerFallbackLine = trim((string) config('app.invoice_footer_fallback_line', ''));
        ?>

        <div class="footer-placeholder"></div>
        </div>
    </div>

    <?php echo $__env->make('prints.partials._footer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</body>

<?php if(empty($forPdf) || !$forPdf): ?>
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
<?php endif; ?>

</html>
<?php /**PATH C:\laragon\www\hms\resources\views/backend/ipd/prescription-print.blade.php ENDPATH**/ ?>