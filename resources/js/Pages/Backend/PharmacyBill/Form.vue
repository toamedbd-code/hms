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

const props = defineProps([
    'pharmacybill',
    'id',
    'patients',
    'medicines',
    'doctors',
    'billnumber',
    'pharmacyNo',
    'caseId',
    'categories'
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
    if (!categoryName) return props.medicines;
    return props.medicines.filter(medicine =>
        medicine.category?.name === categoryName
    );
};

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
    
    calculateTotal();
    nextTick(() => {
        focusOnMedicineName(0);
    });
});

const submit = (shouldPrint = false) => {
    let printTab = null;
    if (shouldPrint) {
        printTab = window.open('about:blank', '_blank');
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

    form.post(routeName, {
        onSuccess: (page) => {
            const billId = page?.props?.flash?.billId;

            if (shouldPrint) {
                const printUrl = billId
                    ? route('backend.download.invoice', { id: billId, module: 'pharmacy' })
                    : null;

                if (printUrl) {
                    if (printTab) {
                        printTab.location.href = printUrl;
                    } else {
                        window.open(printUrl, '_blank');
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

        <div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-lg shadow-lg">
            <div
                class="flex items-center justify-between w-full px-4 py-2 bg-gray-100 border-b rounded-md border-gray-300">
                <div class="flex items-center space-x-4">
                    <div class="relative min-w-[280px]">
                        <div class="relative">
                            <div class="col-span-1">
                                <Multiselect v-model="selectedPatient" :options="patients" :track-by="'id'"
                                    :label="'name'" placeholder="Search and select a patient"
                                    class="w-full text-sm h-[30px] rounded-md border border-slate-300"
                                    @select="handlePatientSelection" />
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
                        <button @click="goToProductReturn"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-emerald-400 to-emerald-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-emerald-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-emerald-500 hover:to-emerald-700">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 7.5h11.25M3 12h7.5m-7.5 4.5h11.25M16.5 7.5l4.5 4.5m0 0-4.5 4.5m4.5-4.5h-9" />
                            </svg>
                            Billing Product Return
                        </button>
                        <button @click="goToPharmacyBillList"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z">
                                </path>
                            </svg>
                            Pharmacy Bill List
                        </button>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="p-4">

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                    <div>
                        <InputLabel for="pharmacy_no" value="Pharmacy No" />
                        <input id="pharmacy_no"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.pharmacy_no" type="text" placeholder="Pharmacy No" readonly />
                        <InputError class="mt-2" :message="form.errors.pharmacy_no" />
                    </div>
                    <div>
                        <InputLabel for="bill_no" value="Bill No" />
                        <input id="bill_no"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.bill_no" type="text" placeholder="Bill No" readonly />
                        <InputError class="mt-2" :message="form.errors.bill_no" />
                    </div>
                    <div>
                        <InputLabel for="case_id" value="Case ID" />
                        <input id="case_id"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.case_id" type="text" placeholder="Case ID" />
                        <InputError class="mt-2" :message="form.errors.case_id" />
                    </div>
                    <div>
                        <InputLabel for="date" value="Date" />
                        <input id="date"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.date" type="date" />
                        <InputError class="mt-2" :message="form.errors.date" />
                    </div>
                </div>

                <hr class="my-4" />

                <div class="mt-4">
                    <div class="flex items-center justify-between mb-2">
                        <h2 class="text-lg font-semibold dark:text-white">Medicine Products</h2>
                        <button type="button" @click="addMedicineRow"
                            class="px-3 py-1 text-white bg-blue-500 rounded hover:bg-blue-600">+ Add</button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm border-collapse table-auto">
                            <thead>
                                <tr class="bg-gray-200">
                                    <th class="p-2 text-left border border-gray-300 dark:border-gray-600">Medicine
                                        Category *</th>
                                    <th class="p-2 text-left border border-gray-300 dark:border-gray-600">Medicine Name
                                        *</th>
                                    <th class="p-2 text-left border border-gray-300 dark:border-gray-600">Batch No *
                                    </th>
                                    <th class="p-2 text-left border border-gray-300 dark:border-gray-600">Expiry Date *
                                    </th>
                                    <th class="p-2 text-left border border-gray-300 dark:border-gray-600">Quantity * |
                                        Available Qty
                                    </th>
                                    <th class="p-2 text-left border border-gray-300 dark:border-gray-600">Sale Price
                                        (Tk.) * Tax
                                    </th>
                                    <th class="p-2 text-left border border-gray-300 dark:border-gray-600">Amount (Tk.) *
                                    </th>
                                    <th class="p-2 text-center border border-gray-300 dark:border-gray-600">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, index) in medicineRows" :key="row.id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="p-1 border border-gray-300 dark:border-gray-600">
                                        <select v-model="row.medicineCategory"
                                            @change="handleCategorySelection(row.medicineCategory, row, index)"
                                            :id="`category-select-${index}`"
                                            class="w-full p-1 text-sm border-none rounded focus:outline-none dark:bg-slate-700 dark:text-white">
                                            <option value="">Select</option>
                                            <option v-for="category in props.categories" :key="category.id"
                                                :value="category.name">
                                                {{ category.name }}</option>
                                        </select>
                                    </td>
                                    <td class="p-1 border border-gray-300 dark:border-gray-600 medicine-dropdown-cell">
                                        <div :id="`medicine-multiselect-${index}`" class="medicine-multiselect-wrapper">
                                            <Multiselect v-model="row.medicineName"
                                                :options="getFilteredMedicines(row.medicineCategory)" :searchable="true"
                                                :close-on-select="true" :show-labels="false" label="medicine_name"
                                                track-by="id" placeholder="Select medicine"
                                                @select="handleMedicineSelection($event, row, index)"
                                                :class="`text-xs medicine-multiselect medicine-multiselect-${index}`">
                                            </Multiselect>
                                        </div>
                                    </td>
                                    <td class="p-1 border border-gray-300 dark:border-gray-600">
                                        <input v-model="row.batchNo" type="text"
                                            class="w-full p-1 text-sm border-none rounded focus:outline-none dark:bg-slate-700 dark:text-white"
                                            readonly />
                                    </td>
                                    <td class="p-1 border border-gray-300 dark:border-gray-600">
                                        <input type="date" v-model="row.expiryDate"
                                            class="w-full p-1 text-sm border-none rounded focus:outline-none dark:bg-slate-700 dark:text-white"
                                            readonly />
                                    </td>
                                    <td class="p-1 border border-gray-300 dark:border-gray-600">
                                        <div class="flex items-center space-x-1">
                                            <input type="number" v-model="row.quantity" :id="`quantity-${index}`"
                                                @input="validateQuantity(row)"
                                                @keydown.enter.prevent="handleQuantityEnter(index)"
                                                class="w-16 p-1 text-sm text-center border-none rounded focus:outline-none dark:bg-slate-700 dark:text-white"
                                                min="1" :max="row.availableQty" />
                                            <span class="text-xs text-gray-500">| {{ ensureNumber(row.availableQty, 0) }}</span>
                                        </div>
                                    </td>
                                    <td class="p-1 border border-gray-300 dark:border-gray-600">
                                        <div class="flex items-center space-x-1">
                                            <input type="number" v-model="row.salePrice" @input="calculateTotal"
                                                class="w-20 p-1 text-sm border-none rounded focus:outline-none dark:bg-slate-700 dark:text-white"
                                                step="0.01" />
                                            <input type="number" v-model="row.tax" @input="calculateTotal"
                                                class="w-12 p-1 text-sm border-none rounded focus:outline-none dark:bg-slate-700 dark:text-white"
                                                placeholder="%" step="0.01" />
                                            <span class="text-xs">%</span>
                                        </div>
                                    </td>
                                    <td
                                        class="p-1 text-center border border-gray-300 dark:border-gray-600 dark:text-white">
                                        {{ ensureNumber(row.amount, 0).toFixed(2) }}
                                    </td>
                                    <td class="p-1 text-center border border-gray-300 dark:border-gray-600">
                                        <button type="button" @click="removeMedicineRow(index)"
                                            class="p-1 text-white bg-red-500 rounded hover:bg-red-600"
                                            :disabled="medicineRows.length === 1">
                                            ×
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <hr class="my-4" />

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-4">
                        <div class="relative">
                            <InputLabel for="doctor_id" value="Hospital Doctor" />
                            <Multiselect v-model="selectedDoctor" :options="props.doctors" :searchable="true"
                                :close-on-select="true" :show-labels="false" label="name" track-by="id"
                                placeholder="Select a doctor" @select="handleDoctorSelection">
                            </Multiselect>
                            <InputError class="mt-2" :message="form.errors.doctor_id" />
                        </div>
                        <div>
                            <InputLabel for="note" value="Note" />
                            <textarea v-model="form.note" rows="3"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"></textarea>
                            <InputError class="mt-2" :message="form.errors.note" />
                        </div>
                    </div>

                    <div class="p-4 bg-gray-100 rounded-md dark:bg-gray-800">
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="font-semibold dark:text-white">Total (Tk.)</span>
                                <span class="text-2xl font-bold dark:text-white">{{ ensureNumber(form.subtotal, 0).toFixed(2) }}</span>
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="dark:text-white">Discount (Tk.)</span>
                                <div class="flex items-center space-x-2">
                                    <input v-model="form.discount_percentage" type="number" step="0.01"
                                        class="w-16 p-1 text-right border rounded dark:bg-gray-700 dark:text-white text-sm"
                                        placeholder="%" />
                                    <span class="text-sm dark:text-white">%</span>
                                    <span class="dark:text-white">{{ ensureNumber(form.discount_amount, 0).toFixed(2) }}</span>
                                </div>
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="dark:text-white">Tax (Tk.)</span>
                                <div class="flex items-center space-x-2">
                                    <input v-model="form.vat_percentage" type="number" step="0.01"
                                        class="w-16 p-1 text-right border rounded dark:bg-gray-700 dark:text-white text-sm"
                                        placeholder="%" />
                                    <span class="text-sm dark:text-white">%</span>
                                    <span class="dark:text-white">{{ ensureNumber(form.vat_amount, 0).toFixed(2) }}</span>
                                </div>
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="dark:text-white">Extra Discount (Tk.)</span>
                                <div class="flex items-center space-x-2">
                                    <input v-model="form.extra_discount" type="number" step="0.01" min="0"
                                        class="w-28 p-1 text-right border rounded dark:bg-gray-700 dark:text-white text-sm"
                                        placeholder="0.00" />
                                    <span class="dark:text-white">{{ ensureNumber(form.extra_discount, 0).toFixed(2) }}</span>
                                </div>
                            </div>

                            <div class="flex justify-between items-center border-t pt-2">
                                <span class="font-semibold dark:text-white">Net Amount (Tk.)</span>
                                <span class="text-xl font-bold text-green-600 dark:text-green-400">{{
                                    ensureNumber(form.net_amount, 0).toFixed(2)
                                    }}</span>
                            </div>

                            <div class="border-t pt-3 space-y-2">
                                <div>
                                    <InputLabel for="payment_mode" value="Payment Mode" />
                                    <select v-model="form.payment_mode"
                                        class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                                        <option>Cash</option>
                                        <option>Card</option>
                                        <option>Bank Transfer</option>
                                    </select>
                                </div>

                                <div>
                                    <InputLabel for="payment_amount" value="Payment Amount (Tk.)" />
                                    <input v-model="form.payment_amount" type="number" step="0.01"
                                        class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                                </div>

                                <div v-if="shouldShowReturnAmount">
                                    <InputLabel for="return_amount" value="Return Amount (Tk.)" />
                                    <input v-model="form.return_amount" type="number" step="0.01" min="0"
                                        :max="overPaymentAmount"
                                        class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />

                                    <div class="mt-1 text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                                        Return Preview: {{ ensureNumber(form.return_amount, 0).toFixed(2) }} Tk.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end mt-6 space-x-3">
                    <button type="button"
                        @click="submit(true)"
                        class="inline-flex items-center px-6 py-2 font-semibold text-white rounded shadow-sm transition-colors"
                        :class="form.processing ? 'bg-blue-300 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700'"
                        :disabled="form.processing">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V4h12v5M6 18h12M8 14h8M6 9h12a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2Z" />
                        </svg>
                        {{ form.processing ? 'Saving...' : 'Save & Print' }}
                    </button>
                    <PrimaryButton type="submit" class="px-6 py-2" :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing">
                        💾 {{ props.id ? 'Update' : 'Save' }}
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

.overflow-x-auto {
    overflow: visible !important;
}

.overflow-x-auto table {
    min-width: 100%;
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
</style>