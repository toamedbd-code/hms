<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';
import SearchableStoreItemSelect from '@/Components/SearchableStoreItemSelect.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'Return Products to Supplier' },
  suppliers: { type: Array, default: () => [] },
  items: { type: Array, default: () => [] },
});

const today = new Date();
const localDate = [
  today.getFullYear(),
  String(today.getMonth() + 1).padStart(2, '0'),
  String(today.getDate()).padStart(2, '0'),
].join('-');

const form = useForm({
  store_supplier_id: '',
  return_date: localDate,
  reason: '',
  notes: '',
  items: [{ store_item_id: '', quantity: 1, unit_cost: 0 }],
});

const addRow = () => form.items.push({ store_item_id: '', quantity: 1, unit_cost: 0 });
const removeRow = (index) => {
  if (form.items.length > 1) form.items.splice(index, 1);
};
const selectedItem = (row) => props.items.find((item) => String(item.id) === String(row.store_item_id));
const formatItem = (item) => `${item.item_name} · stock ${Number(item.current_stock ?? 0).toFixed(2)} ${item.unit || ''}`;
const setItem = (row, item) => {
  if (item) row.unit_cost = Number(item.unit_cost ?? 0);
};
const totalAmount = computed(() => form.items.reduce(
  (total, row) => total + Number(row.quantity ?? 0) * Number(row.unit_cost ?? 0),
  0,
));

const submit = () => form.post(route('backend.stock.product-returns.store'));
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-3 p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
        <a :href="route('backend.stock.product-returns')" class="px-3 py-2 text-sm font-semibold text-white bg-red-600 rounded hover:bg-red-700">Back to Product Returns</a>
      </div>

      <div class="p-4 mt-4 border border-amber-200 rounded bg-amber-50">
        Saving this return immediately reduces Store stock and records stock movement history. Return quantity cannot exceed available stock.
      </div>
      <p v-if="suppliers.length === 0 || items.length === 0" class="p-3 mt-3 text-sm text-rose-700 border border-rose-200 rounded bg-rose-50">
        {{ suppliers.length === 0 ? 'Add an active Store Supplier before creating a return. ' : '' }}
        {{ items.length === 0 ? 'There are no active Store items with stock available to return.' : '' }}
      </p>

      <form class="mt-4 space-y-4" @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <label for="store_supplier_id" class="block mb-1 text-sm font-medium">Supplier <span class="text-red-600">*</span></label>
            <select id="store_supplier_id" v-model="form.store_supplier_id" class="w-full p-2 text-sm border rounded">
              <option value="">Select supplier</option>
              <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
            </select>
            <InputError :message="form.errors.store_supplier_id" class="mt-1" />
          </div>
          <div>
            <label for="return_date" class="block mb-1 text-sm font-medium">Return Date <span class="text-red-600">*</span></label>
            <input id="return_date" v-model="form.return_date" type="date" class="w-full p-2 text-sm border rounded">
            <InputError :message="form.errors.return_date" class="mt-1" />
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm border border-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-2 border text-left">Store Item</th>
                <th class="px-3 py-2 border text-right">Available</th>
                <th class="px-3 py-2 border text-right">Return Qty</th>
                <th class="px-3 py-2 border text-right">Unit Cost</th>
                <th class="px-3 py-2 border text-right">Line Total</th>
                <th class="px-3 py-2 border"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, index) in form.items" :key="index" class="align-top">
                <td class="p-2 border min-w-64">
                  <SearchableStoreItemSelect
                    v-model="row.store_item_id"
                    :items="items"
                    :format-option="formatItem"
                    @select="(item) => setItem(row, item)"
                  />
                  <InputError :message="form.errors[`items.${index}.store_item_id`]" class="mt-1" />
                </td>
                <td class="p-2 border text-right whitespace-nowrap">
                  {{ selectedItem(row) ? Number(selectedItem(row).current_stock).toFixed(2) : '-' }}
                </td>
                <td class="p-2 border min-w-32">
                  <input v-model.number="row.quantity" type="number" min="0.01" step="0.01" :max="selectedItem(row)?.current_stock" class="w-full p-2 text-sm text-right border rounded">
                  <InputError :message="form.errors[`items.${index}.quantity`]" class="mt-1" />
                </td>
                <td class="p-2 border min-w-32">
                  <input v-model.number="row.unit_cost" type="number" min="0" step="0.01" class="w-full p-2 text-sm text-right border rounded">
                  <InputError :message="form.errors[`items.${index}.unit_cost`]" class="mt-1" />
                </td>
                <td class="p-2 border text-right whitespace-nowrap">
                  {{ (Number(row.quantity || 0) * Number(row.unit_cost || 0)).toFixed(2) }}
                </td>
                <td class="p-2 border text-center">
                  <button type="button" class="px-2 py-1 text-xs font-semibold text-white bg-rose-600 rounded disabled:opacity-50" :disabled="form.items.length === 1" @click="removeRow(index)">Remove</button>
                </td>
              </tr>
            </tbody>
          </table>
          <InputError :message="form.errors.items" class="mt-1" />
        </div>

        <button type="button" class="px-3 py-2 text-sm font-semibold border rounded hover:bg-gray-50" @click="addRow">Add Item</button>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <label for="return_reason" class="block mb-1 text-sm font-medium">Reason <span class="text-red-600">*</span></label>
            <input id="return_reason" v-model="form.reason" type="text" maxlength="1000" class="w-full p-2 text-sm border rounded" placeholder="e.g. Damaged, expired, or incorrect item">
            <InputError :message="form.errors.reason" class="mt-1" />
          </div>
          <div>
            <label for="return_notes" class="block mb-1 text-sm font-medium">Notes</label>
            <textarea id="return_notes" v-model="form.notes" rows="2" maxlength="2000" class="w-full p-2 text-sm border rounded"></textarea>
            <InputError :message="form.errors.notes" class="mt-1" />
          </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
          <p class="text-lg font-semibold">Total: {{ totalAmount.toFixed(2) }}</p>
          <button type="submit" class="px-5 py-2 text-sm font-semibold text-white bg-orange-600 rounded hover:bg-orange-700 disabled:opacity-50" :disabled="form.processing || suppliers.length === 0 || items.length === 0">
            {{ form.processing ? 'Saving...' : 'Save Product Return' }}
          </button>
        </div>
      </form>
    </div>
  </BackendLayout>
</template>
