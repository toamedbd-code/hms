<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps(['datas']);

const handleCreate = () => {
  router.get(route('backend.bom.create'));
};

const handleDelete = (id) => {
  if (!confirm('Delete?')) return;
  router.delete(route('backend.bom.destroy', id));
};
</script>

<template>
  <BackendLayout>
    <div class="p-4 bg-white rounded shadow">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold">BOMs</h1>
        <button class="btn-colorful" @click="handleCreate">Create BOM</button>
      </div>

      <table class="w-full mt-4 table-auto">
        <thead>
          <tr>
            <th class="border p-2">#</th>
            <th class="border p-2">Name</th>
            <th class="border p-2">Quantity</th>
            <th class="border p-2">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(b, idx) in props.datas.data" :key="b.id">
            <td class="border p-2">{{ idx + 1 }}</td>
            <td class="border p-2">{{ b.name }}</td>
            <td class="border p-2">{{ b.quantity }}</td>
            <td class="border p-2">
              <a :href="route('backend.bom.edit', b.id)" class="mr-2 text-blue-600">Edit</a>
              <button type="button" @click="handleDelete(b.id)" class="text-red-600">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div class="mt-4">
        <!-- basic pagination links if available -->
        <div v-if="props.datas.meta">
          <span>Page {{ props.datas.meta.current_page }} of {{ props.datas.meta.last_page }}</span>
        </div>
      </div>
    </div>
  </BackendLayout>
</template>
