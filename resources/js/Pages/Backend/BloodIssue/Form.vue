<script setup>
import { ref, onMounted, computed } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AlertMessage from '@/Components/AlertMessage.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';
import PatientModal from '@/Components/PatientModal.vue';


const props = defineProps(['bloodissue', 'id', 'patients', 'doctors']);

const form = useForm({
    case_id: props.bloodissue?.case_id ?? '',
    patient_id: props.bloodissue?.patient_id ?? '',
    issue_date: props.bloodissue?.issue_date ?? '',
    doctor_id: props.bloodissue?.doctor_id ?? '',
    reference_name: props.bloodissue?.reference_name ?? '',
    technician: props.bloodissue?.technician ?? '',
    blood_group: props.bloodissue?.blood_group ?? '',
    bag: props.bloodissue?.bag ?? '',
    charge_category: props.bloodissue?.charge_category ?? '',
    charge_name: props.bloodissue?.charge_name ?? '',
    standard_charge: parseFloat(props.bloodissue?.standard_charge) || 0,
    note: props.bloodissue?.note ?? '',
    total: parseFloat(props.bloodissue?.total) || 0,
    discount: parseFloat(props.bloodissue?.discount) || 0,
    discount_percent: parseFloat(props.bloodissue?.discount_percent) || 0,
    tax: parseFloat(props.bloodissue?.tax) || 0,
    tax_percent: parseFloat(props.bloodissue?.tax_percent) || 0,
    net_amount: parseFloat(props.bloodissue?.net_amount) || 0,
    payment_mode: props.bloodissue?.payment_mode ?? 'Cash',
    payment_amount: parseFloat(props.bloodissue?.payment_amount) || 0,
    apply_tpa: Boolean(props.bloodissue?.apply_tpa) || false,
    _method: props.bloodissue?.id ? 'put' : 'post',
});

// Computed values for automatic calculations
const calculatedTotal = computed(() => {
    return parseFloat(form.standard_charge) || 0;
});

const calculatedDiscount = computed(() => {
    const total = calculatedTotal.value;
    const discountPercent = parseFloat(form.discount_percent) || 0;
    return (total * discountPercent) / 100;
});

const calculatedTax = computed(() => {
    const total = calculatedTotal.value;
    const discount = calculatedDiscount.value;
    const taxPercent = parseFloat(form.tax_percent) || 0;
    return ((total - discount) * taxPercent) / 100;
});

const calculatedNetAmount = computed(() => {
    const total = calculatedTotal.value;
    const discount = calculatedDiscount.value;
    const tax = calculatedTax.value;
    return total - discount + tax;
});

// Update form values when computed values change
const updateCalculations = () => {
    form.total = calculatedTotal.value;
    form.discount = calculatedDiscount.value;
    form.tax = calculatedTax.value;
    form.net_amount = calculatedNetAmount.value;
};

// Watch for changes in relevant fields
const handleStandardChargeChange = () => {
    updateCalculations();
};

const handleDiscountPercentChange = () => {
    updateCalculations();
};

const handleTaxPercentChange = () => {
    updateCalculations();
};

const submit = () => {
    updateCalculations();
    const routeName = props.id ? route('backend.bloodissue.update', props.id) : route('backend.bloodissue.store');
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

onMounted(() => {
    updateCalculations();
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
        }
    });
};

const goToBloodIssueList = () => {
    router.get(route('backend.bloodissue.index'));
};

</script>

<template>
    <BackendLayout>
        <!-- Patient Modal -->
        <PatientModal :isOpen="isPatientModalOpen" :tpas="props.tpas" @close="closePatientModal"
            @patientCreated="handlePatientCreated" />

        <div class="w-full p-2 transition duration-1000 ease-in-out transform bg-white border rounded-md dark:bg-slate-500">
            <div
                class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md">
                <div>
                    <h1 class="p-4 text-xl font-bold dark:text-white">{{ $page.props.pageTitle }}</h1>
                </div>

                <div class="p-2 py-2 flex items-center space-x-2">
                    <div class="flex items-center space-x-3">
                        <button @click="goToBloodIssueList"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z">
                                </path>
                            </svg>
                            Blood Issue List
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Form Content -->
            <form @submit.prevent="submit" class="spaced-form">
                <AlertMessage />

                <div class="grid grid-cols-12 gap-3 items-end">
                    <div class="col-span-4">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Select Patient</label>
                        <div class="flex">
                            <select v-model="form.patient_id"
                                class="flex-1 px-2 py-1.5 border border-gray-300 rounded-l-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs">
                                <option value="">Select Patient</option>
                                <option v-for="data in patients" :key="data.id" :value="data.id">{{ data.name }}
                                </option>
                            </select>
                            <button type="button" @click="openPatientModal"
                                class="px-2 py-1.5 bg-green-500 text-white rounded-r-md hover:bg-green-600 text-xs flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4"></path>
                                </svg>
                                New
                            </button>
                        </div>
                        <InputError class="mt-0.5" :message="form.errors.patient_id" />
                    </div>

                    <!-- Case ID - 3 columns -->
                    <div class="col-span-3">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Case ID</label>
                        <div class="flex">
                            <input v-model="form.case_id" type="text" placeholder="Case ID"
                                class="flex-1 px-2 py-1.5 border border-gray-300 rounded-l-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs" />
                            <button type="button"
                                class="px-2 py-1.5 bg-gray-100 border border-l-0 border-gray-300 rounded-r-md hover:bg-gray-200">
                                <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </button>
                        </div>
                        <InputError class="mt-0.5" :message="form.errors.case_id" />
                    </div>

                    <!-- Apply TPA - 2 columns -->
                    <div class="col-span-2">
                        <div class="flex items-center h-8">
                            <input v-model="form.apply_tpa" type="checkbox" id="apply_tpa"
                                class="w-3 h-3 text-blue-600 border-gray-300 rounded focus:ring-blue-500" />
                            <label for="apply_tpa" class="ml-1 text-xs font-medium text-gray-700">Apply TPA</label>
                        </div>
                    </div>
                </div>

                <!-- Second Row: Issue Date, Hospital Doctor, Reference Name, Technician -->
                <div class="grid grid-cols-4 gap-3">
                    <!-- Issue Date -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            Issue Date <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.issue_date" type="date" placeholder="mm/dd/yyyy"
                            class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs" />
                        <InputError class="mt-0.5" :message="form.errors.issue_date" />
                    </div>

                    <!-- Hospital Doctor -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Hospital Doctor</label>
                        <select v-model="form.doctor_id"
                            class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs">
                            <option value="">Select</option>
                            <option v-for="data in doctors" :key="data.id" :value="data.id">{{ data.name }}</option>
                        </select>
                        <InputError class="mt-0.5" :message="form.errors.doctor_id" />
                    </div>

                    <!-- Reference Name -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            Reference Name <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.reference_name" type="text" placeholder="Reference Name"
                            class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs" />
                        <InputError class="mt-0.5" :message="form.errors.reference_name" />
                    </div>

                    <!-- Technician -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Technician</label>
                        <input v-model="form.technician" type="text" placeholder="Technician"
                            class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs" />
                        <InputError class="mt-0.5" :message="form.errors.technician" />
                    </div>
                </div>

                <!-- Third Row: Blood Group, Bag, Charge Category, Charge Name -->
                <div class="grid grid-cols-4 gap-3">
                    <!-- Blood Group -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Blood Group</label>
                        <select v-model="form.blood_group"
                            class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs">
                            <option value="">Select</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                        </select>
                        <InputError class="mt-0.5" :message="form.errors.blood_group" />
                    </div>

                    <!-- Bag -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            Bag <span class="text-red-500">*</span>
                        </label>
                        <select v-model="form.bag"
                            class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs">
                            <option value="">Select</option>
                            <option value="bag1">Bag 001</option>
                            <option value="bag2">Bag 002</option>
                        </select>
                        <InputError class="mt-0.5" :message="form.errors.bag" />
                    </div>

                    <!-- Charge Category -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            Charge Category <span class="text-red-500">*</span>
                        </label>
                        <select v-model="form.charge_category"
                            class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs">
                            <option value="">Select</option>
                            <option value="category1">Blood Transfusion</option>
                            <option value="category2">Blood Test</option>
                        </select>
                        <InputError class="mt-0.5" :message="form.errors.charge_category" />
                    </div>

                    <!-- Charge Name -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            Charge Name <span class="text-red-500">*</span>
                        </label>
                        <select v-model="form.charge_name"
                            class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs">
                            <option value="">Select</option>
                            <option value="charge1">Standard Charge</option>
                            <option value="charge2">Premium Charge</option>
                        </select>
                        <InputError class="mt-0.5" :message="form.errors.charge_name" />
                    </div>
                </div>

                <!-- Fourth Row: Standard Charge and Note -->
                <div class="grid grid-cols-12 gap-3">
                    <!-- Standard Charge - 4 columns -->
                    <div class="col-span-4">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Standard Charge (Tk.)</label>
                        <input v-model="form.standard_charge" type="number" step="0.01" placeholder="0"
                            @input="handleStandardChargeChange"
                            class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs" />
                        <InputError class="mt-0.5" :message="form.errors.standard_charge" />
                    </div>

                    <!-- Note - 8 columns -->
                    <div class="col-span-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Note</label>
                        <textarea v-model="form.note" rows="2" placeholder="Enter note here..."
                            class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs resize-none"></textarea>
                        <InputError class="mt-0.5" :message="form.errors.note" />
                    </div>
                </div>

                <!-- Calculation Section -->
                <div class="grid grid-cols-12 gap-3">
                    <!-- Empty space - 4 columns -->
                    <div class="col-span-4"></div>

                    <!-- Calculations - 8 columns -->
                    <div class="col-span-8 space-y-2">
                        <!-- Total -->
                        <div class="flex justify-between items-center py-1">
                            <span class="text-xs font-medium text-gray-700">Total (Tk.)</span>
                            <span class="text-sm font-semibold">{{ (parseFloat(form.total) || 0).toFixed(2) }}</span>
                        </div>

                        <!-- Discount -->
                        <div class="flex justify-between items-center py-1">
                            <span class="text-xs font-medium text-gray-700">Discount (Tk.)</span>
                            <div class="flex items-center gap-2">
                                <input v-model="form.discount_percent" type="number" step="0.01" placeholder="0"
                                    @input="handleDiscountPercentChange"
                                    class="w-12 px-1 py-1 border border-gray-300 rounded text-xs text-center" />
                                <span class="text-xs">%</span>
                                <span class="text-sm font-semibold min-w-[60px] text-right">{{
                                    (parseFloat(form.discount) || 0).toFixed(2)
                                    }}</span>
                            </div>
                        </div>

                        <!-- Tax -->
                        <div class="flex justify-between items-center py-1">
                            <span class="text-xs font-medium text-gray-700">Tax (Tk.)</span>
                            <div class="flex items-center gap-2">
                                <input v-model="form.tax_percent" type="number" step="0.01" placeholder="0"
                                    @input="handleTaxPercentChange"
                                    class="w-12 px-1 py-1 border border-gray-300 rounded text-xs text-center" />
                                <span class="text-xs">%</span>
                                <span class="text-sm font-semibold min-w-[60px] text-right">{{ (parseFloat(form.tax) ||
                                    0).toFixed(2)
                                    }}</span>
                            </div>
                        </div>

                        <!-- Net Amount -->
                        <div class="flex justify-between items-center py-2 border-t border-gray-300">
                            <span class="text-sm font-semibold text-gray-700">Net Amount (Tk.)</span>
                            <span class="text-base font-bold">{{ (parseFloat(form.net_amount) || 0).toFixed(2) }}</span>
                        </div>

                        <!-- Payment Mode -->
                        <div class="flex justify-between items-center py-1">
                            <span class="text-xs font-medium text-gray-700">Payment Mode</span>
                            <select v-model="form.payment_mode"
                                class="px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs min-w-[100px]">
                                <option value="Cash">Cash</option>
                                <option value="Card">Card</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                        </div>

                        <!-- Payment Amount -->
                        <div class="flex justify-between items-center py-1">
                            <span class="text-xs font-medium text-gray-700">
                                Payment Amount (Tk.) <span class="text-red-500">*</span>
                            </span>
                            <input v-model="form.payment_amount" type="number" step="0.01" placeholder="0"
                                class="px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs min-w-[100px] text-right" />
                        </div>
                    </div>
                </div>

                <!-- Action Button -->
                <div class="flex justify-end pt-3">
                    <PrimaryButton type="submit"
                        class="px-6 py-1.5 bg-gray-800 text-white rounded-md hover:bg-gray-900 text-xs font-medium"
                        :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        {{ ((props.id ?? false) ? 'UPDATE' : 'CREATE') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </BackendLayout>
</template>

<style scoped>
.spaced-form>div {
    margin-bottom: 0.5rem;
    /* Adjust this value to increase/decrease spacing */
}

/* If you need even more spacing between specific rows */
.spaced-form .grid {
    margin-bottom: 0.5rem;
}

/* Additional spacing for elements within rows if needed */
.spaced-form .grid>div {
    margin-bottom: 0.5rem;
}

.spaced-form label {
    font-weight: bold;
    color: black;
}
</style>