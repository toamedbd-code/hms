<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Medical Invoice</title>
    <style>
        @php
            $__inv_header_h = (int) ($header_height ?? 115);
            $__inv_footer_h = (int) ($footer_height ?? 70);
        @endphp
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 16px;
            line-height: 1.3;
        }

        .invoice-container {
            width: 100%;
        }

        /* Header Section */
        .header-section {
            width: 100%;
            text-align: center;
            margin-bottom: 5px;
            height: {{ $__inv_header_h }}px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .header-placeholder {
            width: 100%;
            height: {{ $__inv_header_h }}px;
            visibility: hidden;
        }

        .header-image {
            width: 100%;
            height: 100%;
            object-fit: fill;
        }

        /* Content Section */
        .content-section {
            padding: 0 15px;
        }

        /* Title section with barcode */
        .title-section-table {
            width: 100%;
            margin-bottom: 12px;
        }

        .barcode-cell-left {
            width: 20%;
            text-align: left;
            vertical-align: top;
        }

        .barcode-cell-right {
            width: 20%;
            text-align: right;
            vertical-align: top;
        }

        .title-cell-center {
            width: 60%;
            text-align: center;
        }

        .barcode-image {
            height: 25px;
            width: 120px;
        }

        .receipt-title {
            font-size: 20px;
            font-weight: bold;
            font-family: Verdana, Geneva, Tahoma, sans-serif;
            margin: 0;
            letter-spacing: 2px;
        }

        .patient-details-table {
            width: 100%;
            border: none;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .patient-details-table td {
            vertical-align: top;
            padding: 0;
            border: none;
        }

        .patient-left {
            width: 50%;
            padding-right: 15px;
            vertical-align: top;
        }

        .patient-right {
            width: 50%;
            padding-left: 15px;
            vertical-align: top;
        }

        /* FIXED: Consistent alignment for both columns */
        .detail-row {
            display: flex;
            align-items: flex-start;
            margin-bottom: 4px;
            min-height: 18px;
        }

        .detail-label {
            font-weight: bold;
            min-width: 85px;
            flex-shrink: 0;
            text-align: left;
        }

        .detail-value {
            flex: 1;
            text-align: left;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .detail-colon {
            margin-right: 4px;
            flex-shrink: 0;
        }

        /* NEW: Full line detail row for Refd. By */
        .full-line-detail-row {
            display: block;
            margin-bottom: 4px;
        }

        .full-line-label {
            font-weight: bold;
            display: inline;
        }

        .full-line-colon {
            margin-right: 4px;
        }

        .full-line-value {
            display: inline;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* Items table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            font-size: inherit;
        }

        .items-table th {
            padding: 5px 3px;
            text-align: left;
            font-weight: bold;
            border-bottom: 1px solid #ccc;
            background-color: #f2f2f2;
        }

        .items-table td {
            padding: 4px;
            vertical-align: top;
        }

        .items-table tr:last-child {
            border-bottom: 1px solid #ccc;
        }

        .items-table .sl-col {
            width: 8%;
            text-align: left;
        }

        .items-table .test-col {
            width: 45%;
        }

        .items-table .qty-col {
            width: 10%;
            text-align: center;
        }

        .items-table .price-col {
            width: 15%;
            text-align: right;
        }

        /* Delivery date */
        .delivery-date {
            margin: 8px 0;
            font-weight: bold;
        }

        /* Bottom section */
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

        /* Due section */
        .due-section {
            margin: 8px 0;
        }

        .due-badge {
            background-color: #ff4444;
            color: white;
            padding: 4px 8px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 4px;
            font-size: 16px;
        }

        .paid-badge {
            background-color: #28a745;
            color: white;
            padding: 4px 8px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 4px;
            font-size: 16px;
        }

        /* Totals table */
        .totals-table {
            width: 100%;
            font-size: inherit;
            margin-top: 0px;
        }

        .totals-table td {
            padding: 2px 4px;
            border-bottom: 1px solid #ddd;
        }

        .totals-table .label-col {
            text-align: left;
            width: 60%;
        }

        .totals-table .amount-col {
            text-align: right;
            width: 40%;
        }

        /* Amount in words */
        .amount-words {
            margin-top: 8px;
            font-weight: bold;
            text-align: right;
            font-size: 16px;
        }

        .prepared-by {
            margin-top: 8px;
        }

        /* Footer Section (static like report print) */
        .footer-section {
            position: fixed; /* keep footer image fixed at bottom */
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            text-align: center;
            padding-bottom: 0px;
            min-height: {{ $__inv_footer_h }}px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            z-index: 10;
        }

        .footer-placeholder {
            width: 100%;
            height: {{ $__inv_footer_h }}px;
            visibility: hidden;
        }

        .footer-image {
            width: 100%;
            height: auto;
            max-height: {{ $__inv_footer_h + 10 }}px;
            object-fit: contain;
        }

        .footer-content {
            position: fixed;
            bottom: {{ (int) floor($__inv_footer_h / 2) }}px; /* sit centered vertically with footer image */
            left: 0;
            right: 0;
            margin: 0 auto;
            font-size: 14px;
            text-align: center;
            padding: 0 20px;
            width: 100%;
            z-index: 60; /* above footer image */
            background: transparent;
        }

        .footer-date-time {
            font-size: 14px;
            text-align: left;
            margin-top: 4px;
            color: #000000ff;
            width: 100%;
            padding: 0 20px;
            margin-bottom: 5px;
        }

        /* Print specific styles */
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                height: auto;
            }

            .content-section {
                padding-bottom: {{ $__inv_footer_h * 2 }}px; /* reserve space for fixed footer */
            }

            .header-section,
            .header-placeholder,
            .header-image {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                width: 100%;
                z-index: 50;
            }

            .header-image { object-fit: cover; height: {{ $__inv_header_h }}px; }

            .header-placeholder,
            .footer-placeholder {
                display: none;
            }

            .footer-date-time {
                color: #000;
            }

            /* Ensure proper alignment in print */
            .detail-row {
                min-height: 16px;
            }
        }

        @page {
            size: A4;
            margin: 0;
        }

        /* A4 Print Settings */
        @media print {
            body {
                font-size: 16px !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                height: auto;
            }

            .receipt-title {
                font-size: 20px;
            }

            .header-section {
                height: {{ $__inv_header_h }}px;
            }

            .header-image {
                height: 100%;
            }

            .barcode-image {
                height: 25px;
                width: 120px;
            }

            .content-section {
                padding: 0 15px;
                padding-bottom: {{ $__inv_footer_h * 2 }}px;
            }

            .items-table,
            .totals-table {
                font-size: 16px !important;
            }

            .due-badge,
            .paid-badge {
                font-size: 16px;
            }

            .amount-words {
                font-size: 16px;
            }

            .footer-section {
                min-height: {{ $__inv_footer_h + 10 }}px;
            }

            .footer-image {
                max-height: {{ $__inv_footer_h + 10 }}px;
            }

            .footer-date-time {
                font-size: 14px !important;
            }
        }

        /* A5 Print Settings */
        @media print and (max-height: 8.3in) and (max-width: 5.8in) {
            body {
                font-size: 16px !important;
            }

            .receipt-title {
                font-size: 16px !important;
            }

            .header-section {
                height: 72px !important;
            }

            .header-image {
                height: 100% !important;
            }

            .barcode-image {
                height: 18px !important;
                width: 90px !important;
            }

            .content-section {
                padding: 0 8px !important;
                padding-bottom: 70px !important;
            }

            .items-table,
            .totals-table {
                font-size: 16px !important;
            }

            .due-badge,
            .paid-badge {
                font-size: 16px !important;
            }

            .amount-words {
                font-size: 16px !important;
            }

            .footer-section {
                min-height: {{ $__inv_footer_h }}px !important;
            }

            .footer-image {
                max-height: {{ $__inv_footer_h + 10 }}px !important;
            }

            .footer-content {
                font-size: 16px !important;
            }

            .footer-date-time {
                font-size: 16px !important;
            }

            /* Adjust for A5 */
            .detail-label {
                min-width: 70px;
            }
            
        }
    </style>
</head>

<body>
    <div class="invoice-container">
        <!-- Header Section -->
        <div class="header-section">
            @if($header_image)
            <img src="{{ $header_image }}" alt="Header" class="header-image">
            @else
            <div class="header-placeholder"></div>
            @endif
        </div>

        <div class="content-section">
            <table class="title-section-table">
                <tr>
                    <td class="barcode-cell-left">
                        {!! DNS1D::getBarcodeHTML(isset($bill) ? $bill->bill_no : $bill_number, 'C128', 1, 30) !!}
                    </td>
                    <td class="title-cell-center">
                        <div class="receipt-title">MONEY RECEIPT</div>
                    </td>
                    <td class="barcode-cell-right">
                        {!! DNS1D::getBarcodeHTML(isset($bill) ? $bill->bill_no : $bill_number, 'C128', 1, 30) !!}
                    </td>
                </tr>
            </table>

            <!-- Patient Details Section -->
            <table class="patient-details-table" style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
                <tr>
                    <td style="width: 15%; vertical-align: top; padding: 2px 0; font-weight: bold;">Bill No</td>
                    <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
                    <td style="width: 28%; vertical-align: top; padding: 2px 0;">{{ $bill_number }}</td>
                    <td style="width: 20%; vertical-align: top; padding: 2px 0; font-weight: bold;">Date & Time</td>
                    <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
                    <td style="width: 28%; vertical-align: top; padding: 2px 0;">{{ $invoiceDateTime }}</td>
                </tr>
                <tr>
                    <td style="width: 15%; vertical-align: top; padding: 2px 0; font-weight: bold;">Name</td>
                    <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
                    <td style="width: 28%; vertical-align: top; padding: 2px 0;">{{ $patient_name }}</td>
                    <td style="width: 20%; vertical-align: top; padding: 2px 0; font-weight: bold;">Age</td>
                    <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
                    <td style="width: 28%; vertical-align: top; padding: 2px 0;">{{ $age }}</td>
                </tr>
                <tr>
                    <td style="width: 15%; vertical-align: top; padding: 2px 0; font-weight: bold;">Contact No</td>
                    <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
                    <td style="width: 28%; vertical-align: top; padding: 2px 0;">{{ $contact_no }}</td>
                    <td style="width: 20%; vertical-align: top; padding: 2px 0; font-weight: bold;">Gender</td>
                    <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
                    <td style="width: 28%; vertical-align: top; padding: 2px 0;">{{ $gender }}</td>
                </tr>
                <tr>
                    <td style="width: 15%; vertical-align: top; padding: 2px 0; font-weight: bold;">Refd. By</td>
                    <td style="width: 2%; vertical-align: top; padding: 2px 0;">:</td>
                    <td colspan="4" style="width: 78%; vertical-align: top; padding: 2px 0;">{{ $refd_by }}</td>
                </tr>
            </table>

            <!-- NEW: Full line for Refd. By -->
            <!-- <div class="full-line-detail-row">
                <span class="full-line-label">Refd. By</span>
                <span class="full-line-colon">:</span>
                <span class="full-line-value">{{ $refd_by }}</span>
            </div> -->

            <table class="items-table">
                <thead>
                    <tr>
                        <th class="sl-col">SL</th>
                        <th class="test-col">Item Name</th>
                        <th class="qty-col">Qty</th>
                        <th class="price-col">Price (Tk.)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bill_items as $index => $item)
                    <tr>
                        <td class="sl-col">{{ $index + 1 }}</td>
                        <td class="test-col">{{ $item->item_name ?? $item->description }}</td>
                        <td class="qty-col">{{ (int) $item->quantity }}</td>
                        <td class="price-col">{{ number_format($item->total_amount, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

@php
use Carbon\Carbon;

$netPayable = (float) $net_payable;

// Invoice time paid (original payment)
$invoicePaid = isset($paid_at_invoice) ? (float) $paid_at_invoice : (float) ($billing->invoice_amount ?? 0);

// Due history
$dueCollections = $billing->dueCollections ?? collect();
$dueCollectedTotal = $dueCollections->sum('collected_amount');

// Payments (including any receipts recorded later)
$payments = $billing->payments ?? collect();
$paymentsTotal = $payments->sum('amount');

// Prefer controller-provided aggregate if available, otherwise compute
$totalPaid = isset($paid) ? (float) $paid : (float) ($paymentsTotal + $dueCollectedTotal);

// Final due
$finalDue = isset($due) ? max((float) $due, 0) : max($netPayable - $totalPaid, 0);

$portalPatientId = (int) ($billing->patient_id ?? 0);
$portalPhone = (string) ($billing->patient_mobile ?? ($billing->patient->phone ?? ''));
$portalToken = '';
if ($portalPatientId > 0) {
    try {
        if (!empty(config('app.key'))) {
            $portalTokenPayload = [
                'patient_id' => $portalPatientId,
                'phone' => $portalPhone,
                'billing_id' => (int) ($billing->id ?? 0),
                'exp' => now()->addDays(30)->timestamp,
            ];
            $portalToken = encrypt(json_encode($portalTokenPayload));
        }
    } catch (\Throwable $e) {
        // If encryption is unavailable, skip portal QR generation to keep invoice rendering stable.
        $portalToken = '';
    }
}
$portalLoginUrl = $portalToken !== ''
    ? route('backend.patient.portal.login', ['token' => $portalToken])
    : '';
$portalQrCode = $portalLoginUrl !== ''
    ? 'data:image/png;base64,' . (new \Milon\Barcode\DNS2D())->getBarcodePNG($portalLoginUrl, 'QRCODE', 5, 5)
    : '';
@endphp


@if ($delivery_date)
<div class="delivery-date">
    Delivery Date & Time:
    {{ Carbon::parse($delivery_date)->format('d-M-Y, h:i A') }}
</div>
@endif


<table class="bottom-section">
<tr>
<td class="left-bottom">

<div class="due-section">

@if(!empty($remarks))
<div>
<strong>Remarks:</strong> {{ $remarks }}
</div>
@endif

@if($finalDue > 0)
<div class="due-badge">DUE</div>
@else
<div class="paid-badge">PAID</div>
@endif

<div>Thank You</div>

</div>

<div class="prepared-by">
<strong>Prepared By:</strong> {{ $prepared_by }}
</div>

@if($portalQrCode !== '')
<div style="margin-top:8px; text-align:left;">
    <img src="{{ $portalQrCode }}" alt="Patient Portal QR" style="width:92px; height:92px; background:#fff;" />
</div>
@endif

</td>


<td class="right-bottom">

<table class="totals-table">

<tr>
<td class="label-col"><strong>Total Amount Tk.</strong></td>
<td class="amount-col">
<strong>{{ number_format($total_amount, 2) }}</strong>
</td>
</tr>

<tr>
<td class="label-col">Vat Tk.</td>
<td class="amount-col">{{ number_format($vat, 2) }}</td>
</tr>

@if ($discount_type == 'percentage')
<tr>
<td class="label-col">
Discount ({{ number_format($discount, 2) }}%)
</td>
<td class="amount-col">
{{ number_format(($total_amount * $discount / 100), 2) }}
</td>
</tr>
@else
<tr>
<td class="label-col">Discount Tk.</td>
<td class="amount-col">{{ number_format($discount, 2) }}</td>
</tr>
@endif


@if ($extra_flat_discount != 0)
<tr>
<td class="label-col">Extra Discount Tk.</td>
<td class="amount-col">
{{ number_format($extra_flat_discount, 2) }}
</td>
</tr>
@endif


<tr>
<td class="label-col"><strong>Net Payable Tk.</strong></td>
<td class="amount-col">
<strong>{{ number_format($netPayable,2) }}</strong>
</td>
</tr>


<tr>
<td class="label-col">Paid (Invoice Time)</td>
<td class="amount-col">
{{ number_format($invoicePaid,2) }}
</td>
</tr>


{{-- Due Collect History --}}
{{-- Payment history intentionally omitted from printed invoice --}}

{{-- Due Collect History --}}
@foreach($dueCollections as $dc)
<tr>
<td class="label-col" style="white-space: nowrap;">
{{ \Carbon\Carbon::parse($dc->collected_at)->format('d-M-Y h:i A') }} - Due Collect
</td>

<td class="amount-col" style="text-align:right;">
{{ number_format($dc->collected_amount, 2) }}
</td>
</tr>
@endforeach


<tr>
<td class="label-col"><strong>Total Paid Tk.</strong></td>
<td class="amount-col">
<strong>{{ number_format($totalPaid,2) }}</strong>
</td>
</tr>

@if (!empty($return_amount) && (float) $return_amount > 0)
<tr>
<td class="label-col"><strong>Return Amount Tk.</strong></td>
<td class="amount-col">
<strong>{{ number_format((float) $return_amount,2) }}</strong>
</td>
</tr>
@endif

<tr>
<td class="label-col"><strong>Due Tk.</strong></td>
<td class="amount-col">
<strong>{{ number_format($finalDue,2) }}</strong>
</td>
</tr>

</table>

</td>
</tr>
</table>

        </div>
<div style="
    width:100%;
    text-align:center;
    white-space:nowrap;
    display:block;
    margin-top:5px;
    font-weight:bold;
">
  {{ $amount_in_words }}
</div>

        <!-- Footer Section -->
        <div class="footer-section">
            @php
                $footerFallbackLine = trim((string) config('app.invoice_footer_fallback_line', 'Powered By: www.toamedit.com Support: 01919-592638'));
                $footerPrintedAt = trim((string) ($printed_at ?? ''));
            @endphp

            @if(!empty($footer_image))
                @if(!empty($footer_content))
                    <div class="footer-content" style="position:relative; z-index:11;">{!! $footer_content !!}</div>
                @endif

                @if($footerFallbackLine !== '' || $footerPrintedAt !== '')
                <div class="footer-date-time">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="text-align: left; padding-right: 12px;">
                                    {{ $footerFallbackLine }}
                            </td>
                            <td style="text-align: right; white-space: nowrap; padding-right: 40px;">
                                @if($footerPrintedAt !== '')
                                    Printing Date: {{ $footerPrintedAt }}
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
                @endif

                <img src="{{ $footer_image }}" alt="Footer" class="footer-image">
            @else
                <div class="footer-placeholder"></div>
                @if(!empty($footer_content))
                    <div class="footer-content">{!! $footer_content !!}</div>
                @elseif($footerFallbackLine !== '')
                    <div class="footer-content">{{ $footerFallbackLine }}@if(!empty($footerPrintedAt)) , Printing Date: {{ $footerPrintedAt }}@endif</div>
                @endif
            @endif
        </div>
    </div>
</body>
</html>