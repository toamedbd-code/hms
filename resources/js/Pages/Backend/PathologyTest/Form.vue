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
import UnitModal from '@/Components/UnitModal.vue';

const props = defineProps({
    pathologytest: { type: Object, default: () => ({}) },
    id: { type: [String, Number, null], default: null },
    testCategories: { type: Array, default: () => [] },
    categoryTypeOptions: { type: Array, default: () => [] },
    mainCategories: { type: Array, default: () => [] },
    charges: { type: Array, default: () => [] },
    pathologyUnits: { type: Array, default: () => [] },
    testParameters: { type: Array, default: () => [] },
    chargeTypes: { type: Array, default: () => [] },
    chargeCategories: { type: Array, default: () => [] },
    chargeUnits: { type: Array, default: () => [] },
    taxCategories: { type: Array, default: () => [] },
    // When embedded=true the component will render without the outer BackendLayout
    embedded: { type: Boolean, default: false },
    // Optional ipd id to auto-add created charge to an IPD when used embedded
    ipdId: { type: [String, Number, null], default: null },
});

const emit = defineEmits(['created']);
const page = usePage();
const pageTitle = computed(() => (page && page.props && page.props.pageTitle) ? page.props.pageTitle : (props.id ? 'Update Item' : 'Create Item'));

// Modal state
const showCategoryModal = ref(false);
const showItemCategoryModal = ref(false);
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
    document.addEventListener('click', closeCategoryDropdowns);
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
    document.removeEventListener('click', closeCategoryDropdowns);
    window.removeEventListener('parameter-created', handleGlobalParameterCreated);
});

const initialDiscountPercentage = Number(props.pathologytest?.discount_percentage ?? 0);
const initialDiscountLocked = Boolean(props.pathologytest?.discount_rate_locked);
// Locked at 0% means this item must never take bill percentage discount.
const initialDiscountOff = initialDiscountLocked && initialDiscountPercentage <= 0;

const form = useForm({
    category_type: props.pathologytest?.category_type ?? '',
    test_name: props.pathologytest?.test_name ?? '',
    test_short_name: props.pathologytest?.test_short_name ?? '',
    test_type: props.pathologytest?.test_type ?? '',
    test_category_id: props.pathologytest?.test_category_id ?? '',
    main_category_id: props.pathologytest?.main_category_id ?? '',
    test_sub_category_id: props.pathologytest?.test_sub_category_id ?? '',
    method: props.pathologytest?.method ?? '',
    room_no: props.pathologytest?.room_no ?? '',
    charge_id: props.pathologytest?.charge_category_id ?? '', // Fixed field mapping
    charge_name: props.pathologytest?.charge_name ?? '',
    tax: props.pathologytest?.tax ?? '',
    standard_charge: props.pathologytest?.standard_charge ?? '',
    amount: props.pathologytest?.amount ?? '',
    referral_percentage: props.pathologytest?.referral_percentage ?? '',
    discount_percentage: initialDiscountOff ? 0 : (props.pathologytest?.discount_percentage ?? ''),
    discount_off: initialDiscountOff,
    discount_rate_locked: initialDiscountLocked,

    parameters: props.pathologytest?.parameters ?? [{
        test_parameter_id: '',
        referance_from: '',
        referance_to: '',
        pathology_unit_id: '',
        name: ''
    }],

    _method: props.pathologytest?.id ? 'put' : 'post',
});

watch(() => form.discount_off, (off) => {
    if (off) {
        form.discount_percentage = 0;
        form.discount_rate_locked = true;
        return;
    }

    form.discount_rate_locked = false;
});

// Main categories are report headings, while category_type remains the service module.
const mainCategories = computed(() => {
    return props.mainCategories || [];
});

const categoryTypeOptions = computed(() => {
    return props.categoryTypeOptions;
});

const categoryTypeSearch = ref('');
const showCategoryTypeOptions = ref(false);
const highlightedCategoryTypeIndex = ref(0);

const selectedCategoryTypeName = computed(() => form.category_type || '');

const filteredCategoryTypes = computed(() => {
    const search = categoryTypeSearch.value.trim().toLowerCase();
    if (!search) return categoryTypeOptions.value;

    return categoryTypeOptions.value.filter(category => String(category || '').toLowerCase().includes(search));
});

const openCategoryTypeSearch = () => {
    categoryTypeSearch.value = '';
    highlightedCategoryTypeIndex.value = 0;
    showMainCategoryOptions.value = false;
    showCategoryTypeOptions.value = true;
};

const openMainCategorySearch = () => {
    mainCategorySearch.value = '';
    highlightedMainCategoryIndex.value = 0;
    showCategoryTypeOptions.value = false;
    showMainCategoryOptions.value = true;
};

const selectCategoryType = (category) => {
    form.category_type = category;
    categoryTypeSearch.value = '';
    showCategoryTypeOptions.value = false;
};

const handleCategoryTypeInput = (event) => {
    const value = event.target.value;
    categoryTypeSearch.value = value;
    if (!value.trim()) {
        form.category_type = '';
    }
    highlightedCategoryTypeIndex.value = 0;
    showMainCategoryOptions.value = false;
    showCategoryTypeOptions.value = true;
};

const handleCategoryTypeEnter = (event) => {
    event.preventDefault();
    const category = filteredCategoryTypes.value[highlightedCategoryTypeIndex.value] || filteredCategoryTypes.value[0];
    if (category) selectCategoryType(category);
};

const clearCategoryTypeSearch = () => {
    categoryTypeSearch.value = '';
    form.category_type = '';
    showCategoryTypeOptions.value = false;
};

const mainCategorySearch = ref('');
const showMainCategoryOptions = ref(false);
const highlightedMainCategoryIndex = ref(0);

const selectedMainCategoryName = computed(() => {
    const selected = mainCategories.value.find(category => String(category.id) === String(form.main_category_id));
    return selected?.name || '';
});

const filteredMainCategories = computed(() => {
    const search = mainCategorySearch.value.trim().toLowerCase();
    if (!search) return mainCategories.value;

    return mainCategories.value.filter(category => String(category.name || '').toLowerCase().includes(search));
});

const selectMainCategory = (category) => {
    form.main_category_id = category.id;
    mainCategorySearch.value = '';
    showMainCategoryOptions.value = false;
};

const handleMainCategoryInput = (event) => {
    const value = event.target.value;
    mainCategorySearch.value = value;
    if (!value.trim()) {
        form.main_category_id = '';
    }
    highlightedMainCategoryIndex.value = 0;
    showCategoryTypeOptions.value = false;
    showMainCategoryOptions.value = true;
};

const handleMainCategoryEnter = (event) => {
    event.preventDefault();
    const category = filteredMainCategories.value[highlightedMainCategoryIndex.value] || filteredMainCategories.value[0];
    if (category) selectMainCategory(category);
};

const clearMainCategorySearch = () => {
    mainCategorySearch.value = '';
    form.main_category_id = '';
    showMainCategoryOptions.value = false;
};

const closeCategoryDropdowns = (event) => {
    const clickedCategoryType = event?.target?.closest?.('#category_type');
    const clickedMainCategory = event?.target?.closest?.('#main_category_id');

    if (clickedCategoryType) {
        mainCategorySearch.value = '';
        showMainCategoryOptions.value = false;
        return;
    }

    if (clickedMainCategory) {
        categoryTypeSearch.value = '';
        showCategoryTypeOptions.value = false;
        return;
    }

    categoryTypeSearch.value = '';
    showCategoryTypeOptions.value = false;
    mainCategorySearch.value = '';
    showMainCategoryOptions.value = false;
};

// Computed property to get ALL recursive subcategories based on selected category
const availableSubCategories = computed(() => {
    if (!form.test_category_id || !categories.value) {
        return [];
    }

    // Get all descendants recursively of the selected category.
    // Protect against cyclic category parent relationships to avoid max call stack.
    const getAllDescendants = (parentId, visited = new Set()) => {
        if (visited.has(parentId)) {
            return [];
        }

        visited.add(parentId);
        const directChildren = categories.value.filter(cat => cat.parent_id == parentId && cat.id !== parentId);
        let allDescendants = [...directChildren];

        directChildren.forEach(child => {
            allDescendants = [...allDescendants, ...getAllDescendants(child.id, visited)];
        });

        return allDescendants;
    };

    const descendants = getAllDescendants(form.test_category_id);

    // If there are descendants, return them.
    if (descendants.length > 0) return descendants;

    // Special case: if selected main category is "Disposable", include the
    // main category itself as an available "sub-category" so the UI shows
    // a selectable option. This ensures Disposable items can be assigned
    // to a Disposable subcategory (used for no-discount logic).
    const parentCat = categories.value.find(cat => cat.id == form.test_category_id);
    if (parentCat && String(parentCat.name || '').trim().toLowerCase() === 'disposable') {
        return [parentCat];
    }

    return [];
});

// Show/hide subcategory field: hide when selected main category is 'Disposable'
const showSubCategoryField = computed(() => {
    if (!form.test_category_id) return false;
    const parentCat = categories.value.find(cat => cat.id == form.test_category_id);
    if (!parentCat) return false;
    if (String(parentCat.name || '').trim().toLowerCase() === 'disposable') return false;
    return availableSubCategories.value.length > 0;
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

// Compute a default charge category id matching the selected category_type (case-insensitive)
const selectedChargeCategoryId = computed(() => {
    const catType = (form.category_type || '').toString().trim().toLowerCase();
    if (!catType) return '';
    const found = (props.chargeCategories || []).find(c => (c.name || '').toString().trim().toLowerCase() === catType);
    return found ? found.id : '';
});

const showItemParameters = computed(() => {
    const categoryType = (form.category_type || '').toString().trim().toLowerCase();
    return categoryType === 'pathology';
});

const itemParametersTitle = computed(() => {
    const categoryType = (form.category_type || '').toString().trim().toLowerCase();
    return categoryType === 'radiology' ? 'Radiology Parameters' : 'Item Parameters';
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

const openItemCategoryModal = () => {
    showItemCategoryModal.value = true;
};

const closeItemCategoryModal = () => {
    showItemCategoryModal.value = false;
};

const handleCategoryCreated = (newCategory) => {
    router.reload({
        only: ['mainCategories'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            const createdName = String(newCategory?.name || '').trim().toLowerCase();
            const category = (page.props.mainCategories || []).find(item => (
                String(item.name || '').trim().toLowerCase() === createdName
            ));
            if (category) form.main_category_id = category.id;
        }
    });
};

const handleItemCategoryCreated = (newCategory) => {
    router.reload({
        only: ['testCategories', 'categoryTypeOptions'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            categories.value = [...(page.props.testCategories || [])];
            const createdName = String(newCategory?.name || '').trim();
            if (createdName) {
                form.category_type = createdName;
            }
        }
    });
};

// Permissions helper
const userPermissions = computed(() => {
    const raw = page.props?.auth?.permissions ?? [];
    return Array.isArray(raw) ? raw.map(p => String(p).trim().toLowerCase()) : [];
});

const canCreateCategory = computed(() => userPermissions.value.includes('test-category-list-create'));
const canEditCategory = computed(() => userPermissions.value.includes('test-category-list-edit'));
const canDeleteCategory = computed(() => userPermissions.value.includes('test-category-list-delete'));

// Edit/Delete helpers for selected main category
const editSelectedCategory = () => {
    if (!form.test_category_id) return;
    router.visit(route('backend.pathologycategory.edit', form.test_category_id));
};

const deleteSelectedCategory = async () => {
    if (!form.test_category_id) return;
    if (!confirm('Are you sure you want to delete the selected category? This will affect related items.')) return;

    try {
        await window.axios.post(route('backend.pathologycategory.destroy', form.test_category_id), { _method: 'delete' });
        // reload categories
        router.reload({ only: ['testCategories'], preserveState: true, preserveScroll: true, onSuccess: (page) => {
            categories.value = page.props.testCategories || [];
            form.test_category_id = '';
        }});
    } catch (err) {
        displayWarning(err.response?.data || err);
    }
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

// Unit modal state and helpers
const showUnitModal = ref(false);
const unitTargetIndex = ref(null);

const openUnitModal = (index) => {
    unitTargetIndex.value = index;
    showUnitModal.value = true;
};

const closeUnitModal = () => {
    unitTargetIndex.value = null;
    showUnitModal.value = false;
};

const handleUnitCreated = (response) => {
    router.reload({
        only: ['pathologyUnits'],
        onSuccess: () => {
            const newUnitId = response.data?.id;
            if (newUnitId && unitTargetIndex.value !== null) {
                // assign the new unit to the parameter row that requested it
                form.parameters[unitTargetIndex.value].pathology_unit_id = newUnitId;
            }
        }
    });
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
        ? route('backend.itemcharge.update', props.id)
        : route('backend.itemcharge.store');

    form.transform(data => {
        const discountOff = Boolean(data.discount_off);
        const discountPercentage = discountOff
            ? 0
            : Math.min(100, Math.max(0, Number(data.discount_percentage ?? 0)));

        return {
            ...data,
            discount_percentage: discountPercentage,
            discount_rate_locked: discountOff,
            remember: '',
            isDirty: false,
        };
    }).post(routeName, {
        onSuccess: async (response) => {
            // reset form when created (not editing)
            if (!props.id) form.reset();
            displayResponse(response);

            // Keep embedded modal flow inside the current billing page instead of
            // jumping to a standalone item page.
            if (props.embedded) {
                emit('created', response);
                return;
            }

            // Regular standalone form behavior.
            try {
                if (!props.id) {
                    router.visit(route('backend.itemcharge.index'));
                }
            } catch (e) {}
        },
        onError: (errorObject) => {
            displayWarning(errorObject);
        },
    });
};

    const goToTestList = () => {
        if (props.embedded) {
            emit('created', { embedded_close: true });
            return;
        }
        router.visit(route('backend.itemcharge.index'));
};

const goBack = () => {
        if (props.embedded) {
            emit('created', { embedded_close: true });
            return;
        }
        router.visit(route('backend.itemcharge.index'));
};

</script>

<template>
    <component :is="props.embedded ? 'div' : BackendLayout">
        <div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md">

            <div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md">
                <div>
                    <h1 class="p-4 text-xl font-bold dark:text-white">{{ pageTitle }}</h1>
                </div>

                <div v-if="!props.embedded" class="p-2 py-2 flex items-center space-x-2">
                    <div class="flex items-center space-x-3">
                        <button @click="goBack"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gray-500 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gray-600">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Back
                        </button>

                        <!-- Header Add-to-IPD removed per UX request -->

                        <button @click="goToTestList"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z">
                                </path>
                            </svg>
                            Item List
                        </button>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="p-4">

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                    <!-- Category Type -->
                    <div class="col-span-1 md:col-span-1">
                        <InputLabel for="category_type" value="Category Type" />
                        <div class="flex items-center space-x-2">
                            <div class="relative flex-1">
                                <input id="category_type" :value="categoryTypeSearch || selectedCategoryTypeName"
                                    @focus="openCategoryTypeSearch" @input="handleCategoryTypeInput" @keydown.enter="handleCategoryTypeEnter"
                                    @keydown.esc="clearCategoryTypeSearch" type="text" autocomplete="off"
                                    placeholder="Select Type" class="form-input w-full" />
                                <div v-if="showCategoryTypeOptions"
                                    class="absolute left-0 right-0 z-20 mt-1 max-h-60 overflow-y-auto rounded-md border border-slate-300 bg-white shadow-lg dark:border-slate-600 dark:bg-slate-700">
                                    <button v-for="category in filteredCategoryTypes" :key="category" type="button"
                                        @mousedown.prevent="selectCategoryType(category)"
                                        @mouseenter="highlightedCategoryTypeIndex = filteredCategoryTypes.indexOf(category)"
                                        :class="['block w-full px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-600 dark:text-slate-100',
                                            { 'dropdown-option-highlight': filteredCategoryTypes.indexOf(category) === highlightedCategoryTypeIndex }]">
                                        {{ category }}
                                    </button>
                                    <div v-if="filteredCategoryTypes.length === 0" class="px-3 py-2 text-sm text-slate-500 dark:text-slate-300">
                                        No Category Types found.
                                    </div>
                                </div>
                            </div>
                            <button v-if="canCreateCategory" type="button" @click.prevent.stop="openItemCategoryModal"
                                class="flex-shrink-0 inline-flex items-center justify-center w-10 h-10 text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200"
                                title="Item Category Add" aria-label="Item Category Add">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                            </button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.category_type" />
                    </div>

                    <!-- Item Name -->
                    <div>
                        <InputLabel for="test_name" value="Item Name" />
                        <input id="test_name" v-model="form.test_name" type="text" class="form-input" />
                        <InputError :message="form.errors.test_name" />
                    </div>

                    <!-- Item Short Name -->
                    <div>
                        <InputLabel for="test_short_name" value="Item Short Name" />
                        <input id="test_short_name" v-model="form.test_short_name" type="text" class="form-input" />
                        <InputError :message="form.errors.test_short_name" />
                    </div>

                    <!-- Main Category with add popup -->
                    <div class="relative">
                        <InputLabel for="main_category_id" value="Main Category" />
                        <div class="flex items-center space-x-2">
                            <div class="relative flex-1">
                                <input id="main_category_id" :value="mainCategorySearch || selectedMainCategoryName"
                                    @focus="openMainCategorySearch" @input="handleMainCategoryInput" @keydown.enter="handleMainCategoryEnter"
                                    @keydown.esc="clearMainCategorySearch" type="text" autocomplete="off"
                                    placeholder="Select Main Category" class="form-input w-full" />
                                <div v-if="showMainCategoryOptions"
                                    class="absolute left-0 right-0 z-20 mt-1 max-h-60 overflow-y-auto rounded-md border border-slate-300 bg-white shadow-lg dark:border-slate-600 dark:bg-slate-700">
                                    <button v-for="category in filteredMainCategories" :key="category.id" type="button"
                                        @mousedown.prevent="selectMainCategory(category)"
                                        @mouseenter="highlightedMainCategoryIndex = filteredMainCategories.indexOf(category)"
                                        :class="['block w-full px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-600 dark:text-slate-100',
                                            { 'dropdown-option-highlight': filteredMainCategories.indexOf(category) === highlightedMainCategoryIndex }]">
                                        {{ category.name }}
                                    </button>
                                    <div v-if="filteredMainCategories.length === 0" class="px-3 py-2 text-sm text-slate-500 dark:text-slate-300">
                                        No Main Categories found.
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button v-if="canCreateCategory" type="button" @click.prevent.stop="openCategoryModal"
                                    class="flex-shrink-0 inline-flex items-center justify-center w-10 h-10 text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200"
                                    title="Main Category Add"
                                    aria-label="Main Category Add">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6">
                                        </path>
                                    </svg>
                                </button>

                                <!-- Edit/Delete buttons removed per request -->
                            </div>
                        </div>
                        <InputError :message="form.errors.main_category_id" />

                        <!-- Debug info for categories -->
                        <small v-if="!mainCategories?.length" class="text-red-500">
                            No Main Categories available.
                        </small>
                    </div>


                    <!-- Sub Category (Shows when main category is selected and has subcategories) -->
                    <div v-if="showSubCategoryField">
                        <InputLabel for="test_sub_category_id" value="Sub Category" />
                        <select id="test_sub_category_id" v-model="form.test_sub_category_id" class="form-input">
                            <option value="">Select Sub Category</option>
                            <option v-for="subCat in availableSubCategories" :key="subCat.id" :value="subCat.id">
                                {{ getCategoryDisplayName(subCat) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.test_sub_category_id" />
                    </div>

                    <!-- Room No -->
                    <div>
                        <InputLabel for="room_no" value="Room No" />
                        <input id="room_no" v-model="form.room_no" type="text" class="form-input" />
                        <InputError :message="form.errors.room_no" />
                    </div>

                    <!-- Charge Selection removed as requested -->

                    <!-- Tax (Auto-populated but editable) -->
                    <div>
                        <InputLabel for="tax" value="Tax %" />
                        <input id="tax" v-model="form.tax" type="text" @focus="$event.target.select()" class="form-input" />
                        <InputError :message="form.errors.tax" />
                    </div>

                    <!-- Standard Charge (Auto-populated but editable) -->
                    <div>
                        <InputLabel for="standard_charge" value="Standard Charge" />
                        <input id="standard_charge" v-model="form.standard_charge" type="number" step="0.01"
                            @focus="$event.target.select()" class="form-input" />
                        <InputError :message="form.errors.standard_charge" />
                    </div>

                    <!-- Amount is calculated from standard charge and tax. -->
                    <div>
                        <InputLabel for="amount" value="Total Amount" />
                        <input id="amount" v-model="form.amount" type="number" step="0.01" readonly
                            class="form-input cursor-not-allowed bg-gray-100 dark:bg-gray-600" />
                        <InputError :message="form.errors.amount" />
                    </div>

                    <!-- Referral Percentage -->
                    <div>
                        <InputLabel for="referral_percentage" value="Referral Percentage (%)" />
                        <input id="referral_percentage" v-model="form.referral_percentage" type="number" step="0.01" min="0" max="100"
                            @focus="$event.target.select()" class="form-input" />
                        <InputError :message="form.errors.referral_percentage" />
                    </div>
                    <div>
                        <InputLabel for="discount_percentage" value="Item Discount (%)" />
                        <div class="flex items-center gap-3">
                            <input id="discount_percentage" v-model="form.discount_percentage" type="number"
                                step="0.01" min="0" max="100" @focus="$event.target.select()"
                                class="form-input flex-1" :disabled="form.discount_off" />
                            <label for="discount_off"
                                class="inline-flex items-center gap-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 cursor-pointer select-none">
                                <input id="discount_off" v-model="form.discount_off" type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                Discount Off
                            </label>
                        </div>
                        <InputError :message="form.errors.discount_percentage" />
                        <InputError :message="form.errors.discount_rate_locked" />
                    </div>
                </div>

                <!-- Parameters Section -->
                    <div v-if="showItemParameters" class="mt-8">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300">{{ itemParametersTitle }}</h3>
                                        <div class="flex items-center space-x-2">
                                            <button type="button" @click="addParameter"
                                                class="px-4 py-2 text-sm text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                                                Add Parameter
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
                                class="form-input">
                                <option value="">Select Unit</option>
                                <option v-for="unit in props.pathologyUnits" :key="unit.id" :value="unit.id">
                                    {{ unit.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors[`parameters.${index}.pathology_unit_id`]" />
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-end gap-2">
                            <button type="button" @click="openUnitModal(index)"
                                class="px-3 py-2 text-sm text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
                                title="Add New Unit">
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
                        No parameters added yet. Click "Add Parameter" to add one.
                    </div>
                </div>

                    <div class="flex justify-end mt-6">
                    <PrimaryButton type="submit" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        {{ props.id ? 'Update Item' : 'Create Item' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>

        <!-- Category Modal -->
        <CategoryModal :is-open="showCategoryModal" :categories="mainCategories" :is-main-category="true"
            :return-to="route('backend.itemcharge.create')"
            @close="closeCategoryModal" @category-created="handleCategoryCreated" />

        <CategoryModal :is-open="showItemCategoryModal" :categories="categories" :is-main-category="false"
            :return-to="route('backend.itemcharge.create')"
            @close="closeItemCategoryModal" @category-created="handleItemCategoryCreated" />

        <HospitalChargeModal :isOpen="showChargeModal" :charge="null" :chargeTypes="props.chargeTypes || []"
            :chargeCategories="props.chargeCategories || []" :chargeUnits="props.chargeUnits || []"
            :taxCategories="props.taxCategories || []" :initialChargeCategoryId="selectedChargeCategoryId"
            @close="closeChargeModal" @created="handleChargeCreated" />

        <ChargeParameterModal :show="showParameterModal" :units="props.pathologyUnits"
            :existing-parameters="testParameters" @close="closeParameterModal"
            @parameter-created="handleParameterCreated" />

        <UnitModal :show="showUnitModal" :existing-units="props.pathologyUnits" @close="closeUnitModal" @unit-created="handleUnitCreated" />

    </component>
</template>

<style scoped>
@reference "../../../../css/app.css";

.form-input {
    @apply block w-full p-2 text-sm border rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600;
}

.dropdown-option-highlight {
    background-color: #2563eb !important;
    color: #ffffff !important;
}
</style>