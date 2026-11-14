<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
  bankAccounts: { type: Array, default: () => [] },
  selectedAccountId: { type: Number, default: 0 },
  history: { type: Array, default: () => [] },
});

const accountId = ref(props.selectedAccountId || props.bankAccounts[0]?.id || 0);
const entries = ref([]);
const bookBalance = ref(0);
const loading = ref(false);
const saving = ref(false);
const statementDate = ref(new Date().toISOString().slice(0, 10));
const statementBalance = ref('');
const notes = ref('');
const selectedEntryIds = ref([]);

function formatAmount(value) {
  return Number(value || 0).toFixed(2);
}

async function loadEntries() {
  if (!accountId.value) return;
  loading.value = true;
  try {
    const res = await axios.get(route('backend.accounts.bank-reconciliation.entries'), {
      params: { account_id: accountId.value },
    });
    entries.value = res?.data?.entries || [];
    bookBalance.value = Number(res?.data?.book_balance || 0);
    selectedEntryIds.value = [];
  } catch (error) {
    console.error(error);
    entries.value = [];
    bookBalance.value = 0;
  } finally {
    loading.value = false;
  }
}

function toggleEntry(id) {
  if (selectedEntryIds.value.includes(id)) {
    selectedEntryIds.value = selectedEntryIds.value.filter((item) => item !== id);
    return;
  }
  selectedEntryIds.value = [...selectedEntryIds.value, id];
}

const clearedBalance = () => entries.value
  .filter((entry) => selectedEntryIds.value.includes(entry.id))
  .reduce((sum, entry) => sum + Number(entry.signed_amount || 0), 0);

function saveReconciliation() {
  if (!accountId.value || statementBalance.value === '') return;
  saving.value = true;
  router.post(route('backend.accounts.bank-reconciliation.store'), {
    account_id: accountId.value,
    statement_date: statementDate.value,
    statement_balance: Number(statementBalance.value),
    notes: notes.value,
    cleared_entry_ids: selectedEntryIds.value,
  }, {
    preserveScroll: true,
    onFinish: () => {
      saving.value = false;
    },
  });
}

watch(accountId, () => {
  loadEntries();
});

onMounted(() => {
  loadEntries();
});
</script>

<template>
  <BackendLayout>
    <div class="p-4 space-y-4">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold">Bank Reconciliation</h1>
        <Link :href="route('backend.accounts.index')" class="px-3 py-2 text-sm text-white bg-gray-600 rounded hover:bg-gray-700">Back</Link>
      </div>

      <div class="grid gap-4 lg:grid-cols-3">
        <div class="p-4 bg-white rounded shadow lg:col-span-2">
          <div class="grid gap-3 md:grid-cols-2">
            <div>
              <label class="block mb-1 text-sm font-medium">Bank Account</label>
              <select v-model="accountId" class="w-full p-2 border rounded">
                <option v-for="account in bankAccounts" :key="account.id" :value="account.id">
                  {{ account.code }} - {{ account.name }}
                </option>
              </select>
            </div>
            <div>
              <label class="block mb-1 text-sm font-medium">Book Balance</label>
              <input :value="formatAmount(bookBalance)" class="w-full p-2 bg-gray-100 border rounded" readonly />
            </div>
            <div>
              <label class="block mb-1 text-sm font-medium">Statement Date</label>
              <input v-model="statementDate" type="date" class="w-full p-2 border rounded" />
            </div>
            <div>
              <label class="block mb-1 text-sm font-medium">Statement Balance</label>
              <input v-model="statementBalance" type="number" step="0.01" class="w-full p-2 border rounded" />
            </div>
          </div>

          <div class="mt-3">
            <label class="block mb-1 text-sm font-medium">Notes</label>
            <textarea v-model="notes" rows="2" class="w-full p-2 border rounded" />
          </div>

          <div class="flex flex-wrap gap-4 mt-4 text-sm">
            <div><span class="font-medium">Cleared Balance:</span> {{ formatAmount(clearedBalance()) }}</div>
            <div><span class="font-medium">Difference:</span> {{ formatAmount(Number(statementBalance || 0) - clearedBalance()) }}</div>
          </div>

          <button
            class="px-4 py-2 mt-4 text-white bg-blue-600 rounded hover:bg-blue-700 disabled:opacity-50"
            :disabled="saving || statementBalance === ''"
            @click="saveReconciliation"
          >
            {{ saving ? 'Saving...' : 'Save Reconciliation' }}
          </button>
        </div>

        <div class="p-4 bg-white rounded shadow">
          <h2 class="mb-3 font-semibold">Recent Reconciliations</h2>
          <div class="space-y-2 text-sm">
            <div v-for="item in history" :key="item.id" class="p-2 border rounded">
              <div>{{ item.statement_date }}</div>
              <div>Statement: {{ formatAmount(item.statement_balance) }}</div>
              <div>Cleared: {{ formatAmount(item.cleared_balance) }}</div>
              <div>Difference: {{ formatAmount(item.difference) }}</div>
            </div>
            <div v-if="!history.length" class="text-gray-500">No reconciliation history yet.</div>
          </div>
        </div>
      </div>

      <div class="p-4 bg-white rounded shadow">
        <h2 class="mb-3 font-semibold">Bank Ledger Entries</h2>
        <div v-if="loading" class="text-sm text-gray-500">Loading entries...</div>
        <table v-else class="w-full text-sm table-auto">
          <thead>
            <tr class="text-left bg-gray-50">
              <th class="p-2 border">Clear</th>
              <th class="p-2 border">Date</th>
              <th class="p-2 border">Description</th>
              <th class="p-2 border">Type</th>
              <th class="p-2 border">Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in entries" :key="entry.id">
              <td class="p-2 border">
                <input type="checkbox" :checked="selectedEntryIds.includes(entry.id)" @change="toggleEntry(entry.id)" />
              </td>
              <td class="p-2 border">{{ entry.date }}</td>
              <td class="p-2 border">{{ entry.description }}</td>
              <td class="p-2 border">{{ entry.entry_type }}</td>
              <td class="p-2 border">{{ formatAmount(entry.amount) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </BackendLayout>
</template>
