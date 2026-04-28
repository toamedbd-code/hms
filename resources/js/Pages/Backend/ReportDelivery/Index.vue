<script setup>
import { computed, ref, nextTick } from 'vue';
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

const rows = computed(() => props.datas?.data ?? []);
const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');

const getItems = (billing) => billing.bill_items ?? billing.billItems ?? [];

const hasDue = (billing) => Number(billing?.due_amount ?? 0) > 0;

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

// (no Open button required per UI change)

const getPatientName = (billing) => {
  const patient = billing?.patient;
  if (!patient) return 'N/A';
  return patient.name || `${patient.first_name ?? ''} ${patient.last_name ?? ''}`.trim() || 'N/A';
};

const getTestNames = (billing) => {
  const items = getItems(billing);
  if (!items.length) return 'N/A';
  return items.map((item) => item.item_name).join(', ');
};

const getTestCount = (billing) => getItems(billing).length;

const getCollectedBy = (billing) => {
  const items = getItems(billing);
  for (const it of items) {
    if (it.collected_by && it.collected_by.name) return it.collected_by.name;
    if (it.collectedBy && it.collectedBy.name) return it.collectedBy.name;
  }
  return 'N/A';
};

const getReportedBy = (billing) => {
  const items = getItems(billing);
  for (const it of items) {
    if (it.reported_by && it.reported_by.name) return it.reported_by.name;
    if (it.reportedBy && it.reportedBy.name) return it.reportedBy.name;
  }
  return 'N/A';
};

const canSend = (billing) => {
  if (hasDue(billing)) return false;
  const items = getItems(billing);
  return items.some((it) => it.reported_at && !it.sent_at);
};

const canDeliver = (billing) => {
  if (hasDue(billing)) return false;
  const items = getItems(billing);
  return items.some((it) => it.reported_at && !it.delivered_at);
};

const getDeliveredBy = (billing) => {
  const items = getItems(billing);
  for (const it of items) {
    if (it.delivered_by && it.delivered_by.name) return it.delivered_by.name;
    if (it.deliveredBy && it.deliveredBy.name) return it.deliveredBy.name;
  }
  return 'N/A';
};

const getReportStatus = (billing) => {
  const items = getItems(billing);
  if (!items.length) return { label: 'N/A', classes: 'text-gray-600 bg-gray-50 border-gray-200' };

  const anyCollected = items.some((it) => it.sample_collected_at);
  const allReported = items.every((it) => it.reported_at);

  if (!anyCollected) return { label: 'Pending', classes: 'text-amber-700 bg-amber-50 border-amber-200' };
  if (!allReported) return { label: 'Processing', classes: 'text-blue-700 bg-blue-50 border-blue-200' };
  return { label: 'Complete', classes: 'text-emerald-700 bg-emerald-50 border-emerald-200' };
};

const isReportComplete = (billing) => getItems(billing).every((it) => it.reported_at);

const handleSearch = () => {
  router.get(
    route('backend.report-delivery.index'),
    {
      search: search.value,
      numOfData: props.datas?.per_page ?? 10,
      status: status.value,
    },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    }
  );
};

const sendAll = (billingId) => {
  router.post(route('backend.report-delivery.sendAll', billingId), {}, { preserveScroll: true });
};

const deliverAll = (billingId) => {
  router.post(route('backend.report-delivery.deliverAll', billingId), {}, { preserveScroll: true });
};

const processingDeliver = ref({});

const getDeliveryDate = (billing) => {
  const items = getItems(billing).map((it) => it.delivered_at).filter(Boolean);
  if (!items.length) return null;
  // find latest delivered_at
  const latest = items.reduce((a, b) => (new Date(a) > new Date(b) ? a : b));
  return formatDateTime(latest);
};

const canPrint = (billing) => {
  const items = getItems(billing);
  return items.some((it) => it.reported_at);
};

const getReportedItems = (billing) => getItems(billing).filter((it) => it.reported_at);

const getItemStatus = (it) => {
  if (!it) return { label: 'N/A', classes: 'text-gray-600 bg-gray-50 border-gray-200' };
  if (!it.sample_collected_at) return { label: 'Pending', classes: 'text-amber-700 bg-amber-50 border-amber-200' };
  if (!it.reported_at) return { label: 'Processing', classes: 'text-blue-700 bg-blue-50 border-blue-200' };
  return { label: 'Complete', classes: 'text-emerald-700 bg-emerald-50 border-emerald-200' };
};

// Helpers to open windows without keeping an opener reference (helps avoid cross-window focus/blocks)
const openNoopener = (name = '_blank') => {
  try {
    const w = window.open('', name);
    try {
      if (w) w.opener = null;
    } catch (e) {
      // ignore
    }
    return w;
  } catch (e) {
    return null;
  }
};

const openNoopenerUrl = (url, name = '_blank') => {
  try {
    const w = window.open(url, name);
    try {
      if (w) w.opener = null;
    } catch (e) {
      // ignore
    }
    return w;
  } catch (e) {
    try {
      return window.open(url, name);
    } catch (ee) {
      return null;
    }
  }
};

// Print picker state
const showPrintPicker = ref(false);
const printBilling = ref(null);
const printCandidates = ref([]);
const selectedPrintIds = ref([]);
const pendingPrintItems = ref([]); // used when Print clicked but billing has due

const selectAllPrint = () => {
  selectedPrintIds.value = printCandidates.value.map((it) => it.id);
};

const openPrintPicker = (billing) => {
  printBilling.value = billing;
  printCandidates.value = getReportedItems(billing);
  selectedPrintIds.value = [];
  // no auto-selection of print options
  showPrintPicker.value = true;
};

const printItem = (it, billing) => {
  if (!it || !billing?.id) return;

  // if billing has due, route through due-collect for this single item
  if (hasDue(billing)) {
    pendingPrintItems.value = [it];
    openDueModal(billing);
    return;
  }

  const win = openNoopener();
  processingDeliver.value = { ...processingDeliver.value, [billing.id]: true };

  router.post(
    route('backend.report-delivery.deliverAll', billing.id),
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
          try {
            const url = route('backend.reporting.print', it.id);
            if (win) win.location = url;
            else openNoopenerUrl(url);
          } catch (e) {
            // ignore
          }
        processingDeliver.value = { ...processingDeliver.value, [billing.id]: false };
      },
        onError: () => {
          try {
            openNoopenerUrl(route('backend.reporting.print', it.id));
          } catch (e) {
            // ignore
          }
          processingDeliver.value = { ...processingDeliver.value, [billing.id]: false };
        },
    }
  );
};

const printSelected = (billing) => {
  if (!billing?.id) return;
  const ids = selectedPrintIds.value ?? [];
  const items = printCandidates.value.filter((it) => ids.includes(it.id));
  if (!items.length) return;

  // If there is due on this billing, defer to due-collect flow with the selected items
  if (hasDue(billing)) {
    pendingPrintItems.value = items;
    openDueModal(billing);
    return;
  }

  // open placeholder windows now (user gesture) to avoid popup blockers
  const wins = items.map(() => openNoopener());

  processingDeliver.value = { ...processingDeliver.value, [billing.id]: true };

  router.post(
    route('backend.report-delivery.deliverAll', billing.id),
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
        items.forEach((it, idx) => {
          const w = wins[idx];
          try {
            const url = route('backend.reporting.print', it.id);
            if (w) w.location = url;
            else openNoopenerUrl(url);
          } catch (e) {
            // ignore
          }
        });

        processingDeliver.value = { ...processingDeliver.value, [billing.id]: false };
        showPrintPicker.value = false;
        handleSearch();
      },
      onError: () => {
        items.forEach((it) => {
          try {
            openNoopenerUrl(route('backend.reporting.print', it.id));
          } catch (e) {
            // ignore
          }
        });
        processingDeliver.value = { ...processingDeliver.value, [billing.id]: false };
        showPrintPicker.value = false;
        handleSearch();
      },
    }
  );
};

const showDueModal = ref(false);
const dueAmount = ref(0);
const dueBilling = ref(null);
const payInput = ref(null);
const requireFullPayment = ref(true);
const partialCollected = ref(false);
const isReady = computed(() => {
  if (!dueBilling.value) return false;
  if (!requireFullPayment.value) return true;
  return Number(dueAmount.value ?? 0) >= Number(dueBilling.value?.due_amount ?? 0);
});

const openDueModal = (billing) => {
  dueBilling.value = billing;
  dueAmount.value = Number(billing?.due_amount ?? 0);
  partialCollected.value = false;
  showDueModal.value = true;
};

const fillFullDue = async () => {
  if (!dueBilling.value) return;
  dueAmount.value = Number(dueBilling.value?.due_amount ?? 0);
  await nextTick();
  try {
    if (payInput.value && payInput.value.focus) payInput.value.focus();
  } catch (e) {
    // ignore
  }
};

const submitDueCollect = () => {
  if (!dueBilling.value?.id) return;

  // items user wanted to print after collecting due (set when Print was clicked)
  const itemsToPrint = pendingPrintItems.value ?? [];
  // open placeholder windows now (user gesture) — we'll close them if we don't proceed
  const wins = itemsToPrint.map(() => openNoopener());

  router.post(
    route('backend.due.collect.store', dueBilling.value.id),
    { amount: dueAmount.value },
    {
      preserveScroll: true,
      onSuccess: () => {
        // update local billing amounts to reflect collection (optimistic)
        const oldDue = Number(dueBilling.value?.due_amount ?? 0);
        const paid = Number(dueAmount.value ?? 0);
        const remainingAfter = oldDue - paid;

        dueBilling.value = {
          ...dueBilling.value,
          due_amount: remainingAfter > 0 ? remainingAfter : 0,
          paid_amount: Number(dueBilling.value?.paid_amount ?? 0) + paid,
        };

        handleSearch();

        // If full payment is required and we still have remaining due, do NOT print/deliver.
        if (requireFullPayment.value && remainingAfter > 0) {
          // Close any placeholder windows opened earlier since we won't use them
          try {
            wins.forEach((w) => {
              if (w && !w.closed) w.close();
            });
          } catch (e) {
            // ignore
          }

          partialCollected.value = true;
          // keep modal open so user can collect remaining amount
          return;
        }

        // proceed to deliver and print
        processingDeliver.value = { ...processingDeliver.value, [dueBilling.value.id]: true };

        router.post(
          route('backend.report-delivery.deliverAll', dueBilling.value.id),
          {},
          {
            preserveScroll: true,
            onSuccess: () => {
              try {
                if (itemsToPrint.length) {
                  itemsToPrint.forEach((it, idx) => {
                    const w = wins[idx];
                    const url = route('backend.reporting.print', it.id);
                    if (w) w.location = url;
                    else openNoopenerUrl(url);
                  });
                }
              } catch (e) {
                // ignore
              }

              processingDeliver.value = { ...processingDeliver.value, [dueBilling.value.id]: false };
              showDueModal.value = false;
              pendingPrintItems.value = [];
              partialCollected.value = false;
              handleSearch();
            },
            onError: () => {
              if (itemsToPrint.length) {
                itemsToPrint.forEach((it) => {
                  try {
                    openNoopenerUrl(route('backend.reporting.print', it.id));
                  } catch (e) {
                    // ignore
                  }
                });
              }
              processingDeliver.value = { ...processingDeliver.value, [dueBilling.value.id]: false };
              showDueModal.value = false;
              pendingPrintItems.value = [];
              partialCollected.value = false;
              handleSearch();
            },
          }
        );
        // attempt early navigation to speed up perceived print opening when allowed
        try {
          if (isReady && isReady.value) {
            setTimeout(() => {
              try {
                if (itemsToPrint.length) {
                  itemsToPrint.forEach((it, idx) => {
                    const w = wins[idx];
                    const url = route('backend.reporting.print', it.id);
                    if (w) w.location = url;
                    else openNoopenerUrl(url);
                  });
                }
              } catch (e) {
                // ignore
              }
            }, 50);
          }
        } catch (e) {
          // ignore
        }
      },
      onError: () => {
        // leave modal open so user can retry
      },
    }
  );
};

// (interactive projections removed - keep modal simple)

const handlePrint = (billing) => {
  if (!canPrint(billing)) return;

  const reported = getReportedItems(billing);
  if (!reported.length) return;

  if (hasDue(billing)) {
    // If there is due and multiple reported items, open the print picker first
    if (reported.length > 1) {
      // allow user to choose which reports and per-item options before collecting due
      openPrintPicker(billing);
    } else {
      // single reported item: store pending and open due modal
      pendingPrintItems.value = reported;
      openDueModal(billing);
    }
    return;
  }

  if (reported.length === 1) {
    const firstReported = reported[0];
    processingDeliver.value = { ...processingDeliver.value, [billing.id]: true };

    // open placeholder window now to avoid popup blocker
    const win = openNoopener();

    router.post(
      route('backend.report-delivery.deliverAll', billing.id),
      {},
      {
        preserveScroll: true,
        onSuccess: () => {
          try {
            const url = route('backend.reporting.print', firstReported.id);
            if (win) win.location = url;
            else openNoopenerUrl(url);
          } catch (e) {
            // ignore popup failures
          }
          processingDeliver.value = { ...processingDeliver.value, [billing.id]: false };
        },
        onError: () => {
          processingDeliver.value = { ...processingDeliver.value, [billing.id]: false };
        },
      }
    );

    return;
  }

  // multiple reported items — show picker modal
  openPrintPicker(billing);
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
          <select
            v-model="status"
            @change="handleSearch"
            class="px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-indigo-200"
          >
            <option value="">All</option>
            <option value="pending">Pending</option>
            <option value="processing">Processing</option>
            <option value="complete">Complete</option>
          </select>
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
              <th class="px-3 py-2 border">Items</th>
              <th class="px-3 py-2 border">Collected By</th>
              <th class="px-3 py-2 border">Reported By</th>
              <th class="px-3 py-2 border">Status</th>
              <th class="px-3 py-2 border">Delivery Date &amp; Time</th>
              <th class="px-3 py-2 border">Delivered By</th>
              <th class="px-3 py-2 border">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="billing in rows" :key="billing.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 border">{{ billing.bill_number ?? 'N/A' }}</td>
              <td class="px-3 py-2 border">{{ getPatientName(billing) }}</td>
              <td class="px-3 py-2 border">{{ getTestNames(billing) }}</td>
              <td class="px-3 py-2 border">{{ getCollectedBy(billing) }}</td>
              <td class="px-3 py-2 border">{{ getReportedBy(billing) }}</td>
              <td class="px-3 py-2 border">
                <span
                  class="inline-flex items-center px-2 py-0.5 text-[10px] font-semibold border rounded"
                  :class="getReportStatus(billing).classes"
                >
                  {{ getReportStatus(billing).label }}
                </span>
              </td>
              <td class="px-3 py-2 border">{{ getDeliveryDate(billing) ?? 'N/A' }}</td>
              <td class="px-3 py-2 border">{{ getDeliveredBy(billing) }}</td>
              <td class="px-3 py-2 border">
                <div class="flex flex-wrap items-center gap-2">
                  <button
                    type="button"
                    class="px-3 py-1 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    @click="handlePrint(billing)"
                    :disabled="!canPrint(billing) || processingDeliver[billing.id]"
                  >
                    Print
                  </button>
                  <button
                    type="button"
                    class="px-3 py-1 text-xs text-white bg-emerald-600 rounded hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    @click="deliverAll(billing.id)"
                    :disabled="!canDeliver(billing)"
                  >
                    Delivered
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="9" class="px-3 py-6 text-center text-gray-500">No reports found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination />

      <!-- Print picker modal -->
      <div v-if="showPrintPicker" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="w-full max-w-2xl p-4 bg-white rounded shadow-lg text-gray-800" @click.self="showPrintPicker = false">
          <h3 class="mb-3 text-lg font-semibold text-gray-900">Print Reports</h3>
          <p class="text-sm text-gray-600 mb-3">Bill: {{ printBilling?.bill_number ?? '' }} — Patient: {{ getPatientName(printBilling) }}</p>

          <div class="mb-3 max-h-64 overflow-auto">
            <table class="w-full text-sm text-left text-gray-700">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2">Select</th>
                  <th class="px-3 py-2">Test</th>
                  <th class="px-3 py-2">Status</th>
                  <th class="px-3 py-2">Reported At</th>
                  <th class="px-3 py-2">Reported By</th>
                  <th class="px-3 py-2">Action</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="it in printCandidates" :key="it.id" class="border-b">
                  <td class="px-3 py-2"><input type="checkbox" :value="it.id" v-model="selectedPrintIds" /></td>
                  <td class="px-3 py-2">{{ it.item_name ?? it.test_name ?? 'N/A' }}</td>
                  <td class="px-3 py-2">
                    <span
                      class="inline-flex items-center px-2 py-0.5 text-[10px] font-semibold border rounded"
                      :class="getItemStatus(it).classes"
                    >
                      {{ getItemStatus(it).label }}
                    </span>
                  </td>
                  <td class="px-3 py-2">{{ formatDateTime(it.reported_at) ?? 'N/A' }}</td>
                  <td class="px-3 py-2">{{ (it.reported_by && it.reported_by.name) || (it.reportedBy && it.reportedBy.name) || 'N/A' }}</td>
                  <td class="px-3 py-2">
                    <button
                      type="button"
                      class="px-2 py-1 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700"
                      @click="printItem(it, printBilling)"
                      :disabled="processingDeliver[printBilling?.id]"
                    >
                      Print
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="flex items-center justify-end gap-2">
            <button type="button" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded" @click="showPrintPicker = false">Cancel</button>
            <button type="button" class="px-4 py-2 text-sm text-gray-700 bg-white border rounded" @click="selectAllPrint">Select All</button>
            <button type="button" class="px-4 py-2 text-sm text-white bg-indigo-600 rounded" :disabled="!selectedPrintIds.length || processingDeliver[printBilling?.id]" @click="printSelected(printBilling)">Print Selected</button>
          </div>
        </div>
      </div>

      <div v-if="showDueModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="w-full max-w-md p-4 bg-white rounded shadow-lg text-gray-800" @click.self="showDueModal = false">
          <h3 class="mb-3 text-lg font-semibold text-gray-900">Collect Due</h3>

          <table class="w-full mb-3 text-sm table-auto">
            <tr>
              <th class="text-left pr-3">Invoice:</th>
              <td>{{ dueBilling?.bill_number ?? '' }}</td>
            </tr>
            <tr>
              <th class="text-left pr-3">Patient:</th>
              <td>{{ getPatientName(dueBilling) }}</td>
            </tr>
            <tr>
              <th class="text-left pr-3">Due Amount:</th>
              <td>
                <span class="text-red-600 font-semibold cursor-pointer" @click="fillFullDue">
                  Tk {{ Number(dueBilling?.due_amount ?? 0).toFixed(2) }}
                </span>
              </td>
            </tr>
          </table>

          <div class="mb-3">
            <label class="block mb-1 text-sm font-medium">Pay Amount</label>
            <input
              v-model.number="dueAmount"
              type="number"
              min="1"
              :max="Number(dueBilling?.due_amount ?? 0)"
              step="0.01"
              ref="payInput"
              @keyup.enter="submitDueCollect"
              class="w-full px-3 py-2 border rounded"
            />
            <p class="text-xs text-gray-500 mt-1">Max: Tk {{ Number(dueBilling?.due_amount ?? 0).toFixed(2) }}</p>
            <label class="inline-flex items-center mt-2 text-sm">
              <input type="checkbox" v-model="requireFullPayment" class="mr-2" />
              Require full payment to print
            </label>

            <div class="mt-2">
              <span v-if="isReady" class="text-green-800 bg-green-100 px-2 py-1 rounded text-sm">Ready</span>
              <span v-else class="text-red-800 bg-red-100 px-2 py-1 rounded text-sm">Not Ready</span>
            </div>

            <div v-if="partialCollected" class="mt-2 text-sm text-yellow-700">Partial payment collected — print blocked until fully paid.</div>
          </div>

          <div class="flex items-center justify-end gap-2">
            <button type="button" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded" @click="showDueModal = false">Cancel</button>
            <button
              type="button"
              class="px-4 py-2 text-sm text-white bg-green-600 rounded disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="!dueAmount || dueAmount <= 0 || dueAmount > Number(dueBilling?.due_amount ?? 0)"
              @click="submitDueCollect"
            >
              Collect
            </button>
          </div>
        </div>
      </div>
    </div>
  </BackendLayout>
</template>
