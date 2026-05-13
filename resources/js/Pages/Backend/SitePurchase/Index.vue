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
  filters: {
    type: Object,
    default: () => ({}),
  },
});

const filters = ref({
  search: props.filters?.search ?? '',
  site_name: props.filters?.site_name ?? '',
  nature: props.filters?.nature ?? '',
  numOfData: props.filters?.numOfData ?? 10,
});

const applyFilter = () => {
  router.get(route('backend.sitepurchase.index'), filters.value, {
    preserveState: true,
    replace: true,
  });
};

const clearFilters = () => {
  filters.value.search = '';
  filters.value.site_name = '';
  filters.value.nature = '';
  applyFilter();
};

const rows = props.purchases?.data ?? [];

const money = (value) => Number(value ?? 0).toFixed(2);

const formatDate = (value) => {
  if (!value) return '-';

  if (typeof value === 'string') {
    const dateOnly = value.match(/^(\d{4}-\d{2}-\d{2})/);
    if (dateOnly?.[1]) return dateOnly[1];
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '-';

  return new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Dhaka',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(date);
};

const deletePurchase = (id) => {
  if (!window.confirm('Are you sure you want to delete this site purchase?')) return;
  router.delete(route('backend.sitepurchase.destroy', id), { preserveScroll: true });
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-2 p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">Site Purchase List</h1>
        <Link :href="route('backend.sitepurchase.create')" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded hover:bg-indigo-700">
          Add Site Purchase
        </Link>
      </div>

      <div class="grid grid-cols-1 gap-2 p-3 mt-3 md:grid-cols-5 bg-slate-100 rounded">
        <input
          v-model="filters.search"
          class="w-full p-2 text-sm border rounded border-slate-300"
          type="text"
          placeholder="Search number/site/vendor/item"
          @input="applyFilter"
        />
        <input
          v-model="filters.site_name"
          class="w-full p-2 text-sm border rounded border-slate-300"
          type="text"
          placeholder="Site"
          @input="applyFilter"
        />
        <select v-model="filters.nature" class="w-full p-2 text-sm border rounded border-slate-300" @change="applyFilter">
          <option value="">All Nature</option>
          <option value="asset">Asset</option>
          <option value="expense">Expense</option>
        </select>
        <select v-model="filters.numOfData" class="w-full p-2 text-sm border rounded border-slate-300" @change="applyFilter">
          <option value="10">Show 10</option>
          <option value="20">Show 20</option>
          <option value="50">Show 50</option>
          <option value="100">Show 100</option>
        </select>
        <button type="button" class="w-full p-2 text-sm text-gray-700 bg-gray-200 rounded hover:bg-gray-300" @click="clearFilters">
          Clear
        </button>
      </div>

      <div class="w-full my-4 overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700 border border-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 border">Purchase No</th>
              <th class="px-3 py-2 border">Site</th>
              <th class="px-3 py-2 border">Vendor</th>
              <th class="px-3 py-2 border">Item</th>
              <th class="px-3 py-2 border">Nature</th>
              <th class="px-3 py-2 border">Date</th>
              <th class="px-3 py-2 border">Total</th>
              <th class="px-3 py-2 border">Paid</th>
              <th class="px-3 py-2 border">Due</th>
              <th class="px-3 py-2 border">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="purchase in rows" :key="purchase.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 border">{{ purchase.purchase_number }}</td>
              <td class="px-3 py-2 border">{{ purchase.site_name }}</td>
              <td class="px-3 py-2 border">{{ purchase.vendor_name || 'N/A' }}</td>
              <td class="px-3 py-2 border">{{ purchase.item_name }}</td>
              <td class="px-3 py-2 border capitalize">{{ purchase.purchase_nature }}</td>
              <td class="px-3 py-2 border">{{ formatDate(purchase.purchase_date) }}</td>
              <td class="px-3 py-2 border">{{ money(purchase.total_amount) }}</td>
              <td class="px-3 py-2 border">{{ money(purchase.paid_amount) }}</td>
              <td class="px-3 py-2 border">{{ money(purchase.due_amount) }}</td>
              <td class="px-3 py-2 border">
                <div class="flex flex-wrap gap-2">
                  <Link :href="route('backend.sitepurchase.show', purchase.id)" class="px-2 py-1 text-xs text-white bg-teal-600 rounded hover:bg-teal-700">
                    View
                  </Link>
                  <Link :href="route('backend.sitepurchase.edit', purchase.id)" class="px-2 py-1 text-xs text-black bg-yellow-400 rounded hover:bg-yellow-500">
                    Edit
                  </Link>
                  <button type="button" class="px-2 py-1 text-xs text-white bg-red-600 rounded hover:bg-red-700" @click="deletePurchase(purchase.id)">
                    Delete
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="10" class="px-3 py-6 text-center text-gray-500 border">No site purchase found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination />
    </div>
  </BackendLayout>
</template>
