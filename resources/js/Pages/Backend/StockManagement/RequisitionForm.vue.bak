<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';
import { displayWarning } from '@/responseMessage.js';

const props = defineProps({
  pageTitle: { type: String, default: 'Create Department Requisition' },
  items: { type: Array, default: () => [] },
});

const form = useForm({
  department: '',
  needed_date: '',
  notes: '',
  items: [
    { store_item_id: '', requested_qty: 1, remarks: '' },
  ],
});

const addRow = () => {
  form.items.push({ store_item_id: '', requested_qty: 1, remarks: '' });
};

const removeRow = (idx) => {
  if (form.items.length <= 1) return;
  form.items.splice(idx, 1);
};

const itemById = computed(() => {
  const map = new Map();
  props.items.forEach((item) => map.set(Number(item.id), item));
  return map;
});

const submit = () => {
  form.post(route('backend.stock.requisition.store'), {
    onError: (errorObject) => displayWarning(errorObject),
  });
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
        <a :href="route('backend.stock.requisitions')" class="px-3 py-2 text-sm font-semibold text-white bg-gray-600 rounded hover:bg-gray-700">Back Requisitions</a>
      </div>

      <form @submit.prevent="submit" class="mt-4 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div>
            <label class="block mb-1 text-sm font-semibold text-slate-700">Department</label>
            <input v-model="form.department" type="text" class="w-full p-2 text-sm border rounded" placeholder="OT / Ward / Lab / Admin" />
            <InputError class="mt-1" :message="form.errors.department" />
          </div>

          <div>
            <label class="block mb-1 text-sm font-semibold text-slate-700">Needed Date</label>
            <input v-model="form.needed_date" type="date" class="w-full p-2 text-sm border rounded" />
            <InputError class="mt-1" :message="form.errors.needed_date" />
          </div>

          <div>
            <label class="block mb-1 text-sm font-semibold text-slate-700">Note</label>
            <input v-model="form.notes" type="text" class="w-full p-2 text-sm border rounded" placeholder="Optional note" />
            <InputError class="mt-1" :message="form.errors.notes" />
          </div>
        </div>

        <div class="border rounded p-3">
          <div class="flex justify-between items-center mb-3">
            <h2 class="font-semibold text-slate-700">Requisition Items</h2>
            <button type="button" class="px-3 py-1 text-sm rounded bg-blue-600 text-white hover:bg-blue-700" @click="addRow">Add Row</button>
          </div>

          <div class="space-y-2">
            <div v-for="(row, idx) in form.items" :key="idx" class="grid grid-cols-1 md:grid-cols-12 gap-2 bg-slate-50 p-2 rounded border">
              <div class="md:col-span-5">
                <select v-model="row.store_item_id" class="w-full p-2 text-sm border rounded">
                  <option value="">Select Item</option>
                  <option v-for="item in items" :key="item.id" :value="item.id">
                    {{ item.item_name }} (Stock: {{ item.current_stock }} {{ item.unit }})
                  </option>
                </select>
              </div>

              <div class="md:col-span-2">
                <input v-model.number="row.requested_qty" type="number" min="0.01" step="0.01" class="w-full p-2 text-sm border rounded" placeholder="Qty" />
              </div>

              <div class="md:col-span-4">
                <input v-model="row.remarks" type="text" class="w-full p-2 text-sm border rounded" placeholder="Remarks" />
              </div>

              <div class="md:col-span-1 flex items-center">
                <button type="button" class="w-full p-2 text-xs text-white bg-rose-600 rounded hover:bg-rose-700" @click="removeRow(idx)">Remove</button>
              </div>

              <div class="md:col-span-12 text-xs text-slate-500" v-if="row.store_item_id && itemById.get(Number(row.store_item_id))">
                Available: {{ Number(itemById.get(Number(row.store_item_id)).current_stock ?? 0).toFixed(2) }} {{ itemById.get(Number(row.store_item_id)).unit }}
              </div>
            </div>
          </div>

          <InputError class="mt-2" :message="form.errors.items" />
        </div>

        <div class="flex justify-end">
          <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700" :disabled="form.processing">
            {{ form.processing ? 'Submitting...' : 'Submit Requisition' }}
          </button>
        </div>
      </form>
    </div>
  </BackendLayout>
</template>
