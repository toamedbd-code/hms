<script setup>
import { ref, watch } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'
import BackendLayout from '@/Layouts/BackendLayout.vue'

/* =======================
   PROPS
======================= */
const props = defineProps({
    pageTitle: String,
    medicine: Object,
    suppliers: Array,
    medicineCategories: Array,
    isEdit: Boolean,
})

/* =======================
   FLASH MESSAGE
======================= */
const flash = usePage().props.flash || {}

/* =======================
   MAIN FORM (Create/Edit)
======================= */
const form = useForm({
    supplier_id: '',
    medicine_category_id: '',
    medicine_name: '',
    generic_name: '',
    medicine_unit_purchase_price: '',
    medicine_unit_selling_price: '',
    medicine_quantity: '',
    expiry_date: '',
    status: 'Active',
    standard_dose: '',
    standard_frequency: '',
    standard_duration: '',
    standard_instructions: '',
    standard_prescription_verified: false,
})

/* =======================
   EDIT AUTO FILL (FINAL FIX)
======================= */
watch(
    () => props.medicine,
    (medicine) => {
        if (medicine) {
            form.supplier_id = medicine.supplier_id
            form.medicine_category_id = medicine.medicine_category_id
            form.medicine_name = medicine.medicine_name
            form.generic_name = medicine.generic_name ?? ''
            form.medicine_unit_purchase_price = medicine.medicine_unit_purchase_price
            form.medicine_unit_selling_price = medicine.medicine_unit_selling_price
            form.medicine_quantity = medicine.medicine_quantity
            form.expiry_date = medicine.expiry_date ?? ''
            form.status = medicine.status
            form.standard_dose = medicine.standard_dose ?? ''
            form.standard_frequency = medicine.standard_frequency ?? ''
            form.standard_duration = medicine.standard_duration ?? ''
            form.standard_instructions = medicine.standard_instructions ?? ''
            form.standard_prescription_verified = Boolean(medicine.standard_prescription_verified)
        }
    },
    { immediate: true }
)

/* =======================
   SUBMIT (CREATE / UPDATE)
======================= */
const submit = () => {
    if (props.isEdit) {
        form.put(
            route('backend.medicineinventory.update', props.medicine.id),
            {
                preserveScroll: true,
                onSuccess: () => {
                    // success handled by flash message
                }
            }
        )
    } else {
        form.post(
            route('backend.medicineinventory.store'),
            {
                preserveScroll: true,
            }
        )
    }
}

/* =======================
   CSV UPLOAD (CREATE ONLY)
======================= */
const csv = ref({
    supplier_id: '',
    medicine_category_id: '',
    file: null,
    skip_duplicates: true,
})

const csvLoading = ref(false)
const csvUploadProgress = ref(0)
const csvProgressKnown = ref(false)
const csvUploadComplete = ref(false)
const csvImportProgress = ref(0)
const csvImportStatus = ref('waiting')
const csvImportCounts = ref({ imported: 0, skipped: 0, failed: 0 })
let pollCsvImportProgress = true
const isCsvDragging = ref(false)
const csvMessage = ref('')
const csvErrors = ref([])
const showImportSummary = ref(false)
const importSummary = ref({
    imported: 0,
    skipped: 0,
    failed: 0,
    message: '',
    errors: [],
})

const normalizeCsvErrors = (errors) => {
    if (Array.isArray(errors)) {
        return errors
    }

    if (!errors || typeof errors !== 'object') {
        return []
    }

    return Object.values(errors).flatMap((value) =>
        Array.isArray(value) ? value : [String(value)]
    )
}

const setCsvFile = (file) => {
    if (!file) {
        return
    }

    csv.value.file = file
    csvMessage.value = ''
    csvErrors.value = []
}

const handleCsvFile = (e) => {
    setCsvFile(e.target.files?.[0])
    e.target.value = ''
}

const handleCsvDrop = (e) => {
    isCsvDragging.value = false
    setCsvFile(e.dataTransfer?.files?.[0])
}

const clearCsvFile = () => {
    csv.value.file = null
    csvMessage.value = ''
    csvErrors.value = []
}

const createCsvImportId = () => {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID()
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
        const random = Math.random() * 16 | 0
        return (character === 'x' ? random : (random & 0x3 | 0x8)).toString(16)
    })
}

const watchCsvImportProgress = async (importId) => {
    pollCsvImportProgress = true

    while (pollCsvImportProgress) {
        try {
            const response = await axios.get(
                route('backend.medicineinventory.import.progress', importId)
            )
            const progress = response.data || {}
            csvImportStatus.value = progress.status || 'waiting'
            csvImportProgress.value = Math.max(0, Math.min(100, Number(progress.progress) || 0))
            csvImportCounts.value = {
                imported: Number(progress.imported) || 0,
                skipped: Number(progress.skipped) || 0,
                failed: Number(progress.failed) || 0,
            }

            if (['completed', 'failed'].includes(csvImportStatus.value)) {
                break
            }
        } catch (error) {
            if (pollCsvImportProgress) {
                csvMessage.value = error.response?.data?.message
                    || 'Could not retrieve CSV import progress.'
                break
            }
        }

        await new Promise((resolve) => window.setTimeout(resolve, 750))
    }
}

const uploadInventoryCsv = async () => {
    csvMessage.value = ''
    csvErrors.value = []
    showImportSummary.value = false
    csvUploadProgress.value = 0
    csvProgressKnown.value = false
    csvUploadComplete.value = false
    csvImportProgress.value = 0
    csvImportStatus.value = 'waiting'
    csvImportCounts.value = { imported: 0, skipped: 0, failed: 0 }

    if (!csv.value.file) {
        csvMessage.value = 'CSV file is required.'
        return
    }

    const formData = new FormData()
    const importId = createCsvImportId()
    if (csv.value.supplier_id) {
        formData.append('supplier_id', csv.value.supplier_id)
    }
    if (csv.value.medicine_category_id) {
        formData.append('medicine_category_id', csv.value.medicine_category_id)
    }
    formData.append('csv_file', csv.value.file)
    formData.append('skip_duplicates', csv.value.skip_duplicates ? '1' : '0')
    formData.append('import_id', importId)

    csvLoading.value = true
    const progressWatcher = watchCsvImportProgress(importId)

    try {
        const res = await axios.post(
            route('backend.medicineinventory.import.csv'),
            formData,
            {
                onUploadProgress: (event) => {
                    if (event.total) {
                        csvProgressKnown.value = true
                        csvUploadProgress.value = Math.round((event.loaded * 100) / event.total)
                    }

                    if (event.total && event.loaded >= event.total) {
                        csvUploadComplete.value = true
                        csvUploadProgress.value = 100
                    }
                },
            }
        )
        if (res.data.status) {
            csvMessage.value = res.data.message || 'CSV imported successfully.'
            importSummary.value = {
                imported: Number(res.data.imported ?? 0),
                skipped: Number(res.data.skipped ?? 0),
                failed: Number(res.data.failed ?? 0),
                message: res.data.message || 'CSV imported successfully.',
                errors: normalizeCsvErrors(res.data.errors),
            }
            await Swal.fire({
                title: 'Import Successful',
                text: importSummary.value.message,
                icon: 'success',
                confirmButtonText: 'OK',
            })
            router.visit(route('backend.medicineinventory.index'))
        } else {
            csvMessage.value = res.data.message || 'CSV upload failed.'
            importSummary.value = {
                imported: 0,
                skipped: 0,
                failed: 0,
                message: csvMessage.value,
                errors: [],
            }
            showImportSummary.value = true
        }
    } catch (e) {
        csvMessage.value = e.response?.data?.message || 'CSV upload failed.'
        csvErrors.value = normalizeCsvErrors(e.response?.data?.errors)
        importSummary.value = {
            imported: 0,
            skipped: 0,
            failed: csvErrors.value.length || 1,
            message: csvMessage.value,
            errors: csvErrors.value,
        }
        showImportSummary.value = true
    } finally {
        pollCsvImportProgress = false
        await progressWatcher
        csvLoading.value = false
        if (!csvMessage.value && csvUploadComplete.value) {
            csvUploadProgress.value = 100
        }
    }
}

/* =======================
   NAVIGATION
======================= */
const goToList = () => {
    router.visit(route('backend.medicineinventory.index'))
}
</script>

<template>
    <BackendLayout>
        <!-- Flash Messages -->
        <div v-if="flash.successMessage" class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
            {{ flash.successMessage }}
        </div>
        <div v-if="flash.errorMessage" class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
            {{ flash.errorMessage }}
        </div>
        <div v-if="flash.infoMessage" class="mb-4 p-4 bg-blue-100 border border-blue-400 text-blue-700 rounded">
            {{ flash.infoMessage }}
        </div>

        <div class="bg-white rounded-md p-4">

            <!-- HEADER -->
            <div class="flex justify-between items-center mb-4">
                <h1 class="text-xl font-bold">{{ pageTitle }}</h1>
                <button
                    class="px-4 py-2 bg-gray-700 text-white rounded"
                    @click="goToList"
                >
                    Medicine Inventory List
                </button>
            </div>

            <!-- =======================
                 CSV UPLOAD (CREATE ONLY)
            ======================== -->
            <div v-if="!isEdit" class="mb-6 p-4 border rounded bg-gray-50">
                <h3 class="font-semibold mb-2">Bulk Medicine Upload (CSV)</h3>
                <p class="text-sm text-gray-600 mb-3">
                    1) Download sample CSV format. 2) Fill supplier/category in CSV rows. 3) Upload and import.
                </p>

                <div class="mb-3 p-3 border border-blue-100 bg-blue-50 rounded text-sm text-blue-800">
                    Required CSV columns: <span class="font-semibold">supplier, category, medicine_name, unit_purchase_price, unit_selling_price, quantity</span>
                    <br />
                    Optional columns: <span class="font-semibold">generic_name</span>, <span class="font-semibold">expiry_date</span> (format: YYYY-MM-DD)
                    <br />
                    Max upload size: <span class="font-semibold">up to 100 MB</span> (server limit dependent).
                </div>

                <p class="text-xs text-gray-600 mb-2">
                    Supplier and category should come from the CSV file. You may still pick defaults below (optional).
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                    <select v-model="csv.supplier_id" class="border rounded p-2">
                        <option value="">Default Supplier (optional)</option>
                        <option v-for="s in suppliers" :key="s.id" :value="s.id">
                            {{ s.name }}
                        </option>
                    </select>

                    <select v-model="csv.medicine_category_id" class="border rounded p-2">
                        <option value="">Default Category (optional)</option>
                        <option v-for="c in medicineCategories" :key="c.id" :value="c.id">
                            {{ c.name }}
                        </option>
                    </select>
                </div>

                <div
                    class="mb-3 rounded-lg border-2 border-dashed p-3 text-center transition"
                    :class="[
                        isCsvDragging ? 'border-green-600 bg-green-50' : 'border-gray-300 bg-white hover:border-green-500 hover:bg-green-50',
                        csvLoading ? 'pointer-events-none opacity-60' : 'cursor-pointer',
                    ]"
                    @dragover.prevent="isCsvDragging = true"
                    @dragleave.prevent="isCsvDragging = false"
                    @drop.prevent="handleCsvDrop"
                >
                    <input
                        id="medicine-inventory-csv"
                        type="file"
                        accept=".csv,text/csv"
                        class="sr-only"
                        :disabled="csvLoading"
                        @change="handleCsvFile"
                    />
                    <label for="medicine-inventory-csv" class="block cursor-pointer">
                        <span class="mb-1 inline-flex h-8 w-8 items-center justify-center rounded-full bg-green-100 text-lg text-green-700" aria-hidden="true">
                            ↑
                        </span>
                        <span class="block font-semibold text-gray-800">
                            {{ isCsvDragging ? 'Drop your CSV file here' : 'Choose a CSV file or drag it here' }}
                        </span>
                        <span class="mt-1 block text-sm text-gray-500">CSV file only</span>
                    </label>
                    <div v-if="csv.file" class="mt-2 flex flex-wrap items-center justify-center gap-2 text-sm">
                        <span class="font-medium text-green-800">Selected: {{ csv.file.name }}</span>
                        <span class="text-gray-500">({{ (csv.file.size / 1024 / 1024).toFixed(2) }} MB)</span>
                        <button
                            type="button"
                            class="rounded px-2 py-1 text-red-700 hover:bg-red-50 disabled:opacity-50"
                            :disabled="csvLoading"
                            @click="clearCsvFile"
                        >
                            Remove
                        </button>
                    </div>
                </div>

                <div v-if="csvLoading" class="mb-3" role="status" aria-live="polite">
                    <div class="mb-1 flex items-center justify-between gap-2 text-sm text-blue-800">
                        <span v-if="!csvUploadComplete">Uploading CSV file...</span>
                        <span v-else-if="csvImportStatus === 'processing'">
                            Importing rows ({{ csvImportCounts.imported }} added, {{ csvImportCounts.skipped }} skipped)
                        </span>
                        <span v-else-if="csvImportStatus === 'failed'">Import failed</span>
                        <span v-else-if="csvImportStatus === 'completed'">Import complete</span>
                        <span v-else>File uploaded. Waiting for the import result...</span>
                        <span v-if="csvUploadComplete">{{ csvImportProgress }}%</span>
                        <span v-else-if="csvProgressKnown">{{ csvUploadProgress }}%</span>
                    </div>
                    <div
                        class="h-3 overflow-hidden rounded-full bg-blue-100"
                        role="progressbar"
                        :aria-valuenow="csvUploadComplete ? csvImportProgress : (csvProgressKnown ? csvUploadProgress : undefined)"
                        :aria-valuemin="0"
                        :aria-valuemax="100"
                        aria-label="CSV upload progress"
                    >
                        <div
                            class="h-full rounded-full bg-green-600 transition-all duration-200"
                            :class="csvUploadComplete || csvProgressKnown ? '' : 'w-1/3 animate-pulse'"
                            :style="csvUploadComplete
                                ? { width: `${csvImportProgress}%` }
                                : (csvProgressKnown ? { width: `${csvUploadProgress}%` } : undefined)"
                        ></div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="csv.skip_duplicates" type="checkbox" class="rounded border-gray-300" />
                        Skip duplicate medicine rows
                    </label>
                </div>

                <div v-if="csvMessage" class="mb-2 p-2 rounded text-sm" :class="csvErrors.length ? 'bg-red-100 text-red-700 border border-red-200' : 'bg-green-100 text-green-700 border border-green-200'">
                    {{ csvMessage }}
                </div>

                <ul v-if="csvErrors.length" class="mb-2 pl-5 list-disc text-xs text-red-700 space-y-1">
                    <li v-for="(err, idx) in csvErrors" :key="idx">{{ err }}</li>
                </ul>

                <div class="flex gap-3">
                    <button
                        class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="csvLoading"
                        @click="uploadInventoryCsv"
                    >
                        {{ csvUploadComplete ? 'Importing...' : csvLoading ? 'Uploading...' : 'Upload Inventory CSV' }}
                    </button>

                    <a
                        :href="route('backend.medicineinventory.sample-csv')"
                        class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300"
                    >
                        Download Sample CSV
                    </a>
                </div>

                <p class="mt-2 text-xs text-amber-700">
                    CSV file is required. Supplier and category name should be provided in CSV columns; missing ones will be auto-created. Duplicate medicine rows will not be inserted.
                </p>
            </div>

            <div v-if="showImportSummary" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
                <div class="w-full max-w-lg bg-white rounded-lg shadow-lg border border-gray-200">
                    <div class="flex items-center justify-between p-4 border-b">
                        <h4 class="text-base font-semibold text-gray-800">CSV Import Summary</h4>
                        <button type="button" class="text-gray-500 hover:text-gray-700" @click="showImportSummary = false">
                            Close
                        </button>
                    </div>

                    <div class="p-4 space-y-3">
                        <p class="text-sm text-gray-700">{{ importSummary.message }}</p>

                        <div class="grid grid-cols-3 gap-2 text-sm">
                            <div class="p-2 text-center rounded bg-emerald-50 text-emerald-700 border border-emerald-100">
                                Imported: {{ importSummary.imported }}
                            </div>
                            <div class="p-2 text-center rounded bg-amber-50 text-amber-700 border border-amber-100">
                                Skipped: {{ importSummary.skipped }}
                            </div>
                            <div class="p-2 text-center rounded bg-red-50 text-red-700 border border-red-100">
                                Failed: {{ importSummary.failed }}
                            </div>
                        </div>

                        <ul v-if="importSummary.errors.length" class="max-h-48 overflow-auto pl-5 list-disc text-xs text-red-700 space-y-1">
                            <li v-for="(err, idx) in importSummary.errors" :key="idx">{{ err }}</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- =======================
                 SINGLE MEDICINE FORM
            ======================== -->
            <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-3 gap-4">

                <select v-model="form.supplier_id" class="border p-2 rounded" required>
                    <option value="">Select Supplier</option>
                    <option v-for="s in suppliers" :key="s.id" :value="s.id">
                        {{ s.name }}
                    </option>
                </select>

                <select v-model="form.medicine_category_id" class="border p-2 rounded" required>
                    <option value="">Select Category</option>
                    <option v-for="c in medicineCategories" :key="c.id" :value="c.id">
                        {{ c.name }}
                    </option>
                </select>

                <input
                    v-model="form.medicine_name"
                    type="text"
                    placeholder="Medicine Name"
                    class="border p-2 rounded"
                    required
                />

                <input
                    v-model="form.generic_name"
                    type="text"
                    placeholder="Generic Name"
                    class="border p-2 rounded"
                />

                <input
                    v-model="form.medicine_unit_purchase_price"
                    type="number"
                    step="0.01"
                    placeholder="Purchase Price"
                    class="border p-2 rounded"
                    required
                />

                <input
                    v-model="form.medicine_unit_selling_price"
                    type="number"
                    step="0.01"
                    placeholder="Selling Price"
                    class="border p-2 rounded"
                    required
                />

                <input
                    v-model="form.medicine_quantity"
                    type="number"
                    placeholder="Quantity"
                    class="border p-2 rounded"
                    required
                />

                <input
                    v-model="form.expiry_date"
                    type="date"
                    class="border p-2 rounded"
                    placeholder="Expiry Date"
                />

                <select v-model="form.status" class="border p-2 rounded">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>

                <div class="md:col-span-3 rounded border border-blue-200 bg-blue-50 p-3">
                    <h2 class="mb-1 text-sm font-semibold text-blue-900">Doctor-verified standard prescription</h2>
                    <p class="mb-3 text-xs text-blue-800">These values will be suggested in prescriptions. Verify them clinically; doctors can still change them for each patient.</p>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                        <input v-model="form.standard_dose" type="text" maxlength="255" placeholder="Standard dose (e.g. 1 tablet)" class="border p-2 rounded" />
                        <input v-model="form.standard_frequency" type="text" maxlength="255" placeholder="Standard frequency (e.g. 1-0-1)" class="border p-2 rounded" />
                        <input v-model="form.standard_duration" type="text" maxlength="255" placeholder="Standard duration (e.g. 7 days)" class="border p-2 rounded" />
                        <input v-model="form.standard_instructions" type="text" maxlength="255" placeholder="Standard instructions (e.g. after meal)" class="border p-2 rounded" />
                    </div>
                    <label class="mt-3 inline-flex items-center gap-2 text-sm text-blue-900">
                        <input v-model="form.standard_prescription_verified" type="checkbox" class="rounded border-blue-300" />
                        Doctor has reviewed and approved these standard values
                    </label>
                </div>

                <div class="md:col-span-3">
                    <button
                        type="submit"
                        class="px-6 py-2 bg-blue-600 text-white rounded"
                        :disabled="form.processing"
                    >
                        {{ isEdit ? 'Update Medicine' : 'Save Medicine' }}
                    </button>
                </div>

            </form>
        </div>
    </BackendLayout>
</template>