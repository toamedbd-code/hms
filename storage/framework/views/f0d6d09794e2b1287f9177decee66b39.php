<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>IPD Invoice</title>
    <style>
        @page {
            margin: 0mm 0mm;
        }

        <?php
            $__inv_header_h = (int) ($header_height ?? 115);
            $__inv_footer_h = (int) ($footer_height ?? 70);
        ?>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }

        .header-image,
        .footer-image {
            width: 100%;
            max-height: <?php echo e($__inv_header_h); ?>px;
            display: block;
            object-fit: cover;
            height: <?php echo e($__inv_header_h); ?>px;
        }

        .footer-content {
            /* keep footer content relative inside footer area so it appears above the footer image */
            position: relative;
            left: 0;
            right: 0;
            width: 100%;
            text-align: center;
            z-index: 60;
            border-top: none !important;
            padding: 6px 12px 0;
            background: transparent;
        }
        @media print and (min-width: 149mm) {
            .header-image {
                max-height: <?php echo e($__inv_header_h); ?>px;
            }

            .footer-image {
                max-height: <?php echo e($__inv_footer_h + 10); ?>px;
            }

            .header-placeholder {
                height: <?php echo e($__inv_header_h); ?>px;
            }

            .footer-placeholder {
                height: <?php echo e($__inv_footer_h); ?>px;
            }

            .content {
                padding-bottom: <?php echo e($__inv_footer_h * 2); ?>px;
            }
        }

        @media print and (max-width: 148mm), screen and (max-width: 148mm) {
            body {
                font-size: 11px;
            }

            .header-image,
            .footer-image {
                max-height: <?php echo e(min($__inv_header_h, 58)); ?>px;
            }

            .header-placeholder,
            .footer-placeholder {
                height: <?php echo e(min($__inv_footer_h, 58)); ?>px;
            }

            .content {
                padding: 8px 8px <?php echo e(min($__inv_footer_h * 2, 64)); ?>px;
            }
        }

        .content {
            padding: 10px 12px;
            padding-bottom: <?php echo e($__inv_footer_h * 2); ?>px;
        }

        .title-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .barcode-cell {
            width: 25%;
            text-align: right;
            vertical-align: top;
        }

        .barcode-cell-left {
            width: 25%;
            text-align: left;
            vertical-align: top;
        }

        .barcode-image {
            height: 26px;
            width: 140px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .info-table td {
            padding: 2px 0;
            vertical-align: top;
        }

        .label {
            font-weight: bold;
            width: 18%;
            white-space: nowrap;
        }

        .colon {
            width: 2%;
            text-align: center;
        }

        .value {
            width: 30%;
        }

        .section-title {
            font-weight: bold;
            margin: 10px 0 6px;
        }

        .payments-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        .payments-table th {
            text-align: left;
            padding: 6px 5px;
            border-bottom: 1px solid #000;
            font-weight: bold;
            background-color: #f0f0f0;
            font-size: 13px;
        }

        .payments-table td {
            padding: 6px 5px;
            border-bottom: 1px solid #ddd;
            font-size: 13px;
        }

        .amount {
            text-align: right;
            white-space: nowrap;
        }

        .summary {
            margin-top: 10px;
            float: right;
            width: 45%;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-table td {
            padding: 4px 5px;
            border-bottom: 1px solid #ddd;
        }

        .summary-total td {
            font-weight: bold;
            border-top: 2px solid #000;
            border-bottom: 1px solid #000;
        }

        .clearfix {
            clear: both;
        }

        .footer {
            position: static;
            bottom: auto;
            left: auto;
            right: auto;
            padding: 0;
            margin: 0;
            width: 100%;
        }

        .footer-content {
            padding: 0 12px;
            margin: 0 0 6px;
            font-size: 12px;
        }

        .powered-by {
            padding: 0 12px;
            margin: 0 0 6px;
            font-size: 11px;
        }

        .footer-meta {
            width: 100%;
            padding: 0 12px 6px;
            font-size: 11px;
        }
        
        @media print {
            body {
                padding-top: 120px;
                padding-bottom: 120px;
            }

            .header-image,
            .header-placeholder {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                width: 100%;
                z-index: 50;
            }

            .footer-image,
            .footer-placeholder,
            .footer {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                width: 100%;
                max-height: <?php echo e($__inv_footer_h + 10); ?>px;
                object-fit: contain;
                z-index: 10;
            }

            .footer-content {
                text-align: center;
                width: 100%;
                position: relative;
                z-index: 60;
                border-top: none !important;
                padding-top: 0;
                background: transparent;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <?php if(!empty($header_image)): ?>
            <img src="<?php echo e($header_image); ?>" class="header-image" alt="Header">
        <?php else: ?>
            <div class="header-placeholder"></div>
        <?php endif; ?>
    </div>

    <div class="content">
        <table class="title-table">
            <tr>
                <td class="barcode-cell-left">
                    <?php if(!empty($ipd_id)): ?>
                        <?php echo DNS1D::getBarcodeHTML($ipd_id, 'C128', 1, 30); ?>

                    <?php endif; ?>
                </td>
                <td>
                    <div class="title">IPD MONEY RECEIPT</div>
                </td>
                <td class="barcode-cell">
                    <?php if(!empty($ipd_id)): ?>
                        <?php echo DNS1D::getBarcodeHTML($ipd_id, 'C128', 1, 30); ?>

                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <?php
            $patientName = $patient?->name ?? 'N/A';
            $patientAge = $patient?->age ?? 'N/A';
            $patientGender = $patient?->gender ?? 'N/A';
            $patientPhone = $patient?->phone ?? ($patient?->mobile ?? '');

            $doctorName = $doctor?->name ?? 'N/A';
            $bedName = $bed?->name ?? 'N/A';

            $admissionDate = $ipdpatient?->admission_date ?? null;
            $dischargeDate = $ipdpatient?->discharged_at ?? ($ipdpatient?->status === 'Inactive' ? $ipdpatient?->updated_at : null);
        ?>

        <table class="info-table">
            <tr>
                <td class="label">IPD ID</td>
                <td class="colon">:</td>
                <td class="value"><?php echo e($ipd_id ?? 'N/A'); ?></td>

                <td class="label">Printed At</td>
                <td class="colon">:</td>
                <td class="value"><?php echo e($printed_at ?? ''); ?></td>
            </tr>
            <tr>
                <td class="label">Patient Name</td>
                <td class="colon">:</td>
                <td class="value"><?php echo e($patientName); ?></td>

                <td class="label">Age</td>
                <td class="colon">:</td>
                <td class="value"><?php echo e($patientAge); ?></td>
            </tr>
            <tr>
                <td class="label">Gender</td>
                <td class="colon">:</td>
                <td class="value"><?php echo e($patientGender); ?></td>

                <td class="label">Phone</td>
                <td class="colon">:</td>
                <td class="value"><?php echo e($patientPhone ?: 'N/A'); ?></td>
            </tr>
            <tr>
                <td class="label">Credit Limit</td>
                <td class="colon">:</td>
                <td class="value">Tk <?php echo e(number_format((float) ($ipdpatient?->credit_limit ?? 0), 2)); ?></td>

                <td class="label"></td>
                <td class="colon"></td>
                <td class="value"></td>
            </tr>
            <tr>
                <td class="label">Consultant</td>
                <td class="colon">:</td>
                <td class="value"><?php echo e($doctorName); ?></td>

                <td class="label">Bed</td>
                <td class="colon">:</td>
                <td class="value"><?php echo e($bedName); ?></td>
            </tr>
            <tr>
                <td class="label">Admission</td>
                <td class="colon">:</td>
                <td class="value">
                    <?php echo e($admissionDate ? \Carbon\Carbon::parse($admissionDate)->format('d-m-Y h:i A') : 'N/A'); ?>

                </td>

                <td class="label">Discharge</td>
                <td class="colon">:</td>
                <td class="value">
                    <?php echo e($dischargeDate ? \Carbon\Carbon::parse($dischargeDate)->format('d-m-Y h:i A') : 'N/A'); ?>

                </td>
            </tr>
            <tr>
                <td class="label">Case</td>
                <td class="colon">:</td>
                <td class="value" colspan="4"><?php echo e($ipdpatient?->case ?? 'N/A'); ?></td>
            </tr>
        </table>

        <div class="section-title">Payment History</div>

        <table class="payments-table">
            <thead>
                <tr>
                    <th style="width: 6%;">SL</th>
                    <th style="width: 20%;">Date</th>
                    <th style="width: 16%;">Method</th>
                    <th style="width: 18%;">Transaction</th>
                    <th style="width: 25%;">Notes</th>
                    <th style="width: 15%; text-align: right;">Amount (Tk)</th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = ($payments ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td><?php echo e($index + 1); ?></td>
                        <td>
                            <?php echo e($payment?->created_at ? \Carbon\Carbon::parse($payment->created_at)->format('d-m-Y h:i A') : 'N/A'); ?>

                        </td>
                        <td><?php echo e($payment?->payment_method ?? 'N/A'); ?></td>
                        <td><?php echo e($payment?->transaction_id ?? ''); ?></td>
                        <td><?php echo e($payment?->notes ?? ''); ?></td>
                        <td class="amount"><?php echo e(number_format((float) ($payment?->amount ?? 0), 2)); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="6">No payments found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="summary">
            <table class="summary-table">
                <tr class="summary-total">
                    <td>Total Paid</td>
                    <td class="amount">Tk <?php echo e(number_format((float) ($total_paid ?? 0), 2)); ?></td>
                </tr>
            </table>
        </div>

        <div class="clearfix"></div>
    </div>

    <?php
        $portalPatientId = (int) ($patient->id ?? 0);
        $portalPhone = (string) ($patient->phone ?? '');
        $portalToken = '';
        if ($portalPatientId > 0) {
            $portalTokenPayload = [
                'patient_id' => $portalPatientId,
                'phone' => $portalPhone,
                'exp' => now()->addDays(30)->timestamp,
            ];
            $portalToken = encrypt(json_encode($portalTokenPayload));
        }
        $portalLoginUrl = $portalToken !== ''
            ? route('backend.patient.portal.login', ['token' => $portalToken])
            : '';
        $portalQrCode = $portalLoginUrl !== ''
            ? 'data:image/png;base64,' . (new \Milon\Barcode\DNS2D())->getBarcodePNG($portalLoginUrl, 'QRCODE', 5, 5)
            : '';
    ?>

    <?php if($portalQrCode !== ''): ?>
    <div style="margin: 8px 0 10px; text-align:center;">
        <img src="<?php echo e($portalQrCode); ?>" alt="Patient Portal QR" style="width:92px; height:92px; background:#fff;" />
    </div>
    <?php endif; ?>

    <div class="footer">
        <?php
            $footerFallbackLine = trim((string) config('app.invoice_footer_fallback_line', 'Powered By: www.toamedit.com Support: 01919-592638'));
            $footerPrintedAt = trim((string) ($printed_at ?? ''));
        ?>

        <div class="footer-meta">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="text-align: left; padding-right: 12px;">
                        <?php echo e($footerFallbackLine); ?>

                    </td>
                    <td style="text-align: right; white-space: nowrap; padding-right: 60px;">
                        <?php if($footerPrintedAt !== ''): ?>
                            Printing Date: <?php echo e($footerPrintedAt); ?>

                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <?php if(!empty($footer_image)): ?>
            <?php if(!empty($footer_content)): ?>
                <div class="footer-content" style="position:relative; z-index:11;"><?php echo $footer_content; ?></div>
            <?php endif; ?>
            <img src="<?php echo e($footer_image); ?>" class="footer-image" alt="Footer">
        <?php else: ?>
            <div class="footer-placeholder"></div>
            <?php if(!empty($footer_content)): ?>
                <div class="footer-content"><?php echo $footer_content; ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>

</html>
<?php /**PATH C:\laragon\www\hms\resources\views/frontend/invoice/ipd-pdf.blade.php ENDPATH**/ ?>