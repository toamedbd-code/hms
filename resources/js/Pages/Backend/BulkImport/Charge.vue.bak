<script setup>
import { ref } from "vue";
import { router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';

let props = defineProps({
    importErrors: Array,
    success: String,
    errors: Object,
});

const csvFile = ref(null);

const submitForm = () => {
    if (!csvFile.value) {
        alert('Please select a CSV file first');
        return;
    }

    const formData = new FormData();
    formData.append('csv_file', csvFile.value);

    router.post(route('backend.charges.import.process'), formData, {
        preserveScroll: true,
        onSuccess: (response) => {
            // Reset form after successful import
            csvFile.value = null;
            // Reset file input
            const fileInput = document.getElementById('csv_file');
            if (fileInput) {
                fileInput.value = '';
            }
        },
        onError: (errors) => {
            console.error('Import errors:', errors);
        },
    });
};

// Fixed download function
const downloadSample = () => {
    // Create a direct link to download the file
    const link = document.createElement('a');
    link.href = route('backend.charges.import.sample');
    link.download = 'charge_import_sample.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

const handleFileChange = (event) => {
    csvFile.value = event.target.files[0];
};
</script>

<template>
    <BackendLayout>
        <div class="w-full p-2 duration-1000 ease-in-out bg-white rounded-md dark:bg-slate-900">
            <div
                class="flex mb-2 items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md dark:bg-gray-800 dark:text-gray-200 shadow-gray-800/50">
                <div>
                    <h1 class="p-4 text-xl font-bold dark:text-white">Bulk Import Charges</h1>
                </div>
            </div>

            <div class="w-full p-4 bg-white rounded-md dark:bg-slate-800 dark:text-gray-200 shadow-gray-800/50">
                <!-- Success Message -->
                <div v-if="success"
                    class="p-4 mb-4 text-green-800 bg-green-100 rounded-md dark:bg-green-900 dark:text-green-200">
                    {{ success }}
                </div>

                <!-- Import Errors -->
                <div v-if="importErrors && importErrors.length"
                    class="p-4 mb-4 text-yellow-800 bg-yellow-100 rounded-md dark:bg-yellow-900 dark:text-yellow-200">
                    <h5 class="font-bold">Import completed with errors:</h5>
                    <ul class="mt-2 ml-4 list-disc">
                        <li v-for="error in importErrors" :key="error">{{ error }}</li>
                    </ul>
                </div>

                <!-- Validation Errors -->
                <div v-if="errors && Object.keys(errors).length"
                    class="p-4 mb-4 text-red-800 bg-red-100 rounded-md dark:bg-red-900 dark:text-red-200">
                    <h5 class="font-bold">Validation Errors:</h5>
                    <ul class="mt-2 ml-4 list-disc">
                        <li v-for="(error, field) in errors" :key="field">{{ error[0] }}</li>
                    </ul>
                </div>

                <form @submit.prevent="submitForm" class="space-y-4">
                    <div>
                        <label for="csv_file"
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">CSV File</label>
                        <input id="csv_file" type="file" accept=".csv" @change="handleFileChange" required
                            class="block w-full p-2 text-sm rounded-md border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Please upload a CSV file with the
                            correct format.</p>
                    </div>

                    <div class="flex justify-end space-x-2">
                        <button type="button" @click="downloadSample"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-200 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Download Sample CSV
                        </button>
                        <button type="submit"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out"
                            style="background: linear-gradient(to right, #3b82f6, #60a5fa);"
                            @mouseover="($event) => $event.target.style.background = 'linear-gradient(to right, #2563eb, #3b82f6)'"
                            @mouseout="($event) => $event.target.style.background = 'linear-gradient(to right, #3b82f6, #60a5fa)'">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                            </svg>
                            Import Data
                        </button>
                    </div>
                </form>

                <div class="mt-6 p-4 bg-gray-50 rounded-md dark:bg-gray-700">
                    <h5 class="font-bold text-gray-700 dark:text-gray-200 mb-2">CSV Format Requirements:</h5>
                    <ul class="text-sm text-gray-600 dark:text-gray-300 space-y-1">
                        <li>File must be in CSV format with the following columns in order:</li>
                        <ul class="ml-4 list-disc space-y-1">
                            <li>charge_type_name (required)</li>
                            <li>charge_type_modules (required, comma-separated)</li>
                            <li>charge_category_name (required)</li>
                            <li>charge_category_description (required)</li>
                            <li>charge_unit_type_name (required)</li>
                            <li>charge_tax_category_name (required)</li>
                            <li>tax_category_percentage (required, numeric)</li>
                            <li>charge_name (required)</li>
                            <li>charge_tax (optional, numeric)</li>
                            <li>charge_standard_charge (optional, numeric)</li>
                            <li>charge_description (optional)</li>
                            <li>status (required: Active, Inactive, or Deleted)</li>
                        </ul>
                        <li>The first row must contain the header names exactly as shown above</li>
                        <li>Use the "Download Sample CSV" button to get a properly formatted file</li>
                    </ul>
                </div>
            </div>
        </div>
    </BackendLayout>
</template>