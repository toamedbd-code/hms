<script setup>
import { ref, nextTick, computed, onMounted, onBeforeUnmount } from "vue";
import BackendLayout from "@/Layouts/BackendLayout.vue";
import InputLabel from "@/Components/InputLabel.vue";
import { router, useForm } from "@inertiajs/vue3";
import { displayResponse } from "@/responseMessage.js";
import { useModalSubmissionGuard } from "@/Composables/useModalSubmissionGuard";

const medicineSuggestions = ref([]);
const testSuggestions = ref([]);
const medicineMemoryByName = ref({});
const medicineDuplicateError = ref("");
let medicineSearchTimer = null;
let testSearchTimer = null;

// Refs for per-row test suggestion UI
const testInputs = ref([]); // DOM refs to test input fields
const activeTestRowIndex = ref(null);
const suggestionsVisible = ref(false);
const highlightedIndex = ref(0);
// Medicine dropdown refs/state
const medicineInputs = ref([]);
const medicineSuggestionElements = ref([]);
const activeMedicineRowIndex = ref(null);
const medicineSuggestionsVisible = ref(false);
const medicineHighlightedIndex = ref(0);
const medicineSearching = ref(false);
const medicineSearchError = ref("");
const medicineDropdownStyle = ref({});
let medicineSearchRequest = 0;

const props = defineProps({
    ipdpatient: Object,
    prescription: Object,
    doctors: Array,
    pharmacyHistory: Array,
    pharmacyDueSummary: Object,
});

const saveError = ref("");
const doctorSignaturePreview = ref(props.prescription?.doctor_signature_url ?? "");
const doctorSealPreview = ref(props.prescription?.doctor_seal_url ?? "");

const normalizeMedicineName = (value) => String(value ?? "").trim().replace(/\s+/g, " ").toLowerCase();
const uniqueMedicineRows = (rows) => {
    const seen = new Set();
    return rows.filter((row) => {
        const name = normalizeMedicineName(row?.medicine_name);
        if (!name) return true;
        if (seen.has(name)) return false;
        seen.add(name);
        return true;
    });
};

const form = useForm({
    doctor_id: props.prescription?.doctor_id ?? props.ipdpatient?.consultant_doctor_id ?? "",
    doctor_designation: props.prescription?.doctor_designation ?? "",
    nibp: props.prescription?.nibp ?? "",
    weight: props.prescription?.weight ?? "",
    pulse: props.prescription?.pulse ?? "",
    temperature: String(props.prescription?.temperature ?? "").match(/-?\d+(?:\.\d+)?/)?.[0] ?? "",
    complaints: props.prescription?.complaints ?? "",
    examinations: props.prescription?.examinations ?? "",
    diagnosis: props.prescription?.diagnosis ?? "",
    advice: props.prescription?.advice ?? "",
    follow_up_date: props.prescription?.follow_up_date ?? "",

    medicines: props.prescription?.medicines?.length
        ? uniqueMedicineRows(props.prescription.medicines.map((item) => ({
              medicine_name: item.medicine_name ?? "",
              generic_name: item.generic_name ?? "",
              quantity: item.quantity ?? 1,
              dose: item.dose ?? "",
              frequency: item.frequency ?? "",
              duration: item.duration ?? "",
              instructions: item.instructions ?? "",
          })))
        : [
              {
                  medicine_name: "",
                  generic_name: "",
                  quantity: 1,
                  dose: "",
                  frequency: "",
                  duration: "",
                  instructions: "",
              },
          ],

    tests: props.prescription?.tests?.length
        ? props.prescription.tests.map((test) => test.test_name ?? "")
        : [""],
    doctor_seal: null,
    doctor_signature: null,
});

const temperatureUnit = ref(/(?:°\s*F|fahrenheit)/i.test(String(props.prescription?.temperature ?? "")) ? "F" : "C");

const changeTemperatureUnit = (event) => {
    const nextUnit = event.target.value;
    const temperature = Number.parseFloat(form.temperature);
    if (Number.isFinite(temperature) && nextUnit !== temperatureUnit.value) {
        form.temperature = String(Number(
            nextUnit === "F"
                ? (temperature * 9) / 5 + 32
                : ((temperature - 32) * 5) / 9
        ).toFixed(1));
    }
    temperatureUnit.value = nextUnit;
};

const refreshDoctors = () => router.reload({
    only: ["doctors"],
    preserveScroll: true,
});

const handleDoctorSealChange = (event) => {
    const file = event?.target?.files?.[0] ?? null;
    form.doctor_seal = file;

    if (!file) {
        doctorSealPreview.value = props.prescription?.doctor_seal_url ?? "";
        return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        doctorSealPreview.value = String(e.target?.result ?? "");
    };
    reader.readAsDataURL(file);
};

const handleDoctorSignatureChange = (event) => {
    const file = event?.target?.files?.[0] ?? null;
    form.doctor_signature = file;

    if (!file) {
        doctorSignaturePreview.value = props.prescription?.doctor_signature_url ?? "";
        return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        doctorSignaturePreview.value = String(e.target?.result ?? "");
    };
    reader.readAsDataURL(file);
};

const setMedicineInput = (element, index) => {
    medicineInputs.value[index] = element;
};

const addMedicineRow = async () => {
    form.medicines.push({
        medicine_name: "",
        generic_name: "",
        quantity: 1,
        dose: "",
        frequency: "",
        duration: "",
        instructions: "",
    });
    await nextTick();
    const newIndex = form.medicines.length - 1;
    medicineInputs.value[newIndex]?.focus();
    medicineInputs.value[newIndex]?.select();
};

const removeMedicineRow = (index) => {
    if (form.medicines.length === 1) return;
    form.medicines.splice(index, 1);
    validateMedicineDuplicates();
};

const openPharmacyBill = () => {
    if (!props.prescription?.id || form.isDirty) {
        savePrescription(true);
        return;
    }

    navigateToPharmacy(props.prescription.id);
};

const navigateToPharmacy = (prescriptionId) => {
    router.visit(route("backend.pharmacybill.create", {
        ipd_patient_id: props.ipdpatient?.id,
        ipd_prescription_id: prescriptionId,
    }));
};

const pharmacyProducts = (bill) => {
    if (Array.isArray(bill.products)) return bill.products;
    try {
        const parsed = JSON.parse(bill.products ?? "[]");
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
};

const pharmacyFinancialHistory = computed(() => {
    const payments = (props.pharmacyPaymentHistory ?? []).map((item) => ({
        id: `payment-${item.id}`,
        date: item.created_at,
        bill: item.billing?.bill_number || "N/A",
        type: "Payment",
        method: item.payment_method || "N/A",
        amount: item.amount,
        note: item.payment_status || "",
    }));
    const dueCollections = (props.pharmacyDueHistory ?? []).map((item) => ({
        id: `due-${item.id}`,
        date: item.collected_at || item.created_at,
        bill: item.billing?.bill_number || "N/A",
        type: "Due Collection",
        method: item.payment_method || "N/A",
        amount: item.collected_amount,
        note: item.note || "",
    }));

    return [...payments, ...dueCollections].sort((left, right) =>
        new Date(right.date ?? 0).getTime() - new Date(left.date ?? 0).getTime()
    );
});

const showPharmacyDueModal = ref(false);
const pharmacyDueForm = useForm({
    amount: "",
    payment_method: "Cash",
    submission_token: "",
});
const {
    isSubmitting: isSubmittingPharmacyDue,
    prepareSubmissionToken,
    ensureSubmissionToken,
    beginSubmission,
    endSubmission,
    resetSubmissionToken,
} = useModalSubmissionGuard("ipd_pharmacy_due");

const openPharmacyDueModal = () => {
    pharmacyDueForm.amount = Number(props.pharmacyDueSummary?.due_amount || 0).toFixed(2);
    pharmacyDueForm.payment_method = "Cash";
    pharmacyDueForm.submission_token = prepareSubmissionToken();
    pharmacyDueForm.clearErrors();
    showPharmacyDueModal.value = true;
};

const closePharmacyDueModal = () => {
    if (pharmacyDueForm.processing) return;
    showPharmacyDueModal.value = false;
    pharmacyDueForm.reset();
    resetSubmissionToken();
};

const submitPharmacyDue = () => {
    if (pharmacyDueForm.processing || isSubmittingPharmacyDue.value) return;

    pharmacyDueForm.submission_token = ensureSubmissionToken();
    beginSubmission();
    pharmacyDueForm.post(route("backend.ipdpatient.pharmacy-due-collect", props.ipdpatient.id), {
        preserveScroll: true,
        onSuccess: () => {
            showPharmacyDueModal.value = false;
            pharmacyDueForm.reset();
            resetSubmissionToken();
        },
        onFinish: () => {
            endSubmission();
        },
    });
};

const addTestRow = () => {
    form.tests.push("");
    nextTick(() => {
        const newIndex = form.tests.length - 1;
        testInputs.value[newIndex]?.focus();
        testInputs.value[newIndex]?.select();
    });
};

const removeTestRow = (index) => {
    if (form.tests.length === 1) return;
    form.tests.splice(index, 1);
};

const fetchSuggestions = async (url, target) => {
    try {
        const response = await fetch(url, { headers: { Accept: "application/json" } });
        if (!response.ok) {
            target.value = [];
            return;
        }
        const data = await response.json();
        target.value = Array.isArray(data?.results) ? data.results : [];
        // If these are test suggestions, reset highlight and show dropdown
        if (target === testSuggestions) {
            highlightedIndex.value = 0;
        }
    } catch (error) {
        target.value = [];
    }
};

const flattenTestSuggestions = computed(() => {
    return Array.isArray(testSuggestions.value) ? [...testSuggestions.value] : [];
});

const groupedTestSuggestions = computed(() => {
    const groups = {};
    (testSuggestions.value || []).forEach((item) => {
        const key = (typeof item === 'string') ? 'Other' : ((item?.category_type ?? 'Other') || 'Other');
        if (!groups[key]) groups[key] = [];
        groups[key].push(item);
    });
    return groups;
});

const normalizeMedicineResults = (results) => {
    if (!Array.isArray(results)) {
        return [];
    }

    return results
        .map((item) => {
            if (typeof item === "string") {
                return {
                    name: item,
                    generic_name: "",
                    dose: "",
                    duration: "",
                    frequency: "",
                    instructions: "",
                    dose_options: [],
                    frequency_options: [],
                    duration_options: [],
                    instructions_options: [],
                };
            }

            const name = String(item?.name ?? item?.medicine_name ?? "").trim();
            if (!name) {
                return null;
            }

            return {
                name,
                generic_name: String(item?.generic_name ?? ""),
                dose: String(item?.dose ?? ""),
                duration: String(item?.duration ?? ""),
                frequency: String(item?.frequency ?? ""),
                instructions: String(item?.instructions ?? ""),
                dose_options: Array.isArray(item?.dose_options) ? item.dose_options : [],
                frequency_options: Array.isArray(item?.frequency_options) ? item.frequency_options : [],
                duration_options: Array.isArray(item?.duration_options) ? item.duration_options : [],
                instructions_options: Array.isArray(item?.instructions_options) ? item.instructions_options : [],
            };
        })
        .filter(Boolean);
};

const normalizeText = (value) => String(value ?? "").trim().toLowerCase();

const rememberMedicineSuggestions = (items) => {
    items.forEach((item) => {
        const key = normalizeMedicineName(item?.name);
        if (!key) return;

        const remembered = medicineMemoryByName.value[key] ?? {};
        const next = { ...remembered, ...item };
        ["dose", "frequency", "duration", "instructions"].forEach((field) => {
            next[`${field}_options`] = [...new Set([
                ...(remembered[`${field}_options`] ?? []),
                ...(item[`${field}_options`] ?? []),
                item[field],
            ].map((value) => String(value ?? "").trim()).filter(Boolean))];
        });
        medicineMemoryByName.value[key] = next;
    });
};

const medicineOptionsListId = (index, field) => `ipd-${field}-suggestions-${index}`;
const medicineOptionsFor = (row, field) => {
    const remembered = medicineMemoryByName.value[normalizeMedicineName(row?.medicine_name)];
    return remembered?.[`${field}_options`] ?? [];
};

const rememberMedicineField = (row, field) => {
    const key = normalizeMedicineName(row?.medicine_name);
    const value = String(row?.[field] ?? "").trim();
    if (!key || !value) return;

    const remembered = medicineMemoryByName.value[key] ?? {};
    medicineMemoryByName.value[key] = {
        ...remembered,
        [`${field}_options`]: [...new Set([...(remembered[`${field}_options`] ?? []), value])],
    };
};

const validateMedicineDuplicates = () => {
    const seen = new Set();
    const duplicate = form.medicines.find((row) => {
        const name = normalizeMedicineName(row?.medicine_name);
        if (!name) return false;
        if (seen.has(name)) return true;
        seen.add(name);
        return false;
    });
    medicineDuplicateError.value = duplicate
        ? `${duplicate.medicine_name} is already added to this prescription.`
        : "";
    return !duplicate;
};

const medicineNameParts = (item) => {
    const name = String(item?.name ?? "");
    const query = String(form.medicines[activeMedicineRowIndex.value]?.medicine_name ?? "").trim();
    const terms = [...new Set(query.split(/\s+/).filter(Boolean))]
        .sort((left, right) => right.length - left.length)
        .map((term) => term.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"));

    if (!name || terms.length === 0) {
        return [{ text: name, matched: false }];
    }

    return name
        .split(new RegExp(`(${terms.join("|")})`, "ig"))
        .filter((text) => text !== "")
        .map((text) => ({
            text,
            matched: terms.some((term) => new RegExp(`^${term}$`, "i").test(text)),
        }));
};

const applyMedicineDefaults = (index, medicineName) => {
    const row = form.medicines[index];
    if (!row) {
        return;
    }

    const selectedName = normalizeText(medicineName);
    if (!selectedName) {
        return;
    }

    const match = medicineSuggestions.value.find(
        (item) => normalizeText(item?.name) === selectedName
    ) ?? medicineMemoryByName.value[normalizeMedicineName(medicineName)];

    if (!match) {
        row.generic_name = "";
        return;
    }

    row.generic_name = match.generic_name ?? "";
    row.dose = match.dose ?? "";
    row.duration = match.duration ?? "";
    row.frequency = match.frequency ?? "";
    row.instructions = match.instructions ?? "";
};

const onMedicineInput = (index) => {
    const row = form.medicines[index];
    if (!row) {
        return;
    }

    row.generic_name = "";
    validateMedicineDuplicates();
    activeMedicineRowIndex.value = index;
    searchMedicine(row.medicine_name, index);
};

const onMedicineChange = (index) => {
    const row = form.medicines[index];
    if (!row) {
        return;
    }

    if (!validateMedicineDuplicates()) return;
    applyMedicineDefaults(index, row.medicine_name);
};

const searchMedicine = (query, rowIndex = null) => {
    if (medicineSearchTimer) {
        clearTimeout(medicineSearchTimer);
    }

    const requestId = ++medicineSearchRequest;
    const term = String(query ?? "").trim();
    if (term.length < 2) {
        medicineSuggestions.value = [];
        medicineSearchError.value = "";
        medicineSearching.value = false;
        medicineSuggestionsVisible.value = false;
        return;
    }

    activeMedicineRowIndex.value = rowIndex;
    medicineSuggestionsVisible.value = true;
    medicineSuggestions.value = [];
    medicineHighlightedIndex.value = 0;
    medicineSearchError.value = "";
    medicineSearching.value = true;
    medicineSearchTimer = setTimeout(async () => {
        try {
            const response = await fetch(route("backend.ipdpatient.prescription.medicines.suggest", { q: term }), {
                headers: { Accept: "application/json" },
            });
            if (!response.ok) {
                throw new Error(`Medicine search failed (${response.status}).`);
            }

            const data = await response.json();
            if (requestId !== medicineSearchRequest) return;
            medicineSuggestions.value = normalizeMedicineResults(data?.results);
            rememberMedicineSuggestions(medicineSuggestions.value);
            medicineHighlightedIndex.value = 0;
            medicineSearching.value = false;
            if (rowIndex !== null) {
                applyMedicineDefaults(rowIndex, form.medicines[rowIndex]?.medicine_name);
            }
            await nextTick();
            updateMedicineDropdownPosition();
        } catch (error) {
            if (requestId !== medicineSearchRequest) return;
            medicineSuggestions.value = [];
            medicineSearching.value = false;
            medicineSearchError.value = "Medicine search is unavailable. Please try again.";
        }
    }, 250);
};

const updateMedicineDropdownPosition = () => {
    const index = activeMedicineRowIndex.value;
    const input = index === null ? null : medicineInputs.value[index];
    if (!input) return;

    const rect = input.getBoundingClientRect();
    medicineDropdownStyle.value = {
        left: `${rect.left}px`,
        top: `${rect.bottom + 4}px`,
        width: `${rect.width}px`,
    };
};

const setMedicineSuggestionElement = (element, index) => {
    medicineSuggestionElements.value[index] = element;
};

const searchTest = (query) => {
    if (testSearchTimer) {
        clearTimeout(testSearchTimer);
    }

    const term = String(query ?? "").trim();
    if (term.length < 2) {
        testSuggestions.value = [];
        return;
    }

    testSearchTimer = setTimeout(() => {
        const url = route("backend.itemcharge.search", { q: term });
        fetchSuggestions(url, testSuggestions);
    }, 250);
};

// Handlers for per-row test suggestion UI
const onTestInput = (index) => {
    const term = String(form.tests[index] ?? '').trim();
    activeTestRowIndex.value = index;
    if (term.length < 2) {
        testSuggestions.value = [];
        suggestionsVisible.value = false;
        return;
    }

    suggestionsVisible.value = true;
    searchTest(term);
};

const onTestKeydown = (index, event) => {
    const list = testSuggestions.value || [];
    if (!suggestionsVisible.value || !list.length) {
        if (event.key === 'Enter') {
            event.preventDefault();
            if (index + 1 >= form.tests.length) addTestRow();
            nextTick(() => { testInputs.value[index + 1]?.focus(); });
        }
        return;
    }

    if (event.key === 'ArrowDown') {
        highlightedIndex.value = (highlightedIndex.value + 1) % list.length;
        event.preventDefault();
    } else if (event.key === 'ArrowUp') {
        highlightedIndex.value = (highlightedIndex.value - 1 + list.length) % list.length;
        event.preventDefault();
    } else if (event.key === 'Enter') {
        event.preventDefault();
        const item = list[highlightedIndex.value];
        const name = typeof item === 'string' ? item : (item?.test_name ?? item?.name ?? '');
        if (name) {
            form.tests[index] = name;
            suggestionsVisible.value = false;
            if (index + 1 >= form.tests.length) addTestRow();
            nextTick(() => { testInputs.value[index + 1]?.focus(); });
        }
    } else if (event.key === 'Escape') {
        suggestionsVisible.value = false;
    }
};

const selectTestSuggestion = (index, item) => {
    const name = typeof item === 'string' ? item : (item?.test_name ?? item?.name ?? '');
    if (!name) return;
    form.tests[index] = name;
    suggestionsVisible.value = false;
    if (index + 1 >= form.tests.length) addTestRow();
    nextTick(() => { testInputs.value[index + 1]?.focus(); });
};

const onTestBlur = (index) => {
    // Delay hiding to allow click on suggestion
    setTimeout(() => {
        suggestionsVisible.value = false;
        activeTestRowIndex.value = null;
    }, 150);
};

// Medicine handlers
const onMedicineFocus = (index) => {
    activeMedicineRowIndex.value = index;
    if (String(form.medicines[index]?.medicine_name ?? "").trim().length >= 2) {
        searchMedicine(form.medicines[index].medicine_name, index);
    } else if (Array.isArray(medicineSuggestions.value) && medicineSuggestions.value.length > 0) {
        medicineSuggestionsVisible.value = true;
    }
    updateMedicineDropdownPosition();
};

const onMedicineBlur = (index) => {
    setTimeout(() => {
        if (activeMedicineRowIndex.value !== index) return;
        medicineSuggestionsVisible.value = false;
        activeMedicineRowIndex.value = null;
    }, 150);
};

const onMedicineKeydown = (index, event) => {
    const list = medicineSuggestions.value || [];
    if (event.key === 'ArrowDown' && medicineSuggestionsVisible.value && list.length) {
        medicineHighlightedIndex.value = (medicineHighlightedIndex.value + 1) % list.length;
        event.preventDefault();
        nextTick(() => medicineSuggestionElements.value[medicineHighlightedIndex.value]?.scrollIntoView({ block: "nearest" }));
    } else if (event.key === 'ArrowUp' && medicineSuggestionsVisible.value && list.length) {
        medicineHighlightedIndex.value = (medicineHighlightedIndex.value - 1 + list.length) % list.length;
        event.preventDefault();
        nextTick(() => medicineSuggestionElements.value[medicineHighlightedIndex.value]?.scrollIntoView({ block: "nearest" }));
    } else if (event.key === 'Escape') {
        medicineSearchRequest++;
        if (medicineSearchTimer) clearTimeout(medicineSearchTimer);
        medicineSearching.value = false;
        medicineSuggestionsVisible.value = false;
        medicineSuggestions.value = [];
    }
};

const onMedicineEnter = async (index) => {
    const list = medicineSuggestions.value || [];
    if (medicineSuggestionsVisible.value && list.length > 0) {
        const pick = Math.max(0, medicineHighlightedIndex.value);
        if (list[pick]) {
            await selectMedicineSuggestion(index, list[pick]);
            return;
        }
    }

    if (index + 1 >= form.medicines.length) {
        await addMedicineRow();
        return;
    }
    await nextTick();
    medicineInputs.value[index + 1]?.focus();
    medicineInputs.value[index + 1]?.select();
};

const selectMedicineSuggestion = async (index, item) => {
    const name = typeof item === 'string' ? item : (item?.name ?? item?.medicine_name ?? '');
    if (!name) return;
    const normalizedName = normalizeMedicineName(name);
    if (form.medicines.some((row, rowIndex) =>
        rowIndex !== index && normalizeMedicineName(row?.medicine_name) === normalizedName
    )) {
        medicineDuplicateError.value = `${name} is already added to this prescription.`;
        return;
    }
    medicineDuplicateError.value = "";
    form.medicines[index].medicine_name = name;
    applyMedicineDefaults(index, name);
    medicineSearchRequest++;
    if (medicineSearchTimer) clearTimeout(medicineSearchTimer);
    medicineSuggestionsVisible.value = false;
    medicineSearching.value = false;
    medicineSearchError.value = "";
    // After selecting, move to next row (single-Enter selects and advances)
    const next = index + 1;
    if (form.medicines[next] !== undefined) {
        await nextTick();
        medicineInputs.value[next]?.focus();
        medicineInputs.value[next]?.select();
    } else {
        await addMedicineRow();
        medicineInputs.value[index + 1]?.focus();
        medicineInputs.value[index + 1]?.select();
    }
};

onMounted(() => {
    window.addEventListener("scroll", updateMedicineDropdownPosition, true);
    window.addEventListener("resize", updateMedicineDropdownPosition);
});

onBeforeUnmount(() => {
    window.removeEventListener("scroll", updateMedicineDropdownPosition, true);
    window.removeEventListener("resize", updateMedicineDropdownPosition);
    if (medicineSearchTimer) clearTimeout(medicineSearchTimer);
});

const savePrescription = (sendToPharmacy = false) => {
    saveError.value = "";
    if (!validateMedicineDuplicates()) {
        saveError.value = medicineDuplicateError.value;
        return;
    }
    form.transform((data) => ({
        ...data,
        temperature: data.temperature ? `${data.temperature} °${temperatureUnit.value}` : "",
    }));

    form.post(route("backend.ipdpatient.prescription.store", props.ipdpatient.id), {
        preserveScroll: true,
        onSuccess: (response) => {
            displayResponse(response);
            if (sendToPharmacy) {
                const prescriptionId = response?.props?.prescription?.id ?? props.prescription?.id;
                if (prescriptionId) {
                    navigateToPharmacy(prescriptionId);
                } else {
                    saveError.value = "Prescription saved, but its ID could not be loaded. Please refresh and try again.";
                }
            }
        },
        onError: (errors) => {
            const firstError = Object.values(errors ?? {})[0];
            saveError.value = firstError || "Failed to save prescription.";
        },
    });
};

const printPrescription = () => {
    const url = route("backend.ipdpatient.prescription.print", props.ipdpatient.id);
    try { window.open(url, '_blank'); } catch (e) { window.open(url, '_blank'); }
};

const downloadPrescriptionPdf = () => {
    const url = route("backend.ipdpatient.prescription.pdf", props.ipdpatient.id);
    try { window.open(url, '_blank'); } catch (e) { window.open(url, '_blank'); }
};
</script>

<template>
    <BackendLayout>
        <div class="prescription-workspace w-full p-2 rounded-md">
            <div class="mb-3 flex items-center justify-between no-print">
                <h1 class="text-xl font-bold dark:text-white">IPD Prescription</h1>
                <div class="flex items-center gap-3">
                    <div class="text-xs text-gray-600 dark:text-gray-300">
                        {{ props.ipdpatient?.patient?.name ?? "" }} (IPD: {{ props.ipdpatient?.id ?? "" }})
                    </div>
                    <button
                        type="button"
                        class="px-3 py-1.5 text-xs bg-blue-600 text-white rounded"
                        @click="downloadPrescriptionPdf"
                    >
                        Download PDF
                    </button>
                    <button
                        type="button"
                        class="px-3 py-1.5 text-xs bg-emerald-600 text-white rounded"
                        @click="printPrescription"
                    >
                        Print
                    </button>
                </div>
            </div>

            <div class="prescription-form-panel border border-gray-200 rounded-md p-3 dark:border-gray-700">
                <!-- successMessage displayed via toast; inline success removed -->
                <div v-if="$page.props.flash?.errorMessage"
                    class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                    {{ $page.props.flash.errorMessage }}
                </div>

                <div v-if="saveError" class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                    {{ saveError }}
                </div>

                <div class="mb-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <InputLabel value="Patient" class="text-xs mb-1" />
                        <div class="flex min-h-[38px] items-center rounded border border-gray-300 px-2 text-xs dark:border-gray-600 dark:bg-slate-700 dark:text-gray-200">
                            {{ props.ipdpatient?.patient?.name ?? "IPD Patient" }}
                            <span class="ml-1 text-gray-500"> (IPD: {{ props.ipdpatient?.id ?? "" }})</span>
                        </div>
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
                            <option v-for="doctor in props.doctors ?? []" :key="doctor.id" :value="doctor.id">{{ doctor.name }}</option>
                        </select>
                        <p v-if="form.errors.doctor_id" class="mt-1 text-xs text-red-600">{{ form.errors.doctor_id }}</p>
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
                        <input v-model="form.complaints" type="text" class="w-full px-2 py-1.5 border rounded text-xs dark:bg-slate-700" placeholder="Chief complaints" />
                        <p v-if="form.errors.complaints" class="mt-1 text-xs text-red-600">{{ form.errors.complaints }}</p>
                    </div>
                    <div>
                        <InputLabel value="Diagnosis" class="text-xs mb-1" />
                        <input v-model="form.diagnosis" type="text" class="w-full px-2 py-1.5 border rounded text-xs dark:bg-slate-700" placeholder="Diagnosis" />
                        <p v-if="form.errors.diagnosis" class="mt-1 text-xs text-red-600">{{ form.errors.diagnosis }}</p>
                    </div>
                </div>

                <div class="mt-3">
                    <InputLabel value="On Examinations" class="text-xs mb-1" />
                    <textarea v-model="form.examinations" rows="1" class="w-full px-2 py-1.5 border rounded text-xs dark:bg-slate-700" placeholder="Enter examination findings on separate lines"></textarea>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <div class="mb-2 text-xs font-semibold text-gray-700 dark:text-gray-200">Medicines</div>
                    <p v-if="medicineDuplicateError" class="mb-2 text-xs text-red-600">{{ medicineDuplicateError }}</p>
                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr class="bg-gray-100 text-gray-700 dark:bg-slate-800 dark:text-gray-200">
                                <th class="px-2 py-2 border">Medicine</th>
                                <th class="px-2 py-2 border">Qty</th>
                                <th class="px-2 py-2 border">Dose</th>
                                <th class="px-2 py-2 border">Frequency</th>
                                <th class="px-2 py-2 border">Duration</th>
                                <th class="px-2 py-2 border">Instructions</th>
                                <th class="px-2 py-2 border text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in form.medicines" :key="index">
                                <td class="px-2 py-2 border">
                                    <div class="relative">
                                        <input
                                            v-model="row.medicine_name"
                                            type="text"
                                            :ref="el => setMedicineInput(el, index)"
                                            class="w-full px-2 py-1 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                                            placeholder="Medicine name"
                                            @input="onMedicineInput(index)"
                                            @change="onMedicineChange(index)"
                                            @focus="onMedicineFocus(index)"
                                            @blur="onMedicineBlur(index)"
                                            @keydown.enter.prevent="onMedicineEnter(index)"
                                            @keydown="onMedicineKeydown(index, $event)"
                                            autocomplete="off"
                                        />
                                        <p v-if="row.generic_name" class="mt-1 text-[11px] font-medium text-emerald-700">
                                            ({{ row.generic_name }})
                                        </p>

                                    </div>
                                </td>
                                <td class="px-2 py-2 border">
                                    <input v-model.number="row.quantity" type="number" min="1" step="1"
                                        class="w-20 px-2 py-1 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                                        aria-label="Prescribed quantity" />
                                    <p v-if="form.errors[`medicines.${index}.quantity`]" class="mt-1 text-[10px] text-red-600">
                                        {{ form.errors[`medicines.${index}.quantity`] }}
                                    </p>
                                </td>
                                <td class="px-2 py-2 border">
                                    <input v-model="row.dose" type="text"
                                        :list="medicineOptionsListId(index, 'dose')"
                                        @input="rememberMedicineField(row, 'dose')"
                                        class="w-full px-2 py-1 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                                        placeholder="e.g. 1 tab" />
                                    <datalist :id="medicineOptionsListId(index, 'dose')">
                                        <option v-for="option in medicineOptionsFor(row, 'dose')" :key="option" :value="option" />
                                    </datalist>
                                </td>
                                <td class="px-2 py-2 border">
                                    <input v-model="row.frequency" type="text"
                                        :list="medicineOptionsListId(index, 'frequency')"
                                        @input="rememberMedicineField(row, 'frequency')"
                                        class="w-full px-2 py-1 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                                        placeholder="e.g. 1-0-1" />
                                    <datalist :id="medicineOptionsListId(index, 'frequency')">
                                        <option v-for="option in medicineOptionsFor(row, 'frequency')" :key="option" :value="option" />
                                    </datalist>
                                </td>
                                <td class="px-2 py-2 border">
                                    <input v-model="row.duration" type="text"
                                        :list="medicineOptionsListId(index, 'duration')"
                                        @input="rememberMedicineField(row, 'duration')"
                                        class="w-full px-2 py-1 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                                        placeholder="e.g. 7 days" />
                                    <datalist :id="medicineOptionsListId(index, 'duration')">
                                        <option v-for="option in medicineOptionsFor(row, 'duration')" :key="option" :value="option" />
                                    </datalist>
                                </td>
                                <td class="px-2 py-2 border">
                                    <input v-model="row.instructions" type="text"
                                        :list="medicineOptionsListId(index, 'instructions')"
                                        @input="rememberMedicineField(row, 'instructions')"
                                        class="w-full px-2 py-1 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                                        placeholder="After meal" />
                                    <datalist :id="medicineOptionsListId(index, 'instructions')">
                                        <option v-for="option in medicineOptionsFor(row, 'instructions')" :key="option" :value="option" />
                                    </datalist>
                                </td>
                                <td class="px-2 py-2 border text-center">
                                    <button type="button" class="px-2 py-1 text-xs bg-red-500 text-white rounded"
                                        @click="removeMedicineRow(index)">
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <Teleport to="body">
                        <div
                            v-if="activeMedicineRowIndex !== null && medicineSuggestionsVisible"
                            :style="medicineDropdownStyle"
                            class="fixed z-[9999] max-h-40 overflow-auto rounded border border-gray-300 bg-white text-xs shadow-lg dark:border-gray-600 dark:bg-slate-800"
                        >
                            <div v-if="medicineSearching" class="px-2 py-1.5 text-slate-500">Searching medicines...</div>
                            <div v-else-if="medicineSearchError" class="px-2 py-1.5 text-red-600">{{ medicineSearchError }}</div>
                            <div v-else-if="medicineSuggestions.length === 0" class="px-2 py-1.5 text-slate-500">No medicines found.</div>
                            <button
                                v-for="(item, sIndex) in medicineSuggestions"
                                :key="`${item.name}-${sIndex}`"
                                :ref="el => setMedicineSuggestionElement(el, sIndex)"
                                type="button"
                                class="!block !w-full !px-2 !py-1.5 !text-left"
                                :class="sIndex === medicineHighlightedIndex ? '!bg-teal-100 !text-teal-900' : '!bg-white !text-slate-800 hover:!bg-slate-100 dark:!bg-slate-800 dark:!text-slate-100 dark:hover:!bg-slate-700'"
                                @mousedown.prevent="selectMedicineSuggestion(activeMedicineRowIndex, item)"
                                @mouseenter="medicineHighlightedIndex = sIndex"
                            >
                                <template v-for="(part, partIndex) in medicineNameParts(item)" :key="partIndex">
                                    <mark v-if="part.matched" class="rounded bg-yellow-200 px-0.5 font-semibold text-slate-900">{{ part.text }}</mark>
                                    <span v-else>{{ part.text }}</span>
                                </template>
                                <span v-if="item.generic_name" class="ml-1 text-[10px] text-slate-500">({{ item.generic_name }})</span>
                            </button>
                        </div>
                    </Teleport>

                    <!-- per-row medicine dropdown replaces datalist -->

                    <div class="mt-3">
                        <button type="button" class="px-3 py-1.5 text-xs bg-gray-600 text-white rounded"
                            @click="addMedicineRow">
                            Add Medicine
                        </button>
                    </div>
                </div>

                <div class="mt-4 border border-gray-200 rounded-md p-2 dark:border-gray-700">
                    <div class="mb-2 text-xs font-semibold text-gray-700 dark:text-gray-200">Recommended Tests / Items</div>

                    <div class="space-y-1.5">
                        <div v-for="(testName, index) in form.tests" :key="`test-${index}`" class="flex items-start gap-2">
                            <div class="h-5 min-w-[20px] px-1 inline-flex items-center justify-center rounded bg-gray-100 text-[11px] font-semibold text-gray-700 dark:bg-slate-700 dark:text-gray-200">
                                {{ index + 1 }}
                            </div>

                            <div class="relative w-full">
                                    <input
                                    v-model="form.tests[index]"
                                    type="text"
                                    :ref="el => testInputs.value[index] = el"
                                    class="w-full px-2 py-1 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                                    placeholder="Item name"
                                    @input="onTestInput(index)"
                                    @keydown="onTestKeydown(index, $event)"
                                    @blur="onTestBlur(index)"
                                    @focus="activeTestRowIndex = index; if (String(form.tests[index] ?? '').trim().length >= 2) { suggestionsVisible = true; searchTest(form.tests[index]); }"
                                />

                                <div v-if="activeTestRowIndex === index && suggestionsVisible && flattenTestSuggestions.length" class="absolute z-50 left-0 right-0 mt-1 bg-white border rounded shadow max-h-48 overflow-auto text-xs">
                                    <div v-for="(items, group) in groupedTestSuggestions" :key="group">
                                        <div class="px-2 py-1 text-xs font-semibold bg-gray-100 text-gray-700">{{ group }}</div>
                                        <div v-for="(item, sIndex) in items" :key="group + '-' + sIndex" @mousedown.prevent="selectTestSuggestion(index, item)" @mouseenter="highlightedIndex = flattenTestSuggestions.indexOf(item)" :class="flattenTestSuggestions.indexOf(item) === highlightedIndex ? 'bg-blue-100' : ''" class="px-2 py-1 cursor-pointer">
                                            {{ typeof item === 'string' ? item : (item.test_name ?? item.name ?? '') }}
                                            <span v-if="typeof item !== 'string' && (item.amount || item.standard_charge)" class="ml-2 text-xs text-gray-500">– ৳{{ item.amount ?? item.standard_charge }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-1">
                                <button type="button" class="px-2 py-1 text-xs bg-red-500 text-white rounded" @click="removeTestRow(index)">
                                    Remove
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-2">
                        <button type="button" class="px-3 py-1.5 text-xs bg-gray-600 text-white rounded" @click="addTestRow">
                            Add Item
                        </button>
                    </div>

                    <!-- testSuggestions dropdown rendered per-row above; removed datalist -->
                </div>

                <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <InputLabel value="Advice" class="text-xs mb-1" />
                        <textarea v-model="form.advice" rows="3" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" placeholder="Advice"></textarea>
                        <p v-if="form.errors.advice" class="mt-1 text-xs text-red-600">{{ form.errors.advice }}</p>
                    </div>
                    <div>
                        <InputLabel value="Follow Up Date" class="text-xs mb-1" />
                        <input v-model="form.follow_up_date" type="date" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" />
                        <p v-if="form.errors.follow_up_date" class="mt-1 text-xs text-red-600">{{ form.errors.follow_up_date }}</p>
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div>
                        <InputLabel value="Seal Upload" class="text-xs mb-1" />
                        <div v-if="doctorSealPreview" class="mb-2">
                            <img :src="doctorSealPreview" alt="Doctor Seal Preview" class="h-16 w-auto border border-gray-200 rounded bg-white p-1" />
                        </div>
                        <input type="file" accept="image/*" @change="handleDoctorSealChange" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" />
                        <p v-if="form.errors.doctor_seal" class="mt-1 text-xs text-red-600">{{ form.errors.doctor_seal }}</p>
                    </div>
                    <div>
                        <InputLabel value="Signature Upload" class="text-xs mb-1" />
                        <div v-if="doctorSignaturePreview" class="mb-2">
                            <img :src="doctorSignaturePreview" alt="Doctor Signature Preview" class="h-16 w-auto border border-gray-200 rounded bg-white p-1" />
                        </div>
                        <input type="file" accept="image/*" @change="handleDoctorSignatureChange" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" />
                        <p v-if="form.errors.doctor_signature" class="mt-1 text-xs text-red-600">{{ form.errors.doctor_signature }}</p>
                    </div>
                    <div>
                        <InputLabel value="Doctor Designation" class="text-xs mb-1" />
                        <textarea v-model="form.doctor_designation" rows="3" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" placeholder="e.g. Consultant Medicine&#10;MBBS, FCPS"></textarea>
                        <p v-if="form.errors.doctor_designation" class="mt-1 text-xs text-red-600">{{ form.errors.doctor_designation }}</p>
                    </div>
                </div>

                <div class="flex items-center justify-end mt-3">
                    <button type="button" class="mr-2 px-4 py-1.5 text-xs bg-teal-700 text-white rounded disabled:opacity-60"
                        :disabled="form.processing || !props.prescription?.id" @click="openPharmacyBill">
                        Send to Pharmacy
                    </button>
                    <button type="button" class="px-4 py-1.5 text-xs bg-blue-600 text-white rounded disabled:opacity-60"
                        :disabled="form.processing" @click="savePrescription">
                        {{ form.processing ? "Saving..." : "Save Prescription" }}
                    </button>
                </div>
            </div>

            <section class="mt-4 rounded-md border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-slate-800 dark:text-white">Pharmacy Dispensing & Patient History</h2>
                    <span class="text-xs text-slate-500">
                        Patient ID: {{ props.ipdpatient?.patient_id }} · IPD ID: {{ props.ipdpatient?.id }}
                        · Bed: {{ props.ipdpatient?.bed?.name || "N/A" }}
                    </span>
                </div>
                <div v-if="props.pharmacyDueSummary?.invoice_count" class="mb-3 grid gap-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-slate-700 sm:grid-cols-3 dark:border-amber-900 dark:bg-amber-950/30 dark:text-slate-200">
                    <div>
                        <span class="block text-slate-500 dark:text-slate-400">IPD Pharmacy Invoices</span>
                        <strong>{{ props.pharmacyDueSummary.invoice_count }}</strong>
                    </div>
                    <div>
                        <span class="block text-slate-500 dark:text-slate-400">Medicine Bill Total</span>
                        <strong>৳ {{ Number(props.pharmacyDueSummary.medicine_total || 0).toFixed(2) }}</strong>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <span class="block text-slate-500 dark:text-slate-400">Medicine Due (All Invoices)</span>
                            <strong class="text-amber-700 dark:text-amber-300">৳ {{ Number(props.pharmacyDueSummary.due_amount || 0).toFixed(2) }}</strong>
                        </div>
                        <button
                            v-if="props.pharmacyDueSummary.collect_url"
                            type="button"
                            class="rounded bg-emerald-600 px-3 py-2 font-semibold text-white hover:bg-emerald-700"
                            @click="openPharmacyDueModal"
                        >
                            Collect Total Due
                        </button>
                    </div>
                </div>
                <div v-if="!props.pharmacyHistory?.length" class="py-3 text-xs text-slate-500">
                    No pharmacy bills found for this patient.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[760px] border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-100 text-left dark:bg-slate-800 dark:text-slate-200">
                                <th class="border px-2 py-2">Date / Admission</th>
                                <th class="border px-2 py-2">Pharmacy Bill</th>
                                <th class="border px-2 py-2">Medicines</th>
                                <th class="border px-2 py-2 text-right">Total</th>
                                <th class="border px-2 py-2">Paid / Due</th>
                                <th class="border px-2 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="bill in props.pharmacyHistory" :key="bill.id">
                                <td class="border px-2 py-2">
                                    {{ bill.date }}
                                    <span class="block text-[10px] text-slate-500">{{ bill.is_current_admission ? `This IPD #${props.ipdpatient?.id}` : `Patient #${props.ipdpatient?.patient_id} history` }}</span>
                                </td>
                                <td class="border px-2 py-2">{{ bill.pharmacy_no }}<span class="block text-slate-500">{{ bill.bill_no }}</span></td>
                                <td class="border px-2 py-2">
                                    <span v-for="(item, index) in pharmacyProducts(bill)" :key="`${bill.id}-${index}`" class="mr-2 inline-block">
                                        {{ item.productName }} × {{ item.quantity }}
                                    </span>
                                </td>
                                <td class="border px-2 py-2 text-right">৳ {{ Number(bill.net_amount || 0).toFixed(2) }}</td>
                                <td class="border px-2 py-2">
                                    <template v-if="bill.is_current_admission">
                                        Paid at dispense ৳ {{ Math.max(Number(bill.payment_amount || 0) - Number(bill.return_amount || 0), 0).toFixed(2) }}
                                        <span class="block text-amber-700">Medicine due shown above</span>
                                    </template>
                                    <template v-else>
                                        Paid ৳ {{ Number(bill.billing?.paid_amt || 0).toFixed(2) }}
                                        <span class="block text-amber-700">Due ৳ {{ Number(bill.billing?.due_amount || 0).toFixed(2) }}</span>
                                    </template>
                                </td>
                                <td class="border px-2 py-2">
                                    {{ bill.is_current_admission ? "Included in medicine due" : (bill.billing?.payment_status === "Pending" ? "Due" : (bill.billing?.payment_status || "Due")) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    <h3 class="mb-2 text-xs font-semibold text-slate-700 dark:text-slate-200">Payment and due collection history</h3>
                    <div v-if="!pharmacyFinancialHistory.length" class="py-2 text-xs text-slate-500">
                        No pharmacy-related payments or due collections found.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[620px] border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-100 text-left dark:bg-slate-800 dark:text-slate-200">
                                    <th class="border px-2 py-2">Date</th>
                                    <th class="border px-2 py-2">Bill No</th>
                                    <th class="border px-2 py-2">Type</th>
                                    <th class="border px-2 py-2">Method</th>
                                    <th class="border px-2 py-2 text-right">Amount</th>
                                    <th class="border px-2 py-2">Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="entry in pharmacyFinancialHistory" :key="entry.id">
                                    <td class="border px-2 py-2">{{ entry.date ? new Date(entry.date).toLocaleString() : "N/A" }}</td>
                                    <td class="border px-2 py-2">{{ entry.bill }}</td>
                                    <td class="border px-2 py-2">{{ entry.type }}</td>
                                    <td class="border px-2 py-2">{{ entry.method }}</td>
                                    <td class="border px-2 py-2 text-right">৳ {{ Number(entry.amount || 0).toFixed(2) }}</td>
                                    <td class="border px-2 py-2">{{ entry.note }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <div v-if="showPharmacyDueModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <form class="w-full max-w-md rounded-lg bg-white p-5 shadow-xl dark:bg-slate-900" @submit.prevent="submitPharmacyDue">
                <h3 class="mb-4 text-base font-semibold text-slate-800 dark:text-white">Collect IPD Medicine Due</h3>
                <p class="mb-3 text-sm text-slate-600 dark:text-slate-300">
                    Total due across {{ props.pharmacyDueSummary?.invoice_count || 0 }} pharmacy invoices:
                    <strong>৳ {{ Number(props.pharmacyDueSummary?.due_amount || 0).toFixed(2) }}</strong>
                </p>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Payment Method</label>
                <select v-model="pharmacyDueForm.payment_method" class="mb-3 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                    <option value="Cash">Cash</option>
                    <option value="Card">Card</option>
                    <option value="Bank">Bank</option>
                    <option value="Mobile Banking">Mobile Banking</option>
                </select>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Amount</label>
                <input
                    v-model="pharmacyDueForm.amount"
                    type="number"
                    min="0.01"
                    :max="props.pharmacyDueSummary?.due_amount || 0"
                    step="0.01"
                    required
                    class="w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                />
                <p v-if="pharmacyDueForm.errors.amount" class="mt-1 text-xs text-red-600">{{ pharmacyDueForm.errors.amount }}</p>
                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 disabled:opacity-60 dark:bg-slate-700 dark:text-slate-200"
                        :disabled="pharmacyDueForm.processing || isSubmittingPharmacyDue"
                        @click="closePharmacyDueModal"
                    >Cancel</button>
                    <button
                        type="submit"
                        class="rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                        :disabled="pharmacyDueForm.processing || isSubmittingPharmacyDue"
                    >{{ pharmacyDueForm.processing ? "Collecting..." : "Collect Due" }}</button>
                </div>
            </form>
        </div>
    </BackendLayout>
</template>
