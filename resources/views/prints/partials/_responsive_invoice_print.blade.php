<style>
    @page {
        size: auto;
    }

    @media print {
        html,
        body {
            width: auto !important;
            min-width: 0 !important;
            max-width: 100% !important;
            height: auto !important;
            overflow: visible !important;
        }

        body {
            margin-left: 0 !important;
            margin-right: 0 !important;
        }

        .content,
        .content-section,
        .patient-info,
        .payment-section,
        .invoice-container {
            box-sizing: border-box !important;
            width: 100% !important;
            min-width: 0 !important;
            max-width: 100% !important;
            margin-left: auto !important;
            margin-right: auto !important;
        }

        .patient-info-table,
        .info-table,
        .payment-table,
        .payment-summary,
        .items-table,
        .summary-table,
        .invoice-heading,
        .patient-detail-grid,
        .appointment-detail-grid {
            box-sizing: border-box !important;
            width: 100% !important;
            max-width: 100% !important;
            table-layout: fixed !important;
        }

        th,
        td {
            min-width: 0 !important;
            max-width: 100% !important;
            white-space: normal !important;
            word-break: normal !important;
            overflow-wrap: anywhere !important;
        }

        .totals-table .due-collect-line,
        .summary-table .due-collect-line {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 8px !important;
            white-space: nowrap !important;
            word-break: normal !important;
            overflow-wrap: normal !important;
            font-size: inherit !important;
        }

        .totals-table .due-collect-label,
        .totals-table .due-collect-amount,
        .summary-table .due-collect-label,
        .summary-table .due-collect-amount {
            white-space: nowrap !important;
            overflow-wrap: normal !important;
        }

        img,
        canvas,
        svg {
            max-width: 100% !important;
        }

        .header-image,
        .footer-image,
        .print-shared-header-image {
            max-width: 100% !important;
            object-fit: contain !important;
        }

        .print-full-page-institution-pad {
            width: 100vw !important;
            height: 100vh !important;
            max-width: none !important;
            object-fit: fill !important;
        }

        .content,
        .content-section,
        .patient-info,
        .payment-section,
        table,
        tr,
        td,
        th {
            break-inside: auto;
        }

        .items-table tr,
        .payment-table tr,
        .payment-summary tr,
        .summary-table tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }
    }
</style>
