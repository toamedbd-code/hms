<script setup>
import { computed, nextTick, onMounted, reactive } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
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
  mainCategories: {
    type: Array,
    default: () => [],
  },
  reportingDepartments: {
    type: Object,
    default: () => ({}),
  },
  pageTitle: {
    type: String,
    default: 'Report Settings',
  },
  fullPagePadAvailable: {
    type: Boolean,
    default: false,
  },
});

const buildMachineInfoCategories = (value = {}) => {
  const categories = {};

  for (const item of props.mainCategories ?? []) {
    const name = typeof item?.name === 'string' ? item.name.trim() : '';
    if (!name) continue;

    const saved = value && typeof value === 'object' ? value[name] : null;
    categories[name] = {
      enabled: saved?.enabled !== undefined ? Boolean(saved.enabled) : true,
      text: typeof saved?.text === 'string' ? saved.text : '',
    };
  }

  if (!value || typeof value !== 'object') {
    return categories;
  }

  Object.entries(value).forEach(([category, config]) => {
    if (!categories[category] && config && typeof config === 'object') {
      categories[category] = {
        enabled: config.enabled !== undefined ? Boolean(config.enabled) : true,
        text: typeof config.text === 'string' ? config.text : '',
      };
    }
  });

  return categories;
};

const reportingDepartmentOptions = [
  { key: 'xray', label: 'X-ray' },
  { key: 'ultrasound', label: 'Ultrasound' },
];

const departmentIdentityFields = [
  { key: 'pathologist_name', label: 'Reporting Doctor Name', placeholder: 'Doctor Name' },
  { key: 'pathologist_designation', label: 'Reporting Doctor Designation', placeholder: 'Radiologist / Consultant' },
  { key: 'technologist_name', label: 'Technologist Name', placeholder: 'Technologist Name' },
  { key: 'technologist_designation', label: 'Technologist Designation', placeholder: 'Designation' },
  { key: 'sample_collected_by_name', label: 'Sample Collected By Name', placeholder: 'Name' },
  { key: 'sample_collected_by_designation', label: 'Sample Collected By Designation', placeholder: 'Designation' },
];

const departmentSignatureFields = [
  { key: 'technologist_signature', label: 'Technologist Signature' },
  { key: 'sample_collected_by_signature', label: 'Sample Collected By Signature' },
  { key: 'pathologist_signature', label: 'Reporting Doctor Signature' },
];

const reportingSettings = props.settings?.attendance_device_options?.reporting ?? {};
const initialReportLayoutMode = (settings) => (settings?.full_page_institution_pad
  ?? reportingSettings.full_page_institution_pad)
  ? 'full_page_pad'
  : 'header_footer';
const reportLayoutMode = reactive({
  default: initialReportLayoutMode(reportingSettings),
  xray: initialReportLayoutMode(reportingSettings.departments?.xray ?? reportingSettings),
  ultrasound: initialReportLayoutMode(reportingSettings.departments?.ultrasound ?? reportingSettings),
});
const layoutModeSaving = reactive({
  default: false,
  xray: false,
  ultrasound: false,
});
const legacyReportLayout = reportingSettings.layout ?? {};
const legacyShowHeader = reportingSettings.show_header ?? reportingSettings.show_header_footer ?? true;
const legacyShowFooter = reportingSettings.show_footer ?? reportingSettings.show_header_footer ?? true;
const millimetersFromPixels = (value) => Math.round((Number(value) || 0) * 25.4 / 96);
const pixelLayoutKeys = new Set([
  'page_margin_top',
  'page_margin_bottom',
  'page_margin_left',
  'page_margin_right',
  'header_height',
  'footer_height',
  'font_size_adjustment',
]);

const departmentNumberLayoutFields = [
  { key: 'full_page_pad_content_offset', group: 'layout', label: 'Text Start Position (mm)', min: 0, max: 100, default: 0 },
  { key: 'page_margin_top', group: 'layout', label: 'Print Top Margin (mm)', min: 0, max: 529, default: 0 },
  { key: 'page_margin_bottom', group: 'layout', label: 'Print Bottom Margin (mm)', min: 0, max: 529, default: 0 },
  { key: 'header_height', group: 'layout', label: 'Header Height (mm)', min: 0, max: 265, default: 115 },
  { key: 'footer_height', group: 'layout', label: 'Footer Height (mm)', min: 0, max: 265, default: 70 },
  { key: 'footer_content_offset', group: 'layout', label: 'Footer Content Position (mm)', min: -50, max: 50, default: 0 },
  { key: 'font_size_adjustment', group: 'layout', label: 'Report Font Size Adjustment (mm)', min: 0, max: 3, default: 0 },
  { key: 'margin_top', group: 'signature', label: 'Signature Margin Top (mm)', min: 0, max: 529, default: 160 },
  { key: 'margin_left', group: 'signature', label: 'Signature Margin Left (mm)', min: 0, max: 529, default: 96 },
];

const buildReportingDepartments = (value = {}) => Object.fromEntries(
  reportingDepartmentOptions.map(({ key }) => {
    const saved = value?.[key] && typeof value[key] === 'object' ? value[key] : {};
    return [key, {
      machine_info_line: saved.machine_info_line ?? '',
      machine_info_line_enabled: saved.machine_info_line_enabled ?? true,
      show_header_footer: Boolean(saved.show_header ?? legacyShowHeader)
        && Boolean(saved.show_footer ?? legacyShowFooter),
      full_page_institution_pad: saved.full_page_institution_pad ?? reportingSettings.full_page_institution_pad ?? false,
      layout: Object.fromEntries(departmentNumberLayoutFields
        .filter((field) => field.group === 'layout')
        .map((field) => {
          const value = saved.layout?.[field.key] ?? legacyReportLayout[field.key] ?? field.default;
          return [field.key, pixelLayoutKeys.has(field.key) ? millimetersFromPixels(value) : value];
        })),
      signature: {
        margin_top: millimetersFromPixels(saved.signature?.margin_top ?? reportingSettings.signature?.margin_top ?? 160),
        margin_left: millimetersFromPixels(saved.signature?.margin_left ?? reportingSettings.signature?.margin_left ?? 96),
      },
      pathologist_name: saved.pathologist_name ?? '',
      pathologist_designation: saved.pathologist_designation ?? '',
      technologist_name: saved.technologist_name ?? '',
      technologist_designation: saved.technologist_designation ?? '',
      sample_collected_by_name: saved.sample_collected_by_name ?? '',
      sample_collected_by_designation: saved.sample_collected_by_designation ?? '',
      technologist_signature: null,
      sample_collected_by_signature: null,
      pathologist_signature: null,
      remove_technologist_signature: false,
      remove_sample_collected_by_signature: false,
      remove_pathologist_signature: false,
    }];
  })
);

const setReportLayoutMode = (scope, mode) => {
  if (layoutModeSaving[scope] || reportLayoutMode[scope] === mode) {
    return;
  }

  const previousMode = reportLayoutMode[scope];
  reportLayoutMode[scope] = mode;

  if (scope === 'default') {
    form.report_full_page_institution_pad = mode === 'full_page_pad';
    form.report_show_header_footer = mode === 'header_footer';
  } else {
    const departmentSettings = form.reporting_departments[scope];
    departmentSettings.full_page_institution_pad = mode === 'full_page_pad';
    departmentSettings.show_header_footer = mode === 'header_footer';
  }

  layoutModeSaving[scope] = true;
  let saved = false;

  router.post(route('backend.report-setting.update'), {
    barcode_scale: form.barcode_scale,
    barcode_height: form.barcode_height,
    report_layout_mode_scope: scope,
    report_layout_mode: mode,
  }, {
    preserveScroll: true,
    onSuccess: (response) => {
      saved = true;
      displayResponse(response);
    },
    onError: (errors) => {
      reportLayoutMode[scope] = previousMode;
      if (scope === 'default') {
        form.report_full_page_institution_pad = previousMode === 'full_page_pad';
        form.report_show_header_footer = previousMode === 'header_footer';
      } else {
        form.reporting_departments[scope].full_page_institution_pad = previousMode === 'full_page_pad';
        form.reporting_departments[scope].show_header_footer = previousMode === 'header_footer';
      }
      displayWarning(errors);
    },
    onFinish: () => {
      if (!saved) {
        reportLayoutMode[scope] = previousMode;
        if (scope === 'default') {
          form.report_full_page_institution_pad = previousMode === 'full_page_pad';
          form.report_show_header_footer = previousMode === 'header_footer';
        } else {
          form.reporting_departments[scope].full_page_institution_pad = previousMode === 'full_page_pad';
          form.reporting_departments[scope].show_header_footer = previousMode === 'header_footer';
        }
      }
      layoutModeSaving[scope] = false;
    },
  });
};

const form = useForm({
  barcode_scale: props.settings?.barcode_scale ?? 2.2,
  barcode_height: props.settings?.barcode_height ?? 52,
  report_show_header_footer: (props.settings?.attendance_device_options?.reporting?.show_header ?? props.settings?.attendance_device_options?.reporting?.show_header_footer ?? true)
    && (props.settings?.attendance_device_options?.reporting?.show_footer ?? props.settings?.attendance_device_options?.reporting?.show_header_footer ?? true),
  report_full_page_institution_pad: props.settings?.attendance_device_options?.reporting?.full_page_institution_pad ?? false,
  report_full_page_pad_content_offset: props.settings?.attendance_device_options?.reporting?.layout?.full_page_pad_content_offset ?? 0,
  report_margin_top: millimetersFromPixels(props.settings?.attendance_device_options?.reporting?.layout?.page_margin_top ?? 0),
  report_margin_bottom: millimetersFromPixels(props.settings?.attendance_device_options?.reporting?.layout?.page_margin_bottom ?? 0),
  report_margin_left: millimetersFromPixels(props.settings?.attendance_device_options?.reporting?.layout?.page_margin_left ?? 48),
  report_margin_right: millimetersFromPixels(props.settings?.attendance_device_options?.reporting?.layout?.page_margin_right ?? 48),
  report_header_height: millimetersFromPixels(props.settings?.attendance_device_options?.reporting?.layout?.header_height ?? 115),
  report_footer_height: millimetersFromPixels(props.settings?.attendance_device_options?.reporting?.layout?.footer_height ?? 70),
  report_footer_content_offset: props.settings?.attendance_device_options?.reporting?.layout?.footer_content_offset ?? 0,
  report_font_size_adjustment: millimetersFromPixels(props.settings?.attendance_device_options?.reporting?.layout?.font_size_adjustment ?? 0),
  signature_margin_top: millimetersFromPixels(props.settings?.attendance_device_options?.reporting?.signature?.margin_top ?? 160),
  signature_margin_left: millimetersFromPixels(props.settings?.attendance_device_options?.reporting?.signature?.margin_left ?? 96),
  cbc_signature_margin_top: millimetersFromPixels(props.settings?.attendance_device_options?.reporting?.cbc?.signature?.margin_top ?? 14),
  machine_info_line: '',
  machine_info_line_enabled: true,
  machine_info_by_category: buildMachineInfoCategories(props.settings?.attendance_device_options?.reporting?.machine_info_by_category ?? {}),
  reporting_departments: buildReportingDepartments(props.reportingDepartments),
  pathologist_name: props.settings?.pathologist_name ?? '',
  pathologist_designation: props.settings?.pathologist_designation ?? '',
  technologist_name: props.settings?.attendance_device_options?.reporting?.identity?.technologist_name ?? '',
  technologist_designation: props.settings?.attendance_device_options?.reporting?.identity?.technologist_designation ?? '',
  sample_collected_by_name: props.settings?.attendance_device_options?.reporting?.identity?.sample_collected_by_name ?? '',
  sample_collected_by_designation: props.settings?.attendance_device_options?.reporting?.identity?.sample_collected_by_designation ?? '',
  technologist_signature: null,
  sample_collected_by_signature: null,
  pathologist_signature: null,
  remove_technologist_signature: false,
  remove_sample_collected_by_signature: false,
  remove_pathologist_signature: false,
});

const areAllMachineInfoCategoriesEnabled = computed(() => {
  const categories = visibleMachineInfoCategories.value.map(([, config]) => config);
  return categories.length > 0 && categories.every((config) => config.enabled);
});

const visibleMachineInfoCategories = computed(() => Object.entries(form.machine_info_by_category ?? {})
  .filter(([category]) => {
    const normalized = category.toLowerCase().replace(/[^a-z0-9]/g, '');
    return !/(xray|radiolog|ultrasound|ultrason|sonograph|sonogram)/.test(normalized);
  }));

const setAllMachineInfoCategoriesEnabled = (enabled) => {
  visibleMachineInfoCategories.value.forEach(([, config]) => {
    config.enabled = enabled;
  });
};

const resizeTextarea = (event) => {
  const textarea = event?.target;
  if (!textarea) return;

  textarea.style.height = 'auto';
  textarea.style.height = `${textarea.scrollHeight}px`;
};

onMounted(() => {
  nextTick(() => {
    document.querySelectorAll('textarea[data-auto-grow]').forEach((textarea) => {
      resizeTextarea({ target: textarea });
    });
  });
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

const reportingDepartmentSignaturePreview = reactive(Object.fromEntries(
  reportingDepartmentOptions.map(({ key }) => [
    key,
    Object.fromEntries(departmentSignatureFields.map(({ key: field }) => [
      field,
      toPreviewUrl(props.reportingDepartments?.[key]?.[`${field}_preview_url`] ?? ''),
    ])),
  ])
));

const setDepartmentSignatureFile = (event, department, field) => {
  const file = event?.target?.files?.[0] ?? null;
  form.reporting_departments[department][field] = file;
  form.reporting_departments[department][`remove_${field}`] = false;

  if (file) {
    reportingDepartmentSignaturePreview[department][field] = URL.createObjectURL(file);
  }
};

const setSignatureFile = (event, field) => {
  const file = event?.target?.files?.[0] ?? null;
  form[field] = file;
  form[`remove_${field}`] = false;

  if (file) {
    signaturePreview[field] = URL.createObjectURL(file);
  }
};

const cancelDepartmentSignature = (department, field) => {
  form.reporting_departments[department][field] = null;
  form.reporting_departments[department][`remove_${field}`] = true;
  reportingDepartmentSignaturePreview[department][field] = '';
  const input = document.getElementById(`${department}_${field}`);
  if (input) input.value = '';
};

const cancelSignature = (field) => {
  form[field] = null;
  form[`remove_${field}`] = true;
  signaturePreview[field] = '';
  const input = document.getElementById(field);
  if (input) input.value = '';
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

const goBack = () => {
  if (typeof window !== 'undefined' && window.history.length > 1) {
    window.history.back();
    return;
  }

  router.visit(route('backend.dashboard'));
};
</script>

<template>
  <BackendLayout>
    <div class="w-full bg-white rounded-md">
      <div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md">
        <h1 class="p-4 text-xl font-bold">{{ pageTitle }}</h1>
        <div class="p-4">
          <button
            type="button"
            @click="goBack"
            class="px-3 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
          >
            Back
          </button>
        </div>
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

        <div class="mt-4 rounded-md border border-slate-200 bg-slate-50 p-4">
          <div class="mb-3 flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-slate-800">Main Category-wise Machine Information</h3>
            <label class="inline-flex shrink-0 items-center gap-2 text-sm text-slate-700">
              <input
                type="checkbox"
                :checked="areAllMachineInfoCategoriesEnabled"
                :disabled="!Object.keys(form.machine_info_by_category ?? {}).length"
                class="h-4 w-4"
                @change="setAllMachineInfoCategoriesEnabled($event.target.checked)"
              />
              <span>{{ areAllMachineInfoCategoriesEnabled ? 'All On' : 'All Off' }}</span>
            </label>
          </div>
          <div v-if="visibleMachineInfoCategories.length" class="space-y-3">
            <div
              v-for="([category, config]) in visibleMachineInfoCategories"
              :key="category"
              class="rounded-md border border-slate-200 bg-white p-3"
            >
              <div class="mb-2 flex items-center justify-between gap-3">
                <span class="text-sm font-semibold text-slate-700">{{ category }}</span>
                <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                  <input v-model="form.machine_info_by_category[category].enabled" type="checkbox" class="h-4 w-4" />
                  Show this line
                </label>
              </div>
              <textarea
                v-model="form.machine_info_by_category[category].text"
                data-auto-grow
                rows="1"
                @input="resizeTextarea"
                class="block w-full resize-none overflow-hidden rounded-md border-slate-300 p-2 text-sm shadow-sm"
                :placeholder="'Machine info for ' + category"
              ></textarea>
            </div>
          </div>
          <p v-else class="text-xs text-slate-500">X-ray and Ultrasound machine information is configured in their report sections above.</p>
          <p class="mt-2 text-xs text-slate-500">Other active main categories get their own machine information line automatically.</p>
        </div>

        <div class="mt-4 flex flex-col gap-4">
          <div class="order-2 grid grid-cols-1 gap-4">
          <div class="mt-2 rounded-md border border-slate-200 bg-slate-50 p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div>
                <InputLabel for="pathologist_name" value="Pathologist Name" />
                <textarea
                  id="pathologist_name"
                  v-model="form.pathologist_name"
                  data-auto-grow
                  rows="1"
                  @input="resizeTextarea"
                  class="block w-full resize-none overflow-hidden rounded-md border-slate-300 p-2 text-sm shadow-sm"
                  placeholder="Dr. Name"
                ></textarea>
                <InputError class="mt-2" :message="form.errors.pathologist_name" />
              </div>
              <div>
                <InputLabel for="pathologist_designation" value="Pathologist Designation" />
                <textarea
                  id="pathologist_designation"
                  v-model="form.pathologist_designation"
                  data-auto-grow
                  rows="1"
                  @input="resizeTextarea"
                  class="block w-full resize-none overflow-hidden rounded-md border-slate-300 p-2 text-sm shadow-sm"
                  placeholder="Senior Consultant Pathologist"
                ></textarea>
                <InputError class="mt-2" :message="form.errors.pathologist_designation" />
              </div>
              <div>
                <InputLabel for="technologist_name" value="Technologist Name" />
                <textarea
                  id="technologist_name"
                  v-model="form.technologist_name"
                  data-auto-grow
                  rows="1"
                  @input="resizeTextarea"
                  class="block w-full resize-none overflow-hidden rounded-md border-slate-300 p-2 text-sm shadow-sm"
                  placeholder="Technologist Name"
                ></textarea>
                <InputError class="mt-2" :message="form.errors.technologist_name" />
              </div>
              <div>
                <InputLabel for="technologist_designation" value="Technologist Designation" />
                <textarea
                  id="technologist_designation"
                  v-model="form.technologist_designation"
                  data-auto-grow
                  rows="1"
                  @input="resizeTextarea"
                  class="block w-full resize-none overflow-hidden rounded-md border-slate-300 p-2 text-sm shadow-sm"
                  placeholder="Designation"
                ></textarea>
                <InputError class="mt-2" :message="form.errors.technologist_designation" />
              </div>
              <div>
                <InputLabel for="sample_collected_by_name" value="Sample Collected By Name" />
                <textarea
                  id="sample_collected_by_name"
                  v-model="form.sample_collected_by_name"
                  data-auto-grow
                  rows="1"
                  @input="resizeTextarea"
                  class="block w-full resize-none overflow-hidden rounded-md border-slate-300 p-2 text-sm shadow-sm"
                  placeholder="Name"
                ></textarea>
                <InputError class="mt-2" :message="form.errors.sample_collected_by_name" />
              </div>
              <div>
                <InputLabel for="sample_collected_by_designation" value="Sample Collected By Designation" />
                <textarea
                  id="sample_collected_by_designation"
                  v-model="form.sample_collected_by_designation"
                  data-auto-grow
                  rows="1"
                  @input="resizeTextarea"
                  class="block w-full resize-none overflow-hidden rounded-md border-slate-300 p-2 text-sm shadow-sm"
                  placeholder="Designation"
                ></textarea>
                <InputError class="mt-2" :message="form.errors.sample_collected_by_designation" />
              </div>
            </div>
            <div class="mt-4 grid grid-cols-1 items-start gap-4 sm:grid-cols-2 xl:grid-cols-3">
              <div>
                <InputLabel for="technologist_signature" value="Technologist Signature" />
                <div v-if="signaturePreview.technologist_signature" class="mb-2 mt-2">
                  <div class="relative inline-block">
                    <img :src="signaturePreview.technologist_signature" alt="Technologist signature" class="h-16 rounded border" @error="() => handlePreviewError('technologist_signature')" />
                    <button type="button" class="absolute -right-2 -top-2 rounded-full bg-red-600 px-2 py-1 text-xs font-bold text-white shadow hover:bg-red-700" aria-label="Cancel technologist signature" @click="cancelSignature('technologist_signature')">&times;</button>
                  </div>
                </div>
                <input
                  id="technologist_signature"
                  type="file"
                  accept="image/*"
                  class="block w-full rounded-md border border-emerald-600 bg-emerald-600 p-2 text-sm text-white shadow-sm file:mr-3 file:rounded file:border-0 file:bg-emerald-700 file:px-3 file:py-1 file:text-white"
                  @change="(event) => setSignatureFile(event, 'technologist_signature')"
                />
                <InputError class="mt-2" :message="form.errors.technologist_signature" />
              </div>

              <div>
                <InputLabel for="sample_collected_by_signature" value="Sample Collected By Signature" />
                <div v-if="signaturePreview.sample_collected_by_signature" class="mb-2 mt-2">
                  <div class="relative inline-block">
                    <img :src="signaturePreview.sample_collected_by_signature" alt="Sample collected by signature" class="h-16 rounded border" @error="() => handlePreviewError('sample_collected_by_signature')" />
                    <button type="button" class="absolute -right-2 -top-2 rounded-full bg-red-600 px-2 py-1 text-xs font-bold text-white shadow hover:bg-red-700" aria-label="Cancel sample collected by signature" @click="cancelSignature('sample_collected_by_signature')">&times;</button>
                  </div>
                </div>
                <input
                  id="sample_collected_by_signature"
                  type="file"
                  accept="image/*"
                  class="block w-full rounded-md border border-emerald-600 bg-emerald-600 p-2 text-sm text-white shadow-sm file:mr-3 file:rounded file:border-0 file:bg-emerald-700 file:px-3 file:py-1 file:text-white"
                  @change="(event) => setSignatureFile(event, 'sample_collected_by_signature')"
                />
                <InputError class="mt-2" :message="form.errors.sample_collected_by_signature" />
              </div>

              <div>
                <InputLabel for="pathologist_signature" value="Pathologist Signature" />
                <div v-if="signaturePreview.pathologist_signature" class="mb-2 mt-2">
                  <div class="relative inline-block">
                    <img :src="signaturePreview.pathologist_signature" alt="Pathologist signature" class="h-16 rounded border" @error="() => handlePreviewError('pathologist_signature')" />
                    <button type="button" class="absolute -right-2 -top-2 rounded-full bg-red-600 px-2 py-1 text-xs font-bold text-white shadow hover:bg-red-700" aria-label="Cancel pathologist signature" @click="cancelSignature('pathologist_signature')">&times;</button>
                  </div>
                </div>
                <input
                  id="pathologist_signature"
                  type="file"
                  accept="image/*"
                  class="block w-full rounded-md border border-emerald-600 bg-emerald-600 p-2 text-sm text-white shadow-sm file:mr-3 file:rounded file:border-0 file:bg-emerald-700 file:px-3 file:py-1 file:text-white"
                  @change="(event) => setSignatureFile(event, 'pathologist_signature')"
                />
                <InputError class="mt-2" :message="form.errors.pathologist_signature" />
              </div>
            </div>
          </div>

          <div
            v-for="department in reportingDepartmentOptions"
            :key="department.key"
            class="mt-2 rounded-md border border-slate-200 bg-slate-50 p-4"
          >
            <h3 class="mb-3 text-sm font-semibold text-slate-800">{{ department.label }} Report Information</h3>
            <div class="mb-4 rounded-md border border-slate-200 bg-white p-3">
              <div class="mb-2 flex items-center justify-between gap-3">
                <span class="text-sm font-semibold text-slate-700">Machine Information</span>
                <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                  <input v-model="form.reporting_departments[department.key].machine_info_line_enabled" type="checkbox" class="h-4 w-4" />
                  Show this line
                </label>
              </div>
              <textarea
                v-model="form.reporting_departments[department.key].machine_info_line"
                data-auto-grow
                rows="1"
                @input="resizeTextarea"
                class="block w-full resize-none overflow-hidden rounded-md border-slate-300 p-2 text-sm shadow-sm"
                :placeholder="'Machine/report information for ' + department.label"
              ></textarea>
              <InputError class="mt-2" :message="form.errors[`reporting_departments.${department.key}.machine_info_line`]" />
            </div>

            <div class="mb-4 flex flex-wrap items-center gap-3">
              <button
                type="button"
                class="rounded px-2 py-1 text-sm font-medium text-white transition disabled:cursor-wait"
                :class="reportLayoutMode[department.key] === 'full_page_pad'
                  ? 'bg-emerald-600 opacity-100 shadow'
                  : 'bg-emerald-600 opacity-45'"
                :disabled="layoutModeSaving[department.key]"
                @click="setReportLayoutMode(department.key, 'full_page_pad')"
              >
                Full Page Institution Pad
              </button>
              <label class="relative inline-flex cursor-pointer items-center" :aria-label="`${department.label} report layout settings`">
                <input
                  type="checkbox"
                  class="peer sr-only"
                  :disabled="layoutModeSaving[department.key]"
                  :checked="reportLayoutMode[department.key] === 'header_footer'"
                  @change="setReportLayoutMode(department.key, $event.target.checked ? 'header_footer' : 'full_page_pad')"
                />
                <span class="relative h-7 w-14 rounded-full border border-green-700 bg-green-600 shadow-inner transition-colors">
                  <span
                    class="absolute left-1 top-1 h-5 w-5 rounded-full bg-white shadow transition-transform"
                    :class="reportLayoutMode[department.key] === 'header_footer' ? 'translate-x-7' : 'translate-x-0'"
                  ></span>
                </span>
              </label>
              <button
                type="button"
                class="rounded px-2 py-1 text-sm font-medium text-white transition disabled:cursor-wait"
                :class="reportLayoutMode[department.key] === 'header_footer'
                  ? 'bg-[#319b9d] opacity-100 shadow'
                  : 'bg-[#319b9d] opacity-45'"
                :disabled="layoutModeSaving[department.key]"
                @click="setReportLayoutMode(department.key, 'header_footer')"
              >
                Show Header / Footer
              </button>
              <p v-if="!fullPagePadAvailable" class="text-xs text-amber-700">Configure a full-page pad in Invoice Design first.</p>
            </div>

            <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
              <template v-for="field in departmentNumberLayoutFields" :key="`${department.key}.${field.group}.${field.key}`">
              <div
                v-if="!['full_page_pad_content_offset', 'page_margin_top', 'header_height', 'footer_height', 'footer_content_offset'].includes(field.key)
                  || (reportLayoutMode[department.key] === 'full_page_pad'
                    ? ['full_page_pad_content_offset', 'page_margin_top', 'footer_content_offset'].includes(field.key) && form.reporting_departments[department.key].full_page_institution_pad
                    : ['header_height', 'footer_height', 'footer_content_offset'].includes(field.key) && form.reporting_departments[department.key].show_header_footer)"
              >
                <InputLabel :for="`${department.key}_${field.group}_${field.key}`" :value="field.label" />
                <div class="flex items-center gap-2">
                  <input
                    :id="`${department.key}_${field.group}_${field.key}`"
                    v-model.number="form.reporting_departments[department.key][field.group][field.key]"
                    type="range"
                    :min="field.min"
                    :max="field.max"
                    :step="field.step ?? 1"
                    class="min-w-0 flex-1"
                  />
                  <input
                    v-model.number="form.reporting_departments[department.key][field.group][field.key]"
                    type="number"
                    :min="field.min"
                    :max="field.max"
                    :step="field.step ?? 1"
                    :aria-label="`${department.label} ${field.label}`"
                    class="block w-24 shrink-0 rounded-md border-slate-300 p-2 text-sm shadow-sm"
                  />
                </div>
                <InputError
                  class="mt-1"
                  :message="form.errors[`reporting_departments.${department.key}.${field.group}.${field.key}`]"
                />
              </div>
              </template>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div v-for="field in departmentIdentityFields" :key="field.key">
                <InputLabel :for="`${department.key}_${field.key}`" :value="field.label" />
                <textarea
                  :id="`${department.key}_${field.key}`"
                  v-model="form.reporting_departments[department.key][field.key]"
                  data-auto-grow
                  rows="1"
                  @input="resizeTextarea"
                  class="block w-full resize-none overflow-hidden rounded-md border-slate-300 p-2 text-sm shadow-sm"
                  :placeholder="field.placeholder"
                ></textarea>
                <InputError class="mt-2" :message="form.errors[`reporting_departments.${department.key}.${field.key}`]" />
              </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
              <div v-for="field in departmentSignatureFields" :key="field.key">
                <InputLabel :for="`${department.key}_${field.key}`" :value="field.label" />
                <div v-if="reportingDepartmentSignaturePreview[department.key][field.key]" class="mb-2 mt-2">
                  <div class="relative inline-block">
                    <img
                      :src="reportingDepartmentSignaturePreview[department.key][field.key]"
                      :alt="`${department.label} ${field.label}`"
                      class="h-16 rounded border"
                    />
                    <button
                      type="button"
                      class="absolute -right-2 -top-2 rounded-full bg-red-600 px-2 py-1 text-xs font-bold text-white shadow hover:bg-red-700"
                      :aria-label="`Cancel ${department.label} ${field.label}`"
                      @click="cancelDepartmentSignature(department.key, field.key)"
                    >
                      &times;
                    </button>
                  </div>
                </div>
                <input
                  :id="`${department.key}_${field.key}`"
                  type="file"
                  accept="image/*"
                  class="block w-full rounded-md border border-emerald-600 bg-emerald-600 p-2 text-sm text-white shadow-sm file:mr-3 file:rounded file:border-0 file:bg-emerald-700 file:px-3 file:py-1 file:text-white"
                  @change="(event) => setDepartmentSignatureFile(event, department.key, field.key)"
                />
                <InputError class="mt-2" :message="form.errors[`reporting_departments.${department.key}.${field.key}`]" />
              </div>
            </div>
          </div>

        </div>

          <div class="order-1">
        <h3 class="mt-6 border-b border-slate-200 pb-2 text-sm font-semibold text-slate-800">Pathology Report Information</h3>

        <div class="mt-4 rounded-md border border-slate-200 bg-slate-50 p-4">
          <div class="flex flex-wrap items-center gap-3">
            <button
              type="button"
              class="rounded px-2 py-1 text-sm font-medium text-white transition disabled:cursor-wait"
              :class="reportLayoutMode.default === 'full_page_pad'
                ? 'bg-emerald-600 opacity-100 shadow'
                : 'bg-emerald-600 opacity-45'"
              :disabled="layoutModeSaving.default"
              @click="setReportLayoutMode('default', 'full_page_pad')"
            >
              Full Page Institution Pad
            </button>
            <label class="relative inline-flex cursor-pointer items-center" aria-label="Default report layout settings">
              <input
                type="checkbox"
                class="peer sr-only"
                :disabled="layoutModeSaving.default"
                :checked="reportLayoutMode.default === 'header_footer'"
                @change="setReportLayoutMode('default', $event.target.checked ? 'header_footer' : 'full_page_pad')"
              />
              <span class="relative h-7 w-14 rounded-full border border-green-700 bg-green-600 shadow-inner transition-colors">
                <span
                  class="absolute left-1 top-1 h-5 w-5 rounded-full bg-white shadow transition-transform"
                  :class="reportLayoutMode.default === 'header_footer' ? 'translate-x-7' : 'translate-x-0'"
                ></span>
              </span>
            </label>
            <button
              type="button"
              class="rounded px-2 py-1 text-sm font-medium text-white transition disabled:cursor-wait"
              :class="reportLayoutMode.default === 'header_footer'
                ? 'bg-[#319b9d] opacity-100 shadow'
                : 'bg-[#319b9d] opacity-45'"
              :disabled="layoutModeSaving.default"
              @click="setReportLayoutMode('default', 'header_footer')"
            >
              Show Header / Footer
            </button>
            <p v-if="!fullPagePadAvailable" class="text-xs text-amber-700">Configure a full-page pad in Invoice Design first.</p>
          </div>
          <div
            v-if="reportLayoutMode.default === 'full_page_pad'"
            class="mt-4"
          >
            <div
              v-if="fullPagePadAvailable"
              class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2 xl:grid-cols-4"
          >
            <div>
              <InputLabel for="report_full_page_pad_content_offset" value="Text Start Position (mm)" />
              <div class="flex items-center gap-2">
                <input id="report_full_page_pad_content_offset" v-model.number="form.report_full_page_pad_content_offset" type="range" min="0" max="100" step="1" class="min-w-0 flex-1" />
                <input v-model.number="form.report_full_page_pad_content_offset" type="number" min="0" max="100" step="1" aria-label="Report text start position in millimeters" class="block w-24 shrink-0 rounded-md border-slate-300 p-2 text-sm shadow-sm" />
              </div>
              <InputError class="mt-1" :message="form.errors.report_full_page_pad_content_offset" />
            </div>
            <div>
              <InputLabel for="report_margin_top" value="Print Top Margin (mm)" />
              <div class="flex items-center gap-2">
                <input id="report_margin_top" v-model.number="form.report_margin_top" type="range" min="0" max="529" step="1" class="min-w-0 flex-1" />
                <input v-model.number="form.report_margin_top" type="number" min="0" max="529" step="1" aria-label="Print top margin in millimeters" class="block w-24 shrink-0 rounded-md border-slate-300 p-2 text-sm shadow-sm" />
              </div>
              <InputError class="mt-2" :message="form.errors.report_margin_top" />
            </div>
            <div>
              <InputLabel for="report_footer_content_offset_pad" value="Footer Content Position (mm)" />
              <div class="flex items-center gap-2">
                <input id="report_footer_content_offset_pad" v-model.number="form.report_footer_content_offset" type="range" min="-50" max="50" step="1" class="min-w-0 flex-1" />
                <input v-model.number="form.report_footer_content_offset" type="number" min="-50" max="50" step="1" aria-label="Footer content position in millimeters" class="block w-24 shrink-0 rounded-md border-slate-300 p-2 text-sm shadow-sm" />
              </div>
              <InputError class="mt-1" :message="form.errors.report_footer_content_offset" />
            </div>
          </div>
          </div>
          <div
            v-if="reportLayoutMode.default === 'header_footer'"
            class="mt-4 grid grid-cols-1 items-start gap-4 border-t border-slate-200 pt-4 sm:grid-cols-2 xl:grid-cols-4"
          >
            <div>
              <InputLabel for="report_header_height" value="Header Height (mm)" />
              <div class="flex items-center gap-2">
                <input id="report_header_height" v-model.number="form.report_header_height" type="range" min="0" max="265" step="1" class="min-w-0 flex-1" />
                <input v-model.number="form.report_header_height" type="number" min="0" max="265" step="1" aria-label="Header height in millimeters" class="block w-24 shrink-0 rounded-md border-slate-300 p-2 text-sm shadow-sm" />
              </div>
              <InputError class="mt-2" :message="form.errors.report_header_height" />
            </div>
            <div>
              <InputLabel for="report_footer_height" value="Footer Height (mm)" />
              <div class="flex items-center gap-2">
                <input id="report_footer_height" v-model.number="form.report_footer_height" type="range" min="0" max="265" step="1" class="min-w-0 flex-1" />
                <input v-model.number="form.report_footer_height" type="number" min="0" max="265" step="1" aria-label="Footer height in millimeters" class="block w-24 shrink-0 rounded-md border-slate-300 p-2 text-sm shadow-sm" />
              </div>
              <InputError class="mt-2" :message="form.errors.report_footer_height" />
            </div>
            <div>
              <InputLabel for="report_footer_content_offset" value="Footer Content Position (mm)" />
              <div class="flex items-center gap-2">
                <input id="report_footer_content_offset" v-model.number="form.report_footer_content_offset" type="range" min="-50" max="50" step="1" class="min-w-0 flex-1" />
                <input v-model.number="form.report_footer_content_offset" type="number" min="-50" max="50" step="1" aria-label="Report footer content position in millimeters" class="block w-24 shrink-0 rounded-md border-slate-300 p-2 text-sm shadow-sm" />
              </div>
              <InputError class="mt-1" :message="form.errors.report_footer_content_offset" />
            </div>
          </div>

          <div class="mt-4 grid grid-cols-1 items-start gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div>
              <InputLabel for="report_margin_left" value="Print Left Margin (mm)" />
              <div class="flex items-center gap-2">
                <input id="report_margin_left" v-model.number="form.report_margin_left" type="range" min="0" max="100" step="1" class="min-w-0 flex-1" />
                <input v-model.number="form.report_margin_left" type="number" min="0" max="100" step="1" aria-label="Print left margin in millimeters" class="block w-24 shrink-0 rounded-md border-slate-300 p-2 text-sm shadow-sm" />
              </div>
              <InputError class="mt-1" :message="form.errors.report_margin_left" />
            </div>
            <div>
              <InputLabel for="report_margin_right" value="Print Right Margin (mm)" />
              <div class="flex items-center gap-2">
                <input id="report_margin_right" v-model.number="form.report_margin_right" type="range" min="0" max="100" step="1" class="min-w-0 flex-1" />
                <input v-model.number="form.report_margin_right" type="number" min="0" max="100" step="1" aria-label="Print right margin in millimeters" class="block w-24 shrink-0 rounded-md border-slate-300 p-2 text-sm shadow-sm" />
              </div>
              <InputError class="mt-1" :message="form.errors.report_margin_right" />
            </div>
            <div>
              <InputLabel for="report_font_size_adjustment" value="Report Font Size Adjustment (mm)" />
              <div class="flex items-center gap-2">
                <input id="report_font_size_adjustment" v-model.number="form.report_font_size_adjustment" type="range" min="0" max="3" step="1" class="min-w-0 flex-1" />
                <input v-model.number="form.report_font_size_adjustment" type="number" min="0" max="3" step="1" aria-label="Report font size increase in millimeters" class="block w-24 shrink-0 rounded-md border-slate-300 p-2 text-sm shadow-sm" />
              </div>
              <InputError class="mt-1" :message="form.errors.report_font_size_adjustment" />
            </div>
            <div>
              <InputLabel for="report_margin_bottom" value="Print Bottom Margin (mm)" />
              <div class="flex items-center gap-2">
                <input id="report_margin_bottom" v-model.number="form.report_margin_bottom" type="range" min="0" max="529" step="1" class="min-w-0 flex-1" />
                <input v-model.number="form.report_margin_bottom" type="number" min="0" max="529" step="1" aria-label="Print bottom margin in millimeters" class="block w-24 shrink-0 rounded-md border-slate-300 p-2 text-sm shadow-sm" />
              </div>
              <InputError class="mt-2" :message="form.errors.report_margin_bottom" />
            </div>
            <div>
              <InputLabel for="signature_margin_top" value="Signature Margin Top (mm)" />
              <div class="flex items-center gap-2">
                <input id="signature_margin_top" v-model.number="form.signature_margin_top" type="range" min="0" max="529" step="1" class="min-w-0 flex-1" />
                <input v-model.number="form.signature_margin_top" type="number" min="0" max="529" step="1" aria-label="Signature margin top in millimeters" class="block w-24 shrink-0 rounded-md border-slate-300 p-2 text-sm shadow-sm" />
              </div>
              <InputError class="mt-2" :message="form.errors.signature_margin_top" />
            </div>
            <div>
              <InputLabel for="cbc_signature_margin_top" value="CBC Signature Vertical Adjustment (mm)" />
              <div class="flex items-center gap-2">
                <input id="cbc_signature_margin_top" v-model.number="form.cbc_signature_margin_top" type="range" min="-132" max="132" step="1" class="min-w-0 flex-1" />
                <input v-model.number="form.cbc_signature_margin_top" type="number" min="-132" max="132" step="1" aria-label="CBC signature vertical adjustment in millimeters" class="block w-24 shrink-0 rounded-md border-slate-300 p-2 text-sm shadow-sm" />
              </div>
              <InputError class="mt-2" :message="form.errors.cbc_signature_margin_top" />
            </div>
            <div>
              <InputLabel for="signature_margin_left" value="Signature Margin Left (mm)" />
              <div class="flex items-center gap-2">
                <input id="signature_margin_left" v-model.number="form.signature_margin_left" type="range" min="0" max="529" step="1" class="min-w-0 flex-1" />
                <input v-model.number="form.signature_margin_left" type="number" min="0" max="529" step="1" aria-label="Signature margin left in millimeters" class="block w-24 shrink-0 rounded-md border-slate-300 p-2 text-sm shadow-sm" />
              </div>
              <InputError class="mt-2" :message="form.errors.signature_margin_left" />
            </div>
          </div>
        </div>

          </div>
        </div>
        <p class="mt-2 text-sm text-gray-600">এগুলো report print layout সামঞ্জস্য করার জন্য — নিরাপদ প্যাডিং হিসেবে প্রয়োগ হবে।</p>

        <div class="flex items-center justify-end mt-6">
          <PrimaryButton type="submit" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
            Save Settings
          </PrimaryButton>
        </div>
      </form>
    </div>
  </BackendLayout>
</template>
