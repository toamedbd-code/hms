<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Multiselect from 'vue-multiselect';
import { displayWarning, successAlert } from '@/responseMessage.js';

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

const medicineOptionsByCategory = ref({});
const loadingMedicineOptions = ref({});
const medicineOptionErrors = ref({});
const categorySelectRefs = ref([]);

const loadMedicineOptions = async (categoryId) => {
  const key = String(categoryId ?? '');
  if (!key || Object.prototype.hasOwnProperty.call(medicineOptionsByCategory.value, key)) return;
  if (loadingMedicineOptions.value[key]) return;

  loadingMedicineOptions.value[key] = true;
  medicineOptionErrors.value[key] = '';

  try {
    const response = await axios.get(route('backend.medicinepurchase.medicine-options'), {
      params: { medicine_category_id: key },
    });
    medicineOptionsByCategory.value[key] = (response.data?.medicines ?? []).map((medicine) => ({
      name: medicine.medicine_name,
      stock: Number(medicine.current_stock ?? 0),
      unitPrice: Number(medicine.unit_purchase_price ?? 0),
      sellingPrice: Number(medicine.unit_selling_price ?? 0),
    }));
  } catch (error) {
    medicineOptionErrors.value[key] = error.response?.data?.message
      || 'Could not load medicines for this category. Please try again.';
  } finally {
    loadingMedicineOptions.value[key] = false;
  }
};

onMounted(() => {
  const categoryIds = [...new Set(form.items.map((item) => String(item.medicine_category_id ?? '')).filter(Boolean))];
  categoryIds.forEach((categoryId) => loadMedicineOptions(categoryId));
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

const setCategorySelectRef = (index, element) => {
  categorySelectRefs.value[index] = element;
};

const addItemAndFocus = async (index = form.items.length - 1) => {
  addItem();
  await nextTick();
  const categorySelect = categorySelectRefs.value[index + 1];
  categorySelect?.activate?.();
  await nextTick();
  categorySelect?.$el?.querySelector('.multiselect__input')?.focus();
};

const removeItem = (index) => {
  if (form.items.length === 1) return;
  form.items.splice(index, 1);
};

const supplierOptions = computed(() => {
  return props.suppliers.map((supplier) => ({
    id: supplier.id,
    name: supplier.name,
  }));
});

const categoryOptions = computed(() => {
  return props.categories.map((category) => ({
    id: category.id,
    name: category.medicine_category_name ?? category.name,
  }));
});

const selectedSupplier = computed({
  get: () => supplierOptions.value.find((supplier) => String(supplier.id) === String(form.supplier_id)) ?? null,
  set: (supplier) => {
    form.supplier_id = supplier?.id ?? '';
    onSupplierChange();
  },
});

const getMedicineOptions = (categoryId) => {
  return medicineOptionsByCategory.value[String(categoryId ?? '')] ?? [];
};

const getSelectedCategory = (item) => {
  return categoryOptions.value.find((category) => String(category.id) === String(item.medicine_category_id)) ?? null;
};

const setSelectedCategory = (index, category) => {
  form.items[index].medicine_category_id = category?.id ?? '';
  onCategoryChange(index);
};

const getSelectedMedicineOption = (item) => {
  return getMedicineOptions(item.medicine_category_id).find((medicine) => medicine.name === item.medicine_name) ?? null;
};

const setSelectedMedicine = (index, medicine) => {
  form.items[index].medicine_name = medicine?.name ?? '';
  onMedicineChange(index);
};

const getSelectedMedicine = (item) => {
  return getSelectedMedicineOption(item);
};

const onCategoryChange = (index) => {
  form.items[index].medicine_name = '';
  loadMedicineOptions(form.items[index].medicine_category_id);
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
  const successFallbackMessage = props.isEdit ? 'Purchase updated successfully' : 'Purchase created successfully';
  const submitOptions = {
    onSuccess: (response) => {
      successAlert('Success', response?.props?.flash?.successMessage || successFallbackMessage);
    },
    onError: (errorObject) => {
      displayWarning(errorObject);
    },
  };

  if (props.isEdit) {
    form.post(route('backend.medicinepurchase.update', props.purchase.id), submitOptions);
    return;
  }

  form.post(route('backend.medicinepurchase.store'), submitOptions);
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-2 p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ isEdit ? 'Edit Purchase Product' : 'Add Purchase Product' }}</h1>
        <Link :href="route('backend.medicinepurchase.index')" class="px-3 py-2 text-sm text-white bg-red-600 rounded hover:bg-red-700">
          Back
        </Link>
      </div>

      <form class="mt-4 space-y-4" @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
          <div>
            <InputLabel for="supplier_id" value="Supplier" />
            <Multiselect
              id="supplier_id"
              v-model="selectedSupplier"
              class="purchase-multiselect"
              :options="supplierOptions"
              label="name"
              track-by="id"
              placeholder="Select Supplier"
              :searchable="true"
              :allow-empty="true"
              :show-labels="false"
              :max-height="220"
              open-direction="bottom"
            />
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

        <div class="overflow-x-auto purchase-items-scroll">
          <div class="purchase-items-scroll__inner">
          <table class="w-full table-fixed text-xs border border-gray-200 purchase-items-table">
            <colgroup>
              <col class="w-[145px]">
              <col class="w-[160px]">
              <col class="w-[65px]">
              <col class="w-[100px]">
              <col class="w-[115px]">
              <col class="w-[70px]">
              <col class="w-[100px]">
              <col class="w-[100px]">
              <col class="w-[80px]">
              <col class="w-[75px]">
              <col class="w-[75px]">
            </colgroup>
            <thead class="bg-gray-50">
              <tr>
                <th class="px-1 py-2 border break-words">Category</th>
                <th class="px-1 py-2 border break-words">Medicine</th>
                <th class="px-1 py-2 border break-words">Current Stock</th>
                <th class="px-1 py-2 border break-words">Batch No</th>
                <th class="px-1 py-2 border break-words">Expiry Date</th>
                <th class="px-1 py-2 border break-words">Quantity</th>
                <th class="px-1 py-2 border break-words">Unit Purchase Price</th>
                <th class="px-1 py-2 border break-words">Unit Selling Price</th>
                <th class="px-1 py-2 border break-words">Discount</th>
                <th class="px-1 py-2 border break-words">Total</th>
                <th class="px-1 py-2 border break-words">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, index) in form.items" :key="index">
                <td class="p-1 border">
                  <Multiselect
                    :ref="(element) => setCategorySelectRef(index, element)"
                    class="purchase-multiselect"
                    :model-value="getSelectedCategory(item)"
                    :options="categoryOptions"
                    label="name"
                    track-by="id"
                    placeholder="Select Category"
                    :searchable="true"
                    :allow-empty="true"
                    :show-labels="false"
                    :max-height="220"
                    open-direction="bottom"
                    @update:model-value="setSelectedCategory(index, $event)"
                  />
                  <InputError class="mt-1" :message="form.errors[`items.${index}.medicine_category_id`]" />
                </td>

                <td class="p-1 border">
                  <Multiselect
                    class="purchase-multiselect"
                    :model-value="getSelectedMedicineOption(item)"
                    :options="getMedicineOptions(item.medicine_category_id)"
                    :loading="loadingMedicineOptions[String(item.medicine_category_id)]"
                    label="name"
                    track-by="name"
                    placeholder="Select Medicine"
                    :searchable="true"
                    :allow-empty="true"
                    :show-labels="false"
                    :max-height="220"
                    open-direction="bottom"
                    @update:model-value="setSelectedMedicine(index, $event)"
                  >
                    <template #option="{ option }">
                      <div class="flex items-center justify-between gap-2">
                        <span>{{ option.name }}</span>
                        <span class="text-xs text-gray-500">Stock: {{ Number(option.stock ?? 0).toFixed(2) }}</span>
                      </div>
                    </template>
                    <template #singleLabel="{ option }">
                      <span>{{ option.name }}</span>
                    </template>
                    <template #noResult>
                      {{ medicineOptionErrors[String(item.medicine_category_id)] || (loadingMedicineOptions[String(item.medicine_category_id)] ? 'Loading medicines...' : 'No medicines found') }}
                    </template>
                    <template #noOptions>
                      {{ medicineOptionErrors[String(item.medicine_category_id)] || (loadingMedicineOptions[String(item.medicine_category_id)] ? 'Loading medicines...' : 'Select a category first') }}
                    </template>
                  </Multiselect>
                  <InputError class="mt-1" :message="form.errors[`items.${index}.medicine_name`]" />
                </td>

                <td class="p-1 border text-center">
                  <span class="inline-block px-2 py-1 text-xs rounded bg-blue-50 text-blue-700">
                    {{ Number(getSelectedMedicine(item)?.stock ?? 0).toFixed(2) }}
                  </span>
                </td>

                <td class="p-1 border">
                  <input v-model="item.batch_no" type="text" class="w-full min-w-0 p-1.5 text-xs border border-gray-300 rounded" placeholder="Batch No">
                  <InputError class="mt-1" :message="form.errors[`items.${index}.batch_no`]" />
                </td>

                <td class="p-1 border">
                  <input v-model="item.expiry_date" type="date" class="w-full min-w-0 p-1.5 text-xs border border-gray-300 rounded">
                  <InputError class="mt-1" :message="form.errors[`items.${index}.expiry_date`]" />
                </td>

                <td class="p-1 border">
                  <input v-model="item.quantity" type="number" min="1" class="w-full min-w-0 p-1.5 text-xs border border-gray-300 rounded">
                  <InputError class="mt-1" :message="form.errors[`items.${index}.quantity`]" />
                </td>

                <td class="p-1 border">
                  <input v-model="item.unit_purchase_price" type="number" min="0" step="0.01" class="w-full min-w-0 p-1.5 text-xs border border-gray-300 rounded">
                  <InputError class="mt-1" :message="form.errors[`items.${index}.unit_purchase_price`]" />
                </td>

                <td class="p-1 border">
                  <input v-model="item.unit_selling_price" type="number" min="0" step="0.01" class="w-full min-w-0 p-1.5 text-xs border border-gray-300 rounded">
                  <InputError class="mt-1" :message="form.errors[`items.${index}.unit_selling_price`]" />
                </td>

                <td class="p-1 border">
                  <input v-model="item.discount" type="number" min="0" step="0.01" class="w-full min-w-0 p-1.5 text-xs border border-gray-300 rounded" @keydown.enter.prevent="addItemAndFocus(index)">
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
        </div>

        <button type="button" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded hover:bg-indigo-700" @click="addItemAndFocus()">
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

<style src="vue-multiselect/dist/vue-multiselect.css"></style>

<style>
.purchase-items-table {
  min-width: 1085px;
}

.purchase-multiselect .multiselect__tags {
  min-height: 40px;
  height: auto;
  padding: 8px 32px 8px 8px;
  border-color: rgb(209 213 219);
  border-radius: 0.25rem;
}

.purchase-multiselect .multiselect__single {
  position: relative;
  display: block;
  height: auto;
  margin-bottom: 0;
  overflow: visible;
  white-space: normal;
  overflow-wrap: anywhere;
  text-overflow: clip;
  line-height: 1.25;
}

.purchase-multiselect .multiselect__input,
.purchase-multiselect .multiselect__placeholder {
  max-width: 100%;
  margin-bottom: 0;
  white-space: normal;
  overflow-wrap: anywhere;
}

.purchase-multiselect .multiselect__content-wrapper {
  max-height: 220px !important;
  overflow-y: auto;
}

.purchase-items-scroll__inner {
  min-width: max-content;
  padding-bottom: 14rem;
}

.purchase-items-scroll td {
  position: relative;
}

.purchase-multiselect.multiselect--active {
  z-index: 40;
}

.purchase-multiselect.multiselect--active .multiselect__content-wrapper {
  z-index: 50;
}

.purchase-multiselect .multiselect__content-wrapper {
  min-width: 100%;
  max-width: min(320px, 80vw);
}

.purchase-multiselect .multiselect__input,
.purchase-multiselect .multiselect__single,
.purchase-multiselect .multiselect__placeholder {
  font-size: 0.75rem;
}
</style>
