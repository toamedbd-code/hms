<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Billing Invoice</title>
    <style>
        @php
            $fontSrc = !empty($banglaFontUrl) ? $banglaFontUrl : '';
            $fontLocalPath = !empty($banglaFontPath) ? (string) $banglaFontPath : '';
        @endphp
        @php
            $solaimanSrc = '';
            $solaimanLocalPath = '';
            $solaimanPath = public_path('fonts/SolaimanLipi.ttf');
            if (is_file($solaimanPath)) {
                $solaimanLocalPath = str_replace('\\', '/', $solaimanPath);
                $solaimanSrc = asset('fonts/SolaimanLipi.ttf');
            }
        @endphp

        @if(!empty($fontSrc) || !empty($solaimanSrc))
            @php
                $fontDataUri = null;
                $candidatePath = '';
                if ($fontLocalPath !== '' && is_file($fontLocalPath)) {
                    $candidatePath = $fontLocalPath;
                } else {
                    $fallbackPath = str_replace('\\', '/', public_path('fonts/NotoSansBengali-Regular.ttf'));
                    if (is_file($fallbackPath)) {
                        $candidatePath = $fallbackPath;
                    }
                }

                if ($candidatePath !== '') {
                    try {
                        $raw = file_get_contents($candidatePath);
                        if ($raw !== false) {
                            $b64 = base64_encode($raw);
                            $fontDataUri = 'data:font/ttf;base64,' . $b64;
                        }
                    } catch (\Throwable $e) {
                        $fontDataUri = null;
                    }
                }
            @endphp

            @if(!empty($fontDataUri))
                @font-face {
                    font-family: 'NotoSansBengali';
                    src: url('{{ $fontDataUri }}') format('truetype');
                    font-weight: normal;
                    font-style: normal;
                }
            @elseif(!empty($fontSrc))
                @font-face {
                    font-family: 'NotoSansBengali';
                    src: url('{{ $fontSrc }}') format('truetype');
                    font-weight: normal;
                    font-style: normal;
                }
            @endif

            @php
                $solaimanDataUri = null;
                if (!empty($solaimanLocalPath) && is_file($solaimanLocalPath)) {
                        try {
                            $rawSol = file_get_contents($solaimanLocalPath);
                            if ($rawSol !== false) {
                                $solaimanDataUri = 'data:font/ttf;base64,' . base64_encode($rawSol);
                            }
                        } catch (\Throwable $e) {
                            $solaimanDataUri = null;
                        }
                }
            @endphp

            @if(!empty($solaimanDataUri))
                @font-face {
                    font-family: 'SolaimanLipi';
                    src: url('{{ $solaimanDataUri }}') format('truetype');
                    font-weight: normal;
                    font-style: normal;
                }
            @elseif(!empty($solaimanSrc))
                @font-face {
                    font-family: 'SolaimanLipi';
                    src: url('{{ $solaimanSrc }}') format('truetype');
                    font-weight: normal;
                    font-style: normal;
                }
            @endif
        @endif
        @php
            // Determine whether to show header/footer; prefer controller-provided variable
            $__inv_show = isset($showHeaderFooter) ? (bool) $showHeaderFooter : (isset($show_header_footer) ? (bool) $show_header_footer : null);
            if ($__inv_show === null) {
                $__ws = function_exists('get_cached_web_setting') ? get_cached_web_setting() : null;
                $__attendance = is_array($__ws?->attendance_device_options) ? $__ws->attendance_device_options : (is_string($__ws?->attendance_device_options) && trim($__ws?->attendance_device_options) !== '' ? json_decode($__ws?->attendance_device_options, true) : []);
                $__reporting = is_array($__attendance) ? data_get($__attendance, 'reporting', []) : [];
                $__settingShowHeader = array_key_exists('show_header', $__reporting) ? (bool) $__reporting['show_header'] : null;
                $__settingShowFooter = array_key_exists('show_footer', $__reporting) ? (bool) $__reporting['show_footer'] : null;
                if ($__settingShowHeader !== null || $__settingShowFooter !== null) {
                    $__inv_show = ($__settingShowHeader ?? true) && ($__settingShowFooter ?? true);
                } else {
                    $__inv_show = array_key_exists('show_header_footer', $__reporting) ? (bool) $__reporting['show_header_footer'] : true;
                }
                $__layout = data_get($__reporting, 'layout', []);
            } else {
                $__layout = [];
            }

            // Prefer reporting layout heights if controller passed them, otherwise fall back to template vars
            $__inv_reportHeaderH = isset($reportHeaderHeight) ? (int) $reportHeaderHeight : null;
            $__inv_reportFooterH = isset($reportFooterHeight) ? (int) $reportFooterHeight : null;

            $__inv_header_h = $__inv_reportHeaderH ?? (int) ($header_height ?? ($__layout['header_height'] ?? 115));
            $__inv_footer_h = $__inv_reportFooterH ?? (int) ($footer_height ?? ($__layout['footer_height'] ?? 70));

            $__inv_footer_fallback_line = trim((string) config('app.invoice_footer_fallback_line', 'Powered By: www.toamedit.com Support: 01919-592638'));
            $__inv_footer_printed_at = trim((string) ($printed_at ?? ''));
            $__inv_footer_font_size = max(6, min(72, (int) ($footer_font_size ?? 14)));
            $__inv_footer_content_plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) ($footer_content ?? ''))));
            $__inv_footer_content_plain = (string) preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $__inv_footer_content_plain);
            $__inv_footer_content_plain = (string) preg_replace('/রুম\s*(নং|নম্বর|নাম্বার)\s*/u', 'রুম নং ', $__inv_footer_content_plain);
            $__inv_footer_content_plain = (string) preg_replace('/প্যা\s*থা?\s*ল\s*জ[ীি]?/u', 'প্যাথলজি', $__inv_footer_content_plain);
            $__inv_footer_content_plain = str_replace(['،', '٬', '﹐', '，'], ',', $__inv_footer_content_plain);
            $__inv_footer_parts = preg_split('/,+/u', $__inv_footer_content_plain) ?: [];
            $__inv_footer_parts = array_values(array_filter(array_map(static function ($part) {
                return trim((string) $part);
            }, $__inv_footer_parts), static function ($part) {
                return $part !== '';
            }));
            $__inv_footer_content_plain = !empty($__inv_footer_parts) ? implode(', ', $__inv_footer_parts) : $__inv_footer_content_plain;
            $__inv_footer_content_plain = trim((string) preg_replace('/[,.;:!?\x{0964}\x{0965}]+$/u', '', $__inv_footer_content_plain));
            $__inv_footer_has_meta_line = ($__inv_footer_fallback_line !== '' || $__inv_footer_printed_at !== '');
            $__inv_footer_extra_h = 0;
            if ($__inv_footer_content_plain !== '') {
                $charsPerLine = max(22, (int) floor(760 / max($__inv_footer_font_size, 10)));
                $lineHeightPx = max(14, (int) ceil($__inv_footer_font_size * 1.25));
                $estimatedLinesByChars = (int) ceil(mb_strlen($__inv_footer_content_plain, 'UTF-8') / $charsPerLine);
                $estimatedLinesByItems = !empty($__inv_footer_parts) ? (int) ceil(count($__inv_footer_parts) / 3) : 1;
                $estimatedLines = max(1, $estimatedLinesByChars, $estimatedLinesByItems);
                $__inv_footer_extra_h += ($estimatedLines * $lineHeightPx);
            }
            if ($__inv_footer_has_meta_line) {
                $__inv_footer_extra_h += 18;
            }
            $__inv_footer_reserved_h = $__inv_footer_h + $__inv_footer_extra_h + 4;

            if (! $__inv_show) {
                $__inv_header_h = 0;
                $__inv_footer_h = 0;
                $__inv_footer_reserved_h = 0;
            }
        @endphp
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 17px;
            line-height: 1.3;
        }

        /* Constrain images to avoid huge intrinsic height causing extra pages */
        img {
            max-width: 100%;
            height: auto;
            display: block;
            page-break-inside: avoid;
        }

        .invoice-container {
            width: 100%;
        }

        /* Header Section */
        .header-section {
            width: 100%;
            text-align: center;
            margin-bottom: 0;
            height: {{ $__inv_header_h }}px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .header-placeholder {
            width: 100%;
            height: {{ $__inv_header_h }}px;
            visibility: hidden;
        }

        .header-image {
            width: 100%;
            max-height: {{ $__inv_header_h }}px;
            height: auto;
            object-fit: cover;
            display: block;
        }

        /* Content Section */
        .content-section {
            padding: 0 5mm;
            /* Reserve space at bottom so fixed footer doesn't overlap content */
            padding-bottom: {{ $__inv_footer_reserved_h + 6 }}px;
        }

        /* Title section with barcode */
        .title-section-table {
            width: 100%;
            margin-bottom: 12px;
        }

        .barcode-cell-left {
            width: 20%;
            text-align: left;
            vertical-align: top;
        }

        .barcode-cell-right {
            width: 20%;
            text-align: right;
            vertical-align: top;
        }

        .title-cell-center {
            width: 60%;
            text-align: center;
        }

        .barcode-image {
            height: 25px;
            width: 120px;
        }

        .receipt-title {
            font-size: 20px;
            font-weight: bold;
            font-family: Verdana, Geneva, Tahoma, sans-serif;
            margin: 0;
            letter-spacing: 2px;
        }

        .patient-details-table {
            width: 100%;
            border: none;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .patient-details-table td {
            vertical-align: top;
            padding: 0;
            border: none;
        }

        .patient-left {
            width: 50%;
            padding-right: 15px;
            vertical-align: top;
        }

        .patient-right {
            width: 50%;
            padding-left: 15px;
            vertical-align: top;
        }

        /* FIXED: Consistent alignment for both columns */
        .detail-row {
            display: flex;
            align-items: flex-start;
            margin-bottom: 4px;
            min-height: 18px;
        }

        .detail-label {
            font-weight: bold;
            min-width: 85px;
            flex-shrink: 0;
            text-align: left;
        }

        .detail-value {
            flex: 1;
            text-align: left;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .detail-colon {
            margin-right: 4px;
            flex-shrink: 0;
        }

        /* NEW: Full line detail row for Refd. By */
        .full-line-detail-row {
            display: block;
            margin-bottom: 4px;
            line-height: 1.4;
            vertical-align: middle;
        }
        /* align the full-line detail start to the right column (same as Contact No column) */
        .full-line-detail-row.right-align {
            margin: -6px 0 6px 50%;
        }

        .full-line-label {
            font-weight: bold;
            display: inline;
        }

        .full-line-colon {
            margin-right: 4px;
        }

        .full-line-value {
            display: inline-block;
            word-wrap: break-word;
            overflow-wrap: break-word;
            max-width: 100%;
            vertical-align: middle;
        }
        .full-line-value.nowrap {
            display: inline-block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 70%;
            vertical-align: middle;
            font-size: 14px;
        }

        /* Ensure value cells can be prevented from wrapping when needed */
        .info-table .value.nowrap {
            white-space: nowrap;
        }

        /* IPD-style info table (match IPD invoice alignment) */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .info-table td {
            padding: 2px 6px;
            vertical-align: top;
        }

        .info-table .label {
            font-weight: bold;
            width: 18%;
            white-space: nowrap;
        }

        .info-table .colon {
            width: 2%;
            text-align: center;
        }

        .info-table .value {
            width: 30%;
            padding-right: 6px;
        }

        /* Refd. By value: allow wrapping starting from second line, keep right padding */
        .refd-value {
            display: block;
            white-space: normal;
            overflow-wrap: break-word;
            word-break: break-word;
            padding-right: 6px;
            margin: 0;
        }
        .info-table .nowrap {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Items table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            font-size: inherit;
            border: 1px solid #bdbdbd;
        }

        .items-table th {
            padding: 5px 3px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #b8b8b8;
            background-color: #f2f2f2;
        }

        .items-table td {
            padding: 4px;
            vertical-align: top;
            border: 1px solid #c4c4c4;
        }

        .items-table tr:last-child {
            border-bottom: none;
        }

        .items-table .sl-col {
            width: 10%;
            text-align: center;
        }

        .items-table .test-col {
            width: 45%;
        }

        .items-table .room-col {
            width: 12%;
            text-align: center;
        }

        .items-table .qty-col {
            width: 10%;
            text-align: center;
        }

        .items-table .price-col {
            width: 15%;
            text-align: right;
        }

        /* Delivery date */
        .delivery-date {
            margin: 8px 0;
            font-weight: bold;
        }

        /* Bottom section */
        .bottom-section {
            width: 100%;
            border: none;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .bottom-section td {
            vertical-align: top;
            width: 50%;
            padding: 0;
            border: none;
        }

        .left-bottom {
            padding-right: 15px;
        }

        .right-bottom {
            padding-left: 15px;
        }

        /* Due section */
        .due-section {
            margin: 8px 0;
        }

        .due-badge {
            background-color: #ff4444;
            color: white;
            padding: 4px 8px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 4px;
            font-size: 16px;
        }

        .paid-badge {
            background-color: #28a745;
            color: white;
            padding: 4px 8px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 4px;
            font-size: 16px;
        }

        /* Totals table */
        .totals-table {
            width: 100%;
            font-size: inherit;
            margin-top: 0px;
        }

        .totals-table td {
            padding: 2px 4px;
            border-bottom: 1px solid #c3c3c3;
        }

        .totals-table .label-col {
            text-align: left;
            width: 60%;
        }

        .totals-table .amount-col {
            text-align: right;
            width: 40%;
        }

        /* Amount in words */
        .amount-words {
            margin-top: 8px;
            font-weight: bold;
            text-align: right;
            font-size: 16px;
        }

        .amount-words-inline {
            padding-top: 4px;
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            line-height: 1.2;
        }

        .prepared-by {
            margin-top: 8px;
        }

        .portal-qr {
            margin-top: 8px;
            display: inline-block;
            text-align: left;
        }

        .portal-qr img {
            width: 92px;
            height: 92px;
            background: #fff;
        }

        /* Footer Section - fixed at bottom of page */
        .footer-section {
            position: fixed; /* keep footer fixed at page bottom */
            bottom: 0;
            left: 0;
            width: 100%;
            text-align: center;
            padding-bottom: 0px;
            height: {{ $__inv_footer_reserved_h }}px;
            display: block;
            z-index: 9999;
            pointer-events: none; /* avoid capturing clicks when overlaying */
        }

        .footer-placeholder {
            width: 100%;
            height: {{ $__inv_footer_reserved_h }}px;
            visibility: hidden;
        }

        .footer-image {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            height: {{ $__inv_footer_h }}px;
            max-height: {{ $__inv_footer_h }}px;
            object-fit: contain;
            object-position: bottom center;
            z-index: 9998;
            pointer-events: none;
        }

        .footer-content {
            /* position relative so it sits above the footer image inside the footer area */
            position: relative;
            left: 0;
            right: 0;
            margin: 0 auto;
            font-size: {{ $__inv_footer_font_size }}px !important;
            @if(!empty($solaimanDataUri) || !empty($solaimanSrc))
                font-family: 'SolaimanLipi', 'NotoSansBengali', Arial, sans-serif !important;
            @elseif(!empty($fontDataUri) || !empty($banglaFontPath) || !empty($banglaFontUrl))
                font-family: 'NotoSansBengali', Arial, sans-serif !important;
            @endif
            text-align: center;
            padding: 2px 20px;
            width: 100%;
            z-index: 10000; /* above footer image */
            background: transparent;
            pointer-events: auto; /* allow selection of footer text if needed */
            line-height: 1.2;
            white-space: normal;
            word-break: break-word;
        }

        .footer-content p,
        .footer-content div {
            margin: 0;
            padding: 0;
        }

        @if(!empty($solaimanDataUri) || !empty($solaimanSrc))
        .footer-content,
        .footer-content * {
            font-family: 'SolaimanLipi', 'NotoSansBengali', Arial, sans-serif !important;
        }
        @elseif(!empty($fontDataUri) || !empty($banglaFontPath) || !empty($banglaFontUrl))
        .footer-content,
        .footer-content * {
            font-family: 'NotoSansBengali', Arial, sans-serif !important;
        }
        @endif

        .footer-meta-wrap {
            position: absolute;
            left: 0;
            right: 0;
            bottom: {{ $__inv_footer_h }}px;
            width: 100%;
            padding: 0 20px;
            margin-bottom: 0;
            z-index: 10000;
            pointer-events: auto;
        }

        .footer-meta-wrap.with-image {
            bottom: calc({{ $__inv_footer_h }}px - 36px);
        }

        .footer-meta-wrap.no-image {
            bottom: 0;
        }

        .footer-date-time {
            font-size: 13px;
            text-align: left;
            margin-top: 0;
            color: #000000ff;
            width: 100%;
            padding: 0;
            margin-bottom: 0;
            line-height: 1.15;
        }

        .footer-custom-line {
            text-align: center;
            padding: 0;
            font-size: {{ $__inv_footer_font_size }}px !important;
            line-height: 1.15;
            white-space: normal;
            word-break: normal;
            overflow-wrap: normal;
            word-wrap: normal;
            hyphens: none;
            margin: 0;
        }

        .footer-custom-item {
            display: inline-block;
            white-space: nowrap;
            margin-bottom: 0;
        }

        .footer-separator {
            display: inline-block;
            white-space: nowrap;
            margin: 0 4px 0 0;
        }

        .footer-meta-row {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .footer-meta-left {
            text-align: left;
            white-space: nowrap;
            padding: 0 8px 0 0;
            vertical-align: middle;
        }

        .footer-meta-right {
            text-align: right;
            white-space: nowrap;
            padding: 0 0 0 8px;
            vertical-align: middle;
        }

        .footer-meta-left,
        .footer-meta-right {
            line-height: 1.1;
        }

        @if(!empty($solaimanDataUri) || !empty($solaimanSrc))
        .footer-custom-line,
        .footer-custom-line * {
            font-family: 'SolaimanLipi', 'NotoSansBengali', Arial, sans-serif !important;
        }
        @elseif(!empty($fontDataUri) || !empty($banglaFontPath) || !empty($banglaFontUrl))
        .footer-custom-line,
        .footer-custom-line * {
            font-family: 'NotoSansBengali', Arial, sans-serif !important;
        }
        @endif

        @if(!empty($is_fast_open))
        @media screen {
            html,
            body {
                background: #eceff3;
            }

            body {
                padding: 12px 0;
            }

            .invoice-container {
                width: 210mm;
                min-height: 297mm;
                margin: 0 auto;
                background: #ffffff;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.14);
                position: relative;
                overflow: hidden;
            }

            .content-section {
                padding-bottom: {{ max(26, (int) ($__inv_footer_reserved_h + 6)) }}px !important;
            }

            .footer-section {
                position: absolute !important;
                left: 0 !important;
                right: 0 !important;
                bottom: 0 !important;
                width: 100% !important;
                height: {{ $__inv_footer_reserved_h }}px !important;
                margin-top: 0 !important;
                z-index: 9999;
                pointer-events: none;
            }

            .footer-meta-wrap,
            .footer-meta-wrap.with-image,
            .footer-meta-wrap.no-image {
                position: absolute !important;
                left: 0 !important;
                right: 0 !important;
                bottom: calc({{ $__inv_footer_h }}px - 36px + 3.5mm) !important;
                margin: 0 !important;
                padding: 0 20px !important;
            }

            .footer-meta-wrap.no-image {
                bottom: 3.5mm !important;
            }

            .footer-image {
                position: absolute !important;
                left: 0 !important;
                right: 0 !important;
                bottom: 0 !important;
                width: 100% !important;
                height: {{ $__inv_footer_h }}px !important;
                max-height: {{ $__inv_footer_h }}px !important;
                object-fit: contain;
                object-position: bottom center;
                display: block;
            }
        }
        @endif

        /* Print specific styles */
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                height: auto;
                margin: 0 !important;
                padding: 0 !important;
            }

            .invoice-container {
                margin: 0 !important;
                padding: 0 !important;
            }

            .print-shared-header.header-section,
            .header-section {
                margin-top: 0 !important;
                margin-bottom: 0 !important;
            }

            .content-section {
                padding-bottom: {{ max(24, (int) ($__inv_footer_reserved_h + 4)) }}px;
            }

            .header-section,
            .header-placeholder,
            .header-image {
                /* avoid fixed positioning for mPDF */
                position: static;
                top: auto;
                left: auto;
                right: auto;
                width: 100%;
                z-index: 50;
            }

            .header-image { object-fit: cover; height: {{ $__inv_header_h }}px; }

            .header-placeholder,
            .footer-placeholder {
                display: none;
            }

            .footer-date-time {
                color: #000;
            }

            /* Ensure proper alignment in print */
            .detail-row {
                min-height: 16px;
            }
        }

        @page {
            size: auto;
            margin: 0;
        }

        /* A4 Print Settings */
        @media print {
            body {
                font-size: 17px !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                height: auto;
                margin: 0 !important;
                padding: 0 !important;
            }

            .receipt-title {
                font-size: 20px;
            }

            .header-section {
                height: {{ $__inv_header_h }}px;
            }

            .header-image {
                height: 100%;
            }

            .barcode-image {
                height: 25px;
                width: 120px;
            }

            .content-section {
                padding: 0 5mm;
                padding-bottom: {{ max(26, (int) ($__inv_footer_reserved_h + 6)) }}px;
            }

            .items-table,
            .totals-table {
                font-size: 17px !important;
            }

            .items-table {
                border: 1px solid #bdbdbd !important;
            }

            .items-table th {
                border: 1px solid #b8b8b8 !important;
                background-color: #f2f2f2 !important;
            }

            .items-table td {
                border: 1px solid #c4c4c4 !important;
            }

            .due-badge,
            .paid-badge {
                font-size: 17px;
            }

            .amount-words {
                font-size: 17px;
            }

            .amount-words-inline {
                font-size: 17px !important;
                padding-top: 3px !important;
            }

            .footer-section {
                height: {{ $__inv_footer_reserved_h }}px;
            }

            .footer-image {
                height: {{ $__inv_footer_h }}px;
                max-height: {{ $__inv_footer_h }}px;
            }

            .footer-date-time {
                font-size: 14px !important;
            }

            .footer-meta-wrap.with-image {
                bottom: calc({{ $__inv_footer_h }}px - 36px + 3.5mm) !important;
            }

            .footer-meta-wrap.no-image {
                bottom: 3.5mm !important;
            }
        }

        /* A5 Print Settings */
        @media print and (max-height: 8.3in) and (max-width: 5.8in) {
            body {
                font-size: 11px !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .receipt-title {
                font-size: 12px !important;
            }

            .header-section {
                height: {{ $__inv_header_h }}px !important;
                margin: 0 !important;
            }

            .header-image {
                height: 100% !important;
                width: 100% !important;
                object-fit: fill !important;
            }

            .print-shared-header .print-shared-header-image {
                width: 100% !important;
                object-fit: fill !important;
            }

            .barcode-image {
                height: 21px !important;
                width: 100px !important;
            }

            .barcode-cell-left img,
            .barcode-cell-left svg,
            .barcode-cell-right img,
            .barcode-cell-right svg {
                width: 100px !important;
                height: 21px !important;
            }

            .content-section {
                padding: 0 5mm !important;
                padding-bottom: {{ max(24, (int) ($__inv_footer_h + 2)) }}px !important;
            }

            .items-table,
            .totals-table {
                font-size: 11px !important;
            }

            .items-table {
                border: 1px solid #bdbdbd !important;
                margin: 8px 0 !important;
            }

            .items-table th {
                padding: 1px 2px !important;
                border: 1px solid #b8b8b8 !important;
                background-color: #f4f4f4 !important;
            }

            .items-table td {
                padding: 2px 2px !important;
                line-height: 1.15 !important;
                border: 1px solid #c4c4c4 !important;
            }

            .title-section-table {
                margin-bottom: 4px !important;
            }

            .detail-row {
                margin-bottom: 0 !important;
                min-height: 10px !important;
            }

            .due-badge,
            .paid-badge {
                font-size: 11px !important;
                padding: 1px 4px !important;
            }

            .amount-words {
                font-size: 11px !important;
                margin-top: 1px !important;
            }

            .amount-words-inline {
                font-size: 11px !important;
                padding-top: 1px !important;
                line-height: 1.1 !important;
            }

            .delivery-date {
                margin: 2px 0 !important;
                font-size: 11px !important;
            }

            .bottom-section {
                margin-top: 2px !important;
            }

            .bottom-section td {
                padding: 0 !important;
            }

            .due-section {
                margin: 2px 0 !important;
            }

            .prepared-by {
                margin-top: 2px !important;
                font-size: 11px !important;
            }

            img[alt="Patient Portal QR"] {
                width: 52px !important;
                height: 52px !important;
            }

            .totals-table td {
                padding: 1px 2px !important;
            }

            .footer-section {
                height: {{ $__inv_footer_reserved_h }}px !important;
                bottom: 0 !important;
            }

            .footer-image {
                height: {{ $__inv_footer_h }}px !important;
                max-height: {{ $__inv_footer_h }}px !important;
                bottom: 0 !important;
            }

            .footer-content {
                font-size: 11px !important;
            }

            .footer-date-time {
                font-size: 9px !important;
            }

            .footer-meta-wrap {
                margin-bottom: 0 !important;
                padding: 0 20px !important;
            }

            .footer-meta-wrap.with-image {
                bottom: calc({{ $__inv_footer_h }}px - 36px) !important;
            }

            .footer-custom-line {
                margin: 0 !important;
                padding: 0 !important;
                line-height: 1.15 !important;
            }

            /* Adjust for A5 */
            .detail-label {
                min-width: 70px;
            }
            
        }
    </style>
</head>

<body>
    <div class="invoice-container">
        {{-- Use shared header partial for consistent header across prints --}}
        @includeIf('prints.partials._header', [
            'header_image' => $header_image ?? null,
            'headerHeight' => $__inv_header_h,
            'footerHeight' => $__inv_footer_h,
            'printed_at' => $invoiceDateTime ?? ($printed_at ?? null),
            'showHeaderFooter' => $__inv_show,
        ])

        <div class="content-section">
            <table class="title-section-table">
                <tr>
                    <td class="barcode-cell-left">
                        {!! DNS1D::getBarcodeHTML(isset($bill) ? $bill->bill_no : $bill_number, 'C128', 1, 30) !!}
                    </td>
                    <td class="title-cell-center">
                        <div class="receipt-title">MONEY RECEIPT</div>
                    </td>
                    <td class="barcode-cell-right">
                        {!! DNS1D::getBarcodeHTML(isset($bill) ? $bill->bill_no : $bill_number, 'C128', 1, 30) !!}
                    </td>
                </tr>
            </table>

            <!-- Patient / IPD Details Section (aligned like IPD invoice) -->
            @php
                $displayRefd = trim((string) ($refd_by ?? $billing->doctor_name ?? $billing->doctor?->name ?? ''));
            @endphp
            @if(!empty($ipd_id))
            <table class="info-table">
                <tr>
                    <td class="label">IPD ID</td><td class="colon">:</td><td class="value">{{ $ipd_id }}</td>
                    <td class="label">Printed At</td><td class="colon">:</td><td class="value">{{ $printed_at ?? $invoiceDateTime ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Patient Name</td><td class="colon">:</td><td class="value">{{ $patient_name }}</td>
                    <td class="label">Age</td><td class="colon">:</td><td class="value">{{ $age }}</td>
                </tr>
                <tr>
                    <td class="label">Gender</td><td class="colon">:</td><td class="value">{{ $gender }}</td>
                    <td class="label">Phone</td><td class="colon">:</td><td class="value">{{ $contact_no }}</td>
                </tr>
                @if($module === 'ipd')
                <tr>
                    <td class="label">Bed</td><td class="colon">:</td><td class="value">{{ $bed ?? '' }}</td>
                    <td class="label">Admission</td><td class="colon">:</td><td class="value">{{ $admission ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Case</td><td class="colon">:</td><td class="value">{{ $case ?? '' }}</td>
                    <td class="label">Discharge</td><td class="colon">:</td><td class="value">{{ $discharge ?? '' }}</td>
                </tr>
                @endif
                            <tr>
                                <td class="label">Consultant</td><td class="colon">:</td><td class="value" colspan="4">{{ $consultant ?? $refd_by ?? '' }}</td>
                            </tr>
            </table>
            @else
            <table class="info-table">
                <tr>
                    <td class="label">Bill No</td><td class="colon">:</td><td class="value">{{ $bill_number }}</td>
                    <td class="label">Date &amp; Time</td><td class="colon">:</td><td class="value">{{ $invoiceDateTime }}</td>
                </tr>
                @if($module === 'ipd')
                <tr>
                    <td class="label">IPD ID</td><td class="colon">:</td><td class="value">{{ $ipd_id ?? 'N/A' }}</td>
                    <td class="label">Printed At</td><td class="colon">:</td><td class="value">{{ $printed_at ?? ($invoiceDateTime ?? '') }}</td>
                </tr>
                @endif
                <tr>
                    <td class="label">Patient Name</td><td class="colon">:</td><td class="value">{{ $patient_name ?? 'N/A' }}</td>
                    <td class="label">Age</td><td class="colon">:</td><td class="value">{{ $age ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Gender</td><td class="colon">:</td><td class="value">{{ $gender ?? 'N/A' }}</td>
                    <td class="label">Phone</td><td class="colon">:</td><td class="value">{{ $contact_no ?? '' }}</td>
                </tr>
                @if($module === 'ipd')
                <tr>
                    <td class="label">Case</td><td class="colon">:</td><td class="value">{{ $case ?? '' }}</td>
                    <td class="label">Bed</td><td class="colon">:</td><td class="value">{{ $bed ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Admission</td><td class="colon">:</td><td class="value">{{ $admission ?? '' }}</td>
                    <td class="label">Discharge</td><td class="colon">:</td><td class="value">{{ $discharge ?? '' }}</td>
                </tr>
                @endif
                <tr>
                    <td class="label">Refd. By</td><td class="colon">:</td><td class="value" colspan="4">{{ $consultant ?? $displayRefd ?? '' }}</td>
                </tr>
            </table>
            @endif

            

                @php
                $hasRoomNo = collect($bill_items ?? [])->contains(function ($it) {
                    return trim((string) data_get($it, 'room_no', '')) !== '';
                });

                // Also consider any room_no present in the full billing items (some invoices
                // are module-filtered; if any item in the full billing has room_no we
                // still show the Room No column so users can see room numbers where present).
                if (! $hasRoomNo && isset($billing)) {
                    $hasRoomNo = collect($billing->billItems ?? [])->contains(function ($it) {
                        return trim((string) data_get($it, 'room_no', '')) !== '';
                    });
                }
            @endphp

            <table class="items-table">
                <thead>
                    <tr>
                        <th class="sl-col">SL</th>
                        <th class="test-col">Item Name</th>
                        @if($hasRoomNo)
                            <th class="room-col">Room No</th>
                        @endif
                        <th class="qty-col">Qty</th>
                        <th class="price-col">Price (Tk.)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bill_items as $index => $item)
                    <tr>
                        <td class="sl-col">{{ $index + 1 }}</td>
                        <td class="test-col">{{ $item->item_name ?? $item->description }}</td>
                        @if($hasRoomNo)
                            <td class="room-col">{{ data_get($item, 'room_no', '') }}</td>
                        @endif
                        <td class="qty-col">{{ (int) $item->quantity }}</td>
                        <td class="price-col">{{ number_format($item->total_amount, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

@php
use Carbon\Carbon;

$netPayable = (float) $net_payable;

// Invoice time paid (original payment)
$invoicePaid = isset($paid_at_invoice) ? (float) $paid_at_invoice : (float) ($billing->invoice_amount ?? 0);

// Due history
$dueCollections = $billing->dueCollections ?? collect();
$dueCollectedTotal = $dueCollections->sum('collected_amount');

// Payments (including any receipts recorded later)
$payments = $billing->payments ?? collect();
$paymentsTotal = $payments->sum('amount');

// Prefer controller-provided aggregate if available, otherwise compute
$totalPaid = isset($paid) ? (float) $paid : (float) ($paymentsTotal + $dueCollectedTotal);

// Compute unadjusted due (what billing page shows): net payable minus total paid
$unadjustedDue = max(0, $netPayable - $totalPaid);

// Controller may provide an adjusted_due (after returns). Keep both values and
// display adjusted due only when return amount exists so users understand the
// reconciliation.
$finalDue = isset($adjusted_due) ? max((float) $adjusted_due, 0) : $unadjustedDue;

// Prefer the controller-provided return amount when available; otherwise derive it
// from the billed/received overpayment for older records.
$returnAmountForView = isset($return_amount) ? (float) $return_amount : 0;
if ($returnAmountForView <= 0) {
    $returnAmountForView = max(0, (float) ($billing->return_amt ?? $billing->return_amount ?? 0));
}
if ($returnAmountForView <= 0) {
    $invoiceAmountForReturn = (float) ($billing->invoice_amount ?? $billing->payable_amount ?? 0);
    $receivingAmountForReturn = (float) ($billing->receiving_amt ?? 0);
    $returnAmountForView = max(0, $receivingAmountForReturn - $invoiceAmountForReturn);
}

$portalPatientId = (int) ($billing->patient_id ?? 0);
$portalPhone = (string) ($billing->patient_mobile ?? ($billing->patient->phone ?? ''));
$portalToken = '';
if ($portalPatientId > 0) {
    try {
        if (!empty(config('app.key'))) {
            $portalTokenPayload = [
                'patient_id' => $portalPatientId,
                'phone' => $portalPhone,
                'billing_id' => (int) ($billing->id ?? 0),
                'exp' => now()->addDays(30)->timestamp,
            ];
            $portalToken = encrypt(json_encode($portalTokenPayload));
        }
    } catch (\Throwable $e) {
        // If encryption is unavailable, skip portal QR generation to keep invoice rendering stable.
        $portalToken = '';
    }
}
$portalLoginUrl = $portalToken !== ''
    ? route('backend.patient.portal.login', ['token' => $portalToken])
    : '';
$portalQrCode = $portalLoginUrl !== ''
    ? 'data:image/png;base64,' . (new \Milon\Barcode\DNS2D())->getBarcodePNG($portalLoginUrl, 'QRCODE', 5, 5)
    : '';
$showReturnAmount = isset($show_return_amount) ? (bool) $show_return_amount : true;
// Compute Medicine and Laboratory subtotals if bill items provide category
$billItemsCollection = collect($bill_items ?? []);
$medicineTotal = $billItemsCollection->filter(function($it){
    return strtolower(trim($it->category ?? '')) === 'medicine';
})->sum('total_amount');
$labTotal = $billItemsCollection->filter(function($it){
    $c = strtolower(trim($it->category ?? ''));
    return in_array($c, ['pathology','radiology']);
})->sum('total_amount');
// detect if any bill item is an IPD item (category 'IPD')
$hasIpdItem = $billItemsCollection->contains(function($it){
    return strtolower(trim($it->category ?? '')) === 'ipd';
});
@endphp

@php
// Debug block: show invoice internals when `debug_invoice=1` is present in query.
// This is temporary to troubleshoot mismatched Due values.
if (request()->boolean('debug_invoice')) {
        try {
        $dbg = [
            'billing_id' => $billing->id ?? null,
            'billing_total' => round((float) ($billing->total ?? 0), 2),
            'base_total' => round((float) ($base_total ?? $total_amount ?? 0), 2),
            'vat_percentage' => round((float) ($vat_percentage ?? 0), 2),
            'vat_amount' => round((float) ($vat ?? 0), 2),
            'net_payable' => round((float) ($net_payable ?? 0), 2),
            'billing_discount' => round((float) ($billing->discount ?? 0), 2),
            'discount_type' => $billing->discount_type ?? null,
            'extra_flat_discount' => round((float) ($billing->extra_flat_discount ?? 0), 2),
            'billing_paid_amt' => round((float) ($billing->paid_amt ?? 0), 2),
            'billing_receiving_amt' => round((float) ($billing->receiving_amt ?? 0), 2),
            'billing_return_amt' => round((float) ($billing->return_amt ?? 0), 2),
            'controller_paid' => round((float) ($paid ?? 0), 2),
            'controller_due' => round((float) ($due ?? 0), 2),
            'invoice_totals_due' => round((float) ($adjusted_due ?? 0), 2),
            'payments_count' => $billing->payments->count() ?? 0,
            'due_collections_count' => $billing->dueCollections->count() ?? 0,
        ];
    } catch (\Throwable $e) {
        $dbg = ['error' => $e->getMessage()];
    }
    echo '<pre style="background:#fff7c0;padding:8px;border:1px solid #e2c44d;">' . e(json_encode($dbg, JSON_PRETTY_PRINT)) . '</pre>';
}
@endphp


@if (!isset($ipd_id) && !$hasIpdItem && $delivery_date)
<div class="delivery-date">
    Delivery Date & Time:
    {{ Carbon::parse($delivery_date)->format('d-M-Y, h:i A') }}
</div>
@endif


<table class="bottom-section">
    <tr>
        <td class="left-bottom" style="vertical-align: top;">
            <div class="due-section">
                @if(!empty($remarks))
                <div>
                    <strong>Remarks:</strong> {{ $remarks }}
                </div>
                @endif

                @if(round((float) $finalDue, 2) > 0)
                <div class="due-badge">DUE</div>
                @else
                <div class="paid-badge">PAID</div>
                @endif

                <div>Thank You</div>
            </div>

            <div class="prepared-by">
                <strong>Prepared By:</strong> {{ $prepared_by }}
            </div>

            @if($portalQrCode !== '')
            <div class="portal-qr">
                <img src="{{ $portalQrCode }}" alt="Patient Portal QR" />
            </div>
            @endif
        </td>
        <td class="right-bottom" style="vertical-align: top;">
            <table class="totals-table">
                <tr>
                    <td class="label-col"><strong>Total Amount Tk.</strong></td>
                    <td class="amount-col">
                        <strong>{{ number_format(($base_total ?? $total_amount ?? 0), 2) }}</strong>
                    </td>
                </tr>

                @if((float) ($medicineTotal ?? 0) > 0)
                <tr>
                    <td class="label-col">Medicine Bill</td>
                    <td class="amount-col">{{ number_format($medicineTotal, 2) }}</td>
                </tr>
                @endif

                @if((isset($ipd_id) && (float) ($labTotal ?? 0) > 0))
                <tr>
                    <td class="label-col">Laboratory Bill</td>
                    <td class="amount-col">{{ number_format($labTotal, 2) }}</td>
                </tr>
                @endif

                @php
                    // Recompute VAT and discounts from base total so printed invoice
                    // matches the billing page summary logic: VAT = total * vat_percent/100,
                    // Discount (percentage) = total * discount_percent/100.
                    $baseTotal = round((float) ($base_total ?? $total_amount ?? 0), 2);
                    $vatPercentLocal = round((float) ($vat_percentage ?? 0), 2);
                    $vatComputed = $vatPercentLocal > 0 ? round(($baseTotal * $vatPercentLocal) / 100, 2) : 0.00;

                    $extraDiscountAmount = round((float) ($extra_flat_discount ?? 0), 2);

                    if (strtolower((string) ($discount_type ?? 'flat')) === 'percentage') {
                        $discountPercentLocal = round((float) ($discount ?? 0), 2);
                        $discountComputed = round(($baseTotal * $discountPercentLocal) / 100, 2);
                    } else {
                        $discountPercentLocal = null;
                        $discountComputed = round((float) ($discount ?? 0), 2);
                    }

                    $totalWithVat = round($baseTotal + $vatComputed, 2);
                    $netComputed = round(max(0, $totalWithVat - $discountComputed - $extraDiscountAmount), 2);
                @endphp

                @if ($vatComputed != 0)
                <tr>
                    <td class="label-col">Vat Tk.</td>
                    <td class="amount-col">{{ number_format($vatComputed, 2) }}</td>
                </tr>
                <tr>
                    <td class="label-col"><strong>Total With VAT Tk.</strong></td>
                    <td class="amount-col"><strong>{{ number_format($totalWithVat, 2) }}</strong></td>
                </tr>
                @endif

                @if ($discountComputed != 0)
                    @if ($discountPercentLocal !== null)
                    <tr>
                        <td class="label-col">
                            Discount ({{ number_format($discountPercentLocal, 2) }}%)
                        </td>
                        <td class="amount-col">
                            {{ number_format($discountComputed, 2) }}
                        </td>
                    </tr>
                    @else
                    <tr>
                        <td class="label-col">Discount Tk.</td>
                        <td class="amount-col">{{ number_format($discountComputed, 2) }}</td>
                    </tr>
                    @endif
                @endif

                @if ($extraDiscountAmount != 0)
                <tr>
                    <td class="label-col">Extra Discount Tk.</td>
                    <td class="amount-col">
                        {{ number_format($extraDiscountAmount, 2) }}
                    </td>
                </tr>
                @endif

                <tr>
                    <td class="label-col"><strong>Net Payable Tk.</strong></td>
                    <td class="amount-col">
                        <strong>{{ number_format($netComputed,2) }}</strong>
                    </td>
                </tr>

                @php
                    $invoicePaidRounded = round((float) ($invoicePaid ?? 0), 2);
                    $totalPaidRounded = round((float) ($totalPaid ?? 0), 2);
                    $returnAmountRounded = round((float) ($returnAmountForView ?? 0), 2);
                    $finalDueRounded = round((float) ($finalDue ?? 0), 2);
                    $netPayableRounded = round((float) ($netPayable ?? 0), 2);
                    // Show return amount whenever a positive return is present.
                    $showReturnAmountCondition = $returnAmountRounded > 0;
                @endphp

                @if ($invoicePaidRounded != 0)
                <tr>
                    <td class="label-col">Paid (Invoice Time)</td>
                    <td class="amount-col">
                        {{ number_format($invoicePaid,2) }}
                    </td>
                </tr>
                @endif

                {{-- Due Collect History --}}
                {{-- Payment history intentionally omitted from printed invoice --}}

                {{-- Due Collect History --}}
                @foreach($dueCollections as $dc)
                <tr>
                    <td class="label-col" style="white-space: nowrap;">
                        {{ \Carbon\Carbon::parse($dc->collected_at)->format('d-M-Y h:i A') }} - Due Collect
                    </td>
                    <td class="amount-col" style="text-align:right;">
                        {{ number_format($dc->collected_amount, 2) }}
                    </td>
                </tr>
                @endforeach

                @if ($totalPaidRounded != 0)
                <tr>
                    <td class="label-col"><strong>Total Paid Tk.</strong></td>
                    <td class="amount-col">
                        <strong>{{ number_format($totalPaid,2) }}</strong>
                    </td>
                </tr>
                @endif

                @if ($showReturnAmount && $showReturnAmountCondition)
                <tr>
                    <td class="label-col"><strong>Return Amount Tk.</strong></td>
                    <td class="amount-col">
                        <strong>{{ number_format($returnAmountForView,2) }}</strong>
                    </td>
                </tr>
                @endif

                {{-- Always show unadjusted due (matches billing page calculation) --}}
                <tr>
                    <td class="label-col"><strong>Due Tk.</strong></td>
                    <td class="amount-col">
                        <strong>{{ number_format($unadjustedDue,2) }}</strong>
                    </td>
                </tr>

                {{-- If controller provided an adjusted due, show it after returns for clarity --}}
                @if(isset($adjusted_due) && round((float) $adjusted_due,2) !== round((float) $unadjustedDue,2))
                <tr>
                    <td class="label-col"><strong>Due After Returns Tk.</strong></td>
                    <td class="amount-col">
                        <strong>{{ number_format($finalDue,2) }}</strong>
                    </td>
                </tr>
                @endif
            </table>
        </td>
    </tr>
    <tr>
        <td colspan="2" class="amount-words-inline">
            {{ $amount_in_words }}
        </td>
    </tr>
</table>

        </div>

        <!-- Footer Section -->
        @if($__inv_show)
        <div class="footer-section">
            @php
                $footerFallbackLine = trim((string) config('app.invoice_footer_fallback_line', 'Powered By: www.toamedit.com Support: 01919-592638'));
                $footerPrintedAt = trim((string) ($printed_at ?? ''));
                $footerContentPlain = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) ($footer_content ?? ''))));
                $footerContentPlain = (string) preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $footerContentPlain);
                $footerContentPlain = (string) preg_replace('/রুম\s*(নং|নম্বর|নাম্বার)\s*/u', 'রুম নং ', $footerContentPlain);
                $footerContentPlain = (string) preg_replace('/প্যা\s*থা?\s*ল\s*জ[ীি]?/u', 'প্যাথলজি', $footerContentPlain);
                $footerContentPlain = str_replace(['،', '٬', '﹐', '，'], ',', $footerContentPlain);
                $footerParts = preg_split('/,+/u', $footerContentPlain) ?: [];
                $footerParts = array_values(array_filter(array_map(static function ($part) {
                    return trim((string) $part);
                }, $footerParts), static function ($part) {
                    return $part !== '';
                }));
                $footerContentPlain = !empty($footerParts) ? implode(', ', $footerParts) : $footerContentPlain;
                $footerContentPlain = trim((string) preg_replace('/[,.;:!?\x{0964}\x{0965}]+$/u', '', $footerContentPlain));
                $footerPartsHtml = '';
                if (!empty($footerParts)) {
                    $renderedFooterParts = [];
                    foreach ($footerParts as $footerItem) {
                        $safeFooterItem = e((string) $footerItem);
                        $safeFooterItem = str_replace(' ', '&nbsp;', $safeFooterItem);
                        $renderedFooterParts[] = '<span class="footer-custom-item">' . $safeFooterItem . '</span>';
                    }
                    $footerPartsHtml = implode('<span class="footer-separator">,&nbsp;</span>', $renderedFooterParts);
                }
                $hasCustomFooterContent = $footerPartsHtml !== '';
                $showFooterMetaRow = ($footerFallbackLine !== '' || $footerPrintedAt !== '');
            @endphp

            @if(!empty($footer_image))
                @if($showFooterMetaRow || $hasCustomFooterContent)
                <div class="footer-meta-wrap with-image">
                    <div class="footer-date-time">
                        @if($hasCustomFooterContent)
                            <div class="footer-custom-line">
                                {!! $footerPartsHtml !!}
                            </div>
                        @endif

                        <table class="footer-meta-row">
                            <tr>
                                <td class="footer-meta-left">
                                @if($footerFallbackLine !== '')
                                    {{ $footerFallbackLine }}
                                @endif
                                </td>
                                <td class="footer-meta-right">
                                @if($footerPrintedAt !== '')
                                    Printing Date: {{ $footerPrintedAt }}
                                @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                @endif

                <img src="{{ $footer_image }}" alt="Footer" class="footer-image">
            @else
                <div class="footer-placeholder"></div>
                @if($showFooterMetaRow || $hasCustomFooterContent)
                    <div class="footer-meta-wrap no-image">
                        <div class="footer-date-time">
                            @if($hasCustomFooterContent)
                                <div class="footer-custom-line">
                                    {!! $footerPartsHtml !!}
                                </div>
                            @endif

                            <table class="footer-meta-row">
                                <tr>
                                    <td class="footer-meta-left">
                                    @if($footerFallbackLine !== '')
                                        {{ $footerFallbackLine }}
                                    @endif
                                    </td>
                                    <td class="footer-meta-right">
                                    @if($footerPrintedAt !== '')
                                        Printing Date: {{ $footerPrintedAt }}
                                    @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                @endif
            @endif
        </div>
        @endif
    </div>
    @if(!empty($auto_print))
    <script>
        (function() {
            var triggered = false;
            var closeScheduled = false;
            var scheduleClose = function() {
                if (closeScheduled) return;
                closeScheduled = true;
                try {
                    localStorage.setItem('billing:close_invoice_tabs', String(Date.now()));
                } catch (e) { /* ignore */ }
                setTimeout(function () {
                    try { window.open('', '_self'); } catch (e) {}
                    try { window.close(); } catch (e) {}
                }, 150);
            };

            try {
                window.addEventListener('afterprint', scheduleClose, { once: true });
            } catch (e) {
                // ignore
            }

            var doPrint = function() {
                if (triggered) return;
                triggered = true;
                try { window.focus(); } catch (e) {}
                try {
                    window.print();
                } catch (e) { scheduleClose(); }
            };
            try {
                window.addEventListener('beforeunload', function () {
                    try { localStorage.setItem('billing:close_invoice_tabs', String(Date.now())); } catch (e) { }
                });
            } catch (e) { /* ignore */ }
            var attemptPrint = function() {
                setTimeout(doPrint, 600);
                setTimeout(doPrint, 1400);
            };
            if (document.readyState === 'complete' || document.readyState === 'interactive') {
                attemptPrint();
            } else {
                window.addEventListener('DOMContentLoaded', function() { attemptPrint(); }, { once: true });
                window.addEventListener('load', function() { attemptPrint(); }, { once: true });
            }
        })();
    </script>
    @endif
</body>
</html>