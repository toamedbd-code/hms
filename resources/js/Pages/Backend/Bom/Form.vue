<script setup>
import { ref } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps(['bom','id']);

const form = useForm({
  name: props.bom?.name ?? '',
  product_id: props.bom?.product_id ?? null,
  quantity: props.bom?.quantity ?? 1,
  unit_id: props.bom?.unit_id ?? null,
  notes: props.bom?.notes ?? '',
  items: props.bom?.items ?? [],
  _method: props.id ? 'put' : 'post',
});

const submit = () => {
  const routeName = props.id ? route('backend.bom.update', props.id) : route('backend.bom.store');
  form.post(routeName, {
    preserveState: true,
    onSuccess: () => {
      if (!props.id) form.reset();
    }
  });
};
</script>

<template>
  <BackendLayout>
    <div class="p-4 bg-white rounded shadow">
      <h1 class="text-lg font-bold">{{ props.id ? 'Edit BOM' : 'Create BOM' }}</h1>

      <form @submit.prevent="submit" class="mt-4">
        <div class="grid grid-cols-1 gap-3">
          <div>
            <label class="block">Name</label>
            <input v-model="form.name" class="w-full p-2 border rounded" />
          </div>

          <div>
            <label class="block">Quantity</label>
            <input type="number" v-model="form.quantity" class="w-full p-2 border rounded" />
          </div>

          <div>
            <label class="block">Notes</label>
            <textarea v-model="form.notes" class="w-full p-2 border rounded"></textarea>
          </div>

          <div class="flex justify-end">
            <button type="submit" class="btn-colorful">{{ props.id ? 'Update' : 'Create' }}</button>
          </div>
        </div>
      </form>
    </div>
  </BackendLayout>
</template>
