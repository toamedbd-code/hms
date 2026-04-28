<script setup>
import { Head, Link } from '@inertiajs/vue3';
const props = defineProps({ entry: Object });
</script>

<template>
  <Head title="Journal Entry Details" />

  <div class="p-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Journal Entry Details</h1>
      <Link :href="route('journal-entry.index')" class="text-sm text-gray-600">Back</Link>
    </div>

    <div class="mt-6 rounded border bg-white p-4 shadow-sm">
      <pre class="text-sm">{{ entry }}</pre>
    </div>
  </div>
</template>
