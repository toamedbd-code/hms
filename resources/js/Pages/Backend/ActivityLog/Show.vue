<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { Link } from '@inertiajs/vue3';

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
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-2 p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
        <Link :href="route('backend.activity-logs.index')" class="px-3 py-2 text-sm text-white bg-gray-600 rounded hover:bg-gray-700">
          Back
        </Link>
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

      <div class="mt-4">
        <h2 class="font-semibold text-gray-800 mb-2">Meta Data</h2>
        <pre class="p-3 text-xs text-gray-700 bg-gray-50 border border-gray-200 rounded overflow-x-auto">{{ JSON.stringify(activityLog.meta || {}, null, 2) }}</pre>
      </div>
    </div>
  </BackendLayout>
</template>
