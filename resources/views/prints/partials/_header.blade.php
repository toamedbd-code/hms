@php
    $showHeaderFooter = isset($showHeaderFooter) ? (bool) $showHeaderFooter : (isset($show_header_footer) ? (bool) $show_header_footer : true);
    $hdr = $headerImage ?? $header_image ?? $headerSrc ?? '';
    $headerHeight = (int) ($headerHeight ?? $header_height ?? $reportHeaderHeight ?? ($invoiceDesign?->header_height ?? 115));
    $footerHeight = (int) ($footerHeight ?? $footer_height ?? $reportFooterHeight ?? ($invoiceDesign?->footer_height ?? 70));

    if (!$showHeaderFooter) {
        $headerHeight = 0;
        $hdr = '';
    }
@endphp

<style>
    :root { --report-header-height: {{ $headerHeight }}px; --report-footer-height: {{ $footerHeight }}px; }
</style>

<div class="header-section">
    @if (!empty($hdr))
        <img src="{{ $hdr }}" alt="Header" class="header-image header-img fixed fixed-header">
    @else
        <div class="header-placeholder fixed fixed-header"></div>
    @endif
</div>
