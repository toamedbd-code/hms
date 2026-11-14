<script setup>
import { ref, onMounted, computed, watch, nextTick, onBeforeUnmount } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AlertMessage from '@/Components/AlertMessage.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';
import PatientModal from '@/Components/PatientModal.vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

const props = defineProps([
    'pharmacybill',
    'id',
    'patients',
    'doctors',
    'billnumber',
    'pharmacyNo',
    'caseId',
    'categories',
    'prescriptionPrefill',
    'ipdPatient',
]);

const createMedicineRow = () => ({
    id: Date.now() + Math.random(),
    medicineCategory: '',
    medicineName: null,
    batchNo: '',
    expiryDate: '',
    quantity: 1,
    availableQty: 0,
    salePrice: 0,
    tax: 0,
    amount: 0,
});

const ensureNumber = (value, defaultValue = 0) => {
    const num = parseFloat(value);
    return isNaN(num) ? defaultValue : num;
};

const initializeMedicineRows = () => {
    if (props.pharmacybill?.mapped_products && Array.isArray(props.pharmacybill.mapped_products)) {
        return props.pharmacybill.mapped_products.map(item => ({
            id: Date.now() + Math.random(),
            medicineCategory: item.medicineCategory || '',
            medicineName: item.medicine || null,
            batchNo: item.batchNo || '',
            expiryDate: item.expiryDate || '',
            quantity: ensureNumber(item.quantity, 1),
            availableQty: ensureNumber(item.availableQty, 0),
            salePrice: ensureNumber(item.rate, 0),
            tax: ensureNumber(item.tax, 0),
            amount: ensureNumber(item.amount, 0),
        }));
    }
    return [createMedicineRow()];
};

const medicineRows = ref(initializeMedicineRows());
const medicineSearchResults = ref([]);
const medicineSearchLoading = ref({});
const medicineSearchTimers = new Map();
const medicineSearchControllers = new Map();
const medicineSearchQueries = new Map();

const selectedPatient = ref(props.pharmacybill?.patient_id ?
    props.patients.find(p => p.id === props.pharmacybill.patient_id) : null
);

const selectedDoctor = ref(props.pharmacybill?.doctor_id ?
    props.doctors.find(d => d.id === props.pharmacybill.doctor_id) : null
);

const form = useForm({
    pharmacy_no: props.pharmacybill?.pharmacy_no ?? props.pharmacyNo,
    bill_no: props.pharmacybill?.bill_no ?? props.billnumber,
    case_id: props.pharmacybill?.case_id ?? props.caseId,
    patient_id: props.pharmacybill?.patient_id ?? '',
    ipd_patient_id: props.pharmacybill?.ipd_patient_id ?? props.ipdPatient?.id ?? '',
    ipd_prescription_id: props.pharmacybill?.ipd_prescription_id ?? props.prescriptionPrefill?.id ?? '',
    doctor_id: props.pharmacybill?.doctor_id ?? '',
    date: props.pharmacybill?.date ?? new Date().toISOString().slice(0, 10),
    products: [],
    subtotal: ensureNumber(props.pharmacybill?.subtotal, 0),
    discount_percentage: ensureNumber(props.pharmacybill?.discount_percentage, 0),
    discount_amount: ensureNumber(props.pharmacybill?.discount_amount, 0),
    vat_percentage: ensureNumber(props.pharmacybill?.vat_percentage, 0),
    vat_amount: ensureNumber(props.pharmacybill?.vat_amount, 0),
    extra_discount: ensureNumber(props.pharmacybill?.extra_discount, 0),
    net_amount: ensureNumber(props.pharmacybill?.net_amount, 0),
    payment_mode: props.pharmacybill?.payment_mode ?? 'Cash',
    payment_amount: ensureNumber(props.pharmacybill?.payment_amount, 0),
    return_amount: ensureNumber(props.pharmacybill?.return_amount, 0),
    note: props.pharmacybill?.note ?? '',
    _method: props.id ? 'put' : 'post',
});

const overPaymentAmount = computed(() => {
    const paymentAmount = ensureNumber(form.payment_amount, 0);
    const netAmount = ensureNumber(form.net_amount, 0);
    return Math.max(paymentAmount - netAmount, 0);
});

const shouldShowReturnAmount = computed(() => {
    return form.payment_mode === 'Cash' && overPaymentAmount.value > 0;
});

const selectedProductCount = computed(() => medicineRows.value.filter((row) => row.medicineName).length);
const dueAmount = computed(() => Math.max(ensureNumber(form.net_amount, 0) - ensureNumber(form.payment_amount, 0) + ensureNumber(form.return_amount, 0), 0));

watch(
    () => [form.payment_mode, form.payment_amount, form.net_amount],
    () => {
        if (!shouldShowReturnAmount.value) {
            form.return_amount = 0;
            return;
        }

        const currentReturn = ensureNumber(form.return_amount, 0);
        const maxReturn = overPaymentAmount.value;
        if (currentReturn <= 0 || currentReturn > maxReturn) {
            form.return_amount = maxReturn;
        }
    }
);

const getFilteredMedicines = (categoryName) => {
    const results = medicineSearchResults.value.filter((medicine) =>
        !categoryName || medicine.category?.name === categoryName
    );
    const selectedMedicines = medicineRows.value
        .map((row) => row.medicineName)
        .filter((medicine) => medicine && (!categoryName || medicine.category?.name === categoryName));

    return [...new Map([...selectedMedicines, ...results].map((medicine) => [medicine.id, medicine])).values()];
};

const searchMedicines = (query, row, rowIndex) => {
    const search = String(query || '').trim();
    medicineSearchQueries.set(rowIndex, search);
    clearTimeout(medicineSearchTimers.get(rowIndex));
    medicineSearchControllers.get(rowIndex)?.abort();

    if (search.length < 2) {
        medicineSearchResults.value = medicineSearchResults.value.filter((medicine) =>
            medicine.category?.name !== row.medicineCategory
        );
        medicineSearchLoading.value = { ...medicineSearchLoading.value, [rowIndex]: false };
        return;
    }

    medicineSearchLoading.value = { ...medicineSearchLoading.value, [rowIndex]: true };
    medicineSearchTimers.set(rowIndex, setTimeout(async () => {
        const controller = new AbortController();
        medicineSearchControllers.set(rowIndex, controller);

        try {
            const response = await axios.get(route('backend.pharmacybill.medicines.search'), {
                params: { search, category: row.medicineCategory || undefined },
                signal: controller.signal,
            });
            if (medicineSearchQueries.get(rowIndex) !== search) return;
            if (!Array.isArray(response.data)) {
                throw new Error('Medicine search returned an invalid response.');
            }

            const otherResults = medicineSearchResults.value.filter((medicine) =>
                medicine.category?.name !== row.medicineCategory
            );
            medicineSearchResults.value = [...otherResults, ...response.data];
        } catch (error) {
            if (error.name !== 'CanceledError' && error.name !== 'AbortError') {
                medicineSearchResults.value = medicineSearchResults.value.filter((medicine) =>
                    medicine.category?.name !== row.medicineCategory
                );
                displayWarning({ message: 'Could not load medicines. Please try searching again.' });
            }
        } finally {
            if (medicineSearchQueries.get(rowIndex) === search) {
                medicineSearchLoading.value = { ...medicineSearchLoading.value, [rowIndex]: false };
            }
        }
    }, 180));
};

onBeforeUnmount(() => {
    medicineSearchTimers.forEach((timer) => clearTimeout(timer));
    medicineSearchControllers.forEach((controller) => controller.abort());
});

const addMedicineRow = () => {
    medicineRows.value.push(createMedicineRow());
    nextTick(() => {
        const newRowIndex = medicineRows.value.length - 1;
        focusOnMedicineName(newRowIndex);
    });
};

const removeMedicineRow = (index) => {
    if (medicineRows.value.length > 1) {
        medicineRows.value.splice(index, 1);
        calculateTotal();
    }
};

const calculateTotal = () => {
    let subtotal = 0;
    
    if (!Array.isArray(medicineRows.value)) {
        console.error('medicineRows.value is not an array:', medicineRows.value);
        return;
    }
    
    medicineRows.value.forEach(row => {
        const quantity = ensureNumber(row.quantity, 0);
        const salePrice = ensureNumber(row.salePrice, 0);
        const tax = ensureNumber(row.tax, 0);
        
        if (row.medicineName && quantity > 0 && salePrice > 0) {
            row.amount = (quantity * salePrice) + ((quantity * salePrice * tax) / 100);
            subtotal += row.amount;
        } else {
            row.amount = 0;
        }
    });

    form.subtotal = subtotal;
    const discountPercentage = ensureNumber(form.discount_percentage, 0);
    const vatPercentage = ensureNumber(form.vat_percentage, 0);
    const extraDiscount = ensureNumber(form.extra_discount, 0);
    
    form.discount_amount = (form.subtotal * discountPercentage) / 100;
    form.vat_amount = (form.subtotal * vatPercentage) / 100;
    form.net_amount = (form.subtotal - form.discount_amount + form.vat_amount - extraDiscount);
};

const handleMedicineSelection = (medicine, row, rowIndex) => {
    if (medicine) {
        row.medicineName = medicine;
        row.medicineCategory = medicine.category?.name || '';
        row.salePrice = ensureNumber(medicine.sale_price, 0);
        row.availableQty = ensureNumber(medicine.medicine_quantity, 0);
        row.batchNo = medicine.batch_no || '';
        row.expiryDate = medicine.expiry_date || '';
        calculateTotal();

        nextTick(() => {
            focusOnQuantity(rowIndex);
        });
    }
};

const handleMedicineEnter = (rowIndex) => {
    nextTick(() => {
        focusOnQuantity(rowIndex);
    });
};

const handleCategorySelection = (category, row, rowIndex) => {
    row.medicineCategory = category;
    row.medicineName = null;
    row.batchNo = '';
    row.expiryDate = '';
    row.salePrice = 0;
    row.availableQty = 0;
    row.tax = 0;
    calculateTotal();

    nextTick(() => {
        focusOnMedicineName(rowIndex);
    });
};

const validateQuantity = (row) => {
    const quantity = ensureNumber(row.quantity, 0);
    const availableQty = ensureNumber(row.availableQty, 0);
    
    if (quantity > availableQty) {
        row.quantity = availableQty;
        displayWarning({ message: 'Quantity cannot exceed available stock!' });
    }
    calculateTotal();
};

const handleQuantityEnter = (rowIndex) => {
    addMedicineRow();
};

const focusOnMedicineName = (rowIndex) => {
    nextTick(() => {
        const medicineMultiselect = (typeof document !== 'undefined') ? document.querySelector(`#medicine-multiselect-${rowIndex} input`) : null;
        if (medicineMultiselect) {
            medicineMultiselect.focus();
            medicineMultiselect.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const row = medicineRows.value[rowIndex];
                    if (row.medicineName) {
                        handleMedicineEnter(rowIndex);
                    }
                }
            }, { once: true }); 
        }
    });
};

const focusOnQuantity = (rowIndex) => {
    nextTick(() => {
        const quantityInput = (typeof document !== 'undefined') ? document.querySelector(`#quantity-${rowIndex}`) : null;
        if (quantityInput) {
            quantityInput.focus();
            quantityInput.select();
        }
    });
};

const handlePatientSelection = (patient) => {
    selectedPatient.value = patient;
    form.patient_id = patient ? patient.id : '';
};

const handleDoctorSelection = (doctor) => {
    selectedDoctor.value = doctor;
    form.doctor_id = doctor ? doctor.id : '';
};

watch(
    () => medicineRows.value,
    (newValue) => {
        if (Array.isArray(newValue)) {
            form.products = newValue.map(row => ({
                productId: row.medicineName?.id || '',
                productName: row.medicineName?.medicine_name || '',
                medicineCategory: row.medicineCategory,
                batchNo: row.batchNo,
                expiryDate: row.expiryDate,
                quantity: ensureNumber(row.quantity, 0),
                availableQty: ensureNumber(row.availableQty, 0),
                rate: ensureNumber(row.salePrice, 0),
                tax: ensureNumber(row.tax, 0),
                amount: ensureNumber(row.amount, 0),
            }));
            calculateTotal();
        }
    },
    { deep: true }
);

watch(
    () => [form.discount_percentage, form.vat_percentage, form.extra_discount],
    () => {
        calculateTotal();
    }
);

onMounted(() => {
    if (!Array.isArray(medicineRows.value)) {
        medicineRows.value = [createMedicineRow()];
    }

    const prefill = props.prescriptionPrefill;
    if (prefill && !props.id) {
        if (props.ipdPatient) {
            form.case_id = props.ipdPatient.billing?.case_number
                || `IPD-${String(props.ipdPatient.id).padStart(6, '0')}`;
        }
        if (prefill.patient_id) {
            const patient = (props.patients ?? []).find((item) => Number(item.id) === Number(prefill.patient_id));
            if (patient) {
                selectedPatient.value = patient;
                form.patient_id = patient.id;
            }
        }
        if (prefill.doctor_id) {
            const doctor = (props.doctors ?? []).find((item) => Number(item.id) === Number(prefill.doctor_id));
            if (doctor) {
                selectedDoctor.value = doctor;
                form.doctor_id = doctor.id;
            }
        }
        if (Array.isArray(prefill.rows) && prefill.rows.length) {
            medicineRows.value = prefill.rows.map((row) => {
                const base = createMedicineRow();
                return {
                    ...base,
                    medicineCategory: row.medicineCategory || '',
                    medicineName: row.medicine || null,
                    quantity: ensureNumber(row.quantity, 1),
                    availableQty: ensureNumber(row.availableQty, 0),
                    salePrice: ensureNumber(row.salePrice, 0),
                    amount: ensureNumber(row.salePrice, 0) * ensureNumber(row.quantity, 1),
                };
            });
        }
    }
    
    calculateTotal();
    nextTick(() => {
        focusOnMedicineName(0);
    });
});

const submit = (shouldPrint = false) => {
    const selectedRows = medicineRows.value.filter((row) => row.medicineName);
    if (selectedRows.length === 0) {
        displayWarning({ message: 'Add at least one medicine before saving the bill.' });
        return;
    }

    const invalidRow = selectedRows.find((row) => (
        ensureNumber(row.quantity, 0) < 1
        || ensureNumber(row.quantity, 0) > ensureNumber(row.availableQty, 0)
    ));
    if (invalidRow) {
        displayWarning({ message: 'Check the medicine quantity against the available stock.' });
        return;
    }

    let printTab = null;
    let printToken = null;
    if (shouldPrint) {
        try {
            // generate a short random token
            printToken = Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
            // open preview waiting page immediately with token so user sees instant feedback
            const previewUrl = route('backend.download.invoice.preview', { print_token: printToken, module: 'pharmacy' });
            printTab = window.open(previewUrl, '_blank');
            try { if (printTab) printTab.opener = null; } catch (e) { /* ignore */ }
        } catch (e) {
            printTab = null;
            printToken = null;
        }
    }

    form.products = medicineRows.value.map(row => ({
        productId: row.medicineName?.id || '',
        productName: row.medicineName?.medicine_name || '',
        medicineCategory: row.medicineCategory,
        batchNo: row.batchNo,
        expiryDate: row.expiryDate,
        quantity: ensureNumber(row.quantity, 0),
        availableQty: ensureNumber(row.availableQty, 0),
        rate: ensureNumber(row.salePrice, 0),
        tax: ensureNumber(row.tax, 0),
        amount: ensureNumber(row.amount, 0),
    }));

    const routeName = props.id ?
        route('backend.pharmacybill.update', props.id) :
        route('backend.pharmacybill.store');

    // attach print_token so server can map token -> billing id for fast preview
    if (printToken) {
        // attach extra param; Laravel will accept unexpected input
        form.print_token = printToken;
    }

    form.post(routeName, {
        onSuccess: (page) => {
            const billId = page?.props?.flash?.billId;
            const pharmacyBillId = page?.props?.flash?.pharmacyBillId;

            if (shouldPrint) {
                const printUrl = billId && pharmacyBillId
                    ? route('backend.download.invoice', {
                        id: billId,
                        module: 'pharmacy',
                        pharmacy_bill_id: pharmacyBillId,
                        fast_open: 1,
                    })
                    : null;

                if (printUrl) {
                    if (printTab) {
                        try { printTab.location.href = printUrl; } catch (e) { /* ignore */ }
                    } else {
                        try { window.open(printUrl, '_blank'); } catch (e) { window.open(printUrl, '_blank'); }
                    }
                } else {
                    if (printTab) {
                        printTab.close();
                    }
                    displayWarning({ message: 'Bill saved, but invoice ID was not returned. Please open invoice from Pharmacy Bill List.' });
                }
                }

            if (!props.id) {
                form.reset();
                medicineRows.value = [createMedicineRow()];
                selectedPatient.value = null;
                selectedDoctor.value = null;
                calculateTotal();
                window.location.reload();
            }
            displayResponse(page);
        },
        onError: (errorObject) => {
            if (printTab) {
                printTab.close();
            }
            displayWarning(errorObject);
        },
    });
};

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

const goToPharmacyBillList = () => {
    router.get(route('backend.pharmacybill.index'));
};

const goToProductReturn = () => {
    const payload = {
        return_type: 'customer',
        patient_id: selectedPatient.value?.id || form.patient_id || undefined,
        case_id: form.case_id || undefined,
        source_bill_no: form.bill_no || undefined,
        source_module: 'pharmacy_bill',
    };

    router.get(route('backend.productreturn.index'), payload);
};
</script>

<template>
    <BackendLayout>
        <PatientModal :isOpen="isPatientModalOpen" @close="closePatientModal"
            @patientCreated="handlePatientCreated" />

        <div class="w-full rounded-md bg-[#dff7ef] p-2 dark:bg-slate-950">
            <div class="overflow-hidden rounded-lg border border-teal-200 bg-[#f1fcf7] shadow-lg dark:border-slate-700 dark:bg-slate-900">
                <div class="flex flex-col gap-3 bg-[#063b56] px-4 py-3 text-white lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 flex-col gap-2 sm:flex-row sm:items-center">
                        <div class="min-w-0 flex-1 sm:min-w-[280px]">
                            <Multiselect v-model="selectedPatient" :options="patients" track-by="id" label="name"
                                placeholder="Search and select a patient" :show-labels="false"
                                class="pharmacy-patient-select text-sm"
                                @select="handlePatientSelection" />
                            <InputError class="mt-1" :message="form.errors.patient_id" />
                        </div>
                        <button type="button" @click="openPatientModal"
                            class="inline-flex shrink-0 items-center justify-center rounded-md bg-teal-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-white/70">
                            + New Patient
                        </button>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="goToProductReturn"
                            class="inline-flex items-center justify-center gap-2 rounded-md bg-emerald-500 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-300">
                            <span aria-hidden="true">↩</span> Product Return
                        </button>
                        <button type="button" @click="goToPharmacyBillList"
                            class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-300">
                            <span aria-hidden="true">☷</span> Pharmacy Bill List
                        </button>
                    </div>
                </div>

            <form @submit.prevent="submit" class="p-4">
                <AlertMessage />

                <div v-if="props.ipdPatient" class="mb-4 rounded-md border border-teal-300 bg-teal-50 px-3 py-2 text-sm text-teal-900">
                    IPD dispensing for {{ props.ipdPatient.patient?.name || props.ipdPatient.patient?.first_name || 'Patient' }}
                    — Patient ID: {{ props.ipdPatient.patient_id }}, IPD ID: {{ props.ipdPatient.id }}.
                    Confirming this bill will deduct stock and add the charge to the IPD running bill.
                </div>
                <div v-if="props.prescriptionPrefill?.unmatched?.length" class="mb-4 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    These prescribed names were not matched to active inventory and must be selected manually:
                    {{ props.prescriptionPrefill.unmatched.join(', ') }}.
                </div>

                <section class="mb-5 overflow-hidden rounded-lg border border-teal-200 bg-white dark:border-slate-700 dark:bg-slate-800/60">
                    <div class="mb-3 flex items-center justify-between gap-3 bg-[#20877e] px-4 py-3 text-white">
                        <div>
                            <h1 class="text-lg font-bold">{{ $page.props.pageTitle }}</h1>
                        </div>
                        <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white">
                            {{ props.id ? 'Edit Bill' : 'New Pharmacy Bill' }}
                        </span>
                    </div>
                    <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <InputLabel for="pharmacy_no" value="Pharmacy No" class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500" />
                        <input id="pharmacy_no"
                            class="block w-full rounded-md border-slate-300 bg-white p-2.5 text-sm text-slate-700 shadow-sm focus:border-teal-500 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
                            v-model="form.pharmacy_no" type="text" placeholder="Pharmacy No" readonly />
                        <InputError class="mt-2" :message="form.errors.pharmacy_no" />
                    </div>
                    <div>
                        <InputLabel for="bill_no" value="Bill No" class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500" />
                        <input id="bill_no"
                            class="block w-full rounded-md border-slate-300 bg-white p-2.5 text-sm text-slate-700 shadow-sm focus:border-teal-500 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
                            v-model="form.bill_no" type="text" placeholder="Bill No" readonly />
                        <InputError class="mt-2" :message="form.errors.bill_no" />
                    </div>
                    <div>
                        <InputLabel for="case_id" value="Case ID" class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500" />
                        <input id="case_id"
                            class="block w-full rounded-md border-slate-300 bg-white p-2.5 text-sm text-slate-700 shadow-sm focus:border-teal-500 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
                            v-model="form.case_id" type="text" placeholder="Case ID" />
                        <InputError class="mt-2" :message="form.errors.case_id" />
                    </div>
                    <div>
                        <InputLabel for="date" value="Date" class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500" />
                        <input id="date"
                            class="block w-full rounded-md border-slate-300 bg-white p-2.5 text-sm text-slate-700 shadow-sm focus:border-teal-500 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
                            v-model="form.date" type="date" />
                        <InputError class="mt-2" :message="form.errors.date" />
                    </div>
                </div>
                </section>

                <section class="mb-5 overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
                    <div class="flex flex-wrap items-center justify-between gap-3 bg-[#20877e] px-4 py-3 text-white">
                        <div>
                            <h2 class="text-base font-semibold">Medicine Products</h2>
                            <p class="text-xs text-blue-100">{{ selectedProductCount }} selected item{{ selectedProductCount === 1 ? '' : 's' }}</p>
                        </div>
                        <button type="button" @click="addMedicineRow"
                            class="inline-flex items-center gap-1 rounded-md bg-teal-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-300">
                            <span class="text-lg leading-none">+</span> Add Medicine
                        </button>
                    </div>

                    <div class="medicine-table-scroll">
                        <table class="w-full min-w-[1120px] border-collapse text-xs">
                            <thead class="bg-[#207c75] text-white dark:bg-slate-800 dark:text-slate-200">
                                <tr>
                                    <th class="border-b border-white/20 px-3 py-3 text-left font-semibold">Category <span class="text-rose-200">*</span></th>
                                    <th class="border-b border-white/20 px-3 py-3 text-left font-semibold">Medicine <span class="text-rose-200">*</span></th>
                                    <th class="border-b border-white/20 px-3 py-3 text-left font-semibold">Batch</th>
                                    <th class="border-b border-white/20 px-3 py-3 text-left font-semibold">Expiry</th>
                                    <th class="border-b border-white/20 px-3 py-3 text-left font-semibold">Quantity / Stock</th>
                                    <th class="border-b border-white/20 px-3 py-3 text-left font-semibold">Sale Price / Tax</th>
                                    <th class="border-b border-white/20 px-3 py-3 text-right font-semibold">Amount (Tk.)</th>
                                    <th class="w-16 border-b border-white/20 px-3 py-3 text-center font-semibold">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <tr v-for="(row, index) in medicineRows" :key="row.id"
                                    class="bg-white transition hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800">
                                    <td class="medicine-dropdown-cell px-2 py-2">
                                        <select v-model="row.medicineCategory"
                                            @change="handleCategorySelection(row.medicineCategory, row, index)"
                                            :id="`category-select-${index}`"
                                            class="w-full rounded-md border-slate-300 bg-white px-2 py-2 text-xs text-slate-700 focus:border-teal-500 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                                            <option value="">Select category</option>
                                            <option v-for="category in props.categories" :key="category.id"
                                                :value="category.name">
                                                {{ category.name }}</option>
                                        </select>
                                    </td>
                                    <td class="medicine-dropdown-cell px-2 py-2">
                                        <div :id="`medicine-multiselect-${index}`" class="medicine-multiselect-wrapper">
                                            <Multiselect v-model="row.medicineName"
                                                :options="getFilteredMedicines(row.medicineCategory)" :searchable="true"
                                                :internal-search="false" :loading="medicineSearchLoading[index] || false"
                                                :close-on-select="true" :show-labels="false" :show-no-results="true"
                                                label="medicine_name" track-by="id"
                                                :placeholder="row.medicineCategory ? 'Type 2+ letters to search' : 'Select category first'"
                                                @search-change="searchMedicines($event, row, index)"
                                                @select="handleMedicineSelection($event, row, index)"
                                                :class="`text-xs medicine-multiselect medicine-multiselect-${index}`">
                                                <template #noOptions>
                                                    <span class="text-xs text-slate-500">Choose a category to search medicines.</span>
                                                </template>
                                                <template #noResult>
                                                    <span class="text-xs text-slate-500">No matching medicine found in available stock.</span>
                                                </template>
                                            </Multiselect>
                                        </div>
                                    </td>
                                    <td class="px-2 py-2">
                                        <input v-model="row.batchNo" type="text"
                                            class="w-full rounded-md border-slate-200 bg-slate-50 px-2 py-2 text-xs text-slate-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                            readonly />
                                    </td>
                                    <td class="px-2 py-2">
                                        <input type="date" v-model="row.expiryDate"
                                            class="w-full rounded-md border-slate-200 bg-slate-50 px-2 py-2 text-xs text-slate-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                            readonly />
                                    </td>
                                    <td class="px-2 py-2">
                                        <div class="flex items-center gap-2">
                                            <input type="number" v-model="row.quantity" :id="`quantity-${index}`"
                                                @input="validateQuantity(row)"
                                                @keydown.enter.prevent="handleQuantityEnter(index)"
                                                class="w-20 rounded-md border-slate-300 px-2 py-2 text-center text-xs focus:border-teal-500 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                                min="1" :max="row.availableQty" />
                                            <span class="whitespace-nowrap rounded-full bg-slate-100 px-2 py-1 text-[10px] font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                                Stock {{ ensureNumber(row.availableQty, 0) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-2 py-2">
                                        <div class="flex items-center gap-1.5">
                                            <input type="number" v-model="row.salePrice" @input="calculateTotal"
                                                class="w-24 rounded-md border-slate-300 px-2 py-2 text-xs focus:border-teal-500 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                                step="0.01" />
                                            <input type="number" v-model="row.tax" @input="calculateTotal"
                                                class="w-16 rounded-md border-slate-300 px-2 py-2 text-xs focus:border-teal-500 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                                placeholder="%" step="0.01" />
                                            <span class="text-[10px] text-slate-500">%</span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-right font-semibold tabular-nums text-slate-800 dark:text-slate-100">
                                        ৳ {{ ensureNumber(row.amount, 0).toFixed(2) }}
                                    </td>
                                    <td class="px-2 py-2 text-center">
                                        <button type="button" @click="removeMedicineRow(index)"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-rose-50 text-lg font-semibold text-rose-600 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-rose-900/20 dark:text-rose-300 dark:hover:bg-rose-900/40"
                                            :aria-label="`Remove medicine row ${index + 1}`"
                                            :disabled="medicineRows.length === 1">
                                            &times;
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-slate-50 dark:bg-slate-800">
                                <tr>
                                    <td colspan="6" class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Subtotal</td>
                                    <td class="px-3 py-3 text-right text-sm font-bold tabular-nums text-slate-900 dark:text-white">৳ {{ ensureNumber(form.subtotal, 0).toFixed(2) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <InputError class="px-4 py-2" :message="form.errors.products" />
                </section>

                <div class="grid grid-cols-1 items-start gap-5 xl:grid-cols-2">
                    <section class="space-y-4 rounded-lg border border-teal-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                        <div class="relative">
                            <InputLabel for="doctor_id" value="Hospital Doctor" class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500" />
                            <Multiselect v-model="selectedDoctor" :options="props.doctors" :searchable="true"
                                :close-on-select="true" :show-labels="false" label="name" track-by="id"
                                placeholder="Select a doctor" @select="handleDoctorSelection">
                            </Multiselect>
                            <InputError class="mt-2" :message="form.errors.doctor_id" />
                        </div>
                        <div>
                            <InputLabel for="note" value="Note" class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500" />
                            <textarea v-model="form.note" rows="3"
                                class="block w-full rounded-md border-slate-300 p-2.5 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"></textarea>
                            <InputError class="mt-2" :message="form.errors.note" />
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-lg border border-teal-200 bg-white dark:border-teal-900 dark:bg-teal-950/30">
                        <div class="mb-3 flex items-center justify-between bg-[#20877e] px-4 py-3 text-white">
                            <div>
                                <h2 class="text-base font-bold">Bill Summary</h2>
                                <p class="text-xs text-teal-50">{{ selectedProductCount }} items</p>
                            </div>
                            <span class="text-xs font-medium text-teal-50">BDT (৳)</span>
                        </div>
                        <div class="space-y-3 p-4">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-slate-600 dark:text-slate-300">Subtotal</span>
                                <span class="text-base font-bold tabular-nums text-slate-900 dark:text-white">৳ {{ ensureNumber(form.subtotal, 0).toFixed(2) }}</span>
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="text-sm text-slate-600 dark:text-slate-300">Discount</span>
                                <div class="flex items-center gap-2">
                                    <input v-model="form.discount_percentage" type="number" step="0.01"
                                        class="w-20 rounded-md border-slate-300 px-2 py-1.5 text-right text-sm focus:border-teal-500 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                        placeholder="%" min="0" max="100" />
                                    <span class="text-xs text-slate-500">%</span>
                                    <span class="w-24 text-right text-sm font-medium tabular-nums text-slate-700 dark:text-slate-200">৳ {{ ensureNumber(form.discount_amount, 0).toFixed(2) }}</span>
                                </div>
                            </div>
                            <InputError :message="form.errors.discount_percentage" />

                            <div class="flex justify-between items-center">
                                <span class="text-sm text-slate-600 dark:text-slate-300">VAT</span>
                                <div class="flex items-center gap-2">
                                    <input v-model="form.vat_percentage" type="number" step="0.01"
                                        class="w-20 rounded-md border-slate-300 px-2 py-1.5 text-right text-sm focus:border-teal-500 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                        placeholder="%" min="0" max="100" />
                                    <span class="text-xs text-slate-500">%</span>
                                    <span class="w-24 text-right text-sm font-medium tabular-nums text-slate-700 dark:text-slate-200">৳ {{ ensureNumber(form.vat_amount, 0).toFixed(2) }}</span>
                                </div>
                            </div>
                            <InputError :message="form.errors.vat_percentage" />

                            <div class="flex justify-between items-center">
                                <span class="text-sm text-slate-600 dark:text-slate-300">Extra Discount</span>
                                <div class="flex items-center space-x-2">
                                    <input v-model="form.extra_discount" type="number" step="0.01" min="0"
                                        class="w-28 rounded-md border-slate-300 px-2 py-1.5 text-right text-sm focus:border-teal-500 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                        placeholder="0.00" />
                                </div>
                            </div>
                            <InputError :message="form.errors.extra_discount" />

                            <div class="flex justify-between items-center border-t border-teal-200 pt-3 dark:border-teal-900">
                                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Net Amount</span>
                                <span class="text-2xl font-bold tabular-nums text-emerald-700 dark:text-emerald-400">৳ {{ ensureNumber(form.net_amount, 0).toFixed(2) }}</span>
                            </div>

                            <div class="grid grid-cols-1 gap-3 border-t border-teal-200 pt-3 sm:grid-cols-2 dark:border-teal-900">
                                <div>
                                    <InputLabel for="payment_mode" value="Payment Mode" class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500" />
                                    <select v-model="form.payment_mode"
                                        class="block w-full rounded-md border-slate-300 bg-white p-2.5 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200">
                                        <option>Cash</option>
                                        <option>Card</option>
                                        <option>Bank Transfer</option>
                                    </select>
                                    <InputError class="mt-1" :message="form.errors.payment_mode" />
                                </div>

                                <div>
                                    <InputLabel for="payment_amount" value="Payment Amount (Tk.)" class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500" />
                                    <input v-model="form.payment_amount" type="number" step="0.01" min="0"
                                        class="block w-full rounded-md border-slate-300 bg-white p-2.5 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200" />
                                    <InputError class="mt-1" :message="form.errors.payment_amount" />
                                </div>

                                <div v-if="shouldShowReturnAmount" class="sm:col-span-2">
                                    <InputLabel for="return_amount" value="Return Amount (Tk.)" class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500" />
                                    <input v-model="form.return_amount" type="number" step="0.01" min="0"
                                        :max="overPaymentAmount"
                                        class="block w-full rounded-md border-slate-300 bg-white p-2.5 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200" />

                                    <div class="mt-1 text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                                        Return Preview: {{ ensureNumber(form.return_amount, 0).toFixed(2) }} Tk.
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-between border-t border-teal-200 pt-2 text-sm dark:border-teal-900">
                                <span class="font-medium text-slate-600 dark:text-slate-300">Due Amount</span>
                                <span class="font-bold tabular-nums text-amber-700 dark:text-amber-400">৳ {{ dueAmount.toFixed(2) }}</span>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="mt-5 flex flex-col-reverse items-stretch justify-end gap-2 border-t border-slate-200 pt-4 sm:flex-row dark:border-slate-700">
                    <button v-if="!form.ipd_patient_id" type="button"
                        @click="submit(true)"
                        class="inline-flex items-center justify-center rounded-md px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors"
                        :class="form.processing ? 'cursor-not-allowed bg-blue-300' : 'bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2'"
                        :disabled="form.processing">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V4h12v5M6 18h12M8 14h8M6 9h12a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2Z" />
                        </svg>
                        {{ form.processing ? 'Saving...' : 'Save & Print' }}
                    </button>
                    <PrimaryButton type="submit" class="justify-center rounded-md px-5 py-2.5"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing">
                        💾 {{ props.id ? 'Update' : 'Save' }}
                    </PrimaryButton>
                </div>
            </form>
            </div>
        </div>
    </BackendLayout>
</template>

<style scoped>
.relative {
    position: relative;
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

.medicine-dropdown-cell {
    position: relative;
    overflow: visible !important;
}

table {
    position: relative;
    z-index: 1;
}

tbody tr {
    position: relative;
    z-index: 2;
}

.medicine-dropdown-cell:focus-within {
    z-index: 10000;
}

.medicine-dropdown-cell:focus-within .medicine-multiselect-wrapper {
    z-index: 10001;
}

.medicine-dropdown-cell:focus-within .multiselect__content-wrapper {
    z-index: 10002 !important;
}

.dark .multiselect {
    background-color: #374151;
    border-color: #4b5563;
    color: #e5e7eb;
}

.dark .multiselect__input,
.dark .multiselect__single {
    background: #374151;
    color: #e5e7eb;
}

.dark .multiselect__content-wrapper {
    background: #374151 !important;
    border-color: #4b5563 !important;
}

.dark .multiselect__option {
    color: #e5e7eb;
}

.dark .multiselect__option--highlight {
    background: #4b5563 !important;
    color: #f3f4f6 !important;
}

.dark .multiselect__option--selected {
    background: #1f2937 !important;
    color: #60a5fa !important;
}

.medicine-table-scroll {
    overflow-x: auto;
    overflow-y: visible;
    isolation: isolate;
}

.medicine-table-scroll table {
    min-width: 1120px;
}

.z-30 {
    z-index: 30;
}

.z-40 {
    z-index: 40;
}

.z-50 {
    z-index: 50;
}

.pharmacy-patient-select :deep(.multiselect__tags) {
    min-height: 40px;
    padding: 8px 40px 0 12px;
    border-color: rgb(203 213 225);
}

.pharmacy-patient-select :deep(.multiselect__single) {
    margin-bottom: 0;
}

.dark .pharmacy-patient-select :deep(.multiselect__tags),
.dark .pharmacy-patient-select :deep(.multiselect__input),
.dark .pharmacy-patient-select :deep(.multiselect__single) {
    border-color: rgb(71 85 105);
    background: rgb(30 41 59);
    color: rgb(226 232 240);
}
</style>