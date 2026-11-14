<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const props = defineProps({
    pageTitle: { type: String, default: 'Pharmacy Profit & Loss Report' },
    summary: { type: Object, default: () => ({}) },
    breakdown: { type: Array, default: () => [] },
    itemSummary: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const filters = ref({
    period: props.filters?.period ?? 'daily',
    date: props.filters?.date ?? new Date().toISOString().slice(0, 10),
    month: props.filters?.month ?? new Date().toISOString().slice(0, 7),
    year: Number(props.filters?.year ?? new Date().getFullYear()),
    from: props.filters?.from ?? new Date().toISOString().slice(0, 10),
    to: props.filters?.to ?? new Date().toISOString().slice(0, 10),
});

const filteredDatesAreValid = computed(() =>
    filters.value.period !== 'custom'
    || (filters.value.from && filters.value.to && filters.value.from <= filters.value.to)
);

const applyFilter = () => {
    if (!filteredDatesAreValid.value) return;
    router.get(route('backend.pharmacy.profit-loss.report'), filters.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const formatMoney = (value) => `৳ ${Number(value ?? 0).toLocaleString('en-BD', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
})}`;

const exportCsv = () => {
    const columns = ['Date', 'Received Qty', 'Purchase Cost', 'Sales Bills', 'Sales Revenue', 'Sold Medicine Cost', 'Gross Profit'];
    const csvRows = [
        columns,
        ...props.breakdown.map((row) => [
            row.date,
            row.received_qty,
            row.purchase_cost,
            row.bill_count,
            row.sold_revenue,
            row.sold_cost,
            row.profit,
        ]),
        ['Total', props.summary?.received_qty, props.summary?.purchase_cost, props.summary?.bill_count,
            props.summary?.sold_revenue, props.summary?.sold_cost, props.summary?.profit],
        [],
        ['Medicine', 'Sold Qty', 'Returned Qty', 'Net Sold Qty', 'Sales Revenue', 'Purchase Cost', 'Gross Profit'],
        ...props.itemSummary.map((item) => [
            item.medicine_name,
            item.sold_qty,
            item.returned_qty,
            item.net_qty,
            item.sold_revenue,
            item.sold_cost,
            item.profit,
        ]),
    ];
    const csv = csvRows.map((row) => row.map((value) => `"${String(value ?? '').replaceAll('"', '""')}"`).join(',')).join('\r\n');
    const url = URL.createObjectURL(new Blob(['\uFEFF', csv], { type: 'text/csv;charset=utf-8' }));
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = 'pharmacy-profit-loss-report.csv';
    anchor.click();
    URL.revokeObjectURL(url);
};

const printReport = () => window.print();
</script>

<template>
    <BackendLayout>
        <div class="pharmacy-profit-loss-print w-full space-y-5 rounded-2xl bg-slate-50 p-4 shadow-sm dark:bg-slate-950">
            <div class="overflow-hidden rounded-2xl bg-gradient-to-r from-emerald-800 via-emerald-700 to-teal-600 p-5 text-white shadow-lg print-report-heading">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 class="mt-1 text-2xl font-extrabold print-report-title">{{ pageTitle }}</h1>
                    </div>
                <div class="flex flex-wrap items-center gap-2 print:hidden">
                    <button type="button" class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-emerald-800 shadow transition hover:bg-emerald-50" @click="exportCsv">Export CSV</button>
                    <button type="button" class="rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-white/20" @click="printReport">Print</button>
                    <a :href="route('backend.dashboard')" class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold !text-white shadow transition hover:bg-red-700">Back</a>
                </div>
                </div>
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 print:hidden">
                <div class="grid grid-cols-1 items-end gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="report-period" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Report Period</label>
                        <select id="report-period" v-model="filters.period" class="w-full rounded border-slate-300 p-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                            <option value="daily">Daily</option>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                            <option value="custom">Custom Date Range</option>
                        </select>
                    </div>
                    <div v-if="filters.period === 'daily'">
                        <label for="report-date" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Date</label>
                        <input id="report-date" v-model="filters.date" type="date" class="w-full rounded border-slate-300 p-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white" />
                    </div>
                    <div v-if="filters.period === 'monthly'">
                        <label for="report-month" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Month</label>
                        <input id="report-month" v-model="filters.month" type="month" class="w-full rounded border-slate-300 p-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white" />
                    </div>
                    <div v-if="filters.period === 'yearly'">
                        <label for="report-year" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Year</label>
                        <input id="report-year" v-model.number="filters.year" type="number" min="2000" max="2100" class="w-full rounded border-slate-300 p-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white" />
                    </div>
                    <template v-if="filters.period === 'custom'">
                        <div>
                            <label for="report-from" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">From</label>
                            <input id="report-from" v-model="filters.from" type="date" class="w-full rounded border-slate-300 p-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white" />
                        </div>
                        <div>
                            <label for="report-to" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">To</label>
                            <input id="report-to" v-model="filters.to" type="date" :min="filters.from" class="w-full rounded border-slate-300 p-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white" />
                        </div>
                    </template>
                    <button type="button" :disabled="!filteredDatesAreValid" class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50" @click="applyFilter">
                        Show Report
                    </button>
                </div>
                <p v-if="!filteredDatesAreValid" class="mt-2 text-xs text-red-600">End date must be the same as or later than the start date.</p>
            </section>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <article class="rounded-2xl border border-blue-200 bg-gradient-to-br from-blue-50 to-white p-4 shadow-sm dark:border-blue-900 dark:from-blue-950/50 dark:to-slate-900">
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">Medicine Received</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ Number(summary?.received_qty ?? 0).toLocaleString() }} pcs</p>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Purchase cost: {{ formatMoney(summary?.purchase_cost) }}</p>
                </article>
                <article class="rounded-2xl border border-teal-200 bg-gradient-to-br from-teal-50 to-white p-4 shadow-sm dark:border-teal-900 dark:from-teal-950/50 dark:to-slate-900">
                    <p class="text-xs font-semibold uppercase tracking-wide text-teal-700 dark:text-teal-300">Total Sell (Sales Revenue)</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ Number(summary?.bill_count ?? 0).toLocaleString() }} bills</p>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Sales revenue: {{ formatMoney(summary?.sold_revenue) }}</p>
                </article>
                <article class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-4 shadow-sm dark:border-amber-900 dark:from-amber-950/50 dark:to-slate-900">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Purchase Cost of Sold Medicines</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ formatMoney(summary?.sold_cost) }}</p>
                </article>
                <article class="rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white p-4 shadow-sm dark:border-indigo-900 dark:from-indigo-950/50 dark:to-slate-900">
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Items Sold</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ Number(summary?.item_count ?? 0).toLocaleString() }} items</p>
                </article>
                <article class="rounded-2xl border border-violet-200 bg-gradient-to-br from-violet-50 to-white p-4 shadow-sm dark:border-violet-900 dark:from-violet-950/50 dark:to-slate-900">
                    <p class="text-xs font-semibold uppercase tracking-wide text-violet-700 dark:text-violet-300">Gross Profit / Loss</p>
                    <p class="mt-2 text-2xl font-bold" :class="Number(summary?.profit ?? 0) >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300'">{{ formatMoney(summary?.profit) }}</p>
                </article>
            </div>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                    <h2 class="font-bold text-slate-900 dark:text-white">Profit &amp; loss by period</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Daily or monthly totals for purchases and sales.</p>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] border-collapse text-sm">
                    <thead class="bg-slate-50 text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        <tr>
                            <th class="border px-3 py-2 text-left">Date / Month</th>
                            <th class="border px-3 py-2 text-right">Received Qty</th>
                            <th class="border px-3 py-2 text-right">Stock Purchase Cost</th>
                            <th class="border px-3 py-2 text-right">Sales Bills</th>
                            <th class="border px-3 py-2 text-right">Sales Revenue</th>
                            <th class="border px-3 py-2 text-right">Purchase Cost of Sold</th>
                            <th class="border px-3 py-2 text-right">Profit / Loss</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in breakdown" :key="row.date" class="transition hover:bg-emerald-50/60 dark:hover:bg-slate-800">
                            <td class="border px-3 py-2">{{ row.date }}</td>
                            <td class="border px-3 py-2 text-right">{{ Number(row.received_qty ?? 0).toLocaleString() }}</td>
                            <td class="border px-3 py-2 text-right">{{ formatMoney(row.purchase_cost) }}</td>
                            <td class="border px-3 py-2 text-right">{{ Number(row.bill_count ?? 0).toLocaleString() }}</td>
                            <td class="border px-3 py-2 text-right">{{ formatMoney(row.sold_revenue) }}</td>
                            <td class="border px-3 py-2 text-right">{{ formatMoney(row.sold_cost) }}</td>
                            <td class="border px-3 py-2 text-right font-semibold" :class="Number(row.profit ?? 0) >= 0 ? 'text-emerald-700' : 'text-red-700'">{{ formatMoney(row.profit) }}</td>
                        </tr>
                        <tr class="bg-slate-50 font-bold dark:bg-slate-800">
                            <td class="border px-3 py-2">Total</td>
                            <td class="border px-3 py-2 text-right">{{ Number(summary?.received_qty ?? 0).toLocaleString() }}</td>
                            <td class="border px-3 py-2 text-right">{{ formatMoney(summary?.purchase_cost) }}</td>
                            <td class="border px-3 py-2 text-right">{{ Number(summary?.bill_count ?? 0).toLocaleString() }}</td>
                            <td class="border px-3 py-2 text-right">{{ formatMoney(summary?.sold_revenue) }}</td>
                            <td class="border px-3 py-2 text-right">{{ formatMoney(summary?.sold_cost) }}</td>
                            <td class="border px-3 py-2 text-right">{{ formatMoney(summary?.profit) }}</td>
                        </tr>
                        <tr v-if="!breakdown.length">
                            <td colspan="7" class="border px-3 py-8 text-center text-slate-500">No pharmacy purchase or sale records for this period.</td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                    <div>
                        <h2 class="font-bold text-slate-900 dark:text-white">Medicine sales summary</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Quantity sold for each item, returns, sales revenue and gross profit.</p>
                    </div>
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ Number(summary?.item_count ?? 0).toLocaleString() }} medicines</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] border-collapse text-sm">
                        <thead class="bg-slate-50 text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            <tr>
                                <th class="border px-3 py-3 text-left">Medicine</th>
                                <th class="border px-3 py-3 text-right">Sold Qty</th>
                                <th class="border px-3 py-3 text-right">Returned Qty</th>
                                <th class="border px-3 py-3 text-right">Net Qty</th>
                                <th class="border px-3 py-3 text-right">Sales Revenue</th>
                                <th class="border px-3 py-3 text-right">Purchase Cost</th>
                                <th class="border px-3 py-3 text-right">Profit / Loss</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in itemSummary" :key="item.medicine_id ?? item.medicine_name" class="transition hover:bg-emerald-50/60 dark:hover:bg-slate-800">
                                <td class="border px-3 py-2 font-medium">{{ item.medicine_name }}</td>
                                <td class="border px-3 py-2 text-right">{{ Number(item.sold_qty ?? 0).toLocaleString() }}</td>
                                <td class="border px-3 py-2 text-right">{{ Number(item.returned_qty ?? 0).toLocaleString() }}</td>
                                <td class="border px-3 py-2 text-right font-semibold">{{ Number(item.net_qty ?? 0).toLocaleString() }}</td>
                                <td class="border px-3 py-2 text-right">{{ formatMoney(item.sold_revenue) }}</td>
                                <td class="border px-3 py-2 text-right">{{ formatMoney(item.sold_cost) }}</td>
                                <td class="border px-3 py-2 text-right font-semibold" :class="Number(item.profit ?? 0) >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300'">{{ formatMoney(item.profit) }}</td>
                            </tr>
                            <tr v-if="!itemSummary.length">
                                <td colspan="7" class="border px-3 py-8 text-center text-slate-500">No medicine sales for this period.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </BackendLayout>
</template>

<style>
@page {
    size: landscape;
    margin: 12mm;
}

@media print {
    html,
    body {
        width: 100% !important;
        height: auto !important;
        overflow: visible !important;
        background: #fff !important;
    }

    body * {
        visibility: hidden !important;
    }

    .pharmacy-profit-loss-print,
    .pharmacy-profit-loss-print * {
        visibility: visible !important;
    }

    .pharmacy-profit-loss-print {
        position: absolute !important;
        inset: 0 auto auto 0 !important;
        display: block !important;
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
        border: 0 !important;
        border-radius: 0 !important;
        background: #fff !important;
        box-shadow: none !important;
        color: #111827 !important;
        font-size: 9pt !important;
    }

    .pharmacy-profit-loss-print .print-report-heading {
        margin-bottom: 8mm !important;
        padding: 0 0 4mm !important;
        border-bottom: 1px solid #9ca3af !important;
        border-radius: 0 !important;
        background: #fff !important;
        color: #111827 !important;
        box-shadow: none !important;
    }

    .pharmacy-profit-loss-print .print-report-title {
        margin: 0 !important;
        color: #111827 !important;
        font-size: 20pt !important;
        font-weight: 700 !important;
    }

    .pharmacy-profit-loss-print section,
    .pharmacy-profit-loss-print article {
        break-inside: avoid;
        border-color: #d1d5db !important;
        border-radius: 4px !important;
        background: #fff !important;
        box-shadow: none !important;
    }

    .pharmacy-profit-loss-print > .grid {
        grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
        gap: 3mm !important;
    }

    .pharmacy-profit-loss-print .overflow-x-auto {
        overflow: visible !important;
    }

    .pharmacy-profit-loss-print table {
        width: 100% !important;
        min-width: 0 !important;
        border-collapse: collapse !important;
        font-size: 8pt !important;
    }

    .pharmacy-profit-loss-print th,
    .pharmacy-profit-loss-print td {
        padding: 2mm !important;
        border: 1px solid #9ca3af !important;
        color: #111827 !important;
    }

    .pharmacy-profit-loss-print thead {
        display: table-header-group;
    }

    .pharmacy-profit-loss-print tr {
        break-inside: avoid;
    }
}
</style>
