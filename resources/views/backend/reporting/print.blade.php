<!DOCTYPE html>
<html lang="en">
@php
    $signatureMarginTop = max((int) ($signatureMarginTop ?? 160), 0);
    $signatureMarginLeft = max((int) ($signatureMarginLeft ?? 96), 0);
    $pageMarginTop = max((int) ($pageMarginTop ?? 0), 0);
    $barcodeValue = (string) ($billing->bill_number ?? ('BILL-' . ($billing->id ?? '')));
    $barcodePng = DNS1D::getBarcodePNG($barcodeValue, 'C128', 1.8, 36);
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
@endphp
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Print</title>
    <style>
        * { box-sizing: border-box; }
        @page { size: A4; margin: 0; }
        body { font-family: Arial, sans-serif; color: #111827; margin: 0; padding: 0; font-size: 16px; line-height: 1.3; }
        .title { font-size: 18px; font-weight: bold; }
        .report-title { font-size: 20px; font-weight: bold; font-family: Verdana, Geneva, Tahoma, sans-serif; margin: 0; letter-spacing: 2px; }
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
            height: var(--report-header-height, 115px);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .header-placeholder { width: 100%; height: var(--report-header-height, 115px); visibility: hidden; }
        .header-image { width: 100%; height: 100%; object-fit: fill; display: block; }
        .patient-details-table td { font-size: 12px; }
        .title-section-table { width: 100%; margin-bottom: 12px; }
        .barcode-cell-left { width: 20%; text-align: left; vertical-align: top; }
        .barcode-cell-right { width: 20%; text-align: right; vertical-align: top; }
        .title-cell-center { width: 60%; text-align: center; }
        .barcode-image { width: 150px; height: 34px; display: block; }
        .content-section {
            width: 100%;
            padding-left: 15px;
            padding-right: 15px;
            padding-bottom: 110px;
        }
        .footer-section {
            position: static;
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
            .header-section { height: 1.2in; }
            .header-placeholder { height: 1.2in; }
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
            .ultra-layout .signature-row {
                margin-top: 12px;
                margin-bottom: 8px;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .ultra-layout .ultra-report-body {
                min-height: 0;
            }
        }
    </style>
</head>
<body class="{{ $isUltrasonogramReport ? 'ultra-layout' : '' }}" style="--report-left-margin: {{ $signatureMarginLeft }}px; --signature-top-margin: {{ $signatureMarginTop }}px; --report-page-top-margin: {{ $pageMarginTop }}px; --report-header-height: {{ $reportHeaderHeight ?? 115 }}px; --report-footer-height: {{ $reportFooterHeight ?? 70 }}px;">
    <div class="header-section">
        @if(!empty($header_image))
            <img src="{{ $header_image }}" alt="Header" class="header-image">
        @else
            <div class="header-placeholder"></div>
        @endif
    </div>

    <div class="content-section">
    <table class="title-section-table">
        <tr>
            <td class="barcode-cell-left">
                <img src="{{ $barcodeDataUri }}" alt="Barcode Left" class="barcode-image">
            </td>
            <td class="title-cell-center">
                <div class="report-title">{{ strtoupper((string) ($reportTitle ?? 'Test Report')) }}</div>
            </td>
            <td class="barcode-cell-right">
                <img src="{{ $barcodeDataUri }}" alt="Barcode Right" class="barcode-image" style="margin-left:auto;">
            </td>
        </tr>
    </table>
    <table class="patient-details-table" style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
        <tr>
            <td style="width: 15%; vertical-align: top; padding: 2px 0; font-weight: bold;">Bill No</td>
            <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
            <td style="width: 28%; vertical-align: top; padding: 2px 0;">{{ $billing->bill_number ?? 'N/A' }}</td>
            <td style="width: 20%; vertical-align: top; padding: 2px 0; font-weight: bold;">Date & Time</td>
            <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
            <td style="width: 28%; vertical-align: top; padding: 2px 0;">{{ $reportDateTime }}</td>
        </tr>
        <tr>
            <td style="width: 15%; vertical-align: top; padding: 2px 0; font-weight: bold;">Name</td>
            <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
            <td style="width: 28%; vertical-align: top; padding: 2px 0;">{{ $patientName }}</td>
            <td style="width: 20%; vertical-align: top; padding: 2px 0; font-weight: bold;">Age</td>
            <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
            <td style="width: 28%; vertical-align: top; padding: 2px 0;">{{ $age }}</td>
        </tr>
        <tr>
            <td style="width: 15%; vertical-align: top; padding: 2px 0; font-weight: bold;">Contact No</td>
            <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
            <td style="width: 28%; vertical-align: top; padding: 2px 0;">{{ $contact_no }}</td>
            <td style="width: 20%; vertical-align: top; padding: 2px 0; font-weight: bold;">Gender</td>
            <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
            <td style="width: 28%; vertical-align: top; padding: 2px 0;">{{ $gender }}</td>
        </tr>
        <tr>
            <td style="width: 15%; vertical-align: top; padding: 2px 0; font-weight: bold;">Refd. By</td>
            <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
            <td colspan="4" style="width: 78%; vertical-align: top; padding: 2px 0;">{{ $refd_by }}</td>
        </tr>
    </table>

    <div class="section">
        @if($isFullPageReport)
            <div class="ultra-test-name">{{ $primaryItem->item_name ?? 'Ultrasonogram' }}</div>
            <div class="ultra-report-body">{!! $noteLooksHtml ? $primaryNoteBody : nl2br(e($primaryNoteBody)) !!}</div>
            @if(!empty($primaryItem->report_range))
                <div class="ultra-range"><strong>Reference:</strong> {{ $primaryItem->report_range }}</div>
            @endif
        @else
            <table style="width:100%; border-collapse: collapse; font-size: 12px;">
                <thead>
                    <tr>
                        <th style="border:1px solid #e5e7eb; padding:6px; text-align:left;">Test Name</th>
                        <th style="border:1px solid #e5e7eb; padding:6px; text-align:left;">Result</th>
                        <th style="border:1px solid #e5e7eb; padding:6px; text-align:left;">Normal Range</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td style="border:1px solid #e5e7eb; padding:6px;">{{ $item->item_name ?? 'N/A' }}</td>
                            <td style="border:1px solid #e5e7eb; padding:6px; white-space: pre-wrap;">{{ $item->report_note ?? '' }}</td>
                            <td style="border:1px solid #e5e7eb; padding:6px;">{{ $item->report_range ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div
        class="section signature-row"
    >
        @php
            $rawName = isset($pathologistNameRaw) ? trim((string) $pathologistNameRaw) : '';
            $rawDesignation = isset($pathologistDesignationRaw) ? trim((string) $pathologistDesignationRaw) : '';
            $hasPathologistIdentity = ($rawName !== '') || ($rawDesignation !== '');
        @endphp
            @if($hasPathologistIdentity)
                <div class="signature-block">
                    @if($technologistSignature)
                        <img src="{{ $technologistSignature }}" alt="tech-sign" style="max-height:80px; display:block; margin:0 auto;" />
                    @endif
                    <div class="signature-name"><span class="name-with-line">{{ $technologistNameSetting ?: ($primaryItem->reportedBy?->name ?? '') }}</span></div>
                    <div class="signature-designation">{{ $technologistDesignationSetting ?: ($primaryItem->reportedBy?->designation ?? '') }}</div>
                </div>

                <div class="signature-block">
                    @if($sampleCollectedBySignature)
                        <img src="{{ $sampleCollectedBySignature }}" alt="sample-sign" style="max-height:80px; display:block; margin:0 auto;" />
                    @endif
                    <div class="signature-name"><span class="name-with-line">{{ $sampleCollectedByNameSetting ?: ($primaryItem->sampleCollectedBy?->name ?? '') }}</span></div>
                    <div class="signature-designation">{{ $sampleCollectedByDesignationSetting ?: ($primaryItem->sampleCollectedBy?->designation ?? '') }}</div>
                </div>

                <div class="signature-block">
                    @if($pathologistSignature)
                        <img src="{{ $pathologistSignature }}" alt="path-sign" style="max-height:80px; display:block; margin:0 auto;" />
                    @endif
                    <div class="signature-name"><span class="name-with-line">{{ $pathologistName ?? '' }}</span></div>
                    <div class="signature-designation">{{ $pathologistDesignation ?? '' }}</div>
                </div>
            @else
                <div class="signature-block">
                    @if($sampleCollectedBySignature)
                        <img src="{{ $sampleCollectedBySignature }}" alt="sample-sign" style="max-height:80px; display:block; margin:0 auto;" />
                    @endif
                    <div class="signature-name"><span class="name-with-line">{{ $sampleCollectedByNameSetting ?: ($primaryItem->sampleCollectedBy?->name ?? '') }}</span></div>
                    <div class="signature-designation">{{ $sampleCollectedByDesignationSetting ?: ($primaryItem->sampleCollectedBy?->designation ?? '') }}</div>
                </div>

                <div class="signature-block">
                    {{-- center placeholder --}}
                </div>

                <div class="signature-block" style="text-align:right;">
                    @if($technologistSignature)
                        <img src="{{ $technologistSignature }}" alt="tech-sign" style="max-height:80px; display:block; margin:0 auto;" />
                    @endif
                    <div class="signature-name"><span class="name-with-line">{{ $technologistNameSetting ?: ($primaryItem->reportedBy?->name ?? '') }}</span></div>
                    <div class="signature-designation">{{ $technologistDesignationSetting ?: ($primaryItem->reportedBy?->designation ?? '') }}</div>
                </div>
            @endif
    </div>

    <div class="footer-placeholder"></div>
    </div>

    <div class="footer-section">
        @php
            $footerFallbackLine = trim((string) config('app.invoice_footer_fallback_line', ''));
            $footerPrintedAt = trim((string) ($reportDateTime ?? ''));
        @endphp

        @if(!empty($footer_image))
            @if(!empty($footer_content))
                <div class="footer-content">{!! $footer_content !!}</div>
            @elseif(!empty($footerFallbackLine))
                <div class="footer-content">{{ $footerFallbackLine }}@if(!empty($footerPrintedAt)), Printing Date: {{ $footerPrintedAt }}@endif</div>
            @endif
            <img src="{{ $footer_image }}" alt="Footer" class="footer-image">
        @else
            @if(!empty($footer_content))
                <div class="footer-content">{!! $footer_content !!}</div>
            @elseif(!empty($footerFallbackLine))
                <div class="footer-content">{{ $footerFallbackLine }}@if(!empty($footerPrintedAt)), Printing Date: {{ $footerPrintedAt }}@endif</div>
            @else
                <div class="footer-placeholder"></div>
            @endif
        @endif
    </div>

</body>

@if (empty($isPdf) || !$isPdf)
<script>
    window.addEventListener('load', function () {
        setTimeout(function () {
            window.print();
        }, 180);
    });
</script>
@endif

</html>
