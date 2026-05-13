<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { useForm } from '@inertiajs/vue3';
const props = defineProps(['asset','id']);

const form = useForm({
  asset_tag: props.asset?.asset_tag ?? '',
  name: props.asset?.name ?? '',
  purchase_date: props.asset?.purchase_date ?? null,
  cost: props.asset?.cost ?? 0,
  salvage_value: props.asset?.salvage_value ?? 0,
  useful_life_months: props.asset?.useful_life_months ?? null,
  _method: props.id ? 'put' : 'post',
});

const submit = () => {
  const routeName = props.id ? route('backend.fixedasset.update', props.id) : route('backend.fixedasset.store');
  form.post(routeName);
};
</script>

<template>
  <BackendLayout>
    <div class="p-4 bg-white rounded shadow">
      <h1 class="text-lg font-bold">{{ props.id ? 'Edit' : 'Create' }} Fixed Asset</h1>
      <form @submit.prevent="submit" class="mt-4">
        <div>
          <label>Name</label>
          <input v-model="form.name" class="w-full p-2 border rounded" />
        </div>
        <div class="mt-2">
          <label>Cost</label>
          <input type="number" v-model="form.cost" class="w-full p-2 border rounded" />
        </div>
        <div class="mt-4">
          <button class="btn-colorful" type="submit">{{ props.id ? 'Update' : 'Create' }}</button>
        </div>
      </form>
    </div>
  </BackendLayout>
</template>
