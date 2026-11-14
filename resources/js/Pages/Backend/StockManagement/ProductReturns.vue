<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'Product Returns' },
  returns: { type: Object, default: () => ({ data: [] }) },
  filters: { type: Object, default: () => ({}) },
});

const rows = computed(() => props.returns?.data ?? []);
const search = ref(props.filters?.search ?? '');
const perPage = ref(Number(props.filters?.per_page ?? props.returns?.per_page ?? 20));

const applyFilters = () => {
  router.get(route('backend.stock.product-returns'), {
    search: search.value,
    per_page: perPage.value,
  }, { preserveState: true, preserveScroll: true, replace: true });
};

const formatDate = (value) => {
  const [year, month, day] = String(value ?? '').slice(0, 10).split('-');
  return year && month && day ? `${day}/${month}/${year}` : '-';
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-3 p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
        <div class="flex flex-wrap gap-2">
          <a :href="route('backend.stock.index')" class="px-3 py-2 text-sm font-semibold text-white bg-red-600 rounded hover:bg-red-700">Back</a>
          <a :href="route('backend.stock.product-returns.create')" class="px-3 py-2 text-sm font-semibold text-white bg-orange-600 rounded hover:bg-orange-700">New Product Return</a>
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3 mt-3">
        <input v-model="search" type="search" placeholder="Search return, supplier or item" class="w-full max-w-sm px-3 py-2 text-sm border rounded" @keyup.enter="applyFilters">
        <div class="flex items-center gap-2">
          <label for="return_per_page" class="text-sm text-slate-600">Show</label>
          <select id="return_per_page" v-model="perPage" class="px-2 py-2 text-sm border rounded" @change="applyFilters">
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
          </select>
        </div>
      </div>

      <div class="w-full mt-3 overflow-x-auto">
        <table class="w-full text-sm border border-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 border text-left">Return No.</th>
              <th class="px-3 py-2 border text-left">Date</th>
              <th class="px-3 py-2 border text-left">Supplier</th>
              <th class="px-3 py-2 border text-left">Items</th>
              <th class="px-3 py-2 border text-left">Reason</th>
              <th class="px-3 py-2 border text-right">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in rows" :key="entry.id" class="align-top hover:bg-gray-50">
              <td class="px-3 py-2 border font-semibold">{{ entry.return_number }}</td>
              <td class="px-3 py-2 border">{{ formatDate(entry.return_date) }}</td>
              <td class="px-3 py-2 border">{{ entry.supplier?.name || '-' }}</td>
              <td class="px-3 py-2 border">
                <div v-for="line in entry.items || []" :key="line.id">
                  {{ line.store_item?.item_name || 'Item' }} — {{ Number(line.quantity).toFixed(2) }} {{ line.store_item?.unit || '' }}
                </div>
              </td>
              <td class="px-3 py-2 border">{{ entry.reason }}</td>
              <td class="px-3 py-2 border text-right">{{ Number(entry.total_amount ?? 0).toFixed(2) }}</td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="6" class="px-3 py-6 text-center text-gray-500 border">No product returns found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="props.returns?.links?.length" class="grid grid-cols-1 gap-4 pt-3 my-2 md:grid-cols-2 items-center">
        <p class="text-sm text-gray-600 text-center md:text-left">
          Displaying {{ props.returns?.from ?? 0 }} to {{ props.returns?.to ?? 0 }} of {{ props.returns?.total ?? 0 }} returns
        </p>
        <nav>
          <ul class="flex items-center justify-center md:justify-end gap-2">
            <li v-for="(link, index) in props.returns.links" :key="`${index}-${link.label}`">
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
