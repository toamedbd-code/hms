<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { Link } from '@inertiajs/vue3';

const rows = ref([]);

const load = async () => {
  try {
    const res = await axios.get(route('backend.accounts.list'));
    // regeneratePagination used elsewhere; this endpoint returns paginated json
    rows.value = res.data.data || res.data || [];
  } catch (e) {
    console.error(e);
    rows.value = [];
  }
};

onMounted(() => load());
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 bg-white rounded-md dark:bg-slate-900">
      <h1 class="text-xl font-bold">{{ $page.props.pageTitle }}</h1>
      <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">Account balances summary (Profit / Loss).</p>
      <div class="mt-3 flex flex-wrap gap-2">
        <Link :href="route('backend.accounts.index')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Chart of Accounts</Link>
        <Link :href="route('backend.ledger.index')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Ledger</Link>
        <Link :href="route('backend.accounts.trial-balance')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Trial Balance</Link>
        <Link :href="route('backend.accounts.profit-loss')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Profit & Loss</Link>
        <Link :href="route('backend.accounts.balance-sheet')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Balance Sheet</Link>
        <Link :href="route('backend.accounts.cash-flow')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600">Cash Flow</Link>
      </div>

      <div class="mt-4 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left">Code</th>
              <th class="px-4 py-2 text-left">Name</th>
              <th class="px-4 py-2 text-left">Type</th>
              <th class="px-4 py-2 text-right">Balance</th>
              <th class="px-4 py-2 text-right">Profit</th>
              <th class="px-4 py-2 text-right">Loss</th>
              <th class="px-4 py-2 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="r in rows" :key="r.id">
              <td class="px-4 py-2">{{ r.code }}</td>
              <td class="px-4 py-2">{{ r.name }}</td>
              <td class="px-4 py-2">{{ r.type }}</td>
              <td class="px-4 py-2 text-right">{{ r.balance }}</td>
              <td class="px-4 py-2 text-right">{{ r.profit }}</td>
              <td class="px-4 py-2 text-right">{{ r.loss }}</td>
              <td class="px-4 py-2 text-right"><Link :href="route('backend.ledger.index') + '?account_id=' + r.id" class="text-indigo-600">Ledger</Link></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </BackendLayout>
</template>

<style scoped></style>
