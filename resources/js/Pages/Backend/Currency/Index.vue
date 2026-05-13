<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router } from '@inertiajs/vue3';
const props = defineProps(['datas']);

const deleteCurrency = (id) => {
  if (!confirm('Are you sure you want to delete this currency?')) return;
  router.delete(route('backend.currency.destroy', id));
};
</script>

<template>
  <BackendLayout>
    <div class="p-4 bg-white rounded shadow">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold">Currencies</h1>
        <a :href="route('backend.currency.create')" class="btn-colorful">Create</a>
      </div>

      <table class="w-full mt-4 table-auto">
        <thead>
          <tr>
            <th class="border p-2">#</th>
            <th class="border p-2">Code</th>
            <th class="border p-2">Name</th>
            <th class="border p-2">Symbol</th>
            <th class="border p-2">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(c, idx) in props.datas.data" :key="c.id">
            <td class="border p-2">{{ idx + 1 }}</td>
            <td class="border p-2">{{ c.code }}</td>
            <td class="border p-2">{{ c.name }}</td>
            <td class="border p-2">{{ c.symbol }}</td>
            <td class="border p-2">
              <a :href="route('backend.currency.edit', c.id)" class="btn btn-sm btn-secondary mr-2">Edit</a>
              <button @click.prevent="deleteCurrency(c.id)" class="btn btn-sm btn-danger">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </BackendLayout>
</template>
