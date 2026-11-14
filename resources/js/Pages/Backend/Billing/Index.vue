<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import BackendLayout from '@/Layouts/BackendLayout.vue';
import BaseTable from '@/Components/BaseTable.vue';
import Pagination from '@/Components/Pagination.vue';
import { progress, router, useForm, usePage } from '@inertiajs/vue3';
import { useModalSubmissionGuard } from '@/Composables/useModalSubmissionGuard';
import {
    formatOfflineBillReference,
    getOfflineBillingEncryptionStatus,
    getOfflineBillingRequests,
} from '@/offlineBillingQueue.js';

let props = defineProps({
    filters: Object,
    isDeveloper: Boolean,
});

const filters = ref({
    search: props.filters?.search ?? props.filters?.bill_no ?? '',
    numOfData: props.filters?.numOfData ?? 10,
    user: props.filters?.user ?? '',
    ipd: props.filters?.ipd ?? '',
    category: props.filters?.category ?? '',
});

const showUserStats = ref(false);
const showFinalBillModal = ref(false);
const finalBillPreviewUrl = ref('');
const finalBillTitle = ref('Final Bill');
const page = usePage();
const userStats = computed(() => page.props.userStats || []);
const totalUserCount = computed(() => (userStats.value || []).reduce((s, r) => s + (r.count || 0), 0));
const isIpdBillingPage = computed(() => filters.value.ipd && String(filters.value.ipd) !== '0');
const csvInput = ref(null);
const csvForm = useForm({ csv_file: null });
const offlinePendingBills = ref([]);
const offlineQueueLocked = ref(false);
const offlineQueueError = ref('');
const offlineAdminId = computed(() => Number(page.props.auth?.admin?.id || 0));

const loadOfflinePendingBills = async () => {
    if (!offlineAdminId.value) return;

    try {
        const encryptionStatus = await getOfflineBillingEncryptionStatus(offlineAdminId.value);
        const requests = await getOfflineBillingRequests(offlineAdminId.value);
        offlineQueueLocked.value = !encryptionStatus.unlocked && requests.some(
            (entry) => ['queued', 'syncing', 'needs_review'].includes(entry.status)
        );
        if (!encryptionStatus.unlocked) {
            offlinePendingBills.value = [];
            offlineQueueError.value = '';
            return;
        }

        const search = String(filters.value.search || '').trim().toLowerCase();
        const category = String(filters.value.category || '').trim().toLowerCase();
        const creator = String(page.props.auth?.admin?.name || '').toLowerCase();

        offlinePendingBills.value = requests
            .filter((entry) => (
                entry.routeName === 'backend.billing.store'
                && ['queued', 'syncing', 'needs_review'].includes(entry.status)
                && entry.data
                && !entry.data.ipd_patient_id
            ))
            .filter((entry) => {
                const patientName = String(entry.data.patient_name || 'Walk-in Patient').toLowerCase();
                const billReference = formatOfflineBillReference(entry.id).toLowerCase();
                const matchesSearch = !search || billReference.includes(search) || patientName.includes(search);
                const matchesCategory = !category || entry.data.items?.some(
                    (item) => String(item.category || '').toLowerCase() === category
                );
                const matchesCreator = !filters.value.user || creator.includes(String(filters.value.user).toLowerCase());
                return matchesSearch && matchesCategory && matchesCreator;
            })
            .reverse()
            .map((entry) => ({
                id: entry.id,
                reference: formatOfflineBillReference(entry.id),
                patientName: entry.data.patient_name || 'Walk-in Patient',
                payable: Number(entry.data.payable_amount || 0),
                paid: Number(entry.data.paid_amt || 0),
                due: Number(entry.data.due_amount || 0),
                createdAt: new Date(entry.createdAt).toLocaleString(),
                status: entry.status,
                lastError: entry.lastError || '',
            }));
        offlineQueueError.value = '';
    } catch (error) {
        offlineQueueError.value = error instanceof Error
            ? error.message
            : 'Could not load the offline bill queue.';
    }
};

const applyFilter = () => {
    if (isIpdBillingPage.value) {
        // preserve IPD list view when on IPD billing page
        filters.value.ipd = 1;
    } else {
        filters.value.ipd = '';
    }
    loadOfflinePendingBills();
    if (!navigator.onLine) return;
    router.get(route('backend.billing.list'), filters.value, { preserveState: true });
};

const clearFilters = () => {
    filters.value.search = '';
    filters.value.user = '';
    filters.value.category = '';
    applyFilter();
};

const goToBillingAdd = () => {
    const url = isIpdBillingPage.value
        ? route('backend.ipdpatient.create')
        : route('backend.billing.view');
    window.open(url, '_blank', 'noopener,noreferrer');
};

const goBack = () => {
    if (window.history.length > 1) {
        progress.start();
        window.setTimeout(() => progress.finish(), 800);
        window.history.back();
        return;
    }

    router.visit(isIpdBillingPage.value
        ? route('backend.ipdpatient.index')
        : route('backend.billing.view'));
};

const openCsvPicker = () => {
    csvInput.value?.click();
};

const uploadBillsCsv = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    csvForm.csv_file = file;
    csvForm.post(route('backend.billing.import-csv'), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            csvForm.reset('csv_file');
            event.target.value = '';
        },
    });
};

// page is defined above to compute user stats
const showDueModal = ref(false);
const {
    isSubmitting: isSubmittingDue,
    prepareSubmissionToken,
    ensureSubmissionToken,
    beginSubmission,
    endSubmission,
    resetSubmissionToken,
} = useModalSubmissionGuard('billing_due');
const dueForm = ref({
    rowType: 'billing',
    rowId: null,
    billNo: 'N/A',
    patientName: 'N/A',
    dueAmount: 0,
    amount: ''
});

const openDueModal = (rowType, rowId) => {
    const rows = page.props?.datas?.data || [];
    const row = rows.find((item) => String(item.row_id) === String(rowId) && String(item.row_type) === String(rowType));

    dueForm.value.rowType = rowType;
    dueForm.value.rowId = rowId;
    dueForm.value.billNo = row?.bill_number || 'N/A';
    dueForm.value.patientName = row?.patient_id || 'N/A';
    dueForm.value.dueAmount = Number(row?.due_amount || 0);
    dueForm.value.amount = '';
    prepareSubmissionToken();
    showDueModal.value = true;
};

const closeDueModal = (force = false) => {
    if (isSubmittingDue.value && !force) return;
    showDueModal.value = false;
    dueForm.value.amount = '';
    resetSubmissionToken();
};

const submitDueCollect = () => {
    if (isSubmittingDue.value) {
        return;
    }

    const amount = Number(dueForm.value.amount || 0);
    const dueAmount = Number(dueForm.value.dueAmount || 0);

    if (!Number.isFinite(amount) || amount <= 0 || amount > dueAmount) {
        alert('Invalid amount.');
        return;
    }

    const routeName = dueForm.value.rowType === 'opd'
        ? 'backend.opd.due.collect.store'
        : 'backend.due.collect.store';
    const invoiceRoute = dueForm.value.rowType === 'opd'
        ? 'backend.download.opd.bill'
        : 'backend.download.invoice';
    const invoiceParams = dueForm.value.rowType === 'opd'
        ? { id: dueForm.value.rowId, module: 'opd', auto_print: 0 }
        : { id: dueForm.value.rowId, module: 'billing', fast_open: 1, auto_print: 0 };
    const invoiceWindow = navigator.onLine
        ? window.open(route(invoiceRoute, invoiceParams), '_blank')
        : null;
    if (invoiceWindow) invoiceWindow.opener = null;
    const closeInvoiceWindow = () => {
        if (invoiceWindow && !invoiceWindow.closed) invoiceWindow.close();
    };
    const openCollectedInvoice = () => {
        if (!invoiceWindow || invoiceWindow.closed) {
            window.toast?.('Collection was saved, but the invoice print window was blocked. Allow pop-ups and try printing the invoice again.', { type: 'warning' });
            return;
        }

        invoiceWindow.location.replace(route(invoiceRoute, { ...invoiceParams, auto_print: 1 }));
        invoiceWindow.focus();
    };

    beginSubmission();

    router.post(route(routeName, dueForm.value.rowId), {
        amount,
        submission_token: ensureSubmissionToken(),
        return_to: window.location.href
    }, {
        preserveScroll: true,
        onSuccess: () => {
            closeDueModal(true);
            openCollectedInvoice();
            router.reload({ only: ['datas'] });
        },
        onError: () => closeInvoiceWindow(),
        onFinish: () => {
            endSubmission();
        }
    });
};

const openFinalBillPreview = (rowId) => {
    const rows = page.props?.datas?.data || [];
    const row = rows.find((item) => String(item.row_id) === String(rowId));
    if (!row?.ipd_patient_id) {
        return;
    }

    const resolvedUrl = route('backend.print.ipd.final-bill', {
        id: row.ipd_patient_id,
        auto_print: 0,
        fast_open: 1,
    });

    finalBillPreviewUrl.value = resolvedUrl;
    finalBillTitle.value = row.bill_number ? `Final Bill - ${row.bill_number}` : 'Final Bill';
    showFinalBillModal.value = true;
};

const handleAction = (actionName, actionId) => {
    if (actionName === 'due-collect') {
        const [rowType, rowId] = String(actionId || '').split('|');
        if (!rowType || !rowId) return;
        openDueModal(rowType, rowId);
        return;
    }

    if (actionName === 'final-bill-preview') {
        const [rowType, rowId] = String(actionId || '').split('|');
        if (!rowType || !rowId) return;
        openFinalBillPreview(rowId);
    }
};

const handleBillingListStorage = (event) => {
    if (event?.key === 'billing:list:refresh') {
        try {
            router.reload({ only: ['datas'] });
        } catch (e) {}
    }
};

const handleBillingListSameTab = () => {
    try { router.reload({ only: ['datas'] }); } catch (e) {}
};

const handleOfflineQueueUpdated = () => loadOfflinePendingBills();

onMounted(() => {
    window.addEventListener('storage', handleBillingListStorage);
    window.addEventListener('billing:list:refresh', handleBillingListSameTab);
    window.addEventListener('offline-billing:queue-updated', handleOfflineQueueUpdated);
    loadOfflinePendingBills();
});

onUnmounted(() => {
    window.removeEventListener('storage', handleBillingListStorage);
    window.removeEventListener('billing:list:refresh', handleBillingListSameTab);
    window.removeEventListener('offline-billing:queue-updated', handleOfflineQueueUpdated);
});

</script>

<template>
    <BackendLayout>

        <div class="w-full p-2 duration-1000 ease-in-out bg-white rounded-md dark:bg-slate-900">

            <div
                class="flex mb-2 items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md dark:bg-gray-800 dark:text-gray-200 shadow-gray-800/50">
                <div>
                    <h1 class="p-4 text-xl font-bold dark:text-white">{{ $page.props.pageTitle }}</h1>
                </div>

                <div class="p-4 py-2 flex items-center space-x-2">
                    <div class="flex items-center space-x-3">
                        <button @click="goBack"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out"
                            style="background: linear-gradient(to right, #6b7280, #9ca3af);"
                            onmouseover="this.style.background='linear-gradient(to right, #4b5563, #6b7280)';"
                            onmouseout="this.style.background='linear-gradient(to right, #6b7280, #9ca3af)';">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Back
                        </button>
                        <template v-if="isDeveloper && !isIpdBillingPage">
                            <a
                                :href="route('backend.billing.export-all-csv')"
                                class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold !text-white rounded-md shadow-lg active:scale-95 transition-all duration-150"
                                style="background: linear-gradient(to right, #059669, #34d399); color: #ffffff !important;"
                            >
                                Download All Bill List
                            </a>
                            <input
                                ref="csvInput"
                                type="file"
                                accept=".csv,text/csv"
                                class="hidden"
                                @change="uploadBillsCsv"
                            />
                            <button
                                type="button"
                                :disabled="csvForm.processing"
                                @click="openCsvPicker"
                                class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white rounded-md shadow-lg disabled:opacity-50 active:scale-95 transition-all duration-150"
                                style="background: linear-gradient(to right, #4f46e5, #818cf8);"
                            >
                                {{ csvForm.processing ? 'Uploading...' : 'Upload CSV' }}
                            </button>
                        </template>
                        <button @click="goToBillingAdd"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out"
                            style="background: linear-gradient(to right, #3b82f6, #60a5fa);"
                            onmouseover="this.style.background='linear-gradient(to right, #2563eb, #3b82f6)';"
                            onmouseout="this.style.background='linear-gradient(to right, #3b82f6, #60a5fa)';">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15">
                                </path>
                            </svg>
                            {{ isIpdBillingPage ? 'Ipd Patient Add' : 'Billing Add' }}
                        </button>
                    </div>
                </div>
            </div>
            <p v-if="csvForm.errors.csv_file" class="mb-2 px-3 py-2 text-sm text-red-700 bg-red-100 rounded-md">
                {{ csvForm.errors.csv_file }}
            </p>
            <div class="flex justify-between w-full p-2 py-3 text-gray-700 rounded-md bg-slate-300 shadow-gray-800/50 dark:bg-gray-700 dark:text-gray-200">
                <div class="w-full">
                    <div class="flex flex-col md:flex-row md:items-center md:gap-2">
                        <div class="w-full md:flex-1 md:mr-2">
                            <input id="search" v-model="filters.search" @input="applyFilter"
                                class="block w-full p-3 text-sm rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 placeholder-gray-500 dark:placeholder-slate-400 focus:border-indigo-300 dark:focus:border-slate-500"
                                type="text" placeholder="Search Date (dd/mm/yyyy), Bill No, Patient" />
                        </div>

                        <div class="w-full md:w-56 md:mx-2">
                            <input v-model="filters.user" @input="applyFilter"
                                class="block w-full p-2 text-sm rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 placeholder-gray-500 dark:placeholder-slate-400 focus:border-indigo-300 dark:focus:border-slate-500"
                                type="text" placeholder="Search by User (creator)" />
                        </div>

                        <div class="w-full md:w-36 flex items-center md:mx-2">
                            <button
                                type="button"
                                @click="showUserStats = !showUserStats"
                                class="w-full px-3 py-2 text-sm font-semibold rounded-md bg-indigo-600 text-white hover:bg-indigo-700 shadow-md"
                            >
                                {{ showUserStats ? 'Hide Stats' : 'Show Stats (' + totalUserCount + ')' }}
                            </button>
                        </div>

                        <div class="w-full md:w-auto flex items-center justify-end space-x-2 md:ml-2">
                            <select v-model="filters.numOfData" @change="applyFilter"
                                class="w-24 p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                                <option value="10">show 10</option>
                                <option value="20">show 20</option>
                                <option value="40">show 40</option>
                                <option value="50">show 50</option>
                                <option value="100">show 100</option>
                                <option value="200">show 200</option>
                                <option value="250">show 250</option>
                                <option value="500">show 500</option>
                                <option value="all">show all</option>
                            </select>

                            <button
                                type="button"
                                @click="clearFilters"
                                class="px-3 py-2 text-sm rounded-md bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-slate-600 dark:text-slate-100 dark:hover:bg-slate-500"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="showUserStats && $page.props.userStats && $page.props.userStats.length" class="w-full my-3 p-3 bg-white rounded-md shadow-sm">
                <h3 class="text-sm font-semibold mb-2">User daily counts</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-gray-700 border">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-3 py-2 border text-center">Created Date</th>
                                <th class="px-3 py-2 border text-center">Count</th>
                                <th class="px-3 py-2 border text-center">Total Amount</th>
                                <th class="px-3 py-2 border text-center">Total Discount</th>
                                <th class="px-3 py-2 border text-center">Due Pending Income</th>
                                <th class="px-3 py-2 border text-center">Total Net Income</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in $page.props.userStats" :key="row.date">
                                <td class="px-3 py-2 border text-center">{{ row.date }}</td>
                                <td class="px-3 py-2 border text-center">{{ row.count }}</td>
                                <td class="px-3 py-2 border text-center">Tk {{ Number(row.total_amount || 0).toFixed(2) }}</td>
                                <td class="px-3 py-2 border text-center">Tk {{ Number(row.total_discount || 0).toFixed(2) }}</td>
                                <td class="px-3 py-2 border text-center">Tk {{ Number(row.due_pending_income || 0).toFixed(2) }}</td>
                                <td class="px-3 py-2 border text-center">Tk {{ Number(row.total_net_income || 0).toFixed(2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <section v-if="offlinePendingBills.length || offlineQueueLocked || offlineQueueError" class="my-3 rounded-md border border-amber-300 bg-amber-50 p-3">
                <h2 class="mb-2 text-sm font-semibold text-amber-900">Offline bills waiting to synchronize</h2>
                <p v-if="offlineQueueLocked" class="mb-2 text-sm text-amber-800">
                    Unlock Offline Billing from the billing page to view this browser's unsynchronized bills.
                </p>
                <p v-if="offlineQueueError" class="mb-2 text-sm text-red-700">{{ offlineQueueError }}</p>
                <div v-if="offlinePendingBills.length" class="overflow-x-auto">
                    <table class="w-full min-w-[700px] border-collapse text-xs text-gray-800">
                        <thead class="bg-amber-100">
                            <tr>
                                <th class="border px-3 py-2 text-left">Bill Reference</th>
                                <th class="border px-3 py-2 text-left">Patient</th>
                                <th class="border px-3 py-2 text-right">Payable</th>
                                <th class="border px-3 py-2 text-right">Paid</th>
                                <th class="border px-3 py-2 text-right">Due</th>
                                <th class="border px-3 py-2 text-left">Status</th>
                                <th class="border px-3 py-2 text-left">Saved</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="bill in offlinePendingBills" :key="bill.id">
                                <td class="border px-3 py-2 font-mono">{{ bill.reference }}</td>
                                <td class="border px-3 py-2">{{ bill.patientName }}</td>
                                <td class="border px-3 py-2 text-right">{{ bill.payable.toFixed(2) }}</td>
                                <td class="border px-3 py-2 text-right">{{ bill.paid.toFixed(2) }}</td>
                                <td class="border px-3 py-2 text-right">{{ bill.due.toFixed(2) }}</td>
                                <td class="border px-3 py-2">
                                    <span v-if="bill.status === 'needs_review'" class="font-semibold text-red-700">Needs review</span>
                                    <span v-else-if="bill.status === 'syncing'" class="font-semibold text-blue-700">Synchronizing</span>
                                    <span v-else class="font-semibold text-amber-800">Waiting to sync</span>
                                    <span v-if="bill.lastError" class="mt-1 block text-red-700">{{ bill.lastError }}</span>
                                </td>
                                <td class="border px-3 py-2">{{ bill.createdAt }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="offlinePendingBills.length" class="mt-2 text-xs text-amber-800">
                    These are local provisional references, not server bill numbers. They move into the regular bill list after synchronization.
                </p>
            </section>

            <div class="my-3 w-full overflow-x-auto">
                <BaseTable class="min-w-[1400px]" @action="handleAction" />
            </div>
            <Pagination />
        </div>

        <div v-if="showFinalBillModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-7xl rounded-lg bg-white shadow-xl" style="height: min(95vh, 1100px);">
                <div class="flex items-center justify-between border-b px-5 py-3">
                    <h3 class="text-base font-semibold text-gray-800">{{ finalBillTitle }}</h3>
                    <div class="flex items-center gap-2">
                        <a v-if="finalBillPreviewUrl" :href="finalBillPreviewUrl" target="_blank" rel="noopener"
                            class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white">
                            Open in New Tab
                        </a>
                        <button type="button" class="text-gray-500 hover:text-gray-700" @click="showFinalBillModal = false">✕</button>
                    </div>
                </div>
                <div class="h-[calc(100%-56px)] overflow-hidden">
                    <iframe v-if="finalBillPreviewUrl" :src="finalBillPreviewUrl" class="h-full w-full border-0" title="Final Bill Preview"></iframe>
                </div>
            </div>
        </div>

        <div v-if="showDueModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b px-5 py-3">
                    <h3 class="text-base font-semibold text-gray-800">Due Collect</h3>
                    <button type="button" class="text-gray-500 hover:text-gray-700 disabled:opacity-50 disabled:cursor-not-allowed" :disabled="isSubmittingDue" @click="closeDueModal">✕</button>
                </div>
                <div class="px-5 py-4">
                    <table class="w-full text-sm text-gray-700">
                        <tr>
                            <td class="py-1 font-semibold">Bill No</td>
                            <td class="py-1">{{ dueForm.billNo }}</td>
                        </tr>
                        <tr>
                            <td class="py-1 font-semibold">Patient</td>
                            <td class="py-1">{{ dueForm.patientName }}</td>
                        </tr>
                        <tr>
                            <td class="py-1 font-semibold">Due Amount</td>
                            <td class="py-1 text-red-600 font-semibold">Tk {{ Number(dueForm.dueAmount || 0).toFixed(2) }}</td>
                        </tr>
                    </table>

                    <div class="mt-4">
                        <label class="mb-1 block text-sm font-semibold text-gray-700">Pay Amount</label>
                        <input
                            v-model="dueForm.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            :max="dueForm.dueAmount"
                            :disabled="isSubmittingDue"
                            @keydown.enter.prevent="submitDueCollect"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none disabled:bg-gray-100 disabled:cursor-not-allowed"
                        >
                        <p class="mt-1 text-xs text-gray-500">Max: Tk {{ Number(dueForm.dueAmount || 0).toFixed(2) }}</p>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 border-t px-5 py-3">
                    <button type="button" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 disabled:opacity-60 disabled:cursor-not-allowed" :disabled="isSubmittingDue" @click="closeDueModal">Cancel</button>
                    <button
                        type="button"
                        class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60 disabled:cursor-not-allowed"
                        :disabled="isSubmittingDue"
                        @click="submitDueCollect"
                    >
                        {{ isSubmittingDue ? 'Collecting...' : 'Collect Due' }}
                    </button>
                </div>
            </div>
        </div>
    </BackendLayout>
</template>
