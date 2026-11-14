<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'Store Adjustments' },
  adjustments: { type: Object, default: () => ({ data: [] }) },
  timezone: { type: String, default: 'Asia/Dhaka' },
  filters: { type: Object, default: () => ({}) },
});

const perPage = ref(Number(props.filters?.per_page ?? props.adjustments?.per_page ?? 20));
const rows = computed(() => props.adjustments?.data ?? []);

const formatDateTime = (item) => {
  const timestamp = item?.created_at;
  if (!timestamp) {
    const [year, month, day] = String(item?.movement_date || '').slice(0, 10).split('-');
    return year && month && day ? `${day}/${month}/${year}` : '-';
  }

  const date = new Date(timestamp);
  if (Number.isNaN(date.getTime())) return String(timestamp);

  return date.toLocaleString('en-GB', {
    timeZone: props.timezone,
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  });
};

const applyPerPage = () => {
  router.get(route('backend.stock.adjustments'), { per_page: perPage.value }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
      <div class="flex flex-wrap items-center justify-between gap-4 p-5 border border-emerald-100 rounded-xl bg-gradient-to-r from-emerald-50 via-teal-50 to-cyan-50">
        <div class="flex items-center gap-3">
          <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white text-emerald-700 shadow-sm">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 6.5h16M4 12h16M4 17.5h16M7 4v5m10 1v5m-7 1v5" />
            </svg>
          </div>
          <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-800">{{ pageTitle }}</h1>
            <p class="mt-1 text-sm text-slate-600">Review stock increases and decreases recorded in the store.</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <a :href="route('backend.stock.index')" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7 7-7M3 12h18" />
            </svg>
            Back
          </a>
          <a :href="route('backend.stock.adjustment.create')" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14" />
            </svg>
            New Adjustment
          </a>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-3 mt-4 sm:grid-cols-2">
        <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4">
          <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-50 text-sky-700">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />
            </svg>
          </div>
          <div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total movements</p>
            <p class="mt-0.5 text-xl font-bold text-slate-800">{{ props.adjustments?.total ?? 0 }}</p>
          </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4">
          <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v14H4zM8 9h8M8 13h5" />
            </svg>
          </div>
          <div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">On this page</p>
            <p class="mt-0.5 text-xl font-bold text-slate-800">{{ rows.length }}</p>
          </div>
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3 mt-5">
        <p class="text-sm text-slate-500">Stock movement history</p>
        <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
          <label for="per_page" class="text-sm font-medium text-slate-600">Rows per page</label>
          <select id="per_page" v-model="perPage" class="rounded-md border-slate-300 bg-white py-1 pl-2 pr-7 text-sm font-medium text-slate-700 focus:border-emerald-500 focus:ring-emerald-500" @change="applyPerPage">
          <option :value="10">10</option>
          <option :value="20">20</option>
          <option :value="50">50</option>
          <option :value="100">100</option>
          </select>
        </div>
      </div>

      <div class="w-full mt-3 overflow-x-auto rounded-xl border border-slate-200">
        <table class="w-full min-w-[1050px] text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="whitespace-nowrap border-b border-slate-200 px-4 py-3 text-center text-xs font-bold uppercase tracking-wide text-slate-600">Date</th>
              <th class="whitespace-nowrap border-b border-slate-200 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Item</th>
              <th class="whitespace-nowrap border-b border-slate-200 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Batch / Asset</th>
              <th class="whitespace-nowrap border-b border-slate-200 px-4 py-3 text-center text-xs font-bold uppercase tracking-wide text-slate-600">Type</th>
              <th class="whitespace-nowrap border-b border-slate-200 px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-600">Qty</th>
              <th class="whitespace-nowrap border-b border-slate-200 px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-600">Unit Price</th>
              <th class="whitespace-nowrap border-b border-slate-200 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Reference</th>
              <th class="whitespace-nowrap border-b border-slate-200 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Department</th>
              <th class="whitespace-nowrap border-b border-slate-200 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Reason</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in rows" :key="item.id" class="transition-colors hover:bg-emerald-50/60" :class="index % 2 === 1 ? 'bg-slate-50/50' : 'bg-white'">
              <td class="whitespace-nowrap border-b border-slate-100 px-4 py-3 text-center text-slate-700">{{ formatDateTime(item) }}</td>
              <td class="border-b border-slate-100 px-4 py-3 font-semibold text-slate-800">{{ item.store_item?.item_name || '-' }}</td>
              <td class="border-b border-slate-100 px-4 py-3">
                <div class="font-medium text-slate-700">{{ item.inventory_lot?.batch_no || '-' }}</div>
                <div v-if="item.inventory_lot?.expiry_date" class="mt-1 text-xs text-slate-500">Exp: {{ String(item.inventory_lot.expiry_date).slice(0, 10) }}</div>
                <div v-if="item.inventory_lot?.serial_number || item.inventory_lot?.asset_tag" class="mt-1 text-xs text-slate-500">
                  {{ item.inventory_lot?.serial_number || '' }} {{ item.inventory_lot?.asset_tag || '' }}
                </div>
              </td>
              <td class="border-b border-slate-100 px-4 py-3 text-center">
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide" :class="item.movement_type === 'increase' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'">
                  <svg v-if="item.movement_type === 'increase'" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v9.586l3.293-3.293a1 1 0 111.414 1.414l-5 5a1 1 0 01-1.414 0l-5-5a1 1 0 111.414-1.414L9 13.586V4a1 1 0 011-1z" clip-rule="evenodd" transform="rotate(180 10 10)" /></svg>
                  <svg v-else class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v9.586l3.293-3.293a1 1 0 111.414 1.414l-5 5a1 1 0 01-1.414 0l-5-5a1 1 0 111.414-1.414L9 13.586V4a1 1 0 011-1z" clip-rule="evenodd" /></svg>
                  {{ item.movement_type }}
                </span>
              </td>
              <td class="whitespace-nowrap border-b border-slate-100 px-4 py-3 text-right font-semibold tabular-nums text-slate-800">{{ item.quantity }} <span class="text-xs font-normal text-slate-500">{{ item.store_item?.unit }}</span></td>
              <td class="whitespace-nowrap border-b border-slate-100 px-4 py-3 text-right tabular-nums text-slate-700">{{ Number(item.unit_price ?? 0).toFixed(2) }}</td>
              <td class="border-b border-slate-100 px-4 py-3 text-slate-700">{{ item.reference_no || '-' }}</td>
              <td class="border-b border-slate-100 px-4 py-3 text-slate-700">{{ item.department || '-' }}</td>
              <td class="border-b border-slate-100 px-4 py-3 text-slate-700">{{ item.reason }}</td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="9" class="px-4 py-14 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                  <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />
                  </svg>
                </div>
                <p class="mt-3 font-semibold text-slate-700">No movements yet</p>
                <p class="mt-1 text-sm text-slate-500">Stock adjustments will appear here after they are recorded.</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="props.adjustments?.links?.length" class="grid grid-cols-1 items-center gap-4 border-t border-slate-100 pt-4 mt-4 md:grid-cols-2">
        <p class="text-sm text-slate-500 text-center md:text-left">
          Displaying {{ props.adjustments?.from ?? 0 }} to {{ props.adjustments?.to ?? 0 }} of {{ props.adjustments?.total ?? 0 }} items
        </p>
        <nav>
          <ul class="flex flex-wrap items-center justify-center gap-1.5 md:justify-end">
            <li v-for="(link, index) in props.adjustments.links" :key="`${index}-${link.label}`">
              <a v-if="link.url" :href="link.url" class="inline-flex min-h-9 items-center justify-center rounded-lg border px-3 py-1.5 text-sm font-medium transition" :class="link.active ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'hover:bg-slate-50 border-slate-200 text-slate-700'">
                <span v-html="link.label"></span>
              </a>
              <span v-else class="inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-100 px-3 py-1.5 text-sm text-slate-300" v-html="link.label"></span>
            </li>
          </ul>
        </nav>
      </div>
    </div>
  </BackendLayout>
</template>
