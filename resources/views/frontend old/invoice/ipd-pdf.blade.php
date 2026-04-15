<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>IPD Invoice</title>
    <style>
        @page {
            margin: 0mm 0mm;
        }

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
            max-height: 80px;
            display: block;
            object-fit: contain;
        }

        .header-placeholder,
        .footer-placeholder {
            height: 80px;
            width: 100%;
        }

        .content {
            padding: 10px 12px;
            padding-bottom: 80px;
        }

        .title-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 1px;
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
            height: 26px;
            width: 140px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .info-table td {
            padding: 2px 0;
            vertical-align: top;
        }

        .label {
            font-weight: bold;
            width: 18%;
            white-space: nowrap;
        }

        .colon {
            width: 2%;
            text-align: center;
        }

        .value {
            width: 30%;
        }

        .section-title {
            font-weight: bold;
            margin: 10px 0 6px;
        }

        .payments-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        .payments-table th {
            text-align: left;
            padding: 6px 5px;
            border-bottom: 1px solid #000;
            font-weight: bold;
            background-color: #f0f0f0;
            font-size: 13px;
        }

        .payments-table td {
            padding: 6px 5px;
            border-bottom: 1px solid #ddd;
            font-size: 13px;
        }

        .amount {
            text-align: right;
            white-space: nowrap;
        }

        .summary {
            margin-top: 10px;
            float: right;
            width: 45%;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-table td {
            padding: 4px 5px;
            border-bottom: 1px solid #ddd;
        }

        .summary-total td {
            font-weight: bold;
            border-top: 2px solid #000;
            border-bottom: 1px solid #000;
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
    </style>
</head>

<body>
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
    <div class="header">
        @if(!empty($headerSrc))
            <img src="{{ $headerSrc }}" class="header-image" alt="Header">
        @else
            <div class="header-placeholder"></div>
        @endif
    </div>

    <div class="content">
        <table class="title-table">
            <tr>
                <td class="barcode-cell-left">
                    @if(!empty($ipd_id))
                        {!! DNS1D::getBarcodeHTML($ipd_id, 'C128', 1, 30) !!}
                    @endif
                </td>
                <td>
                    <div class="title">IPD MONEY RECEIPT</div>
                </td>
                <td class="barcode-cell">
                    @if(!empty($ipd_id))
                        {!! DNS1D::getBarcodeHTML($ipd_id, 'C128', 1, 30) !!}
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
        @endphp

        <table class="info-table">
            <tr>
                <td class="label">IPD ID</td>
                <td class="colon">:</td>
                <td class="value">{{ $ipd_id ?? 'N/A' }}</td>

                <td class="label">Printed At</td>
                <td class="colon">:</td>
                <td class="value">{{ $printed_at ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">Patient Name</td>
                <td class="colon">:</td>
                <td class="value">{{ $patientName }}</td>

                <td class="label">Age</td>
                <td class="colon">:</td>
                <td class="value">{{ $patientAge }}</td>
            </tr>
            <tr>
                <td class="label">Gender</td>
                <td class="colon">:</td>
                <td class="value">{{ $patientGender }}</td>

                <td class="label">Phone</td>
                <td class="colon">:</td>
                <td class="value">{{ $patientPhone ?: 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Credit Limit</td>
                <td class="colon">:</td>
                <td class="value">Tk {{ number_format((float) ($ipdpatient?->credit_limit ?? 0), 2) }}</td>

                <td class="label"></td>
                <td class="colon"></td>
                <td class="value"></td>
            </tr>
            <tr>
                <td class="label">Consultant</td>
                <td class="colon">:</td>
                <td class="value">{{ $doctorName }}</td>

                <td class="label">Bed</td>
                <td class="colon">:</td>
                <td class="value">{{ $bedName }}</td>
            </tr>
            <tr>
                <td class="label">Admission</td>
                <td class="colon">:</td>
                <td class="value">
                    {{ $admissionDate ? \Carbon\Carbon::parse($admissionDate)->format('d-m-Y h:i A') : 'N/A' }}
                </td>

                <td class="label">Discharge</td>
                <td class="colon">:</td>
                <td class="value">
                    {{ $dischargeDate ? \Carbon\Carbon::parse($dischargeDate)->format('d-m-Y h:i A') : 'N/A' }}
                </td>
            </tr>
            <tr>
                <td class="label">Case</td>
                <td class="colon">:</td>
                <td class="value" colspan="4">{{ $ipdpatient?->case ?? 'N/A' }}</td>
            </tr>
        </table>

        <div class="section-title">Payment History</div>

        <table class="payments-table">
            <thead>
                <tr>
                    <th style="width: 6%;">SL</th>
                    <th style="width: 20%;">Date</th>
                    <th style="width: 16%;">Method</th>
                    <th style="width: 18%;">Transaction</th>
                    <th style="width: 25%;">Notes</th>
                    <th style="width: 15%; text-align: right;">Amount (Tk)</th>
                </tr>
            </thead>
            <tbody>
                @forelse(($payments ?? []) as $index => $payment)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            {{ $payment?->created_at ? \Carbon\Carbon::parse($payment->created_at)->format('d-m-Y h:i A') : 'N/A' }}
                        </td>
                        <td>{{ $payment?->payment_method ?? 'N/A' }}</td>
                        <td>{{ $payment?->transaction_id ?? '' }}</td>
                        <td>{{ $payment?->notes ?? '' }}</td>
                        <td class="amount">{{ number_format((float) ($payment?->amount ?? 0), 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No payments found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="summary">
            <table class="summary-table">
                <tr class="summary-total">
                    <td>Total Paid</td>
                    <td class="amount">Tk {{ number_format((float) ($total_paid ?? 0), 2) }}</td>
                </tr>
            </table>
        </div>

        <div class="clearfix"></div>
    </div>

    @php
        $footerSrc = $resolveInlineImage($footer_image ?? '')
            ?: $resolveInlineImage($footer_image_path ?? '')
            ?: $resolveInlineImage($footer_image_url ?? '');
    @endphp
    <div class="footer">
        <div class="powered-by">
            Powered By: www.toamedit.com Support: 01919-592638 — Printing Date: {{ now()->timezone('Asia/Dhaka')->format('d F, Y h:i a') }}
        </div>

        @if(!empty($footerSrc))
            @if(!empty($footer_content))
                <div class="footer-content" style="position:relative; z-index:11;">{!! $footer_content !!}</div>
            @endif
            <img src="{{ $footerSrc }}" class="footer-image" alt="Footer">
        @else
            <div class="footer-placeholder"></div>
            @if(!empty($footer_content))
                <div class="footer-content">{!! $footer_content !!}</div>
            @endif
        @endif
    </div>
</body>

</html>
