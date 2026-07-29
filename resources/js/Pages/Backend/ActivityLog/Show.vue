<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
  pageTitle: {
    type: String,
    default: 'Activity Log Detail',
  },
  activityLog: {
    type: Object,
    default: () => ({}),
  },
});

const billingDetailEntries = computed(() => {
  const meta = props.activityLog?.meta || {};
  const source = meta.changes && typeof meta.changes === 'object' ? meta.changes : meta;
  const keys = ['bill_number', 'invoice_number', 'case_number', 'patient_id', 'total_amount', 'payable_amount', 'paid_amt', 'due_amount'];

  return keys
    .filter((key) => source[key] !== undefined && source[key] !== null && source[key] !== '')
    .map((key) => ({
      key,
      label: key.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase()),
      value: typeof source[key] === 'number' ? source[key].toLocaleString() : source[key],
    }));
});

const billingItemChangeEntries = computed(() => {
  const meta = props.activityLog?.meta || {};
  const source = meta.changes && typeof meta.changes === 'object' ? meta.changes : meta;
  const itemChanges = Array.isArray(source.item_changes) ? source.item_changes : [];

  return itemChanges.map((item) => ({
    ...item,
    old_amount: typeof item.old_amount === 'number' ? item.old_amount.toLocaleString() : item.old_amount,
    new_amount: typeof item.new_amount === 'number' ? item.new_amount.toLocaleString() : item.new_amount,
    delta_amount: typeof item.delta_amount === 'number' ? item.delta_amount.toLocaleString() : item.delta_amount,
  }));
});

const formatMetaLabel = (key) => key.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const goBack = () => {
  if (typeof window !== 'undefined' && window.history.length > 1) {
    window.history.back();
    return;
  }

  router.visit(route('backend.activity-logs.index'));
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-2 p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
        <button
          type="button"
          @click="goBack"
          class="px-3 py-2 text-sm font-medium text-white bg-red-600 rounded hover:bg-red-700"
        >
          Back
        </button>
      </div>

      <div class="grid grid-cols-1 gap-3 mt-4 md:grid-cols-2 text-sm">
        <div><span class="font-semibold">Date Time:</span> {{ activityLog.created_at_local || activityLog.created_at }}</div>
        <div><span class="font-semibold">User:</span> {{ activityLog.user_name || 'System' }}</div>
        <div><span class="font-semibold">Module:</span> {{ activityLog.module }}</div>
        <div><span class="font-semibold">Action:</span> {{ activityLog.action }}</div>
        <div>
          <span class="font-semibold">Status:</span>
          <span class="px-2 py-1 text-xs rounded uppercase" :class="activityLog.status === 'failed' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'">
            {{ activityLog.status }}
          </span>
        </div>
        <div><span class="font-semibold">Login Duration:</span> {{ activityLog.meta?.session_duration_human || '-' }}</div>
        <div><span class="font-semibold">IP:</span> {{ activityLog.ip_address || '-' }}</div>
        <div class="md:col-span-2"><span class="font-semibold">Description:</span> {{ activityLog.description || '-' }}</div>
        <div class="md:col-span-2"><span class="font-semibold">User Agent:</span> {{ activityLog.user_agent || '-' }}</div>
      </div>

      <div v-if="billingDetailEntries.length || billingItemChangeEntries.length" class="mt-4">
        <h2 class="font-semibold text-gray-800 mb-2">Billing Details</h2>
        <div v-if="billingDetailEntries.length" class="grid grid-cols-1 gap-2 md:grid-cols-2">
          <div v-for="entry in billingDetailEntries" :key="entry.key" class="p-3 border border-gray-200 rounded bg-gray-50">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ entry.label }}</div>
            <div class="mt-1 text-sm text-gray-800">{{ entry.value }}</div>
          </div>
        </div>

        <div v-if="billingItemChangeEntries.length" class="mt-3">
          <h3 class="mb-2 text-sm font-semibold text-gray-700">Item Changes</h3>
          <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
            <div v-for="item in billingItemChangeEntries" :key="item.item_name + '-' + item.change_type" class="p-3 border border-gray-200 rounded bg-gray-50">
              <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ item.item_name }}</div>
              <div class="mt-1 text-sm text-gray-800">
                <span class="font-medium">{{ item.change_type }}</span>
                · old: {{ item.old_amount }} · new: {{ item.new_amount }} · delta: {{ item.delta_amount }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-4">
        <h2 class="font-semibold text-gray-800 mb-2">Meta Data</h2>
        <pre class="p-3 text-xs text-gray-700 bg-gray-50 border border-gray-200 rounded overflow-x-auto">{{ JSON.stringify(activityLog.meta || {}, null, 2) }}</pre>
      </div>
    </div>
  </BackendLayout>
</template>
