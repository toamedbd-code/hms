"*** Begin Replacement"
<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
  datas: Object,
  filters: {
    type: Object,
    default: () => ({}),
  },
  mode: {
    type: String,
    default: 'doctor',
  },
  term: {
    type: String,
    default: '',
  },
  meta: {
    type: Object,
    default: () => ({}),
  },
});

const rows = computed(() => props.datas?.data ?? []);

const filters = ref({
  q: props.filters?.q ?? props.term ?? '',
  mode: props.filters?.mode ?? props.mode ?? 'doctor',
  from: props.filters?.from ?? '',
  to: props.filters?.to ?? '',
  numOfData: props.filters?.numOfData ?? props.datas?.per_page ?? 10,
});

// Keep local filters in sync if server props update (Inertia preserveState can
// keep component state; syncing ensures UI shows the returned mode/values).
watch(
  () => props.filters,
  (nf) => {
    filters.value.q = nf?.q ?? props.term ?? '';
    filters.value.mode = nf?.mode ?? props.mode ?? 'doctor';
    filters.value.from = nf?.from ?? '';
    filters.value.to = nf?.to ?? '';
    filters.value.numOfData = nf?.numOfData ?? props.datas?.per_page ?? 10;
  },
  { immediate: true }
);

const applyFilter = () => {
  router.get(route('backend.report-summary.index'), filters.value, { preserveState: true });
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <h1 class="text-lg font-semibold text-gray-800">Report Summary</h1>
      </div>

      <div class="p-4 mt-4 bg-white border rounded">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-2">
          <select v-model="filters.mode" class="p-2 border rounded">
            <option value="doctor">Report Summary</option>
            <option value="test">Test Name (e.g. CBC)</option>
            <option value="referrer">Referrer Doctor</option>
            <option value="technologist">Technologist / Pathologist (reported)</option>
            <option value="collector">Sample Collector</option>
          </select>

          <input v-model="filters.q" @keyup.enter="applyFilter"
            :placeholder="filters.mode === 'test' ? 'Test name (e.g. CBC)' : 'Search term (name / id)'
            " class="p-2 border rounded" />

          <input v-model="filters.from" type="date" class="p-2 border rounded" />
          <input v-model="filters.to" type="date" class="p-2 border rounded" />

          <select v-model="filters.numOfData" @change="applyFilter" class="p-2 border rounded">
            <option value="10">10</option>
            <option value="20">20</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>

          <button @click="applyFilter" class="px-3 py-2 bg-indigo-600 text-white rounded">Search</button>
        </div>
      </div>

      <div class="mt-3 p-3 bg-white border rounded">
        <div class="flex items-center gap-4 text-sm text-gray-700">
          <div v-if="props.meta?.total_cases">Total Cases: <strong class="ml-1">{{ props.meta.total_cases }}</strong></div>
          <div v-if="props.meta?.total_items">Total Items: <strong class="ml-1">{{ props.meta.total_items }}</strong></div>
          <div v-if="props.meta?.total_reports">Total Reports: <strong class="ml-1">{{ props.meta.total_reports }}</strong></div>
          <div v-if="props.meta?.total_collected">Total Collected: <strong class="ml-1">{{ props.meta.total_collected }}</strong></div>
          <div v-if="props.meta?.distinct_cases">Distinct Cases: <strong class="ml-1">{{ props.meta.distinct_cases }}</strong></div>
        </div>
      </div>

      <div class="my-4 overflow-x-auto bg-white border rounded">
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr class="bg-gray-50">
              <th v-if="filters.mode === 'test'" class="px-3 py-2 text-left border-b border-gray-200">Case ID</th>
              <th v-if="filters.mode === 'test'" class="px-3 py-2 text-left border-b border-gray-200">Patient</th>
              <th v-if="filters.mode === 'test'" class="px-3 py-2 text-left border-b border-gray-200">Matched Tests</th>
              <th v-if="filters.mode === 'test'" class="px-3 py-2 text-right border-b border-gray-200">Price</th>

              <th v-if="filters.mode === 'referrer'" class="px-3 py-2 text-left border-b border-gray-200">Case ID</th>
              <th v-if="filters.mode === 'referrer'" class="px-3 py-2 text-left border-b border-gray-200">Patient</th>
              <th v-if="filters.mode === 'referrer'" class="px-3 py-2 text-left border-b border-gray-200">Referrer</th>

              <th v-if="filters.mode === 'technologist' || filters.mode === 'pathologist'" class="px-3 py-2 text-left border-b border-gray-200">Reported At</th>
              <th v-if="filters.mode === 'technologist' || filters.mode === 'pathologist'" class="px-3 py-2 text-left border-b border-gray-200">Case ID</th>
              <th v-if="filters.mode === 'technologist' || filters.mode === 'pathologist'" class="px-3 py-2 text-left border-b border-gray-200">Patient</th>
              <th v-if="filters.mode === 'technologist' || filters.mode === 'pathologist'" class="px-3 py-2 text-left border-b border-gray-200">Reporter</th>
              <th v-if="filters.mode === 'technologist' || filters.mode === 'pathologist'" class="px-3 py-2 text-left border-b border-gray-200">Test</th>
              <th v-if="filters.mode === 'technologist' || filters.mode === 'pathologist'" class="px-3 py-2 text-right border-b border-gray-200">Price</th>

              <th v-if="filters.mode === 'collector'" class="px-3 py-2 text-left border-b border-gray-200">Collected At</th>
              <th v-if="filters.mode === 'collector'" class="px-3 py-2 text-left border-b border-gray-200">Case ID</th>
              <th v-if="filters.mode === 'collector'" class="px-3 py-2 text-left border-b border-gray-200">Patient</th>
              <th v-if="filters.mode === 'collector'" class="px-3 py-2 text-left border-b border-gray-200">Collector</th>
              <th v-if="filters.mode === 'collector'" class="px-3 py-2 text-left border-b border-gray-200">Test</th>
              <th v-if="filters.mode === 'collector'" class="px-3 py-2 text-right border-b border-gray-200">Price</th>

              <th v-if="filters.mode === 'doctor'" class="px-3 py-2 text-left border-b border-gray-200">Doctor</th>
              <th v-if="filters.mode === 'doctor'" class="px-3 py-2 text-right border-b border-gray-200">Case Count</th>
              <th v-if="filters.mode === 'doctor'" class="px-3 py-2 text-right border-b border-gray-200">Pathology Items</th>
              <th v-if="filters.mode === 'doctor'" class="px-3 py-2 text-left border-b border-gray-200">Top Tests</th>
            </tr>
          </thead>
          <tbody>
            <!-- TEST MODE -->
            <tr v-for="row in rows" v-if="filters.mode === 'test'" :key="row.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 border-b border-gray-100">{{ row.case_number }}</td>
              <td class="px-3 py-2 border-b border-gray-100">{{ row.patient_name }} <div class="text-xs text-gray-500">{{ row.patient_mobile }}</div></td>
              <td class="px-3 py-2 border-b border-gray-100">
                <div v-if="row.matched_tests && row.matched_tests.length">
                  <div v-for="m in row.matched_tests" :key="m" class="text-xs">{{ m }}</div>
                </div>
              </td>
              <td class="px-3 py-2 text-right border-b border-gray-100">{{ row.price ? Number(row.price).toFixed(2) : '0.00' }}</td>
            </tr>

            <!-- Totals row for Test mode -->
            <tr v-if="filters.mode === 'test' && (props.meta?.total_items || props.meta?.grand_total)">
              <td colspan="2" class="px-3 py-2 font-semibold">Totals</td>
              <td class="px-3 py-2 font-semibold">Quantity: {{ props.meta.total_items ?? 0 }}</td>
              <td class="px-3 py-2 text-right font-semibold">{{ props.meta.grand_total ? Number(props.meta.grand_total).toFixed(2) : '0.00' }}</td>
            </tr>

            <!-- REFERRER MODE -->
            <tr v-for="row in rows" v-if="filters.mode === 'referrer'" :key="row.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 border-b border-gray-100">{{ row.case_number }}</td>
              <td class="px-3 py-2 border-b border-gray-100">{{ row.patient_name }} <div class="text-xs text-gray-500">{{ row.patient_mobile }}</div></td>
              <td class="px-3 py-2 border-b border-gray-100">{{ row.referrer_name ?? 'N/A' }}</td>
            </tr>

            <!-- TECHNOLOGIST / PATHOLOGIST MODE -->
            <tr v-for="row in rows" v-if="filters.mode === 'technologist' || filters.mode === 'pathologist'" :key="row.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 border-b border-gray-100">{{ row.reported_at ? new Date(row.reported_at).toLocaleString() : 'N/A' }}</td>
              <td class="px-3 py-2 border-b border-gray-100">{{ row.case_number }}</td>
              <td class="px-3 py-2 border-b border-gray-100">{{ row.patient_name ?? 'N/A' }}</td>
              <td class="px-3 py-2 border-b border-gray-100">{{ row.reporter_name ?? 'N/A' }}</td>
              <td class="px-3 py-2 border-b border-gray-100">{{ row.item_name ?? '' }}</td>
              <td class="px-3 py-2 text-right border-b border-gray-100">{{ row.price ? Number(row.price).toFixed(2) : '0.00' }}</td>
            </tr>

            <!-- COLLECTOR MODE -->
            <tr v-for="row in rows" v-if="filters.mode === 'collector'" :key="row.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 border-b border-gray-100">{{ row.sample_collected_at ? new Date(row.sample_collected_at).toLocaleString() : 'N/A' }}</td>
              <td class="px-3 py-2 border-b border-gray-100">{{ row.case_number }}</td>
              <td class="px-3 py-2 border-b border-gray-100">{{ row.patient_name ?? 'N/A' }}</td>
              <td class="px-3 py-2 border-b border-gray-100">{{ row.collector_name ?? 'N/A' }}</td>
              <td class="px-3 py-2 border-b border-gray-100">{{ row.item_name ?? '' }}</td>
              <td class="px-3 py-2 text-right border-b border-gray-100">{{ row.price ? Number(row.price).toFixed(2) : '0.00' }}</td>
            </tr>

            <!-- DOCTOR MODE -->
            <tr v-for="row in rows" v-if="filters.mode === 'doctor'" :key="row.doctor_id" class="hover:bg-gray-50">
              <td class="px-3 py-2 border-b border-gray-100">{{ row.doctor_name ?? 'N/A' }}</td>
              <td class="px-3 py-2 text-right border-b border-gray-100">{{ row.case_count ?? 0 }}</td>
              <td class="px-3 py-2 text-right border-b border-gray-100">{{ row.pathology_count ?? 0 }}</td>
              <td class="px-3 py-2 border-b border-gray-100">
                <div v-if="row.top_tests && row.top_tests.length">
                  <div v-for="t in row.top_tests.slice(0, 4)" :key="t.item_name" class="text-xs">{{ t.item_name }} ({{ t.count }})</div>
                </div>
              </td>
            </tr>

            <tr v-if="rows.length === 0">
              <td colspan="20" class="px-3 py-6 text-center text-gray-500 border-b border-gray-100">No data found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination />
    </div>
  </BackendLayout>
</template>

"*** End Replacement"
