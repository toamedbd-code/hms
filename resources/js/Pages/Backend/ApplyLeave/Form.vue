<script setup>
import { ref, onMounted } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AlertMessage from '@/Components/AlertMessage.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';

const props = defineProps(["applyleave", "id", "leaveTypes", "employeeDetails"]);

const form = useForm({
    apply_date: props.applyleave?.apply_date ?? '',
    leave_type_id: props.applyleave?.leave_type_id ?? '',
    employee_id: props.applyleave?.employee_id ?? '',
    from: props.applyleave?.from ?? '',
    to: props.applyleave?.to ?? '',
    reason: props.applyleave?.reason ?? '',
    attachment: props.applyleave?.attachment ?? '',

    _method: props.applyleave?.id ? "put" : "post",
});

const handlefileChange = (event) => {
    const file = event.target.files[0];
    form.attachment = file;
};

const submit = () => {
    const routeName = props.id
        ? route("backend.applyleave.update", props.id)
        : route("backend.applyleave.store");
    form
        .transform((data) => ({
            ...data,
            remember: "",
            isDirty: false,
        }))
        .post(routeName, {
            onSuccess: (response) => {
                if (!props.id) form.reset();
                displayResponse(response);
            },
            onError: (errorObject) => {
                displayWarning(errorObject);
            },
        });
};

const goToApplyLeaveList = () => {
    router.visit(route('backend.applyleave.index'));
};

</script>

<template>
    <BackendLayout>
        <div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md">

            <div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md">
                <div>
                    <h1 class="p-4 text-xl font-bold">{{ $page.props.pageTitle }}</h1>
                </div>

                <div class="flex items-center p-3 py-2 space-x-1">
                    <div class="p-2 py-2 flex items-center space-x-2">
                        <div class="flex items-center space-x-3">
                            <button @click="goToApplyLeaveList"
                                class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2">
                                <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z">
                                    </path>
                                </svg>
                                Apply Leave List
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="p-4">
                <AlertMessage />
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4">
                    <div class="col-span-1 md:col-span-2">
                        <InputLabel for="apply_date" value="Apply date" />
                        <input id="apply_date"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.apply_date" type="date" placeholder="Apply date" />
                        <InputError class="mt-2" :message="form.errors.apply_date" />
                    </div>
                    <div class="col-span-1 md:col-span-2">
                        <InputLabel for="leave_type_id" value="Leave type" />
                        <select id="leave_type_id"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.leave_type_id" type="text" placeholder="Leave type">
                            <option value="">Select A Type</option>
                            <option v-for="leaveType in leaveTypes" :key="leaveType.id" :value="leaveType.id">{{
                                leaveType.type_name }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.leave_type_id" />
                    </div>
                    <div class="col-span-1 md:col-span-2">
                        <InputLabel for="employee_id" value="Employee Name" />
                        <select id="employee_id"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.employee_id" type="text" placeholder="Employee Name">
                            <option value="">Select Name</option>
                            <option v-for="user in employeeDetails" :key="user.id" :value="user.id">{{
                                user.first_name }}{{ user.last_name }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.employee_id" />
                    </div>
                    <div class="col-span-1 md:col-span-2">
                        <InputLabel for="from" value="From" />
                        <input id="from"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.from" type="date" placeholder="From" />
                        <InputError class="mt-2" :message="form.errors.from" />
                    </div>
                    <div class="col-span-1 md:col-span-2">
                        <InputLabel for="to" value="To" />
                        <input id="to"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.to" type="date" placeholder="To" />
                        <InputError class="mt-2" :message="form.errors.to" />
                    </div>
                    <div class="col-span-1 md:col-span-2">
                        <InputLabel for="reason" value="Reason" />
                        <textarea id="reason"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.reason" type="text" placeholder="Reason"></textarea>
                        <InputError class="mt-2" :message="form.errors.reason" />
                    </div>
                    <div class="col-span-1 md:col-span-2">
                        <InputLabel for="attachment" value="Attachment" />
                        <input id="attachment"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            type="file" @change="handlefileChange" placeholder="Attachment" />
                        <InputError class="mt-2" :message="form.errors.attachment" />
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
