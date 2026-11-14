<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pharmacy Invoice {{ $pharmacyBill->bill_no }}</title>
    <style>
        @page {
            size: auto;
            margin: 0;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 12px;
            background: #edf2f0;
            color: #1b2923;
            font: 11px Arial, Helvetica, sans-serif;
        }

        .toolbar {
            max-width: 210mm;
            margin: 0 auto 12px;
            text-align: right;
        }

        .toolbar button {
            padding: 9px 18px;
            border: 0;
            border-radius: 4px;
            background: #126b46;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }

        .paper {
            width: 210mm;
            min-height: 297mm;
            max-width: calc(100vw - 32px);
            margin: 0 auto;
            padding: 0 3mm 3mm;
            background: #fff;
            box-shadow: 0 8px 30px #163b2b1c;
            border-top: 4px solid #16764a;
        }

        .print-shared-header { overflow: hidden; }

        .identity {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 5px;
            padding: 2mm 0;
            border-bottom: 1px solid #dce7e1;
        }

        .identity h1 {
            margin: 0;
            color: #176b45;
            font-size: 13px;
            letter-spacing: .02em;
        }

        .identity .subtitle {
            margin-top: 2px;
            color: #648071;
            font-size: 9px;
        }

        .invoice-meta {
            padding: 4px 5px;
            border: 1px solid #dce7e1;
            border-radius: 5px;
            background: #f7faf8;
            text-align: right;
            line-height: 1.55;
            font-size: 9px;
        }

        .receipt-title {
            margin: 2mm 0;
            text-align: center;
            color: #176b45;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .13em;
        }

        .details {
            display: grid;
            grid-template-columns: 1fr;
            gap: 3px;
            margin-bottom: 2mm;
            padding: 6px;
            border: 1px solid #e1ebe5;
            border-left: 3px solid #238457;
            border-radius: 4px;
            background: #f8fbf9;
        }

        .detail { display: flex; gap: 4px; min-width: 0; }
        .detail .label { flex: 0 0 auto; font-weight: 700; }
        .detail .value { overflow-wrap: anywhere; }

        table { width: 100%; border-collapse: collapse; }
        .items th, .items td {
            padding: 4px 2px;
            border-bottom: 1px solid #dce5df;
        }

        .items th {
            border-top: 1px solid #cedbd3;
            background: #edf5f0;
            color: #244b38;
            font-weight: 700;
            text-align: left;
        }

        .items tbody tr:nth-child(even) { background: #fafcfb; }
        .items .serial { width: 8%; text-align: center; }
        .items .medicine { width: 38%; }
        .items .quantity { width: 12%; text-align: center; }
        .items .rate, .items .amount { text-align: right; white-space: nowrap; }
        .totals { width: 100%; margin: 2mm 0 0 auto; }
        .totals td { padding: 3px; border-bottom: 1px solid #e1e8e3; }
        .totals td:last-child { text-align: right; white-space: nowrap; }
        .totals .grand {
            border-top: 1px solid #83a893;
            background: #f2f8f4;
            color: #145d3b;
            font-size: 12px;
            font-weight: 700;
        }

        .payment-status {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-top: 3mm;
            padding: 8px 10px;
            border: 1px solid #e1ebe5;
            border-radius: 5px;
            background: #f8fbf9;
        }

        .status-badge {
            padding: 5px 11px;
            border-radius: 3px;
            color: #fff;
            background: {{ $currentDue > 0 ? '#df4141' : '#23945a' }};
            font-size: 12px;
            letter-spacing: .08em;
            font-weight: 700;
        }

        .balance { text-align: right; line-height: 1.7; }
        .balance strong { color: #145d3b; font-size: 13px; }
        .note { margin-top: 3mm; overflow-wrap: anywhere; }
        .thank-you { margin: 4mm 0 0; text-align: center; color: #526b5e; }
        .footer {
            margin-top: 4mm;
            padding-top: 2mm;
            border-top: 1px solid #dce7e1;
            text-align: center;
        }

        .footer img { display: block; width: 100%; max-width: 100%; max-height: {{ max(16, (int) ($footerHeight ?? 70)) }}px; margin: 0 auto 2mm; object-fit: contain; }
        .footer-content { color: #52616b; font-size: {{ max(6, min(30, (int) ($invoiceDesign?->footer_font_size ?? 10))) }}px; }

        @media print {
            html, body { width: auto; margin: 0; padding: 0; background: #fff; }
            .toolbar { display: none; }
            .paper {
                width: 100%;
                max-width: none;
                min-height: 0;
                margin: 0;
                padding: 0 3mm 3mm;
                border-top-width: 2px;
                box-shadow: none;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .items tr, .totals tr, .details, .payment-status { break-inside: avoid; page-break-inside: avoid; }
            .footer { break-inside: avoid; page-break-inside: avoid; }
        }

        @media screen and (max-width: 640px) {
            body { padding: 8px; }
            .paper { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print Invoice</button>
    </div>

    <main class="paper">
        @includeIf('prints.partials._header', [
            'module' => 'pharmacy',
            'invoiceDesign' => $invoiceDesign,
            'headerImage' => $invoiceDesign?->header_photo_url ?? '',
            'headerHeight' => $headerHeight,
            'footerHeight' => $footerHeight,
            'showHeaderFooter' => true,
            'allowInvoiceDesignFallback' => false,
        ])

        <header class="identity">
            <div>
                <h1>Pharmacy Department</h1>
            </div>
            <div class="invoice-meta">
                <div><strong>Bill No:</strong> {{ $pharmacyBill->bill_no }}</div>
                <div><strong>Pharmacy No:</strong> {{ $pharmacyBill->pharmacy_no }}</div>
                <div><strong>Date:</strong> {{ $pharmacyBill->date }}</div>
                @if($pharmacyTaxNumber !== '')
                    <div><strong>VAT/Mushak No.:</strong> {{ $pharmacyTaxNumber }}</div>
                @endif
            </div>
        </header>

        <div class="receipt-title">MONEY RECEIPT</div>

        <section class="details">
            <div class="detail">
                <span class="label">Patient:</span>
                <span class="value">{{ $patientName }}</span>
            </div>
            <div class="detail">
                <span class="label">Payment:</span>
                <span class="value">{{ $pharmacyBill->payment_mode ?: 'N/A' }}</span>
            </div>
        </section>

        <table class="items">
            <thead>
                <tr>
                    <th class="serial">SL</th>
                    <th class="medicine">Medicine</th>
                    <th class="quantity">Qty</th>
                    <th class="rate">Price (Tk)</th>
                    <th class="amount">Total (Tk)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $index => $product)
                    <tr>
                        <td class="serial">{{ $index + 1 }}</td>
                        <td>{{ $product['medicine_name'] ?? $product['productName'] ?? 'Medicine' }}</td>
                        <td class="quantity">{{ $product['quantity'] ?? 0 }}</td>
                        <td class="rate">{{ number_format((float) ($product['unit_price'] ?? $product['rate'] ?? 0), 2) }}</td>
                        <td class="amount">{{ number_format((float) ($product['net_amount'] ?? $product['total_amount'] ?? $product['amount'] ?? 0), 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No medicine items found.</td></tr>
                @endforelse
            </tbody>
        </table>

        <table class="totals">
            <tbody>
                <tr><td>Subtotal</td><td>{{ number_format((float) $pharmacyBill->subtotal, 2) }}</td></tr>
                @if((float) $pharmacyBill->discount_amount > 0)
                    <tr><td>Discount</td><td>- {{ number_format((float) $pharmacyBill->discount_amount, 2) }}</td></tr>
                @endif
                @if((float) $pharmacyBill->extra_discount > 0)
                    <tr><td>Extra Discount</td><td>- {{ number_format((float) $pharmacyBill->extra_discount, 2) }}</td></tr>
                @endif
                @if((float) $pharmacyBill->vat_amount > 0)
                    <tr><td>VAT</td><td>{{ number_format((float) $pharmacyBill->vat_amount, 2) }}</td></tr>
                @endif
                <tr class="grand"><td>This Invoice</td><td>{{ number_format((float) $pharmacyBill->net_amount, 2) }}</td></tr>
            </tbody>
        </table>

        <section class="payment-status">
            <span class="status-badge">{{ $currentDue > 0 ? 'DUE' : 'PAID' }}</span>
            <div class="balance">
                @if($pharmacyBill->ipd_patient_id)
                    <div>Previous Medicine Due: Tk {{ number_format($previousDue, 2) }}</div>
                @endif
                <div>Paid on This Invoice: Tk {{ number_format($currentPaid, 2) }}</div>
                <div><strong>Total Medicine Due: Tk {{ number_format($currentDue, 2) }}</strong></div>
            </div>
        </section>

        @if($pharmacyBill->note)
            <div class="note"><strong>Note:</strong> {{ $pharmacyBill->note }}</div>
        @endif

        <p class="thank-you">Thank you for your purchase.</p>

        @if(($invoiceDesign?->active_pad_type ?? 'header') !== 'institution' && ($invoiceDesign?->footer_photo_url || $invoiceDesign?->footer_content))
            <footer class="footer">
                @if($invoiceDesign?->footer_photo_url)
                    <img src="{{ $invoiceDesign->footer_photo_url }}" alt="Pharmacy footer">
                @endif
                @if($invoiceDesign?->footer_content)
                    <div class="footer-content">{{ strip_tags($invoiceDesign->footer_content) }}</div>
                @endif
            </footer>
        @endif
    </main>
    @if($autoPrint)
        <script>
            window.addEventListener('load', function () {
                window.setTimeout(function () { window.print(); }, 500);
            });
        </script>
    @endif
</body>
</html>
