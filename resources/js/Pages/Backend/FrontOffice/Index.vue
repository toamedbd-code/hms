
<script setup>
import { computed, ref } from "vue";
import BackendLayout from '@/Layouts/BackendLayout.vue';
import BaseTable from '@/Components/BaseTable.vue';
import Pagination from '@/Components/Pagination.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import * as XLSX from 'xlsx';
import { displayResponse, warningMessage } from '@/responseMessage.js';

let props = defineProps({
    filters: Object,
});

const filters = ref({
    name: props.filters?.name ?? '',
    numOfData: props.filters?.numOfData ?? 10,
});

const applyFilter = () => {
    router.get(route('backend.frontoffice.index'), filters.value, { preserveState: true });
};

const page = usePage();
const fileInputRef = ref(null);
const printSectionRef = ref(null);
const isUploading = ref(false);

const userPermissions = computed(() => {
    const raw = page.props?.auth?.permissions ?? [];
    return Array.isArray(raw) ? raw : [];
});

const canCreateFrontOffice = computed(() => userPermissions.value.includes('frontoffice-create'));
const canImportFrontOffice = computed(() => userPermissions.value.includes('frontoffice-create'));
const hospitalName = computed(() => page.props?.webSetting?.company_name || 'Hospital');
const hospitalAddress = computed(() => page.props?.webSetting?.address || page.props?.webSetting?.report_title || '');

const visitorRows = computed(() => {
    const rowSource = page.props?.datas?.data ?? page.props?.datas ?? [];
    return Array.isArray(rowSource) ? rowSource : [];
});

const pad = (value) => String(value).padStart(2, '0');

const normalizeDate = (value) => {
    if (value === null || value === undefined || value === '') return '';

    if (typeof value === 'number') {
        const parsed = XLSX.SSF.parse_date_code(value);
        if (parsed) return `${parsed.y}-${pad(parsed.m)}-${pad(parsed.d)}`;
    }

    const parsedDate = new Date(value);
    if (!Number.isNaN(parsedDate.getTime())) {
        return `${parsedDate.getFullYear()}-${pad(parsedDate.getMonth() + 1)}-${pad(parsedDate.getDate())}`;
    }

    return String(value).trim();
};

const normalizeTime = (value) => {
    if (value === null || value === undefined || value === '') return '';

    if (typeof value === 'number') {
        const dayPortion = value % 1;
        const totalMinutes = Math.round(dayPortion * 24 * 60);
        const hours = Math.floor(totalMinutes / 60) % 24;
        const minutes = totalMinutes % 60;
        return `${pad(hours)}:${pad(minutes)}`;
    }

    const raw = String(value).trim();
    const hhmmMatch = raw.match(/^(\d{1,2}):(\d{2})/);
    if (hhmmMatch) {
        return `${pad(Number(hhmmMatch[1]))}:${pad(Number(hhmmMatch[2]))}`;
    }

    const parsedDate = new Date(`1970-01-01 ${raw}`);
    if (!Number.isNaN(parsedDate.getTime())) {
        return `${pad(parsedDate.getHours())}:${pad(parsedDate.getMinutes())}`;
    }

    return raw;
};

const extractValue = (row, candidates) => {
    const keys = Object.keys(row || {});
    for (const candidate of candidates) {
        const hit = keys.find((key) => key.toLowerCase().replace(/\s+/g, '_') === candidate);
        if (hit) return row[hit];
    }
    return '';
};

const mapImportRow = (row) => {
    return {
        name: String(extractValue(row, ['name', 'visitor_name', 'visitor'])).trim(),
        purpose: String(extractValue(row, ['purpose', 'visit_purpose'])).trim(),
        visit_to: String(extractValue(row, ['visit_to', 'visited_to', 'visited_person'])).trim(),
        phone: String(extractValue(row, ['phone', 'phone_number', 'mobile'])).trim(),
        date_in: normalizeDate(extractValue(row, ['date_in', 'date', 'visit_date'])),
        time_in: normalizeTime(extractValue(row, ['time_in', 'in_time', 'check_in'])),
        time_out: normalizeTime(extractValue(row, ['time_out', 'out_time', 'check_out'])),
    };
};

const printVisitors = () => {
        const printContents = printSectionRef.value?.innerHTML;
        if (!printContents) {
                warningMessage('No visitor table found to print.');
                return;
        }

        const printWindow = window.open('', '_blank', 'width=1200,height=800');
        if (!printWindow) {
                warningMessage('Popup blocked. Please allow popups for printing.');
                return;
        }

        const printableHospitalName = stripHtml(hospitalName.value || 'Hospital');
        const printableHospitalAddress = stripHtml(hospitalAddress.value || '');
        const generatedAt = formatNowForPrint();

        printWindow.document.write(`
            <html>
                <head>
                    <title>Visitor List Print</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 16px; color: #111827; }
                        .header { text-align: center; margin-bottom: 12px; }
                        .hospital-name { font-size: 20px; font-weight: 700; margin: 0; }
                        .hospital-address { font-size: 12px; margin: 3px 0 0 0; color: #374151; }
                        .meta { font-size: 12px; margin: 8px 0 0 0; color: #4b5563; }
                        table { width: 100%; border-collapse: collapse; font-size: 12px; }
                        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: center; }
                        th { background: #f3f4f6; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <p class="hospital-name">${printableHospitalName}</p>
                        ${printableHospitalAddress ? `<p class="hospital-address">${printableHospitalAddress}</p>` : ''}
                        <p class="meta">Visitor List | Generated: ${generatedAt}</p>
                    </div>
                    ${printContents}
                </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        printWindow.close();
};

const stripHtml = (value) => String(value ?? '').replace(/<[^>]*>/g, '').trim();

const formatNowForPrint = () => {
    const now = new Date();
    const day = pad(now.getDate());
    const month = pad(now.getMonth() + 1);
    const year = now.getFullYear();
    const hours = now.getHours();
    const minutes = now.getMinutes();
    const suffix = hours >= 12 ? 'PM' : 'AM';
    const hour12 = hours % 12 === 0 ? 12 : hours % 12;

    return `${day}-${month}-${year} ${pad(hour12)}:${pad(minutes)} ${suffix}`;
};

const toAmPmTime = (value) => {
        const raw = String(value ?? '').trim();
        if (!raw) return '';

        const match = raw.match(/^(\d{1,2}):(\d{2})(?::\d{2})?/);
        if (!match) return raw;

        let hours = Number(match[1]);
        const minutes = Number(match[2]);
        if (Number.isNaN(hours) || Number.isNaN(minutes)) return raw;

        const suffix = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        if (hours === 0) hours = 12;

        return `${pad(hours)}:${pad(minutes)} ${suffix}`;
};

const extractStatusText = (row) => {
        const statusText = row?.status_text ?? row?.status ?? '';
        return stripHtml(statusText);
};

const exportVisitorsExcel = () => {
    const headers = ['Name', 'Purpose', 'Visit To', 'Phone', 'Date In', 'Time In', 'Time Out', 'Status'];
    const body = visitorRows.value.map((row) => [
        stripHtml(row.name ?? ''),
        stripHtml(row.purpose ?? ''),
        stripHtml(row.visit_to ?? ''),
        stripHtml(row.phone ?? ''),
        stripHtml(row.date_in ?? ''),
        toAmPmTime(row.time_in),
        toAmPmTime(row.time_out),
        extractStatusText(row),
    ]);

    const sheet = XLSX.utils.aoa_to_sheet([headers, ...body]);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, sheet, 'Visitors');

    const now = new Date();
    const fileName = `visitor-list-${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}-${pad(now.getHours())}${pad(now.getMinutes())}.xlsx`;
    XLSX.writeFile(workbook, fileName);
};

const openFilePicker = () => {
    if (fileInputRef.value) {
        fileInputRef.value.click();
    }
};

const handleFileChange = async (event) => {
    const file = event.target.files?.[0] ?? null;

    if (!file) return;

    try {
        isUploading.value = true;

        const buffer = await file.arrayBuffer();
        const workbook = XLSX.read(buffer, { type: 'array', cellDates: true });
        const firstSheetName = workbook.SheetNames?.[0];

        if (!firstSheetName) {
            warningMessage('No worksheet found in uploaded file.');
            return;
        }

        const rows = XLSX.utils.sheet_to_json(workbook.Sheets[firstSheetName], {
            defval: '',
            raw: true,
        });

        const mappedRows = rows
            .map(mapImportRow)
            .filter((row) => row.name || row.purpose || row.visit_to || row.phone || row.date_in || row.time_in || row.time_out);

        if (!mappedRows.length) {
            warningMessage('Uploaded file has no usable row data.');
            return;
        }

        router.post(route('backend.frontoffice.import'), {
            rows: mappedRows,
        }, {
            preserveScroll: true,
            onSuccess: (response) => {
                displayResponse(response);
            },
            onError: () => {
                warningMessage('Visitor import failed. Please check your file format.');
            },
        });
    } catch (error) {
        warningMessage('Unable to read uploaded file. Please upload a valid Excel/CSV file.');
    } finally {
        isUploading.value = false;
        event.target.value = '';
    }
};
</script>

<template>
    <BackendLayout>
        <div class="w-full p-4 mt-3 duration-1000 ease-in-out bg-white rounded shadow-md shadow-gray-800/50 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between w-full gap-2 p-3 mb-3 text-gray-700 rounded shadow-md bg-gray-100 shadow-gray-800/20 dark:bg-gray-800 dark:text-gray-200">
                <div class="text-lg font-semibold">Visitor List Actions</div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="inline-flex items-center px-3 py-2 text-sm font-semibold text-white bg-slate-700 rounded hover:bg-slate-800" @click="printVisitors">
                        Print
                    </button>

                    <button type="button" class="inline-flex items-center px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700" @click="exportVisitorsExcel">
                        Excel
                    </button>

                    <a
                        class="inline-flex items-center px-3 py-2 text-sm font-semibold text-white bg-amber-600 rounded hover:bg-amber-700"
                        href="/sample/visitor_import_sample.csv"
                        download
                    >
                        Download Sample
                    </a>

                    <button
                        v-if="canImportFrontOffice"
                        type="button"
                        class="inline-flex items-center px-3 py-2 text-sm font-semibold text-white bg-indigo-600 rounded hover:bg-indigo-700 disabled:opacity-60"
                        :disabled="isUploading"
                        @click="openFilePicker"
                    >
                        {{ isUploading ? 'Uploading...' : 'File Upload' }}
                    </button>

                    <input ref="fileInputRef" type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="handleFileChange" />

                    <Link
                        v-if="canCreateFrontOffice"
                        :href="route('backend.frontoffice.create')"
                        class="inline-flex items-center px-3 py-2 text-sm font-semibold text-white bg-green-600 rounded hover:bg-green-700"
                    >
                        Add
                    </Link>
                </div>
            </div>

            <div class="flex justify-between w-full p-4 space-x-2 text-gray-700 rounded shadow-md bg-slate-600 shadow-gray-800/50 dark:bg-gray-700 dark:text-gray-200">
                <div class="grid w-full grid-cols-1 gap-2 md:grid-cols-5">
                    <div class="flex space-x-2">
                        <div class="w-full">
                            <input id="name" v-model="filters.name"
                                class="block w-full p-2 text-sm bg-gray-300 rounded shadow-sm border-slate-100 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                type="text" placeholder="Title" @input="applyFilter" />
                        </div>
                    </div>
                </div>

                <div class="hidden min-w-24 md:block">
                    <select v-model="filters.numOfData" @change="applyFilter"
                        class="w-full p-2 text-sm bg-gray-300 rounded shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                        <option value="10">show 10</option>
                        <option value="20">show 20</option>
                        <option value="30">show 30</option>
                        <option value="40">show 40</option>
                        <option value="100">show 100</option>
                        <option value="150">show 150</option>
                        <option value="500">show 500</option>
                    </select>
                </div>
            </div>

            <div ref="printSectionRef" class="w-full my-3 overflow-x-auto">
                <BaseTable />
            </div>
            <Pagination />
        </div>
    </BackendLayout>
</template>

