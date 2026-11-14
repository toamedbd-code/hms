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
  department: {
    type: String,
    default: '',
  },
});

const rows = computed(() => props.datas?.data ?? []);
const searchBillNumber = ref(props.filters?.bill_number ?? '');

const getItems = (billing) => billing.bill_items ?? billing.billItems ?? [];

const getItemNames = (billing) => getItems(billing)
  .map((item) => String(item.item_name ?? '').trim())
  .filter((name) => name !== '');

const getItemNamesText = (billing) => getItemNames(billing).join(', ') || 'N/A';

const getItemNamesPreview = (billing) => {
  const names = getItemNames(billing);
  if (names.length <= 4) return names.join(', ') || 'N/A';
  return `${names.slice(0, 4).join(', ')}, ...`;
};

const getCollectorNames = (billing) => {
  const items = getItems(billing);
  const names = items
    .map((item) => item.collected_by?.name)
    .filter((name) => name && name.trim() !== '');
  return [...new Set(names)].join(', ') || 'N/A';
};

const hasReportedItems = (billing) => getItems(billing).some((item) => !!item.reported_at);

const hasPendingItems = (billing) => getItems(billing).some((item) => !item.reported_at);

const getCompletedItems = (billing) => getItems(billing).filter((item) => !!item.reported_at);

const submitSearch = () => {
  const value = searchBillNumber.value.trim();
  if (!value) {
    warningMessage('Enter a bill number to search.');
    return;
  }

  // Preserve the current reporting department (ultrasound/xray/pathology)
  // so searches performed from a department page remain in that context.
  const params = { bill_number: value, include_reported: 1 };
  if (props.department && props.department !== '') {
    params.department = props.department;
  }

  router.get(route('backend.reporting.index'), params, { preserveScroll: true });
};

const buildEditRoute = (billing, includeReported = false) => {
  const base = { billing: billing.id };
  if (props.department && props.department !== '') {
    base.department = props.department;
  }
  if (includeReported) base.include_reported = 1;
  return route('backend.reporting.edit', base);
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
            class="inline-flex items-center rounded-md border border-slate-700 bg-slate-700 px-3 py-1.5 text-xs font-semibold tracking-wide text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-300"
            style="color: #ffffff !important"
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
        <table
          class="w-full text-sm text-left text-gray-700 border"
          :class="{ 'table-fixed': department === 'pathology' }"
        >
          <colgroup v-if="department === 'pathology'">
            <col style="width: 14%">
            <col style="width: 13%">
            <col style="width: 36%">
            <col style="width: 12%">
            <col style="width: 10%">
            <col style="width: 15%">
          </colgroup>
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 border">Bill No</th>
              <th class="px-3 py-2 border">Patient</th>
              <th v-if="department === 'pathology'" class="px-3 py-2 border">Item Name</th>
              <th class="px-3 py-2 border">Collected By</th>
              <th class="px-3 py-2 border">Test Count</th>
              <th class="px-3 py-2 border">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="billing in rows" :key="billing.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 border">{{ billing.bill_number ?? 'N/A' }}</td>
              <td class="px-3 py-2 border">
                {{ billing.patient?.name || billing.patient_name || 'N/A' }}
              </td>
              <td v-if="department === 'pathology'" class="max-w-0 px-3 py-2 border">
                <span class="block truncate whitespace-nowrap" :title="getItemNamesText(billing)">
                  {{ getItemNamesPreview(billing) }}
                </span>
              </td>
              <td class="px-3 py-2 border">{{ getCollectorNames(billing) }}</td>
              <td class="px-3 py-2 border">{{ getCompletedItems(billing).length || getItems(billing).length }}</td>
              <td class="px-3 py-2 border">
                <Link
                  v-if="hasReportedItems(billing)"
                  class="px-3 py-1 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700"
                  :href="buildEditRoute(billing, true)"
                >
                  Edit Report
                </Link>
                <Link
                  v-else-if="hasPendingItems(billing)"
                  class="px-3 py-1 text-xs text-white bg-emerald-600 rounded hover:bg-emerald-700"
                  :href="buildEditRoute(billing, false)"
                >
                  Add Report
                </Link>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td :colspan="department === 'pathology' ? 6 : 5" class="px-3 py-6 text-center text-gray-500">No pending reports.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination />
    </div>
  </BackendLayout>
</template>
