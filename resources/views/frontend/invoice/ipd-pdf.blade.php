<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>IPD Invoice</title>
    <style>
        @page {
            margin: 0mm 0mm;
        }

        @php
            $__inv_header_h = (int) ($header_height ?? 115);
            $__inv_footer_h = (int) ($footer_height ?? 70);
        @endphp
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }

        .header-image,
        .footer-image {
            width: 100%;
            max-height: {{ $__inv_header_h }}px;
            display: block;
            object-fit: cover;
            height: {{ $__inv_header_h }}px;
        }

        .footer-content {
            /* keep footer content relative inside footer area so it appears above the footer image */
            position: relative;
            left: 0;
            right: 0;
            width: 100%;
            text-align: center;
            z-index: 60;
            border-top: none !important;
            padding: 6px 12px 0;
            background: transparent;
        }
        @media print and (min-width: 149mm) {
            .header-image {
                max-height: {{ $__inv_header_h }}px;
            }

            .footer-image {
                max-height: {{ $__inv_footer_h + 10 }}px;
            }

            .header-placeholder {
                height: {{ $__inv_header_h }}px;
            }

            .footer-placeholder {
                height: {{ $__inv_footer_h }}px;
            }

            .content {
                padding-bottom: {{ $__inv_footer_h * 2 }}px;
            }
        }

        @media print and (max-width: 148mm), screen and (max-width: 148mm) {
            body {
                font-size: 11px;
            }

            .header-image,
            .footer-image {
                max-height: {{ min($__inv_header_h, 58) }}px;
            }

            .header-placeholder,
            .footer-placeholder {
                height: {{ min($__inv_footer_h, 58) }}px;
            }

            .content {
                padding: 8px 8px {{ min($__inv_footer_h * 2, 64) }}px;
            }
        }

        .content-section {
            padding: 0 5mm;
            padding-bottom: {{ max($__inv_footer_h + 18, 96) }}px;
        }

        .title-section-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .title-cell-center {
            width: 50%;
            text-align: center;
            vertical-align: middle;
        }

        .title {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 2px;
            font-family: Verdana, Geneva, Tahoma, sans-serif;
        }

        .barcode-cell {
            width: 25%;
            text-align: right;
            vertical-align: top;
        }

        .barcode-cell-left {
            width: 25%;
            text-align: left;
            vertical-align: top;
        }

        .barcode-image {
            height: 25px;
            width: 120px;
        }

        .info-table,
        .payments-table,
        .items-table,
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 10px;
            font-size: inherit;
            table-layout: fixed;
        }

        .info-table td,
        .payments-table td,
        .payments-table th,
        .items-table td,
        .items-table th,
        .totals-table td {
            padding: 2px 6px;
            vertical-align: top;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        .label {
            font-weight: bold;
            width: 16%;
            white-space: nowrap;
        }

        .colon {
            width: 2%;
            text-align: center;
            white-space: nowrap;
        }

        .value {
            width: 32%;
            padding-right: 6px;
            min-width: 0;
        }

        .section-title {
            font-weight: bold;
            margin: 10px 0 6px;
        }

        .payments-table,
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            font-size: inherit;
            border: 1px solid #bdbdbd;
        }

        .payments-table th,
        .items-table th {
            text-align: left;
            padding: 6px 4px;
            font-weight: bold;
            border: 1px solid #b8b8b8;
            background-color: #f8f8f8;
            white-space: nowrap;
        }

        .totals-table {
            width: 100%;
            font-size: inherit;
            margin-top: 0px;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 2px 4px;
            vertical-align: top;
            border-bottom: 1px solid #c3c3c3;
        }

        .totals-table tr:last-child td {
            border-bottom: none;
        }

        .totals-table .label-col {
            text-align: left;
            width: 60%;
        }

        .totals-table .amount-col {
            text-align: right;
            width: 40%;
        }

        .payments-table td,
        .items-table td {
            padding: 5px 4px;
            vertical-align: top;
            border: 1px solid #c4c4c4;
            min-width: 0;
            word-break: break-word;
            overflow-wrap: anywhere;
            white-space: normal;
        }

        .items-table td:nth-child(2) {
            min-width: 0;
        }

        .items-table td:nth-child(3),
        .items-table td:nth-child(4),
        .items-table td:nth-child(5),
        .amount {
            text-align: right;
            white-space: nowrap;
        }

        .bottom-section {
            width: 100%;
            border: none;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .bottom-section td {
            vertical-align: top;
            width: 50%;
            padding: 0;
            border: none;
        }

        .left-bottom {
            padding-right: 15px;
        }

        .right-bottom {
            padding-left: 15px;
        }

        .due-section {
            margin: 8px 0;
        }

        .prepared-portal-row {
            margin-top: 10px;
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .prepared-by {
            display: inline-block;
            vertical-align: top;
            width: calc(100% - 108px);
            min-width: 0;
            word-break: break-word;
            overflow-wrap: anywhere;
            font-size: 13px;
        }

        .portal-qr {
            display: inline-block;
            vertical-align: top;
            width: 92px;
            min-width: 92px;
            margin-top: 6px;
            text-align: left;
        }

        .portal-qr img {
            width: 92px;
            height: 92px;
            background: #fff;
            display: block;
        }

        .due-badge,
        .paid-badge {
            color: #ffffff !important;
            padding: 4px 8px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 4px;
            font-size: 16px;
            border-radius: 2px;
            border: 1px solid transparent;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            -webkit-text-fill-color: #ffffff !important;
            text-fill-color: #ffffff !important;
        }

        .due-badge {
            background-color: #ff4444 !important;
            border-color: #ff4444 !important;
        }

        .paid-badge {
            background-color: #28a745 !important;
            border-color: #28a745 !important;
        }

        .totals-table,
        .summary-table,
        .history-table {
            width: 100%;
            font-size: inherit;
            margin-top: 0px;
            border-collapse: collapse;
        }

        .totals-table td,
        .summary-table td,
        .history-table td,
        .history-table th {
            padding: 2px 4px;
            vertical-align: top;
        }

        .totals-table td {
            border-bottom: 1px solid #c3c3c3;
        }

        .totals-table tr:last-child td {
            border-bottom: none;
        }

        .summary-table .label-col,
        .totals-table .label-col,
        .history-table th {
            text-align: left;
            width: 60%;
            font-weight: 600;
        }

        .summary-table .amount-col,
        .totals-table .amount-col,
        .history-table td {
            text-align: right;
            width: 40%;
        }

        .history-table th {
            background-color: #f8f8f8;
        }

        .amount-words-inline {
            padding-top: 6px;
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            line-height: 1.3;
        }

        .prepared-by {
            display: block;
            vertical-align: top;
            width: 100%;
            min-width: 0;
            word-break: break-word;
            overflow-wrap: anywhere;
            font-size: 13px;
            margin-top: 8px;
        }

        .portal-qr {
            display: block;
            vertical-align: top;
            width: 92px;
            min-width: 92px;
            margin-top: 6px;
            margin-left: 0;
            float: left;
            text-align: left;
        }

        .portal-qr img {
            width: 92px;
            height: 92px;
            background: #fff;
            display: block;
        }

        .clearfix {
            clear: both;
        }

        .footer {
            position: static;
            bottom: auto;
            left: auto;
            right: auto;
            padding: 0;
            margin: 0;
            width: 100%;
        }

        .footer-wrapper {
            margin-top: 8px;
        }

        .footer-placeholder {
            display: block;
            width: 100%;
            height: {{ max($__inv_footer_h + 10, 80) }}px;
        }

        .footer-content {
            padding: 0 12px;
            margin: 0 0 6px;
            font-size: 12px;
        }

        .powered-by {
            padding: 0 12px;
            margin: 0 0 6px;
            font-size: 11px;
        }

        .footer-meta {
            width: 100%;
            padding: 0 12px 6px;
            font-size: 11px;
        }
        
        @media print {
            body {
                padding-top: 120px;
                padding-bottom: 120px;
            }

            .header-image,
            .header-placeholder {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                width: 100%;
                z-index: 50;
            }

            .footer-image,
            .footer-placeholder,
            .footer {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                width: 100%;
                max-height: {{ $__inv_footer_h + 10 }}px;
                object-fit: contain;
                z-index: 10;
                display: block;
            }

            /* Keep footer content inside footer container so it displays like report print */
            .footer-content {
                position: relative;
                left: 0;
                right: 0;
                width: 100%;
                text-align: center;
                z-index: 60;
                border-top: none !important;
                padding-top: 0;
                background: transparent;
            }

            .header-placeholder, .footer-placeholder { display: none; }

            .due-badge {
                background-color: #ff4444 !important;
                color: #ffffff !important;
                box-shadow: none !important;
            }

            .paid-badge {
                background-color: #28a745 !important;
                color: #ffffff !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>

<body>
    @include('prints.partials._header')

    <div class="content-section">
        @php
            $barcodeImage = $barcode ?? '';
            $barcodeLabel = $ipd_id ?? ($bill_number ?? '');
        @endphp

        <table class="title-section-table">
            <tr>
                <td class="barcode-cell-left">
                    @if(!empty($barcodeImage))
                        <img src="{{ $barcodeImage }}" alt="Barcode" class="barcode-image" />
                    @elseif(!empty($barcodeLabel))
                        {!! DNS1D::getBarcodeHTML($barcodeLabel, 'C128', 1, 30) !!}
                    @endif
                </td>
                <td class="title-cell-center">
                    <div class="title">MONEY RECEIPT</div>
                </td>
                <td class="barcode-cell">
                    @if(!empty($barcodeImage))
                        <img src="{{ $barcodeImage }}" alt="Barcode" class="barcode-image" />
                    @elseif(!empty($barcodeLabel))
                        {!! DNS1D::getBarcodeHTML($barcodeLabel, 'C128', 1, 30) !!}
                    @endif
                </td>
            </tr>
        </table>

        @php
            $patientName = $patient?->name ?? 'N/A';
            $patientAge = $patient?->age ?? 'N/A';
            $patientGender = $patient?->gender ?? 'N/A';
            $patientPhone = $patient?->phone ?? ($patient?->mobile ?? '');

            $doctorName = $doctor?->name ?? 'N/A';
            $bedName = $bed?->name ?? 'N/A';

            $admissionDate = $ipdpatient?->admission_date ?? null;
            $dischargeDate = $ipdpatient?->discharged_at ?? ($ipdpatient?->status === 'Inactive' ? $ipdpatient?->updated_at : null);
            $runningLinesCollection = collect($runningLines ?? []);
            $dueCollectionsList = collect($due_collections ?? []);
            if ($dueCollectionsList->isEmpty()) {
                $dueCollectionsList = collect($payments ?? [])->filter(function ($p) {
                    return isset($p->type) && $p->type === 'due_collection';
                })->values();
            }
        @endphp

        @if(!empty($billing_info))
            <table class="info-table">
                <tr>
                    <td class="label">Bill No</td><td class="colon">:</td><td class="value">{{ $billing_info['bill_no'] ?? ($bill_no ?? '') }}</td>
                    <td class="label">Date &amp; Time</td><td class="colon">:</td><td class="value">{{ $billing_info['bill_date_time'] ?? ($bill_date_time ?? '') }}</td>
                </tr>
                <tr>
                    <td class="label">Patient Name</td><td class="colon">:</td><td class="value">{{ $billing_info['patient_name'] ?? ($patient?->name ?? 'N/A') }}</td>
                    <td class="label">Age</td><td class="colon">:</td><td class="value">{{ $billing_info['age_display'] ?? ($age_display ?? ($patient?->age ? $patient->age . ' years' : 'N/A')) }}</td>
                </tr>
                <tr>
                    <td class="label">Gender</td><td class="colon">:</td><td class="value">{{ $billing_info['gender'] ?? ($patient?->gender ?? 'N/A') }}</td>
                    <td class="label">Phone</td><td class="colon">:</td><td class="value">{{ $billing_info['phone'] ?? ($patient?->phone ?? ($patient?->mobile ?? '')) }}</td>
                </tr>
                <tr>
                    <td class="label">Refd. By</td><td class="colon">:</td><td class="value">{{ $billing_info['refd_by'] ?? ($doctor?->name ?? '') }}</td>
                    <td class="label"></td><td class="colon"></td><td class="value"></td>
                </tr>
            </table>
        @endif

        <table class="info-table">
            <tr>
                <td class="label">IPD ID</td><td class="colon">:</td><td class="value">{{ $ipd_id ?? ($ipdId ?? 'N/A') }}</td>
                <td class="label">Date &amp; Time</td><td class="colon">:</td><td class="value">{{ !empty($admissionDate) ? \Carbon\Carbon::parse($admissionDate)->format('d-m-Y h:i:s A') : ($bill_date_time ?? ($invoiceDateTime ? \Carbon\Carbon::parse($invoiceDateTime)->format('d-m-Y h:i:s A') : '')) }}</td>
            </tr>
            <tr>
                <td class="label">Patient Name</td><td class="colon">:</td><td class="value">{{ $patientName ?? ($patient?->name ?? 'N/A') }}</td>
                <td class="label">Age</td><td class="colon">:</td><td class="value">{{ $age_display ?? ($patientAge ?? ($patient?->age ? $patient->age . ' years' : 'N/A')) }}</td>
            </tr>
            <tr>
                <td class="label">Gender</td><td class="colon">:</td><td class="value">{{ $patientGender ?? ($patient?->gender ?? 'N/A') }}</td>
                <td class="label">Phone</td><td class="colon">:</td><td class="value">{{ $patientPhone ?? ($patient?->phone ?? ($patient?->mobile ?? '')) }}</td>
            </tr>
            <tr>
                <td class="label">Case</td><td class="colon">:</td><td class="value">{{ $case ?? ($ipdpatient?->case ?? '') }}</td>
                <td class="label">Bed</td><td class="colon">:</td><td class="value">{{ $bedName ?? ($bed?->name ?? ($ipdpatient->bed?->name ?? 'N/A')) }}</td>
            </tr>
            <tr>
                <td class="label">Admission</td><td class="colon">:</td><td class="value">{{ $admission ?? ($ipdpatient?->admission_date ? \Carbon\Carbon::parse($ipdpatient->admission_date)->format('d-m-Y h:i:s A') : 'N/A') }}</td>
                <td class="label">Discharge</td><td class="colon">:</td><td class="value">{{ $discharge ?? ($ipdpatient?->discharged_at ? \Carbon\Carbon::parse($ipdpatient->discharged_at)->format('d-m-Y h:i A') : 'N/A') }}</td>
            </tr>
            <tr>
                <td class="label">Refd. By</td><td class="colon">:</td><td class="value">{{ $consultant ?? ($doctorName ?? ($doctor?->name ?? 'N/A')) }}</td>
                <td class="label"></td><td class="colon"></td><td class="value"></td>
            </tr>
            {{-- duplicate billing-style patient block removed to keep IPD info single-source --}}
        </table>

        @if($runningLinesCollection->isNotEmpty())
            <div class="section-title">Item Details</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">SL</th>
                        <th style="width: 55%;">Item</th>
                        <th style="width: 10%;">Qty</th>
                        <th style="width: 15%;">Rate</th>
                        <th style="width: 15%; text-align: right;">Amount (Tk)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($runningLinesCollection as $index => $line)
                        @php
                            $lineItemName = is_array($line) ? ($line['item_name'] ?? ($line['description'] ?? 'Item')) : ($line->item_name ?? ($line->description ?? 'Item'));
                            $lineQuantity = is_array($line) ? ($line['quantity'] ?? 1) : ($line->quantity ?? 1);
                            $lineUnitPrice = is_array($line) ? ($line['unit_price'] ?? 0) : ($line->unit_price ?? 0);
                            $lineAmount = is_array($line) ? ($line['net_amount'] ?? ($line['total_amount'] ?? 0)) : ($line->net_amount ?? ($line->total_amount ?? 0));
                        @endphp
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $lineItemName }}</td>
                            <td>{{ number_format((float) $lineQuantity, 2) }}</td>
                            <td>{{ number_format((float) $lineUnitPrice, 2) }}</td>
                            <td class="amount">{{ number_format((float) $lineAmount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @php
            $dueCollectionsList = collect($due_collections ?? []);
            if ($dueCollectionsList->isEmpty()) {
                $dueCollectionsList = collect($payments ?? [])->filter(function ($p) {
                    return isset($p->type) && $p->type === 'due_collection';
                })->values();
            }

            $dueCollectionsTotal = (float) $dueCollectionsList->sum('amount');
            $displayDueAmount = (float) ($due_amount ?? 0);

            $portalPatientId = (int) ($patient->id ?? 0);
            $portalPhone = (string) ($patient->phone ?? '');
            $portalToken = '';
            if ($portalPatientId > 0) {
                $portalTokenPayload = [
                    'patient_id' => $portalPatientId,
                    'phone' => $portalPhone,
                    'exp' => now()->addDays(30)->timestamp,
                ];
                $portalToken = encrypt(json_encode($portalTokenPayload));
            }
            $portalLoginUrl = $portalToken !== ''
                ? route('backend.patient.portal.login', ['token' => $portalToken])
                : '';
            $portalQrCode = $portalLoginUrl !== ''
                ? 'data:image/png;base64,' . (new \Milon\Barcode\DNS2D())->getBarcodePNG($portalLoginUrl, 'QRCODE', 5, 5)
                : '';
        @endphp

        <table class="bottom-section">
            <tr>
                <td class="left-bottom">
                    <div class="due-section">
                        @if(isset($remarks) && trim((string) $remarks) !== '')
                            <div><strong>Remarks:</strong> {{ $remarks }}</div>
                        @endif
                        @if(round((float) $displayDueAmount, 2) > 0)
                            <div class="due-badge">DUE</div>
                        @else
                            <div class="paid-badge">PAID</div>
                        @endif
                        <div>Thank You</div>
                    </div>
                    <div class="prepared-portal-row">
                        <div class="prepared-by">
                            <strong>Prepared By:</strong> {{ $prepared_by ?? '' }}
                        </div>

                        @if(!empty($portalQrCode))
                        <div class="portal-qr">
                            <img src="{{ $portalQrCode }}" alt="Patient Portal QR" />
                        </div>
                        @endif
                    </div>
                </td>
                <td class="right-bottom">
                    <table class="totals-table">
                        <tr>
                            <td class="label-col"><strong>Total Amount Tk.</strong></td>
                            <td class="amount-col"><strong>{{ number_format((float) ($base_total ?? $total_amount ?? 0), 2) }}</strong></td>
                        </tr>
                        @php
                            $baseTotal = round((float) ($base_total ?? $total_amount ?? 0), 2);
                            $vatPercentLocal = round((float) ($vat_percentage ?? 0), 2);
                            $vatComputed = $vatPercentLocal > 0 ? round(($baseTotal * $vatPercentLocal) / 100, 2) : 0.00;
                            $extraDiscount = round((float) ($extra_flat_discount ?? 0), 2);
                            if (strtolower((string) ($discount_type ?? 'flat')) === 'percentage') {
                                $discountPercentLocal = round((float) ($discount ?? 0), 2);
                                $discountComputed = round(($baseTotal * $discountPercentLocal) / 100, 2);
                            } else {
                                $discountPercentLocal = null;
                                $discountComputed = round((float) ($discount ?? 0), 2);
                            }

                            $totalWithVat = round($baseTotal + $vatComputed, 2);
                            $netComputed = round(max(0, $totalWithVat - $discountComputed - $extraDiscount), 2);
                        @endphp

                        @if ($vatComputed != 0)
                        <tr>
                            <td class="label-col">Vat Tk.</td>
                            <td class="amount-col">{{ number_format($vatComputed, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="label-col"><strong>Total With VAT Tk.</strong></td>
                            <td class="amount-col"><strong>{{ number_format($totalWithVat, 2) }}</strong></td>
                        </tr>
                        @endif

                        @if ($discountComputed != 0)
                            @if ($discountPercentLocal !== null)
                            <tr>
                                <td class="label-col">
                                    Discount ({{ number_format($discountPercentLocal, 2) }}%)
                                </td>
                                <td class="amount-col">
                                    {{ number_format($discountComputed, 2) }}</td>
                            </tr>
                            @else
                            <tr>
                                <td class="label-col">Discount Tk.</td>
                                <td class="amount-col">{{ number_format($discountComputed, 2) }}</td>
                            </tr>
                            @endif
                        @endif

                        @if ($extraDiscount != 0)
                        <tr>
                            <td class="label-col">Extra Discount Tk.</td>
                            <td class="amount-col">{{ number_format($extraDiscount, 2) }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="label-col"><strong>Net Payable Tk.</strong></td>
                            <td class="amount-col"><strong>{{ number_format($netComputed, 2) }}</strong></td>
                        </tr>
                        @php
                            $invoiceTimePaidAmount = round((float) ($invoice_time_paid_amount ?? 0), 2);
                            $totalPaidAmount = round((float) ($total_paid ?? 0), 2);
                            $returnAmountValue = round((float) ($return_amount ?? 0), 2);
                            $dueAmountValue = round((float) ($adjusted_due ?? $due_amount ?? 0), 2);
                            $netPayableIPD = round((float) ($net_payable ?? 0), 2);
                            // Return Amount should only show if there's actual overpayment
                            $showReturnIPD = $returnAmountValue > 0 && $totalPaidAmount > $netPayableIPD;
                        @endphp
                        @if ($invoiceTimePaidAmount != 0)
                        <tr>
                            <td class="label-col">Paid (Invoice Time)</td>
                            <td class="amount-col">{{ number_format($invoiceTimePaidAmount, 2) }}</td>
                        </tr>
                        @endif
                        @if ($totalPaidAmount != 0)
                        <tr>
                            <td class="label-col"><strong>Total Paid Tk.</strong></td>
                            <td class="amount-col"><strong>{{ number_format($totalPaidAmount, 2) }}</strong></td>
                        </tr>
                        @endif
                        @if ($showReturnIPD)
                        <tr>
                            <td class="label-col"><strong>Return Amount Tk.</strong></td>
                            <td class="amount-col"><strong>{{ number_format($returnAmountValue, 2) }}</strong></td>
                        </tr>
                        @endif
                        @php
                            $unadjustedDueIPD = max(0, $netPayableIPD - $totalPaidAmount);
                        @endphp
                        <tr>
                            <td class="label-col"><strong>Due Tk.</strong></td>
                            <td class="amount-col"><strong>{{ number_format($unadjustedDueIPD, 2) }}</strong></td>
                        </tr>
                        @if(isset($adjusted_due) && round((float) $adjusted_due,2) !== round($unadjustedDueIPD,2))
                        <tr>
                            <td class="label-col"><strong>Due After Returns Tk.</strong></td>
                            <td class="amount-col"><strong>{{ number_format((float) $adjusted_due, 2) }}</strong></td>
                        </tr>
                        @endif
                    </table>
                    @if($dueCollectionsList->isNotEmpty())
                    <div class="section-title">Due Collection History</div>
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th style="width: 60%;">Date</th>
                                <th style="width: 40%; text-align: right;">Amount (Tk)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($dueCollectionsList as $dc)
                            <tr>
                                <td>{{ $dc->created_at ? \Carbon\Carbon::parse($dc->created_at)->format('d-m-Y h:i A') : 'Due Collect' }}</td>
                                <td>{{ number_format((float) ($dc->amount ?? 0), 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                </td>
            </tr>
            <tr>
                <td colspan="2" class="amount-words-inline">{{ $amount_in_words ?? '' }}</td>
            </tr>
        </table>
    </div>

    @php
        $portalPatientId = (int) ($patient->id ?? 0);
        $portalPhone = (string) ($patient->phone ?? '');
        $portalToken = '';
        if ($portalPatientId > 0) {
            $portalTokenPayload = [
                'patient_id' => $portalPatientId,
                'phone' => $portalPhone,
                'exp' => now()->addDays(30)->timestamp,
            ];
            $portalToken = encrypt(json_encode($portalTokenPayload));
        }
        $portalLoginUrl = $portalToken !== ''
            ? route('backend.patient.portal.login', ['token' => $portalToken])
            : '';
        $portalQrCode = $portalLoginUrl !== ''
            ? 'data:image/png;base64,' . (new \Milon\Barcode\DNS2D())->getBarcodePNG($portalLoginUrl, 'QRCODE', 5, 5)
            : '';
    @endphp

    <div class="footer-placeholder"></div>

    @if(!empty($auto_print))
    <script>
        (function () {
            var triggered = false;
            var closeScheduled = false;
            var scheduleClose = function () {
                if (closeScheduled) return;
                closeScheduled = true;
                try { localStorage.setItem('ipd:close_invoice_tabs', String(Date.now())); } catch (e) {}
                setTimeout(function () {
                    try { window.open('', '_self'); } catch (e) {}
                    try { window.close(); } catch (e) {}
                }, 150);
            };

            try { window.addEventListener('afterprint', scheduleClose, { once: true }); } catch (e) {}

            var doPrint = function () {
                if (triggered) return;
                triggered = true;
                try { window.focus(); } catch (e) {}
                try { window.print(); } catch (e) { scheduleClose(); }
            };

            if (document.readyState === 'complete') {
                setTimeout(doPrint, 120);
            } else {
                window.addEventListener('load', function () { setTimeout(doPrint, 120); }, { once: true });
            }
        })();
    </script>
    @endif
    @include('prints.partials._footer')
</body>

</html>
