<script setup>
import { useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';
import { displayWarning } from '@/responseMessage.js';

const props = defineProps({
  pageTitle: { type: String, default: 'Store Item Setup' },
});

const form = useForm({
  item_code: '',
  item_name: '',
  category: '',
  unit: 'pcs',
  reorder_level: 10,
  current_stock: 0,
  unit_cost: 0,
  status: 'Active',
  notes: '',
});

const submit = () => {
  form.post(route('backend.stock.item.store'), {
    onError: (errorObject) => displayWarning(errorObject),
  });
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
        <a :href="route('backend.stock.index')" class="px-3 py-2 text-sm font-semibold text-white bg-gray-600 rounded hover:bg-gray-700">Back Dashboard</a>
      </div>

      <form class="grid grid-cols-1 gap-3 mt-4 md:grid-cols-2" @submit.prevent="submit">
        <div>
          <label class="block mb-1 text-sm font-semibold text-slate-700">Item Code (Optional)</label>
          <input v-model="form.item_code" type="text" class="w-full p-2 text-sm border rounded" placeholder="Auto-generated if empty" />
          <InputError class="mt-1" :message="form.errors.item_code" />
        </div>

        <div>
          <label class="block mb-1 text-sm font-semibold text-slate-700">Item Name</label>
          <input v-model="form.item_name" type="text" class="w-full p-2 text-sm border rounded" placeholder="Eg: Surgical Gloves" />
          <InputError class="mt-1" :message="form.errors.item_name" />
        </div>

        <div>
          <label class="block mb-1 text-sm font-semibold text-slate-700">Category</label>
          <input v-model="form.category" type="text" class="w-full p-2 text-sm border rounded" placeholder="Consumable / OT / Lab / Admin" />
          <InputError class="mt-1" :message="form.errors.category" />
        </div>

        <div>
          <label class="block mb-1 text-sm font-semibold text-slate-700">Unit</label>
          <input v-model="form.unit" type="text" class="w-full p-2 text-sm border rounded" placeholder="pcs / box / bottle / meter" />
          <InputError class="mt-1" :message="form.errors.unit" />
        </div>

        <div>
          <label class="block mb-1 text-sm font-semibold text-slate-700">Opening Stock</label>
          <input v-model.number="form.current_stock" type="number" min="0" step="0.01" class="w-full p-2 text-sm border rounded" />
          <InputError class="mt-1" :message="form.errors.current_stock" />
        </div>

        <div>
          <label class="block mb-1 text-sm font-semibold text-slate-700">Unit Cost</label>
          <input v-model.number="form.unit_cost" type="number" min="0" step="0.01" class="w-full p-2 text-sm border rounded" />
          <InputError class="mt-1" :message="form.errors.unit_cost" />
        </div>

        <div>
          <label class="block mb-1 text-sm font-semibold text-slate-700">Reorder Level</label>
          <input v-model.number="form.reorder_level" type="number" min="0" step="0.01" class="w-full p-2 text-sm border rounded" />
          <InputError class="mt-1" :message="form.errors.reorder_level" />
        </div>

        <div>
          <label class="block mb-1 text-sm font-semibold text-slate-700">Status</label>
          <select v-model="form.status" class="w-full p-2 text-sm border rounded">
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
          </select>
          <InputError class="mt-1" :message="form.errors.status" />
        </div>

        <div class="md:col-span-2">
          <label class="block mb-1 text-sm font-semibold text-slate-700">Notes</label>
          <textarea v-model="form.notes" rows="3" class="w-full p-2 text-sm border rounded" placeholder="Any additional details"></textarea>
          <InputError class="mt-1" :message="form.errors.notes" />
        </div>

        <div class="md:col-span-2 flex justify-end">
          <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded hover:bg-indigo-700" :disabled="form.processing">
            {{ form.processing ? 'Saving...' : 'Save Store Item' }}
          </button>
        </div>
      </form>
    </div>
  </BackendLayout>
</template>
