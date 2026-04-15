<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>OPD Invoice</title>
    <style>
        @php
            $__inv_header_h = (int) ($header_height ?? 115);
            $__inv_footer_h = (int) ($footer_height ?? 70);
        @endphp
        @page {
            margin: 0mm 0mm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 14px; /* A4 size - 14px */
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }

        /* A5 Paper Size - Smaller font */
        @media print and (max-width: 148mm), screen and (max-width: 148mm) {
            body {
                font-size: 11px; /* A5 size - 11px */
            }
        }

        /* Alternative media query for A5 detection */
        @page a5 {
            size: A5;
        }
        
        @media print and (width: 148mm) {
            body {
                font-size: 11px;
            }
        }

        .header {
            margin: 0;
            padding: 0;
        }

        .header-image {
            width: 100%;
            max-height: {{ $__inv_header_h }}px;
            margin: 0;
            padding: 0;
            display: block;
            object-fit: cover;
            height: {{ $__inv_header_h }}px;
        }

        .header-placeholder {
            height: {{ $__inv_header_h }}px;
            width: 100%;
        }

        /* Patient Info Two Column Layout */
        .patient-info {
            margin-bottom: 10px;
            padding: 4px 10px 10px;
            width: 100%;
        }

        .patient-info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .patient-info-table td {
            vertical-align: top;
            padding: 2px 0;
        }

        .patient-info-table .label {
            font-weight: bold;
            width: 16%;
            white-space: nowrap;
        }

        .patient-info-table .colon {
            width: 2%;
            text-align: center;
        }

        .patient-info-table .value {
            width: 32%;
        }

        /* Payment Table */
        .payment-section {
            padding: 10px;
        }

        .payment-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        .payment-table th {
            text-align: left;
            padding: 5px;
            border-bottom: 1px solid #000;
            font-weight: bold;
            background-color: #f0f0f0;
        }

        .payment-table td {
            padding: 5px;
            border-bottom: 1px solid #ddd;
        }

        .amount-col {
            text-align: right;
        }

        /* Summary Section */
        .summary-section {
            margin-top: 10px;
            padding: 10px;
            float: right;
            width: 50%;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .summary-table td {
            padding: 3px 5px;
            border-bottom: 1px solid #ddd;
        }

        .summary-table td:first-child {
            width: 72%;
            white-space: nowrap;
        }

        .summary-table .amount-col {
            width: 28%;
            white-space: nowrap;
        }

        .summary-table .due-collect-label {
            white-space: nowrap;
        }

        .summary-table .total-row td {
            font-weight: bold;
            border-top: 2px solid #000;
            border-bottom: 1px solid #000;
        }

        .summary-table .balance-row td {
            font-weight: bold;
            border-top: 1px solid #000;
        }

        @media print {
            body {
                padding-top: {{ $__inv_header_h + 10 }}px; /* reserve space for fixed header */
                padding-bottom: {{ $__inv_footer_h * 2 }}px; /* reserve space for fixed footer */
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
            }

            .footer-content {
                position: fixed;
                bottom: {{ (int) floor($__inv_footer_h / 2) }}px; /* sit centered with footer image */
                left: 0;
                right: 0;
                width: 100%;
                text-align: center;
                z-index: 60; /* ensure content is above footer image */
                border-top: none !important; /* remove border */
                padding-top: 0;
                background: transparent;
            }
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

        .footer p {
            text-align: left;
            font-size: inherit; /* Inherits from body font-size */
            padding: 0 10px;
            margin: 0 0 5px 20px;
        }

        .footer-image {
            width: 100%;
            max-height: {{ $__inv_footer_h + 10 }}px;
            margin: 0;
            padding: 0;
            display: block;
            object-fit: contain;
            height: auto;
        }

        .footer-placeholder {
            height: {{ $__inv_footer_h }}px;
            width: 100%;
        }

        .footer-content {
            text-align: center;
            padding: 0 10px;
            margin: 0 0 5px;
            font-size: inherit;
        }

        .footer-meta {
            width: 100%;
            padding: 0 10px 5px;
            font-size: 11px;
        }

        /* Paper size classes for manual control */
        .paper-a4 {
            font-size: 14px !important;
        }

        .paper-a5 {
            font-size: 11px !important;
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
        }

        @media print and (max-width: 148mm), screen and (max-width: 148mm) {
            .header-image,
            .footer-image {
                max-height: {{ min($__inv_header_h, 58) }}px;
            }

            .header-placeholder,
            .footer-placeholder {
                height: {{ min($__inv_footer_h, 58) }}px;
            }
        }
    </style>
</head>

<body>
    <!-- Header Section -->
    <div class="header">
        @if($header_image)
        <img src="{{ $header_image }}" class="header-image" alt="Clinic Header">
        @else
        <div class="header-placeholder"></div>
        @endif
    </div>

    <!-- Patient Information Section - Two Column Layout -->
    <div class="patient-info">
        <table class="patient-info-table">
            <tr>
                <td class="label">OPD ID</td>
                <td class="colon">:</td>
                <td class="value">{{ $opd_id }}</td>
                <td class="label">OPD Checkin ID</td>
                <td class="colon">:</td>
                <td class="value">{{ $opd_checkin_id }}</td>
            </tr>
            <tr>
                <td class="label">Patient Name</td>
                <td class="colon">:</td>
                <td class="value">{{ $patient_name ?? '' }}</td>
                <td class="label">Date</td>
                <td class="colon">:</td>
                <td class="value">{{ $appointment_date ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">Blood Group</td>
                <td class="colon">:</td>
                <td class="value">{{ $blood_group ?? '-' }}</td>
                <td class="label">Age</td>
                <td class="colon">:</td>
                <td class="value">{{ $age ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">Address</td>
                <td class="colon">:</td>
                <td class="value">{{ $address ?? '-' }}</td>
                <td class="label">Gender</td>
                <td class="colon">:</td>
                <td class="value">{{ $gender ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">Consultant Doctor</td>
                <td class="colon">:</td>
                <td class="value">{{ $consultant_doctor ?? '' }}</td>
                <td class="label">Known Allergies</td>
                <td class="colon">:</td>
                <td class="value">{{ $known_allergies ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Consultant Qualification</td>
                <td class="colon">:</td>
                <td class="value" colspan="4">{{ $consultant_qualification ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <!-- Payment Details Section -->
    <div class="payment-section">
        <h3>Payment Details</h3>
        <table class="payment-table">
            <thead>
                <tr>
                    <th style="width: 8%;">#</th>
                    <th style="width: 52%;">Description</th>
                    <th style="width: 20%;">Tax (%)</th>
                    <th style="width: 20%; text-align: right !important;" class="amount-col">Amount (Tk)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>{{ $description }}</td>
                    <td>{{ number_format($tax_percent, 2) . '(%)' }}</td>
                    <td class="amount-col">{{ number_format($amount, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <!-- Summary Section -->
        <div class="summary-section">
            <table class="summary-table">
                <tr>
                    <td>Net Amount</td>
                    <td class="amount-col">Tk {{ number_format($amount, 2) }}</td>
                </tr>
                <tr>
                    <td>Tax({{ number_format($tax_percent, 2) }}%)</td>
                    <td class="amount-col">Tk {{ number_format($tax_amount, 2) }}</td>
                </tr>
                <tr>
                    <td>Discount({{ number_format($discount, 2) }}%)</td>
                    <td class="amount-col">Tk {{ number_format($discount_amount, 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td>Total</td>
                    <td class="amount-col">Tk {{ number_format($total_amount, 2) }}</td>
                </tr>
                <tr>
                    <td>Paid (Invoice Time)</td>
                    <td class="amount-col">Tk {{ number_format($invoice_time_paid_amount ?? $paid_amount, 2) }}</td>
                </tr>
                @foreach(($opd_due_collections ?? []) as $dueCollection)
                <tr>
                    <td class="due-collect-label">
                        {{ \Carbon\Carbon::parse($dueCollection->collected_at)->format('d-M-Y h:i A') }} - Due Collect
                    </td>
                    <td class="amount-col">Tk {{ number_format((float) $dueCollection->collected_amount, 2) }}</td>
                </tr>
                @endforeach
                <tr>
                    <td><strong>Total Paid Amount</strong></td>
                    <td class="amount-col"><strong>Tk {{ number_format($paid_amount, 2) }}</strong></td>
                </tr>
                <tr class="balance-row">
                    <td>Due Amount</td>
                    <td class="amount-col">Tk {{ number_format($balance_amount, 2) }}</td>
                </tr>
            </table>
        </div>

        <div class="clearfix"></div>
    </div>

    @php
        $portalToken = '';
        if (!empty($patient_id)) {
            $portalTokenPayload = [
                'patient_id' => (int) $patient_id,
                'phone' => (string) ($patient_phone ?? ''),
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

    @if($portalQrCode !== '')
    <div style="margin: 8px 0 10px; text-align:center;">
        <img src="{{ $portalQrCode }}" alt="Patient Portal QR" style="width:92px; height:92px; background:#fff;" />
    </div>
    @endif

    <!-- Footer Section -->
    <div class="footer">
        @php
            $footerFallbackLine = trim((string) config('app.invoice_footer_fallback_line', 'Powered By: www.toamedit.com Support: 01919-592638'));
            $footerPrintedAt = trim((string) ($printed_at ?? ''));
        @endphp
        <div class="footer-meta">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="text-align: left; padding-right: 12px;">
                        {{ $footerFallbackLine }}
                    </td>
                    <td style="text-align: right; white-space: nowrap; padding-right: 60px;">
                        @if($footerPrintedAt !== '')
                        Printing Date: {{ $footerPrintedAt }}
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        @if(!empty($footer_image))
                @if(!empty($footer_content))
                    <div class="footer-content" style="position:fixed; bottom:{{ (int) floor($__inv_footer_h / 2) + 18 }}px; left:0; right:0; width:100%; text-align:center; z-index:60; border-top:none !important; padding-top:0; background:transparent;">{!! $footer_content !!}</div>
            @endif
            <img src="{{ $footer_image }}" class="footer-image" alt="Clinic Footer">
        @else
            <div class="footer-placeholder"></div>
            @if(!empty($footer_content))
                <div class="footer-content">{!! $footer_content !!}</div>
            @endif
        @endif
    </div>
</body>

</html>