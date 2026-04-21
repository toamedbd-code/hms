<script setup>
import { ref } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps(['employees', 'filters', 'pageTitle']);

const goToCreate = () => {
    router.visit(route('employee.create'));
};

const goToEdit = (id) => {
    router.visit(route('employee.edit', id));
};

const page = usePage();
</script>

<template>
    <BackendLayout>
        <div class="w-full p-2 bg-white rounded-md">
            <div class="flex items-center justify-between mb-3">
                <h1 class="text-xl font-bold">{{ $page.props.pageTitle ?? 'Employees' }}</h1>
                <div>
                    <button @click="goToCreate" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded">Create</button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full table-auto border-collapse">
                    <thead>
                        <tr class="text-left">
                            <th class="p-2">ID</th>
                            <th class="p-2">Employee ID</th>
                            <th class="p-2">Name</th>
                            <th class="p-2">Email</th>
                            <th class="p-2">Phone</th>
                            <th class="p-2">Status</th>
                            <th class="p-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="emp in employees.data" :key="emp.id" class="border-t">
                            <td class="p-2">{{ emp.id }}</td>
                            <td class="p-2">{{ emp.employee_id }}</td>
                            <td class="p-2">{{ emp.first_name }} {{ emp.last_name }}</td>
                            <td class="p-2">{{ emp.email }}</td>
                            <td class="p-2">{{ emp.phone }}</td>
                            <td class="p-2">{{ emp.status }}</td>
                            <td class="p-2">
                                <button @click="goToEdit(emp.id)" class="px-2 py-1 mr-2 text-sm text-white bg-green-600 rounded">Edit</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <div v-if="employees.links" class="flex items-center space-x-2">
                    <a v-for="link in employees.links" :key="link.label" v-html="link.label" @click.prevent="link.url && router.visit(link.url)" class="px-2 py-1 text-sm border rounded" :class="{'opacity-50': !link.url}"></a>
                </div>
            </div>
        </div>
    </BackendLayout>
</template>
