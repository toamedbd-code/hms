<script setup>
import { computed, reactive, ref } from 'vue';
import axios from 'axios';
import { router, useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
  orders: { type: Object, default: () => ({ data: [], links: [] }) },
  suppliers: { type: Array, default: () => [] },
  items: { type: Array, default: () => [] },
  receipts: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
});
const search = ref(props.filters?.search ?? '');
const itemOptions = ref([...props.items]);
const supplierOptions = ref([...props.suppliers]);
const form = useForm({
  lab_supplier_id: '',
  order_date: new Date().toISOString().slice(0, 10),
  expected_delivery_date: '',
  notes: '',
  items: [{ lab_inventory_item_id: '', quantity: 1, unit_cost: 0 }],
});
const activeSupplierSearch = ref(false);
const supplierSearch = ref('');
const addSupplierModal = ref(false);
const savingSupplier = ref(false);
const supplierError = ref('');
const supplierForm = reactive({
  name: '',
  phone: '',
  contact_person: '',
  email: '',
  address: '',
});
const activeItemSearch = ref(null);
const itemSearch = reactive({});
const addInventoryModal = ref(false);
const savingInventoryItem = ref(false);
const inventoryItemError = ref('');
const inventoryItemTargetLine = ref(null);
const inventoryItemForm = reactive({
  name: '',
  category: '',
  unit: 'pcs',
  opening_stock: 0,
  reorder_level: 0,
});
const total = computed(() => form.items.reduce((sum, line) => sum + Number(line.quantity || 0) * Number(line.unit_cost || 0), 0));
const selectedSupplierLabel = computed(() => supplierOptions.value.find(
  (supplier) => Number(supplier.id) === Number(form.lab_supplier_id),
)?.name ?? '');
const filteredSuppliers = computed(() => {
  const term = supplierSearch.value.trim().toLowerCase();
  return supplierOptions.value.filter((supplier) => !term || `${supplier.name} ${supplier.phone ?? ''} ${supplier.contact_person ?? ''}`.toLowerCase().includes(term));
});
const openSupplierSearch = () => {
  activeSupplierSearch.value = true;
  supplierSearch.value = '';
};
const updateSupplierSearch = (event) => {
  supplierSearch.value = event.target.value;
  if (form.lab_supplier_id) {
    form.lab_supplier_id = '';
    form.clearErrors('lab_supplier_id');
  }
  activeSupplierSearch.value = true;
};
const selectSupplier = (supplier) => {
  form.lab_supplier_id = supplier.id;
  supplierSearch.value = '';
  activeSupplierSearch.value = false;
  form.clearErrors('lab_supplier_id');
};
const clearSupplier = () => {
  form.lab_supplier_id = '';
  supplierSearch.value = '';
  activeSupplierSearch.value = true;
};
const openAddSupplierModal = () => {
  supplierError.value = '';
  Object.assign(supplierForm, { name: '', phone: '', contact_person: '', email: '', address: '' });
  addSupplierModal.value = true;
};
const createSupplier = async () => {
  supplierError.value = '';
  savingSupplier.value = true;
  try {
    const response = await axios.post(route('backend.lab.suppliers.store'), supplierForm);
    const supplier = response.data?.supplier;
    if (!supplier?.id) throw new Error('The server did not return the created Lab supplier.');
    supplierOptions.value.push(supplier);
    selectSupplier(supplier);
    addSupplierModal.value = false;
  } catch (error) {
    if (error.response?.status === 422) {
      supplierError.value = Object.values(error.response.data?.errors ?? {}).flat()[0] ?? 'Enter valid Lab supplier details.';
    } else {
      supplierError.value = error.response?.data?.message ?? error.message ?? 'Could not create Lab supplier.';
    }
  } finally {
    savingSupplier.value = false;
  }
};
const addLine = () => {
  form.items.push({ lab_inventory_item_id: '', quantity: 1, unit_cost: 0 });
  itemSearch[form.items.length - 1] = '';
};
const removeLine = (index) => {
  if (form.items.length > 1) {
    form.items.splice(index, 1);
    Object.keys(itemSearch).forEach((key) => delete itemSearch[key]);
    form.items.forEach((_, rowIndex) => { itemSearch[rowIndex] = ''; });
    if (activeItemSearch.value === index) activeItemSearch.value = null;
  }
};
const itemLabel = (line) => {
  const item = itemOptions.value.find((candidate) => Number(candidate.id) === Number(line.lab_inventory_item_id));
  return item ? `${item.name} (${item.current_stock} ${item.unit})` : '';
};
const filteredItems = (index) => {
  const term = String(itemSearch[index] ?? '').trim().toLowerCase();
  return itemOptions.value.filter((item) => !term || `${item.name} ${item.category ?? ''} ${item.unit}`.toLowerCase().includes(term));
};
const openItemSearch = (index) => {
  activeItemSearch.value = index;
  itemSearch[index] = '';
};
const updateItemSearch = (index, event) => {
  itemSearch[index] = event.target.value;
  if (form.items[index].lab_inventory_item_id) {
    form.items[index].lab_inventory_item_id = '';
    form.clearErrors(`items.${index}.lab_inventory_item_id`);
  }
  activeItemSearch.value = index;
};
const selectInventoryItem = (index, item) => {
  form.items[index].lab_inventory_item_id = item.id;
  itemSearch[index] = '';
  activeItemSearch.value = null;
  form.clearErrors(`items.${index}.lab_inventory_item_id`);
};
const clearInventoryItem = (index) => {
  form.items[index].lab_inventory_item_id = '';
  itemSearch[index] = '';
  activeItemSearch.value = index;
};
const openAddInventoryModal = (line) => {
  inventoryItemError.value = '';
  inventoryItemTargetLine.value = line;
  Object.assign(inventoryItemForm, { name: '', category: '', unit: 'pcs', opening_stock: 0, reorder_level: 0 });
  addInventoryModal.value = true;
};
const createInventoryItem = async () => {
  inventoryItemError.value = '';
  savingInventoryItem.value = true;
  try {
    const response = await axios.post(route('backend.lab.inventory.store'), inventoryItemForm);
    const createdItem = response.data?.item;
    if (!createdItem?.id) throw new Error('The server did not return the created Lab inventory item.');
    itemOptions.value.push(createdItem);
    const targetIndex = form.items.indexOf(inventoryItemTargetLine.value);
    const targetLine = targetIndex >= 0 ? inventoryItemTargetLine.value : form.items[form.items.length - 1];
    targetLine.lab_inventory_item_id = createdItem.id;
    if (targetIndex >= 0) itemSearch[targetIndex] = '';
    activeItemSearch.value = null;
    addInventoryModal.value = false;
  } catch (error) {
    if (error.response?.status === 422) {
      inventoryItemError.value = Object.values(error.response.data?.errors ?? {}).flat()[0] ?? 'Enter valid Lab inventory item details.';
    } else {
      inventoryItemError.value = error.response?.data?.message ?? error.message ?? 'Could not create Lab inventory item.';
    }
  } finally {
    savingInventoryItem.value = false;
  }
};
const submit = () => {
  if (!form.lab_supplier_id) {
    form.setError('lab_supplier_id', 'Select a supplier from the list.');
    activeSupplierSearch.value = true;
    return;
  }
  const missingItemIndex = form.items.findIndex((line) => !line.lab_inventory_item_id);
  if (missingItemIndex >= 0) {
    form.setError(`items.${missingItemIndex}.lab_inventory_item_id`, 'Select an item from the list.');
    activeItemSearch.value = missingItemIndex;
    return;
  }
  form.post(route('backend.lab.purchase-orders.store'), { onSuccess: () => form.reset('lab_supplier_id', 'expected_delivery_date', 'notes', 'items') });
};
const applySearch = () => router.get(route('backend.lab.purchase-orders'), { search: search.value || undefined }, { preserveState: true, replace: true });
const money = (value) => Number(value ?? 0).toFixed(2);
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 space-y-4 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold">Lab Purchase Orders</h1>
        <div class="flex gap-2"><a :href="route('backend.lab.suppliers')" class="px-3 py-2 text-sm text-white bg-emerald-600 rounded">Suppliers</a><a :href="route('backend.lab.dashboard')" class="px-3 py-2 text-sm text-white bg-emerald-600 rounded">Lab Dashboard</a></div>
      </div>
      <form class="p-4 space-y-3 border rounded-lg" @submit.prevent="submit">
        <h2 class="font-semibold">Create Purchase Order</h2>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
          <div class="relative">
            <label class="block mb-1 text-sm">Supplier *</label>
            <div class="flex">
              <input
                :value="activeSupplierSearch ? supplierSearch : selectedSupplierLabel"
                type="text"
                autocomplete="off"
                class="w-full p-2 border rounded-l"
                placeholder="Search and select supplier"
                role="combobox"
                :aria-expanded="activeSupplierSearch"
                @focus="openSupplierSearch"
                @input="updateSupplierSearch"
                @keydown.esc="activeSupplierSearch = false"
              />
              <button v-if="form.lab_supplier_id" type="button" class="px-3 border border-l-0 text-slate-600 hover:bg-slate-100" aria-label="Clear selected supplier" @click="clearSupplier">×</button>
              <button type="button" class="px-3 font-semibold text-white bg-emerald-600 border border-emerald-600 rounded-r hover:bg-emerald-700" aria-label="Add supplier" title="Add supplier" @click="openAddSupplierModal">+</button>
            </div>
            <div v-if="activeSupplierSearch" class="absolute z-30 w-full mt-1 overflow-auto bg-white border rounded shadow-lg max-h-52">
              <button v-for="supplier in filteredSuppliers" :key="supplier.id" type="button" class="block w-full px-3 py-2 text-left hover:bg-emerald-50" @mousedown.prevent="selectSupplier(supplier)">
                <span class="font-medium">{{ supplier.name }}</span><span v-if="supplier.phone || supplier.contact_person" class="ml-2 text-xs text-slate-500">{{ [supplier.phone, supplier.contact_person].filter(Boolean).join(' · ') }}</span>
              </button>
              <div v-if="filteredSuppliers.length === 0" class="px-3 py-2 text-sm text-slate-500">No matching suppliers.</div>
            </div>
            <InputError :message="form.errors.lab_supplier_id" />
          </div>
          <div><label class="block mb-1 text-sm">Order date *</label><input v-model="form.order_date" type="date" required class="w-full p-2 border rounded" /><InputError :message="form.errors.order_date" /></div>
          <div><label class="block mb-1 text-sm">Expected delivery</label><input v-model="form.expected_delivery_date" type="date" class="w-full p-2 border rounded" /><InputError :message="form.errors.expected_delivery_date" /></div>
        </div>
        <div v-for="(line, index) in form.items" :key="index" class="grid grid-cols-1 gap-2 items-end md:grid-cols-[minmax(0,1fr)_auto_150px_150px_auto]">
          <div class="relative">
            <label class="block mb-1 text-sm">Lab inventory item *</label>
            <div class="flex">
              <input
                :value="activeItemSearch === index ? itemSearch[index] : itemLabel(line)"
                type="text"
                autocomplete="off"
                class="w-full p-2 border rounded-l"
                placeholder="Search and select item"
                role="combobox"
                :aria-expanded="activeItemSearch === index"
                @focus="openItemSearch(index)"
                @input="updateItemSearch(index, $event)"
                @keydown.esc="activeItemSearch = null"
              />
              <button v-if="line.lab_inventory_item_id" type="button" class="px-3 border border-l-0 rounded-r text-slate-600 hover:bg-slate-100" aria-label="Clear selected inventory item" @click="clearInventoryItem(index)">×</button>
            </div>
            <div v-if="activeItemSearch === index" class="absolute z-20 w-full mt-1 overflow-auto bg-white border rounded shadow-lg max-h-52">
              <button v-for="item in filteredItems(index)" :key="item.id" type="button" class="block w-full px-3 py-2 text-left text-white bg-emerald-600 hover:bg-emerald-700" @mousedown.prevent="selectInventoryItem(index, item)">
                <span class="font-medium text-white">{{ item.name }}</span><span class="ml-2 text-xs text-white">{{ item.current_stock }} {{ item.unit }}<template v-if="item.category"> · {{ item.category }}</template></span>
              </button>
              <div v-if="filteredItems(index).length === 0" class="px-3 py-2 text-sm text-slate-500">No matching inventory items.</div>
            </div>
            <InputError :message="form.errors[`items.${index}.lab_inventory_item_id`]" />
          </div>
          <button type="button" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700" aria-label="Add Lab inventory item" title="Add Lab inventory item" @click="openAddInventoryModal(line)">+</button>
          <div><label class="block mb-1 text-sm">Quantity *</label><input v-model.number="line.quantity" type="number" min="0.01" step="0.01" required class="w-full p-2 border rounded" /><InputError :message="form.errors[`items.${index}.quantity`]" /></div>
          <div><label class="block mb-1 text-sm">Unit cost *</label><input v-model.number="line.unit_cost" type="number" min="0" step="0.01" required class="w-full p-2 border rounded" /><InputError :message="form.errors[`items.${index}.unit_cost`]" /></div>
          <button type="button" class="px-3 py-2 text-sm text-red-700 border rounded" @click="removeLine(index)">Remove</button>
        </div>
        <InputError :message="form.errors.items" />
        <button type="button" class="px-3 py-2 text-sm border rounded" @click="addLine">Add item</button>
        <div><label class="block mb-1 text-sm">Notes</label><textarea v-model="form.notes" rows="2" class="w-full p-2 border rounded" /></div>
        <div class="flex items-center justify-between"><strong>Order total: {{ money(total) }}</strong><button :disabled="form.processing || supplierOptions.length === 0 || itemOptions.length === 0" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded disabled:opacity-50">{{ form.processing ? 'Saving...' : 'Create Order' }}</button></div>
        <p v-if="supplierOptions.length === 0" class="text-sm text-amber-700">Use the + button beside the supplier field to add a Lab supplier.</p>
        <p v-if="itemOptions.length === 0" class="text-sm text-amber-700">Use the + button beside the item field to create a Lab inventory item.</p>
      </form>
      <div class="flex gap-2"><input v-model="search" class="p-2 text-sm border rounded" placeholder="Search order or supplier" @keyup.enter="applySearch" /><button class="px-3 py-2 text-sm text-white bg-emerald-600 rounded" @click="applySearch">Search</button><a :href="route('backend.lab.purchase-payments')" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded">Supplier Payments</a></div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm border">
          <thead class="bg-slate-50"><tr><th class="p-2 border">Order</th><th class="p-2 border">Date</th><th class="p-2 text-left border">Supplier</th><th class="p-2 border">Status</th><th class="p-2 text-right border">Total</th><th class="p-2 text-right border">Received</th><th class="p-2 text-right border">Due</th><th class="p-2 border">Actions</th></tr></thead>
          <tbody>
            <tr v-for="order in orders.data" :key="order.id">
              <td class="p-2 border">{{ order.order_no }}</td><td class="p-2 text-center border">{{ order.order_date }}</td><td class="p-2 border">{{ order.supplier_name }}</td><td class="p-2 text-center border">{{ order.status }}</td>
              <td class="p-2 text-right border">{{ money(order.total_amount) }}</td><td class="p-2 text-right border">{{ money(order.received_amount) }}</td><td class="p-2 text-right border">{{ money(Number(order.received_amount) - Number(order.paid_amount)) }}</td>
              <td class="p-2 text-center border"><div class="flex justify-center gap-2"><a v-if="order.status !== 'Received' && order.status !== 'Cancelled'" :href="route('backend.lab.purchase-orders.receive', order.id)" class="text-emerald-700 hover:underline">Receive</a><a v-if="Number(order.received_amount) > Number(order.paid_amount)" :href="route('backend.lab.purchase-payments.create', order.id)" class="text-indigo-700 hover:underline">Pay</a></div></td>
            </tr>
            <tr v-if="orders.data.length === 0"><td colspan="8" class="p-5 text-center border text-slate-500">No Lab purchase orders found.</td></tr>
          </tbody>
        </table>
      </div>
      <div class="flex justify-end gap-2"><a v-for="(link, index) in orders.links" :key="index" :href="link.url || '#'" class="px-3 py-1 text-sm border rounded" :class="link.active ? 'bg-emerald-600 text-white' : ''" v-html="link.label" /></div>
      <section>
        <h2 class="mb-2 font-semibold">Recent Goods Receipts (GRN)</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-sm border">
            <thead class="bg-slate-50"><tr><th class="p-2 border">GRN</th><th class="p-2 border">Date</th><th class="p-2 border">Purchase order</th><th class="p-2 text-left border">Supplier</th><th class="p-2 text-left border">Received items</th></tr></thead>
            <tbody>
              <tr v-for="receipt in receipts" :key="receipt.id">
                <td class="p-2 border">{{ receipt.receipt_no }}</td><td class="p-2 text-center border">{{ receipt.received_date }}</td><td class="p-2 border">{{ receipt.order_no }}</td><td class="p-2 border">{{ receipt.supplier_name }}</td>
                <td class="p-2 border"><div v-for="(item, index) in receipt.items" :key="index">{{ item.name }}: {{ money(item.quantity) }} {{ item.unit }}<span v-if="item.batch_no"> · Batch {{ item.batch_no }}</span><span v-if="item.expiry_date"> · Exp {{ item.expiry_date }}</span></div></td>
              </tr>
              <tr v-if="receipts.length === 0"><td colspan="5" class="p-4 text-center border text-slate-500">No goods receipts recorded.</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
    <Modal :show="addInventoryModal" max-width="lg" @close="addInventoryModal = false">
      <form class="p-5 space-y-4" @submit.prevent="createInventoryItem">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold">Add Lab Inventory Item</h2>
          <button type="button" class="text-xl text-slate-500" aria-label="Close" @click="addInventoryModal = false">×</button>
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div class="sm:col-span-2"><label class="block mb-1 text-sm">Item name *</label><input v-model="inventoryItemForm.name" required maxlength="255" class="w-full p-2 border rounded" /></div>
          <div><label class="block mb-1 text-sm">Category</label><input v-model="inventoryItemForm.category" maxlength="100" class="w-full p-2 border rounded" /></div>
          <div><label class="block mb-1 text-sm">Unit *</label><input v-model="inventoryItemForm.unit" required maxlength="40" class="w-full p-2 border rounded" /></div>
          <div><label class="block mb-1 text-sm">Opening stock</label><input v-model.number="inventoryItemForm.opening_stock" type="number" min="0" step="0.01" required class="w-full p-2 border rounded" /></div>
          <div><label class="block mb-1 text-sm">Reorder at</label><input v-model.number="inventoryItemForm.reorder_level" type="number" min="0" step="0.01" required class="w-full p-2 border rounded" /></div>
        </div>
        <p v-if="inventoryItemError" class="text-sm text-red-600">{{ inventoryItemError }}</p>
        <div class="flex justify-end gap-2">
          <button type="button" class="px-3 py-2 border rounded" @click="addInventoryModal = false">Cancel</button>
          <button :disabled="savingInventoryItem" class="px-4 py-2 font-semibold text-white bg-emerald-600 rounded disabled:opacity-50">{{ savingInventoryItem ? 'Saving...' : 'Save & Select Item' }}</button>
        </div>
      </form>
    </Modal>
    <Modal :show="addSupplierModal" max-width="lg" @close="addSupplierModal = false">
      <form class="p-5 space-y-4" @submit.prevent="createSupplier">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold">Add Lab Supplier</h2>
          <button type="button" class="text-xl text-slate-500" aria-label="Close" @click="addSupplierModal = false">×</button>
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div class="sm:col-span-2"><label class="block mb-1 text-sm">Supplier name *</label><input v-model="supplierForm.name" required maxlength="255" class="w-full p-2 border rounded" /></div>
          <div><label class="block mb-1 text-sm">Phone</label><input v-model="supplierForm.phone" maxlength="40" class="w-full p-2 border rounded" /></div>
          <div><label class="block mb-1 text-sm">Contact person</label><input v-model="supplierForm.contact_person" maxlength="150" class="w-full p-2 border rounded" /></div>
          <div><label class="block mb-1 text-sm">Email</label><input v-model="supplierForm.email" type="email" maxlength="190" class="w-full p-2 border rounded" /></div>
          <div><label class="block mb-1 text-sm">Address</label><input v-model="supplierForm.address" class="w-full p-2 border rounded" /></div>
        </div>
        <p v-if="supplierError" class="text-sm text-red-600">{{ supplierError }}</p>
        <div class="flex justify-end gap-2">
          <button type="button" class="px-3 py-2 border rounded" @click="addSupplierModal = false">Cancel</button>
          <button :disabled="savingSupplier" class="px-4 py-2 font-semibold text-white bg-emerald-600 rounded disabled:opacity-50">{{ savingSupplier ? 'Saving...' : 'Save & Select Supplier' }}</button>
        </div>
      </form>
    </Modal>
  </BackendLayout>
</template>
