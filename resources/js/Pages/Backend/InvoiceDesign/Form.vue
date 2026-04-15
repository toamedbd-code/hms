<script setup>
import { ref, onMounted } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AlertMessage from '@/Components/AlertMessage.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';

const props = defineProps(['invoicedesign', 'id']);

const normalizePreviewUrl = (value) => {
    if (!value) return null;
    const normalized = String(value).replace(/\\/g, '/').trim();
    if (!normalized) return null;

    if (normalized.startsWith('http://') || normalized.startsWith('https://') || normalized.startsWith('data:')) {
        return normalized;
    }

    if (normalized.startsWith('/storage/')) return normalized.replace('/storage/storage/', '/storage/');
    if (normalized.startsWith('storage/')) return `/${normalized}`.replace('/storage/storage/', '/storage/');
    if (normalized.startsWith('public/storage/')) return `/${normalized.replace(/^public\//, '')}`;
    if (normalized.startsWith('/public/storage/')) return normalized.replace('/public/storage/', '/storage/');

    return `/storage/${normalized}`.replace('/storage/storage/', '/storage/');
};

const form = useForm({
    footer_content: props.invoicedesign?.footer_content ?? '',
    module: props.invoicedesign?.module ?? '',
    headerPhoto: null,
    footerPhoto: null,
    headerPhotoPreview: normalizePreviewUrl(
        props.invoicedesign?.header_photo_url ?? props.invoicedesign?.header_photo_path
    ),
    footerPhotoPreview: normalizePreviewUrl(
        props.invoicedesign?.footer_photo_url ?? props.invoicedesign?.footer_photo_path
    ),
    header_height: props.invoicedesign?.header_height ?? 115,
    footer_height: props.invoicedesign?.footer_height ?? 70,

    _method: props.invoicedesign?.id ? 'put' : 'post',
});

const handlePhotoChange = (event, field) => {
    if (event.target.files.length > 0) {
        const file = event.target.files[0];
        form[field] = file;

        // Display photo preview
        const reader = new FileReader();
        reader.onload = (e) => {
            form[`${field}Preview`] = e.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const removePhoto = (field) => {
    form[field] = null;
    form[`${field}Preview`] = null;
    // Reset file input
    document.getElementById(field).value = '';
};

const submit = () => {
    const routeName = props.id
        ? route('backend.invoicedesign.update', props.id)
        : route('backend.invoicedesign.store');

    form.post(routeName, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: (response) => {
            if (!props.id) {
                form.reset();
                document.getElementById('headerPhoto').value = '';
                document.getElementById('footerPhoto').value = '';
            }
            displayResponse(response);
        },
        onError: (errors) => {
            displayWarning(errors);
        },
    });
};

const goToInvoiceDesignList = () => {
    router.visit(route('backend.invoicedesign.index'));
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
                        <button @click="goToInvoiceDesignList"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z">
                                </path>
                            </svg>
                            Invoice Design List
                        </button>
                    </div>
                </div>
            </div>

            <!-- Form Content -->
            <form @submit.prevent="submit" class="p-6">
                <!-- <AlertMessage /> -->

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">

                    <div class="col-span-3 md:col-span-3">
                        <InputLabel for="module" value="Module *" />
                        <select id="module" v-model="form.module"
                            class="w-[20%] p-2 mt-1 text-sm border rounded-md shadow-sm border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:focus:ring-slate-500 dark:focus:border-slate-500">
                            <option value="">Select Module</option>
                            <option value="opd">OPD</option>
                            <option value="ipd">IPD</option>
                            <option value="pathology">Pathology</option>
                            <option value="radiology">Radiology</option>
                            <option value="pharmacy">Pharmacy</option>
                            <option value="appointment">Appointment</option>
                            <option value="billing">Billing</option>
                            <option value="prescription">Prescription</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.module" />
                    </div>

                    <!-- Header Photo -->
                    <div class="col-span-1 md:col-span-2">
                        <InputLabel for="headerPhoto" value="Header Image" />

                        <!-- Preview above input -->
                        <div v-if="form.headerPhotoPreview" class="relative w-full mb-2">
                            <img :src="form.headerPhotoPreview" alt="Header preview"
                                class="object-fill w-full h-32 border rounded-md" />
                            <button type="button" @click="removePhoto('headerPhoto')"
                                class="absolute p-1 text-white bg-red-500 rounded-full -top-2 -right-2 hover:bg-red-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>

                        <input id="headerPhoto" type="file" accept="image/*"
                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-gray-600 dark:file:text-gray-200"
                            @change="(e) => handlePhotoChange(e, 'headerPhoto')" />

                        <InputError class="mt-1" :message="form.errors.headerPhoto" />
                    </div>

                    <!-- Footer Content Field -->
                    <div class="col-span-1 md:col-span-2">
                        <InputLabel for="footer_content" value="Footer Content *" />
                        <textarea id="footer_content" v-model="form.footer_content" rows="4"
                            class="w-full p-2 mt-1 text-sm border rounded-md shadow-sm border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:focus:ring-slate-500 dark:focus:border-slate-500"
                            placeholder="Enter Footer Contents"></textarea>
                        <InputError class="mt-1" :message="form.errors.footer_content" />
                    </div>

                    <!-- Header/Footer Height -->
                    <div class="col-span-1 md:col-span-1">
                        <InputLabel for="header_height" value="Header Height (px)" />
                        <input id="header_height" type="number" min="0" max="1000" step="1" v-model.number="form.header_height"
                            class="w-full p-2 mt-1 text-sm border rounded-md shadow-sm border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                        <InputError class="mt-1" :message="form.errors.header_height" />
                    </div>

                    <div class="col-span-1 md:col-span-1">
                        <InputLabel for="footer_height" value="Footer Height (px)" />
                        <input id="footer_height" type="number" min="0" max="1000" step="1" v-model.number="form.footer_height"
                            class="w-full p-2 mt-1 text-sm border rounded-md shadow-sm border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                        <InputError class="mt-1" :message="form.errors.footer_height" />
                    </div>

                    <!-- Footer Photo -->
                    <div class="col-span-1 md:col-span-2">
                        <InputLabel for="footerPhoto" value="Footer Image" />

                        <!-- Preview above input -->
                        <div v-if="form.footerPhotoPreview" class="relative w-full mb-2">
                            <img :src="form.footerPhotoPreview" alt="Footer preview"
                                class="object-contain w-full h-32 border rounded-md" />
                            <button type="button" @click="removePhoto('footerPhoto')"
                                class="absolute p-1 text-white bg-red-500 rounded-full -top-2 -right-2 hover:bg-red-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>

                        <input id="footerPhoto" type="file" accept="image/*"
                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-gray-600 dark:file:text-gray-200"
                            @change="(e) => handlePhotoChange(e, 'footerPhoto')" />

                        <InputError class="mt-1" :message="form.errors.footerPhoto" />
                    </div>
                </div>

                <!-- Form Footer -->
                <div class="flex items-center justify-end pt-6 space-x-3 border-t border-gray-200 dark:border-gray-700">
                    <button type="button" @click="router.visit(route('backend.invoicedesign.index'))"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-700">
                        Cancel
                    </button>
                    <PrimaryButton type="submit" class="px-4 py-2 text-sm"
                        :class="{ 'opacity-50 cursor-not-allowed': form.processing }" :disabled="form.processing">
                        <span v-if="form.processing">
                            <svg class="w-5 h-5 mr-2 -ml-1 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            Processing...
                        </span>
                        <span v-else>
                            {{ props.id ? 'Update Design' : 'Create Design' }}
                        </span>
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </BackendLayout>
</template>