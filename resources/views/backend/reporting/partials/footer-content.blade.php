@if($content !== '' || $printedAt !== '')
    <div class="footer-content">
        <div class="footer-content-main">{!! $content !!}</div>
        @if($printedAt !== '')
            <div class="footer-printed-at">Printing Date: <span class="print-datetime">{{ $printedAt }}</span></div>
        @endif
    </div>
@else
    <div class="footer-placeholder"></div>
@endif
