<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'Monthly Closing & Valuation' },
  month: { type: String, default: '' },
  currentMonth: { type: String, default: '' },
  rows: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({}) },
});

const month = ref(props.month || new Date().toISOString().slice(0, 7));
const rows = computed(() => props.rows ?? []);

const loadMonth = () => {
  router.get(route('backend.stock.monthly-closing'), { month: month.value }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const exportCsv = () => {
  window.location.href = route('backend.stock.monthly-closing', { month: month.value, export: 'csv' });
};

const printReport = () => window.print();
const formatQty = (value) => Number(value ?? 0).toFixed(2);
const formatMoney = (value) => Number(value ?? 0).toFixed(2);
</script>

<template>
  <BackendLayout>
    <div id="monthly-closing-print-area" class="w-full p-4 mt-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
      <div class="monthly-closing-header flex flex-wrap items-center justify-between gap-4 p-5 border border-emerald-100 rounded-xl bg-gradient-to-r from-emerald-50 via-teal-50 to-cyan-50">
        <div>
          <h1 class="text-xl font-bold tracking-tight text-slate-800">{{ pageTitle }}</h1>
          <p class="mt-1 text-sm text-slate-600">Monthly store movement and closing balance by item.</p>
          <p class="monthly-closing-print-month">Report month: {{ month }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <a :href="route('backend.stock.index')" class="px-3 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg shadow-sm transition hover:bg-red-700 print:hidden">Back</a>
          <label for="closing-month" class="sr-only">Report month</label>
          <input id="closing-month" v-model="month" type="month" :max="currentMonth" class="p-2 text-sm border border-slate-300 rounded-lg bg-white focus:border-emerald-500 focus:ring-emerald-500 print:hidden" @change="loadMonth" />
          <button type="button" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg shadow-sm transition hover:bg-indigo-700 print:hidden" @click="printReport">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8V3h10v5M7 17H5a2 2 0 01-2-2v-5a2 2 0 012-2h14a2 2 0 012 2v5a2 2 0 01-2 2h-2m-10-4h10v8H7z" /></svg>
            Print
          </button>
          <button type="button" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded-lg shadow-sm transition hover:bg-emerald-700 print:hidden" @click="exportCsv">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M5 17v3h14v-3" /></svg>
            Export CSV
          </button>
        </div>
      </div>

      <div class="monthly-closing-summary grid grid-cols-1 gap-3 mt-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Opening Stock</p>
          <p class="mt-2 text-2xl font-bold tabular-nums text-slate-800">{{ formatQty(summary.total_opening_qty) }}</p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800">Received This Month</p>
          <p class="mt-2 text-2xl font-bold tabular-nums text-emerald-950">{{ formatQty(summary.total_received_qty) }}</p>
        </div>
        <div class="rounded-xl border border-rose-200 bg-rose-50/60 p-4 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-rose-800">Issued / Used</p>
          <p class="mt-2 text-2xl font-bold tabular-nums text-rose-950">{{ formatQty(summary.total_issued_qty) }}</p>
        </div>
        <div class="rounded-xl border border-sky-200 bg-sky-50/60 p-4 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-sky-800">Closing Stock</p>
          <p class="mt-2 text-2xl font-bold tabular-nums text-sky-950">{{ formatQty(summary.total_closing_qty) }}</p>
        </div>
        <div class="rounded-xl border border-violet-200 bg-violet-50/60 p-4 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-violet-800">Closing Value</p>
          <p class="mt-2 text-2xl font-bold tabular-nums text-violet-950">{{ formatMoney(summary.total_closing_value) }}</p>
        </div>
      </div>

      <div class="mt-4 flex flex-wrap items-center justify-between gap-2 print:mt-3">
        <div>
          <h2 class="text-base font-semibold text-slate-800">Item-wise monthly statement</h2>
          <p class="monthly-closing-explanation mt-1 text-xs text-slate-500">Closing quantity is reconciled from current stock and all recorded movements dated from the selected month onward.</p>
        </div>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ rows.length }} items</span>
      </div>

      <div class="monthly-closing-table w-full mt-3 overflow-x-auto rounded-xl border border-slate-200">
        <table class="w-full min-w-[1200px] text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="whitespace-nowrap border-b border-slate-200 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Code</th>
              <th class="whitespace-nowrap border-b border-slate-200 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Item</th>
              <th class="whitespace-nowrap border-b border-slate-200 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Unit</th>
              <th class="whitespace-nowrap border-b border-slate-200 px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-600">Opening</th>
              <th class="whitespace-nowrap border-b border-slate-200 px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-600">Received</th>
              <th class="whitespace-nowrap border-b border-slate-200 px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-600">Received Value</th>
              <th class="whitespace-nowrap border-b border-slate-200 px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-600">Issued / Used</th>
              <th class="whitespace-nowrap border-b border-slate-200 px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-600">Issued Value</th>
              <th class="whitespace-nowrap border-b border-slate-200 px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-600">Closing</th>
              <th class="whitespace-nowrap border-b border-slate-200 px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-600">Unit Cost</th>
              <th class="whitespace-nowrap border-b border-slate-200 px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-600">Closing Value</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, index) in rows" :key="`${row.item_code}-${row.item_name}`" class="transition-colors hover:bg-emerald-50/60" :class="index % 2 ? 'bg-slate-50/50' : 'bg-white'">
              <td class="whitespace-nowrap border-b border-slate-100 px-4 py-3 font-medium text-slate-600">{{ row.item_code || '-' }}</td>
              <td class="border-b border-slate-100 px-4 py-3 font-semibold text-slate-800">{{ row.item_name }}</td>
              <td class="whitespace-nowrap border-b border-slate-100 px-4 py-3 text-slate-600">{{ row.unit }}</td>
              <td class="whitespace-nowrap border-b border-slate-100 px-4 py-3 text-right tabular-nums text-slate-700">{{ formatQty(row.opening_qty) }}</td>
              <td class="whitespace-nowrap border-b border-slate-100 px-4 py-3 text-right font-semibold tabular-nums text-emerald-700">{{ formatQty(row.received_qty) }}</td>
              <td class="whitespace-nowrap border-b border-slate-100 px-4 py-3 text-right tabular-nums text-slate-600">{{ formatMoney(row.received_value) }}</td>
              <td class="whitespace-nowrap border-b border-slate-100 px-4 py-3 text-right font-semibold tabular-nums text-rose-700">{{ formatQty(row.issued_qty) }}</td>
              <td class="whitespace-nowrap border-b border-slate-100 px-4 py-3 text-right tabular-nums text-slate-600">{{ formatMoney(row.issued_value) }}</td>
              <td class="whitespace-nowrap border-b border-slate-100 px-4 py-3 text-right font-bold tabular-nums text-sky-800">{{ formatQty(row.closing_qty) }}</td>
              <td class="whitespace-nowrap border-b border-slate-100 px-4 py-3 text-right tabular-nums text-slate-600">{{ formatMoney(row.unit_cost) }}</td>
              <td class="whitespace-nowrap border-b border-slate-100 px-4 py-3 text-right font-semibold tabular-nums text-slate-800">{{ formatMoney(row.closing_value) }}</td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="11" class="px-4 py-14 text-center text-slate-500">
                <p class="font-semibold text-slate-700">No stock items found</p>
                <p class="mt-1 text-sm">Items and their monthly movements will appear here.</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </BackendLayout>
</template>

<style>
@page {
  size: A4 landscape;
  margin: 12mm;
}

.monthly-closing-print-month {
  display: none;
}

@media print {
  html,
  body {
    width: 100% !important;
    min-width: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
    background: #fff !important;
  }

  body * {
    visibility: hidden !important;
  }

  #monthly-closing-print-area,
  #monthly-closing-print-area * {
    visibility: visible !important;
  }

  #monthly-closing-print-area {
    position: absolute;
    top: 0;
    left: 0;
    width: 100% !important;
    max-width: none !important;
    margin: 0 !important;
    padding: 0 1mm !important;
    border: 0 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    background: #fff !important;
    color: #111827 !important;
    font-family: Arial, Helvetica, sans-serif !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  #monthly-closing-print-area .monthly-closing-header {
    display: flex !important;
    flex-wrap: nowrap !important;
    min-height: 0 !important;
    justify-content: space-between !important;
    margin: 0 0 5mm !important;
    padding: 4mm 5mm !important;
    border: 1px solid #94a3b8 !important;
    border-radius: 0 !important;
    background: #f8fafc !important;
  }

  #monthly-closing-print-area h1 {
    margin: 0 !important;
    font-size: 17pt !important;
    line-height: 1.2 !important;
  }

  #monthly-closing-print-area .monthly-closing-header > div:first-child > p {
    margin-top: 1mm !important;
    font-size: 9pt !important;
    color: #475569 !important;
  }

  #monthly-closing-print-area .monthly-closing-print-month {
    display: block !important;
    margin-top: 1mm !important;
    font-size: 9pt !important;
    font-weight: 700 !important;
    color: #111827 !important;
  }

  #monthly-closing-print-area .monthly-closing-summary {
    display: grid !important;
    grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
    gap: 3mm !important;
    margin: 0 0 5mm !important;
  }

  #monthly-closing-print-area .monthly-closing-summary > div {
    min-width: 0 !important;
    padding: 3mm !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 2mm !important;
    background: #fff !important;
    box-shadow: none !important;
  }

  #monthly-closing-print-area .monthly-closing-summary p:first-child {
    font-size: 8pt !important;
    line-height: 1.2 !important;
    color: #475569 !important;
  }

  #monthly-closing-print-area .monthly-closing-summary p:nth-child(2) {
    margin-top: 2mm !important;
    font-size: 14pt !important;
    line-height: 1.15 !important;
    overflow-wrap: anywhere !important;
  }

  #monthly-closing-print-area h2 {
    font-size: 12pt !important;
  }

  #monthly-closing-print-area .monthly-closing-explanation,
  #monthly-closing-print-area .monthly-closing-table + * {
    display: none !important;
  }

  #monthly-closing-print-area .monthly-closing-table {
    width: 100% !important;
    margin-top: 2mm !important;
    overflow: visible !important;
    border-radius: 0 !important;
    border-color: #94a3b8 !important;
  }

  #monthly-closing-print-area table {
    min-width: 0 !important;
    width: 100% !important;
    table-layout: fixed !important;
    border-collapse: collapse !important;
    font-size: 8pt !important;
  }

  #monthly-closing-print-area th:nth-child(1) { width: 8%; }
  #monthly-closing-print-area th:nth-child(2) { width: 14%; }
  #monthly-closing-print-area th:nth-child(3) { width: 6%; }
  #monthly-closing-print-area th:nth-child(n+4) { width: 9%; }

  #monthly-closing-print-area th {
    font-size: 7.5pt !important;
    white-space: normal !important;
    background: #e2e8f0 !important;
  }

  #monthly-closing-print-area thead {
    display: table-header-group;
  }

  #monthly-closing-print-area tr {
    break-inside: avoid !important;
    page-break-inside: avoid !important;
  }

  #monthly-closing-print-area th,
  #monthly-closing-print-area td {
    padding: 2.2mm 2mm !important;
    color: #111827 !important;
    border: 1px solid #cbd5e1 !important;
    background: #fff !important;
  }

  #monthly-closing-print-area td:nth-child(n+4) {
    text-align: right !important;
    font-variant-numeric: tabular-nums !important;
  }
}
</style>
