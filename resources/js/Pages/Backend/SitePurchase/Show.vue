<script setup>
import { Link } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const props = defineProps({
  purchase: {
    type: Object,
    required: true,
  },
});

const money = (value) => Number(value ?? 0).toFixed(2);
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">Site Purchase Details</h1>
        <Link :href="route('backend.sitepurchase.index')" class="px-3 py-2 text-sm text-white bg-gray-600 rounded hover:bg-gray-700">
          Back to List
        </Link>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4 text-sm text-gray-800">
        <div class="p-3 border rounded"><strong>Purchase No:</strong> {{ purchase.purchase_number }}</div>
        <div class="p-3 border rounded"><strong>Site:</strong> {{ purchase.site_name }}</div>
        <div class="p-3 border rounded"><strong>Vendor:</strong> {{ purchase.vendor_name || 'N/A' }}</div>
        <div class="p-3 border rounded"><strong>Item:</strong> {{ purchase.item_name }}</div>
        <div class="p-3 border rounded"><strong>Category:</strong> {{ purchase.category_name || 'N/A' }}</div>
        <div class="p-3 border rounded"><strong>Nature:</strong> {{ purchase.purchase_nature }}</div>
        <div class="p-3 border rounded"><strong>Date:</strong> {{ purchase.purchase_date }}</div>
        <div class="p-3 border rounded"><strong>Quantity:</strong> {{ purchase.quantity }}</div>
        <div class="p-3 border rounded"><strong>Unit Price:</strong> {{ money(purchase.unit_price) }}</div>
        <div class="p-3 border rounded"><strong>Total:</strong> {{ money(purchase.total_amount) }}</div>
        <div class="p-3 border rounded"><strong>Paid:</strong> {{ money(purchase.paid_amount) }}</div>
        <div class="p-3 border rounded"><strong>Due:</strong> {{ money(purchase.due_amount) }}</div>
        <div class="p-3 border rounded md:col-span-2"><strong>Notes:</strong> {{ purchase.notes || 'N/A' }}</div>
      </div>
    </div>
  </BackendLayout>
</template>
