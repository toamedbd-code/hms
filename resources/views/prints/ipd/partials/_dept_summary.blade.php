<div class="avoid-break">
    <div class="h2">Department-wise Billing Summary</div>

    <table class="tbl table-compact">
        <thead>
            <tr>
                <th style="width:48mm;">Department</th>
                <th style="width:20mm;" class="text-right">Gross</th>
                <th style="width:24mm;" class="text-right">Pkg Incl (-)</th>
                <th style="width:20mm;" class="text-right">Disc (-)</th>
                <th style="width:22mm;" class="text-right">Taxable</th>
                <th style="width:14mm;" class="text-right">Tax%</th>
                <th style="width:20mm;" class="text-right">Tax Amt</th>
                <th style="width:22mm;" class="text-right">Net</th>
            </tr>
        </thead>

        <tbody>
            @foreach($vm['dept_summary'] as $row)
                <tr>
                    <td class="wrap">{{ $row['department_name'] }}</td>
                    <td class="num">{{ number_format($row['gross_amount'], 2) }}</td>
                    <td class="num">{{ number_format($row['package_included_amount'], 2) }}</td>
                    <td class="num">{{ number_format($row['discount_amount'], 2) }}</td>
                    <td class="num">{{ number_format($row['taxable_amount'], 2) }}</td>
                    <td class="num">
                        @if(isset($row['tax_rate_effective']) && $row['tax_rate_effective'] !== null)
                            {{ number_format($row['tax_rate_effective'], 2) }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="num">{{ number_format($row['tax_amount'], 2) }}</td>
                    <td class="num">{{ number_format($row['net_amount'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>

        <tfoot>
            <tr>
                <th class="text-right">TOTAL</th>
                <th class="num">{{ number_format($vm['totals']['gross_total'], 2) }}</th>
                <th class="num">{{ number_format($vm['totals']['package_included_total'], 2) }}</th>
                <th class="num">{{ number_format($vm['totals']['discount_total'], 2) }}</th>
                <th class="num">{{ number_format($vm['totals']['taxable_total'], 2) }}</th>
                <th class="num"></th>
                <th class="num">{{ number_format($vm['totals']['tax_total'], 2) }}</th>
                <th class="num">{{ number_format($vm['totals']['net_total'], 2) }}</th>
            </tr>
        </tfoot>
    </table>
</div>
