<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Appointment Invoice</title>
    <style>
        @php
            $__inv_header_h = (int) ($reportHeaderHeight ?? $header_height ?? 115);
            $__inv_footer_h = (int) ($reportFooterHeight ?? $footer_height ?? 70);
        @endphp
        @page {
            margin: 0;
            size: auto;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }

        /* A5 specific styles */
        @media print {
            body.A5 {
                font-size: 13px !important;
            }

            .A5 .info-label {
                min-width: 100px !important;
            }

            .A5 .title {
                font-size: 16px !important;
            }

            .A5 .payment-table th,
            .A5 .payment-table td {
                padding: 3px !important;
            }

            body.A5 .panel-title { font-size: 13px !important; }
            body.A5 .detail-label,
            body.A5 .detail-value { font-size: 12px !important; }
            body.A5 .payment-table th,
            body.A5 .payment-table td { font-size: 12px !important; }
            body.A5 .payment-summary td { font-size: 12px !important; }

            .A5 .summary-section {
                width: 45% !important;
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
            object-fit: contain;
            height: {{ $__inv_header_h }}px;
        }

        .header-placeholder {
            width: 100%;
            height: {{ $__inv_header_h }}px;
            visibility: hidden;
        }

        .print-shared-header-fallback {
            border: 0 !important;
        }
        .print-shared-header::before,
        .print-shared-header::after,
        .print-shared-header-fallback::before,
        .print-shared-header-fallback::after {
            content: none !important;
            display: none !important;
        }

        .content { margin-bottom: {{ $__inv_footer_h * 2 }}px; padding: 8px 5mm; }

        /* Patient Info Two Column Layout */
        .patient-info {
            margin-bottom: 10px;
            width: 100%;
        }

        .patient-info table {
            width: 100%;
            border-collapse: collapse;
        }

        .patient-info td {
            vertical-align: top;
            padding: 2px 10px 2px 0;
            width: 50%;
        }

        .info-label {
            font-weight: bold;
            display: inline-block;
            min-width: 120px;
        }

        .info-value {
            display: inline-block;
        }

        /* Payment Table */
        .payment-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        .payment-table th {
            text-align: left;
            padding: 5px;
            border: 1px solid #000;
            font-weight: bold;
            background-color: #f0f0f0;
        }

        .payment-table td {
            padding: 5px;
            border: 1px solid #000;
        }

        .amount-col {
            text-align: right;
        }

        /* Summary Section */
        .summary-section {
            margin-top: 10px;
            float: right;
            width: 40%;
            line-height: 1;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-table td {
            padding: 3px 5px;
        }

        .summary-label {
            font-weight: bold;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 0;
            margin: 0;
        }

        .footer p {
            text-align: center;
            font-size: 0.8em;
            padding: 0 10px;
            margin: 5px 0;
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
            width: 100%;
            height: {{ $__inv_footer_h }}px;
            visibility: hidden;
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

        @media print and (min-width: 149mm) {
            .header-image,
            .footer-image {
                max-height: {{ $__inv_header_h }}px;
            }

            .header-placeholder,
            .footer-placeholder {
                height: {{ $__inv_footer_h }}px;
            }

            .content {
                margin-bottom: {{ $__inv_footer_h * 2 }}px;
            }
        }

        @media print and (max-width: 148mm), screen and (max-width: 148mm) {
            body {
                font-size: 11px;
            }

            .content {
                margin-bottom: {{ min($__inv_footer_h * 2, 64) }}px;
                padding: 8px 5mm;
            }
        }

        @media print {
            body.A5 .content {
                margin-bottom: 0 !important;
                padding-bottom: 4px !important;
            }
        }

        .barcode {
            text-align: center;
            margin: 5px 0;
        }

        .barcode img {
            height: 30px;
        }

        .title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin: 5px 0;
        }

        .invoice-heading,
        .appointment-summary,
        .detail-grid,
        .payment-summary {
            width: 100%;
            border-collapse: collapse;
        }

        .invoice-heading { background: #f3f8fa; border: 0 !important; border-bottom: 2px solid #0f766e !important; margin: 0 0 9px; outline: 0 !important; box-shadow: none !important; table-layout: fixed; }
        .invoice-heading tbody,
        .invoice-heading tr,
        .invoice-heading td { border: 0 !important; outline: 0 !important; box-shadow: none !important; }
        .invoice-heading td { padding: 7px 8px; vertical-align: middle; }
        .invoice-heading-title { width: 32%; }
        .invoice-heading-token { text-align: center; width: 42%; }
        .invoice-heading-reference { text-align: right; width: 26%; }
        .heading-barcode { display: block; height: 24px; max-width: 100%; object-fit: contain; }
        .invoice-heading-title .heading-barcode { margin: 0 0 3px; }
        .invoice-heading-reference .heading-barcode { margin: 0 0 3px auto; }
        .token-layout { border-collapse: collapse; margin: 0 auto; table-layout: fixed; width: 100%; }
        .token-layout td { padding: 0 2px !important; text-align: center; vertical-align: middle; }
        .token-barcode-cell { width: 28%; }
        .token-value-cell { width: 44%; }
        .token-barcode { display: block; height: 24px; margin: 0 auto; max-width: 100%; width: 100%; }
        .token-qr-row td { padding-top: 3px !important; }
        .token-qr { display: block; height: 24px; margin: 0 auto; width: 24px; }
        .invoice-title {
            color: #123b4a;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.2;
            white-space: nowrap;
        }
        .invoice-caption { color: #64748b; font-size: 10px; }
        .invoice-reference { text-align: right; }
        .reference-number { color: #123b4a; font-size: 15px; font-weight: 700; }
        .invoice-heading::before,
        .invoice-heading::after,
        .invoice-heading tr::before,
        .invoice-heading tr::after,
        .invoice-heading td::before,
        .invoice-heading td::after,
        .invoice-heading *::before,
        .invoice-heading *::after,
        .invoice-title::before,
        .invoice-title::after,
        .reference-number::before,
        .reference-number::after { content: none !important; display: none !important; }
        .reference-serial {
            border: 2px solid #0f766e;
            color: #0f766e;
            display: inline-block;
            font-size: 30px;
            font-weight: 700;
            line-height: 1.1;
            min-width: 76px;
            padding: 6px 10px;
            text-align: center;
        }
        .reference-serial-label {
            display: block;
            font-size: 9px;
            letter-spacing: 1px;
            margin-bottom: 3px;
        }

        .appointment-summary { margin: 0 0 10px; }
        .appointment-summary td {
            background: #eff6f6;
            border: 1px solid #cbdedf;
            padding: 5px 7px;
            text-align: center;
            vertical-align: middle;
            width: 50%;
        }
        .summary-caption { color: #64748b; display: block; font-size: 10px; }
        .summary-value { color: #123b4a; display: inline; font-size: 13px; font-weight: 700; white-space: nowrap; }
        .summary-separator { display: inline; }

        .detail-grid { margin: 0 0 9px; }
        .detail-grid > tbody > tr > td { vertical-align: top; width: 50%; }
        .detail-grid > tbody > tr > td:first-child { padding-right: 6px; }
        .detail-grid > tbody > tr > td:last-child { padding-left: 6px; }
        .detail-panel { background: #fbfdfe; border: 1px solid #d8e1e5; padding: 7px 8px; }
        .panel-title {
            border-bottom: 1px solid #d8e1e5;
            color: #123b4a;
            font-size: 11px;
            font-weight: 700;
            margin: 0 0 6px;
            padding: 0 0 5px;
        }
        .patient-detail-grid { border-collapse: collapse; table-layout: fixed; width: 100%; }
        .patient-detail-grid td { padding: 0 8px 0 0; vertical-align: top; width: 50%; }
        .detail-row {
            display: grid;
            grid-template-columns: 104px 8px minmax(0, 1fr);
            margin: 0;
            width: 100%;
        }
        .detail-label,
        .detail-colon,
        .detail-value { font-size: 11px; min-width: 0; }
        .detail-label { color: #64748b; white-space: nowrap; }
        .detail-colon { color: #64748b; text-align: center; }
        .detail-value { color: #111827; overflow-wrap: anywhere; }
        .appointment-detail-grid { border-collapse: collapse; table-layout: fixed; width: 100%; }
        .appointment-detail-grid td { padding: 0 8px 0 0; vertical-align: top; width: 50%; }
        .doctor-detail-row { margin-top: 0; }
        .payment-mode-row { margin-top: 0; }
        .payment-totals-layout { border-collapse: collapse; margin-top: 5px; table-layout: fixed; width: 100%; }
        .payment-totals-layout > tbody > tr > td { vertical-align: top; width: 50%; }
        .payment-totals-qr { padding: 4px 8px; text-align: center; }
        .payment-qr { display: inline-block; height: 68px; width: 68px; }
        .payment-summary { margin: 0 0 0 auto; width: 100%; }

        .section-title { border-bottom: 1px solid #cbdedf; color: #123b4a; font-size: 12px; font-weight: 700; margin: 8px 0 4px; padding-bottom: 3px; }
        .payment-table { margin: 0; }
        .payment-table th { background: #eff6f6; border-color: #cbdedf; font-size: 11px; padding: 6px; }
        .payment-table td { border-color: #cbdedf; font-size: 11px; padding: 6px; }
        .payment-summary { margin: 0; width: 100%; }
        .payment-summary td { border-bottom: 1px solid #e2e8f0; font-size: 10px; padding: 4px 6px; }
        .payment-summary .total-row td { background: #eff6f6; border-bottom: 0; color: #123b4a; font-size: 12px; font-weight: 700; }

        .footer-section {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: {{ $__inv_footer_h + 22 }}px;
            display: block;
            z-index: 9999;
            pointer-events: none;
        }
        .footer-placeholder { height: {{ $__inv_footer_h + 22 }}px; visibility: hidden; }
        .footer-image {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            height: {{ $__inv_footer_h }}px;
            max-height: {{ $__inv_footer_h }}px;
            object-fit: contain;
            object-position: bottom center;
            z-index: 9998;
        }
        .footer-meta-wrap {
            position: absolute;
            left: 0;
            right: 0;
            bottom: {{ $__inv_footer_h }}px;
            width: 100%;
            padding: 0 20px;
            z-index: 10000;
            box-sizing: border-box;
        }
        .footer-meta-wrap.with-image { bottom: calc({{ $__inv_footer_h }}px - 36px); }
        .footer-meta-wrap.no-image { bottom: 0; }
        .footer-meta-row { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .footer-meta-left,
        .footer-meta-right {
            width: 50%;
            line-height: 1.1;
            vertical-align: middle;
            font-size: 13px;
        }
        .footer-meta-left { text-align: left; white-space: nowrap; padding-right: 8px; }
        .footer-meta-right { text-align: right; white-space: nowrap; padding-left: 8px; }
        .footer-custom-line {
            text-align: center;
            font-size: {{ max(6, min(72, (int) ($footer_font_size ?? 14))) }}px;
            line-height: 1.15;
            white-space: normal;
        }
        .footer-custom-item,
        .footer-separator { display: inline-block; white-space: nowrap; }
        .footer-date-time { color: #000; font-size: 13px; line-height: 1.15; width: 100%; }

        @media print and (max-width: 148mm), screen and (max-width: 148mm) {
            .invoice-title,
            .reference-number { font-size: 13px !important; }
            .invoice-title { white-space: nowrap; }
            .detail-panel { padding: 6px; }
            .detail-row { grid-template-columns: 104px 8px minmax(0, 1fr); }
            .payment-summary { width: 100%; }
        }

        @media print {
            .content { margin-bottom: 0 !important; padding-bottom: 4px !important; }
            .footer-section { height: {{ $__inv_footer_h + 22 }}px !important; overflow: visible !important; }
            .footer-image {
                position: absolute !important;
                bottom: 2mm !important;
                height: auto !important;
                max-height: none !important;
                width: 100% !important;
                object-fit: initial !important;
            }
            .footer-meta-wrap.with-image { bottom: calc({{ $__inv_footer_h }}px - 36px + 3.5mm) !important; }
            .footer-meta-left,
            .footer-meta-right,
            .footer-date-time { font-size: 9px !important; }
            .footer-meta-wrap { box-sizing: border-box !important; padding: 0 20px !important; }
        }

        @media print and (max-width: 90mm) {
            .invoice-heading td { padding: 3px 2px; }
            .invoice-heading,
            .invoice-heading tbody,
            .invoice-heading tr,
            .invoice-heading td { display: block; box-sizing: border-box; width: 100%; }
            .invoice-heading-title { text-align: left; }
            .invoice-heading-reference { text-align: right; }
            .invoice-title,
            .reference-number { font-size: 9px; }
            .invoice-title { white-space: nowrap; }
            .invoice-caption,
            .reference-serial-label { font-size: 7px; }
            .reference-serial { font-size: 18px; min-width: 0; padding: 3px 1px; }
            .reference-number { font-size: 9px !important; }
            .heading-barcode { height: 14px; }
            .payment-totals-layout { margin-top: 3px; }
            .payment-totals-layout > tbody > tr > td { width: 38%; }
            .payment-totals-layout > tbody > tr > td:last-child { width: 62%; }
            .payment-totals-qr { padding: 3px 2px; }
            .payment-qr { height: 40px; width: 40px; }
            .appointment-summary td { padding: 3px 2px; }
            .appointment-summary td { padding: 4px 2px; }
            .summary-caption { font-size: 8px; }
            .summary-value { font-size: 10px; }
            .detail-grid > tbody > tr > td {
                display: block;
                padding: 0 !important;
                width: 100%;
            }
            .detail-panel { margin-bottom: 4px; padding: 4px; }
            .panel-title { font-size: 9px; margin-bottom: 3px; padding-bottom: 2px; }
            .patient-detail-grid td,
            .appointment-detail-grid td { padding: 0 4px 0 0; }
            .appointment-detail-grid .detail-row { margin: 0; }
            .detail-row { margin: 0; }
            .detail-label,
            .detail-value { font-size: 10px; }
            .detail-row { grid-template-columns: 86px 8px minmax(0, 1fr); }
            .payment-mode-row,
            .doctor-detail-row { margin-top: 0; }
            .section-title { font-size: 10px; margin: 4px 0 2px; }
            .payment-table th,
            .payment-table td { font-size: 9px; padding: 3px; }
            .payment-summary { width: 100%; margin-top: 4px; }
            .payment-summary td { font-size: 8px; padding: 2px 3px; }
            .payment-summary .total-row td { font-size: 9px; }
        }

    </style>
</head>

<body class="{{ $paperSize ?? 'A4' }}">
    <!-- Header Section -->
    @includeIf('prints.partials._header', [
        'header_image' => $header_image ?? null,
        'headerHeight' => $__inv_header_h,
        'footerHeight' => $__inv_footer_h,
        'printed_at' => $printed_at ?? null,
        'showHeaderFooter' => $showHeaderFooter ?? true,
    ])

    <div class="content">
        @php
            $appointmentNumber = function_exists('prefixed_serial')
                ? prefixed_serial('appointment_prefix', 'APPN', $appointment->id, 3)
                : 'APPN' . str_pad($appointment->id, 3, '0', STR_PAD_LEFT);
            $appointmentTime = Carbon\Carbon::parse($appointment->appoinment_date);
            $patientAge = trim((string) ($patient->age ?? ''));
            $patientAge = preg_replace('/\s*(?:years?\s*)+$/i', '', $patientAge);
            $doctorFee = (float) ($appointment->doctor_fee ?? 0);
            $discountPercent = (float) ($appointment->discount_percentage ?? 0);
            $discountAmount = round($doctorFee * $discountPercent / 100, 2);
            $netAmount = max(0, $doctorFee - $discountAmount);
            $paidAmount = (float) ($paid_amount ?? 0);
            $dueAmount = max(0, (float) ($due_amount ?? ($netAmount - $paidAmount)));
            $displayPaymentMode = $appointment->payment_mode;
            if (
                strtolower((string) ($appointment->booking_source ?? '')) === 'website'
                && $paidAmount <= 0
                && strtolower((string) $displayPaymentMode) === 'cash'
            ) {
                $displayPaymentMode = null;
            }
            $slotLabels = [
                'Morning' => '6:00 AM - 12:00 PM',
                'Noon' => '12:00 PM - 2:00 PM',
                'Evening' => '2:00 PM - 8:00 PM',
                'Night' => '8:00 PM - 6:00 AM',
            ];
            $paymentSources = [
                'Cash' => 'Offline',
                'Cheque' => 'Offline',
                'Transfer to Bank Account' => 'Bank Transfer',
                'Upi' => 'Online',
                'Online' => 'Online',
                'Other' => 'Other',
            ];
            $paymentSource = strtolower((string) ($appointment->booking_source ?? '')) === 'website'
                ? 'Website'
                : ($paymentSources[$appointment->payment_mode ?? ''] ?? 'N/A');
            $departmentName = $doctor->details->department->name ?? 'N/A';
            $appointmentQrPayload = json_encode([
                'Patient' => $patient->name ?? 'N/A',
                'Phone' => $patient->phone ?? 'N/A',
                'Age' => $patient->age ?? 'N/A',
                'Gender' => $patient->gender ?? 'N/A',
                'Email' => $patient->email ?? 'N/A',
                'Blood Group' => $patient->blood_group ?? 'N/A',
                'Address' => $patient->address ?? 'N/A',
                'Department' => $departmentName,
                'Shift' => $appointment->shift ?? 'N/A',
                'Time Slot' => $slotLabels[$appointment->slot ?? ''] ?? ($appointment->slot ?? 'N/A'),
                'Payment Mode' => $displayPaymentMode ?: 'N/A',
                'Doctor' => $doctor->name ?? 'N/A',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            $appointmentQrCode = 'data:image/png;base64,' . (new \Milon\Barcode\DNS2D())->getBarcodePNG($appointmentQrPayload, 'QRCODE', 3, 3);
            $appointmentBarcode = 'data:image/png;base64,' . (new \Milon\Barcode\DNS1D())->getBarcodePNG($appointmentNumber, 'C128', 1.5, 30);
        @endphp

        <table class="invoice-heading">
            <tr>
                <td class="invoice-heading-title">
                    <img class="heading-barcode" src="{{ $appointmentBarcode }}" alt="Appointment invoice barcode">
                    <div class="invoice-title">Appointment Invoice</div>
                </td>
                <td class="invoice-heading-token">
                    <table class="token-layout">
                        <tr>
                            <td class="token-value-cell">
                                <div class="reference-serial">
                                    <span class="reference-serial-label">TOKEN / SERIAL NO.</span>
                                    {{ $appointment->patient_serial ?? 'N/A' }}
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="invoice-heading-reference">
                    <img class="heading-barcode" src="{{ $appointmentBarcode }}" alt="Appointment number barcode">
                    <div class="reference-number">{{ $appointmentNumber }}</div>
                </td>
            </tr>
        </table>

        <table class="appointment-summary">
            <tr>
                <td>
                    <span class="summary-caption">Appointment Date &amp; Time</span>
                    <span class="summary-value">{{ $appointmentTime->format('d M Y') }}</span><span class="summary-separator"> </span><span class="summary-value">{{ $appointmentTime->format('h:i A') }}</span>
                </td>
                <td><span class="summary-caption">Status</span><span class="summary-value">{{ $appointment->appoinment_status ?? 'Pending' }}</span></td>
            </tr>
        </table>

        <table class="detail-grid">
            <tr>
                <td>
                    <div class="detail-panel">
                        <div class="panel-title">Patient Details and Doctor Information</div>
                        <table class="patient-detail-grid">
                            <tr>
                                <td><div class="detail-row"><span class="detail-label">Name</span><span class="detail-colon">:</span><span class="detail-value">{{ $patient->name ?? 'N/A' }}</span></div></td>
                                <td><div class="detail-row"><span class="detail-label">Phone</span><span class="detail-colon">:</span><span class="detail-value">{{ $patient->phone ?? 'N/A' }}</span></div></td>
                            </tr>
                            <tr>
                                <td><div class="detail-row"><span class="detail-label">Age</span><span class="detail-colon">:</span><span class="detail-value">{{ $patientAge !== '' ? $patientAge . ' years' : 'N/A' }}</span></div></td>
                                <td><div class="detail-row"><span class="detail-label">Gender</span><span class="detail-colon">:</span><span class="detail-value">{{ $patient->gender ?? 'N/A' }}</span></div></td>
                            </tr>
                            <tr>
                                <td><div class="detail-row"><span class="detail-label">Email</span><span class="detail-colon">:</span><span class="detail-value">{{ $patient->email ?? 'N/A' }}</span></div></td>
                                <td><div class="detail-row"><span class="detail-label">Blood Group</span><span class="detail-colon">:</span><span class="detail-value">{{ $patient->blood_group ?? 'N/A' }}</span></div></td>
                            </tr>
                        </table>
                        @if (!empty($patient->address))
                            <div class="detail-row"><span class="detail-label">Address</span><span class="detail-colon">:</span><span class="detail-value">{{ $patient->address }}</span></div>
                        @endif
                        <table class="appointment-detail-grid">
                            <tr>
                                <td><div class="detail-row"><span class="detail-label">Department</span><span class="detail-colon">:</span><span class="detail-value">{{ $departmentName }}</span></div></td>
                                <td><div class="detail-row"><span class="detail-label">Shift</span><span class="detail-colon">:</span><span class="detail-value">{{ $appointment->shift ?? 'N/A' }}</span></div></td>
                            </tr>
                            <tr>
                                <td><div class="detail-row"><span class="detail-label">Time Slot</span><span class="detail-colon">:</span><span class="detail-value">{{ $slotLabels[$appointment->slot ?? ''] ?? ($appointment->slot ?? 'N/A') }}</span></div></td>
                                <td><div class="detail-row payment-mode-row"><span class="detail-label">Payment Mode</span><span class="detail-colon">:</span><span class="detail-value">{{ $displayPaymentMode ?: 'N/A' }}</span></div></td>
                            </tr>
                        </table>
                        <div class="detail-row doctor-detail-row"><span class="detail-label">Doctor</span><span class="detail-colon">:</span><span class="detail-value">{{ $doctor->name ?? 'N/A' }}</span></div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="section-title">Payment Details</div>
        <table class="payment-table">
            <thead>
                <tr>
                    <th style="width: 42%;">Transaction ID</th>
                    <th style="width: 25%;">Source</th>
                    <th style="width: 33%; text-align: right;" class="amount-col">Appointment Fee (Tk)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $appointment->transaction_id ?? 'N/A' }}</td>
                    <td>{{ $paymentSource }}</td>
                    <td class="amount-col">{{ number_format($doctorFee, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <table class="payment-totals-layout">
            <tr>
                <td class="payment-totals-qr">
                    <img class="payment-qr" src="{{ $appointmentQrCode }}" alt="Patient and doctor information QR code">
                </td>
                <td>
                    <table class="payment-summary">
                        <tr>
                            <td>Appointment Fee</td>
                            <td class="amount-col">Tk. {{ number_format($doctorFee, 2) }}</td>
                        </tr>
                        @if ($discountAmount > 0)
                            <tr>
                                <td>Discount ({{ number_format($discountPercent, 2) }}%)</td>
                                <td class="amount-col">- Tk. {{ number_format($discountAmount, 2) }}</td>
                            </tr>
                        @endif
                        <tr class="total-row">
                            <td>Net Payable</td>
                            <td class="amount-col">Tk. {{ number_format($netAmount, 2) }}</td>
                        </tr>
                        <tr>
                            <td>Paid Amount</td>
                            <td class="amount-col">Tk. {{ number_format($paidAmount, 2) }}</td>
                        </tr>
                        <tr>
                            <td>Due Amount</td>
                            <td class="amount-col">Tk. {{ number_format($dueAmount, 2) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <!-- Footer Section -->
    @if ($showHeaderFooter ?? true)
        <div class="footer-section">
            @php
                $footerFallbackLine = trim((string) config('app.invoice_footer_fallback_line', 'Powered By: www.toamedit.com Support: 01919-592638'));
                $footerPrintedAt = trim((string) ($printed_at ?? ''));
                $footerContentPlain = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) ($footer_content ?? ''))));
                $footerContentPlain = (string) preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $footerContentPlain);
                $footerContentPlain = str_replace(['،', '٬', '﹐', '，'], ',', $footerContentPlain);
                $footerParts = preg_split('/,+/u', $footerContentPlain) ?: [];
                $footerParts = array_values(array_filter(array_map(static function ($part) {
                    return trim((string) $part);
                }, $footerParts), static function ($part) {
                    return $part !== '';
                }));
                $footerPartsHtml = '';
                foreach ($footerParts as $footerItem) {
                    $safeFooterItem = str_replace(' ', '&nbsp;', e((string) $footerItem));
                    $footerPartsHtml .= ($footerPartsHtml === '' ? '' : '<span class="footer-separator">,&nbsp;</span>')
                        . '<span class="footer-custom-item">' . $safeFooterItem . '</span>';
                }
                $hasCustomFooterContent = $footerPartsHtml !== '';
                $showFooterMetaRow = ($footerFallbackLine !== '' || $footerPrintedAt !== '');
            @endphp

            @if (!empty($footer_image))
                @if ($showFooterMetaRow || $hasCustomFooterContent)
                    <div class="footer-meta-wrap with-image">
                        <div class="footer-date-time">
                            @if ($hasCustomFooterContent)
                                <div class="footer-custom-line">{!! $footerPartsHtml !!}</div>
                            @endif
                            <table class="footer-meta-row">
                                <tr>
                                    <td class="footer-meta-left">{{ $footerFallbackLine }}</td>
                                    <td class="footer-meta-right">
                                        @if ($footerPrintedAt !== '')
                                            Printing Date: {{ $footerPrintedAt }}
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                @endif
                <img src="{{ $footer_image }}" alt="Footer" class="footer-image">
            @else
                <div class="footer-placeholder"></div>
                @if ($showFooterMetaRow || $hasCustomFooterContent)
                    <div class="footer-meta-wrap no-image">
                        <div class="footer-date-time">
                            @if ($hasCustomFooterContent)
                                <div class="footer-custom-line">{!! $footerPartsHtml !!}</div>
                            @endif
                            <table class="footer-meta-row">
                                <tr>
                                    <td class="footer-meta-left">{{ $footerFallbackLine }}</td>
                                    <td class="footer-meta-right">
                                        @if ($footerPrintedAt !== '')
                                            Printing Date: {{ $footerPrintedAt }}
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    @endif
    @if (!empty($autoPrint))
        <script>
            (function () {
                var triggered = false;
                var doPrint = function () {
                    if (triggered) return;
                    triggered = true;
                    try { window.focus(); } catch (e) {}
                    try { window.print(); } catch (e) {}
                };
                var attemptPrint = function () {
                    setTimeout(doPrint, 600);
                    setTimeout(doPrint, 1400);
                };
                if (document.readyState === 'complete' || document.readyState === 'interactive') {
                    attemptPrint();
                } else {
                    window.addEventListener('DOMContentLoaded', attemptPrint, { once: true });
                    window.addEventListener('load', attemptPrint, { once: true });
                }
            })();
        </script>
    @endif
    @include('prints.partials._responsive_invoice_print')
</body>

</html>