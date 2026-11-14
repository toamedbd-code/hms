<script setup>
import { ref, reactive, nextTick, onMounted, onBeforeUnmount } from "vue";
import BackendLayout from "@/Layouts/BackendLayout.vue";
import InputLabel from "@/Components/InputLabel.vue";
import { useForm } from "@inertiajs/vue3";
import { displayResponse } from "@/responseMessage.js";

const APP_TIMEZONE = "Asia/Dhaka";

const formatCurrentDate = () => {
    return new Intl.DateTimeFormat("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        timeZone: APP_TIMEZONE,
    }).format(new Date());
};

const props = defineProps({
    opdpatient: Object,
    prescription: Object,
});

const saveError = ref("");
const doctorSignaturePreview = ref(props.prescription?.doctor_signature_url ?? "");
const doctorSealPreview = ref(props.prescription?.doctor_seal_url ?? "");
const medicineSuggestions = ref([]);
const medicineMemoryByName = ref({});
const medicineDuplicateError = ref("");
const testSuggestions = ref([]);
// Medicine dropdown refs/state (align with IPD implementation)
const medicineInputs = ref([]);
const activeMedicineRowIndex = ref(null);
const medicineSuggestionsVisible = ref(false);
const medicineHighlightedIndex = ref(0);
let medicineSearchTimer = null;
const testSearchTimers = {};
// Track whether dropdown should open above the input for each row
const medicineDropdownAbove = reactive({});
const medicineDropdownAttempts = reactive({});
const medicineDropdownCoords = reactive({});

const getScrollParent = (node) => {
    if (typeof window === 'undefined' || !node) return window;
    let parent = node.parentElement;
    while (parent) {
        try {
            const style = window.getComputedStyle(parent);
            const oy = style.overflowY;
            if ((oy === 'auto' || oy === 'scroll') && parent.scrollHeight > parent.clientHeight) {
                return parent;
            }
        } catch (e) {
            // ignore
        }
        parent = parent.parentElement;
    }
    return document.scrollingElement || window;
};

const computeMedicineDropdownPosition = (rowIndex) => {
    nextTick(() => {
        try {
            if (typeof document === 'undefined') return;
            const input = medicineInputs.value?.[rowIndex];
            if (!input) {
                medicineDropdownAttempts[rowIndex] = (medicineDropdownAttempts[rowIndex] || 0) + 1;
                if (medicineDropdownAttempts[rowIndex] <= 6) {
                    setTimeout(() => computeMedicineDropdownPosition(rowIndex), 80);
                }
                return;
            }

            const padding = 8;
            const estimatedHeight = 240;
            const inputRect = input.getBoundingClientRect();
            const left = (window.pageXOffset || document.documentElement.scrollLeft) + inputRect.left;
            const width = Math.max(180, inputRect.width || 200);

            // Always position above the input (user requested unconditional above)
            let top = (window.pageYOffset || document.documentElement.scrollTop) + inputRect.top - estimatedHeight - padding;
            if (top < 8) top = 8;

            medicineDropdownCoords[rowIndex] = { top, left, width };
            medicineDropdownAbove[rowIndex] = true;

            // Try to ensure there's room by centering input in its scroll parent if too close to top
            const scrollParent = getScrollParent(input);
            const viewportSpaceAbove = inputRect.top;
            if (viewportSpaceAbove < 80) {
                try {
                    input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } catch (e) {
                    if (scrollParent && scrollParent !== window) {
                        const parentRect = scrollParent.getBoundingClientRect();
                        const inputTopInParent = inputRect.top - parentRect.top + scrollParent.scrollTop;
                        const desired = Math.max(0, inputTopInParent - Math.floor(scrollParent.clientHeight / 2));
                        try { scrollParent.scrollTo({ top: desired, behavior: 'smooth' }); } catch (e2) { scrollParent.scrollTop = desired; }
                    } else {
                        const viewportTop = (window.pageYOffset || document.documentElement.scrollTop);
                        const delta = inputRect.top - Math.floor(window.innerHeight / 2);
                        try { window.scrollBy({ top: delta, behavior: 'smooth' }); } catch (e3) { window.scrollTo({ top: viewportTop + delta }); }
                    }
                }
            }

            // After dropdown is rendered in body, read actual height and adjust top
            setTimeout(() => {
                try {
                    const portal = document.querySelector(`#medicineDropdownPortal_${rowIndex}`);
                    if (!portal) return;
                    const actualHeight = portal.offsetHeight || estimatedHeight;
                    let newTop = (window.pageYOffset || document.documentElement.scrollTop) + inputRect.top - actualHeight - padding;
                    if (newTop < 8) {
                        const extraUp = 8 - newTop;
                        try { window.scrollBy({ top: -extraUp, left: 0, behavior: 'smooth' }); } catch (e) { window.scrollTo({ top: Math.max(0, (window.pageYOffset || document.documentElement.scrollTop) - extraUp) }); }
                        newTop = 8;
                    }
                    medicineDropdownCoords[rowIndex] = { top: newTop, left, width };
                } catch (e) {
                    // ignore
                }
            }, 120);
        } catch (e) {
            // ignore
        }
    });
};

const recomputeActiveDropdown = () => {
    const idx = activeMedicineRowIndex.value;
    if (idx === null || idx === undefined) return;
    computeMedicineDropdownPosition(idx);
};

onMounted(() => {
    if (typeof window !== 'undefined') {
        window.addEventListener('resize', recomputeActiveDropdown);
        window.addEventListener('scroll', recomputeActiveDropdown, true);
    }
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', recomputeActiveDropdown);
        window.removeEventListener('scroll', recomputeActiveDropdown, true);
    }
});

// Per-row suggestions and dropdown state for Recommended Tests
const testSuggestionsMap = reactive({});
const focusedTestIndex = ref(-1);
const showTestDropdown = ref(false);
const highlightedIndex = ref(-1);

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
    notes: props.prescription?.notes ?? "",
    nibp: props.opdpatient?.nibp ?? "",
    doctor_designation: props.prescription?.doctor_designation ?? props.opdpatient?.consultation_type ?? "",
    items: props.prescription?.items?.length
        ? uniqueMedicineRows(props.prescription.items
              .filter((item) => {
                  const medicineName = String(item?.medicine_name ?? "").trim().toUpperCase();
                  return medicineName !== "" && medicineName !== "N/A";
              })
              .map((item) => ({
              test_name: item.test_name ?? "",
              medicine_name: item.medicine_name ?? "",
              generic_name: item.generic_name ?? "",
              dose: item.dose ?? "",
              duration: item.duration ?? "",
              frequency: item.frequency ?? "",
              instructions: item.instructions ?? "",
          })))
        : [
              {
                  test_name: "",
                  medicine_name: "",
                  generic_name: "",
                  dose: "",
                  duration: "",
                  frequency: "",
                  instructions: "",
              },
          ],
    tests: props.prescription?.items?.length
        ? Array.from(
              new Set(
                  props.prescription.items
                      .map((item) => String(item?.test_name ?? "").trim())
                      .filter((name) => name !== "" && name.toUpperCase() !== "N/A")
              )
          )
        : [""],
    doctor_seal: null,
    doctor_signature: null,
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

const addRow = () => {
    form.items.push({
        test_name: "",
        medicine_name: "",
        generic_name: "",
        dose: "",
        duration: "",
        frequency: "",
        instructions: "",
    });
    nextTick(() => {
        const newIndex = form.items.length - 1;
        medicineInputs.value[newIndex]?.focus();
        medicineInputs.value[newIndex]?.select();
    });
};

const removeRow = (index) => {
    if (form.items.length === 1) return;
    form.items.splice(index, 1);
    validateMedicineDuplicates();
};

const addTestRow = () => {
    form.tests.push("");
    nextTick(() => {
        const newIndex = form.tests.length - 1;
        const field = (typeof document !== 'undefined') ? document.querySelector(`#testSearch_${newIndex}`) : null;
        if (field) { field.focus(); field.select(); }
    });
};

const removeTestRow = (index) => {
    if (form.tests.length === 1) return;
    form.tests.splice(index, 1);
};

const savePrescription = () => {
    saveError.value = "";
    if (!validateMedicineDuplicates()) {
        saveError.value = medicineDuplicateError.value;
        return;
    }
    form.post(route("backend.opdpatient.prescription.store", props.opdpatient.id), {
        preserveScroll: true,
        onSuccess: (response) => {
            displayResponse(response);
        },
        onError: (errors) => {
            const firstError = Object.values(errors ?? {})[0];
            saveError.value = firstError || "Failed to save prescription.";
        },
    });
};

const printPrescription = () => {
    const url = route("backend.opdpatient.prescription.print", props.opdpatient.id);
    try { window.open(url, '_blank'); } catch (e) { window.open(url, '_blank'); }
};

const downloadPrescriptionPdf = () => {
    const url = route("backend.opdpatient.prescription.pdf", props.opdpatient.id);
    try { window.open(url, '_blank'); } catch (e) { window.open(url, '_blank'); }
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
    } catch (error) {
        target.value = [];
    }
};

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
                    duration_options: [],
                    frequency_options: [],
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
                duration_options: Array.isArray(item?.duration_options) ? item.duration_options : [],
                frequency_options: Array.isArray(item?.frequency_options) ? item.frequency_options : [],
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

const medicineOptionsListId = (index, field) => `opd-${field}-suggestions-${index}`;
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
    const duplicate = form.items.find((row) => {
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

const applyMedicineDefaults = (index, medicineName) => {
    const row = form.items[index];
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
    const row = form.items[index];
    if (!row) {
        return;
    }
    row.generic_name = "";
    validateMedicineDuplicates();
    searchMedicine(row.medicine_name, index);
};

const onMedicineChange = (index) => {
    const row = form.items[index];
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
    const term = String(query ?? "").trim();
    if (term.length < 2) {
        medicineSuggestions.value = [];
        return;
    }
    medicineSearchTimer = setTimeout(async () => {
        const url = route("backend.opdpatient.prescription.medicines.suggest", { q: term });

        try {
            const response = await fetch(url, { headers: { Accept: "application/json" } });
            if (!response.ok) {
                medicineSuggestions.value = [];
                return;
            }

            const data = await response.json();
            medicineSuggestions.value = normalizeMedicineResults(data?.results);
            rememberMedicineSuggestions(medicineSuggestions.value);

            if (rowIndex !== null) {
                // show dropdown for this row
                medicineDropdownAttempts[rowIndex] = 0;
                medicineSuggestionsVisible.value = true;
                activeMedicineRowIndex.value = rowIndex;
                medicineHighlightedIndex.value = 0;
                applyMedicineDefaults(rowIndex, form.items[rowIndex]?.medicine_name);
                // compute whether dropdown should open above or below
                computeMedicineDropdownPosition(rowIndex);
            }
        } catch (error) {
            medicineSuggestions.value = [];
        }
    }, 250);
};

const onMedicineFocus = (rowIndex) => {
    activeMedicineRowIndex.value = rowIndex;
    const medicineName = String(form.items[rowIndex]?.medicine_name ?? '').trim();
    if (medicineName.length >= 2) {
        searchMedicine(medicineName, rowIndex);
        return;
    }
    if (Array.isArray(medicineSuggestions.value) && medicineSuggestions.value.length > 0) {
        medicineSuggestionsVisible.value = true;
        computeMedicineDropdownPosition(rowIndex);
    }
};

const onMedicineBlur = (rowIndex) => {
    setTimeout(() => {
        if (activeMedicineRowIndex.value === rowIndex) {
            medicineSuggestionsVisible.value = false;
            medicineHighlightedIndex.value = -1;
            activeMedicineRowIndex.value = null;
        }
    }, 150);
};

const onMedicineKeyDown = (rowIndex, event) => {
    const list = medicineSuggestions.value || [];
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        medicineHighlightedIndex.value = (medicineHighlightedIndex.value + 1) % list.length;
        scrollToHighlighted(rowIndex);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        medicineHighlightedIndex.value = (medicineHighlightedIndex.value - 1 + list.length) % list.length;
        scrollToHighlighted(rowIndex);
    } else if (event.key === 'Escape') {
        medicineSuggestionsVisible.value = false;
    }
};

const onMedicineEnter = async (rowIndex) => {
    const list = medicineSuggestions.value || [];
    if (medicineSuggestionsVisible.value && list.length > 0) {
        const pick = (medicineHighlightedIndex.value >= 0) ? medicineHighlightedIndex.value : 0;
        const item = list[pick];
        if (item) {
            await selectMedicineSuggestion(rowIndex, item);
            return;
        }
    }

    // If dropdown not open (or no suggestions), move to next row / add new
    const nextIndex = rowIndex + 1;
    if (form.items[nextIndex] !== undefined) {
        await nextTick();
        medicineInputs.value[nextIndex]?.focus();
        medicineInputs.value[nextIndex]?.select();
    } else {
        addRow();
        await nextTick();
        const newIndex = form.items.length - 1;
        medicineInputs.value[newIndex]?.focus();
        medicineInputs.value[newIndex]?.select();
    }
};

const selectMedicineSuggestion = async (rowIndex, item) => {
    const name = typeof item === 'string' ? item : (item?.name ?? item?.medicine_name ?? '');
    if (!name) return;
    const normalizedName = normalizeMedicineName(name);
    if (form.items.some((row, index) =>
        index !== rowIndex && normalizeMedicineName(row?.medicine_name) === normalizedName
    )) {
        medicineDuplicateError.value = `${name} is already added to this prescription.`;
        return;
    }
    medicineDuplicateError.value = "";
    form.items[rowIndex].medicine_name = name;
    applyMedicineDefaults(rowIndex, name);
    medicineSuggestionsVisible.value = false;
    medicineHighlightedIndex.value = 0;
    // After selecting, move focus to next row (select+move in one Enter)
    const nextIndex = rowIndex + 1;
    if (form.items[nextIndex] !== undefined) {
        await nextTick();
        medicineInputs.value[nextIndex]?.focus();
        medicineInputs.value[nextIndex]?.select();
    } else {
        addRow();
        await nextTick();
        const newIndex = form.items.length - 1;
        medicineInputs.value[newIndex]?.focus();
        medicineInputs.value[newIndex]?.select();
    }
};

const searchTest = (query, rowIndex) => {
    try {
        if (testSearchTimers[rowIndex]) {
            clearTimeout(testSearchTimers[rowIndex]);
        }
    } catch (e) { /* ignore */ }

    const term = String(query ?? "").trim();
    if (term.length < 2) {
        testSuggestionsMap[rowIndex] = [];
        showTestDropdown.value = false;
        return;
    }

    testSearchTimers[rowIndex] = setTimeout(async () => {
        const url = route("backend.itemcharge.search", { q: term });
        try {
            const response = await fetch(url, { headers: { Accept: "application/json" } });
            if (!response.ok) {
                testSuggestionsMap[rowIndex] = [];
                showTestDropdown.value = false;
                return;
            }
            const data = await response.json();
            testSuggestionsMap[rowIndex] = Array.isArray(data?.results) ? data.results : [];
            focusedTestIndex.value = rowIndex;
            showTestDropdown.value = true;
            highlightedIndex.value = -1;
        } catch (err) {
            testSuggestionsMap[rowIndex] = [];
            showTestDropdown.value = false;
        }
    }, 250);
};

const resetTestHighlight = () => { highlightedIndex.value = -1; };

const scrollToHighlighted = (rowIndex) => {
    nextTick(() => {
        const doc = (typeof document !== 'undefined') ? document : null;
        const dropdown = doc ? (doc.querySelector(`#testDropdown_${rowIndex}`) || doc.querySelector(`#medicineDropdownPortal_${rowIndex}`) || doc.querySelector(`#medicineDropdown_${rowIndex}`)) : null;
        const highlightedItem = dropdown?.querySelector('.highlighted');
        if (highlightedItem) highlightedItem.scrollIntoView({ block: 'nearest' });
    });
};

const selectTestFromSuggestion = async (rowIndex, item) => {
    const name = (typeof item === 'string') ? item : (item?.test_name ?? item?.name ?? '');
    form.tests[rowIndex] = name || '';
    showTestDropdown.value = false;
    highlightedIndex.value = -1;
    // keep focus on the field so a subsequent Enter can add next row
    await nextTick();
    const field = (typeof document !== 'undefined') ? document.querySelector(`#testSearch_${rowIndex}`) : null;
    if (field) {
        field.focus();
        field.select();
    }
};

const onTestFocus = (rowIndex) => {
    focusedTestIndex.value = rowIndex;
    if (Array.isArray(testSuggestionsMap[rowIndex]) && testSuggestionsMap[rowIndex].length > 0) {
        showTestDropdown.value = true;
    } else {
        showTestDropdown.value = false;
    }
};

const onTestBlur = (rowIndex) => {
    setTimeout(() => {
        if (focusedTestIndex.value === rowIndex) {
            showTestDropdown.value = false;
            highlightedIndex.value = -1;
        }
    }, 180);
};

const flattenTestSuggestionsArr = (rowIndex) => {
    return Array.isArray(testSuggestionsMap[rowIndex]) ? testSuggestionsMap[rowIndex] : [];
};

const groupSuggestions = (rowIndex) => {
    const groups = {};
    flattenTestSuggestionsArr(rowIndex).forEach((item) => {
        const key = (typeof item === 'string') ? 'Other' : ((item?.category_type ?? 'Other') || 'Other');
        if (!groups[key]) groups[key] = [];
        groups[key].push(item);
    });
    return groups;
};

const flatIndexOf = (rowIndex, item) => {
    return flattenTestSuggestionsArr(rowIndex).indexOf(item);
};

const onTestKeyDown = (rowIndex, event) => {
    const list = flattenTestSuggestionsArr(rowIndex);
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        highlightedIndex.value = Math.min((highlightedIndex.value || -1) + 1, list.length - 1);
        scrollToHighlighted(rowIndex);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlightedIndex.value = Math.max((highlightedIndex.value || -1) - 1, -1);
        scrollToHighlighted(rowIndex);
    }
};

const onTestEnter = async (rowIndex) => {
    const list = flattenTestSuggestionsArr(rowIndex);
    if (showTestDropdown.value && list.length > 0) {
        const pick = (highlightedIndex.value >= 0) ? highlightedIndex.value : 0;
        await selectTestFromSuggestion(rowIndex, list[pick]);
        return;
    }

    // If dropdown not open (or no suggestions), move to next row / add new
    const nextIndex = rowIndex + 1;
    if (form.tests[nextIndex] !== undefined) {
        await nextTick();
        const field = (typeof document !== 'undefined') ? document.querySelector(`#testSearch_${nextIndex}`) : null;
        if (field) { field.focus(); field.select(); }
    } else {
        addTestRow();
        await nextTick();
        const newIndex = form.tests.length - 1;
        const field = (typeof document !== 'undefined') ? document.querySelector(`#testSearch_${newIndex}`) : null;
        if (field) { field.focus(); field.select(); }
    }
};
</script>

<template>
    <BackendLayout>
        <div class="prescription-workspace w-full p-2 rounded-md">
            <div class="mb-3 flex items-center justify-between no-print">
                <h1 class="text-xl font-bold dark:text-white">OPD Prescription</h1>
                <div class="flex items-center gap-3">
                    <div class="text-xs text-gray-600 dark:text-gray-300">
                        {{ props.opdpatient?.patient?.name ?? "" }}
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

            <div class="prescription-form-panel border border-gray-200 rounded-md p-3 dark:border-gray-700 prescription-print">
                <div class="print-only mb-4">
                    <div class="prescription-header">
                        <div>
                            <h2 class="text-lg font-bold">Prescription</h2>
                            <p class="text-xs text-gray-700">Dr. {{ props.opdpatient?.doctor?.name ?? 'N/A' }}</p>
                            <p class="text-[11px] text-gray-600">Department: {{ props.opdpatient?.consultation_type ?? 'N/A' }}</p>
                        </div>
                        <div class="text-xs text-gray-700 text-right">
                            <p>Date: {{ formatCurrentDate() }}</p>
                        </div>
                    </div>
                    <div class="prescription-patient">
                        <div><span class="label">Patient</span> {{ props.opdpatient?.patient?.name ?? 'N/A' }}</div>
                        <div><span class="label">Age</span> {{ props.opdpatient?.patient?.age ?? 'N/A' }}</div>
                        <div><span class="label">Gender</span> {{ props.opdpatient?.patient?.gender ?? 'N/A' }}</div>
                        <div><span class="label">Phone</span> {{ props.opdpatient?.patient?.phone ?? 'N/A' }}</div>
                    </div>
                    <div class="mt-3 border-b border-gray-300"></div>
                    <div class="mt-3 text-sm font-semibold">Rx</div>
                </div>
                
                <div v-if="$page.props.flash?.errorMessage"
                    class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                    {{ $page.props.flash.errorMessage }}
                </div>
                <div class="mb-3 no-print">
                    <InputLabel value="NIBP" class="text-xs mb-1" />
                    <input
                        v-model="form.nibp"
                        type="text"
                        class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                        placeholder="e.g. 120/80"
                    />
                    <p v-if="form.errors.nibp" class="mt-1 text-xs text-red-600">
                        {{ form.errors.nibp }}
                    </p>
                </div>

                <div class="mb-3 no-print grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div>
                        <InputLabel value="Seal Upload" class="text-xs mb-1" />
                        <div v-if="doctorSealPreview" class="mb-2">
                            <img :src="doctorSealPreview" alt="Doctor Seal Preview" class="h-16 w-auto border border-gray-200 rounded bg-white p-1" />
                        </div>
                        <input
                            type="file"
                            accept="image/*"
                            @change="handleDoctorSealChange"
                            class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                        />
                        <p v-if="form.errors.doctor_seal" class="mt-1 text-xs text-red-600">
                            {{ form.errors.doctor_seal }}
                        </p>
                    </div>

                    <div>
                        <InputLabel value="Signature Upload" class="text-xs mb-1" />
                        <div v-if="doctorSignaturePreview" class="mb-2">
                            <img :src="doctorSignaturePreview" alt="Doctor Signature Preview" class="h-16 w-auto border border-gray-200 rounded bg-white p-1" />
                        </div>
                        <input
                            type="file"
                            accept="image/*"
                            @change="handleDoctorSignatureChange"
                            class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                        />
                        <p v-if="form.errors.doctor_signature" class="mt-1 text-xs text-red-600">
                            {{ form.errors.doctor_signature }}
                        </p>
                    </div>

                    <div>
                        <InputLabel value="Doctor Designation" class="text-xs mb-1" />
                        <textarea
                            v-model="form.doctor_designation"
                            rows="4"
                            class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                            placeholder="e.g. Consultant Medicine&#10;MBBS, FCPS"
                        ></textarea>
                        <p v-if="form.errors.doctor_designation" class="mt-1 text-xs text-red-600">
                            {{ form.errors.doctor_designation }}
                        </p>
                    </div>
                </div>

                <div class="mb-3 no-print">
                    <InputLabel value="Notes" class="text-xs mb-1" />
                    <textarea
                        v-model="form.notes"
                        rows="2"
                        class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                        placeholder="Additional notes"
                    ></textarea>
                    <p v-if="form.errors.notes" class="mt-1 text-xs text-red-600">
                        {{ form.errors.notes }}
                    </p>
                </div>

                <div v-if="saveError" class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                    {{ saveError }}
                </div>
                <div v-if="medicineDuplicateError" class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                    {{ medicineDuplicateError }}
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr class="bg-gray-100 text-gray-700 dark:bg-slate-800 dark:text-gray-200">
                                <th class="px-2 py-2 border">Medicine</th>
                                <th class="px-2 py-2 border">Dose</th>
                                <th class="px-2 py-2 border">Duration</th>
                                <th class="px-2 py-2 border">Frequency</th>
                                <th class="px-2 py-2 border">Instructions</th>
                                <th class="px-2 py-2 border no-print">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in form.items" :key="index">
                                <td class="px-2 py-2 border">
                                    <div class="relative">
                                        <input
                                            v-model="row.medicine_name"
                                            type="text"
                                            :ref="el => medicineInputs.value[index] = el"
                                            class="w-full px-2 py-1 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                                            placeholder="Medicine name"
                                            @input="onMedicineInput(index)"
                                            @change="onMedicineChange(index)"
                                            @focus="onMedicineFocus(index)"
                                            @blur="onMedicineBlur(index)"
                                            @keydown.enter.prevent="onMedicineEnter(index)"
                                            @keydown="onMedicineKeyDown(index, $event)"
                                            autocomplete="off"
                                        />

                                        <teleport to="body">
                                            <div v-if="activeMedicineRowIndex === index && medicineSuggestionsVisible" :id="`medicineDropdownPortal_${index}`" :style="{ position: 'absolute', top: (medicineDropdownCoords[index]?.top ? medicineDropdownCoords[index].top + 'px' : '0px'), left: (medicineDropdownCoords[index]?.left ? medicineDropdownCoords[index].left + 'px' : '0px'), width: (medicineDropdownCoords[index]?.width ? medicineDropdownCoords[index].width + 'px' : '240px') }" class="z-50 bg-white border rounded shadow text-xs">
                                                <div v-for="(item, sIndex) in medicineSuggestions" :key="sIndex" @mousedown.prevent="selectMedicineSuggestion(index, item)" @mouseenter="medicineHighlightedIndex = sIndex" :class="{ 'bg-blue-100': medicineHighlightedIndex === sIndex, 'highlighted': medicineHighlightedIndex === sIndex }" class="px-3 py-2 cursor-pointer">
                                                    <div class="font-medium">{{ typeof item === 'string' ? item : (item.name ?? item.medicine_name ?? '') }}</div>
                                                    <div v-if="typeof item !== 'string' && item.generic_name" class="text-[11px] text-emerald-700">({{ item.generic_name }})</div>
                                                    <div v-if="typeof item !== 'string' && (item.dose || item.duration)" class="text-[11px] text-gray-500">{{ item.dose ? item.dose + (item.duration ? ' · ' + item.duration : '') : (item.duration ?? '') }}</div>
                                                </div>
                                                <div v-if="!medicineSuggestions.length" class="px-3 py-2 text-sm text-gray-500 text-center">No medicines found</div>
                                            </div>
                                        </teleport>
                                    </div>
                                    <p v-if="form.errors[`items.${index}.medicine_name`]" class="mt-1 text-xs text-red-600">
                                        {{ form.errors[`items.${index}.medicine_name`] }}
                                    </p>
                                </td>
                                <td class="px-2 py-2 border">
                                    <input
                                        v-model="row.dose"
                                        type="text"
                                        :list="medicineOptionsListId(index, 'dose')"
                                        @input="rememberMedicineField(row, 'dose')"
                                        class="w-full px-2 py-1 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                                        placeholder="e.g. 1 tab"
                                    />
                                    <datalist :id="medicineOptionsListId(index, 'dose')">
                                        <option v-for="option in medicineOptionsFor(row, 'dose')" :key="option" :value="option" />
                                    </datalist>
                                    <p v-if="row.generic_name" class="mt-1 text-[11px] font-medium text-emerald-700">
                                        ({{ row.generic_name }})
                                    </p>
                                    <p v-if="form.errors[`items.${index}.dose`]" class="mt-1 text-xs text-red-600">
                                        {{ form.errors[`items.${index}.dose`] }}
                                    </p>
                                </td>
                                <td class="px-2 py-2 border">
                                    <input
                                        v-model="row.duration"
                                        type="text"
                                        :list="medicineOptionsListId(index, 'duration')"
                                        @input="rememberMedicineField(row, 'duration')"
                                        class="w-full px-2 py-1 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                                        placeholder="e.g. 7 days"
                                    />
                                    <datalist :id="medicineOptionsListId(index, 'duration')">
                                        <option v-for="option in medicineOptionsFor(row, 'duration')" :key="option" :value="option" />
                                    </datalist>
                                    <p v-if="form.errors[`items.${index}.duration`]" class="mt-1 text-xs text-red-600">
                                        {{ form.errors[`items.${index}.duration`] }}
                                    </p>
                                </td>
                                <td class="px-2 py-2 border">
                                    <input
                                        v-model="row.frequency"
                                        type="text"
                                        :list="medicineOptionsListId(index, 'frequency')"
                                        @input="rememberMedicineField(row, 'frequency')"
                                        class="w-full px-2 py-1 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                                        placeholder="e.g. 1-0-1"
                                    />
                                    <datalist :id="medicineOptionsListId(index, 'frequency')">
                                        <option v-for="option in medicineOptionsFor(row, 'frequency')" :key="option" :value="option" />
                                    </datalist>
                                </td>
                                <td class="px-2 py-2 border">
                                    <input
                                        v-model="row.instructions"
                                        type="text"
                                        :list="medicineOptionsListId(index, 'instructions')"
                                        @input="rememberMedicineField(row, 'instructions')"
                                        class="w-full px-2 py-1 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                                        placeholder="After meal"
                                    />
                                    <datalist :id="medicineOptionsListId(index, 'instructions')">
                                        <option v-for="option in medicineOptionsFor(row, 'instructions')" :key="option" :value="option" />
                                    </datalist>
                                </td>
                                <td class="px-2 py-2 border text-center no-print">
                                    <button
                                        type="button"
                                        class="px-2 py-1 text-xs bg-red-500 text-white rounded"
                                        @click="removeRow(index)"
                                    >
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <!-- per-row medicine dropdown replaces datalist -->
                </div>

                <div class="mt-3 no-print">
                    <button
                        id="addMedicineBtn"
                        type="button"
                        class="px-3 py-1.5 text-xs bg-gray-600 text-white rounded"
                        @click="addRow"
                    >
                        Add Medicine
                    </button>
                </div>

                <div class="mt-3 border border-gray-200 rounded-md p-2 dark:border-gray-700">
                    <div class="mb-2 text-xs font-semibold text-gray-700 dark:text-gray-200">Recommended Items</div>
                    <div class="space-y-1.5">
                        <div
                            v-for="(testName, index) in form.tests"
                            :key="`test-${index}`"
                            class="flex items-center gap-2"
                        >
                            <div class="h-5 min-w-[20px] px-1 inline-flex items-center justify-center rounded bg-gray-100 text-[11px] font-semibold text-gray-700 dark:bg-slate-700 dark:text-gray-200">
                                {{ index + 1 }}
                            </div>

                            <div class="relative flex-1">
                                <input
                                    :id="`testSearch_${index}`"
                                    v-model="form.tests[index]"
                                    type="text"
                                    class="w-full px-2 py-1 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                                    placeholder="Item name"
                                    @input="e => { searchTest(e.target.value, index); resetTestHighlight(); }"
                                    @focus="onTestFocus(index)"
                                    @blur="onTestBlur(index)"
                                    @keydown.enter.prevent="onTestEnter(index)"
                                    @keydown="onTestKeyDown(index, $event)"
                                    autocomplete="off"
                                />

                                <!-- Custom dropdown for test suggestions (grouped by category_type) -->
                                <div v-if="focusedTestIndex === index && showTestDropdown" :id="`testDropdown_${index}`" class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto">
                                    <template v-for="(items, group) in groupSuggestions(index)" :key="group">
                                        <div class="px-3 py-2 text-xs font-semibold bg-gray-100 text-gray-700">{{ group }}</div>
                                        <div v-for="(item, sIndex) in items" :key="group + '-' + sIndex" @click="selectTestFromSuggestion(index, item)" @mouseenter="highlightedIndex = flatIndexOf(index, item)" :class="flatIndexOf(index, item) === highlightedIndex ? 'bg-blue-100 highlighted' : ''" class="px-3 py-2 text-sm cursor-pointer hover:bg-blue-50 hover:text-blue-700 border-b border-gray-100 last:border-b-0">
                                            <div class="font-medium">{{ typeof item === 'string' ? item : (item.test_name ?? item.name ?? '') }}</div>
                                            <div v-if="typeof item !== 'string' && (item.amount || item.standard_charge)" class="text-[11px] text-gray-500">৳{{ item.amount ?? item.standard_charge }}</div>
                                        </div>
                                    </template>
                                    <div v-if="flattenTestSuggestionsArr(index).length === 0" class="px-3 py-2 text-sm text-gray-500 text-center">
                                        No tests found
                                    </div>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="px-2 py-1 text-xs bg-red-500 text-white rounded no-print"
                                @click="removeTestRow(index)"
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                    <div class="mt-2 no-print">
                        <button
                            type="button"
                            class="px-3 py-1.5 text-xs bg-gray-600 text-white rounded"
                            @click="addTestRow"
                        >
                            Add Item
                        </button>
                    </div>
                    <p v-if="form.errors.tests" class="mt-1 text-xs text-red-600">
                        {{ form.errors.tests }}
                    </p>
                    <p
                        v-for="(testName, index) in form.tests"
                        :key="`test-error-${index}`"
                        v-show="form.errors[`tests.${index}`]"
                        class="mt-1 text-xs text-red-600"
                    >
                        {{ form.errors[`tests.${index}`] }}
                    </p>
                    <!-- custom dropdown used instead of datalist -->
                </div>

                <div class="flex items-center justify-end mt-3 no-print">
                    <button
                        type="button"
                        class="px-4 py-1.5 text-xs bg-blue-600 text-white rounded disabled:opacity-60"
                        :disabled="form.processing"
                        @click="savePrescription"
                    >
                        {{ form.processing ? "Saving..." : "Save Prescription" }}
                    </button>
                </div>

                <div class="print-only mt-6 prescription-footer">
                    <div class="text-xs text-gray-600">
                        Notes: {{ form.notes || 'N/A' }}
                    </div>
                    <div class="text-right">
                        <div class="border-t border-gray-400 w-44 mt-10 pt-1 text-xs text-gray-600">
                            Doctor Signature
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </BackendLayout>
</template>

<style scoped>
.print-only {
    display: none;
}

@media print {
    .no-print {
        display: none !important;
    }

    .print-only {
        display: block !important;
    }

    .prescription-print {
        border: 1px solid #111;
        padding: 12px;
    }

    .prescription-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
    }

    .prescription-patient {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 6px 12px;
        margin-top: 10px;
        font-size: 12px;
        color: #111;
    }

    .prescription-patient .label {
        display: inline-block;
        min-width: 70px;
        font-weight: 600;
        color: #111;
    }

    .prescription-footer {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 12px;
    }

    table {
        border: 1px solid #111;
    }

    th,
    td {
        border: 1px solid #111 !important;
    }
}
</style>
