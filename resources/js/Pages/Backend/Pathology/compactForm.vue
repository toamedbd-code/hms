<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AlertMessage from '@/Components/AlertMessage.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';

const props = defineProps(['pathology', 'id', 'patients', 'doctors', 'billnumber', 'pathologyTests']);

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

const form = useForm({
    case_id: props.pathology?.case_id ?? '',
    patient_id: props.pathology?.patient_id ?? '',
    doctor_id: props.pathology?.doctor_id ?? '',
    apply_tpa: Boolean(props.pathology?.apply_tpa) || false,
    bill_no: props.pathology?.bill_no ?? '',
    date: props.pathology?.date ?? new Date().toISOString().split('T')[0],
    tests: [],
    note: props.pathology?.note ?? '',
    payee: props.pathology?.payee ?? '',
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
    payment_amount: props.pathology?.payment_amount ?? 0,
    doctor_name: props.pathology?.doctor_name ?? '',

    _method: props.pathology?.id ? 'put' : 'post',
});

// Handle test selection change
const onTestChange = (testRowIndex, selectedTestId) => {
    const selectedTest = props.pathologyTests.find(test => test.id == selectedTestId);
    if (selectedTest) {
        testRows.value[testRowIndex].testId = selectedTestId;
        testRows.value[testRowIndex].testName = selectedTest.test_name;
        testRows.value[testRowIndex].reportDays = selectedTest.report_days || '';
        testRows.value[testRowIndex].tax = selectedTest.tax || '';
        testRows.value[testRowIndex].amount = selectedTest.amount || selectedTest.standard_charge || '';
        
        // Auto-calculate report date based on report days
        if (selectedTest.report_days) {
            const today = new Date();
            const reportDate = new Date(today.getTime() + (selectedTest.report_days * 24 * 60 * 60 * 1000));
            testRows.value[testRowIndex].reportDate = reportDate.toISOString().split('T')[0];
        }
    }
};

const generateBillNumber = async () => {
    try {
        const lastBillNumber = props.billnumber ? props.billnumber : ''; 
        let newNumber = 1; 

        if (lastBillNumber && lastBillNumber.startsWith('PATB')) {
            const numberPart = parseInt(lastBillNumber.replace('PATB', ''), 10);
            if (!isNaN(numberPart)) {
                newNumber = numberPart + 1;
            }
        }

        return `PATB${newNumber}`;
    } catch (error) {
        console.error('Error generating bill number:', error);
        return 'PATB1';
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
    // Generate bill number and case ID if creating new record
    if (!props.pathology?.id) {
        form.case_id = generateCaseId();

        setTimeout(async () => {
            form.bill_no = await generateBillNumber();
        }, 1000);
    }

    if (props.pathology?.tests && props.pathology.tests.length > 0) {
        testRows.value = props.pathology.tests.map(test => ({
            id: test.id || Date.now() + Math.random(),
            testId: test.testId || '',
            testName: test.test_name || '',
            reportDays: test.report_days || '',
            reportDate: test.report_date || '',
            tax: test.tax || '',
            amount: test.amount || ''
        }));
    }
    updateCalculations();
});

const updateCalculations = () => {
    // Calculate subtotal from test rows
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

// Watch for changes in percentage fields and recalculate
watch(() => form.discount_percentage, updateCalculations);
watch(() => form.vat_percentage, updateCalculations);
watch(() => form.tax_percentage, updateCalculations);
watch(() => form.extra_vat_percentage, updateCalculations);
watch(() => form.extra_discount, updateCalculations);

// Watch for changes in test rows and update calculations
watch(testRows, () => {
    updateFormTests();
    updateCalculations();
}, { deep: true });

// Update form tests array
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
    updateFormTests();

    const routeName = props.id ? route('backend.pathology.update', props.id) : route('backend.pathology.store');
    form.transform(data => ({
        ...data,
        remember: '',
        isDirty: false,
    })).post(routeName, {
        onSuccess: (response) => {
            if (!props.id) {
                form.reset();
                testRows.value = [createTestRow()];
            }
            displayResponse(response);
        },
        onError: (errorObject) => {
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
        console.log('Test row removed. Remaining rows:', testRows.value.length);
    }
};

// Auto-calculate commission amount based on percentage
watch(() => form.commission_percentage, (newVal) => {
    if (newVal && form.net_amount) {
        const percentage = parseFloat(newVal) || 0;
        const net_amount = parseFloat(form.net_amount) || 0;
        form.commission_amount = parseFloat((net_amount * percentage / 100).toFixed(2));
    }
});

watch(() => form.net_amount, (newVal) => {
    if (newVal) {
        form.payment_amount = parseFloat(newVal).toFixed(2) || 0;
    } else {
        form.payment_amount = '';
    }
});
</script>

<template>
    <BackendLayout>
        <div class="w-full mt-2 bg-white border border-gray-300 rounded-md shadow-lg">

            <!-- Header Section -->
            <div class="flex items-center justify-between w-full px-3 py-1.5 bg-gray-100 border-b border-gray-300">
                <div class="flex items-center space-x-3">
                    <div class="relative min-w-[260px]">
                        <select id="patient_id" v-model="form.patient_id"
                            class="block w-full p-1 text-xs rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                            <option value="">Select Patient</option>
                            <option v-for="data in patients" :key="data.id" :value="data.id">{{ data.name }}</option>
                        </select>
                        <InputError class="mt-0.5" :message="form.errors.patient_id" />
                    </div>
                    <button class="px-2 py-1 text-xs text-white bg-blue-600 rounded hover:bg-blue-700">
                        + New Patient
                    </button>
                    <div class="flex items-center">
                        <input v-model="form.prescription_no" type="text" placeholder="Prescription No"
                            class="px-2 py-1 text-xs border border-gray-300 rounded-l focus:outline-none focus:border-blue-500" />
                        <button class="px-2 py-1 text-xs bg-gray-200 border border-l-0 border-gray-300 rounded-r hover:bg-gray-300">
                            🔍
                        </button>
                    </div>
                    <div class="flex items-center h-6">
                        <input v-model="form.apply_tpa" type="checkbox" id="apply_tpa"
                            class="w-3 h-3 text-blue-600 border-gray-300 rounded focus:ring-blue-500" />
                        <label for="apply_tpa" class="ml-1 text-xs font-medium text-black">Apply TPA</label>
                    </div>
                </div>
            </div>

            <!-- Bill Info Header -->
            <div class="flex items-center justify-between px-3 py-1.5 bg-gray-50 border-b border-gray-200">
                <div class="flex space-x-6">
                    <div class="flex items-center space-x-2">
                        <span class="text-xs font-semibold">Bill No</span>
                        <div class="flex items-center">
                            <svg v-if="billStatus.showSpinner" class="animate-spin -ml-1 mr-1 h-3 w-3 text-amber-600"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            <span :class="billStatus.class" class="text-xs">{{ billStatus.text }}</span>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2">
                        <span class="text-xs font-semibold">Case ID</span>
                        <div class="flex items-center">
                            <svg v-if="caseStatus.showSpinner" class="animate-spin -ml-1 mr-1 h-3 w-3 text-amber-600"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            <span :class="caseStatus.class" class="text-xs">{{ caseStatus.text }}</span>
                        </div>
                    </div>
                </div>
                <span class="text-xs text-gray-600">Date {{ new Date().toLocaleDateString() }}</span>
            </div>

            <form @submit.prevent="submit" class="p-3">
                <AlertMessage />

                <!-- Dynamic Test Rows -->
                <div class="mb-3">
                    <div v-for="(test, index) in testRows" :key="test.id" class="mb-2">
                        <div class="grid grid-cols-5 gap-3">
                            <div>
                                <div class="mb-1">
                                    <span class="text-xs font-medium text-black">Test Name</span>
                                    <span class="text-red-500 ml-0.5 text-xs">*</span>
                                </div>
                                <select v-model="test.testId" @change="onTestChange(index, test.testId)"
                                    class="block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none bg-white">
                                    <option value="">Select Test</option>
                                    <option v-for="pathTest in pathologyTests" :key="pathTest.id" :value="pathTest.id">
                                        {{ pathTest.test_name }}
                                    </option>
                                </select>
                                <InputError class="mt-0.5 text-xs" :message="form.errors[`tests.${index}.testId`]" />
                            </div>

                            <div>
                                <div class="mb-1">
                                    <span class="text-xs font-medium text-black">Report Days</span>
                                </div>
                                <input v-model="test.reportDays" type="number"
                                    class="block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors[`tests.${index}.reportDays`]" />
                            </div>

                            <div>
                                <div class="mb-1">
                                    <span class="text-xs font-medium text-black">Report Date</span>
                                    <span class="text-red-500 ml-0.5 text-xs">*</span>
                                </div>
                                <input v-model="test.reportDate" type="date"
                                    class="block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors[`tests.${index}.reportDate`]" />
                            </div>

                            <div>
                                <div class="mb-1">
                                    <span class="text-xs font-medium text-black">Tax</span>
                                </div>
                                <div class="relative">
                                    <input v-model="test.tax" type="number" step="0.01"
                                        class="block w-full px-2 py-1 pr-5 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none" />
                                    <span class="absolute right-2 top-1 text-xs text-gray-500">%</span>
                                </div>
                                <InputError class="mt-0.5 text-xs" :message="form.errors[`tests.${index}.tax`]" />
                            </div>

                            <div>
                                <div class="mb-1 flex justify-between items-center">
                                    <span class="text-xs font-medium text-black">Amount (Tk.)</span>
                                    <button type="button" @click="removeTest(test.id)" :disabled="testRows.length === 1"
                                        :class="testRows.length === 1 ? 'text-gray-300 cursor-not-allowed' : 'text-red-500 hover:text-red-700'"
                                        class="text-sm font-bold">×</button>
                                </div>
                                <input v-model="test.amount" type="number" step="0.01"
                                    class="block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors[`tests.${index}.amount`]" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add Button -->
                <div class="mb-3">
                    <button type="button" @click="addTest"
                        class="inline-flex items-center px-3 py-1 text-xs font-medium text-white bg-green-600 rounded hover:bg-green-700">
                        <span class="mr-0.5">+</span> Add Test
                    </button>
                </div>

                <!-- Doctor Details and Bottom Section in 2 columns -->
                <div class="grid grid-cols-2 gap-4 mb-3">
                    <!-- Left Column - Form Fields -->
                    <div class="space-y-3">
                        <!-- Doctor Details Row -->
                        <div class="grid grid-cols-1 gap-3">
                            <div>
                                <div class="mb-1">
                                    <span class="text-xs font-medium text-black">Referral Doctor</span>
                                </div>
                                <select id="referralDoctor" v-model="form.doctor_id"
                                    class="block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none bg-white">
                                    <option value="">Select Doctor</option>
                                    <option v-for="data in doctors" :key="data.id" :value="data.id">{{ data.name }}</option>
                                </select>
                                <InputError class="mt-0.5 text-xs" :message="form.errors.doctor_id" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <div class="mb-1">
                                    <span class="text-xs font-medium text-black">Doctor Name</span>
                                </div>
                                <input id="doctor_name" v-model="form.doctor_name" type="text"
                                    class="block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.doctor_name" />
                            </div>

                            <div>
                                <div class="mb-1">
                                    <span class="text-xs font-medium text-black">Payee</span>
                                    <span class="text-red-500 ml-0.5 text-xs">*</span>
                                </div>
                                <select id="payee" v-model="form.payee"
                                    class="block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none bg-white">
                                    <option value="">Select Payee</option>
                                    <option value="patient">Patient</option>
                                    <option value="insurance">Insurance</option>
                                    <option value="company">Company</option>
                                </select>
                                <InputError class="mt-0.5 text-xs" :message="form.errors.payee" />
                            </div>
                        </div>

                        <!-- Commission Details -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <div class="mb-1">
                                    <span class="text-xs font-medium text-black">Commission %</span>
                                    <span class="text-red-500 ml-0.5 text-xs">*</span>
                                </div>
                                <input id="commission_percentage" v-model="form.commission_percentage" type="number" step="0.01"
                                    placeholder="Percentage"
                                    class="block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.commission_percentage" />
                            </div>

                            <div>
                                <div class="mb-1">
                                    <span class="text-xs font-medium text-black">Commission Amount (Tk.)</span>
                                    <span class="text-red-500 ml-0.5 text-xs">*</span>
                                </div>
                                <input id="commission_amount" v-model="form.commission_amount" type="number" step="0.01"
                                    placeholder="Commission Amount"
                                    class="block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.commission_amount" />
                            </div>
                        </div>

                        <!-- Note Section -->
                        <div>
                            <div class="mb-1">
                                <span class="text-xs font-medium text-black">Note</span>
                            </div>
                            <textarea id="note" v-model="form.note" rows="4"
                                class="block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none resize-none"></textarea>
                            <InputError class="mt-0.5 text-xs" :message="form.errors.note" />
                        </div>
                    </div>

                    <!-- Right Column - Summary Section -->
                    <div class="space-y-2">
                        <!-- Totals -->
                        <div class="space-y-2">
                            <!-- Subtotal -->
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-black">Subtotal (Tk.)</span>
                                <span class="text-xs font-medium">{{ (parseFloat(form.subtotal) || 0).toFixed(2) }}</span>
                            </div>

                            <!-- Discount Section -->
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-black">Discount</span>
                                <div class="flex items-center space-x-1">
                                    <div class="flex items-center">
                                        <input v-model="form.discount_percentage" type="number" step="0.01"
                                            placeholder="0"
                                            class="w-12 px-1 py-0.5 text-xs border border-gray-300 rounded-l focus:border-blue-500 focus:outline-none text-center" />
                                        <span
                                            class="px-1 py-0.5 text-xs bg-gray-100 border border-l-0 border-gray-300 rounded-r">%</span>
                                    </div>
                                    <span class="text-xs text-gray-400">=</span>
                                    <span class="text-xs font-medium">{{ (parseFloat(form.discount_amount) || 0).toFixed(2) }}</span>
                                </div>
                            </div>

                            <!-- VAT Section -->
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-black">VAT</span>
                                <div class="flex items-center space-x-1">
                                    <div class="flex items-center">
                                        <input v-model="form.vat_percentage" type="number" step="0.01" placeholder="0"
                                            class="w-12 px-1 py-0.5 text-xs border border-gray-300 rounded-l focus:border-blue-500 focus:outline-none text-center" />
                                        <span
                                            class="px-1 py-0.5 text-xs bg-gray-100 border border-l-0 border-gray-300 rounded-r">%</span>
                                    </div>
                                    <span class="text-xs text-gray-400">=</span>
                                    <span class="text-xs font-medium">{{ (parseFloat(form.vat_amount) || 0).toFixed(2) }}</span>
                                </div>
                            </div>

                            <!-- Tax Section -->
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-black">Tax</span>
                                <div class="flex items-center space-x-1">
                                    <div class="flex items-center">
                                        <input v-model="form.tax_percentage" type="number" step="0.01" placeholder="0"
                                            class="w-12 px-1 py-0.5 text-xs border border-gray-300 rounded-l focus:border-blue-500 focus:outline-none text-center" />
                                        <span
                                            class="px-1 py-0.5 text-xs bg-gray-100 border border-l-0 border-gray-300 rounded-r">%</span>
                                    </div>
                                    <span class="text-xs text-gray-400">=</span>
                                    <span class="text-xs font-medium">{{ (parseFloat(form.tax_amount) || 0).toFixed(2) }}</span>
                                </div>
                            </div>

                            <!-- Extra VAT Section -->
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-black">Extra VAT</span>
                                <div class="flex items-center space-x-1">
                                    <div class="flex items-center">
                                        <input v-model="form.extra_vat_percentage" type="number" step="0.01"
                                            placeholder="0"
                                            class="w-12 px-1 py-0.5 text-xs border border-gray-300 rounded-l focus:border-blue-500 focus:outline-none text-center" />
                                        <span
                                            class="px-1 py-0.5 text-xs bg-gray-100 border border-l-0 border-gray-300 rounded-r">%</span>
                                    </div>
                                    <span class="text-xs text-gray-400">=</span>
                                    <span class="text-sm font-medium">{{ (parseFloat(form.extra_vat_amount) || 0).toFixed(2) }} Tk.</span>
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
                                <span class="text-lg font-bold text-green-600">{{ (parseFloat(form.net_amount) || 0).toFixed(2) }}</span>
                            </div>
                        </div>

                        <!-- Payment Section -->
                        <div class="pt-4">
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-sm font-medium text-black">Payment Mode</span>
                                <div class="flex items-center">
                                    <span class="text-sm font-medium text-black">Amount (Tk.)</span>
                                    <span class="text-red-500 ml-1" style="margin-right: 190px;">*</span>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <select v-model="form.payment_mode"
                                    class="px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none bg-white">
                                    <option value="Cash">Cash</option>
                                    <option value="Card">Card</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                </select>
                                <input v-model="form.payment_amount" type="text" step="0.01" 
                                    class="px-3 py-2 text-sm text-end border border-gray-300 rounded focus:border-blue-500 focus:outline-none" />
                            </div>
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

.text-blue-600 {
    color: #2563eb;
}

.span {
    font-weight: bold;
    color: black;
}
</style>