<script setup>
import { ref } from "vue";
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
const testSuggestions = ref([]);
let medicineSearchTimer = null;
let testSearchTimer = null;

const form = useForm({
    notes: props.prescription?.notes ?? "",
    nibp: props.opdpatient?.nibp ?? "",
    doctor_designation: props.prescription?.doctor_designation ?? props.opdpatient?.consultation_type ?? "",
    items: props.prescription?.items?.length
        ? props.prescription.items
              .filter((item) => {
                  const medicineName = String(item?.medicine_name ?? "").trim().toUpperCase();
                  return medicineName !== "" && medicineName !== "N/A";
              })
              .map((item) => ({
              test_name: item.test_name ?? "",
              medicine_name: item.medicine_name ?? "",
              dose: item.dose ?? "",
              duration: item.duration ?? "",
              frequency: item.frequency ?? "",
              instructions: item.instructions ?? "",
          }))
        : [
              {
                  test_name: "",
                  medicine_name: "",
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
        dose: "",
        duration: "",
        frequency: "",
        instructions: "",
    });
};

const removeRow = (index) => {
    if (form.items.length === 1) return;
    form.items.splice(index, 1);
};

const addTestRow = () => {
    form.tests.push("");
};

const removeTestRow = (index) => {
    if (form.tests.length === 1) return;
    form.tests.splice(index, 1);
};

const savePrescription = () => {
    saveError.value = "";
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
    window.open(route("backend.opdpatient.prescription.print", props.opdpatient.id), "_blank");
};

const downloadPrescriptionPdf = () => {
    window.open(route("backend.opdpatient.prescription.pdf", props.opdpatient.id), "_blank");
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
                    dose: "",
                    duration: "",
                    frequency: "",
                    instructions: "",
                };
            }

            const name = String(item?.name ?? item?.medicine_name ?? "").trim();
            if (!name) {
                return null;
            }

            return {
                name,
                dose: String(item?.dose ?? ""),
                duration: String(item?.duration ?? ""),
                frequency: String(item?.frequency ?? ""),
                instructions: String(item?.instructions ?? ""),
            };
        })
        .filter(Boolean);
};

const normalizeText = (value) => String(value ?? "").trim().toLowerCase();

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
    );

    if (!match) {
        return;
    }

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
    searchMedicine(row.medicine_name, index);
};

const onMedicineChange = (index) => {
    const row = form.items[index];
    if (!row) {
        return;
    }
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
        const url = route("backend.medicineinventory.search", {
            q: term,
            include_defaults: 1,
        });

        try {
            const response = await fetch(url, { headers: { Accept: "application/json" } });
            if (!response.ok) {
                medicineSuggestions.value = [];
                return;
            }

            const data = await response.json();
            medicineSuggestions.value = normalizeMedicineResults(data?.results);

            if (rowIndex !== null) {
                applyMedicineDefaults(rowIndex, form.items[rowIndex]?.medicine_name);
            }
        } catch (error) {
            medicineSuggestions.value = [];
        }
    }, 250);
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
        const url = route("backend.testpathology.search", { q: term });
        fetchSuggestions(url, testSuggestions);
    }, 250);
};
</script>

<template>
    <BackendLayout>
        <div class="w-full p-2 bg-white rounded-md dark:bg-slate-900">
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

            <div class="border border-gray-200 rounded-md p-3 dark:border-gray-700 prescription-print">
                <div class="print-only mb-4">
                    <div class="prescription-header">
                        <div>
                            <h2 class="text-lg font-bold">Prescription</h2>
                            <p class="text-xs text-gray-700">Dr. {{ props.opdpatient?.doctor?.name ?? 'N/A' }}</p>
                            <p class="text-[11px] text-gray-600">Department: {{ props.opdpatient?.consultation_type ?? 'N/A' }}</p>
                        </div>
                        <div class="text-xs text-gray-700 text-right">
                            <p>Date: {{ formatCurrentDate() }}</p>
                            <p>OPD ID: {{ props.opdpatient?.id ?? 'N/A' }}</p>
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
                <div v-if="$page.props.flash?.successMessage"
                    class="mb-3 rounded border border-green-200 bg-green-50 px-3 py-2 text-xs text-green-700">
                    {{ $page.props.flash.successMessage }}
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
                                    <input
                                        v-model="row.medicine_name"
                                        type="text"
                                        list="medicine-suggestions"
                                        class="w-full px-2 py-1 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                                        placeholder="Medicine name"
                                        @input="onMedicineInput(index)"
                                        @change="onMedicineChange(index)"
                                    />
                                    <p v-if="form.errors[`items.${index}.medicine_name`]" class="mt-1 text-xs text-red-600">
                                        {{ form.errors[`items.${index}.medicine_name`] }}
                                    </p>
                                </td>
                                <td class="px-2 py-2 border">
                                    <input
                                        v-model="row.dose"
                                        type="text"
                                        class="w-full px-2 py-1 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                                        placeholder="e.g. 1 tab"
                                    />
                                    <p v-if="form.errors[`items.${index}.dose`]" class="mt-1 text-xs text-red-600">
                                        {{ form.errors[`items.${index}.dose`] }}
                                    </p>
                                </td>
                                <td class="px-2 py-2 border">
                                    <input
                                        v-model="row.duration"
                                        type="text"
                                        class="w-full px-2 py-1 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                                        placeholder="e.g. 7 days"
                                    />
                                    <p v-if="form.errors[`items.${index}.duration`]" class="mt-1 text-xs text-red-600">
                                        {{ form.errors[`items.${index}.duration`] }}
                                    </p>
                                </td>
                                <td class="px-2 py-2 border">
                                    <input
                                        v-model="row.frequency"
                                        type="text"
                                        class="w-full px-2 py-1 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                                        placeholder="e.g. 1-0-1"
                                    />
                                </td>
                                <td class="px-2 py-2 border">
                                    <input
                                        v-model="row.instructions"
                                        type="text"
                                        class="w-full px-2 py-1 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                                        placeholder="After meal"
                                    />
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
                    <datalist id="medicine-suggestions">
                        <option v-for="item in medicineSuggestions" :key="item.name" :value="item.name" />
                    </datalist>
                </div>

                <div class="mt-3 no-print">
                    <button
                        type="button"
                        class="px-3 py-1.5 text-xs bg-gray-600 text-white rounded"
                        @click="addRow"
                    >
                        Add Medicine
                    </button>
                </div>

                <div class="mt-3 border border-gray-200 rounded-md p-2 dark:border-gray-700">
                    <div class="mb-2 text-xs font-semibold text-gray-700 dark:text-gray-200">Recommended Tests</div>
                    <div class="space-y-1.5">
                        <div
                            v-for="(testName, index) in form.tests"
                            :key="`test-${index}`"
                            class="flex items-center gap-2"
                        >
                            <div class="h-5 min-w-[20px] px-1 inline-flex items-center justify-center rounded bg-gray-100 text-[11px] font-semibold text-gray-700 dark:bg-slate-700 dark:text-gray-200">
                                {{ index + 1 }}
                            </div>
                            <input
                                v-model="form.tests[index]"
                                type="text"
                                list="test-suggestions"
                                class="w-full px-2 py-1 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                                placeholder="Test name"
                                @input="searchTest(form.tests[index])"
                            />
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
                            Add Test
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
                    <datalist id="test-suggestions">
                        <option v-for="name in testSuggestions" :key="name" :value="name" />
                    </datalist>
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
