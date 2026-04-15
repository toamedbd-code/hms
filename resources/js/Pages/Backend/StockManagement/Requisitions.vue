<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'Department Requisitions' },
  requisitions: { type: Object, default: () => ({ data: [] }) },
  filters: { type: Object, default: () => ({}) },
});

const status = ref(props.filters?.status ?? '');
const rows = computed(() => props.requisitions?.data ?? []);

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
  router.get(route('backend.stock.requisitions'), { status: status.value }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const decide = (id, action) => {
  router.post(route('backend.stock.requisition.decision', id), { action }, {
    preserveScroll: true,
  });
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-2 p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
        <div class="flex gap-2">
          <a :href="route('backend.stock.index')" class="px-3 py-2 text-sm font-semibold text-white bg-gray-600 rounded hover:bg-gray-700">Back Dashboard</a>
          <a :href="route('backend.stock.requisition.create')" class="px-3 py-2 text-sm font-semibold text-white bg-indigo-600 rounded hover:bg-indigo-700">Create Requisition</a>
        </div>
      </div>

      <div class="flex items-center gap-2 mt-3">
        <label class="text-sm text-slate-700">Status</label>
        <select v-model="status" class="p-2 text-sm border rounded" @change="applyFilter">
          <option value="">All</option>
          <option value="Pending">Pending</option>
          <option value="Approved">Approved</option>
          <option value="Rejected">Rejected</option>
        </select>
      </div>

      <div class="w-full mt-3 overflow-x-auto">
        <table class="w-full text-sm border border-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 border">Req No</th>
              <th class="px-3 py-2 border">Department</th>
              <th class="px-3 py-2 border">Needed Date</th>
              <th class="px-3 py-2 border">Items</th>
              <th class="px-3 py-2 border">Status</th>
              <th class="px-3 py-2 border">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id" class="hover:bg-gray-50 align-top">
              <td class="px-3 py-2 border text-center">{{ row.requisition_no }}</td>
              <td class="px-3 py-2 border">{{ row.department }}</td>
              <td class="px-3 py-2 border text-center">{{ formatDateTime(row.needed_date) }}</td>
              <td class="px-3 py-2 border">
                <ul class="space-y-1">
                  <li v-for="line in row.items" :key="line.id" class="text-xs">
                    {{ line.store_item?.item_name || '-' }} - Req: {{ Number(line.requested_qty).toFixed(2) }} / Issued: {{ Number(line.issued_qty).toFixed(2) }}
                  </li>
                </ul>
              </td>
              <td class="px-3 py-2 border text-center">
                <span class="px-2 py-1 text-xs rounded" :class="row.status === 'Approved' ? 'bg-emerald-100 text-emerald-700' : row.status === 'Rejected' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700'">
                  {{ row.status }}
                </span>
              </td>
              <td class="px-3 py-2 border text-center">
                <div v-if="row.status === 'Pending'" class="flex flex-wrap items-center justify-center gap-1">
                  <button type="button" class="px-2 py-1 text-xs text-white bg-emerald-600 rounded hover:bg-emerald-700" @click="decide(row.id, 'approve')">Approve</button>
                  <button type="button" class="px-2 py-1 text-xs text-white bg-rose-600 rounded hover:bg-rose-700" @click="decide(row.id, 'reject')">Reject</button>
                  <a :href="route('backend.stock.requisition.print', row.id)" class="px-2 py-1 text-xs text-white bg-sky-600 rounded hover:bg-sky-700">Print</a>
                  <a :href="route('backend.stock.requisition.issue-slip', row.id)" class="px-2 py-1 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">Issue Slip</a>
                </div>
                <div v-else class="flex items-center justify-center gap-1">
                  <span class="text-xs text-slate-500">Processed</span>
                  <a :href="route('backend.stock.requisition.print', row.id)" class="px-2 py-1 text-xs text-white bg-sky-600 rounded hover:bg-sky-700">Print</a>
                  <a :href="route('backend.stock.requisition.issue-slip', row.id)" class="px-2 py-1 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">Issue Slip</a>
                </div>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="6" class="px-3 py-6 text-center text-gray-500 border">No requisition found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="props.requisitions?.links?.length" class="grid grid-cols-1 gap-4 pt-3 my-2 md:grid-cols-2 items-center">
        <p class="text-sm text-gray-600 text-center md:text-left">
          Displaying {{ props.requisitions?.from ?? 0 }} to {{ props.requisitions?.to ?? 0 }} of {{ props.requisitions?.total ?? 0 }} rows
        </p>
        <nav>
          <ul class="flex items-center justify-center md:justify-end gap-2">
            <li v-for="(link, index) in props.requisitions.links" :key="`${index}-${link.label}`">
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
