<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';

const filters = ref({ account_id: '', from: '', to: '', q: '', numOfData: 10 });
const accounts = ref([]);
const transactions = ref([]);
const meta = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0 });
const loading = ref(false);
const selectedTx = ref(null);
const summary = ref({
  assets: 0,
  liabilities: 0,
  income: 0,
  expense: 0,
  net_profit: 0,
});

function formatDate(d) {
  if (!d) return '';
  const dt = new Date(d);
  return dt.toLocaleDateString();
}

function formatAmount(v) {
  return '৳ ' + Number(v || 0).toFixed(2);
}

onMounted(() => {
  const today = new Date();
  const from = new Date();
  from.setDate(today.getDate() - 30);
  filters.value.from = from.toISOString().slice(0, 10);
  filters.value.to = today.toISOString().slice(0, 10);
  loadAccounts();
  loadFinancialSummary();
  fetchTransactions();
});

async function loadAccounts() {
  try {
    const res = await axios.get(route('backend.accounts.list'), { params: { numOfData: 200 } });
    accounts.value = (res.data.data || []).map(a => ({ id: a.id, code: a.code, name: a.name }));
  } catch (err) {
    console.error(err);
  }
}

async function fetchTransactions(page = 1) {
  loading.value = true;
  try {
    const params = { ...filters.value, page };
    const res = await axios.get(route('backend.ledger.list'), { params });
    transactions.value = res.data.data || [];
    meta.value.current_page = res.data.current_page || 1;
    meta.value.last_page = res.data.last_page || 1;
    meta.value.per_page = res.data.per_page || filters.value.numOfData;
    meta.value.total = res.data.total || 0;
  } catch (err) {
    console.error(err);
  } finally {
    loading.value = false;
  }
}

async function loadFinancialSummary() {
  try {
    const res = await axios.get(route('backend.accounts.financial-summary.list'), {
      params: {
        from: filters.value.from || undefined,
        to: filters.value.to || undefined,
        as_of: filters.value.to || undefined,
      },
    });

    summary.value = res?.data?.totals || {
      assets: 0,
      liabilities: 0,
      income: 0,
      expense: 0,
      net_profit: 0,
    };
  } catch (err) {
    console.error(err);
  }
}

function exportLedgerCsv() {
  const query = {
    account_id: filters.value.account_id || undefined,
    from: filters.value.from || undefined,
    to: filters.value.to || undefined,
    q: filters.value.q || undefined,
  };

  const url = route('backend.ledger.export', query);
  window.open(url, '_blank', 'noopener');
}

function viewTx(tx) {
  selectedTx.value = tx;
}

function closeModal() {
  selectedTx.value = null;
}

function totalFor(tx, type) {
  return tx.entries.reduce((s, e) => s + ((e.entry_type === type) ? Number(e.amount) : 0), 0);
}

function goto(page) {
  if (page < 1 || page > meta.value.last_page) return;
  fetchTransactions(page);
}
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 bg-white rounded-md dark:bg-slate-900">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-bold">{{ $page.props.pageTitle }}</h1>
        </div>
        <Link :href="route('backend.dashboard')" class="px-3 py-2 text-sm text-white bg-red-600 rounded hover:bg-red-700">Back</Link>
      </div>
      <div class="mt-3 flex flex-wrap gap-2">
        <Link :href="route('backend.accounts.index')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Chart of Accounts</Link>
        <Link :href="route('backend.accounts.balances')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Balances</Link>
        <Link :href="route('backend.accounts.trial-balance')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Trial Balance</Link>
        <Link :href="route('backend.accounts.profit-loss')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Profit & Loss</Link>
        <Link :href="route('backend.accounts.balance-sheet')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Balance Sheet</Link>
        <Link :href="route('backend.accounts.cash-flow')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Cash Flow</Link>
      </div>

      <div class="mt-4 p-3 border rounded bg-gray-50">
        <div class="grid grid-cols-4 gap-3">
          <div>
            <label class="text-sm">Account</label>
            <select v-model="filters.account_id" class="w-full border rounded p-2">
              <option value="">-- All --</option>
              <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
            </select>
          </div>
          <div>
            <label class="text-sm">From</label>
            <input type="date" v-model="filters.from" class="w-full border rounded p-2" />
          </div>
          <div>
            <label class="text-sm">To</label>
            <input type="date" v-model="filters.to" class="w-full border rounded p-2" />
          </div>
          <div>
            <label class="text-sm">Search</label>
            <input v-model="filters.q" @keyup.enter="fetchTransactions()" placeholder="description, uuid" class="w-full border rounded p-2" />
          </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
          <button @click.prevent="fetchTransactions(); loadFinancialSummary();" class="btn-colorful-sm">Apply</button>
          <button @click.prevent="exportLedgerCsv" class="px-3 py-2 text-sm rounded bg-emerald-600 text-white">Export CSV</button>
        </div>
      </div>

      <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
        <div class="p-3 border rounded bg-white dark:bg-slate-900">
          <div class="text-sm text-gray-500">Total Asset</div>
          <div class="text-lg font-semibold">{{ formatAmount(summary.assets) }}</div>
        </div>
        <div class="p-3 border rounded bg-white dark:bg-slate-900">
          <div class="text-sm text-gray-500">Total Liability</div>
          <div class="text-lg font-semibold">{{ formatAmount(summary.liabilities) }}</div>
        </div>
        <div class="p-3 border rounded bg-white dark:bg-slate-900">
          <div class="text-sm text-gray-500">Total Income</div>
          <div class="text-lg font-semibold">{{ formatAmount(summary.income) }}</div>
        </div>
        <div class="p-3 border rounded bg-white dark:bg-slate-900">
          <div class="text-sm text-gray-500">Total Expense</div>
          <div class="text-lg font-semibold">{{ formatAmount(summary.expense) }}</div>
        </div>
        <div class="p-3 border rounded bg-white dark:bg-slate-900">
          <div class="text-sm text-gray-500">Net Profit</div>
          <div class="text-lg font-semibold" :class="Number(summary.net_profit || 0) >= 0 ? 'text-emerald-600' : 'text-amber-600'">{{ formatAmount(summary.net_profit) }}</div>
        </div>
      </div>

      <div class="mt-4">
        <table class="w-full table-auto border-collapse">
          <thead>
            <tr class="bg-gray-100"><th class="p-2">Date</th><th class="p-2">UUID</th><th class="p-2">Description</th><th class="p-2">Debit</th><th class="p-2">Credit</th><th class="p-2">Entries</th><th class="p-2">Action</th></tr>
          </thead>
          <tbody>
            <tr v-for="tx in transactions" :key="tx.id" class="border-t">
              <td class="p-2">{{ formatDate(tx.date) }}</td>
              <td class="p-2">{{ tx.uuid }}</td>
              <td class="p-2">{{ tx.description }}</td>
              <td class="p-2">{{ formatAmount(totalFor(tx, 'debit')) }}</td>
              <td class="p-2">{{ formatAmount(totalFor(tx, 'credit')) }}</td>
              <td class="p-2">{{ tx.entries.length }}</td>
              <td class="p-2"><button @click.prevent="viewTx(tx)" class="btn-colorful-sm">View</button></td>
            </tr>
            <tr v-if="!transactions.length && !loading"><td class="p-4 text-center" colspan="7">No transactions</td></tr>
          </tbody>
        </table>

        <div class="mt-3 flex items-center justify-between">
          <div>Showing page {{ meta.current_page }} of {{ meta.last_page }} — {{ meta.total }} records</div>
          <div class="space-x-2">
            <button :disabled="meta.current_page<=1" @click="goto(meta.current_page-1)" class="px-2 py-1 border rounded">Prev</button>
            <button :disabled="meta.current_page>=meta.last_page" @click="goto(meta.current_page+1)" class="px-2 py-1 border rounded">Next</button>
          </div>
        </div>
      </div>

      <div v-if="selectedTx" class="fixed inset-0 bg-black/50 flex items-center justify-center">
        <div class="bg-white rounded p-4 w-2/3 max-h-[80vh] overflow-auto">
          <div class="flex justify-between items-center mb-3">
            <div>
              <div class="font-semibold">Transaction: {{ selectedTx.uuid }}</div>
              <div class="text-sm text-gray-600">{{ selectedTx.description }} — {{ formatDate(selectedTx.date) }}</div>
            </div>
            <div><button @click="closeModal" class="btn-colorful-sm">Close</button></div>
          </div>

          <table class="w-full table-auto border-collapse">
            <thead>
              <tr class="bg-gray-100"><th class="p-2">Account</th><th class="p-2">Type</th><th class="p-2">Amount</th></tr>
            </thead>
            <tbody>
              <tr v-for="e in selectedTx.entries" :key="e.id" class="border-t">
                <td class="p-2">{{ e.account ? (e.account.code + ' — ' + e.account.name) : e.account_id }}</td>
                <td class="p-2">{{ e.entry_type }}</td>
                <td class="p-2">{{ formatAmount(e.amount) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </BackendLayout>
</template>

<style scoped></style>
