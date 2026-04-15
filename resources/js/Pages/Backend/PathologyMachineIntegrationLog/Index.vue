<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { successMessage, warningMessage } from '@/responseMessage.js';

const props = defineProps({
  pageTitle: {
    type: String,
    default: 'Pathology Machine Integration Logs',
  },
  logs: {
    type: Object,
    default: () => ({ data: [] }),
  },
  events: {
    type: Array,
    default: () => [],
  },
  levels: {
    type: Array,
    default: () => [],
  },
  formats: {
    type: Array,
    default: () => [],
  },
  communicationModes: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
});

const filterForm = ref({
  event: props.filters?.event ?? '',
  level: props.filters?.level ?? '',
  source_format: props.filters?.source_format ?? '',
  communication_mode: props.filters?.communication_mode ?? '',
  date_from: props.filters?.date_from ?? '',
  date_to: props.filters?.date_to ?? '',
  search: props.filters?.search ?? '',
  per_page: Number(props.logs?.per_page ?? 25),
});

const rows = computed(() => props.logs?.data ?? []);
const payloadModalOpen = ref(false);
const payloadModalTitle = ref('');
const payloadModalContent = ref('');
const retryLoadingId = ref(null);

const exportUrl = computed(() => route('backend.pathology-machine-logs.export', filterForm.value));

const applyFilter = () => {
  router.get(route('backend.pathology-machine-logs.index'), filterForm.value, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilter = () => {
  filterForm.value = {
    event: '',
    level: '',
    source_format: '',
    communication_mode: '',
    date_from: '',
    date_to: '',
    search: '',
    per_page: 25,
  };
  applyFilter();
};

const applyFailedOnly = () => {
  filterForm.value.level = 'warning';
  applyFilter();
};

const openPayloadModal = (item) => {
  payloadModalTitle.value = `Log #${item.id} - Raw Payload`;
  payloadModalContent.value = item.raw_payload || 'Raw payload was not saved for this event.';
  payloadModalOpen.value = true;
};

const closePayloadModal = () => {
  payloadModalOpen.value = false;
};

const retrySimulate = async (item) => {
  if (!item?.id) return;

  if (!window.confirm('এই log payload দিয়ে retry simulation চালাবেন?')) {
    return;
  }

  try {
    retryLoadingId.value = item.id;
    const response = await axios.post(route('backend.pathology-machine-logs.retry-simulate', item.id));
    const message = response?.data?.message || 'Retry simulation completed.';
    successMessage(message);
    applyFilter();
  } catch (error) {
    const message = error?.response?.data?.message || 'Retry simulation failed.';
    warningMessage(message);
  } finally {
    retryLoadingId.value = null;
  }
};

const contextPreview = (context) => {
  if (!context) return '-';
  const text = JSON.stringify(context);
  if (text.length <= 80) return text;
  return `${text.slice(0, 80)}...`;
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-2 p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
        <div class="flex items-center gap-2">
          <a :href="exportUrl" class="px-3 py-2 text-sm text-white bg-emerald-600 rounded hover:bg-emerald-700">Export CSV</a>
          <button type="button" class="px-3 py-2 text-sm text-white bg-amber-600 rounded hover:bg-amber-700" @click="applyFailedOnly">Failed Only</button>
          <button type="button" class="px-3 py-2 text-sm text-white bg-gray-600 rounded hover:bg-gray-700" @click="resetFilter">Reset</button>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-2 p-3 mt-3 bg-slate-100 rounded md:grid-cols-8">
        <div>
          <input v-model="filterForm.search" type="text" class="w-full p-2 text-sm border border-gray-300 rounded" placeholder="Search request/message/ip" @keyup.enter="applyFilter" />
        </div>

        <div>
          <select v-model="filterForm.event" class="w-full p-2 text-sm border border-gray-300 rounded" @change="applyFilter">
            <option value="">All Event</option>
            <option v-for="item in events" :key="item" :value="item">{{ item }}</option>
          </select>
        </div>

        <div>
          <select v-model="filterForm.level" class="w-full p-2 text-sm border border-gray-300 rounded" @change="applyFilter">
            <option value="">All Level</option>
            <option v-for="item in levels" :key="item" :value="item">{{ item }}</option>
          </select>
        </div>

        <div>
          <select v-model="filterForm.source_format" class="w-full p-2 text-sm border border-gray-300 rounded" @change="applyFilter">
            <option value="">All Format</option>
            <option v-for="item in formats" :key="item" :value="item">{{ item }}</option>
          </select>
        </div>

        <div>
          <select v-model="filterForm.communication_mode" class="w-full p-2 text-sm border border-gray-300 rounded" @change="applyFilter">
            <option value="">All Communication</option>
            <option v-for="item in communicationModes" :key="item" :value="item">{{ item }}</option>
          </select>
        </div>

        <div>
          <input v-model="filterForm.date_from" type="date" class="w-full p-2 text-sm border border-gray-300 rounded" @change="applyFilter" />
        </div>

        <div>
          <input v-model="filterForm.date_to" type="date" class="w-full p-2 text-sm border border-gray-300 rounded" @change="applyFilter" />
        </div>

        <div class="flex items-center gap-2">
          <select v-model="filterForm.per_page" class="w-full p-2 text-sm border border-gray-300 rounded" @change="applyFilter">
            <option :value="10">Show 10</option>
            <option :value="25">Show 25</option>
            <option :value="50">Show 50</option>
            <option :value="100">Show 100</option>
          </select>
          <button type="button" class="w-full px-2 py-2 text-sm text-white bg-gray-600 rounded hover:bg-gray-700" @click="resetFilter">Reset</button>
        </div>
      </div>

      <div class="w-full mt-4 overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700 border border-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 border">Time</th>
              <th class="px-3 py-2 border">Request ID</th>
              <th class="px-3 py-2 border">Event</th>
              <th class="px-3 py-2 border">Level</th>
              <th class="px-3 py-2 border">Format</th>
              <th class="px-3 py-2 border">Communication</th>
              <th class="px-3 py-2 border">IP</th>
              <th class="px-3 py-2 border">Message</th>
              <th class="px-3 py-2 border">Context</th>
              <th class="px-3 py-2 border">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in rows" :key="item.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 border whitespace-nowrap">{{ item.created_at }}</td>
              <td class="px-3 py-2 border">{{ item.request_id || '-' }}</td>
              <td class="px-3 py-2 border">{{ item.event || '-' }}</td>
              <td class="px-3 py-2 border">
                <span class="px-2 py-1 text-xs rounded uppercase" :class="item.level === 'error' ? 'bg-rose-100 text-rose-700' : item.level === 'warning' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'">
                  {{ item.level || '-' }}
                </span>
              </td>
              <td class="px-3 py-2 border">{{ item.source_format || '-' }}</td>
              <td class="px-3 py-2 border">{{ item.communication_mode || '-' }}</td>
              <td class="px-3 py-2 border">{{ item.ip_address || '-' }}</td>
              <td class="px-3 py-2 border">{{ item.message || '-' }}</td>
              <td class="px-3 py-2 border" :title="JSON.stringify(item.context || {})">{{ contextPreview(item.context) }}</td>
              <td class="px-3 py-2 border">
                <div class="flex flex-wrap items-center gap-2">
                  <button type="button" class="px-2 py-1 text-xs text-white bg-sky-600 rounded hover:bg-sky-700" @click="openPayloadModal(item)">
                    Payload
                  </button>
                  <button
                    type="button"
                    class="px-2 py-1 text-xs text-white bg-emerald-600 rounded hover:bg-emerald-700 disabled:opacity-60"
                    :disabled="retryLoadingId === item.id || !item.raw_payload"
                    @click="retrySimulate(item)"
                  >
                    {{ retryLoadingId === item.id ? 'Retrying...' : 'Retry Simulate' }}
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="rows.length === 0">
              <td colspan="10" class="px-3 py-6 text-center text-gray-500 border">No integration logs found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="props.logs?.links?.length" class="grid grid-cols-1 gap-4 pt-2 my-4 md:grid-cols-2 items-center">
        <p class="text-sm text-gray-600 text-center md:text-left">
          Displaying {{ props.logs?.from ?? 0 }} to {{ props.logs?.to ?? 0 }} of {{ props.logs?.total ?? 0 }} items
        </p>

        <nav>
          <ul class="flex items-center justify-center md:justify-end gap-2">
            <li v-for="(link, index) in props.logs.links" :key="`${index}-${link.label}`">
              <a
                v-if="link.url"
                :href="link.url"
                class="px-3 py-2 text-sm border border-gray-300 rounded hover:bg-gray-100"
                :class="{ 'bg-indigo-600 text-white border-indigo-600 hover:bg-indigo-700': link.active }"
              >
                <span v-html="link.label"></span>
              </a>
              <span v-else class="px-3 py-2 text-sm text-gray-400 border border-gray-200 rounded" v-html="link.label"></span>
            </li>
          </ul>
        </nav>
      </div>

      <div v-if="payloadModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
        <div class="w-full max-w-4xl p-4 bg-white rounded shadow-lg">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-semibold text-gray-800">{{ payloadModalTitle }}</h2>
            <button type="button" class="px-3 py-1 text-sm text-white bg-gray-600 rounded hover:bg-gray-700" @click="closePayloadModal">Close</button>
          </div>
          <pre class="p-3 overflow-auto text-xs text-gray-800 bg-gray-100 rounded max-h-[60vh] whitespace-pre-wrap">{{ payloadModalContent }}</pre>
        </div>
      </div>
    </div>
  </BackendLayout>
</template>
