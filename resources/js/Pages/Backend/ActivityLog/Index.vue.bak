<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const props = defineProps({
  pageTitle: {
    type: String,
    default: 'Activity Logs',
  },
  activityLogs: {
    type: Object,
    default: () => ({ data: [] }),
  },
  modules: {
    type: Array,
    default: () => [],
  },
  actions: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
});

const filterForm = ref({
  module: props.filters?.module ?? '',
  action: props.filters?.action ?? '',
  status: props.filters?.status ?? '',
  date_from: props.filters?.date_from ?? '',
  date_to: props.filters?.date_to ?? '',
  search: props.filters?.search ?? '',
  per_page: Number(props.activityLogs?.per_page ?? 25),
});

const rows = computed(() => props.activityLogs?.data ?? []);

const applyFilter = () => {
  router.get(route('backend.activity-logs.index'), filterForm.value, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilter = () => {
  filterForm.value = {
    module: '',
    action: '',
    status: '',
    date_from: '',
    date_to: '',
    search: '',
    per_page: 25,
  };
  applyFilter();
};

const printUrl = computed(() => route('backend.activity-logs.print', filterForm.value));
const exportUrl = computed(() => route('backend.activity-logs.export', filterForm.value));
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-2 p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
        <div class="flex items-center gap-2">
          <a :href="printUrl" target="_blank" rel="noopener noreferrer" class="px-3 py-2 text-sm text-white bg-slate-700 rounded hover:bg-slate-800">
            Print
          </a>
          <a :href="exportUrl" class="px-3 py-2 text-sm text-white bg-emerald-600 rounded hover:bg-emerald-700">
            Export CSV
          </a>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-2 p-3 mt-3 bg-slate-100 rounded md:grid-cols-8">
        <div>
          <input v-model="filterForm.search" type="text" class="w-full p-2 text-sm border border-gray-300 rounded" placeholder="Search description" @keyup.enter="applyFilter" />
        </div>
        <div>
          <select v-model="filterForm.module" class="w-full p-2 text-sm border border-gray-300 rounded" @change="applyFilter">
            <option value="">All Module</option>
            <option v-for="moduleName in modules" :key="moduleName" :value="moduleName">{{ moduleName }}</option>
          </select>
        </div>
        <div>
          <select v-model="filterForm.action" class="w-full p-2 text-sm border border-gray-300 rounded" @change="applyFilter">
            <option value="">All Action</option>
            <option v-for="actionName in actions" :key="actionName" :value="actionName">{{ actionName }}</option>
          </select>
        </div>
        <div>
          <select v-model="filterForm.status" class="w-full p-2 text-sm border border-gray-300 rounded" @change="applyFilter">
            <option value="">All Status</option>
            <option value="success">Success</option>
            <option value="failed">Failed</option>
          </select>
        </div>
        <div>
          <input v-model="filterForm.date_from" type="date" class="w-full p-2 text-sm border border-gray-300 rounded" @change="applyFilter" />
        </div>
        <div>
          <input v-model="filterForm.date_to" type="date" class="w-full p-2 text-sm border border-gray-300 rounded" @change="applyFilter" />
        </div>
        <div>
          <select v-model="filterForm.per_page" class="w-full p-2 text-sm border border-gray-300 rounded" @change="applyFilter">
            <option :value="10">Show 10</option>
            <option :value="25">Show 25</option>
            <option :value="50">Show 50</option>
            <option :value="100">Show 100</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" class="w-full px-2 py-2 text-sm text-white bg-blue-600 rounded hover:bg-blue-700" @click="applyFilter">Filter</button>
          <button type="button" class="w-full px-2 py-2 text-sm text-white bg-gray-500 rounded hover:bg-gray-600" @click="resetFilter">Reset</button>
        </div>
      </div>

      <div class="w-full mt-4 overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700 border border-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 border">Date Time</th>
              <th class="px-3 py-2 border">User</th>
              <th class="px-3 py-2 border">Module</th>
              <th class="px-3 py-2 border">Action</th>
              <th class="px-3 py-2 border">Description</th>
              <th class="px-3 py-2 border">Login Duration</th>
              <th class="px-3 py-2 border">Status</th>
              <th class="px-3 py-2 border">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in rows" :key="item.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 border">{{ item.created_at_local || item.created_at }}</td>
              <td class="px-3 py-2 border">{{ item.user_name || 'System' }}</td>
              <td class="px-3 py-2 border">{{ item.module }}</td>
              <td class="px-3 py-2 border">{{ item.action }}</td>
              <td class="px-3 py-2 border">{{ item.description || '-' }}</td>
              <td class="px-3 py-2 border">{{ item.meta?.session_duration_human || '-' }}</td>
              <td class="px-3 py-2 border">
                <span class="px-2 py-1 text-xs rounded uppercase" :class="item.status === 'failed' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'">
                  {{ item.status }}
                </span>
              </td>
              <td class="px-3 py-2 border">
                <Link :href="route('backend.activity-logs.show', item.id)" class="px-2 py-1 text-xs text-white bg-blue-600 rounded hover:bg-blue-700">
                  View
                </Link>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="8" class="px-3 py-6 text-center text-gray-500 border">No activity logs found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="props.activityLogs?.links?.length" class="grid grid-cols-1 gap-4 pt-2 my-4 md:grid-cols-2 items-center">
        <p class="text-sm text-gray-600 text-center md:text-left">
          Displaying {{ props.activityLogs?.from ?? 0 }} to {{ props.activityLogs?.to ?? 0 }} of {{ props.activityLogs?.total ?? 0 }} items
        </p>

        <nav>
          <ul class="flex items-center justify-center md:justify-end gap-2">
            <li v-for="(link, index) in props.activityLogs.links" :key="`${index}-${link.label}`">
              <Link
                v-if="link.url"
                :href="link.url"
                class="px-3 py-2 text-sm border border-gray-300 rounded hover:bg-gray-100"
                :class="{ 'bg-indigo-600 text-white border-indigo-600 hover:bg-indigo-700': link.active }"
              >
                <span v-html="link.label"></span>
              </Link>
              <span v-else class="px-3 py-2 text-sm text-gray-400 border border-gray-200 rounded" v-html="link.label"></span>
            </li>
          </ul>
        </nav>
      </div>
    </div>
  </BackendLayout>
</template>
