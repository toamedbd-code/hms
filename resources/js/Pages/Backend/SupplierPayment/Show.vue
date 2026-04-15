<script setup>
import { Link } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const props = defineProps({
  payment: {
    type: Object,
    default: () => ({}),
  },
});

const money = (value) => Number(value ?? 0).toFixed(2);
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">Supplier Payment Details</h1>
        <Link :href="route('backend.supplierpayment.index')" class="px-3 py-2 text-sm text-white bg-gray-600 rounded hover:bg-gray-700">
          Back
        </Link>
      </div>

      <div class="grid grid-cols-1 gap-3 mt-4 md:grid-cols-2">
        <div><span class="font-semibold">Supplier:</span> {{ payment.supplier?.name ?? 'N/A' }}</div>
        <div><span class="font-semibold">Payment Date:</span> {{ payment.payment_date ?? 'N/A' }}</div>
        <div><span class="font-semibold">Total Amount:</span> {{ money(payment.total_amount) }}</div>
        <div><span class="font-semibold">Paid Amount:</span> {{ money(payment.paid_amount) }}</div>
        <div><span class="font-semibold">Due Amount:</span> {{ money(payment.due_amount) }}</div>
        <div><span class="font-semibold">Payment Type:</span> {{ payment.payment_type ?? 'N/A' }}</div>
        <div><span class="font-semibold">Status:</span> {{ payment.status ?? 'N/A' }}</div>
        <div class="md:col-span-2"><span class="font-semibold">Notes:</span> {{ payment.notes ?? '-' }}</div>
      </div>
    </div>
  </BackendLayout>
</template>
