<script setup>
import { ref, onMounted, watch} from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AlertMessage from '@/Components/AlertMessage.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';
import AddItemModal from '@/Components/AddItemModal.vue';

const props = defineProps(['user', 'id', 'roles', 'designations', 'departments', 'specialists', 'adminDetails']);
const showPassword = ref(false);

const showDesignationModal = ref(false);
const showDepartmentModal = ref(false);
const showSpecialistModal = ref(false);

// Forms for modals
const designationForm = useForm({
    name: '',
});

const departmentForm = useForm({
    name: '',
});

const specialistForm = useForm({
    name: '',
});

const form = useForm({
    // Basic Information
    staff_id: props.adminDetails?.staff_id ?? '',
    first_name: props.user?.first_name ?? '',
    last_name: props.user?.last_name ?? '',
    father_name: props.adminDetails?.father_name ?? '',
    mother_name: props.adminDetails?.mother_name ?? '',
    gender: props.adminDetails?.gender ?? '',
    marital_status: props.adminDetails?.marital_status ?? '',
    blood_group: props.adminDetails?.blood_group ?? '',
    date_of_birth: props.adminDetails?.date_of_birth ?? '',
    date_of_joining: props.adminDetails?.date_of_joining ?? '',
    phone: props.user?.phone ?? '',
    emergency_contact: props.adminDetails?.emergency_contact ?? '',
    email: props.user?.email ?? '',
    password: props.user?.password ?? '',
    photo: '',
    photoPreview: props.user?.photo ?? '',
    role_id: props.user?.role_id ?? '',
    doctor_charge: props.user?.doctor_charge ?? '',
    designation_id: props.adminDetails?.designation_id ?? '',
    department_id: props.adminDetails?.department_id ?? '',
    specialist_id: props.adminDetails?.specialist_id ?? '',
    password: '',
    current_address: props.adminDetails?.current_address ?? '',
    permanent_address: props.adminDetails?.permanent_address ?? '',
    pan_number: props.adminDetails?.pan_number ?? '',
    national_id_number: props.adminDetails?.national_id_number ?? '',
    local_id_number: props.adminDetails?.local_id_number ?? '',
    qualification: props.adminDetails?.qualification ?? '',
    work_experience: props.adminDetails?.work_experience ?? '',
    specialization: props.adminDetails?.specialization ?? '',
    note: props.adminDetails?.note ?? '',

    // Payroll
    epf_no: props.adminDetails?.epf_no ?? '',
    basic_salary: props.adminDetails?.basic_salary ?? '',
    contract_type: props.adminDetails?.contract_type ?? '',
    work_shift: props.adminDetails?.work_shift ?? '',
    work_location: props.adminDetails?.work_location ?? '',

    // Leave
    number_of_leaves: props.adminDetails?.number_of_leaves ?? '',

    // Bank Details
    bank_account_title: props.adminDetails?.bank_account_title ?? '',
    bank_account_no: props.adminDetails?.bank_account_no ?? '',
    bank_name: props.adminDetails?.bank_name ?? '',
    bank_branch_name: props.adminDetails?.bank_branch_name ?? '',
    ifsc_code: props.adminDetails?.ifsc_code ?? '',

    // Social Media
    facebook_url: props.adminDetails?.facebook_url ?? '',
    linkedin_url: props.adminDetails?.linkedin_url ?? '',
    twitter_url: props.adminDetails?.twitter_url ?? '',
    instagram_url: props.adminDetails?.instagram_url ?? '',

    // Documents
    resume: null,
    resumePreview: props.adminDetails?.resume_path ?? '',
    resignation_letter: null,
    resignation_letterPreview: props.adminDetails?.resignation_letter_path ?? '',
    joining_letter: null,
    joining_letterPreview: props.adminDetails?.joining_letter_path ?? '',
    other_documents: null,
    other_documentsPreview: props.adminDetails?.other_documents_path ?? '',

    _method: props.user?.id ? 'put' : 'post',
});

watch(() => form.role_id, (newRoleId) => {
    if (newRoleId != '2') {
        form.doctor_charge = '';
        form.specialist_id = '';
    }
});

const handleFileChange = (event, field) => {
    const file = event.target.files[0];

    if (file) {
        form[field] = file;

        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                form[`${field}Preview`] = e.target.result;
            };
            reader.readAsDataURL(file);
        } else {
            form[`${field}Preview`] = file.name;
        }
    }
};

const handlePhotoChange = (event) => {
    handleFileChange(event, 'photo');
};

const handleResumeChange = (event) => {
    handleFileChange(event, 'resume');
};

const handleResignationLetterChange = (event) => {
    handleFileChange(event, 'resignation_letter');
};

const handleJoiningLetterChange = (event) => {
    handleFileChange(event, 'joining_letter');
};

const handleOtherDocumentsChange = (event) => {
    handleFileChange(event, 'other_documents');
};

const submit = () => {
    const routeName = props.id ? route('backend.admin.update', props.id) : route('backend.admin.store');

    form.transform(data => {
        const transformedData = { ...data };

        delete transformedData.photoPreview;
        delete transformedData.resumePreview;
        delete transformedData.restoration_letterPreview;
        delete transformedData.joining_letterPreview;
        delete transformedData.other_documentsPreview;

        const fileFields = ['photo', 'resume', 'resignation_letter', 'joining_letter', 'other_documents'];

        fileFields.forEach(field => {
            if (!(transformedData[field] instanceof File)) {
                delete transformedData[field];
            }
        });

        return {
            ...transformedData,
            remember: '',
            isDirty: false,
        };
    }).post(routeName, {
        onSuccess: (response) => {
            if (!props.id)
                form.reset();
            displayResponse(response);
        },
        onError: (errorObject) => {
            displayWarning(errorObject);
        },
    });
};

const goToAdminList = () => {
    router.visit(route('backend.admin.index'));
};
const goToRoleList = () => {
    router.visit(route('backend.role.index'));
};

</script>

<template>
    <BackendLayout>
        <div class="w-full transition duration-1000 ease-in-out transform bg-white border rounded-md dark:bg-slate-500">
            <div
                class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md shadow-md dark:bg-gray-800 dark:text-gray-200">
                <div>
                    <h1 class="p-4 text-xl font-bold dark:text-white">{{ $page.props.pageTitle }}</h1>
                </div>

                <div class="p-2 py-2 flex items-center space-x-2">
                    <div class="flex items-center space-x-3">
                        <button @click="goToAdminList"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z">
                                </path>
                            </svg>
                            Staff List
                        </button>

                        <button @click="goToRoleList"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z">
                                </path>
                            </svg>
                            Role List
                        </button>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="p-1">
                <!-- <AlertMessage /> -->

                <!-- Basic Information Section -->
                <div class="p-2 mb-6 border border-gray-200 rounded-md dark:border-gray-700">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4">
                        <div class="col-span-1">
                            <InputLabel for="staff_id" value="Staff ID"><span class="text-red-500">*</span>
                            </InputLabel>
                            <input id="staff_id" v-model="form.staff_id" type="text" placeholder="Staff ID"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.staff_id" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="role_id" value="Role"><span class="text-red-500">*</span></InputLabel>
                            <select id="role_id" v-model="form.role_id"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                                <option value="">Select Role</option>
                                <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.role_id" />
                        </div>

                        <div class="col-span-1" v-if="form.role_id == '2'">
                            <InputLabel for="doctor_charge" value="Doctor Charge "><span class="text-red-500">*</span></InputLabel>
                            <input id="doctor_charge" v-model="form.doctor_charge" type="number" placeholder="Doctor Charge"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.doctor_charge" />
                        </div>

                        <!-- Designation Field -->
                        <div class="col-span-1">
                            <InputLabel for="designation_id" value="Designation" ><span class="text-red-500">*</span></InputLabel>
                            <div class="flex items-center justify-between">
                                <select id="designation_id" v-model="form.designation_id"
                                    class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                                    <option value="">Select Designation</option>
                                    <option v-for="designation in designations" :key="designation.id"
                                        :value="designation.id">{{ designation.name }}</option>
                                </select>
                                <button type="button" @click="showDesignationModal = true"
                                    class="ml-1 inline-flex items-center px-1 py-2.5 bg-blue-500 hover:bg-blue-600 text-white rounded-md shadow-sm transition-colors duration-200 dark:bg-blue-600 dark:hover:bg-blue-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                            <InputError class="mt-2" :message="form.errors.designation_id" />
                        </div>

                        <!-- Department Field -->
                        <div class="col-span-1">
                            <InputLabel for="department_id" value="Department"><span class="text-red-500">*</span></InputLabel>
                            <div class="flex items-center justify-between">
                                <select id="department_id" v-model="form.department_id"
                                    class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                                    <option value="">Select Department</option>
                                    <option v-for="department in departments" :key="department.id"
                                        :value="department.id">{{
                                            department.name }}</option>
                                </select>
                                <button type="button" @click="showDepartmentModal = true"
                                    class="ml-1 inline-flex items-center px-1 py-2.5 bg-blue-500 hover:bg-blue-600 text-white rounded-md shadow-sm transition-colors duration-200 dark:bg-blue-600 dark:hover:bg-blue-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                            <InputError class="mt-2" :message="form.errors.department_id" />
                        </div>

                        <!-- Specialist Field -->
                        <div class="col-span-1" v-if="form.role_id == '2'">
                            <InputLabel for="specialist_id" value="Specialist"><span class="text-red-500">*</span></InputLabel>
                            <div class="flex items-center justify-between">
                                <select id="specialist_id" v-model="form.specialist_id"
                                    class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                                    <option value="">Select Specialist</option>
                                    <option v-for="specialist in specialists" :key="specialist.id"
                                        :value="specialist.id">{{
                                            specialist.name }}</option>
                                </select>
                                <button type="button" @click="showSpecialistModal = true"
                                    class="ml-1 inline-flex items-center px-1 py-2.5 bg-blue-500 hover:bg-blue-600 text-white rounded-md shadow-sm transition-colors duration-200 dark:bg-blue-600 dark:hover:bg-blue-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                            <InputError class="mt-2" :message="form.errors.specialist_id" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="first_name" value="First Name "><span class="text-red-500">*</span>
                            </InputLabel>
                            <input id="first_name" v-model="form.first_name" type="text" placeholder="First Name"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.first_name" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="last_name" value="Last Name" />
                            <input id="last_name" v-model="form.last_name" type="text" placeholder="Last Name"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.last_name" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="father_name" value="Father Name" />
                            <input id="father_name" v-model="form.father_name" type="text" placeholder="Father Name"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.father_name" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="mother_name" value="Mother Name" />
                            <input id="mother_name" v-model="form.mother_name" type="text" placeholder="Mother Name"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.mother_name" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="gender" value="Gender"> <span class="text-red-500">*</span></InputLabel>
                            <select id="gender" v-model="form.gender"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.gender" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="marital_status" value="Marital Status"><span class="text-red-500">*</span></InputLabel>
                            <select id="marital_status" v-model="form.marital_status"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                                <option value="">Select Status</option>
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Divorced">Divorced</option>
                                <option value="Widowed">Widowed</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.marital_status" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="blood_group" value="Blood Group"><span class="text-red-500">*</span></InputLabel>
                            <select id="blood_group" v-model="form.blood_group"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                                <option value="">Select Blood Group</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.blood_group" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="date_of_birth" value="Date Of Birth "><span class="text-red-500">*</span>
                            </InputLabel>
                            <input id="date_of_birth" v-model="form.date_of_birth" type="date"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.date_of_birth" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="date_of_joining" value="Date Of Joining"><span class="text-red-500">*</span></InputLabel>
                            <input id="date_of_joining" v-model="form.date_of_joining" type="date"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.date_of_joining" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="phone" value="Phone"><span class="text-red-500">*</span></InputLabel>
                            <input id="phone" v-model="form.phone" type="text" placeholder="Phone"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.phone" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="emergency_contact" value="Emergency Contact" />
                            <input id="emergency_contact" v-model="form.emergency_contact" type="text"
                                placeholder="Emergency Contact"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.emergency_contact" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="email" value="Email"><span class="text-red-500">*</span></InputLabel>
                            <input id="email" v-model="form.email" type="email" placeholder="Email"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div class="col-span-1 relative">
                            <InputLabel for="password" value="Password"><span v-if="!props.id" class="text-red-500">*</span></InputLabel>
                            <div class="relative">
                                <input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'"
                                    placeholder="Password"
                                    class="block w-full p-2 pr-10 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                                <button type="button" @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
                                        viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                        <path fill-rule="evenodd"
                                            d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z"
                                            clip-rule="evenodd" />
                                        <path
                                            d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" />
                                    </svg>
                                </button>
                            </div>
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <div class="col-span-1 md:col-span-2">
                            <InputLabel for="photo" value="Photo" />
                            <div v-if="form.photoPreview">
                                <img :src="form.photoPreview" alt="Photo Preview" class="max-w-xs mt-2" height="60"
                                    width="60" />
                            </div>
                            <input id="photo" type="file" accept="image/*"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                @change="handlePhotoChange" />
                            <InputError class="mt-2" :message="form.errors.photo" />
                        </div>

                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4">
                        <div class="col-span-1 sm:col-span-2">
                            <InputLabel for="current_address" value="Current Address" />
                            <textarea id="current_address" v-model="form.current_address" rows="2"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"></textarea>
                            <InputError class="mt-2" :message="form.errors.current_address" />
                        </div>

                        <div class="col-span-1 sm:col-span-2">
                            <InputLabel for="permanent_address" value="Permanent Address" />
                            <textarea id="permanent_address" v-model="form.permanent_address" rows="2"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"></textarea>
                            <InputError class="mt-2" :message="form.errors.permanent_address" />
                        </div>
                        <div class="col-span-1">
                            <InputLabel for="qualification" value="Qualification" />
                            <textarea id="qualification" v-model="form.qualification" type="text"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.qualification" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="work_experience" value="Work Experience" />
                            <textarea id="work_experience" v-model="form.work_experience" type="text"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.work_experience" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="specialization" value="Specialization" />
                            <textarea id="specialization" v-model="form.specialization" type="text"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.specialization" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="note" value="Note" />
                            <textarea id="note" v-model="form.note" type="text"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.note" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="pan_number" value="Pan Number" />
                            <input id="pan_number" v-model="form.pan_number" type="text" placeholder="Pan Number"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.pan_number" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="national_id_number" value="National Identification Number" />
                            <input id="national_id_number" v-model="form.national_id_number" type="text"
                                placeholder="National ID"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.national_id_number" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="local_id_number" value="Local Identification Number" />
                            <input id="local_id_number" v-model="form.local_id_number" type="text"
                                placeholder="Local ID"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.local_id_number" />
                        </div>


                    </div>
                </div>

                <!-- Payroll Section -->
                <div class="p-4 mb-6 border border-gray-200 rounded-md dark:border-gray-700">
                    <h2 class="mb-4 text-lg font-semibold">Payroll</h2>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4">
                        <div class="col-span-1">
                            <InputLabel for="epf_no" value="EPF No" />
                            <input id="epf_no" v-model="form.epf_no" type="text" placeholder="EPF Number"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.epf_no" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="basic_salary" value="Basic Salary" />
                            <input id="basic_salary" v-model="form.basic_salary" type="number"
                                placeholder="Basic Salary"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.basic_salary" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="contract_type" value="Contract Type" />
                            <select id="contract_type" v-model="form.contract_type"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                                <option value="">Select Contract Type</option>
                                <option value="Permanent">Permanent</option>
                                <option value="Probation">Probation</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.contract_type" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="work_shift" value="Work Shift" />
                            <input id="work_shift" v-model="form.leave_level" type="text" placeholder="Work Shift"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.work_shift" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="work_location" value="Work Location" />
                            <input id="work_location" v-model="form.leave_level" type="text" placeholder="Work Location"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.work_location" />
                        </div>
                    </div>
                </div>

                <!-- Leave Section -->
                <div class="p-4 mb-6 border border-gray-200 rounded-md dark:border-gray-700">
                    <h2 class="mb-4 text-lg font-semibold">Leave</h2>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4">
                        <div class="col-span-1">
                            <InputLabel for="number_of_leaves" value="Number of Leaves" />
                            <input id="number_of_leaves" v-model="form.number_of_leaves" type="number"
                                placeholder="Number of Leaves"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.number_of_leaves" />
                        </div>
                    </div>
                </div>

                <!-- Bank Details Section -->
                <div class="p-4 mb-6 border border-gray-200 rounded-md dark:border-gray-700">
                    <h2 class="mb-4 text-lg font-semibold">Bank Account Details</h2>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4">
                        <div class="col-span-1">
                            <InputLabel for="bank_account_title" value="Account Title" />
                            <input id="bank_account_title" v-model="form.bank_account_title" type="text"
                                placeholder="Account Title"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.bank_account_title" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="bank_account_no" value="Bank Account No." />
                            <input id="bank_account_no" v-model="form.bank_account_no" type="text"
                                placeholder="Account Number"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.bank_account_no" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="bank_name" value="Bank Name" />
                            <input id="bank_name" v-model="form.bank_name" type="text" placeholder="Bank Name"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.bank_name" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="ifsc_code" value="IFSC Code" />
                            <input id="ifsc_code" v-model="form.ifsc_code" type="text" placeholder="IFSC Code"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.ifsc_code" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="bank_branch_name" value="Bank Branch Name" />
                            <input id="bank_branch_name" v-model="form.bank_branch_name" type="text"
                                placeholder="Branch Name"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.bank_branch_name" />
                        </div>
                    </div>
                </div>

                <!-- Social Media Section -->
                <div class="p-4 mb-6 border border-gray-200 rounded-md dark:border-gray-700">
                    <h2 class="mb-4 text-lg font-semibold">Social Media Links</h2>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4">
                        <div class="col-span-1">
                            <InputLabel for="facebook_url" value="Facebook URL" />
                            <input id="facebook_url" v-model="form.facebook_url" type="url" placeholder="Facebook URL"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.facebook_url" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="linkedin_url" value="LinkedIn URL" />
                            <input id="linkedin_url" v-model="form.linkedin_url" type="url" placeholder="LinkedIn URL"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.linkedin_url" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="twitter_url" value="Twitter URL" />
                            <input id="twitter_url" v-model="form.twitter_url" type="url" placeholder="Twitter URL"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.twitter_url" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="instagram_url" value="Instagram URL" />
                            <input id="instagram_url" v-model="form.instagram_url" type="url"
                                placeholder="Instagram URL"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                            <InputError class="mt-2" :message="form.errors.instagram_url" />
                        </div>
                    </div>
                </div>

                <!-- Documents Section -->
                <div class="p-4 mb-6 border border-gray-200 rounded-md dark:border-gray-700">
                    <h2 class="mb-4 text-lg font-semibold">Upload Documents</h2>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4">
                        <div class="col-span-1">
                            <InputLabel for="resume" value="Resume" />
                            <input id="resume" type="file" accept=".pdf,.doc,.docx"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                @change="handleResumeChange" />
                            <InputError class="mt-2" :message="form.errors.resume" />
                            <div v-if="form.resumePreview" class="mt-2 text-sm text-blue-500">
                                <a :href="form.resumePreview" target="_blank">View Current Resume</a>
                            </div>
                        </div>
                        <div class="col-span-1">
                            <InputLabel for="joining_letter" value="Joining Letter" />
                            <input id="joining_letter" type="file" accept=".pdf,.doc,.docx"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                @change="handleJoiningLetterChange" />
                            <InputError class="mt-2" :message="form.errors.joining_letter" />
                            <div v-if="form.joining_letterPreview" class="mt-2 text-sm text-blue-500">
                                <a :href="form.joining_letterPreview" target="_blank">View Joining Letter</a>
                            </div>
                        </div>
                        <div class="col-span-1">
                            <InputLabel for="resignation_letter" value="Resignation Letter" />
                            <input id="resignation_letter" type="file" accept=".pdf,.doc,.docx"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                @change="handleResignationLetterChange" />
                            <InputError class="mt-2" :message="form.errors.resignation_letter" />
                            <div v-if="form.resignation_letterPreview" class="mt-2 text-sm text-blue-500">
                                <a :href="form.resignation_letterPreview" target="_blank">View Resignation Letter</a>
                            </div>
                        </div>
                        <div class="col-span-1">
                            <InputLabel for="other_documents" value="Other Letter" />
                            <input id="other_documents" type="file" accept=".pdf,.doc,.docx"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                @change="handleOtherDocumentsChange" />
                            <InputError class="mt-2" :message="form.errors.other_documents" />
                            <div v-if="form.other_documentsPreview" class="mt-2 text-sm text-blue-500">
                                <a :href="form.other_documentsPreview" target="_blank">View Other Documents</a>
                            </div>
                        </div>
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
        <AddItemModal :show="showDesignationModal" @close="showDesignationModal = false" title="Designation"
            inputLabel="Designation Name" inputId="designation_name" :form="designationForm"
            routeName="backend.designation.store" :reloadOnly="['designations']" />

        <AddItemModal :show="showDepartmentModal" @close="showDepartmentModal = false" title="Department"
            inputLabel="Department Name" inputId="department_name" :form="departmentForm"
            routeName="backend.department.store" :reloadOnly="['departments']" />

        <AddItemModal :show="showSpecialistModal" @close="showSpecialistModal = false" title="Specialist"
            inputLabel="Specialist Name" inputId="specialist_name" :form="specialistForm"
            routeName="backend.specialist.store" :reloadOnly="['specialists']" />
    </BackendLayout>
</template>
