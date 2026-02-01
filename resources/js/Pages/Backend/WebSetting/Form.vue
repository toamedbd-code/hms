<script setup>
import { ref, onMounted, watch } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AlertMessage from '@/Components/AlertMessage.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';
import { data } from 'jquery';

// Define props
const props = defineProps(["websetting", "id"]);

// Setup form with initial values
const form = useForm({
    company_name: props.websetting?.company_name ?? '',
    company_short_name: props.websetting?.company_short_name ?? '',
    phone: props.websetting?.phone ?? '',
    logo: props.websetting?.logo ?? '',
    icon: props.websetting?.icon ?? '',
    report_title: props.websetting?.report_title ?? '',

    logoPreview: props.websetting?.logo ?? "",
    iconPreview: props.websetting?.icon ?? "",
    _method: props.websetting?.id ? "post" : "post",
});

// Auto-generate company short name from company name
watch(() => form.company_name, (newValue) => {
    if (newValue) {
        // Generate short name by taking first letters of each word (max 10 chars)
        const words = newValue.trim().split(/\s+/);
        const shortName = words.map(word => word.charAt(0).toUpperCase()).join('');
        form.company_short_name = shortName.substring(0, 10);
    }
});

const handleLogoChange = (event) => {
    const file = event.target.files[0];
    form.logo = file;

    // Display image preview
    const reader = new FileReader();
    reader.onload = (e) => {
        form.logoPreview = e.target.result;
    };
    reader.readAsDataURL(file);
};

const handleIconChange = (event) => {
    const file = event.target.files[0];
    form.icon = file;

    // Display image preview
    const reader = new FileReader();
    reader.onload = (e) => {
        form.iconPreview = e.target.result;
    };
    reader.readAsDataURL(file);
};

const submit = () => {
    const routeName = route("backend.websetting.store");
    form.transform(data => ({
        ...data,
        remember: '',
        isDirty: false,
    })).post(routeName, {

        onSuccess: (response) => {
            displayResponse(response);
            router.reload({
                only: ['websetting'],
                preserveScroll: true,
                preserveState: true
            });
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
            class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md">

            <div
                class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md">
                <div>
                    <h1 class="p-4 text-xl font-bold dark:text-white">{{ $page.props.pageTitle }}</h1>
                </div>
                <div class="p-4 py-2">
                </div>
            </div>

            <form @submit.prevent="submit" class="p-4">
                <!-- <AlertMessage /> -->
                
                <!-- Main Fields -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3">
                    <div class="col-span-1">
                        <InputLabel for="company_name" value="Company Name" />
                        <input id="company_name"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.company_name" type="text" placeholder="Enter company name" />
                        <InputError class="mt-2" :message="form.errors.company_name" />
                    </div>

                    <div class="col-span-1">
                        <InputLabel for="company_short_name" value="Company Short Name" />
                        <input id="company_short_name"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.company_short_name" type="text" placeholder="Auto-generated or custom" />
                        <InputError class="mt-2" :message="form.errors.company_short_name" />
                        <p class="text-xs text-gray-500 mt-1">Auto-generated from company name or enter custom</p>
                    </div>

                    <div class="col-span-1">
                        <InputLabel for="phone" value="Phone" />
                        <input id="phone"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.phone" type="text" placeholder="Enter phone number" />
                        <InputError class="mt-2" :message="form.errors.phone" />
                    </div>
                </div>

                <!-- Report Title Field -->
                <div class="grid grid-cols-1 gap-3 mt-3">
                    <div class="col-span-1">
                        <InputLabel for="report_title" value="Report Title" />
                        <input id="report_title"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.report_title" type="text" placeholder="Enter report title" />
                        <InputError class="mt-2" :message="form.errors.report_title" />
                    </div>
                </div>

                <!-- File Upload Section -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    <!-- Logo Upload -->
                    <div class="col-span-1">
                        <InputLabel for="logo" value="Company Logo" />
                        <div v-if="form.logoPreview" class="mb-3">
                            <img :src="form.logoPreview" alt="Logo Preview" 
                                class="max-w-xs rounded-md border shadow-sm" 
                                width="150" height="120" />
                        </div>
                        <input id="logo"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            type="file" accept="image/*" @change="handleLogoChange" />
                        <InputError class="mt-2" :message="form.errors.logo" />
                        <p class="text-xs text-gray-500 mt-1">Recommended: 300x200px, Max: 2MB</p>
                    </div>

                    <!-- Icon Upload -->
                    <div class="col-span-1">
                        <InputLabel for="icon" value="Company Icon" />
                        <div v-if="form.iconPreview" class="mb-3">
                            <img :src="form.iconPreview" alt="Icon Preview" 
                                class="max-w-xs rounded-md border shadow-sm" 
                                width="80" height="80" />
                        </div>
                        <input id="icon"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            type="file" accept="image/*,.ico" @change="handleIconChange" />
                        <InputError class="mt-2" :message="form.errors.icon" />
                        <p class="text-xs text-gray-500 mt-1">Recommended: 64x64px, Max: 1MB</p>
                    </div>
                </div>

                <div class="flex items-center justify-end mt-6">
                    <PrimaryButton type="submit" class="ms-4" :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing">
                        {{ props.id ? 'Update Settings' : 'Save Settings' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </BackendLayout>
</template>