<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'Lab Dashboard' },
  labManagementManaged: { type: Boolean, default: true },
  items: { type: Object, default: () => ({ data: [], links: [] }) },
  summary: { type: Object, default: () => ({}) },
  filters: { type: Object, default: () => ({}) },
});

const perPage = ref(Number(props.filters?.per_page ?? props.items?.per_page ?? 20));
const rows = computed(() => props.items?.data ?? []);
const formatQuantity = (value) => Number(value ?? 0).toFixed(2);

const applyPerPage = () => {
  router.get(route('backend.lab.dashboard'), { per_page: perPage.value }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const goBack = () => {
  if (window.history.length > 1) {
    window.history.back();
  } else {
    router.visit(route('backend.dashboard'));
  }
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-3 p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
        <div class="flex flex-wrap items-center gap-2">
          <button type="button" @click="goBack" class="px-3 py-2 text-sm font-semibold text-white bg-red-600 rounded hover:bg-red-700">Back</button>
          <a :href="route('backend.lab.inventory')" class="px-3 py-2 text-sm font-semibold text-white bg-indigo-600 rounded hover:bg-indigo-700">Lab Inventory</a>
          <a :href="route('backend.lab.suppliers')" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Lab Suppliers</a>
          <a :href="route('backend.lab.purchase-orders')" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Purchase Orders / GRN</a>
          <a :href="route('backend.lab.purchase-payments')" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Purchase Payments</a>
          <a :href="route('backend.lab.tests')" class="px-3 py-2 text-sm font-semibold text-white bg-teal-600 rounded hover:bg-teal-700">Test Catalog</a>
          <a :href="route('backend.lab.orders')" class="px-3 py-2 text-sm font-semibold text-white bg-amber-600 rounded hover:bg-amber-700">Lab Orders</a>
          <a :href="route('backend.lab.patients')" class="px-3 py-2 text-sm font-semibold text-white bg-fuchsia-600 rounded hover:bg-fuchsia-700">Lab Patients</a>
        </div>
      </div>

      <div class="mt-3 rounded border px-4 py-3 text-sm" :class="labManagementManaged ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900'">
        Lab mode: <strong>{{ labManagementManaged ? 'Managed' : 'Unmanaged' }}</strong>.
        {{ labManagementManaged ? 'Inventory stock is checked and consumed when results are verified.' : 'Lab billing and result verification continue without inventory stock limits.' }}
        Both modes synchronize patient payments with Accounts and Reports.
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
          <p class="mt-1 text-lg font-semibold text-slate-800">{{ formatQuantity(summary.total_quantity) }}</p>
        </div>
        <div class="p-3 bg-slate-50 border border-slate-200 rounded">
          <p class="text-xs text-slate-500">Configured Tests</p>
          <p class="mt-1 text-lg font-semibold text-slate-800">{{ summary.configured_tests ?? 0 }}</p>
        </div>
        <div class="p-3 bg-slate-50 border border-slate-200 rounded">
          <p class="text-xs text-slate-500">Low Stock Items</p>
          <p class="mt-1 text-lg font-semibold text-slate-800">{{ summary.low_stock_items ?? 0 }}</p>
        </div>
      </div>

      <div class="flex items-center justify-end gap-2 mt-3">
        <label for="lab-dashboard-per-page" class="text-sm text-slate-600">Show</label>
        <select id="lab-dashboard-per-page" v-model="perPage" class="px-2 py-1 text-sm border rounded" @change="applyPerPage">
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
              <th class="px-3 py-2 border text-right">Reorder Level</th>
              <th class="px-3 py-2 border text-center">Status</th>
              <th class="px-3 py-2 border text-center">Test Usage</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in rows" :key="item.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 border">LAB-{{ String(item.id).padStart(5, '0') }}</td>
              <td class="px-3 py-2 border">{{ item.name }}</td>
              <td class="px-3 py-2 border">{{ item.category || '-' }}</td>
              <td class="px-3 py-2 border">{{ item.unit || '-' }}</td>
              <td class="px-3 py-2 border text-right">{{ formatQuantity(item.current_stock) }}</td>
              <td class="px-3 py-2 border text-right">{{ formatQuantity(item.reorder_level) }}</td>
              <td class="px-3 py-2 border text-center">
                <span class="px-2 py-1 text-xs rounded" :class="item.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'">{{ item.is_active ? 'Active' : 'Inactive' }}</span>
              </td>
              <td class="px-3 py-2 border text-center">
                <a :href="route('backend.lab.tests')" class="text-indigo-700 hover:underline">{{ item.configured_test_count }} Tests</a>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="8" class="px-3 py-6 text-center text-gray-500 border">No Lab inventory items found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="items?.links?.length" class="grid grid-cols-1 gap-4 pt-3 my-2 md:grid-cols-2 items-center">
        <p class="text-sm text-gray-600 text-center md:text-left">
          Displaying {{ items.from ?? 0 }} to {{ items.to ?? 0 }} of {{ items.total ?? 0 }} items
        </p>
        <nav>
          <ul class="flex items-center justify-center md:justify-end gap-2">
            <li v-for="(link, index) in items.links" :key="`${index}-${link.label}`">
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
