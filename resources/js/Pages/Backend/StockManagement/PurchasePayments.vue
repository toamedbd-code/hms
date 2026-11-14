<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'Purchase Payments' },
  payments: { type: Object, default: () => ({ data: [] }) },
  outstandingOrders: { type: Array, default: () => [] },
  payableAccounts: { type: Array, default: () => [] },
  paymentRouteScope: { type: String, default: 'stock' },
  filters: { type: Object, default: () => ({}) },
});

const fromDate = ref(props.filters?.from_date ?? '');
const toDate = ref(props.filters?.to_date ?? '');
const selectedOrder = ref(null);
const rows = computed(() => props.payments?.data ?? []);
const money = (value) => Number(value ?? 0).toFixed(2);
const printReport = () => window.print();
const paymentsRoute = computed(() => props.paymentRouteScope === 'accounts'
  ? 'backend.accounts.store-purchase-payments.index'
  : 'backend.stock.purchase-payments');
const paymentForm = useForm({
  amount: '',
  payment_date: new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10),
  payment_account_id: '',
  reference_no: '',
  notes: '',
  return_to: 'purchase-payments',
});
const selectedDue = computed(() => Number(selectedOrder.value?.due_amount ?? 0));
const openPaymentModal = (order) => {
  selectedOrder.value = order;
  paymentForm.reset();
  paymentForm.clearErrors();
  paymentForm.amount = selectedDue.value.toFixed(2);
  paymentForm.payment_date = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
  paymentForm.return_to = props.paymentRouteScope === 'accounts' ? '' : 'purchase-payments';
};
const closePaymentModal = () => {
  if (paymentForm.processing) return;
  selectedOrder.value = null;
  paymentForm.clearErrors();
};
const submitPayment = () => {
  if (!selectedOrder.value) return;
  const storeRoute = props.paymentRouteScope === 'accounts'
    ? 'backend.accounts.store-purchase-payments.store'
    : 'backend.stock.purchase-payments.store';

  paymentForm.post(route(storeRoute, selectedOrder.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      selectedOrder.value = null;
      paymentForm.reset();
    },
  });
};
const onEscape = (event) => {
  if (event.key === 'Escape') closePaymentModal();
};
const applyFilters = () => router.get(route(paymentsRoute.value), {
  from_date: fromDate.value || undefined,
  to_date: toDate.value || undefined,
  per_page: props.filters?.per_page ?? 20,
}, { preserveState: true, preserveScroll: true, replace: true });

onMounted(() => window.addEventListener('keydown', onEscape));
onBeforeUnmount(() => window.removeEventListener('keydown', onEscape));
</script>

<template>
  <BackendLayout>
    <div id="purchase-payments-print-area" class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
        <div class="flex gap-2 print:hidden">
          <a v-if="paymentRouteScope === 'accounts'" :href="route('backend.accounts.vendor-payment.index')" class="px-3 py-2 text-sm font-semibold text-white bg-indigo-600 rounded hover:bg-indigo-700">Pharmacy Payments</a>
          <a v-else :href="route('backend.stock.index')" class="px-3 py-2 text-sm font-semibold text-white bg-indigo-600 rounded hover:bg-indigo-700">Stock Management</a>
          <button type="button" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700" @click="printReport">Print</button>
          <a v-if="paymentRouteScope !== 'accounts'" :href="route('backend.stock.purchase-orders')" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Purchase Orders</a>
        </div>
      </div>
      <section class="mt-4 print:hidden">
        <h2 class="mb-2 text-base font-semibold text-slate-800">Outstanding Store Supplier Dues</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-sm border">
            <thead class="bg-gray-50">
              <tr>
                <th class="p-2 text-left border">Purchase Order</th>
                <th class="p-2 text-left border">Supplier</th>
                <th class="p-2 text-right border">Received</th>
                <th class="p-2 text-right border">Paid</th>
                <th class="p-2 text-right border">Due</th>
                <th class="p-2 text-center border">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="order in outstandingOrders" :key="order.id">
                <td class="p-2 border">{{ order.purchase_order_no }}</td>
                <td class="p-2 border">{{ order.supplier?.name || '-' }}</td>
                <td class="p-2 text-right border">{{ money(order.received_amount) }}</td>
                <td class="p-2 text-right border">{{ money(order.paid_amount) }}</td>
                <td class="p-2 text-right font-semibold border">{{ money(order.due_amount) }}</td>
                <td class="p-2 text-center border">
                  <button type="button" class="px-3 py-1 text-xs font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700" @click="openPaymentModal(order)">Pay from Account</button>
                </td>
              </tr>
              <tr v-if="outstandingOrders.length === 0">
                <td colspan="6" class="p-4 text-center text-slate-500 border">No outstanding store supplier dues.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
      <h2 class="mt-6 mb-2 text-base font-semibold text-slate-800">Recorded Store Payments</h2>
      <div class="flex flex-wrap items-end gap-2 mt-4 print:hidden">
        <label class="text-sm">From date<input v-model="fromDate" type="date" class="block p-2 border rounded" /></label>
        <label class="text-sm">To date<input v-model="toDate" type="date" class="block p-2 border rounded" /></label>
        <button type="button" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700" @click="applyFilters">Filter</button>
      </div>
      <div class="mt-4 overflow-x-auto">
        <table class="w-full text-sm border">
          <thead class="bg-gray-50"><tr><th class="p-2 border">Date</th><th class="p-2 border text-left">Purchase order</th><th class="p-2 border text-left">Supplier</th><th class="p-2 border text-left">Account</th><th class="p-2 border">Reference</th><th class="p-2 border text-right">Amount</th></tr></thead>
          <tbody>
            <tr v-for="payment in rows" :key="payment.id">
              <td class="p-2 border text-center">{{ String(payment.payment_date).slice(0, 10) }}</td>
              <td class="p-2 border"><a :href="route('backend.stock.purchase-orders.show', payment.purchase_order?.id)" class="text-indigo-700 hover:underline">{{ payment.purchase_order?.purchase_order_no || '-' }}</a></td>
              <td class="p-2 border">{{ payment.purchase_order?.supplier?.name || '-' }}</td>
              <td class="p-2 border">{{ payment.payment_account?.name || 'Cash' }}</td>
              <td class="p-2 border text-center">{{ payment.reference_no || '-' }}</td>
              <td class="p-2 border text-right">{{ money(payment.amount) }}</td>
            </tr>
            <tr v-if="rows.length === 0"><td colspan="6" class="p-6 text-center text-slate-500 border">No purchase payments found.</td></tr>
          </tbody>
        </table>
      </div>
      <div v-if="payments?.links?.length" class="flex justify-end gap-2 mt-4 print:hidden">
        <a v-for="(link, index) in payments.links" :key="index" :href="link.url || '#'" class="px-3 py-1 text-sm border rounded" :class="link.active ? 'bg-emerald-600 text-white border-emerald-600' : 'text-slate-600'" v-html="link.label" />
      </div>
    </div>

    <div v-if="selectedOrder" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/50 p-4 print:hidden" @click.self="closePaymentModal">
      <section class="w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="supplier-payment-title">
        <div class="flex items-start justify-between gap-4 border-b p-5">
          <div>
            <h2 id="supplier-payment-title" class="text-lg font-semibold text-slate-800">Pay Store Supplier</h2>
            <p class="mt-1 text-sm text-slate-600">{{ selectedOrder.purchase_order_no }} · {{ selectedOrder.supplier?.name || '-' }}</p>
          </div>
          <button type="button" class="rounded p-1 text-xl leading-none text-slate-500 hover:bg-slate-100 hover:text-slate-800" aria-label="Close payment form" :disabled="paymentForm.processing" @click="closePaymentModal">&times;</button>
        </div>

        <div class="grid grid-cols-1 gap-3 p-5 text-sm sm:grid-cols-3">
          <div class="rounded border p-3"><span class="text-slate-500">Goods received</span><div class="font-semibold">{{ money(selectedOrder.received_amount) }}</div></div>
          <div class="rounded border p-3"><span class="text-slate-500">Already paid</span><div class="font-semibold">{{ money(selectedOrder.paid_amount) }}</div></div>
          <div class="rounded border p-3"><span class="text-slate-500">Balance due</span><div class="font-semibold">{{ money(selectedOrder.due_amount) }}</div></div>
        </div>

        <form class="space-y-4 px-5 pb-5" @submit.prevent="submitPayment">
          <div>
            <label class="mb-1 block text-sm font-semibold" for="supplier-payment-amount">Payment amount</label>
            <input id="supplier-payment-amount" v-model="paymentForm.amount" type="number" min="0.01" :max="selectedDue" step="0.01" required class="w-full rounded border p-2" />
            <InputError :message="paymentForm.errors.amount" />
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-semibold" for="supplier-payment-date">Payment date</label>
              <input id="supplier-payment-date" v-model="paymentForm.payment_date" type="date" required class="w-full rounded border p-2" />
              <InputError :message="paymentForm.errors.payment_date" />
            </div>
            <div>
              <span class="mb-1 block text-sm font-semibold">Cash / bank account</span>
              <div class="grid gap-2" role="radiogroup" aria-label="Cash or bank account">
                <label
                  v-for="account in payableAccounts"
                  :key="account.id"
                  class="flex cursor-pointer items-center justify-between gap-3 rounded border p-3 transition"
                  :class="String(paymentForm.payment_account_id) === String(account.id) ? 'border-emerald-600 bg-emerald-50 ring-1 ring-emerald-500' : 'border-slate-300 hover:border-emerald-400 hover:bg-slate-50'"
                >
                  <span class="flex items-center gap-3">
                    <input v-model="paymentForm.payment_account_id" type="radio" name="supplier-payment-account" :value="String(account.id)" required class="text-emerald-600 focus:ring-emerald-500" />
                    <span><span class="block font-medium text-slate-800">{{ account.name }}</span><span class="text-xs text-slate-500">{{ account.code }}</span></span>
                  </span>
                  <svg v-if="String(paymentForm.payment_account_id) === String(account.id)" class="h-5 w-5 shrink-0 text-emerald-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.2 7.26a1 1 0 0 1-1.42.003L3.29 9.17a1 1 0 1 1 1.42-1.407l4.09 4.13 6.49-6.597a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd" /></svg>
                </label>
              </div>
              <InputError :message="paymentForm.errors.payment_account_id" />
            </div>
          </div>
          <div>
            <label class="mb-1 block text-sm font-semibold" for="supplier-payment-reference">Reference number</label>
            <input id="supplier-payment-reference" v-model="paymentForm.reference_no" type="text" class="w-full rounded border p-2" />
            <InputError :message="paymentForm.errors.reference_no" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-semibold" for="supplier-payment-notes">Notes</label>
            <textarea id="supplier-payment-notes" v-model="paymentForm.notes" rows="2" class="w-full rounded border p-2"></textarea>
            <InputError :message="paymentForm.errors.notes" />
          </div>
          <div class="flex justify-end gap-2 border-t pt-4">
            <button type="button" class="rounded border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" :disabled="paymentForm.processing" @click="closePaymentModal">Cancel</button>
            <button type="submit" class="rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="paymentForm.processing || selectedDue <= 0">
              {{ paymentForm.processing ? 'Saving...' : 'Save Payment' }}
            </button>
          </div>
        </form>
      </section>
    </div>
  </BackendLayout>
</template>

<style scoped>
@media print {
  :global(body *) {
    visibility: hidden !important;
  }

  #purchase-payments-print-area,
  #purchase-payments-print-area * {
    visibility: visible !important;
  }

  #purchase-payments-print-area {
    position: absolute !important;
    inset: 0 auto auto 0 !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    box-shadow: none !important;
  }
}
</style>
