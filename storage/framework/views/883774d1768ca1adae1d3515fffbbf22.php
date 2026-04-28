<!DOCTYPE html>
<html lang="en">
<?php
    $signatureMarginTop = max((int) ($signatureMarginTop ?? 160), 0);
    $signatureMarginLeft = max((int) ($signatureMarginLeft ?? 96), 0);
    $pageMarginTop = max((int) ($pageMarginTop ?? 0), 0);
    $barcodeValue = (string) ($billing->bill_number ?? ('BILL-' . ($billing->id ?? '')));
    $barcodePng = DNS1D::getBarcodePNG($barcodeValue, 'C128', 1.8, 52);
    $barcodeDataUri = 'data:image/png;base64,' . $barcodePng;
    $isUltrasonogramReport = (bool) ($isUltrasonogramReport ?? false);
    $fullPageMarker = '[[FULL_PAGE]]';
    $primaryRawNote = trim((string) ($primaryItem->report_note ?? ''));
    $hasFullPageMarker = str_starts_with($primaryRawNote, $fullPageMarker);
    $primaryNoteBody = $hasFullPageMarker
        ? trim(substr($primaryRawNote, strlen($fullPageMarker)))
        : $primaryRawNote;

    $detectText = strtolower(trim(
        (string) ($primaryItem->item_name ?? '') . ' '
        . (string) ($primaryItem->category ?? '') . ' '
        . (string) ($reportTitle ?? '')
    ));
    $isXrayReport = str_contains($detectText, 'xray')
        || str_contains($detectText, 'x-ray')
        || str_contains($detectText, 'radiography');
    $isFullPageReport = $isUltrasonogramReport || $isXrayReport || $hasFullPageMarker;
    $noteLooksHtml = preg_match('/<[^>]+>/', $primaryNoteBody) === 1;
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Print</title>
    <style>
        :root {
            --report-header-height: <?php echo e($reportHeaderHeight ?? 115); ?>px;
            --report-footer-height: <?php echo e($reportFooterHeight ?? 70); ?>px;
            --report-page-top-margin: <?php echo e($pageMarginTop ?? 0); ?>px;
            --signature-top-margin: <?php echo e(isset($signatureMarginTop) ? (int) $signatureMarginTop : 160); ?>px;
            --signature-left-margin: <?php echo e(isset($signatureMarginLeft) ? (int) $signatureMarginLeft : 96); ?>px;
        }
        * { box-sizing: border-box; }
        @page { size: A4; margin: 0; }
        body { font-family: Arial, sans-serif; color: #111827; margin: 0; padding: 0; font-size: 16px; line-height: 1.3; }
        .title { font-size: 18px; font-weight: bold; }
        .report-title { font-size: 22px; font-weight: 800; font-family: 'Arial Black', 'Impact', sans-serif; margin: 0; letter-spacing: 6px; text-transform: uppercase; display: inline-block; background: #fff; padding: 0 8px; position: relative; z-index: 11; }
        .meta { font-size: 12px; color: #6b7280; margin-top: 4px; }
        .section { margin-top: 16px; }
        .label { font-weight: 600; }
        .note { white-space: pre-wrap; border: 1px solid #e5e7eb; padding: 10px; border-radius: 6px; min-height: 120px; }
        .header-section {
            width: 100%;
            padding-left: 0;
            padding-right: 0;
            margin-top: var(--report-page-top-margin, 0px);
            text-align: center;
            margin-bottom: 5px;
            /* prefer min-height to avoid collapsing when CSS vars resolve to 0 */
            min-height: calc(var(--report-header-height, 115px) + 2mm);
            height: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: visible !important;
        }
        .header-placeholder { width: 100%; height: calc(var(--report-header-height, 115px) + 2mm); visibility: hidden; }
        .header-image { width: 100%; height: auto; object-fit: contain; display: block; visibility: visible !important; -webkit-print-color-adjust: exact !important; }
        .header-banner-image { width: 100%; height: auto; max-height: calc(var(--report-header-height, 115px) + 2mm); object-fit: contain; display: block; visibility: visible !important; -webkit-print-color-adjust: exact !important; }
        .header-banner-image { width: 100%; height: auto; object-fit: contain; display: block; }
        .patient-details-table td { font-size: 12px; }
        .patient-details-table .label-cell { font-weight:600; width: 14%; }
        .patient-details-table .sep-cell { width:2%; }
        .patient-details-table .value-cell { width: 30%; }
                .title-section-table { width: 100%; margin-bottom: 12px; }
        .barcode-cell-left { width: 20%; text-align: left; vertical-align: top; }
        .barcode-cell-right { width: 20%; text-align: right; vertical-align: top; }
        .title-cell-center { width: 60%; text-align: center; }
                .barcode-image { height: 25px; width: 120px; max-width: 120px; display: block; object-fit: contain; margin: 0 auto; }
                .receipt-title { font-size: 20px; font-weight: bold; font-family: Verdana, Geneva, Tahoma, sans-serif; margin: 0; letter-spacing: 2px; }
          .barcode-small { height: 60px; width: auto; display:inline-block; vertical-align: middle; }

          /* Header layout: map to table columns (S/N+TestName = 50%, Result = 25%, Range = 25%)
              Place left barcode at right edge of left gutter and right barcode at left edge of right gutter */
        .header-left { flex: 0 0 20%; max-width: 20%; display:flex; flex-direction:column; align-items:center; justify-content:flex-start; padding-right:6mm; padding-top:6px; position:relative; overflow:visible; z-index:1; }
        .header-center { flex: 0 0 60%; max-width: 60%; display:flex; align-items:center; justify-content:center; text-align:center; padding: 0 6mm; margin-top: -6px; white-space: nowrap; overflow: visible; text-overflow: ellipsis; position:relative; z-index:11; }
        .header-center .report-title { display:block; line-height:1; }
        .header-right { flex: 0 0 20%; max-width: 20%; display:flex; flex-direction:column; align-items:center; justify-content:flex-start; padding-left:6mm; padding-top:6px; position:relative; overflow:visible; z-index:1; }

        .barcode-caption { font-size: 12px; text-align: center; margin-top: 4px; color: #111827; }
        .barcode-caption .label { font-weight: 600; display:block; font-size:11px; color:#374151; }
        .barcode-caption .value { display:block; font-size:12px; color:#111827; }

        /* Table cell helpers for single-line alignment */
        .sn-cell { text-align: center; padding-right: 6px; vertical-align: middle; }
        .testname-cell { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; vertical-align: top; }
        .result-cell { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: center; vertical-align: middle; line-height: 1.1; }
        .range-cell { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; vertical-align: middle; text-align: center; }
        .result-cell > * { margin: 0; padding: 0; display: inline; }

        /* Electrolyte layout - three lines with minimal gap */
        .electrolyte-container { display:flex; flex-direction:column; gap:4px; }
        .electrolyte-list { display:flex; flex-direction:column; gap:2px; }
        .ele-item { display:block; margin:0; padding:0; font-size:13px; }
        .content-section {
            width: 100%;
            padding-left: 15px;
            padding-right: 15px;
            padding-bottom: 110px;
        }
        .footer-section {
            /* Keep footer in normal flow on-screen so it scrolls with the page
               and does not overlay content. For print, `@media print` will
               switch this to `position: fixed` so the footer sticks to the
               bottom of each printed page. */
            position: relative;
            width: 100%;
            padding-left: 0;
            padding-right: 0;
            text-align: center;
            padding-bottom: 0;
            min-height: var(--report-footer-height, 70px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
        }
        .footer-placeholder { width: 100%; height: var(--report-footer-height, 70px); visibility: hidden; }
        .footer-image { width: 100%; height: auto; max-height: var(--report-footer-height, 70px); object-fit: contain; display: block; }
        .footer-content { text-align: center; width: 100%; }
        .footer-date-time { font-size: 12px; color: #4b5563; margin-bottom: 4px; }

        .ultra-test-name {
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 6px;
            margin-bottom: 12px;
        }
        .ultra-report-body {
            min-height: 220px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 12px;
            font-size: 13px;
            line-height: 1.5;
            white-space: pre-wrap;
            page-break-inside: auto;
            break-inside: auto;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .ultra-report-body table {
            width: 100% !important;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: auto;
            break-inside: auto;
        }
        .ultra-report-body tr,
        .ultra-report-body td,
        .ultra-report-body th {
            page-break-inside: avoid;
            break-inside: avoid;
            vertical-align: top;
        }
        .ultra-report-body img {
            max-width: 100% !important;
            height: auto !important;
        }
        .ultra-range {
            margin-top: 10px;
            font-size: 12px;
        }
        .ultra-layout .content-section { padding-bottom: 80px; }
        .ultra-layout .signature-row {
            margin-top: 24px;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        .ultra-layout .footer-image { max-height: 56px; }
        .ultra-layout .signature-image,
        .ultra-layout .signature-top-line {
            height: 46px;
        }

        /* Paper-size locking to keep report print identical across printers */
        @media print and (min-width: 149mm) {
            .header-section { height: calc(var(--report-header-height, 115px) + 2mm); }
            .header-placeholder { height: calc(var(--report-header-height, 115px) + 2mm); }
            .header-image { height: 100%; }
            .footer-placeholder { height: 70px; }
            .footer-image { max-height: 80px; }
            .content-section { padding-bottom: 110px; }
        }

        @media print and (max-width: 148mm), screen and (max-width: 148mm) {
            body { font-size: 12px; }
            .header-section { height: 1.2in; }
            .header-placeholder { height: 1.2in; }
            .header-image { height: 100%; }
            .footer-placeholder { height: 52px; }
            .footer-image { max-height: 56px; }
            .footer-date-time { font-size: 10px; margin-bottom: 2px; }
            .content-section { padding-bottom: 88px; }
            .report-title { font-size: 16px; }
        }
        .signature-block {
            font-size: 12px;
            flex: 1;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .signature-row {
            margin-top: var(--signature-top-margin, 160px);
            margin-bottom: 18px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            width: 100%;
        }
        .signature-row .signature-block {
            flex: 1 1 0;
            max-width: 33%;
            min-width: 140px;
            text-align: center;
        }
        .signature-row > div[style*="flex: 1 1 0;"] {
            /* keep center placeholder proportional */
            flex: 1 1 0;
            max-width: 33%;
        }
        .signature-top-line {
            width: 150px;
            height: 56px;
            border-bottom: 1px solid #6b7280;
            margin-bottom: 8px;
            margin-left: auto;
            margin-right: auto;
        }
        /* Name underline that matches the name width */
        .name-with-line {
            display: inline-block;
            border-top: 1px solid #111827;
            padding-top: 6px;
            margin-top: 6px;
            white-space: pre-line;
            font-weight: 700; /* force bold */
        }
        .signature-image {
            width: 150px;
            height: 56px;
            object-fit: contain;
            display: block;
            margin: 0 auto 8px auto;
        }
        .signature-block .meta {
            font-size: 12px;
            word-break: break-word;
        }
        .signature-line { display: none; }
        .signature-block .label { min-height: 18px; width: 100%; }
        .signature-block .meta { min-height: 16px; width: 100%; text-align: center; }
        .signature-block .meta.multiline { white-space: pre-line; }
        @media print {
            .content-section { padding-bottom: 72px; }
            .footer-section {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
            }
            .ultra-layout .content-section { padding-bottom: 56px; }
        }
        </style>

    <div class="content-section" <?php if(empty($hasHeader)): ?> style="margin-top:var(--report-page-top-margin);" <?php endif; ?>>
        <?php if(!empty($hasHeader) && $hasHeader): ?>
            <?php if ($__env->exists('prints.partials._header', ['header_image' => $header_image ?? null, 'printed_at' => $reportDateTime ?? null])) echo $__env->make('prints.partials._header', ['header_image' => $header_image ?? null, 'printed_at' => $reportDateTime ?? null], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:-6px;margin-bottom:8px;padding:0 20px;">
                <div class="header-left">
                    <img src="<?php echo e($barcodeDataUri); ?>" alt="Barcode Left" class="barcode-image" />
                </div>
                <div class="header-center">
                    <div class="receipt-title"><?php echo e(strtoupper((string) ($reportTitle ?? 'Item Report'))); ?></div>
                    <?php if(!empty($headerHtml)): ?>
                        <div class="meta" style="margin-top:4px; font-size:12px;"><?php echo $headerHtml; ?></div>
                    <?php endif; ?>
                </div>
                <div class="header-right">
                    <img src="<?php echo e($barcodeDataUri); ?>" alt="Barcode Right" class="barcode-image" />
                </div>
            </div>
        <?php else: ?>
            
            <div class="title-section-table" style="margin-bottom:10px;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;padding:0 20px;">
                    <div class="barcode-cell-left">
                        <img src="<?php echo e($barcodeDataUri); ?>" alt="Barcode Left" class="barcode-image" />
                    </div>

                    <div class="title-cell-center">
                        <div class="receipt-title"><?php echo e(strtoupper((string) ($reportTitle ?? 'Item Report'))); ?></div>
                    </div>

                    <div class="barcode-cell-right">
                        <img src="<?php echo e($barcodeDataUri); ?>" alt="Barcode Right" class="barcode-image" />
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <table class="patient-details-table" style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
        <tr>
            <td style="width: 15%; vertical-align: top; padding: 2px 0; font-weight: bold;">Bill No</td>
            <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
            <td style="width: 28%; vertical-align: top; padding: 2px 0;"><?php echo e($billing->bill_number ?? 'N/A'); ?></td>
            <td style="width: 20%; vertical-align: top; padding: 2px 0; font-weight: bold;">Date & Time</td>
            <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
            <td style="width: 28%; vertical-align: top; padding: 2px 0;"><span class="report-datetime"><?php echo e($reportDateTime); ?></span></td>
        </tr>
        <tr>
            <td style="width: 15%; vertical-align: top; padding: 2px 0; font-weight: bold;">Name</td>
            <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
            <td style="width: 28%; vertical-align: top; padding: 2px 0;"><?php echo e($patientName); ?></td>
            <td style="width: 20%; vertical-align: top; padding: 2px 0; font-weight: bold;">Age</td>
            <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
            <td style="width: 28%; vertical-align: top; padding: 2px 0;"><?php echo e($age); ?></td>
        </tr>
        <tr>
            <td style="width: 15%; vertical-align: top; padding: 2px 0; font-weight: bold;">Contact No</td>
            <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
            <td style="width: 28%; vertical-align: top; padding: 2px 0;"><?php echo e($contact_no); ?></td>
            <td style="width: 20%; vertical-align: top; padding: 2px 0; font-weight: bold;">Gender</td>
            <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
            <td style="width: 28%; vertical-align: top; padding: 2px 0;"><?php echo e($gender); ?></td>
        </tr>
        <tr>
            <td style="width: 15%; vertical-align: top; padding: 2px 0; font-weight: bold;">Refd. By</td>
            <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
            <td colspan="4" style="width: 78%; vertical-align: top; padding: 2px 0;"><?php echo e($refd_by); ?></td>
        </tr>
    </table>

    <div class="section">
        <?php if($isFullPageReport): ?>
            <div class="ultra-test-name"><?php echo e($primaryItem->item_name ?? 'Ultrasonogram'); ?></div>
            <div class="ultra-report-body"><?php echo $noteLooksHtml ? $primaryNoteBody : nl2br(e($primaryNoteBody)); ?></div>
            <?php if(!empty($primaryItem->report_range)): ?>
                <div class="ultra-range"><strong>Reference:</strong> <?php echo e($primaryItem->report_range); ?></div>
            <?php endif; ?>
        <?php else: ?>
            <table style="width:100%; border-collapse: collapse; font-size: 12px;">
                <thead>
                    <tr>
                        <th class="sn-cell" style="border:1px solid #e5e7eb; padding:8px; text-align:center; width:6%;">S/N</th>
                            <th class="testname-cell" style="border:1px solid #e5e7eb; padding:8px; text-align:left; width:44%;">Item Name</th>
                            <th class="result-cell" style="border:1px solid #e5e7eb; padding:8px; text-align:center; width:25%;">Result</th>
                            <th class="range-cell" style="border:1px solid #e5e7eb; padding:8px; text-align:center; width:25%;">Normal Range</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                            $params = (!empty($item->printed_parameter_rows) && is_array($item->printed_parameter_rows)) ? $item->printed_parameter_rows : [];
                        ?>

                        <?php if(count($params) > 0): ?>
                            <?php $__currentLoopData = $params; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $pr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php
                                    $rHtml = $pr['result_html'] ?? '';
                                    $pos = strpos($rHtml, ':');
                                    if ($pos !== false) {
                                        $nHtml = trim(substr($rHtml, 0, $pos));
                                        $vHtml = trim(substr($rHtml, $pos + 1));
                                    } else {
                                        $nHtml = '';
                                        $vHtml = $rHtml;
                                    }
                                ?>
                                <tr>
                                    <td class="sn-cell" style="border:1px solid #e5e7eb; padding:8px; vertical-align: middle;"><?php echo e($index + 1); ?></td>
                                    <td class="testname-cell" style="border:1px solid #e5e7eb; padding:8px; vertical-align: middle;"><?php echo $nHtml !== '' ? $nHtml : e($item->item_name ?? 'N/A'); ?></td>
                                    <td class="result-cell" style="border:1px solid #e5e7eb; padding:8px; vertical-align: middle;"><?php echo $vHtml; ?></td>
                                    <td class="range-cell" style="border:1px solid #e5e7eb; padding:8px; vertical-align: middle;"><?php echo $pr['normal_range'] ?? '-'; ?></td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php else: ?>
                            <tr>
                                <td class="sn-cell" style="border:1px solid #e5e7eb; padding:8px; vertical-align: middle;">1</td>
                                <td class="testname-cell" style="border:1px solid #e5e7eb; padding:8px; vertical-align: middle;"><?php echo e($item->item_name ?? 'N/A'); ?></td>
                                <td class="result-cell" style="border:1px solid #e5e7eb; padding:8px; vertical-align: middle;"><?php echo nl2br(e($item->report_note ?? '')); ?></td>
                                <td class="range-cell" style="border:1px solid #e5e7eb; padding:8px; vertical-align: middle;"><?php echo e($item->report_range ?? ''); ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div
        class="section signature-row"
    >
        <?php
            $rawName = isset($pathologistNameRaw) ? trim((string) $pathologistNameRaw) : '';
            $rawDesignation = isset($pathologistDesignationRaw) ? trim((string) $pathologistDesignationRaw) : '';
            $hasPathologistIdentity = ($rawName !== '') || ($rawDesignation !== '');
        ?>
            <?php if($hasPathologistIdentity): ?>
                <div class="signature-block">
                    <?php if($technologistSignature): ?>
                        <img src="<?php echo e($technologistSignature); ?>" alt="tech-sign" style="max-height:80px; display:block; margin:0 auto;" />
                    <?php endif; ?>
                    <div class="signature-name"><span class="name-with-line"><?php echo e($technologistNameSetting ?: ($primaryItem->reportedBy?->name ?? '')); ?></span></div>
                    <div class="signature-designation"><?php echo e($technologistDesignationSetting ?: ($primaryItem->reportedBy?->designation ?? '')); ?></div>
                </div>

                <div class="signature-block">
                    <?php if($sampleCollectedBySignature): ?>
                        <img src="<?php echo e($sampleCollectedBySignature); ?>" alt="sample-sign" style="max-height:80px; display:block; margin:0 auto;" />
                    <?php endif; ?>
                    <div class="signature-name"><span class="name-with-line"><?php echo e($sampleCollectedByNameSetting ?: ($primaryItem->sampleCollectedBy?->name ?? '')); ?></span></div>
                    <div class="signature-designation"><?php echo e($sampleCollectedByDesignationSetting ?: ($primaryItem->sampleCollectedBy?->designation ?? '')); ?></div>
                </div>

                <div class="signature-block">
                    <?php if($pathologistSignature): ?>
                        <img src="<?php echo e($pathologistSignature); ?>" alt="path-sign" style="max-height:80px; display:block; margin:0 auto;" />
                    <?php endif; ?>
                    <div class="signature-name"><span class="name-with-line"><?php echo e($pathologistName ?? ''); ?></span></div>
                    <div class="signature-designation"><?php echo e($pathologistDesignation ?? ''); ?></div>
                </div>
            <?php else: ?>
                <div class="signature-block">
                    <?php if($sampleCollectedBySignature): ?>
                        <img src="<?php echo e($sampleCollectedBySignature); ?>" alt="sample-sign" style="max-height:80px; display:block; margin:0 auto;" />
                    <?php endif; ?>
                    <div class="signature-name"><span class="name-with-line"><?php echo e($sampleCollectedByNameSetting ?: ($primaryItem->sampleCollectedBy?->name ?? '')); ?></span></div>
                    <div class="signature-designation"><?php echo e($sampleCollectedByDesignationSetting ?: ($primaryItem->sampleCollectedBy?->designation ?? '')); ?></div>
                </div>

                <div class="signature-block">
                    
                </div>

                <div class="signature-block" style="text-align:right;">
                    <?php if($technologistSignature): ?>
                        <img src="<?php echo e($technologistSignature); ?>" alt="tech-sign" style="max-height:80px; display:block; margin:0 auto;" />
                    <?php endif; ?>
                    <div class="signature-name"><span class="name-with-line"><?php echo e($technologistNameSetting ?: ($primaryItem->reportedBy?->name ?? '')); ?></span></div>
                    <div class="signature-designation"><?php echo e($technologistDesignationSetting ?: ($primaryItem->reportedBy?->designation ?? '')); ?></div>
                </div>
            <?php endif; ?>
    </div>

        <?php if(!empty($hasFooter) && $hasFooter): ?>
            <?php if ($__env->exists('prints.partials._footer')) echo $__env->make('prints.partials._footer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?>
    </div>

</body>

<?php if(empty($isPdf) || !$isPdf): ?>
<script>
    // Format JS date to match server format like: 19-Apr-2026 03:45 PM
    function _formatPrintDate(d) {
        try {
            const pad = (n) => (n < 10 ? '0' + n : n);
            const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            const dd = pad(d.getDate());
            const mon = months[d.getMonth()];
            const yyyy = d.getFullYear();
            let hrs = d.getHours();
            const mins = pad(d.getMinutes());
            const ampm = hrs >= 12 ? 'PM' : 'AM';
            hrs = hrs % 12; hrs = hrs ? hrs : 12; // convert 0 -> 12
            const hh = pad(hrs);
            return `${dd}-${mon}-${yyyy} ${hh}:${mins} ${ampm}`;
        } catch (e) {
            return '';
        }
    }

    function _injectCurrentPrintDate() {
        try {
            const nowStr = _formatPrintDate(new Date());
            // Keep the report's original Date & Time (server-provided) intact.
            // Only update the footer's printing timestamp so it reflects
            // the actual print time when the user clicks Print.
            document.querySelectorAll('.print-datetime').forEach(el => { el.textContent = nowStr; });
        } catch (e) {
            // ignore
        }
    }

    // On load, inject current datetime then trigger print after images load
    window.addEventListener('load', function () {
        setTimeout(function () {
            _injectCurrentPrintDate();
            // Wait for images (footer/header) to load before triggering print.
            const imgs = Array.from(document.images || []);
            const pending = imgs.filter(i => !i.complete);
            if (pending.length === 0) {
                window.print();
                return;
            }
            // Fallback: print after 2s in case some images never load.
            const fallback = setTimeout(() => {
                window.print();
            }, 2000);
            Promise.all(pending.map(img => new Promise((resolve) => {
                img.addEventListener('load', resolve, { once: true });
                img.addEventListener('error', resolve, { once: true });
            }))).then(() => {
                clearTimeout(fallback);
                window.print();
            }).catch(() => {
                clearTimeout(fallback);
                window.print();
            });
        }, 250);
    });

    // Also ensure datetime is updated before a manual print (browser print dialog)
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
<?php /**PATH C:\laragon\www\hms\resources\views/backend/reporting/print.blade.php ENDPATH**/ ?>