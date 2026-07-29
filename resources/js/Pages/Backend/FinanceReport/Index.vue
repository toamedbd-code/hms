<script setup>
import { ref, watch, computed } from "vue";
import { router, usePage } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import jsPDF from 'jspdf';
import html2canvas from 'html2canvas';
import { saveAs } from 'file-saver';
import * as XLSX from 'xlsx';

const page = usePage();

const props = defineProps({
    reportData: {
        type: Array,
        default: () => []
    },
    reportType: {
        type: String,
        default: 'daily-transaction'
    },
    dateFrom: {
        type: String,
        default: ''
    },
    dateTo: {
        type: String,
        default: ''
    },
    footerTotals: {
        type: Object,
        default: () => ({})
    },
    tableHeaders: {
        type: Array,
        default: () => []
    },
    dataFields: {
        type: Array,
        default: () => []
    }
});

const selectedReport = ref(props.reportType || 'daily-transaction');
const dateFrom = ref(props.dateFrom || '');
const dateTo = ref(props.dateTo || '');
const isLoading = ref(false);
const pdfOpening = ref(false);

const reports = [
    { id: 'daily-transaction', label: 'Daily Transaction Report' },
    { id: 'all-transaction', label: 'All Transaction Report' },
    { id: 'pharmacy-transaction', label: 'Pharmacy Transaction Report' },
    { id: 'appointment-transaction', label: 'Appointment Transaction Report' },
    { id: 'opd-transaction', label: 'OPD Transaction Report' },
    { id: 'ipd-transaction', label: 'IPD Transaction Report' },
    { id: 'income', label: 'Income Report' },
    { id: 'expense', label: 'Expense Report' },
    { id: 'referral', label: 'Referral Report' },
    { id: 'pending-transaction', label: 'Pending Transaction Report' },
];

const selectReport = (reportId) => {
    selectedReport.value = reportId;
};

const handleSearch = () => {
    if (!dateFrom.value || !dateTo.value) {
        alert('Please select both date from and date to');
        return;
    }

    router.get('/finance/report', {
        report_type: selectedReport.value,
        date_from: dateFrom.value,
        date_to: dateTo.value,
    }, {
        preserveState: false,
        preserveScroll: false
    });
};

const getReportTitle = () => {
    const report = reports.find(r => r.id === selectedReport.value);
    return report ? report.label : '';
};

const hasReportData = computed(() => {
    return props.reportData && props.reportData.length > 0;
});

const formatCurrency = (amount) => {
    const num = Number(amount || 0);
    return `৳${num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

const formatDate = (dateStr) => {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
};

const getCurrentDateTime = () => {
    const now = new Date();
    const day = String(now.getDate()).padStart(2, '0');
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const year = now.getFullYear();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    return `${day}/${month}/${year} ${hours}:${minutes}`;
};

const goBack = () => {
    if (window.history.length > 1) {
        window.history.back();
        return;
    }
    router.visit(route('backend.dashboard'));
};

// Open PDF in a new tab like the report generator flow
const downloadPDF = async () => {
    if (!dateFrom.value || !dateTo.value) {
        alert('Please select both date from and date to');
        return;
    }

    if (pdfOpening.value) return;
    pdfOpening.value = true;
    isLoading.value = true;

    const params = new URLSearchParams({
        report_type: selectedReport.value,
        date_from: dateFrom.value,
        date_to: dateTo.value,
        inline: '1',
    });

    let pdfUrl = '/finance/report/download-pdf';
    if (typeof route === 'function') {
        pdfUrl = `${route('backend.finance.report.pdf')}?${params.toString()}`;
    } else if (typeof window !== 'undefined' && typeof window.route === 'function') {
        pdfUrl = `${window.route('backend.finance.report.pdf')}?${params.toString()}`;
    } else {
        pdfUrl = `/finance/report/download-pdf?${params.toString()}`;
    }

    try {
        // Reuse a module-scoped reference to avoid opening duplicate tabs and
        // close any intermediate blank window if created by the browser.
        const winName = 'financeReportPopup';
        // store reference on window to persist across component reloads
        if (!window.__financeReportWin) window.__financeReportWin = null;

        try {
            // If we already have a reference and it's open, reuse it
            if (window.__financeReportWin && !window.__financeReportWin.closed) {
                try {
                    window.__financeReportWin.location.href = pdfUrl;
                    window.__financeReportWin.focus();
                } catch (e) {
                    // If navigating existing window fails, null it to open anew
                    window.__financeReportWin = null;
                }
            }

            if (!window.__financeReportWin) {
                // Try to get a handle to a window with the name without creating a blank page
                let probe = null;
                try {
                    probe = window.open('', winName);
                } catch (e) {
                    probe = null;
                }

                // If probe exists and is not an about:blank placeholder, reuse it
                if (probe && probe.location && probe.location.href && probe.location.href !== 'about:blank') {
                    try {
                        probe.location.href = pdfUrl;
                        probe.focus();
                        window.__financeReportWin = probe;
                    } catch (e) {
                        // fallthrough to open normally
                        try { probe.close(); } catch (ee) { /* ignore */ }
                        window.__financeReportWin = window.open(pdfUrl, winName, 'noopener,noreferrer');
                    }
                } else {
                    // If probe is a blank window, close it to avoid leftover blank tab
                    if (probe && probe.location && probe.location.href === 'about:blank') {
                        try { probe.close(); } catch (e) { /* ignore */ }
                    }

                    // Open the PDF directly in a named window
                    window.__financeReportWin = window.open(pdfUrl, winName, 'noopener,noreferrer');
                    try { if (window.__financeReportWin) window.__financeReportWin.focus(); } catch (e) { /* ignore */ }
                }
            }
        } catch (openErr) {
            console.warn('Primary open method failed, attempting anchor fallback', openErr);
            try {
                const a = document.createElement('a');
                a.href = pdfUrl;
                a.target = winName;
                a.rel = 'noopener noreferrer';
                a.style.display = 'none';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            } catch (anchorErr) {
                console.warn('Anchor fallback failed, attempting fetch-and-open fallback', anchorErr);
                try {
                    const resp = await fetch(pdfUrl, { credentials: 'same-origin' });
                    if (!resp.ok) throw new Error('Network response was not ok');
                    const blob = await resp.blob();
                    const blobUrl = URL.createObjectURL(blob);
                    const bw = window.open(blobUrl, winName);
                    if (!bw) {
                        const dl = document.createElement('a');
                        dl.href = blobUrl;
                        dl.download = `${selectedReport.value || 'report'}.pdf`;
                        document.body.appendChild(dl);
                        dl.click();
                        document.body.removeChild(dl);
                    } else {
                        window.__financeReportWin = bw;
                        try { window.__financeReportWin.focus(); } catch (e) { /* ignore */ }
                    }
                } catch (fetchErr) {
                    console.error('Final fetch fallback failed:', fetchErr);
                    alert('Unable to open PDF. Please try downloading from the server.');
                }
            }
        }
    } catch (error) {
        console.error('Error opening report URL:', error);
    } finally {
        isLoading.value = false;
        setTimeout(() => {
            pdfOpening.value = false;
        }, 800);
    }
};

// Helper function to wrap text based on column width
const wrapText = (pdf, text, maxWidth, fontSize) => {
    if (!text) return '';

    const words = text.toString().split(' ');
    const lines = [];
    let currentLine = words[0];

    for (let i = 1; i < words.length; i++) {
        const word = words[i];
        const testLine = currentLine + ' ' + word;
        const testWidth = pdf.getStringUnitWidth(testLine) * fontSize / pdf.internal.scaleFactor;

        if (testWidth <= maxWidth) {
            currentLine = testLine;
        } else {
            lines.push(currentLine);
            currentLine = word;
        }
    }
    lines.push(currentLine);

    return lines.join('\n');
};

// Improved column width calculation
const calculateOptimizedColumnWidths = (headers, data, dataFields, tableWidth) => {
    const minColWidth = 15; // Minimum column width
    const maxColWidth = 40; // Maximum column width (reduced for portrait mode)
    const padding = 4; // Padding for text

    // Calculate content-based widths
    const contentWidths = headers.map((header, index) => {
        let maxContentWidth = header.length * 1.5; // Base on header length

        // Check data content
        data.forEach(row => {
            let value = row[dataFields[index]?.fieldName];

            if (value === undefined || value === null) return;

            // Clean HTML
            if (typeof value === 'string' && value.includes('<')) {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = value;
                value = tempDiv.textContent || tempDiv.innerText || '';
            }

            // Remove currency symbol
            if (typeof value === 'string' && value.startsWith('৳')) {
                value = value.replace('৳', '');
            }

            const valueLength = value.toString().length;
            maxContentWidth = Math.max(maxContentWidth, valueLength * 1.2);
        });

        // Apply min/max constraints
        return Math.min(Math.max(maxContentWidth + padding, minColWidth), maxColWidth);
    });

    // Calculate total width
    const totalContentWidth = contentWidths.reduce((sum, width) => sum + width, 0);

    // If content fits, use content widths
    if (totalContentWidth <= tableWidth) {
        return contentWidths;
    }

    // Otherwise, scale down proportionally
    const scaleFactor = tableWidth / totalContentWidth;
    return contentWidths.map(width => Math.max(width * scaleFactor, minColWidth));
};

// Fixed Excel download function with proper title alignment
const downloadExcelWithRawData = () => {
    if (!hasReportData.value) {
        alert('No data available to download');
        return;
    }

    try {
        // Create workbook
        const wb = XLSX.utils.book_new();

        // Prepare data for Excel
        const excelData = [];

        // Add title rows - proper alignment using merged cells
        excelData.push([getReportTitle().toUpperCase()]);
        excelData.push([`Generated on: ${getCurrentDateTime()}`]);
        excelData.push([`Date Range: ${formatDate(dateFrom.value)} to ${formatDate(dateTo.value)}`]);
        excelData.push([]); // Empty row for spacing
        excelData.push([`Total Records: ${props.reportData.length}`]);
        if (props.footerTotals.total_transactions) {
            excelData.push([`Total Transactions: ${props.footerTotals.total_transactions}`]);
        }
        excelData.push([]); // Empty row for spacing

        // Record header start row index then add table headers
        const totalCols = visibleTableHeaders.value.length;
        const headerStartIndex = excelData.length;
        excelData.push(visibleTableHeaders.value);

        // Add data rows
        props.reportData.forEach(row => {
            const dataRow = [];

            visibleDataFields.value.forEach((field) => {
                let value = row[field.fieldName];

                // Use raw numeric values where available
                const rawFieldName = `${field.fieldName}_raw`;
                if (row[rawFieldName] !== undefined) {
                    value = row[rawFieldName];
                }

                // Clean HTML from status badges
                if (typeof value === 'string' && value.includes('<')) {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = value;
                    value = tempDiv.textContent || tempDiv.innerText || '';
                }

                // Remove currency symbol for numeric processing
                if (typeof value === 'string' && value.startsWith('৳')) {
                    value = value.replace('৳', '').replace(/,/g, '');
                }

                dataRow.push(value);
            });

            excelData.push(dataRow);
        });

        // Add footer totals if available: place GRAND TOTAL row directly under table columns
        if (showFooterTotals.value) {
            // Empty row spacing
            excelData.push([]);

            const summaryRow = [];
            visibleTableHeaders.value.forEach((header, index) => {
                if (index < getFooterColumnSpan.value) {
                    summaryRow.push(index === 0 ? 'GRAND TOTAL' : '');
                } else {
                    const fieldName = visibleDataFields.value[index]?.fieldName;
                    const totalKey = fieldName ? fieldName.replace('_raw', '') : null;
                    if (totalKey && props.footerTotals[totalKey] !== undefined) {
                        // For numeric totals, use formatted currency string where appropriate
                        const val = props.footerTotals[totalKey];
                        summaryRow.push(typeof val === 'number' ? val : val);
                    } else {
                        summaryRow.push('');
                    }
                }
            });
            excelData.push(summaryRow);
        }

        // Create worksheet from the 2D array
        const ws = XLSX.utils.aoa_to_sheet(excelData);

        // Set column widths
        const colWidths = visibleTableHeaders.value.map(() => ({ width: 15 }));
        ws['!cols'] = colWidths;

        // Define merge ranges for title alignment using actual row indexes
        const mergeRanges = [];
        // Main title at row 0
        mergeRanges.push({ s: { r: 0, c: 0 }, e: { r: 0, c: totalCols - 1 } });
        // Generated date row 1
        mergeRanges.push({ s: { r: 1, c: 0 }, e: { r: 1, c: totalCols - 1 } });
        // Date range row 2
        mergeRanges.push({ s: { r: 2, c: 0 }, e: { r: 2, c: totalCols - 1 } });
        // Total records row 4 (if present)
        mergeRanges.push({ s: { r: 4, c: 0 }, e: { r: 4, c: totalCols - 1 } });
        if (props.footerTotals.total_transactions) {
            mergeRanges.push({ s: { r: 5, c: 0 }, e: { r: 5, c: totalCols - 1 } });
        }

        ws['!merges'] = mergeRanges;

        // Apply styling
        const range = XLSX.utils.decode_range(ws['!ref']);

        // Style title rows with center alignment (only rows that exist)
        const titleRows = [0, 1, 2, 4];
        if (props.footerTotals.total_transactions) titleRows.push(5);
        titleRows.forEach(r => {
            for (let c = 0; c < totalCols; c++) {
                const cell = XLSX.utils.encode_cell({ r, c });
                if (ws[cell]) {
                    ws[cell].s = {
                        font: { bold: true, sz: r === 0 ? 16 : 12 },
                        alignment: { horizontal: 'center', vertical: 'center' }
                    };
                }
            }
        });

        // Style header row (the actual headerStartIndex)
        for (let C = range.s.c; C <= range.e.c; ++C) {
            const cell = XLSX.utils.encode_cell({ r: headerStartIndex, c: C });
            if (ws[cell]) {
                ws[cell].s = {
                    font: { bold: true, color: { rgb: "FFFFFF" } },
                    fill: { fgColor: { rgb: "4F81BD" } },
                    alignment: { horizontal: 'center', vertical: 'center' }
                };
            }
        }

        XLSX.utils.book_append_sheet(wb, ws, 'Finance Report');

        const fileName = `${selectedReport.value}_report_${dateFrom.value}_to_${dateTo.value}.xlsx`;
        XLSX.writeFile(wb, fileName);
    } catch (error) {
        console.error('Error generating Excel:', error);
        alert('Error generating Excel file. Please try again.');
    }
};

watch(() => props.reportType, (newVal) => {
    if (newVal) selectedReport.value = newVal;
});

watch(() => props.dateFrom, (newVal) => {
    if (newVal) dateFrom.value = newVal;
});

watch(() => props.dateTo, (newVal) => {
    if (newVal) dateTo.value = newVal;
});

const showFooterTotals = computed(() => {
    return hasReportData.value && Object.keys(props.footerTotals).length > 0;
});

const showsRefundColumn = computed(() => {
    if (!hasReportData.value) return false;
    return props.reportData.some(row => Number(row.total_return_amount_raw ?? row.total_return_amount ?? 0) > 0)
        || Number(props.footerTotals.total_return_amount ?? 0) > 0;
});

const visibleTableHeaders = computed(() => {
    if (showsRefundColumn.value) return props.tableHeaders;
    return props.tableHeaders.filter(header => header !== 'Refund' && header !== 'Total Refund');
});

const visibleDataFields = computed(() => {
    return props.dataFields.filter(field => field.fieldName !== 'total_return_amount' || showsRefundColumn.value);
});

const getFooterColumnSpan = computed(() => {
    const spans = {
        'daily-transaction': 2,
        'all-transaction': 2,
        'pharmacy-transaction': 6,
        'appointment-transaction': 6,
        'opd-transaction': 6,
        'ipd-transaction': 6,
        'income': 2,
        'expense': 3,
        'referral': 4,
        'pending-transaction': 6,
    };
    return spans[props.reportType] || 1;
});
</script>

<template>
    <BackendLayout>
        <div class="fixed-report-top container mx-auto px-3 sm:px-4 py-4 sm:py-6">
            <!-- Header -->
            <div class="mb-4 sm:mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-xl sm:text-2xl font-semibold text-gray-800">Finance Reports</h1>
                    <p class="text-sm text-gray-600 mt-1">Generate and view comprehensive financial reports</p>
                </div>
                <button @click="goBack"
                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white bg-red-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-red-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-red-700">
                    <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back
                </button>
            </div>

            <!-- Report Options Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 mb-4 sm:mb-6">
                <button type="button" v-for="report in reports" :key="report.id" @click="selectReport(report.id)" :class="[
                    'flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 sm:py-3 rounded-lg text-left transition-all duration-200 text-sm sm:text-base',
                    selectedReport === report.id
                        ? 'bg-blue-100 text-blue-900 shadow-sm'
                        : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-200'
                ]">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span class="font-medium truncate">{{ report.label }}</span>
                </button>
            </div>

            <!-- Selected Report Section -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <!-- Report Title -->
                <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 bg-gray-50">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <h2 class="text-lg sm:text-xl font-semibold text-gray-800">
                            {{ getReportTitle() }}
                        </h2>

                        <!-- Download Buttons -->
                        <!-- Download Buttons -->
                        <div v-if="hasReportData" class="flex gap-2">
                            <button type="button" @click.prevent="downloadPDF" :disabled="isLoading"
                                class="flex items-center gap-2 px-3 sm:px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                {{ isLoading ? 'Opening Report...' : 'Report' }}
                            </button>
                            <button type="button" @click="downloadExcelWithRawData"
                                class="flex items-center gap-2 px-3 sm:px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors text-sm font-medium">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Excel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Date Range Form -->
                <div class="px-4 sm:px-6 py-4 sm:py-6">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
                        <!-- Date From -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Date From <span class="text-red-500">*</span>
                            </label>
                            <input v-model="dateFrom" type="date"
                                class="w-full px-3 sm:px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-sm sm:text-base"
                                required />
                        </div>

                        <!-- Date To -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Date To <span class="text-red-500">*</span>
                            </label>
                            <input v-model="dateTo" type="date"
                                class="w-full px-3 sm:px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-sm sm:text-base"
                                required />
                        </div>

                        <!-- Search Button -->
                        <div class="flex items-end">
                            <button type="button" @click="handleSearch"
                                class="w-full lg:w-auto flex items-center justify-center gap-2 px-4 sm:px-6 py-2.5 bg-gray-700 hover:bg-gray-800 text-white font-medium rounded-lg transition-colors duration-200 shadow-sm text-sm sm:text-base disabled:opacity-50 disabled:cursor-not-allowed"
                                :disabled="!dateFrom || !dateTo">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                Generate Report
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Results Area -->
                <div class="px-2 sm:px-4 py-4 bg-gray-50 border-t border-gray-200">
                    <div v-if="hasReportData">
                        <!-- Summary Stats -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 px-2">
                            <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                                <div class="text-sm text-gray-600 mb-1">Total Records</div>
                                <div class="text-2xl font-bold text-gray-800">{{ reportData.length }}</div>
                            </div>
                            <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                                <div class="text-sm text-gray-600 mb-1">Date Range</div>
                                <div class="text-sm font-semibold text-gray-800">
                                    {{ formatDate(dateFrom) }} - {{ formatDate(dateTo) }}
                                </div>
                            </div>
                            <div v-if="footerTotals.total_transactions"
                                class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                                <div class="text-sm text-gray-600 mb-1">Total Transactions</div>
                                <div class="text-2xl font-bold text-gray-800">{{ footerTotals.total_transactions }}
                                </div>
                            </div>
                            <div v-if="footerTotals.total_bills"
                                class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                                <div class="text-sm text-gray-600 mb-1">Total Bills</div>
                                <div class="text-2xl font-bold text-gray-800">{{ footerTotals.total_bills }}</div>
                            </div>
                        </div>

                        <!-- Table with Footer -->
                        <div id="report-table"
                            class="overflow-x-auto bg-white rounded-lg shadow border border-gray-200">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th v-for="(header, index) in visibleTableHeaders" :key="index" :class="[
                                            'px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider',
                                            visibleDataFields[index]?.class || ''
                                        ]">
                                            {{ header }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="(row, rowIndex) in reportData" :key="rowIndex"
                                        :class="rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50'">
                                        <td v-for="(field, fieldIndex) in visibleDataFields" :key="fieldIndex"
                                            :class="['px-4 py-3 whitespace-nowrap text-sm', field.class]"
                                            v-html="row[field.fieldName]"></td>
                                    </tr>
                                </tbody>

                                <!-- Table Footer with Totals -->
                                <tfoot v-if="showFooterTotals" class="finance-total-footer">
                                    <tr>
                                        <td :colspan="getFooterColumnSpan" class="finance-total-label">
                                            Grand Total
                                        </td>

                                        <!-- Daily Transaction Footer -->
                                        <template v-if="reportType === 'daily-transaction'">
                                            <td class="finance-total-cell text-center">
                                                {{ footerTotals.total_transactions || 0 }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_amount || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_discount || 0) }}
                                            </td>
                                            <td v-if="showsRefundColumn" class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_return_amount || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.amount_after_discount || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.cash_amount || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.non_cash_amount || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_commission || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_paid || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_due || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_due_collected || 0) }}
                                            </td>
                                        </template>

                                        <!-- All Transaction Footer (same structure as Daily) -->
                                        <template v-else-if="reportType === 'all-transaction'">
                                            <td class="finance-total-cell text-center">
                                                {{ footerTotals.total_transactions || 0 }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_amount || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_discount || 0) }}
                                            </td>
                                            <td v-if="showsRefundColumn" class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_return_amount || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.amount_after_discount || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.cash_amount || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.non_cash_amount || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_commission || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_paid || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_due || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_due_collected || 0) }}
                                            </td>
                                        </template>

                                        <!-- Appointment / OPD / IPD Transaction Footer -->
                                        <template v-else-if="['appointment-transaction', 'opd-transaction', 'ipd-transaction'].includes(reportType)">
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.discount || 0) }}
                                            </td>
                                            <td v-if="showsRefundColumn" class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_return_amount || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.payable_amount || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.paid_amt || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.due_amount || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_due_collected || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-center">
                                                —
                                            </td>
                                        </template>

                                        <!-- Pharmacy Transaction Footer -->
                                        <template v-else-if="reportType === 'pharmacy-transaction'">
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.discount || 0) }}
                                            </td>
                                            <td v-if="showsRefundColumn" class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_return_amount || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.payable_amount || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.paid_amt || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.due_amount || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_due_collected || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-center">
                                                —
                                            </td>
                                        </template>

                                        <!-- Income Footer -->
                                        <template v-else-if="reportType === 'income'">
                                            <td class="finance-total-cell text-center">
                                                {{ footerTotals.total_bills || 0 }}
                                            </td>
                                            <td class="finance-total-cell">
                                                —
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_bill || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_discount || 0) }}
                                            </td>
                                            <td v-if="showsRefundColumn" class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_return_amount || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_income || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.net_income || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_due_collected || 0) }}
                                            </td>
                                        </template>

                                        <!-- Expense Footer -->
                                        <template v-else-if="reportType === 'expense'">
                                            <td class="finance-total-cell text-center">
                                                {{ footerTotals.total_transactions || 0 }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_expense || 0) }}
                                            </td>
                                            <td v-if="showsRefundColumn" class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_return_amount || 0) }}
                                            </td>
                                        </template>

                                        <!-- Referral Footer -->
                                        <template v-else-if="reportType === 'referral'">
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_bill_amount || 0) }}
                                            </td>
                                            <td v-if="showsRefundColumn" class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_return_amount || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_commission || 0) }}
                                            </td>
                                        </template>

                                        <!-- Pending Transaction Footer -->
                                        <template v-else-if="reportType === 'pending-transaction'">
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.discount || 0) }}
                                            </td>
                                            <td v-if="showsRefundColumn" class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_return_amount || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.payable_amount || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.paid_amt || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.due_amount || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-right">
                                                {{ formatCurrency(footerTotals.total_due_collected || 0) }}
                                            </td>
                                            <td class="finance-total-cell text-center">
                                                —
                                            </td>
                                        </template>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="text-center text-gray-500 py-8 sm:py-12">
                        <svg class="w-12 h-12 sm:w-16 sm:h-16 mx-auto mb-4 text-gray-400" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p class="text-base sm:text-lg font-medium">No data to display</p>
                        <p class="text-sm mt-1">Select date range and click "Generate Report" to view data</p>
                    </div>
                </div>
            </div>
        </div>
    </BackendLayout>
</template>

<style scoped>
.fixed-report-top {
    --report-page-top-margin: 48px;
}
</style>

<style scoped>
/* Custom responsive styles */
@media (max-width: 640px) {
    .container {
        padding-left: 1rem;
        padding-right: 1rem;
    }
}

/* Ensure table is properly styled for PDF export */
#report-table {
    font-family: Arial, sans-serif;
}

#report-table table {
    border-collapse: collapse;
    width: 100%;
}

#report-table th,
#report-table td {
    border: 1px solid #e5e7eb;
    padding: 8px 12px;
}

#report-table th {
    background-color: #f9fafb;
    font-weight: 600;
}

.finance-total-footer {
    background: linear-gradient(90deg, #0f172a 0%, #1f2937 50%, #0f172a 100%);
}

.finance-total-label {
    padding: 0.9rem 1rem;
    white-space: nowrap;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #fef3c7;
    border-top: 2px solid #334155;
}

.finance-total-cell {
    padding: 0.9rem 1rem;
    white-space: nowrap;
    font-size: 0.82rem;
    font-weight: 700;
    color: #f8fafc;
    border-top: 2px solid #334155;
}
</style>