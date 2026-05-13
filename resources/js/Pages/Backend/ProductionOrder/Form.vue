<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { useForm } from '@inertiajs/vue3';
const form = useForm({ bom_id: null, qty_planned: 1, scheduled_at: null });

const submit = () => {
  form.post(route('backend.production-order.store'));
};
</script>

<template>
  <BackendLayout>
    <div class="p-4 bg-white rounded shadow">
      <h1 class="text-lg font-bold">Create Production Order</h1>
      <form @submit.prevent="submit" class="mt-4">
        <div>
          <label>BOM ID</label>
          <input v-model="form.bom_id" class="w-full p-2 border rounded" />
        </div>
        <div class="mt-2">
          <label>Quantity</label>
          <input type="number" v-model="form.qty_planned" class="w-full p-2 border rounded" />
        </div>
        <div class="mt-4">
          <button class="btn-colorful" type="submit">Create</button>
        </div>
      </form>
    </div>
  </BackendLayout>
</template>
