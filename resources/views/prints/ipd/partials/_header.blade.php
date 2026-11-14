<div class="header">
    <table class="plain">
        <tr>
            @if(!empty($vm['design']['header_image']))
                <td colspan="2">
                    <img
                        src="{{ $vm['design']['header_image'] }}"
                        alt=""
                        class="invoice-design-header"
                        style="height: {{ min(180, max(0, (int) ($vm['design']['header_height'] ?? 115))) }}px;"
                    >
                </td>
            @else
                <td style="width: 25mm;">
                    @if(!empty($vm['hospital']['logo_url']))
                        <img src="{{ public_path($vm['hospital']['logo_url']) }}" style="width:22mm; height:auto;">
                    @endif
                </td>

                <td>
                    <div class="h1">{{ $vm['hospital']['name'] }}</div>
                    <div class="small">
                        {{ $vm['hospital']['address'] }}
                        @if(!empty($vm['hospital']['phone'])) | {{ $vm['hospital']['phone'] }} @endif
                        @if(!empty($vm['hospital']['email'])) | {{ $vm['hospital']['email'] }} @endif
                    </div>
                    <div class="small">
                        BIN/VAT: {{ $vm['hospital']['bin_vat_no'] }}
                        @if(!empty($vm['hospital']['tax_reg_no']))
                            | Tax Reg: {{ $vm['hospital']['tax_reg_no'] }}
                        @endif
                    </div>
                </td>
            @endif

            <td style="width: 62mm;">
                <div class="box">
                    <div class="h2 text-center">IPD FINAL DISCHARGE INVOICE</div>
                    <div class="small">
                        Invoice No: <span class="nowrap">{{ $vm['invoice']['invoice_no'] }}</span><br>
                        IPD No: <span class="nowrap">{{ $vm['invoice']['ipd_no'] }}</span><br>
                        UHID: <span class="nowrap">{{ $vm['invoice']['uhid'] }}</span><br>
                        Print: <span class="nowrap">{{ $vm['invoice']['printed_at'] }}</span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div class="hr"></div>
</div>
