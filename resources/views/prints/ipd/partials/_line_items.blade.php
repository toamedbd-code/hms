<div>
    <div class="h2">Detailed Line Items</div>

    @php
        $lineItems = collect($vm['lines'] ?? []);
        $showRate = $lineItems->contains(fn($line) => (float) ($line['unit_price'] ?? 0) !== 0.0);
        $showGross = $lineItems->contains(fn($line) => (float) ($line['gross_amount'] ?? 0) !== 0.0);
        $showPkg = $lineItems->contains(fn($line) => !empty($line['is_package']));
        $showDiscount = $lineItems->contains(fn($line) => (float) ($line['discount_amount'] ?? 0) !== 0.0);
        $showTaxRate = $lineItems->contains(fn($line) => isset($line['tax_rate']) && $line['tax_rate'] !== null && (float) ($line['tax_rate'] ?? 0) !== 0.0);
        $showTaxAmount = $lineItems->contains(fn($line) => (float) ($line['tax_amount'] ?? 0) !== 0.0);
        $showTax = $showTaxRate || $showTaxAmount;
        $showTaxable = $showTax;
    @endphp

    <table class="tbl table-compact">
        <thead>
            <tr>
                <th style="width:5mm" class="text-center">SL</th>
                <th style="width:24mm" class="text-center">Date</th>
                <th style="width:12mm" class="text-center">Dept</th>
                <th style="width:35mm">Particulars</th>
                <th style="width:9mm" class="text-right">Qty</th>
                @if($showRate)
                    <th style="width:14mm" class="text-right">Rate</th>
                @endif
                @if($showGross)
                    <th style="width:16mm" class="text-right">Gross</th>
                @endif
                @if($showPkg)
                    <th style="width:7mm" class="text-center">Pkg</th>
                @endif
                @if($showDiscount)
                    <th style="width:12mm" class="text-right">Disc</th>
                @endif
                @if($showTaxable)
                    <th style="width:14mm" class="text-right">Taxable</th>
                @endif
                @if($showTaxRate)
                    <th style="width:8mm" class="text-right">Tax%</th>
                @endif
                @if($showTaxAmount)
                    <th style="width:12mm" class="text-right">Tax</th>
                @endif
                <th style="width:14mm" class="text-right">Net</th>
            </tr>
        </thead>

        <tbody>
            @foreach($lineItems as $line)
                @php
                    $qty = (float) ($line['qty'] ?? 0);
                    $unitPrice = (float) ($line['unit_price'] ?? 0);
                    $gross = (float) ($line['gross_amount'] ?? 0);
                    $discount = (float) ($line['discount_amount'] ?? 0);
                    $taxable = (float) ($line['taxable_amount'] ?? 0);
                    $taxRate = $line['tax_rate'] ?? null;
                    $taxAmount = (float) ($line['tax_amount'] ?? 0);
                    $net = (float) ($line['net_amount'] ?? 0);
                @endphp
                @if($qty !== 0.0 || $unitPrice !== 0.0 || $gross !== 0.0 || $discount !== 0.0 || $taxable !== 0.0 || $taxAmount !== 0.0 || $net !== 0.0 || !empty($line['particulars']))
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
                        <td class="num">{{ number_format($qty, 2) }}</td>
                        @if($showRate)
                            <td class="num">{{ number_format($unitPrice, 2) }}</td>
                        @endif
                        @if($showGross)
                            <td class="num">{{ number_format($gross, 2) }}</td>
                        @endif
                        @if($showPkg)
                            <td class="center">{{ !empty($line['is_package']) ? 'Y' : 'N' }}</td>
                        @endif
                        @if($showDiscount)
                            <td class="num">{{ number_format($discount, 2) }}</td>
                        @endif
                        @if($showTaxable)
                            <td class="num">{{ number_format($taxable, 2) }}</td>
                        @endif
                        @if($showTaxRate)
                            <td class="num">{{ number_format((float) ($taxRate ?? 0), 2) }}</td>
                        @endif
                        @if($showTaxAmount)
                            <td class="num">{{ number_format($taxAmount, 2) }}</td>
                        @endif
                        <td class="num">{{ number_format($net, 2) }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

</div>
