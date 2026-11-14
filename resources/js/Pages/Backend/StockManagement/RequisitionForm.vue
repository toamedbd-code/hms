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
  pageTitle: { type: String, default: 'Create Department Requisition' },
  items: { type: Array, default: () => [] },
  departments: { type: Array, default: () => [] },
  defaultDepartmentId: { type: Number, default: null },
  reagentOnly: { type: Boolean, default: false },
  initialStoreItemId: { type: Number, default: null },
});

const form = useForm({
  department_id: props.defaultDepartmentId || '',
  reagent_only: props.reagentOnly,
  needed_date: '',
  notes: '',
  items: [
    { store_item_id: props.initialStoreItemId || '', requested_qty: 1, remarks: '' },
  ],
});

const availableDepartments = ref([...props.departments]);
watch(() => props.departments, (departments) => {
  availableDepartments.value = [...departments];
});

const showDepartmentModal = ref(false);
const departmentName = ref('');
const departmentError = ref('');
const isSavingDepartment = ref(false);

const addDepartment = async () => {
  departmentError.value = '';
  isSavingDepartment.value = true;

  try {
    const response = await axios.post(route('backend.stock.requisition.department.store'), {
      name: departmentName.value,
    });
    const createdDepartment = response.data?.department;

    if (!createdDepartment?.id) {
      throw new Error('The server did not return the created department.');
    }

    availableDepartments.value.push(createdDepartment);
    form.department_id = createdDepartment.id;
    departmentName.value = '';
    showDepartmentModal.value = false;
  } catch (error) {
    if (error.response?.status === 422) {
      departmentError.value = error.response.data?.errors?.name?.[0] ?? 'Enter a valid department name.';
    } else {
      departmentError.value = error.response?.data?.message ?? error.message ?? 'Could not create department.';
    }
  } finally {
    isSavingDepartment.value = false;
  }
};

const addRow = () => {
  form.items.push({ store_item_id: '', requested_qty: 1, remarks: '' });
};

const removeRow = (idx) => {
  if (form.items.length <= 1) return;
  form.items.splice(idx, 1);
};

const itemById = computed(() => {
  const map = new Map();
  props.items.forEach((item) => map.set(Number(item.id), item));
  return map;
});
const formatDepartment = (department) => department.name;

const submit = () => {
  form.post(route('backend.stock.requisition.store'), {
    onError: (errorObject) => displayWarning(errorObject),
  });
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
        <a :href="route('backend.stock.requisitions')" class="px-3 py-2 text-sm font-semibold text-white bg-red-600 rounded hover:bg-red-700">Back</a>
      </div>

      <div v-if="reagentOnly" class="p-4 mt-4 border border-teal-200 rounded-lg bg-teal-50">
        <h2 class="font-semibold text-teal-900">From Central Store to Lab</h2>
        <p class="mt-1 text-sm text-teal-800">This indent requests stock already received in Central Store. Choose the Lab department and the quantity to transfer. After submitting, Store must approve the indent; approval moves stock into Lab and makes it available to billing. If the central balance is insufficient, order and receive the reagent first.</p>
        <p v-if="!defaultDepartmentId" class="mt-2 text-sm font-semibold text-amber-800">No default department selected. Make sure you choose Lab before submitting.</p>
        <Link :href="route('backend.stock.purchase-orders.create', { reagent_only: 1, store_item_id: initialStoreItemId || undefined })" class="inline-flex mt-3 px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Purchase / receive reagent first</Link>
      </div>
      <div v-else class="p-4 mt-4 border border-cyan-200 rounded-lg bg-cyan-50 text-sm text-cyan-950">
        Request any active Central Store item, including tissue, soap, pens, notebooks, pads and other routine supplies. Stock moves only when Store approves the requisition; approval reduces Central Store quantity and adds the issued quantity to this department's inventory.
      </div>

      <form @submit.prevent="submit" class="mt-4 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div>
            <label class="block mb-1 text-sm font-semibold text-slate-700">Department</label>
            <div class="flex items-center gap-2">
              <SearchableStoreItemSelect
                v-model="form.department_id"
                :items="availableDepartments"
                :format-option="formatDepartment"
                placeholder="Search or select department"
                class="min-w-0 flex-1"
              />
              <button type="button" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded bg-emerald-600 text-xl font-semibold leading-none text-white hover:bg-emerald-700" aria-label="Add Department" title="Add Department" @click="showDepartmentModal = true">+</button>
            </div>
            <InputError class="mt-1" :message="form.errors.department_id" />
          </div>

          <div>
            <label class="block mb-1 text-sm font-semibold text-slate-700">Needed Date</label>
            <input v-model="form.needed_date" type="date" class="w-full p-2 text-sm border rounded" />
            <InputError class="mt-1" :message="form.errors.needed_date" />
          </div>

          <div>
            <label class="block mb-1 text-sm font-semibold text-slate-700">Note</label>
            <input v-model="form.notes" type="text" class="w-full p-2 text-sm border rounded" placeholder="Optional note" />
            <InputError class="mt-1" :message="form.errors.notes" />
          </div>
        </div>

        <div class="border rounded p-3">
          <div class="flex justify-between items-center mb-3">
            <h2 class="font-semibold text-slate-700">{{ reagentOnly ? 'Reagent Indent Items' : 'Requisition Items' }}</h2>
            <button type="button" class="px-3 py-1 text-sm rounded bg-blue-600 text-white hover:bg-blue-700" @click="addRow">Add Row</button>
          </div>

          <div class="space-y-2">
            <div v-for="(row, idx) in form.items" :key="idx" class="grid grid-cols-1 md:grid-cols-12 gap-2 bg-slate-50 p-2 rounded border">
              <div class="md:col-span-5">
                <select v-model="row.store_item_id" class="w-full p-2 text-sm border rounded">
                  <option value="">Select {{ reagentOnly ? 'Reagent' : 'Item' }}</option>
                  <option v-for="item in items" :key="item.id" :value="item.id">
                    {{ item.item_name }}{{ item.category ? ` · ${item.category}` : '' }} (Central stock: {{ item.current_stock }} {{ item.unit }})
                  </option>
                </select>
              </div>

              <div class="md:col-span-2">
                <input v-model.number="row.requested_qty" type="number" min="0.01" step="0.01" class="w-full p-2 text-sm border rounded" placeholder="Qty" />
              </div>

              <div class="md:col-span-4">
                <input v-model="row.remarks" type="text" class="w-full p-2 text-sm border rounded" placeholder="Remarks" />
              </div>

              <div class="md:col-span-1 flex items-center">
                <button type="button" class="w-full p-2 text-xs text-white bg-rose-600 rounded hover:bg-rose-700" @click="removeRow(idx)">Remove</button>
              </div>

              <div class="md:col-span-12 text-xs text-slate-500" v-if="row.store_item_id && itemById.get(Number(row.store_item_id))">
                Available: {{ Number(itemById.get(Number(row.store_item_id)).current_stock ?? 0).toFixed(2) }} {{ itemById.get(Number(row.store_item_id)).unit }}
              </div>
            </div>
          </div>

          <InputError class="mt-2" :message="form.errors.items" />
        </div>

        <div class="flex justify-end">
          <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700" :disabled="form.processing">
            {{ form.processing ? 'Submitting...' : 'Submit Requisition' }}
          </button>
        </div>
      </form>
    </div>

    <Modal :show="showDepartmentModal" @close="showDepartmentModal = false">
      <form class="p-6" @submit.prevent="addDepartment">
        <h2 class="text-lg font-semibold text-slate-800">Add Department</h2>
        <label for="new-department-name" class="block mt-4 mb-1 text-sm font-semibold text-slate-700">Department name</label>
        <input id="new-department-name" v-model="departmentName" type="text" maxlength="255" required autofocus class="w-full p-2 text-sm border rounded" placeholder="Enter department name" />
        <p v-if="departmentError" class="mt-2 text-sm text-rose-600">{{ departmentError }}</p>
        <div class="flex justify-end gap-2 mt-5">
          <button type="button" class="px-4 py-2 text-sm font-semibold text-slate-700 bg-slate-100 rounded hover:bg-slate-200" :disabled="isSavingDepartment" @click="showDepartmentModal = false">Cancel</button>
          <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700 disabled:opacity-50" :disabled="isSavingDepartment">
            {{ isSavingDepartment ? 'Saving...' : 'Save Department' }}
          </button>
        </div>
      </form>
    </Modal>
  </BackendLayout>
</template>
