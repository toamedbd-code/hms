<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const props = defineProps({
  payments: { type: Object, default: () => ({ data: [], links: [] }) },
  outstandingOrders: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
});
const fromDate = ref(props.filters?.from_date ?? '');
const toDate = ref(props.filters?.to_date ?? '');
const money = (value) => Number(value ?? 0).toFixed(2);
const applyFilters = () => router.get(route('backend.lab.purchase-payments'), {
  from_date: fromDate.value || undefined,
  to_date: toDate.value || undefined,
}, { preserveState: true, preserveScroll: true, replace: true });
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 space-y-4 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded"><h1 class="text-xl font-semibold">Lab Supplier Payments</h1><div class="flex gap-2"><a :href="route('backend.lab.purchase-orders')" class="px-3 py-2 text-sm text-white bg-emerald-600 rounded">Purchase Orders</a><a :href="route('backend.lab.dashboard')" class="px-3 py-2 text-sm text-white bg-emerald-600 rounded">Lab Dashboard</a></div></div>
      <section>
        <h2 class="mb-2 font-semibold">Outstanding Received Goods</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-sm border">
            <thead class="bg-slate-50"><tr><th class="p-2 border">Order</th><th class="p-2 text-left border">Supplier</th><th class="p-2 text-right border">Received</th><th class="p-2 text-right border">Paid</th><th class="p-2 text-right border">Due</th><th class="p-2 border">Action</th></tr></thead>
            <tbody>
              <tr v-for="order in outstandingOrders" :key="order.id"><td class="p-2 border">{{ order.order_no }}</td><td class="p-2 border">{{ order.supplier_name }}</td><td class="p-2 text-right border">{{ money(order.received_amount) }}</td><td class="p-2 text-right border">{{ money(order.paid_amount) }}</td><td class="p-2 text-right font-semibold border">{{ money(Number(order.received_amount) - Number(order.paid_amount)) }}</td><td class="p-2 text-center border"><a :href="route('backend.lab.purchase-payments.create', order.id)" class="px-3 py-1 text-xs font-semibold text-white bg-emerald-600 rounded">Pay supplier</a></td></tr>
              <tr v-if="outstandingOrders.length === 0"><td colspan="6" class="p-4 text-center border text-slate-500">No outstanding Lab supplier dues.</td></tr>
            </tbody>
          </table>
        </div>
      </section>
      <section>
        <h2 class="mb-2 font-semibold">Recorded Payments</h2>
        <div class="flex flex-wrap items-end gap-2 mb-3">
          <label class="text-sm">From date<input v-model="fromDate" type="date" class="block p-2 border rounded" /></label><label class="text-sm">To date<input v-model="toDate" type="date" class="block p-2 border rounded" /></label><button class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded" @click="applyFilters">Filter</button>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm border">
            <thead class="bg-slate-50"><tr><th class="p-2 border">Date</th><th class="p-2 border">Order</th><th class="p-2 text-left border">Supplier</th><th class="p-2 border">Account</th><th class="p-2 border">Reference</th><th class="p-2 text-right border">Amount</th></tr></thead>
            <tbody>
              <tr v-for="payment in payments.data" :key="payment.id"><td class="p-2 text-center border">{{ String(payment.payment_date).slice(0, 10) }}</td><td class="p-2 border">{{ payment.order_no }}</td><td class="p-2 border">{{ payment.supplier_name }}</td><td class="p-2 text-center border">{{ payment.account_name || 'Cash' }}</td><td class="p-2 text-center border">{{ payment.reference_no || '-' }}</td><td class="p-2 text-right border">{{ money(payment.amount) }}</td></tr>
              <tr v-if="payments.data.length === 0"><td colspan="6" class="p-5 text-center border text-slate-500">No Lab supplier payments found.</td></tr>
            </tbody>
          </table>
        </div>
        <div class="flex justify-end gap-2 mt-3"><a v-for="(link, index) in payments.links" :key="index" :href="link.url || '#'" class="px-3 py-1 text-sm border rounded" :class="link.active ? 'bg-emerald-600 text-white' : ''" v-html="link.label" /></div>
      </section>
    </div>
  </BackendLayout>
</template>
