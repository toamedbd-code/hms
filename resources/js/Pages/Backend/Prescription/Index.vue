<script setup>
import { ref } from "vue";
import BackendLayout from '@/Layouts/BackendLayout.vue';
import BaseTable from '@/Components/BaseTable.vue';
import Pagination from '@/Components/Pagination.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    filters: Object,
    canCreate: { type: Boolean, default: false },
});

const filters = ref({
    prescription_no: props.filters?.prescription_no ?? '',
    patient_name: props.filters?.patient_name ?? '',
    date_from: props.filters?.date_from ?? '',
    date_to: props.filters?.date_to ?? '',
    numOfData: props.filters?.numOfData ?? 10,
});

const applyFilter = () => {
    router.get(route('backend.prescription.index'), filters.value, { preserveState: true });
};

const goToAdd = () => {
    router.visit(route('backend.prescription.create'));
};

const goBack = () => {
    if (window.history.length > 1) {
        window.history.back();
        return;
    }
    router.visit(route('backend.dashboard'));
};
</script>

<template>
    <BackendLayout>
        <div class="w-full min-w-0 p-2 duration-1000 ease-in-out bg-white rounded-md dark:bg-slate-900">
            <div
                class="flex mb-2 items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md dark:bg-gray-800 dark:text-gray-200 shadow-gray-800/50">
                <div>
                    <h1 class="p-4 text-xl font-bold dark:text-white">{{ $page.props.pageTitle }}</h1>
                </div>
                <div class="p-4 py-2 flex items-center space-x-2">
                    <button @click="goBack"
                        class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gray-500 border-0 rounded-md shadow-lg hover:bg-gray-600">
                        Back
                    </button>
                    <button v-if="props.canCreate" @click="goToAdd"
                        class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white border-0 rounded-md shadow-lg"
                        style="background: linear-gradient(to right, #3b82f6, #60a5fa);">
                        Prescription Add
                    </button>
                </div>
            </div>


            <div class="flex justify-between w-full min-w-0 p-2 py-3 space-x-2 text-gray-700 rounded-md bg-slate-300 dark:bg-gray-700 dark:text-gray-200">
                <div class="grid w-full grid-cols-1 gap-2 md:grid-cols-5">
                    <input v-model="filters.prescription_no" type="text" placeholder="Prescription No"
                        class="block p-2 text-sm rounded-md border-slate-300 dark:bg-slate-700" @input="applyFilter" />
                    <input v-model="filters.patient_name" type="text" placeholder="Patient Name"
                        class="block p-2 text-sm rounded-md border-slate-300 dark:bg-slate-700" @input="applyFilter" />
                    <input v-model="filters.date_from" type="date"
                        class="block p-2 text-sm rounded-md border-slate-300 dark:bg-slate-700" @change="applyFilter" />
                    <input v-model="filters.date_to" type="date"
                        class="block p-2 text-sm rounded-md border-slate-300 dark:bg-slate-700" @change="applyFilter" />
                    <select v-model="filters.numOfData" @change="applyFilter"
                        class="p-2 text-sm rounded-md border-slate-300 dark:bg-slate-700">
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>
            </div>

            <div class="w-full min-w-0 my-3 overflow-x-auto">
                <BaseTable />
            </div>
            <Pagination />
        </div>
    </BackendLayout>
</template>
