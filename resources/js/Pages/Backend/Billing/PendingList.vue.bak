<script setup>
import { ref } from "vue";
import BackendLayout from '@/Layouts/BackendLayout.vue';
import BaseTable from '@/Components/BaseTable.vue';
import Pagination from '@/Components/Pagination.vue';
import { router, usePage } from '@inertiajs/vue3';

let props = defineProps({
    filters: Object,
});

const filters = ref({

    numOfData: props.filters?.numOfData ?? 10,
});

const applyFilter = () => {
    router.get(route('backend.billing.list'), filters.value, { preserveState: true });
};

const goToBillingAdd = () => {
    router.visit(route('backend.billing.view'));
};

const page = usePage();
const showDueModal = ref(false);
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
    showDueModal.value = true;
};

const closeDueModal = () => {
    showDueModal.value = false;
    dueForm.value.amount = '';
};

const submitDueCollect = () => {
    const amount = Number(dueForm.value.amount || 0);
    const dueAmount = Number(dueForm.value.dueAmount || 0);

    if (!Number.isFinite(amount) || amount <= 0 || amount > dueAmount) {
        alert('Invalid amount.');
        return;
    }

    const routeName = dueForm.value.rowType === 'opd'
        ? 'backend.opd.due.collect.store'
        : 'backend.due.collect.store';

    router.post(route(routeName, dueForm.value.rowId), {
        amount
    }, {
        preserveScroll: true,
        onSuccess: () => {
            closeDueModal();
            router.reload({ only: ['datas'] });
        }
    });
};

const handleAction = (actionName, actionId) => {
    if (actionName !== 'due-collect') return;
    const [rowType, rowId] = String(actionId || '').split('|');
    if (!rowType || !rowId) return;
    openDueModal(rowType, rowId);
};

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
                            Billing Add
                        </button>
                    </div>
                </div>
            </div>
            <div
                class="flex justify-between w-full p-2 py-3 space-x-2 text-gray-700 rounded-md bg-slate-300 shadow-gray-800/50 dark:bg-gray-700 dark:text-gray-200">

                <div class="grid w-full grid-cols-1 gap-2 md:grid-cols-5">

                    <div class="flex space-x-2">
                        <div class="w-full">
                            <input id="name" v-model="filters.bill_no"
                                class="block w-full p-2 text-sm rounded-md border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                type="text" placeholder="Bill No" @input="applyFilter" />
                        </div>

                        <div class="block min-w-24 md:hidden">
                            <select v-model="filters.numOfData" @change="applyFilter"
                                class="w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                                <option value="10">Show 10</option>
                                <option value="20">Show 20</option>
                                <option value="30">Show 30</option>
                                <option value="40">Show 40</option>
                                <option value="100">Show 100</option>
                                <option value="150">Show 150</option>
                                <option value="500">Show 500</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="hidden min-w-24 md:block">
                    <select v-model="filters.numOfData" @change="applyFilter"
                        class="w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
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

            <div class="w-full my-3 overflow-x-auto">
                <BaseTable @action="handleAction" />
            </div>
            <Pagination />
        </div>

        <div v-if="showDueModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b px-5 py-3">
                    <h3 class="text-base font-semibold text-gray-800">Due Collect</h3>
                    <button type="button" class="text-gray-500 hover:text-gray-700" @click="closeDueModal">✕</button>
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
                            @keydown.enter.prevent="submitDueCollect"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                        >
                        <p class="mt-1 text-xs text-gray-500">Max: Tk {{ Number(dueForm.dueAmount || 0).toFixed(2) }}</p>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 border-t px-5 py-3">
                    <button type="button" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700" @click="closeDueModal">Cancel</button>
                    <button
                        type="button"
                        class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white"
                        @click="submitDueCollect"
                    >
                        Collect Due
                    </button>
                </div>
            </div>
        </div>
    </BackendLayout>
</template>
