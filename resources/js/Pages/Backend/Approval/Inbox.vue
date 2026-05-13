<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  datas: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
  quickFilters: {
    type: Array,
    default: () => ([]),
  },
});

const localFilters = reactive({
  view: props.filters.view ?? '',
  q: props.filters.q ?? '',
  status: props.filters.status ?? '',
  module: props.filters.module ?? '',
  numOfData: props.filters.numOfData ?? 20,
});

const hoveredQuickKey = ref(null);
const pinnedQuickKey = ref(null);
const quickFilterContainer = ref(null);

const showBreakdown = (key) => {
  if (pinnedQuickKey.value) {
    return;
  }

  hoveredQuickKey.value = key;
};

const hideBreakdown = () => {
  if (pinnedQuickKey.value) {
    return;
  }

  hoveredQuickKey.value = null;
};

const togglePinnedBreakdown = (key) => {
  if (pinnedQuickKey.value === key) {
    pinnedQuickKey.value = null;
    hoveredQuickKey.value = null;
    return;
  }

  pinnedQuickKey.value = key;
  hoveredQuickKey.value = key;
};

const closePinnedBreakdown = () => {
  pinnedQuickKey.value = null;
  hoveredQuickKey.value = null;
};

const handleGlobalKeydown = (event) => {
  if (event.key === 'Escape' && pinnedQuickKey.value) {
    closePinnedBreakdown();
  }
};

const handleGlobalPointerDown = (event) => {
  if (!pinnedQuickKey.value) {
    return;
  }

  const root = quickFilterContainer.value;
  const target = event.target;

  if (!root || !(target instanceof Node)) {
    return;
  }

  if (!root.contains(target)) {
    closePinnedBreakdown();
  }
};

onMounted(() => {
  window.addEventListener('keydown', handleGlobalKeydown);
  document.addEventListener('pointerdown', handleGlobalPointerDown);
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleGlobalKeydown);
  document.removeEventListener('pointerdown', handleGlobalPointerDown);
});

const isBreakdownVisible = (key) => {
  return pinnedQuickKey.value === key || (!pinnedQuickKey.value && hoveredQuickKey.value === key);
};

const getBreakdownPercent = (count, total) => {
  const safeCount = Number(count) || 0;
  const safeTotal = Number(total) || 0;

  if (safeTotal <= 0) {
    return 0;
  }

  return Math.round((safeCount / safeTotal) * 100);
};

const setQuickFilter = (filter) => {
  localFilters.view = filter.view ?? '';
  localFilters.status = filter.status ?? '';
  applyFilter();
};

const applyFilter = () => {
  router.get(route('backend.approval.requests.index'), localFilters, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilter = () => {
  localFilters.view = '';
  localFilters.q = '';
  localFilters.status = '';
  localFilters.module = '';
  localFilters.numOfData = 20;
  applyFilter();
};

const approveRequest = (item) => {
  if (!item?.can_approve) {
    return;
  }

  const comment = window.prompt('Optional approval comment', '') ?? '';
  router.post(
    route('backend.approval.requests.approve', item.id),
    { comment },
    { preserveScroll: true }
  );
};

const rejectRequest = (item) => {
  if (!item?.can_approve) {
    return;
  }

  const comment = window.prompt('Rejection reason (optional)', '') ?? '';
  router.post(
    route('backend.approval.requests.reject', item.id),
    { comment },
    { preserveScroll: true }
  );
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 bg-white rounded-md shadow">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h1 class="text-xl font-bold text-gray-800">{{ $page.props.pageTitle ?? 'Approval Inbox' }}</h1>
        <div class="text-sm text-gray-500">
          Showing {{ props.datas?.data?.length ?? 0 }} of {{ props.datas?.total ?? 0 }} requests
        </div>
      </div>

      <div class="grid grid-cols-1 gap-2 p-3 mb-4 rounded-md md:grid-cols-5 bg-slate-100">
        <input
          v-model="localFilters.q"
          type="text"
          class="w-full p-2 text-sm bg-white border rounded"
          placeholder="Search by entity type or id"
          @input="applyFilter"
        />

        <input
          v-model="localFilters.module"
          type="text"
          class="w-full p-2 text-sm bg-white border rounded"
          placeholder="Module (e.g. purchase)"
          @input="applyFilter"
        />

        <select v-model="localFilters.status" class="w-full p-2 text-sm bg-white border rounded" @change="applyFilter">
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
        </select>

        <select v-model="localFilters.numOfData" class="w-full p-2 text-sm bg-white border rounded" @change="applyFilter">
          <option :value="10">Show 10</option>
          <option :value="20">Show 20</option>
          <option :value="50">Show 50</option>
          <option :value="100">Show 100</option>
        </select>

        <button class="px-3 py-2 text-sm text-white bg-gray-700 rounded hover:bg-gray-800" @click="resetFilter">
          Reset
        </button>
      </div>

      <div ref="quickFilterContainer" class="flex flex-wrap items-center gap-2 mb-4">
        <button
          v-for="quick in props.quickFilters"
          :key="quick.key"
          class="relative inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold rounded border"
          :class="
            localFilters.view === (quick.view ?? '') && localFilters.status === (quick.status ?? '')
              ? 'bg-blue-600 text-white border-blue-600'
              : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100'
          "
          @mouseenter="showBreakdown(quick.key)"
          @mouseleave="hideBreakdown"
          @focusin="showBreakdown(quick.key)"
          @focusout="hideBreakdown"
          @click="setQuickFilter(quick)"
        >
          {{ quick.label }}
          <span
            class="inline-flex min-w-5 justify-center rounded-full px-1.5 py-0.5 text-[10px] cursor-pointer"
            :class="
              localFilters.view === (quick.view ?? '') && localFilters.status === (quick.status ?? '')
                ? 'bg-white/25 text-white'
                : 'bg-slate-200 text-slate-700'
            "
            :title="quick.branch_breakdown_text || 'No branch data'"
            @click.stop="togglePinnedBreakdown(quick.key)"
          >
            {{ quick.count ?? 0 }}
          </span>

          <div
            v-if="isBreakdownVisible(quick.key) && quick.branch_breakdown?.length"
            class="absolute left-0 z-20 w-64 p-2 mt-2 text-xs text-left text-slate-700 bg-white border rounded-md shadow-lg top-full"
            @click.stop
          >
            <div class="flex items-center justify-between mb-1">
              <div class="font-semibold text-slate-800">Branch Breakdown</div>
              <button
                v-if="pinnedQuickKey === quick.key"
                type="button"
                class="px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 border border-slate-300 rounded hover:bg-slate-100"
                @click="closePinnedBreakdown"
              >
                Close
              </button>
            </div>
            <div class="space-y-1">
              <div v-for="branch in quick.branch_breakdown" :key="`${quick.key}-${branch.branch_id ?? 'global'}`" class="flex items-center justify-between gap-2">
                <span class="truncate">{{ branch.branch_name }}</span>
                <span class="text-slate-500">{{ branch.count }} ({{ getBreakdownPercent(branch.count, quick.count) }}%)</span>
              </div>
            </div>
          </div>
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm border border-collapse">
          <thead class="bg-slate-100">
            <tr>
              <th class="p-2 text-left border">#</th>
              <th class="p-2 text-left border">Module</th>
              <th class="p-2 text-left border">Entity</th>
              <th class="p-2 text-left border">Current Step</th>
              <th class="p-2 text-left border">Status</th>
              <th class="p-2 text-left border">Requested</th>
              <th class="p-2 text-left border">Latest Action</th>
              <th class="p-2 text-left border">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in props.datas.data" :key="item.id" class="hover:bg-slate-50">
              <td class="p-2 border">{{ index + 1 }}</td>
              <td class="p-2 border">{{ item.module }}</td>
              <td class="p-2 border">{{ item.entity_type }} #{{ item.entity_id }}</td>
              <td class="p-2 border">{{ item.current_step_no ?? '-' }}</td>
              <td class="p-2 border">
                <span
                  class="inline-flex px-2 py-0.5 rounded text-xs font-semibold"
                  :class="{
                    'bg-amber-100 text-amber-700': item.status === 'pending',
                    'bg-emerald-100 text-emerald-700': item.status === 'approved',
                    'bg-red-100 text-red-700': item.status === 'rejected',
                  }"
                >
                  {{ item.status }}
                </span>
              </td>
              <td class="p-2 border">{{ item.requested_at ?? '-' }}</td>
              <td class="p-2 border">
                <span v-if="item.latest_action">
                  {{ item.latest_action.action }} (Step {{ item.latest_action.step_no }})
                </span>
                <span v-else>-</span>
              </td>
              <td class="p-2 border">
                <div class="flex items-center gap-2">
                  <button
                    v-if="item.detail_url"
                    class="px-2 py-1 text-xs text-white bg-slate-600 rounded hover:bg-slate-700"
                    @click="router.visit(item.detail_url)"
                  >
                    Open
                  </button>
                  <button
                    class="px-2 py-1 text-xs text-white bg-emerald-600 rounded hover:bg-emerald-700 disabled:opacity-50"
                    :disabled="!item.can_approve || item.status !== 'pending'"
                    @click="approveRequest(item)"
                  >
                    Approve
                  </button>
                  <button
                    class="px-2 py-1 text-xs text-white bg-red-600 rounded hover:bg-red-700 disabled:opacity-50"
                    :disabled="!item.can_approve || item.status !== 'pending'"
                    @click="rejectRequest(item)"
                  >
                    Reject
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!props.datas?.data?.length">
              <td colspan="8" class="p-4 text-center text-gray-500 border">No approval requests found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-3">
        <Pagination />
      </div>
    </div>
  </BackendLayout>
</template>
