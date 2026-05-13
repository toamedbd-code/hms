<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
  <Head title="Journal Entries" />

  <div class="p-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Journal Entries</h1>
      <Link :href="route('backend.journal-entry.create')" class="btn-colorful">Create Entry</Link>
    </div>

    <div class="mt-6 rounded border bg-white p-4 shadow-sm">
      <p class="text-sm text-gray-600">Journal Entries module is installed. Use the API or Create page to add entries.</p>
      <div class="mt-4 text-sm text-gray-500">To fetch entries via API: GET <code>/journal-entry</code> (Accept: application/json)</div>
    </div>
  </div>
</template>

<style scoped>
/* minimal styling left intentionally blank */
</style>
