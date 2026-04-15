<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AlertMessage from '@/Components/AlertMessage.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';
import { showToastIfNoFlash } from '@/responseMessage.js';
import PatientModal from '@/Components/PatientModal.vue';
import SymptomTypeModal from '@/Components/SymptomTypeModal.vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';
import eventBus from '@/eventBus.js';

const APP_TIMEZONE = 'Asia/Dhaka';

const getCurrentDateTimeForInput = () => {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: APP_TIMEZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(new Date());

    const getPart = (type) => parts.find((part) => part.type === type)?.value ?? '';

    return `${getPart('year')}-${getPart('month')}-${getPart('day')}T${getPart('hour')}:${getPart('minute')}`;
};

const props = defineProps(['ipdpatient', 'id', 'patients', 'doctors', 'bedGroups', 'beds', 'symptomTypes']);

const form = useForm({
    patient_id: props.ipdpatient?.patient_id ?? '',
    consultant_doctor_id: props.ipdpatient?.consultant_doctor_id ?? '',

    // Left side fields
    symptom_type: props.ipdpatient?.symptom_type ?? '',
    symptom_title: props.ipdpatient?.symptom_title ?? '',
    symptom_description: props.ipdpatient?.symptom_description ?? '',
    note: props.ipdpatient?.note ?? '',

    // Right side fields
    admission_date: props.ipdpatient?.admission_date ?? '',
    case: props.ipdpatient?.case ?? '',
    tpa: props.ipdpatient?.tpa ?? '',
    casualty: props.ipdpatient?.casualty ?? 'no',
    old_patient: props.ipdpatient?.old_patient ?? 'no',
    credit_limit: props.ipdpatient?.credit_limit ?? '',
    advance_amount: props.ipdpatient?.advance_amount ?? 0,
    reference: props.ipdpatient?.reference ?? '',
    bed_group_id: props.ipdpatient?.bed_group_id ?? '',
    bed_id: props.ipdpatient?.bed_id ?? '',
    live_consultation: props.ipdpatient?.live_consultation ?? 'no',

    _method: props.ipdpatient?.id ? 'put' : 'post',
});

const allBeds = computed(() => props.beds ?? []);
const filteredBeds = ref(allBeds.value);
const unavailableBedIds = ref(new Set());

watch(() => form.bed_group_id, (newBedGroupId) => {
    const beds = allBeds.value;

    if (newBedGroupId) {
        filteredBeds.value = beds.filter(bed => bed.bed_group_id == newBedGroupId);
    } else {
        filteredBeds.value = [];
    }

    form.bed_id = 0;
});

if (props.ipdpatient?.bed_group_id) {
    filteredBeds.value = allBeds.value.filter(
        bed => bed.bed_group_id == props.ipdpatient.bed_group_id
    );
}

const isBedUnavailable = (bedId) => unavailableBedIds.value.has(Number(bedId));

const loadBedAvailability = async () => {
    if (props.id) return;
    try {
        const response = await fetch(route('backend.bed.status.snapshot'));
        if (!response.ok) return;
        const beds = await response.json();
        const blocked = new Set(
            beds.filter((bed) => !bed.is_available).map((bed) => Number(bed.id))
        );
        unavailableBedIds.value = blocked;
    } catch (error) {
        // ignore
    }
};


const submit = () => {
    const routeName = props.id ? route('backend.ipdpatient.update', props.id) : route('backend.ipdpatient.store');
    form.transform(data => ({
        ...data,
        patient_id: data.patient_id?.id || data.patient_id,
        consultant_doctor_id: data.consultant_doctor_id?.id || data.consultant_doctor_id,
        remember: '',
        isDirty: false,
    })).post(routeName, {
        onSuccess: (response) => {
            if (!props.id) form.reset();
            // Always display server flash messages as toast pop-ups.
            try {
                displayResponse(response);
            } catch (e) {
                // ignore
            }
            const billId = response?.props?.flash?.billId;

            if (billId) {
                // Open IPD invoice PDF in a new tab when server provides billId
                window.open(route('backend.download.ipd.invoice', { id: billId }), "_blank");
            }

            // Show toast only when server did not set a flash (to avoid duplicates
            // when the page already renders an inline AlertMessage from flash props).
            showToastIfNoFlash(response);
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
const patientsList = ref([...(props.patients || [])]);
const symptomTypes = ref([...(props.symptomTypes || [])]);
const showSymptomTypeModal = ref(false);
const openPatientModal = () => {
    isPatientModalOpen.value = true;
};
const closePatientModal = () => {
    isPatientModalOpen.value = false;
};

const openSymptomTypeModal = () => {
    showSymptomTypeModal.value = true;
};

const closeSymptomTypeModal = () => {
    showSymptomTypeModal.value = false;
};

const handleSymptomTypeCreated = (createdName) => {
    if (createdName) {
        form.symptom_type = createdName;
    }

    router.reload({
        only: ['symptomTypes'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            symptomTypes.value = [...(page.props.symptomTypes || [])];
        }
    });
};

const normalizedSymptomTypes = computed(() => {
    const types = [...symptomTypes.value];
    const current = String(form.symptom_type || '').trim();

    if (current && !types.some((type) => String(type.name) === current)) {
        types.unshift({ id: `custom-${current}`, name: current });
    }

    return types;
});
const handlePatientCreated = (newPatient) => {
    if (!newPatient) return;

    // Add the new patient to the list immediately
    patientsList.value.push(newPatient);
    form.patient_id = newPatient.id;

    // Reload the patients list
    router.reload({
        only: ['patients'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            patientsList.value = [...(page.props.patients || [])];
        }
    });
};

const patientSearchQuery = ref('');
const filteredPatients = ref([]);
const showPatientDropdown = ref(false);
const selectedPatientIndex = ref(-1);

// Watch for changes in the search query
const filterPatients = () => {
    if (patientSearchQuery.value.length > 0) {

        filteredPatients.value = props.patients.filter(patient =>
            patient.name.toLowerCase().includes(patientSearchQuery.value.toLowerCase()) ||
            (patient.phone && patient.phone.includes(patientSearchQuery.value))
        );
    } else {
        filteredPatients.value = props.patients.slice(0, 10);
    }

    selectedPatientIndex.value = -1;
};

const shouldShowNoResults = computed(() => {
    return patientSearchQuery.value.length > 0 && filteredPatients.value.length === 0;
});

const selectPatient = (patient) => {
    form.patient_id = patient.id;
    patientSearchQuery.value = patient.name;
    showPatientDropdown.value = false;
    selectedPatientIndex.value = -1;
};

const handleBlur = () => {
    setTimeout(() => {
        showPatientDropdown.value = false;
        selectedPatientIndex.value = -1;
    }, 200);
};

// Initialize with first few patients
onMounted(() => {
    filteredPatients.value = props.patients.slice(0, 10);
    loadBedAvailability();
});

const handleBedSelected = async (bed) => {
    if (props.id) return;
    if (!bed?.id || !bed?.bed_group_id) return;
    if (isBedUnavailable(bed.id)) return;

    form.bed_group_id = bed.bed_group_id;
    await nextTick();
    form.bed_id = bed.id;
};

const handleBedSelectedEvent = (event) => {
    const bed = event?.detail;
    handleBedSelected(bed);
};

onMounted(() => {
    eventBus.on('bedSelected', handleBedSelected);
    window.addEventListener('ipd-bed-selected', handleBedSelectedEvent);
    window.__ipdBedSelectReady = true;
});

onMounted(async () => {
    if (props.id) return;
    const params = new URLSearchParams(window.location.search);
    const bedGroupId = params.get('bed_group_id');
    const bedId = params.get('bed_id');

    if (bedGroupId && bedId && !isBedUnavailable(bedId)) {
        form.bed_group_id = bedGroupId;
        await nextTick();
        form.bed_id = bedId;
    }
});

onBeforeUnmount(() => {
    eventBus.off('bedSelected', handleBedSelected);
    window.removeEventListener('ipd-bed-selected', handleBedSelectedEvent);
    window.__ipdBedSelectReady = false;
});

// Keyboard navigation handler
const handleKeyDown = (event) => {
    if (!showPatientDropdown.value || filteredPatients.value.length === 0) return;

    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            selectedPatientIndex.value = selectedPatientIndex.value < filteredPatients.value.length - 1
                ? selectedPatientIndex.value + 1
                : 0;
            break;

        case 'ArrowUp':
            event.preventDefault();
            selectedPatientIndex.value = selectedPatientIndex.value > 0
                ? selectedPatientIndex.value - 1
                : filteredPatients.value.length - 1;
            break;

        case 'Enter':
            event.preventDefault();
            if (selectedPatientIndex.value >= 0 && selectedPatientIndex.value < filteredPatients.value.length) {
                selectPatient(filteredPatients.value[selectedPatientIndex.value]);
            } else if (filteredPatients.value.length === 1) {
                selectPatient(filteredPatients.value[0]);
            }
            break;

        case 'Escape':
            event.preventDefault();
            showPatientDropdown.value = false;
            selectedPatientIndex.value = -1;
            break;
    }
};

watch(patientSearchQuery, (newValue) => {
    if (newValue === '') {
        form.patient_id = '';
    }
});

//select auto date time on click
const handleAdmissionDateFocus = (event) => {
    if (!form.admission_date) {
        form.admission_date = getCurrentDateTimeForInput();
    }
};

const goToIpdList = () => {
    router.visit(route('backend.ipdpatient.index'));
};

const handlePatientSelect = (selectedPatient) => {
    form.patient_id = selectedPatient ? selectedPatient.id : '';
};

const handleDoctorSelect = (selectedDoctor) => {
    form.consultant_doctor_id = selectedDoctor ? selectedDoctor.id : '';
};

</script>

<template>
    <!-- Patient Modal -->
    <PatientModal :isOpen="isPatientModalOpen" :tpas="props.tpas" @close="closePatientModal"
        @patientCreated="handlePatientCreated" />
    <SymptomTypeModal :isOpen="showSymptomTypeModal" @close="closeSymptomTypeModal"
        @symptomTypeCreated="handleSymptomTypeCreated" />

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
                                <Multiselect :modelValue="patientsList.find(p => p.id === form.patient_id)"
                                    @update:modelValue="handlePatientSelect" :options="patientsList" :track-by="'id'"
                                    :label="'name'" placeholder="Search and select a patient"
                                    class="w-full text-sm rounded-md border border-slate-300" />
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
                            <button @click="goToIpdList"
                                class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2">
                                <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z">
                                    </path>
                                </svg>
                                Ipd Patient List
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="p-4">
                <AlertMessage />

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <!-- Left Column -->
                    <div class="space-y-3">
                        <!-- Symptoms Type and Title in same row -->
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <InputLabel for="symptom_type" value="Symptoms Type" />
                                <div class="flex items-center space-x-2">
                                    <select id="symptom_type" v-model="form.symptom_type"
                                        class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                                        <option value="">Select</option>
                                        <option v-for="type in normalizedSymptomTypes" :key="type.id" :value="type.name">
                                            {{ type.name }}
                                        </option>
                                    </select>
                                    <button type="button" @click="openSymptomTypeModal"
                                        class="flex-shrink-0 inline-flex items-center justify-center w-9 h-9 text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                        title="Add Symptoms Type">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                    </button>
                                </div>
                                <InputError class="mt-1" :message="form.errors.symptom_type" />
                            </div>

                            <div>
                                <InputLabel for="symptom_title" value="Symptoms Title" />
                                <input id="symptom_title" v-model="form.symptom_title" type="text"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                    placeholder="Enter symptom title" />
                                <InputError class="mt-1" :message="form.errors.symptom_title" />
                            </div>
                        </div>

                        <!-- Symptoms Description -->
                        <div>
                            <InputLabel for="symptom_description" value="Symptoms Description" />
                            <textarea id="symptom_description" v-model="form.symptom_description" rows="2"
                                class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                placeholder="Describe symptoms in detail"></textarea>
                            <InputError class="mt-1" :message="form.errors.symptom_description" />
                        </div>

                        <!-- Note -->
                        <div>
                            <InputLabel for="note" value="Note" />
                            <textarea id="note" v-model="form.note" rows="2"
                                class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                placeholder="Additional notes"></textarea>
                            <InputError class="mt-1" :message="form.errors.note" />
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-3">
                        <!-- Admission Date & Case -->
                        <div class="w-full grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div class="col-span-2">
                                <InputLabel for="admission_date" value="Admission Date" class="required" />
                                <input id="admission_date" v-model="form.admission_date" type="datetime-local"
                                    @focus="handleAdmissionDateFocus"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                    required />
                                <InputError class="mt-1" :message="form.errors.admission_date" />
                            </div>

                        </div>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <InputLabel for="case" value="Case" />
                                <select id="case" v-model="form.case"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                                    <option value="">Select</option>
                                    <option value="new">New Case</option>
                                    <option value="followup">Follow-up</option>
                                    <option value="emergency">Emergency</option>
                                </select>
                                <InputError class="mt-1" :message="form.errors.case" />
                            </div>
                            <div>
                                <InputLabel for="tpa" value="Tpa" />
                                <input id="tpa" v-model="form.tpa" type="text"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                    placeholder="tpa" />
                                <InputError class="mt-1" :message="form.errors.tpa" />
                            </div>
                        </div>

                        <!-- Casualty & Old Patient -->
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <InputLabel for="casualty" value="Casualty" />
                                <select id="casualty" v-model="form.casualty"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                                    <option value="no">No</option>
                                    <option value="yes">Yes</option>
                                </select>
                                <InputError class="mt-1" :message="form.errors.casualty" />
                            </div>
                            <div>
                                <InputLabel for="old_patient" value="Old Patient" />
                                <select id="old_patient" v-model="form.old_patient"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                                    <option value="no">No</option>
                                    <option value="yes">Yes</option>
                                </select>
                                <InputError class="mt-1" :message="form.errors.old_patient" />
                            </div>
                        </div>

                        <!-- Reference & Consultant Doctor -->
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <InputLabel for="credit_limit" value="Credit Limit" />
                                <input id="credit_limit" v-model="form.credit_limit" type="text"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                    placeholder="Credit Limit" />
                                <InputError class="mt-1" :message="form.errors.credit_limit" />
                            </div>
                            <div>
                                <InputLabel for="advance_amount" value="Advance Amount (Tk)" />
                                <input id="advance_amount" v-model="form.advance_amount" type="number" step="0.01"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300" placeholder="0.00" />
                                <InputError class="mt-1" :message="form.errors.advance_amount" />
                            </div>
                            <div>
                                <InputLabel for="reference" value="Reference" />
                                <input id="reference" v-model="form.reference" type="text"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                    placeholder="Reference" />
                                <InputError class="mt-1" :message="form.errors.reference" />
                            </div>

                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <InputLabel for="consultant_doctor_id" value="Consultant Doctor" class="required" />
                                <Multiselect :modelValue="props.doctors.find(d => d.id === form.consultant_doctor_id)"
                                    @update:modelValue="handleDoctorSelect" :options="doctors" :track-by="'id'"
                                    :label="'name'" placeholder="Search and select a doctor"
                                    class="w-full text-sm rounded-md border border-slate-300" />
                                <InputError class="mt-1" :message="form.errors.consultant_doctor_id" />
                            </div>

                            <div>
                                <InputLabel for="bed_group_id" value="Bed Group" class="required" />
                                <select id="bed_group_id" v-model="form.bed_group_id"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                    required>
                                    <option value="">Select Group</option>
                                    <option v-for="data in bedGroups" :key="data.id" :value="data.id">{{ data.name }}
                                    </option>
                                </select>
                                <InputError class="mt-1" :message="form.errors.bed_group_id" />
                            </div>
                        </div>

                        <!-- Live Consultation & Paid Amount -->
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <InputLabel for="bed_id" value="Bed Number" class="required" />
                                <select id="bed_id" v-model="form.bed_id"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                    required :disabled="!form.bed_group_id">
                                    <option value="">Select Bed</option>
                                    <option v-for="bed in filteredBeds" :key="bed.id" :value="bed.id"
                                        :disabled="isBedUnavailable(bed.id)">
                                        {{ bed.name }}{{ isBedUnavailable(bed.id) ? ' (Occupied)' : '' }}
                                    </option>
                                </select>
                                <InputError class="mt-1" :message="form.errors.bed_id" />
                            </div>
                            <div>
                                <InputLabel for="live_consultation" value="Live Consultation" />
                                <select id="live_consultation" v-model="form.live_consultation"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                                    <option value="no">No</option>
                                    <option value="yes">Yes</option>
                                </select>
                                <InputError class="mt-1" :message="form.errors.live_consultation" />
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