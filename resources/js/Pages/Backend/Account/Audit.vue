<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 bg-white rounded-md dark:bg-slate-900">
      <div class="flex items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-bold">{{ $page.props.pageTitle }}</h1>
          <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">Audit log placeholder. Show ledger/audit trails here.</p>
        </div>
        <Link :href="route('backend.dashboard')" class="px-3 py-2 text-sm text-white bg-red-600 rounded hover:bg-red-700">Back</Link>
      </div>
    </div>
  </BackendLayout>
</template>
