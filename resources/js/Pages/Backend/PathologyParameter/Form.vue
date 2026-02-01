<script setup>
import { ref, onMounted } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AlertMessage from '@/Components/AlertMessage.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';
import UnitModal from '@/Components/UnitModal.vue';

const props = defineProps(['pathologyparameter', 'id', 'units']);

const form = useForm({
    name: props.pathologyparameter?.name ?? '',
    referance_from: props.pathologyparameter?.referance_from ?? '',
    referance_to: props.pathologyparameter?.referance_to ?? '',
    pathology_unit_id: props.pathologyparameter?.pathology_unit_id ?? '',
    description: props.pathologyparameter?.description ?? '',

    _method: props.pathologyparameter?.id ? 'put' : 'post',
});

const showUnitModal = ref(false);

const openUnitModal = () => {
    showUnitModal.value = true;
};

const closeUnitModal = () => {
    showUnitModal.value = false;
};

const handleUnitCreated = (response) => {
    router.reload({
        only: ['units'],
        onSuccess: () => {
            const newUnitId = response.data?.id;
            if (newUnitId) {
                form.pathology_unit_id = newUnitId;
            }
        }
    });
};
const submit = () => {
    const routeName = props.id ? route('backend.parameterofpathology.update', props.id) : route('backend.parameterofpathology.store');
    form.transform(data => ({
        ...data,
        remember: '',
        isDirty: false,
    })).post(routeName, {
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

const goToTestParameterList = () => {
    router.get(route('backend.parameterofpathology.index'));
};

</script>

<template>
    <BackendLayout>
        <div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md">

            <div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md">
                <div>
                    <h1 class="p-4 text-xl font-bold dark:text-white">{{ $page.props.pageTitle }}</h1>
                </div>

                <div class="p-2 py-2 flex items-center space-x-2">
                    <div class="flex items-center space-x-3">
                        <button @click="goToTestParameterList"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z">
                                </path>
                            </svg>
                            Test Parameter List
                        </button>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="p-4">
                <!-- <AlertMessage /> -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <!-- Parameter Name -->
                    <div class="col-span-1 sm:col-span-2">
                        <InputLabel for="name" value="Parameter Name*" />
                        <input id="name" type="text" required
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.name" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <!-- Reference Range -->
                    <div class="col-span-1">
                        <InputLabel for="referance_from" value="Reference Range From*" />
                        <input id="referance_from" type="text" required
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.referance_from" />
                        <InputError class="mt-2" :message="form.errors.referance_from" />
                    </div>

                    <div class="col-span-1">
                        <InputLabel for="referance_to" value="Reference Range To*" />
                        <input id="referance_to" type="text" required
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.referance_to" />
                        <InputError class="mt-2" :message="form.errors.referance_to" />
                    </div>

                    <!-- Unit -->
                    <div class="col-span-1">
                        <InputLabel for="pathology_unit_id" value="Unit*" />
                        <div class="flex items-center space-x-2">
                            <select id="pathology_unit_id" required
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                v-model="form.pathology_unit_id">
                                <option value="">Select Unit</option>
                                <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
                            </select>
                            <button type="button" @click="openUnitModal"
                                class="flex-shrink-0 inline-flex items-center justify-center w-10 h-10 text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200"
                                title="Add New Unit">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6">
                                    </path>
                                </svg>
                            </button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.pathology_unit_id" />
                    </div>

                    <!-- Description -->
                    <div class="col-span-1 sm:col-span-2">
                        <InputLabel for="description" value="Description" />
                        <textarea id="description"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.description" rows="3"></textarea>
                        <InputError class="mt-2" :message="form.errors.description" />
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

        <UnitModal :show="showUnitModal" :existing-units="units" @close="closeUnitModal" @unit-created="handleUnitCreated" />
    </BackendLayout>
</template>