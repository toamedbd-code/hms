<script setup>
import { computed } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'GRN / Purchase Receive' },
  grns: { type: Object, default: () => ({ data: [] }) },
});

const rows = computed(() => props.grns?.data ?? []);

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
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
        <div class="flex gap-2">
          <a :href="route('backend.stock.index')" class="px-3 py-2 text-sm font-semibold text-white bg-gray-600 rounded hover:bg-gray-700">Back Dashboard</a>
          <a :href="route('backend.stock.grn.create')" class="px-3 py-2 text-sm font-semibold text-white bg-indigo-600 rounded hover:bg-indigo-700">New GRN</a>
        </div>
      </div>

      <div class="w-full mt-3 overflow-x-auto">
        <table class="w-full text-sm border border-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 border">GRN No</th>
              <th class="px-3 py-2 border">Date</th>
              <th class="px-3 py-2 border">Supplier</th>
              <th class="px-3 py-2 border">Invoice</th>
              <th class="px-3 py-2 border">Lines</th>
              <th class="px-3 py-2 border">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id" class="hover:bg-gray-50 align-top">
              <td class="px-3 py-2 border text-center">{{ row.grn_no }}</td>
              <td class="px-3 py-2 border text-center">{{ formatDateTime(row.receive_date) }}</td>
              <td class="px-3 py-2 border">{{ row.supplier_name || '-' }}</td>
              <td class="px-3 py-2 border">{{ row.invoice_no || '-' }}</td>
              <td class="px-3 py-2 border">
                <ul class="space-y-1">
                  <li v-for="line in row.items" :key="line.id" class="text-xs">
                    {{ line.store_item?.item_name || '-' }} - {{ Number(line.quantity).toFixed(2) }} x {{ Number(line.unit_cost).toFixed(2) }}
                  </li>
                </ul>
              </td>
              <td class="px-3 py-2 border text-center">
                <a :href="route('backend.stock.grn.print', row.id)" class="px-2 py-1 text-xs text-white bg-sky-600 rounded hover:bg-sky-700">Print</a>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="6" class="px-3 py-6 text-center text-gray-500 border">No GRN found.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </BackendLayout>
</template>
