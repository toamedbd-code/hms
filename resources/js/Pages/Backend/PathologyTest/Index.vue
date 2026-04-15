<script setup>
import { ref } from "vue";
import BackendLayout from '@/Layouts/BackendLayout.vue';
import BaseTable from '@/Components/BaseTable.vue';
import Pagination from '@/Components/Pagination.vue';
import { router, useForm } from '@inertiajs/vue3';
import { displayResponse, displayWarning } from '@/responseMessage.js';

let props = defineProps({
    filters: Object,
});

const filters = ref({

    numOfData: props.filters?.numOfData ?? 10,
});

const applyFilter = () => {
    router.get(route('backend.testpathology.index'), filters.value, { preserveState: true });
};

const goToTestAdd = () => {
    router.visit(route('backend.testpathology.create'));
};

const importForm = useForm({
    csv_file: null,
});

const csvRequiredFields = [
    'category_type',
    'test_name',
    'test_category',
];

const csvTestInfoFields = [
    'test_short_name',
    'test_type',
    'test_sub_category',
    'method',
    'report_days',
];

const csvChargeFields = [
    'charge_category_id',
    'charge_name',
    'tax',
    'standard_charge',
    'amount',
];

const csvParameterFields = [
    'parameter_name',
    'reference_from',
    'reference_to',
    'unit',
    'normal_range',
];

const handleFileChange = (event) => {
    const file = event.target.files?.[0] || null;
    if (!file) return;

    importForm.csv_file = file;
    importForm.post(route('backend.testpathology.import'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: (response) => {
            displayResponse(response);
        },
        onError: (errors) => {
            displayWarning(errors);
        },
        onFinish: () => {
            importForm.reset('csv_file');
            event.target.value = '';
        }
    });
};

</script>

<template>
    <BackendLayout>

        <div class="w-full p-2 duration-1000 ease-in-out bg-white rounded-md dark:bg-slate-900">

            <div
                class="flex mb-2 items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md dark:bg-gray-800 dark:text-gray-200 shadow-gray-800/50">
                <div>
                    <h1 class="p-4 text-xl font-bold dark:text-white">{{ $page.props.pageTitle }}</h1>
                </div>

                <div class="p-4 py-2 flex items-center space-x-2">
                    <div class="flex items-center space-x-3">
                        <div class="flex items-center bg-gray-50 dark:bg-gray-800 rounded-lg p-1 shadow-inner">
                            <a :href="route('backend.testpathology.sample-csv')"
                                class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out"
                                style="background: linear-gradient(to right, #f59e0b, #fbbf24);"
                                onmouseover="this.style.background='linear-gradient(to right, #d97706, #f59e0b)';"
                                onmouseout="this.style.background='linear-gradient(to right, #f59e0b, #fbbf24)';">
                                <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 6v6m0 0v6m0-6H6m6 0h6"></path>
                                </svg>
                                Download Sample CSV
                            </a>
                        </div>
                        <div class="flex items-center bg-gray-50 dark:bg-gray-800 rounded-lg p-1 shadow-inner">
                            <label class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out cursor-pointer"
                                style="background: linear-gradient(to right, #10b981, #34d399);"
                                onmouseover="this.style.background='linear-gradient(to right, #059669, #10b981)';"
                                onmouseout="this.style.background='linear-gradient(to right, #10b981, #34d399)';">
                                <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M7 10l5-5m0 0l5 5m-5-5v12"></path>
                                </svg>
                                CSV Upload
                                <input type="file" accept=".csv,text/csv" class="hidden" @change="handleFileChange" />
                            </label>
                        </div>
                        <div class="flex items-center bg-gray-50 dark:bg-gray-800 rounded-lg p-1 shadow-inner">
                            <button @click="goToTestAdd"
                                class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out"
                                style="background: linear-gradient(to right, #3b82f6, #60a5fa);"
                                onmouseover="this.style.background='linear-gradient(to right, #2563eb, #3b82f6)';"
                                onmouseout="this.style.background='linear-gradient(to right, #3b82f6, #60a5fa)';">
                                <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15">
                                    </path>
                                </svg>
                                Test Add
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-3 text-sm text-emerald-900 dark:border-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-100">
                <p class="font-semibold">CSV Upload Format</p>
                <p class="mt-1 text-xs">Use exact header names. `category_type`, `test_name`, `test_category` are required.</p>

                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide">Required</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span v-for="field in csvRequiredFields" :key="`required-${field}`"
                                class="rounded-md bg-white px-2 py-1 text-xs font-medium text-emerald-800 shadow-sm ring-1 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-100 dark:ring-emerald-700">
                                {{ field }}
                            </span>
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide">Test Info</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span v-for="field in csvTestInfoFields" :key="`info-${field}`"
                                class="rounded-md bg-white px-2 py-1 text-xs font-medium text-emerald-800 shadow-sm ring-1 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-100 dark:ring-emerald-700">
                                {{ field }}
                            </span>
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide">Charge</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span v-for="field in csvChargeFields" :key="`charge-${field}`"
                                class="rounded-md bg-white px-2 py-1 text-xs font-medium text-emerald-800 shadow-sm ring-1 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-100 dark:ring-emerald-700">
                                {{ field }}
                            </span>
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide">Test Parameters</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span v-for="field in csvParameterFields" :key="`param-${field}`"
                                class="rounded-md bg-white px-2 py-1 text-xs font-medium text-emerald-800 shadow-sm ring-1 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-100 dark:ring-emerald-700">
                                {{ field }}
                            </span>
                        </div>
                    </div>
                </div>

                <p class="mt-3 text-xs">
                    Tip: Multiple parameters in one row দিতে চাইলে `|` ব্যবহার করুন। Example: `parameter_name` = `Hemoglobin|WBC`
                </p>
            </div>

            <div
                class="flex justify-between w-full p-2 py-3 space-x-2 text-gray-700 rounded-md bg-slate-300 shadow-gray-800/50 dark:bg-gray-700 dark:text-gray-200">

                <div class="grid w-full grid-cols-1 gap-2 md:grid-cols-5">

                    <div class="flex space-x-2">
                        <div class="w-full">
                            <input id="name" v-model="filters.test_name"
                                class="block w-full p-2 text-sm rounded-md border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                type="text" placeholder="Test Name" @input="applyFilter" />
                        </div>

                        <div class="block min-w-24 md:hidden">
                            <select v-model="filters.numOfData" @change="applyFilter"
                                class="w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                                <option value="10">Show 10</option>
                                <option value="20">Show 20</option>
                                <option value="30">Show 30</option>
                                <option value="40">Show 40</option>
                                <option value="100">Show 100</option>
                                <option value="150">Show 150</option>
                                <option value="500">Show 500</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="hidden min-w-24 md:block">
                    <select v-model="filters.numOfData" @change="applyFilter"
                        class="w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                        <option value="10">show 10</option>
                        <option value="20">show 20</option>
                        <option value="30">show 30</option>
                        <option value="40">show 40</option>
                        <option value="100">show 100</option>
                        <option value="150">show 150</option>
                        <option value="500">show 500</option>
                    </select>
                </div>
            </div>

            <div class="w-full my-3 overflow-x-auto">
                <BaseTable />
            </div>
            <Pagination />
        </div>
    </BackendLayout>
</template>
