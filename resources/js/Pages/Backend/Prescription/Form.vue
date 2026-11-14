<script setup>
import { ref, nextTick, watch, onMounted, onBeforeUnmount } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PatientModal from '@/Components/PatientModal.vue';
import { router, useForm } from '@inertiajs/vue3';
import { displayResponse, warningMessage } from '@/responseMessage.js';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

const props = defineProps({
    prescription: Object,
    prescriptionSettings: Object,
    id: [Number, String],
    patients: Array,
    doctors: Array,
    hasPharmacyModule: { type: Boolean, default: false },
    canCreate: { type: Boolean, default: false },
});

const patients = ref([...(props.patients ?? [])]);
const doctorOptions = ref([...(props.doctors ?? [])]);
const isPatientModalOpen = ref(false);
const saveError = ref('');
const medicineSuggestions = ref([]);
const testSuggestions = ref([]);
const activeMedicineRowIndex = ref(null);
const activeTestRowIndex = ref(null);
const medicineInputs = ref([]);
const testInputs = ref([]);
const medicineSuggestionElements = ref([]);
const clinicalTextareas = ref([]);
const medicineHighlightedIndex = ref(0);
const testHighlightedIndex = ref(0);
const testSuggestionElements = ref([]);
const medicineDropdownStyle = ref({});
const medicineFieldSuggestions = ref([]);
let medicineSearchTimer = null;
let testSearchTimer = null;
let medicineSearchRequest = 0;
let testSearchRequest = 0;

watch(() => props.doctors, (doctors) => {
    doctorOptions.value = [...(doctors ?? [])];
});

const selectedPatient = ref(
    props.prescription?.patient_id
        ? (props.patients ?? []).find((p) => Number(p.id) === Number(props.prescription.patient_id)) ?? null
        : null
);

const savedTemperature = String(props.prescription?.temperature ?? '');
const savedTemperatureValue = savedTemperature.match(/-?\d+(?:\.\d+)?/)?.[0] ?? '';
const temperatureUnit = ref(/(?:°\s*F|fahrenheit)/i.test(savedTemperature) ? 'F' : 'C');

const form = useForm({
    patient_id: props.prescription?.patient_id ?? '',
    doctor_id: props.prescription?.doctor_id ?? '',
    complaints: props.prescription?.complaints ?? '',
    examinations: props.prescription?.examinations ?? '',
    diagnosis: props.prescription?.diagnosis ?? '',
    advice: props.prescription?.advice ?? '',
    follow_up_date: props.prescription?.follow_up_date ?? '',
    nibp: props.prescription?.nibp ?? '',
    weight: props.prescription?.weight ?? '',
    pulse: props.prescription?.pulse ?? '',
    temperature: savedTemperatureValue,
    medicines: props.prescription?.medicines?.length
        ? props.prescription.medicines.map((item) => ({
            medicine_name: item.medicine_name ?? '',
            generic_name: item.generic_name ?? '',
            dose: item.dose ?? '',
            frequency: item.frequency ?? '',
            duration: item.duration ?? '',
            instructions: item.instructions ?? '',
        }))
        : [{ medicine_name: '', generic_name: '', dose: '', frequency: '', duration: '', instructions: '' }],
    tests: props.prescription?.tests?.length
        ? props.prescription.tests.map((test) => test.test_name ?? '')
        : [''],
    _method: props.id ? 'put' : 'post',
});

form.transform((data) => ({
    ...data,
    temperature: data.temperature ? `${data.temperature} °${temperatureUnit.value}` : '',
}));

const changeTemperatureUnit = (event) => {
    const nextUnit = event.target.value;
    const temperature = Number.parseFloat(form.temperature);
    if (Number.isFinite(temperature) && nextUnit !== temperatureUnit.value) {
        form.temperature = String(Number(
            nextUnit === 'F'
                ? (temperature * 9) / 5 + 32
                : ((temperature - 32) * 5) / 9
        ).toFixed(1));
    }
    temperatureUnit.value = nextUnit;
};

const resizeClinicalTextarea = (element) => {
    if (!element) return;
    element.style.height = 'auto';
    element.style.height = `${element.scrollHeight}px`;
};

const goBack = () => router.visit(route('backend.prescription.index'));
const startNewPrescription = () => {
    window.location.assign(route('backend.prescription.create'));
};

const handlePatientSelection = (patient) => {
    form.patient_id = patient?.id ?? '';
};

const handlePatientCreated = (patient) => {
    if (!patient) return;
    patients.value = [patient, ...patients.value.filter((item) => Number(item.id) !== Number(patient.id))];
    selectedPatient.value = patient;
    form.patient_id = patient.id;
};

const addMedicineRow = async () => {
    form.medicines.push({ medicine_name: '', generic_name: '', dose: '', frequency: '', duration: '', instructions: '' });
    await nextTick();
    medicineInputs.value[form.medicines.length - 1]?.focus();
};

const removeMedicineRow = (index) => {
    if (form.medicines.length === 1) {
        form.medicines[0] = { medicine_name: '', generic_name: '', dose: '', frequency: '', duration: '', instructions: '' };
        medicineFieldSuggestions.value[0] = null;
        return;
    }
    form.medicines.splice(index, 1);
    medicineFieldSuggestions.value.splice(index, 1);
};

const normalizeMedicineName = (name) => String(name ?? '').trim().replace(/\s+/g, ' ').toLocaleLowerCase();

const findDuplicateMedicine = (name, excludedIndex) => {
    const normalizedName = normalizeMedicineName(name);
    if (!normalizedName) return -1;
    return form.medicines.findIndex((medicine, index) =>
        index !== excludedIndex && normalizeMedicineName(medicine.medicine_name) === normalizedName
    );
};

const warnDuplicateMedicine = (duplicateIndex) => {
    warningMessage(`This medicine is already added in row ${duplicateIndex + 1}.`);
};

const handleMedicineBlur = (index) => {
    const duplicateIndex = findDuplicateMedicine(form.medicines[index]?.medicine_name, index);
    if (duplicateIndex < 0) return;

    warnDuplicateMedicine(duplicateIndex);
    form.medicines[index].medicine_name = '';
};

const addTestRow = () => form.tests.push('');
const removeTestRow = (index) => {
    if (form.tests.length === 1) {
        form.tests[0] = '';
        return;
    }
    form.tests.splice(index, 1);
};

const searchMedicine = (query, rowIndex) => {
    if (medicineSearchTimer) clearTimeout(medicineSearchTimer);
    const term = String(query ?? '').trim();
    const requestId = ++medicineSearchRequest;
    activeMedicineRowIndex.value = rowIndex;
    medicineSuggestions.value = [];
    medicineHighlightedIndex.value = 0;
    if (term.length < 2) {
        return;
    }
    medicineSearchTimer = setTimeout(async () => {
        try {
            const response = await fetch(route('backend.prescription.medicines.suggest', { q: term }), {
                headers: { Accept: 'application/json' },
            });
            const data = await response.json();
            if (requestId !== medicineSearchRequest) return;
            medicineSuggestions.value = Array.isArray(data?.results) ? data.results : [];
            const exactMatch = medicineSuggestions.value.find(
                (item) => normalizeMedicineName(item?.name ?? item?.medicine_name) === normalizeMedicineName(term)
            );
            if (exactMatch && form.medicines[rowIndex]) {
                form.medicines[rowIndex].generic_name = String(exactMatch.generic_name ?? '');
            }
            await nextTick();
            updateMedicineDropdownPosition();
        } catch (e) {
            if (requestId === medicineSearchRequest) medicineSuggestions.value = [];
        }
    }, 250);
};

const handleMedicineInput = (query, rowIndex) => {
    form.medicines[rowIndex].generic_name = '';
    medicineFieldSuggestions.value[rowIndex] = null;
    searchMedicine(query, rowIndex);
};

const updateMedicineDropdownPosition = () => {
    const index = activeMedicineRowIndex.value;
    const input = index === null ? null : medicineInputs.value[index];
    if (!input || medicineSuggestions.value.length === 0) return;

    const rect = input.getBoundingClientRect();
    const height = Math.min(160, medicineSuggestions.value.length * 28 + 2);
    medicineDropdownStyle.value = {
        left: `${rect.left}px`,
        top: `${Math.max(8, rect.top - height - 4)}px`,
        width: `${rect.width}px`,
    };
};

const handleMedicineSuggestionKeydown = (event, index) => {
    if (activeMedicineRowIndex.value !== index || medicineSuggestions.value.length === 0) return;

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        const direction = event.key === 'ArrowDown' ? 1 : -1;
        medicineHighlightedIndex.value = (
            medicineHighlightedIndex.value + direction + medicineSuggestions.value.length
        ) % medicineSuggestions.value.length;
        nextTick(() => {
            medicineSuggestionElements.value[medicineHighlightedIndex.value]?.scrollIntoView({ block: 'nearest' });
        });
    } else if (event.key === 'Escape') {
        medicineSuggestions.value = [];
        activeMedicineRowIndex.value = null;
    }
};

const medicineNameParts = (item) => {
    const name = String(item?.name ?? item?.medicine_name ?? '');
    const query = String(form.medicines[activeMedicineRowIndex.value]?.medicine_name ?? '').trim();
    const matchIndex = name.toLocaleLowerCase().indexOf(query.toLocaleLowerCase());
    if (!query || matchIndex < 0) return [{ text: name, matched: false }];

    return [
        { text: name.slice(0, matchIndex), matched: false },
        { text: name.slice(matchIndex, matchIndex + query.length), matched: true },
        { text: name.slice(matchIndex + query.length), matched: false },
    ];
};

const selectMedicineSuggestion = async (index, item) => {
    const row = form.medicines[index];
    if (!row) return;
    const medicineName = item?.name ?? item?.medicine_name ?? '';
    const duplicateIndex = findDuplicateMedicine(medicineName, index);
    if (duplicateIndex >= 0) {
        warnDuplicateMedicine(duplicateIndex);
        medicineInputs.value[index]?.focus();
        return;
    }
    medicineSearchRequest++;
    if (medicineSearchTimer) clearTimeout(medicineSearchTimer);
    row.medicine_name = medicineName;
    row.generic_name = item?.generic_name ?? '';
    row.dose = item?.is_standard ? (item.dose ?? '') : (item?.dose ?? row.dose);
    row.frequency = item?.is_standard ? (item.frequency ?? '') : (item?.frequency ?? row.frequency);
    row.duration = item?.is_standard ? (item.duration ?? '') : (item?.duration ?? row.duration);
    row.instructions = item?.is_standard ? (item.instructions ?? '') : (item?.instructions ?? row.instructions);
    medicineFieldSuggestions.value[index] = item?.is_standard ? {
        dose: item?.dose ? [item.dose] : [],
        frequency: item?.frequency ? [item.frequency] : [],
        duration: item?.duration ? [item.duration] : [],
        instructions: item?.instructions ? [item.instructions] : [],
    } : null;
    medicineSuggestions.value = [];
    activeMedicineRowIndex.value = null;
    if (index + 1 >= form.medicines.length) {
        await addMedicineRow();
    } else {
        await nextTick();
        medicineInputs.value[index + 1]?.focus();
    }
};

const searchTest = (query, rowIndex) => {
    if (testSearchTimer) clearTimeout(testSearchTimer);
    const requestId = ++testSearchRequest;
    const term = String(query ?? '').trim();
    activeTestRowIndex.value = rowIndex;
    testSuggestions.value = [];
    testHighlightedIndex.value = 0;
    if (term.length < 2) {
        return;
    }
    testSearchTimer = setTimeout(async () => {
        try {
            const response = await fetch(route('backend.prescription.tests.suggest', { q: term }), {
                headers: { Accept: 'application/json' },
            });
            const data = await response.json();
            if (requestId !== testSearchRequest) return;
            testSuggestions.value = Array.isArray(data) ? data : [];
            await nextTick();
        } catch (e) {
            if (requestId === testSearchRequest) testSuggestions.value = [];
        }
    }, 250);
};

const testSuggestionName = (item) => String(item?.test_name ?? item?.name ?? item ?? '');

const testNameParts = (item) => {
    const name = testSuggestionName(item);
    const query = String(form.tests[activeTestRowIndex.value] ?? '').trim();
    const matchIndex = name.toLocaleLowerCase().indexOf(query.toLocaleLowerCase());
    if (!query || matchIndex < 0) return [{ text: name, matched: false }];

    return [
        { text: name.slice(0, matchIndex), matched: false },
        { text: name.slice(matchIndex, matchIndex + query.length), matched: true },
        { text: name.slice(matchIndex + query.length), matched: false },
    ];
};

const selectTestSuggestion = (index, item) => {
    testSearchRequest++;
    if (testSearchTimer) clearTimeout(testSearchTimer);
    form.tests[index] = testSuggestionName(item);
    testSuggestions.value = [];
    activeTestRowIndex.value = null;
    testHighlightedIndex.value = 0;
    if (index + 1 >= form.tests.length) addTestRow();
    nextTick(() => testInputs.value[index + 1]?.focus());
};

const handleTestSuggestionKeydown = (event, index) => {
    if (activeTestRowIndex.value !== index || testSuggestions.value.length === 0) {
        if (event.key === 'Enter') event.preventDefault();
        return;
    }

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        const direction = event.key === 'ArrowDown' ? 1 : -1;
        testHighlightedIndex.value = (
            testHighlightedIndex.value + direction + testSuggestions.value.length
        ) % testSuggestions.value.length;
        nextTick(() => {
            testSuggestionElements.value[testHighlightedIndex.value]?.scrollIntoView({ block: 'nearest' });
        });
    } else if (event.key === 'Enter') {
        event.preventDefault();
        selectTestSuggestion(index, testSuggestions.value[testHighlightedIndex.value]);
    } else if (event.key === 'Escape') {
        testSearchRequest++;
        testSuggestions.value = [];
        activeTestRowIndex.value = null;
    }
};

const handleMedicineEnter = (event, index) => {
    event.preventDefault();
    if (activeMedicineRowIndex.value === index && medicineSuggestions.value.length) {
        selectMedicineSuggestion(index, medicineSuggestions.value[medicineHighlightedIndex.value]);
        return;
    }
    const duplicateIndex = findDuplicateMedicine(form.medicines[index]?.medicine_name, index);
    if (duplicateIndex >= 0) {
        warnDuplicateMedicine(duplicateIndex);
        medicineInputs.value[index]?.focus();
        return;
    }
    if (index + 1 >= form.medicines.length) addMedicineRow();
    nextTick(() => medicineInputs.value[index + 1]?.focus());
};

onMounted(() => {
    window.addEventListener('scroll', updateMedicineDropdownPosition, true);
    window.addEventListener('resize', updateMedicineDropdownPosition);
    nextTick(() => clinicalTextareas.value.forEach(resizeClinicalTextarea));
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', updateMedicineDropdownPosition, true);
    window.removeEventListener('resize', updateMedicineDropdownPosition);
    if (medicineSearchTimer) clearTimeout(medicineSearchTimer);
});

const refreshDoctors = () => router.reload({
    only: ['doctors'],
    preserveScroll: true,
    onSuccess: (page) => {
        doctorOptions.value = [...(page.props.doctors ?? [])];
    },
});

const savePrescription = () => {
    saveError.value = '';
    const duplicateMedicineIndex = form.medicines.findIndex((medicine, index) =>
        findDuplicateMedicine(medicine.medicine_name, index) >= 0
    );
    if (duplicateMedicineIndex >= 0) {
        const duplicateIndex = findDuplicateMedicine(form.medicines[duplicateMedicineIndex].medicine_name, duplicateMedicineIndex);
        warnDuplicateMedicine(duplicateIndex);
        medicineInputs.value[duplicateMedicineIndex]?.focus();
        return;
    }
    const routeName = props.id
        ? route('backend.prescription.update', props.id)
        : route('backend.prescription.store');

    form.post(routeName, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: (response) => displayResponse(response),
        onError: (errors) => {
            const firstError = Object.values(errors ?? {})[0];
            saveError.value = firstError || 'Failed to save prescription.';
            const medicineError = Object.entries(errors ?? {}).find(([key]) =>
                key === 'medicines' || key.startsWith('medicines.')
            );
            if (medicineError) warningMessage(medicineError[1]);
        },
    });
};

const printPrescription = () => {
    if (!props.id) return;
    window.open(route('backend.prescription.print', props.id), '_blank');
};

const downloadPdf = () => {
    if (!props.id) return;
    window.open(route('backend.prescription.pdf', props.id), '_blank');
};

const sendToPharmacy = () => {
    if (!props.id || !props.hasPharmacyModule) return;
    router.visit(route('backend.pharmacybill.create', { prescription_id: props.id }));
};
</script>

<template>
    <BackendLayout>
        <PatientModal :isOpen="isPatientModalOpen" @close="isPatientModalOpen = false" @patientCreated="handlePatientCreated" />

        <div class="w-full p-2 bg-white rounded-md dark:bg-slate-900">
            <div class="mb-3 flex items-center justify-between">
                <h1 class="text-xl font-bold dark:text-white">{{ $page.props.pageTitle }}</h1>
                <div class="flex items-center gap-2">
                    <button type="button" class="px-3 py-1.5 text-xs bg-gray-500 text-white rounded" @click="goBack">Back</button>
                    <button v-if="props.canCreate" type="button" class="px-3 py-1.5 text-xs bg-teal-600 text-white rounded" @click="startNewPrescription">Prescription Add</button>
                    <button v-if="props.id" type="button" class="px-3 py-1.5 text-xs bg-blue-600 text-white rounded" @click="downloadPdf">Download PDF</button>
                    <button v-if="props.id" type="button" class="px-3 py-1.5 text-xs bg-emerald-600 text-white rounded" @click="printPrescription">Print</button>
                    <button v-if="props.id && props.hasPharmacyModule" type="button" class="px-3 py-1.5 text-xs bg-indigo-600 text-white rounded" @click="sendToPharmacy">Send to Pharmacy</button>
                </div>
            </div>

            <div class="border border-gray-200 rounded-md p-3 dark:border-gray-700">
                <div v-if="$page.props.flash?.errorMessage" class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                    {{ $page.props.flash.errorMessage }}
                </div>
                <div v-if="saveError" class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">{{ saveError }}</div>

                <div class="mb-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <InputLabel value="Patient" class="text-xs mb-1" />
                        <div class="flex gap-2">
                            <div class="flex-1">
                                <Multiselect
                                    v-model="selectedPatient"
                                    :options="patients"
                                    track-by="id"
                                    label="name"
                                    placeholder="Search and select a patient"
                                    class="w-full text-sm"
                                    @select="handlePatientSelection"
                                />
                            </div>
                            <button type="button" class="px-3 py-2 text-xs text-white bg-blue-600 rounded" @click="isPatientModalOpen = true">+ New</button>
                        </div>
                        <InputError class="mt-1" :message="form.errors.patient_id" />
                    </div>
                    <div>
                        <div class="mb-1 flex items-center justify-between">
                            <InputLabel value="Doctor" class="text-xs" />
                            <div class="flex items-center gap-2 text-[11px]">
                                <a :href="route('backend.admin.create')" target="_blank" rel="noopener" class="font-medium text-blue-600 hover:underline">+ Add Doctor</a>
                                <button type="button" class="text-gray-600 hover:text-blue-600" title="Refresh doctors" @click="refreshDoctors">Refresh</button>
                            </div>
                        </div>
                        <select v-model="form.doctor_id" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200">
                            <option value="">Select doctor</option>
                            <option v-for="doctor in doctorOptions" :key="doctor.id" :value="doctor.id">{{ doctor.name }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.doctor_id" />
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-6">
                    <div>
                        <InputLabel value="NIBP" class="text-xs mb-1" />
                        <input v-model="form.nibp" type="text" maxlength="255" class="w-full px-2 py-1.5 border rounded text-xs dark:bg-slate-700" placeholder="e.g. 120/80 mmHg" />
                    </div>
                    <div>
                        <InputLabel value="Weight" class="text-xs mb-1" />
                        <input v-model="form.weight" type="text" maxlength="255" class="w-full px-2 py-1.5 border rounded text-xs dark:bg-slate-700" placeholder="e.g. 65 kg" />
                    </div>
                    <div>
                        <InputLabel value="Pulse" class="text-xs mb-1" />
                        <input v-model="form.pulse" type="text" maxlength="255" class="w-full px-2 py-1.5 border rounded text-xs dark:bg-slate-700" placeholder="e.g. 72 bpm" />
                    </div>
                    <div>
                        <InputLabel value="Temperature" class="text-xs mb-1" />
                        <div class="flex">
                            <input v-model="form.temperature" type="number" step="0.1" class="min-w-0 w-full px-2 py-1.5 border rounded-l text-xs dark:bg-slate-700" :placeholder="temperatureUnit === 'C' ? 'e.g. 37' : 'e.g. 98.6'" />
                            <select :value="temperatureUnit" class="border border-l-0 rounded-r px-2 text-xs dark:bg-slate-700" aria-label="Temperature unit" @change="changeTemperatureUnit">
                                <option value="C">°C</option>
                                <option value="F">°F</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <InputLabel value="Complaints" class="text-xs mb-1" />
                        <textarea :ref="el => clinicalTextareas[1] = el" v-model="form.complaints" rows="1" class="w-full resize-none overflow-hidden px-2 py-1.5 border rounded text-xs dark:bg-slate-700" placeholder="Chief complaints" @input="resizeClinicalTextarea($event.target)"></textarea>
                    </div>
                    <div>
                        <InputLabel value="Diagnosis" class="text-xs mb-1" />
                        <textarea :ref="el => clinicalTextareas[2] = el" v-model="form.diagnosis" rows="1" class="w-full resize-none overflow-hidden px-2 py-1.5 border rounded text-xs dark:bg-slate-700" placeholder="Diagnosis" @input="resizeClinicalTextarea($event.target)"></textarea>
                    </div>
                </div>

                <div class="mt-3">
                    <InputLabel value="On Examinations" class="text-xs mb-1" />
                    <textarea :ref="el => clinicalTextareas[3] = el" v-model="form.examinations" rows="1" class="w-full resize-none overflow-hidden px-2 py-1.5 border rounded text-xs dark:bg-slate-700" placeholder="Enter examination findings on separate lines" @input="resizeClinicalTextarea($event.target)"></textarea>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <div class="mb-2 text-xs font-semibold">Medicines</div>
                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr class="bg-gray-100 dark:bg-slate-800">
                                <th class="px-2 py-2 border">Medicine</th>
                                <th class="px-2 py-2 border">Dose</th>
                                <th class="px-2 py-2 border">Frequency</th>
                                <th class="px-2 py-2 border">Duration</th>
                                <th class="px-2 py-2 border">Instructions</th>
                                <th class="px-2 py-2 border text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in form.medicines" :key="index">
                                <td class="px-2 py-2 border relative">
                                    <input
                                        :ref="el => medicineInputs[index] = el"
                                        v-model="row.medicine_name"
                                        type="text"
                                        class="w-full px-2 py-1 border rounded text-xs dark:bg-slate-700"
                                        placeholder="Medicine name"
                                        autocomplete="off"
                                        @input="handleMedicineInput(row.medicine_name, index)"
                                        @focus="searchMedicine(row.medicine_name, index)"
                                        @blur="handleMedicineBlur(index)"
                                        @keydown.enter="handleMedicineEnter($event, index)"
                                        @keydown="handleMedicineSuggestionKeydown($event, index)"
                                    />
                                    <div v-if="row.generic_name" class="mt-0.5 text-[10px] text-slate-500">{{ row.generic_name }}</div>
                                </td>
                                <td class="px-2 py-2 border">
                                    <input v-model="row.dose" :list="`medicine-dose-options-${index}`" type="text" class="w-full px-2 py-1 border rounded text-xs dark:bg-slate-700" placeholder="Search or enter dose" />
                                    <datalist :id="`medicine-dose-options-${index}`"><option v-for="value in medicineFieldSuggestions[index]?.dose ?? []" :key="value" :value="value" /></datalist>
                                </td>
                                <td class="px-2 py-2 border">
                                    <input v-model="row.frequency" :list="`medicine-frequency-options-${index}`" type="text" class="w-full px-2 py-1 border rounded text-xs dark:bg-slate-700" placeholder="Search or enter frequency" />
                                    <datalist :id="`medicine-frequency-options-${index}`"><option v-for="value in medicineFieldSuggestions[index]?.frequency ?? []" :key="value" :value="value" /></datalist>
                                </td>
                                <td class="px-2 py-2 border">
                                    <input v-model="row.duration" :list="`medicine-duration-options-${index}`" type="text" class="w-full px-2 py-1 border rounded text-xs dark:bg-slate-700" placeholder="Search or enter duration" />
                                    <datalist :id="`medicine-duration-options-${index}`"><option v-for="value in medicineFieldSuggestions[index]?.duration ?? []" :key="value" :value="value" /></datalist>
                                </td>
                                <td class="px-2 py-2 border">
                                    <input v-model="row.instructions" :list="`medicine-instruction-options-${index}`" type="text" class="w-full px-2 py-1 border rounded text-xs dark:bg-slate-700" placeholder="Search or enter instructions" />
                                    <datalist :id="`medicine-instruction-options-${index}`"><option v-for="value in medicineFieldSuggestions[index]?.instructions ?? []" :key="value" :value="value" /></datalist>
                                </td>
                                <td class="px-2 py-2 border text-center">
                                    <button type="button" class="px-2 py-1 text-xs bg-red-500 text-white rounded" @click="removeMedicineRow(index)">Remove</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" class="mt-2 px-3 py-1.5 text-xs bg-gray-600 text-white rounded" @click="addMedicineRow">Add Medicine</button>
                    <InputError class="mt-1" :message="form.errors.medicines" />
                </div>

                <Teleport to="body">
                    <div
                        v-if="activeMedicineRowIndex !== null && medicineSuggestions.length"
                        :style="medicineDropdownStyle"
                        class="fixed z-[9999] max-h-40 overflow-auto rounded border border-gray-300 bg-white text-xs shadow-lg"
                    >
                        <div
                            v-for="(item, sIndex) in medicineSuggestions"
                            :key="sIndex"
                            :ref="el => medicineSuggestionElements[sIndex] = el"
                            class="cursor-pointer px-2 py-1.5"
                            :class="sIndex === medicineHighlightedIndex ? 'bg-blue-100 text-blue-900' : 'hover:bg-blue-50'"
                            @mousedown.prevent="selectMedicineSuggestion(activeMedicineRowIndex, item)"
                        >
                            <template v-for="(part, partIndex) in medicineNameParts(item)" :key="partIndex"><mark v-if="part.matched" class="bg-yellow-200 font-semibold text-inherit">{{ part.text }}</mark><span v-else>{{ part.text }}</span></template>
                            <span v-if="item.generic_name" class="ml-1 text-emerald-700">({{ item.generic_name }})</span>
                        </div>
                    </div>
                </Teleport>

                <div class="mt-4 border rounded-md p-2">
                    <div class="mb-2 text-xs font-semibold">Recommended Tests / Items</div>
                    <div v-for="(testName, index) in form.tests" :key="`test-${index}`" class="flex items-start gap-2 mb-1.5">
                        <div class="relative w-full">
                            <input
                                :ref="el => testInputs[index] = el"
                                v-model="form.tests[index]"
                                type="text"
                                class="w-full px-2 py-1 border rounded text-xs dark:bg-slate-700"
                                placeholder="Item name"
                                @input="searchTest(form.tests[index], index)"
                                @focus="searchTest(form.tests[index], index)"
                                @keydown="handleTestSuggestionKeydown($event, index)"
                            />
                            <div v-if="activeTestRowIndex === index && testSuggestions.length" class="absolute z-50 left-0 right-0 mt-1 bg-white border rounded shadow max-h-40 overflow-auto text-xs">
                                <div
                                    v-for="(item, sIndex) in testSuggestions"
                                    :key="sIndex"
                                    :ref="el => testSuggestionElements[sIndex] = el"
                                    class="px-2 py-1 cursor-pointer"
                                    :class="sIndex === testHighlightedIndex ? 'bg-blue-100 text-blue-900' : 'hover:bg-blue-50'"
                                    @mousedown.prevent="selectTestSuggestion(index, item)"
                                >
                                    <template v-for="(part, partIndex) in testNameParts(item)" :key="partIndex"><mark v-if="part.matched" class="bg-yellow-200 font-semibold text-inherit">{{ part.text }}</mark><span v-else>{{ part.text }}</span></template>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="px-2 py-1 text-xs bg-red-500 text-white rounded" @click="removeTestRow(index)">Remove</button>
                    </div>
                    <button type="button" class="mt-1 px-3 py-1.5 text-xs bg-gray-600 text-white rounded" @click="addTestRow">Add Item</button>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <InputLabel value="Advice" class="text-xs mb-1" />
                        <textarea :ref="el => clinicalTextareas[0] = el" v-model="form.advice" rows="1" class="w-full resize-none overflow-hidden px-2 py-1.5 border rounded text-xs dark:bg-slate-700" placeholder="Advice" @input="resizeClinicalTextarea($event.target)"></textarea>
                    </div>
                    <div class="self-start">
                        <InputLabel value="Follow Up Date" class="text-xs mb-1" />
                        <input v-model="form.follow_up_date" type="date" class="w-full px-2 py-1.5 border rounded text-xs dark:bg-slate-700" />
                    </div>
                </div>

                <div class="flex items-center justify-end mt-3">
                    <button type="button" class="px-4 py-1.5 text-xs bg-blue-600 text-white rounded disabled:opacity-60" :disabled="form.processing" @click="savePrescription">
                        {{ form.processing ? 'Saving...' : (props.id ? 'Update Prescription' : 'Save Prescription') }}
                    </button>
                </div>
            </div>
        </div>
    </BackendLayout>
</template>
