<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'Store Dashboard' },
  items: { type: Object, default: () => ({ data: [] }) },
  summary: { type: Object, default: () => ({}) },
  currentDate: { type: String, default: '' },
  dailyUsageSummary: { type: Object, default: () => ({}) },
  procurementSummary: { type: Object, default: () => ({}) },
  filters: { type: Object, default: () => ({}) },
});

const perPage = ref(Number(props.filters?.per_page ?? props.items?.per_page ?? 20));
const rows = computed(() => props.items?.data ?? []);

const formatMoney = (value) => Number(value ?? 0).toFixed(2);
const stockReportUrl = (filters = {}) => route('backend.stock.report', filters);

const applyPerPage = () => {
  router.get(route('backend.stock.index'), { per_page: perPage.value }, {
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
          <a :href="route('backend.stock.item.create')" class="px-3 py-2 text-sm font-semibold text-white bg-indigo-600 rounded hover:bg-indigo-700">Store Item Setup</a>
          <a :href="route('backend.stock.grns')" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Purchase Delivery</a>
          <a :href="route('backend.stock.purchase-orders')" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Purchase Orders</a>
          <a :href="route('backend.stock.purchase-payments')" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Purchase Payments</a>
          <a :href="route('backend.stock.product-returns')" class="px-3 py-2 text-sm font-semibold text-white bg-orange-600 rounded hover:bg-orange-700">Product Returns</a>
          <a :href="route('backend.stock.requisitions')" class="px-3 py-2 text-sm font-semibold text-white bg-teal-600 rounded hover:bg-teal-700">Requisitions</a>
          <a :href="route('backend.stock.department-reagents')" class="px-3 py-2 text-sm font-semibold text-white bg-cyan-700 rounded hover:bg-cyan-800">Department Inventory</a>
          <a :href="route('backend.stock.adjustments')" class="px-3 py-2 text-sm font-semibold text-white bg-sky-600 rounded hover:bg-sky-700">Adjustments</a>
          <a :href="route('backend.stock.report')" class="px-3 py-2 text-sm font-semibold text-white bg-blue-600 rounded hover:bg-blue-700">Stock Report</a>
          <a :href="route('backend.stock.low-stock-report')" class="px-3 py-2 text-sm font-semibold !text-white bg-amber-600 rounded hover:bg-amber-700">Low Stock</a>
          <a :href="route('backend.stock.movement-report')" class="px-3 py-2 text-sm font-semibold !text-white bg-fuchsia-600 rounded hover:bg-fuchsia-700">Movement Report</a>
          <a :href="route('backend.stock.monthly-closing')" class="px-3 py-2 text-sm font-semibold !text-white bg-rose-600 rounded hover:bg-rose-700">Monthly Closing</a>
        </div>
      </div>

      <div class="dashboard-summary-grid grid auto-rows-fr grid-cols-1 gap-2 mt-2 sm:grid-cols-2 xl:grid-cols-5">
        <a :href="route('backend.stock.purchase-orders', { status: 'Ordered' })" class="group flex h-full min-h-[108px] flex-col rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition duration-150 hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" title="View open purchase orders">
          <div class="flex min-h-8 items-start justify-between gap-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Open Purchase Orders</p>
            <span class="rounded-lg bg-indigo-50 p-1.5 text-indigo-600 group-hover:bg-indigo-100">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h8l5 5v13H7zM15 3v6h5M10 14h7M10 18h7" /></svg>
            </span>
          </div>
          <p class="mt-auto pt-2 text-xl font-bold tabular-nums text-slate-800">{{ procurementSummary.open_purchase_orders ?? 0 }}</p>
        </a>

        <a :href="route('backend.stock.purchase-orders')" class="group flex h-full min-h-[108px] flex-col rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition duration-150 hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2" title="View ordered quantities">
          <div class="flex min-h-8 items-start justify-between gap-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ordered Quantity</p>
            <span class="rounded-lg bg-sky-50 p-1.5 text-sky-600 group-hover:bg-sky-100">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16M8 4v4m8 2v4m-5 2v4" /></svg>
            </span>
          </div>
          <p class="mt-auto pt-2 text-xl font-bold tabular-nums text-slate-800">{{ Number(procurementSummary.ordered_quantity ?? 0).toFixed(2) }}</p>
        </a>

        <a :href="route('backend.stock.grns')" class="group flex h-full min-h-[108px] flex-col rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition duration-150 hover:-translate-y-0.5 hover:border-cyan-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2" title="View purchase deliveries">
          <div class="flex min-h-8 items-start justify-between gap-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pending Delivery Qty</p>
            <span class="rounded-lg bg-cyan-50 p-1.5 text-cyan-600 group-hover:bg-cyan-100">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v10H3zM14 10h4l3 3v4h-7zM7 20a2 2 0 100-4 2 2 0 000 4zm11 0a2 2 0 100-4 2 2 0 000 4z" /></svg>
            </span>
          </div>
          <p class="mt-auto pt-2 text-xl font-bold tabular-nums text-slate-800">{{ Number(procurementSummary.pending_delivery_quantity ?? 0).toFixed(2) }}</p>
        </a>

        <a :href="route('backend.stock.purchase-payments')" class="group flex h-full min-h-[108px] flex-col rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition duration-150 hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2" title="View outstanding supplier dues">
          <div class="flex min-h-8 items-start justify-between gap-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Purchase Due</p>
            <span class="rounded-lg bg-amber-50 p-1.5 text-amber-600 group-hover:bg-amber-100">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18m7-14H9a3 3 0 000 6h6a3 3 0 010 6H5" /></svg>
            </span>
          </div>
          <p class="mt-auto pt-2 text-xl font-bold tabular-nums text-slate-800">{{ formatMoney(procurementSummary.purchase_due) }}</p>
        </a>

        <a :href="route('backend.stock.purchase-payments')" class="group flex h-full min-h-[108px] flex-col rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition duration-150 hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2" title="View supplier payments">
          <div class="flex min-h-8 items-start justify-between gap-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Supplier Payments</p>
            <span class="rounded-lg bg-emerald-50 p-1.5 text-emerald-600 group-hover:bg-emerald-100">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18v10H3zM7 11h4m-4 3h7M17 4v3m-10 10v3" /></svg>
            </span>
          </div>
          <p class="mt-auto pt-2 text-xl font-bold tabular-nums text-slate-800">{{ formatMoney(procurementSummary.supplier_payments) }}</p>
        </a>
      </div>

      <div class="dashboard-summary-grid grid auto-rows-fr grid-cols-1 gap-2 mt-2 sm:grid-cols-2 xl:grid-cols-5">
        <a
          :href="stockReportUrl()"
          class="group flex h-full min-h-[108px] flex-col rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition duration-150 hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
          title="Open the complete stock report"
        >
          <div class="flex min-h-8 items-start justify-between gap-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Items</p>
            <span class="rounded-lg bg-indigo-50 p-1.5 text-indigo-600 transition group-hover:bg-indigo-100">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5.5h16v13H4zM8 9h8M8 13h5" /></svg>
            </span>
          </div>
          <p class="mt-auto pt-2 text-xl font-bold tabular-nums text-slate-800">{{ summary.total_items ?? 0 }}</p>
        </a>

        <a
          :href="stockReportUrl({ status: 'Active' })"
          class="group flex h-full min-h-[108px] flex-col rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition duration-150 hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
          title="View active store items"
        >
          <div class="flex min-h-8 items-start justify-between gap-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Active Items</p>
            <span class="rounded-lg bg-emerald-50 p-1.5 text-emerald-600 transition group-hover:bg-emerald-100">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5 2a8 8 0 11-16 0 8 8 0 0116 0z" /></svg>
            </span>
          </div>
          <p class="mt-auto pt-2 text-xl font-bold tabular-nums text-slate-800">{{ summary.active_items ?? 0 }}</p>
        </a>

        <a
          :href="stockReportUrl()"
          class="group flex h-full min-h-[108px] flex-col rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition duration-150 hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2"
          title="Open stock quantities in the stock report"
        >
          <div class="flex min-h-8 items-start justify-between gap-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Quantity</p>
            <span class="rounded-lg bg-sky-50 p-1.5 text-sky-600 transition group-hover:bg-sky-100">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16M7 4v6m10 4v6" /></svg>
            </span>
          </div>
          <p class="mt-auto pt-2 text-xl font-bold tabular-nums text-slate-800">{{ Number(summary.total_quantity ?? 0).toFixed(0) }}</p>
        </a>

        <a
          :href="stockReportUrl()"
          class="group flex h-full min-h-[108px] flex-col rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition duration-150 hover:-translate-y-0.5 hover:border-violet-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2"
          title="Open inventory values in the stock report"
        >
          <div class="flex min-h-8 items-start justify-between gap-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Inventory Value</p>
            <span class="rounded-lg bg-violet-50 p-1.5 text-violet-600 transition group-hover:bg-violet-100">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18m7-14H9a3 3 0 000 6h6a3 3 0 010 6H5" /></svg>
            </span>
          </div>
          <p class="mt-auto pt-2 text-xl font-bold tabular-nums text-slate-800">{{ formatMoney(summary.inventory_value) }}</p>
        </a>

        <a
          :href="route('backend.stock.low-stock-report')"
          class="group flex h-full min-h-[108px] flex-col rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition duration-150 hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2"
          title="View low stock items"
        >
          <div class="flex min-h-8 items-start justify-between gap-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Low Stock Items</p>
            <span class="rounded-lg bg-amber-50 p-1.5 text-amber-600 transition group-hover:bg-amber-100">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 4.9L2.8 18a2 2 0 001.7 3h15a2 2 0 001.7-3L13.7 4.9a2 2 0 00-3.4 0z" /></svg>
            </span>
          </div>
          <p class="mt-auto pt-2 text-xl font-bold tabular-nums text-slate-800">{{ Number(summary.low_stock_items ?? 0) }}</p>
        </a>

        <a
          :href="route('backend.stock.daily-usage', { date: currentDate })"
          class="group flex h-full min-h-[108px] flex-col rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition duration-150 hover:-translate-y-0.5 hover:border-rose-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2"
          title="View today's item usage by department"
        >
          <div class="flex min-h-8 items-start justify-between gap-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Today's Usage</p>
            <span class="rounded-lg bg-rose-50 p-1.5 text-rose-600 transition group-hover:bg-rose-100">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </span>
          </div>
          <p class="mt-auto pt-2 text-xl font-bold tabular-nums text-slate-800">{{ Number(dailyUsageSummary.total_used_quantity ?? 0).toFixed(2) }}</p>
        </a>
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
        <p class="mb-2 text-sm text-slate-600">
          Opening Stock is the starting balance; Purchased Qty is received through GRNs. Total Current Stock = Central Stock + Department Stock.
        </p>
        <table class="w-full text-sm border border-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 border text-left">Item Code</th>
              <th class="px-3 py-2 border text-left">Item Name</th>
              <th class="px-3 py-2 border text-left">Category</th>
              <th class="px-3 py-2 border text-left">Unit</th>
              <th class="px-3 py-2 border text-right">Opening Stock</th>
              <th class="px-3 py-2 border text-right">Purchased Qty</th>
              <th class="px-3 py-2 border text-right">Central Stock</th>
              <th class="px-3 py-2 border text-right">Department Stock</th>
              <th class="px-3 py-2 border text-right">Total Current Stock</th>
              <th class="px-3 py-2 border text-right">Unit Cost</th>
              <th class="px-3 py-2 border text-right">Reorder Level</th>
              <th class="px-3 py-2 border text-center">Status</th>
              <th class="px-3 py-2 border text-center">Bill Usage</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in rows" :key="item.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 border">{{ item.item_code || '-' }}</td>
              <td class="px-3 py-2 border">{{ item.item_name }}</td>
              <td class="px-3 py-2 border">{{ item.category || '-' }}</td>
              <td class="px-3 py-2 border">{{ item.unit || '-' }}</td>
              <td class="px-3 py-2 border text-right">{{ Number(item.opening_quantity ?? 0).toFixed(2) }}</td>
              <td class="px-3 py-2 border text-right">{{ Number(item.purchased_quantity ?? 0).toFixed(2) }}</td>
              <td class="px-3 py-2 border text-right">{{ Number(item.central_stock ?? 0).toFixed(2) }}</td>
              <td class="px-3 py-2 border text-right">{{ Number(item.department_stock ?? 0).toFixed(2) }}</td>
              <td class="px-3 py-2 border text-right">{{ Number(item.current_stock ?? 0).toFixed(2) }}</td>
              <td class="px-3 py-2 border text-right">{{ formatMoney(item.unit_cost) }}</td>
              <td class="px-3 py-2 border text-right">{{ Number(item.reorder_level ?? 0).toFixed(2) }}</td>
              <td class="px-3 py-2 border text-center">
                <span class="px-2 py-1 text-xs rounded" :class="item.status === 'Active' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'">{{ item.status }}</span>
              </td>
              <td class="px-3 py-2 border text-center">
                <a :href="route('backend.stock.reagent-usage', item.id)" class="text-indigo-700 hover:underline">Configure</a>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="13" class="px-3 py-6 text-center text-gray-500 border">No store item found.</td>
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

<style scoped>
.dashboard-summary-grid > a {
  min-height: 82px;
  padding: 10px;
}

.dashboard-summary-grid > a > div:first-child {
  min-height: 24px;
}

.dashboard-summary-grid > a > div:first-child > span {
  padding: 4px;
}

.dashboard-summary-grid > a > p {
  padding-top: 4px;
  font-size: 1.125rem;
  line-height: 1.5rem;
}
</style>
