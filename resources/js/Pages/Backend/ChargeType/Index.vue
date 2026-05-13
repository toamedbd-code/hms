<script setup>
import { ref } from "vue";
import BackendLayout from '@/Layouts/BackendLayout.vue';
import BaseTable from '@/Components/BaseTable.vue';
import Pagination from '@/Components/Pagination.vue';
import { router } from '@inertiajs/vue3';
import { displayResponse, displayWarning } from '@/responseMessage.js';

let props = defineProps({
    filters: Object,
    datas: Object,
    tableHeaders: Array,
    dataFields: Array,
});

const filters = ref({
    numOfData: props.filters?.numOfData ?? 10,
});

const applyFilter = () => {
    router.get(route('backend.chargetype.index'), filters.value, { preserveState: true });
};

const toggleModule = (chargeTypeId, moduleName, currentStatus) => {
    const action = currentStatus ? 'remove' : 'add';

    router.post(route('backend.chargetype.toggle-module'), {
        charge_type_id: chargeTypeId,
        module: moduleName,
        action: action
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (response) => {
            displayResponse(response);
        },
        onError: (errors) => {
            console.error('Error updating module:', errors);
        }
    });
};

const isModuleActive = (chargeType, moduleName) => {
    const modules = JSON.parse(chargeType.modules || '[]');
    return modules.includes(moduleName);
};

const allModules = ['Appointment', 'OPD', 'IPD', 'Pathology', 'Radiology', 'Blood Bank', 'Ambulance'];

const goToChargeTypeAdd = () => {
    router.get(route('backend.chargetype.create'));
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
                            <button @click="goToChargeTypeAdd"
                                class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out"
                                style="background: linear-gradient(to right, #3b82f6, #60a5fa);"
                                onmouseover="this.style.background='linear-gradient(to right, #2563eb, #3b82f6)';"
                                onmouseout="this.style.background='linear-gradient(to right, #3b82f6, #60a5fa)';">
                                <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15">
                                    </path>
                                </svg>
                                Charge Type Add
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Filters -->
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

            <!-- Custom Table with Checkboxes -->
            <div class="w-full my-3 overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 dark:bg-slate-800 dark:border-slate-600">
                    <thead class="bg-gray-50 dark:bg-slate-700">
                        <tr>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-black uppercase tracking-wider dark:text-gray-300">
                                Sl/No
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-black uppercase tracking-wider dark:text-gray-300">
                                Charge Type
                            </th>
                            <th v-for="module in allModules" :key="module"
                                class="px-4 py-3 text-center text-xs font-medium text-black uppercase tracking-wider dark:text-gray-300">
                                {{ module }}
                            </th>
                            <th
                                class="px-4 py-3 text-center text-xs font-medium text-black uppercase tracking-wider dark:text-gray-300">
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-slate-800 dark:divide-slate-600">
                        <tr v-for="(item, index) in datas.data" :key="item.id"
                            class="hover:bg-gray-50 dark:hover:bg-slate-700">

                            <!-- Serial Number -->
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                {{ index + 1 + (datas.current_page - 1) * datas.per_page }}
                            </td>

                            <!-- Charge Type Name -->
                            <td
                                class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ item.name }}
                            </td>

                            <!-- Module Checkboxes -->
                            <td v-for="module in allModules" :key="module"
                                class="px-4 py-4 whitespace-nowrap text-center">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" :checked="isModuleActive(item, module)"
                                        @change="toggleModule(item.id, module, isModuleActive(item, module))"
                                        class="form-checkbox h-5 w-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500 focus:ring-2 dark:bg-slate-700 dark:border-slate-500" />
                                    <span class="sr-only">Toggle {{ module }} for {{ item.name }}</span>
                                </label>
                            </td>

                            <!-- Action Buttons -->
                            <td class="px-4 py-4 whitespace-nowrap text-center text-sm font-medium">
                                <div class="flex justify-center space-x-2">
                                    <!-- Status Toggle -->
                                    <a :href="route('backend.chargetype.status.change', { id: item.id, status: item.status === 'Active' ? 'Inactive' : 'Active' })"
                                        :class="[
                                            'px-3 py-1 rounded text-xs font-semibold text-white',
                                            item.status === 'Active' ? 'bg-gray-500 hover:bg-gray-600' : 'bg-green-500 hover:bg-green-600'
                                        ]">
                                        {{ item.status === 'Active' ? 'Inactive' : 'Active' }}
                                    </a>

                                    <!-- Edit Button -->
                                    <a :href="route('backend.chargetype.edit', item.id)"
                                        class="px-3 py-1 rounded text-xs font-semibold bg-yellow-400 text-black hover:bg-yellow-500">
                                        Edit
                                    </a>

                                    <!-- Delete Button -->
                                    <button @click="deleteChargeType(item.id)"
                                        class="px-3 py-1 rounded text-xs font-semibold bg-red-500 text-white hover:bg-red-600">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :data="datas" />
        </div>
    </BackendLayout>
</template>

<style scoped>
.form-checkbox {
    transition: all 0.2s ease-in-out;
}

.form-checkbox:checked {
    background-color: #3b82f6;
    border-color: #3b82f6;
}

.form-checkbox:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
</style>