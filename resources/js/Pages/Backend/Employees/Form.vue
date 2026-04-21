<script setup>
import { ref } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps(['employee', 'pageTitle']);

const form = useForm({
    employee_id: props.employee?.employee_id ?? '',
    first_name: props.employee?.first_name ?? '',
    last_name: props.employee?.last_name ?? '',
    email: props.employee?.email ?? '',
    phone: props.employee?.phone ?? '',
    department_id: props.employee?.department_id ?? null,
    hired_at: props.employee?.hired_at ?? '',
    status: props.employee?.status ?? 'active',
    _method: props.employee?.id ? 'put' : 'post',
});

const submit = () => {
    const routeName = props.employee?.id ? route('employee.update', props.employee.id) : route('employee.store');

    form.post(routeName, {
        onSuccess: () => {
            router.visit(route('employee.index'));
        }
    });
};
</script>

<template>
    <BackendLayout>
        <div class="w-full p-4 bg-white rounded-md">
            <h1 class="text-xl font-bold">{{ $page.props.pageTitle ?? 'Employee' }}</h1>

            <form @submit.prevent="submit" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <InputLabel for="employee_id" value="Employee ID" />
                    <input v-model="form.employee_id" id="employee_id" type="text" class="w-full p-2 border rounded" />
                    <InputError :message="form.errors.employee_id" />
                </div>

                <div>
                    <InputLabel for="first_name" value="First Name" />
                    <input v-model="form.first_name" id="first_name" type="text" class="w-full p-2 border rounded" />
                    <InputError :message="form.errors.first_name" />
                </div>

                <div>
                    <InputLabel for="last_name" value="Last Name" />
                    <input v-model="form.last_name" id="last_name" type="text" class="w-full p-2 border rounded" />
                    <InputError :message="form.errors.last_name" />
                </div>

                <div>
                    <InputLabel for="email" value="Email" />
                    <input v-model="form.email" id="email" type="email" class="w-full p-2 border rounded" />
                    <InputError :message="form.errors.email" />
                </div>

                <div>
                    <InputLabel for="phone" value="Phone" />
                    <input v-model="form.phone" id="phone" type="text" class="w-full p-2 border rounded" />
                    <InputError :message="form.errors.phone" />
                </div>

                <div>
                    <InputLabel for="hired_at" value="Hired At" />
                    <input v-model="form.hired_at" id="hired_at" type="date" class="w-full p-2 border rounded" />
                    <InputError :message="form.errors.hired_at" />
                </div>

                <div>
                    <InputLabel for="status" value="Status" />
                    <select v-model="form.status" id="status" class="w-full p-2 border rounded">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <InputError :message="form.errors.status" />
                </div>

                <div class="col-span-1 sm:col-span-2 flex justify-end mt-4">
                    <PrimaryButton type="submit">{{ props.employee?.id ? 'Update' : 'Create' }}</PrimaryButton>
                </div>
            </form>
        </div>
    </BackendLayout>
</template>
