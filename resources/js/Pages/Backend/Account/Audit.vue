<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 bg-white rounded-md dark:bg-slate-900">
      <h1 class="text-xl font-bold">{{ $page.props.pageTitle }}</h1>
      <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">Audit log placeholder. Show ledger/audit trails here.</p>
    </div>
  </BackendLayout>
</template>
