<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>OPD Invoice</title>
    <style>
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
            max-height: 80px;
            margin: 0;
            padding: 0;
            display: block;
        }

        .header-placeholder {
            height: 80px;
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

        .clearfix {
            clear: both;
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
            text-align: left;
            font-size: inherit; /* Inherits from body font-size */
            padding: 0 10px;
            margin: 0 0 5px 20px;
        }

        .footer-image {
            width: 100%;
            max-height: 80px;
            margin: 0;
            padding: 0;
            display: block;
        }

        .footer-placeholder {
            height: 80px;
            width: 100%;
        }

        .footer-content {
            text-align: center;
            padding: 0 10px;
            margin: 0 0 5px;
            font-size: inherit;
        }

        /* Paper size classes for manual control */
        .paper-a4 {
            font-size: 14px !important;
        }

        .paper-a5 {
            font-size: 11px !important;
        }
    </style>
</head>

<body>
    <!-- Header Section -->
    <div class="header">
        @php
            $resolveInlineImage = function ($value) {
                $source = trim((string) $value);
                if ($source === '') {
                    return '';
                }

                if (str_starts_with($source, 'data:image/')) {
                    return $source;
                }

                $normalized = str_replace('\\\\', '/', $source);
                $normalized = preg_replace('#^file:///+#', '', $normalized);
                $normalized = preg_replace('#^/([A-Za-z]:/)#', '$1', (string) $normalized);

                $candidates = [$normalized];

                $relative = ltrim($normalized, '/');
                if (!preg_match('#^[A-Za-z]:/#', $relative) && !str_starts_with($relative, 'http://') && !str_starts_with($relative, 'https://')) {
                    $relative = preg_replace('#^storage/#', '', $relative);
                    $relative = preg_replace('#^public/storage/#', '', $relative);
                    $relative = preg_replace('#^public/#', '', $relative);

                    $candidates[] = public_path('storage/' . $relative);
                    $candidates[] = storage_path('app/public/' . $relative);
                    $candidates[] = public_path($relative);
                }

                foreach ($candidates as $filePath) {
                    $path = str_replace('\\\\', '/', (string) $filePath);
                    if ($path !== '' && file_exists($path)) {
                        $mime = mime_content_type($path) ?: 'image/png';
                        $binary = file_get_contents($path);
                        if ($binary !== false) {
                            return 'data:' . $mime . ';base64,' . base64_encode($binary);
                        }
                    }
                }

                return str_starts_with($source, 'http://') || str_starts_with($source, 'https://')
                    ? $source
                    : '';
            };

            $headerSrc = $resolveInlineImage($header_image ?? '')
                ?: $resolveInlineImage($header_image_path ?? '')
                ?: $resolveInlineImage($header_image_url ?? '');
        @endphp
        @if($headerSrc)
        <img src="{{ $headerSrc }}" class="header-image" alt="Clinic Header">
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

    <!-- Footer Section -->
    <div class="footer">
            @php
                $footerSrc = $resolveInlineImage($footer_image ?? '')
                    ?: $resolveInlineImage($footer_image_path ?? '')
                    ?: $resolveInlineImage($footer_image_url ?? '');
            @endphp

            @if($footerSrc)
                @if(!empty($footer_content))
                    <div class="footer-content" style="position:relative; z-index:11;">{!! $footer_content !!}</div>
                @endif
                <img src="{{ $footerSrc }}" class="footer-image" alt="Clinic Footer">
            @else
                <div class="footer-placeholder"></div>
                @if(!empty($footer_content))
                    <div class="footer-content">{!! $footer_content !!}</div>
                @endif
            @endif
    </div>
</body>

</html>