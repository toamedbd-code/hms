<script setup>
import { ref } from "vue";
import BackendLayout from "@/Layouts/BackendLayout.vue";
import InputLabel from "@/Components/InputLabel.vue";
import { useForm } from "@inertiajs/vue3";
import { displayResponse } from "@/responseMessage.js";

const medicineSuggestions = ref([]);
const testSuggestions = ref([]);
let medicineSearchTimer = null;
let testSearchTimer = null;

const props = defineProps({
    ipdpatient: Object,
    prescription: Object,
    doctors: Array,
});

const saveError = ref("");
const doctorSignaturePreview = ref(props.prescription?.doctor_signature_url ?? "");
const doctorSealPreview = ref(props.prescription?.doctor_seal_url ?? "");

const form = useForm({
    doctor_id: props.prescription?.doctor_id ?? props.ipdpatient?.consultant_doctor_id ?? "",
    doctor_designation: props.prescription?.doctor_designation ?? "",
    complaints: props.prescription?.complaints ?? "",
    diagnosis: props.prescription?.diagnosis ?? "",
    advice: props.prescription?.advice ?? "",
    follow_up_date: props.prescription?.follow_up_date ?? "",

    medicines: props.prescription?.medicines?.length
        ? props.prescription.medicines.map((item) => ({
              medicine_name: item.medicine_name ?? "",
              dose: item.dose ?? "",
              frequency: item.frequency ?? "",
              duration: item.duration ?? "",
              instructions: item.instructions ?? "",
          }))
        : [
              {
                  medicine_name: "",
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

const addMedicineRow = () => {
    form.medicines.push({
        medicine_name: "",
        dose: "",
        frequency: "",
        duration: "",
        instructions: "",
    });
};

const removeMedicineRow = (index) => {
    if (form.medicines.length === 1) return;
    form.medicines.splice(index, 1);
};

const addTestRow = () => {
    form.tests.push("");
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
    const row = form.medicines[index];
    if (!row) {
        return;
    }

    searchMedicine(row.medicine_name, index);
};

const onMedicineChange = (index) => {
    const row = form.medicines[index];
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
                applyMedicineDefaults(rowIndex, form.medicines[rowIndex]?.medicine_name);
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

const savePrescription = () => {
    saveError.value = "";

    form.post(route("backend.ipdpatient.prescription.store", props.ipdpatient.id), {
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
    window.open(route("backend.ipdpatient.prescription.print", props.ipdpatient.id), "_blank");
};

const downloadPrescriptionPdf = () => {
    window.open(route("backend.ipdpatient.prescription.pdf", props.ipdpatient.id), "_blank");
};
</script>

<template>
    <BackendLayout>
        <div class="w-full p-2 bg-white rounded-md dark:bg-slate-900">
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

            <div class="border border-gray-200 rounded-md p-3 dark:border-gray-700">
                <div v-if="$page.props.flash?.successMessage"
                    class="mb-3 rounded border border-green-200 bg-green-50 px-3 py-2 text-xs text-green-700">
                    {{ $page.props.flash.successMessage }}
                </div>
                <div v-if="$page.props.flash?.errorMessage"
                    class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                    {{ $page.props.flash.errorMessage }}
                </div>

                <div v-if="saveError" class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                    {{ saveError }}
                </div>

                <div class="mb-3">
                    <InputLabel value="Doctor" class="text-xs mb-1" />
                    <select
                        v-model="form.doctor_id"
                        class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                    >
                        <option value="">Select doctor</option>
                        <option v-for="doctor in props.doctors ?? []" :key="doctor.id" :value="doctor.id">
                            {{ doctor.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.doctor_id" class="mt-1 text-xs text-red-600">{{ form.errors.doctor_id }}</p>
                </div>

                <div class="mb-3 grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div>
                        <InputLabel value="Seal Upload" class="text-xs mb-1" />
                        <div v-if="doctorSealPreview" class="mb-2">
                            <img :src="doctorSealPreview" alt="Doctor Seal Preview" class="h-16 w-auto border border-gray-200 rounded bg-white p-1" />
                        </div>
                        <input
                            type="file"
                            accept="image/*"
                            @change="handleDoctorSealChange"
                            class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                        />
                        <p v-if="form.errors.doctor_seal" class="mt-1 text-xs text-red-600">{{ form.errors.doctor_seal }}</p>
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
                            class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                        />
                        <p v-if="form.errors.doctor_signature" class="mt-1 text-xs text-red-600">{{ form.errors.doctor_signature }}</p>
                    </div>

                    <div>
                        <InputLabel value="Doctor Designation" class="text-xs mb-1" />
                        <textarea
                            v-model="form.doctor_designation"
                            rows="4"
                            class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                            placeholder="e.g. Consultant Medicine&#10;MBBS, FCPS"
                        ></textarea>
                        <p v-if="form.errors.doctor_designation" class="mt-1 text-xs text-red-600">{{ form.errors.doctor_designation }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <InputLabel value="Complaints" class="text-xs mb-1" />
                        <textarea v-model="form.complaints" rows="3"
                            class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                            placeholder="Chief complaints"></textarea>
                        <p v-if="form.errors.complaints" class="mt-1 text-xs text-red-600">{{ form.errors.complaints }}</p>
                    </div>

                    <div>
                        <InputLabel value="Diagnosis" class="text-xs mb-1" />
                        <textarea v-model="form.diagnosis" rows="3"
                            class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                            placeholder="Diagnosis"></textarea>
                        <p v-if="form.errors.diagnosis" class="mt-1 text-xs text-red-600">{{ form.errors.diagnosis }}</p>
                    </div>

                    <div>
                        <InputLabel value="Advice" class="text-xs mb-1" />
                        <textarea v-model="form.advice" rows="3"
                            class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                            placeholder="Advice"></textarea>
                        <p v-if="form.errors.advice" class="mt-1 text-xs text-red-600">{{ form.errors.advice }}</p>
                    </div>

                    <div>
                        <InputLabel value="Follow Up Date" class="text-xs mb-1" />
                        <input v-model="form.follow_up_date" type="date"
                            class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" />
                        <p v-if="form.errors.follow_up_date" class="mt-1 text-xs text-red-600">{{ form.errors.follow_up_date }}</p>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <div class="mb-2 text-xs font-semibold text-gray-700 dark:text-gray-200">Medicines</div>
                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr class="bg-gray-100 text-gray-700 dark:bg-slate-800 dark:text-gray-200">
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
                                <td class="px-2 py-2 border">
                                    <input
                                        v-model="row.medicine_name"
                                        type="text"
                                        list="ipd-medicine-suggestions"
                                        class="w-full px-2 py-1 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                                        placeholder="Medicine name"
                                        @input="onMedicineInput(index)"
                                        @change="onMedicineChange(index)"
                                    />
                                </td>
                                <td class="px-2 py-2 border">
                                    <input v-model="row.dose" type="text"
                                        class="w-full px-2 py-1 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                                        placeholder="e.g. 1 tab" />
                                </td>
                                <td class="px-2 py-2 border">
                                    <input v-model="row.frequency" type="text"
                                        class="w-full px-2 py-1 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                                        placeholder="e.g. 1-0-1" />
                                </td>
                                <td class="px-2 py-2 border">
                                    <input v-model="row.duration" type="text"
                                        class="w-full px-2 py-1 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                                        placeholder="e.g. 7 days" />
                                </td>
                                <td class="px-2 py-2 border">
                                    <input v-model="row.instructions" type="text"
                                        class="w-full px-2 py-1 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                                        placeholder="After meal" />
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

                    <datalist id="ipd-medicine-suggestions">
                        <option v-for="item in medicineSuggestions" :key="item.name" :value="item.name" />
                    </datalist>

                    <div class="mt-3">
                        <button type="button" class="px-3 py-1.5 text-xs bg-gray-600 text-white rounded"
                            @click="addMedicineRow">
                            Add Medicine
                        </button>
                    </div>
                </div>

                <div class="mt-4 border border-gray-200 rounded-md p-2 dark:border-gray-700">
                    <div class="mb-2 text-xs font-semibold text-gray-700 dark:text-gray-200">Recommended Tests</div>

                    <div class="space-y-1.5">
                        <div v-for="(testName, index) in form.tests" :key="`test-${index}`" class="flex items-center gap-2">
                            <div class="h-5 min-w-[20px] px-1 inline-flex items-center justify-center rounded bg-gray-100 text-[11px] font-semibold text-gray-700 dark:bg-slate-700 dark:text-gray-200">
                                {{ index + 1 }}
                            </div>
                            <input
                                v-model="form.tests[index]"
                                type="text"
                                list="ipd-test-suggestions"
                                class="w-full px-2 py-1 border border-gray-300 rounded text-xs dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                                placeholder="Test name"
                                @input="searchTest(form.tests[index])"
                            />
                            <button type="button" class="px-2 py-1 text-xs bg-red-500 text-white rounded"
                                @click="removeTestRow(index)">
                                Remove
                            </button>
                        </div>
                    </div>

                    <div class="mt-2">
                        <button type="button" class="px-3 py-1.5 text-xs bg-gray-600 text-white rounded" @click="addTestRow">
                            Add Test
                        </button>
                    </div>

                    <datalist id="ipd-test-suggestions">
                        <option v-for="name in testSuggestions" :key="name" :value="name" />
                    </datalist>
                </div>

                <div class="flex items-center justify-end mt-3">
                    <button type="button" class="px-4 py-1.5 text-xs bg-blue-600 text-white rounded disabled:opacity-60"
                        :disabled="form.processing" @click="savePrescription">
                        {{ form.processing ? "Saving..." : "Save Prescription" }}
                    </button>
                </div>
            </div>
        </div>
    </BackendLayout>
</template>
