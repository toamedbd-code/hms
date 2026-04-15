<script setup>
import { reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';

const toPreviewUrl = (value) => {
  if (!value) return '';

  const normalized = String(value).replace(/\\/g, '/').trim();

  if (
    normalized.startsWith('http://') ||
    normalized.startsWith('https://') ||
    normalized.startsWith('data:')
  ) {
    return normalized;
  }

  if (normalized.startsWith('/storage/')) {
    return normalized;
  }

  if (normalized.startsWith('storage/')) {
    return `/${normalized}`;
  }

  return `/storage/${normalized.replace(/^\/+/, '')}`;
};

const props = defineProps({
  settings: Object,
  pageTitle: {
    type: String,
    default: 'Report Settings',
  },
});

const form = useForm({
  barcode_scale: props.settings?.barcode_scale ?? 2.2,
  barcode_height: props.settings?.barcode_height ?? 52,
  report_header_html: props.settings?.report_header_html ?? '',
  report_footer_html: props.settings?.report_footer_html ?? '',
  report_show_header_footer: props.settings?.attendance_device_options?.reporting?.show_header_footer ?? true,
  report_margin_top: props.settings?.attendance_device_options?.reporting?.layout?.page_margin_top ?? 0,
  report_margin_bottom: props.settings?.attendance_device_options?.reporting?.layout?.page_margin_bottom ?? 0,
  report_header_height: props.settings?.attendance_device_options?.reporting?.layout?.header_height ?? 115,
  report_footer_height: props.settings?.attendance_device_options?.reporting?.layout?.footer_height ?? 70,
  signature_margin_top: props.settings?.attendance_device_options?.reporting?.signature?.margin_top ?? 160,
  signature_margin_left: props.settings?.attendance_device_options?.reporting?.signature?.margin_left ?? 96,
  pathologist_name: props.settings?.pathologist_name ?? '',
  pathologist_designation: props.settings?.pathologist_designation ?? '',
  technologist_name: props.settings?.attendance_device_options?.reporting?.identity?.technologist_name ?? '',
  technologist_designation: props.settings?.attendance_device_options?.reporting?.identity?.technologist_designation ?? '',
  sample_collected_by_name: props.settings?.attendance_device_options?.reporting?.identity?.sample_collected_by_name ?? '',
  sample_collected_by_designation: props.settings?.attendance_device_options?.reporting?.identity?.sample_collected_by_designation ?? '',
  technologist_signature: null,
  sample_collected_by_signature: null,
  pathologist_signature: null,
});

const signaturePreview = reactive({
  technologist_signature: toPreviewUrl(
    props.settings?.technologist_signature_preview_url ?? props.settings?.technologist_signature ?? ''
  ),
  sample_collected_by_signature: toPreviewUrl(
    props.settings?.sample_collected_by_signature_preview_url ?? props.settings?.sample_collected_by_signature ?? ''
  ),
  pathologist_signature: toPreviewUrl(
    props.settings?.pathologist_signature_preview_url ?? props.settings?.pathologist_signature ?? ''
  ),
});

const setSignatureFile = (event, field) => {
  const file = event?.target?.files?.[0] ?? null;
  form[field] = file;

  if (file) {
    signaturePreview[field] = URL.createObjectURL(file);
  }
};

const handlePreviewError = (field) => {
  signaturePreview[field] = '';
};

const submit = () => {
  form.post(route('backend.report-setting.update'), {
    forceFormData: true,
    onSuccess: (response) => displayResponse(response),
    onError: (errors) => displayWarning(errors),
  });
};
</script>

<template>
  <BackendLayout>
    <div class="w-full bg-white rounded-md">
      <div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md">
        <h1 class="p-4 text-xl font-bold">{{ pageTitle }}</h1>
      </div>

      <form @submit.prevent="submit" class="p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <InputLabel for="barcode_scale" value="Barcode Scale" />
            <input
              id="barcode_scale"
              v-model="form.barcode_scale"
              type="number"
              step="0.1"
              class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300"
            />
            <InputError class="mt-2" :message="form.errors.barcode_scale" />
          </div>

          <div>
            <InputLabel for="barcode_height" value="Barcode Height" />
            <input
              id="barcode_height"
              v-model="form.barcode_height"
              type="number"
              class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300"
            />
            <InputError class="mt-2" :message="form.errors.barcode_height" />
          </div>
        </div>

        <div class="grid grid-cols-1 gap-4 mt-4">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <InputLabel for="pathologist_name" value="Pathologist Name" />
              <input
                id="pathologist_name"
                v-model="form.pathologist_name"
                type="text"
                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300"
                placeholder="Dr. Name"
              />
              <InputError class="mt-2" :message="form.errors.pathologist_name" />
            </div>

            <div>
              <InputLabel for="pathologist_designation" value="Pathologist Designation" />
              <textarea
                id="pathologist_designation"
                v-model="form.pathologist_designation"
                rows="2"
                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300"
                placeholder="Senior Consultant Pathologist"
              ></textarea>
              <InputError class="mt-2" :message="form.errors.pathologist_designation" />
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mt-2">
            <div>
              <InputLabel for="technologist_name" value="Technologist Name" />
              <textarea id="technologist_name" v-model="form.technologist_name" rows="2" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300" placeholder="Technologist Name"></textarea>
              <InputError class="mt-2" :message="form.errors.technologist_name" />
            </div>

            <div>
              <InputLabel for="technologist_designation" value="Technologist Designation" />
              <textarea id="technologist_designation" v-model="form.technologist_designation" rows="2" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300" placeholder="Designation"></textarea>
              <InputError class="mt-2" :message="form.errors.technologist_designation" />
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mt-2">
            <div>
              <InputLabel for="sample_collected_by_name" value="Sample Collected By Name" />
              <textarea id="sample_collected_by_name" v-model="form.sample_collected_by_name" rows="2" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300" placeholder="Name"></textarea>
              <InputError class="mt-2" :message="form.errors.sample_collected_by_name" />
            </div>

            <div>
              <InputLabel for="sample_collected_by_designation" value="Sample Collected By Designation" />
              <textarea id="sample_collected_by_designation" v-model="form.sample_collected_by_designation" rows="2" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300" placeholder="Designation"></textarea>
              <InputError class="mt-2" :message="form.errors.sample_collected_by_designation" />
            </div>
          </div>

          <div>
            <InputLabel for="report_header_html" value="Report Header (HTML)" />
            <textarea
              id="report_header_html"
              v-model="form.report_header_html"
              rows="4"
              class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300"
            ></textarea>
            <InputError class="mt-2" :message="form.errors.report_header_html" />
          </div>

          <div>
            <InputLabel for="report_footer_html" value="Report Footer (HTML)" />
            <textarea
              id="report_footer_html"
              v-model="form.report_footer_html"
              rows="4"
              class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300"
            ></textarea>
            <InputError class="mt-2" :message="form.errors.report_footer_html" />
          </div>
        </div>

        <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-3">
          <div class="flex items-center gap-2">
            <input id="report_show_header_footer" type="checkbox" v-model="form.report_show_header_footer" class="w-4 h-4" />
            <label for="report_show_header_footer" class="text-sm text-gray-700">Show header/footer</label>
          </div>

          <div>
            <InputLabel for="report_margin_top" value="Print Top Margin (px)" />
            <input id="report_margin_top" v-model.number="form.report_margin_top" type="number" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300" />
            <InputError class="mt-2" :message="form.errors.report_margin_top" />
          </div>

          <div>
            <InputLabel for="report_margin_bottom" value="Print Bottom Margin (px)" />
            <input id="report_margin_bottom" v-model.number="form.report_margin_bottom" type="number" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300" />
            <InputError class="mt-2" :message="form.errors.report_margin_bottom" />
          </div>

          <div>
            <InputLabel for="report_header_height" value="Header Height (px)" />
            <input id="report_header_height" v-model.number="form.report_header_height" type="number" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300" />
            <InputError class="mt-2" :message="form.errors.report_header_height" />
          </div>

          <div>
            <InputLabel for="report_footer_height" value="Footer Height (px)" />
            <input id="report_footer_height" v-model.number="form.report_footer_height" type="number" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300" />
            <InputError class="mt-2" :message="form.errors.report_footer_height" />
          </div>
        </div>

        <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-3">
          <div>
            <InputLabel for="signature_margin_top" value="Signature Margin Top (px)" />
            <input id="signature_margin_top" v-model.number="form.signature_margin_top" type="number" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300" />
            <InputError class="mt-2" :message="form.errors.signature_margin_top" />
          </div>

          <div>
            <InputLabel for="signature_margin_left" value="Signature Margin Left (px)" />
            <input id="signature_margin_left" v-model.number="form.signature_margin_left" type="number" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300" />
            <InputError class="mt-2" :message="form.errors.signature_margin_left" />
          </div>

          <div>
            <p class="text-sm text-gray-600 mt-6">এগুলো report print layout সামঞ্জস্য করার জন্য — নিরাপদ প্যাডিং হিসেবে প্রয়োগ হবে।</p>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-3">
          <div>
            <InputLabel for="technologist_signature" value="Technologist Signature" />
            <div v-if="signaturePreview.technologist_signature" class="mt-2 mb-2">
              <img :src="signaturePreview.technologist_signature" alt="Technologist signature" class="h-16 border rounded" @error="() => handlePreviewError('technologist_signature')" />
            </div>
            <input
              id="technologist_signature"
              type="file"
              accept="image/*"
              class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300"
              @change="(event) => setSignatureFile(event, 'technologist_signature')"
            />
            <InputError class="mt-2" :message="form.errors.technologist_signature" />
          </div>

          <div>
            <InputLabel for="sample_collected_by_signature" value="Sample Collected By Signature" />
            <div v-if="signaturePreview.sample_collected_by_signature" class="mt-2 mb-2">
              <img :src="signaturePreview.sample_collected_by_signature" alt="Sample collected by signature" class="h-16 border rounded" @error="() => handlePreviewError('sample_collected_by_signature')" />
            </div>
            <input
              id="sample_collected_by_signature"
              type="file"
              accept="image/*"
              class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300"
              @change="(event) => setSignatureFile(event, 'sample_collected_by_signature')"
            />
            <InputError class="mt-2" :message="form.errors.sample_collected_by_signature" />
          </div>

          <div>
            <InputLabel for="pathologist_signature" value="Pathologist Signature" />
            <div v-if="signaturePreview.pathologist_signature" class="mt-2 mb-2">
              <img :src="signaturePreview.pathologist_signature" alt="Pathologist signature" class="h-16 border rounded" @error="() => handlePreviewError('pathologist_signature')" />
            </div>
            <input
              id="pathologist_signature"
              type="file"
              accept="image/*"
              class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300"
              @change="(event) => setSignatureFile(event, 'pathologist_signature')"
            />
            <InputError class="mt-2" :message="form.errors.pathologist_signature" />
          </div>
        </div>

        <div class="flex items-center justify-end mt-6">
          <PrimaryButton type="submit" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
            Save Settings
          </PrimaryButton>
        </div>
      </form>
    </div>
  </BackendLayout>
</template>
