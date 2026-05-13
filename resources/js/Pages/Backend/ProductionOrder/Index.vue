<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
const props = defineProps(['datas']);
</script>

<template>
  <BackendLayout>
    <div class="p-4 bg-white rounded shadow">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold">Production Orders</h1>
        <a :href="route('backend.production-order.create')" class="btn-colorful">Create</a>
      </div>

      <table class="w-full mt-4 table-auto">
        <thead>
          <tr>
            <th class="border p-2">#</th>
            <th class="border p-2">Reference</th>
            <th class="border p-2">BOM</th>
            <th class="border p-2">Planned</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(p, idx) in props.datas.data" :key="p.id">
            <td class="border p-2">{{ idx + 1 }}</td>
            <td class="border p-2">{{ p.reference }}</td>
            <td class="border p-2">{{ p.bom?.name }}</td>
            <td class="border p-2">{{ p.qty_planned }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </BackendLayout>
</template>
