<script setup>
import { useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
  order: { type: Object, required: true },
  items: { type: Array, default: () => [] },
});
const form = useForm({
  received_date: new Date().toISOString().slice(0, 10),
  notes: '',
  items: props.items.map((item) => ({
    lab_purchase_order_item_id: item.id,
    quantity: 0,
    batch_no: '',
    expiry_date: '',
  })),
});
const submit = () => form.transform((data) => ({
  ...data,
  items: data.items.filter((line) => Number(line.quantity) > 0),
})).post(route('backend.lab.purchase-orders.receive.store', props.order.id));
const money = (value) => Number(value ?? 0).toFixed(2);
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 space-y-4 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <div><h1 class="text-xl font-semibold">Receive Lab Goods</h1><p class="mt-1 text-sm text-slate-600">{{ order.order_no }} · {{ order.supplier_name }}</p></div>
        <a :href="route('backend.lab.purchase-orders')" class="px-3 py-2 text-sm text-white bg-emerald-600 rounded">Back to Orders</a>
      </div>
      <form class="space-y-4" @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
          <div><label class="block mb-1 text-sm">Received date *</label><input v-model="form.received_date" type="date" required class="w-full p-2 border rounded" /><InputError :message="form.errors.received_date" /></div>
          <div><label class="block mb-1 text-sm">Notes</label><input v-model="form.notes" class="w-full p-2 border rounded" /><InputError :message="form.errors.notes" /></div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm border">
            <thead class="bg-slate-50"><tr><th class="p-2 text-left border">Item</th><th class="p-2 text-right border">Ordered</th><th class="p-2 text-right border">Previously received</th><th class="p-2 text-right border">Remaining</th><th class="p-2 border">Receive now</th><th class="p-2 border">Batch</th><th class="p-2 border">Expiry</th></tr></thead>
            <tbody>
              <tr v-for="(item, index) in items" :key="item.id">
                <td class="p-2 border">{{ item.name }} <span class="text-xs text-slate-500">{{ item.unit }}</span></td><td class="p-2 text-right border">{{ money(item.quantity) }}</td><td class="p-2 text-right border">{{ money(item.received_quantity) }}</td><td class="p-2 text-right border">{{ money(item.remaining_quantity) }}</td>
                <td class="p-2 border"><input v-model.number="form.items[index].quantity" type="number" min="0" :max="item.remaining_quantity" step="0.01" class="w-28 p-2 border rounded" /><InputError :message="form.errors[`items.${index}.quantity`]" /></td>
                <td class="p-2 border"><input v-model="form.items[index].batch_no" maxlength="100" class="w-32 p-2 border rounded" /></td><td class="p-2 border"><input v-model="form.items[index].expiry_date" type="date" class="p-2 border rounded" /></td>
              </tr>
              <tr v-if="items.length === 0"><td colspan="7" class="p-5 text-center border text-slate-500">No order items found.</td></tr>
            </tbody>
          </table>
        </div>
        <InputError :message="form.errors.items" />
        <div class="flex justify-end"><button :disabled="form.processing || items.every((item) => Number(item.remaining_quantity) <= 0)" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded disabled:opacity-50">{{ form.processing ? 'Saving...' : 'Receive Goods & Update Lab Stock' }}</button></div>
      </form>
    </div>
  </BackendLayout>
</template>
