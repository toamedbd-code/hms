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
})

const csvLoading = ref(false)

const handleCsvFile = (e) => {
    csv.value.file = e.target.files[0]
}

const uploadInventoryCsv = async () => {
    if (!csv.value.supplier_id || !csv.value.medicine_category_id || !csv.value.file) {
        alert('Supplier, Category & CSV file required')
        return
    }

    const formData = new FormData()
    formData.append('supplier_id', csv.value.supplier_id)
    formData.append('medicine_category_id', csv.value.medicine_category_id)
    formData.append('csv_file', csv.value.file)

    csvLoading.value = true

    try {
        const res = await axios.post(
            route('backend.medicineinventory.import.csv'),
            formData
        )
        if (res.data.status) {
            alert(res.data.message || 'CSV Imported Successfully')
            router.visit(route('backend.medicineinventory.index'))
        } else {
            alert(res.data.message || 'CSV upload failed')
        }
    } catch (e) {
        alert(e.response?.data?.message || 'CSV upload failed')
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
                <h3 class="font-semibold mb-3">Bulk Medicine Upload (CSV)</h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                    <select v-model="csv.supplier_id" class="border rounded p-2">
                        <option value="">Select Supplier</option>
                        <option v-for="s in suppliers" :key="s.id" :value="s.id">
                            {{ s.name }}
                        </option>
                    </select>

                    <select v-model="csv.medicine_category_id" class="border rounded p-2">
                        <option value="">Select Category</option>
                        <option v-for="c in medicineCategories" :key="c.id" :value="c.id">
                            {{ c.name }}
                        </option>
                    </select>

                    <input type="file" accept=".csv" @change="handleCsvFile" />
                </div>

                <div class="flex gap-3">
                    <button
                        class="px-4 py-2 bg-green-600 text-white rounded"
                        :disabled="csvLoading"
                        @click="uploadInventoryCsv"
                    >
                        {{ csvLoading ? 'Uploading...' : 'Upload Inventory CSV' }}
                    </button>

                    <a
                        href="/sample/medicine_inventory_sample.csv"
                        target="_blank"
                        class="px-4 py-2 bg-gray-300 rounded"
                    >
                        Download Sample CSV
                    </a>
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