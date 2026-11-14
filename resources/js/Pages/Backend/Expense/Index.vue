<script setup>
import { onMounted, ref } from "vue";
import BackendLayout from '@/Layouts/BackendLayout.vue';
import BaseTable from '@/Components/BaseTable.vue';
import Pagination from '@/Components/Pagination.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    filters: Object,
    supplierPayments: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    expenseTotals: {
        type: Object,
        default: () => ({ regular: 0, store: 0, pharmacy: 0, lab: 0, total: 0, printed_at: '' }),
    },
});

const isPageRefresh = window.performance?.getEntriesByType?.('navigation')?.[0]?.type === 'reload';
const searchParams = new URLSearchParams(window.location.search);
const hasDateQuery = searchParams.has('date_from') || searchParams.has('date_to');

const filters = ref({
    numOfData: props.filters?.numOfData ?? 10,
    name: props.filters?.name ?? '',
    date_from: isPageRefresh ? '' : (props.filters?.date_from ?? ''),
    date_to: isPageRefresh ? '' : (props.filters?.date_to ?? ''),
});

const applyFilter = () => {
    router.get(route('backend.expense.index'), filters.value, { preserveState: true });
};

onMounted(() => {
    if (isPageRefresh && hasDateQuery) {
        router.get(route('backend.expense.index'), filters.value, {
            preserveState: true,
            replace: true,
        });
    }
});

const goToExpenseAdd = () => {
    router.visit(route('backend.expense.create'));
};

const goBack = () => {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit(route('backend.dashboard'));
    }
};

const printExpenseTotal = () => window.print();
const formatMoney = (amount) => Number(amount ?? 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
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
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-red-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-red-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-red-700">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M10.5 19.5 3 12m7.5-7.5L3 12m7.5 0H21"></path>
                            </svg>
                            Back
                        </button>

                        <button @click="goToExpenseAdd"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out"
                            style="background: linear-gradient(to right, #3b82f6, #60a5fa);"
                            onmouseover="this.style.background='linear-gradient(to right, #2563eb, #3b82f6)';"
                            onmouseout="this.style.background='linear-gradient(to right, #3b82f6, #60a5fa)';">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15">
                                </path>
                            </svg>
                            Expense Add
                        </button>
                        <button @click="printExpenseTotal"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-emerald-600 border-0 rounded-md shadow-lg hover:bg-emerald-700 print:hidden">
                            Print Expense Total
                        </button>
                    </div>
                </div>
            </div>
            <div
                class="flex justify-between w-full p-2 py-3 space-x-2 text-gray-700 rounded-md bg-slate-300 shadow-gray-800/50 dark:bg-gray-700 dark:text-gray-200">

                <div class="grid w-full grid-cols-1 gap-2 md:grid-cols-5">

                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-4 md:col-span-4">
                        <div>
                            <label class="sr-only" for="name">Name</label>
                            <input id="name" v-model="filters.name"
                                class="block w-full p-2 text-sm rounded-md border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                type="text" placeholder="Name" @input="applyFilter" />
                        </div>

                        <div>
                            <div class="relative">
                                <input id="expense-date-from" v-model="filters.date_from"
                                    :class="{ 'is-empty': !filters.date_from }"
                                    class="date-filter-input block w-full p-2 text-sm rounded-md border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                    type="date" aria-label="From Date" />
                                <span v-if="!filters.date_from"
                                    class="pointer-events-none absolute inset-y-0 left-2 right-8 flex items-center justify-start text-sm text-gray-500 dark:text-slate-300">
                                    From Date
                                </span>
                            </div>
                        </div>

                        <div>
                            <div class="relative">
                                <input id="expense-date-to" v-model="filters.date_to"
                                    :class="{ 'is-empty': !filters.date_to }"
                                    class="date-filter-input block w-full p-2 text-sm rounded-md border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                    type="date" aria-label="To Date" />
                                <span v-if="!filters.date_to"
                                    class="pointer-events-none absolute inset-y-0 left-2 right-8 flex items-center justify-start text-sm text-gray-500 dark:text-slate-300">
                                    To Date
                                </span>
                            </div>
                        </div>

                        <div>
                            <button type="button" @click="applyFilter"
                                class="w-full rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-300">
                                Search
                            </button>
                        </div>

                        <div class="block min-w-24 sm:hidden">
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
                <BaseTable />
            </div>
            <Pagination />

            <section class="mt-8">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <div>
                        <h2 class="text-lg font-bold text-gray-800 dark:text-white">Store & Pharmacy Supplier Payments</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Supplier payments recorded through Store or Pharmacy Accounts are listed here as expenses.</p>
                    </div>
                </div>
                <div class="w-full overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-700 border border-gray-200 dark:text-gray-200 dark:border-slate-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-3 py-2 border dark:border-slate-700">Date</th>
                                <th class="px-3 py-2 border dark:border-slate-700">Supplier</th>
                                <th class="px-3 py-2 border dark:border-slate-700">Source</th>
                                <th class="px-3 py-2 border dark:border-slate-700">Invoice / Reference</th>
                                <th class="px-3 py-2 border dark:border-slate-700">Payment Account</th>
                                <th class="px-3 py-2 text-right border dark:border-slate-700">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="payment in supplierPayments.data" :key="payment.key" class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="px-3 py-2 border dark:border-slate-700">{{ payment.display_date }}</td>
                                <td class="px-3 py-2 border dark:border-slate-700">{{ payment.supplier }}</td>
                                <td class="px-3 py-2 border dark:border-slate-700">{{ payment.source }}</td>
                                <td class="px-3 py-2 border dark:border-slate-700">{{ payment.bill_number }}<span v-if="payment.reference !== 'N/A'" class="block text-xs text-gray-500">{{ payment.reference }}</span></td>
                                <td class="px-3 py-2 border dark:border-slate-700">{{ payment.account }}</td>
                                <td class="px-3 py-2 text-right border dark:border-slate-700">৳ {{ Number(payment.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</td>
                            </tr>
                            <tr v-if="!supplierPayments.data?.length">
                                <td colspan="6" class="px-3 py-6 text-center text-gray-500 border dark:border-slate-700">No Store or Pharmacy supplier payments found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="supplierPayments.links?.length" class="flex flex-wrap justify-end gap-2 mt-3">
                    <template v-for="(link, index) in supplierPayments.links" :key="index">
                        <a v-if="link.url" :href="link.url" class="px-3 py-1 text-sm border rounded dark:border-slate-600"
                            :class="link.active ? 'bg-emerald-600 text-white border-emerald-600' : 'text-slate-600 dark:text-slate-200'"
                            v-html="link.label" />
                        <span v-else class="px-3 py-1 text-sm text-slate-400 border rounded dark:border-slate-600" v-html="link.label" />
                    </template>
                </div>
            </section>
        </div>

        <section id="expense-total-print" class="expense-total-print">
            <h1>Expense Total Summary</h1>
            <p class="print-date">Printed: {{ expenseTotals.printed_at }}</p>
            <table>
                <tbody>
                    <tr>
                        <th>Other Expenses</th>
                        <td>৳ {{ formatMoney(expenseTotals.regular) }}</td>
                    </tr>
                    <tr>
                        <th>Store Supplier Payments</th>
                        <td>৳ {{ formatMoney(expenseTotals.store) }}</td>
                    </tr>
                    <tr>
                        <th>Pharmacy Supplier Payments</th>
                        <td>৳ {{ formatMoney(expenseTotals.pharmacy) }}</td>
                    </tr>
                    <tr>
                        <th>Laboratory Supplier Payments</th>
                        <td>৳ {{ formatMoney(expenseTotals.lab) }}</td>
                    </tr>
                    <tr class="grand-total">
                        <th>Total Expenses</th>
                        <td>৳ {{ formatMoney(expenseTotals.total) }}</td>
                    </tr>
                </tbody>
            </table>
            <p class="summary-note">Includes all active expenses and recorded Store, Pharmacy and Laboratory supplier payments.</p>
        </section>
    </BackendLayout>
</template>

<style scoped>
.date-filter-input.is-empty:not(:focus)::-webkit-datetime-edit {
    color: transparent;
}

.expense-total-print {
    display: none;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 15mm;
    }

    :global(body *) {
        visibility: hidden !important;
    }

    #expense-total-print,
    #expense-total-print * {
        visibility: visible !important;
    }

    #expense-total-print {
        display: block !important;
        position: fixed !important;
        inset: 0 !important;
        width: 100% !important;
        padding: 24px !important;
        color: #111827 !important;
        background: #fff !important;
        font-family: Arial, sans-serif !important;
    }

    #expense-total-print h1 {
        margin: 0 0 8px;
        font-size: 24px;
        font-weight: 700;
        text-align: center;
    }

    #expense-total-print .print-date {
        margin: 0 0 28px;
        color: #4b5563;
        font-size: 12px;
        text-align: center;
    }

    #expense-total-print table {
        width: 100%;
        border-collapse: collapse;
    }

    #expense-total-print th,
    #expense-total-print td {
        padding: 12px;
        border: 1px solid #d1d5db;
        text-align: left;
    }

    #expense-total-print td {
        text-align: right;
        white-space: nowrap;
    }

    #expense-total-print .grand-total th,
    #expense-total-print .grand-total td {
        border-top: 2px solid #111827;
        font-size: 18px;
        font-weight: 700;
    }

    #expense-total-print .summary-note {
        margin-top: 18px;
        color: #4b5563;
        font-size: 11px;
        text-align: center;
    }
}
</style>
