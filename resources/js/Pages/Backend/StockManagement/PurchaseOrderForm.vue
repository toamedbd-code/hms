<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import SearchableStoreItemSelect from '@/Components/SearchableStoreItemSelect.vue';
import { displayWarning } from '@/responseMessage.js';

const props = defineProps({
  pageTitle: { type: String, default: 'Create Purchase Order' },
  suppliers: { type: Array, default: () => [] },
  items: { type: Array, default: () => [] },
  reagentOnly: { type: Boolean, default: false },
  initialStoreItemId: { type: Number, default: null },
  order: { type: Object, default: null },
});

const itemOptions = ref([...props.items]);
const supplierOptions = ref([...props.suppliers]);
watch(() => props.items, (items) => {
  itemOptions.value = [...items];
});
watch(() => props.suppliers, (suppliers) => {
  supplierOptions.value = [...suppliers];
});

const form = useForm({
  store_supplier_id: props.order?.store_supplier_id ?? '',
  order_date: props.order?.order_date ?? new Date().toISOString().split('T')[0],
  expected_delivery_date: props.order?.expected_delivery_date ?? '',
  notes: props.order?.notes ?? '',
  reagent_only: props.reagentOnly,
  items: props.order?.items?.length
    ? props.order.items.map((line) => ({
        store_item_id: line.store_item_id,
        quantity: Number(line.quantity),
        unit_cost: Number(line.unit_cost),
      }))
    : [{
        store_item_id: props.initialStoreItemId || '',
        quantity: 1,
        unit_cost: Number(itemOptions.value.find((item) => Number(item.id) === Number(props.initialStoreItemId))?.unit_cost ?? 0),
      }],
});

const addOrderRow = () => form.items.push({ store_item_id: '', quantity: 1, unit_cost: 0 });
const removeRow = (index) => {
  if (form.items.length > 1) form.items.splice(index, 1);
};
const formatItem = (item) => `${item.item_name} · ${item.unit}`;
const formatSupplier = (supplier) => supplier.name;
const selectItem = (row, item) => {
  if (item) row.unit_cost = Number(item.unit_cost ?? 0);
};
const selectedItem = (row) => itemOptions.value.find((item) => Number(item.id) === Number(row.store_item_id));
const total = computed(() => form.items.reduce((sum, row) => sum + Number(row.quantity ?? 0) * Number(row.unit_cost ?? 0), 0));

const showSupplierModal = ref(false);
const isSavingSupplier = ref(false);
const supplierError = ref('');
const supplierForm = ref({
  name: '',
  phone: '',
  contact_person_name: '',
  contact_person_phone: '',
  address: '',
  notes: '',
});

const showItemModal = ref(false);
const isSavingItem = ref(false);
const itemError = ref('');
const itemForm = ref({});
const itemTargetRow = ref(null);
const newItemDefaults = () => ({
  item_code: '',
  item_name: '',
  category: props.reagentOnly ? 'Reagent' : '',
  stock_behavior: 'consumable',
  batch_no: '',
  expiry_date: '',
  serial_number: '',
  asset_tag: '',
  unit: 'pcs',
  reorder_level: 10,
  current_stock: 0,
  unit_cost: 0,
  status: 'Active',
  notes: '',
});
itemForm.value = newItemDefaults();

const openItemModal = (row) => {
  itemError.value = '';
  itemForm.value = newItemDefaults();
  itemTargetRow.value = row;
  showItemModal.value = true;
};

const createStoreItem = async () => {
  itemError.value = '';
  isSavingItem.value = true;

  try {
    const response = await axios.post(route('backend.stock.item.store'), itemForm.value);
    const createdItem = response.data?.item;

    if (!createdItem?.id) {
      throw new Error('The server did not return the created store item.');
    }

    itemOptions.value.push(createdItem);
    const targetRow = itemTargetRow.value && form.items.includes(itemTargetRow.value)
      ? itemTargetRow.value
      : [...form.items].reverse().find((row) => !row.store_item_id);
    const row = targetRow || { store_item_id: '', quantity: 1, unit_cost: 0 };
    if (!targetRow) form.items.push(row);
    row.store_item_id = createdItem.id;
    row.unit_cost = Number(createdItem.unit_cost ?? 0);
    itemTargetRow.value = null;
    showItemModal.value = false;
  } catch (error) {
    if (error.response?.status === 422) {
      itemError.value = Object.values(error.response.data?.errors ?? {}).flat()[0] ?? 'Enter valid store item details.';
    } else {
      itemError.value = error.response?.data?.message ?? error.message ?? 'Could not create store item.';
    }
  } finally {
    isSavingItem.value = false;
  }
};

const addSupplier = async () => {
  supplierError.value = '';
  isSavingSupplier.value = true;

  try {
    const response = await axios.post(route('backend.storesupplier.quick'), supplierForm.value);
    const supplier = response.data?.supplier;

    if (!supplier?.id) {
      throw new Error('The server did not return the created supplier.');
    }

    supplierOptions.value.push(supplier);
    form.store_supplier_id = supplier.id;
    supplierForm.value = {
      name: '',
      phone: '',
      contact_person_name: '',
      contact_person_phone: '',
      address: '',
      notes: '',
    };
    showSupplierModal.value = false;
  } catch (error) {
    if (error.response?.status === 422) {
      supplierError.value = Object.values(error.response.data?.errors ?? {}).flat()[0] ?? 'Enter valid supplier details.';
    } else {
      supplierError.value = error.response?.data?.message ?? error.message ?? 'Could not create supplier.';
    }
  } finally {
    isSavingSupplier.value = false;
  }
};

const submit = () => {
  const options = { onError: (errors) => displayWarning(errors) };
  if (props.order?.id) {
    form.put(route('backend.stock.purchase-orders.update', props.order.id), options);
    return;
  }
  form.post(route('backend.stock.purchase-orders.store'), options);
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <div>
          <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
          <p v-if="order?.purchase_order_no" class="mt-1 text-sm text-slate-600">{{ order.purchase_order_no }} · Edit supplier, dates, notes or ordered items</p>
        </div>
        <div class="flex gap-2">
          <a :href="route('backend.stock.item.create')" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Store Item Setup</a>
          <a :href="route('backend.storesupplier.index')" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Store Suppliers</a>
          <a :href="route('backend.stock.purchase-orders')" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Back</a>
        </div>
      </div>

      <div v-if="reagentOnly" class="p-4 mt-4 border border-indigo-200 rounded-lg bg-indigo-50">
        <h2 class="font-semibold text-indigo-900">Reagent purchase workflow</h2>
        <p class="mt-1 text-sm text-indigo-800">Choose the supplier and order quantity, then save the purchase order. Open the order and select <strong>Receive</strong> to record the delivered batch and expiry date into Central Store. After receipt, create a reagent indent to send stock to the Lab.</p>
        <p v-if="initialStoreItemId" class="mt-2 text-sm font-medium text-indigo-900">Selected reagent: {{ itemOptions.find((item) => Number(item.id) === Number(initialStoreItemId))?.item_name || 'Reagent' }}</p>
      </div>

      <form class="mt-4 space-y-4" @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
          <div>
            <label class="block mb-1 text-sm font-semibold">Supplier</label>
            <div class="flex items-center gap-2">
              <SearchableStoreItemSelect
                v-model="form.store_supplier_id"
                :items="supplierOptions"
                :format-option="formatSupplier"
                placeholder="Search or select supplier"
                class="min-w-0 flex-1"
              />
              <button type="button" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded bg-emerald-600 text-xl font-semibold leading-none text-white hover:bg-emerald-700" aria-label="Add Store Supplier" title="Add Store Supplier" @click="showSupplierModal = true">+</button>
            </div>
            <InputError :message="form.errors.store_supplier_id" />
          </div>
          <div>
            <label class="block mb-1 text-sm font-semibold">Order date</label>
            <input v-model="form.order_date" type="date" class="w-full p-2 text-sm border rounded" />
            <InputError :message="form.errors.order_date" />
          </div>
          <div>
            <label class="block mb-1 text-sm font-semibold">Expected delivery</label>
            <input v-model="form.expected_delivery_date" type="date" class="w-full p-2 text-sm border rounded" />
            <InputError :message="form.errors.expected_delivery_date" />
          </div>
          <div>
            <label class="block mb-1 text-sm font-semibold">Notes</label>
            <input v-model="form.notes" type="text" class="w-full p-2 text-sm border rounded" />
          </div>
        </div>

        <div class="p-3 border rounded">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
              <button type="button" class="inline-flex h-7 w-7 items-center justify-center rounded bg-emerald-600 text-lg font-semibold leading-none text-white hover:bg-emerald-700" aria-label="Create Store Item" title="Create Store Item" @click="openItemModal()">+</button>
              <h2 class="font-semibold">{{ reagentOnly ? 'Reagent items' : 'Order items' }}</h2>
            </div>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="px-3 py-1 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700" @click="addOrderRow">New Order</button>
            </div>
          </div>
          <div v-for="(row, index) in form.items" :key="index" class="grid grid-cols-1 gap-2 p-2 mb-2 border rounded sm:grid-cols-12">
            <SearchableStoreItemSelect
              v-model="row.store_item_id"
              :items="itemOptions"
              :format-option="formatItem"
              placeholder="Search or select store item"
              class="sm:col-span-5"
              @select="selectItem(row, $event)"
            />
            <input v-model.number="row.quantity" type="number" :step="selectedItem(row)?.stock_behavior === 'reusable' ? 1 : 0.01" :min="selectedItem(row)?.stock_behavior === 'reusable' ? 1 : 0.01" class="p-2 text-sm border rounded sm:col-span-2" placeholder="Quantity" />
            <input v-model.number="row.unit_cost" type="number" min="0" step="0.01" class="p-2 text-sm border rounded sm:col-span-2" placeholder="Unit cost" />
            <div class="flex items-center justify-end sm:col-span-2">{{ (Number(row.quantity || 0) * Number(row.unit_cost || 0)).toFixed(2) }}</div>
            <button type="button" class="px-2 py-1 text-sm text-white bg-rose-600 rounded sm:col-span-1" @click="removeRow(index)">X</button>
            <InputError class="sm:col-span-12" :message="form.errors[`items.${index}.store_item_id`] || form.errors[`items.${index}.quantity`] || form.errors[`items.${index}.unit_cost`]" />
          </div>
          <InputError :message="form.errors.items" />
          <div class="text-right font-semibold">Order total: {{ total.toFixed(2) }}</div>
        </div>

        <div class="flex justify-end">
          <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded" :disabled="form.processing">
            {{ form.processing ? 'Saving...' : order?.id ? 'Update Purchase Order' : 'Create Purchase Order' }}
          </button>
        </div>
      </form>
    </div>

    <Modal :show="showItemModal" max-width="2xl" @close="showItemModal = false">
      <form class="max-h-[85vh] overflow-y-auto p-6" @submit.prevent="createStoreItem">
        <h2 class="text-lg font-semibold text-slate-800">Create Store Item</h2>
        <div class="grid grid-cols-1 gap-3 mt-4 sm:grid-cols-2">
          <div>
            <label for="new-store-item-code" class="block mb-1 text-sm font-semibold text-slate-700">Item Code</label>
            <input id="new-store-item-code" v-model="itemForm.item_code" type="text" maxlength="50" class="w-full p-2 text-sm border rounded" placeholder="Auto-generated if empty" />
          </div>
          <div>
            <label for="new-store-item-name" class="block mb-1 text-sm font-semibold text-slate-700">Item Name *</label>
            <input id="new-store-item-name" v-model="itemForm.item_name" type="text" maxlength="255" required class="w-full p-2 text-sm border rounded" />
          </div>
          <div>
            <label for="new-store-item-category" class="block mb-1 text-sm font-semibold text-slate-700">Category</label>
            <input id="new-store-item-category" v-model="itemForm.category" type="text" maxlength="120" :readonly="reagentOnly" class="w-full p-2 text-sm border rounded read-only:bg-slate-100" :placeholder="reagentOnly ? 'Reagent' : 'Reagent / Film / Device'" />
          </div>
          <div>
            <label for="new-store-item-behavior" class="block mb-1 text-sm font-semibold text-slate-700">Stock Behavior *</label>
            <select id="new-store-item-behavior" v-model="itemForm.stock_behavior" required class="w-full p-2 text-sm border rounded">
              <option value="consumable">Consumable</option>
              <option value="reusable">Reusable asset</option>
            </select>
          </div>
          <div>
            <label for="new-store-item-unit" class="block mb-1 text-sm font-semibold text-slate-700">Unit *</label>
            <input id="new-store-item-unit" v-model="itemForm.unit" type="text" maxlength="50" required class="w-full p-2 text-sm border rounded" />
          </div>
          <div>
            <label for="new-store-item-cost" class="block mb-1 text-sm font-semibold text-slate-700">Unit Cost *</label>
            <input id="new-store-item-cost" v-model.number="itemForm.unit_cost" type="number" min="0" step="0.01" required class="w-full p-2 text-sm border rounded" />
          </div>
          <div>
            <label for="new-store-item-stock" class="block mb-1 text-sm font-semibold text-slate-700">Opening Stock *</label>
            <input id="new-store-item-stock" v-model.number="itemForm.current_stock" type="number" min="0" step="0.01" required class="w-full p-2 text-sm border rounded" />
          </div>
          <div>
            <label for="new-store-item-reorder" class="block mb-1 text-sm font-semibold text-slate-700">Reorder Level *</label>
            <input id="new-store-item-reorder" v-model.number="itemForm.reorder_level" type="number" min="0" step="0.01" required class="w-full p-2 text-sm border rounded" />
          </div>
          <div>
            <label for="new-store-item-batch" class="block mb-1 text-sm font-semibold text-slate-700">Opening Batch / Lot</label>
            <input id="new-store-item-batch" v-model="itemForm.batch_no" type="text" maxlength="100" class="w-full p-2 text-sm border rounded" />
          </div>
          <div v-if="itemForm.stock_behavior === 'consumable'">
            <label for="new-store-item-expiry" class="block mb-1 text-sm font-semibold text-slate-700">Expiry Date</label>
            <input id="new-store-item-expiry" v-model="itemForm.expiry_date" type="date" class="w-full p-2 text-sm border rounded" />
          </div>
          <template v-if="itemForm.stock_behavior === 'reusable'">
            <div>
              <label for="new-store-item-serial" class="block mb-1 text-sm font-semibold text-slate-700">Serial Number</label>
              <input id="new-store-item-serial" v-model="itemForm.serial_number" type="text" maxlength="120" class="w-full p-2 text-sm border rounded" />
            </div>
            <div>
              <label for="new-store-item-asset-tag" class="block mb-1 text-sm font-semibold text-slate-700">Asset Tag</label>
              <input id="new-store-item-asset-tag" v-model="itemForm.asset_tag" type="text" maxlength="120" class="w-full p-2 text-sm border rounded" />
            </div>
          </template>
          <div class="sm:col-span-2">
            <label for="new-store-item-notes" class="block mb-1 text-sm font-semibold text-slate-700">Notes</label>
            <textarea id="new-store-item-notes" v-model="itemForm.notes" rows="2" class="w-full p-2 text-sm border rounded"></textarea>
          </div>
        </div>
        <p v-if="itemError" class="mt-3 text-sm text-rose-600">{{ itemError }}</p>
        <div class="flex justify-end gap-2 mt-5">
          <button type="button" class="px-4 py-2 text-sm font-semibold text-slate-700 bg-slate-100 rounded hover:bg-slate-200" :disabled="isSavingItem" @click="showItemModal = false">Cancel</button>
          <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700 disabled:opacity-50" :disabled="isSavingItem">
            {{ isSavingItem ? 'Saving...' : 'Create Item' }}
          </button>
        </div>
      </form>
    </Modal>

    <Modal :show="showSupplierModal" max-width="lg" @close="showSupplierModal = false">
      <form class="p-6" @submit.prevent="addSupplier">
        <h2 class="text-lg font-semibold text-slate-800">Add Store Supplier</h2>
        <p class="mt-1 text-sm text-slate-500">This supplier is only for Store Management.</p>
        <div class="grid grid-cols-1 gap-3 mt-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label for="quick-supplier-name" class="block mb-1 text-sm font-semibold text-slate-700">Supplier name *</label>
            <input id="quick-supplier-name" v-model="supplierForm.name" type="text" maxlength="255" required autofocus class="w-full p-2 text-sm border rounded" />
          </div>
          <div>
            <label for="quick-supplier-phone" class="block mb-1 text-sm font-semibold text-slate-700">Phone</label>
            <input id="quick-supplier-phone" v-model="supplierForm.phone" type="text" maxlength="255" class="w-full p-2 text-sm border rounded" />
          </div>
          <div>
            <label for="quick-supplier-contact" class="block mb-1 text-sm font-semibold text-slate-700">Contact person</label>
            <input id="quick-supplier-contact" v-model="supplierForm.contact_person_name" type="text" maxlength="255" class="w-full p-2 text-sm border rounded" />
          </div>
          <div>
            <label for="quick-supplier-contact-phone" class="block mb-1 text-sm font-semibold text-slate-700">Contact phone</label>
            <input id="quick-supplier-contact-phone" v-model="supplierForm.contact_person_phone" type="text" maxlength="255" class="w-full p-2 text-sm border rounded" />
          </div>
          <div>
            <label for="quick-supplier-address" class="block mb-1 text-sm font-semibold text-slate-700">Address</label>
            <input id="quick-supplier-address" v-model="supplierForm.address" type="text" maxlength="255" class="w-full p-2 text-sm border rounded" />
          </div>
          <div class="sm:col-span-2">
            <label for="quick-supplier-notes" class="block mb-1 text-sm font-semibold text-slate-700">Notes</label>
            <textarea id="quick-supplier-notes" v-model="supplierForm.notes" rows="2" class="w-full p-2 text-sm border rounded"></textarea>
          </div>
        </div>
        <p v-if="supplierError" class="mt-3 text-sm text-rose-600">{{ supplierError }}</p>
        <div class="flex justify-end gap-2 mt-5">
          <button type="button" class="px-4 py-2 text-sm font-semibold text-slate-700 bg-slate-100 rounded hover:bg-slate-200" :disabled="isSavingSupplier" @click="showSupplierModal = false">Cancel</button>
          <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700 disabled:opacity-50" :disabled="isSavingSupplier">
            {{ isSavingSupplier ? 'Saving...' : 'Save Supplier' }}
          </button>
        </div>
      </form>
    </Modal>
  </BackendLayout>
</template>
