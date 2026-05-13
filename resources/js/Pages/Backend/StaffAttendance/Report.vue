<script setup>
import { computed, ref } from "vue";
import BackendLayout from '@/Layouts/BackendLayout.vue';
import BaseTable from '@/Components/BaseTable.vue';
import Pagination from '@/Components/Pagination.vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    datas: Array,
    filters: {
        type: Object,
        default: () => ({}),
    },
    websetting: {
        type: Object,
        default: () => ({}),
    },
});

const integrationOptions = computed(() => {
    const defaults = {
        modules: {
            fingerprint: true,
            face_attendance: true,
            leave: true,
            duty_roster: true,
            salary_sheet: true,
        },
    };

    const raw = props.websetting?.attendance_device_options;
    if (!raw) return defaults;

    try {
        const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
        return {
            ...defaults,
            ...parsed,
            modules: {
                ...defaults.modules,
                ...(parsed?.modules ?? {}),
            },
        };
    } catch (error) {
        return defaults;
    }
});

const filters = ref({
    numOfData: props.filters?.numOfData ?? 10,
    attendance_date: new Date().toISOString().split('T')[0], 
});


const applyFilter = () => {
    router.get(route('backend.staffattendance.report'), filters.value, { preserveState: true });
};

</script>

<template>
    <BackendLayout>

        <div
            class="w-full p-4 mt-3 duration-1000 ease-in-out bg-white rounded-md shadow-md shadow-gray-800/50 dark:bg-slate-900">

            <div class="flex flex-wrap items-center justify-between gap-2 py-2">
                <h1 class="text-xl font-bold dark:text-white">{{ $page.props.pageTitle }}</h1>
                <Link
                    v-if="integrationOptions.modules.salary_sheet"
                    :href="route('backend.staffattendance.salary-sheet')"
                    class="px-3 py-2 text-xs text-white bg-emerald-600 rounded hover:bg-emerald-700"
                >
                    Salary Sheet
                </Link>
            </div>

            <div class="mb-3 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">
                <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold bg-emerald-600 text-white mr-2">Auto Synced</span>
                Face/Device events flow into attendance report, duty roster impact, and salary sheet calculations.
            </div>

            <div class="grid grid-cols-1 gap-2 mt-2 mb-3 sm:grid-cols-2 lg:grid-cols-5 print:hidden">
                <a
                    v-if="integrationOptions.modules.face_attendance"
                    :href="route('backend.attendance.face')"
                    class="px-3 py-2 text-xs font-semibold text-violet-700 border border-violet-200 rounded bg-violet-50 hover:bg-violet-100"
                >
                    Face Attendance
                </a>
                <a
                    v-if="integrationOptions.modules.fingerprint || integrationOptions.modules.face_attendance"
                    href="/admin/attendance/devices"
                    class="px-3 py-2 text-xs font-semibold text-cyan-700 border border-cyan-200 rounded bg-cyan-50 hover:bg-cyan-100"
                >
                    Device Settings
                </a>
                <Link
                    v-if="integrationOptions.modules.leave"
                    :href="route('backend.pending.request')"
                    class="px-3 py-2 text-xs font-semibold text-amber-700 border border-amber-200 rounded bg-amber-50 hover:bg-amber-100"
                >
                    Leave Requests
                </Link>
                <Link
                    v-if="integrationOptions.modules.duty_roster"
                    :href="route('backend.staffattendance.duty-roster')"
                    class="px-3 py-2 text-xs font-semibold text-emerald-700 border border-emerald-200 rounded bg-emerald-50 hover:bg-emerald-100"
                >
                    Duty Roster
                </Link>
                <Link
                    v-if="integrationOptions.modules.salary_sheet"
                    :href="route('backend.staffattendance.salary-sheet')"
                    class="px-3 py-2 text-xs font-semibold text-slate-700 border border-slate-200 rounded bg-slate-50 hover:bg-slate-100"
                >
                    Salary Sheet
                </Link>
            </div>

            <div
                class="flex justify-between w-full p-2 py-3 space-x-2 text-gray-700 rounded-md shadow-md bg-slate-300 shadow-gray-800/50 dark:bg-gray-700 dark:text-gray-200">

                <div class="grid w-full grid-cols-1 gap-2 md:grid-cols-5">

                    <div class="flex space-x-2">
                        <div class="w-full">
                            <input id="title_en" v-model="filters.staff_id"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                type="text" placeholder="Staff Id" @input="applyFilter" />
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
                    <div class="w-full">
                        <input id="title_en" v-model="filters.name"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            type="text" placeholder="Name" @input="applyFilter" />
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
