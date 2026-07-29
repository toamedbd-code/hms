<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { Link } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const loading = ref(false);
const asOf = ref(new Date().toISOString().slice(0, 10));

const assets = ref([]);
const liabilities = ref([]);
const equity = ref([]);
const totals = ref({ assets: 0, liabilities: 0, equity: 0, liabilities_and_equity: 0, difference: 0 });

const isBalanced = computed(() => Number(totals.value.difference || 0) === 0);

function formatAmount(v) {
  return Number(v || 0).toFixed(2);
}

async function loadReport() {
  loading.value = true;
  try {
    const res = await axios.get(route('backend.accounts.balance-sheet.list'), { params: { as_of: asOf.value } });
    assets.value = res?.data?.assets || [];
    liabilities.value = res?.data?.liabilities || [];
    equity.value = res?.data?.equity || [];
    totals.value = res?.data?.totals || { assets: 0, liabilities: 0, equity: 0, liabilities_and_equity: 0, difference: 0 };
  } catch (err) {
    console.error(err);
    assets.value = [];
    liabilities.value = [];
    equity.value = [];
    totals.value = { assets: 0, liabilities: 0, equity: 0, liabilities_and_equity: 0, difference: 0 };
  } finally {
    loading.value = false;
  }
}

function exportCsv() {
  const url = route('backend.accounts.balance-sheet.list', { as_of: asOf.value, format: 'csv' });
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
          <p class="text-sm text-gray-600 dark:text-gray-300">Snapshot of Assets, Liabilities and Equity as of a date.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">          <Link :href="route('backend.dashboard')" class="px-3 py-2 text-sm text-white bg-red-600 rounded hover:bg-red-700">Back</Link>          <Link :href="route('backend.accounts.index')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Chart of Accounts</Link>
          <Link :href="route('backend.accounts.trial-balance')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Trial Balance</Link>
          <Link :href="route('backend.accounts.profit-loss')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Profit & Loss</Link>
          <Link :href="route('backend.accounts.cash-flow')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Cash Flow</Link>
        </div>
      </div>

      <div class="mt-4 p-3 border rounded bg-gray-50 dark:bg-slate-800/40 dark:border-slate-700">
        <div class="flex flex-wrap items-end gap-2">
          <div>
            <label class="text-sm">As Of Date</label>
            <input v-model="asOf" type="date" class="w-full border rounded p-2 dark:bg-slate-700 dark:border-slate-600" />
          </div>
          <button @click="loadReport" class="btn-colorful-sm">Apply</button>
          <button @click="exportCsv" class="px-3 py-2 text-sm rounded bg-emerald-600 text-white">Export CSV</button>
        </div>
      </div>

      <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
        <div class="p-3 rounded border dark:border-slate-700">
          <div class="text-gray-500">Total Assets</div>
          <div class="text-lg font-semibold">{{ formatAmount(totals.assets) }}</div>
        </div>
        <div class="p-3 rounded border dark:border-slate-700">
          <div class="text-gray-500">Total Liabilities + Equity</div>
          <div class="text-lg font-semibold">{{ formatAmount(totals.liabilities_and_equity) }}</div>
        </div>
        <div class="p-3 rounded border dark:border-slate-700" :class="isBalanced ? 'border-emerald-500' : 'border-amber-500'">
          <div class="text-gray-500">Difference</div>
          <div class="text-lg font-semibold" :class="isBalanced ? 'text-emerald-600' : 'text-amber-600'">{{ formatAmount(totals.difference) }}</div>
        </div>
      </div>

      <div class="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="border rounded dark:border-slate-700 overflow-x-auto">
          <div class="px-4 py-2 font-semibold bg-gray-50 dark:bg-slate-800">Assets</div>
          <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
            <thead class="bg-gray-50 dark:bg-slate-800">
              <tr>
                <th class="px-4 py-2 text-left">Code</th>
                <th class="px-4 py-2 text-left">Name</th>
                <th class="px-4 py-2 text-right">Amount</th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-slate-900 divide-y divide-gray-200 dark:divide-slate-700">
              <tr v-for="row in assets" :key="`asset-${row.account_id || row.code}`">
                <td class="px-4 py-2">{{ row.code }}</td>
                <td class="px-4 py-2">{{ row.name }}</td>
                <td class="px-4 py-2 text-right">{{ formatAmount(row.amount) }}</td>
              </tr>
              <tr v-if="!loading && assets.length === 0"><td colspan="3" class="px-4 py-6 text-center text-gray-500">No asset rows</td></tr>
            </tbody>
          </table>
        </div>

        <div class="space-y-4">
          <div class="border rounded dark:border-slate-700 overflow-x-auto">
            <div class="px-4 py-2 font-semibold bg-gray-50 dark:bg-slate-800">Liabilities</div>
            <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
              <thead class="bg-gray-50 dark:bg-slate-800">
                <tr>
                  <th class="px-4 py-2 text-left">Code</th>
                  <th class="px-4 py-2 text-left">Name</th>
                  <th class="px-4 py-2 text-right">Amount</th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-slate-900 divide-y divide-gray-200 dark:divide-slate-700">
                <tr v-for="row in liabilities" :key="`liab-${row.account_id || row.code}`">
                  <td class="px-4 py-2">{{ row.code }}</td>
                  <td class="px-4 py-2">{{ row.name }}</td>
                  <td class="px-4 py-2 text-right">{{ formatAmount(row.amount) }}</td>
                </tr>
                <tr v-if="!loading && liabilities.length === 0"><td colspan="3" class="px-4 py-6 text-center text-gray-500">No liability rows</td></tr>
              </tbody>
            </table>
          </div>

          <div class="border rounded dark:border-slate-700 overflow-x-auto">
            <div class="px-4 py-2 font-semibold bg-gray-50 dark:bg-slate-800">Equity</div>
            <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
              <thead class="bg-gray-50 dark:bg-slate-800">
                <tr>
                  <th class="px-4 py-2 text-left">Code</th>
                  <th class="px-4 py-2 text-left">Name</th>
                  <th class="px-4 py-2 text-right">Amount</th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-slate-900 divide-y divide-gray-200 dark:divide-slate-700">
                <tr v-for="row in equity" :key="`eq-${row.account_id || row.code}`">
                  <td class="px-4 py-2">{{ row.code }}</td>
                  <td class="px-4 py-2">{{ row.name }}</td>
                  <td class="px-4 py-2 text-right">{{ formatAmount(row.amount) }}</td>
                </tr>
                <tr v-if="!loading && equity.length === 0"><td colspan="3" class="px-4 py-6 text-center text-gray-500">No equity rows</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </BackendLayout>
</template>

<style scoped></style>
