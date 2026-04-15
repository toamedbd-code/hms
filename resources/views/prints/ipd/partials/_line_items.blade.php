<div>
    <div class="h2">Detailed Line Items</div>

    <table class="tbl table-compact">
        <thead>
            <tr>
                <th style="width:5mm" class="text-center">SL</th>
                <th style="width:18mm" class="text-center">Date</th>
                <th style="width:12mm" class="text-center">Dept</th>
                <th style="width:54mm">Particulars</th>
                <th style="width:8mm" class="text-right">Qty</th>
                <th style="width:12mm" class="text-right">Rate</th>
                <th style="width:14mm" class="text-right">Gross</th>
                <th style="width:7mm" class="text-center">Pkg</th>
                <th style="width:12mm" class="text-right">Disc</th>
                <th style="width:14mm" class="text-right">Taxable</th>
                <th style="width:8mm" class="text-right">Tax%</th>
                <th style="width:12mm" class="text-right">Tax</th>
                <th style="width:14mm" class="text-right">Net</th>
            </tr>
        </thead>

        <tbody>
            @foreach($vm['lines'] as $line)
                <tr>
                    <td class="center">{{ $line['sl'] }}</td>
                    <td class="center nowrap">{{ $line['service_at'] }}</td>
                    <td class="center">{{ $line['department_code'] }}</td>

                    <td class="wrap">
                        {{ $line['particulars'] }}
                        @if(!empty($line['is_package']))
                            <span class="muted small"> (Included in Package)</span>
                        @endif
                    </td>

                    <td class="num">{{ number_format($line['qty'], 2) }}</td>
                    <td class="num">{{ number_format($line['unit_price'], 2) }}</td>
                    <td class="num">{{ number_format($line['gross_amount'], 2) }}</td>

                    <td class="center">{{ !empty($line['is_package']) ? 'Y' : 'N' }}</td>

                    <td class="num">{{ number_format($line['discount_amount'], 2) }}</td>
                    <td class="num">{{ number_format($line['taxable_amount'], 2) }}</td>
                    <td class="num">{{ number_format($line['tax_rate'], 2) }}</td>
                    <td class="num">{{ number_format($line['tax_amount'], 2) }}</td>
                    <td class="num">{{ number_format($line['net_amount'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="small muted" style="margin-top: 1.5mm;">
        Note: Lines marked <span class="nowrap">Pkg=Y</span> are included in package and billed at 0.00.
    </div>
</div>
