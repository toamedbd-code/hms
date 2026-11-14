<script setup>
import { computed, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'Stock Report' },
  items: { type: [Object, Array], default: () => ({ data: [] }) },
  summary: { type: Object, default: () => ({}) },
  categories: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  printMode: { type: Boolean, default: false },
});

const filters = ref({
  search: props.filters?.search ?? '',
  category: props.filters?.category ?? '',
  status: props.filters?.status ?? '',
  stock_level: props.filters?.stock_level ?? 'all',
  per_page: Number(props.filters?.per_page ?? props.items?.per_page ?? 20),
});
const rows = computed(() => Array.isArray(props.items) ? props.items : (props.items?.data ?? []));

const applyFilters = () => {
  router.get(route('backend.stock.report'), filters.value, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilters = () => {
  filters.value = { search: '', category: '', status: '', stock_level: 'all', per_page: 20 };
  applyFilters();
};

const exportCsv = () => {
  window.location.href = route('backend.stock.report', { ...filters.value, export: 'csv' });
};

const printReport = () => {
  const url = route('backend.stock.report', { ...filters.value, print: 1 });
  window.open(url, '_blank', 'noopener');
};

onMounted(() => {
  if (props.printMode) {
    window.setTimeout(() => window.print(), 500);
  }
});

const formatQuantity = (value) => Number(value ?? 0).toFixed(2);
const formatMoney = (value) => Number(value ?? 0).toFixed(2);

const stockStatus = (item) => {
  const quantity = Number(item.current_stock ?? 0);
  if (quantity <= 0) return 'Out of Stock';
  if (quantity <= Number(item.reorder_level ?? 0)) return 'Low Stock';
  return 'In Stock';
};

const stockStatusClass = (item) => {
  const status = stockStatus(item);
  if (status === 'Out of Stock') return 'bg-rose-100 text-rose-700';
  if (status === 'Low Stock') return 'bg-amber-100 text-amber-700';
  return 'bg-emerald-100 text-emerald-700';
};
</script>

<template>
  <BackendLayout>
    <div id="stock-report-print-area" class="print-report w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-3 p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
        <div class="flex flex-wrap gap-2 print:hidden">
          <a :href="route('backend.stock.index')" class="px-3 py-2 text-sm font-semibold text-white bg-red-600 rounded hover:bg-red-700">Back to Store</a>
          <button type="button" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700" @click="exportCsv">Export CSV</button>
          <button type="button" class="px-3 py-2 text-sm font-semibold text-white bg-indigo-600 rounded hover:bg-indigo-700" @click="printReport">Print</button>
        </div>
      </div>
      <p v-if="printMode" class="mt-2 text-xs text-slate-600">
        Printed: {{ new Date().toLocaleString() }} · Items: {{ summary.item_count ?? rows.length }}
        <span v-if="filters.search"> · Search: {{ filters.search }}</span>
        <span v-if="filters.category"> · Category: {{ filters.category }}</span>
        <span v-if="filters.status"> · Status: {{ filters.status }}</span>
        <span v-if="filters.stock_level && filters.stock_level !== 'all'"> · Stock: {{ filters.stock_level }}</span>
      </p>

      <div class="grid grid-cols-1 gap-3 mt-3 md:grid-cols-3">
        <div class="p-3 border rounded bg-slate-50">
          <p class="text-xs text-slate-500">Matching Items</p>
          <p class="mt-1 text-lg font-semibold text-slate-800">{{ Number(summary.item_count ?? 0) }}</p>
        </div>
        <div class="p-3 border rounded bg-slate-50">
          <p class="text-xs text-slate-500">Total Stock Quantity</p>
          <p class="mt-1 text-lg font-semibold text-slate-800">{{ formatQuantity(summary.total_quantity) }}</p>
        </div>
        <div class="p-3 border rounded bg-slate-50">
          <p class="text-xs text-slate-500">Stock Value</p>
          <p class="mt-1 text-lg font-semibold text-slate-800">{{ formatMoney(summary.total_value) }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-2 p-3 mt-3 bg-slate-100 rounded md:grid-cols-6 print:hidden">
        <input
          v-model="filters.search"
          type="search"
          class="w-full p-2 text-sm border rounded md:col-span-2"
          placeholder="Search item, code or category"
          @keyup.enter="applyFilters"
        >
        <select v-model="filters.category" class="w-full p-2 text-sm border rounded" @change="applyFilters">
          <option value="">All Categories</option>
          <option v-for="category in categories" :key="category" :value="category">{{ category }}</option>
        </select>
        <select v-model="filters.status" class="w-full p-2 text-sm border rounded" @change="applyFilters">
          <option value="">All Item Statuses</option>
          <option value="Active">Active</option>
          <option value="Inactive">Inactive</option>
        </select>
        <select v-model="filters.stock_level" class="w-full p-2 text-sm border rounded" @change="applyFilters">
          <option value="all">All Stock Levels</option>
          <option value="available">In Stock</option>
          <option value="low">Low Stock</option>
          <option value="out">Out of Stock</option>
        </select>
        <div class="flex gap-2">
          <button type="button" class="flex-1 px-3 py-2 text-sm text-white bg-indigo-600 rounded hover:bg-indigo-700" @click="applyFilters">Search</button>
          <button type="button" class="flex-1 px-3 py-2 text-sm border rounded hover:bg-white" @click="resetFilters">Reset</button>
        </div>
      </div>

      <p class="mt-2 text-xs text-slate-600">
        Reorder Level হলো ন্যূনতম স্টক সীমা। মূল স্টক এই পরিমাণে বা এর নিচে নামলে আইটেমটি Low Stock হিসেবে দেখায়, যাতে সময়মতো নতুন করে কেনা বা স্টক যোগ করা যায়।
      </p>

      <div class="flex items-center justify-end gap-2 mt-3 print:hidden">
        <label for="stock_report_per_page" class="text-sm text-slate-600">Show</label>
        <select id="stock_report_per_page" v-model="filters.per_page" class="px-2 py-1 text-sm border rounded" @change="applyFilters">
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
              <th class="px-3 py-2 border text-left">Item</th>
              <th class="px-3 py-2 border text-left">Category</th>
              <th class="px-3 py-2 border text-left">Unit</th>
              <th class="px-3 py-2 border text-right">Main Stock</th>
              <th class="px-3 py-2 border text-right">Tests Done</th>
              <th class="px-3 py-2 border text-right">Tests Remaining</th>
              <th class="px-3 py-2 border text-right">Unit Cost</th>
              <th class="px-3 py-2 border text-right">Stock Value</th>
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
              <td class="px-3 py-2 border text-right">{{ formatQuantity(item.current_stock) }}</td>
              <td class="px-3 py-2 border text-right">
                {{ item.has_test_usage ? formatQuantity(item.lab_tests_done) : '-' }}
              </td>
              <td class="px-3 py-2 border text-right">
                {{ item.has_test_usage ? formatQuantity(item.lab_tests_remaining) : '-' }}
              </td>
              <td class="px-3 py-2 border text-right">{{ formatMoney(item.unit_cost) }}</td>
              <td class="px-3 py-2 border text-right">{{ formatMoney(Number(item.current_stock ?? 0) * Number(item.unit_cost ?? 0)) }}</td>
              <td class="px-3 py-2 border text-right">{{ formatQuantity(item.reorder_level) }}</td>
              <td class="px-3 py-2 border text-center">
                <div class="flex flex-col items-center gap-1">
                  <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold" :class="stockStatusClass(item)">
                    <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>
                    {{ stockStatus(item) }}
                  </span>
                  <span
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                    :class="item.status === 'Active' ? 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-200' : 'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200'"
                  >{{ item.status }}</span>
                </div>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="11" class="px-3 py-6 text-center text-gray-500 border">No stock items match these filters.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="props.items?.links?.length" class="grid grid-cols-1 gap-4 pt-3 my-2 md:grid-cols-2 items-center print:hidden">
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

<style scoped>
@page {
  size: landscape;
  margin: 12mm;
}

@media print {
  :global(body *) {
    visibility: hidden !important;
  }

  #stock-report-print-area,
  #stock-report-print-area * {
    visibility: visible !important;
  }

  #stock-report-print-area {
    position: absolute !important;
    inset: 0 auto auto 0 !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    box-shadow: none !important;
  }

  :global(.sidebar),
  :global(header),
  :global(nav) {
    display: none !important;
  }

  table {
    width: 100% !important;
    font-size: 10px !important;
  }

  th,
  td {
    padding: 5px !important;
  }

  thead {
    display: table-header-group;
  }

  tr {
    break-inside: avoid;
  }
}
</style>
