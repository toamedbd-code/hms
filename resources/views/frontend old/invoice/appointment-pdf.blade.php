<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Appointment Invoice</title>
    <style>
        @page {
            margin: 0mm 0mm;
            size: A4;
            /* Default to A4 */
        }

        @page A5 {
            size: A5;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            /* Default A4 size */
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }

        /* A5 specific styles */
        @media print {
            body.A5 {
                font-size: 11px !important;
            }

            .A5 .info-label {
                min-width: 100px !important;
            }

            .A5 .header-image,
            .A5 .footer-image {
                max-height: 60px !important;
            }

            .A5 .title {
                font-size: 13px !important;
            }

            .A5 .payment-table th,
            .A5 .payment-table td {
                padding: 3px !important;
            }

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
            max-height: 80px;
            margin: 0;
            padding: 0;
            display: block;
        }

        .content {
            margin-bottom: 60px;
            padding: 10px;
        }

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
            max-height: 80px;
            margin: 0;
            padding: 0;
            display: block;
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
    </style>
</head>

<body class="{{ $paperSize ?? 'A4' }}">
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
        @endif
    </div>

    <!-- Content Section -->
    <div class="content">
        <div class="patient-info">
            <table>
                <tr>
                    <td>
                        <div><span class="info-label">Patient Name:</span> <span class="info-value">{{ $patient->name }}</span></div>
                        <div><span class="info-label">Age:</span> <span class="info-value">{{ $patient->age ?? '' }} Years</span></div>
                        <div><span class="info-label">Email:</span> <span class="info-value">{{ $patient->email ?? '' }}</span></div>
                        <div><span class="info-label">Phone:</span> <span class="info-value">{{ $patient->phone ?? '' }}</span></div>
                        <div><span class="info-label">Gender:</span> <span class="info-value">{{ $patient->gender ?? '' }}</span></div>
                        <div><span class="info-label">Doctor:</span> <span class="info-value">{{ $doctor->name }} ({{ $doctor->id }})</span></div>
                        <div><span class="info-label">Department:</span> <span class="info-value">{{ $doctor->details->department->name ?? '' }}</span></div>
                        <div><span class="info-label">Blood Group:</span> <span class="info-value">{{ $patient->blood_group ?? '' }}</span></div>
                        <div><span class="info-label">Address:</span> <span class="info-value">{{ $patient->address ?? '' }}</span></div>
                    </td>
                    <td>
                        <div><span class="info-label">Appointment No:</span> <span class="info-value">APPN{{ str_pad($appointment->id, 3, '0', STR_PAD_LEFT) }}</span></div>
                        <div><span class="info-label">Appointment Date:</span> <span class="info-value">{{ Carbon\Carbon::parse($appointment->appoinment_date)->format('d-m-Y h:i A') }}</span></div>
                        <div><span class="info-label">Appointment Priority:</span> <span class="info-value">{{ $appointment->appointment_priority ?? '' }}</span></div>
                        <div><span class="info-label">Shift:</span> <span class="info-value">{{ $appointment->shift ?? '' }}</span></div>
                        <div><span class="info-label">Slot:</span> <span class="info-value">
                                @if($appointment->slot == 'Morning')
                                6:00 AM - 12:00 PM
                                @elseif($appointment->slot == 'Noon')
                                12:00 PM - 2:00 PM
                                @elseif($appointment->slot == 'Evening')
                                2:00 PM - 8:00 PM
                                @elseif($appointment->slot == 'Night')
                                8:00 PM - 6:00 AM
                                @else
                                {{ $appointment->slot ?? '' }}
                                @endif
                            </span></div>
                        <div><span class="info-label">Payment Mode:</span> <span class="info-value">{{ $appointment->payment_mode ?? '' }}</span></div>
                        <div><span class="info-label">Status:</span> <span class="info-value">{{ $appointment->appoinment_status ?? '' }}</span></div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Payment Details Section -->
        <h3>Payment Details</h3>
        <table class="payment-table">
            <thead>
                <tr>
                    <th style="width: 52%;">Transaction ID </th>
                    <th style="width: 20%;">Source</th>
                    <th style="width: 20%; text-align:right;" class="amount-col">Amount (Tk)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $appointment->transaction_id ?? '' }}</td>
                    <td>
                        @if($appointment->payment_mode == 'Cash')
                        Offline
                        @elseif($appointment->payment_mode == 'Cheque')
                        Offline
                        @elseif($appointment->payment_mode == 'Transfer to Bank Account')
                        Bank Transfer
                        @elseif($appointment->payment_mode == 'Upi')
                        Online
                        @elseif($appointment->payment_mode == 'Online')
                        Online
                        @elseif($appointment->payment_mode == 'Other')
                        Other
                        @else
                        N/A
                        @endif
                    </td>
                    <td class="amount-col">{{ number_format($appointment->doctor_fee, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <!-- Summary Section -->
        <div class="summary-section">
            <table class="summary-table">
                <tr>
                    <td><span class="summary-label">Net Amount</span></td>
                    <td class="amount-col">Tk. {{ number_format($appointment->doctor_fee, 2) }}</td>
                </tr>
                <tr>
                    <td><span class="summary-label">Discount( {{ $appointment->discount_percentage }}% )</span></td>
                    <td class="amount-col">
                        @if($appointment->discount_percentage > 0)
                        Tk. {{ number_format($appointment->doctor_fee * ($appointment->discount_percentage / 100), 2) }}
                        @else
                        Tk. 0.00
                        @endif
                    </td>
                </tr>
                <tr>
                    <td><span class="summary-label">Paid Amount</span></td>
                    <td class="amount-col">Tk. {{ number_format($appointment->doctor_fee - ($appointment->doctor_fee * ($appointment->discount_percentage / 100)), 2) }}</td>
                </tr>
            </table>
        </div>
        <div style="clear: both;"></div>
    </div>

    <!-- Footer Section -->
    @php
        $footerSrc = $resolveInlineImage($footer_image ?? '')
            ?: $resolveInlineImage($footer_image_path ?? '')
            ?: $resolveInlineImage($footer_image_url ?? '');
    @endphp
    <div class="footer">
        @if(!empty($footerSrc))
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