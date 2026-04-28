<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'Store Dashboard' },
  items: { type: Object, default: () => ({ data: [] }) },
  summary: { type: Object, default: () => ({}) },
  filters: { type: Object, default: () => ({}) },
});

const perPage = ref(Number(props.filters?.per_page ?? props.items?.per_page ?? 20));
const rows = computed(() => props.items?.data ?? []);

const formatMoney = (value) => Number(value ?? 0).toFixed(2);

const applyPerPage = () => {
  router.get(route('backend.stock.index'), { per_page: perPage.value }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-3 p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
        <div class="flex flex-wrap items-center gap-2">
          <a :href="route('backend.stock.item.create')" class="px-3 py-2 text-sm font-semibold text-white bg-indigo-600 rounded hover:bg-indigo-700">Store Item Setup</a>
          <a :href="route('backend.stock.grns')" class="px-3 py-2 text-sm font-semibold text-white bg-cyan-600 rounded hover:bg-cyan-700">GRN Receive</a>
          <a :href="route('backend.stock.requisitions')" class="px-3 py-2 text-sm font-semibold text-white bg-slate-700 rounded hover:bg-slate-800">Requisitions</a>
          <a :href="route('backend.stock.adjustments')" class="px-3 py-2 text-sm font-semibold text-white bg-sky-600 rounded hover:bg-sky-700">Adjustments</a>
          <a :href="route('backend.stock.adjustment.create')" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Stock In/Out Entry</a>
          <a :href="route('backend.stock.low-stock-report')" class="px-3 py-2 text-sm font-semibold text-white bg-amber-600 rounded hover:bg-amber-700">Low Stock</a>
          <a :href="route('backend.stock.movement-report')" class="px-3 py-2 text-sm font-semibold text-white bg-violet-600 rounded hover:bg-violet-700">Movement Report</a>
          <a :href="route('backend.stock.monthly-closing')" class="px-3 py-2 text-sm font-semibold text-white bg-rose-600 rounded hover:bg-rose-700">Monthly Closing</a>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-3 mt-3 md:grid-cols-2 xl:grid-cols-5">
        <div class="p-3 bg-slate-50 border border-slate-200 rounded">
          <p class="text-xs text-slate-500">Total Items</p>
          <p class="mt-1 text-lg font-semibold text-slate-800">{{ summary.total_items ?? 0 }}</p>
        </div>
        <div class="p-3 bg-slate-50 border border-slate-200 rounded">
          <p class="text-xs text-slate-500">Active Items</p>
          <p class="mt-1 text-lg font-semibold text-slate-800">{{ summary.active_items ?? 0 }}</p>
        </div>
        <div class="p-3 bg-slate-50 border border-slate-200 rounded">
          <p class="text-xs text-slate-500">Total Quantity</p>
          <p class="mt-1 text-lg font-semibold text-slate-800">{{ Number(summary.total_quantity ?? 0).toFixed(0) }}</p>
        </div>
        <div class="p-3 bg-slate-50 border border-slate-200 rounded">
          <p class="text-xs text-slate-500">Inventory Value</p>
          <p class="mt-1 text-lg font-semibold text-slate-800">{{ formatMoney(summary.inventory_value) }}</p>
        </div>
        <div class="p-3 bg-slate-50 border border-slate-200 rounded">
          <p class="text-xs text-slate-500">Low Stock Items</p>
          <p class="mt-1 text-lg font-semibold text-slate-800">{{ Number(summary.low_stock_items ?? 0) }}</p>
        </div>
      </div>

      <div class="flex items-center justify-end gap-2 mt-3">
        <label for="per_page" class="text-sm text-slate-600">Show</label>
        <select id="per_page" v-model="perPage" class="px-2 py-1 text-sm border rounded" @change="applyPerPage">
          <option :value="10">10</option>
          <option :value="20">20</option>
          <option :value="50">50</option>
          <option :value="100">100</option>
        </select>
      </div>

      <div class="w-full mt-3 overflow-x-auto">
        <table class="w-full text-sm border border-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 border text-left">Item Code</th>
              <th class="px-3 py-2 border text-left">Item Name</th>
              <th class="px-3 py-2 border text-left">Category</th>
              <th class="px-3 py-2 border text-left">Unit</th>
              <th class="px-3 py-2 border text-right">Quantity</th>
              <th class="px-3 py-2 border text-right">Unit Cost</th>
              <th class="px-3 py-2 border text-right">Reorder Level</th>
              <th class="px-3 py-2 border text-center">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in rows" :key="item.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 border">{{ item.item_code || '-' }}</td>
              <td class="px-3 py-2 border">{{ item.item_name }}</td>
              <td class="px-3 py-2 border">{{ item.category || '-' }}</td>
              <td class="px-3 py-2 border">{{ item.unit || '-' }}</td>
              <td class="px-3 py-2 border text-right">{{ Number(item.current_stock ?? 0).toFixed(2) }}</td>
              <td class="px-3 py-2 border text-right">{{ formatMoney(item.unit_cost) }}</td>
              <td class="px-3 py-2 border text-right">{{ Number(item.reorder_level ?? 0).toFixed(2) }}</td>
              <td class="px-3 py-2 border text-center">
                <span class="px-2 py-1 text-xs rounded" :class="item.status === 'Active' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'">{{ item.status }}</span>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="9" class="px-3 py-6 text-center text-gray-500 border">No store item found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="props.items?.links?.length" class="grid grid-cols-1 gap-4 pt-3 my-2 md:grid-cols-2 items-center">
        <p class="text-sm text-gray-600 text-center md:text-left">
          Displaying {{ props.items?.from ?? 0 }} to {{ props.items?.to ?? 0 }} of {{ props.items?.total ?? 0 }} items
        </p>
        <nav>
          <ul class="flex items-center justify-center md:justify-end gap-2">
            <li v-for="(link, index) in props.items.links" :key="`${index}-${link.label}`">
              <a v-if="link.url" :href="link.url" class="px-3 py-1 text-sm border rounded" :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'hover:bg-gray-100 border-gray-300'">
                <span v-html="link.label"></span>
              </a>
              <span v-else class="px-3 py-1 text-sm text-gray-400 border border-gray-200 rounded" v-html="link.label"></span>
            </li>
          </ul>
        </nav>
      </div>
    </div>
  </BackendLayout>
</template>
