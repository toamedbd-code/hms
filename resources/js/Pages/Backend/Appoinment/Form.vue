<script setup>
import { ref, onMounted, nextTick, watch } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AlertMessage from '@/Components/AlertMessage.vue';
import PatientModal from '@/Components/PatientModal.vue';
import DoctorModal from '@/Components/DoctorModal.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

const props = defineProps([
    'appoinment', 
    'id', 
    'patients', 
    'doctors', 
    'tpas',
    'designations',
    'departments', 
    'specialists', 
]);

const form = useForm({
    patient_id: props.appoinment?.patient_id ?? '',
    doctor_id: props.appoinment?.doctor_id ?? '',
    doctor_fee: props.appoinment?.doctor_fee ?? '',
    shift: props.appoinment?.shift ?? '',
    appoinment_date: props.appoinment?.appoinment_date ?? '',
    slot: props.appoinment?.slot ?? '',
    appointment_priority: props.appoinment?.appointment_priority ?? '',
    payment_mode: props.appoinment?.payment_mode ?? '',
    appoinment_status: props.appoinment?.appoinment_status ?? 'Pending',
    discount_percentage: props.appoinment?.discount_percentage ?? '',
    message: props.appoinment?.message ?? '',
    live_consultant: props.appoinment?.live_consultant ?? '',

    _method: props.appoinment?.id ? 'put' : 'post',
});

const isPatientModalOpen = ref(false);
const patientsList = ref([...props.patients]);

const getPatientName = (id) => {
    const patient = patientsList.value.find(p => p.id === id);
    return patient ? patient.name : '';
};

const openPatientModal = () => {
    isPatientModalOpen.value = true;
};

const closePatientModal = () => {
    isPatientModalOpen.value = false;
};

const handlePatientCreated = (newPatient) => {
    closePatientModal();

    router.reload({
        only: ['patients'],
        preserveScroll: true,
        onSuccess: (page) => {
            patientsList.value = [...page.props.patients];
            form.patient_id = newPatient.id;
        }
    });
};

const isDoctorModalOpen = ref(false);
const doctorsList = ref([...props.doctors]);

const openDoctorModal = () => {
    isDoctorModalOpen.value = true;
};

const closeDoctorModal = () => {
    isDoctorModalOpen.value = false;
};

const handleDoctorCreated = (newDoctor) => {
    closeDoctorModal();
    router.reload({
        only: ['doctors'],
        preserveScroll: true,
        onSuccess: (page) => {
            doctorsList.value = [...page.props.doctors];
            form.doctor_id = newDoctor.id;
        }
    });
};

watch(() => form.doctor_id, (newDoctorId) => {
    if (newDoctorId) {
        const selectedDoctor = doctorsList.value.find(doctor => 
            doctor.id === (typeof newDoctorId === 'object' ? newDoctorId.id : newDoctorId)
        );
        
        if (selectedDoctor && selectedDoctor.doctor_charge) {
            form.doctor_fee = selectedDoctor.doctor_charge;
        }
    }
}, { deep: true });

const setCurrentDateTime = () => {
    if (!form.appoinment_date) {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        
        form.appoinment_date = `${year}-${month}-${day}T${hours}:${minutes}`;
    }
};

const timeSlotOptions = [
    { value: 'Morning', label: 'Morning (6:00 AM - 12:00 PM)' },
    { value: 'Noon', label: 'Noon (12:00 PM - 2:00 PM)' },
    { value: 'Evening', label: 'Evening (2:00 PM - 8:00 PM)' },
    { value: 'Night', label: 'Night (8:00 PM - 6:00 AM)' }
];

const submit = () => {
    const formData = {
        ...form.data(),
        patient_id: typeof form.patient_id === 'object' ? form.patient_id.id : form.patient_id,
        doctor_id: typeof form.doctor_id === 'object' ? form.doctor_id.id : form.doctor_id,
    };

    const routeName = props.id ? route('backend.appoinment.update', props.id) : route('backend.appoinment.store');
    
    form.transform(data => ({
        ...formData,
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

const goToAppoinmentList = () => {
    router.get(route('backend.appoinment.index'));
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
                        <button @click="goToAppoinmentList"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z">
                                </path>
                            </svg>
                            Appoinment List
                        </button>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="p-4">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4">
                    <div class="col-span-1">
                        <InputLabel for="patient_id" value="Patient"><span class="text-red-500">*</span></InputLabel>
                        <div class="flex space-x-2">
                            <Multiselect v-model="form.patient_id" :options="patientsList" :track-by="'id'"
                                :label="'name'" placeholder="Search and select a patient"
                                class="w-full text-sm h-[30px] rounded-md border border-slate-300"
                                :custom-label="({ name }) => name" :close-on-select="true" :preserve-search="false" />

                            <button type="button" @click="openPatientModal"
                                class="px-2 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                            </button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.patient_id" />
                    </div>

                    <div class="col-span-1">
                        <InputLabel for="doctor_id" value="Doctor"><span class="text-red-500">*</span></InputLabel>
                        <div class="flex space-x-2">
                            <Multiselect v-model="form.doctor_id" :options="doctorsList" :track-by="'id'"
                                :label="'name'" placeholder="Search and select a doctor"
                                class="w-full text-sm h-[30px] rounded-md border border-slate-300"
                                :custom-label="({ name }) => name" :close-on-select="true" :preserve-search="false" />

                            <button type="button" @click="openDoctorModal"
                                class="px-2 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                            </button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.doctor_id" />
                    </div>

                    <div class="col-span-1">
                        <InputLabel for="doctor_fee" value="Doctor Fee" />
                        <input id="doctor_fee"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.doctor_fee" type="number" placeholder="Doctor Fee" readonly />
                        <InputError class="mt-2" :message="form.errors.doctor_fee" />
                        <p class="text-xs text-gray-500 mt-1">Auto-populated based on selected doctor</p>
                    </div>

                    <div class="col-span-1">
                        <InputLabel for="shift" value="Shift"><span class="text-red-500">*</span></InputLabel>
                        <select id="shift" v-model="form.shift"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600">
                            <option value="">Select Shift</option>
                            <option value="Morning">Morning</option>
                            <option value="Evening">Evening</option>
                            <option value="Night">Night</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.shift" />
                    </div>

                    <div class="col-span-1">
                        <InputLabel for="appoinment_date" value="Appointment Date & Time"><span class="text-red-500">*</span>
                        </InputLabel>
                        <input id="appoinment_date"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.appoinment_date" 
                            type="datetime-local" 
                            @click="setCurrentDateTime" />
                        <InputError class="mt-2" :message="form.errors.appoinment_date" />
                    </div>

                    <div class="col-span-1">
                        <InputLabel for="slot" value="Time Slot" />
                        <select id="slot" v-model="form.slot"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600">
                            <option value="">Select Time Slot</option>
                            <option v-for="option in timeSlotOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.slot" />
                    </div>

                    <div class="col-span-1">
                        <InputLabel for="appointment_priority" value="Priority" />
                        <select id="appointment_priority" v-model="form.appointment_priority"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600">
                            <option value="">Select Priority</option>
                            <option value="Normal">Normal</option>
                            <option value="Urgent">Urgent</option>
                            <option value="Very Urgent">Very Urgent</option>
                            <option value="Low">Low</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.appointment_priority" />
                    </div>

                    <div class="col-span-1">
                        <InputLabel for="payment_mode" value="Payment Mode" />
                        <select id="payment_mode" v-model="form.payment_mode"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600">
                            <option value="">Select Payment Method</option>
                            <option value="Cash">Cash</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Transfer to Bank Account">Transfer to Bank Account</option>
                            <option value="Upi">Upi</option>
                            <option value="Online">Online</option>
                            <option value="Other">Other</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.payment_mode" />
                    </div>

                    <div class="col-span-1">
                        <InputLabel for="appoinment_status" value="Appoinment Status" />
                        <select id="appoinment_status" v-model="form.appoinment_status"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600">
                            <option value="">Select Status</option>
                            <option value="Pending">Pending</option>
                            <option value="Approved">Approved</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.appoinment_status" />
                    </div>

                    <div class="col-span-1">
                        <InputLabel for="discount_percentage" value="Discount (%)" />
                        <input id="discount_percentage"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.discount_percentage" type="number" min="0" max="100"
                            placeholder="Discount Percentage" />
                        <InputError class="mt-2" :message="form.errors.discount_percentage" />
                    </div>

                    <div class="col-span-1">
                        <InputLabel for="live_consultant" value="Live Consultant" />
                        <select id="live_consultant" v-model="form.live_consultant"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600">
                            <option value="">Choose Option</option>
                            <option value="Yes">Yes</option>
                            <option value="No">No</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.live_consultant" />
                    </div>

                    <div class="col-span-1 md:col-span-4">
                        <InputLabel for="message" value="Message/Notes" />
                        <textarea id="message" rows="3"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.message" placeholder="Any special notes or message"></textarea>
                        <InputError class="mt-2" :message="form.errors.message" />
                    </div>
                </div>

                <div class="flex items-center justify-end mt-4">
                    <PrimaryButton type="submit" class="ms-4" :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing">
                        {{ ((props.id ?? false) ? 'Update' : 'Create') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>

        <PatientModal :isOpen="isPatientModalOpen" @close="closePatientModal" @patientCreated="handlePatientCreated" />

        <DoctorModal 
            :isOpen="isDoctorModalOpen" 
            @close="closeDoctorModal" 
            @doctorCreated="handleDoctorCreated"
            :designations="props.designations"
            :departments="props.departments"
            :specialists="props.specialists"
        />
    </BackendLayout>
</template>