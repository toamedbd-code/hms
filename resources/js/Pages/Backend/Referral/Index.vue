<script setup>
import { ref } from "vue";
import BackendLayout from '@/Layouts/BackendLayout.vue';
import BaseTable from '@/Components/BaseTable.vue';
import Pagination from '@/Components/Pagination.vue';
import { router } from '@inertiajs/vue3';

let props = defineProps({
    filters: Object,
});

const filters = ref({

    numOfData: props.filters?.numOfData ?? 10,
});

const applyFilter = () => {
    router.get(route('backend.referral.index'), filters.value, { preserveState: true });
};
const goToRefferalAdd = () => {
    router.get(route('backend.referral.create'))
}

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
                        <button @click="goToRefferalAdd"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out"
                            style="background: linear-gradient(to right, #3b82f6, #60a5fa);"
                            onmouseover="this.style.background='linear-gradient(to right, #2563eb, #3b82f6)';"
                            onmouseout="this.style.background='linear-gradient(to right, #3b82f6, #60a5fa)';">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15">
                                </path>
                            </svg>
                            Refferal Add
                        </button>
                    </div>
                </div>
            </div>
            <div
                class="flex justify-between w-full p-2 py-3 space-x-2 text-gray-700 rounded-md bg-slate-300 shadow-gray-800/50 dark:bg-gray-700 dark:text-gray-200">

                <div class="grid w-full grid-cols-1 gap-2 md:grid-cols-5">

                    <div class="flex space-x-2">
                        <div class="w-full">
                            <input id="name" v-model="filters.name"
                                class="block w-full p-2 text-sm rounded-md border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                type="text" placeholder="Name" @input="applyFilter" />
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
