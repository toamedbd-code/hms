<div class="avoid-break">
    <div class="h2">Department-wise Billing Summary</div>

    @php
        $deptSummary = collect($vm['dept_summary'] ?? []);
        $showPkgIncl = $deptSummary->contains(fn($row) => (float) ($row['package_included_amount'] ?? 0) !== 0.0);
        $showDiscount = $deptSummary->contains(fn($row) => (float) ($row['discount_amount'] ?? 0) !== 0.0);
        $showTax = $deptSummary->contains(fn($row) => (float) ($row['tax_amount'] ?? 0) !== 0.0 || isset($row['tax_rate_effective']) && $row['tax_rate_effective'] !== null);
        $showTaxable = $showTax;
    @endphp

    <table class="tbl table-compact">
        <thead>
            <tr>
                <th style="width:48mm;">Department</th>
                <th style="width:20mm;" class="text-right">Gross</th>
                @if($showPkgIncl)
                    <th style="width:24mm;" class="text-right">Pkg Incl (-)</th>
                @endif
                @if($showDiscount)
                    <th style="width:20mm;" class="text-right">Disc (-)</th>
                @endif
                @if($showTaxable)
                    <th style="width:22mm;" class="text-right">Taxable</th>
                @endif
                @if($showTax)
                    <th style="width:14mm;" class="text-right">Tax%</th>
                    <th style="width:20mm;" class="text-right">Tax Amt</th>
                @endif
                <th style="width:22mm;" class="text-right">Net</th>
            </tr>
        </thead>

        <tbody>
            @foreach($deptSummary as $row)
                @php
                    $gross = (float) ($row['gross_amount'] ?? 0);
                    $package = (float) ($row['package_included_amount'] ?? 0);
                    $discount = (float) ($row['discount_amount'] ?? 0);
                    $taxable = (float) ($row['taxable_amount'] ?? 0);
                    $taxAmount = (float) ($row['tax_amount'] ?? 0);
                    $net = (float) ($row['net_amount'] ?? 0);
                    $taxRate = $row['tax_rate_effective'] ?? null;
                @endphp
                @if($gross !== 0.0 || $package !== 0.0 || $discount !== 0.0 || $taxable !== 0.0 || $taxAmount !== 0.0 || $net !== 0.0)
                    <tr>
                        <td class="wrap">{{ $row['department_name'] }}</td>
                        <td class="num">{{ number_format($gross, 2) }}</td>
                        @if($showPkgIncl)
                            <td class="num">{{ number_format($package, 2) }}</td>
                        @endif
                        @if($showDiscount)
                            <td class="num">{{ number_format($discount, 2) }}</td>
                        @endif
                        @if($showTaxable)
                            <td class="num">{{ number_format($taxable, 2) }}</td>
                        @endif
                        @if($showTax)
                            <td class="num">
                                @if($taxRate !== null)
                                    {{ number_format((float) $taxRate, 2) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="num">{{ number_format($taxAmount, 2) }}</td>
                        @endif
                        <td class="num">{{ number_format($net, 2) }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>

        <tfoot>
            <tr>
                <th class="text-right">TOTAL</th>
                <th class="num">{{ number_format($vm['totals']['gross_total'] ?? 0, 2) }}</th>
                @if($showPkgIncl)
                    <th class="num">{{ number_format($vm['totals']['package_included_total'] ?? 0, 2) }}</th>
                @endif
                @if($showDiscount)
                    <th class="num">{{ number_format($vm['totals']['discount_total'] ?? 0, 2) }}</th>
                @endif
                @if($showTaxable)
                    <th class="num">{{ number_format($vm['totals']['taxable_total'] ?? 0, 2) }}</th>
                @endif
                @if($showTax)
                    <th class="num"></th>
                    <th class="num">{{ number_format($vm['totals']['tax_total'] ?? 0, 2) }}</th>
                @endif
                <th class="num">{{ number_format($vm['totals']['net_total'] ?? 0, 2) }}</th>
            </tr>
        </tfoot>
    </table>
</div>
