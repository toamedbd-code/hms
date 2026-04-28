<script setup>
import { computed, ref } from "vue";
import BackendLayout from '@/Layouts/BackendLayout.vue';
import BaseTable from '@/Components/BaseTable.vue';
import Pagination from '@/Components/Pagination.vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    filters: Object,
});

const filters = ref({
    name: props.filters?.name ?? '',
    numOfData: props.filters?.numOfData ?? 10,
    expiry_filter: props.filters?.expiry_filter ?? 'all',
});

const page = usePage();
const expiryAlert = computed(() => page.props?.pharmacyAlerts?.medicineExpiry || {
    expired_count: 0,
    expiring_soon_count: 0,
});
const totalAlerts = computed(() => {
    const expired = Number(expiryAlert.value.expired_count || 0);
    const expiringSoon = Number(expiryAlert.value.expiring_soon_count || 0);
    return expired + expiringSoon;
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

const setExpiryFilter = (value) => {
    filters.value.expiry_filter = value;
    applyFilter();
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

                <div class="md:col-span-5 flex flex-wrap gap-2">
                    <button
                        type="button"
                        @click="setExpiryFilter('alerts')"
                        class="px-3 py-1.5 text-xs font-semibold rounded border transition"
                        :class="filters.expiry_filter === 'alerts'
                            ? 'bg-violet-600 text-white border-violet-600'
                            : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                    >
                        All Alerts ({{ totalAlerts }})
                    </button>
                    <button
                        type="button"
                        @click="setExpiryFilter('all')"
                        class="px-3 py-1.5 text-xs font-semibold rounded border transition"
                        :class="filters.expiry_filter === 'all'
                            ? 'bg-blue-600 text-white border-blue-600'
                            : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                    >
                        All Medicines
                    </button>
                    <button
                        type="button"
                        @click="setExpiryFilter('expired')"
                        class="px-3 py-1.5 text-xs font-semibold rounded border transition"
                        :class="filters.expiry_filter === 'expired'
                            ? 'bg-rose-600 text-white border-rose-600'
                            : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                    >
                        Only Expired ({{ Number(expiryAlert.expired_count || 0) }})
                    </button>
                    <button
                        type="button"
                        @click="setExpiryFilter('expiring_soon')"
                        class="px-3 py-1.5 text-xs font-semibold rounded border transition"
                        :class="filters.expiry_filter === 'expiring_soon'
                            ? 'bg-amber-500 text-white border-amber-500'
                            : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                    >
                        Expiring Soon (30 Days) ({{ Number(expiryAlert.expiring_soon_count || 0) }})
                    </button>
                </div>
            </div>

            <!-- ===== Table ===== -->
            <div class="w-full overflow-x-auto">
                <BaseTable />
            </div>

            <!-- ===== Pagination ===== -->
            <Pagination v-if="$page.props.datas?.links" />

        </div>

    </BackendLayout>
</template>
