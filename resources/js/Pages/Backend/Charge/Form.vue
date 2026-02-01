<script setup>
import { ref, onMounted } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AlertMessage from '@/Components/AlertMessage.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';

// Import modal components
import ChargeTypeModal from '@/Components/ChargeTypeModal.vue';
import ChargeCategoryModal from '@/Components/ChargeCategoryModal.vue';
import UnitTypeModal from '@/Components/UnitTypeModal.vue';
import TaxCategoryModal from '@/Components/TaxCategoryModal.vue';

const props = defineProps(['charge', 'id', 'chargeTypes', 'chargeCategories', 'chargeUnits', 'taxCategories']);

// Form setup
const form = useForm({
    name: props.charge?.name ?? '',
    charge_type_id: props.charge?.charge_type_id ?? '',
    charge_category_id: props.charge?.charge_category_id ?? '',
    unit_type_id: props.charge?.unit_type_id ?? '',
    tax_category_id: props.charge?.tax_category_id ?? '',
    tax: props.charge?.tax ?? '',
    standard_charge: props.charge?.standard_charge ?? '',
    description: props.charge?.description ?? '',
    _method: props.charge?.id ? 'put' : 'post',
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

const closeModal = (modalName) => {
    modals.value[modalName] = false;
};

const handleChargeTypeCreated = (response) => {
    router.reload({
        only: ['chargeTypes'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            modalData.value.chargeTypes = page.props.chargeTypes || [];
            displayResponse(response);
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
            displayResponse(response);
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
            displayResponse(response);
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
            displayResponse(response);
        }
    });
};

const submit = () => {
    const routeName = props.id ? route('backend.hospitalcharge.update', props.id) : route('backend.hospitalcharge.store');
    form.transform(data => ({
        ...data,
        remember: '',
        isDirty: false,
    })).post(routeName, {
        onSuccess: (response) => {
            if (!props.id)
                form.reset();
            displayResponse(response);
        },
        onError: (errorObject) => {
            displayWarning(errorObject);
        },
    });
};

const goToChargeList = () => {
    router.visit(route('backend.hospitalcharge.index'));
};
</script>

<template>
    <BackendLayout>
        <div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md">

            <div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md">
                <div>
                    <h1 class="p-4 text-xl font-bold dark:text-white">{{ $page.props.pageTitle }}</h1>
                </div>

                <div class="p-2 py-2 flex items-center space-x-2">
                    <div class="flex items-center space-x-3">
                        <button @click="goToChargeList"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z">
                                </path>
                            </svg>
                            Hospital Charge List
                        </button>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="p-4">

                <!-- First Row -->
                <div class="grid grid-cols-1 gap-3 mb-4 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4">
                    <div class="col-span-1">
                        <InputLabel for="charge_type_id" value="Charge Type *" />
                        <div class="flex items-center space-x-2">
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
                        <InputError class="mt-2" :message="form.errors.charge_type_id" />
                    </div>

                    <div class="col-span-1">
                        <InputLabel for="charge_category_id" value="Charge Category *" />
                        <div class="flex items-center space-x-2">
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
                        <InputError class="mt-2" :message="form.errors.charge_category_id" />
                    </div>

                    <div class="col-span-1">
                        <InputLabel for="unit_type_id" value="Unit Type *" />
                        <div class="flex items-center space-x-2">
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
                        <InputError class="mt-2" :message="form.errors.unit_type_id" />
                    </div>

                    <div class="col-span-1">
                        <InputLabel for="name" value="Charge Name *" />
                        <input id="name"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.name" type="text" placeholder="Charge Name" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>
                </div>

                <!-- Second Row -->
                <div class="grid grid-cols-1 gap-3 mb-4 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4">
                    <div class="col-span-1">
                        <InputLabel for="tax_category_id" value="Tax Category *" />
                        <div class="flex items-center space-x-2">
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
                        <InputError class="mt-2" :message="form.errors.tax_category_id" />
                    </div>

                    <div class="col-span-1">
                        <InputLabel for="tax" value="Tax" />
                        <div class="relative">
                            <input id="tax" disabled
                                class="block w-full p-2 pr-8 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                v-model="form.tax" type="number" step="0.01" min="0" max="100" placeholder="Tax" />
                            <span
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-500">%</span>
                        </div>
                        <InputError class="mt-2" :message="form.errors.tax" />
                    </div>

                    <div class="col-span-1">
                        <InputLabel for="standard_charge" value="Standard Charge (Tk.) *" />
                        <input id="standard_charge"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.standard_charge" type="number" step="0.01" min="0"
                            placeholder="Standard Charge" />
                        <InputError class="mt-2" :message="form.errors.standard_charge" />
                    </div>
                </div>

                <!-- Description Row -->
                <div class="mb-4">
                    <InputLabel for="description" value="Description" />
                    <textarea id="description"
                        class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        v-model="form.description" rows="3" placeholder="Description"></textarea>
                    <InputError class="mt-2" :message="form.errors.description" />
                </div>

                <div class="flex items-center justify-end mt-4">
                    <PrimaryButton type="submit" class="ms-4" :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing">
                        {{ ((props.id ?? false) ? 'Update' : 'Create') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>

        <!-- Modals -->
        <ChargeTypeModal :isOpen="modals.chargeType" @close="closeModal('chargeType')"
            @created="handleChargeTypeCreated" @updated="handleChargeTypeCreated" />

        <ChargeCategoryModal :isOpen="modals.chargeCategory" :chargeTypes="modalData.chargeTypes"
            @close="closeModal('chargeCategory')" @created="handleChargeCategoryCreated"
            @updated="handleChargeCategoryCreated" />

        <UnitTypeModal :isOpen="modals.unitType" @close="closeModal('unitType')" @created="handleUnitTypeCreated"
            @updated="handleUnitTypeCreated" />

        <TaxCategoryModal :isOpen="modals.taxCategory" @close="closeModal('taxCategory')"
            @created="handleTaxCategoryCreated" @updated="handleTaxCategoryCreated" />
    </BackendLayout>
</template>