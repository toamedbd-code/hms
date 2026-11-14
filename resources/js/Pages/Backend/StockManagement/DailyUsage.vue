<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'Daily Stock Usage' },
  date: { type: String, default: '' },
  currentDate: { type: String, default: '' },
  summary: { type: Object, default: () => ({}) },
  rows: { type: Object, default: () => ({ data: [] }) },
});

const selectedDate = ref(props.date);
const movements = computed(() => props.rows?.data ?? []);
const updateDate = () => {
  router.get(route('backend.stock.daily-usage'), { date: selectedDate.value }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};
const formatQuantity = (value) => Number(value ?? 0).toFixed(2);
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-rose-100 bg-gradient-to-r from-rose-50 via-orange-50 to-amber-50 p-5">
        <div>
          <h1 class="text-xl font-bold tracking-tight text-slate-800">{{ pageTitle }}</h1>
          <p class="mt-1 text-sm text-slate-600">Item quantities used by departments on the selected day.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <a :href="route('backend.stock.index')" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">Back</a>
          <label for="daily-usage-date" class="sr-only">Usage date</label>
          <input id="daily-usage-date" v-model="selectedDate" type="date" :max="currentDate" class="rounded-lg border border-slate-300 bg-white p-2 text-sm focus:border-rose-500 focus:ring-rose-500" @change="updateDate" />
        </div>
      </div>

      <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Products</p>
          <p class="mt-2 text-2xl font-bold tabular-nums text-slate-800">{{ summary.total_items ?? 0 }}</p>
        </div>
        <div class="rounded-xl border border-rose-200 bg-rose-50/60 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-rose-800">Products Used</p>
          <p class="mt-2 text-2xl font-bold tabular-nums text-rose-950">{{ summary.items_used ?? 0 }}</p>
        </div>
        <div class="rounded-xl border border-orange-200 bg-orange-50/60 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-orange-800">Total Quantity Used</p>
          <p class="mt-2 text-2xl font-bold tabular-nums text-orange-950">{{ formatQuantity(summary.total_used_quantity) }}</p>
        </div>
        <div class="rounded-xl border border-indigo-200 bg-indigo-50/60 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-indigo-800">Departments Using Stock</p>
          <p class="mt-2 text-2xl font-bold tabular-nums text-indigo-950">{{ summary.departments_using ?? 0 }}</p>
        </div>
      </div>

      <div class="mt-5 flex flex-wrap items-end justify-between gap-2">
        <div>
          <h2 class="text-base font-semibold text-slate-800">Usage by product and department</h2>
          <p class="mt-1 text-xs text-slate-500">Includes billed test consumption and manually recorded department use; indent transfers are not counted as consumption.</p>
        </div>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ props.rows?.total ?? 0 }} records</span>
      </div>

      <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200">
        <table class="w-full min-w-[800px] text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="border-b border-slate-200 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Code</th>
              <th class="border-b border-slate-200 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Product</th>
              <th class="border-b border-slate-200 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Department</th>
              <th class="border-b border-slate-200 px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-600">Current Stock</th>
              <th class="border-b border-slate-200 px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-600">Used</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, index) in movements" :key="`${row.store_item_id}-${row.department_id}`" class="transition-colors hover:bg-rose-50/50" :class="index % 2 ? 'bg-slate-50/50' : 'bg-white'">
              <td class="border-b border-slate-100 px-4 py-3 text-slate-600">{{ row.item_code || '-' }}</td>
              <td class="border-b border-slate-100 px-4 py-3 font-semibold text-slate-800">{{ row.item_name }}</td>
              <td class="border-b border-slate-100 px-4 py-3 text-slate-700">{{ row.department_name || '-' }}</td>
              <td class="border-b border-slate-100 px-4 py-3 text-right font-semibold tabular-nums text-slate-700">{{ formatQuantity(row.current_stock) }} {{ row.unit }}</td>
              <td class="border-b border-slate-100 px-4 py-3 text-right font-semibold tabular-nums text-rose-700">{{ formatQuantity(row.used_quantity) }} {{ row.unit }}</td>
            </tr>
            <tr v-if="movements.length === 0">
              <td colspan="5" class="px-4 py-14 text-center">
                <p class="font-semibold text-slate-700">No usage recorded for this date</p>
                <p class="mt-1 text-sm text-slate-500">Billed test usage and department use will appear here when recorded.</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="props.rows?.links?.length" class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
        <p class="text-sm text-slate-500">Displaying {{ props.rows.from ?? 0 }} to {{ props.rows.to ?? 0 }} of {{ props.rows.total ?? 0 }} records</p>
        <nav class="flex flex-wrap items-center gap-1.5">
          <template v-for="(link, index) in props.rows.links" :key="`${index}-${link.label}`">
            <a v-if="link.url" :href="link.url" class="inline-flex min-h-9 items-center justify-center rounded-lg border px-3 py-1.5 text-sm font-medium" :class="link.active ? 'border-rose-600 bg-rose-600 text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50'">
              <span v-html="link.label"></span>
            </a>
            <span v-else class="inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-100 px-3 py-1.5 text-sm text-slate-300" v-html="link.label"></span>
          </template>
        </nav>
      </div>
    </div>
  </BackendLayout>
</template>
