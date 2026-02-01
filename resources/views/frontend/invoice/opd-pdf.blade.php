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

        /* Patient Info Two Column Layout */
        .patient-info {
            margin-bottom: 10px;
            padding: 10px;
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
            width: 40%;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-table td {
            padding: 3px 5px;
            border-bottom: 1px solid #ddd;
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
    @if($header_image)
    <div class="header">
        <img src="{{ $header_image }}" class="header-image" alt="Clinic Header">
    </div>
    @endif

    <!-- Patient Information Section - Two Column Layout -->
    <div class="patient-info">
        <table>
            <tr>
                <td>
                    <div><span class="info-label">OPD ID:</span> <span class="info-value">{{ $opd_id }}</span></div>
                </td>
                <td>
                    <div><span class="info-label">OPD Checkin ID:</span> <span class="info-value">{{ $opd_checkin_id }}</span></div>
                </td>
            </tr>
            <tr>
                <td>
                    <div><span class="info-label">Patient Name:</span> <span class="info-value">{{ $patient_name ?? '' }}</span></div>
                </td>
                <td>
                    <div><span class="info-label">Date:</span> <span class="info-value">{{ $appointment_date ?? '' }}</span></div>
                </td>
            </tr>
            <tr>
                <td>
                    <div><span class="info-label">Blood Group:</span> <span class="info-value">{{ $blood_group ?? '-' }}</span></div>
                </td>
                <td>
                    <div><span class="info-label">Age:</span> <span class="info-value">{{ $age ?? '' }}</span></div>
                </td>
            </tr>
            <tr>
                <td>
                    <div><span class="info-label">Address:</span> <span class="info-value">{{ $address ?? '-' }}</span></div>
                </td>
                <td>
                    <div><span class="info-label">Gender:</span> <span class="info-value">{{ $gender ?? '' }}</span></div>
                </td>
            </tr>
            <tr>
                <td>
                    <div><span class="info-label">Consultant Doctor:</span> <span class="info-value">{{ $consultant_doctor ?? '' }}</span></div>
                </td>
                <td>
                    <div><span class="info-label">Known Allergies:</span> <span class="info-value">{{ $known_allergies ?? '-' }}</span></div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div><span class="info-label"></span> <span class="info-value">{{ $consultant_qualification }}</span></div>
                </td>
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
                    <td>Paid Amount</td>
                    <td class="amount-col">Tk {{ number_format($paid_amount, 2) }}</td>
                </tr>
                <tr class="balance-row">
                    <td>Balance Amount</td>
                    <td class="amount-col">Tk {{ number_format($balance_amount, 2) }}</td>
                </tr>
            </table>
        </div>

        <div class="clearfix"></div>
    </div>

    <!-- Footer Section -->
    <div class="footer">
        <p>Address: {{ $clinic_address }}</p>
        @if($footer_image)
        <img src="{{ $footer_image }}" class="footer-image" alt="Clinic Footer">
        @endif
    </div>
</body>

</html>