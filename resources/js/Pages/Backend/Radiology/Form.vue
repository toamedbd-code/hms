<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AlertMessage from '@/Components/AlertMessage.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';
import PatientModal from '@/Components/PatientModal.vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

const props = defineProps(['radiology', 'id', 'radiologyTests', 'patients', 'doctors', 'radiologyNo', 'billNo', 'lastCaseId']);

// Search functionality
const searchQueries = ref({});
const showDropdowns = ref({});
const selectedTestIndex = ref({}); // Track selected index in dropdown for arrow navigation
const dropdownRefs = ref({}); // Store dropdown element references

const createTestRow = () => ({
    id: Date.now() + Math.random(),
    testId: '',
    test_name: '',
    reportDays: '',
    reportDate: '',
    tax: 0,
    amount: 0,
});

// Initialize test rows
const testRows = ref([]);

// Initialize form with correct field names matching database
const form = useForm({
    case_id: props.radiology?.case_id ?? '',
    patient_id: props.radiology?.patient_id ?? '',
    referral_doctor_id: props.radiology?.referral_doctor_id ?? '',
    doctor_name: props.radiology?.doctor_name ?? '',
    note: props.radiology?.note ?? '',

    // Financial fields
    total_amount: props.radiology?.total_amount ?? 0,
    tax_amount: props.radiology?.tax_amount ?? 0,
    discount_amount: props.radiology?.discount_amount ?? 0,
    discount_percentage: props.radiology?.discount_percentage ?? 0,
    net_amount: props.radiology?.net_amount ?? 0,

    // Payment fields
    payment_mode: props.radiology?.payment_mode ?? 'Cash',
    payment_amount: props.radiology?.payment_amount ?? 0,

    // Test details will be JSON
    tests: [],

    _method: props.id ? 'put' : 'post',
});

// Initialize test rows on component mount
onMounted(() => {
    if (props.radiology && props.radiology.tests && props.radiology.tests.length > 0) {
        // For edit mode, populate test rows from existing data
        testRows.value = props.radiology.tests.map((test, index) => ({
            id: test.id || (Date.now() + index),
            testId: test.testId || '',
            test_name: test.test_name || '',
            reportDays: test.reportDays || '',
            reportDate: test.reportDate || '',
            tax: parseFloat(test.tax || 0),
            amount: parseFloat(test.amount || 0),
        }));

        // Initialize search queries for existing tests
        testRows.value.forEach(test => {
            if (test.testId) {
                searchQueries.value[test.id] = test.test_name;
            }
        });
    } else {
        // For create mode, start with one empty row
        testRows.value = [createTestRow()];
    }

    // Initialize search queries and dropdown states
    testRows.value.forEach(test => {
        if (!searchQueries.value[test.id]) {
            searchQueries.value[test.id] = '';
        }
        showDropdowns.value[test.id] = false;
        selectedTestIndex.value[test.id] = -1;
    });
});

// Modal state for patient creation
const isPatientModalOpen = ref(false);
const patientsList = ref([...props.patients]);

const openPatientModal = () => {
    isPatientModalOpen.value = true;
};

const closePatientModal = () => {
    isPatientModalOpen.value = false;
};

const handlePatientCreated = (newPatient) => {
    patientsList.value.push(newPatient);
    form.patient_id = newPatient.id;

    router.reload({
        only: ['patients'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            patientsList.value = [...page.props.patients];
        }
    });
};

// Check if test is already selected (prevent duplicates)
const isTestAlreadySelected = (testId, currentTestRowId) => {
    return testRows.value.some(row => row.testId === testId && row.id !== currentTestRowId);
};

// Test search functionality
const getFilteredTests = (testId) => {
    const query = searchQueries.value[testId]?.toLowerCase() || '';
    if (!query) return props.radiologyTests || [];

    return (props.radiologyTests || []).filter(test =>
        test.test_name.toLowerCase().includes(query) ||
        test.test_short_name?.toLowerCase().includes(query)
    );
};

const handleTestSearch = (index, value) => {
    const test = testRows.value[index];
    searchQueries.value[test.id] = value;
    selectedTestIndex.value[test.id] = -1; // Reset selection

    // Show dropdown if there's input or on focus
    showDropdowns.value[test.id] = true;

    // Clear test selection if search is cleared
    if (!value) {
        test.testId = '';
        test.test_name = '';
        test.reportDays = '';
        test.reportDate = '';
        test.tax = 0;
        test.amount = 0;
    }
};

const handleTestFocus = (index) => {
    const test = testRows.value[index];
    showDropdowns.value[test.id] = true;
    selectedTestIndex.value[test.id] = -1; // Reset selection when focusing
};

const handleTestBlur = (index, event) => {
    const test = testRows.value[index];

    // Don't hide if clicking on dropdown
    const isClickingDropdown = event.relatedTarget &&
        event.relatedTarget.closest(`[data-dropdown-id="${test.id}"]`);

    if (!isClickingDropdown) {
        // Delay hiding dropdown to allow for clicks
        setTimeout(() => {
            showDropdowns.value[test.id] = false;
            selectedTestIndex.value[test.id] = -1;
        }, 200);
    }
};

// Handle keyboard navigation in test search
const handleTestKeydown = (index, event) => {
    const test = testRows.value[index];
    const filteredTests = getFilteredTests(test.id);

    if (!showDropdowns.value[test.id] || filteredTests.length === 0) {
        return;
    }

    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            selectedTestIndex.value[test.id] = Math.min(
                selectedTestIndex.value[test.id] + 1,
                filteredTests.length - 1
            );
            scrollToSelectedOption(test.id);
            break;

        case 'ArrowUp':
            event.preventDefault();
            selectedTestIndex.value[test.id] = Math.max(
                selectedTestIndex.value[test.id] - 1,
                0
            );
            scrollToSelectedOption(test.id);
            break;

        case 'Enter':
            event.preventDefault();
            const selectedIndex = selectedTestIndex.value[test.id];
            if (selectedIndex >= 0 && selectedIndex < filteredTests.length) {
                handleDropdownClick(index, filteredTests[selectedIndex]);
            } else if (filteredTests.length > 0) {
                // If no specific selection, select first item
                handleDropdownClick(index, filteredTests[0]);
            }
            break;

        case 'Escape':
            event.preventDefault();
            showDropdowns.value[test.id] = false;
            selectedTestIndex.value[test.id] = -1;
            break;
    }
};

const scrollToSelectedOption = (testId) => {
    const selectedIndex = selectedTestIndex.value[testId];
    if (selectedIndex >= 0) {
        setTimeout(() => {
            const dropdownElement = document.querySelector(`[data-dropdown-id="${testId}"]`);
            const selectedOption = dropdownElement?.querySelector(`[data-option-index="${selectedIndex}"]`);
            if (selectedOption && dropdownElement) {
                const optionTop = selectedOption.offsetTop;
                const optionHeight = selectedOption.offsetHeight;
                const dropdownScrollTop = dropdownElement.scrollTop;
                const dropdownHeight = dropdownElement.clientHeight;

                if (optionTop < dropdownScrollTop) {
                    dropdownElement.scrollTop = optionTop;
                } else if (optionTop + optionHeight > dropdownScrollTop + dropdownHeight) {
                    dropdownElement.scrollTop = optionTop + optionHeight - dropdownHeight;
                }
            }
        }, 0);
    }
};

const handleDropdownClick = (index, selectedTest) => {
    const test = testRows.value[index];

    // Check for duplicate
    if (isTestAlreadySelected(selectedTest.id, test.id)) {
        alert('This test is already selected. Please choose a different test.');
        return;
    }

    test.testId = selectedTest.id;
    test.test_name = selectedTest.test_name;
    test.reportDays = selectedTest.report_days || 0;
    test.tax = parseFloat(selectedTest.tax || 0);
    test.amount = parseFloat(selectedTest.amount || selectedTest.standard_charge || 0);

    // Set search query to selected test name
    searchQueries.value[test.id] = selectedTest.test_name;
    showDropdowns.value[test.id] = false;
    selectedTestIndex.value[test.id] = -1;

    // Calculate report date
    if (selectedTest.report_days && selectedTest.report_days > 0) {
        const currentDate = new Date();
        currentDate.setDate(currentDate.getDate() + parseInt(selectedTest.report_days));
        test.reportDate = currentDate.toISOString().split('T')[0];
    } else {
        test.reportDate = new Date().toISOString().split('T')[0];
    }

    // Jump to report date field
    setTimeout(() => {
        document.getElementById(`reportDate_${index}`)?.focus();
    }, 100);
};

const handleReportDateEnter = (index) => {
    const test = testRows.value[index];

    // Validate that we have a selected test
    if (!test.testId || !test.reportDate) {
        if (!test.testId) {
            alert('Please select a test first.');
            setTimeout(() => {
                document.getElementById(`testSearch_${index}`)?.focus();
            }, 100);
        }
        return;
    }

    // Add to cart (this is already happening in real-time through the computed properties)
    // Focus next test or add new row if this is the last one
    if (index < testRows.value.length - 1) {
        const nextIndex = index + 1;
        setTimeout(() => {
            document.getElementById(`testSearch_${nextIndex}`)?.focus();
        }, 100);
    } else {
        // This is the last row, add a new one and focus it
        addTest();
    }
};

// Computed properties for calculations
const totalAmount = computed(() => {
    return testRows.value.reduce((sum, row) => sum + (parseFloat(row.amount) || 0), 0);
});

const taxAmount = computed(() => {
    return testRows.value.reduce((sum, row) => {
        const rowAmount = parseFloat(row.amount) || 0;
        const rowTax = parseFloat(row.tax) || 0;
        return sum + (rowAmount * rowTax) / 100;
    }, 0);
});

// const onDiscountPercentageChange = () => {
//     if (form.discount_percentage > 0) {
//         form.discount_percentage = (totalAmount.value * parseFloat(form.discount_percentage)) / 100;
//     }
// };

// const onDiscountAmountChange = () => {
//     if (form.discount_amount > 0) {
//         form.discount_amount = (totalAmount.value - parseFloat(form.discount_percentage));
//     }
// };

const onDiscountPercentageChange = () => {
    if (form.discount_percentage > 0) {
        form.discount_amount = 0;
    }
};

const onDiscountAmountChange = () => {
    if (form.discount_amount > 0) {
        form.discount_percentage = 0;
    }
};

const discountAmount = computed(() => {
    if (form.discount_percentage > 0) {
        return (totalAmount.value * parseFloat(form.discount_percentage)) / 100;
    }
    return parseFloat(form.discount_amount) || 0;
});

const netAmount = computed(() => {
    return totalAmount.value + taxAmount.value - discountAmount.value;
});

// Auto-set payment amount to net amount when net amount changes
watch(netAmount, (newAmount) => {
    if (!form.payment_amount || form.payment_amount === 0) {
        form.payment_amount = newAmount;
    }
});

// Watch for form updates
watch([totalAmount, taxAmount, discountAmount, netAmount], ([total, tax, discount, net]) => {
    form.total_amount = total;
    form.tax_amount = tax;
    form.discount_amount = discount;
    form.net_amount = net;
});

const addTest = () => {
    const newTest = createTestRow();
    testRows.value.push(newTest);
    searchQueries.value[newTest.id] = '';
    showDropdowns.value[newTest.id] = false;
    selectedTestIndex.value[newTest.id] = -1;

    // Focus on the new test field
    setTimeout(() => {
        const newIndex = testRows.value.length - 1;
        document.getElementById(`testSearch_${newIndex}`)?.focus();
    }, 100);
};

const removeTest = (testId) => {
    if (testRows.value.length > 1) {
        testRows.value = testRows.value.filter(row => row.id !== testId);
        delete searchQueries.value[testId];
        delete showDropdowns.value[testId];
        delete selectedTestIndex.value[testId];
    }
};

const submit = () => {
    const routeName = props.id ? route('backend.radiology.update', props.id) : route('backend.radiology.store');

    // Filter valid tests and prepare data for backend
    const validTests = testRows.value.filter(row => row.testId && row.amount > 0);

    if (validTests.length === 0) {
        alert('Please select at least one test with valid amount.');
        return;
    }

    // Check for duplicates before submitting
    const testIds = validTests.map(test => test.testId);
    const uniqueTestIds = [...new Set(testIds)];

    if (testIds.length !== uniqueTestIds.length) {
        alert('Duplicate tests found. Please remove duplicate entries.');
        return;
    }

    // Prepare tests data in the format expected by backend
    const testsData = validTests.map(test => ({
        testId: test.testId,
        testName: test.test_name,
        reportDays: test.reportDays || 0,
        reportDate: test.reportDate,
        tax: test.tax || 0,
        amount: test.amount,
    }));

    const formData = {
        ...form.data(),
        tests: testsData,
        subtotal: totalAmount.value,
        net_amount: netAmount.value,
    };

    form.transform(() => ({
        ...formData,
        patient_id: typeof formData.patient_id === 'object' ? formData.patient_id.id : formData.patient_id,
    })).post(routeName, {
        onSuccess: (response) => {
            if (props.id) {
                router.reload({
                    only: ['radiology'],
                    preserveScroll: true,
                    onSuccess: () => {
                        const successMessage = response?.props?.flash?.successMessage;
                        const billId = response?.props?.flash?.billId;

                        if (successMessage && billId) {
                            window.open(route("backend.download.invoice", { id: billId, module: 'radiology' }), "_blank");
                        }
                    }
                });
            } else {
                form.reset();
                testRows.value = [createTestRow()];
                searchQueries.value = {};
                showDropdowns.value = {};
                selectedTestIndex.value = {};

                const successMessage = response?.props?.flash?.successMessage;
                const billId = response?.props?.flash?.billId;

                if (successMessage && billId) {
                    window.open(route("backend.download.invoice", { id: billId, module: 'radiology' }), "_blank");
                }
            }
            displayResponse(response);
        },
        onError: (errorObject) => {
            displayWarning(errorObject);
        },
    });
};

// Helper function to format currency
const formatCurrency = (amount) => {
    return parseFloat(amount || 0).toFixed(2);
};

const goToRadiologyList = () => {
    router.visit(route('backend.radiology.index'));
};
</script>

<template>
    <BackendLayout>
        <!-- Patient Modal -->
        <PatientModal :isOpen="isPatientModalOpen" @close="closePatientModal" @patientCreated="handlePatientCreated" />

        <!-- Main Form Container -->
        <div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md">
            <!-- Header Bar -->
            <div class="flex items-center justify-between w-full px-4 bg-gray-100 rounded-md">
                <div class="flex items-center space-x-4">
                    <div class="relative min-w-[280px]">
                        <div class="relative">
                            <div class="col-span-1">
                                <Multiselect v-model="form.patient_id" :options="patients" :track-by="'id'"
                                    :label="'name'" placeholder="Search and select a patient"
                                    class="w-full text-sm h-[30px] rounded-md border border-slate-300" />
                                <InputError class="mt-1" :message="form.errors.patient_id" />
                            </div>
                        </div>
                    </div>
                    <button @click="openPatientModal"
                        class="px-3 py-2.5 text-sm text-white bg-blue-600 rounded hover:bg-blue-700">
                        + New Patient
                    </button>
                </div>
                <div class="p-2 py-2 flex items-center space-x-2">
                    <div class="flex items-center space-x-3">
                        <button @click="goToRadiologyList"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z">
                                </path>
                            </svg>
                            Radiology List
                        </button>
                    </div>
                </div>
            </div>

            <!-- Form Content -->
            <form @submit.prevent="submit" class="p-3">
                <!-- Test Section - Grid Layout -->
                <div class="mb-6">
                    <div v-for="(test, index) in testRows" :key="test.id" class="mb-4">
                        <div class="grid grid-cols-5 gap-6">
                            <!-- Searchable Test Name Field -->
                            <div class="relative">
                                <div class="mb-2">
                                    <span class="text-sm font-medium text-black">Test Name</span>
                                    <span class="text-red-500 ml-1">*</span>
                                </div>
                                <input :id="`testSearch_${index}`" v-model="searchQueries[test.id]"
                                    @input="handleTestSearch(index, $event.target.value)"
                                    @focus="handleTestFocus(index)" @blur="handleTestBlur(index, $event)"
                                    @keydown="handleTestKeydown(index, $event)" type="text" placeholder="Search test..."
                                    class="block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none"
                                    autocomplete="off" />

                                <!-- Search Dropdown -->
                                <div v-if="showDropdowns[test.id] && getFilteredTests(test.id).length > 0"
                                    :data-dropdown-id="test.id"
                                    class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto">
                                    <div v-for="(radiologyTest, optionIndex) in getFilteredTests(test.id)"
                                        :key="radiologyTest.id" :data-option-index="optionIndex"
                                        @click="handleDropdownClick(index, radiologyTest)" :class="[
                                            'px-3 py-2 text-sm cursor-pointer border-b border-gray-100 last:border-b-0',
                                            selectedTestIndex[test.id] === optionIndex
                                                ? 'bg-blue-100 text-blue-700'
                                                : 'hover:bg-blue-50 hover:text-blue-700',
                                            isTestAlreadySelected(radiologyTest.id, test.id)
                                                ? 'opacity-50 cursor-not-allowed'
                                                : ''
                                        ]">
                                        <div class="font-medium">
                                            {{ radiologyTest.test_name }}
                                            <span v-if="isTestAlreadySelected(radiologyTest.id, test.id)"
                                                class="text-red-500 text-xs ml-2">(Already Selected)</span>
                                        </div>
                                        <div class="text-xs text-gray-500">Amount: {{ radiologyTest.amount ||
                                            radiologyTest.standard_charge || 'N/A' }} Tk.</div>
                                    </div>
                                </div>

                                <!-- No results found -->
                                <div v-if="showDropdowns[test.id] && getFilteredTests(test.id).length === 0 && searchQueries[test.id]"
                                    class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg">
                                    <div class="px-3 py-2 text-sm text-gray-500 text-center">
                                        No tests found
                                    </div>
                                </div>

                                <InputError class="mt-1" :message="form.errors[`tests.${index}.testId`]" />
                            </div>

                            <div>
                                <div class="mb-2">
                                    <span class="text-sm font-medium text-black">Report Days</span>
                                </div>
                                <input v-model="test.reportDays" type="number"
                                    class="block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none" />
                                <InputError class="mt-1" :message="form.errors[`tests.${index}.reportDays`]" />
                            </div>

                            <div>
                                <div class="mb-2">
                                    <span class="text-sm font-medium text-black">Report Date</span>
                                    <span class="text-red-500 ml-1">*</span>
                                </div>
                                <input :id="`reportDate_${index}`" v-model="test.reportDate" type="date"
                                    @keydown.enter.prevent="handleReportDateEnter(index)"
                                    class="block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none" />
                                <InputError class="mt-1" :message="form.errors[`tests.${index}.reportDate`]" />
                            </div>

                            <div>
                                <div class="mb-2">
                                    <span class="text-sm font-medium text-black">Tax</span>
                                </div>
                                <div class="relative">
                                    <input v-model="test.tax" type="number" step="0.01"
                                        class="block w-full px-3 py-2 pr-8 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none" />
                                    <span class="absolute right-3 top-2 text-sm text-gray-500">%</span>
                                </div>
                                <InputError class="mt-1" :message="form.errors[`tests.${index}.tax`]" />
                            </div>

                            <div>
                                <div class="mb-1 flex justify-between items-center">
                                    <span class="text-sm font-medium text-black">Amount (Tk.)</span>
                                    <button type="button" @click="removeTest(test.id)" :disabled="testRows.length === 1"
                                        :class="testRows.length === 1 ? 'text-gray-300 cursor-not-allowed' : 'text-red-500 hover:text-red-700'"
                                        class="text-lg font-bold">×</button>
                                </div>
                                <input v-model="test.amount" type="number" step="0.01" disabled
                                    class="block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none" />
                                <InputError class="mt-1" :message="form.errors[`tests.${index}.amount`]" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add Button -->
                <div class="mb-8">
                    <button type="button" @click="addTest"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-green-600 rounded hover:bg-green-700">
                        <span class="mr-1">+</span> Add Test
                    </button>
                </div>

                <!-- Doctor, Note and Summary Section -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-6">
                    <!-- Left Column - Doctor and Note -->
                    <div class="space-y-6">
                        <div>
                            <InputLabel for="referral_doctor_id" value="Referral Doctor" />
                            <select id="referral_doctor_id" v-model="form.referral_doctor_id"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                                <option value="">Select</option>
                                <option v-for="doctor in doctors" :key="doctor.id" :value="doctor.id">{{ doctor.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.referral_doctor_id" />
                        </div>

                        <div>
                            <InputLabel for="doctor_name" value="Doctor Name" />
                            <input id="doctor_name" v-model="form.doctor_name" type="text"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.doctor_name" />
                        </div>

                        <div>
                            <InputLabel for="note" value="Note" />
                            <textarea id="note" v-model="form.note" rows="4"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"></textarea>
                            <InputError class="mt-2" :message="form.errors.note" />
                        </div>
                    </div>

                    <!-- Right Column - Bill Summary -->
                    <div class="space-y-4 bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 border-b pb-2">Bill Summary
                        </h3>

                        <div class="grid grid-cols-2 gap-4 items-center">
                            <InputLabel value="Subtotal (Tk.)" />
                            <div class="text-right font-semibold text-lg dark:text-white">{{ formatCurrency(totalAmount)
                            }}</div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 items-center">
                            <InputLabel value="Tax Amount (Tk.)" />
                            <div class="text-right font-semibold text-orange-600 dark:text-orange-400">{{
                                formatCurrency(taxAmount) }}</div>
                        </div>

                        <!-- Discount Percentage -->
                        <div class="grid grid-cols-2 gap-4 items-center">
                            <InputLabel value="Discount (%)" />
                            <div class="flex items-center justify-end space-x-2">
                                <input v-model.number="form.discount_percentage" @input="onDiscountPercentageChange"
                                    type="number" step="0.01" min="0" max="100" placeholder="0"
                                    class="w-20 p-1 text-sm rounded border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 text-right" />
                                <span class="text-xs text-gray-500">%</span>
                                <span class="font-semibold text-red-600 dark:text-red-400 min-w-[80px] text-right">
                                    {{ form.discount_percentage > 0 ? '-' + formatCurrency((totalAmount *
                                        form.discount_percentage) / 100) : '0.00' }}
                                </span>
                            </div>
                        </div>

                        <!-- Fixed Discount Amount -->
                        <div class="grid grid-cols-2 gap-4 items-center">
                            <InputLabel value="Discount (Tk.)" />
                            <div class="flex items-center justify-end space-x-2">
                                <input v-model.number="form.discount_amount" @input="onDiscountAmountChange"
                                    type="number" step="0.01" min="0" placeholder="0"
                                    class="w-24 p-1 text-sm rounded border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 text-right" />
                                <span class="text-xs text-gray-500">Tk</span>
                                <span class="font-semibold text-red-600 dark:text-red-400 min-w-[80px] text-right">
                                    {{ form.discount_amount > 0 ? '-' + formatCurrency(form.discount_amount) : '0.00' }}
                                </span>
                            </div>
                        </div>

                        <!-- Total Discount Display -->
                        <div class="grid grid-cols-2 gap-4 items-center bg-red-50 dark:bg-red-900/20 p-2 rounded">
                            <InputLabel value="Total Discount" class="font-semibold" />
                            <div class="text-right font-bold text-red-600 dark:text-red-400">
                                -{{ formatCurrency(discountAmount) }}
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 items-center border-t pt-3 dark:border-slate-600">
                            <InputLabel value="Net Amount (Tk.)" class="text-lg font-bold" />
                            <div class="text-right font-bold text-xl text-green-600 dark:text-green-400">{{
                                formatCurrency(netAmount) }}</div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mt-4">
                            <div>
                                <InputLabel for="payment_mode" value="Payment Mode" />
                                <select id="payment_mode" v-model="form.payment_mode"
                                    class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                                    <option value="Cash">Cash</option>
                                    <option value="Card">Card</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Mobile Banking">Mobile Banking</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.payment_mode" />
                            </div>

                            <div>
                                <InputLabel for="payment_amount" value="Payment Amount (Tk.)" />
                                <input id="payment_amount" v-model.number="form.payment_amount" type="number"
                                    step="0.01" min="0"
                                    class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                                <InputError class="mt-2" :message="form.errors.payment_amount" />
                                <div v-if="form.payment_amount < netAmount" class="text-xs text-orange-600 mt-1">
                                    Due: {{ formatCurrency(netAmount - form.payment_amount) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex items-center justify-end mt-6 pt-4 border-t dark:border-slate-600">
                    <PrimaryButton type="submit" class="ms-4" :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing">
                        {{ ((props.id ?? false) ? 'Update Bill' : 'Create Bill') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </BackendLayout>
</template>

<style scoped>
input[type="number"],
input[type="date"],
select {
    min-width: 80px;
}

.calculation-field {
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
}

.dark .calculation-field {
    background-color: #1e293b;
    border-color: #475569;
}

/* Highlight selected dropdown option */
.dropdown-option-selected {
    background-color: #dbeafe !important;
    color: #1e40af !important;
}

/* Smooth transitions */
.dropdown-container {
    transition: all 0.2s ease-in-out;
}
</style>