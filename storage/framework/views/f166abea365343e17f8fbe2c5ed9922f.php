<?php
    $ftr = $footer_image ?? $footerImage ?? $footerSrc ?? $footer ?? '';
    $footerContentVal = $footer_content ?? $footerContent ?? $footerHtml ?? $footer_html ?? config('app.invoice_footer_fallback_line', '');
    $footerHeight = (int) ($footerHeight ?? $footer_height ?? $reportFooterHeight ?? ($invoiceDesign?->footer_height ?? 70));

    $footerContentPosition = strtolower(trim((string) ($footer_content_position ?? $footerContentPosition ?? ($invoiceDesign?->footer_content_position ?? 'above'))));
    if (!in_array($footerContentPosition, ['above', 'below'])) {
        $footerContentPosition = 'above';
    }
?>

<style>
    :root { --report-footer-height: <?php echo e($footerHeight); ?>px; }

    /* Reserve space so content doesn't overlap fixed footer */
    .content-section, .sheet, .invoice-container, .page, .card, .container { padding-bottom: calc(var(--report-footer-height) + 12px) !important; }

    .footer-wrapper {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        height: var(--report-footer-height);
        overflow: visible; /* allow content to be visible above image */
        z-index: 9999;
        background: transparent;
        box-sizing: border-box;
    }

    /* Layout: content area (position can be above or below the image) */
    .footer-content-area {
        position: absolute;
        left: 0;
        right: 0;
        min-height: calc(var(--report-footer-height) * 0.35);
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 6px 8px;
        box-sizing: border-box;
        font-size: 12px;
        color: #334155;
        z-index: 2000 !important; /* ensure content sits above the image */
        pointer-events: none;
        overflow: visible;
        white-space: normal;
        line-height: 1.2;
    }

    .footer-content-area.above { top: 0; }
    .footer-content-area.below { top: auto; bottom: calc(var(--report-footer-height) * 0.08); }

    .footer-image-area {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        height: var(--report-footer-height);
        display: block;
        z-index: 1000; /* image below content */
        overflow: hidden;
        padding: 0; /* remove inner padding to avoid side gaps */
        box-sizing: border-box;
        background-position: center bottom;
        background-repeat: no-repeat;
        background-size: cover;
    }

    /* Force footer image to span full width and not be constrained by template-specific rules */
    .footer-wrapper .footer-image-area img.footer-image,
    .footer-image {
        width: 100% !important;
        max-width: 100% !important;
        height: auto !important;
        margin: 0 !important;
        padding: 0 !important;
        display: block !important;
        object-fit: contain !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .footer-placeholder { width: 100%; height: var(--report-footer-height); visibility: hidden; }

    @media print {
        .footer-wrapper { position: fixed !important; }
        .footer-wrapper .footer-image-area img.footer-image { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        .footer-content-area { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }
</style>

<div class="footer-wrapper" role="contentinfo">
    <?php if($footerContentPosition === 'above'): ?>
        <?php if(!empty($footerContentVal)): ?>
            <div class="footer-content-area above"><?php echo $footerContentVal; ?></div>
        <?php endif; ?>

        <div class="footer-image-area" aria-hidden="<?php echo e(empty($ftr) ? 'true' : 'false'); ?>">
            <?php if(!empty($ftr)): ?>
                <img src="<?php echo e($ftr); ?>" class="footer-image" alt="Footer">
            <?php else: ?>
                <div class="footer-placeholder" aria-hidden="true"></div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <div class="footer-image-area" aria-hidden="<?php echo e(empty($ftr) ? 'true' : 'false'); ?>">
            <?php if(!empty($ftr)): ?>
                <img src="<?php echo e($ftr); ?>" class="footer-image" alt="Footer">
            <?php else: ?>
                <div class="footer-placeholder" aria-hidden="true"></div>
            <?php endif; ?>
        </div>

        <?php if(!empty($footerContentVal)): ?>
            <div class="footer-content-area below"><?php echo $footerContentVal; ?></div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php /**PATH C:\laragon\www\hms\resources\views/prints/partials/_footer.blade.php ENDPATH**/ ?>