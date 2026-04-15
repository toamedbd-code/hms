<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'Monthly Closing & Valuation' },
  month: { type: String, default: '' },
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
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-2 p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
        <div class="flex items-center gap-2">
          <input v-model="month" type="month" class="p-2 text-sm border rounded" @change="loadMonth" />
          <button type="button" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700" @click="exportCsv">Export CSV</button>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3">
        <div class="p-3 border rounded bg-slate-50">
          <p class="text-xs text-slate-500">Total Received Qty</p>
          <p class="text-lg font-semibold text-slate-800 mt-1">{{ Number(summary.total_received_qty ?? 0).toFixed(2) }}</p>
        </div>
        <div class="p-3 border rounded bg-slate-50">
          <p class="text-xs text-slate-500">Total Issued Qty</p>
          <p class="text-lg font-semibold text-slate-800 mt-1">{{ Number(summary.total_issued_qty ?? 0).toFixed(2) }}</p>
        </div>
        <div class="p-3 border rounded bg-slate-50">
          <p class="text-xs text-slate-500">Total Closing Value</p>
          <p class="text-lg font-semibold text-slate-800 mt-1">{{ Number(summary.total_closing_value ?? 0).toFixed(2) }}</p>
        </div>
      </div>

      <div class="w-full mt-3 overflow-x-auto">
        <table class="w-full text-sm border border-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 border">Code</th>
              <th class="px-3 py-2 border text-left">Item</th>
              <th class="px-3 py-2 border">Unit</th>
              <th class="px-3 py-2 border">Opening</th>
              <th class="px-3 py-2 border">Received</th>
              <th class="px-3 py-2 border">Issued</th>
              <th class="px-3 py-2 border">Closing</th>
              <th class="px-3 py-2 border">Unit Cost</th>
              <th class="px-3 py-2 border">Closing Value</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="`${row.item_code}-${row.item_name}`" class="hover:bg-gray-50">
              <td class="px-3 py-2 border text-center">{{ row.item_code || '-' }}</td>
              <td class="px-3 py-2 border">{{ row.item_name }}</td>
              <td class="px-3 py-2 border text-center">{{ row.unit }}</td>
              <td class="px-3 py-2 border text-right">{{ Number(row.opening_qty).toFixed(2) }}</td>
              <td class="px-3 py-2 border text-right">{{ Number(row.received_qty).toFixed(2) }}</td>
              <td class="px-3 py-2 border text-right">{{ Number(row.issued_qty).toFixed(2) }}</td>
              <td class="px-3 py-2 border text-right">{{ Number(row.closing_qty).toFixed(2) }}</td>
              <td class="px-3 py-2 border text-right">{{ Number(row.unit_cost).toFixed(2) }}</td>
              <td class="px-3 py-2 border text-right">{{ Number(row.closing_value).toFixed(2) }}</td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="9" class="px-3 py-6 text-center text-gray-500 border">No data found.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </BackendLayout>
</template>
