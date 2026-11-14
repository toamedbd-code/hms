<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { useForm, Link } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import SearchableStoreItemSelect from '@/Components/SearchableStoreItemSelect.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'Configure Bill Usage' },
  reagent: { type: Object, required: true },
  isFilmItem: { type: Boolean, default: false },
  tests: { type: Array, default: () => [] },
  categoryOptions: { type: Array, default: () => [] },
  departments: { type: Array, default: () => [] },
  mappings: { type: Array, default: () => [] },
  categoryMappings: { type: Array, default: () => [] },
});

const form = useForm({
  mappings: props.mappings.map((mapping) => ({
    test_id: Number(mapping.test_id),
    department_id: mapping.department_id ? Number(mapping.department_id) : '',
    quantity_per_test: props.isFilmItem ? 1 : Number(mapping.quantity_per_test),
  })),
  category_mappings: props.categoryMappings.map((mapping) => ({
    category_key: mapping.category_key,
    department_id: mapping.department_id ? Number(mapping.department_id) : '',
    quantity_per_test: props.isFilmItem ? 1 : Number(mapping.quantity_per_test),
  })),
});

const departmentOptions = ref([...props.departments]);
const showDepartmentModal = ref(false);
const departmentName = ref('');
const departmentError = ref('');
const isSavingDepartment = ref(false);
const departmentTarget = ref(null);

function addMapping() {
  form.mappings.push({ test_id: '', department_id: '', quantity_per_test: 1 });
}

function removeMapping(index) {
  form.mappings.splice(index, 1);
}

function addCategoryMapping() {
  form.category_mappings.push({ category_key: '', department_id: '', quantity_per_test: 1 });
}

function removeCategoryMapping(index) {
  form.category_mappings.splice(index, 1);
}

function openDepartmentModal(type, index) {
  departmentTarget.value = { type, index };
  departmentName.value = '';
  departmentError.value = '';
  showDepartmentModal.value = true;
}

async function addDepartment() {
  departmentError.value = '';
  isSavingDepartment.value = true;

  try {
    const response = await axios.post(route('backend.stock.requisition.department.store'), {
      name: departmentName.value.trim(),
    });
    const createdDepartment = response.data?.department;

    if (!createdDepartment?.id) {
      throw new Error('The server did not return the created department.');
    }

    departmentOptions.value.push(createdDepartment);
    const target = departmentTarget.value;
    if (target) {
      form[target.type][target.index].department_id = Number(createdDepartment.id);
    }
    showDepartmentModal.value = false;
    departmentTarget.value = null;
  } catch (error) {
    if (error.response?.status === 422) {
      departmentError.value = error.response.data?.errors?.name?.[0] ?? 'Enter a valid department name.';
    } else {
      departmentError.value = error.response?.data?.message ?? error.message ?? 'Could not create department.';
    }
  } finally {
    isSavingDepartment.value = false;
  }
}

function availableCategories(index) {
  const selectedKeys = form.category_mappings
    .filter((_, rowIndex) => rowIndex !== index)
    .map((mapping) => mapping.category_key);

  return props.categoryOptions.filter((category) => !selectedKeys.includes(category.category_key));
}

function availableTests(index) {
  const selectedIds = form.mappings
    .filter((_, rowIndex) => rowIndex !== index)
    .map((mapping) => Number(mapping.test_id));

  return props.tests.filter((test) => !selectedIds.includes(Number(test.id)));
}

function formatTest(test) {
  return `${test.test_name} (${test.category_type})`;
}

function submit() {
  form.post(route('backend.stock.reagent-usage.update', props.reagent.id));
}
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">{{ pageTitle }}</h1>
          <p class="mt-1 text-sm text-slate-600">{{ reagent.item_name }} · {{ reagent.category || 'Uncategorized' }} · {{ reagent.stock_behavior || 'consumable' }}</p>
          <p v-if="isFilmItem" class="mt-1 text-sm text-sky-800">Configure a default by test category. If a particular test needs a different film size, add a test-specific mapping below; that test will use its selected size instead of the category default. Inventory remains tracked separately by size.</p>
          <p v-else class="mt-1 text-sm text-sky-800">Set a quantity by category to apply it to every billed test in that category, or configure individual tests below. A test-specific rule takes priority over its category rule. Issue stock to the selected department through Store Requisitions.</p>
          <p v-if="reagent.stock_behavior === 'reusable'" class="mt-1 text-sm text-amber-700">Reusable devices are recorded as configured but are not deducted per bill. Set a genuinely single-use device or supply to Consumable in Store Item Setup.</p>
        </div>
        <Link :href="route('backend.stock.index')" class="px-3 py-2 text-sm border rounded">Back to Store Inventory</Link>
      </div>

      <form class="mt-4" @submit.prevent="submit">
        <h2 class="mb-2 text-lg font-semibold text-slate-800">Category-wise usage</h2>
        <div class="overflow-visible">
          <table class="w-full text-sm border border-slate-200">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-3 py-2 text-left border">Test Category</th>
                <th class="px-3 py-2 text-left border">Department</th>
                <th class="px-3 py-2 text-right border">{{ reagent.unit }} per test</th>
                <th class="px-3 py-2 text-center border">Remove</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(mapping, index) in form.category_mappings" :key="`category-${index}`">
                <td class="p-2 border">
                  <select v-model="mapping.category_key" class="w-full p-2 border rounded">
                    <option value="" disabled>Select category</option>
                    <option v-for="category in availableCategories(index)" :key="category.category_key" :value="category.category_key">
                      {{ category.label }}
                    </option>
                  </select>
                  <InputError class="mt-1" :message="form.errors[`category_mappings.${index}.category_key`]" />
                </td>
                <td class="p-2 border">
                  <div class="flex gap-2">
                    <select v-model="mapping.department_id" class="w-full p-2 border rounded">
                      <option value="" disabled>Select department</option>
                      <option v-for="department in departmentOptions" :key="department.id" :value="department.id">
                        {{ department.name }}
                      </option>
                    </select>
                    <button type="button" class="px-3 text-sm font-semibold text-white bg-emerald-600 rounded" title="Add Department" @click="openDepartmentModal('category_mappings', index)">+</button>
                  </div>
                  <InputError class="mt-1" :message="form.errors[`category_mappings.${index}.department_id`]" />
                </td>
                <td class="p-2 border">
                  <span v-if="isFilmItem" class="block text-right text-slate-700">1 {{ reagent.unit }} per billed test</span>
                  <template v-else>
                    <input v-model.number="mapping.quantity_per_test" type="number" min="0.01" step="0.01" class="w-full p-2 text-right border rounded" />
                    <InputError class="mt-1" :message="form.errors[`category_mappings.${index}.quantity_per_test`]" />
                  </template>
                </td>
                <td class="p-2 text-center border">
                  <button type="button" class="text-red-700 hover:underline" @click="removeCategoryMapping(index)">Remove</button>
                </td>
              </tr>
              <tr v-if="form.category_mappings.length === 0">
                <td colspan="4" class="px-3 py-4 text-center text-slate-500">No category usage configured.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <InputError class="mt-2" :message="form.errors.category_mappings" />
        <button type="button" class="px-3 py-2 mt-3 text-sm border rounded" @click="addCategoryMapping">Add Category</button>

        <h2 class="mt-6 mb-2 text-lg font-semibold text-slate-800">{{ isFilmItem ? 'Test-specific film size (optional override)' : 'Individual test usage (optional override)' }}</h2>
        <div class="overflow-visible">
          <table class="w-full text-sm border border-slate-200">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-3 py-2 text-left border">Diagnostic Test</th>
                <th class="px-3 py-2 text-left border">Department</th>
                <th class="px-3 py-2 text-right border">{{ reagent.unit }} per test</th>
                <th class="px-3 py-2 text-center border">Remove</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(mapping, index) in form.mappings" :key="index">
                <td class="p-2 border">
                  <SearchableStoreItemSelect
                    v-model="mapping.test_id"
                    :items="availableTests(index)"
                    :format-option="formatTest"
                    placeholder="Search a test by name or category"
                    drop-up
                    class="min-w-64"
                  />
                  <InputError class="mt-1" :message="form.errors[`mappings.${index}.test_id` ]" />
                </td>
                <td class="p-2 border">
                  <div class="flex gap-2">
                    <select v-model="mapping.department_id" class="w-full p-2 border rounded">
                      <option value="" disabled>Select department</option>
                      <option v-for="department in departmentOptions" :key="department.id" :value="department.id">
                        {{ department.name }}
                      </option>
                    </select>
                    <button type="button" class="px-3 text-sm font-semibold text-white bg-emerald-600 rounded" title="Add Department" @click="openDepartmentModal('mappings', index)">+</button>
                  </div>
                  <InputError class="mt-1" :message="form.errors[`mappings.${index}.department_id` ]" />
                </td>
                <td class="p-2 border">
                  <span v-if="isFilmItem" class="block text-right text-slate-700">1 {{ reagent.unit }} per billed test</span>
                  <template v-else>
                  <input
                    v-model.number="mapping.quantity_per_test"
                    type="number"
                    min="0.01"
                    step="0.01"
                    class="w-full p-2 text-right border rounded"
                  />
                  <InputError class="mt-1" :message="form.errors[`mappings.${index}.quantity_per_test` ]" />
                  </template>
                </td>
                <td class="p-2 text-center border">
                  <button type="button" class="text-red-700 hover:underline" @click="removeMapping(index)">Remove</button>
                </td>
              </tr>
              <tr v-if="form.mappings.length === 0">
                <td colspan="4" class="px-3 py-6 text-center text-slate-500">No test usage configured for this item.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <InputError class="mt-2" :message="form.errors.mappings" />

        <div class="flex flex-wrap justify-between gap-2 mt-4">
          <button type="button" class="px-3 py-2 text-sm border rounded" @click="addMapping">Add Test</button>
          <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded hover:bg-indigo-700" :disabled="form.processing">
            {{ form.processing ? 'Saving...' : 'Save Usage' }}
          </button>
        </div>
      </form>
    </div>

    <Modal :show="showDepartmentModal" max-width="md" @close="showDepartmentModal = false">
      <form class="p-6" @submit.prevent="addDepartment">
        <h2 class="text-lg font-semibold text-slate-800">Add Department</h2>
        <p class="mt-1 text-sm text-slate-600">The department will also be available in the Department module and other Store Management forms.</p>
        <label for="new-department-name" class="block mt-4 mb-1 text-sm font-medium text-slate-700">Department Name</label>
        <input
          id="new-department-name"
          v-model="departmentName"
          type="text"
          maxlength="255"
          required
          autofocus
          class="w-full p-2 border rounded"
          placeholder="Enter department name"
        />
        <p v-if="departmentError" class="mt-2 text-sm text-red-600">{{ departmentError }}</p>
        <div class="flex justify-end gap-2 mt-5">
          <button type="button" class="px-3 py-2 text-sm border rounded" :disabled="isSavingDepartment" @click="showDepartmentModal = false">Cancel</button>
          <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded" :disabled="isSavingDepartment">
            {{ isSavingDepartment ? 'Saving...' : 'Save Department' }}
          </button>
        </div>
      </form>
    </Modal>
  </BackendLayout>
</template>