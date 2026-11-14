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

const props = defineProps({
    ipdpatient: { type: Object, default: null },
    id: { type: [String, Number], default: null },
    patients: { type: Array, default: () => [] },
    doctors: { type: Array, default: () => [] },
    bedGroups: { type: Array, default: () => [] },
    beds: { type: Array, default: () => [] },
    symptomTypes: { type: Array, default: () => [] },
    designations: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    specialists: { type: Array, default: () => [] },
    symptomTitles: { type: Array, default: () => [] },
    pathologyAndRadiologyTests: { type: Array, default: () => [] },
    hospitalCharges: { type: Array, default: () => [] },
    tpas: { type: Array, default: () => [] },
});

// `datetime-local` only accepts `YYYY-MM-DDTHH:MM`, while the server sends
// `YYYY-MM-DD HH:MM:SS`. Without this the saved date shows up blank on edit.
const toDateTimeInput = (value) => {
    if (!value) return '';

    const text = String(value).trim().replace(' ', 'T');

    return text.slice(0, 16);
};

const form = useForm({
    patient_id: props.ipdpatient?.patient_id ?? '',
    consultant_doctor_id: props.ipdpatient?.consultant_doctor_id ?? '',

    // Left side fields
    symptom_type: props.ipdpatient?.symptom_type ?? '',
    symptom_title: props.ipdpatient?.symptom_title ?? '',
    symptom_description: props.ipdpatient?.symptom_description ?? '',
    note: props.ipdpatient?.note ?? '',

    // Right side fields
    admission_date: toDateTimeInput(props.ipdpatient?.admission_date),
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

const toList = (value) => {
    if (Array.isArray(value)) return value;
    if (value && typeof value === 'object') return Object.values(value);
    return [];
};

// Build combined items list for searching
const allAvailableHospitalItems = computed(() => {
    const items = [];

    toList(props.pathologyAndRadiologyTests).forEach((test) => {
        items.push({
            id: `test-${test.id}`,
            sourceId: test.id,
            name: test.test_name || '',
            category: test.category_type || '',
            unitPrice: Number(test.amount ?? test.standard_charge ?? 0),
            type: 'test',
            alt: [test.test_name, test.test_short_name ?? '', test.charge_name ?? ''].filter(Boolean).join(' | '),
        });
    });

    toList(props.hospitalCharges).forEach((charge) => {
        items.push({
            id: `charge-${charge.id}`,
            sourceId: charge.id,
            name: charge.name || '',
            category: charge.module || '',
            unitPrice: Number(charge.amount ?? charge.standard_charge ?? 0),
            type: 'charge',
            alt: [charge.name, charge.short_name ?? ''].filter(Boolean).join(' | '),
        });
    });

    return items;
});

const getFilteredHospitalItems = (searchQuery) => {
    const rawQuery = String(searchQuery || '').trim();
    const source = allAvailableHospitalItems.value;

    if (!rawQuery) {
        return source.slice(0, 50);
    }

    const tokens = rawQuery.split(/\s+/).filter(Boolean);
    const normalizedTokens = tokens.map((t) => normalizeForMatch(t)).filter(Boolean);

    const filtered = source.filter((item) => {
        const hay = normalizeForMatch(`${item.name || ''} ${item.alt || ''} ${item.category || ''}`);
        return normalizedTokens.every((tok) => hay.includes(tok));
    });

    return smartSortSearchResults(filtered, rawQuery, (i) => i.name || '').slice(0, 50);
};

const activeHospitalItemRowForSearch = ref(null);
const suppressHospitalItemSearch = ref(false);
const hospitalItemDropdownStyle = ref({});
const hospitalItemInputEls = new Map();
const hospitalMedicineResults = ref([]);
const hospitalMedicineSearchLoading = ref(false);
let hospitalMedicineSearchTimer = null;
let hospitalMedicineSearchController = null;
let hospitalMedicineSearchQuery = '';

const setHospitalItemInputEl = (index, el) => {
    if (el) {
        hospitalItemInputEls.set(index, el);
    } else {
        hospitalItemInputEls.delete(index);
    }
};

const hospitalItemOptions = computed(() => {
    const rowIndex = activeHospitalItemRowForSearch.value;
    if (rowIndex === null || rowIndex === undefined) return [];

    const row = form.hospital_charge_items[rowIndex];
    const query = String(row?.item_name || '').trim();
    const localResults = getFilteredHospitalItems(query);
    const remoteMedicines = hospitalMedicineResults.value.filter((item) =>
        item.name.toLowerCase().includes(query.toLowerCase())
    );

    return [...remoteMedicines, ...localResults.filter((item) => item.type !== 'medicine')].slice(0, 50);
});

const positionHospitalItemDropdown = (rowIndex) => {
    nextTick(() => {
        const input = hospitalItemInputEls.get(rowIndex);
        if (!input) return;

        const rect = input.getBoundingClientRect();
        const width = Math.max(rect.width, 280);
        const spaceBelow = window.innerHeight - rect.bottom;
        const openUp = spaceBelow < 220 && rect.top > 220;

        hospitalItemDropdownStyle.value = {
            position: 'fixed',
            left: `${rect.left}px`,
            width: `${width}px`,
            zIndex: 99999,
            ...(openUp
                ? { bottom: `${window.innerHeight - rect.top + 4}px`, top: 'auto' }
                : { top: `${rect.bottom + 4}px`, bottom: 'auto' }),
        };
    });
};

const rowHasSelectedHospitalItem = (rowIndex) => {
    const row = form.hospital_charge_items[rowIndex];
    return Boolean(String(row?.item_name || '').trim()) && Number(row?.unit_price || 0) > 0;
};

const openHospitalItemSearch = (rowIndex, fromFocus = false) => {
    if (suppressHospitalItemSearch.value) return;
    if (fromFocus && rowHasSelectedHospitalItem(rowIndex)) return;

    activeHospitalItemRowForSearch.value = rowIndex;
    positionHospitalItemDropdown(rowIndex);
};

const closeHospitalItemSearch = () => {
    activeHospitalItemRowForSearch.value = null;
};

const onHospitalItemQuery = (rowIndex) => {
    if (suppressHospitalItemSearch.value) return;

    activeHospitalItemRowForSearch.value = rowIndex;
    positionHospitalItemDropdown(rowIndex);
    const query = String(form.hospital_charge_items[rowIndex]?.item_name || '').trim();
    hospitalMedicineSearchQuery = query;
    clearTimeout(hospitalMedicineSearchTimer);
    hospitalMedicineSearchController?.abort();
    hospitalMedicineResults.value = [];

    if (query.length < 2) {
        hospitalMedicineSearchLoading.value = false;
        return;
    }

    hospitalMedicineSearchLoading.value = true;
    hospitalMedicineSearchTimer = setTimeout(async () => {
        const controller = new AbortController();
        hospitalMedicineSearchController = controller;

        try {
            const response = await fetch(route('backend.ipdpatient.medicines.search', { search: query }), {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            if (!response.ok) throw new Error(`Medicine search failed (${response.status}).`);
            const results = await response.json();
            if (hospitalMedicineSearchQuery === query && Array.isArray(results)) {
                hospitalMedicineResults.value = results;
            }
        } catch (error) {
            if (error.name !== 'AbortError' && hospitalMedicineSearchQuery === query) {
                hospitalMedicineResults.value = [];
                console.error('Unable to search medicines for IPD charges.', error);
            }
        } finally {
            if (hospitalMedicineSearchQuery === query) {
                hospitalMedicineSearchLoading.value = false;
            }
        }
    }, 180);
};

const selectHospitalItemForRow = (item, rowIndex) => {
    if (!item || rowIndex < 0 || !form.hospital_charge_items[rowIndex]) return;

    suppressHospitalItemSearch.value = true;
    closeHospitalItemSearch();

    form.hospital_charge_items[rowIndex].item_name = item.name || '';
    form.hospital_charge_items[rowIndex].unit_price = Number(item.unitPrice ?? 0);
    if (!Number(form.hospital_charge_items[rowIndex].quantity || 0)) {
        form.hospital_charge_items[rowIndex].quantity = 1;
    }

    hospitalItemInputEls.get(rowIndex)?.blur();

    nextTick(() => {
        const shouldAddRow = rowIndex >= form.hospital_charge_items.length - 1;
        if (shouldAddRow) {
            addHospitalChargeItem();
        }

        nextTick(() => {
            if (shouldAddRow) {
                hospitalItemInputEls.get(rowIndex + 1)?.focus();
            }

            requestAnimationFrame(() => {
                suppressHospitalItemSearch.value = false;
            });
        });
    });
};

const handleHospitalItemKeydown = (event, rowIndex) => {
    if (event.key === 'Escape') {
        event.preventDefault();
        closeHospitalItemSearch();
        return;
    }

    if (event.key === 'Enter' && hospitalItemOptions.value.length === 1) {
        event.preventDefault();
        selectHospitalItemForRow(hospitalItemOptions.value[0], rowIndex);
    }
};

const handleHospitalItemDocumentClick = (event) => {
    if (activeHospitalItemRowForSearch.value === null) return;

    const input = hospitalItemInputEls.get(activeHospitalItemRowForSearch.value);
    if (input && input.contains(event.target)) return;
    if (event.target?.closest?.('.hospital-item-dropdown')) return;

    closeHospitalItemSearch();
};

onMounted(() => {
    document.addEventListener('click', handleHospitalItemDocumentClick);
    window.addEventListener('resize', () => {
        if (activeHospitalItemRowForSearch.value !== null) {
            positionHospitalItemDropdown(activeHospitalItemRowForSearch.value);
        }
    });

    onBeforeUnmount(() => {
        clearTimeout(hospitalMedicineSearchTimer);
        hospitalMedicineSearchController?.abort();
    });
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

    form.bed_id = '';
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
const showSymptomTypeSuggestions = ref(false);
const showSymptomTitleSuggestions = ref(false);
const openPatientModal = () => {
    isPatientModalOpen.value = true;
};
const closePatientModal = () => {
    isPatientModalOpen.value = false;
};

const openSymptomTypeModal = () => {
    showSymptomTypeSuggestions.value = false;
    showSymptomTypeModal.value = true;
};

const closeSymptomTypeModal = () => {
    showSymptomTypeModal.value = false;
};

const handleSymptomTypeCreated = (createdName) => {
    if (createdName) {
        form.symptom_type = createdName;
        if (!symptomTypes.value.some((type) => String(type.name) === createdName)) {
            symptomTypes.value = [{ id: `custom-${createdName}`, name: createdName }, ...symptomTypes.value];
        }
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

const handleSymptomTypeSelect = (type) => {
    form.symptom_type = type?.name ?? '';
    showSymptomTypeSuggestions.value = false;
};

const filteredSymptomTypes = computed(() => {
    const query = String(form.symptom_type || '').trim().toLowerCase();
    return symptomTypes.value
        .filter((type) => !query || String(type.name).toLowerCase().includes(query))
        .slice(0, 10);
});

const filteredSymptomTitles = computed(() => {
    const query = String(form.symptom_title || '').trim().toLowerCase();
    return (props.symptomTitles || [])
        .filter((title) => !query || String(title).toLowerCase().includes(query))
        .slice(0, 10);
});

const selectSymptomTitle = (title) => {
    form.symptom_title = title;
    showSymptomTitleSuggestions.value = false;
};
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

onMounted(() => {
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
    document.removeEventListener('click', handleHospitalItemDocumentClick);
    window.__ipdBedSelectReady = false;
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
                                    <div class="relative w-full">
                                        <input
                                            id="symptom_type"
                                            v-model="form.symptom_type"
                                            type="text"
                                            autocomplete="off"
                                            role="combobox"
                                            aria-autocomplete="list"
                                            :aria-expanded="showSymptomTypeSuggestions"
                                            placeholder="Search or type a symptom type"
                                            class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                            @focus="showSymptomTypeSuggestions = true"
                                            @input="showSymptomTypeSuggestions = true"
                                            @keydown.esc="showSymptomTypeSuggestions = false"
                                            @blur="showSymptomTypeSuggestions = false"
                                        />
                                        <div
                                            v-if="showSymptomTypeSuggestions"
                                            class="absolute z-30 mt-1 w-full overflow-auto bg-white border rounded-md shadow-lg border-slate-200 max-h-52 dark:bg-slate-800 dark:border-slate-600"
                                            role="listbox"
                                        >
                                            <button
                                                v-for="type in filteredSymptomTypes"
                                                :key="type.id"
                                                type="button"
                                                class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-700"
                                                role="option"
                                                @mousedown.prevent="handleSymptomTypeSelect(type)"
                                            >
                                                {{ type.name }}
                                            </button>
                                            <p v-if="filteredSymptomTypes.length === 0" class="px-3 py-2 text-sm text-slate-500">
                                                No saved symptom types. The entered type will be saved with this admission.
                                            </p>
                                        </div>
                                    </div>
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
                                <div class="relative">
                                    <input
                                        id="symptom_title"
                                        v-model="form.symptom_title"
                                        type="text"
                                        autocomplete="off"
                                        role="combobox"
                                        aria-autocomplete="list"
                                        :aria-expanded="showSymptomTitleSuggestions"
                                        class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                        placeholder="Enter symptom title"
                                        @focus="showSymptomTitleSuggestions = true"
                                        @input="showSymptomTitleSuggestions = true"
                                        @keydown.esc="showSymptomTitleSuggestions = false"
                                        @blur="showSymptomTitleSuggestions = false"
                                    />
                                    <div
                                        v-if="showSymptomTitleSuggestions"
                                        class="absolute z-30 mt-1 w-full overflow-auto bg-white border rounded-md shadow-lg border-slate-200 max-h-52 dark:bg-slate-800 dark:border-slate-600"
                                        role="listbox"
                                    >
                                        <button
                                            v-for="(title, index) in filteredSymptomTitles"
                                            :key="`${title}-${index}`"
                                            type="button"
                                            class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-700"
                                            role="option"
                                            @mousedown.prevent="selectSymptomTitle(title)"
                                        >
                                            {{ title }}
                                        </button>
                                        <p v-if="filteredSymptomTitles.length === 0" class="px-3 py-2 text-sm text-slate-500">
                                            No saved symptom titles. This title will be saved with the admission.
                                        </p>
                                    </div>
                                </div>
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

                        <!-- Advance Amount (admission only; later money is taken from the patient overview) -->
                        <div v-if="!props.id">
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
                                    <td class="px-3 py-2">
                                        <input
                                            :ref="(el) => setHospitalItemInputEl(index, el)"
                                            v-model="item.item_name"
                                            type="text"
                                            autocomplete="off"
                                            class="block w-full p-1.5 text-sm rounded-md border-slate-300"
                                            placeholder="Search and select item"
                                            @focus="openHospitalItemSearch(index, true)"
                                            @input="onHospitalItemQuery(index)"
                                            @keydown="handleHospitalItemKeydown($event, index)"
                                        />
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

                <Teleport to="body">
                    <div
                        v-if="activeHospitalItemRowForSearch !== null"
                        class="hospital-item-dropdown overflow-auto bg-white border rounded-md shadow-lg border-slate-200 max-h-60"
                        :style="hospitalItemDropdownStyle"
                    >
                        <button
                            v-for="option in hospitalItemOptions"
                            :key="option.id"
                            type="button"
                            class="flex items-center justify-between w-full px-3 py-2 text-left border-b border-slate-100 last:border-b-0 hover:bg-slate-50"
                            @mousedown.prevent="selectHospitalItemForRow(option, activeHospitalItemRowForSearch)"
                        >
                            <span>
                                <span class="block text-sm font-medium text-slate-800">{{ option.name }}</span>
                                <span class="block text-xs text-slate-500">{{ option.category || option.type }}</span>
                            </span>
                            <span class="text-xs font-semibold text-slate-600">{{ formatTk(option.unitPrice) }}</span>
                        </button>
                        <div
                            v-if="hospitalItemOptions.length === 0"
                            class="px-3 py-2 text-sm text-slate-500"
                        >
                            {{ hospitalMedicineSearchLoading ? 'Searching medicines...' : 'No matching items' }}
                        </div>
                    </div>
                </Teleport>

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
</style>