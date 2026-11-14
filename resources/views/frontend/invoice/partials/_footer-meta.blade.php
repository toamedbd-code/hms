<div class="footer-meta-wrap {{ $footerMetaClass }}">
    <div class="footer-date-time">
        @if($hasCustomFooterContent)
            <div class="footer-custom-line">
                {!! $footerPartsHtml !!}
            </div>
        @endif

        <div class="footer-meta-row">
            <span class="footer-meta-left">{{ $footerFallbackLine }}</span>
            <span class="footer-meta-right">Printing Date &amp; Time: {{ $footerPrintedAt }}</span>
        </div>
    </div>
</div>
