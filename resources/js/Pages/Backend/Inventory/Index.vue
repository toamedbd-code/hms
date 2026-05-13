
<script setup>
import { computed, ref } from "vue";
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
    router.get(route('backend.inventory.index'), filters.value, { preserveState: true });
};

const resolveFirstRoute = (routeNames = []) => {
    for (const routeName of routeNames) {
        try {
            const ziggy = route();
            if (typeof ziggy?.has === 'function' && !ziggy.has(routeName)) {
                continue;
            }
            return route(routeName);
        } catch (e) {
            // try next candidate
        }
    }

    return null;
};

const quickLinks = computed(() => {
    const links = [
        {
            label: 'General Inventory',
            icon: '📦',
            href: resolveFirstRoute(['backend.inventory.index', 'backend.backend.inventory.index']),
        },
        {
            label: 'Medicine Inventory',
            icon: '💊',
            href: resolveFirstRoute(['backend.medicineinventory.index', 'backend.backend.medicineinventory.index']),
        },
        {
            label: 'Suppliers',
            icon: '🏭',
            href: resolveFirstRoute(['backend.medicinesupplier.index', 'backend.backend.medicinesupplier.index']),
        },
        {
            label: 'Purchases',
            icon: '🛒',
            href: resolveFirstRoute(['backend.medicinepurchase.index', 'backend.backend.medicinepurchase.index']),
        },
        {
            label: 'Supplier Payments',
            icon: '💳',
            href: resolveFirstRoute(['backend.supplierpayment.index', 'backend.backend.supplierpayment.index']),
        },
        {
            label: 'Product Return',
            icon: '↩️',
            href: resolveFirstRoute(['backend.productreturn.index', 'backend.backend.productreturn.index']),
        },
        {
            label: 'Stock Center',
            icon: '📊',
            href: resolveFirstRoute(['backend.stock.index', 'backend.backend.stock.index']),
        },
        {
            label: 'Low Stock Report',
            icon: '⚠️',
            href: resolveFirstRoute(['backend.stock.low-stock-report', 'backend.backend.stock.low-stock-report']),
        },
    ];

    return links.filter((item) => Boolean(item.href));
});

const openQuickLink = (href) => {
    if (!href) return;
    router.visit(href);
};
</script>

<template>
    <BackendLayout>

        <div
            class="w-full p-4 mt-3 duration-1000 ease-in-out bg-white rounded shadow-md shadow-gray-800/50 dark:bg-slate-900">

            <div class="mb-4 rounded border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-200">General Inventory Tools</h2>
                <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                    <button
                        v-for="item in quickLinks"
                        :key="item.label"
                        type="button"
                        @click="openQuickLink(item.href)"
                        class="flex items-center justify-start gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                    >
                        <span>{{ item.icon }}</span>
                        <span class="truncate">{{ item.label }}</span>
                    </button>
                </div>
            </div>

            <div
                class="flex justify-between w-full p-4 space-x-2 text-gray-700 rounded shadow-md bg-slate-600 shadow-gray-800/50 dark:bg-gray-700 dark:text-gray-200">

                <div class="grid w-full grid-cols-1 gap-2 md:grid-cols-5">

                    <div class="flex space-x-2">
                        <div class="w-full">
                            <input id="name" v-model="filters.name"
                                class="block w-full p-2 text-sm bg-gray-300 rounded shadow-sm border-slate-100 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                type="text" placeholder="Search Inventory Name" @input="applyFilter" />
                        </div>

                    </div>
                </div>

                <div class="hidden min-w-24 md:block">
                    <select v-model="filters.numOfData" @change="applyFilter"
                            class="w-full p-2 text-sm bg-gray-300 rounded shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
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

