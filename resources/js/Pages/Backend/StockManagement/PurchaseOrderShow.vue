<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'Purchase Order Details' },
  order: { type: Object, required: true },
  payableAccounts: { type: Array, default: () => [] },
  timezone: { type: String, default: 'Asia/Dhaka' },
});

const money = (value) => Number(value ?? 0).toFixed(2);
const date = (value) => {
  if (!value) return '-';
  const datePart = String(value).match(/^\d{4}-\d{2}-\d{2}/)?.[0];
  if (!datePart) return String(value);
  const [year, month, day] = datePart.split('-');
  return `${day}/${month}/${year}`;
};
const dateTime = (value) => {
  if (!value) return '-';
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return String(value);
  return parsed.toLocaleString('en-GB', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
    timeZone: props.timezone,
  });
};
const remaining = (line) => Math.max(0, Number(line.quantity ?? 0) - Number(line.received_quantity ?? 0));
const dueAmount = computed(() => Math.max(0, Number(props.order.received_amount ?? 0) - Number(props.order.paid_amount ?? 0)));
const paymentModalOpen = ref(false);
const paymentForm = useForm({
  amount: dueAmount.value.toFixed(2),
  payment_date: new Date().toISOString().slice(0, 10),
  payment_account_id: '',
  reference_no: '',
  notes: '',
});
const openPaymentModal = () => {
  paymentForm.amount = dueAmount.value.toFixed(2);
  paymentModalOpen.value = true;
};
const closePaymentModal = () => {
  if (paymentForm.processing) return;
  paymentModalOpen.value = false;
  paymentForm.clearErrors();
};
const submitPayment = () => paymentForm.post(route('backend.stock.purchase-payments.store', props.order.id), {
  onSuccess: () => {
    paymentModalOpen.value = false;
    paymentForm.reset();
  },
});
const paymentStatus = computed(() => {
  if (Number(props.order.received_amount ?? 0) <= 0) return 'Not Delivered';
  if (dueAmount.value <= 0.005) return 'Paid';
  return Number(props.order.paid_amount ?? 0) > 0 ? 'Partial' : 'Due';
});
const paymentStatusClass = computed(() => ({
  Paid: 'bg-emerald-100 text-emerald-800',
  Partial: 'bg-amber-100 text-amber-800',
  Due: 'bg-rose-100 text-rose-800',
  'Not Delivered': 'bg-slate-100 text-slate-700',
}[paymentStatus.value]));
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 space-y-4 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-3 p-4 bg-gray-100 rounded">
        <div>
          <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
          <p class="mt-1 text-sm text-slate-600">{{ order.purchase_order_no }} · {{ order.supplier?.name }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <a :href="route('backend.stock.purchase-orders')" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Back</a>
          <a v-if="order.can_edit" :href="route('backend.stock.purchase-orders.edit', order.id)" class="px-3 py-2 text-sm font-semibold text-white bg-indigo-600 rounded hover:bg-indigo-700">Edit Purchase Order</a>
          <a v-if="['Ordered', 'Partially Received'].includes(order.status)" :href="route('backend.stock.grn.create', { purchase_order_id: order.id })" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Record Delivery</a>
          <button v-if="dueAmount > 0.005" type="button" class="px-3 py-2 text-sm text-white bg-amber-600 rounded hover:bg-amber-700" @click="openPaymentModal">Pay Supplier</button>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3 md:grid-cols-7">
        <div class="p-3 border rounded"><div class="text-xs text-slate-500">Order date</div><div class="font-semibold">{{ date(order.order_date_display || order.order_date) }}</div></div>
        <div class="p-3 border rounded"><div class="text-xs text-slate-500">Expected delivery</div><div class="font-semibold">{{ date(order.expected_delivery_date_display || order.expected_delivery_date) }}</div></div>
        <div class="p-3 border rounded"><div class="text-xs text-slate-500">Ordered</div><div class="font-semibold">{{ money(order.total_amount) }}</div></div>
        <div class="p-3 border rounded"><div class="text-xs text-slate-500">Received</div><div class="font-semibold">{{ money(order.received_amount) }}</div></div>
        <div class="p-3 border rounded"><div class="text-xs text-slate-500">Paid</div><div class="font-semibold">{{ money(order.paid_amount) }}</div></div>
        <div class="p-3 border rounded"><div class="text-xs text-slate-500">Due</div><div class="font-semibold" :class="dueAmount > 0.005 ? 'text-rose-700' : 'text-emerald-700'">{{ money(dueAmount) }}</div></div>
        <div class="p-3 border rounded"><div class="text-xs text-slate-500">Payment status</div><span class="inline-block px-2 py-1 mt-1 text-xs font-semibold rounded" :class="paymentStatusClass">{{ paymentStatus }}</span></div>
      </div>

      <div>
        <h2 class="mb-2 font-semibold">Ordered items · {{ order.status }}</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-sm border">
            <thead class="bg-gray-50"><tr><th class="p-2 border text-left">Item</th><th class="p-2 border text-right">Order qty</th><th class="p-2 border text-right">Delivered</th><th class="p-2 border text-right">Remaining</th><th class="p-2 border text-right">Unit cost</th><th class="p-2 border text-right">Total</th></tr></thead>
            <tbody>
              <tr v-for="line in order.items" :key="line.id">
                <td class="p-2 border">{{ line.store_item?.item_name || '-' }} <span class="text-xs text-slate-500">{{ line.store_item?.item_code }}</span></td>
                <td class="p-2 border text-right">{{ Number(line.quantity).toFixed(2) }} {{ line.store_item?.unit }}</td>
                <td class="p-2 border text-right">{{ Number(line.received_quantity ?? 0).toFixed(2) }}</td>
                <td class="p-2 border text-right">{{ remaining(line).toFixed(2) }}</td>
                <td class="p-2 border text-right">{{ money(line.unit_cost) }}</td>
                <td class="p-2 border text-right">{{ money(line.line_total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-if="order.notes" class="mt-2 text-sm text-slate-600">Notes: {{ order.notes }}</p>
      </div>

      <div>
        <h2 class="mb-2 font-semibold">Deliveries</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-sm border">
            <thead class="bg-gray-50"><tr><th class="p-2 border text-left">GRN</th><th class="p-2 border">Date</th><th class="p-2 border text-left">Invoice</th><th class="p-2 border text-right">Amount</th><th class="p-2 border">Action</th></tr></thead>
            <tbody>
              <tr v-for="grn in order.grns" :key="grn.id">
                <td class="p-2 border">{{ grn.grn_no }}</td><td class="p-2 border text-center">{{ dateTime(grn.created_at) }}</td>
                <td class="p-2 border">{{ grn.invoice_no || '-' }}</td>
                <td class="p-2 border text-right">{{ money(grn.items?.reduce((sum, item) => sum + Number(item.line_total ?? 0), 0)) }}</td>
                <td class="p-2 border text-center"><a :href="route('backend.stock.grn.print', grn.id)" class="text-indigo-700 hover:underline">Print</a></td>
              </tr>
              <tr v-if="!order.grns?.length"><td colspan="5" class="p-4 text-center text-slate-500 border">No deliveries recorded.</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div>
        <h2 class="mb-2 font-semibold">Payments</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-sm border">
            <thead class="bg-gray-50"><tr><th class="p-2 border">Date</th><th class="p-2 border text-left">Account</th><th class="p-2 border">Reference</th><th class="p-2 border text-right">Amount</th></tr></thead>
            <tbody>
              <tr v-for="payment in order.payments" :key="payment.id">
                <td class="p-2 border text-center">{{ date(payment.payment_date) }}</td>
                <td class="p-2 border">{{ payment.payment_account?.name || 'Cash' }}</td>
                <td class="p-2 border">{{ payment.reference_no || '-' }}</td>
                <td class="p-2 border text-right">{{ money(payment.amount) }}</td>
              </tr>
              <tr v-if="!order.payments?.length"><td colspan="4" class="p-4 text-center text-slate-500 border">No payments recorded.</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="paymentModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" role="presentation" @click.self="closePaymentModal">
        <section class="w-full max-w-xl p-5 bg-white rounded-lg shadow-xl" role="dialog" aria-modal="true" aria-labelledby="supplier-payment-title">
          <div class="flex items-start justify-between gap-3 pb-3 border-b">
            <div>
              <h2 id="supplier-payment-title" class="text-lg font-semibold">Pay Supplier</h2>
              <p class="text-sm text-slate-600">{{ order.purchase_order_no }} · {{ order.supplier?.name }}</p>
            </div>
            <button type="button" class="px-2 py-1 text-slate-500 rounded hover:bg-slate-100" aria-label="Close payment dialog" :disabled="paymentForm.processing" @click="closePaymentModal">✕</button>
          </div>

          <div class="grid grid-cols-2 gap-3 mt-4 text-sm">
            <div class="p-3 border rounded"><span class="text-slate-500">Goods received</span><div class="font-semibold">{{ money(order.received_amount) }}</div></div>
            <div class="p-3 border rounded"><span class="text-slate-500">Balance due</span><div class="font-semibold text-rose-700">{{ money(dueAmount) }}</div></div>
          </div>

          <form class="mt-4 space-y-3" @submit.prevent="submitPayment">
            <div>
              <label class="block mb-1 text-sm font-semibold">Payment amount *</label>
              <input v-model.number="paymentForm.amount" type="number" min="0.01" :max="dueAmount" step="0.01" required class="w-full p-2 border rounded" />
              <InputError :message="paymentForm.errors.amount" />
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div>
                <label class="block mb-1 text-sm font-semibold">Payment date *</label>
                <input v-model="paymentForm.payment_date" type="date" required class="w-full p-2 border rounded" />
                <InputError :message="paymentForm.errors.payment_date" />
              </div>
              <div>
                <span class="block mb-1 text-sm font-semibold">Cash / bank account *</span>
                <div class="grid gap-2" role="radiogroup" aria-label="Cash or bank account">
                  <label
                    v-for="account in payableAccounts"
                    :key="account.id"
                    class="flex items-center justify-between gap-3 p-3 transition border rounded cursor-pointer"
                    :class="String(paymentForm.payment_account_id) === String(account.id) ? 'border-emerald-600 bg-emerald-50 ring-1 ring-emerald-500' : 'border-slate-300 hover:border-emerald-400 hover:bg-slate-50'"
                  >
                    <span class="flex items-center gap-3">
                      <input v-model="paymentForm.payment_account_id" type="radio" name="purchase-show-payment-account" :value="String(account.id)" required class="text-emerald-600 focus:ring-emerald-500" />
                      <span><span class="block font-medium text-slate-800">{{ account.name }}</span><span class="text-xs text-slate-500">{{ account.code }}</span></span>
                    </span>
                    <svg v-if="String(paymentForm.payment_account_id) === String(account.id)" class="w-5 h-5 text-emerald-600 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.2 7.26a1 1 0 0 1-1.42.003L3.29 9.17a1 1 0 1 1 1.42-1.407l4.09 4.13 6.49-6.597a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd" /></svg>
                  </label>
                </div>
                <InputError :message="paymentForm.errors.payment_account_id" />
                <p v-if="payableAccounts.length === 0" class="mt-1 text-sm text-rose-700">No active cash or bank accounts are available for payment.</p>
              </div>
            </div>
            <div>
              <label class="block mb-1 text-sm font-semibold">Reference number</label>
              <input v-model="paymentForm.reference_no" type="text" maxlength="100" class="w-full p-2 border rounded" />
              <InputError :message="paymentForm.errors.reference_no" />
            </div>
            <div>
              <label class="block mb-1 text-sm font-semibold">Notes</label>
              <textarea v-model="paymentForm.notes" rows="2" class="w-full p-2 border rounded"></textarea>
              <InputError :message="paymentForm.errors.notes" />
            </div>
            <div class="flex justify-end gap-2 pt-2">
              <button type="button" class="px-4 py-2 text-sm border rounded" :disabled="paymentForm.processing" @click="closePaymentModal">Cancel</button>
              <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded disabled:opacity-50" :disabled="paymentForm.processing || dueAmount <= 0.005 || payableAccounts.length === 0">
                {{ paymentForm.processing ? 'Saving...' : 'Save Payment' }}
              </button>
            </div>
          </form>
        </section>
      </div>
    </div>
  </BackendLayout>
</template>
