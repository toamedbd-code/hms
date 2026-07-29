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

    $hdr = trim((string) ($headerImage ?? $header_image ?? $headerSrc ?? ''));
    $headerTitle = trim((string) ($headerTitle ?? $header_title ?? config('app.name', 'TOAMED HOSPITAL')));
    $headerSubtitle = trim((string) ($headerSubtitle ?? $header_subtitle ?? ''));
    $headerHeight = (int) ($headerHeight ?? $header_height ?? $reportHeaderHeight ?? ($invoiceDesign?->header_height ?? 0));
    $footerHeight = (int) ($footerHeight ?? $footer_height ?? $reportFooterHeight ?? ($invoiceDesign?->footer_height ?? 0));

    $resolvedInvoiceDesign = null;
    if ($allowInvoiceDesignFallback && ($hdr === '' || $headerHeight <= 0 || $footerHeight <= 0) && class_exists(\App\Models\InvoiceDesign::class)) {
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

    if ($hdr === '' && $resolvedInvoiceDesign) {
        $hdr = (string) (publicStorageUrl($resolvedInvoiceDesign->header_photo_path) ?? '');
    }

    if ($headerHeight <= 0) {
        $headerHeight = (int) ($resolvedInvoiceDesign?->header_height ?? 115);
    }

    if ($footerHeight <= 0) {
        $footerHeight = (int) ($resolvedInvoiceDesign?->footer_height ?? 70);
    }

    if (!$showHeaderFooter) {
        $headerHeight = 0;
        $hdr = '';
    }
@endphp

<style>
    :root { --report-header-height: {{ $headerHeight }}px; --report-footer-height: {{ $footerHeight }}px; }

    .print-shared-header {
        width: 100%;
        height: var(--report-header-height);
        margin: 0;
        padding: 0;
        overflow: hidden;
        box-sizing: border-box;
    }

    .print-shared-header .print-shared-header-image {
        width: 100%;
        height: 100%;
        object-fit: fill;
        display: block;
    }

    .print-shared-header .print-shared-header-placeholder {
        width: 100%;
        height: 100%;
        visibility: hidden;
    }

    .print-shared-header-fallback {
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 10px 16px;
        border-bottom: 1px solid #d0d7de;
        background: linear-gradient(90deg, #f8fafc 0%, #ffffff 100%);
        color: #0f172a;
    }

    .print-shared-header-title {
        font-size: 18px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .print-shared-header-subtitle {
        margin-top: 4px;
        font-size: 12px;
        color: #475569;
    }
</style>

<div class="print-shared-header header-section">
    @if (!empty($hdr))
        <img src="{{ $hdr }}" alt="Header" class="print-shared-header-image header-image header-img">
    @else
        @if($showHeaderFooter)
            <div class="print-shared-header-fallback">
                <div class="print-shared-header-title">{{ $headerTitle }}</div>
                @if($headerSubtitle !== '')
                    <div class="print-shared-header-subtitle">{{ $headerSubtitle }}</div>
                @endif
            </div>
        @else
            <div class="print-shared-header-placeholder header-placeholder"></div>
        @endif
    @endif
</div>
