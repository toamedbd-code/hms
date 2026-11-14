<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'Reagent Management' },
  labDepartmentId: { type: Number, default: null },
  items: { type: Object, default: () => ({ data: [] }) },
  summary: { type: Object, default: () => ({}) },
  filters: { type: Object, default: () => ({}) },
});

const perPage = ref(Number(props.filters?.per_page ?? props.items?.per_page ?? 20));
const rows = computed(() => props.items?.data ?? []);
const formatMoney = (value) => Number(value ?? 0).toFixed(2);

function applyPerPage() {
  router.get(route('backend.stock.reagents'), { per_page: perPage.value }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
}
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-3 p-4 bg-gray-100 rounded">
        <div>
          <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
          <p class="mt-1 text-sm text-gray-600">Buy supplies into Central Store, receive deliveries, then send them to the Lab with a department indent.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <Link :href="route('backend.stock.index')" class="px-3 py-2 text-sm font-semibold border border-slate-300 rounded">Stock Management</Link>
          <Link :href="route('backend.stock.department-reagents')" class="px-3 py-2 text-sm font-semibold border border-slate-300 rounded">Department Inventory</Link>
          <Link :href="route('backend.stock.purchase-orders.create', { reagent_only: 1 })" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded">Purchase Reagents</Link>
          <Link :href="route('backend.stock.grns')" class="px-3 py-2 text-sm font-semibold border border-slate-300 rounded">Receive GRN</Link>
          <Link :href="route('backend.stock.requisition.create', { reagent_only: 1 })" class="px-3 py-2 text-sm font-semibold text-white bg-teal-600 rounded">Create Reagent Indent</Link>
          <Link :href="route('backend.stock.item.create', { category: 'Reagent' })" class="px-3 py-2 text-sm font-semibold text-white bg-indigo-600 rounded hover:bg-indigo-700">Add Reagent</Link>
        </div>
      </div>

      <ol class="grid grid-cols-1 gap-3 mt-4 text-sm sm:grid-cols-2 xl:grid-cols-4">
        <li class="p-3 border border-slate-200 rounded-lg">
          <span class="font-semibold text-slate-800">1. Set up reagent</span>
          <p class="mt-1 text-slate-600">Add the reagent once with its unit and reorder level.</p>
        </li>
        <li class="p-3 border border-slate-200 rounded-lg">
          <span class="font-semibold text-slate-800">2. Purchase from supplier</span>
          <p class="mt-1 text-slate-600">Create a reagent-only purchase order. It does not increase stock yet.</p>
        </li>
        <li class="p-3 border border-slate-200 rounded-lg">
          <span class="font-semibold text-slate-800">3. Receive delivered goods</span>
          <p class="mt-1 text-slate-600">Use Receive / GRN to record the delivered batch and expiry date in Central Store.</p>
        </li>
        <li class="p-3 border border-slate-200 rounded-lg">
          <span class="font-semibold text-slate-800">4. Send stock to department</span>
          <p class="mt-1 text-slate-600">Create an indent for Lab. Stock is usable after Store approves and issues it.</p>
        </li>
      </ol>
      <div class="p-3 mt-3 text-sm border border-sky-200 rounded-lg bg-sky-50 text-sky-900">
        Billing checks Lab department stock, not Central Store stock. Select <strong>Send to Lab</strong> beside the reagent, enter the quantity, submit the indent, then approve it from Requisitions to transfer stock.
      </div>

      <div class="grid grid-cols-1 gap-3 mt-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="p-3 border border-slate-200 rounded">
          <p class="text-xs text-slate-500">Total Reagents</p>
          <p class="mt-1 text-lg font-semibold text-slate-800">{{ summary.total_items ?? 0 }}</p>
        </div>
        <div class="p-3 border border-slate-200 rounded">
          <p class="text-xs text-slate-500">Active Reagents</p>
          <p class="mt-1 text-lg font-semibold text-slate-800">{{ summary.active_items ?? 0 }}</p>
        </div>
        <div class="p-3 border border-slate-200 rounded">
          <p class="text-xs text-slate-500">Low Stock</p>
          <p class="mt-1 text-lg font-semibold text-amber-700">{{ summary.low_stock_items ?? 0 }}</p>
        </div>
        <div class="p-3 border border-slate-200 rounded">
          <p class="text-xs text-slate-500">Inventory Value</p>
          <p class="mt-1 text-lg font-semibold text-slate-800">{{ formatMoney(summary.inventory_value) }}</p>
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-2 mt-4">
        <p class="text-sm text-slate-600">Total stock: {{ Number(summary.total_quantity ?? 0).toFixed(2) }}</p>
        <label class="flex items-center gap-2 text-sm text-slate-600">
          Rows
          <select v-model="perPage" class="px-2 py-1 border rounded" @change="applyPerPage">
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
          </select>
        </label>
      </div>

      <div class="w-full mt-3 overflow-x-auto">
        <table class="w-full text-sm border border-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 border text-left">Code</th>
              <th class="px-3 py-2 border text-left">Reagent</th>
              <th class="px-3 py-2 border text-left">Unit</th>
              <th class="px-3 py-2 border text-right">Available</th>
              <th class="px-3 py-2 border text-right">Unit Cost</th>
              <th class="px-3 py-2 border text-right">Reorder Level</th>
              <th class="px-3 py-2 border text-center">Status</th>
              <th class="px-3 py-2 border text-center">Usage</th>
              <th class="px-3 py-2 border text-center">Stock Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in rows" :key="item.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 border">{{ item.item_code || '-' }}</td>
              <td class="px-3 py-2 border">{{ item.item_name }}</td>
              <td class="px-3 py-2 border">{{ item.unit || '-' }}</td>
              <td class="px-3 py-2 border text-right" :class="Number(item.current_stock) <= Number(item.reorder_level) ? 'text-amber-700 font-semibold' : ''">
                {{ Number(item.current_stock ?? 0).toFixed(2) }}
              </td>
              <td class="px-3 py-2 border text-right">{{ formatMoney(item.unit_cost) }}</td>
              <td class="px-3 py-2 border text-right">{{ Number(item.reorder_level ?? 0).toFixed(2) }}</td>
              <td class="px-3 py-2 border text-center">{{ item.status }}</td>
              <td class="px-3 py-2 border text-center">
                <Link :href="route('backend.stock.reagent-usage', item.id)" class="text-indigo-700 hover:underline">Configure</Link>
              </td>
              <td class="px-3 py-2 border text-center">
                <div class="flex flex-wrap justify-center gap-1">
                  <Link :href="route('backend.stock.purchase-orders.create', { reagent_only: 1, store_item_id: item.id })" class="px-2 py-1 text-xs font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Purchase</Link>
                  <Link :href="route('backend.stock.requisition.create', { reagent_only: 1, store_item_id: item.id, department_id: labDepartmentId || undefined })" class="px-2 py-1 text-xs font-semibold text-white bg-teal-600 rounded hover:bg-teal-700">
                    {{ labDepartmentId ? 'Send to Lab' : 'Create Indent' }}
                  </Link>
                </div>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="9" class="px-3 py-6 text-center text-gray-500 border">No reagents found. Add an item with the Reagent category to begin.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="props.items?.links?.length" class="flex flex-wrap items-center justify-between gap-3 pt-3">
        <p class="text-sm text-gray-600">Displaying {{ props.items.from ?? 0 }} to {{ props.items.to ?? 0 }} of {{ props.items.total ?? 0 }} reagents</p>
        <nav class="flex flex-wrap items-center gap-2">
          <Link
            v-for="(link, index) in props.items.links"
            :key="`${index}-${link.label}`"
            :href="link.url || ''"
            preserve-scroll
            class="px-3 py-1 text-sm border rounded"
            :class="[link.active ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300', !link.url ? 'pointer-events-none opacity-50' : '']"
          >
            <span v-html="link.label"></span>
          </Link>
        </nav>
      </div>
    </div>
  </BackendLayout>
</template>