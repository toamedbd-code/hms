@php
    $hasPackage = !empty($vm['package']['exists']);

    $payerType = $vm['payer']['payer_type'] ?? 'SELF';
    $isInsurance = in_array($payerType, ['INSURANCE', 'TPA', 'CORPORATE'], true);

    $netTotal = (float)($vm['totals']['net_total'] ?? 0);

    $insuranceApproved = (float)($vm['insurance']['approved_amount'] ?? 0);
    $insuranceNonPayable = (float)($vm['insurance']['non_payable_amount'] ?? 0);

    $advanceTotal = (float)($vm['payments']['advance_total'] ?? 0);
    $insurancePayable = (float)($vm['payments']['insurance_payable'] ?? 0);

    $patientFinalPayable = (float)($vm['payments']['patient_final_payable'] ?? 0);
    $paidAfterAdvance = (float)($vm['payments']['paid_total_excluding_advances'] ?? 0);

    $due = (float)($vm['payments']['due_amount'] ?? 0);
    $refund = (float)($vm['payments']['refund_amount'] ?? 0);
@endphp

{{-- Keep totals/settlement together (best effort). If it doesn't fit, consider forcing a new page before this partial. --}}
<div class="avoid-break">
    <div class="box" style="padding: 1mm;">
        <div class="h2" style="margin-bottom: 1mm;">Bill Totals & Settlement</div>

        <table class="plain" style="width:100%; table-layout: fixed;">
            <tr>
                <td class="small muted">Gross Total</td>
                <td class="num nowrap">{{ number_format($vm['totals']['gross_total'] ?? 0, 2) }}</td>
            </tr>

            @if($hasPackage)
                <tr>
                    <td class="small muted">Package Included (-)</td>
                    <td class="num nowrap">{{ number_format($vm['totals']['package_included_total'] ?? 0, 2) }}</td>
                </tr>
            @endif

            <tr>
                <td class="small muted">Total Discount (-)</td>
                <td class="num nowrap">{{ number_format($vm['totals']['discount_total'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td class="small muted">Total Taxable</td>
                <td class="num nowrap">{{ number_format($vm['totals']['taxable_total'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td class="small muted">Total GST/VAT/Service Tax (+)</td>
                <td class="num nowrap">{{ number_format($vm['totals']['tax_total'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td style="padding-top: 1.2mm; border-top: 0.2mm solid #333;" class="h2">NET PAYABLE</td>
                <td style="padding-top: 1.2mm; border-top: 0.2mm solid #333;" class="num h2 nowrap">{{ number_format($netTotal, 2) }}</td>
            </tr>
            <tr>
                <td class="small muted">Advance / Deposits (-)</td>
                <td class="num nowrap">{{ number_format($advanceTotal, 2) }}</td>
            </tr>
            @if($isInsurance)
                <tr>
                    <td class="small muted">Insurance/TPA Payable (-)</td>
                    <td class="num nowrap">{{ number_format($insurancePayable, 2) }}</td>
                </tr>
            @endif
            <tr>
                <td style="padding-top: 1.2mm; border-top: 0.2mm solid #333;" class="h2">Patient Final Payable</td>
                <td style="padding-top: 1.2mm; border-top: 0.2mm solid #333;" class="num h2 nowrap">{{ number_format($patientFinalPayable, 2) }}</td>
            </tr>
            <tr>
                <td class="small muted">Amount Paid</td>
                <td class="num nowrap">{{ number_format($paidAfterAdvance, 2) }}</td>
            </tr>
            <tr>
                <td style="padding-top: 1.2mm; border-top: 0.2mm solid #333;" class="h2">Due / Refund</td>
                <td style="padding-top: 1.2mm; border-top: 0.2mm solid #333;" class="num h2 nowrap">
                    @if($due > 0)
                        Due: {{ number_format($due, 2) }}
                    @elseif($refund > 0)
                        Refund: {{ number_format($refund, 2) }}
                    @else
                        0.00
                    @endif
                </td>
            </tr>
        </table>
    </div>
</div>
