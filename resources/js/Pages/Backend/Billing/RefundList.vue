<script setup>
import axios from 'axios';
import { ref } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import BaseTable from '@/Components/BaseTable.vue';
import Pagination from '@/Components/Pagination.vue';
import { router, usePage } from '@inertiajs/vue3';
import { successMessage } from '@/responseMessage.js';

let props = defineProps({
  filters: Object,
});

const filters = ref({
  name: props.filters?.name ?? '',
  numOfData: props.filters?.numOfData ?? 10,
});

const page = usePage();
const showRefundModal = ref(false);
const isSubmittingRefund = ref(false);
const refundForm = ref({
  billingId: null,
  billNumber: 'N/A',
  patientName: 'N/A',
  refundAmount: '',
  remainingRefund: 0,
  paymentMethod: '',
  note: '',
});
const errorMessage = ref('');

const applyFilter = () => {
  router.get(route('backend.refunds.list'), filters.value, { preserveState: true });
};

const openRefundModal = (billingId) => {
  const rows = page.props?.datas?.data || [];
  const row = rows.find((item) => String(item.row_id) === String(billingId));

  refundForm.value.billingId = billingId;
  refundForm.value.billNumber = row?.bill_number || 'N/A';
  refundForm.value.patientName = row?.patient_name || 'N/A';
  refundForm.value.refundAmount = '';
  refundForm.value.remainingRefund = Number(row?.return_amt || 0);
  refundForm.value.paymentMethod = '';
  refundForm.value.note = '';
  errorMessage.value = '';
  showRefundModal.value = true;
};

const closeRefundModal = () => {
  if (isSubmittingRefund.value) return;
  showRefundModal.value = false;
  errorMessage.value = '';
};

const submitRefund = async () => {
  if (isSubmittingRefund.value) return;

  const amount = Number(refundForm.value.refundAmount || 0);
  const remaining = Number(refundForm.value.remainingRefund || 0);

  if (!Number.isFinite(amount) || amount <= 0 || amount > remaining) {
    errorMessage.value = 'Please enter a valid refund amount.';
    return;
  }

  isSubmittingRefund.value = true;
  errorMessage.value = '';

  try {
    await axios.post(route('backend.refunds.process'), {
      billing_id: refundForm.value.billingId,
      refund_amount: amount,
      payment_method: refundForm.value.paymentMethod,
      note: refundForm.value.note,
    });

    showRefundModal.value = false;
    successMessage('Refund processed successfully');
    try {
      localStorage.setItem('dashboard:refresh', String(Date.now()));
      window.dispatchEvent(new Event('dashboard:refresh'));
    } catch (e) {
      // ignore localStorage errors in private mode or limited browser environments
    }
    router.reload({ only: ['datas'] });
  } catch (error) {
    errorMessage.value = error?.response?.data?.message || 'Refund processing failed. Please try again.';
  } finally {
    isSubmittingRefund.value = false;
  }
};

const handleAction = (actionName, actionId) => {
  if (actionName !== 'refund') return;
  openRefundModal(actionId);
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-2 duration-1000 ease-in-out bg-white rounded-md dark:bg-slate-900">
      <div class="flex mb-2 items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md dark:bg-gray-800 dark:text-gray-200 shadow-gray-800/50">
        <div>
          <h1 class="p-4 text-xl font-bold dark:text-white">{{ $page.props.pageTitle }}</h1>
        </div>
      </div>

      <div class="flex justify-between w-full p-2 py-3 space-x-2 text-gray-700 rounded-md bg-slate-300 shadow-gray-800/50 dark:bg-gray-700 dark:text-gray-200">
        <div class="grid w-full grid-cols-1 gap-2 md:grid-cols-5">
          <div class="flex space-x-2">
            <div class="w-full">
              <input
                id="name"
                v-model="filters.name"
                class="block w-full p-2 text-sm rounded-md border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                type="text"
                placeholder="Bill No, Invoice No or Patient"
                @input="applyFilter"
              />
            </div>
            <div class="block min-w-24 md:hidden">
              <select v-model="filters.numOfData" @change="applyFilter"
                class="w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                <option value="10">Show 10</option>
                <option value="20">Show 20</option>
                <option value="30">Show 30</option>
                <option value="40">Show 40</option>
                <option value="100">Show 100</option>
                <option value="150">Show 150</option>
                <option value="500">Show 500</option>
              </select>
            </div>
          </div>
        </div>

        <div class="hidden min-w-24 md:block">
          <select v-model="filters.numOfData" @change="applyFilter"
            class="w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
            <option value="10">show 10</option>
            <option value="20">show 20</option>
            <option value="30">show 30</option>
            <option value="40">show 40</option>
            <option value="100">show 100</option>
            <option value="150">show 150</option>
            <option value="500">show 500</option>
          </select>
        </div>
      </div>

      <div class="w-full my-3 overflow-x-auto">
        <BaseTable @action="handleAction" />
      </div>
      <Pagination />
    </div>

    <div v-if="showRefundModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-lg overflow-hidden rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b px-5 py-3">
          <h3 class="text-base font-semibold text-gray-800">Refund Payment</h3>
          <button type="button" class="text-gray-500 hover:text-gray-700" @click="closeRefundModal">✕</button>
        </div>
        <div class="px-5 py-4">
          <div class="grid gap-3">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block mb-1 text-sm font-semibold text-gray-700">Bill No</label>
                <div class="rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ refundForm.billNumber }}</div>
              </div>
              <div>
                <label class="block mb-1 text-sm font-semibold text-gray-700">Patient</label>
                <div class="rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ refundForm.patientName }}</div>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block mb-1 text-sm font-semibold text-gray-700">Available Refund</label>
                <div class="rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-700">Tk {{ refundForm.remainingRefund.toFixed(2) }}</div>
              </div>
              <div>
                <label class="block mb-1 text-sm font-semibold text-gray-700">Refund Amount</label>
                <input
                  type="number"
                  step="0.01"
                  min="0.01"
                  :max="refundForm.remainingRefund"
                  v-model="refundForm.refundAmount"
                  class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                />
              </div>
            </div>

            <div>
              <label class="block mb-1 text-sm font-semibold text-gray-700">Payment Method</label>
              <input
                v-model="refundForm.paymentMethod"
                type="text"
                placeholder="Cash, Card, Bank Transfer..."
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
              />
            </div>

            <div>
              <label class="block mb-1 text-sm font-semibold text-gray-700">Note</label>
              <textarea
                v-model="refundForm.note"
                rows="3"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
              ></textarea>
            </div>

            <p v-if="errorMessage" class="text-sm text-red-600">{{ errorMessage }}</p>
          </div>
        </div>
        <div class="flex items-center justify-end gap-2 border-t px-5 py-3">
          <button type="button" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700" @click="closeRefundModal" :disabled="isSubmittingRefund">Cancel</button>
          <button type="button" class="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white" :disabled="isSubmittingRefund" @click="submitRefund">
            {{ isSubmittingRefund ? 'Processing...' : 'Process Refund' }}
          </button>
        </div>
      </div>
    </div>
  </BackendLayout>
</template>
