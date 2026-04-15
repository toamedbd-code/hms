<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const props = defineProps({
  purchase: {
    type: Object,
    required: true,
  },
});

const rows = computed(() => {
  if (Array.isArray(props.purchase?.purchase_items)) return props.purchase.purchase_items;
  if (Array.isArray(props.purchase?.purchaseItems)) return props.purchase.purchaseItems;
  return [];
});

const receiveForm = useForm({
  items: rows.value.map((item) => ({
    id: item.id,
    received_quantity: Number(item.received_quantity ?? 0),
  })),
});

const submitReceive = () => {
  receiveForm.post(route('backend.medicinepurchase.receive', props.purchase.id));
};

const money = (value) => Number(value ?? 0).toFixed(2);

const pendingQty = (item) => {
  const qty = Number(item.quantity ?? 0);
  const received = Number(item.received_quantity ?? 0);
  return Math.max(0, qty - received);
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-2 p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">Purchase Product Details</h1>
        <div class="flex items-center gap-2">
          <Link :href="route('backend.medicinepurchase.edit', purchase.id)" class="px-3 py-2 text-sm text-black bg-yellow-400 rounded hover:bg-yellow-500">
            Edit
          </Link>
          <Link :href="route('backend.medicinepurchase.index')" class="px-3 py-2 text-sm text-white bg-gray-600 rounded hover:bg-gray-700">
            Back
          </Link>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-3 p-3 mt-3 border rounded md:grid-cols-5">
        <div><span class="text-gray-500">Purchase No:</span> <span class="font-medium">{{ purchase.purchase_number }}</span></div>
        <div><span class="text-gray-500">Supplier:</span> <span class="font-medium">{{ purchase.supplier?.name ?? 'N/A' }}</span></div>
        <div><span class="text-gray-500">Date:</span> <span class="font-medium">{{ purchase.purchase_date }}</span></div>
        <div><span class="text-gray-500">Invoice No:</span> <span class="font-medium">{{ purchase.invoice_number ?? 'N/A' }}</span></div>
        <div><span class="text-gray-500">Status:</span> <span class="font-medium capitalize">{{ purchase.status }}</span></div>
      </div>

      <div class="w-full my-4 overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700 border border-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 border">Category</th>
              <th class="px-3 py-2 border">Medicine</th>
              <th class="px-3 py-2 border">Batch No</th>
              <th class="px-3 py-2 border">Expiry Date</th>
              <th class="px-3 py-2 border">Order Qty</th>
              <th class="px-3 py-2 border">Unit Price</th>
              <th class="px-3 py-2 border">Unit Selling Price</th>
              <th class="px-3 py-2 border">Discount</th>
              <th class="px-3 py-2 border">Total</th>
              <th class="px-3 py-2 border">Received Qty</th>
              <th class="px-3 py-2 border">Pending Qty</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in rows" :key="item.id">
              <td class="px-3 py-2 border">{{ item.medicine_category?.medicine_category_name ?? 'N/A' }}</td>
              <td class="px-3 py-2 border">{{ item.medicine_name }}</td>
              <td class="px-3 py-2 border">{{ item.batch_no ?? 'N/A' }}</td>
              <td class="px-3 py-2 border">{{ item.expiry_date ?? 'N/A' }}</td>
              <td class="px-3 py-2 border">{{ item.quantity }}</td>
              <td class="px-3 py-2 border">{{ money(item.unit_purchase_price) }}</td>
              <td class="px-3 py-2 border">{{ money(item.unit_selling_price) }}</td>
              <td class="px-3 py-2 border">{{ money(item.discount) }}</td>
              <td class="px-3 py-2 border">{{ money(item.total_purchase_price) }}</td>
              <td class="px-3 py-2 border">
                <input
                  v-model="receiveForm.items[index].received_quantity"
                  type="number"
                  :min="item.received_quantity"
                  :max="item.quantity"
                  class="w-full p-2 border border-gray-300 rounded"
                >
                <p v-if="receiveForm.errors[`items.${index}.received_quantity`]" class="mt-1 text-xs text-red-600">
                  {{ receiveForm.errors[`items.${index}.received_quantity`] }}
                </p>
              </td>
              <td class="px-3 py-2 border">{{ pendingQty(item) }}</td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="11" class="px-3 py-6 text-center text-gray-500 border">No purchase items found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="grid grid-cols-1 gap-3 p-3 border rounded md:grid-cols-3">
        <div><span class="text-gray-500">Total Amount:</span> <span class="font-semibold">{{ money(purchase.total_amount) }}</span></div>
        <div><span class="text-gray-500">Paid Amount:</span> <span class="font-semibold">{{ money(purchase.paid_amount) }}</span></div>
        <div><span class="text-gray-500">Due Amount:</span> <span class="font-semibold">{{ money(purchase.due_amount) }}</span></div>
      </div>

      <div class="mt-4">
        <button
          type="button"
          class="px-4 py-2 text-white bg-indigo-600 rounded hover:bg-indigo-700 disabled:opacity-50"
          :disabled="receiveForm.processing || purchase.status === 'received'"
          @click="submitReceive"
        >
          {{ purchase.status === 'received' ? 'All Items Received' : 'Update Received Quantity' }}
        </button>
      </div>
    </div>
  </BackendLayout>
</template>
