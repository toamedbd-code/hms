<?php
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
?>

<style>
    :root { --report-header-height: <?php echo e($headerHeight); ?>px; --report-footer-height: <?php echo e($footerHeight); ?>px; }

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
</style>

<div class="print-shared-header header-section">
    <?php if(!empty($hdr)): ?>
        <img src="<?php echo e($hdr); ?>" alt="Header" class="print-shared-header-image header-image header-img">
    <?php else: ?>
        <div class="print-shared-header-placeholder header-placeholder"></div>
    <?php endif; ?>
</div>
<?php /**PATH C:\laragon\www\hms\resources\views/prints/partials/_header.blade.php ENDPATH**/ ?>