<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
  purchase: {
    type: Object,
    default: null,
  },
  suppliers: {
    type: Array,
    default: () => [],
  },
  categories: {
    type: Array,
    default: () => [],
  },
  medicines: {
    type: Array,
    default: () => [],
  },
  isEdit: {
    type: Boolean,
    default: false,
  },
});

const normalizedItems = Array.isArray(props.purchase?.purchase_items) && props.purchase.purchase_items.length
  ? props.purchase.purchase_items.map((item) => ({
      medicine_category_id: item.medicine_category_id ?? '',
      medicine_name: item.medicine_name ?? '',
      batch_no: item.batch_no ?? '',
      expiry_date: (item.expiry_date ?? '').toString().split('T')[0],
      quantity: Number(item.quantity ?? 1),
      unit_purchase_price: Number(item.unit_purchase_price ?? 0),
      unit_selling_price: Number(item.unit_selling_price ?? 0),
      discount: Number(item.discount ?? 0),
      received_quantity: Number(item.received_quantity ?? 0),
    }))
  : [{
      medicine_category_id: '',
      medicine_name: '',
      batch_no: '',
      expiry_date: '',
      quantity: 1,
      unit_purchase_price: 0,
      unit_selling_price: 0,
      discount: 0,
      received_quantity: 0,
    }];

const form = useForm({
  supplier_id: props.purchase?.supplier_id ?? '',
  purchase_date: (props.purchase?.purchase_date ?? '').toString().split('T')[0],
  invoice_number: props.purchase?.invoice_number ?? '',
  initial_paid_amount: Number(props.purchase?.paid_amount ?? 0),
  notes: props.purchase?.notes ?? '',
  items: normalizedItems,
  _method: props.isEdit ? 'put' : 'post',
});

const addItem = () => {
  form.items.push({
    medicine_category_id: '',
    medicine_name: '',
    batch_no: '',
    expiry_date: '',
    quantity: 1,
    unit_purchase_price: 0,
    unit_selling_price: 0,
    discount: 0,
    received_quantity: 0,
  });
};

const removeItem = (index) => {
  if (form.items.length === 1) return;
  form.items.splice(index, 1);
};

const medicineOptionsBySupplierAndCategory = computed(() => {
  const grouped = {};

  props.medicines.forEach((medicine) => {
    const categoryKey = `${medicine.medicine_category_id ?? ''}`;
    const allKey = 'all';

    if (!grouped[categoryKey]) {
      grouped[categoryKey] = [];
    }

    if (!grouped[allKey]) {
      grouped[allKey] = [];
    }

    const option = {
      name: medicine.medicine_name,
      stock: Number(medicine.current_stock ?? 0),
      unitPrice: Number(medicine.unit_purchase_price ?? 0),
      sellingPrice: Number(medicine.unit_selling_price ?? 0),
    };

    grouped[categoryKey].push(option);
    grouped[allKey].push(option);
  });

  Object.keys(grouped).forEach((key) => {
    grouped[key] = grouped[key]
      .filter((item, index, arr) => index === arr.findIndex((x) => x.name === item.name))
      .sort((a, b) => (a.name ?? '').localeCompare(b.name ?? ''));
  });

  return grouped;
});

const getMedicineOptions = (categoryId) => {
  if (categoryId) {
    return medicineOptionsBySupplierAndCategory.value[`${categoryId}`] ?? [];
  }

  return medicineOptionsBySupplierAndCategory.value.all ?? [];
};

const getSelectedMedicine = (item) => {
  return getMedicineOptions(item.medicine_category_id).find((medicine) => medicine.name === item.medicine_name) ?? null;
};

const onCategoryChange = (index) => {
  form.items[index].medicine_name = '';
};

const onSupplierChange = () => {
  form.items = form.items.map((item) => ({
    ...item,
    medicine_name: '',
  }));
};

const onMedicineChange = (index) => {
  const selected = getSelectedMedicine(form.items[index]);
  if (!selected) return;

  if (!Number(form.items[index].unit_purchase_price)) {
    form.items[index].unit_purchase_price = selected.unitPrice;
  }

  if (!Number(form.items[index].unit_selling_price)) {
    form.items[index].unit_selling_price = selected.sellingPrice || selected.unitPrice;
  }
};

const grandTotal = computed(() => {
  return form.items.reduce((sum, item) => {
    const qty = Number(item.quantity || 0);
    const unitPrice = Number(item.unit_purchase_price || 0);
    const gross = qty * unitPrice;
    const discount = Math.max(0, Number(item.discount || 0));
    return sum + Math.max(0, gross - discount);
  }, 0);
});

const dueAmount = computed(() => {
  const paid = Number(form.initial_paid_amount || 0);
  return Math.max(0, grandTotal.value - paid);
});

const submit = () => {
  if (props.isEdit) {
    form.post(route('backend.medicinepurchase.update', props.purchase.id));
    return;
  }

  form.post(route('backend.medicinepurchase.store'));
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-2 p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ isEdit ? 'Edit Purchase Product' : 'Add Purchase Product' }}</h1>
        <Link :href="route('backend.medicinepurchase.index')" class="px-3 py-2 text-sm text-white bg-gray-600 rounded hover:bg-gray-700">
          Back
        </Link>
      </div>

      <form class="mt-4 space-y-4" @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
          <div>
            <InputLabel for="supplier_id" value="Supplier" />
            <select id="supplier_id" v-model="form.supplier_id" class="w-full p-2 border border-gray-300 rounded" @change="onSupplierChange">
              <option value="">Select Supplier</option>
              <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                {{ supplier.name }}
              </option>
            </select>
            <InputError class="mt-1" :message="form.errors.supplier_id" />
          </div>

          <div>
            <InputLabel for="purchase_date" value="Purchase Date" />
            <input id="purchase_date" v-model="form.purchase_date" type="date" class="w-full p-2 border border-gray-300 rounded">
            <InputError class="mt-1" :message="form.errors.purchase_date" />
          </div>

          <div>
            <InputLabel for="invoice_number" value="Invoice Number" />
            <input id="invoice_number" v-model="form.invoice_number" type="text" class="w-full p-2 border border-gray-300 rounded" placeholder="Enter invoice number">
            <InputError class="mt-1" :message="form.errors.invoice_number" />
          </div>

          <div>
            <InputLabel value="Total Amount" />
            <input :value="grandTotal.toFixed(2)" readonly type="text" class="w-full p-2 bg-gray-100 border border-gray-300 rounded">
          </div>

          <div>
            <InputLabel for="initial_paid_amount" value="Initial Payment" />
            <input id="initial_paid_amount" v-model="form.initial_paid_amount" type="number" min="0" step="0.01" class="w-full p-2 border border-gray-300 rounded">
            <InputError class="mt-1" :message="form.errors.initial_paid_amount" />
          </div>

          <div>
            <InputLabel value="Due Amount" />
            <input :value="dueAmount.toFixed(2)" readonly type="text" class="w-full p-2 bg-gray-100 border border-gray-300 rounded">
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm border border-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-2 border">Category</th>
                <th class="px-3 py-2 border">Medicine</th>
                <th class="px-3 py-2 border">Current Stock</th>
                <th class="px-3 py-2 border">Batch No</th>
                <th class="px-3 py-2 border">Expiry Date</th>
                <th class="px-3 py-2 border">Quantity</th>
                <th class="px-3 py-2 border">Unit Purchase Price</th>
                <th class="px-3 py-2 border">Unit Selling Price</th>
                <th class="px-3 py-2 border">Discount</th>
                <th class="px-3 py-2 border">Total</th>
                <th class="px-3 py-2 border">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, index) in form.items" :key="index">
                <td class="p-2 border">
                  <select v-model="item.medicine_category_id" class="w-full p-2 border border-gray-300 rounded" @change="onCategoryChange(index)">
                    <option value="">Select Category</option>
                    <option v-for="category in categories" :key="category.id" :value="category.id">
                      {{ category.medicine_category_name ?? category.name }}
                    </option>
                  </select>
                  <InputError class="mt-1" :message="form.errors[`items.${index}.medicine_category_id`]" />
                </td>

                <td class="p-2 border">
                  <select v-model="item.medicine_name" class="w-full p-2 border border-gray-300 rounded" @change="onMedicineChange(index)">
                    <option value="">Select Medicine</option>
                    <option v-for="medicine in getMedicineOptions(item.medicine_category_id)" :key="`${item.medicine_category_id}-${medicine.name}`" :value="medicine.name">
                      {{ medicine.name }}
                    </option>
                  </select>
                  <InputError class="mt-1" :message="form.errors[`items.${index}.medicine_name`]" />
                </td>

                <td class="p-2 border text-center">
                  <span class="inline-block px-2 py-1 text-xs rounded bg-blue-50 text-blue-700">
                    {{ Number(getSelectedMedicine(item)?.stock ?? 0).toFixed(2) }}
                  </span>
                </td>

                <td class="p-2 border">
                  <input v-model="item.batch_no" type="text" class="w-full p-2 border border-gray-300 rounded" placeholder="Batch No">
                  <InputError class="mt-1" :message="form.errors[`items.${index}.batch_no`]" />
                </td>

                <td class="p-2 border">
                  <input v-model="item.expiry_date" type="date" class="w-full p-2 border border-gray-300 rounded">
                  <InputError class="mt-1" :message="form.errors[`items.${index}.expiry_date`]" />
                </td>

                <td class="p-2 border">
                  <input v-model="item.quantity" type="number" min="1" class="w-full p-2 border border-gray-300 rounded">
                  <InputError class="mt-1" :message="form.errors[`items.${index}.quantity`]" />
                </td>

                <td class="p-2 border">
                  <input v-model="item.unit_purchase_price" type="number" min="0" step="0.01" class="w-full p-2 border border-gray-300 rounded">
                  <InputError class="mt-1" :message="form.errors[`items.${index}.unit_purchase_price`]" />
                </td>

                <td class="p-2 border">
                  <input v-model="item.unit_selling_price" type="number" min="0" step="0.01" class="w-full p-2 border border-gray-300 rounded">
                  <InputError class="mt-1" :message="form.errors[`items.${index}.unit_selling_price`]" />
                </td>

                <td class="p-2 border">
                  <input v-model="item.discount" type="number" min="0" step="0.01" class="w-full p-2 border border-gray-300 rounded">
                  <InputError class="mt-1" :message="form.errors[`items.${index}.discount`]" />
                </td>

                <td class="p-2 border text-right">
                  {{ Math.max(0, (Number(item.quantity || 0) * Number(item.unit_purchase_price || 0)) - Number(item.discount || 0)).toFixed(2) }}
                </td>

                <td class="p-2 border text-center">
                  <button type="button" class="px-2 py-1 text-xs text-white bg-red-600 rounded hover:bg-red-700" @click="removeItem(index)">
                    Remove
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <button type="button" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded hover:bg-indigo-700" @click="addItem">
          + Add Item
        </button>

        <div>
          <InputLabel for="notes" value="Notes" />
          <textarea id="notes" v-model="form.notes" rows="3" class="w-full p-2 border border-gray-300 rounded"></textarea>
          <InputError class="mt-1" :message="form.errors.notes" />
        </div>

        <button type="submit" class="px-4 py-2 text-white bg-emerald-600 rounded hover:bg-emerald-700" :disabled="form.processing">
          {{ isEdit ? 'Update Purchase' : 'Save Purchase' }}
        </button>
      </form>
    </div>
  </BackendLayout>
</template>
