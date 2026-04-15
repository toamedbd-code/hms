<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>IPD Final Discharge Invoice</title>

    <style>
        /* =========================
           DOMPDF A4 Print Core CSS
           ========================= */

        @page {
            size: A4 portrait;
            /* Reserve space for fixed header/footer */
            margin: 32mm 10mm 18mm 10mm; /* top right bottom left */
        }

        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 9.5pt;
            line-height: 1.15;
            color: #111;
            margin: 0;
            padding: 0;
        }

        * { box-sizing: border-box; }

        /* Fixed header/footer (repeat every page) */
        .header {
            position: fixed;
            top: -28mm;
            left: 0;
            right: 0;
            height: 28mm;
        }

        .footer {
            position: fixed;
            bottom: -14mm;
            left: 0;
            right: 0;
            height: 14mm;
        }

        /* Typography */
        .h1 { font-size: 12pt; font-weight: 700; }
        .h2 { font-size: 10.5pt; font-weight: 700; }
        .small { font-size: 8.5pt; }
        .muted { color: #555; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .nowrap { white-space: nowrap; }

        /* Boxes & separators */
        .box {
            border: 0.3mm solid #333;
            padding: 2mm;
        }

        .hr { border-top: 0.2mm solid #333; margin: 2mm 0; }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        /* Default table (bordered) */
        .tbl th, .tbl td {
            border: 0.2mm solid #333;
            padding: 1.2mm 1.4mm;
            vertical-align: top;
        }

        .tbl th { font-weight: 700; background: #f2f2f2; }

        /* Compact rows */
        .table-compact th, .table-compact td { padding: 1mm 1.2mm; }

        /* Plain tables (no borders) for header/footer blocks */
        .plain, .plain td, .plain th { border: none !important; }
        .plain td, .plain th { padding: 0.5mm 0.8mm; }

        /* Wrapping */
        .wrap {
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* Numbers */
        .num { text-align: right; }
        .center { text-align: center; }

        /* Pagination control */
        thead { display: table-header-group; } /* repeat table header on each page */
        tfoot { display: table-footer-group; }

        tr, td, th { page-break-inside: avoid; }
        .avoid-break { page-break-inside: avoid; }
        .page-break { page-break-before: always; }
    </style>
</head>

<body>
    @include('prints.ipd.partials._header', ['vm' => $vm])
    @include('prints.ipd.partials._footer', ['vm' => $vm])

    <main>
        @include('prints.ipd.partials._patient_payer', ['vm' => $vm])

        <div class="hr"></div>

        @include('prints.ipd.partials._dept_summary', ['vm' => $vm])

        <div class="hr"></div>

        @include('prints.ipd.partials._line_items', ['vm' => $vm])

        <div class="hr"></div>

        @include('prints.ipd.partials._totals_payments', ['vm' => $vm])
    </main>

    {{-- DOMPDF page numbering (most reliable) --}}
    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->get_font("DejaVu Sans", "normal");
            // A4 is ~595x842 pt. These coordinates place it near bottom-right inside margins.
            $pdf->page_text(470, 827, "Page {PAGE_NUM} of {PAGE_COUNT}", $font, 8, array(0,0,0));
        }
    </script>
</body>
</html>
