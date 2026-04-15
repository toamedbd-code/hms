<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';
import { displayWarning } from '@/responseMessage.js';

const props = defineProps({
  pageTitle: { type: String, default: 'Store Adjustment Entry' },
  items: { type: Array, default: () => [] },
});

const form = useForm({
  store_item_id: '',
  adjustment_type: 'increase',
  quantity: 1,
  unit_price: '',
  reason: '',
  movement_date: new Date().toISOString().split('T')[0],
  reference_no: '',
  department: '',
});

const selectedItem = computed(() => props.items.find((m) => Number(m.id) === Number(form.store_item_id)) || null);

const syncDefaultPrice = () => {
  if (!selectedItem.value) return;
  form.unit_price = Number(selectedItem.value.unit_cost ?? 0).toFixed(2);
};

const submit = () => {
  form.post(route('backend.stock.adjustment.store'), {
    onError: (errorObject) => displayWarning(errorObject),
  });
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
        <a :href="route('backend.stock.adjustments')" class="px-3 py-2 text-sm font-semibold text-white bg-gray-600 rounded hover:bg-gray-700">Back</a>
      </div>

      <form class="grid grid-cols-1 gap-3 mt-4 md:grid-cols-2" @submit.prevent="submit">
        <div class="md:col-span-2">
          <label class="block mb-1 text-sm font-semibold text-slate-700">Store Item</label>
          <select v-model="form.store_item_id" class="w-full p-2 text-sm border rounded" @change="syncDefaultPrice">
            <option value="">Select item</option>
            <option v-for="item in items" :key="item.id" :value="item.id">{{ item.item_name }} (Stock: {{ item.current_stock }})</option>
          </select>
          <InputError class="mt-1" :message="form.errors.store_item_id" />
        </div>

        <div>
          <label class="block mb-1 text-sm font-semibold text-slate-700">Adjustment Type</label>
          <select v-model="form.adjustment_type" class="w-full p-2 text-sm border rounded">
            <option value="increase">Increase (Stock In)</option>
            <option value="decrease">Decrease (Stock Out)</option>
          </select>
          <InputError class="mt-1" :message="form.errors.adjustment_type" />
        </div>

        <div>
          <label class="block mb-1 text-sm font-semibold text-slate-700">Quantity</label>
          <input v-model.number="form.quantity" type="number" min="1" class="w-full p-2 text-sm border rounded" />
          <InputError class="mt-1" :message="form.errors.quantity" />
        </div>

        <div>
          <label class="block mb-1 text-sm font-semibold text-slate-700">Unit Price</label>
          <input v-model="form.unit_price" type="number" min="0" step="0.01" class="w-full p-2 text-sm border rounded" />
          <InputError class="mt-1" :message="form.errors.unit_price" />
        </div>

        <div>
          <label class="block mb-1 text-sm font-semibold text-slate-700">Movement Date</label>
          <input v-model="form.movement_date" type="date" class="w-full p-2 text-sm border rounded" />
          <InputError class="mt-1" :message="form.errors.movement_date" />
        </div>

        <div>
          <label class="block mb-1 text-sm font-semibold text-slate-700">Reference No</label>
          <input v-model="form.reference_no" type="text" class="w-full p-2 text-sm border rounded" placeholder="GRN/Issue/Transfer ref" />
          <InputError class="mt-1" :message="form.errors.reference_no" />
        </div>

        <div>
          <label class="block mb-1 text-sm font-semibold text-slate-700">Department (Optional)</label>
          <input v-model="form.department" type="text" class="w-full p-2 text-sm border rounded" placeholder="OT / Ward / Lab" />
          <InputError class="mt-1" :message="form.errors.department" />
        </div>

        <div class="md:col-span-2">
          <label class="block mb-1 text-sm font-semibold text-slate-700">Reason</label>
          <textarea v-model="form.reason" rows="3" class="w-full p-2 text-sm border rounded" placeholder="Why stock is being adjusted"></textarea>
          <InputError class="mt-1" :message="form.errors.reason" />
        </div>

        <div class="md:col-span-2 flex justify-end">
          <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700" :disabled="form.processing">
            {{ form.processing ? 'Saving...' : 'Save Adjustment' }}
          </button>
        </div>
      </form>
    </div>
  </BackendLayout>
</template>
