<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const APP_TIMEZONE = 'Asia/Dhaka';

const props = defineProps({
  return: {
    type: Object,
    default: () => ({}),
  },
});

const returnData = computed(() => props.return ?? {});

const approveReturn = () => {
  router.post(route('backend.productreturn.approve', returnData.value.id), {}, { preserveScroll: true });
};

const processReturn = () => {
  router.post(route('backend.productreturn.process', returnData.value.id), {}, { preserveScroll: true });
};

const formatReturnDate = (value) => {
  if (!value) return '-';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return date.toLocaleDateString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    timeZone: APP_TIMEZONE,
  });
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-2 p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ returnData.return_type === 'supplier' ? 'Supplier Product Return Details' : 'Customer Product Return Details' }}</h1>
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="px-3 py-2 text-sm text-white bg-blue-600 rounded hover:bg-blue-700 disabled:opacity-60"
            :disabled="returnData.status !== 'pending'"
            @click="approveReturn"
          >
            Approve
          </button>
          <button
            type="button"
            class="px-3 py-2 text-sm text-white bg-emerald-600 rounded hover:bg-emerald-700 disabled:opacity-60"
            :disabled="returnData.status !== 'approved'"
            @click="processReturn"
          >
            Process
          </button>
          <Link :href="route('backend.productreturn.index', { return_type: returnData.return_type || 'supplier' })" class="px-3 py-2 text-sm text-white bg-gray-600 rounded hover:bg-gray-700">
            Back
          </Link>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-3 mt-4 md:grid-cols-2">
        <div><span class="font-semibold">Return No:</span> {{ returnData.return_number }}</div>
        <div><span class="font-semibold">Status:</span> <span class="capitalize">{{ returnData.status }}</span></div>
        <div><span class="font-semibold">Type:</span> <span class="capitalize">{{ returnData.return_type }}</span></div>
        <div><span class="font-semibold">Supplier:</span> {{ returnData.supplier?.name ?? 'N/A' }}</div>
        <div><span class="font-semibold">Date:</span> {{ formatReturnDate(returnData.return_date) }}</div>
        <div><span class="font-semibold">Total:</span> {{ Number(returnData.total_amount ?? 0).toFixed(2) }}</div>
        <div class="md:col-span-2"><span class="font-semibold">Reason:</span> {{ returnData.reason ?? '-' }}</div>
        <div class="md:col-span-2"><span class="font-semibold">Notes:</span> {{ returnData.notes ?? '-' }}</div>
      </div>

      <div class="w-full mt-4 overflow-x-auto">
        <table class="w-full text-sm border border-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 border">Medicine</th>
              <th class="px-3 py-2 border">Quantity</th>
              <th class="px-3 py-2 border">Unit Price</th>
              <th class="px-3 py-2 border">Total</th>
              <th class="px-3 py-2 border">Condition</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in (returnData.return_items || [])" :key="item.id">
              <td class="px-3 py-2 border">{{ item.medicine_inventory?.medicine_name ?? 'N/A' }}</td>
              <td class="px-3 py-2 border">{{ item.quantity }}</td>
              <td class="px-3 py-2 border">{{ Number(item.unit_price ?? 0).toFixed(2) }}</td>
              <td class="px-3 py-2 border">{{ Number(item.total_amount ?? 0).toFixed(2) }}</td>
              <td class="px-3 py-2 border capitalize">{{ item.condition }}</td>
            </tr>
            <tr v-if="!(returnData.return_items || []).length">
              <td colspan="5" class="px-3 py-6 text-center text-gray-500 border">No return items.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </BackendLayout>
</template>
