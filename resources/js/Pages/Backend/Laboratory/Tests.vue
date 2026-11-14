<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'Lab Test Catalog' },
  tests: { type: Object, default: () => ({ data: [], links: [] }) },
  inventoryItems: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
});
const search = ref(props.filters?.search ?? '');
const form = useForm({ test_code: '', name: '', category: '', specimen: '', method: '', unit: '', reference_range: '', price: 0, turnaround_hours: 24, inventory_items: [{ lab_inventory_item_id: '', quantity_per_test: 1 }] });
const addInventoryUse = () => form.inventory_items.push({ lab_inventory_item_id: '', quantity_per_test: 1 });
const removeInventoryUse = (index) => form.inventory_items.splice(index, 1);
const inventoryError = (index) => form.errors[`inventory_items.${index}.lab_inventory_item_id`] || form.errors[`inventory_items.${index}.quantity_per_test`];
const submit = () => form.transform((data) => ({
  ...data,
  inventory_items: data.inventory_items.filter((item) => item.lab_inventory_item_id && item.quantity_per_test),
})).post(route('backend.lab.tests.store'), { onSuccess: () => form.reset() });
const applySearch = () => router.get(route('backend.lab.tests'), { search: search.value || undefined }, { preserveState: true, replace: true });
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 space-y-4 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold">{{ pageTitle }}</h1>
        <a :href="route('backend.lab.dashboard')" class="px-3 py-2 text-sm text-white bg-emerald-600 rounded">Lab Dashboard</a>
      </div>
      <form class="p-4 border rounded-lg" @submit.prevent="submit">
        <h2 class="mb-3 font-semibold">Add Test</h2>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
          <div><label class="block mb-1 text-sm">Test code</label><input v-model="form.test_code" maxlength="40" class="w-full p-2 border rounded" placeholder="Auto-generated if blank" /><InputError :message="form.errors.test_code" /></div>
          <div><label class="block mb-1 text-sm">Test name *</label><input v-model="form.name" required maxlength="255" class="w-full p-2 border rounded" /><InputError :message="form.errors.name" /></div>
          <div><label class="block mb-1 text-sm">Category</label><input v-model="form.category" maxlength="100" class="w-full p-2 border rounded" placeholder="Hematology, Biochemistry..." /><InputError :message="form.errors.category" /></div>
          <div><label class="block mb-1 text-sm">Specimen</label><input v-model="form.specimen" maxlength="100" class="w-full p-2 border rounded" placeholder="Blood, Serum, Urine..." /><InputError :message="form.errors.specimen" /></div>
          <div><label class="block mb-1 text-sm">Method</label><input v-model="form.method" maxlength="150" class="w-full p-2 border rounded" /><InputError :message="form.errors.method" /></div>
          <div><label class="block mb-1 text-sm">Unit</label><input v-model="form.unit" maxlength="50" class="w-full p-2 border rounded" /><InputError :message="form.errors.unit" /></div>
          <div><label class="block mb-1 text-sm">Reference range</label><input v-model="form.reference_range" maxlength="150" class="w-full p-2 border rounded" /><InputError :message="form.errors.reference_range" /></div>
          <div><label class="block mb-1 text-sm">Price *</label><input v-model.number="form.price" type="number" min="0" step="0.01" required class="w-full p-2 border rounded" /><InputError :message="form.errors.price" /></div>
          <div><label class="block mb-1 text-sm">Turnaround (hours) *</label><input v-model.number="form.turnaround_hours" type="number" min="1" max="8760" required class="w-full p-2 border rounded" /><InputError :message="form.errors.turnaround_hours" /></div>
        </div>
        <div class="mt-4">
          <div class="flex items-center justify-between mb-2"><h3 class="font-medium">Reagents and supplies consumed per test</h3><button type="button" class="px-3 py-1 text-sm text-white bg-slate-600 rounded" @click="addInventoryUse">Add supply</button></div>
          <div v-for="(use, index) in form.inventory_items" :key="index" class="grid grid-cols-1 gap-2 mb-2 md:grid-cols-[1fr_180px_auto]">
            <select v-model="use.lab_inventory_item_id" class="w-full p-2 border rounded"><option value="">Select reagent / supply</option><option v-for="item in inventoryItems" :key="item.id" :value="item.id">{{ item.name }} ({{ item.current_stock }} {{ item.unit }})</option></select>
            <input v-model.number="use.quantity_per_test" type="number" min="0.01" step="0.01" class="w-full p-2 border rounded" aria-label="Quantity consumed per test" />
            <button type="button" class="px-3 py-2 text-sm text-red-700 border rounded" @click="removeInventoryUse(index)">Remove</button>
            <InputError :message="inventoryError(index)" />
          </div>
          <p v-if="inventoryItems.length === 0" class="text-sm text-amber-700">Add lab inventory items before configuring test consumption.</p>
          <InputError :message="form.errors.inventory_items" />
        </div>
        <div class="flex justify-end mt-3"><button :disabled="form.processing" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded disabled:opacity-50">{{ form.processing ? 'Saving...' : 'Add Test' }}</button></div>
      </form>
      <div class="flex gap-2"><input v-model="search" class="p-2 text-sm border rounded" placeholder="Search test catalog" @keyup.enter="applySearch" /><button class="px-3 py-2 text-sm text-white bg-emerald-600 rounded" @click="applySearch">Search</button></div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm border"><thead class="bg-slate-50"><tr><th class="p-2 text-left border">Code</th><th class="p-2 text-left border">Test</th><th class="p-2 text-left border">Category</th><th class="p-2 text-left border">Specimen</th><th class="p-2 text-left border">Reference range</th><th class="p-2 text-right border">Price</th><th class="p-2 text-right border">TAT (h)</th><th class="p-2 border">Reagents</th><th class="p-2 border">Status</th></tr></thead>
          <tbody><tr v-for="test in tests.data" :key="test.id"><td class="p-2 border">{{ test.test_code }}</td><td class="p-2 border">{{ test.name }}<div class="text-xs text-slate-500">{{ test.method || '' }} {{ test.unit || '' }}</div></td><td class="p-2 border">{{ test.category || '-' }}</td><td class="p-2 border">{{ test.specimen || '-' }}</td><td class="p-2 border">{{ test.reference_range || '-' }}</td><td class="p-2 text-right border">{{ Number(test.price).toFixed(2) }}</td><td class="p-2 text-right border">{{ test.turnaround_hours }}</td><td class="p-2 text-center border">{{ test.reagent_count }}</td><td class="p-2 text-center border"><div>{{ test.is_active ? 'Active' : 'Inactive' }}</div><button class="mt-1 text-xs text-indigo-700 hover:underline" @click="router.post(route('backend.lab.tests.toggle', test.id))">{{ test.is_active ? 'Deactivate' : 'Activate' }}</button></td></tr>
            <tr v-if="tests.data.length === 0"><td colspan="9" class="p-5 text-center border text-slate-500">No tests found.</td></tr></tbody>
        </table>
      </div>
      <div class="flex justify-end gap-2"><a v-for="(link, index) in tests.links" :key="index" :href="link.url || '#'" class="px-3 py-1 text-sm border rounded" :class="link.active ? 'bg-emerald-600 text-white' : ''" v-html="link.label" /></div>
    </div>
  </BackendLayout>
</template>
