<script setup>
import { onMounted, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import SearchableTextInput from '@/Components/SearchableTextInput.vue';
import { displayWarning } from '@/responseMessage.js';

const props = defineProps({
  pageTitle: { type: String, default: 'Store Item Setup' },
  initialCategory: { type: String, default: '' },
  categories: { type: Array, default: () => [] },
  units: { type: Array, default: () => [] },
  itemNames: { type: Array, default: () => [] },
  createdItem: { type: Object, default: null },
});

const availableCategories = ref([...props.categories]);
const availableUnits = ref([...new Set(['pcs', ...props.units])]);
const availableItemNames = ref([...props.itemNames]);
const stockBehaviorOptions = [
  { label: 'Consumable (deduct by bill usage)', value: 'consumable' },
  { label: 'Reusable asset (department stock only)', value: 'reusable' },
];
const showCategoryModal = ref(false);
const newCategory = ref('');
const categoryError = ref('');
const showUnitModal = ref(false);
const newUnit = ref('');
const unitError = ref('');

const form = useForm({
  item_code: '',
  item_name: '',
  category: props.initialCategory,
  stock_behavior: ['device', 'equipment', 'instrument', 'machine'].some((type) => props.initialCategory.toLowerCase().includes(type)) ? 'reusable' : 'consumable',
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
  notify_opener: new URLSearchParams(window.location.search).get('notify_opener') === '1',
});

const addCategory = () => {
  const category = newCategory.value.trim();
  categoryError.value = '';

  if (!category) {
    categoryError.value = 'Enter a category name.';
    return;
  }

  const existingCategory = availableCategories.value.find(
    (value) => value.toLocaleLowerCase() === category.toLocaleLowerCase(),
  );

  if (existingCategory) {
    form.category = existingCategory;
  } else {
    availableCategories.value.push(category);
    availableCategories.value.sort((a, b) => a.localeCompare(b));
    form.category = category;
  }

  newCategory.value = '';
  showCategoryModal.value = false;
};

const addUnit = () => {
  const unit = newUnit.value.trim();
  unitError.value = '';

  if (!unit) {
    unitError.value = 'Enter a unit name.';
    return;
  }

  const existingUnit = availableUnits.value.find(
    (value) => value.toLocaleLowerCase() === unit.toLocaleLowerCase(),
  );

  if (existingUnit) {
    form.unit = existingUnit;
  } else {
    availableUnits.value.push(unit);
    availableUnits.value.sort((a, b) => a.localeCompare(b));
    form.unit = unit;
  }

  newUnit.value = '';
  showUnitModal.value = false;
};

onMounted(() => {
  if (!form.notify_opener || !props.createdItem?.id || !window.opener) return;

  window.opener.postMessage({
    type: 'store-item-created',
    item: props.createdItem,
  }, window.location.origin);
  window.close();
});

const submit = () => {
  form.post(route('backend.stock.item.store'), {
    onError: (errorObject) => displayWarning(errorObject),
  });
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
        <a :href="route('backend.stock.index')" class="px-3 py-2 text-sm font-semibold text-white bg-red-600 rounded hover:bg-red-700">Back</a>
      </div>

      <form class="grid grid-cols-1 gap-3 mt-4 md:grid-cols-2" @submit.prevent="submit">
        <div>
          <label class="block mb-1 text-sm font-semibold text-slate-700">Item Code (Optional)</label>
          <input v-model="form.item_code" type="text" class="w-full p-2 text-sm border rounded" placeholder="Auto-generated if empty" />
          <InputError class="mt-1" :message="form.errors.item_code" />
        </div>

        <div>
          <label class="block mb-1 text-sm font-semibold text-slate-700">Item Name</label>
          <SearchableTextInput v-model="form.item_name" :options="availableItemNames" placeholder="Search or enter item name" />
          <InputError class="mt-1" :message="form.errors.item_name" />
        </div>

        <div>
          <label class="block mb-1 text-sm font-semibold text-slate-700">Category</label>
          <div class="flex items-center gap-2">
            <SearchableTextInput v-model="form.category" :options="availableCategories" placeholder="Search or enter category" />
            <button
              type="button"
              class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded bg-emerald-600 text-xl font-semibold leading-none text-white hover:bg-emerald-700"
              aria-label="Add category"
              title="Add category"
              @click="showCategoryModal = true"
            >+</button>
          </div>
          <InputError class="mt-1" :message="form.errors.category" />
        </div>

        <div>
          <label class="block mb-1 text-sm font-semibold text-slate-700">Stock Behavior</label>
          <SearchableTextInput
            v-model="form.stock_behavior"
            :options="stockBehaviorOptions"
            placeholder="Search stock behavior"
            :allow-custom="false"
          />
          <InputError class="mt-1" :message="form.errors.stock_behavior" />
        </div>

        <div>
          <label class="block mb-1 text-sm font-semibold text-slate-700">Opening Batch / Lot</label>
          <input v-model="form.batch_no" type="text" class="w-full p-2 text-sm border rounded" placeholder="Supplier batch or lot" />
          <InputError class="mt-1" :message="form.errors.batch_no" />
        </div>

        <div v-if="form.stock_behavior === 'consumable'">
          <label class="block mb-1 text-sm font-semibold text-slate-700">Expiry Date</label>
          <input v-model="form.expiry_date" type="date" class="w-full p-2 text-sm border rounded" />
          <InputError class="mt-1" :message="form.errors.expiry_date" />
        </div>

        <div v-if="form.stock_behavior === 'reusable'">
          <label class="block mb-1 text-sm font-semibold text-slate-700">Serial Number</label>
          <input v-model="form.serial_number" type="text" class="w-full p-2 text-sm border rounded" />
          <InputError class="mt-1" :message="form.errors.serial_number" />
        </div>

        <div v-if="form.stock_behavior === 'reusable'">
          <label class="block mb-1 text-sm font-semibold text-slate-700">Asset Tag</label>
          <input v-model="form.asset_tag" type="text" class="w-full p-2 text-sm border rounded" />
          <InputError class="mt-1" :message="form.errors.asset_tag" />
        </div>

        <div>
          <label class="block mb-1 text-sm font-semibold text-slate-700">Unit</label>
          <div class="flex items-center gap-2">
            <SearchableTextInput v-model="form.unit" :options="availableUnits" placeholder="Search or enter unit" />
            <button
              type="button"
              class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded bg-emerald-600 text-xl font-semibold leading-none text-white hover:bg-emerald-700"
              aria-label="Add unit"
              title="Add unit"
              @click="showUnitModal = true"
            >+</button>
          </div>
          <InputError class="mt-1" :message="form.errors.unit" />
        </div>

        <div>
          <label class="block mb-1 text-sm font-semibold text-slate-700">Opening Stock</label>
          <input v-model.number="form.current_stock" type="number" min="0" step="0.01" class="w-full p-2 text-sm border rounded" />
          <InputError class="mt-1" :message="form.errors.current_stock" />
        </div>

        <div>
          <label class="block mb-1 text-sm font-semibold text-slate-700">Unit Cost</label>
          <input v-model.number="form.unit_cost" type="number" min="0" step="0.01" class="w-full p-2 text-sm border rounded" />
          <InputError class="mt-1" :message="form.errors.unit_cost" />
        </div>

        <div>
          <label class="block mb-1 text-sm font-semibold text-slate-700">Reorder Level</label>
          <input v-model.number="form.reorder_level" type="number" min="0" step="0.01" class="w-full p-2 text-sm border rounded" />
          <InputError class="mt-1" :message="form.errors.reorder_level" />
        </div>

        <div>
          <label class="block mb-1 text-sm font-semibold text-slate-700">Status</label>
          <select v-model="form.status" class="w-full p-2 text-sm border rounded">
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
          </select>
          <InputError class="mt-1" :message="form.errors.status" />
        </div>

        <div class="md:col-span-2">
          <label class="block mb-1 text-sm font-semibold text-slate-700">Notes</label>
          <textarea v-model="form.notes" rows="3" class="w-full p-2 text-sm border rounded" placeholder="Any additional details"></textarea>
          <InputError class="mt-1" :message="form.errors.notes" />
        </div>

        <div class="md:col-span-2 flex justify-end">
          <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded hover:bg-indigo-700" :disabled="form.processing">
            {{ form.processing ? 'Saving...' : 'Save Store Item' }}
          </button>
        </div>
      </form>
    </div>

    <Modal :show="showCategoryModal" max-width="md" @close="showCategoryModal = false">
      <form class="p-6" @submit.prevent="addCategory">
        <h2 class="text-lg font-semibold text-slate-800">Add Category</h2>
        <label for="new-store-item-category" class="block mt-4 mb-1 text-sm font-semibold text-slate-700">Category name</label>
        <input
          id="new-store-item-category"
          v-model="newCategory"
          type="text"
          maxlength="120"
          required
          autofocus
          class="w-full p-2 text-sm border rounded"
          placeholder="Enter category name"
        />
        <p v-if="categoryError" class="mt-2 text-sm text-rose-600">{{ categoryError }}</p>
        <div class="flex justify-end gap-2 mt-5">
          <button type="button" class="px-4 py-2 text-sm font-semibold text-slate-700 bg-slate-100 rounded hover:bg-slate-200" @click="showCategoryModal = false">Cancel</button>
          <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Add Category</button>
        </div>
      </form>
    </Modal>

    <Modal :show="showUnitModal" max-width="md" @close="showUnitModal = false">
      <form class="p-6" @submit.prevent="addUnit">
        <h2 class="text-lg font-semibold text-slate-800">Add Unit</h2>
        <label for="new-store-item-unit" class="block mt-4 mb-1 text-sm font-semibold text-slate-700">Unit name</label>
        <input
          id="new-store-item-unit"
          v-model="newUnit"
          type="text"
          maxlength="50"
          required
          autofocus
          class="w-full p-2 text-sm border rounded"
          placeholder="Enter unit name"
        />
        <p v-if="unitError" class="mt-2 text-sm text-rose-600">{{ unitError }}</p>
        <div class="flex justify-end gap-2 mt-5">
          <button type="button" class="px-4 py-2 text-sm font-semibold text-slate-700 bg-slate-100 rounded hover:bg-slate-200" @click="showUnitModal = false">Cancel</button>
          <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Add Unit</button>
        </div>
      </form>
    </Modal>
  </BackendLayout>
</template>
