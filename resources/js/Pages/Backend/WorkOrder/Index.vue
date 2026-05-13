<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
const props = defineProps(['datas']);
</script>

<template>
  <BackendLayout>
    <div class="p-4 bg-white rounded shadow">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold">Work Orders</h1>
        <a :href="route('backend.work-order.create')" class="btn-colorful">Create</a>
      </div>

      <table class="w-full mt-4 table-auto">
        <thead>
          <tr>
            <th class="border p-2">#</th>
            <th class="border p-2">Operation</th>
            <th class="border p-2">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(w, idx) in props.datas.data" :key="w.id">
            <td class="border p-2">{{ idx + 1 }}</td>
            <td class="border p-2">{{ w.operation }}</td>
            <td class="border p-2">{{ w.status }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </BackendLayout>
</template>
