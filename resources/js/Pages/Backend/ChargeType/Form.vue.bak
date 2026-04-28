<script setup>
import { ref, onMounted } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AlertMessage from '@/Components/AlertMessage.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';

const props = defineProps(['chargetype', 'id']);

// Available module options
const moduleOptions = [
    'Appointment',
    'OPD',
    'IPD',
    'Pathology',
    'Radiology',
    'Blood Bank',
    'Ambulance'
];

// Initialize form with modules as array
const form = useForm({
    name: props.chargetype?.name ?? '',
    modules: props.chargetype?.modules ? JSON.parse(props.chargetype.modules) : [],

    _method: props.chargetype?.id ? 'put' : 'post',
});

// Handle checkbox changes
const toggleModule = (module) => {
    if (form.modules.includes(module)) {
        form.modules = form.modules.filter(m => m !== module);
    } else {
        form.modules.push(module);
    }
};

const submit = () => {
    // Convert modules array to JSON string before submitting
    const formData = {
        ...form.data(),
        modules: JSON.stringify(form.modules)
    };

    const routeName = props.id ? route('backend.chargetype.update', props.id) : route('backend.chargetype.store');

    form.transform(data => ({
        ...data,
        modules: JSON.stringify(data.modules),
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

const goToChargeTypeList = () => {
    router.get(route('backend.chargetype.index'));
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
                        <button @click="goToChargeTypeList"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z">
                                </path>
                            </svg>
                            Charge Type List
                        </button>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="p-4">
                <AlertMessage />
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <InputLabel for="name" value="Charge Type *" />
                        <input id="name"
                            class="block w-full p-2 mt-1 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.name" type="text" placeholder="Charge Type Name" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div>
                        <InputLabel value="Module *" />
                        <div class="flex flex-col gap-2 mt-2 ">
                            <div v-for="(module, index) in moduleOptions" :key="module" class="flex items-center">
                                <input type="checkbox" :id="'module_' + module" :value="module"
                                    :checked="form.modules.includes(module)" @change="toggleModule(module)"
                                    class="w-4 h-4 text-black text-black rounded focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600" />
                                <label :for="'module_' + module"
                                    class="ml-2 text-sm font-medium text-black dark:text-gray-300">
                                    {{ module }}
                                </label>
                            </div>
                        </div>
                        <InputError class="mt-2" :message="form.errors.modules" />
                    </div>
                </div>

                <div class="flex items-center justify-end mt-6">
                    <PrimaryButton type="submit" class="ms-4" :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing">
                        {{ ((props.id ?? false) ? 'Update' : 'Create') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </BackendLayout>
</template>