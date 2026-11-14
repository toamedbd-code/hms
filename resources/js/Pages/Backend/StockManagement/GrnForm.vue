<script setup>
import { useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';
import { displayWarning } from '@/responseMessage.js';

const props = defineProps({
  pageTitle: { type: String, default: 'Record Purchase Delivery' },
  items: { type: Array, default: () => [] },
  purchaseOrders: { type: Array, default: () => [] },
  selectedPurchaseOrder: { type: Object, default: null },
});

const orderLines = (order) => {
  if (!order) return [{ store_item_id: '', store_purchase_order_item_id: '', quantity: 1, unit_cost: 0, batch_no: '', expiry_date: '', serial_number: '', asset_tag: '' }];

  return (order.items ?? []).flatMap((line) => {
    const remaining = Math.max(0, Number(line.quantity ?? 0) - Number(line.received_quantity ?? 0));
    if (remaining <= 0) return [];
    const count = line.store_item?.stock_behavior === 'reusable' ? Math.ceil(remaining) : 1;

    return Array.from({ length: count }, () => ({
      store_item_id: line.store_item_id,
      store_purchase_order_item_id: line.id,
      quantity: line.store_item?.stock_behavior === 'reusable' ? 1 : remaining,
      unit_cost: Number(line.unit_cost ?? 0),
      batch_no: '',
      expiry_date: '',
      serial_number: '',
      asset_tag: '',
    }));
  });
};

const form = useForm({
  store_purchase_order_id: props.selectedPurchaseOrder?.id ?? '',
  supplier_name: props.selectedPurchaseOrder?.supplier?.name ?? '',
  invoice_no: '',
  receive_date: new Date().toISOString().split('T')[0],
  notes: '',
  items: orderLines(props.selectedPurchaseOrder),
});

const addRow = () => {
  form.items.push({ store_item_id: '', store_purchase_order_item_id: '', quantity: 1, unit_cost: 0, batch_no: '', expiry_date: '', serial_number: '', asset_tag: '' });
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

const onPurchaseOrderChange = () => {
  const order = props.purchaseOrders.find((candidate) => Number(candidate.id) === Number(form.store_purchase_order_id)) || null;
  form.supplier_name = order?.supplier?.name ?? '';
  form.items = orderLines(order);
};

const itemForRow = (row) => props.items.find((item) => Number(item.id) === Number(row.store_item_id)) || null;
const remainingForRow = (row) => {
  const order = props.purchaseOrders.find((candidate) => Number(candidate.id) === Number(form.store_purchase_order_id));
  const line = order?.items?.find((candidate) => Number(candidate.id) === Number(row.store_purchase_order_item_id));
  return Math.max(0, Number(line?.quantity ?? 0) - Number(line?.received_quantity ?? 0));
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
        <a :href="route('backend.stock.grns')" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Back</a>
      </div>
      <div class="p-3 mt-3 text-sm border border-sky-200 rounded bg-sky-50 text-sky-900">
        Record each delivered quantity here. Saving the delivery adds the received stock to Central Store and updates the purchase order's remaining quantity. Issue the stock to a department from Store Requisitions before it is used by a billed test.
      </div>

      <form @submit.prevent="submit" class="mt-4 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
          <div>
            <label class="block mb-1 text-sm font-semibold text-slate-700">Purchase Order</label>
            <select v-model="form.store_purchase_order_id" class="w-full p-2 text-sm border rounded" @change="onPurchaseOrderChange">
              <option value="">Manual delivery (no purchase order)</option>
              <option v-for="order in purchaseOrders" :key="order.id" :value="order.id">{{ order.purchase_order_no }} · {{ order.supplier?.name }}</option>
            </select>
            <InputError class="mt-1" :message="form.errors.store_purchase_order_id" />
          </div>
          <div>
            <label class="block mb-1 text-sm font-semibold text-slate-700">Supplier Name</label>
            <input v-model="form.supplier_name" type="text" :readonly="Boolean(form.store_purchase_order_id)" class="w-full p-2 text-sm border rounded" />
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
          <h2 class="font-semibold text-slate-700">Delivered items and quantities</h2>
            <button v-if="!form.store_purchase_order_id" type="button" class="px-3 py-1 text-sm font-semibold rounded bg-emerald-600 text-white hover:bg-emerald-700" @click="addRow">Add New Order Item</button>
          </div>

          <div class="space-y-2">
            <div v-for="(row, idx) in form.items" :key="idx" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-12 gap-2 bg-slate-50 p-2 rounded border">
              <div class="xl:col-span-3">
                <select v-model="row.store_item_id" class="w-full p-2 text-sm border rounded" :disabled="Boolean(form.store_purchase_order_id)" @change="onItemChange(row)">
                  <option value="">Select Item</option>
                  <option v-for="item in items" :key="item.id" :value="item.id">{{ item.item_name }} · {{ item.category || 'Item' }} ({{ item.unit }})</option>
                </select>
              </div>
              <div class="xl:col-span-1">
                <input v-model.number="row.quantity" type="number" :min="itemForRow(row)?.stock_behavior === 'reusable' ? 1 : 0.01" :step="itemForRow(row)?.stock_behavior === 'reusable' ? 1 : 0.01" :max="itemForRow(row)?.stock_behavior === 'reusable' ? 1 : (form.store_purchase_order_id ? remainingForRow(row) : undefined)" class="w-full p-2 text-sm border rounded" placeholder="Qty" />
              </div>
              <div class="xl:col-span-1">
                <input v-model.number="row.unit_cost" type="number" min="0" step="0.01" class="w-full p-2 text-sm border rounded" placeholder="Unit Cost" />
              </div>
              <div class="xl:col-span-2">
                <input v-model="row.batch_no" type="text" class="w-full p-2 text-sm border rounded" placeholder="Batch / Lot (optional)" />
              </div>
              <div v-if="itemForRow(row)?.stock_behavior !== 'reusable'" class="xl:col-span-2">
                <input v-model="row.expiry_date" type="date" class="w-full p-2 text-sm border rounded" aria-label="Expiry date" />
              </div>
              <div v-if="itemForRow(row)?.stock_behavior === 'reusable'" class="xl:col-span-2">
                <input v-model="row.serial_number" type="text" class="w-full p-2 text-sm border rounded" placeholder="Serial No *" />
              </div>
              <div v-if="itemForRow(row)?.stock_behavior === 'reusable'" class="xl:col-span-2">
                <input v-model="row.asset_tag" type="text" class="w-full p-2 text-sm border rounded" placeholder="Asset Tag *" />
              </div>
              <div class="xl:col-span-1">
                <button type="button" class="w-full p-2 text-xs text-white bg-rose-600 rounded hover:bg-rose-700" @click="removeRow(idx)">X</button>
              </div>
            </div>
          </div>

          <InputError class="mt-2" :message="form.errors.items" />
        </div>

        <div class="flex justify-end">
          <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700" :disabled="form.processing">
            {{ form.processing ? 'Saving...' : 'Save Delivery' }}
          </button>
        </div>
      </form>
    </div>
  </BackendLayout>
</template>
