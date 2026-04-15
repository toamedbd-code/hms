<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AlertMessage from '@/Components/AlertMessage.vue';
import CategoryModal from '@/Components/CategoryModal.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';
import HospitalChargeModal from '@/Components/HospitalChargeModal.vue';
import ChargeParameterModal from '@/Components/ChargeParameterModal.vue';

const props = defineProps([
    'pathologytest',
    'id',
    'testCategories',
    'charges',
    'pathologyUnits',
    'testParameters',
    'chargeTypes',
    'chargeCategories',
    'chargeUnits',
    'taxCategories']);

// Modal state
const showCategoryModal = ref(false);
const showChargeModal = ref(false);
const showParameterModal = ref(false);

// Create a reactive copy of categories for updates
const categories = ref([...props.testCategories]);
const charges = ref([...(props.charges || [])]);
const testParameters = ref([...(props.testParameters || [])]);

// Listen for global parameter-created events (dispatched by ChargeParameterModal)
const handleGlobalParameterCreated = (e) => {
    const payload = e?.detail || {};
    console.log('parameter-created event received in PathologyTest Form', payload);
    // If payload has id/name, push into local list and update UI
    if (payload && (payload.id || payload.name)) {
        // Avoid duplicates
        const exists = testParameters.value.some(p => String(p.id) === String(payload.id));
        if (!exists) {
            testParameters.value.push(payload);
        }
    }
};

onMounted(() => {
    window.addEventListener('parameter-created', handleGlobalParameterCreated);
    window.addEventListener('testParameters-updated', () => {
        console.log('testParameters-updated event received — reloading testParameters');
        router.reload({
            only: ['testParameters'],
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                testParameters.value = [...(page.props.testParameters || [])];
            }
        });
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('parameter-created', handleGlobalParameterCreated);
});

const form = useForm({
    category_type: props.pathologytest?.category_type ?? '',
    test_name: props.pathologytest?.test_name ?? '',
    test_short_name: props.pathologytest?.test_short_name ?? '',
    test_type: props.pathologytest?.test_type ?? '',
    test_category_id: props.pathologytest?.test_category_id ?? '',
    test_sub_category_id: props.pathologytest?.test_sub_category_id ?? '',
    method: props.pathologytest?.method ?? '',
    report_days: props.pathologytest?.report_days ?? '',
    charge_id: props.pathologytest?.charge_category_id ?? '', // Fixed field mapping
    charge_name: props.pathologytest?.charge_name ?? '',
    tax: props.pathologytest?.tax ?? '',
    standard_charge: props.pathologytest?.standard_charge ?? '',
    amount: props.pathologytest?.amount ?? '',

    parameters: props.pathologytest?.parameters ?? [{
        test_parameter_id: '',
        referance_from: '',
        referance_to: '',
        pathology_unit_id: '',
        name: ''
    }],

    _method: props.pathologytest?.id ? 'put' : 'post',
});

// Computed property to get main categories (no parent_id)
const mainCategories = computed(() => {
    if (!categories.value) return [];
    return categories.value.filter(category => !category.parent_id);
});

// Computed property to get ALL recursive subcategories based on selected category
const availableSubCategories = computed(() => {
    if (!form.test_category_id || !categories.value) {
        return [];
    }

    // Get all descendants recursively of the selected category
    const getAllDescendants = (parentId) => {
        const directChildren = categories.value.filter(cat => cat.parent_id == parentId);
        let allDescendants = [...directChildren];

        // For each direct child, get their descendants too
        directChildren.forEach(child => {
            allDescendants = [...allDescendants, ...getAllDescendants(child.id)];
        });

        return allDescendants;
    };

    return getAllDescendants(form.test_category_id);
});

// Helper function to get category hierarchy display name
const getCategoryDisplayName = (category) => {
    if (!category.parent_id) return category.name;

    // Find parent category
    const parent = categories.value.find(cat => cat.id == category.parent_id);
    if (parent) {
        return `${getCategoryDisplayName(parent)} > ${category.name}`;
    }
    return category.name;
};

// Watch for category change to reset subcategory
watch(() => form.test_category_id, (newCategoryId) => {
    form.test_sub_category_id = '';
});

watch(() => props.charges, (newCharges) => {
    charges.value = [...(newCharges || [])];
});

watch(() => props.testParameters, (newParameters) => {
    testParameters.value = [...(newParameters || [])];
});

// Watch for test name changes to auto-generate short name and type
watch(() => form.test_name, (newTestName) => {
    if (newTestName && !props.pathologytest?.id) {
        // Auto-generate short name (first letters of each word)
        const words = newTestName.trim().split(/\s+/);
        form.test_short_name = words.map(word => word.charAt(0).toUpperCase()).join('');

        form.test_type = newTestName;
    } else if (!newTestName) {
        form.test_short_name = '';
        form.test_type = '';
    }
});

// Keep charge name and parameter names in sync with test name when creating a new test
watch(() => form.test_name, (newTestName) => {
    // only auto-fill when creating (not editing existing test) to avoid overwriting saved data
    if (!props.pathologytest?.id) {
        form.charge_name = newTestName || '';

        // Update any parameter rows that don't already reference an existing parameter
        form.parameters = form.parameters.map(p => {
            if (!p.test_parameter_id) {
                return {
                    ...p,
                    name: newTestName || '',
                };
            }
            return p;
        });
    }
});

// Recalculate amount when standard_charge or tax change
const recalcAmount = () => {
    const standard = parseFloat(form.standard_charge) || 0;
    const taxPercentage = parseFloat(form.tax) || 0;
    if (standard > 0) {
        if (taxPercentage > 0) {
            const taxAmount = (standard * taxPercentage) / 100;
            form.amount = (standard + taxAmount).toFixed(2);
        } else {
            form.amount = standard.toFixed(2);
        }
    } else {
        form.amount = '';
    }
};

watch(() => form.standard_charge, recalcAmount);
watch(() => form.tax, recalcAmount);

// Modal functions
const openCategoryModal = () => {
    showCategoryModal.value = true;
};

const closeCategoryModal = () => {
    showCategoryModal.value = false;
};

const handleCategoryCreated = (newCategory) => {
    router.reload({
        only: ['testCategories'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            categories.value = page.props.testCategories || [];
        }
    });
};

const addParameter = () => {
    form.parameters.push({
        test_parameter_id: '',
        referance_from: '',
        referance_to: '',
        pathology_unit_id: '',
        name: ''
    });
};

// CSV import for parameters: expected columns (header optional): name,referance_from,referance_to,unit
const handleParamCsvUpload = (event) => {
    const file = event.target.files && event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        const text = e.target.result;
        const lines = text.split(/\r?\n/).map(l => l.trim()).filter(l => l.length);
        if (!lines.length) {
            alert('CSV is empty');
            return;
        }

        // detect header
        const first = lines[0];
        const sep = first.includes(',') ? ',' : '\t';
        const firstCols = first.split(sep).map(c => c.trim().toLowerCase());
        const hasHeader = ['name','referance_from','referance_to','unit'].some(h => firstCols.includes(h));
        const rows = hasHeader ? lines.slice(1) : lines;

        let imported = 0;
        rows.forEach(line => {
            const cols = line.split(sep).map(c => c.trim());
            // map by header if present, otherwise positional
            let name = '';
            let referFrom = '';
            let referTo = '';
            let unitVal = '';

            if (hasHeader) {
                const header = firstCols;
                const map = {};
                header.forEach((h, i) => map[h] = cols[i] || '');
                name = map.name || '';
                referFrom = map.referance_from || map.reference_from || '';
                referTo = map.referance_to || '';
                unitVal = map.unit || '';
            } else {
                name = cols[0] || '';
                referFrom = cols[1] || '';
                referTo = cols[2] || '';
                unitVal = cols[3] || '';
            }

            if (!name) return; // skip empty

            // find unit id by name (case-insensitive) or numeric id
            let unitId = '';
            if (unitVal) {
                const asNumber = Number(unitVal);
                if (!Number.isNaN(asNumber) && asNumber > 0) {
                    unitId = asNumber;
                } else {
                    const found = (props.pathologyUnits || []).find(u => (u.name||'').toLowerCase() === unitVal.toLowerCase());
                    if (found) unitId = found.id;
                }
            }

            form.parameters.push({
                test_parameter_id: '',
                referance_from: referFrom,
                referance_to: referTo,
                pathology_unit_id: unitId,
                name: name
            });
            imported++;
        });

        alert(`${imported} parameter(s) imported from CSV.`);
        // reset file input
        event.target.value = null;
    };
    reader.readAsText(file);
};

const removeParameter = (index) => {
    if (form.parameters.length > 1) {
        form.parameters.splice(index, 1);
    }
};

const onParameterSelect = (index, parameterId) => {
    if (parameterId) {
        const selectedParameter = testParameters.value.find(param => param.id == parameterId);
        if (selectedParameter) {
            form.parameters[index].test_parameter_id = parameterId;
            form.parameters[index].name = selectedParameter.name;
            form.parameters[index].referance_from = selectedParameter.referance_from;
            form.parameters[index].referance_to = selectedParameter.referance_to;
            form.parameters[index].pathology_unit_id = selectedParameter.pathology_unit_id;
        }
    } else {
        form.parameters[index].name = '';
        form.parameters[index].referance_from = '';
        form.parameters[index].referance_to = '';
        form.parameters[index].pathology_unit_id = '';
    }
};

const onChargeSelect = (chargeId) => {
    if (chargeId) {
        const selectedCharge = charges.value.find(charge => charge.id == chargeId);
        if (selectedCharge) {
            form.charge_name = selectedCharge.name;
            form.tax = selectedCharge.tax;
            form.standard_charge = selectedCharge.standard_charge;

            // Calculate amount: if tax > 0, calculate tax percentage and add to standard_charge
            const standardCharge = parseFloat(selectedCharge.standard_charge) || 0;
            const taxPercentage = parseFloat(selectedCharge.tax) || 0;

            if (taxPercentage > 0) {
                const taxAmount = (standardCharge * taxPercentage) / 100;
                form.amount = (standardCharge + taxAmount).toFixed(2);
            } else {
                form.amount = standardCharge.toFixed(2);
            }
        }
    } else {
        // Clear fields when no charge is selected
        form.charge_name = '';
        form.tax = '';
        form.standard_charge = '';
        form.amount = '';
    }
};

// Hospital Charge Modal functions
const openChargeModal = () => {
    showChargeModal.value = true;
};

const closeChargeModal = () => {
    showChargeModal.value = false;
};

const handleChargeCreated = (response) => {
    const previousChargeIds = new Set((charges.value || []).map(charge => String(charge.id)));

    // Reload charges and testParameters after creating a new charge
    router.reload({
        only: ['charges', 'testParameters'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            const latestCharges = page.props.charges || [];
            charges.value = [...latestCharges];

            const latestParameters = page.props.testParameters || [];
            testParameters.value = [...latestParameters];

            const createdCharge = latestCharges.find(charge => !previousChargeIds.has(String(charge.id)))
                || latestCharges[latestCharges.length - 1];

            if (createdCharge) {
                form.charge_id = createdCharge.id;
                onChargeSelect(createdCharge.id);
            }

            displayResponse(response);
        }
    });
};

// Parameter Modal functions
const openParameterModal = () => {
    showParameterModal.value = true;
};

const closeParameterModal = () => {
    showParameterModal.value = false;
};

const handleParameterCreated = (response) => {
    const previousParameterIds = new Set((testParameters.value || []).map(param => String(param.id)));

    // Reload parameters after creating a new one
    router.reload({
        only: ['testParameters'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            const latestParameters = page.props.testParameters || [];
            testParameters.value = [...latestParameters];

            const createdParameter = latestParameters.find(param => !previousParameterIds.has(String(param.id)))
                || latestParameters[latestParameters.length - 1];

            if (createdParameter) {
                let targetIndex = form.parameters.findIndex(param => !param.test_parameter_id);

                if (targetIndex === -1) {
                    form.parameters.push({
                        test_parameter_id: '',
                        referance_from: '',
                        referance_to: '',
                        pathology_unit_id: '',
                        name: ''
                    });
                    targetIndex = form.parameters.length - 1;
                }

                onParameterSelect(targetIndex, createdParameter.id);
            }

            displayResponse(response);
        }
    });
};

const submit = () => {
    const routeName = props.id
        ? route('backend.testpathology.update', props.id)
        : route('backend.testpathology.store');

    form.transform(data => ({
        ...data,
        remember: '',
        isDirty: false,
    })).post(routeName, {
        onSuccess: (response) => {
            if (!props.id) form.reset();
            displayResponse(response);
        },
        onError: (errorObject) => {
            displayWarning(errorObject);
        },
    });
};

const goToTestList = () => {
    router.visit(route('backend.testpathology.index'));
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
                        <button @click="goToTestList"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z">
                                </path>
                            </svg>
                            Test List
                        </button>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="p-4">

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                    <!-- Category Type -->
                    <div class="col-span-1 md:col-span-1">
                        <InputLabel for="category_type" value="Category Type" />
                        <select id="category_type" v-model="form.category_type"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                            <option value="">-- Select Type --</option>
                            <option value="Pathology">Pathology</option>
                            <option value="Radiology">Radiology</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.category_type" />
                    </div>

                    <!-- Test Name -->
                    <div>
                        <InputLabel for="test_name" value="Test Name" />
                        <input id="test_name" v-model="form.test_name" type="text" class="form-input" />
                        <InputError :message="form.errors.test_name" />
                    </div>

                    <!-- Test Short Name -->
                    <div>
                        <InputLabel for="test_short_name" value="Test Short Name" />
                        <input id="test_short_name" v-model="form.test_short_name" type="text" class="form-input" />
                        <InputError :message="form.errors.test_short_name" />
                    </div>

                    <!-- Test Type -->
                    <div>
                        <InputLabel for="test_type" value="Test Type" />
                        <input id="test_type" v-model="form.test_type" type="text" class="form-input" />
                        <InputError :message="form.errors.test_type" />
                    </div>

                    <!-- Main Category with Add Button -->
                    <div class="relative">
                        <InputLabel for="test_category_id" value="Main Category" />
                        <div class="flex items-center space-x-2">
                            <select id="test_category_id" v-model="form.test_category_id" class="form-input flex-1">
                                <option value="">Select Main Category</option>
                                <option v-for="cat in mainCategories" :key="cat.id" :value="cat.id">
                                    {{ cat.name }}
                                </option>
                            </select>
                            <button type="button" @click="openCategoryModal"
                                class="flex-shrink-0 inline-flex items-center justify-center w-10 h-10 text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200"
                                title="Add New Category">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6">
                                    </path>
                                </svg>
                            </button>
                        </div>
                        <InputError :message="form.errors.test_category_id" />

                        <!-- Debug info for categories -->
                        <small v-if="!mainCategories?.length" class="text-red-500">
                            No categories available. Check if testCategories prop is passed correctly.
                        </small>
                    </div>

                    <!-- Sub Category (Shows when main category is selected and has subcategories) -->
                    <div v-if="availableSubCategories.length > 0">
                        <InputLabel for="test_sub_category_id" value="Sub Category" />
                        <select id="test_sub_category_id" v-model="form.test_sub_category_id" class="form-input">
                            <option value="">Select Sub Category</option>
                            <option v-for="subCat in availableSubCategories" :key="subCat.id" :value="subCat.id">
                                {{ getCategoryDisplayName(subCat) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.test_sub_category_id" />
                    </div>

                    <!-- Method -->
                    <div>
                        <InputLabel for="method" value="Method" />
                        <input id="method" v-model="form.method" type="text" class="form-input" />
                        <InputError :message="form.errors.method" />
                    </div>

                    <!-- Report Days -->
                    <div>
                        <InputLabel for="report_days" value="Report Days" />
                        <input id="report_days" v-model="form.report_days" type="number" class="form-input" />
                        <InputError :message="form.errors.report_days" />
                    </div>

                    <!-- Charge Selection -->
                    <div>
                        <InputLabel for="charge_id" value="Select Charge" />
                        <div class="flex items-center space-x-2">
                            <select id="charge_id" v-model="form.charge_id" class="form-input flex-1"
                                @change="onChargeSelect(form.charge_id)">
                                <option value="">Select Charge</option>
                                <option v-for="charge in charges" :key="charge.id" :value="charge.id">
                                    {{ charge.name }}
                                </option>
                            </select>
                            <button type="button" @click="openChargeModal"
                                class="flex-shrink-0 inline-flex items-center justify-center w-10 h-10 text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200"
                                title="Add New Charge">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6">
                                    </path>
                                </svg>
                            </button>
                        </div>
                        <InputError :message="form.errors.charge_id" />

                        <!-- Debug info for charges -->
                        <small v-if="!charges?.length" class="text-red-500">
                            No charges available. Check if charges prop is passed correctly.
                        </small>
                    </div>

                    <!-- Charge Name (Auto-populated but editable) -->
                    <div>
                        <InputLabel for="charge_name" value="Charge Name" />
                        <input id="charge_name" v-model="form.charge_name" type="text" class="form-input" />
                        <InputError :message="form.errors.charge_name" />
                    </div>

                    <!-- Tax (Auto-populated but editable) -->
                    <div>
                        <InputLabel for="tax" value="Tax %" />
                        <input id="tax" v-model="form.tax" type="text" class="form-input" />
                        <InputError :message="form.errors.tax" />
                    </div>

                    <!-- Standard Charge (Auto-populated but editable) -->
                    <div>
                        <InputLabel for="standard_charge" value="Standard Charge" />
                        <input id="standard_charge" v-model="form.standard_charge" type="number" step="0.01" class="form-input" />
                        <InputError :message="form.errors.standard_charge" />
                    </div>

                    <!-- Amount (Auto-populated but editable) -->
                    <div>
                        <InputLabel for="amount" value="Total Amount" />
                        <input id="amount" v-model="form.amount" type="number" step="0.01" class="form-input" />
                        <InputError :message="form.errors.amount" />
                    </div>
                </div>

                <!-- Parameters Section -->
                <div class="mt-8">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300">Test Parameters</h3>
                                        <div class="flex items-center space-x-2">
                                            <button type="button" @click="openParameterModal"
                                                class="px-4 py-2 text-sm text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                                                Add New Parameter
                                            </button>
                                        </div>
                    </div>

                    <!-- Debug info for parameters -->
                    <small v-if="!testParameters?.length" class="text-red-500 block mb-4">
                        No test parameters available. Check if testParameters prop is passed correctly.
                    </small>

                    <small v-if="!props.pathologyUnits?.length" class="text-red-500 block mb-4">
                        No pathology units available. Check if pathologyUnits prop is passed correctly.
                    </small>

                    <div v-for="(parameter, index) in form.parameters" :key="index"
                        class="grid grid-cols-1 gap-3 mb-4 p-4 border border-gray-200 rounded-lg sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">

                        <!-- Reference From -->
                        <div>
                            <InputLabel :for="`reference_from_${index}`" value="Reference From" />
                            <input :id="`reference_from_${index}`" v-model="form.parameters[index].referance_from"
                                type="text" class="form-input" placeholder="e.g., 12.0" />
                            <InputError :message="form.errors[`parameters.${index}.referance_from`]" />
                        </div>

                        <!-- Reference To -->
                        <div>
                            <InputLabel :for="`reference_to_${index}`" value="Reference To" />
                            <input :id="`reference_to_${index}`" v-model="form.parameters[index].referance_to"
                                type="text" class="form-input" placeholder="e.g., 16.0" />
                            <InputError :message="form.errors[`parameters.${index}.referance_to`]" />
                        </div>

                        <!-- Unit -->
                        <div>
                            <InputLabel :for="`unit_${index}`" value="Unit" />
                            <select :id="`unit_${index}`" v-model="form.parameters[index].pathology_unit_id"
                                class="form-input"
                                :class="{ 'bg-gray-100 dark:bg-gray-600': form.parameters[index].test_parameter_id }"
                                :disabled="!!form.parameters[index].test_parameter_id">
                                <option value="">Select Unit</option>
                                <option v-for="unit in props.pathologyUnits" :key="unit.id" :value="unit.id">
                                    {{ unit.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors[`parameters.${index}.pathology_unit_id`]" />
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-end gap-2">
                            <button type="button" @click="addParameter"
                                class="px-3 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                title="Add Parameter">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                            </button>
                            <button v-if="form.parameters.length > 1" type="button" @click="removeParameter(index)"
                                class="px-3 py-2 text-sm text-white bg-red-600 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                                title="Remove Parameter">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <!-- Parameter Name (show text input when no existing parameter selected) -->
                        <div class="lg:col-span-2">
                            <InputLabel :for="`test_parameter_id_${index}`" value="Parameter Name" />
                            <template v-if="form.parameters[index].test_parameter_id">
                                <select :id="`test_parameter_id_${index}`" class="form-input"
                                    v-model="form.parameters[index].test_parameter_id"
                                    @change="onParameterSelect(index, form.parameters[index].test_parameter_id)">
                                    <option value="">-- Select Parameter --</option>
                                    <option v-for="data in testParameters" :key="data.id" :value="data.id">
                                        {{ data.name }}
                                    </option>
                                </select>
                            </template>
                            <template v-else>
                                <input :id="`test_parameter_name_${index}`" v-model="form.parameters[index].name" type="text" class="form-input" />
                            </template>
                            <InputError :message="form.errors[`parameters.${index}.test_parameter_id`]" />
                        </div>
                    </div>

                    <!-- Show message if no parameters -->
                    <div v-if="!form.parameters?.length" class="text-center py-4 text-gray-500">
                        No parameters added yet. Click "Add New Parameter" to add one.
                    </div>
                </div>

                <div class="flex justify-end mt-6">
                    <PrimaryButton type="submit" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        {{ props.id ? 'Update Test' : 'Create Test' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>

        <!-- Category Modal -->
        <CategoryModal :is-open="showCategoryModal" :categories="categories" @close="closeCategoryModal"
            @category-created="handleCategoryCreated" />

        <HospitalChargeModal :isOpen="showChargeModal" :charge="null" :chargeTypes="props.chargeTypes || []"
            :chargeCategories="props.chargeCategories || []" :chargeUnits="props.chargeUnits || []"
            :taxCategories="props.taxCategories || []" @close="closeChargeModal" @created="handleChargeCreated" />

        <ChargeParameterModal :show="showParameterModal" :units="props.pathologyUnits"
            :existing-parameters="testParameters" @close="closeParameterModal"
            @parameter-created="handleParameterCreated" />

    </BackendLayout>
</template>

<style scoped>
.form-input {
    @apply block w-full p-2 text-sm border rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600;
}
</style>