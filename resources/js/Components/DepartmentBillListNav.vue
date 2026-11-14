<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    active: {
        type: String,
        required: true,
    },
});

const page = usePage();
const isPharmacyUser = computed(() => page.props.auth?.admin?.department_scope === 'pharmacy');
const permissions = computed(() => {
    const raw = page.props.auth?.permissions ?? [];
    if (Array.isArray(raw)) return raw.map((permission) => String(permission).toLowerCase());
    if (raw && typeof raw === 'object') return Object.values(raw).map((permission) => String(permission).toLowerCase());
    return [];
});

const departments = computed(() => {
    const available = [
        { key: 'billing', label: 'Billing', permission: 'billing', href: () => route('backend.billing.list') },
        { key: 'opd', label: 'OPD Bills', permission: 'opd-patient-list', href: () => route('backend.opdpatient.index') },
        { key: 'ipd', label: 'IPD Bills', permission: 'ipd-patient-list', href: () => route('backend.billing.list', { ipd: 1 }) },
        { key: 'appointment', label: 'Appointments', permission: 'appoinment-list', href: () => route('backend.appoinment.index') },
        { key: 'pharmacy', label: 'Pharmacy Bills', permission: 'pharmacy-bill-list', href: () => route('backend.pharmacybill.index') },
        { key: 'prescription', label: 'Prescriptions', permission: 'prescription-list', href: () => route('backend.prescription.index') },
    ];

    return available.filter((department) => permissions.value.includes(department.permission));
});
</script>

<template>
    <nav
        v-if="departments.length && !isPharmacyUser"
        aria-label="Bill departments"
        class="mb-3 flex flex-wrap gap-2 rounded-md border border-slate-200 bg-slate-50 p-2 dark:border-slate-700 dark:bg-slate-800"
    >
        <Link
            v-for="department in departments"
            :key="department.key"
            :href="department.href()"
            :aria-current="props.active === department.key ? 'page' : undefined"
            class="rounded-md px-3 py-2 text-sm font-semibold transition-colors"
            :class="props.active === department.key
                ? 'bg-indigo-600 text-white shadow-sm'
                : 'bg-white text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600'"
        >
            {{ department.label }}
        </Link>
    </nav>
</template>
