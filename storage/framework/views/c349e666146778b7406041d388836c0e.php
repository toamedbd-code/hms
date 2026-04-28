<?php
    $showHeaderFooter = isset($showHeaderFooter) ? (bool) $showHeaderFooter : (isset($show_header_footer) ? (bool) $show_header_footer : true);
    $hdr = $headerImage ?? $header_image ?? $headerSrc ?? '';
    $headerHeight = (int) ($headerHeight ?? $header_height ?? $reportHeaderHeight ?? ($invoiceDesign?->header_height ?? 115));
    $footerHeight = (int) ($footerHeight ?? $footer_height ?? $reportFooterHeight ?? ($invoiceDesign?->footer_height ?? 70));

    if (!$showHeaderFooter) {
        $headerHeight = 0;
        $hdr = '';
    }
?>

<style>
    :root { --report-header-height: <?php echo e($headerHeight); ?>px; --report-footer-height: <?php echo e($footerHeight); ?>px; }
</style>

<div class="header-section">
    <?php if(!empty($hdr)): ?>
        <img src="<?php echo e($hdr); ?>" alt="Header" class="header-image header-img fixed fixed-header">
    <?php else: ?>
        <div class="header-placeholder fixed fixed-header"></div>
    <?php endif; ?>
</div>
<?php /**PATH C:\laragon\www\hms\resources\views/prints/partials/_header.blade.php ENDPATH**/ ?>