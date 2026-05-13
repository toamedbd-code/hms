<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import axios from 'axios';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { showToastIfNoFlash } from '@/responseMessage.js';

const props = defineProps({
    isOpen: Boolean,
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

// Local copies so we can push newly created items without relying on prop mutation
const localDesignations = ref(props.designations ? [...props.designations] : []);
const localDepartments = ref(props.departments ? [...props.departments] : []);
const localSpecialists = ref(props.specialists ? [...props.specialists] : []);

watch(() => props.designations, (v) => (localDesignations.value = v ? [...v] : []));
watch(() => props.departments, (v) => (localDepartments.value = v ? [...v] : []));
watch(() => props.specialists, (v) => (localSpecialists.value = v ? [...v] : []));

const showDesignationModal = ref(false);
const showDepartmentModal = ref(false);
const showSpecialistModal = ref(false);

const newDesignation = useForm({ name: '' });
const newDepartment = useForm({ name: '' });
const newSpecialist = useForm({ name: '' });

const openDesignationCreate = () => {
    showDesignationModal.value = true;
};

const openDepartmentCreate = () => {
    showDepartmentModal.value = true;
};

const openSpecialistCreate = () => {
    showSpecialistModal.value = true;
};

const createDesignation = async () => {
    newDesignation.processing = true;
    try {
        const res = await axios.post(route('backend.designation.store'), { name: newDesignation.name }, { headers: { Accept: 'application/json' } });
        const created = res.data?.designation ?? res.data?.data ?? res.data;
        if (created) {
            localDesignations.value.push(created);
            form.designation_id = created.id;
        }
        showDesignationModal.value = false;
        newDesignation.reset();
    } catch (error) {
        if (error.response?.status === 422) {
            newDesignation.errors = error.response.data.errors || {};
        } else {
            showToastIfNoFlash({ props: { flash: { errorMessage: error.response?.data?.errorMessage ?? 'Server error' } } });
        }
    } finally {
        newDesignation.processing = false;
    }
};

const createDepartment = async () => {
    newDepartment.processing = true;
    try {
        const res = await axios.post(route('backend.department.store'), { name: newDepartment.name }, { headers: { Accept: 'application/json' } });
        const created = res.data?.department ?? res.data?.data ?? res.data;
        if (created) {
            localDepartments.value.push(created);
            form.department_id = created.id;
        }
        showDepartmentModal.value = false;
        newDepartment.reset();
    } catch (error) {
        if (error.response?.status === 422) {
            newDepartment.errors = error.response.data.errors || {};
        } else {
            showToastIfNoFlash({ props: { flash: { errorMessage: error.response?.data?.errorMessage ?? 'Server error' } } });
        }
    } finally {
        newDepartment.processing = false;
    }
};

const createSpecialist = async () => {
    newSpecialist.processing = true;
    try {
        const res = await axios.post(route('backend.specialist.store'), { name: newSpecialist.name }, { headers: { Accept: 'application/json' } });
        const created = res.data?.specialist ?? res.data?.data ?? res.data;
        if (created) {
            localSpecialists.value.push(created);
            form.specialist_id = created.id;
        }
        showSpecialistModal.value = false;
        newSpecialist.reset();
    } catch (error) {
        if (error.response?.status === 422) {
            newSpecialist.errors = error.response.data.errors || {};
        } else {
            showToastIfNoFlash({ props: { flash: { errorMessage: error.response?.data?.errorMessage ?? 'Server error' } } });
        }
    } finally {
        newSpecialist.processing = false;
    }
};

const submitForm = async () => {
    form.processing = true;
    form.clearErrors && form.clearErrors();

    try {
        const payload = {
            name: form.name,
            email: form.email,
            phone: form.phone,
            gender: form.gender,
            doctor_charge: form.doctor_charge,
            designation_id: form.designation_id,
            department_id: form.department_id,
            specialist_id: form.specialist_id,
        };

        const response = await axios.post(route('backend.doctors.store'), payload, {
            headers: { Accept: 'application/json' },
        });

        const newDoctor = response.data?.doctor ?? null;
        const successMessage = response.data?.successMessage ?? null;

        if (successMessage) {
            showToastIfNoFlash({ props: { flash: { successMessage } } });
        }

        form.reset();
        emit('doctorCreated', newDoctor);
        close();
    } catch (error) {
        if (error.response?.status === 422) {
            form.errors = error.response.data.errors || {};
        } else {
            showToastIfNoFlash({ props: { flash: { errorMessage: error.response?.data?.errorMessage ?? 'Server error' } } });
        }
    } finally {
        form.processing = false;
    }
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
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
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
                                            <div class="mt-1 flex">
                                                <select id="designation" v-model="form.designation_id"
                                                    class="flex-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                                    <option :value="null" disabled>Select a Designation</option>
                                                    <option v-for="designation in localDesignations" :key="designation.id"
                                                        :value="designation.id">{{ designation.name }}</option>
                                                </select>
                                                <button type="button" @click="openDesignationCreate"
                                                    class="ml-2 inline-flex items-center justify-center w-9 h-9 bg-green-500 hover:bg-green-600 text-white rounded-md text-sm"
                                                    aria-label="Add designation">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                                    </svg>
                                                </button>
                                            </div>
                                            <InputError class="mt-2" :message="form.errors.designation_id" />
                                        </div>
                                    </div>

                                    <!-- Third row with 2 fields (or adjust as needed) -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                        <div>
                                            <InputLabel for="department" value="Department" />
                                            <div class="mt-1 flex">
                                                <select id="department" v-model="form.department_id"
                                                    class="flex-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                                    <option :value="null" disabled>Select a Department</option>
                                                    <option v-for="department in localDepartments" :key="department.id"
                                                        :value="department.id">{{ department.name }}</option>
                                                </select>
                                                <button type="button" @click="openDepartmentCreate"
                                                    class="ml-2 inline-flex items-center justify-center w-9 h-9 bg-green-500 hover:bg-green-600 text-white rounded-md text-sm"
                                                    aria-label="Add department">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                                    </svg>
                                                </button>
                                            </div>
                                            <InputError class="mt-2" :message="form.errors.department_id" />
                                        </div>

                                        <div>
                                            <InputLabel for="specialist" value="Specialist" />
                                            <div class="mt-1 flex">
                                                <select id="specialist" v-model="form.specialist_id"
                                                    class="flex-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                                    <option :value="null" disabled>Select a Specialist</option>
                                                    <option v-for="specialist in localSpecialists" :key="specialist.id"
                                                        :value="specialist.id">{{ specialist.name }}</option>
                                                </select>
                                                <button type="button" @click="openSpecialistCreate"
                                                    class="ml-2 inline-flex items-center justify-center w-9 h-9 bg-green-500 hover:bg-green-600 text-white rounded-md text-sm"
                                                    aria-label="Add specialist">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                                    </svg>
                                                </button>
                                            </div>
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

                    <!-- Inline create modals appended to body so they appear above parent modal -->
                    <teleport to="body">
                        <div v-if="showDesignationModal" class="fixed inset-0 z-50 overflow-y-auto">
                            <div class="flex items-center justify-center min-h-screen px-4">
                                <div class="fixed inset-0 bg-black opacity-50 z-40" @click="showDesignationModal = false"></div>
                                <div class="relative bg-white rounded-lg shadow-lg max-w-md w-full z-50 p-6" role="dialog" aria-modal="true">
                                    <h3 class="text-lg font-medium mb-3">Add Designation</h3>
                                    <div>
                                        <InputLabel for="new_designation_name" value="Name" />
                                        <input id="new_designation_name" v-model="newDesignation.name" type="text"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <InputError class="mt-2" :message="newDesignation.errors.name" />
                                    </div>
                                    <div class="mt-4 flex justify-end space-x-2">
                                        <button type="button" @click="(showDesignationModal = false, newDesignation.reset())"
                                            class="px-4 py-2 rounded border bg-white">Cancel</button>
                                        <button type="button" :disabled="newDesignation.processing" @click="createDesignation"
                                            class="px-4 py-2 rounded bg-green-500 text-white hover:bg-green-600">Save</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="showDepartmentModal" class="fixed inset-0 z-50 overflow-y-auto">
                            <div class="flex items-center justify-center min-h-screen px-4">
                                <div class="fixed inset-0 bg-black opacity-50 z-40" @click="showDepartmentModal = false"></div>
                                <div class="relative bg-white rounded-lg shadow-lg max-w-md w-full z-50 p-6" role="dialog" aria-modal="true">
                                    <h3 class="text-lg font-medium mb-3">Add Department</h3>
                                    <div>
                                        <InputLabel for="new_department_name" value="Name" />
                                        <input id="new_department_name" v-model="newDepartment.name" type="text"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <InputError class="mt-2" :message="newDepartment.errors.name" />
                                    </div>
                                    <div class="mt-4 flex justify-end space-x-2">
                                        <button type="button" @click="(showDepartmentModal = false, newDepartment.reset())"
                                            class="px-4 py-2 rounded border bg-white">Cancel</button>
                                        <button type="button" :disabled="newDepartment.processing" @click="createDepartment"
                                            class="px-4 py-2 rounded bg-green-500 text-white hover:bg-green-600">Save</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="showSpecialistModal" class="fixed inset-0 z-50 overflow-y-auto">
                            <div class="flex items-center justify-center min-h-screen px-4">
                                <div class="fixed inset-0 bg-black opacity-50 z-40" @click="showSpecialistModal = false"></div>
                                <div class="relative bg-white rounded-lg shadow-lg max-w-md w-full z-50 p-6" role="dialog" aria-modal="true">
                                    <h3 class="text-lg font-medium mb-3">Add Specialist</h3>
                                    <div>
                                        <InputLabel for="new_specialist_name" value="Name" />
                                        <input id="new_specialist_name" v-model="newSpecialist.name" type="text"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <InputError class="mt-2" :message="newSpecialist.errors.name" />
                                    </div>
                                    <div class="mt-4 flex justify-end space-x-2">
                                        <button type="button" @click="(showSpecialistModal = false, newSpecialist.reset())"
                                            class="px-4 py-2 rounded border bg-white">Cancel</button>
                                        <button type="button" :disabled="newSpecialist.processing" @click="createSpecialist"
                                            class="px-4 py-2 rounded bg-green-500 text-white hover:bg-green-600">Save</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </teleport>
                </template>