<script setup>
import { computed, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'Store Suppliers' },
  suppliers: { type: Object, default: () => ({ data: [] }) },
  filters: { type: Object, default: () => ({}) },
  createdSupplier: { type: Object, default: null },
});

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');
const rows = computed(() => props.suppliers?.data ?? []);

onMounted(() => {
  if (!props.createdSupplier?.id || !window.opener) return;
  window.opener.postMessage({
    type: 'store-supplier-created',
    supplier: { id: props.createdSupplier.id, name: props.createdSupplier.name },
  }, window.location.origin);
  window.close();
});

const applyFilters = () => router.get(route('backend.storesupplier.index'), {
  search: search.value || undefined,
  status: status.value || undefined,
}, { preserveState: true, preserveScroll: true, replace: true });

const removeSupplier = (supplier) => {
  if (supplier.purchase_orders_count > 0) {
    window.alert('This supplier has purchase orders and cannot be deleted.');
    return;
  }

  if (window.confirm(`Delete store supplier "${supplier.name}"?`)) {
    router.delete(route('backend.storesupplier.destroy', supplier.id), { preserveScroll: true });
  }
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-3 p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
        <div class="flex gap-2">
          <a :href="route('backend.stock.purchase-orders')" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Purchase Orders</a>
          <a :href="route('backend.storesupplier.create')" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Add Store Supplier</a>
        </div>
      </div>

      <div class="flex flex-wrap gap-2 mt-4">
        <input v-model="search" class="p-2 text-sm border rounded" placeholder="Search supplier / contact / phone" @keyup.enter="applyFilters" />
        <select v-model="status" class="p-2 text-sm border rounded" @change="applyFilters">
          <option value="">All statuses</option>
          <option>Active</option>
          <option>Inactive</option>
        </select>
        <button type="button" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700" @click="applyFilters">Search</button>
      </div>

      <div class="mt-4 overflow-x-auto">
        <table class="w-full text-sm border border-gray-200">
          <thead class="bg-gray-50"><tr>
            <th class="px-3 py-2 border text-left">Supplier</th>
            <th class="px-3 py-2 border text-left">Phone</th>
            <th class="px-3 py-2 border text-left">Contact person</th>
            <th class="px-3 py-2 border text-left">Contact phone</th>
            <th class="px-3 py-2 border text-left">Address</th>
            <th class="px-3 py-2 border text-center">Purchase orders</th>
            <th class="px-3 py-2 border text-center">Status</th>
            <th class="px-3 py-2 border text-center">Actions</th>
          </tr></thead>
          <tbody>
            <tr v-for="supplier in rows" :key="supplier.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 border">{{ supplier.name }}</td>
              <td class="px-3 py-2 border">{{ supplier.phone || '-' }}</td>
              <td class="px-3 py-2 border">{{ supplier.contact_person_name || '-' }}</td>
              <td class="px-3 py-2 border">{{ supplier.contact_person_phone || '-' }}</td>
              <td class="px-3 py-2 border">{{ supplier.address || '-' }}</td>
              <td class="px-3 py-2 border text-center">{{ supplier.purchase_orders_count }}</td>
              <td class="px-3 py-2 border text-center">{{ supplier.status }}</td>
              <td class="px-3 py-2 border text-center whitespace-nowrap">
                <a :href="route('backend.storesupplier.edit', supplier.id)" class="inline-block px-3 py-1 text-xs font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Edit</a>
                <button type="button" class="px-3 py-1 ml-1 text-xs font-semibold text-white bg-rose-600 rounded hover:bg-rose-700" :disabled="supplier.purchase_orders_count > 0" @click="removeSupplier(supplier)">Delete</button>
              </td>
            </tr>
            <tr v-if="rows.length === 0"><td colspan="8" class="px-3 py-6 text-center text-gray-500 border">No store suppliers found.</td></tr>
          </tbody>
        </table>
      </div>

      <div v-if="suppliers?.links?.length" class="flex flex-wrap justify-end gap-2 mt-4">
        <a v-for="(link, index) in suppliers.links" :key="index" :href="link.url || '#'" class="px-3 py-1 text-sm border rounded" :class="link.active ? 'bg-emerald-600 text-white border-emerald-600' : 'text-slate-600'" v-html="link.label" />
      </div>
    </div>
  </BackendLayout>
</template>
