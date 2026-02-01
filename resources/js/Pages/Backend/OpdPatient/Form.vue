<script setup>
import { ref, computed, watch } from 'vue';
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

const props = defineProps(['opdpatient', 'id', 'patients', 'doctors', 'chargeTypes', 'charges']);

const form = useForm({
    patient_id: props.opdpatient?.patient_id ? { id: props.opdpatient.patient_id, name: props.opdpatient.patient?.name } :'',
    consultant_doctor_id: props.opdpatient?.consultant_doctor_id ? { id: props.opdpatient.consultant_doctor_id, name: props.opdpatient.doctor?.name } : '',

    // Left side fields
    symptom_type: props.opdpatient?.symptom_type ?? '',
    symptom_title: props.opdpatient?.symptom_title ?? '',
    symptom_description: props.opdpatient?.symptom_description ?? '',
    note: props.opdpatient?.note ?? '',
    allergies: props.opdpatient?.allergies ?? '',

    // Right side fields
    appointment_date: props.opdpatient?.appointment_date ?? '',
    case: props.opdpatient?.case ?? '',
    casualty: props.opdpatient?.casualty ?? 'no',
    old_patient: props.opdpatient?.old_patient ?? 'no',
    reference: props.opdpatient?.reference ?? '',
    apply_tpa: Boolean(props.opdpatient?.apply_tpa) || false,
    charge_id: props.opdpatient?.charge_id ?? '',
    charge_type_id: props.opdpatient?.charge_type_id ?? '',
    applied_charge: props.opdpatient?.applied_charge ?? 0,
    standard_charge: props.opdpatient?.standard_charge ?? 0,
    tax: props.opdpatient?.tax ?? 0,
    discount: props.opdpatient?.discount ?? 0,
    payment_mode: props.opdpatient?.payment_mode ?? 'cash',
    amount: props.opdpatient?.amount ?? 0,
    live_consultation: props.opdpatient?.live_consultation ?? 'no',
    paid_amount: props.opdpatient?.paid_amount ?? 0,

    _method: props.opdpatient?.id ? 'put' : 'post',
});

const filteredCharges = computed(() => {
    if (!form.charge_type_id) {
        return [];
    }
    return props.charges.filter(charge => charge.charge_type_id == form.charge_type_id);
});

const calculateAmount = () => {
    const appliedCharge = parseFloat(form.applied_charge) || 0;
    const tax = parseFloat(form.tax) || 0;
    const discount = parseFloat(form.discount) || 0;

    const taxAmount = (appliedCharge * tax) / 100;
    const discountAmount = (appliedCharge * discount) / 100;
    const totalAmount = appliedCharge + taxAmount - discountAmount;

    form.amount = parseFloat(totalAmount.toFixed(2));
};

watch(() => form.charge_type_id, (newChargeType) => {
    form.charge_id = '';
    form.standard_charge = 0;
    form.applied_charge = 0;
    form.tax = 0;
    calculateAmount();
});

watch(() => form.charge_id, (newChargeId) => {
    if (newChargeId) {
        const selectedCharge = props.charges.find(charge => charge.id == newChargeId);
        if (selectedCharge) {
            form.standard_charge = selectedCharge.standard_charge || 0;
            form.applied_charge = selectedCharge.standard_charge || 0;
            form.tax = selectedCharge.tax || 0;
            calculateAmount();
        }
    } else {
        form.standard_charge = 0;
        form.applied_charge = 0;
        form.tax = 0;
        calculateAmount();
    }
});

watch([() => form.applied_charge, () => form.tax, () => form.discount], () => {
    calculateAmount();
});

const submit = () => {
    const routeName = props.id ? route('backend.opdpatient.update', props.id) : route('backend.opdpatient.store');

    form.transform(data => ({
        ...data,
        patient_id: data.patient_id?.id || data.patient_id,
        consultant_doctor_id: data.consultant_doctor_id?.id || data.consultant_doctor_id,
        remember: '',
        isDirty: false,
    })).post(routeName, {
        onSuccess: (response) => {
            if (!props.id) {
                form.reset();
            }
            const successMessage = response?.props?.flash?.successMessage;
            const billId = response?.props?.flash?.billId;

            if (successMessage && billId) {
                window.open(route("backend.download.opd.bill", { id: billId, module: 'opd' }), "_blank");

            }
            displayResponse(response);
        },
        onError: (errorObject) => {
            displayWarning(errorObject);
        },
    });
};

const saveAndPrint = () => {
    submit();
    setTimeout(() => {
        window.print();
    }, 1000);
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
        }
    });
};

const goToOpdList = () => {
    router.visit(route('backend.opdpatient.index'));
};

</script>

<template>
    <!-- Patient Modal -->
    <PatientModal :isOpen="isPatientModalOpen" :tpas="props.tpas" @close="closePatientModal"
        @patientCreated="handlePatientCreated" />
    <BackendLayout>
        <div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md">

            <div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md">
                <div>
                    <h1 class="p-4 text-xl font-bold">{{ $page.props.pageTitle }}</h1>
                </div>

                <div class="flex items-center p-3 py-2 space-x-1">
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

                    <div class="flex items-center space-x-1">
                        <button @click="openPatientModal"
                            class="px-3 py-2.5 text-sm text-white bg-green-600 rounded hover:bg-green-700 transition-colors">
                            + New Patient
                        </button>
                    </div>

                    <div class="p-2 py-2 flex items-center space-x-2">
                        <div class="flex items-center space-x-3">
                            <button @click="goToOpdList"
                                class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2">
                                <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z">
                                    </path>
                                </svg>
                                Opd Patient List
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="p-4">

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <!-- Left Column -->
                    <div class="space-y-3">
                        <!-- Symptoms Type and Title in same row -->
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <InputLabel for="symptom_type" value="Symptoms Type" />
                                <select id="symptom_type" v-model="form.symptom_type"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300">
                                    <option value="">Select</option>
                                    <option value="fever">Fever</option>
                                    <option value="cough">Cough</option>
                                    <option value="headache">Headache</option>
                                    <option value="body_pain">Body Pain</option>
                                    <option value="other">Other</option>
                                </select>
                                <InputError class="mt-1" :message="form.errors.symptom_type" />
                            </div>

                            <div>
                                <InputLabel for="symptom_title" value="Symptoms Title" />
                                <input id="symptom_title" v-model="form.symptom_title" type="text"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300"
                                    placeholder="Enter symptom title" />
                                <InputError class="mt-1" :message="form.errors.symptom_title" />
                            </div>
                        </div>

                        <!-- Symptoms Description -->
                        <div>
                            <InputLabel for="symptom_description" value="Symptoms Description" />
                            <textarea id="symptom_description" v-model="form.symptom_description" rows="2"
                                class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300"
                                placeholder="Describe symptoms in detail"></textarea>
                            <InputError class="mt-1" :message="form.errors.symptom_description" />
                        </div>

                        <!-- Note -->
                        <div>
                            <InputLabel for="note" value="Note" />
                            <textarea id="note" v-model="form.note" rows="2"
                                class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300"
                                placeholder="Additional notes"></textarea>
                            <InputError class="mt-1" :message="form.errors.note" />
                        </div>

                        <!-- Any Known Allergies -->
                        <div>
                            <InputLabel for="allergies" value="Any Known Allergies" />
                            <textarea id="allergies" v-model="form.allergies" rows="2"
                                class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300"
                                placeholder="List any known allergies"></textarea>
                            <InputError class="mt-1" :message="form.errors.allergies" />
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-3">
                        <!-- Appointment Date & Case -->
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <InputLabel for="appointment_date" value="Appointment Date" class="required" />
                                <input id="appointment_date" v-model="form.appointment_date" type="date"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300"
                                    required />
                                <InputError class="mt-1" :message="form.errors.appointment_date" />
                            </div>
                            <div>
                                <InputLabel for="case" value="Case" />
                                <select id="case" v-model="form.case"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300">
                                    <option value="">Select</option>
                                    <option value="new">New Case</option>
                                    <option value="followup">Follow-up</option>
                                    <option value="emergency">Emergency</option>
                                </select>
                                <InputError class="mt-1" :message="form.errors.case" />
                            </div>
                        </div>

                        <!-- Casualty & Old Patient -->
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <InputLabel for="casualty" value="Casualty" />
                                <select id="casualty" v-model="form.casualty"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300">
                                    <option value="no">No</option>
                                    <option value="yes">Yes</option>
                                </select>
                                <InputError class="mt-1" :message="form.errors.casualty" />
                            </div>
                            <div>
                                <InputLabel for="old_patient" value="Old Patient" />
                                <select id="old_patient" v-model="form.old_patient"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300">
                                    <option value="no">No</option>
                                    <option value="yes">Yes</option>
                                </select>
                                <InputError class="mt-1" :message="form.errors.old_patient" />
                            </div>
                        </div>

                        <!-- Reference & Consultant Doctor -->
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <InputLabel for="reference" value="Reference" />
                                <input id="reference" v-model="form.reference" type="text"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300"
                                    placeholder="Reference" />
                                <InputError class="mt-1" :message="form.errors.reference" />
                            </div>
                            <div>
                                <InputLabel for="consultant_doctor_id" value="Consultant Doctor" class="required" />
                                <Multiselect v-model="form.consultant_doctor_id" :options="doctors" :track-by="'id'"
                                    :label="'name'" placeholder="Search and select a doctor"
                                    class="w-full text-sm rounded-md border border-slate-300" />
                                <InputError class="mt-1" :message="form.errors.consultant_doctor_id" />
                            </div>
                        </div>

                        <!-- Apply TPA -->
                        <div class="flex items-center mt-1">
                            <input id="apply_tpa" v-model="form.apply_tpa" type="checkbox"
                                class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" />
                            <InputLabel for="apply_tpa" value="Apply TPA" class="ml-2" />
                        </div>

                        <!-- Charge Category & Charge (Dynamic) -->
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <InputLabel for="charge_type_id" value="Charge Category" class="required" />
                                <select id="charge_type_id" v-model="form.charge_type_id"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300"
                                    required>
                                    <option value="">Select Charge Category</option>
                                    <option v-for="data in chargeTypes" :key="data.id" :value="data.id">{{ data.name }}
                                    </option>
                                </select>
                                <InputError class="mt-1" :message="form.errors.charge_type_id" />
                            </div>
                            <div>
                                <InputLabel for="charge_id" value="Charge" class="required" />
                                <select id="charge_id" v-model="form.charge_id"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300"
                                    :disabled="!form.charge_type_id" required>
                                    <option value="">{{ form.charge_type_id ? 'Select Charge' : 'Select Charge Category First' }}</option>
                                    <option v-for="data in filteredCharges" :key="data.id" :value="data.id">
                                        {{ data.name }} - {{ data.standard_charge }} Tk
                                    </option>
                                </select>
                                <InputError class="mt-1" :message="form.errors.charge_id" />
                            </div>
                        </div>

                        <!-- Applied Charge & Standard Charge -->
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <InputLabel for="applied_charge" value="Applied Charge (Tk.)" class="required" />
                                <input id="applied_charge" v-model="form.applied_charge" type="number" step="0.01"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300"
                                    placeholder="0" required />
                                <InputError class="mt-1" :message="form.errors.applied_charge" />
                            </div>
                            <div>
                                <InputLabel for="standard_charge" value="Standard Charge (Tk.)" />
                                <input id="standard_charge" v-model="form.standard_charge" type="number" step="0.01"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300"
                                    placeholder="0" readonly />
                                <InputError class="mt-1" :message="form.errors.standard_charge" />
                            </div>
                        </div>

                        <!-- Tax & Discount -->
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <InputLabel for="tax" value="Tax" />
                                <div class="relative">
                                    <input id="tax" v-model="form.tax" type="number" step="0.01"
                                        class="block w-full p-1.5 pr-8 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300"
                                        placeholder="0" />
                                    <span
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-500">%</span>
                                </div>
                                <InputError class="mt-1" :message="form.errors.tax" />
                            </div>
                            <div>
                                <InputLabel for="discount" value="Discount" />
                                <div class="relative">
                                    <input id="discount" v-model="form.discount" type="number" step="0.01"
                                        class="block w-full p-1.5 pr-8 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300"
                                        placeholder="0" />
                                    <span
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-500">%</span>
                                </div>
                                <InputError class="mt-1" :message="form.errors.discount" />
                            </div>
                        </div>

                        <!-- Payment Mode & Amount -->
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <InputLabel for="payment_mode" value="Payment Mode" />
                                <select id="payment_mode" v-model="form.payment_mode"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300">
                                    <option value="cash">Cash</option>
                                    <option value="card">Card</option>
                                    <option value="online">Online</option>
                                    <option value="insurance">Insurance</option>
                                </select>
                                <InputError class="mt-1" :message="form.errors.payment_mode" />
                            </div>
                            <div>
                                <InputLabel for="amount" value="Amount (Tk.)" class="required" />
                                <input id="amount" v-model="form.amount" type="number" step="0.01"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300"
                                    placeholder="0" required readonly />
                                <InputError class="mt-1" :message="form.errors.amount" />
                            </div>
                        </div>

                        <!-- Live Consultation & Paid Amount -->
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <InputLabel for="live_consultation" value="Live Consultation" />
                                <select id="live_consultation" v-model="form.live_consultation"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300">
                                    <option value="no">No</option>
                                    <option value="yes">Yes</option>
                                </select>
                                <InputError class="mt-1" :message="form.errors.live_consultation" />
                            </div>
                            <div>
                                <InputLabel for="paid_amount" value="Paid Amount (Tk.)" class="required" />
                                <input id="paid_amount" v-model="form.paid_amount" type="number" step="0.01"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300"
                                    placeholder="0" required />
                                <InputError class="mt-1" :message="form.errors.paid_amount" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end mt-3">
                    <PrimaryButton type="submit" class="ms-3" :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing">
                        {{ ((props.id ?? false) ? 'Update' : 'Create') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </BackendLayout>
</template>

<style scoped>
.required::after {
    content: " *";
    color: #e53e3e;
}
</style>