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
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 16px;
            line-height: 1.3;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .invoice-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            width: 100%;
            height: 100%;
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
            flex: 1;
            padding: 0 15px;
            display: flex;
            flex-direction: column;
        }

        /* Title section with barcode */
        .title-section-table {
            width: 100%;
            margin-bottom: 12px;
        }

        .barcode-cell-left {
            right: 0;
            width: 20%;
        }

        .barcode-cell-right {
            right: 0;
            text-align: right;
            width: 20%;
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
            flex-grow: 1;
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
            font-size: 14px;
            text-align: left;
            padding: 0 20px;
            width: 100%;
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
                padding-bottom: 80px;
            }

            .footer-section {
                position: fixed;
                bottom: 0;
            }

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
                min-height: 100px;
            }

            .header-image {
                max-height: 100px;
            }

            .barcode-image {
                height: 25px;
                width: 120px;
            }

            .content-section {
                padding: 0 15px;
                padding-bottom: 80px;
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
                min-height: 80px;
            }

            .footer-image {
                max-height: 80px;
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
                min-height: 80px !important;
            }

            .header-image {
                max-height: 80px !important;
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
                min-height: 70px !important;
            }

            .footer-image {
                max-height: 80px !important;
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
                        @if(strpos($barcode, 'data:image') === 0)
                        <img src="{{ $barcode }}" alt="Barcode" class="barcode-image">
                        @endif
                    </td>
                    <td class="title-cell-center">
                        <div class="receipt-title">MONEY RECEIPT</div>
                    </td>
                    <td class="barcode-cell-right">
                        @if(strpos($barcode, 'data:image') === 0)
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
            @endphp

            @if ($delivery_date)
            <div class="delivery-date">
                Delivery Date & Time: {{ Carbon::parse($delivery_date)->format('d-m-Y, h:i A') }}
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
                            @if($due > 0)
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
                                <td class="amount-col"><strong>{{ number_format($total_amount, 2) }}</strong></td>
                            </tr>
                            <tr>
                                <td class="label-col">Vat Tk.</td>
                                <td class="amount-col">{{ number_format($vat, 2) }}</td>
                            </tr>
                            @if ($discount_type == 'percentage')
                            <tr>
                                <td class="label-col">Discount ({{ number_format($discount, 2) }}%)</td>
                                <td class="amount-col">{{ number_format(($total_amount * $discount / 100), 2) }}</td>
                            </tr>
                            @else
                            <tr>
                                <td class="label-col">Discount Tk.</td>
                                <td class="amount-col">{{ number_format($discount, 2) }}</td>
                            </tr>
                            @endif
                            @if ($extra_flat_discount != 0 )
                            <tr>
                                <td class="label-col">Extra Discount Tk.</td>
                                <td class="amount-col">{{ number_format($extra_flat_discount, 2) }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td class="label-col"><strong>Net Payable Tk</strong></td>
                                <td class="amount-col"><strong>{{ number_format($net_payable, 2) }}</strong></td>
                            </tr>
                            <tr>
                                <td class="label-col">Paid Tk.</td>
                                <td class="amount-col">{{ number_format($paid, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="label-col"><strong>Due Tk.</strong></td>
                                <td class="amount-col"><strong>{{ number_format($due, 2) }}</strong></td>
                            </tr>
                        </table>

                        <div class="amount-words">
                            {{ $amount_in_words }}
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Footer Section -->
        <div class="footer-section">
            @if($footer_content)
            <div class="footer-content">
                {!! $footer_content !!}
            </div>
            @endif

            <div class="footer-date-time">
                Powered By: www.toamedit.com Support: 01919-592638 ...............................Printing 
            Date: {{ now()->timezone('Asia/Dhaka')->format('d F, Y h:i a') }}
                
            </div>

            @if($footer_image)
            <img src="{{ $footer_image }}" alt="Footer" class="footer-image">
            @else
            <div class="footer-placeholder"></div>
            @endif
        </div>
    </div>
</body>
</html>