<script setup>
import { ref, watch, defineProps, defineEmits } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { displayResponse, displayWarning, showToastIfNoFlash } from '@/responseMessage.js';

// Import modal components (you'll need to import these in your actual component)
import ChargeTypeModal from '@/Components/ChargeTypeModal.vue';
import ChargeCategoryModal from '@/Components/ChargeCategoryModal.vue';
import UnitTypeModal from '@/Components/UnitTypeModal.vue';
import TaxCategoryModal from '@/Components/TaxCategoryModal.vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false
    },
    charge: {
        type: Object,
        default: null
    },
    chargeTypes: {
        type: Array,
        default: () => []
    },
    chargeCategories: {
        type: Array,
        default: () => []
    },
    chargeUnits: {
        type: Array,
        default: () => []
    },
    taxCategories: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['close', 'created', 'updated']);

// Form setup
const form = useForm({
    name: '',
    charge_type_id: '',
    charge_category_id: '',
    unit_type_id: '',
    tax_category_id: '',
    tax: '',
    standard_charge: '',
    description: '',
    _method: 'post',
});

// Modal visibility states
const modals = ref({
    chargeType: false,
    chargeCategory: false,
    unitType: false,
    taxCategory: false
});

// Data for modals
const modalData = ref({
    chargeTypes: [...props.chargeTypes],
    chargeCategories: [...props.chargeCategories],
    chargeUnits: [...props.chargeUnits],
    taxCategories: [...props.taxCategories]
});

// Watch for prop changes to update form
watch(() => props.charge, (newCharge) => {
    if (newCharge) {
        form.name = newCharge.name || '';
        form.charge_type_id = newCharge.charge_type_id || '';
        form.charge_category_id = newCharge.charge_category_id || '';
        form.unit_type_id = newCharge.unit_type_id || '';
        form.tax_category_id = newCharge.tax_category_id || '';
        form.tax = newCharge.tax || '';
        form.standard_charge = newCharge.standard_charge || '';
        form.description = newCharge.description || '';
        form._method = 'put';
    } else {
        form.reset();
        form._method = 'post';
    }
}, { immediate: true });

// Watch for prop arrays to update modal data
watch(() => props.chargeTypes, (newTypes) => {
    modalData.value.chargeTypes = [...newTypes];
});

watch(() => props.chargeCategories, (newCategories) => {
    modalData.value.chargeCategories = [...newCategories];
});

watch(() => props.chargeUnits, (newUnits) => {
    modalData.value.chargeUnits = [...newUnits];
});

watch(() => props.taxCategories, (newTaxCategories) => {
    modalData.value.taxCategories = [...newTaxCategories];
});

// Function to update tax percentage when tax category is selected
const updateTaxPercentage = () => {
    if (form.tax_category_id) {
        const selectedTaxCategory = modalData.value.taxCategories.find(
            category => category.id == form.tax_category_id
        );
        if (selectedTaxCategory) {
            form.tax = selectedTaxCategory.percentage;
        }
    } else {
        form.tax = '';
    }
};

// Modal event handlers
const openModal = (modalName) => {
    modals.value[modalName] = true;
};

const closeSubModal = (modalName) => {
    modals.value[modalName] = false;
};

const handleChargeTypeCreated = (response) => {
    router.reload({
        only: ['chargeTypes'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            modalData.value.chargeTypes = page.props.chargeTypes || [];
            showToastIfNoFlash(response);
        }
    });
};

const handleChargeCategoryCreated = (response) => {
    router.reload({
        only: ['chargeCategories'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            modalData.value.chargeCategories = page.props.chargeCategories || [];
            showToastIfNoFlash(response);
        }
    });
};

const handleUnitTypeCreated = (response) => {
    router.reload({
        only: ['chargeUnits'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            modalData.value.chargeUnits = page.props.chargeUnits || [];
            showToastIfNoFlash(response);
        }
    });
};

const handleTaxCategoryCreated = (response) => {
    router.reload({
        only: ['taxCategories'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            modalData.value.taxCategories = page.props.taxCategories || [];
            showToastIfNoFlash(response);
        }
    });
};

const submit = () => {
    const routeName = props.charge?.id 
        ? route('backend.hospitalcharge.update', props.charge.id) 
        : route('backend.hospitalcharge.store');
    
    form.transform(data => ({
        ...data,
        remember: '',
        isDirty: false,
    })).post(routeName, {
        onSuccess: (response) => {
            if (!props.charge?.id) {
                form.reset();
            }
            emit(props.charge?.id ? 'updated' : 'created', response);
            closeModal();
        },
        onError: (errorObject) => {
            displayWarning(errorObject);
        },
    });
};

const closeModal = () => {
    form.reset();
    form.clearErrors();
    emit('close');
};
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog"
        aria-modal="true">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" aria-hidden="true"
                @click="closeModal"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div
                class="inline-block w-full max-w-6xl px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:p-6 dark:bg-gray-800">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white" id="modal-title">
                        {{ charge?.id ? 'Edit Hospital Charge' : 'Create Hospital Charge' }}
                    </h3>
                    <button @click="closeModal"
                        class="text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600 dark:hover:text-gray-300"
                        aria-label="Close">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- First Row -->
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4">
                        <div class="col-span-1">
                            <label for="charge_type_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Charge Type *
                            </label>
                            <div class="flex items-center space-x-2 mt-1">
                                <select id="charge_type_id"
                                    class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                    v-model="form.charge_type_id">
                                    <option value="">Select Charge Type</option>
                                    <option v-for="type in modalData.chargeTypes" :key="type.id" :value="type.id">
                                        {{ type.name }}
                                    </option>
                                </select>
                                <button type="button" @click="openModal('chargeType')"
                                    class="flex-shrink-0 p-2 text-white bg-green-500 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-300">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                </button>
                            </div>
                            <p v-if="form.errors.charge_type_id" class="mt-2 text-sm text-red-600">
                                {{ form.errors.charge_type_id }}
                            </p>
                        </div>

                        <div class="col-span-1">
                            <label for="charge_category_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Charge Category *
                            </label>
                            <div class="flex items-center space-x-2 mt-1">
                                <select id="charge_category_id"
                                    class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                    v-model="form.charge_category_id">
                                    <option value="">Select Charge Category</option>
                                    <option v-for="category in modalData.chargeCategories" :key="category.id"
                                        :value="category.id">
                                        {{ category.name }}
                                    </option>
                                </select>
                                <button type="button" @click="openModal('chargeCategory')"
                                    class="flex-shrink-0 p-2 text-white bg-green-500 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-300">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                </button>
                            </div>
                            <p v-if="form.errors.charge_category_id" class="mt-2 text-sm text-red-600">
                                {{ form.errors.charge_category_id }}
                            </p>
                        </div>

                        <div class="col-span-1">
                            <label for="unit_type_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Unit Type *
                            </label>
                            <div class="flex items-center space-x-2 mt-1">
                                <select id="unit_type_id"
                                    class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                    v-model="form.unit_type_id">
                                    <option value="">Select</option>
                                    <option v-for="unitType in modalData.chargeUnits" :key="unitType.id"
                                        :value="unitType.id">
                                        {{ unitType.name }}
                                    </option>
                                </select>
                                <button type="button" @click="openModal('unitType')"
                                    class="flex-shrink-0 p-2 text-white bg-green-500 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-300">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                </button>
                            </div>
                            <p v-if="form.errors.unit_type_id" class="mt-2 text-sm text-red-600">
                                {{ form.errors.unit_type_id }}
                            </p>
                        </div>

                        <div class="col-span-1">
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Charge Name *
                            </label>
                            <input id="name"
                                class="mt-1 block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                v-model="form.name" type="text" placeholder="Charge Name" />
                            <p v-if="form.errors.name" class="mt-2 text-sm text-red-600">
                                {{ form.errors.name }}
                            </p>
                        </div>
                    </div>

                    <!-- Second Row -->
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4">
                        <div class="col-span-1">
                            <label for="tax_category_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Tax Category *
                            </label>
                            <div class="flex items-center space-x-2 mt-1">
                                <select id="tax_category_id"
                                    class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                    v-model="form.tax_category_id" @change="updateTaxPercentage">
                                    <option value="">Select Tax Category</option>
                                    <option v-for="tax in modalData.taxCategories" :key="tax.id" :value="tax.id">
                                        {{ tax.name }} ({{ tax.percentage }}%)
                                    </option>
                                </select>
                                <button type="button" @click="openModal('taxCategory')"
                                    class="flex-shrink-0 p-2 text-white bg-green-500 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-300">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                </button>
                            </div>
                            <p v-if="form.errors.tax_category_id" class="mt-2 text-sm text-red-600">
                                {{ form.errors.tax_category_id }}
                            </p>
                        </div>

                        <div class="col-span-1">
                            <label for="tax" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Tax
                            </label>
                            <div class="relative mt-1">
                                <input id="tax" disabled
                                    class="block w-full p-2 pr-8 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                    v-model="form.tax" type="number" step="0.01" min="0" max="100" placeholder="Tax" />
                                <span
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-500">%</span>
                            </div>
                            <p v-if="form.errors.tax" class="mt-2 text-sm text-red-600">
                                {{ form.errors.tax }}
                            </p>
                        </div>

                        <div class="col-span-1">
                            <label for="standard_charge" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Standard Charge (Tk.) *
                            </label>
                            <input id="standard_charge"
                                class="mt-1 block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                v-model="form.standard_charge" type="number" step="0.01" min="0"
                                placeholder="Standard Charge" />
                            <p v-if="form.errors.standard_charge" class="mt-2 text-sm text-red-600">
                                {{ form.errors.standard_charge }}
                            </p>
                        </div>
                    </div>

                    <!-- Description Row -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Description
                        </label>
                        <textarea id="description"
                            class="mt-1 block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.description" rows="3" placeholder="Description"></textarea>
                        <p v-if="form.errors.description" class="mt-2 text-sm text-red-600">
                            {{ form.errors.description }}
                        </p>
                    </div>

                    <!-- Modal Actions -->
                    <div class="flex items-center justify-end space-x-3 pt-4 border-t">
                        <button type="button" @click="closeModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-600 dark:text-gray-300 dark:border-gray-500 dark:hover:bg-gray-700">
                            Cancel
                        </button>
                        <button type="submit"
                            class="inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                            :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            {{ charge?.id ? 'Update' : 'Create' }}
                        </button>
                    </div>
                </form>

                <!-- Sub-Modals -->
                <ChargeTypeModal :isOpen="modals.chargeType" @close="closeSubModal('chargeType')"
                    @created="handleChargeTypeCreated" @updated="handleChargeTypeCreated" />

                <ChargeCategoryModal :isOpen="modals.chargeCategory" :chargeTypes="modalData.chargeTypes"
                    @close="closeSubModal('chargeCategory')" @created="handleChargeCategoryCreated"
                    @updated="handleChargeCategoryCreated" />

                <UnitTypeModal :isOpen="modals.unitType" @close="closeSubModal('unitType')" @created="handleUnitTypeCreated"
                    @updated="handleUnitTypeCreated" />

                <TaxCategoryModal :isOpen="modals.taxCategory" @close="closeSubModal('taxCategory')"
                    @created="handleTaxCategoryCreated" @updated="handleTaxCategoryCreated" />
            </div>
        </div>
    </div>
</template>