<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
  purchases: {
    type: Object,
    default: () => ({ data: [] }),
  },
});

const rows = props.purchases?.data ?? [];

const deletePurchase = (id) => {
  if (!window.confirm('Are you sure you want to delete this purchase?')) return;
  router.delete(route('backend.medicinepurchase.destroy', id), { preserveScroll: true });
};

const money = (value) => Number(value ?? 0).toFixed(2);

const paymentStatus = (purchase) => {
  return Number(purchase?.due_amount ?? 0) <= 0 ? 'paid' : 'pending';
};

const statusClass = (purchase) => {
  return paymentStatus(purchase) === 'paid'
    ? 'text-emerald-700 bg-emerald-100'
    : 'text-amber-700 bg-amber-100';
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-2 p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">Purchase Product List</h1>
        <Link :href="route('backend.medicinepurchase.create')" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded hover:bg-indigo-700">
          Add Purchase Product
        </Link>
      </div>

      <div class="w-full my-4 overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700 border border-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 border">Purchase No</th>
              <th class="px-3 py-2 border">Supplier</th>
              <th class="px-3 py-2 border">Date</th>
              <th class="px-3 py-2 border">Total</th>
              <th class="px-3 py-2 border">Sell Total</th>
              <th class="px-3 py-2 border">Paid</th>
              <th class="px-3 py-2 border">Due</th>
              <th class="px-3 py-2 border">Status</th>
              <th class="px-3 py-2 border">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="purchase in rows" :key="purchase.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 border">{{ purchase.purchase_number }}</td>
              <td class="px-3 py-2 border">{{ purchase.supplier?.name ?? 'N/A' }}</td>
              <td class="px-3 py-2 border">{{ purchase.purchase_date }}</td>
              <td class="px-3 py-2 border">{{ money(purchase.total_amount) }}</td>
              <td class="px-3 py-2 border">{{ money(purchase.sell_total_amount) }}</td>
              <td class="px-3 py-2 border">{{ money(purchase.paid_amount) }}</td>
              <td class="px-3 py-2 border">{{ money(purchase.due_amount) }}</td>
              <td class="px-3 py-2 border">
                <span class="px-2 py-1 text-xs rounded capitalize" :class="statusClass(purchase)">
                  {{ paymentStatus(purchase) }}
                </span>
              </td>
              <td class="px-3 py-2 border">
                <div class="flex flex-wrap gap-2">
                  <Link :href="route('backend.medicinepurchase.show', purchase.id)" class="px-2 py-1 text-xs text-white bg-teal-600 rounded hover:bg-teal-700">
                    View
                  </Link>
                  <Link
                    :href="route('backend.supplierpayment.create', { purchase_id: purchase.id })"
                    class="px-2 py-1 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700"
                  >
                    Add Payment
                  </Link>
                  <Link :href="route('backend.medicinepurchase.edit', purchase.id)" class="px-2 py-1 text-xs text-black bg-yellow-400 rounded hover:bg-yellow-500">
                    Edit
                  </Link>
                  <button type="button" class="px-2 py-1 text-xs text-white bg-red-600 rounded hover:bg-red-700" @click="deletePurchase(purchase.id)">
                    Delete
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="9" class="px-3 py-6 text-center text-gray-500 border">No purchase product found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="props.purchases?.links?.length" class="pt-2">
        <p class="text-sm text-gray-600 text-center md:text-left">
          Displaying {{ props.purchases?.from ?? 0 }} to {{ props.purchases?.to ?? 0 }} of {{ props.purchases?.total ?? 0 }} items
        </p>
      </div>

      <Pagination />
    </div>
  </BackendLayout>
</template>
