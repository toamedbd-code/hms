<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { useForm } from '@inertiajs/vue3';
const form = useForm({ production_order_id: null, operation: '', planned_start: null, planned_end: null });

const submit = () => {
  form.post(route('backend.work-order.store'));
};
</script>

<template>
  <BackendLayout>
    <div class="p-4 bg-white rounded shadow">
      <h1 class="text-lg font-bold">Create Work Order</h1>
      <form @submit.prevent="submit" class="mt-4">
        <div>
          <label>Production Order ID</label>
          <input v-model="form.production_order_id" class="w-full p-2 border rounded" />
        </div>
        <div class="mt-2">
          <label>Operation</label>
          <input v-model="form.operation" class="w-full p-2 border rounded" />
        </div>
        <div class="mt-4">
          <button class="btn-colorful" type="submit">Create</button>
        </div>
      </form>
    </div>
  </BackendLayout>
</template>
