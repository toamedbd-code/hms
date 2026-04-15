<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const APP_TIMEZONE = 'Asia/Dhaka';

const getCurrentAppDate = () => {
  // Keep default date aligned with configured app timezone, not UTC.
  return new Intl.DateTimeFormat('en-CA', { timeZone: APP_TIMEZONE }).format(new Date());
};

const normalizeDateForInput = (value) => {
  if (!value) return getCurrentAppDate();
  if (typeof value === 'string') return value.split('T')[0];

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return getCurrentAppDate();

  return new Intl.DateTimeFormat('en-CA', { timeZone: APP_TIMEZONE }).format(date);
};

const props = defineProps({
  returnType: {
    type: String,
    default: 'supplier',
  },
  return: {
    type: Object,
    default: null,
  },
  returnItems: {
    type: Array,
    default: () => [],
  },
  suppliers: {
    type: Array,
    default: () => [],
  },
  medicines: {
    type: Array,
    default: () => [],
  },
  sourceBillNo: {
    type: String,
    default: '',
  },
  sourceBillFound: {
    type: Boolean,
    default: false,
  },
  sourceBillingId: {
    type: [Number, String],
    default: null,
  },
  sourceCustomerName: {
    type: String,
    default: '',
  },
  sourceBillItems: {
    type: Array,
    default: () => [],
  },
  isEdit: {
    type: Boolean,
    default: false,
  },
});

const isSupplierMode = props.returnType === 'supplier';
const isCustomerMode = props.returnType === 'customer';

const billSearchNo = ref(props.sourceBillNo || '');

const defaultItems = [{ medicine_inventory_id: '', quantity: 1, unit_price: 0, billed_quantity: 0, condition: isSupplierMode ? 'damaged' : 'good' }];

const sourceBillDefaultItems = Array.isArray(props.sourceBillItems) && props.sourceBillItems.length
  ? props.sourceBillItems.map((item) => ({
      medicine_inventory_id: item.medicine_inventory_id,
      quantity: 0,
      unit_price: Number(item.unit_price ?? 0),
      billed_quantity: Number(item.billed_quantity ?? 0),
      condition: 'good',
    }))
  : defaultItems;

const initialItems = Array.isArray(props.returnItems) && props.returnItems.length
  ? props.returnItems
  : (Array.isArray(props.return?.return_items) ? props.return.return_items : []);

const existingItems = Array.isArray(initialItems) && initialItems.length
  ? initialItems.map((i) => ({
      medicine_inventory_id: i.medicine_inventory_id,
      quantity: i.quantity,
      unit_price: Number(i.unit_price ?? 0),
      billed_quantity: Number(i.billed_quantity ?? 0),
      condition: i.condition ?? (isSupplierMode ? 'damaged' : 'good'),
    }))
  : (props.sourceBillNo && !props.isEdit ? sourceBillDefaultItems : defaultItems);

const form = useForm({
  return_type: props.return?.return_type ?? props.returnType,
  supplier_id: props.return?.supplier_id ?? '',
  source_bill_no: props.sourceBillNo || '',
  billing_id: props.sourceBillingId || '',
  customer_name: props.sourceCustomerName || '',
  return_date: normalizeDateForInput(props.return?.return_date),
  reason: props.return?.reason ?? '',
  notes: props.return?.notes ?? '',
  items: existingItems,
  _method: props.isEdit ? 'put' : 'post',
});

const addItem = () => {
  form.items.push({ medicine_inventory_id: '', quantity: 1, unit_price: 0, condition: isSupplierMode ? 'damaged' : 'good' });
};

const removeItem = (index) => {
  if (form.items.length === 1) return;
  form.items.splice(index, 1);
};

const getMedicine = (id) => props.medicines.find((m) => String(m.id) === String(id));

const syncPriceFromMedicine = (index) => {
  const medicine = getMedicine(form.items[index].medicine_inventory_id);
  if (!medicine) return;
  form.items[index].unit_price = Number(medicine.medicine_unit_purchase_price ?? medicine.medicine_unit_selling_price ?? 0);
};

const grandTotal = computed(() => {
  return form.items.reduce((sum, item) => {
    const qty = Number(item.quantity || 0);
    const rate = Number(item.unit_price || 0);
    return sum + qty * rate;
  }, 0);
});

const submit = () => {
  if (isCustomerMode && !props.isEdit && !form.source_bill_no) {
    alert('Please search Customer Bill No first.');
    return;
  }

  if (isCustomerMode && !props.isEdit && props.sourceBillNo) {
    const selectedItems = form.items
      .map((item) => ({
        medicine_inventory_id: item.medicine_inventory_id,
        quantity: Number(item.quantity || 0),
        unit_price: Number(item.unit_price || 0),
        condition: item.condition || 'good',
      }))
      .filter((item) => item.quantity > 0);

    if (selectedItems.length === 0) {
      alert('Please enter return quantity for at least one medicine.');
      return;
    }

    form.items = selectedItems;
  }

  if (props.isEdit) {
    form.post(route('backend.productreturn.update', props.return.id));
    return;
  }

  form.post(route('backend.productreturn.store'));
};

const searchByCustomerBillNo = () => {
  if (!isCustomerMode) return;

  const normalized = (billSearchNo.value || '').trim();
  billSearchNo.value = normalized;

  router.get(route('backend.productreturn.create'), {
    return_type: 'customer',
    source_bill_no: normalized || undefined,
  }, {
    preserveState: false,
    preserveScroll: true,
    replace: true,
  });
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-2 p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ isEdit ? (isSupplierMode ? 'Edit Supplier Product Return' : 'Edit Customer Product Return') : (isSupplierMode ? 'Add Supplier Product Return' : 'Add Customer Product Return') }}</h1>
        <div class="flex items-center gap-2" v-if="isCustomerMode">
          <input
            v-model="billSearchNo"
            type="text"
            placeholder="Customer Bill No"
            class="px-3 py-2 text-sm border border-gray-300 rounded w-52"
            @keydown.enter.prevent="searchByCustomerBillNo"
          >
          <button
            type="button"
            class="px-3 py-2 text-sm text-white bg-indigo-600 rounded hover:bg-indigo-700"
            @click="searchByCustomerBillNo"
          >
            Search Bill
          </button>
          <Link :href="route('backend.productreturn.index', { return_type: props.returnType })" class="px-3 py-2 text-sm text-white bg-gray-600 rounded hover:bg-gray-700">
            Back
          </Link>
        </div>
        <Link v-else :href="route('backend.productreturn.index', { return_type: props.returnType })" class="px-3 py-2 text-sm text-white bg-gray-600 rounded hover:bg-gray-700">
          Back
        </Link>
      </div>

      <div v-if="isCustomerMode && !isEdit && sourceBillNo" class="p-3 mt-3 text-sm rounded" :class="sourceBillFound ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200'">
        <template v-if="sourceBillFound">
          Using customer bill: <span class="font-semibold">{{ sourceBillNo }}</span>. Only medicines from this bill are available for return.
          <span v-if="sourceCustomerName"> Customer: <span class="font-semibold">{{ sourceCustomerName }}</span>.</span>
        </template>
        <template v-else>
          Bill not found for: <span class="font-semibold">{{ sourceBillNo }}</span>. No medicines available.
        </template>
      </div>

      <div v-else-if="isCustomerMode && !isEdit" class="p-3 mt-3 text-sm rounded bg-blue-50 text-blue-700 border border-blue-200">
        Search customer bill number first to load billed medicines for return.
      </div>

      <form class="mt-4 space-y-4" @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
          <div>
            <InputLabel for="return_type" value="Return Type" />
            <select id="return_type" v-model="form.return_type" class="w-full p-2 border border-gray-300 rounded" disabled>
              <option value="customer">Customer</option>
              <option value="supplier">Supplier</option>
            </select>
            <InputError class="mt-1" :message="form.errors.return_type" />
          </div>

          <div v-if="isSupplierMode">
            <InputLabel for="supplier_id" value="Supplier" />
            <select id="supplier_id" v-model="form.supplier_id" class="w-full p-2 border border-gray-300 rounded">
              <option value="">Select supplier</option>
              <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                {{ supplier.name }}
              </option>
            </select>
            <InputError class="mt-1" :message="form.errors.supplier_id" />
          </div>

          <input v-model="form.source_bill_no" type="hidden" />
          <input v-model="form.billing_id" type="hidden" />
          <input v-model="form.customer_name" type="hidden" />

          <div>
            <InputLabel for="return_date" value="Return Date" />
            <input id="return_date" v-model="form.return_date" type="date" class="w-full p-2 border border-gray-300 rounded" />
            <InputError class="mt-1" :message="form.errors.return_date" />
          </div>

          <div>
            <InputLabel value="Total Amount" />
            <input :value="grandTotal.toFixed(2)" type="text" readonly class="w-full p-2 bg-gray-100 border border-gray-300 rounded" />
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm border border-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-2 border">Medicine</th>
                <th class="px-3 py-2 border">{{ isCustomerMode ? 'Billed Qty' : 'Stock Qty' }}</th>
                <th class="px-3 py-2 border">Quantity</th>
                <th class="px-3 py-2 border">Unit Price</th>
                <th class="px-3 py-2 border">Condition</th>
                <th class="px-3 py-2 border">Total</th>
                <th v-if="!sourceBillNo || isEdit || isSupplierMode" class="px-3 py-2 border">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, index) in form.items" :key="index">
                <td class="p-2 border">
                  <template v-if="isCustomerMode && sourceBillNo && !isEdit">
                    <div class="px-2 py-2 bg-gray-50 border border-gray-200 rounded">
                      {{ getMedicine(item.medicine_inventory_id)?.medicine_name || 'N/A' }}
                    </div>
                  </template>
                  <template v-else>
                    <select v-model="item.medicine_inventory_id" class="w-full p-2 border border-gray-300 rounded" @change="syncPriceFromMedicine(index)">
                      <option value="">Select medicine</option>
                      <option v-for="medicine in medicines" :key="medicine.id" :value="medicine.id">
                        {{ medicine.medicine_name }} (Stock: {{ medicine.medicine_quantity }})
                      </option>
                    </select>
                  </template>
                  <InputError class="mt-1" :message="form.errors[`items.${index}.medicine_inventory_id`]" />
                </td>
                <td class="p-2 border text-right">
                  {{ isCustomerMode ? Number(item.billed_quantity || 0).toFixed(2) : Number(getMedicine(item.medicine_inventory_id)?.medicine_quantity || 0).toFixed(2) }}
                </td>
                <td class="p-2 border">
                  <input v-model="item.quantity" type="number" min="0" :max="isCustomerMode ? (item.billed_quantity || undefined) : undefined" class="w-full p-2 border border-gray-300 rounded" />
                  <InputError class="mt-1" :message="form.errors[`items.${index}.quantity`]" />
                </td>
                <td class="p-2 border">
                  <input v-model="item.unit_price" type="number" min="0" step="0.01" class="w-full p-2 border border-gray-300 rounded" :readonly="isCustomerMode && !!sourceBillNo && !isEdit" />
                  <InputError class="mt-1" :message="form.errors[`items.${index}.unit_price`]" />
                </td>
                <td class="p-2 border">
                  <select v-model="item.condition" class="w-full p-2 border border-gray-300 rounded" :disabled="isSupplierMode">
                    <option value="good">Good</option>
                    <option value="damaged">Damaged</option>
                    <option value="expired">Expired</option>
                  </select>
                  <InputError class="mt-1" :message="form.errors[`items.${index}.condition`]" />
                </td>
                <td class="p-2 border text-right">{{ (Number(item.quantity || 0) * Number(item.unit_price || 0)).toFixed(2) }}</td>
                <td v-if="!sourceBillNo || isEdit || isSupplierMode" class="p-2 border text-center">
                  <button type="button" class="px-2 py-1 text-xs text-white bg-red-600 rounded hover:bg-red-700" @click="removeItem(index)">
                    Remove
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <button v-if="!sourceBillNo || isEdit || isSupplierMode" type="button" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded hover:bg-indigo-700" @click="addItem">
          + Add Item
        </button>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <InputLabel for="reason" value="Reason" />
            <textarea id="reason" v-model="form.reason" rows="3" class="w-full p-2 border border-gray-300 rounded"></textarea>
            <InputError class="mt-1" :message="form.errors.reason" />
          </div>
          <div>
            <InputLabel for="notes" value="Notes" />
            <textarea id="notes" v-model="form.notes" rows="3" class="w-full p-2 border border-gray-300 rounded"></textarea>
            <InputError class="mt-1" :message="form.errors.notes" />
          </div>
        </div>

        <button type="submit" class="px-4 py-2 text-white bg-emerald-600 rounded hover:bg-emerald-700" :disabled="form.processing">
          {{ isEdit ? 'Update Return' : 'Save Return' }}
        </button>
      </form>
    </div>
  </BackendLayout>
</template>
