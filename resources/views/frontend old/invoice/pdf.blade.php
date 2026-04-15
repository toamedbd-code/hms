<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Medical Invoice</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 13px;
            line-height: 1.35;
            color: #111;
        }

        @page {
            size: A4;
            margin: 12mm 12mm 24mm 12mm;
        }

        .invoice-container {
            width: 100%;
        }

        /* Header Section */
        .header-section {
            width: 100%;
            text-align: center;
            margin-bottom: 5px;
            min-height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .header-placeholder {
            width: 100%;
            height: 100px;
            visibility: hidden;
        }

        .header-image {
            width: 100%;
            height: auto;
            max-height: 130px;
            object-fit: contain;
        }

        /* Content Section */
        .content-section {
            padding: 0;
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
            height: 28px;
            width: 150px;
            object-fit: contain;
        }

        .receipt-title {
            font-size: 18px;
            font-weight: bold;
            font-family: inherit;
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

        .items-table tbody tr {
            border-bottom: 1px solid #e6e6e6;
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
            font-size: 13px;
        }

        .paid-badge {
            background-color: #28a745;
            color: white;
            padding: 4px 8px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 4px;
            font-size: 13px;
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
            font-size: 13px;
        }

        .prepared-by {
            margin-top: 8px;
        }

        /* Footer Section */
        .footer-section {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            text-align: center;
            padding-bottom: 0px;
            min-height: 70px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
        }

        .footer-placeholder {
            width: 100%;
            height: 70px;
            visibility: hidden;
        }

        .footer-image {
            width: 100%;
            height: auto;
            max-height: 80px;
            object-fit: contain;
        }

        .footer-content {
            margin-top: 4px;
            font-size: 11px;
            text-align: left;
            padding: 0 20px;
            width: 100%;
        }

        .footer-date-time {
            font-size: 10.5px;
            text-align: left;
            margin-top: 4px;
            color: #000000ff;
            width: 100%;
            padding: 0 20px;
            margin-bottom: 5px;
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
                        @if(!empty($barcode))
                        <img src="{{ $barcode }}" alt="Barcode" class="barcode-image">
                        @endif
                    </td>
                    <td class="title-cell-center">
                        <div class="receipt-title">MONEY RECEIPT</div>
                    </td>
                    <td class="barcode-cell-right">
                        @if(!empty($barcode))
                        <img src="{{ $barcode }}" alt="Barcode" class="barcode-image">
                        @endif
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

// Due history
$dueCollections = $billing->dueCollections ?? collect();

$dueCollectedTotal = (float) $dueCollections->sum('collected_amount');

// Invoice time paid (original payment)
$legacyInvoicePaid = (float) ($billing->invoice_amount ?? 0);
$paidAmt = (float) ($billing->paid_amt ?? ($paid ?? 0));
$invoicePaid = $legacyInvoicePaid > 0
    ? $legacyInvoicePaid
    : max(0, $paidAmt - $dueCollectedTotal);

// Total paid
$totalPaid = $invoicePaid + $dueCollectedTotal;

// Final due
$finalDue = max($netPayable - $totalPaid, 0);
@endphp


@if ($delivery_date)
<div class="delivery-date">
    Delivery Date & Time:
    {{ Carbon::parse($delivery_date)->format('d-m-Y, h:i A') }}
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
                $footerPrintedAt = trim((string) ($printed_at ?? now()->timezone('Asia/Dhaka')->format('d F, Y h:i a')));
            @endphp

            @if(!empty($footer_image))
                @if(!empty($footer_content))
                    <div class="footer-content" style="position:relative; z-index:11;">{!! $footer_content !!}</div>
                @elseif($footerFallbackLine !== '')
                    <div class="footer-content" style="position:relative; z-index:11;">{{ $footerFallbackLine }}@if(!empty($footerPrintedAt)) , Printing Date: {{ $footerPrintedAt }}@endif</div>
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