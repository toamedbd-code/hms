<script setup>
import { computed, ref, onBeforeUnmount, onMounted } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'Purchase Orders' },
  orders: { type: Object, default: () => ({ data: [] }) },
  payableAccounts: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');
const selectedOrder = ref(null);
const rows = computed(() => props.orders?.data ?? []);
const money = (value) => Number(value ?? 0).toFixed(2);
const dueAmount = (order) => Math.max(0, Number(order.received_amount ?? 0) - Number(order.paid_amount ?? 0));
const paymentForm = useForm({
  amount: '',
  payment_date: '',
  payment_account_id: '',
  reference_no: '',
  notes: '',
  return_to: 'purchase-orders',
});
const localDate = () => new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
const openPaymentModal = (order) => {
  selectedOrder.value = order;
  paymentForm.reset();
  paymentForm.clearErrors();
  paymentForm.amount = dueAmount(order).toFixed(2);
  paymentForm.payment_date = localDate();
};
const closePaymentModal = () => {
  if (paymentForm.processing) return;
  selectedOrder.value = null;
  paymentForm.clearErrors();
};
const submitPayment = () => {
  if (!selectedOrder.value) return;
  paymentForm.post(route('backend.stock.purchase-payments.store', selectedOrder.value.id), {
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
const paymentStatus = (order) => {
  if (Number(order.received_amount ?? 0) <= 0) return 'Not Delivered';
  if (dueAmount(order) <= 0.005) return 'Paid';
  return Number(order.paid_amount ?? 0) > 0 ? 'Partial' : 'Due';
};
const paymentStatusClass = (order) => ({
  Paid: 'bg-emerald-100 text-emerald-800',
  Partial: 'bg-amber-100 text-amber-800',
  Due: 'bg-rose-100 text-rose-800',
  'Not Delivered': 'bg-slate-100 text-slate-700',
}[paymentStatus(order)]);
const formatDate = (value) => {
  if (!value) return '-';
  const datePart = String(value).match(/^\d{4}-\d{2}-\d{2}/)?.[0];
  if (!datePart) return String(value);
  const [year, month, day] = datePart.split('-');
  return `${day}/${month}/${year}`;
};

const applyFilters = () => router.get(route('backend.stock.purchase-orders'), {
  search: search.value || undefined,
  status: status.value || undefined,
  per_page: props.filters?.per_page ?? 20,
}, { preserveState: true, preserveScroll: true, replace: true });

onMounted(() => window.addEventListener('keydown', onEscape));
onBeforeUnmount(() => window.removeEventListener('keydown', onEscape));
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-3 p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
        <div class="flex gap-2">
          <a :href="route('backend.storesupplier.index')" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Store Suppliers</a>
          <a :href="route('backend.stock.index')" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Inventory</a>
          <a :href="route('backend.stock.purchase-orders.create')" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Create Purchase Order</a>
        </div>
      </div>

      <div class="flex flex-wrap gap-2 mt-4">
        <input v-model="search" class="p-2 text-sm border rounded" placeholder="Search PO or supplier" @keyup.enter="applyFilters" />
        <select v-model="status" class="p-2 text-sm border rounded" @change="applyFilters">
          <option value="">All statuses</option>
          <option>Ordered</option>
          <option>Partially Received</option>
          <option>Received</option>
          <option>Cancelled</option>
        </select>
        <button type="button" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700" @click="applyFilters">Search</button>
      </div>

      <div class="mt-4 overflow-x-auto">
        <table class="w-full text-sm border border-gray-200">
          <thead class="bg-gray-50"><tr>
            <th class="px-3 py-2 border text-left">PO No</th>
            <th class="px-3 py-2 border text-left">Supplier</th>
            <th class="px-3 py-2 border text-center">Order Date</th>
            <th class="px-3 py-2 border text-center">Expected</th>
            <th class="px-3 py-2 border text-right">Ordered</th>
            <th class="px-3 py-2 border text-right">Received</th>
            <th class="px-3 py-2 border text-right">Paid</th>
            <th class="px-3 py-2 border text-right">Due</th>
            <th class="px-3 py-2 border text-center">Payment</th>
            <th class="px-3 py-2 border text-center">Status</th>
            <th class="px-3 py-2 border text-center">Action</th>
          </tr></thead>
          <tbody>
            <tr v-for="order in rows" :key="order.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 border">{{ order.purchase_order_no }}</td>
              <td class="px-3 py-2 border">{{ order.supplier?.name || '-' }}</td>
              <td class="px-3 py-2 border text-center">{{ formatDate(order.order_date_display || order.order_date) }}</td>
              <td class="px-3 py-2 border text-center">{{ formatDate(order.expected_delivery_date_display || order.expected_delivery_date) }}</td>
              <td class="px-3 py-2 border text-right">{{ money(order.total_amount) }}</td>
              <td class="px-3 py-2 border text-right">{{ money(order.received_amount) }}</td>
              <td class="px-3 py-2 border text-right">{{ money(order.paid_amount) }}</td>
              <td class="px-3 py-2 border text-right font-semibold">{{ money(dueAmount(order)) }}</td>
              <td class="px-3 py-2 border text-center">
                <span class="inline-block px-2 py-1 text-xs font-semibold rounded" :class="paymentStatusClass(order)">{{ paymentStatus(order) }}</span>
              </td>
              <td class="px-3 py-2 border text-center">{{ order.status }}</td>
              <td class="px-3 py-2 border text-center">
                <div class="flex justify-center gap-1">
                  <a v-if="order.can_edit" :href="route('backend.stock.purchase-orders.edit', order.id)" class="inline-block px-3 py-1 font-semibold text-white bg-indigo-600 rounded hover:bg-indigo-700">Edit</a>
                  <a v-if="['Ordered', 'Partially Received'].includes(order.status)" :href="route('backend.stock.grn.create', { purchase_order_id: order.id })" class="inline-block px-3 py-1 font-semibold text-white bg-sky-600 rounded hover:bg-sky-700">Receive</a>
                  <button v-if="dueAmount(order) > 0.005" type="button" class="inline-block px-3 py-1 font-semibold text-white bg-amber-600 rounded hover:bg-amber-700" @click="openPaymentModal(order)">Pay</button>
                  <a :href="route('backend.stock.purchase-orders.show', order.id)" class="inline-block px-3 py-1 font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">View</a>
                </div>
              </td>
            </tr>
            <tr v-if="rows.length === 0"><td colspan="11" class="px-3 py-6 text-center text-gray-500 border">No purchase orders found.</td></tr>
          </tbody>
        </table>
      </div>

      <div v-if="orders?.links?.length" class="flex flex-wrap justify-end gap-2 mt-4">
        <a v-for="(link, index) in orders.links" :key="index" :href="link.url || '#'" class="px-3 py-1 text-sm border rounded" :class="link.active ? 'bg-emerald-600 text-white border-emerald-600' : 'text-slate-600'" v-html="link.label" />
      </div>
    </div>

    <div v-if="selectedOrder" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/50 p-4" @click.self="closePaymentModal">
      <section class="w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="purchase-order-payment-title">
        <div class="flex items-start justify-between gap-4 border-b p-5">
          <div>
            <h2 id="purchase-order-payment-title" class="text-lg font-semibold text-slate-800">Pay Supplier</h2>
            <p class="mt-1 text-sm text-slate-600">{{ selectedOrder.purchase_order_no }} · {{ selectedOrder.supplier?.name || '-' }}</p>
          </div>
          <button type="button" class="rounded p-1 text-xl leading-none text-slate-500 hover:bg-slate-100 hover:text-slate-800" aria-label="Close payment form" :disabled="paymentForm.processing" @click="closePaymentModal">&times;</button>
        </div>

        <div class="grid grid-cols-1 gap-3 p-5 text-sm sm:grid-cols-3">
          <div class="rounded border p-3"><span class="text-slate-500">Goods received</span><div class="font-semibold">{{ money(selectedOrder.received_amount) }}</div></div>
          <div class="rounded border p-3"><span class="text-slate-500">Already paid</span><div class="font-semibold">{{ money(selectedOrder.paid_amount) }}</div></div>
          <div class="rounded border p-3"><span class="text-slate-500">Balance due</span><div class="font-semibold text-rose-700">{{ money(dueAmount(selectedOrder)) }}</div></div>
        </div>

        <form class="space-y-4 px-5 pb-5" @submit.prevent="submitPayment">
          <div>
            <label class="mb-1 block text-sm font-semibold" for="order-payment-amount">Payment amount</label>
            <input id="order-payment-amount" v-model="paymentForm.amount" type="number" min="0.01" :max="dueAmount(selectedOrder)" step="0.01" required class="w-full rounded border p-2" />
            <InputError :message="paymentForm.errors.amount" />
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-semibold" for="order-payment-date">Payment date</label>
              <input id="order-payment-date" v-model="paymentForm.payment_date" type="date" required class="w-full rounded border p-2" />
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
                    <input v-model="paymentForm.payment_account_id" type="radio" name="order-payment-account" :value="String(account.id)" required class="text-emerald-600 focus:ring-emerald-500" />
                    <span><span class="block font-medium text-slate-800">{{ account.name }}</span><span class="text-xs text-slate-500">{{ account.code }}</span></span>
                  </span>
                  <svg v-if="String(paymentForm.payment_account_id) === String(account.id)" class="h-5 w-5 shrink-0 text-emerald-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.2 7.26a1 1 0 0 1-1.42.003L3.29 9.17a1 1 0 1 1 1.42-1.407l4.09 4.13 6.49-6.597a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd" /></svg>
                </label>
              </div>
              <InputError :message="paymentForm.errors.payment_account_id" />
              <p v-if="payableAccounts.length === 0" class="mt-1 text-sm text-rose-700">No active cash or bank accounts are available for payment.</p>
            </div>
          </div>
          <div>
            <label class="mb-1 block text-sm font-semibold" for="order-payment-reference">Reference number</label>
            <input id="order-payment-reference" v-model="paymentForm.reference_no" type="text" maxlength="100" class="w-full rounded border p-2" />
            <InputError :message="paymentForm.errors.reference_no" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-semibold" for="order-payment-notes">Notes</label>
            <textarea id="order-payment-notes" v-model="paymentForm.notes" rows="2" class="w-full rounded border p-2"></textarea>
            <InputError :message="paymentForm.errors.notes" />
          </div>
          <div class="flex justify-end gap-2 border-t pt-4">
            <button type="button" class="rounded border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" :disabled="paymentForm.processing" @click="closePaymentModal">Cancel</button>
            <button type="submit" class="rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="paymentForm.processing || dueAmount(selectedOrder) <= 0.005 || payableAccounts.length === 0">
              {{ paymentForm.processing ? 'Saving...' : 'Save Payment' }}
            </button>
          </div>
        </form>
      </section>
    </div>
  </BackendLayout>
</template>
