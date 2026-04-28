<script setup>
import { useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';
import { displayWarning } from '@/responseMessage.js';

const props = defineProps({
  pageTitle: { type: String, default: 'Create GRN Receive' },
  items: { type: Array, default: () => [] },
});

const form = useForm({
  supplier_name: '',
  invoice_no: '',
  receive_date: new Date().toISOString().split('T')[0],
  notes: '',
  items: [
    { store_item_id: '', quantity: 1, unit_cost: 0 },
  ],
});

const addRow = () => {
  form.items.push({ store_item_id: '', quantity: 1, unit_cost: 0 });
};

const removeRow = (index) => {
  if (form.items.length <= 1) return;
  form.items.splice(index, 1);
};

const onItemChange = (row) => {
  const selected = props.items.find((item) => Number(item.id) === Number(row.store_item_id));
  if (selected) {
    row.unit_cost = Number(selected.unit_cost ?? 0);
  }
};

const submit = () => {
  form.post(route('backend.stock.grn.store'), {
    onError: (errorObject) => displayWarning(errorObject),
  });
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
        <a :href="route('backend.stock.grns')" class="px-3 py-2 text-sm font-semibold text-white bg-gray-600 rounded hover:bg-gray-700">Back GRN List</a>
      </div>

      <form @submit.prevent="submit" class="mt-4 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
          <div>
            <label class="block mb-1 text-sm font-semibold text-slate-700">Supplier Name</label>
            <input v-model="form.supplier_name" type="text" class="w-full p-2 text-sm border rounded" />
            <InputError class="mt-1" :message="form.errors.supplier_name" />
          </div>
          <div>
            <label class="block mb-1 text-sm font-semibold text-slate-700">Invoice No</label>
            <input v-model="form.invoice_no" type="text" class="w-full p-2 text-sm border rounded" />
            <InputError class="mt-1" :message="form.errors.invoice_no" />
          </div>
          <div>
            <label class="block mb-1 text-sm font-semibold text-slate-700">Receive Date</label>
            <input v-model="form.receive_date" type="date" class="w-full p-2 text-sm border rounded" />
            <InputError class="mt-1" :message="form.errors.receive_date" />
          </div>
          <div>
            <label class="block mb-1 text-sm font-semibold text-slate-700">Notes</label>
            <input v-model="form.notes" type="text" class="w-full p-2 text-sm border rounded" />
            <InputError class="mt-1" :message="form.errors.notes" />
          </div>
        </div>

        <div class="border rounded p-3">
          <div class="flex justify-between items-center mb-3">
            <h2 class="font-semibold text-slate-700">GRN Items</h2>
            <button type="button" class="px-3 py-1 text-sm rounded bg-blue-600 text-white hover:bg-blue-700" @click="addRow">Add Row</button>
          </div>

          <div class="space-y-2">
            <div v-for="(row, idx) in form.items" :key="idx" class="grid grid-cols-1 md:grid-cols-12 gap-2 bg-slate-50 p-2 rounded border">
              <div class="md:col-span-6">
                <select v-model="row.store_item_id" class="w-full p-2 text-sm border rounded" @change="onItemChange(row)">
                  <option value="">Select Item</option>
                  <option v-for="item in items" :key="item.id" :value="item.id">{{ item.item_name }} ({{ item.unit }})</option>
                </select>
              </div>
              <div class="md:col-span-2">
                <input v-model.number="row.quantity" type="number" min="0.01" step="0.01" class="w-full p-2 text-sm border rounded" placeholder="Qty" />
              </div>
              <div class="md:col-span-3">
                <input v-model.number="row.unit_cost" type="number" min="0" step="0.01" class="w-full p-2 text-sm border rounded" placeholder="Unit Cost" />
              </div>
              <div class="md:col-span-1">
                <button type="button" class="w-full p-2 text-xs text-white bg-rose-600 rounded hover:bg-rose-700" @click="removeRow(idx)">X</button>
              </div>
            </div>
          </div>

          <InputError class="mt-2" :message="form.errors.items" />
        </div>

        <div class="flex justify-end">
          <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700" :disabled="form.processing">
            {{ form.processing ? 'Saving...' : 'Save GRN' }}
          </button>
        </div>
      </form>
    </div>
  </BackendLayout>
</template>
