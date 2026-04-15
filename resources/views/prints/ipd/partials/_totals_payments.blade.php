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
    // If due-collections were recorded separately, subtract them so invoice shows updated due.
    $dueCollectedTotal = (float)($vm['payments']['due_collected_total'] ?? $vm['payments']['due_collected'] ?? 0);
    if ($dueCollectedTotal > 0) {
        $due = max(0, $due - $dueCollectedTotal);
    }
@endphp

{{-- Keep totals/settlement together (best effort). If it doesn't fit, consider forcing a new page before this partial. --}}
<div class="avoid-break">
    <div class="h2">Totals & Settlement</div>

    <table class="plain">
        <tr>
            {{-- LEFT COLUMN: Totals + (optional) Insurance split --}}
            <td style="width: 52%; vertical-align: top;">
                <div class="box">
                    <div class="h2" style="margin-bottom: 1mm;">Bill Totals</div>

                    <table class="plain">
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
                            <td style="padding-top: 1.2mm; border-top: 0.2mm solid #333;" class="h2">
                                NET PAYABLE
                            </td>
                            <td style="padding-top: 1.2mm; border-top: 0.2mm solid #333;" class="num h2 nowrap">
                                {{ number_format($netTotal, 2) }}
                            </td>
                        </tr>
                    </table>
                </div>

                @if($isInsurance)
                    <div style="height: 2mm;"></div>

                    <div class="box">
                        <div class="h2" style="margin-bottom: 1mm;">Insurance / TPA Split</div>

                        <table class="plain">
                            <tr>
                                <td class="small muted">Net Bill Amount</td>
                                <td class="num nowrap">{{ number_format($netTotal, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="small muted">Approved Amount</td>
                                <td class="num nowrap">{{ number_format($insuranceApproved, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="small muted">Non-payable / Deduction</td>
                                <td class="num nowrap">{{ number_format($insuranceNonPayable, 2) }}</td>
                            </tr>
                            <tr>
                                <td style="padding-top: 1.2mm; border-top: 0.2mm solid #333;" class="h2">
                                    Patient Payable
                                </td>
                                <td style="padding-top: 1.2mm; border-top: 0.2mm solid #333;" class="num h2 nowrap">
                                    {{ number_format($patientFinalPayable, 2) }}
                                </td>
                            </tr>
                        </table>

                        <div class="small muted" style="margin-top: 1mm;">
                            Approval No: {{ $vm['payer']['approval_no'] ?? '—' }}
                            @if(!empty($vm['insurance']['remarks']))
                                <br>Remarks: {{ $vm['insurance']['remarks'] }}
                            @endif
                        </div>
                    </div>
                @endif
            </td>

            {{-- RIGHT COLUMN: Settlement + payment modes --}}
            <td style="width: 48%; vertical-align: top;">
                <div class="box">
                    <div class="h2" style="margin-bottom: 1mm;">Final Settlement</div>

                    <table class="plain">
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
                            <td style="padding-top: 1.2mm; border-top: 0.2mm solid #333;" class="h2">
                                Patient Final Payable
                            </td>
                            <td style="padding-top: 1.2mm; border-top: 0.2mm solid #333;" class="num h2 nowrap">
                                {{ number_format($patientFinalPayable, 2) }}
                            </td>
                        </tr>

                        <tr>
                            <td class="small muted">Amount Paid</td>
                            <td class="num nowrap">{{ number_format($paidAfterAdvance, 2) }}</td>
                        </tr>

                        <tr>
                            <td style="padding-top: 1.2mm; border-top: 0.2mm solid #333;" class="h2">
                                Due / Refund
                            </td>
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

                    <div class="hr"></div>

                    <div class="h2" style="margin-bottom: 1mm;">Payment Mode Breakdown</div>
                    <table class="plain">
                        <tr>
                            <td class="small muted">Cash</td>
                            <td class="num nowrap">{{ number_format($vm['payments']['mode_breakdown']['cash'] ?? 0, 2) }}</td>
                            <td class="small muted">Card</td>
                            <td class="num nowrap">{{ number_format($vm['payments']['mode_breakdown']['card'] ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="small muted">MFS</td>
                            <td class="num nowrap">{{ number_format($vm['payments']['mode_breakdown']['mfs'] ?? 0, 2) }}</td>
                            <td class="small muted">Bank</td>
                            <td class="num nowrap">{{ number_format($vm['payments']['mode_breakdown']['bank'] ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="small muted">Cheque</td>
                            <td class="num nowrap">{{ number_format($vm['payments']['mode_breakdown']['cheque'] ?? 0, 2) }}</td>
                            <td class="small muted"></td>
                            <td class="num nowrap"></td>
                        </tr>
                    </table>

                    <div class="small muted" style="margin-top: 1mm;">
                        Receipt Nos: {{ $vm['payments']['receipt_nos'] ?? '—' }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div style="height: 2mm;"></div>

    {{-- Signatures (keep together) --}}
    <div class="box avoid-break">
        <table class="plain">
            <tr>
                <td style="width: 33%;">
                    <div class="small muted">Prepared By</div>
                    <div style="height: 10mm;"></div>
                    <div class="hr"></div>
                </td>
                <td style="width: 34%;">
                    <div class="small muted">Checked By</div>
                    <div style="height: 10mm;"></div>
                    <div class="hr"></div>
                </td>
                <td style="width: 33%;">
                    <div class="small muted">Authorized By / Cashier</div>
                    <div style="height: 10mm;"></div>
                    <div class="hr"></div>
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <div class="small muted">Patient/Attendant Signature</div>
                    <div style="height: 10mm;"></div>
                    <div class="hr"></div>
                </td>
            </tr>
            <tr>
                <td colspan="3" class="small muted">
                    Important: This is a computer-generated invoice. Refund (if any) is subject to verification as per hospital policy.
                </td>
            </tr>
        </table>
    </div>
</div>
