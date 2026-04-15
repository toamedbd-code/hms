<script setup>
import { ref, computed, watch } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AlertMessage from '@/Components/AlertMessage.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';

const props = defineProps(['permission', 'permissions', 'id']);

const form = useForm({
    name: props.permission?.name ?? '',
    guard_name: props.permission?.guard_name ?? 'admin',
    parent_id: props.permission?.parent_id ?? '',
    _method: props.permission?.id ? 'put' : 'post',
});

const moduleOptions = [
    { value: 'all', label: 'All Modules' },
    { value: 'attendance', label: 'Attendance' },
    { value: 'pathology', label: 'Pathology' },
    { value: 'payroll', label: 'Payroll' },
    { value: 'reporting', label: 'Reporting' },
    { value: 'billing', label: 'Billing' },
    { value: 'pharmacy', label: 'Pharmacy' },
    { value: 'opd', label: 'OPD' },
    { value: 'ipd', label: 'IPD' },
    { value: 'website', label: 'Website' },
    { value: 'other', label: 'Other' },
];

const inferPermissionModule = (permissionName) => {
    const name = String(permissionName ?? '').toLowerCase();

    if (/(attendance|leave|duty|roaster|salary|face)/.test(name)) return 'attendance';
    if (/pathology/.test(name)) return 'pathology';
    if (/(payroll|salary-sheet|payslip)/.test(name)) return 'payroll';
    if (/(report|reporting|invoice|print)/.test(name)) return 'reporting';
    if (/billing/.test(name)) return 'billing';
    if (/(pharmacy|medicine|supplier)/.test(name)) return 'pharmacy';
    if (/(opd|outpatient)/.test(name)) return 'opd';
    if (/(ipd|inpatient)/.test(name)) return 'ipd';
    if (/(website|cms)/.test(name)) return 'website';

    return 'other';
};

const getParentPermissionName = () => {
    if (!props.permission?.parent_id) return '';
    const matched = (props.permissions ?? []).find((item) => Number(item.id) === Number(props.permission.parent_id));
    return matched?.name ?? props.permission?.name ?? '';
};

const selectedModule = ref(inferPermissionModule(getParentPermissionName() || props.permission?.name || ''));

const filteredParentPermissions = computed(() => {
    const list = props.permissions ?? [];
    if (selectedModule.value === 'all') {
        return list;
    }

    return list.filter((permission) => inferPermissionModule(permission?.name) === selectedModule.value);
});

watch(filteredParentPermissions, (list) => {
    if (!form.parent_id) return;
    const exists = list.some((item) => Number(item.id) === Number(form.parent_id));
    if (!exists) {
        form.parent_id = '';
    }
});


const submit = () => {
    const routeName = props.id ? route('backend.permission.update', props.id) : route('backend.permission.store');
    form.transform(data => ({
        ...data,
        remember: '',
        isDirty: false,
    })).post(routeName, {

        onSuccess: (response) => {
            if (!props.id)
                form.reset();
            displayResponse(response)
        },
        onError: (errorObject) => {

            displayWarning(errorObject);
        },
    });
};



</script>

<template>
    <BackendLayout>
        <div
            class="w-full mt-3 transition duration-1000 ease-in-out transform bg-white border border-gray-700 rounded-md shadow-lg shadow-gray-800/50 dark:bg-slate-900">

            <div
                class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md shadow-md dark:bg-gray-800 dark:text-gray-200 shadow-gray-800/50">
                <div>
                    <h1 class="p-4 text-xl font-bold dark:text-white">{{ $page.props.pageTitle }}</h1>
                </div>
                <div class="p-4 py-2">
                </div>
            </div>

            <form @submit.prevent="submit" class="p-4">
                <AlertMessage />
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4">

                    <div class="col-span-1 md:col-span-2">
                        <InputLabel for="name" value="Permission Name" />
                        <input id="name"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.name" type="text" placeholder="Permission Name" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="col-span-1 md:col-span-2">
                        <InputLabel for="guard_name" value="Guard Name" />
                        <input id="guard_name"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.guard_name" type="text" readonly placeholder="Guard Name" />
                        <InputError class="mt-2" :message="form.errors.bn_name" />
                    </div>
                    <div class="col-span-1 md:col-span-2">
                        <InputLabel for="permission_module" value="Module" />
                        <select id="permission_module"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="selectedModule">
                            <template v-for="module in moduleOptions" :key="module.value">
                                <option :value="module.value">{{ module.label }}</option>
                            </template>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Module select করলে Parent Permission list filter হবে।</p>
                    </div>

                    <div class="col-span-1 md:col-span-2">
                        <InputLabel for="parent_id" value="Parent Permission " />
                        <select id="parent_id"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.parent_id" placeholder="Select Permission">
                            <option value="">--Select Parent Permission--</option>
                            <template v-for="permissionInfo in filteredParentPermissions" :key="permissionInfo.id">
                                <option :value="permissionInfo.id">{{ permissionInfo.name }}</option>
                            </template>
                        </select>
                        <InputError class="mt-2" :message="form.errors.parent_id" />
                    </div>

                </div>
                <div class="flex items-center justify-end mt-4">
                    <PrimaryButton type="submit" class="ms-4" :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing">
                        {{ ((props.id ?? false) ? 'Update' : 'Create') }}
                    </PrimaryButton>
                </div>
            </form>

        </div>
    </BackendLayout>
</template>
