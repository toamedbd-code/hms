<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { displayWarning } from '@/responseMessage.js';
import ActionButton from '@/Components/ActionButton.vue';

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
});

const rows = computed(() => props.datas?.data ?? []);
const printedBarcodes = ref({});
const search = ref(props.filters?.search ?? '');

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
          <ActionButton type="button" @click="handleSearch" variant="primary">Search</ActionButton>
          <ActionButton type="button" @click="goBack" variant="muted">Back</ActionButton>
        </div>
      </div>

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
              <td class="px-3 py-2 border">{{ getTestNames(billing) }}</td>
              <td class="px-3 py-2 border">{{ getItems(billing).length }}</td>
              <td class="px-3 py-2 border">
                <div class="flex flex-wrap gap-2">
                  <ActionButton
                    type="button"
                    variant="success"
                    :disabled="!canCollect(billing.id)"
                    @click="handleCollect(billing)"
                  >
                    Collect Sample
                  </ActionButton>
                  <ActionButton
                    :href="route('backend.sample-collection.barcode', billing.id)"
                    external
                    variant="indigo"
                    @click="markBarcodePrinted(billing.id)"
                  >
                    Print Barcode
                  </ActionButton>
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
      <div v-if="showCollectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
        <div class="bg-white rounded shadow p-4 w-full max-w-2xl text-gray-800">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-semibold text-gray-900">Collect Samples - {{ modalBilling?.bill_number || '' }}</h2>
            <div class="flex items-center gap-2">
              <ActionButton type="button" variant="muted" @click="showCollectModal = false">Close</ActionButton>
              <ActionButton type="button" variant="success" @click="collectAllFromModal">Collect All</ActionButton>
            </div>
          </div>

          <div class="overflow-y-auto max-h-72">
            <table class="w-full text-sm text-left text-gray-700">
              <thead>
                <tr>
                  <th class="pb-2 text-gray-600">Test</th>
                  <th class="pb-2 text-right text-gray-600">Action</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in modalItems" :key="item.id" class="border-t">
                  <td class="py-2">{{ item.item_name }}</td>
                  <td class="py-2 text-right">
                    <ActionButton
                      type="button"
                      variant="success"
                      :disabled="collectingItemIds[item.id]"
                      @click="collectItem(item)"
                    >
                      <span v-if="collectingItemIds[item.id]">Collecting...</span>
                      <span v-else>Collect</span>
                    </ActionButton>
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
