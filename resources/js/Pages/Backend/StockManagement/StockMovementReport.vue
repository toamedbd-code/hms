<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'Stock Movement Report' },
  adjustments: { type: Object, default: () => ({ data: [] }) },
  items: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
});

const filterForm = ref({
  store_item_id: props.filters?.store_item_id ?? '',
  start_date: props.filters?.start_date ?? '',
  end_date: props.filters?.end_date ?? '',
});

const rows = computed(() => props.adjustments?.data ?? []);

const formatDateTime = (value) => {
  if (!value) return '-';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);

  return date.toLocaleString('en-GB', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  });
};

const applyFilter = () => {
  router.get(route('backend.stock.movement-report'), filterForm.value, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilter = () => {
  filterForm.value = { store_item_id: '', start_date: '', end_date: '' };
  applyFilter();
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
        <a :href="route('backend.stock.index')" class="px-3 py-2 text-sm font-semibold text-white bg-gray-600 rounded hover:bg-gray-700">Back Dashboard</a>
      </div>

      <div class="grid grid-cols-1 gap-2 p-3 mt-3 bg-slate-100 rounded md:grid-cols-4">
        <div>
          <select v-model="filterForm.store_item_id" class="w-full p-2 text-sm border rounded" @change="applyFilter">
            <option value="">All Store Items</option>
            <option v-for="item in items" :key="item.id" :value="item.id">{{ item.item_name }}</option>
          </select>
        </div>
        <div>
          <input v-model="filterForm.start_date" type="date" class="w-full p-2 text-sm border rounded" @change="applyFilter" />
        </div>
        <div>
          <input v-model="filterForm.end_date" type="date" class="w-full p-2 text-sm border rounded" @change="applyFilter" />
        </div>
        <div>
          <button type="button" class="w-full px-3 py-2 text-sm text-white bg-gray-600 rounded hover:bg-gray-700" @click="resetFilter">Reset</button>
        </div>
      </div>

      <div class="w-full mt-3 overflow-x-auto">
        <table class="w-full text-sm border border-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 border">Date</th>
              <th class="px-3 py-2 border text-left">Item</th>
              <th class="px-3 py-2 border">Type</th>
              <th class="px-3 py-2 border">Qty</th>
              <th class="px-3 py-2 border">Unit Price</th>
              <th class="px-3 py-2 border text-left">Reference</th>
              <th class="px-3 py-2 border text-left">Department</th>
              <th class="px-3 py-2 border text-left">Reason</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in rows" :key="item.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 border text-center">{{ formatDateTime(item.movement_date) }}</td>
              <td class="px-3 py-2 border">{{ item.store_item?.item_name || '-' }}</td>
              <td class="px-3 py-2 border text-center">{{ item.movement_type }}</td>
              <td class="px-3 py-2 border text-center">{{ item.quantity }}</td>
              <td class="px-3 py-2 border text-center">{{ Number(item.unit_price ?? 0).toFixed(2) }}</td>
              <td class="px-3 py-2 border">{{ item.reference_no || '-' }}</td>
              <td class="px-3 py-2 border">{{ item.department || '-' }}</td>
              <td class="px-3 py-2 border">{{ item.reason }}</td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="8" class="px-3 py-6 text-center text-gray-500 border">No movement found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="props.adjustments?.links?.length" class="grid grid-cols-1 gap-4 pt-3 my-2 md:grid-cols-2 items-center">
        <p class="text-sm text-gray-600 text-center md:text-left">
          Displaying {{ props.adjustments?.from ?? 0 }} to {{ props.adjustments?.to ?? 0 }} of {{ props.adjustments?.total ?? 0 }} items
        </p>
        <nav>
          <ul class="flex items-center justify-center md:justify-end gap-2">
            <li v-for="(link, index) in props.adjustments.links" :key="`${index}-${link.label}`">
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
