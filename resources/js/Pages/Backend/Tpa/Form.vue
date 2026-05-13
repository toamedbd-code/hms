<script setup>
import { ref, onMounted } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AlertMessage from '@/Components/AlertMessage.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';

const props = defineProps(['tpa', 'id']);

const form = useForm({
    name: props.tpa?.name ?? '',
    code: props.tpa?.code ?? '',
    contact_number: props.tpa?.contact_number ?? '',
    address: props.tpa?.address ?? '',
    contact_person_name: props.tpa?.contact_person_name ?? '',
    contact_person_phone: props.tpa?.contact_person_phone ?? '',

    _method: props.tpa?.id ? 'put' : 'post',
});

const submit = () => {
    const routeName = props.id ? route('backend.tpa.update', props.id) : route('backend.tpa.store');
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

const goToTpaList = () => {
    router.get(route('backend.tpa.index'));
};

</script>

<template>
    <BackendLayout>
        <div
            class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md">

            <div
                class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md shadow-md dark:bg-gray-800 dark:text-gray-200">
                <div>
                    <h1 class="p-4 text-xl font-bold dark:text-white">{{ $page.props.pageTitle }}</h1>
                </div>

                <div class="p-2 py-2 flex items-center space-x-2">
                    <div class="flex items-center space-x-3">

                        <button @click="goToTpaList"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z">
                                </path>
                            </svg>
                            Tpa List
                        </button>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="p-4">
                <AlertMessage />
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4">

                    <div class="col-span-1 md:col-span-1">
                        <InputLabel for="name" value="Name" />
                        <input id="name"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.name" type="text" placeholder="Name" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>
                    <div class="col-span-1 md:col-span-1">
                        <InputLabel for="code" value="Code" />
                        <input id="code"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.code" type="text" placeholder="Code" />
                        <InputError class="mt-2" :message="form.errors.code" />
                    </div>
                    <div class="col-span-1 md:col-span-1">
                        <InputLabel for="contact_number" value="Contact Number" />
                        <input id="contact_number"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.contact_number" type="text" placeholder="Contact Number" />
                        <InputError class="mt-2" :message="form.errors.contact_number" />
                    </div>
                    <div class="col-span-1 md:col-span-1">
                        <InputLabel for="address" value="Address" />
                        <textarea id="address"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.address" type="text" placeholder="Address" />
                        <InputError class="mt-2" :message="form.errors.address" />
                    </div>
                    <div class="col-span-1 md:col-span-1">
                        <InputLabel for="contact_person_name" value="Contact Person Name" />
                        <input id="contact_person_name"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.contact_person_name" type="text" placeholder="Contact Person Name" />
                        <InputError class="mt-2" :message="form.errors.contact_person_name" />
                    </div>
                    <div class="col-span-1 md:col-span-1">
                        <InputLabel for="contact_person_phone" value="Contact Person Phone" />
                        <input id="contact_person_phone"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.contact_person_phone" type="text" placeholder="Contact Person Phone" />
                        <InputError class="mt-2" :message="form.errors.contact_person_phone" />
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
