<!DOCTYPE html>
<html lang="en">
@php
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
    $isCbcReport = preg_match('/\b(cbc|complete blood count|ha?emogram|ha?ematology|blood count)\b/i', $detectText) === 1;
    $cbcHistogramData = is_array($primaryItem->cbc_histogram_data ?? null)
        ? $primaryItem->cbc_histogram_data
        : [];
    $showCbcHistograms = $isCbcReport
        && !empty($primaryItem->show_cbc_histogram)
        && $cbcHistogramData !== [];
    $isXrayReport = str_contains($detectText, 'xray')
        || str_contains($detectText, 'x-ray')
        || str_contains($detectText, 'radiography');
    $isFullPageReport = $isUltrasonogramReport || $isXrayReport || $hasFullPageMarker;
    $noteLooksHtml = preg_match('/<[^>]+>/', $primaryNoteBody) === 1;
@endphp
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Report Print</title>
    <style>
        * { box-sizing: border-box; }
        @page { size: A4; margin: 0; }
        body { font-family: Arial, sans-serif; color: #111827; margin: 0; padding: 0; font-size: 16px; line-height: 1.3; }
        @media screen {
            html { min-height: 100%; background: #e5e7eb; }
            body {
                position: relative;
                width: min(210mm, calc(100% - 32px));
                min-height: 297mm;
                margin: 24px auto;
                background: #fff;
                box-shadow: 0 4px 18px rgba(15, 23, 42, 0.18);
            }
            body .footer-section {
                position: absolute;
                right: 0;
                bottom: 0;
                left: 0;
            }
            body .report-institution-pad {
                position: absolute;
                inset: 0 auto auto 0;
                width: 100%;
                height: 297mm;
            }
        }
        .title { font-size: 18px; font-weight: bold; }
        .report-title { display: inline-block; max-width: 100%; font-size: 20px; font-weight: bold; font-family: Verdana, Geneva, Tahoma, sans-serif; margin: 0; letter-spacing: 1px; white-space: nowrap; }
        .meta { font-size: 12px; color: #6b7280; margin-top: 4px; }
        .section { margin-top: 16px; }
        .label { font-weight: 600; }
        .note { white-space: pre-wrap; border: 1px solid #e5e7eb; padding: 10px; border-radius: 6px; min-height: 120px; }
        .group-title {
            margin-top: 10px;
            margin-bottom: 4px;
            font-size: 12px;
            font-weight: 700;
            text-transform: none;
            color: #111827;
        }
        .urine-report-title {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #111827;
            background: linear-gradient(180deg, #dfe2e5 0%, #c9ced3 100%);
            border: 1px solid #b9bec6;
            padding: 8px 16px;
            margin: 8px auto 16px auto;
            width: 100%;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.3);
        }
        .urine-section-shell {
            width: 100%;
            border: 1px solid #1f2937;
            margin: 0 0 12px 0;
            background: #fff;
        }
        .urine-section-header {
            text-align: center;
            font-size: 15px;
            font-weight: 700;
            padding: 6px 0;
            border-bottom: 1px solid #1f2937;
            background: #f3f4f6;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .urine-section-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 12px;
        }
        .urine-section-table td {
            border: 1px solid #1f2937;
            padding: 5px 8px;
            vertical-align: middle;
            line-height: 1.25;
            background: #fff;
        }
        .urine-section-table .urine-key {
            width: 50%;
            font-weight: 600;
            text-align: left;
        }
        .urine-section-table .urine-value {
            width: 50%;
            text-align: left;
            font-weight: 600;
        }
        .cbc-report-title {
            display: inline-block;
            max-width: 100%;
            border: 1px solid #111827;
            padding: 2px 18px;
            font-size: 15px;
            font-weight: 700;
            line-height: 1.2;
            white-space: nowrap;
        }
        .cbc-layout .content-section {
            padding-bottom: calc(60px + var(--report-page-bottom-margin, 0px));
        }
        .cbc-layout .header-section {
            height: min(28mm, var(--report-header-height, 115px));
        }
        .cbc-layout .header-placeholder {
            height: min(28mm, var(--report-header-height, 115px));
        }
        .cbc-layout .title-section-table {
            margin-bottom: 5px;
        }
        .cbc-layout .cbc-report-title {
            font-size: 13px;
        }
        .cbc-result-layout {
            position: relative;
            width: 100%;
            padding-bottom: 4px;
        }
        .cbc-result-table {
            width: 100%;
            margin: 0 0 3px;
            table-layout: fixed;
            border-collapse: collapse;
            font-size: calc(11.5px + var(--report-font-size-adjustment, 0px));
            line-height: 1.2;
        }
        .cbc-result-layout.cbc-no-histogram .cbc-result-table {
            width: 100%;
        }
        .cbc-result-table th {
            border: 1px solid #9ca3af;
            padding: 3px 5px;
            background: #e5e7eb;
            text-align: left;
            white-space: nowrap;
            font-size: calc(11.5px + var(--report-font-size-adjustment, 0px));
        }
        .cbc-result-table th:nth-child(3) {
            padding-left: 2px;
        }
        .cbc-data-row {
            display: grid;
            grid-template-columns: 43% 16% 10% 31%;
            align-items: center;
            min-height: 18px;
            font-size: calc(11.5px + var(--report-font-size-adjustment, 0px));
            line-height: 1.3;
        }
        .cbc-data-list { width: 70%; }
        .cbc-result-layout.cbc-no-histogram .cbc-data-list { width: 100%; }
        .cbc-data-cell {
            min-width: 0;
            padding: 1px 4px;
            overflow-wrap: anywhere;
        }
        .cbc-parameter { font-weight: 600; }
        .cbc-result {
            white-space: nowrap;
            padding-right: 2px;
        }
        .cbc-result-value {
            display: block;
            text-align: left;
            padding-right: 2px;
        }
        .cbc-result-value.cbc-result-out {
            font-weight: 700 !important;
        }
        .cbc-result-unit {
            display: block;
            text-align: left;
            white-space: nowrap;
            padding-left: 2px;
        }
        .cbc-group-row {
            font-weight: 700;
            text-transform: uppercase;
            min-height: 18px;
        }
        .cbc-group-row .cbc-group-title {
            grid-column: 1 / span 4;
            padding-left: 4px;
        }
        .cbc-differential-row .cbc-parameter {
            padding-left: 12px;
            font-weight: 400;
        }
        .cbc-histograms {
            position: absolute;
            top: 28px;
            right: 0;
            width: 30%;
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 8px;
        }
        .cbc-histogram {
            min-height: 88px;
            border: 1px solid #9ca3af;
            display: flex;
            width: 100%;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #4b5563;
            padding: 5px;
            font-size: 8px;
        }
        .cbc-histogram svg {
            display: block;
            width: 100%;
            height: 64px;
        }
        .cbc-histogram strong {
            color: #111827;
            margin-bottom: 3px;
            font-size: 9px;
        }
        .cbc-layout .content-section {
            padding-bottom: calc(36px + var(--report-page-bottom-margin, 0px));
        }
        .cbc-layout .signature-row {
            margin-top: var(--cbc-signature-margin-top, 14px);
            margin-bottom: 6px;
            gap: 8px;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .cbc-layout .signature-row .signature-block {
            flex: 1 1 0;
            min-width: 0;
            max-width: 33%;
            font-size: 9px;
            overflow-wrap: anywhere;
        }
        .cbc-layout .signature-row .signature-block img {
            max-width: 100%;
            max-height: 42px !important;
            object-fit: contain;
        }
        .cbc-layout .signature-image-slot {
            flex-basis: 69px;
            height: 69px;
        }
        .cbc-layout .signature-row .name-with-line {
            padding-top: 1.5mm;
            margin-top: 1.5mm;
            font-size: 9px;
        }
        .cbc-layout .signature-row .signature-designation {
            font-size: 8px;
            line-height: 1.15;
            overflow-wrap: anywhere;
        }
        .cbc-blood-film {
            margin-top: 8px;
            font-size: 9px;
            text-decoration: underline;
            font-weight: 700;
        }
        .cbc-blood-film-content {
            margin-top: 2px;
            font-size: 9px;
            line-height: 1.25;
            white-space: pre-line;
            overflow-wrap: anywhere;
        }
        .group-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 11.5px;
            line-height: 1.2;
        }
        .group-table th,
        .group-table td {
            border: 1px solid #1f2937;
            padding: 4px 5px;
            vertical-align: middle;
            line-height: 1.2;
        }
        .group-table th {
            background: #e5e7eb;
            font-weight: 700;
            text-align: left;
            color: #111827;
        }
        .group-table .testname-cell {
            text-align: left;
            font-weight: 600;
        }
        .group-table .result-cell,
        .group-table .unit-cell,
        .group-table .range-cell {
            text-align: center;
        }
        .group-table .range-cell {
            text-align: left;
            padding-left: 8px;
        }
        .compact-meta-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 0 auto 10px auto;
            border: 1px solid #1f2937;
            background: #f8fafc;
            font-size: 11px;
        }
        .compact-meta-table td {
            border: 1px solid #1f2937;
            padding: 3px 6px;
            vertical-align: top;
            line-height: 1.3;
        }
        .compact-meta-table .info-label {
            font-weight: 700;
            width: 18%;
            white-space: nowrap;
        }
        .compact-meta-table .info-colon {
            width: 2%;
            text-align: center;
        }
        .compact-meta-table .info-value {
            width: 30%;
        }
        .report-note-inline {
            margin-top: 4px;
            margin-bottom: 8px;
            text-align: center;
            font-size: 11px;
            font-style: italic;
            color: #374151;
        }
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
        .title-section-table { width: 100%; margin-bottom: 12px; table-layout: fixed; }
        .barcode-cell-left { width: 22.5%; text-align: left; vertical-align: top; }
        .barcode-cell-right { width: 22.5%; text-align: right; vertical-align: top; }
        .title-cell-center { width: 55%; text-align: center; vertical-align: top; }
        .barcode-image { width: 150px; max-width: 100%; height: 34px; display: block; }
        .content-section {
            width: 100%;
            padding-left: var(--report-page-left-margin, 48px);
            padding-right: var(--report-page-right-margin, 48px);
            padding-bottom: calc(110px + var(--report-page-bottom-margin, 0px));
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
        .footer-content {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            width: 100%;
            padding: 0 10mm 0 6mm;
            font-size: 10px;
            line-height: 1.2;
            text-align: left;
            white-space: nowrap;
            transform: translateY({{ -max(-50, min(50, (int) ($reportFooterContentOffset ?? 0))) }}mm);
        }
        .footer-content-main {
            flex: 1 1 auto;
            min-width: 0;
            overflow: hidden;
            text-align: left;
        }
        .footer-content-main > * {
            display: inline-block !important;
            max-width: 100%;
            margin: 0 !important;
            padding: 0 !important;
            vertical-align: middle !important;
        }
        .footer-content-main * { font-size: inherit !important; line-height: inherit !important; }
        .footer-printed-at {
            flex: 0 0 auto;
            text-align: right;
            white-space: nowrap;
        }
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
        .ultra-layout .content-section { padding-bottom: calc(80px + var(--report-page-bottom-margin, 0px)); }
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
        .ultra-layout .signature-image-slot {
            flex-basis: 73px;
            height: 73px;
        }

        /* Paper-size locking to keep report print identical across printers */
        @media print and (min-width: 149mm) {
            .header-section { height: var(--report-header-height, 115px); }
            .header-placeholder { height: var(--report-header-height, 115px); }
            .header-image { height: 100%; }
            .footer-placeholder { height: 70px; }
            .footer-image { max-height: 80px; }
            .content-section { padding-bottom: calc(110px + var(--report-page-bottom-margin, 0px)); }
        }

        @media print and (max-width: 148mm), screen and (max-width: 148mm) {
            body { font-size: 12px; }
            .header-section { height: var(--report-header-height, 115px); }
            .header-placeholder { height: var(--report-header-height, 115px); }
            .header-image { height: 100%; }
            .footer-placeholder { height: 52px; }
            .footer-image { max-height: 56px; }
            .footer-date-time { font-size: 10px; margin-bottom: 2px; }
            .content-section { padding-bottom: calc(88px + var(--report-page-bottom-margin, 0px)); }
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
            margin-left: var(--report-left-margin, 96px);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            width: calc(100% - var(--report-left-margin, 96px));
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
            padding-top: 1.5mm;
            margin-top: 1.5mm;
            white-space: pre-line;
            font-weight: 700; /* force bold */
        }
        .signature-image {
            width: 150px;
            height: 56px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
            mix-blend-mode: multiply;
        }
        .signature-image-slot {
            display: flex;
            flex: 0 0 61px;
            width: 100%;
            height: 61px;
            align-items: flex-end;
            justify-content: center;
        }
        .signature-block .meta {
            font-size: 12px;
            word-break: break-word;
        }
        .signature-line { display: none; }
        .signature-designation { white-space: pre-line; }
        .signature-block .label { min-height: 18px; width: 100%; }
        .signature-block .meta { min-height: 16px; width: 100%; text-align: center; }
        .signature-block .meta.multiline { white-space: pre-line; }
        .no-wrap { white-space: nowrap; }
        .report-institution-pad {
            position: fixed;
            inset: 0;
            width: 100vw;
            height: 100vh;
            max-width: none;
            object-fit: fill;
            z-index: 0;
            pointer-events: none;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        body.with-institution-pad .header-section {
            display: none !important;
        }
        body.with-institution-pad .footer-section {
            position: absolute;
            right: 0;
            bottom: 0;
            left: 0;
            display: flex !important;
        }
        body.with-institution-pad .footer-image { display: none; }
        body.with-institution-pad .content-section {
            position: relative;
            z-index: 1;
            padding-top: {{ max(0, min(100, (int) ($fullPageInstitutionPadContentOffset ?? 0))) }}mm !important;
            padding-bottom: calc({{ max((int) ($reportFooterHeight ?? 70), 40) }}px + var(--report-page-bottom-margin, 0px)) !important;
        }
        @media print {
            body {
                position: static;
                width: auto;
                min-height: 0;
                margin: 0;
                background: #fff;
                box-shadow: none;
            }
            .report-institution-pad {
                position: fixed;
                inset: 0;
                width: 100vw;
                height: 100vh;
            }
            .content-section { padding-bottom: calc(72px + var(--report-page-bottom-margin, 0px)); }
            .footer-section {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
            }
            .ultra-layout .content-section { padding-bottom: calc(56px + var(--report-page-bottom-margin, 0px)); }
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
<body class="{{ ($isUltrasonogramReport ? 'ultra-layout ' : '') . ($isCbcReport ? 'cbc-layout ' : '') . (!empty($fullPageInstitutionPadImage) ? 'with-institution-pad' : '') }}" style="--report-left-margin: {{ $signatureMarginLeft }}px; --signature-top-margin: {{ $signatureMarginTop }}px; --cbc-signature-margin-top: {{ $cbcSignatureMarginTop ?? 14 }}px; --report-page-top-margin: {{ $pageMarginTop }}px; --report-page-bottom-margin: {{ max((int) ($pageMarginBottom ?? 0), 0) }}px; --report-page-left-margin: {{ max((int) ($pageMarginLeft ?? 48), 0) }}px; --report-page-right-margin: {{ max((int) ($pageMarginRight ?? 48), 0) }}px; --report-header-height: {{ $reportHeaderHeight ?? 115 }}px; --report-footer-height: {{ $reportFooterHeight ?? 70 }}px; --report-font-size-adjustment: {{ max(0, min(10, (int) ($reportFontSizeAdjustment ?? 0))) }}px;">
    @if(!empty($fullPageInstitutionPadImage))
        <img src="{{ $fullPageInstitutionPadImage }}" alt="" aria-hidden="true" class="report-institution-pad">
    @endif
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
                @if($isCbcReport)
                    <div class="cbc-report-title">HAEMATOLOGY REPORT</div>
                @else
                    <div class="report-title">{{ strtoupper((string) ($reportTitle ?? 'Test Report')) }}</div>
                @endif
            </td>
            <td class="barcode-cell-right">
                <img src="{{ $barcodeDataUri }}" alt="Barcode Right" class="barcode-image" style="margin-left:auto;">
            </td>
        </tr>
    </table>
    <table class="patient-details-table" style="width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 12px;">
        <colgroup>
            <col style="width:14%;"><col style="width:2%;"><col style="width:34%;">
            <col style="width:14%;"><col style="width:2%;"><col style="width:34%;">
        </colgroup>
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
            <td colspan="4" style="vertical-align: top; padding: 2px 0; white-space: normal; word-break: break-word;">{{ $refd_by }}</td>
        </tr>
    </table>

    @if(!empty($machineInfoLineEnabled) && $machineInfoLineEnabled !== false)
        <div style="margin: 4px 0 12px 0; text-align: center; font-size: 11px; font-style: italic; color: #374151;">{{ $machineInfoLine ?? 'Estimations were carried out by Macfura F-580 Automatic Haematology Analyzer & Verified manually' }}</div>
    @endif

    <div class="section">
        @if($isFullPageReport)
            <div class="ultra-test-name">{{ $primaryItem->item_name ?? 'Ultrasonogram' }}</div>
            <div class="ultra-report-body">{!! $noteLooksHtml ? $primaryNoteBody : nl2br(e($primaryNoteBody)) !!}</div>
            @if(!empty($primaryItem->report_range) && !$isUltrasonogramReport && !$isXrayReport)
                <div class="ultra-range"><strong>Reference:</strong> {{ $primaryItem->report_range }}</div>
            @endif
        @else
            @php
                $splitTrailingUnit = function ($rawValue, $preferredUnit = '') {
                    $value = trim((string) ($rawValue ?? ''));
                    $unit = trim((string) ($preferredUnit ?? ''));

                    if ($value === '') {
                        return ['', $unit];
                    }

                    if (preg_match('/^(?<numeric>.*?)(?:\s+)?(?<unit>[\p{L}µμ%\/]+(?:\s*[\p{L}µμ%\/]+)*)\s*$/u', $value, $m)) {
                        $candidateUnit = trim((string) ($m['unit'] ?? ''));
                        $candidateValue = trim((string) ($m['numeric'] ?? ''));

                        if ($candidateUnit !== '' && preg_match('/[0-9]/u', $candidateValue) === 1) {
                            $value = $candidateValue;
                            $unit = $candidateUnit;
                        }
                    }

                    if ($unit !== '') {
                        $value = preg_replace('/\s*' . preg_quote($unit, '/') . '$/iu', '', $value);
                        $value = trim((string) $value);
                    }

                    return [$value, $unit];
                };

                $paramRows = [];
                foreach ($items as $it) {
                    $params = (!empty($it->printed_parameter_rows) && is_array($it->printed_parameter_rows)) ? $it->printed_parameter_rows : [];
                    if (count($params) > 0) {
                                foreach ($params as $pr) {
                                    // Prefer structured fields if controller provided them
                                    if (array_key_exists('param', $pr) || array_key_exists('value', $pr)) {
                                        $pName = trim((string) ($pr['param'] ?? ''));
                                        $pVal = trim((string) ($pr['value'] ?? ''));
                                    } else {
                                        $rHtml = $pr['result_html'] ?? '';
                                        $pos = strpos($rHtml, ':');
                                        if ($pos !== false) {
                                            $pName = trim(strip_tags(substr($rHtml, 0, $pos)));
                                            $pVal = trim(strip_tags(substr($rHtml, $pos + 1)));
                                        } else {
                                            $pName = '';
                                            $pVal = trim(strip_tags($rHtml));
                                        }
                                    }

                                    $paramRows[] = [
                                        'param'            => $pName ?: trim((string) ($it->item_name ?? '')),
                                        'value'            => $pVal,
                                        'unit'             => $pr['unit'] ?? '',
                                        'range'            => $pr['normal_range'] ?? ($it->report_range ?? ''),
                                        'is_out'           => array_key_exists('is_out', $pr) ? (bool) $pr['is_out'] : null,
                                        'value_num'        => array_key_exists('value_num', $pr) ? $pr['value_num'] : null,
                                        'item_name'        => $it->item_name ?? '',
                                        'report_function'  => $it->report_function ?? '',
                                        'main_category_name' => $it->main_category_name ?? '',
                                    ];
                                }
                    } else {
                        $paramRows[] = [
                            'param' => trim((string) ($it->item_name ?? '')) ?: 'N/A',
                            'value' => trim((string) ($it->report_note ?? '')),
                            'unit' => $it->resolved_print_unit ?? '',
                            'range' => $it->resolved_print_range ?? $it->report_range ?? '',
                            'is_out' => null,
                            'value_num' => null,
                            'item_name' => $it->item_name ?? '',
                            'report_function' => $it->report_function ?? '',
                            'main_category_name' => $it->main_category_name ?? '',
                        ];
                    }
                }

                $allParams = collect($paramRows);

                // Simple keyword-based inference for test function/category (AI-like)
                $inferCategory = function (?string $text, ?string $itemName = null) {
                    $itemText = trim((string) ($itemName ?? ''));
                    $t = trim((string) $text);
                    if ($t === '') return '';

                    // Resolve the function from the complete test name first.
                    // Parameter names are only a fallback because they can be units or abbreviations.
                    $testRules = [
                        'Hematology' => '/\b(cbc|complete blood count|hematology|haematology|blood count)\b/i',
                        'Lipid Profile' => '/\b(lipid|cholesterol|triglyceride|dyslipid)\b/i',
                        'Electrolytes' => '/\b(electrolytes?|s\.electrolytes?|sodium|potassium|chloride|bi-?carbonate)\b/i',
                        'Thyroid Function' => '/\b(tsh|thyroid|thyroxine|free\s*t[34]|ft[34])\b/i',
                        'Glucose' => '/\b(rbs|fbs|blood sugar|glucose|hba1c|sugar)\b/i',
                        'Liver Function' => '/\b(lft|liver|sgpt|sgot|alt|ast|bilirubin|alkaline phosphatase|alp|ggt)\b/i',
                        'Renal Function' => '/\b(rft|renal|kidney|creatinine|urea|bun|uric acid)\b/i',
                        'Cardiac Function' => '/\b(troponin|cardiac|ck-?mb|ckmb|myoglobin)\b/i',
                        'Urinalysis' => '/\b(urine|urinalysis|r\/?e|m\/?e)\b/i',
                        'Coagulation' => '/\b(pt|inr|aptt|bleeding time|clotting time|coagulation)\b/i',
                        'Immunology' => '/\b(ige|igg|igm|iga|antibody|antigen|hiv|hbsag|vdrl|crp)\b/i',
                        'Fertility Function' => '/\b(fsh|lh|prolactin|estrogen|progesterone|testosterone)\b/i',
                    ];

                    foreach ($testRules as $label => $pattern) {
                        if ($itemText !== '' && preg_match($pattern, $itemText) === 1) return $label;
                    }

                    $map = [
                        'Lipid Profile' => '/(?iu)\b(cholesterol|total cholesterol|hdl(-c)?|ldl(-c)?|vldl|tc|tg|triglycerid(e)?|triglyceride|apo b|apob|apo(a)?|lipid|lipoprotein|লিপিড|লিপিড প্রোফাইল)\b/',
                        'Renal Function' => '/(?iu)\b(creat(inine)?|creat\.|creat\b|creatinine|ক্রিটিনাইন|ক্রিয়েটিনিন|urea|bun|blood urea nitrogen|uric acid|egfr|gfr|renal|kidney|কিডনি)\b/',
                        'Glucose' => '/(?iu)\b(rbs|random blood sugar|random sugar|random|glucose|fbs|fasting blood sugar|ppbs|post prandial|hba1c|a1c|blood sugar|sugar|সুগার|শর্করা|রক্তে চিনি)\b/',
                        'Liver Function' => '/(?iu)\b(alt|ast|sgpt|sgot|sgpt\/?sgot|sgot\/?sgpt|bilirubin|alk phos|alkaline phosphatase|alp|ggt|transaminase|lft|liver|liver function|liver panel|যকৃত|লিভার|এসজিপিটি|এসজিওটি)\b/',
                        'Thyroid Function' => '/(?iu)\b(tsh|t3|t4|free t3|free t4|thyroid|ft3|ft4|থাইরয়েড)\b/',
                        'CBC' => '/(?iu)\b(hemoglobin|hb|hematocrit|hct|wbc|rbc|platelet|plt|mpv|mcv|mch|mchc|cbc|esr|differential|হেমোগ্লোবিন|প্লেটলেট)\b/',
                        'Electrolytes' => '/(?iu)\b(sodium|na|potassium|k|chloride|cl|calcium|ca|magnesium|mg|electrolyte|ইলেকট্রোলাইট|সোডিয়াম|পটাশিয়াম)\b/',
                    ];

                    // explicit substring tokens (fast path)
                    $explicit = [
                        'sgpt' => 'Liver Function', 'sgot' => 'Liver Function', 'alt' => 'Liver Function', 'ast' => 'Liver Function', 'এএসজিপিটি' => 'Liver Function', 'এএসজিওটি' => 'Liver Function',
                        'creatinine' => 'Renal Function', 'ক্রিটিনাইন' => 'Renal Function', 'urea' => 'Renal Function', 'bun' => 'Renal Function',
                        // Ensure uric acid variants map to Renal Function
                        'uric acid' => 'Renal Function', 'uricacid' => 'Renal Function', 'uric' => 'Renal Function', 'ইউরিক' => 'Renal Function',
                        'rbs' => 'Glucose', 'hba1c' => 'Glucose', 'a1c' => 'Glucose', 'glucose' => 'Glucose', 'সুগার' => 'Glucose', 'শর্করা' => 'Glucose',
                        'cholesterol' => 'Lipid Profile', 'hdl' => 'Lipid Profile', 'ldl' => 'Lipid Profile', 'triglyceride' => 'Lipid Profile', 'ট্রাইগ্লিসারাইড' => 'Lipid Profile', 'লিপিড' => 'Lipid Profile',
                        // Liver tokens: include alkaline phosphatase variants
                        'alk phos' => 'Liver Function', 'alk-phos' => 'Liver Function', 'alkaline phosphatase' => 'Liver Function', 'alp' => 'Liver Function', 'ggt' => 'Liver Function',
                        // Thyroid tokens
                        'tsh' => 'Thyroid Function', 't3' => 'Thyroid Function', 't4' => 'Thyroid Function', 'ft3' => 'Thyroid Function', 'ft4' => 'Thyroid Function', 'thyroid' => 'Thyroid Function', 'thyroxine' => 'Thyroid Function',
                        // FSH variants (fertility)
                        'fsh' => 'Fertility Function', 'এফএসএইচ' => 'Fertility Function', 'এফ এস এইচ' => 'Fertility Function',
                        // Bone marrow => Hematology / CBC
                        'bone marrow' => 'CBC', 'bone-marrow' => 'CBC', 'marrow' => 'CBC', 'বোন ম্যারো' => 'CBC', 'বোনম্যারো' => 'CBC',
                    ];

                    foreach ($explicit as $token => $lbl) {
                        try {
                            if (mb_stripos($t, $token) !== false) return $lbl;
                        } catch (\Throwable $_) {
                        }
                    }

                    foreach ($map as $label => $pattern) {
                        try {
                            if (@preg_match($pattern, $t) === 1) return $label;
                        } catch (\Throwable $_) {
                        }
                    }

                    // Fallback: synthesize a label from the test name so every test gets a function
                    try {
                        if ($t !== '' && preg_match_all('/[\p{L}\p{N}]{2,}/u', $t, $m) && !empty($m[0])) {
                            usort($m[0], function ($a, $b) { return mb_strlen($b) <=> mb_strlen($a); });
                            $tok = $m[0][0];
                            $gen = (mb_strtoupper($tok, 'UTF-8') === $tok) ? $tok : mb_convert_case($tok, MB_CASE_TITLE, 'UTF-8');
                            return $gen;
                        }
                    } catch (\Throwable $_) {
                        // ignore
                    }

                    return 'Misc Tests';
                };

                $lipid = $allParams->filter(function ($r) {
                    return preg_match('/cholesterol|triglycerid|triglyceride|hdl|ldl|vldl|lipid/i', $r['param'] . ' ' . $r['item_name']);
                })->values();

                $creatinine = $allParams->filter(function ($r) {
                    return preg_match('/creatinine/i', $r['param'] . ' ' . $r['item_name']);
                })->values();

                $rbs = $allParams->filter(function ($r) {
                    return preg_match('/\brbs\b|random blood sugar|random sugar|glucose|blood sugar|sugar/i', $r['param'] . ' ' . $r['item_name']);
                })->values();

                $others = $allParams->reject(function ($r) use ($lipid, $creatinine, $rbs) {
                    $key = $r['param'] . '|' . $r['item_name'];
                    return $lipid->contains(fn($x) => ($x['param'] . '|' . $x['item_name']) === $key)
                        || $creatinine->contains(fn($x) => ($x['param'] . '|' . $x['item_name']) === $key)
                        || $rbs->contains(fn($x) => ($x['param'] . '|' . $x['item_name']) === $key);
                })->values();
            @endphp

            {{-- Group tests by inferred function/category and render each group once --}}
            @php
                $groupedByFunction = $allParams->groupBy(function ($r) use ($inferCategory) {
                    // Priority: report_function → inferred from test name → main_category_name
                    // main_category_name is only used for the report title, NOT the group heading.
                    $lbl = trim((string) ($r['report_function'] ?? ''))
                        ?: $inferCategory($r['param'] ?? '', $r['item_name'] ?? '')
                        ?: trim((string) ($r['main_category_name'] ?? ''));
                    return $lbl ?: 'Other Tests';
                });
            @endphp

            @php
                $reportTextForPattern = strtolower(trim((string) ($reportTitle ?? '') . ' ' . ($primaryItem->item_name ?? '') . ' ' . ($primaryItem->category ?? '')));
                $isUrineReport = preg_match('/\b(urine|urinalysis|r\/?e|m\/?e)\b/i', $reportTextForPattern) === 1;

                $urineSectionTitles = ['Physical Examination', 'Chemical Examination', 'Microscopic Examination'];
                $urineSectionMap = [];
                foreach ($urineSectionTitles as $sectionTitle) {
                    $urineSectionMap[$sectionTitle] = [];
                }

                if ($isUrineReport) {
                    foreach ($allParams as $row) {
                        $rowName = strtolower(trim((string) ($row['param'] ?? '')));
                        $section = 'Microscopic Examination';
                        if (preg_match('/\b(color|colour|appearance|sediment|specific gravity|sg)\b/i', $rowName)) {
                            $section = 'Physical Examination';
                        } elseif (preg_match('/\b(reaction|phosphate|albumin|sugar|bile salt|bile pigment|ketone|bilirubin)\b/i', $rowName)) {
                            $section = 'Chemical Examination';
                        } elseif (preg_match('/\b(pus|epithelial|rbc|cast|bacteria|hyaline|crystal|oxalate|triple|uric|amorphous)\b/i', $rowName)) {
                            $section = 'Microscopic Examination';
                        }
                        $urineSectionMap[$section][] = $row;
                    }
                }
            @endphp

            @if($isCbcReport)
                <div class="cbc-result-layout {{ $showCbcHistograms ? '' : 'cbc-no-histogram' }}">
                    @php
                        $bloodFilmRow = $allParams->first(function ($row) {
                            return strtolower(preg_replace('/[^a-z0-9]+/i', '', trim((string) ($row['param'] ?? '')))) === 'bloodfilm';
                        });
                        $bloodFilmText = trim((string) ($bloodFilmRow['value'] ?? ''));
                        $cbcParams = $allParams->reject(function ($row) {
                            return strtolower(preg_replace('/[^a-z0-9]+/i', '', trim((string) ($row['param'] ?? '')))) === 'bloodfilm';
                        });
                        $cbcParameterOrder = [
                            'hemoglobin',
                            'esr',
                            'totalwbccount',
                            'differentialcount',
                            'neutrophils',
                            'lymphocytes',
                            'monocytes',
                            'eosinophils',
                            'basophils',
                            'blastatypical',
                            'myelocytes',
                            'totalcireosinophils',
                            'totalrbccount',
                            'hctpcv',
                            'mcv',
                            'mch',
                            'mchc',
                            'rdwcv',
                            'rdwsd',
                            'totalplateletcount',
                            'mpv',
                            'pct',
                            'pdw',
                            'plcr',
                            'bt',
                            'ct',
                        ];
                        $cbcParameterAliases = [
                            'haemoglobin' => 'hemoglobin',
                            'haemoglobinhb' => 'hemoglobin',
                            'hemoglobinhb' => 'hemoglobin',
                            'hb' => 'hemoglobin',
                            'hgb' => 'hemoglobin',
                            'esrwestergren' => 'esr',
                            'wbccount' => 'totalwbccount',
                            'whitebloodcellcount' => 'totalwbccount',
                            'differential' => 'differentialcount',
                            'neutrophil' => 'neutrophils',
                            'lymphocyte' => 'lymphocytes',
                            'monocyte' => 'monocytes',
                            'eosinophil' => 'eosinophils',
                            'basophil' => 'basophils',
                            'atypicalcell' => 'blastatypical',
                            'blastatypicalcell' => 'blastatypical',
                            'blastatypicalpercent' => 'blastatypical',
                            'blastyatypical' => 'blastatypical',
                            'blastyatypicalcell' => 'blastatypical',
                            'blastyatypicalpercent' => 'blastatypical',
                            'myelocytespercent' => 'myelocytes',
                            'totalcireosinophil' => 'totalcireosinophils',
                            'totalcirculatingeosinophils' => 'totalcireosinophils',
                            'rbccount' => 'totalrbccount',
                            'hematocrit' => 'hctpcv',
                            'plateletcount' => 'totalplateletcount',
                            'bleedingtime' => 'bt',
                            'clottingtime' => 'ct',
                        ];
                        $cbcParameterLabels = [
                            'hemoglobin' => 'Haemoglobin(Hb)',
                            'esr' => 'ESR(Westergren)',
                            'totalwbccount' => 'TOTAL WBC COUNT',
                            'differentialcount' => 'DIFFERENTIAL COUNT',
                            'neutrophils' => 'Neutrophils',
                            'lymphocytes' => 'Lymphocytes',
                            'monocytes' => 'Monocytes',
                            'eosinophils' => 'Eosinophils',
                            'basophils' => 'Basophils',
                            'blastatypical' => 'Blast/Atypical Cell',
                            'myelocytes' => 'Myelocytes',
                            'totalcireosinophils' => 'Total Cir. Eosinophil',
                            'totalrbccount' => 'TOTAL RBC COUNT',
                            'hctpcv' => 'HCT/PCV',
                            'mcv' => 'MCV',
                            'mch' => 'MCH',
                            'mchc' => 'MCHC',
                            'rdwcv' => 'RDW-CV',
                            'rdwsd' => 'RDW-SD',
                            'totalplateletcount' => 'TOTAL PLATELET COUNT',
                            'mpv' => 'MPV',
                            'pct' => 'PCT',
                            'pdw' => 'PDW',
                            'plcr' => 'PLCR',
                            'bt' => 'Bleeding Time (BT)',
                            'ct' => 'Clotting Time (CT)',
                        ];
                        $cbcRows = $cbcParams->values()->map(function ($row, $index) use ($cbcParameterAliases, $cbcParameterLabels, $cbcParameterOrder) {
                            $originalName = trim((string) ($row['param'] ?? ''));
                            $normalizedName = strtolower(preg_replace('/[^a-z0-9]+/i', '', $originalName));
                            $canonicalName = $cbcParameterAliases[$normalizedName] ?? $normalizedName;
                            $displayName = $cbcParameterLabels[$canonicalName] ?? $originalName;
                            $order = array_search($canonicalName, $cbcParameterOrder, true);

                            $row['display_param'] = $displayName;
                            $row['is_section_heading'] = $canonicalName === 'differentialcount';
                            $row['_display_order'] = $order === false ? count($cbcParameterOrder) + $index : $order;

                            return $row;
                        })->sortBy('_display_order')->values();
                    @endphp
                    <table class="cbc-result-table">
                        <colgroup>
                            @if($showCbcHistograms)
                                <col style="width:30%">
                                <col style="width:11%">
                                <col style="width:8%">
                                <col style="width:21%">
                                <col style="width:30%">
                            @else
                                <col style="width:43%">
                                <col style="width:16%">
                                <col style="width:10%">
                                <col style="width:31%">
                            @endif
                        </colgroup>
                        <thead>
                            <tr>
                                <th>PARAMETER</th>
                                <th>RESULT</th>
                                <th>UNIT</th>
                                <th>REFERENCE RANGE</th>
                                @if($showCbcHistograms)
                                    <th>HISTOGRAM</th>
                                @endif
                            </tr>
                        </thead>
                    </table>
                    <div class="cbc-data-list">
                    @foreach($cbcRows as $row)
                        @php
                            $parameterName = trim((string) ($row['display_param'] ?? $row['param'] ?? ''));
                            $valueRaw = trim((string) ($row['value'] ?? ''));
                            $rangeRaw = trim((string) ($row['range'] ?? ''));
                            $unitText = trim((string) ($row['unit'] ?? ''));
                            if ($rangeRaw === $unitText) {
                                $rangeRaw = '';
                            }
                            [$resultDisplay, $unitText] = $splitTrailingUnit($valueRaw, $unitText);
                            [$rangeValue, $rangeUnit] = $splitTrailingUnit($rangeRaw, $unitText);
                            $rangeDisplay = $rangeValue !== '' ? $rangeValue : $rangeRaw;
                            if ($rangeUnit !== '') {
                                $rangeDisplay = trim($rangeDisplay . ' ' . $rangeUnit);
                            }
                            $outside = isset($row['is_out']) && $row['is_out'] !== null
                                ? (bool) $row['is_out']
                                : false;
                            $parseReferenceNumber = static function (string $number): float {
                                $normalizedNumber = preg_match('/^[+-]?\d{1,3}(?:,\d{3})+$/', $number)
                                    ? str_replace(',', '', $number)
                                    : str_replace(',', '.', $number);

                                return (float) $normalizedNumber;
                            };
                            $valueNumber = $row['value_num'] ?? null;
                            if ($valueNumber === null && preg_match('/[+-]?[0-9]+(?:[\.,][0-9]+)?/u', $valueRaw, $valueMatch)) {
                                $valueNumber = $parseReferenceNumber($valueMatch[0]);
                            }
                            if (!isset($row['is_out']) || $row['is_out'] === null) {
                                if ($rangeRaw !== '' && $valueNumber !== null) {
                                    $normalizedRange = preg_replace('/[–—]/u', '-', $rangeRaw);
                                    if (preg_match('/([<>]=?)\s*([+-]?[0-9]+(?:[\.,][0-9]+)?)/u', $normalizedRange, $rangeMatch)) {
                                        $operator = $rangeMatch[1];
                                        $limit = $parseReferenceNumber($rangeMatch[2]);
                                        $outside = match ($operator) {
                                            '<' => !($valueNumber < $limit),
                                            '<=' => !($valueNumber <= $limit),
                                            '>' => !($valueNumber > $limit),
                                            '>=' => !($valueNumber >= $limit),
                                        };
                                    } elseif (preg_match_all('/[+-]?[0-9]+(?:[\.,][0-9]+)?/u', $normalizedRange, $rangeNumbers) && count($rangeNumbers[0]) >= 2) {
                                        $minimum = $parseReferenceNumber($rangeNumbers[0][0]);
                                        $maximum = $parseReferenceNumber($rangeNumbers[0][1]);
                                        $outside = $valueNumber < $minimum || $valueNumber > $maximum;
                                    }
                                } elseif ($rangeRaw !== '' && !preg_match('/[0-9]/', $rangeRaw)) {
                                    $outside = mb_strtolower($valueRaw) !== mb_strtolower($rangeRaw);
                                }
                            }
                            $normalizedParameterName = strtolower(preg_replace('/[^a-z0-9]+/i', '', $parameterName));
                            $rowClass = !empty($row['is_section_heading'])
                                ? 'cbc-group-row'
                                : (in_array($normalizedParameterName, [
                                    'neutrophils', 'lymphocytes', 'monocytes', 'eosinophils', 'basophils',
                                    'blastatypicalcell', 'blastatypical', 'myelocytes',
                                ], true) ? 'cbc-differential-row' : '');
                        @endphp
                        @if(!empty($row['is_section_heading']))
                            <div class="cbc-data-row {{ $rowClass }}">
                                <div class="cbc-group-title">{{ $parameterName ?: '-' }}</div>
                            </div>
                        @else
                            <div class="cbc-data-row {{ $rowClass }}">
                                <div class="cbc-data-cell cbc-parameter">{{ $parameterName ?: '-' }}</div>
                                <div class="cbc-data-cell cbc-result cbc-result-value {{ $outside ? 'cbc-result-out' : '' }}">
                                    {{ $resultDisplay ?: '-' }}
                                </div>
                                <div class="cbc-data-cell cbc-result cbc-result-unit">{{ $unitText }}</div>
                                <div class="cbc-data-cell cbc-range">{{ $rangeDisplay }}</div>
                            </div>
                        @endif
                    @endforeach
                    </div>
                    @if($showCbcHistograms)
                        <aside class="cbc-histograms" aria-label="Analyzer histograms">
                            @foreach($cbcHistogramData as $histogramLabel => $histogramValues)
                                @php
                                    $histogramMax = max($histogramValues);
                                    $histogramMax = $histogramMax > 0 ? $histogramMax : 1;
                                    $histogramLastIndex = max(count($histogramValues) - 1, 1);
                                    $histogramPoints = collect($histogramValues)->map(function ($value, $index) use ($histogramLastIndex, $histogramMax) {
                                        $x = ($index / $histogramLastIndex) * 190 + 5;
                                        $y = 62 - ((max((float) $value, 0) / $histogramMax) * 50);
                                        return number_format($x, 1, '.', '') . ',' . number_format($y, 1, '.', '');
                                    })->implode(' ');
                                @endphp
                                <div class="cbc-histogram">
                                    <strong>{{ $histogramLabel }}</strong>
                                    <svg viewBox="0 0 200 70" role="img" aria-label="{{ $histogramLabel }} histogram">
                                        <polyline points="{{ $histogramPoints }}" fill="none" stroke="#111827" stroke-width="2" />
                                    </svg>
                                </div>
                            @endforeach
                        </aside>
                    @endif
                </div>
                @if($bloodFilmText !== '')
                    <div class="cbc-blood-film">Blood Film:</div>
                    <div class="cbc-blood-film-content">{{ $bloodFilmText }}</div>
                @endif
            @elseif($isUrineReport)
                <div class="urine-report-title">Urine Examination Report</div>
                @foreach($urineSectionTitles as $sectionTitle)
                    @if(!empty($urineSectionMap[$sectionTitle]))
                        <div class="urine-section-shell">
                            <div class="urine-section-header">{{ $sectionTitle }}</div>
                            <table class="urine-section-table">
                                <tbody>
                                    @foreach($urineSectionMap[$sectionTitle] as $row)
                                        @php
                                            $rowLabel = trim((string) ($row['param'] ?? ''));
                                            $rowValue = trim((string) ($row['value'] ?? ''));
                                            $rowUnit = trim((string) ($row['unit'] ?? ''));
                                            $rowDisplayValue = $rowValue !== '' ? $rowValue : '-';
                                            if ($rowUnit !== '') {
                                                $rowDisplayValue .= ' ' . $rowUnit;
                                            }
                                        @endphp
                                        <tr>
                                            <td class="urine-key">{{ $rowLabel ?: '-' }}</td>
                                            <td class="urine-value">{{ $rowDisplayValue }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endforeach
            @else
                @foreach($groupedByFunction as $label => $rows)
                    <div class="group-title">{{ $label }}</div>
                    <table class="group-table">
                        <colgroup>
                            <col style="width:42%" />
                            <col style="width:18%" />
                            <col style="width:15%" />
                            <col style="width:25%" />
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="testname-cell">Test</th>
                                <th class="result-cell">Result</th>
                                <th class="unit-cell">Unit</th>
                                <th class="range-cell">Reference Range</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                @php
                                    $valRaw = trim((string) ($row['value'] ?? ''));
                                    $rangeRaw = trim((string) ($row['range'] ?? ''));
                                    $unitText = trim((string) data_get($row, 'unit', ''));
                                    if ($unitText === '' && preg_match('/(?<unit>[\p{L}µμ%\/]+(?:\s*[\p{L}µμ%\/]+)*)\s*$/u', $rangeRaw, $unitMatch)) {
                                        $unitText = trim((string) ($unitMatch['unit'] ?? ''));
                                    }
                                    [$resultDisplay, $unitText] = $splitTrailingUnit($valRaw, $unitText);
                                    [$rangeValue, $rangeUnit] = $splitTrailingUnit($rangeRaw, $unitText);
                                    $rangeDisplay = $rangeValue !== '' ? $rangeValue : $rangeRaw;
                                    if ($rangeUnit !== '') {
                                        $rangeDisplay = trim($rangeDisplay . ' ' . $rangeUnit);
                                    }

                                    $outside = isset($row['is_out']) && $row['is_out'] !== null ? (bool) $row['is_out'] : false;

                                    $valNum = $row['value_num'] ?? null;
                                    if ($valNum === null && preg_match('/[+-]?[0-9]+(?:[\.,][0-9]+)?/u', $valRaw, $vm)) {
                                        $valNum = floatval(str_replace(',', '.', $vm[0]));
                                    }

                                    if (!isset($row['is_out']) || $row['is_out'] === null) {
                                        if ($rangeRaw !== '') {
                                            if ($valNum === null && !preg_match('/[0-9]/', $rangeRaw)) {
                                                if (mb_strtolower($valRaw) !== mb_strtolower($rangeRaw)) {
                                                    $outside = true;
                                                }
                                            } elseif ($valNum !== null) {
                                                $r = preg_replace('/[–—]/u', '-', $rangeRaw);
                                                if (preg_match('/([<>]=?)\s*([+-]?[0-9]+(?:[\.,][0-9]+)?)/u', $r, $m2)) {
                                                    $op = $m2[1];
                                                    $limit = floatval(str_replace(',', '.', $m2[2]));
                                                    if ($op === '<' && !($valNum < $limit)) $outside = true;
                                                    if ($op === '<=' && !($valNum <= $limit)) $outside = true;
                                                    if ($op === '>' && !($valNum > $limit)) $outside = true;
                                                    if ($op === '>=' && !($valNum >= $limit)) $outside = true;
                                                } else {
                                                    if (preg_match_all('/[+-]?[0-9]+(?:[\.,][0-9]+)?/u', $r, $nums) && count($nums[0]) >= 2) {
                                                        $min = floatval(str_replace(',', '.', $nums[0][0]));
                                                        $max = floatval(str_replace(',', '.', $nums[0][1]));
                                                        if ($valNum < $min || $valNum > $max) $outside = true;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                @endphp
                                <tr>
                                    <td class="testname-cell">{{ $row['param'] }}</td>
                                    <td class="result-cell">@if($outside)<span style="font-weight:700">{{ $resultDisplay !== '' ? $resultDisplay : '-' }}</span>@else{{ $resultDisplay !== '' ? $resultDisplay : '-' }}@endif</td>
                                    <td class="unit-cell">{{ $unitText ?: '-' }}</td>
                                    <td class="range-cell">{{ $rangeDisplay ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach
            @endif
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
                    <div class="signature-image-slot">
                        @if($technologistSignature)
                            <img class="signature-image" src="{{ $technologistSignature }}" alt="tech-sign" />
                        @endif
                    </div>
                    <div class="signature-name"><span class="name-with-line">{{ $technologistNameSetting ?: ($primaryItem->reportedBy?->name ?? '') }}</span></div>
                    <div class="signature-designation">{{ $technologistDesignationSetting ?: ($primaryItem->reportedBy?->designation ?? '') }}</div>
                </div>

                <div class="signature-block">
                    <div class="signature-image-slot">
                        @if($sampleCollectedBySignature)
                            <img class="signature-image" src="{{ $sampleCollectedBySignature }}" alt="sample-sign" />
                        @endif
                    </div>
                    <div class="signature-name"><span class="name-with-line">{{ $sampleCollectedByNameSetting ?: ($primaryItem->sampleCollectedBy?->name ?? '') }}</span></div>
                    <div class="signature-designation">{{ $sampleCollectedByDesignationSetting ?: ($primaryItem->sampleCollectedBy?->designation ?? '') }}</div>
                </div>

                <div class="signature-block">
                    <div class="signature-image-slot">
                        @if($pathologistSignature)
                            <img class="signature-image" src="{{ $pathologistSignature }}" alt="path-sign" />
                        @endif
                    </div>
                    <div class="signature-name"><span class="name-with-line">{{ $pathologistName ?? '' }}</span></div>
                    <div class="signature-designation">{{ $pathologistDesignation ?? '' }}</div>
                </div>
            @else
                <div class="signature-block">
                    <div class="signature-image-slot">
                        @if($sampleCollectedBySignature)
                            <img class="signature-image" src="{{ $sampleCollectedBySignature }}" alt="sample-sign" />
                        @endif
                    </div>
                    <div class="signature-name"><span class="name-with-line">{{ $sampleCollectedByNameSetting ?: ($primaryItem->sampleCollectedBy?->name ?? '') }}</span></div>
                    <div class="signature-designation">{{ $sampleCollectedByDesignationSetting ?: ($primaryItem->sampleCollectedBy?->designation ?? '') }}</div>
                </div>

                <div class="signature-block">
                    <div class="signature-image-slot"></div>
                    <div class="signature-name"></div>
                    <div class="signature-designation"></div>
                </div>

                <div class="signature-block" style="text-align:right;">
                    <div class="signature-image-slot">
                        @if($technologistSignature)
                            <img class="signature-image" src="{{ $technologistSignature }}" alt="tech-sign" />
                        @endif
                    </div>
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
            $footerDisplayContent = trim((string) ($footer_content ?: ($footerHtml ?: $footerFallbackLine)));
        @endphp

        @if(!empty($footer_image))
            @if(($footer_content_position ?? 'above') === 'below')
                <img src="{{ $footer_image }}" alt="Footer" class="footer-image">
                @include('backend.reporting.partials.footer-content', ['content' => $footerDisplayContent, 'printedAt' => $footerPrintedAt])
            @else
                @include('backend.reporting.partials.footer-content', ['content' => $footerDisplayContent, 'printedAt' => $footerPrintedAt])
                <img src="{{ $footer_image }}" alt="Footer" class="footer-image">
            @endif
        @else
            @include('backend.reporting.partials.footer-content', ['content' => $footerDisplayContent, 'printedAt' => $footerPrintedAt])
        @endif
    </div>

</body>

@if (empty($isPdf) || !$isPdf)
<script>
    window.addEventListener('load', function () {
        var fontSizeAdjustment = {{ max(0, min(10, (int) ($reportFontSizeAdjustment ?? 0))) }};
        if (fontSizeAdjustment !== 0) {
            var elements = Array.prototype.slice.call(document.body.querySelectorAll('*')).filter(function (element) {
                return element.textContent.trim() !== '';
            });
            var fontSizes = elements.map(function (element) {
                return parseFloat(window.getComputedStyle(element).fontSize);
            });

            elements.forEach(function (element, index) {
                if (element.closest('.cbc-layout')) {
                    return;
                }
                if (Number.isFinite(fontSizes[index])) {
                    element.style.setProperty('font-size', (fontSizes[index] + fontSizeAdjustment) + 'px', 'important');
                }
            });

            document.querySelectorAll('.report-title, .cbc-report-title').forEach(function (title) {
                var availableWidth = title.parentElement ? title.parentElement.clientWidth : 0;
                if (!availableWidth) return;

                var fontSize = parseFloat(window.getComputedStyle(title).fontSize);
                while (title.scrollWidth > availableWidth && fontSize > 10) {
                    fontSize -= 0.5;
                    title.style.setProperty('font-size', fontSize + 'px', 'important');
                }
            });
        }

        // update any printing datetime placeholders to the current browser time
        function formatPrintDate(d) {
            try {
                var dd = String(d.getDate()).padStart(2, '0');
                var monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                var m = monthNames[d.getMonth()];
                var yyyy = d.getFullYear();
                var hh = d.getHours();
                var ampm = hh >= 12 ? 'PM' : 'AM';
                hh = hh % 12; hh = hh ? hh : 12; // 0 -> 12
                hh = String(hh).padStart(2, '0');
                var min = String(d.getMinutes()).padStart(2, '0');
                var sec = String(d.getSeconds()).padStart(2, '0');
                return dd + '-' + m + '-' + yyyy + ' ' + hh + ':' + min + ':' + sec + ' ' + ampm;
            } catch (e) { return d.toLocaleString(); }
        }

        var els = document.querySelectorAll('.print-datetime');
        if (els && els.length) {
            var now = new Date();
            var txt = formatPrintDate(now);
            els.forEach(function (el) { el.textContent = txt; });
        }

        @if($trackPrintedDelivery ?? false)
        var deliveryRequestSent = false;
        window.addEventListener('afterprint', function () {
            if (deliveryRequestSent) return;
            deliveryRequestSent = true;

            var deliveryUrls = @json($printedDeliveryUrls ?? []);
            var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            Promise.all(deliveryUrls.map(function (url) {
                return fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                }).then(function (response) {
                    if (!response.ok) {
                        return response.json().then(function (data) {
                            throw new Error(data.message || 'Could not update report delivery status.');
                        });
                    }
                });
            })).catch(function (error) {
                console.error('Report delivery status update failed:', error);
            });
        }, { once: true });
        @endif

        setTimeout(function () { window.print(); }, 180);
    });
</script>
@endif

</html>
