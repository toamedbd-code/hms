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
import DoctorModal from '@/Components/DoctorModal.vue';
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

const props = defineProps(['ipdpatient', 'id', 'patients', 'doctors', 'bedGroups', 'beds', 'symptomTypes', 'designations', 'departments', 'specialists', 'pathologyAndRadiologyTests', 'medicineInventories', 'hospitalCharges']);

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
    hospital_charge_items: Array.isArray(props.ipdpatient?.hospital_charge_items) && props.ipdpatient.hospital_charge_items.length
        ? props.ipdpatient.hospital_charge_items
        : [{ item_name: '', unit_price: '', quantity: 1 }],

    _method: props.ipdpatient?.id ? 'put' : 'post',
});

const normalizeDoctorId = (value) => {
    if (value && typeof value === 'object') {
        return value.id ?? null;
    }

    return value ?? null;
};

const findDoctorById = (list, id) => {
    if (!id) return null;
    return list.find((item) => Number(item.id) === Number(id)) ?? null;
};

const isDoctorModalOpen = ref(false);
const doctorsList = ref([...(props.doctors || [])]);

watch(() => props.doctors, (newDoctors) => {
    doctorsList.value = [...(newDoctors || [])];
}, { immediate: true });

const openDoctorModal = () => {
    isDoctorModalOpen.value = true;
};

const closeDoctorModal = () => {
    isDoctorModalOpen.value = false;
};

const handleDoctorCreated = (newDoctor) => {
    closeDoctorModal();

    if (!newDoctor) return;

    const doctorId = normalizeDoctorId(newDoctor);
    const existingDoctor = findDoctorById(doctorsList.value, doctorId);

    if (!existingDoctor) {
        doctorsList.value = [...doctorsList.value, newDoctor];
    }

    const selectedDoctor = findDoctorById(doctorsList.value, doctorId);
    if (selectedDoctor) {
        form.consultant_doctor_id = selectedDoctor.id;
    }

    router.reload({
        only: ['doctors'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            doctorsList.value = [...(page.props.doctors || [])];
            const refreshedDoctor = findDoctorById(doctorsList.value, doctorId);
            if (refreshedDoctor) {
                form.consultant_doctor_id = refreshedDoctor.id;
            }
        }
    });
};

const addHospitalChargeItem = () => {
    form.hospital_charge_items.push({ item_name: '', unit_price: '', quantity: 1 });
};

const removeHospitalChargeItem = (index) => {
    if (form.hospital_charge_items.length <= 1) {
        form.hospital_charge_items[0] = { item_name: '', unit_price: '', quantity: 1 };
        return;
    }
    form.hospital_charge_items.splice(index, 1);
};

const getHospitalChargeRowTotal = (item) => {
    const unitPrice = Number(item?.unit_price ?? 0);
    const quantity = Number(item?.quantity ?? 0);
    if (unitPrice <= 0 || quantity <= 0) return 0;
    return unitPrice * quantity;
};

const hospitalChargeGrandTotal = computed(() => {
    return (form.hospital_charge_items || []).reduce((sum, item) => sum + getHospitalChargeRowTotal(item), 0);
});

const formatTk = (amount) => `Tk ${Number(amount || 0).toFixed(2)}`;

const autoGrowTextarea = (event) => {
    const textarea = event.target;
    textarea.style.height = 'auto';
    textarea.style.height = (textarea.scrollHeight) + 'px';
};

const applyGrandTotalToAdvance = () => {
    form.advance_amount = Number(hospitalChargeGrandTotal.value.toFixed(2));
};

// Normalize for matching: remove non-alphanumeric, collapse spaces, lowercase
const normalizeForMatch = (v) => {
    if (v == null) return '';
    return String(v)
        .toLowerCase()
        .replace(/[^a-z0-9\s]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
};

// Smart sorting helper copied from BillingPage to keep identical search behavior
const smartSortSearchResults = (items, queryString, getDisplayText = (item) => item.name || '') => {
    if (!queryString || !queryString.trim()) return items;

    const normalizedQuery = normalizeForMatch(queryString);
    const firstChar = normalizedQuery.charAt(0);

    return items.sort((a, b) => {
        const displayA = getDisplayText(a) || '';
        const displayB = getDisplayText(b) || '';

        const getMatchScore = (itemDisplay) => {
            const normalized = normalizeForMatch(itemDisplay);
            const words = normalized.split(/\s+/).filter(Boolean);
            const firstWord = words[0] || '';

            if (firstWord.startsWith(normalizedQuery)) return 120;
            if (normalized.startsWith(normalizedQuery)) return 110;
            if (firstChar && firstWord.startsWith(firstChar)) return 100;
            if (firstChar && normalized.startsWith(firstChar)) return 90;
            if (words.some(word => word.startsWith(normalizedQuery))) return 80;
            if (normalized.includes(normalizedQuery)) return 70;
            return 50;
        };

        const scoreA = getMatchScore(displayA);
        const scoreB = getMatchScore(displayB);

        return scoreB - scoreA;
    });
};

// Build combined items list for searching
const allAvailableHospitalItems = computed(() => {
    const items = [];

    // Add tests
    if (Array.isArray(props.pathologyAndRadiologyTests)) {
        console.log('✓ Tests available:', props.pathologyAndRadiologyTests.length);
        props.pathologyAndRadiologyTests.forEach((test) => {
            items.push({
                id: test.id,
                name: test.test_name || '',
                category: test.category_type || '',
                unitPrice: test.amount ?? 0,
                type: 'test',
                alt: [test.test_name, test.test_short_name ?? '', test.charge_name ?? ''].filter(Boolean).join(' | '),
            });
        });
    } else {
        console.warn('✗ Tests not available or not an array');
    }

    // Add medicines
    if (Array.isArray(props.medicineInventories)) {
        console.log('✓ Medicines available:', props.medicineInventories.length);
        props.medicineInventories.forEach((medicine) => {
            items.push({
                id: medicine.id,
                name: medicine.medicine_name || '',
                category: 'Medicine',
                unitPrice: medicine.medicine_unit_selling_price ?? 0,
                type: 'medicine',
                alt: [medicine.medicine_name, medicine.medicine_code ?? ''].filter(Boolean).join(' | '),
            });
        });
    } else {
        console.warn('✗ Medicines not available or not an array');
    }

    // Add hospital charges
    if (Array.isArray(props.hospitalCharges)) {
        console.log('✓ Charges available:', props.hospitalCharges.length);
        props.hospitalCharges.forEach((charge) => {
            items.push({
                id: charge.id,
                name: charge.name || '',
                category: charge.module || '',
                unitPrice: charge.amount ?? 0,
                type: 'charge',
                alt: [charge.name, charge.short_name ?? ''].filter(Boolean).join(' | '),
            });
        });
    } else {
        console.warn('✗ Charges not available or not an array');
    }

    console.log('📋 Total items for search:', items.length);
    return items;
});

// Filter items based on search query
// Use BillingPage's smart sorter and limit results for best UX
const getFilteredHospitalItems = (searchQuery) => {
    const rawQuery = String(searchQuery || '').trim();
    if (!rawQuery) return [];

    const tokens = rawQuery.split(/\s+/).filter(Boolean);
    const normalizedTokens = tokens.map(t => normalizeForMatch(t)).filter(Boolean);

    const filtered = allAvailableHospitalItems.value.filter((item) => {
        try {
            const hay = normalizeForMatch(((item.name || '') + ' ' + (item.alt || '')));
            if (!normalizedTokens.length) return false;
            return normalizedTokens.every((tok) => hay.includes(tok));
        } catch (e) {
            return false;
        }
    });

    const sorted = smartSortSearchResults(filtered, rawQuery, (i) => i.name || '');
    return sorted.slice(0, 50);
};


const hospitalItemSearchResults = ref([]);
const hospitalItemOptions = ref([]);

onMounted(() => {
    // Initialize with all items when component mounts, so they're always available
    console.log('📍 Component mounted, initializing search options...');
    hospitalItemOptions.value = allAvailableHospitalItems.value;
    console.log('✓ Initialized with', hospitalItemOptions.value.length, 'items');
});
const activeHospitalItemRowForSearch = ref(null);

const onHospitalItemSearch = (searchQuery) => {
    const q = typeof searchQuery === 'string' ? searchQuery : (searchQuery?.query || '');
    
    // When empty, restore full option list so dropdown can show suggestions on focus
    if (!q || String(q).trim().length < 1) {
        hospitalItemOptions.value = allAvailableHospitalItems.value;
        return;
    }

    hospitalItemOptions.value = getFilteredHospitalItems(q);
};

// Positioning helpers: place appended multiselect dropdown exactly under the input
const _positioningHandles = new Map();

const positionDropdownForRow = (rowIndex) => {
    nextTick(() => {
        try {
            const input = document.querySelector(`[data-hospital-item-multiselect="${rowIndex}"] .multiselect__input`);
            if (!input) return;

            const wrappers = Array.from(document.querySelectorAll('.multiselect__content-wrapper'));
            if (!wrappers.length) return;

            const wrapper = wrappers[wrappers.length - 1];

            const rect = input.getBoundingClientRect();
            wrapper.style.position = 'fixed';
            wrapper.style.left = `${rect.left}px`;
            wrapper.style.top = `${rect.bottom + 4}px`;
            wrapper.style.minWidth = `${rect.width}px`;
            wrapper.style.zIndex = '99999';
        } catch (e) {
            // ignore
        }
    });
};

const onHospitalMultiselectOpen = (rowIndex) => {
    // Ensure options populated so suggestions show immediately on open
    hospitalItemOptions.value = allAvailableHospitalItems.value;
    activeHospitalItemRowForSearch.value = rowIndex;

    // initial position
    positionDropdownForRow(rowIndex);

    const handle = () => positionDropdownForRow(rowIndex);
    window.addEventListener('scroll', handle, { passive: true });
    window.addEventListener('resize', handle);
    _positioningHandles.set(rowIndex, handle);
};

const onHospitalMultiselectClose = (rowIndex) => {
    const handle = _positioningHandles.get(rowIndex);
    if (handle) {
        window.removeEventListener('scroll', handle);
        window.removeEventListener('resize', handle);
        _positioningHandles.delete(rowIndex);
    }
};

const selectHospitalItemForRow = (item, rowIndex) => {
    if (!item || rowIndex < 0 || !form.hospital_charge_items[rowIndex]) return;

    form.hospital_charge_items[rowIndex].item_name = item.name || item.test_name || item.charge_name || '';
    form.hospital_charge_items[rowIndex].unit_price = Number(item.unitPrice ?? item.amount ?? item.standard_charge ?? 0);
    if (!Number(form.hospital_charge_items[rowIndex].quantity || 0)) {
        form.hospital_charge_items[rowIndex].quantity = 1;
    }
};

const onHospitalItemSelected = (item, rowIndex) => {
    if (!item) return;
    selectHospitalItemForRow(item, rowIndex);
    
    // Move to next row on selection
    nextTick(() => {
        if (rowIndex < form.hospital_charge_items.length - 1) {
            const nextRowInput = document.querySelector(`[data-hospital-item-multiselect="${rowIndex + 1}"] .multiselect__input`);
            if (nextRowInput) {
                nextRowInput.focus();
            }
        } else {
            addHospitalChargeItem();
            nextTick(() => {
                const newRowInput = document.querySelector(`[data-hospital-item-multiselect="${rowIndex + 1}"] .multiselect__input`);
                if (newRowInput) {
                    newRowInput.focus();
                }
            });
        }
    });
};

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


const submit = (options = {}) => {
    const routeName = props.id ? route('backend.ipdpatient.update', props.id) : route('backend.ipdpatient.store');
    const autoPrint = Boolean(options?.autoPrint ?? true);
    form.transform(data => ({
        ...data,
        hospital_charge_items: (data.hospital_charge_items || [])
            .map((item) => ({
                item_name: String(item?.item_name ?? '').trim(),
                unit_price: Number(item?.unit_price ?? 0),
                quantity: Number(item?.quantity ?? 1),
            }))
            .filter((item) => item.item_name !== '' && item.unit_price > 0 && item.quantity > 0),
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
                const url = route('backend.print.ipd.invoice', {
                    id: billId,
                    ...(autoPrint ? { auto_print: 1 } : {}),
                });
                try {
                    const popup = window.open(url, '_blank', 'noopener,noreferrer');
                    if (!popup) {
                        window.location.href = url;
                    }
                } catch (e) {
                    window.location.href = url;
                }
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
    submit({ autoPrint: true });
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

const goBack = () => {
    if (window.history.length > 1) {
        window.history.back();
        return;
    }
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
    <DoctorModal :isOpen="isDoctorModalOpen" :designations="props.designations || []"
        :departments="props.departments || []" :specialists="props.specialists || []"
        @close="closeDoctorModal" @doctorCreated="handleDoctorCreated" />

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
                            <button @click="goBack"
                                class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gray-500 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gray-600 ml-2">
                                <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
                                </svg>
                                Back
                            </button>
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
                            <textarea id="symptom_description" v-model="form.symptom_description" rows="1"
                                @input="autoGrowTextarea"
                                class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600 resize-none overflow-hidden"
                                placeholder="Describe symptoms in detail"></textarea>
                            <InputError class="mt-1" :message="form.errors.symptom_description" />
                        </div>

                        <!-- Note -->
                        <div>
                            <InputLabel for="note" value="Note" />
                            <textarea id="note" v-model="form.note" rows="1"
                                @input="autoGrowTextarea"
                                class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600 resize-none overflow-hidden"
                                placeholder="Additional notes"></textarea>
                            <InputError class="mt-1" :message="form.errors.note" />
                        </div>

                        <!-- Consultant Doctor -->
                        <div>
                            <InputLabel for="consultant_doctor_id" value="Consultant Doctor" class="required" />
                            <div class="flex items-center space-x-2">
                                <Multiselect :modelValue="doctorsList.find((doctor) => Number(doctor.id) === Number(form.consultant_doctor_id))"
                                    @update:modelValue="handleDoctorSelect" :options="doctorsList" :track-by="'id'"
                                    :label="'name'" placeholder="Search and select a doctor"
                                    class="w-full text-sm rounded-md border border-slate-300 doctor-multiselect" />
                                <button type="button" @click="openDoctorModal"
                                    class="flex-shrink-0 inline-flex items-center justify-center w-9 h-9 text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                    title="Add Doctor">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                </button>
                            </div>
                            <InputError class="mt-1" :message="form.errors.consultant_doctor_id" />
                        </div>

                        <!-- Reference Doctor -->
                        <div>
                            <InputLabel for="reference" value="Reference Doctor" />
                            <input id="reference" v-model="form.reference" type="text"
                                class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                placeholder="Reference doctor name" />
                            <InputError class="mt-1" :message="form.errors.reference" />
                        </div>

                        <!-- Live Consultation -->
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

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <InputLabel for="credit_limit" value="Credit Limit" />
                                <input id="credit_limit" v-model="form.credit_limit" type="text"
                                    class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                    placeholder="Credit Limit" />
                                <InputError class="mt-1" :message="form.errors.credit_limit" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
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
                        </div>

                        <!-- Advance Amount -->
                        <div>
                            <InputLabel for="advance_amount" value="Advance Amount (Tk)" />
                            <input id="advance_amount" v-model="form.advance_amount" type="number" step="0.01"
                                class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" placeholder="0.00" />
                            <InputError class="mt-1" :message="form.errors.advance_amount" />
                        </div>
                    </div>
                </div>


                <div class="mt-3">
                    <div class="flex items-center justify-between mb-2">
                        <InputLabel for="hospital_charge_items" value="Hospital Charge Items" />
                        <button
                            type="button"
                            class="px-2 py-1 text-xs font-semibold text-white bg-emerald-600 rounded-md hover:bg-emerald-700"
                            @click="addHospitalChargeItem"
                        >
                            + Add Row
                        </button>
                    </div>

                    <div class="overflow-x-auto border rounded-md border-slate-200">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-3 py-2 text-left">Item Name</th>
                                    <th class="px-3 py-2 text-left">Price (Tk.)</th>
                                    <th class="px-3 py-2 text-left">Qty</th>
                                    <th class="px-3 py-2 text-left">Subtotal</th>
                                    <th class="px-3 py-2 text-left">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in form.hospital_charge_items" :key="index" class="border-t border-slate-200">
                                    <td class="px-3 py-2 relative" :data-hospital-item-multiselect="index">
                                        <Multiselect
                                            id="hospital-multiselect-" :class="`hospital-item-multiselect hospital-item-multiselect-${index} item-name-multiselect w-full text-sm rounded-md border border-slate-300`"
                                            :modelValue="hospitalItemOptions.find(opt => opt.name === item.item_name) || null"
                                            @update:modelValue="(val) => { if (val) { item.item_name = val.name; item.unit_price = val.unitPrice; } else { item.item_name = ''; } }"
                                            @search-input="onHospitalItemSearch"
                                            @search-change="onHospitalItemSearch"
                                            :options="hospitalItemOptions"
                                            :track-by="'id'"
                                            :label="'name'"
                                            
                                            @open="() => onHospitalMultiselectOpen(index)"
                                            @close="() => onHospitalMultiselectClose(index)"
                                            :searchable="true"
                                            :close-on-select="true"
                                            :clear-on-select="false"
                                            :allow-empty="true"
                                            :preserve-search="true"
                                            :max-height="300"
                                            :show-labels="false"
                                            placeholder="Search and select item"
                                            @select="(item) => { onHospitalItemSelected(item, index); }"
                                        >
                                            <template #option="{ option }">
                                                <div class="text-xs">
                                                    <div class="font-medium">{{ option.name }}</div>
                                                    <div class="text-slate-600">Tk {{ Number(option.unitPrice ?? 0).toFixed(2) }}</div>
                                                </div>
                                            </template>
                                            <template #singleLabel="{ option }">
                                                {{ typeof option === 'object' ? (option.name) : option }}
                                            </template>
                                        </Multiselect>
                                    </td>
                                    <td class="px-3 py-2">
                                        <input
                                            v-model="item.unit_price"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="block w-full p-1.5 text-sm rounded-md border-slate-300"
                                            placeholder="0.00"
                                        />
                                    </td>
                                    <td class="px-3 py-2">
                                        <input
                                            v-model="item.quantity"
                                            type="number"
                                            min="1"
                                            step="1"
                                            class="block w-full p-1.5 text-sm rounded-md border-slate-300"
                                            placeholder="1"
                                        />
                                    </td>
                                    <td class="px-3 py-2 font-semibold text-slate-700">
                                        {{ formatTk(getHospitalChargeRowTotal(item)) }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <button
                                            type="button"
                                            class="px-2 py-1 text-xs font-semibold text-white bg-red-600 rounded-md hover:bg-red-700"
                                            @click="removeHospitalChargeItem(index)"
                                        >
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-slate-50 border-t border-slate-200">
                                <tr>
                                    <td class="px-3 py-2 text-right font-semibold" colspan="3">Grand Total</td>
                                    <td class="px-3 py-2 font-bold text-emerald-700">{{ formatTk(hospitalChargeGrandTotal) }}</td>
                                    <td class="px-3 py-2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <InputError class="mt-1" :message="form.errors.hospital_charge_items" />
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

:deep(.doctor-multiselect .multiselect__content-wrapper) {
    max-height: 280px !important;
}

:deep(.doctor-multiselect .multiselect__content) {
    max-height: 260px !important;
}

:deep(.item-name-multiselect .multiselect__content-wrapper) {
    max-height: 160px !important;
}

:deep(.item-name-multiselect .multiselect__content) {
    max-height: 140px !important;
}

:deep(.item-name-multiselect .multiselect__option) {
    padding: 6px 12px !important;
    font-size: 0.875rem !important;
}
</style>