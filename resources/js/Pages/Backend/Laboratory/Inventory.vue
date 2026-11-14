<script setup>
import { reactive } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'Lab Inventory' },
  items: { type: Object, default: () => ({ data: [], links: [] }) },
  movements: { type: Array, default: () => [] },
});
const form = useForm({ name: '', category: '', unit: 'pcs', reorder_level: 0, opening_stock: 0 });
const adjustments = reactive(Object.fromEntries(props.items.data.map((item) => [item.id, { movement_type: 'in', quantity: '', reason: '', reference_no: '' }])));
const submit = () => form.post(route('backend.lab.inventory.store'), { onSuccess: () => form.reset() });
const adjust = (item) => router.post(route('backend.lab.inventory.adjust', item.id), adjustments[item.id]);
const money = (value) => Number(value ?? 0).toFixed(2);
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 space-y-4 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-2 p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold">{{ pageTitle }}</h1>
        <div class="flex flex-wrap gap-2">
          <a :href="route('backend.lab.suppliers')" class="px-3 py-2 text-sm font-semibold text-white bg-indigo-600 rounded">Lab Suppliers</a>
          <a :href="route('backend.lab.purchase-orders')" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded">Purchase Orders / GRN</a>
          <a :href="route('backend.lab.purchase-payments')" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded">Supplier Payments</a>
          <a :href="route('backend.lab.dashboard')" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Lab Dashboard</a>
        </div>
      </div>
      <form class="p-4 border rounded-lg" @submit.prevent="submit">
        <h2 class="mb-3 font-semibold">Add Reagent / Supply</h2>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
          <div><label class="block mb-1 text-sm">Item name *</label><input v-model="form.name" required class="w-full p-2 border rounded" /><InputError :message="form.errors.name" /></div>
          <div><label class="block mb-1 text-sm">Category</label><input v-model="form.category" class="w-full p-2 border rounded" /><InputError :message="form.errors.category" /></div>
          <div><label class="block mb-1 text-sm">Unit *</label><input v-model="form.unit" required class="w-full p-2 border rounded" /><InputError :message="form.errors.unit" /></div>
          <div><label class="block mb-1 text-sm">Opening stock *</label><input v-model.number="form.opening_stock" type="number" min="0" step="0.01" required class="w-full p-2 border rounded" /><InputError :message="form.errors.opening_stock" /></div>
          <div><label class="block mb-1 text-sm">Reorder at *</label><input v-model.number="form.reorder_level" type="number" min="0" step="0.01" required class="w-full p-2 border rounded" /><InputError :message="form.errors.reorder_level" /></div>
        </div>
        <div class="flex justify-end mt-3"><button :disabled="form.processing" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded">{{ form.processing ? 'Saving...' : 'Add Inventory Item' }}</button></div>
      </form>
      <section>
        <h2 class="mb-2 font-semibold">Current Lab Inventory</h2>
        <div class="overflow-x-auto"><table class="w-full text-sm border"><thead class="bg-slate-50"><tr><th class="p-2 text-left border">Item</th><th class="p-2 text-left border">Category</th><th class="p-2 border">On hand</th><th class="p-2 border">Reorder at</th><th class="p-2 border">Movement</th><th class="p-2 border">Qty</th><th class="p-2 border">Reason</th><th class="p-2 border">Action</th></tr></thead>
          <tbody><tr v-for="item in items.data" :key="item.id" :class="Number(item.current_stock) <= Number(item.reorder_level) ? 'bg-amber-50' : ''">
            <td class="p-2 border">{{ item.name }}</td><td class="p-2 border">{{ item.category || '-' }}</td><td class="p-2 text-right border font-semibold">{{ money(item.current_stock) }} {{ item.unit }}</td><td class="p-2 text-right border">{{ money(item.reorder_level) }}</td>
            <td class="p-2 border"><select v-model="adjustments[item.id].movement_type" class="w-full p-2 border rounded"><option value="in">Stock In</option><option value="out">Stock Out</option></select></td>
            <td class="p-2 border"><input v-model="adjustments[item.id].quantity" type="number" min="0.01" step="0.01" class="w-full p-2 border rounded" /></td>
            <td class="p-2 border"><input v-model="adjustments[item.id].reason" required class="w-full p-2 border rounded" placeholder="Reason" /><input v-model="adjustments[item.id].reference_no" class="w-full p-2 mt-1 border rounded" placeholder="Reference (optional)" /></td>
            <td class="p-2 text-center border"><button class="px-3 py-2 text-white bg-emerald-600 rounded" @click="adjust(item)">Save</button></td>
          </tr><tr v-if="items.data.length === 0"><td colspan="8" class="p-5 text-center border text-slate-500">No lab inventory items found.</td></tr></tbody>
        </table></div>
        <div class="flex justify-end gap-2 mt-3"><a v-for="(link, index) in items.links" :key="index" :href="link.url || '#'" class="px-3 py-1 text-sm border rounded" :class="link.active ? 'bg-emerald-600 text-white' : ''" v-html="link.label" /></div>
      </section>
      <section>
        <h2 class="mb-2 font-semibold">Recent Inventory Movements</h2>
        <div class="overflow-x-auto"><table class="w-full text-sm border"><thead class="bg-slate-50"><tr><th class="p-2 border">Date</th><th class="p-2 text-left border">Item</th><th class="p-2 border">Type</th><th class="p-2 text-right border">Quantity</th><th class="p-2 border">Reference</th><th class="p-2 text-left border">Reason</th></tr></thead>
          <tbody><tr v-for="movement in movements" :key="movement.id"><td class="p-2 text-center border">{{ movement.movement_date }}</td><td class="p-2 border">{{ movement.item_name }}</td><td class="p-2 text-center border">{{ movement.movement_type }}</td><td class="p-2 text-right border">{{ money(movement.quantity) }} {{ movement.unit }}</td><td class="p-2 text-center border">{{ movement.reference_no || '-' }}</td><td class="p-2 border">{{ movement.reason }}</td></tr><tr v-if="movements.length === 0"><td colspan="6" class="p-4 text-center border text-slate-500">No inventory movements yet.</td></tr></tbody>
        </table></div>
      </section>
    </div>
  </BackendLayout>
</template>
