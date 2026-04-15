<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'Low Stock Report' },
  items: { type: Array, default: () => [] },
  threshold: { type: Number, default: 10 },
});
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
        <a :href="route('backend.stock.index')" class="px-3 py-2 text-sm font-semibold text-white bg-gray-600 rounded hover:bg-gray-700">Back Dashboard</a>
      </div>

      <p class="mt-3 text-sm text-slate-600">Threshold: {{ threshold }} বা কম quantity হলে low stock হিসেবে দেখাবে।</p>

      <div class="w-full mt-3 overflow-x-auto">
        <table class="w-full text-sm border border-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 border text-left">Item Code</th>
              <th class="px-3 py-2 border text-left">Item</th>
              <th class="px-3 py-2 border text-left">Category</th>
              <th class="px-3 py-2 border text-left">Unit</th>
              <th class="px-3 py-2 border text-center">Quantity</th>
              <th class="px-3 py-2 border text-center">Reorder Level</th>
              <th class="px-3 py-2 border text-center">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 border">{{ item.item_code || '-' }}</td>
              <td class="px-3 py-2 border">{{ item.item_name }}</td>
              <td class="px-3 py-2 border">{{ item.category || '-' }}</td>
              <td class="px-3 py-2 border">{{ item.unit || '-' }}</td>
              <td class="px-3 py-2 border text-center">{{ Number(item.current_stock ?? 0).toFixed(2) }}</td>
              <td class="px-3 py-2 border text-center">{{ Number(item.reorder_level ?? 0).toFixed(2) }}</td>
              <td class="px-3 py-2 border text-center">
                <span class="px-2 py-1 text-xs rounded" :class="Number(item.current_stock ?? 0) <= 0 ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700'">
                  {{ Number(item.current_stock ?? 0) <= 0 ? 'Out of Stock' : 'Low Stock' }}
                </span>
              </td>
            </tr>
            <tr v-if="items.length === 0">
              <td colspan="7" class="px-3 py-6 text-center text-gray-500 border">No low stock item found.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </BackendLayout>
</template>
