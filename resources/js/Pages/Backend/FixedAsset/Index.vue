<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
const props = defineProps(['datas']);
</script>

<template>
  <BackendLayout>
    <div class="p-4 bg-white rounded shadow">
        <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold">Fixed Assets</h1>
        <a :href="route('backend.fixedasset.create')" class="btn-colorful">Create</a>
      </div>

      <table class="w-full mt-4 table-auto">
        <thead>
          <tr>
            <th class="border p-2">#</th>
            <th class="border p-2">Name</th>
            <th class="border p-2">Cost</th>
            <th class="border p-2">Net Book Value</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(a, idx) in props.datas.data" :key="a.id">
            <td class="border p-2">{{ idx + 1 }}</td>
            <td class="border p-2">{{ a.name }}</td>
            <td class="border p-2">{{ a.cost }}</td>
            <td class="border p-2">{{ a.net_book_value }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </BackendLayout>
</template>
