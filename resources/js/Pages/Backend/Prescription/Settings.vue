<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { onMounted, nextTick, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { displayResponse } from '@/responseMessage.js';

const props = defineProps({
    settings: Object,
});

const form = useForm({
    doctor_designation: props.settings?.doctor_designation ?? '',
    active_pad_type: props.settings?.institution_pad_url ? 'institution' : 'header',
    institution_pad_content_offset: props.settings?.institution_pad_content_offset ?? 0,
    doctor_seal: null,
    doctor_signature: null,
    prescription_header: null,
    institution_pad: null,
});

const designationInput = ref(null);

const resizeDesignation = (element = designationInput.value) => {
    if (!element) return;
    element.style.height = 'auto';
    element.style.height = `${element.scrollHeight}px`;
};

const selectPad = (type, event) => {
    const file = event.target.files?.[0] ?? null;
    if (!file) return;
    form.active_pad_type = type;
    if (type === 'header') {
        form.prescription_header = file;
        form.institution_pad = null;
    } else {
        form.institution_pad = file;
        form.prescription_header = null;
    }
};

onMounted(() => nextTick(() => resizeDesignation()));

const submit = () => {
    form.post(route('backend.prescription.settings.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: (response) => displayResponse(response),
    });
};

const goBack = () => router.visit(route('backend.prescription.index'));
</script>

<template>
    <BackendLayout>
        <div class="w-full rounded-md bg-white p-4 dark:bg-slate-900">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-xl font-bold dark:text-white">Prescription Settings</h1>
                <button type="button" class="rounded bg-gray-500 px-3 py-1.5 text-xs text-white" @click="goBack">Back</button>
            </div>

            <div class="rounded-md border border-gray-200 p-4 dark:border-gray-700">
                <form class="grid grid-cols-1 gap-4 md:grid-cols-2" @submit.prevent="submit">
                    <div class="md:col-span-2">
                        <InputLabel value="Prescription Header Image" class="mb-1 text-xs" />
                        <img v-if="form.active_pad_type === 'header' && settings?.prescription_header_url" :src="settings.prescription_header_url" alt="Current prescription header" class="mb-2 max-h-28 w-full rounded border bg-white object-contain object-left p-1" />
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="w-full rounded border px-2 py-1.5 text-xs" @change="selectPad('header', $event)" />
                        <InputError class="mt-1" :message="form.errors.prescription_header" />
                    </div>
                    <div class="md:col-span-2">
                        <InputLabel value="Full-page Institution Pad (A4)" class="mb-1 text-xs" />
                        <img v-if="form.active_pad_type === 'institution' && settings?.institution_pad_url" :src="settings.institution_pad_url" alt="Current full-page institution pad" class="mb-2 max-h-48 w-full rounded border bg-white object-contain object-left p-1" />
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="w-full rounded border px-2 py-1.5 text-xs" @change="selectPad('institution', $event)" />
                        <InputError class="mt-1" :message="form.errors.institution_pad" />
                    </div>
                    <div v-if="form.active_pad_type === 'institution'" class="md:col-span-2 rounded border border-gray-200 p-3 dark:border-gray-700">
                        <InputLabel value="Text Start Position on Full-page Pad (mm from top)" class="mb-2 text-xs" />
                        <div class="flex items-center gap-3">
                            <input v-model.number="form.institution_pad_content_offset" type="range" min="0" max="100" step="1" class="min-w-0 flex-1" />
                            <input v-model.number="form.institution_pad_content_offset" type="number" min="0" max="100" step="1" class="w-20 rounded border px-2 py-1.5 text-xs dark:bg-slate-700" aria-label="Text start position in millimeters" />
                        </div>
                        <InputError class="mt-1" :message="form.errors.institution_pad_content_offset" />
                    </div>
                    <div class="md:col-span-2">
                        <InputLabel value="Doctor Designation" class="mb-1 text-xs" />
                        <textarea
                            ref="designationInput"
                            v-model="form.doctor_designation"
                            rows="1"
                            maxlength="2000"
                            class="w-full resize-none overflow-hidden rounded border px-2 py-1.5 text-sm dark:bg-slate-700"
                            placeholder="e.g. Consultant Medicine&#10;MBBS, FCPS&#10;Specialist in Medicine"
                            @input="resizeDesignation($event.target)"
                        ></textarea>
                        <InputError class="mt-1" :message="form.errors.doctor_designation" />
                    </div>

                    <div>
                        <InputLabel value="Doctor Seal" class="mb-1 text-xs" />
                        <img v-if="settings?.doctor_seal_url" :src="settings.doctor_seal_url" alt="Current doctor seal" class="mb-2 h-16 w-auto rounded border bg-white p-1" />
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="w-full rounded border px-2 py-1.5 text-xs" @change="form.doctor_seal = $event.target.files?.[0] ?? null" />
                        <InputError class="mt-1" :message="form.errors.doctor_seal" />
                    </div>

                    <div>
                        <InputLabel value="Doctor Signature" class="mb-1 text-xs" />
                        <img v-if="settings?.doctor_signature_url" :src="settings.doctor_signature_url" alt="Current doctor signature" class="mb-2 h-16 w-auto rounded border bg-white p-1" />
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="w-full rounded border px-2 py-1.5 text-xs" @change="form.doctor_signature = $event.target.files?.[0] ?? null" />
                        <InputError class="mt-1" :message="form.errors.doctor_signature" />
                    </div>

                    <div class="md:col-span-2 flex justify-end">
                        <button type="submit" class="rounded bg-emerald-600 px-4 py-2 text-sm text-white disabled:opacity-60" :disabled="form.processing">
                            {{ form.processing ? 'Saving...' : 'Save Settings' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </BackendLayout>
</template>
