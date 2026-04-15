<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
  datas: Object,
  filters: {
    type: Object,
    default: () => ({}),
  },
  pageTitle: {
    type: String,
    default: 'Report Delivery',
  },
});

const PRINTED_STORAGE_KEY = 'report-delivery-printed-items';
const loadPrintedRows = () => {
  try {
    const raw = window.localStorage.getItem(PRINTED_STORAGE_KEY);
    if (!raw) return {};
    const parsed = JSON.parse(raw);
    return parsed && typeof parsed === 'object' ? parsed : {};
  } catch (error) {
    return {};
  }
};

const rows = computed(() => props.datas?.data ?? []);
const printedRows = ref(loadPrintedRows());
const search = ref(props.filters?.search ?? '');

const hasDue = (item) => Number(item?.billing?.due_amount ?? 0) > 0;

const formatDateTime = (value) => {
  if (!value) return 'N/A';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Asia/Dhaka',
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: true,
  }).formatToParts(date);

  const getPart = (type) => parts.find((part) => part.type === type)?.value ?? '';

  const day = getPart('day');
  const month = getPart('month');
  const year = getPart('year');
  const hour = getPart('hour');
  const minute = getPart('minute');
  const second = getPart('second');
  const dayPeriod = getPart('dayPeriod');

  return `${day}-${month}-${year} ${hour}:${minute}:${second} ${dayPeriod}`;
};

const getPrintHref = (item) => {
  if (!item?.id) return '#';

  if (hasDue(item) && item?.billing?.id) {
    return route('backend.due.collect', {
      id: item.billing.id,
      redirect_to: route('backend.reporting.print', item.id),
      return_to: route('backend.report-delivery.index'),
    });
  }

  return route('backend.reporting.print', item.id);
};

const markPrinted = (itemId) => {
  printedRows.value = {
    ...printedRows.value,
    [itemId]: true,
  };

  try {
    window.localStorage.setItem(PRINTED_STORAGE_KEY, JSON.stringify(printedRows.value));
  } catch (error) {
    // Ignore storage failures (e.g. private mode restrictions).
  }
};

const canDeliver = (item) => {
  if (!isReportComplete(item)) return false;
  if (item.delivered_at) return false;
  if (hasDue(item)) return false;
  return !!printedRows.value[item.id] || !!item.sent_at;
};

const canSend = (item) => {
  if (!isReportComplete(item)) return false;
  if (item.sent_at) return false;
  if (hasDue(item)) return false;
  return true;
};

const getPatientName = (item) => {
  const patient = item.billing?.patient;
  if (!patient) return 'N/A';
  return patient.name || `${patient.first_name ?? ''} ${patient.last_name ?? ''}`.trim() || 'N/A';
};

const sendReport = (itemId) => {
  router.post(route('backend.report-delivery.send', itemId), {}, { preserveScroll: true });
};

const deliverReport = (itemId) => {
  router.post(route('backend.report-delivery.deliver', itemId), {}, { preserveScroll: true });
};

const getReportStatus = (item) => {
  if (!item.sample_collected_at) {
    return { label: 'Pending', classes: 'text-amber-700 bg-amber-50 border-amber-200' };
  }
  if (!item.reported_at) {
    return { label: 'Processing', classes: 'text-blue-700 bg-blue-50 border-blue-200' };
  }
  return { label: 'Complete', classes: 'text-emerald-700 bg-emerald-50 border-emerald-200' };
};

const isReportComplete = (item) => !!item.reported_at;

const handleSearch = () => {
  router.get(
    route('backend.report-delivery.index'),
    {
      search: search.value,
      numOfData: props.datas?.per_page ?? 10,
    },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    }
  );
};

const goBack = () => {
  if (window.history.length > 1) {
    window.history.back();
    return;
  }

  router.get(route('backend.dashboard'));
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-3 p-4 bg-gray-100 rounded">
        <h1 class="text-lg font-semibold text-gray-800">{{ pageTitle }}</h1>
        <div class="flex flex-wrap items-center gap-2 ml-auto">
          <input
            v-model="search"
            type="text"
            placeholder="Bill no / patient / test"
            class="w-56 px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-indigo-200"
            @keyup.enter="handleSearch"
          />
          <button
            type="button"
            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded hover:bg-indigo-700"
            @click="handleSearch"
          >
            Search
          </button>
          <button
            type="button"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50"
            @click="goBack"
          >
            Back
          </button>
        </div>
      </div>

      <div class="w-full my-4 overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700 border">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 border">Bill No</th>
              <th class="px-3 py-2 border">Patient</th>
              <th class="px-3 py-2 border">Test</th>
              <th class="px-3 py-2 border">Collected By</th>
              <th class="px-3 py-2 border">Reported By</th>
              <th class="px-3 py-2 border">Delivery Date &amp; Time</th>
              <th class="px-3 py-2 border">Delivered By</th>
              <th class="px-3 py-2 border">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in rows" :key="item.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 border">{{ item.billing?.bill_number ?? 'N/A' }}</td>
              <td class="px-3 py-2 border">{{ getPatientName(item) }}</td>
              <td class="px-3 py-2 border">{{ item.item_name ?? 'N/A' }}</td>
              <td class="px-3 py-2 border">{{ item.collected_by?.name ?? 'N/A' }}</td>
              <td class="px-3 py-2 border">{{ item.reported_by?.name ?? 'N/A' }}</td>
              <td class="px-3 py-2 border">{{ formatDateTime(item.delivered_at) }}</td>
              <td class="px-3 py-2 border">{{ item.delivered_by?.name ?? 'N/A' }}</td>
              <td class="px-3 py-2 border">
                <div class="flex flex-wrap items-center gap-2">
                  <span
                    class="inline-flex items-center px-2 py-0.5 text-[10px] font-semibold border rounded"
                    :class="getReportStatus(item).classes"
                  >
                    {{ getReportStatus(item).label }}
                  </span>
                  <a
                    v-if="isReportComplete(item)"
                    class="px-3 py-1 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700"
                    :href="getPrintHref(item)"
                    :target="hasDue(item) ? null : '_blank'"
                    rel="noopener"
                    @click="markPrinted(item.id)"
                  >
                    Print
                  </a>
                  <button
                    v-else
                    type="button"
                    class="px-3 py-1 text-xs text-white bg-indigo-300 rounded cursor-not-allowed"
                    disabled
                  >
                    Print
                  </button>
                  <a
                    v-if="item.report_file && isReportComplete(item)"
                    class="px-3 py-1 text-xs text-white bg-gray-700 rounded hover:bg-gray-800"
                    :href="`/storage/${item.report_file}`"
                    target="_blank"
                    rel="noopener"
                  >
                    Download
                  </a>
                  <button
                    type="button"
                    class="px-3 py-1 text-xs text-white bg-amber-600 rounded hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    @click="sendReport(item.id)"
                    :disabled="!canSend(item)"
                  >
                    Send
                  </button>
                  <button
                    type="button"
                    class="px-3 py-1 text-xs text-white bg-emerald-600 rounded hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    @click="deliverReport(item.id)"
                    :disabled="!canDeliver(item)"
                  >
                    Delivered
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="8" class="px-3 py-6 text-center text-gray-500">No reports found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination />
    </div>
  </BackendLayout>
</template>
