<script setup>
import { ref, onMounted, computed, watch, nextTick } from 'vue';
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

const props = defineProps(['pathology', 'id', 'patients', 'raferrers', 'doctors', 'billNo', 'lastCaseId', 'billnumber', 'pathologyTests']);

const createTestRow = () => ({
    id: Date.now() + Math.random(),
    testId: '',
    testName: '',
    reportDays: '',
    reportDate: '',
    tax: '',
    amount: ''
});

const testRows = ref([createTestRow()]);
const searchQueries = ref({});
const showDropdowns = ref({});
const focusedRowIndex = ref(0);

const form = useForm({
    case_id: props.pathology?.case_id ?? '',
    patient_id: props.pathology?.patient_id ?? '',
    doctor_id: props.pathology?.doctor_id ?? '',
    apply_tpa: Boolean(props.pathology?.apply_tpa) || false,
    bill_no: props.pathology?.bill_no ?? '',
    date: props.pathology?.date ?? new Date().toISOString().split('T')[0],
    tests: [],
    note: props.pathology?.note ?? '',
    payee_id: props.pathology?.payee_id ?? '',
    commission_percentage: props.pathology?.commission_percentage ?? '',
    commission_amount: props.pathology?.commission_amount ?? '',

    subtotal: props.pathology?.subtotal ?? 0,

    discount_percentage: props.pathology?.discount_percentage ?? 0,
    discount_amount: props.pathology?.discount_amount ?? 0,

    vat_percentage: props.pathology?.vat_percentage ?? 0,
    vat_amount: props.pathology?.vat_amount ?? 0,

    tax_percentage: props.pathology?.tax_percentage ?? 0,
    tax_amount: props.pathology?.tax_amount ?? 0,

    extra_vat_percentage: props.pathology?.extra_vat_percentage ?? 0,
    extra_vat_amount: props.pathology?.extra_vat_amount ?? 0,

    extra_discount: props.pathology?.extra_discount ?? 0,
    net_amount: props.pathology?.net_amount ?? 0,
    payment_mode: props.pathology?.payment_mode ?? 'Cash',
    payment_amount: props.pathology?.payment_amount ?? '',
    doctor_name: props.pathology?.doctor_name ?? '',

    _method: props.pathology?.id ? 'put' : 'post',
});

// Get filtered tests based on search query
const getFilteredTests = (rowId) => {
    const query = searchQueries.value[rowId] || '';
    if (!query) return props.pathologyTests;

    return props.pathologyTests.filter(test =>
        test.test_name.toLowerCase().includes(query.toLowerCase())
    );
};

// Check if test is already selected
const isTestAlreadySelected = (testId, currentRowId) => {
    return testRows.value.some(row => row.id !== currentRowId && row.testId === testId);
};

// Handle test search input
const handleTestSearch = (rowIndex, value) => {
    const rowId = testRows.value[rowIndex].id;
    searchQueries.value[rowId] = value;
    showDropdowns.value[rowId] = true;
};

const highlightedIndex = ref(-1);

// Add these methods for arrow key navigation
const handleTestSearchKeyDown = (rowIndex, event) => {
    const rowId = testRows.value[rowIndex].id;
    const filteredTests = getFilteredTests(rowId);

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        highlightedIndex.value = Math.min(highlightedIndex.value + 1, filteredTests.length - 1);
        scrollToHighlighted(rowIndex);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlightedIndex.value = Math.max(highlightedIndex.value - 1, -1);
        scrollToHighlighted(rowIndex);
    } else if (event.key === 'Enter' && highlightedIndex.value >= 0) {
        event.preventDefault();
        selectTest(rowIndex, filteredTests[highlightedIndex.value]);
    }
};

const scrollToHighlighted = (rowIndex) => {
    nextTick(() => {
        const dropdown = (typeof document !== 'undefined') ? document.querySelector(`#dropdown_${rowIndex}`) : null;
        const highlightedItem = dropdown?.querySelector('.highlighted');
        if (highlightedItem) {
            highlightedItem.scrollIntoView({ block: 'nearest' });
        }
    });
};

const resetHighlight = () => {
    highlightedIndex.value = -1;
};

const selectTest = async (rowIndex, test) => {
    const row = testRows.value[rowIndex];

    // Check if test already selected
    if (isTestAlreadySelected(test.id, row.id)) {
        alert('This test is already added to the cart!');
        await focusTestField(rowIndex);
        return;
    }

    // Set test data
    row.testId = test.id;
    row.testName = test.test_name;
    row.reportDays = test.report_days || '';
    row.tax = test.tax || '';
    row.amount = test.amount || test.standard_charge || '';

    // Auto-calculate report date
    if (test.report_days) {
        const today = new Date();
        const reportDate = new Date(today.getTime() + (test.report_days * 24 * 60 * 60 * 1000));
        row.reportDate = reportDate.toISOString().split('T')[0];
    }

    // Clear search and hide dropdown
    searchQueries.value[row.id] = test.test_name;
    showDropdowns.value[row.id] = false;

    // Focus on report date field
    await nextTick();
    const reportDateField = (typeof document !== 'undefined') ? document.querySelector(`#reportDate_${rowIndex}`) : null;
    if (reportDateField) {
        reportDateField.focus();
    }
};

// Handle Enter key on test search
const handleTestSearchEnter = async (rowIndex) => {
    const rowId = testRows.value[rowIndex].id;
    const query = searchQueries.value[rowId] || '';
    const filteredTests = getFilteredTests(rowId);

    if (filteredTests.length > 0) {
        await selectTest(rowIndex, filteredTests[0]);
    }
};

// Handle Enter key on report date
const handleReportDateEnter = async (rowIndex) => {
    addTest();

    await nextTick();
    const newRowIndex = testRows.value.length - 1;
    await focusTestField(newRowIndex);
};

// Focus on test field
const focusTestField = async (rowIndex) => {
    await nextTick();
    const testField = (typeof document !== 'undefined') ? document.querySelector(`#testSearch_${rowIndex}`) : null;
    if (testField) {
        testField.focus();
        testField.select();
    }
};

const handleDropdownClick = async (rowIndex, test) => {
    await selectTest(rowIndex, test);
};

// Handle input focus
const handleTestFocus = (rowIndex) => {
    const rowId = testRows.value[rowIndex].id;
    focusedRowIndex.value = rowIndex;
    showDropdowns.value[rowId] = true;
};

const handleTestBlur = (rowIndex) => {
    const rowId = testRows.value[rowIndex].id;
    setTimeout(() => {
        showDropdowns.value[rowId] = false;
        highlightedIndex.value = -1;
    }, 200);
};

// Computed properties
const previousPaidAmount = computed(() => {
    return props.pathology?.payment_amount ? parseFloat(props.pathology.payment_amount) : 0;
});

const totalPaidAmount = computed(() => {
    return parseFloat(form.payment_amount) || 0;
});

const dueAmount = computed(() => {
    const netAmount = parseFloat(form.net_amount) || 0;
    return netAmount - totalPaidAmount.value;
});

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
            if (!form.patient_id && newPatient.id) {
                form.patient_id = newPatient.id;
            }
        }
    });
};

const generatePathologyNumber = async () => {
    try {
        const lastPathologyNo = props.pathologyNo ? props.pathologyNo : '';
        let newNumber = 1;

        if (lastPathologyNo && lastPathologyNo.startsWith('PATB')) {
            const numberPart = parseInt(lastPathologyNo.replace('PATB', ''), 10);
            if (!isNaN(numberPart)) {
                newNumber = numberPart + 1;
            }
        }

        return `PATB${newNumber}`;
    } catch (error) {
        console.error('Error generating pathology number:', error);
        return 'PATB1';
    }
};

const generateBillNumber = async () => {
    try {
        const lastBillNo = props.billNo ? props.billNo : '';
        let newNumber = '0001';
        const yearMonth = new Date().toISOString().slice(0, 7).replace('-', '');

        if (lastBillNo && lastBillNo.startsWith('BILL')) {
            const lastNumber = lastBillNo.slice(8);
            const num = parseInt(lastNumber, 10);
            if (!isNaN(num)) {
                newNumber = String(num + 1).padStart(4, '0');
            }
        }

        return `BILL${yearMonth}${newNumber}`;
    } catch (error) {
        console.error('Error generating bill number:', error);
        return `BILL${new Date().toISOString().slice(0, 7).replace('-', '')}0001`;
    }
};

const generateCaseId = () => {
    const timestamp = Date.now().toString().slice(-6);
    const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
    return `CASE${timestamp}${random}`;
};

const billStatus = computed(() => {
    if (props.pathology?.bill_no) {
        return {
            text: props.pathology.bill_no,
            class: 'text-blue-600 font-medium',
            showSpinner: false
        };
    }
    if (form.bill_no) {
        return {
            text: form.bill_no,
            class: 'text-blue-600 font-medium',
            showSpinner: false
        };
    }
    return {
        text: 'Generating...',
        class: 'text-amber-600 font-medium',
        showSpinner: true
    };
});

const caseStatus = computed(() => {
    if (form.case_id) {
        return {
            text: form.case_id,
            class: 'text-blue-600 font-medium',
            showSpinner: false
        };
    }
    return {
        text: 'Generating...',
        class: 'text-amber-600 font-medium',
        showSpinner: true
    };
});

onMounted(async () => {

    if (!props.pathology?.id) {
        form.case_id = generateCaseId();
        form.payment_amount = '';

        setTimeout(async () => {
            form.pathology_no = await generatePathologyNumber();
            form.bill_no = await generateBillNumber();
        }, 1000);
    } else {
        form.payment_amount = props.pathology.payment_amount || '';
    }

    if (props.pathology?.tests && props.pathology.tests.length > 0) {
        testRows.value = props.pathology.tests.map(test => ({
            id: test.id || Date.now() + Math.random(),
            testId: test.testId || '',
            testName: test.test_name || '',
            reportDays: test.report_days || '',
            reportDate: test.reportDate || '',
            tax: test.tax || '',
            amount: test.amount || ''
        }));

        testRows.value.forEach(row => {
            if (row.testName) {
                searchQueries.value[row.id] = row.testName;
            }
        });
    }
    updateCalculations();

    await focusTestField(0);
});

const updateCalculations = () => {
    let subtotal = 0;
    testRows.value.forEach(test => {
        const amount = parseFloat(test.amount) || 0;
        subtotal += amount;
    });

    form.subtotal = parseFloat(subtotal.toFixed(2));

    // Calculate discount amount from percentage
    const discount_percentage = parseFloat(form.discount_percentage) || 0;
    form.discount_amount = parseFloat((subtotal * discount_percentage / 100).toFixed(2));

    // Calculate VAT amount from percentage (after discount)
    const vat_percentage = parseFloat(form.vat_percentage) || 0;
    const afterDiscount = subtotal - form.discount_amount;
    form.vat_amount = parseFloat((afterDiscount * vat_percentage / 100).toFixed(2));

    // Calculate tax amount from percentage (after discount)
    const tax_percentage = parseFloat(form.tax_percentage) || 0;
    form.tax_amount = parseFloat((afterDiscount * tax_percentage / 100).toFixed(2));

    // Calculate extra VAT amount from percentage (after discount)
    const extra_vat_percentage = parseFloat(form.extra_vat_percentage) || 0;
    form.extra_vat_amount = parseFloat((afterDiscount * extra_vat_percentage / 100).toFixed(2));

    // Calculate net amount
    const extra_discount = parseFloat(form.extra_discount) || 0;

    form.net_amount = parseFloat((subtotal
        - form.discount_amount
        + form.vat_amount
        + form.tax_amount
        + form.extra_vat_amount
        - extra_discount).toFixed(2));

    // Ensure net amount is not negative
    if (form.net_amount < 0) {
        form.net_amount = 0;
    }
};

watch(() => form.discount_percentage, updateCalculations);
watch(() => form.vat_percentage, updateCalculations);
watch(() => form.tax_percentage, updateCalculations);
watch(() => form.extra_vat_percentage, updateCalculations);
watch(() => form.extra_discount, updateCalculations);

watch(testRows, () => {
    updateFormTests();
    updateCalculations();
}, { deep: true });

const updateFormTests = () => {
    form.tests = testRows.value.map(test => ({
        testId: test.testId,
        testName: test.testName,
        reportDays: test.reportDays,
        reportDate: test.reportDate,
        tax: test.tax,
        amount: test.amount
    }));
};

const submit = () => {

    if (!form.patient_id) {
        alert('Please select a patient');
        return;
    }

    if (testRows.value.length === 0 || !testRows.value[0].testId) {
        alert('Please add at least one test');
        return;
    }

    updateFormTests();

    const routeName = props.id ? route('backend.pathology.update', props.id) : route('backend.pathology.store');

    // Open blank window synchronously to avoid popup blockers when navigating later
    let invoiceWindow = null;
    try {
        invoiceWindow = window.open('', '_blank');
    } catch (e) {
        invoiceWindow = null;
    }

    form.transform(data => ({
        ...data,
        patient_id: typeof data.patient_id === 'object' ? data.patient_id.id : data.patient_id,
        payment_amount: parseFloat(data.payment_amount) || 0,
        remember: '',
        isDirty: false,
    })).post(routeName, {
        onSuccess: (response) => {

            if (props.id) {
                router.reload({
                    only: ['pathology'],
                    preserveScroll: true,
                    onSuccess: () => {
                        const successMessage = response?.props?.flash?.successMessage;
                        const billId = response?.props?.flash?.billId;

                        if (billId) {
                            const invoiceUrl = route("backend.download.invoice", { id: billId, module: 'pathology' });
                            try {
                                if (invoiceWindow && !invoiceWindow.closed) {
                                    invoiceWindow.location = invoiceUrl;
                                } else {
                                    window.open(invoiceUrl, '_blank');
                                }
                            } catch (e) {
                                window.open(invoiceUrl, '_blank');
                            }
                        }
                    }
                });
            } else {
                form.reset();
                testRows.value = [createTestRow()];
                searchQueries.value = {};
                showDropdowns.value = {};

                const successMessage = response?.props?.flash?.successMessage;
                const billId = response?.props?.flash?.billId;

                if (billId) {
                    const invoiceUrl = route("backend.download.invoice", { id: billId, module: 'pathology' });
                    try {
                        if (invoiceWindow && !invoiceWindow.closed) {
                            invoiceWindow.location = invoiceUrl;
                        } else {
                            window.open(invoiceUrl, '_blank');
                        }
                    } catch (e) {
                        window.open(invoiceUrl, '_blank');
                    }
                }
            }

            displayResponse(response);
        },
        onError: (errorObject) => {
            console.log('Submission error:', errorObject);
            displayWarning(errorObject);
        },
    });
};

const addTest = () => {
    testRows.value.push(createTestRow());
    console.log('Test row added. Total rows:', testRows.value.length);
};

const removeTest = (testId) => {
    if (testRows.value.length > 1) {
        testRows.value = testRows.value.filter(test => test.id !== testId);

        delete searchQueries.value[testId];
        delete showDropdowns.value[testId];
        console.log('Test row removed. Remaining rows:', testRows.value.length);
    }
};

const selectedPayee = computed(() => {
    if (!form.payee_id) return null;
    return props.raferrers.find(referrer => referrer.id === form.payee_id);
});

watch(() => form.payee_id, (newPayeeId) => {
    if (newPayeeId && selectedPayee.value) {
        form.commission_percentage = selectedPayee.value.pathology_commission || 0;

        if (form.net_amount && form.commission_percentage) {
            const percentage = parseFloat(form.commission_percentage) || 0;
            const net_amount = parseFloat(form.net_amount) || 0;
            form.commission_amount = parseFloat((net_amount * percentage / 100).toFixed(2));
        }
    } else {
        form.commission_percentage = '';
        form.commission_amount = '';
    }
});

watch(() => form.commission_percentage, (newVal) => {
    if (newVal && form.net_amount) {
        const percentage = parseFloat(newVal) || 0;
        const net_amount = parseFloat(form.net_amount) || 0;
        form.commission_amount = parseFloat((net_amount * percentage / 100).toFixed(2));
    } else {
        form.commission_amount = '';
    }
});

watch(() => form.net_amount, (newVal) => {
    if (newVal && form.commission_percentage) {
        const percentage = parseFloat(form.commission_percentage) || 0;
        const net_amount = parseFloat(newVal) || 0;
        form.commission_amount = parseFloat((net_amount * percentage / 100).toFixed(2));
    }
});

const goToPathologyList = () => {
    router.visit(route('backend.pathology.index'));
};

const nextBillNumber = computed(() => {
    if (!props.billNo) return 'BILL' + new Date().toISOString().slice(0, 7).replace('-', '') + '0001';

    const prefix = props.billNo.match(/^[A-Za-z]+/)?.[0] || 'BILL';
    const numbers = props.billNo.match(/\d+$/)?.[0] || '0000';
    const nextNum = String(parseInt(numbers) + 1).padStart(numbers.length, '0');

    return `${prefix}${nextNum}`;
});

const nextCaseId = computed(() => {
    if (!props.lastCaseId) return 'CASE' + Date.now().toString().slice(-6) + Math.floor(Math.random() * 1000).toString().padStart(3, '0');

    const prefix = props.lastCaseId.match(/^[A-Za-z]+/)?.[0] || 'CASE';
    const numbers = props.lastCaseId.match(/\d+$/)?.[0] || '0000';
    const nextNum = String(parseInt(numbers) + 1).padStart(numbers.length, '0');

    return `${prefix}${nextNum}`;
});

</script>

<template>
    <BackendLayout>
        <!-- Patient Modal -->
        <PatientModal :isOpen="isPatientModalOpen" :tpas="props.tpas" @close="closePatientModal"
            @patientCreated="handlePatientCreated" />

        <div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-lg shadow-lg">
            <div
                class="flex items-center justify-between w-full px-4 py-2 bg-gray-100 border-b rounded-md border-gray-300">
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
                    <div class="flex items-center">
                        <input v-model="form.prescription_no" type="text" placeholder="Prescription No"
                            class="px-3 py-2.5 text-sm border border-gray-300 rounded-l focus:outline-none focus:border-blue-500" />
                        <button
                            class="px-3 py-2.5 text-sm bg-gray-200 border border-l-0 border-gray-300 rounded-r hover:bg-gray-300">
                            🔍
                        </button>
                    </div>
                    <div class="col-span-2">
                        <div class="flex items-center h-8">
                            <input v-model="form.apply_tpa" type="checkbox" id="apply_tpa"
                                class="w-3 h-3 text-blue-600 border-gray-300 rounded focus:ring-blue-500" />
                            <label for="apply_tpa" class="ml-1 text-xs font-medium text-black">Apply TPA</label>
                        </div>
                    </div>

                </div>
                <div class="p-2 py-2 flex items-center space-x-2">
                    <div class="flex items-center space-x-3">
                        <button @click="goToPathologyList"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z">
                                </path>
                            </svg>
                            Pathology List
                        </button>
                    </div>
                </div>
            </div>

            <!-- Enhanced Bill Info Header -->
            <div class="flex items-center justify-between px-4 py-2 bg-gray-50 border-b border-gray-200">
                <div class="flex space-x-8">
                    <!-- Bill Number Display -->
                    <div class="flex items-center space-x-2">
                        <span class="text-sm font-semibold">Bill No</span>
                        <span v-if="!props.id" class="text-blue-600 font-medium">{{ nextBillNumber ?? '' }}</span>
                        <span v-if="props.id" class="text-blue-600 font-medium">{{ props.billNo ?? '' }}</span>
                    </div>

                    <!-- Case ID Display -->
                    <div class="flex items-center space-x-2">
                        <span class="text-sm font-semibold">Case ID</span>
                        <div class="flex items-center">
                            <span v-if="!props.id" class="text-blue-600 font-medium">{{ nextCaseId ?? '' }}</span>
                            <span v-if="props.id" class="text-blue-600 font-medium">{{ props.lastCaseId ?? '' }}</span>
                        </div>
                    </div>
                </div>
                <span class="text-sm text-gray-600">Date {{ new Date().toLocaleDateString() }}</span>
            </div>

            <form @submit.prevent="submit" class="p-6">
                <!-- <AlertMessage /> -->

                <!-- Dynamic Test Rows -->
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
                                    @input="e => { handleTestSearch(index, e.target.value); resetHighlight(); }"
                                    @focus="handleTestFocus(index)" @blur="handleTestBlur(index)"
                                    @keydown.enter.prevent="handleTestSearchEnter(index)"
                                    @keydown="handleTestSearchKeyDown(index, $event)" type="text"
                                    placeholder="Search test..."
                                    class="block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none"
                                    autocomplete="off" />

                                <!-- Search Dropdown -->
                                <div v-if="showDropdowns[test.id]" :id="`dropdown_${index}`"
                                    class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto">
                                    <div v-for="(pathTest, testIndex) in getFilteredTests(test.id)" :key="pathTest.id"
                                        @click="handleDropdownClick(index, pathTest)"
                                        :class="{ 'bg-blue-100': highlightedIndex === testIndex, 'highlighted': highlightedIndex === testIndex }"
                                        class="px-3 py-2 text-sm cursor-pointer hover:bg-blue-50 hover:text-blue-700 border-b border-gray-100 last:border-b-0">
                                        <div class="font-medium">{{ pathTest.test_name }}</div>
                                        <div class="text-xs text-gray-500">Amount: {{ pathTest.amount ||
                                            pathTest.standard_charge || 'N/A' }} Tk.</div>
                                    </div>
                                    <div v-if="getFilteredTests(test.id).length === 0"
                                        class="px-3 py-2 text-sm text-gray-500 text-center">
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
                                <input v-model="test.amount" type="number" step="0.01"
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

                <!-- Second Row - Doctor Details -->
                <div class="grid grid-cols-3 gap-6 mb-6">
                    <div>
                        <div class="mb-2">
                            <span class="text-sm font-medium text-black">Referral Doctor</span>
                        </div>
                        <select id="referralDoctor" v-model="form.doctor_id"
                            class="block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none bg-white">
                            <option value="">Select Doctor</option>
                            <option v-for="data in doctors" :key="data.id" :value="data.id">{{ data.name }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.doctor_id" />
                    </div>

                    <div>
                        <div class="mb-2">
                            <span class="text-sm font-medium text-black">Doctor Name</span>
                        </div>
                        <input id="doctor_name" v-model="form.doctor_name" type="text"
                            class="block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none" />
                        <InputError class="mt-1" :message="form.errors.doctor_name" />
                    </div>

                    <div>
                        <div class="mb-2">
                            <span class="text-sm font-medium text-black">Payee</span>

                        </div>
                        <select id="payee_id" v-model="form.payee_id"
                            class="block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none bg-white">
                            <option value="">Select Payee</option>
                            <option v-for="data in raferrers" :key="data.id" :value="data.id">{{ data.name }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.payee_id" />
                    </div>
                </div>

                <!-- Third Row - Commission Details -->
                <div class="grid grid-cols-2 gap-6 mb-6">
                    <div>
                        <div class="mb-2">
                            <span class="text-sm font-medium text-black">Commission Percentage (%)</span>

                        </div>
                        <input id="commission_percentage" v-model="form.commission_percentage" type="number" step="0.01"
                            placeholder="Percentage"
                            class="block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none" />
                        <InputError class="mt-1" :message="form.errors.commission_percentage" />
                    </div>

                    <div>
                        <div class="mb-2">
                            <span class="text-sm font-medium text-black">Commission Amount (Tk.)</span>

                        </div>
                        <input id="commission_amount" v-model="form.commission_amount" type="number" step="0.01"
                            placeholder="Commission Amount"
                            class="block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none" />
                        <InputError class="mt-1" :message="form.errors.commission_amount" />
                    </div>
                </div>

                <!-- Bottom Section - Note and Summary -->
                <div class="grid grid-cols-2 gap-8 mb-6">
                    <!-- Note Section -->
                    <div>
                        <div class="mb-2">
                            <span class="text-sm font-medium text-black">Note</span>
                        </div>
                        <textarea id="note" v-model="form.note" rows="8"
                            class="block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none resize-none"></textarea>
                        <InputError class="mt-1" :message="form.errors.note" />
                    </div>

                    <!-- Summary Section -->
                    <div class="space-y-4">
                        <!-- Totals -->
                        <div class="space-y-3">
                            <!-- Subtotal -->
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-black">Subtotal (Tk.)</span>
                                <span class="text-sm font-medium">{{ (parseFloat(form.subtotal) || 0).toFixed(2)
                                    }}</span>
                            </div>

                            <!-- Discount Section with Percentage and Amount -->
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-black">Discount</span>
                                <div class="flex items-center space-x-2">
                                    <div class="flex items-center">
                                        <input v-model="form.discount_percentage" type="number" step="0.01"
                                            placeholder="0"
                                            class="w-16 px-2 py-1 text-xs border border-gray-300 rounded-l focus:border-blue-500 focus:outline-none text-center" />
                                        <span
                                            class="px-2 py-1 text-xs bg-gray-100 border border-l-0 border-gray-300 rounded-r">%</span>
                                    </div>
                                    <span class="text-sm text-gray-400">=</span>
                                    <span class="text-sm font-medium">{{ (parseFloat(form.discount_amount) ||
                                        0).toFixed(2) }} Tk.</span>
                                </div>
                            </div>

                            <!-- VAT Section with Percentage and Amount -->
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-black">VAT</span>
                                <div class="flex items-center space-x-2">
                                    <div class="flex items-center">
                                        <input v-model="form.vat_percentage" type="number" step="0.01" placeholder="0"
                                            class="w-16 px-2 py-1 text-xs border border-gray-300 rounded-l focus:border-blue-500 focus:outline-none text-center" />
                                        <span
                                            class="px-2 py-1 text-xs bg-gray-100 border border-l-0 border-gray-300 rounded-r">%</span>
                                    </div>
                                    <span class="text-sm text-gray-400">=</span>
                                    <span class="text-sm font-medium">{{ (parseFloat(form.vat_amount) || 0).toFixed(2)
                                        }} Tk.</span>
                                </div>
                            </div>

                            <!-- Tax Section with Percentage and Amount -->
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-black">Tax</span>
                                <div class="flex items-center space-x-2">
                                    <div class="flex items-center">
                                        <input v-model="form.tax_percentage" type="number" step="0.01" placeholder="0"
                                            class="w-16 px-2 py-1 text-xs border border-gray-300 rounded-l focus:border-blue-500 focus:outline-none text-center" />
                                        <span
                                            class="px-2 py-1 text-xs bg-gray-100 border border-l-0 border-gray-300 rounded-r">%</span>
                                    </div>
                                    <span class="text-sm text-gray-400">=</span>
                                    <span class="text-sm font-medium">{{ (parseFloat(form.tax_amount) || 0).toFixed(2)
                                        }} Tk.</span>
                                </div>
                            </div>

                            <!-- Extra VAT Section with Percentage and Amount -->
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-black">Extra VAT</span>
                                <div class="flex items-center space-x-2">
                                    <div class="flex items-center">
                                        <input v-model="form.extra_vat_percentage" type="number" step="0.01"
                                            placeholder="0"
                                            class="w-16 px-2 py-1 text-xs border border-gray-300 rounded-l focus:border-blue-500 focus:outline-none text-center" />
                                        <span
                                            class="px-2 py-1 text-xs bg-gray-100 border border-l-0 border-gray-300 rounded-r">%</span>
                                    </div>
                                    <span class="text-sm text-gray-400">=</span>
                                    <span class="text-sm font-medium">{{ (parseFloat(form.extra_vat_amount) ||
                                        0).toFixed(2) }} Tk.</span>
                                </div>
                            </div>

                            <!-- Extra Discount Section -->
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-black">Extra Discount</span>
                                <div class="flex items-center space-x-2">
                                    <input v-model="form.extra_discount" type="number" step="0.01" placeholder="0"
                                        class="w-20 px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none text-center" />
                                    <span class="text-sm text-gray-500">Tk.</span>
                                </div>
                            </div>

                            <!-- Net Amount -->
                            <div class="flex justify-between items-center border-t pt-3">
                                <span class="text-sm font-medium text-black">Net Amount (Tk.)</span>
                                <span class="text-lg font-bold text-green-600">{{ (parseFloat(form.net_amount) ||
                                    0).toFixed(2) }}</span>
                            </div>

                            <!-- Total Paid Amount (for edit mode) -->
                            <div v-if="props.id && previousPaidAmount > 0" class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Previous Paid Amount (Tk.)</span>
                                <span class="text-sm text-gray-600">{{ previousPaidAmount.toFixed(2) }}</span>
                            </div>

                        </div>

                        <!-- Payment Section -->
                        <div class="pt-4">
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-sm font-medium text-black">Payment Mode</span>
                                <div class="flex items-center">
                                    <span class="text-sm font-medium text-black">
                                        {{ props.id ? 'Payment Amount (Tk.)' : 'Amount (Tk.)' }}
                                    </span>
                                    <span class="text-red-500 ml-1" style="margin-right: 140px;">*</span>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <select v-model="form.payment_mode"
                                    class="px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none bg-white">
                                    <option value="Cash">Cash</option>
                                    <option value="Card">Card</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                </select>
                                <input v-model="form.payment_amount" type="number" step="0.01"
                                    placeholder="Enter payment amount"
                                    class="px-3 py-2 text-sm text-end border border-gray-300 rounded focus:border-blue-500 focus:outline-none" />
                            </div>
                            <InputError class="mt-1" :message="form.errors.payment_amount" />
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex items-center justify-end">
                    <PrimaryButton type="submit" class="px-6 py-2" :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing">
                        {{ ((props.id ?? false) ? 'Update' : 'Save') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </BackendLayout>
</template>

<style scoped>
.relative {
    position: relative;
}

.absolute {
    z-index: 50;
}

.transition-all {
    transition: all 0.15s ease-in-out;
}

input:focus {
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.hover\:bg-blue-50:hover {
    background-color: rgb(239 246 255);
}

.hover\:text-blue-700:hover {
    color: rgb(29 78 216);
}

.highlighted {
    background-color: #ebf8ff;
    color: #1a365d;
}
</style>