<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold">{{ title }}</h1>
    <p class="mt-4 text-gray-600">This is a placeholder page created automatically because the original route/page was missing.</p>
  </div>
</template>

<script setup>
import { usePage } from '@inertiajs/vue3'
const props = defineProps({ title: String })
</script>

<style scoped>
</style>
