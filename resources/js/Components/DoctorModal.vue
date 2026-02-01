<script setup>
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { displayResponse } from '@/responseMessage.js';

const props = defineProps({
    isOpen: Boolean,
    // Assuming you'll pass these options from the backend
    designations: Array,
    departments: Array,
    specialists: Array,
});

const emit = defineEmits(['close', 'doctorCreated']);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    gender: 'Male',
    doctor_charge: '',
    designation_id: null,
    department_id: null,
    specialist_id: null,
});

const close = () => {
    emit('close');
};

const submitForm = () => {
    form.post(route('backend.doctors.store'), {
        preserveScroll: true,
        onSuccess: (response) => {
            displayResponse(response);
            form.reset();
            emit('doctorCreated', response.props.doctor);
            close();
        }
    });
};
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75" @click="close"></div>
            </div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div
                class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
                <!-- Increased max-w-lg to max-w-3xl to accommodate wider content -->
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Add New Doctor
                            </h3>
                            <div class="mt-2">
                                <form @submit.prevent="submitForm">
                                    <!-- First row with 3 fields -->
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                        <div>
                                            <InputLabel for="name" value="Name" />
                                            <input id="name" v-model="form.name" type="text"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                required>
                                            <InputError class="mt-2" :message="form.errors.name" />
                                        </div>

                                        <div>
                                            <InputLabel for="email" value="Email" />
                                            <input id="email" v-model="form.email" type="email"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                required>
                                            <InputError class="mt-2" :message="form.errors.email" />
                                        </div>

                                        <div>
                                            <InputLabel for="phone" value="Phone" />
                                            <input id="phone" v-model="form.phone" type="text"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                            <InputError class="mt-2" :message="form.errors.phone" />
                                        </div>
                                    </div>

                                    <!-- Second row with 3 fields -->
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                        <div>
                                            <InputLabel for="gender" value="Gender" />
                                            <select id="gender" v-model="form.gender"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                                <option value="Other">Other</option>
                                            </select>
                                            <InputError class="mt-2" :message="form.errors.gender" />
                                        </div>

                                        <div>
                                            <InputLabel for="doctor_charge" value="Doctor Charge" />
                                            <input id="doctor_charge" v-model="form.doctor_charge" type="number"
                                                step="0.01"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                            <InputError class="mt-2" :message="form.errors.doctor_charge" />
                                        </div>

                                        <div>
                                            <InputLabel for="designation" value="Designation" />
                                            <select id="designation" v-model="form.designation_id"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                                <option :value="null" disabled>Select a Designation</option>
                                                <option v-for="designation in designations" :key="designation.id"
                                                    :value="designation.id">{{ designation.name }}</option>
                                            </select>
                                            <InputError class="mt-2" :message="form.errors.designation_id" />
                                        </div>
                                    </div>

                                    <!-- Third row with 2 fields (or adjust as needed) -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                        <div>
                                            <InputLabel for="department" value="Department" />
                                            <select id="department" v-model="form.department_id"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                                <option :value="null" disabled>Select a Department</option>
                                                <option v-for="department in departments" :key="department.id"
                                                    :value="department.id">{{ department.name }}</option>
                                            </select>
                                            <InputError class="mt-2" :message="form.errors.department_id" />
                                        </div>

                                        <div>
                                            <InputLabel for="specialist" value="Specialist" />
                                            <select id="specialist" v-model="form.specialist_id"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                                <option :value="null" disabled>Select a Specialist</option>
                                                <option v-for="specialist in specialists" :key="specialist.id"
                                                    :value="specialist.id">{{ specialist.name }}</option>
                                            </select>
                                            <InputError class="mt-2" :message="form.errors.specialist_id" />
                                        </div>
                                    </div>

                                    <!-- Buttons (keep this part unchanged) -->
                                    <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                                        <button type="submit" :disabled="form.processing"
                                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:col-start-2 sm:text-sm">
                                            Save
                                        </button>
                                        <button type="button" @click="close"
                                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:col-start-1 sm:text-sm">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>