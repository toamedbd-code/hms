<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { route } from 'ziggy-js';
import { successMessage } from '@/responseMessage.js';

const props = defineProps({
  pageTitle: String,
  payee: Object,
  totals: Object,
  billRows: Array,
});

const page = usePage();

watch(
  () => page.props.flash?.successMessage,
  (message) => {
    if (message) {
      successMessage(message);
    }
  },
  { immediate: true }
);

const isProcessing = ref(false);
const modalError = ref('');
const showPartialModal = ref(false);
const partialForm = ref({
  referralId: null,
  billNumber: 'N/A',
  date: 'N/A',
  commissionAmount: 0,
  paidAmount: 0,
  pendingAmount: 0,
  amount: '',
});

const openPartialModal = (row) => {
  partialForm.value.referralId = row.id;
  partialForm.value.billNumber = row.bill_number || 'N/A';
  partialForm.value.date = row.date || 'N/A';
  partialForm.value.commissionAmount = Number(row.commission_amount || 0);
  partialForm.value.paidAmount = Number(row.paid_amount || 0);
  partialForm.value.pendingAmount = Number(row.pending_amount || 0);
  partialForm.value.amount = partialForm.value.pendingAmount > 0 ? partialForm.value.pendingAmount : '';
  modalError.value = '';
  showPartialModal.value = true;
};

const closePartialModal = () => {
  showPartialModal.value = false;
  partialForm.value = {
    referralId: null,
    billNumber: 'N/A',
    date: 'N/A',
    commissionAmount: 0,
    paidAmount: 0,
    pendingAmount: 0,
    amount: '',
  };
  modalError.value = '';
};

const printSummaryUrl = computed(() =>
  route('backend.referral.commission.payment.payee.print', {
    payeeId: props.payee.id,
    search: filters.value.search,
    date_from: filters.value.date_from,
    date_to: filters.value.date_to,
  })
);

const openPrintSummaryPreview = () => {
  if (!printSummaryUrl.value) {
    return;
  }

  window.open(printSummaryUrl.value, '_blank', 'noopener,noreferrer');
};

const submitPartialPayment = () => {
  if (isProcessing.value) return;

  const amount = Number(partialForm.value.amount || 0);
  const pendingAmount = Number(partialForm.value.pendingAmount || 0);
  modalError.value = '';

  if (!Number.isFinite(amount) || amount <= 0) {
    modalError.value = 'Please enter a valid amount.';
    return;
  }

  if (amount > pendingAmount) {
    modalError.value = 'Amount cannot exceed the pending balance.';
    return;
  }

  const referralId = partialForm.value.referralId;
  if (!referralId) {
    modalError.value = 'Referral not found.';
    return;
  }

  const postUrl = route('backend.referral.commission.payment', referralId);
  isProcessing.value = true;

  router.post(postUrl, {
    payment_type: 'partial',
    amount,
  }, {
    preserveScroll: true,
    preserveState: true,
    onStart: () => {
      isProcessing.value = true;
    },
    onSuccess: () => {
      successMessage(page.props?.flash?.successMessage || 'Partial commission payment recorded.');
      closePartialModal();
      router.reload({ only: ['payee', 'billRows', 'totals'] });
    },
    onError: (errors) => {
      modalError.value = errors?.amount?.[0] || errors?.payment_type?.[0] || 'Unable to process the payment.';
    },
    onFinish: () => {
      isProcessing.value = false;
    },
  });
};

const money = (value) => Number(value ?? 0).toFixed(2);
const filters = ref({
  search: page.props.filters?.search ?? '',
  date_from: page.props.filters?.date_from ?? '',
  date_to: page.props.filters?.date_to ?? '',
});

const applyFilters = () => {
  router.get(
    route('backend.referral.commission.payment.payee.bills', props.payee.id),
    {
      search: filters.value.search,
      date_from: filters.value.date_from,
      date_to: filters.value.date_to,
    },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    }
  );
};

const resetFilters = () => {
  filters.value.search = '';
  filters.value.date_from = '';
  filters.value.date_to = '';
  router.get(
    route('backend.referral.commission.payment.payee.bills', props.payee.id),
    {},
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    }
  );
};

const payeeName = computed(() => props.payee?.name ?? 'N/A');
const payeePhone = computed(() => props.payee?.phone ?? 'N/A');
const printedTime = new Date().toLocaleString('en-GB', {
  day: '2-digit',
  month: 'short',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
  hour12: true,
});
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 bg-white rounded-md shadow-sm">
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
          <div class="text-sm text-gray-600">Payee: {{ payeeName }} | Phone: {{ payeePhone }}</div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <Link :href="route('backend.referral.index')" class="px-4 py-2 text-sm font-semibold text-white bg-red-600 rounded hover:bg-red-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-red-300 focus:ring-offset-2">
            Back to Referral List
          </Link>
          <button type="button" class="px-4 py-2 text-sm font-semibold text-white bg-slate-700 rounded hover:bg-slate-800 hover:text-white focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2" @click.prevent="openPrintSummaryPreview">
            Print Summary
          </button>
        </div>
      </div>

      <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
        <form @submit.prevent="applyFilters" class="grid gap-3 md:grid-cols-[1.5fr_1fr_1fr_auto] items-end">
          <div>
            <label class="block text-xs font-semibold text-slate-600">Search Bill</label>
            <input
              v-model="filters.search"
              type="search"
              placeholder="Bill number..."
              class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600">Date From</label>
            <input
              v-model="filters.date_from"
              type="date"
              class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600">Date To</label>
            <input
              v-model="filters.date_to"
              type="date"
              class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
            />
          </div>
          <div class="flex items-center gap-2">
            <button type="submit" class="h-10 rounded-md bg-slate-800 px-4 text-sm font-semibold text-white">Search</button>
            <button type="button" class="h-10 rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-100" @click="resetFilters">Reset</button>
          </div>
        </form>
      </div>

      <div class="grid grid-cols-1 gap-3 mt-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="p-4 bg-slate-50 rounded shadow-sm">
          <div class="text-xs text-slate-500">Total Bills</div>
          <div class="mt-1 text-lg font-semibold text-slate-900">{{ totals.billCount }}</div>
        </div>
        <div class="p-4 bg-slate-50 rounded shadow-sm">
          <div class="text-xs text-slate-500">Total Commission</div>
          <div class="mt-1 text-lg font-semibold text-slate-900">৳{{ money(totals.totalCommission) }}</div>
        </div>
        <div class="p-4 bg-slate-50 rounded shadow-sm">
          <div class="text-xs text-slate-500">Already Paid</div>
          <div class="mt-1 text-lg font-semibold text-slate-900">৳{{ money(totals.paidAmount) }}</div>
        </div>
        <div class="p-4 bg-slate-50 rounded shadow-sm">
          <div class="text-xs text-slate-500">Pending Amount</div>
          <div class="mt-1 text-lg font-semibold text-slate-900">৳{{ money(totals.pendingAmount) }}</div>
        </div>
      </div>

      <div class="mt-6 overflow-x-auto">
        <table class="min-w-full table-fixed text-sm text-left text-gray-700 border border-gray-200 rounded-lg border-collapse">
          <thead class="bg-gray-100 text-xs uppercase tracking-wide text-gray-700">
            <tr>
              <th style="width: 4%;" class="px-4 py-3 border border-gray-200">#</th>
              <th style="width: 22%;" class="px-4 py-3 border border-gray-200">Bill No</th>
              <th style="width: 15%;" class="px-4 py-3 border border-gray-200">Date</th>
              <th style="width: 15%;" class="px-4 py-3 border border-gray-200 text-right">Commission</th>
              <th style="width: 10%;" class="px-4 py-3 border border-gray-200 text-right">Paid</th>
              <th style="width: 18%;" class="px-4 py-3 border border-gray-200 text-right">Paid Date & Time</th>
              <th style="width: 12%;" class="px-4 py-3 border border-gray-200 text-right">Pending</th>
              <th style="width: 7%;" class="px-4 py-3 border border-gray-200">Status</th>
              <th style="width: 7%;" class="px-4 py-3 border border-gray-200">Action</th>
            </tr>
          </thead>
          <tbody class="bg-white">
            <tr v-for="(row, index) in billRows" :key="row.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 border border-gray-200">{{ index + 1 }}</td>
              <td class="px-4 py-3 border border-gray-200">{{ row.bill_number }}</td>
              <td class="px-4 py-3 border border-gray-200">{{ row.date }}</td>
              <td class="px-4 py-3 border border-gray-200 text-right">৳{{ money(row.commission_amount) }}</td>
              <td class="px-4 py-3 border border-gray-200 text-right">৳{{ money(row.paid_amount) }}</td>
              <td class="px-4 py-3 border border-gray-200 text-right whitespace-nowrap">{{ row.paid_date_time }}</td>
              <td class="px-4 py-3 border border-gray-200 text-right">৳{{ money(row.pending_amount) }}</td>
              <td class="px-4 py-3 border border-gray-200">{{ row.paid_status ?? 'N/A' }}</td>
              <td class="px-4 py-3 border border-gray-200">
                <div class="flex flex-wrap gap-2">
                  <button
                    type="button"
                    class="px-3 py-1 text-xs font-semibold text-white bg-blue-600 rounded hover:bg-blue-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2"
                    @click.prevent="openPartialModal(row)"
                  >
                    Pay Partial
                  </button>
                  <Link :href="route('backend.referral.commission.payment.paid', row.id)" class="px-3 py-1 text-xs font-semibold text-white bg-green-600 rounded hover:bg-green-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-emerald-300 focus:ring-offset-2">
                    Mark Paid
                  </Link>
                </div>
              </td>
            </tr>
            <tr v-if="!billRows || billRows.length === 0">
              <td colspan="9" class="px-4 py-6 border border-gray-200 text-center text-sm text-gray-500">No referral bills found for this payee.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="showPartialModal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/12 p-4">
        <div class="w-full max-w-md rounded-3xl bg-white shadow-2xl ring-1 ring-slate-200/80 overflow-hidden border border-slate-200">
          <div class="flex items-center justify-between border-b px-5 py-3 bg-slate-50">
            <div>
              <h3 class="text-base font-semibold text-slate-900">Partial Commission Payment</h3>
              <p class="text-sm text-slate-500">Bill: {{ partialForm.billNumber }} | Date: {{ partialForm.date }}</p>
            </div>
            <button type="button" class="text-slate-500 hover:text-slate-700" @click="closePartialModal">✕</button>
          </div>
          <div class="px-5 py-4">
            <div class="space-y-3 text-sm text-gray-700">
              <div class="flex justify-between">
                <span class="font-semibold">Commission</span>
                <span>৳{{ Number(partialForm.commissionAmount).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="font-semibold">Paid</span>
                <span>৳{{ Number(partialForm.paidAmount).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="font-semibold">Pending</span>
                <span class="text-red-600 font-semibold">৳{{ Number(partialForm.pendingAmount).toFixed(2) }}</span>
              </div>
            </div>
            <div class="mt-4">
              <label class="mb-1 block text-sm font-semibold text-gray-700">Pay Amount</label>
              <input
                v-model="partialForm.amount"
                type="number"
                step="0.01"
                min="0.01"
                :max="partialForm.pendingAmount"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                @keydown.enter.prevent="submitPartialPayment"
              >
              <p class="mt-1 text-xs text-gray-500">Max: ৳{{ Number(partialForm.pendingAmount || 0).toFixed(2) }}</p>
              <p v-if="modalError" class="mt-1 text-xs font-medium text-red-600">{{ modalError }}</p>
            </div>
          </div>
          <div class="flex items-center justify-end gap-2 border-t px-5 py-3 bg-slate-50">
            <button type="button" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700" @click="closePartialModal">Cancel</button>
            <button
              type="button"
              class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
              :disabled="isProcessing"
              @click.prevent="submitPartialPayment"
            >
              {{ isProcessing ? 'Processing...' : 'Pay Commission' }}
            </button>
          </div>
        </div>
      </div>

    </div>
  </BackendLayout>
</template>

<style scoped>
.print-modal {
  z-index: 9999;
}

@media print {
  body {
    background: #fff !important;
    margin: 0 !important;
    padding: 0 !important;
  }

  body * {
    visibility: hidden !important;
  }

  .print-modal,
  .print-modal * {
    visibility: visible !important;
  }

  .print-modal {
    position: static !important;
    inset: auto !important;
    width: auto !important;
    max-width: 100% !important;
    min-height: auto !important;
    height: auto !important;
    margin: 0 !important;
    padding: 0 !important;
    box-shadow: none !important;
    background: #fff !important;
  }

  .print-modal > div {
    border: none !important;
    box-shadow: none !important;
  }

  .printable-area {
    overflow: visible !important;
    max-height: none !important;
  }

  .print-modal .no-print {
    display: none !important;
  }

  .print-shared-header,
  .footer-wrapper {
    visibility: visible !important;
  }

  .print-shared-header {
    width: 100%;
    height: 115px;
    margin: 0;
    padding: 0;
    overflow: hidden;
    box-sizing: border-box;
    background: transparent;
  }

  .print-shared-header-placeholder {
    width: 100%;
    height: 100%;
    visibility: hidden;
    display: block;
  }

  body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 10px;
    color: #000;
  }

  .card {
    width: 100%;
    margin: 0;
    background: #fff;
    border: none;
    border-radius: 0;
    padding: 8px;
  }

  .title {
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 6px;
  }

  .row {
    margin-bottom: 6px;
    font-size: 10px;
  }

  .label {
    color: #000;
    font-weight: 700;
    margin-right: 6px;
  }

  .value-wrap {
    display: inline-block;
    vertical-align: top;
    max-width: 360px;
    word-break: break-word;
  }

  .table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 7px;
    margin-bottom: 7px;
    table-layout: fixed;
    font-size: 10px;
  }

  .table th,
  .table td {
    border: 1px solid #000;
    padding: 4px;
    vertical-align: middle;
    text-align: left;
    word-break: break-word;
  }

  .table th {
    background: #f5f5f5;
  }

  .amount {
    text-align: right;
    white-space: nowrap;
  }

  .print-time {
    margin-top: 10px;
    font-size: 10px;
    color: #000;
  }

  .footer-wrapper {
    position: relative;
    left: 0;
    right: 0;
    bottom: 0;
    height: 70px;
    overflow: hidden;
    z-index: 1;
    background: transparent;
    box-sizing: border-box;
    clear: both;
  }

  .footer-placeholder {
    width: 100%;
    height: 100%;
    visibility: hidden;
    display: block;
  }
}
</style>
