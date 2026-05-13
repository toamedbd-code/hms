<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { useForm } from '@inertiajs/vue3';
const props = defineProps(['currency','id']);

const form = useForm({ code: props.currency?.code ?? '', name: props.currency?.name ?? '', symbol: props.currency?.symbol ?? '', is_base: props.currency?.is_base ?? false, _method: props.id ? 'put' : 'post' });

const submit = () => {
  const routeName = props.id ? route('backend.currency.update', props.id) : route('backend.currency.store');
  form.post(routeName);
};
</script>

<template>
  <BackendLayout>
    <div class="p-4 bg-white rounded shadow">
      <h1 class="text-lg font-bold">{{ props.id ? 'Edit' : 'Create' }} Currency</h1>
      <form @submit.prevent="submit" class="mt-4">
        <div>
          <label>Code</label>
          <input v-model="form.code" class="w-full p-2 border rounded" />
        </div>
        <div class="mt-2">
          <label>Name</label>
          <input v-model="form.name" class="w-full p-2 border rounded" />
        </div>
        <div class="mt-2">
          <label>Symbol</label>
          <input v-model="form.symbol" class="w-full p-2 border rounded" />
        </div>
        <div class="mt-4">
          <button class="btn-colorful" type="submit">{{ props.id ? 'Update' : 'Create' }}</button>
        </div>
      </form>
    </div>
  </BackendLayout>
</template>
