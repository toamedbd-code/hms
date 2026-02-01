<script setup>
import { ref } from "vue";
import BackendLayout from '@/Layouts/BackendLayout.vue';
import BaseTable from '@/Components/BaseTable.vue';
import Pagination from '@/Components/Pagination.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    filters: Object,
});

const filters = ref({
    name: props.filters?.name ?? '',
    numOfData: props.filters?.numOfData ?? 10,
});

const applyFilter = () => {
    router.get(
        route('backend.medicineinventory.index'),
        filters.value,
        {
            preserveState: true,
            replace: true,
        }
    );
};

const goToInventoryAdd = () => {
    router.visit(route('backend.medicineinventory.create'));
};
</script>

<template>
    <BackendLayout>

        <div class="w-full p-3 bg-white rounded-md dark:bg-slate-900">

            <!-- ===== Header ===== -->
            <div
                class="flex items-center justify-between mb-3 bg-gray-100 rounded-md dark:bg-gray-800"
            >
                <h1 class="p-4 text-xl font-bold dark:text-white">
                    {{ $page.props.pageTitle }}
                </h1>

                <div class="p-4">
                    <button
                        @click="goToInventoryAdd"
                        class="inline-flex items-center px-4 py-2 text-sm font-semibold text-white rounded-md shadow"
                        style="background: linear-gradient(to right, #3b82f6, #60a5fa);"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Medicine Inventory Add
                    </button>
                </div>
            </div>

            <!-- ===== Filter Bar ===== -->
            <div
                class="grid grid-cols-1 gap-3 p-3 mb-3 bg-slate-200 rounded-md md:grid-cols-5 dark:bg-gray-700"
            >
                <!-- Search -->
                <div class="md:col-span-3">
                    <input
                        v-model="filters.name"
                        @input="applyFilter"
                        type="text"
                        placeholder="Search by Medicine Name"
                        class="w-full p-2 text-sm border rounded-md dark:bg-slate-700 dark:text-white"
                    />
                </div>

                <!-- Show Per Page -->
                <div class="md:col-span-2">
                    <select
                        v-model="filters.numOfData"
                        @change="applyFilter"
                        class="w-full p-2 text-sm border rounded-md dark:bg-slate-700 dark:text-white"
                    >
                        <option value="10">Show 10</option>
                        <option value="20">Show 20</option>
                        <option value="30">Show 30</option>
                        <option value="50">Show 50</option>
                        <option value="100">Show 100</option>
                    
                    </select>
                </div>
            </div>

            <!-- ===== Table ===== -->
            <div class="w-full overflow-x-auto">
                <BaseTable />
            </div>

            <!-- ===== Pagination ===== -->
            <Pagination v-if="$page.props.data?.links" />

        </div>

    </BackendLayout>
</template>
