<script setup>
import { useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';
import { displayWarning } from '@/responseMessage.js';

const props = defineProps({
  pageTitle: { type: String, default: 'Record Purchase Payment' },
  order: { type: Object, required: true },
  receivedAmount: { type: Number, default: 0 },
  paidAmount: { type: Number, default: 0 },
  payableAccounts: { type: Array, default: () => [] },
  paymentRouteScope: { type: String, default: 'stock' },
});

const outstanding = Math.max(0, Number(props.receivedAmount) - Number(props.paidAmount));
const form = useForm({
  amount: outstanding.toFixed(2),
  payment_date: new Date().toISOString().split('T')[0],
  payment_account_id: '',
  reference_no: '',
  notes: '',
});

const submitRoute = props.paymentRouteScope === 'accounts'
  ? 'backend.accounts.store-purchase-payments.store'
  : 'backend.stock.purchase-payments.store';
const backRoute = props.paymentRouteScope === 'accounts'
  ? 'backend.accounts.store-purchase-payments.index'
  : 'backend.stock.purchase-orders.show';
const submit = () => form.post(route(submitRoute, props.order.id), {
  onError: (errors) => displayWarning(errors),
});
</script>

<template>
  <BackendLayout>
    <div class="w-full max-w-3xl p-4 mt-3 mx-auto bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <div><h1 class="text-xl font-semibold">{{ pageTitle }}</h1><p class="text-sm text-slate-600">{{ order.purchase_order_no }} · {{ order.supplier?.name }}</p></div>
        <a :href="paymentRouteScope === 'accounts' ? route(backRoute) : route(backRoute, order.id)" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Back</a>
      </div>

      <div class="grid grid-cols-3 gap-3 mt-4 text-sm">
        <div class="p-3 border rounded"><span class="text-slate-500">Goods received</span><div class="font-semibold">{{ Number(receivedAmount).toFixed(2) }}</div></div>
        <div class="p-3 border rounded"><span class="text-slate-500">Already paid</span><div class="font-semibold">{{ Number(paidAmount).toFixed(2) }}</div></div>
        <div class="p-3 border rounded"><span class="text-slate-500">Balance due</span><div class="font-semibold">{{ outstanding.toFixed(2) }}</div></div>
      </div>

      <form class="mt-4 space-y-3" @submit.prevent="submit">
        <div>
          <label class="block mb-1 text-sm font-semibold">Payment amount</label>
          <input v-model.number="form.amount" type="number" min="0.01" :max="outstanding" step="0.01" class="w-full p-2 border rounded" />
          <InputError :message="form.errors.amount" />
        </div>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
          <div>
            <label class="block mb-1 text-sm font-semibold">Payment date</label>
            <input v-model="form.payment_date" type="date" class="w-full p-2 border rounded" />
            <InputError :message="form.errors.payment_date" />
          </div>
          <div>
            <label class="block mb-1 text-sm font-semibold">Cash / bank account</label>
            <select v-model="form.payment_account_id" class="w-full p-2 border rounded">
              <option value="">Select account</option>
              <option v-for="account in payableAccounts" :key="account.id" :value="account.id">{{ account.name }} ({{ account.code }})</option>
            </select>
            <InputError :message="form.errors.payment_account_id" />
          </div>
        </div>
        <div>
          <label class="block mb-1 text-sm font-semibold">Reference number</label>
          <input v-model="form.reference_no" type="text" class="w-full p-2 border rounded" />
          <InputError :message="form.errors.reference_no" />
        </div>
        <div>
          <label class="block mb-1 text-sm font-semibold">Notes</label>
          <textarea v-model="form.notes" rows="3" class="w-full p-2 border rounded"></textarea>
          <InputError :message="form.errors.notes" />
        </div>
        <div class="flex justify-end">
          <button type="submit" :disabled="form.processing || outstanding <= 0" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded disabled:opacity-50">{{ form.processing ? 'Saving...' : 'Save Payment' }}</button>
        </div>
      </form>
    </div>
  </BackendLayout>
</template>
