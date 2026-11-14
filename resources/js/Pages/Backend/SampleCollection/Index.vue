<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { displayWarning } from '@/responseMessage.js';
import {
  formatOfflineBillReference,
  getOfflineBillingEncryptionStatus,
  getOfflineBillingRequests,
} from '@/offlineBillingQueue.js';

const props = defineProps({
  datas: Object,
  filters: {
    type: Object,
    default: () => ({}),
  },
  pageTitle: {
    type: String,
    default: 'Sample Collection',
  },
  allowedCategories: {
    type: Array,
    default: () => [],
  },
});

const rows = computed(() => props.datas?.data ?? []);
const page = usePage();
const offlineAdminId = computed(() => Number(page.props.auth?.admin?.id || 0));
const offlinePendingSamples = ref([]);
const offlineQueueLocked = ref(false);
const offlineQueueError = ref('');
const printedBarcodes = ref({});
const search = ref(props.filters?.search ?? '');

const openBarcodePage = (billingId) => {
  const url = route('backend.sample-collection.barcode', billingId);

  const printWindow = window.open(url, '_blank');
  if (!printWindow) {
    displayWarning({ message: 'বারকোড পেজ খুলতে ব্রাউজারের pop-up অনুমতি দিন।' });
    return;
  }

  printWindow.opener = null;
  markBarcodePrinted(billingId);
};

const loadOfflinePendingSamples = async () => {
  if (!offlineAdminId.value) return;

  try {
    const encryptionStatus = await getOfflineBillingEncryptionStatus(offlineAdminId.value);
    const requests = await getOfflineBillingRequests(offlineAdminId.value);
    offlineQueueLocked.value = !encryptionStatus.unlocked && requests.some(
      (entry) => ['queued', 'syncing', 'needs_review'].includes(entry.status)
    );
    if (!encryptionStatus.unlocked) {
      offlinePendingSamples.value = [];
      offlineQueueError.value = '';
      return;
    }

    const allowedCategories = new Set(props.allowedCategories.map((category) => String(category).toLowerCase()));
    const query = String(search.value || '').trim().toLowerCase();
    const excludedCategories = new Set([
      'medicine', 'pharmacy', 'opd', 'ipd', 'appointment', 'service',
      'store inventory', 'blood bank', 'ambulance', 'disposable',
      'radiology', 'ecg', 'ultrasound', 'xray', 'x-ray',
      'ultrasonogram', 'ultrasonography',
    ]);

    offlinePendingSamples.value = requests
      .filter((entry) => (
        entry.routeName === 'backend.billing.store'
        && ['queued', 'syncing', 'needs_review'].includes(entry.status)
        && entry.data
        && !entry.data.ipd_patient_id
      ))
      .map((entry) => {
        const items = (Array.isArray(entry.data.items) ? entry.data.items : []).filter((item) => {
          const category = String(item.category || '').trim().toLowerCase();
          return allowedCategories.has(category) && !excludedCategories.has(category);
        });
        const patientName = entry.data.patient_name || 'Walk-in Patient';
        const reference = formatOfflineBillReference(entry.id);
        const matchesSearch = !query
          || reference.toLowerCase().includes(query)
          || String(patientName).toLowerCase().includes(query)
          || items.some((item) => String(item.name || '').toLowerCase().includes(query));

        return {
          id: entry.id,
          reference,
          patientName,
          status: entry.status,
          items,
          lastError: entry.lastError || '',
          createdAt: entry.createdAt,
          matchesSearch,
        };
      })
      .filter((entry) => entry.items.length > 0 && entry.matchesSearch)
      .reverse();
    offlineQueueError.value = '';
  } catch (error) {
    offlineQueueError.value = error instanceof Error
      ? error.message
      : 'Could not load the offline sample queue.';
  }
};

// modal state for per-item collection
const showCollectModal = ref(false);
const modalItems = ref([]);
const modalBilling = ref(null);
const collectingItemIds = ref({});
const collectedMap = ref({});

const getItems = (billing) => billing.bill_items ?? billing.billItems ?? [];

const getPatientName = (billing) => {
  const patient = billing?.patient;
  if (!patient) return 'N/A';
  if (patient.name) return patient.name;

  const legacyName = `${patient.first_name ?? ''} ${patient.last_name ?? ''}`.trim();
  return legacyName || 'N/A';
};

const getTestNames = (billing) => {
  const items = getItems(billing);
  if (!items.length) return 'N/A';
  return items.map((item) => item.item_name).join(', ');
};

const collectSample = (billingId) => {
  router.post(route('backend.sample-collection.collect', billingId), {}, {
    preserveScroll: true,
  });
};

const collectItem = async (item) => {
  if (collectingItemIds.value[item.id]) return;
  collectingItemIds.value = { ...collectingItemIds.value, [item.id]: true };
  try {
    await axios.post(route('backend.sample-collection.item.collect', item.id));
    // remove collected item from modal list
    modalItems.value = modalItems.value.filter((i) => i.id !== item.id);
    collectedMap.value = { ...collectedMap.value, [item.id]: true };

    if (modalItems.value.length === 0) {
      showCollectModal.value = false;
      router.reload();
    }
  } catch (e) {
    displayWarning({ message: 'Failed to collect sample for the test.' });
  } finally {
    collectingItemIds.value = { ...collectingItemIds.value, [item.id]: false };
  }
};

const collectAllFromModal = async () => {
  if (!modalBilling.value) return;
  try {
    await axios.post(route('backend.sample-collection.collect', modalBilling.value.id));
    showCollectModal.value = false;
    router.reload();
  } catch (e) {
    displayWarning({ message: 'Failed to collect samples.' });
  }
};

const openCollectModal = (billing) => {
  if (!canCollect(billing.id)) {
    displayWarning({ message: 'Print barcode first, then collect the sample.' });
    return;
  }
  modalBilling.value = billing;
  modalItems.value = (getItems(billing) || []).map((it) => ({ ...it }));
  showCollectModal.value = true;
};

const markBarcodePrinted = (billingId) => {
  printedBarcodes.value = {
    ...printedBarcodes.value,
    [billingId]: true,
  };
};

const canCollect = (billingId) => !!printedBarcodes.value[billingId];

const handleCollect = (billing) => {
  openCollectModal(billing);
};

const handleSearch = () => {
  loadOfflinePendingSamples();
  if (!navigator.onLine) return;

  router.get(
    route('backend.sample-collection.index'),
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

const handleOfflineQueueUpdated = () => loadOfflinePendingSamples();
const handleSyncedBillingRefresh = () => router.reload({ only: ['datas'] });

onMounted(() => {
  window.addEventListener('offline-billing:queue-updated', handleOfflineQueueUpdated);
  window.addEventListener('billing:list:refresh', handleSyncedBillingRefresh);
  window.addEventListener('sample-collection:list:refresh', handleSyncedBillingRefresh);
  loadOfflinePendingSamples();
});

onUnmounted(() => {
  window.removeEventListener('offline-billing:queue-updated', handleOfflineQueueUpdated);
  window.removeEventListener('billing:list:refresh', handleSyncedBillingRefresh);
  window.removeEventListener('sample-collection:list:refresh', handleSyncedBillingRefresh);
});
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
            placeholder="Bill no / patient / item"
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

      <section v-if="offlinePendingSamples.length || offlineQueueLocked || offlineQueueError" class="w-full my-4 rounded-md border border-amber-300 bg-amber-50 p-3">
        <h2 class="mb-2 text-sm font-semibold text-amber-900">Offline samples waiting to synchronize</h2>
        <p v-if="offlineQueueLocked" class="mb-2 text-sm text-amber-800">
          Unlock Offline Billing from the billing page to view this browser's unsynchronized samples.
        </p>
        <p v-if="offlineQueueError" class="mb-2 text-sm text-red-700">{{ offlineQueueError }}</p>
        <div v-if="offlinePendingSamples.length" class="overflow-x-auto">
          <table class="w-full min-w-[650px] border-collapse text-sm text-gray-800">
            <thead class="bg-amber-100">
              <tr>
                <th class="border px-3 py-2 text-left">Offline Reference</th>
                <th class="border px-3 py-2 text-left">Patient</th>
                <th class="border px-3 py-2 text-left">Pending Items</th>
                <th class="border px-3 py-2 text-center">Count</th>
                <th class="border px-3 py-2 text-left">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="billing in offlinePendingSamples" :key="billing.id">
                <td class="border px-3 py-2 font-mono">{{ billing.reference }}</td>
                <td class="border px-3 py-2">{{ billing.patientName }}</td>
                <td class="border px-3 py-2">{{ billing.items.map((item) => item.name).join(', ') }}</td>
                <td class="border px-3 py-2 text-center">{{ billing.items.length }}</td>
                <td class="border px-3 py-2">
                  <span v-if="billing.status === 'needs_review'" class="font-semibold text-red-700">Needs review</span>
                  <span v-else-if="billing.status === 'syncing'" class="font-semibold text-blue-700">Synchronizing</span>
                  <span v-else class="font-semibold text-amber-800">Waiting to sync</span>
                  <span v-if="billing.lastError" class="mt-1 block text-red-700">{{ billing.lastError }}</span>
                </td>
              </tr>
            </tbody>
          </table>
          <p class="mt-2 text-xs text-amber-800">
            Actual bill and sample item IDs are assigned by the server during synchronization. Sample collection becomes available in the regular list after those IDs are created.
          </p>
        </div>
      </section>

      <div class="w-full my-4 overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700 border">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 border">Bill No</th>
              <th class="px-3 py-2 border">Patient</th>
              <th class="px-3 py-2 border">Item Names</th>
              <th class="px-3 py-2 border">Item Count</th>
              <th class="px-3 py-2 border">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="billing in rows" :key="billing.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 border">{{ billing.bill_number ?? 'N/A' }}</td>
              <td class="px-3 py-2 border">
                {{ getPatientName(billing) }}
              </td>
              <td class="px-3 py-2 border" :title="getTestNames(billing)">
                {{ getTestNames(billing) }}
              </td>
              <td class="px-3 py-2 border">{{ getItems(billing).length }}</td>
              <td class="px-3 py-2 border">
                <div class="flex flex-wrap gap-2">
                  <button
                    type="button"
                    class="px-3 py-1.5 text-xs font-semibold text-white bg-emerald-600 rounded-md shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-300 disabled:bg-slate-400 disabled:hover:bg-slate-400 disabled:cursor-not-allowed"
                    :disabled="!canCollect(billing.id)"
                    @click="handleCollect(billing)"
                  >
                    Collect Sample
                  </button>
                  <button
                    type="button"
                    class="px-3 py-1.5 text-xs font-semibold text-white bg-amber-600 rounded-md shadow-sm hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-300"
                    @click="openBarcodePage(billing.id)"
                  >
                    Print Barcode
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="5" class="px-3 py-6 text-center text-gray-500">No pending samples.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Per-test collect modal -->
      <div v-if="showCollectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="bg-white rounded shadow p-4 w-full max-w-2xl">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-semibold">Collect Samples - {{ modalBilling?.bill_number || '' }}</h2>
            <div class="flex items-center gap-2">
              <button
                type="button"
                class="px-3 py-1 text-sm text-gray-700 bg-white border rounded hover:bg-gray-50"
                @click="showCollectModal = false"
              >
                Close
              </button>
              <button
                type="button"
                class="px-3 py-1 text-sm text-white bg-green-600 rounded hover:bg-green-700"
                @click="collectAllFromModal"
              >
                Collect All
              </button>
            </div>
          </div>

          <div class="overflow-y-auto max-h-72">
            <table class="w-full text-sm text-left">
              <thead>
                <tr>
                  <th class="pb-2">Test</th>
                  <th class="pb-2 text-right">Action</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in modalItems" :key="item.id" class="border-t">
                  <td class="py-2">{{ item.item_name }}</td>
                  <td class="py-2 text-right">
                    <button
                      type="button"
                      class="px-3 py-1 text-xs text-white bg-blue-600 rounded hover:bg-blue-700 disabled:opacity-50"
                      :disabled="collectingItemIds[item.id]"
                      @click="collectItem(item)"
                    >
                      <span v-if="collectingItemIds[item.id]">Collecting...</span>
                      <span v-else>Collect</span>
                    </button>
                  </td>
                </tr>
                <tr v-if="modalItems.length === 0">
                  <td colspan="2" class="py-6 text-center text-gray-500">No uncollected items.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <Pagination />
    </div>
  </BackendLayout>
</template>
