<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { ref, computed, onMounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';

const page = usePage();
const accountsProp = page.props?.accounts || [];
const form = ref({ code: '', name: '', type: 'asset', parent_id: '' });
const loading = ref(false);
const savingOpening = ref(false);
const postingOpening = ref(false);
const openingStatusLoading = ref(false);
const openingHistoryLoading = ref(false);
const openingStatus = ref({
  is_posted: false,
  locked: false,
  last_posting: null,
  history_preview: [],
});
const openingHistoryRows = ref([]);
const openingHistoryMeta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 10 });
const historyFilters = ref({
  posting_type: 'all',
  from_date: '',
  to_date: '',
  q: '',
  sort: 'newest',
});
const selectedHistoryRow = ref(null);
const repostMode = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const flatAccounts = computed(() => {
  const out = [];
  accountsProp.forEach(a => {
    out.push({ id: a.id, name: a.name, code: a.code });
    if (a.children && a.children.length) {
      a.children.forEach(c => out.push({ id: c.id, name: `${a.name} > ${c.name}`, code: c.code }));
    }
  });
  return out;
});

const openingRows = ref([]);

const appendOpeningRows = (items, depth = 0) => {
  items.forEach((acc) => {
    const hasChildren = Array.isArray(acc.children) && acc.children.length > 0;
    const suggestedType = ['asset', 'expense'].includes(acc.type) ? 'debit' : 'credit';

    openingRows.value.push({
      account_id: acc.id,
      code: acc.code,
      name: acc.name,
      type: acc.type,
      depth,
      has_children: hasChildren,
      opening_balance: Number(acc.opening_balance || 0),
      opening_balance_type: acc.opening_balance_type || suggestedType,
    });

    if (hasChildren) {
      appendOpeningRows(acc.children, depth + 1);
    }
  });
};

appendOpeningRows(accountsProp);

const openingLeafRows = computed(() => openingRows.value.filter((row) => !row.has_children));
const accountLookup = computed(() => {
  const map = new Map();
  openingRows.value.forEach((row) => {
    map.set(row.account_id, {
      code: row.code,
      name: row.name,
    });
  });
  return map;
});
const selectedSnapshotLines = computed(() => {
  const snapshot = selectedHistoryRow.value?.snapshot;
  if (!Array.isArray(snapshot)) {
    return [];
  }

  return snapshot.map((line, index) => {
    const accountMeta = accountLookup.value.get(line.account_id) || null;
    const fallbackCode = line.account_id ? `#${line.account_id}` : '-';
    return {
      index: index + 1,
      account_id: line.account_id,
      code: line.account_code || accountMeta?.code || fallbackCode,
      name: line.account_name || accountMeta?.name || '-',
      entry_type: line.entry_type || '-',
      amount: Number(line.amount || 0),
    };
  });
});

const openingDebitTotal = computed(() => {
  return openingLeafRows.value.reduce((sum, row) => {
    const amount = Number(row.opening_balance || 0);
    if (amount <= 0) return sum;
    return row.opening_balance_type === 'debit' ? sum + amount : sum;
  }, 0);
});

const openingCreditTotal = computed(() => {
  return openingLeafRows.value.reduce((sum, row) => {
    const amount = Number(row.opening_balance || 0);
    if (amount <= 0) return sum;
    return row.opening_balance_type === 'credit' ? sum + amount : sum;
  }, 0);
});

const openingDifference = computed(() => Number((openingDebitTotal.value - openingCreditTotal.value).toFixed(2)));
const isOpeningLocked = computed(() => Boolean(openingStatus.value?.locked) && !repostMode.value);

onMounted(() => {
  const initialPage = hydrateHistoryFiltersFromUrl();
  fetchOpeningStatus();
  fetchOpeningHistory(initialPage);
});

async function saveAccount() {
  loading.value = true;
  try {
    const payload = { ...form.value };
    if (payload.parent_id === '') delete payload.parent_id;

    if (isEditing.value && editingId.value) {
      await axios.put(route('backend.accounts.update', editingId.value), payload);
    } else {
      await axios.post(route('backend.accounts.store'), payload);
    }

    window.location.reload();
  } catch (err) {
    console.error(err);
    alert(err?.response?.data?.message || 'Failed to save account');
  } finally {
    loading.value = false;
  }
}

function editAccount(acc) {
  isEditing.value = true;
  editingId.value = acc.id;
  form.value.code = acc.code || '';
  form.value.name = acc.name || '';
  form.value.type = acc.type || 'asset';
  form.value.parent_id = acc.parent ? acc.parent.id : '';
}

function cancelEdit() {
  isEditing.value = false;
  editingId.value = null;
  form.value = { code: '', name: '', type: 'asset', parent_id: '' };
}

async function deleteAccount(id) {
  if (!confirm('Are you sure you want to delete this account?')) return;
  try {
    await axios.delete(route('backend.accounts.destroy', id));
    window.location.reload();
  } catch (err) {
    console.error(err);
    alert(err?.response?.data?.message || 'Failed to delete account');
  }
}

async function saveOpeningBalances() {
  if (isOpeningLocked.value) {
    alert('Opening balances are locked. Enable repost mode first.');
    return;
  }

  savingOpening.value = true;
  try {
    await axios.post(route('backend.accounts.opening-balances.save'), {
      rows: openingLeafRows.value.map((row) => ({
        account_id: row.account_id,
        opening_balance: Number(row.opening_balance || 0),
        opening_balance_type: row.opening_balance_type,
      })),
      repost: repostMode.value,
    });

    alert('Opening balances saved successfully.');
    await fetchOpeningStatus();
    await fetchOpeningHistory(1);
  } catch (err) {
    console.error(err);
    alert(err?.response?.data?.message || 'Failed to save opening balances');
  } finally {
    savingOpening.value = false;
  }
}

async function postOpeningToLedger() {
  if (isOpeningLocked.value) {
    alert('Opening balances are locked. Enable repost mode first.');
    return;
  }

  const confirmText = repostMode.value
    ? 'Repost opening balances to ledger now? Existing opening posting will be replaced.'
    : 'Post opening balances to ledger now?';

  if (!confirm(confirmText)) {
    return;
  }

  postingOpening.value = true;
  try {
    await axios.post(route('backend.accounts.opening-balances.post'), {
      posting_date: new Date().toISOString().slice(0, 10),
      repost: repostMode.value,
    });

    alert('Opening balances posted to ledger successfully.');
    repostMode.value = false;
    await fetchOpeningStatus();
    await fetchOpeningHistory(1);
    window.location.reload();
  } catch (err) {
    console.error(err);
    alert(err?.response?.data?.message || 'Failed to post opening balances');
  } finally {
    postingOpening.value = false;
  }
}

async function fetchOpeningStatus() {
  openingStatusLoading.value = true;
  try {
    const res = await axios.get(route('backend.accounts.opening-balances.status'));
    openingStatus.value = res.data || { is_posted: false, locked: false, last_posting: null };
  } catch (err) {
    console.error(err);
    openingStatus.value = { is_posted: false, locked: false, last_posting: null };
  } finally {
    openingStatusLoading.value = false;
  }
}

async function fetchOpeningHistory(page = 1) {
  openingHistoryLoading.value = true;
  try {
    const res = await axios.get(route('backend.accounts.opening-balances.history'), {
      params: buildOpeningHistoryParams(page),
    });

    openingHistoryRows.value = res?.data?.data || [];
    openingHistoryMeta.value = {
      current_page: res?.data?.current_page || 1,
      last_page: res?.data?.last_page || 1,
      total: res?.data?.total || 0,
      per_page: res?.data?.per_page || 10,
    };

    syncHistoryFiltersToUrl(openingHistoryMeta.value.current_page || page || 1);
  } catch (err) {
    console.error(err);
    openingHistoryRows.value = [];
  } finally {
    openingHistoryLoading.value = false;
  }
}

function buildOpeningHistoryParams(page = 1) {
  const params = {
    numOfData: openingHistoryMeta.value.per_page || 10,
    page,
  };

  if (historyFilters.value.posting_type && historyFilters.value.posting_type !== 'all') {
    params.posting_type = historyFilters.value.posting_type;
  }

  if (historyFilters.value.from_date) {
    params.from_date = historyFilters.value.from_date;
  }

  if (historyFilters.value.to_date) {
    params.to_date = historyFilters.value.to_date;
  }

  if (historyFilters.value.q && historyFilters.value.q.trim() !== '') {
    params.q = historyFilters.value.q.trim();
  }

  if (historyFilters.value.sort && historyFilters.value.sort !== 'newest') {
    params.sort = historyFilters.value.sort;
  }

  return params;
}

function formatDateInput(date) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

function hydrateHistoryFiltersFromUrl() {
  const params = new URLSearchParams(window.location.search);

  const postingType = params.get('ob_posting_type');
  const fromDate = params.get('ob_from_date');
  const toDate = params.get('ob_to_date');
  const keyword = params.get('ob_q');
  const sort = params.get('ob_sort');
  const page = Number(params.get('ob_page') || 1);

  if (postingType && ['all', 'initial', 'repost'].includes(postingType)) {
    historyFilters.value.posting_type = postingType;
  }

  if (fromDate) {
    historyFilters.value.from_date = fromDate;
  }

  if (toDate) {
    historyFilters.value.to_date = toDate;
  }

  if (keyword) {
    historyFilters.value.q = keyword;
  }

  if (sort && ['newest', 'oldest'].includes(sort)) {
    historyFilters.value.sort = sort;
  }

  return Number.isFinite(page) && page > 0 ? page : 1;
}

function syncHistoryFiltersToUrl(page = 1) {
  const url = new URL(window.location.href);

  const setOrDelete = (key, value) => {
    if (value === null || value === undefined || value === '' || value === 'all') {
      url.searchParams.delete(key);
      return;
    }

    url.searchParams.set(key, value);
  };

  setOrDelete('ob_posting_type', historyFilters.value.posting_type);
  setOrDelete('ob_from_date', historyFilters.value.from_date);
  setOrDelete('ob_to_date', historyFilters.value.to_date);
  setOrDelete('ob_q', historyFilters.value.q?.trim() || '');
  setOrDelete('ob_sort', historyFilters.value.sort || 'newest');

  if (page > 1) {
    url.searchParams.set('ob_page', String(page));
  } else {
    url.searchParams.delete('ob_page');
  }

  window.history.replaceState({}, '', url.toString());
}

function applyHistoryFilters() {
  fetchOpeningHistory(1);
}

function applyHistoryPreset(preset) {
  const now = new Date();

  if (preset === 'all') {
    historyFilters.value.from_date = '';
    historyFilters.value.to_date = '';
    fetchOpeningHistory(1);
    return;
  }

  if (preset === 'today') {
    const today = formatDateInput(now);
    historyFilters.value.from_date = today;
    historyFilters.value.to_date = today;
    fetchOpeningHistory(1);
    return;
  }

  if (preset === 'this_month') {
    const start = new Date(now.getFullYear(), now.getMonth(), 1);
    historyFilters.value.from_date = formatDateInput(start);
    historyFilters.value.to_date = formatDateInput(now);
    fetchOpeningHistory(1);
    return;
  }

  if (preset === 'last_30_days') {
    const from = new Date(now);
    from.setDate(from.getDate() - 29);
    historyFilters.value.from_date = formatDateInput(from);
    historyFilters.value.to_date = formatDateInput(now);
    fetchOpeningHistory(1);
  }
}

function resetHistoryFilters() {
  historyFilters.value = {
    posting_type: 'all',
    from_date: '',
    to_date: '',
    q: '',
    sort: 'newest',
  };

  fetchOpeningHistory(1);
}

function enableRepostMode() {
  repostMode.value = true;
}

function cancelRepostMode() {
  repostMode.value = false;
}

function gotoOpeningHistoryPage(page) {
  if (page < 1 || page > openingHistoryMeta.value.last_page) {
    return;
  }

  fetchOpeningHistory(page);
}

function openJournalEntry(row) {
  if (!row?.journal_entry_id) return;
  const url = route('backend.journal-entry.show', { journal_entry: row.journal_entry_id });
  window.open(url, '_blank', 'noopener');
}

function openLedgerTransaction(row) {
  if (!row?.transaction_id) return;
  const url = route('backend.ledger.show', row.transaction_id);
  window.open(url, '_blank', 'noopener');
}

function openHistorySnapshot(row) {
  selectedHistoryRow.value = row;
}

function closeHistorySnapshot() {
  selectedHistoryRow.value = null;
}

function exportHistoryCurrentPageCsv() {
  if (!openingHistoryRows.value.length) {
    alert('No history rows available to export.');
    return;
  }

  const headers = [
    'id',
    'created_at',
    'type',
    'posting_date',
    'journal_entry_id',
    'journal_reference',
    'ledger_transaction_id',
    'ledger_uuid',
    'total_debit',
    'total_credit',
    'line_count',
    'posted_by',
    'notes',
  ];

  const csvEscape = (value) => {
    const text = String(value ?? '');
    return `"${text.replace(/"/g, '""')}"`;
  };

  const rows = openingHistoryRows.value.map((row) => [
    row.id,
    row.created_at,
    row.is_repost ? 'repost' : 'initial',
    row.posting_date,
    row.journal_entry_id,
    row.journal_reference,
    row.transaction_id,
    row.uuid,
    Number(row.total_debit || 0).toFixed(2),
    Number(row.total_credit || 0).toFixed(2),
    row.line_count,
    row.posted_by,
    row.notes,
  ]);

  const csvContent = [
    headers.map(csvEscape).join(','),
    ...rows.map((row) => row.map(csvEscape).join(',')),
  ].join('\n');

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  const stamp = new Date().toISOString().replace(/[:.]/g, '-');
  link.href = url;
  link.download = `opening-balance-history-page-${openingHistoryMeta.value.current_page}-${stamp}.csv`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

function exportHistoryServerCsv() {
  const query = {
    ...buildOpeningHistoryParams(1),
    format: 'csv',
  };

  const url = route('backend.accounts.opening-balances.history', query);
  window.open(url, '_blank', 'noopener');
}
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 bg-white rounded-md dark:bg-slate-900">
      <h1 class="text-xl font-bold">{{ $page.props.pageTitle }}</h1>
      <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">Chart of Accounts — add, view and manage accounts.</p>
      <div class="mt-3 flex flex-wrap gap-2">
        <Link :href="route('backend.accounts.balances')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Balances</Link>
        <Link :href="route('backend.ledger.index')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Ledger</Link>
        <Link :href="route('backend.accounts.trial-balance')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Trial Balance</Link>
        <Link :href="route('backend.accounts.profit-loss')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Profit & Loss</Link>
        <Link :href="route('backend.accounts.balance-sheet')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Balance Sheet</Link>
        <Link :href="route('backend.accounts.cash-flow')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Cash Flow</Link>
      </div>

      <div class="mt-4 grid grid-cols-2 gap-6">
        <div>
          <h2 class="font-semibold mb-2">Existing Accounts</h2>
          <div class="space-y-2">
            <div v-for="acc in accountsProp" :key="acc.id" class="p-3 border rounded">
              <div class="flex justify-between items-start">
                <div>
                  <div class="font-medium">{{ acc.code }} — {{ acc.name }} ({{ acc.type }})</div>
                  <div class="text-sm text-gray-600 mt-1">Opening: {{ acc.opening_balance ?? '-' }} — Balance: {{ acc.balance ? acc.balance.balance : 0 }} — P: {{ acc.balance ? acc.balance.profit : 0 }} / L: {{ acc.balance ? acc.balance.loss : 0 }}</div>
                    <div class="text-sm mt-1" v-if="acc.children && acc.children.length">
                      <div v-for="child in acc.children" :key="child.id" class="text-sm">- {{ child.code }} — {{ child.name }} — Bal: {{ child.balance ? child.balance.balance : 0 }} — P: {{ child.balance ? child.balance.profit : 0 }} / L: {{ child.balance ? child.balance.loss : 0 }}</div>
                    </div>
                </div>
                <div class="space-x-2">
                  <button @click="editAccount(acc)" class="px-2 py-1 bg-yellow-400 text-black rounded text-sm">Edit</button>
                  <button @click="deleteAccount(acc.id)" class="px-2 py-1 bg-red-500 text-white rounded text-sm">Delete</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div>
          <h2 class="font-semibold mb-2">{{ isEditing ? 'Edit Account' : 'Create Account' }}</h2>
          <div class="space-y-2">
            <div>
              <label class="block text-sm">Code</label>
              <input v-model="form.code" class="w-full border rounded p-2" placeholder="e.g. CASH" />
            </div>
            <div>
              <label class="block text-sm">Name</label>
              <input v-model="form.name" class="w-full border rounded p-2" placeholder="Cash" />
            </div>
            <div>
              <label class="block text-sm">Type</label>
              <select v-model="form.type" class="w-full border rounded p-2">
                <option value="asset">Asset</option>
                <option value="liability">Liability</option>
                <option value="equity">Equity</option>
                <option value="income">Income</option>
                <option value="expense">Expense</option>
              </select>
            </div>
            <div>
              <label class="block text-sm">Parent (optional)</label>
              <select v-model="form.parent_id" class="w-full border rounded p-2">
                <option value="">-- none --</option>
                <option v-for="a in flatAccounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
              </select>
            </div>
            <div class="flex items-center gap-2">
              <button :disabled="loading" @click.prevent="saveAccount" class="btn-colorful">{{ isEditing ? 'Save' : 'Create' }}</button>
              <button v-if="isEditing" @click.prevent="cancelEdit" class="px-4 py-2 bg-gray-300 text-black rounded">Cancel</button>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-8 p-4 border rounded bg-slate-50 dark:bg-slate-800/40">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
          <div>
            <h2 class="font-semibold">Opening Balance Setup</h2>
            <p class="text-xs text-gray-600 dark:text-gray-300">Leaf accounts-এ opening balance set করুন, তারপর ledger-এ post করুন।</p>
            <p v-if="openingStatusLoading" class="text-xs text-gray-500 mt-1">Checking posting status...</p>
            <p v-else-if="openingStatus.is_posted && !repostMode" class="text-xs text-amber-700 mt-1">
              Opening balances already posted and locked.
              <span v-if="openingStatus.last_posting">Last TX: {{ openingStatus.last_posting.uuid }} on {{ openingStatus.last_posting.date }}</span>
            </p>
            <p v-else-if="repostMode" class="text-xs text-emerald-700 mt-1">Repost mode enabled. You can edit and repost opening balances.</p>
            <p v-else class="text-xs text-emerald-700 mt-1">Opening balances are editable.</p>
          </div>
          <div class="text-sm">
            <span class="mr-3">Debit: {{ openingDebitTotal.toFixed(2) }}</span>
            <span class="mr-3">Credit: {{ openingCreditTotal.toFixed(2) }}</span>
            <span :class="openingDifference === 0 ? 'text-green-600' : 'text-amber-600'">Diff: {{ openingDifference.toFixed(2) }}</span>
          </div>
        </div>

        <div class="overflow-x-auto max-h-96 border rounded bg-white dark:bg-slate-900">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-100 dark:bg-slate-700">
              <tr>
                <th class="px-3 py-2 text-left">Code</th>
                <th class="px-3 py-2 text-left">Name</th>
                <th class="px-3 py-2 text-left">Type</th>
                <th class="px-3 py-2 text-left">Opening Type</th>
                <th class="px-3 py-2 text-right">Opening Balance</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in openingLeafRows" :key="row.account_id" class="border-t">
                <td class="px-3 py-2">{{ row.code }}</td>
                <td class="px-3 py-2">{{ row.name }}</td>
                <td class="px-3 py-2 uppercase">{{ row.type }}</td>
                <td class="px-3 py-2">
                  <select v-model="row.opening_balance_type" class="border rounded p-1" :disabled="isOpeningLocked || savingOpening || postingOpening">
                    <option value="debit">Debit</option>
                    <option value="credit">Credit</option>
                  </select>
                </td>
                <td class="px-3 py-2 text-right">
                  <input
                    v-model.number="row.opening_balance"
                    type="number"
                    min="0"
                    step="0.01"
                    class="w-36 border rounded p-1 text-right"
                    :disabled="isOpeningLocked || savingOpening || postingOpening"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="mt-3 flex items-center gap-2">
          <button
            v-if="openingStatus.is_posted && !repostMode"
            @click="enableRepostMode"
            class="px-3 py-2 bg-amber-500 text-white rounded"
          >
            Enable Repost Mode
          </button>
          <button
            v-if="repostMode"
            @click="cancelRepostMode"
            class="px-3 py-2 bg-slate-500 text-white rounded"
          >
            Cancel Repost Mode
          </button>
          <button :disabled="savingOpening || isOpeningLocked" @click="saveOpeningBalances" class="btn-colorful-sm">
            {{ savingOpening ? 'Saving...' : 'Save Opening Balances' }}
          </button>
          <button :disabled="postingOpening || isOpeningLocked" @click="postOpeningToLedger" class="px-3 py-2 bg-emerald-600 text-white rounded">
            {{ postingOpening ? 'Posting...' : (repostMode ? 'Repost Opening To Ledger' : 'Post Opening To Ledger') }}
          </button>
          <button
            :disabled="openingHistoryLoading"
            @click="fetchOpeningHistory(openingHistoryMeta.current_page || 1)"
            class="px-3 py-2 bg-slate-600 text-white rounded"
          >
            {{ openingHistoryLoading ? 'Refreshing...' : 'Refresh History' }}
          </button>
          <button
            :disabled="openingHistoryLoading || !openingHistoryRows.length"
            @click="exportHistoryCurrentPageCsv"
            class="px-3 py-2 bg-indigo-600 text-white rounded"
          >
            Export Page CSV
          </button>
          <button
            @click="exportHistoryServerCsv"
            class="px-3 py-2 bg-indigo-800 text-white rounded"
          >
            Export Filtered CSV
          </button>
        </div>

        <div class="mt-3 grid grid-cols-1 md:grid-cols-6 gap-2">
          <select v-model="historyFilters.posting_type" class="border rounded p-2 text-sm">
            <option value="all">All postings</option>
            <option value="initial">Initial only</option>
            <option value="repost">Repost only</option>
          </select>
          <select v-model="historyFilters.sort" class="border rounded p-2 text-sm">
            <option value="newest">Newest first</option>
            <option value="oldest">Oldest first</option>
          </select>
          <input v-model="historyFilters.from_date" type="date" class="border rounded p-2 text-sm" />
          <input v-model="historyFilters.to_date" type="date" :min="historyFilters.from_date || undefined" class="border rounded p-2 text-sm" />
          <input
            v-model="historyFilters.q"
            type="text"
            class="border rounded p-2 text-sm"
            placeholder="Search notes, journal ref, ledger uuid..."
            @keydown.enter.prevent="applyHistoryFilters"
          />
          <div class="flex gap-2">
            <button class="px-3 py-2 bg-slate-700 text-white rounded text-sm" @click="applyHistoryFilters">Apply Filters</button>
            <button class="px-3 py-2 bg-slate-300 text-black rounded text-sm" @click="resetHistoryFilters">Reset</button>
          </div>
        </div>

        <div class="mt-2 flex flex-wrap gap-2 text-xs">
          <button class="px-2 py-1 border rounded" @click="applyHistoryPreset('today')">Today</button>
          <button class="px-2 py-1 border rounded" @click="applyHistoryPreset('this_month')">This Month</button>
          <button class="px-2 py-1 border rounded" @click="applyHistoryPreset('last_30_days')">Last 30 Days</button>
          <button class="px-2 py-1 border rounded" @click="applyHistoryPreset('all')">All Time</button>
        </div>

        <div class="mt-4 border rounded bg-white dark:bg-slate-900 overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-100 dark:bg-slate-700">
              <tr>
                <th class="px-3 py-2 text-left">Posted At</th>
                <th class="px-3 py-2 text-left">Type</th>
                <th class="px-3 py-2 text-left">Posting Date</th>
                <th class="px-3 py-2 text-left">Journal</th>
                <th class="px-3 py-2 text-left">Ledger UUID</th>
                <th class="px-3 py-2 text-right">Debit</th>
                <th class="px-3 py-2 text-right">Credit</th>
                <th class="px-3 py-2 text-right">Lines</th>
                <th class="px-3 py-2 text-left">By</th>
                <th class="px-3 py-2 text-left">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in openingHistoryRows" :key="row.id" class="border-t">
                <td class="px-3 py-2">{{ row.created_at || '-' }}</td>
                <td class="px-3 py-2">
                  <span :class="row.is_repost ? 'text-amber-700' : 'text-emerald-700'">{{ row.is_repost ? 'Repost' : 'Initial' }}</span>
                </td>
                <td class="px-3 py-2">{{ row.posting_date || '-' }}</td>
                <td class="px-3 py-2">{{ row.journal_reference || row.journal_entry_id || '-' }}</td>
                <td class="px-3 py-2">{{ row.uuid || '-' }}</td>
                <td class="px-3 py-2 text-right">{{ Number(row.total_debit || 0).toFixed(2) }}</td>
                <td class="px-3 py-2 text-right">{{ Number(row.total_credit || 0).toFixed(2) }}</td>
                <td class="px-3 py-2 text-right">{{ row.line_count || 0 }}</td>
                <td class="px-3 py-2">{{ row.posted_by || '-' }}</td>
                <td class="px-3 py-2">
                  <div class="flex items-center gap-2">
                    <button
                      class="px-2 py-1 border rounded text-xs"
                      :disabled="!row.journal_entry_id"
                      @click="openJournalEntry(row)"
                    >
                      Journal
                    </button>
                    <button
                      class="px-2 py-1 border rounded text-xs"
                      :disabled="!row.transaction_id"
                      @click="openLedgerTransaction(row)"
                    >
                      Ledger
                    </button>
                    <button
                      class="px-2 py-1 border rounded text-xs"
                      :disabled="!row.snapshot || !row.snapshot.length"
                      @click="openHistorySnapshot(row)"
                    >
                      Details
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!openingHistoryRows.length && !openingHistoryLoading">
                <td colspan="10" class="px-3 py-4 text-center text-gray-500">No opening posting history found.</td>
              </tr>
              <tr v-if="openingHistoryLoading">
                <td colspan="10" class="px-3 py-4 text-center text-gray-500">Loading history...</td>
              </tr>
            </tbody>
          </table>

          <div class="flex items-center justify-between px-3 py-2 border-t text-xs text-gray-600">
            <div>
              Page {{ openingHistoryMeta.current_page }} / {{ openingHistoryMeta.last_page }} • Total {{ openingHistoryMeta.total }}
            </div>
            <div class="space-x-2">
              <button class="px-2 py-1 border rounded" :disabled="openingHistoryMeta.current_page <= 1" @click="gotoOpeningHistoryPage(openingHistoryMeta.current_page - 1)">Prev</button>
              <button class="px-2 py-1 border rounded" :disabled="openingHistoryMeta.current_page >= openingHistoryMeta.last_page" @click="gotoOpeningHistoryPage(openingHistoryMeta.current_page + 1)">Next</button>
            </div>
          </div>
        </div>
      </div>

      <div
        v-if="selectedHistoryRow"
        class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4"
        @click.self="closeHistorySnapshot"
      >
        <div class="w-full max-w-4xl bg-white dark:bg-slate-900 rounded shadow-lg border">
          <div class="px-4 py-3 border-b flex items-center justify-between">
            <div>
              <h3 class="font-semibold">Opening Posting Snapshot</h3>
              <p class="text-xs text-gray-600 dark:text-gray-300">
                {{ selectedHistoryRow.journal_reference || selectedHistoryRow.journal_entry_id || '-' }} • {{ selectedHistoryRow.posting_date || '-' }}
              </p>
            </div>
            <button class="px-3 py-1 border rounded" @click="closeHistorySnapshot">Close</button>
          </div>

          <div class="p-4 overflow-x-auto max-h-[60vh]">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-100 dark:bg-slate-700">
                <tr>
                  <th class="px-3 py-2 text-left">#</th>
                  <th class="px-3 py-2 text-left">Code</th>
                  <th class="px-3 py-2 text-left">Account</th>
                  <th class="px-3 py-2 text-left">Type</th>
                  <th class="px-3 py-2 text-right">Amount</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="line in selectedSnapshotLines" :key="`${selectedHistoryRow.id}-${line.index}`" class="border-t">
                  <td class="px-3 py-2">{{ line.index }}</td>
                  <td class="px-3 py-2">{{ line.code }}</td>
                  <td class="px-3 py-2">{{ line.name }}</td>
                  <td class="px-3 py-2 uppercase">{{ line.entry_type }}</td>
                  <td class="px-3 py-2 text-right">{{ line.amount.toFixed(2) }}</td>
                </tr>
                <tr v-if="!selectedSnapshotLines.length">
                  <td colspan="5" class="px-3 py-4 text-center text-gray-500">No snapshot lines available.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </BackendLayout>
</template>
