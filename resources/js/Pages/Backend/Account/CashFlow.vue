<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { Link } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const loading = ref(false);
const filters = ref({
  from: '',
  to: new Date().toISOString().slice(0, 10),
});

const rows = ref([]);
const totals = ref({ inflow: 0, outflow: 0, net: 0 });

const isPositive = computed(() => Number(totals.value.net || 0) >= 0);

function formatAmount(value) {
  return Number(value || 0).toFixed(2);
}

async function loadReport() {
  loading.value = true;
  try {
    const res = await axios.get(route('backend.accounts.cash-flow.list'), {
      params: {
        from: filters.value.from || undefined,
        to: filters.value.to || undefined,
      },
    });

    rows.value = res?.data?.rows || [];
    totals.value = res?.data?.totals || { inflow: 0, outflow: 0, net: 0 };
  } catch (err) {
    console.error(err);
    rows.value = [];
    totals.value = { inflow: 0, outflow: 0, net: 0 };
  } finally {
    loading.value = false;
  }
}

function resetFilters() {
  filters.value.from = '';
  filters.value.to = new Date().toISOString().slice(0, 10);
  loadReport();
}

function exportCsv() {
  const url = route('backend.accounts.cash-flow.list', {
    from: filters.value.from || undefined,
    to: filters.value.to || undefined,
    format: 'csv',
  });

  window.open(url, '_blank', 'noopener');
}

onMounted(() => {
  loadReport();
});
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 bg-white rounded-md dark:bg-slate-900">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-bold">{{ $page.props.pageTitle }}</h1>
          <p class="text-sm text-gray-600 dark:text-gray-300">Cash and bank account movement summary by period.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <Link :href="route('backend.dashboard')" class="px-3 py-2 text-sm text-white bg-red-600 rounded hover:bg-red-700">Back</Link>
          <Link :href="route('backend.accounts.index')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Chart of Accounts</Link>
          <Link :href="route('backend.ledger.index')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Ledger</Link>
          <Link :href="route('backend.accounts.trial-balance')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Trial Balance</Link>
          <Link :href="route('backend.accounts.profit-loss')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Profit & Loss</Link>
          <Link :href="route('backend.accounts.balance-sheet')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Balance Sheet</Link>
        </div>
      </div>

      <div class="mt-4 p-3 border rounded bg-gray-50 dark:bg-slate-800/40 dark:border-slate-700">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div>
            <label class="text-sm">From Date</label>
            <input v-model="filters.from" type="date" class="w-full border rounded p-2 dark:bg-slate-700 dark:border-slate-600" />
          </div>
          <div>
            <label class="text-sm">To Date</label>
            <input v-model="filters.to" type="date" class="w-full border rounded p-2 dark:bg-slate-700 dark:border-slate-600" />
          </div>
          <div class="flex items-end gap-2">
            <button @click="loadReport" class="btn-colorful-sm">Apply</button>
            <button @click="resetFilters" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Reset</button>
            <button @click="exportCsv" class="px-3 py-2 text-sm rounded bg-emerald-600 text-white">Export CSV</button>
          </div>
        </div>
      </div>

      <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
        <div class="p-3 rounded border dark:border-slate-700">
          <div class="text-gray-500">Total Inflow</div>
          <div class="text-lg font-semibold">{{ formatAmount(totals.inflow) }}</div>
        </div>
        <div class="p-3 rounded border dark:border-slate-700">
          <div class="text-gray-500">Total Outflow</div>
          <div class="text-lg font-semibold">{{ formatAmount(totals.outflow) }}</div>
        </div>
        <div class="p-3 rounded border dark:border-slate-700" :class="isPositive ? 'border-emerald-500' : 'border-amber-500'">
          <div class="text-gray-500">Net Cash Flow</div>
          <div class="text-lg font-semibold" :class="isPositive ? 'text-emerald-600' : 'text-amber-600'">{{ formatAmount(totals.net) }}</div>
        </div>
      </div>

      <div class="mt-4 overflow-x-auto border rounded dark:border-slate-700">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
          <thead class="bg-gray-50 dark:bg-slate-800">
            <tr>
              <th class="px-4 py-2 text-left">Date</th>
              <th class="px-4 py-2 text-left">UUID</th>
              <th class="px-4 py-2 text-left">Description</th>
              <th class="px-4 py-2 text-left">Reference</th>
              <th class="px-4 py-2 text-right">Inflow</th>
              <th class="px-4 py-2 text-right">Outflow</th>
              <th class="px-4 py-2 text-right">Net</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-slate-900 divide-y divide-gray-200 dark:divide-slate-700">
            <tr v-for="row in rows" :key="row.transaction_id">
              <td class="px-4 py-2">{{ row.date }}</td>
              <td class="px-4 py-2">{{ row.uuid }}</td>
              <td class="px-4 py-2">{{ row.description }}</td>
              <td class="px-4 py-2">{{ row.reference_type }}</td>
              <td class="px-4 py-2 text-right">{{ formatAmount(row.inflow) }}</td>
              <td class="px-4 py-2 text-right">{{ formatAmount(row.outflow) }}</td>
              <td class="px-4 py-2 text-right">{{ formatAmount(row.net) }}</td>
            </tr>
            <tr v-if="!loading && rows.length === 0">
              <td colspan="7" class="px-4 py-8 text-center text-gray-500">No cash-flow rows found.</td>
            </tr>
            <tr v-if="loading">
              <td colspan="7" class="px-4 py-8 text-center text-gray-500">Loading...</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </BackendLayout>
</template>

<style scoped></style>
