<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { Link } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const rows = ref([]);
const loading = ref(false);
const filters = ref({
  q: '',
  type: 'all',
});
const totals = ref({
  debit: 0,
  credit: 0,
  difference: 0,
});

const isBalanced = computed(() => Number(totals.value.difference || 0) === 0);

function formatAmount(value) {
  return Number(value || 0).toFixed(2);
}

async function loadTrialBalance() {
  loading.value = true;
  try {
    const params = {
      q: filters.value.q || undefined,
      type: filters.value.type || 'all',
    };

    const res = await axios.get(route('backend.accounts.trial-balance.list'), { params });
    rows.value = res?.data?.rows || [];
    totals.value = res?.data?.totals || { debit: 0, credit: 0, difference: 0 };
  } catch (err) {
    console.error(err);
    rows.value = [];
    totals.value = { debit: 0, credit: 0, difference: 0 };
  } finally {
    loading.value = false;
  }
}

function resetFilters() {
  filters.value.q = '';
  filters.value.type = 'all';
  loadTrialBalance();
}

function exportCsv() {
  const query = {
    q: filters.value.q || undefined,
    type: filters.value.type || 'all',
    format: 'csv',
  };

  const url = route('backend.accounts.trial-balance.list', query);
  window.open(url, '_blank', 'noopener');
}

onMounted(() => {
  loadTrialBalance();
});
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 bg-white rounded-md dark:bg-slate-900">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-bold">{{ $page.props.pageTitle }}</h1>
          <p class="text-sm text-gray-600 dark:text-gray-300">Debit/Credit equality check with account-wise summary.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <Link :href="route('backend.accounts.index')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Chart of Accounts</Link>
          <Link :href="route('backend.accounts.balances')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Balances</Link>
          <Link :href="route('backend.ledger.index')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Ledger</Link>
          <Link :href="route('backend.accounts.profit-loss')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Profit & Loss</Link>
          <Link :href="route('backend.accounts.balance-sheet')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Balance Sheet</Link>
          <Link :href="route('backend.accounts.cash-flow')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Cash Flow</Link>
        </div>
      </div>

      <div class="mt-4 p-3 border rounded bg-gray-50 dark:bg-slate-800/40 dark:border-slate-700">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div>
            <label class="text-sm">Search (Code/Name)</label>
            <input
              v-model="filters.q"
              @keyup.enter="loadTrialBalance"
              type="text"
              placeholder="e.g. CASH"
              class="w-full border rounded p-2 dark:bg-slate-700 dark:border-slate-600"
            />
          </div>
          <div>
            <label class="text-sm">Type</label>
            <select v-model="filters.type" class="w-full border rounded p-2 dark:bg-slate-700 dark:border-slate-600">
              <option value="all">All</option>
              <option value="asset">Asset</option>
              <option value="liability">Liability</option>
              <option value="equity">Equity</option>
              <option value="income">Income</option>
              <option value="expense">Expense</option>
            </select>
          </div>
          <div class="flex items-end gap-2">
            <button @click="loadTrialBalance" class="btn-colorful-sm">Apply</button>
            <button @click="resetFilters" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Reset</button>
            <button @click="exportCsv" class="px-3 py-2 text-sm rounded bg-emerald-600 text-white">Export CSV</button>
          </div>
        </div>
      </div>

      <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
        <div class="p-3 rounded border dark:border-slate-700">
          <div class="text-gray-500">Total Debit</div>
          <div class="text-lg font-semibold">{{ formatAmount(totals.debit) }}</div>
        </div>
        <div class="p-3 rounded border dark:border-slate-700">
          <div class="text-gray-500">Total Credit</div>
          <div class="text-lg font-semibold">{{ formatAmount(totals.credit) }}</div>
        </div>
        <div class="p-3 rounded border dark:border-slate-700" :class="isBalanced ? 'border-emerald-500' : 'border-amber-500'">
          <div class="text-gray-500">Difference (Debit - Credit)</div>
          <div class="text-lg font-semibold" :class="isBalanced ? 'text-emerald-600' : 'text-amber-600'">
            {{ formatAmount(totals.difference) }}
          </div>
        </div>
      </div>

      <div class="mt-4 overflow-x-auto border rounded dark:border-slate-700">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
          <thead class="bg-gray-50 dark:bg-slate-800">
            <tr>
              <th class="px-4 py-2 text-left">Code</th>
              <th class="px-4 py-2 text-left">Name</th>
              <th class="px-4 py-2 text-left">Type</th>
              <th class="px-4 py-2 text-right">Debit</th>
              <th class="px-4 py-2 text-right">Credit</th>
              <th class="px-4 py-2 text-right">Net</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-slate-900 divide-y divide-gray-200 dark:divide-slate-700">
            <tr v-for="row in rows" :key="row.account_id">
              <td class="px-4 py-2">{{ row.code }}</td>
              <td class="px-4 py-2">{{ row.name }}</td>
              <td class="px-4 py-2 capitalize">{{ row.type }}</td>
              <td class="px-4 py-2 text-right">{{ formatAmount(row.debit) }}</td>
              <td class="px-4 py-2 text-right">{{ formatAmount(row.credit) }}</td>
              <td class="px-4 py-2 text-right">{{ formatAmount(row.net) }}</td>
            </tr>
            <tr v-if="!loading && rows.length === 0">
              <td colspan="6" class="px-4 py-8 text-center text-gray-500">No trial balance data found.</td>
            </tr>
            <tr v-if="loading">
              <td colspan="6" class="px-4 py-8 text-center text-gray-500">Loading...</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </BackendLayout>
</template>

<style scoped></style>
