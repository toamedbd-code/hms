<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>IPD Running Bill</title>
    <style>
        * {
            box-sizing: border-box;
        }

        @page {
            margin: 0mm;
            size: A4;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 10px;
            color: #111827;
            -webkit-font-smoothing: antialiased;
        }

        .page {
            width: 210mm;
            max-width: 210mm;
            margin: 0 auto;
            padding: 8mm 10mm 18mm 10mm;
            box-shadow: none;
        }

        .header-image,
        .footer-image {
            width: 100%;
            display: block;
            height: auto;
            object-fit: contain;
        }

        .header-placeholder,
        .footer-placeholder {
            width: 100%;
            height: 80px;
            display: block;
        }

        .footer-image.fixed,
        .footer-placeholder.fixed {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .barcode {
            margin-top: 6px;
        }

        .barcode img {
            height: 42px;
        }

        .title {
            font-size: 18px;
            font-weight: 700;
        }

        .muted {
            color: #6b7280;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px 16px;
            margin-bottom: 12px;
        }

        /* IPD-style info table to match final-bill layout */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .info-table td {
            padding: 2px 6px;
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

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 8px;
            margin: 12px 0 16px;
        }

        .summary-card {
            border: 1px solid #e5e7eb;
            padding: 8px;
            border-radius: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            table-layout: fixed;
            word-wrap: break-word;
        }

        th,
        td {
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
            font-size: 11px;
        }

        th {
            background: #f3f4f6;
        }

        .text-right {
            text-align: right;
        }

        /* keep rows together when printing */
        tr, td, th {
            page-break-inside: avoid;
        }

        thead { display: table-header-group; }
        tfoot { display: table-footer-group; }

        .actions {
            margin-top: 10px;
        }

        @media print and (min-width: 149mm) {
            .header-image,
            .footer-image { max-height: 80px; }
            .header-placeholder,
            .footer-placeholder { height: 80px; }
            .page { padding-bottom: 90px; }
        }

        @media print and (max-width: 148mm), screen and (max-width: 148mm) {
            body { font-size: 11px; }
            .header-image,
            .footer-image { max-height: 58px; }
            .header-placeholder,
            .footer-placeholder { height: 58px; }
            .page { padding-bottom: 70px; }
            .title { font-size: 15px; }
        }

        @media print {
            .actions { display: none; }
            body { padding: 0; }
            /* tighter spacing for print */
            th, td { padding: 4px 6px; }
            .title { font-size: 16px; }
        }
    </style>
</head>

<body>
    <div class="page">
        @includeIf('prints.partials._header')

        @php
            $ipd_serial = prefixed_serial('ipd_no_prefix', 'IPDN', $ipdpatient->id, 4) ?? ($ipdpatient?->id ?? 'N/A');
        @endphp
        <table style="width:100%; border-collapse: collapse; margin-bottom:8px;">
            <tr style="border:0;">
                <td style="border:0; width:30%; vertical-align: middle;">
                    @if(function_exists('DNS1D'))
                        {!! DNS1D::getBarcodeHTML($ipd_serial, 'C128', 1, 30) !!}
                    @elseif(!empty($barcodeImage))
                        <img src="{{ $barcodeImage }}" alt="Barcode" style="height:32px;">
                    @endif
                </td>
                <td style="border:0; text-align:center; vertical-align: middle;">
                    <div class="title">IPD Running Bill</div>
                    <div class="muted">Print: {{ $printed_at ?? '' }}</div>
                </td>
                <td style="border:0; width:30%; text-align:right; vertical-align: middle;">
                    @if(function_exists('DNS1D'))
                        {!! DNS1D::getBarcodeHTML($ipd_serial, 'C128', 1, 30) !!}
                    @elseif(!empty($barcodeImage))
                        <img src="{{ $barcodeImage }}" alt="Barcode" style="height:32px;">
                    @endif
                </td>
            </tr>
        </table>

        @php
            $patientName = $ipdpatient?->patient?->name ?? 'N/A';
            $patientAge = $ipdpatient?->patient?->age ?? 'N/A';
            $patientGender = $ipdpatient?->patient?->gender ?? 'N/A';
            $patientPhone = $ipdpatient?->patient?->phone ?? ($ipdpatient?->patient?->mobile ?? 'N/A');
            $doctorName = $ipdpatient?->doctor?->name ?? 'N/A';
            $bedName = $ipdpatient?->bed?->name ?? 'N/A';
            $admissionDate = $admission_at ?? null;
            $asOf = $summary['as_of'] ?? null;
        @endphp

        <table class="info-table">
            <tr>
                <td class="label">IPD ID</td>
                <td class="colon">:</td>
                <td class="value">{{ prefixed_serial('ipd_no_prefix', 'IPDN', $ipdpatient->id, 4) ?? ($ipdpatient?->id ?? 'N/A') }}</td>

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
                <td class="value">{{ $patientPhone }}</td>
            </tr>
            <tr>
                <td class="label">Credit Limit</td>
                <td class="colon">:</td>
                <td class="value">Tk {{ number_format((float) ($ipdpatient?->credit_limit ?? 0), 2) }}</td>

                <td class="label">Consultant</td>
                <td class="colon">:</td>
                <td class="value">{{ $doctorName }}</td>
            </tr>
            <tr>
                <td class="label">Bed</td>
                <td class="colon">:</td>
                <td class="value">{{ $bedName }}</td>

                <td class="label">Admission</td>
                <td class="colon">:</td>
                <td class="value">{{ $admissionDate ? \Carbon\Carbon::parse($admissionDate)->format('d-m-Y h:i A') : 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">As of</td>
                <td class="colon">:</td>
                <td class="value">{{ $asOf ?? 'N/A' }}</td>

                <td class="label">Case</td>
                <td class="colon">:</td>
                <td class="value">{{ $ipdpatient?->case ?? 'N/A' }}</td>
            </tr>
        </table>

        <div class="summary-grid">
            <div class="summary-card">
                <div class="muted">Total</div>
                <div><strong>Tk {{ number_format((float) ($summary['total'] ?? 0), 2) }}</strong></div>
            </div>
            <div class="summary-card">
                <div class="muted">Paid</div>
                <div><strong>Tk {{ number_format((float) ($summary['paid'] ?? 0), 2) }}</strong></div>
            </div>
            <div class="summary-card">
                <div class="muted">Due</div>
                <div><strong>Tk {{ number_format((float) ($summary['due'] ?? 0), 2) }}</strong></div>
            </div>
            <div class="summary-card">
                <div class="muted">Change</div>
                <div><strong>Tk {{ number_format((float) ($summary['change'] ?? 0), 2) }}</strong></div>
            </div>
            <div class="summary-card">
                <div class="muted">Status</div>
                <div><strong>{{ $summary['payment_status'] ?? 'N/A' }}</strong></div>
            </div>
        </div>

        @php
            $linesCollection = collect($lines ?? []);
            $medicineTotal = (float) $linesCollection->where('category', 'Medicine')->sum('net_amount');
            $labTotal = (float) $linesCollection->filter(function ($l) {
                $cat = $l['category'] ?? '';
                return in_array($cat, ['Pathology', 'Radiology']);
            })->sum('net_amount');
        @endphp

        @if($medicineTotal > 0 || $labTotal > 0)
        <table style="width:100%; margin-top:8px; border-collapse: collapse;">
            <tbody>
                @if($medicineTotal > 0)
                <tr>
                    <td style="border:0; width:70%;"></td>
                    <td style="border:0; width:20%; text-align:right; padding-right:8px; font-weight:600;">Medicine Bill</td>
                    <td style="border:0; width:10%; text-align:right; font-weight:600;">Tk {{ number_format($medicineTotal, 2) }}</td>
                </tr>
                @endif

                @if($labTotal > 0)
                <tr>
                    <td style="border:0; width:70%;"></td>
                    <td style="border:0; width:20%; text-align:right; padding-right:8px; font-weight:600;">Laboratory Bill</td>
                    <td style="border:0; width:10%; text-align:right; font-weight:600;">Tk {{ number_format($labTotal, 2) }}</td>
                </tr>
                @endif
            </tbody>
        </table>
        @endif

        <div>
            <strong>Line Items</strong>
            <table>
                <thead>
                    <tr>
                        <th style="width: 55%">Item</th>
                        <th style="width: 15%" class="text-right">Qty</th>
                        <th style="width: 15%" class="text-right">Unit</th>
                        <th style="width: 15%" class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lines as $line)
                        <tr>
                            <td>{{ $line['item_name'] ?? 'N/A' }}</td>
                            <td class="text-right">{{ number_format((float) ($line['quantity'] ?? 0), 2) }}</td>
                            <td class="text-right">{{ number_format((float) ($line['unit_price'] ?? 0), 2) }}</td>
                            <td class="text-right">{{ number_format((float) ($line['net_amount'] ?? 0), 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="muted">No charge items found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="actions">
            <button onclick="window.print()">Print</button>
        </div>

        @php
            $footerFallbackLine = trim((string) config('app.invoice_footer_fallback_line', ''));
        @endphp

        @include('prints.partials._footer', ['footer_image' => $footerImage ?? null, 'footer_content' => $footerContent ?? ($footerFallbackLine ?: null)])
    </div>
</body>

<script>
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 220);
    });
</script>

</html>
