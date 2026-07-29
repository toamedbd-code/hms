@php
    $showHeaderFooter = isset($showHeaderFooter) ? (bool) $showHeaderFooter : (isset($show_header_footer) ? (bool) $show_header_footer : null);
    $allowInvoiceDesignFallback = isset($allowInvoiceDesignFallback) ? (bool) $allowInvoiceDesignFallback : true;
    if ($showHeaderFooter === null && function_exists('get_cached_web_setting')) {
        $webSetting = get_cached_web_setting();
        $attendanceOptions = $webSetting?->attendance_device_options ?? [];

        if (!is_array($attendanceOptions)) {
            try {
                $attendanceOptions = is_string($attendanceOptions) && trim($attendanceOptions) !== ''
                    ? json_decode($attendanceOptions, true)
                    : [];
            } catch (\Throwable $e) {
                $attendanceOptions = [];
            }
        }

        $invoiceOptions  = is_array($attendanceOptions) ? (data_get($attendanceOptions, 'invoice', []) ?? []) : [];
        $reportingOptions = is_array($attendanceOptions) ? (data_get($attendanceOptions, 'reporting', []) ?? []) : [];
        // reporting section overrides invoice section for header/footer visibility
        $effectiveOptions = array_replace_recursive(
            is_array($invoiceOptions) ? $invoiceOptions : [],
            is_array($reportingOptions) ? $reportingOptions : []
        );

        $settingShowHeader = array_key_exists('show_header', $effectiveOptions) ? (bool) $effectiveOptions['show_header'] : null;
        $settingShowFooter = array_key_exists('show_footer', $effectiveOptions) ? (bool) $effectiveOptions['show_footer'] : null;

        if ($settingShowHeader !== null || $settingShowFooter !== null) {
            $showHeaderFooter = ($settingShowHeader ?? true) && ($settingShowFooter ?? true);
        } else {
            $showHeaderFooter = true;
        }
    }
    $showHeaderFooter = $showHeaderFooter ?? true;

    $ftr = trim((string) ($footer_image ?? $footerImage ?? $footerSrc ?? $footer ?? ''));
    $footerContentVal = (string) ($footer_content ?? $footerContent ?? $footerHtml ?? $footer_html ?? '');
    $footerHeight = (int) ($footerHeight ?? $footer_height ?? $reportFooterHeight ?? ($invoiceDesign?->footer_height ?? 0));

    $footerContentPosition = strtolower(trim((string) ($footer_content_position ?? $footerContentPosition ?? ($invoiceDesign?->footer_content_position ?? ''))));

    $resolvedInvoiceDesign = null;
    if ($allowInvoiceDesignFallback && ($ftr === '' || trim($footerContentVal) === '' || $footerHeight <= 0 || $footerContentPosition === '') && class_exists(\App\Models\InvoiceDesign::class)) {
        $resolvedInvoiceDesign = \App\Models\InvoiceDesign::query()
            ->where('status', 'Active')
            ->whereRaw('LOWER(TRIM(module)) = ?', ['billing'])
            ->first();

        if (!$resolvedInvoiceDesign) {
            $resolvedInvoiceDesign = \App\Models\InvoiceDesign::query()
                ->where('status', 'Active')
                ->whereNull('module')
                ->first();
        }

        if (!$resolvedInvoiceDesign) {
            $resolvedInvoiceDesign = \App\Models\InvoiceDesign::query()
                ->where('status', 'Active')
                ->orderByDesc('id')
                ->first();
        }
    }

    if ($ftr === '' && $resolvedInvoiceDesign) {
        $ftr = (string) (publicStorageUrl($resolvedInvoiceDesign->footer_photo_path) ?? '');
    }

    if (trim($footerContentVal) === '') {
        $footerContentVal = (string) ($resolvedInvoiceDesign?->footer_content ?? config('app.invoice_footer_fallback_line', ''));
    }

    if ($footerHeight <= 0) {
        $footerHeight = (int) ($resolvedInvoiceDesign?->footer_height ?? 70);
    }

    if ($footerContentPosition === '') {
        $footerContentPosition = strtolower(trim((string) ($resolvedInvoiceDesign?->footer_content_position ?? 'above')));
    }

    if (!in_array($footerContentPosition, ['above', 'below'])) {
        $footerContentPosition = 'above';
    }

    if (!$showHeaderFooter) {
        $footerHeight = 0;
        $ftr = '';
        $footerContentVal = '';
    }

    $printedAt = now()->format('d M Y, h:i A');
@endphp

<style>
    :root { --report-footer-height: {{ $footerHeight }}px; }

    /* Reserve space so content doesn't overlap fixed footer */
        .content-section, .sheet, .invoice-container, .page, .card, .container, body { }
        /* NOTE: reserve space for footer only when printing. Do not affect normal screen layout. */

    .footer-wrapper {
        /* Default: keep footer in normal flow on screen so it doesn't overlay content.
           Print-specific rules (below) will switch to fixed positioning for page bottoms. */
        position: relative;
        left: 0;
        right: 0;
        bottom: 0;
        height: var(--report-footer-height);
        overflow: hidden;
        z-index: 1;
        background: transparent;
        box-sizing: border-box;
        clear: both;
    }

    /* Layout: content area (position can be above or below the image) */
    .footer-image-area { position: relative; height: var(--report-footer-height); overflow: hidden; }

    .footer-content-area {
        position: absolute;
        left: 0;
        right: 0;
        top: 0;
        bottom: 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        text-align: left;
        padding: 6px 12px;
        box-sizing: border-box;
        font-size: 12px;
        color: #334155;
        z-index: 2000 !important; /* ensure content sits above the image */
        pointer-events: auto;
        overflow: hidden;
        white-space: nowrap;
        line-height: 1.2;
    }

    .footer-content-row {
        width: 100%;
        display: table;
        table-layout: fixed;
        line-height: 1.2;
    }

    .footer-content-main {
        display: table-cell;
        vertical-align: middle;
        text-align: left;
        padding-right: 12px;
        overflow: hidden;
    }

    .footer-printed-at {
        flex: 0 0 auto;
        font-size: 12px;
        color: #475569;
        white-space: nowrap;
        text-align: right;
        margin-left: 16px;
        line-height: 1.4;
        align-self: center;
    }

    .footer-content-area.above {
        /* Keep above content layered inside the footer area without shifting upward on screen */
        top: auto;
        bottom: 0;
    }
    .footer-content-area.below { top: auto; bottom: calc(var(--report-footer-height) * 0.08); }

    .footer-content-row {
        width: 100%;
        display: table;
        table-layout: fixed;
        line-height: 1.2;
    }

    .footer-content-main {
        display: table-cell;
        vertical-align: middle;
        text-align: left;
        padding-right: 12px;
        overflow: hidden;
    }

    /* Force any block-level elements inside the footer content to behave inline
       so the left and right items stay on the same baseline */
    .footer-content-main > * {
        display: inline-block !important;
        vertical-align: middle !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .footer-content-main p,
    .footer-content-main div {
        display: inline-block !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .footer-printed-at {
        display: table-cell;
        width: 1%;
        white-space: nowrap;
        text-align: right;
        vertical-align: middle;
        font-size: 12px;
        color: #475569;
        padding-left: 16px;
    }
    /* Footer image: keep natural aspect ratio and avoid side-cropping */
    .footer-wrapper .footer-image-area img.footer-image,
    .footer-image {
        display: block !important;
        margin: 0 auto !important;
        padding: 0 !important;
        max-width: 100% !important;
        width: auto !important;
        height: 100% !important;
        object-fit: contain !important;
        object-position: center bottom !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .footer-placeholder { width: 100%; height: var(--report-footer-height); visibility: hidden; display: block; }

    @media print {
        /* On print, make footer stick to bottom of each printed page and ensure image + content are visible */
        html, body { margin: 0 !important; padding: 0 !important; height: auto !important; overflow: visible !important; }

        /* Prevent transformed ancestors from changing fixed positioning context.
           Some templates apply `transform`/`translateZ` which makes `position: fixed`
           behave like `position: absolute` relative to that ancestor. Clear them. */
        html, body, .invoice-container, .sheet, .page, .container, .card {
            transform: none !important;
            -webkit-transform: none !important;
            -ms-transform: none !important;
        }

        .footer-wrapper {
            position: fixed !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            height: var(--report-footer-height) !important;
            overflow: visible !important;
            z-index: 9999 !important;
            background: transparent !important;
            /* ensure the footer is rendered in the viewport stacking context */
            transform: none !important;
            -webkit-transform: none !important;
        }

        /* Put the image inside an absolute area anchored to footer bottom.
           Ensure image sits under the content (lower z-index) and is decorative only. */
        .footer-image-area {
            position: absolute !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            height: 100% !important;
            z-index: 0 !important; /* keep image below content */
            overflow: visible !important;
        }

        .footer-wrapper .footer-image-area img.footer-image,
        .footer-image {
            /* Center the footer image horizontally and preserve aspect ratio to avoid horizontal cropping */
            position: absolute !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
            bottom: 0 !important;
            display: block !important;
            width: auto !important;
            max-width: 100% !important;
            height: 100% !important;
            object-fit: contain !important;
            object-position: center bottom !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            z-index: 0 !important;
            pointer-events: none !important;
        }

        /* Footer content in print: by default overlay centered inside image.
           When positioned `above`, move the content outside the image and
           render it as a single horizontal line (left content + printed-at on right). */
        .footer-content-area {
            position: absolute !important;
            left: 0 !important;
            right: 0 !important;
            top: 0 !important;
            bottom: 0 !important;
            z-index: 100000 !important; /* ensure content always sits above image */
            pointer-events: auto !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important; /* center horizontally */
            padding: 6px 12px !important;
            color: #111 !important;
            background: transparent !important;
            white-space: nowrap !important;
            text-align: center !important;
        }

        /* Default: stacked column for small footprint */
        .footer-content-row { display: flex !important; flex-direction: column !important; gap: 4px; align-items: center; justify-content: center; }
        .footer-content-main, .footer-printed-at { pointer-events: none !important; color: #111 !important; display: block; }

        /* Above-image layout: place content immediately above the footer image
           and render as a single-line row with left/right alignment. */
        .footer-content-area.above {
            top: auto !important;
            /* move content above the footer image so it is never covered; shift down by 5mm toward the image */
            bottom: calc(var(--report-footer-height) - 5mm) !important;
            justify-content: space-between !important;
            padding: 4px 10mm 4px 6mm !important; /* extra right padding to keep timestamp inside page */
        }

        .footer-content-area.above .footer-content-row {
            flex-direction: row !important;
            gap: 6px !important;
            align-items: center !important;
            justify-content: space-between !important;
            width: 100%;
            white-space: nowrap !important;
            padding-right: 4mm !important; /* extra inner space to avoid clipping */
        }

        /* Constrain main and timestamp widths so timestamp stays inside page on one line */
        .footer-content-area.above .footer-content-main {
            display: inline-block !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            flex: 1 1 auto !important;
            max-width: calc(100% - 130px) !important;
            text-align: left !important;
            font-size: 12px !important;
            padding-right: 4mm !important;
        }

        .footer-content-area.above .footer-printed-at {
            display: inline-block !important;
            white-space: nowrap !important;
            margin-left: 8px !important;
            flex: 0 0 100px !important;
            text-align: right !important;
            font-size: 12px !important;
            padding-right: 2mm !important;
            /* Shift timestamp right by 10mm (was -40mm, now -30mm) so it moves toward the right */
            transform: translateX(-30mm) !important;
        }

        /* Reserve space at page bottom so content doesn't overlap/clip the footer */
        .content-section, .page, .sheet, .invoice-container, body {
            padding-bottom: calc(var(--report-footer-height) + 12mm) !important;
            overflow: visible !important;
        }

        /* Ensure the placeholder is present when blade falls back to it */
        .footer-placeholder { display: block !important; visibility: visible !important; height: var(--report-footer-height) !important; }

        /* Avoid parent containers hiding overflow during print */
        html *, body *, .content-section, .page, .sheet, .container { overflow: visible !important; }

        /* Prevent breaking/clipping of footer blocks */
        .footer-wrapper, .footer-image-area, .footer-content-area { page-break-inside: avoid !important; break-inside: avoid !important; }
    }

    /* On screen, keep footer in normal flow and layout content with flex so it scrolls with page */
    @media screen {
        .footer-wrapper { position: relative !important; }
        .footer-placeholder { display: block; visibility: visible; }
        .footer-wrapper .footer-image-area img.footer-image { position: relative; max-height: var(--report-footer-height); }
        .footer-content-area { position: absolute; pointer-events: auto; display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; }
        .footer-content-row { display: flex; width: 100%; align-items: center; justify-content: space-between; }
        .footer-content-main { display: block; overflow: hidden; flex: 1 1 auto; text-align: left; padding-right: 12px; }
        .footer-printed-at { display: block; flex: 0 0 auto; white-space: nowrap; text-align: right; padding-left: 12px; }
    }
</style>

<div class="footer-wrapper" role="contentinfo">
    <div class="footer-image-area" aria-hidden="{{ empty($ftr) ? 'true' : 'false' }}">
        @if(!empty($ftr))
            <img src="{{ $ftr }}" class="footer-image" alt="Footer">
        @else
            <div class="footer-placeholder" aria-hidden="true"></div>
        @endif

        @if(!empty($footerContentVal))
            <div class="footer-content-area {{ $footerContentPosition === 'below' ? 'below' : 'above' }}">
                <div class="footer-content-row">
                    <div class="footer-content-main">{!! $footerContentVal !!}</div>
                    <div class="footer-printed-at">Printing Date &amp; Time: {{ $printedAt }}</div>
                </div>
            </div>
        @endif
    </div>
</div>
