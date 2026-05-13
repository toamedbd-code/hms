<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Billing Invoice</title>
    <style>
        @php
            // Determine whether to show header/footer; prefer controller-provided variable
            $__inv_show = isset($showHeaderFooter) ? (bool) $showHeaderFooter : (isset($show_header_footer) ? (bool) $show_header_footer : null);
            if ($__inv_show === null) {
                $__ws = function_exists('get_cached_web_setting') ? get_cached_web_setting() : null;
                $__attendance = is_array($__ws?->attendance_device_options) ? $__ws->attendance_device_options : (is_string($__ws?->attendance_device_options) && trim($__ws?->attendance_device_options) !== '' ? json_decode($__ws?->attendance_device_options, true) : []);
                $__reporting = is_array($__attendance) ? data_get($__attendance, 'reporting', []) : [];
                $__settingShowHeader = array_key_exists('show_header', $__reporting) ? (bool) $__reporting['show_header'] : null;
                $__settingShowFooter = array_key_exists('show_footer', $__reporting) ? (bool) $__reporting['show_footer'] : null;
                if ($__settingShowHeader !== null || $__settingShowFooter !== null) {
                    $__inv_show = ($__settingShowHeader ?? true) && ($__settingShowFooter ?? true);
                } else {
                    $__inv_show = array_key_exists('show_header_footer', $__reporting) ? (bool) $__reporting['show_header_footer'] : true;
                }
                $__layout = data_get($__reporting, 'layout', []);
            } else {
                $__layout = [];
            }

            // Prefer reporting layout heights if controller passed them, otherwise fall back to template vars
            $__inv_reportHeaderH = isset($reportHeaderHeight) ? (int) $reportHeaderHeight : null;
            $__inv_reportFooterH = isset($reportFooterHeight) ? (int) $reportFooterHeight : null;

            $__inv_header_h = $__inv_reportHeaderH ?? (int) ($header_height ?? ($__layout['header_height'] ?? 115));
            $__inv_footer_h = $__inv_reportFooterH ?? (int) ($footer_height ?? ($__layout['footer_height'] ?? 70));

            if (! $__inv_show) {
                $__inv_header_h = 0;
                $__inv_footer_h = 0;
            }
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

        /* IPD-style info table (match IPD invoice alignment) */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .info-table td {
            padding: 2px 0;
            vertical-align: top;
        }

        .info-table .label {
            font-weight: bold;
            width: 18%;
            white-space: nowrap;
        }

        .info-table .colon {
            width: 2%;
            text-align: center;
        }

        .info-table .value {
            width: 30%;
        }

        .info-table .refd-by-value {
            width: auto;
            white-space: normal;
            overflow-wrap: break-word;
            word-break: break-word;
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
            /* position relative so it sits above the footer image inside the footer area */
            position: relative;
            left: 0;
            right: 0;
            margin: 0 auto;
            font-size: 14px;
            text-align: center;
            padding: 6px 20px 0;
            width: 100%;
            z-index: 60;
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
        {{-- Use shared header partial for consistent header across prints --}}
        @includeIf('prints.partials._header', [
            'header_image' => $header_image ?? null,
            'headerHeight' => $__inv_header_h,
            'footerHeight' => $__inv_footer_h,
            'printed_at' => $invoiceDateTime ?? ($printed_at ?? null),
            'showHeaderFooter' => $__inv_show,
            'allowInvoiceDesignFallback' => false,
        ])

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

            <!-- Patient / IPD Details Section (aligned like IPD invoice) -->
            @if(!empty($ipd_id))
            <table class="info-table">
                <tr>
                    <td class="label">Bill No</td><td class="colon">:</td><td class="value">{{ $bill_number }}</td>
                    <td class="label">Date & Time</td><td class="colon">:</td><td class="value">{{ $invoiceDateTime ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">IPD ID</td><td class="colon">:</td><td class="value">{{ $ipd_id }}</td>
                    <td class="label">Case</td><td class="colon">:</td><td class="value">{{ $case ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Patient Name</td><td class="colon">:</td><td class="value">{{ $patient_name }}</td>
                    <td class="label">Age</td><td class="colon">:</td><td class="value">{{ $age }}</td>
                </tr>
                <tr>
                    <td class="label">Gender</td><td class="colon">:</td><td class="value">{{ $gender }}</td>
                    <td class="label">Phone</td><td class="colon">:</td><td class="value">{{ $contact_no }}</td>
                </tr>
                <tr>
                    <td class="label">Bed</td><td class="colon">:</td><td class="value">{{ $bed ?? '' }}</td>
                    <td class="label">Admission</td><td class="colon">:</td><td class="value">{{ $admission ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Bed Group</td><td class="colon">:</td><td class="value">{{ $bed_group ?? '' }}</td>
                    <td class="label">Discharge</td><td class="colon">:</td><td class="value">{{ $discharge ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Consultant</td><td class="colon">:</td><td class="value" colspan="4">{{ $consultant ?? $refd_by ?? '' }}</td>
                </tr>
            </table>
            @else
            <table class="info-table">
                <tr>
                    <td class="label">Bill No</td><td class="colon">:</td><td class="value">{{ $bill_number }}</td>
                    <td class="label">Date & Time</td><td class="colon">:</td><td class="value">{{ $invoiceDateTime }}</td>
                </tr>
                <tr>
                    <td class="label">Name</td><td class="colon">:</td><td class="value">{{ $patient_name }}</td>
                    <td class="label">Age</td><td class="colon">:</td><td class="value">{{ $age }}</td>
                </tr>
                <tr>
                    <td class="label">Contact No</td><td class="colon">:</td><td class="value">{{ $contact_no }}</td>
                    <td class="label">Gender</td><td class="colon">:</td><td class="value">{{ $gender }}</td>
                </tr>
                <tr>
                    <td class="label">Refd. By</td><td class="colon">:</td><td class="value refd-by-value" colspan="4">{{ $refd_by }}</td>
                </tr>
            </table>
            @endif

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
// Compute Medicine and Laboratory subtotals if bill items provide category
$billItemsCollection = collect($bill_items ?? []);
$medicineTotal = $billItemsCollection->filter(function($it){
    return strtolower(trim($it->category ?? '')) === 'medicine';
})->sum('total_amount');
$labTotal = $billItemsCollection->filter(function($it){
    $c = strtolower(trim($it->category ?? ''));
    return in_array($c, ['pathology','radiology']);
})->sum('total_amount');
@endphp


@if (!isset($ipd_id) && $delivery_date)
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

@if(($module ?? '') === 'ipd' && (float) ($medicineTotal ?? 0) > 0)
<tr>
<td class="label-col">Medicine Bill</td>
<td class="amount-col">{{ number_format($medicineTotal, 2) }}</td>
</tr>
@endif

@if((isset($ipd_id) && (float) ($labTotal ?? 0) > 0))
<tr>
<td class="label-col">Laboratory Bill</td>
<td class="amount-col">{{ number_format($labTotal, 2) }}</td>
</tr>
@endif

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

        @includeIf('prints.partials._footer', [
            'footer_image' => $footer_image ?? null,
            'footer_content' => $footer_content ?? null,
            'footer_content_position' => $footer_content_position ?? 'above',
            'footerHeight' => $__inv_footer_h,
            'showHeaderFooter' => $__inv_show,
            'allowInvoiceDesignFallback' => false,
        ])
    </div>
</body>
</html>