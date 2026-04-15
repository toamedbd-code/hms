<script setup>
import { ref, watch } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
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
    medicine_unit_purchase_price: '',
    medicine_unit_selling_price: '',
    medicine_quantity: '',
    expiry_date: '',
    status: 'Active',
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
            form.medicine_unit_purchase_price = medicine.medicine_unit_purchase_price
            form.medicine_unit_selling_price = medicine.medicine_unit_selling_price
            form.medicine_quantity = medicine.medicine_quantity
            form.expiry_date = medicine.expiry_date ?? ''
            form.status = medicine.status
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

const handleCsvFile = (e) => {
    csv.value.file = e.target.files[0]
    csvMessage.value = ''
    csvErrors.value = []
}

const uploadInventoryCsv = async () => {
    csvMessage.value = ''
    csvErrors.value = []
    showImportSummary.value = false

    if (!csv.value.file) {
        csvMessage.value = 'CSV file is required.'
        return
    }

    const formData = new FormData()
    if (csv.value.supplier_id) {
        formData.append('supplier_id', csv.value.supplier_id)
    }
    if (csv.value.medicine_category_id) {
        formData.append('medicine_category_id', csv.value.medicine_category_id)
    }
    formData.append('csv_file', csv.value.file)
    formData.append('skip_duplicates', csv.value.skip_duplicates ? '1' : '0')

    csvLoading.value = true

    try {
        const res = await axios.post(
            route('backend.medicineinventory.import.csv'),
            formData
        )
        if (res.data.status) {
            csvMessage.value = res.data.message || 'CSV imported successfully.'
            importSummary.value = {
                imported: Number(res.data.imported ?? 0),
                skipped: Number(res.data.skipped ?? 0),
                failed: 0,
                message: res.data.message || 'CSV imported successfully.',
                errors: [],
            }
            showImportSummary.value = true
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
        csvErrors.value = e.response?.data?.errors || []
        importSummary.value = {
            imported: 0,
            skipped: 0,
            failed: Array.isArray(csvErrors.value) ? csvErrors.value.length : 1,
            message: csvMessage.value,
            errors: Array.isArray(csvErrors.value) ? csvErrors.value : [],
        }
        showImportSummary.value = true
    } finally {
        csvLoading.value = false
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
                    Optional column: <span class="font-semibold">expiry_date</span> (format: YYYY-MM-DD)
                    <br />
                    Max upload size: <span class="font-semibold">up to 100 MB</span> (server limit dependent).
                </div>

                <p class="text-xs text-gray-600 mb-2">
                    Supplier and category should come from the CSV file. You may still pick defaults below (optional).
                </p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
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

                    <input type="file" accept=".csv" @change="handleCsvFile" />
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="csv.skip_duplicates" type="checkbox" class="rounded border-gray-300" />
                        Skip duplicate medicine rows
                    </label>
                    <div class="text-sm text-gray-600" v-if="csv.file">
                        Selected: <span class="font-medium">{{ csv.file.name }}</span>
                    </div>
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
                        {{ csvLoading ? 'Uploading...' : 'Upload Inventory CSV' }}
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