<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { warningMessage } from '@/responseMessage.js';

const props = defineProps({
  datas: Object,
  filters: {
    type: Object,
    default: () => ({}),
  },
  pageTitle: {
    type: String,
    default: 'Reporting',
  },
});

const rows = computed(() => props.datas?.data ?? []);
const searchBillNumber = ref(props.filters?.bill_number ?? '');

const getItems = (billing) => billing.bill_items ?? billing.billItems ?? [];

const getCollectorNames = (billing) => {
  const items = getItems(billing);
  const names = items
    .map((item) => item.collected_by?.name)
    .filter((name) => name && name.trim() !== '');
  return [...new Set(names)].join(', ') || 'N/A';
};

const hasReportedItems = (billing) => getItems(billing).some((item) => !!item.reported_at);

const hasPendingItems = (billing) => getItems(billing).some((item) => !item.reported_at);

const submitSearch = () => {
  const value = searchBillNumber.value.trim();
  if (!value) {
    warningMessage('Enter a bill number to search.');
    return;
  }

  router.get(route('backend.reporting.index'), { bill_number: value, include_reported: 1 }, { preserveScroll: true });
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <h1 class="text-lg font-semibold text-gray-800">{{ pageTitle }}</h1>
        <div class="flex items-center gap-3">
          <Link
            :href="route('backend.dashboard')"
            class="px-3 py-1 text-xs text-white bg-slate-600 rounded hover:bg-slate-700"
          >
            Back
          </Link>
          <form @submit.prevent="submitSearch" class="flex items-center gap-2">
            <input
              v-model="searchBillNumber"
              type="text"
              class="w-48 p-2 text-sm rounded-md border border-slate-300 focus:border-indigo-300"
              placeholder="Search bill no"
            />
            <button
              type="submit"
              class="px-3 py-1 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700"
            >
              Search
            </button>
          </form>
        </div>
      </div>

      <div class="w-full my-4 overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700 border">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 border">Bill No</th>
              <th class="px-3 py-2 border">Patient</th>
              <th class="px-3 py-2 border">Collected By</th>
              <th class="px-3 py-2 border">Test Count</th>
              <th class="px-3 py-2 border">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="billing in rows" :key="billing.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 border">{{ billing.bill_number ?? 'N/A' }}</td>
              <td class="px-3 py-2 border">
                {{ billing.patient?.first_name ? `${billing.patient.first_name} ${billing.patient.last_name ?? ''}` : 'N/A' }}
              </td>
              <td class="px-3 py-2 border">{{ getCollectorNames(billing) }}</td>
              <td class="px-3 py-2 border">{{ getItems(billing).length }}</td>
              <td class="px-3 py-2 border">
                <Link
                  v-if="hasPendingItems(billing)"
                  class="px-3 py-1 text-xs text-white bg-emerald-600 rounded hover:bg-emerald-700"
                  :href="route('backend.reporting.edit', billing.id)"
                >
                  Add Report
                </Link>
                <Link
                  v-else-if="hasReportedItems(billing)"
                  class="px-3 py-1 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700"
                  :href="route('backend.reporting.edit', { billing: billing.id, include_reported: 1 })"
                >
                  Edit Report
                </Link>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="5" class="px-3 py-6 text-center text-gray-500">No pending reports.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination />
    </div>
  </BackendLayout>
</template>
