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
            font-size: 13px;
            margin: 0;
            padding: 16px;
            color: #111827;
        }

        .page {
            max-width: 900px;
            margin: 0 auto;
            padding-bottom: 90px;
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
        }

        th,
        td {
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
        }

        .text-right {
            text-align: right;
        }

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
            .actions {
                display: none;
            }

            body {
                padding: 0;
            }
        }
    </style>
</head>

<body>
    <div class="page">
        @if (!empty($headerImage))
            <img src="{{ $headerImage }}" class="header-image" alt="Header">
        @else
            <div class="header-placeholder"></div>
        @endif

        <div class="header">
            <div>
                <div class="title">IPD Running Bill</div>
                <div class="muted">Print: {{ $printed_at ?? '' }}</div>
            </div>
            <div class="muted">
                IPD ID: {{ $ipdpatient?->id ?? 'N/A' }}
                @if (!empty($barcodeImage))
                    <div class="barcode"><img src="{{ $barcodeImage }}" alt="Barcode"></div>
                @endif
            </div>
        </div>

        <div class="info-grid">
            <div><strong>Patient:</strong> {{ $ipdpatient?->patient?->name ?? 'N/A' }}</div>
            <div><strong>Phone:</strong> {{ $ipdpatient?->patient?->phone ?? 'N/A' }}</div>
            <div><strong>Consultant:</strong> {{ $ipdpatient?->doctor?->name ?? 'N/A' }}</div>
            <div><strong>Bed:</strong> {{ $ipdpatient?->bed?->name ?? 'N/A' }}</div>
            <div><strong>Admission:</strong> {{ $admission_at ?? 'N/A' }}</div>
            <div><strong>As of:</strong> {{ $summary['as_of'] ?? 'N/A' }}</div>
        </div>

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

        <div>
            <strong>Line Items</strong>
            <table>
                <thead>
                    <tr>
                        <th style="width: 44%">Item</th>
                        <th style="width: 16%">Category</th>
                        <th style="width: 10%" class="text-right">Qty</th>
                        <th style="width: 15%" class="text-right">Unit</th>
                        <th style="width: 15%" class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lines as $line)
                        <tr>
                            <td>{{ $line['item_name'] ?? 'N/A' }}</td>
                            <td>{{ $line['category'] ?? '' }}</td>
                            <td class="text-right">{{ number_format((float) ($line['quantity'] ?? 0), 2) }}</td>
                            <td class="text-right">{{ number_format((float) ($line['unit_price'] ?? 0), 2) }}</td>
                            <td class="text-right">{{ number_format((float) ($line['net_amount'] ?? 0), 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="muted">No charge items found.</td>
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

        @if (!empty($footerContent))
            <div class="footer-content" style="position:relative; z-index:11; text-align:center;">{!! $footerContent !!}</div>
        @elseif(!empty($footerFallbackLine))
            <div class="footer-content" style="position:relative; z-index:11; text-align:center;">{{ $footerFallbackLine }}</div>
        @endif

        @if (!empty($footerImage))
            <img src="{{ $footerImage }}" class="footer-image fixed" alt="Footer">
        @else
            <div class="footer-placeholder fixed"></div>
        @endif
    </div>
</body>

<script>
    window.addEventListener('load', function () {
        setTimeout(function () {
            window.print();
        }, 180);
    });
</script>

</html>
