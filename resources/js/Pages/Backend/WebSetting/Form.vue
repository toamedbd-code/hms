<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import eventBus from '@/eventBus.js';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';

const props = defineProps(['websetting', 'id', 'activeSection', 'activeModule', 'singleSectionMode', 'availableTemplates', 'bookingDoctors', 'sidebarMenus']);
const bookingDoctors = props.bookingDoctors ?? [];
const bookingPanelOpen = ref(false);
const page = usePage();
const vatStorageKey = 'backend.websetting.lastVatSettings';
const currentWebSetting = computed(() => {
    const propsValue = page?.props ?? {};
    const pageSetting = propsValue.websetting ?? propsValue.webSetting ?? null;

    if (pageSetting && typeof pageSetting === 'object') {
        return pageSetting;
    }

    if (props.websetting && typeof props.websetting === 'object') {
        return props.websetting;
    }

    return {};
});
const getWebSettingValue = (key, fallback = '') => {
    const source = currentWebSetting.value && typeof currentWebSetting.value === 'object'
        ? currentWebSetting.value
        : null;

    const sourceValue = source?.[key];

    if (sourceValue === null || sourceValue === undefined || sourceValue === '') {
        const propValue = props.websetting && typeof props.websetting === 'object'
            ? props.websetting[key]
            : undefined;

        return propValue ?? fallback;
    }

    return sourceValue ?? fallback;
};
const sidebarMenus = ref(props.sidebarMenus ?? []);
const searchQuery = ref('');
const settingsSections = [
    { key: 'general', label: 'General Setting', hint: 'Hospital profile, logo, language, currency' },
    { key: 'cms', label: 'CMS Setting', hint: 'Website hero, about, doctors and CTA content' },
    { key: 'prefix', label: 'Prefix Setting', hint: 'All document and bill numbering prefixes' },
    { key: 'sms', label: 'SMS Setting', hint: 'Gateway credentials and Bulk SMS access' },
    { key: 'sidebar', label: 'Sidebar Menu Order', hint: 'Reorder top-level sidebar items by moving them up or down' },
    { key: 'module', label: 'Module Setting', hint: 'Attendance, pathology, payroll, reporting integrations', hidden: true },
    { key: 'other', label: 'Other Setting', hint: 'Theme, panel visibility and extra options' },
];
const filteredSidebarMenus = computed(() => {
    const query = String(searchQuery.value || '').trim().toLowerCase();
    if (query === '') {
        return sidebarMenus.value;
    }
    return sidebarMenus.value.filter((menu) => String(menu.name || '').toLowerCase().includes(query));
});

const manualSidebarPositions = reactive({});
const refreshSidebarPositions = () => {
    sidebarMenus.value.forEach((menu, index) => {
        manualSidebarPositions[menu.id] = String(index + 1);
    });
};

const getSidebarMenuPosition = (menuId) => {
    const index = sidebarMenus.value.findIndex((item) => Number(item.id) === Number(menuId));
    return index >= 0 ? index + 1 : 0;
};

const setSidebarMenuPosition = (menuId, newPosition) => {
    const position = Number(newPosition);
    if (!Number.isFinite(position) || position < 1) {
        manualSidebarPositions[menuId] = String(getSidebarMenuPosition(menuId) || 1);
        return;
    }

    const currentIndex = sidebarMenus.value.findIndex((item) => Number(item.id) === Number(menuId));
    if (currentIndex === -1) {
        return;
    }

    const targetIndex = Math.min(Math.max(position - 1, 0), sidebarMenus.value.length - 1);
    if (targetIndex === currentIndex) {
        manualSidebarPositions[menuId] = String(currentIndex + 1);
        return;
    }

    const items = [...sidebarMenus.value];
    const [moved] = items.splice(currentIndex, 1);
    items.splice(targetIndex, 0, moved);
    sidebarMenus.value = items;
    refreshSidebarPositions();
};

const updateManualSidebarPosition = (menuId, event) => {
    manualSidebarPositions[menuId] = event.target.value;
};

const settingsSectionStorageKey = 'backend.websetting.activeSection';
const authPermissions = computed(() => {
    const permissions = page.props?.auth?.permissions ?? [];
    return Array.isArray(permissions) ? permissions : [];
});

const hasPermission = (permissionName) => authPermissions.value.includes(permissionName);

const visibleSettingsSections = computed(() => {
    const canManageAllWebSettings = hasPermission('websetting-add');
    const canManageGeneralSettings = canManageAllWebSettings || hasPermission('general-setting-add');
    const canManageCmsSettings = canManageAllWebSettings || hasPermission('cms-setting');
    const canManageSidebarSettings = canManageAllWebSettings || canManageGeneralSettings || canManageCmsSettings || hasPermission('sidebar-setting');

    return settingsSections.filter((section) => {
        if (section.hidden) {
            return false;
        }

        if (section.key === 'general') {
            return canManageGeneralSettings;
        }

        if (section.key === 'cms') {
            return canManageCmsSettings;
        }

        if (section.key === 'sidebar') {
            return canManageSidebarSettings;
        }

        return canManageAllWebSettings;
    });
});

const allowedSectionKeys = computed(() => visibleSettingsSections.value.map((section) => section.key));
const routableSectionKeys = computed(() => settingsSections.map((section) => section.key));

const normalizeSection = (section) => {
    const allowedKeys = routableSectionKeys.value;
    const fallbackSection = allowedKeys[0] ?? 'general';
    return allowedKeys.includes(section) ? section : fallbackSection;
};

const isSingleSectionMode = computed(() => Boolean(props.singleSectionMode));

const getSectionMeta = (section) => {
    const normalizedSection = normalizeSection(section);
    return visibleSettingsSections.value.find((item) => item.key === normalizedSection)
        ?? settingsSections.find((item) => item.key === normalizedSection)
        ?? settingsSections[0];
};

const getInitialSettingsSection = () => {
    const sectionFromProps = normalizeSection(String(props.activeSection ?? '').trim());
    if (String(props.activeSection ?? '').trim() !== '') {
        return sectionFromProps;
    }

    if (typeof window === 'undefined') {
        return 'general';
    }

    const params = new URLSearchParams(window.location.search);
    const sectionFromQuery = normalizeSection(params.get('section'));

    if (sectionFromQuery !== 'general' || params.get('section') === 'general') {
        return sectionFromQuery;
    }

    const sectionFromStorage = normalizeSection(window.localStorage.getItem(settingsSectionStorageKey));
    return sectionFromStorage;
};

const activeSettingsSection = ref(getInitialSettingsSection());
const showSectionToast = ref(false);
const sectionToastMessage = ref('');
let sectionToastTimer = null;

const moveSidebarItem = (index, direction) => {
    const newIndex = direction === 'up' ? index - 1 : index + 1;
    if (newIndex < 0 || newIndex >= sidebarMenus.value.length) {
        return;
    }
    const items = [...sidebarMenus.value];
    const temp = items[newIndex];
    items[newIndex] = items[index];
    items[index] = temp;
    sidebarMenus.value = items;
};

const moveSidebarMenuUp = (index) => moveSidebarItem(index, 'up');
const moveSidebarMenuDown = (index) => moveSidebarItem(index, 'down');

const goBack = () => {
    if (typeof window !== 'undefined' && window.history.length > 1) {
        window.history.back();
        return;
    }

    router.visit(route('backend.dashboard'));
};

const setActiveSettingsSection = (section) => {
    if (isSingleSectionMode.value) {
        return;
    }

    const normalizedSection = normalizeSection(section);
    if (activeSettingsSection.value === normalizedSection) {
        return;
    }

    activeSettingsSection.value = normalizedSection;

    if (typeof window === 'undefined') {
        return;
    }

    const sectionMeta = getSectionMeta(normalizedSection);
    sectionToastMessage.value = `${sectionMeta.label} selected`;
    showSectionToast.value = true;

    if (sectionToastTimer) {
        window.clearTimeout(sectionToastTimer);
    }

    sectionToastTimer = window.setTimeout(() => {
        showSectionToast.value = false;
    }, 1600);
};

const sectionButtonClass = (section) => {
    const isActive = activeSettingsSection.value === section;
    return isActive
        ? 'border-sky-600 bg-sky-600 text-white shadow-sm'
        : 'border-slate-300 bg-white text-slate-700 hover:border-sky-300 hover:text-sky-700';
};

const parseDeviceOptions = (rawOptions) => {
    const defaultWebhookUrl = typeof window !== 'undefined'
        ? `${window.location.origin}/api/attendance/device/webhook`
        : '/api/attendance/device/webhook';
    const defaultPathologyWebhookUrl = typeof window !== 'undefined'
        ? `${window.location.origin}/api/pathology/machine/webhook`
        : '/api/pathology/machine/webhook';

    const defaults = {
        modules: {
            fingerprint: true,
            face_attendance: true,
            leave: true,
            duty_roster: true,
            salary_sheet: true,
        },
        payroll: {
            salary_sheet: {
                late_fee_per_late: 0,
                overtime_multiplier: 1,
                late_grace_days: 3,
                late_deduction_rate: 0.25,
                late_highlight_limit: 3,
                unpaid_highlight_limit: 2,
                waive_short_late: false,
                short_late_limit_minutes: 15,
            },
        },
        reporting: {
            signature: {
                margin_top: 160,
                margin_left: 96,
            },
            layout: {
                page_margin_top: 0,
            },
        },
        sync_mode: 'realtime',
        sync_interval_minutes: 5,
        zkteco: {
            enabled: true,
            model_hint: 'ZKTeco K40/uFace/F18',
            protocol: 'push_webhook',
        },
        webhook: {
            endpoint_url: defaultWebhookUrl,
            signature_algorithm: 'sha256',
            signature_header: 'X-Device-Signature',
            secret_header: 'X-Device-Secret',
            payload_device_key: 'device_id',
            payload_employee_key: 'employee_code',
            payload_type_key: 'type',
            payload_timestamp_key: 'timestamp',
        },
        pathology: {
            enabled: false,
            communication_mode: 'hl7_mllp',
            webhook_endpoint_url: defaultPathologyWebhookUrl,
            ack_response_mode: 'auto',
            ack_success_text: 'ACK',
            ack_failure_text: 'NACK',
            save_raw_payload: false,
            timeout_seconds: 30,
            retry_limit: 3,
            auto_import_results: true,
            require_acknowledgement: true,
            hematology: {
                enabled: false,
                vendor: '',
                model: '',
                protocol: 'hl7_mllp',
                host: '',
                port: '2575',
                api_url: '',
                device_identifier: '',
            },
            ultrasound: {
                enabled: false,
                vendor: '',
                model: '',
                protocol: 'dicom_mwl',
                host: '',
                port: '104',
                ae_title: 'HMS',
                accession_key: 'accession_no',
            },
            security: {
                tls_enabled: false,
                api_token: '',
                shared_secret: '',
            },
            mapping: {
                sample_id_key: 'sample_id',
                patient_id_key: 'patient_id',
                test_code_key: 'test_code',
                result_value_key: 'result_value',
                result_unit_key: 'unit',
                reference_range_key: 'reference_range',
                result_time_key: 'result_time',
            },
        },
    };

    if (!rawOptions) {
        return defaults;
    }

    try {
        const parsed = typeof rawOptions === 'string' ? JSON.parse(rawOptions) : rawOptions;
        return {
            ...defaults,
            ...parsed,
            modules: {
                ...defaults.modules,
                ...(parsed?.modules ?? {}),
            },
            payroll: {
                ...defaults.payroll,
                ...(parsed?.payroll ?? {}),
                salary_sheet: {
                    ...defaults.payroll.salary_sheet,
                    ...(parsed?.payroll?.salary_sheet ?? {}),
                },
            },
            reporting: {
                ...defaults.reporting,
                ...(parsed?.reporting ?? {}),
                signature: {
                    ...defaults.reporting.signature,
                    ...(parsed?.reporting?.signature ?? {}),
                },
                layout: {
                    ...defaults.reporting.layout,
                    ...(parsed?.reporting?.layout ?? {}),
                },
            },
            zkteco: {
                ...defaults.zkteco,
                ...(parsed?.zkteco ?? {}),
            },
            webhook: {
                ...defaults.webhook,
                ...(parsed?.webhook ?? {}),
            },
            pathology: {
                ...defaults.pathology,
                ...(parsed?.pathology ?? {}),
                hematology: {
                    ...defaults.pathology.hematology,
                    ...(parsed?.pathology?.hematology ?? {}),
                },
                ultrasound: {
                    ...defaults.pathology.ultrasound,
                    ...(parsed?.pathology?.ultrasound ?? {}),
                },
                security: {
                    ...defaults.pathology.security,
                    ...(parsed?.pathology?.security ?? {}),
                },
                mapping: {
                    ...defaults.pathology.mapping,
                    ...(parsed?.pathology?.mapping ?? {}),
                },
            },
        };
    } catch (_) {
        return defaults;
    }
};

const deviceOptions = ref(parseDeviceOptions(props.websetting?.attendance_device_options));
const moduleSections = reactive({
    attendance: true,
    pathology: false,
    payroll: false,
    reporting: false,
});
const moduleSectionKeys = ['attendance', 'pathology', 'payroll', 'reporting'];
const lockedModuleSection = ref('');
const isModuleSectionLocked = computed(() => lockedModuleSection.value !== '');

const normalizeModuleSection = (moduleKey) => {
    const normalized = String(moduleKey ?? '').trim().toLowerCase();
    return moduleSectionKeys.includes(normalized) ? normalized : '';
};

const setSingleModuleSection = (moduleKey) => {
    const normalized = normalizeModuleSection(moduleKey);
    if (!normalized) {
        return;
    }

    moduleSectionKeys.forEach((key) => {
        moduleSections[key] = key === normalized;
    });
};

const toggleModuleSection = (moduleKey) => {
    const normalized = normalizeModuleSection(moduleKey);
    if (!normalized) {
        return;
    }

    setActiveSettingsSection('module');
    lockedModuleSection.value = '';
    setSingleModuleSection(normalized);
};

const getSelectedModuleSection = () => moduleSectionKeys.find((key) => moduleSections[key]) ?? '';

const applyModuleSelectionFromQuery = () => {
    const moduleFromProps = normalizeModuleSection(props.activeModule);
    if (moduleFromProps) {
        lockedModuleSection.value = moduleFromProps;
        setSingleModuleSection(moduleFromProps);
        return;
    }

    if (typeof window === 'undefined') {
        return;
    }

    const params = new URLSearchParams(window.location.search);
    const selectedModule = normalizeModuleSection(params.get('module'));
    if (!selectedModule) {
        lockedModuleSection.value = '';
        return;
    }

    // Do not force the active settings section to 'module' — keep top-level selection hidden.
    lockedModuleSection.value = selectedModule;
    setSingleModuleSection(selectedModule);
};

const themePreviewTokens = {
    default: { primary: '#64748b', soft: '#f1f5f9', contrast: '#334155', surface: '#ffffff' },
    red: { primary: '#dc2626', soft: '#fee2e2', contrast: '#7f1d1d', surface: '#fff1f2' },
    blue: { primary: '#1d4ed8', soft: '#dbeafe', contrast: '#1e3a8a', surface: '#eff6ff' },
    gray: { primary: '#475569', soft: '#e2e8f0', contrast: '#1e293b', surface: '#f8fafc' },
    emerald: { primary: '#059669', soft: '#d1fae5', contrast: '#065f46', surface: '#ecfdf5' },
    amber: { primary: '#d97706', soft: '#fef3c7', contrast: '#92400e', surface: '#fffbeb' },
    rose: { primary: '#e11d48', soft: '#ffe4e6', contrast: '#9f1239', surface: '#fff1f2' },
    indigo: { primary: '#4f46e5', soft: '#e0e7ff', contrast: '#3730a3', surface: '#eef2ff' },
};

const prettyThemeName = (name) => {
    if (!name) return '';
    if (name === 'amber') return 'Yellow';
    return String(name).charAt(0).toUpperCase() + String(name).slice(1);
};

const themeDescription = (name) => {
    const descriptions = {
        default: 'Neutral clean look with no accent color mood.',
        red: 'Bold and energetic tone for high-attention workflows.',
        blue: 'Calm and clinical tone with clean depth.',
        gray: 'Professional minimal look for focused usage.',
        emerald: 'Fresh healthcare-friendly modern tone.',
        amber: 'Warm and bright golden mood.',
        rose: 'Premium vibrant tone with soft warmth.',
        indigo: 'Rich and elegant deep-focus visual style.',
    };

    return descriptions[name] ?? 'Theme preview';
};

const hexToRgb = (hex) => {
    const value = String(hex || '').replace('#', '').trim();
    if (!/^[0-9a-fA-F]{6}$/.test(value)) {
        return '37 99 235';
    }

    const number = parseInt(value, 16);
    const r = (number >> 16) & 255;
    const g = (number >> 8) & 255;
    const b = number & 255;

    return `${r} ${g} ${b}`;
};

const applyLiveThemePreview = (themeName) => {
    if (typeof document === 'undefined') {
        return;
    }

    const normalized = String(themeName || 'default').trim().toLowerCase();
    const tokens = themePreviewTokens[normalized] ?? themePreviewTokens.default;
    const root = document.documentElement;

    root.style.setProperty('--app-theme-primary', tokens.primary);
    root.style.setProperty('--app-theme-soft', tokens.soft);
    root.style.setProperty('--app-theme-contrast', tokens.contrast);
    root.style.setProperty('--app-theme-surface', tokens.surface);
    root.style.setProperty('--app-theme-primary-rgb', hexToRgb(tokens.primary));

    Object.keys(themePreviewTokens).forEach((key) => root.classList.remove(`theme-${key}`));
    root.classList.add(`theme-${normalized}`);
};

const parseFeaturedDoctorsRows = (rawJson) => {
    if (!rawJson || typeof rawJson !== 'string') {
        return [];
    }

    try {
        const parsed = JSON.parse(rawJson);
        if (!Array.isArray(parsed)) {
            return [];
        }

        return parsed
            .filter((item) => item && typeof item === 'object')
            .map((item) => ({
                name: String(item.name ?? ''),
                specialty: String(item.specialty ?? ''),
                designation: String(item.designation ?? ''),
                phone: String(item.phone ?? ''),
                experience: String(item.experience ?? ''),
                doctor_fee: String(item.doctor_fee ?? ''),
                bio: String(item.bio ?? ''),
                image_url: String(item.image_url ?? ''),
            }));
    } catch (_) {
        return [];
    }
};

const parseSimpleListRows = (rawJson) => {
    if (!rawJson || typeof rawJson !== 'string') {
        return [];
    }

    try {
        const parsed = JSON.parse(rawJson);
        if (!Array.isArray(parsed)) {
            return [];
        }

        return parsed
            .map((item) => ({ label: String(item ?? '') }))
            .filter((item) => item.label.trim() !== '');
    } catch (_) {
        return [];
    }
};

const parseTestimonialsRows = (rawJson) => {
    if (!rawJson || typeof rawJson !== 'string') {
        return [];
    }

    try {
        const parsed = JSON.parse(rawJson);
        if (!Array.isArray(parsed)) {
            return [];
        }

        return parsed
            .filter((item) => item && typeof item === 'object')
            .map((item) => ({
                name: String(item.name ?? ''),
                role: String(item.role ?? ''),
                quote: String(item.quote ?? ''),
            }));
    } catch (_) {
        return [];
    }
};

const getStoredVatValues = () => {
    if (typeof window === 'undefined' || typeof window.localStorage === 'undefined') {
        return null;
    }

    try {
        const raw = window.localStorage.getItem(vatStorageKey);
        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== 'object') {
            return null;
        }

        return parsed;
    } catch (_) {
        return null;
    }
};

const persistVatValuesToStorage = () => {
    if (typeof window === 'undefined' || typeof window.localStorage === 'undefined') {
        return;
    }

    try {
        const normalizedVatPercent = (form.vat_percent === null || form.vat_percent === undefined || form.vat_percent === '')
            ? 0.0
            : form.vat_percent;

        window.localStorage.setItem(vatStorageKey, JSON.stringify({
            vat_enabled: Boolean(form.vat_enabled ?? false),
            vat_percent: normalizedVatPercent,
        }));
    } catch (_) {
        // ignore storage failures
    }
};

const syncVatFieldsFromSettings = (source = null) => {
    const settings = source && typeof source === 'object' ? source : currentWebSetting.value ?? props.websetting ?? null;
    const fallbackSettings = getStoredVatValues();

    const resolvedSettings = {
        ...(fallbackSettings && typeof fallbackSettings === 'object' ? fallbackSettings : {}),
        ...(settings && typeof settings === 'object' ? settings : {}),
    };

    if (!resolvedSettings || typeof resolvedSettings !== 'object') {
        return;
    }

    try {
        if (Object.prototype.hasOwnProperty.call(resolvedSettings, 'vat_enabled')) {
            form.vat_enabled = Boolean(resolvedSettings.vat_enabled);
        }

        if (Object.prototype.hasOwnProperty.call(resolvedSettings, 'vat_percent') && resolvedSettings.vat_percent !== null && resolvedSettings.vat_percent !== '') {
            const parsedVatPercent = Number(resolvedSettings.vat_percent);
            if (Number.isFinite(parsedVatPercent)) {
                form.vat_percent = parsedVatPercent;
            }
        }
    } catch (e) {
        // ignore field sync failures
    }
};

const form = useForm({
    company_name: currentWebSetting.value?.company_name ?? props.websetting?.company_name ?? 'ToaMed',
    company_short_name: props.websetting?.company_short_name ?? 'TM',
    hospital_code: props.websetting?.hospital_code ?? '',
    address: props.websetting?.address ?? props.websetting?.report_title ?? 'Mirpur, Dhaka.',
    phone: props.websetting?.phone ?? '01919592638',
    email: props.websetting?.email ?? 'toamedbd@gmail.com',
    login_banner: props.websetting?.login_banner ?? (page.props?.loginTexts?.banner ?? 'Hospital Management Suite'),
    login_title: props.websetting?.login_title ?? (page.props?.loginTexts?.title ?? 'Welcome Back.'),
    login_subtitle: props.websetting?.login_subtitle ?? (page.props?.loginTexts?.subtitle ?? 'Continue with your secure account and manage all operations with confidence.'),
    logo: null,
    icon: null,
    language: props.websetting?.language ?? 'English',
    date_format: props.websetting?.date_format ?? 'dd-mm-yyyy',
    time_zone: props.websetting?.time_zone ?? '(GMT+06:00) Asia, Dhaka',
    currency: props.websetting?.currency ?? 'BDT',
    currency_symbol: props.websetting?.currency_symbol ?? 'Tk.',
    vat_enabled: Boolean(currentWebSetting.value?.vat_enabled ?? props.websetting?.vat_enabled ?? false),
    vat_percent: currentWebSetting.value?.vat_percent ?? props.websetting?.vat_percent ?? 0.00,
    credit_limit: props.websetting?.credit_limit ?? 10000,
    max_billing_discount_percent: props.websetting?.max_billing_discount_percent ?? 100,
    low_stock_threshold: props.websetting?.low_stock_threshold ?? 10,
    time_format: props.websetting?.time_format ?? '12 Hour',
    mobile_app_api_url: props.websetting?.mobile_app_api_url ?? '',
    mobile_app_primary_color_code: props.websetting?.mobile_app_primary_color_code ?? '444444',
    mobile_app_secondary_color_code: props.websetting?.mobile_app_secondary_color_code ?? 'ffffff',
    mobile_app_logo: null,
    doctor_restriction_mode: Boolean(props.websetting?.doctor_restriction_mode),
    superadmin_visibility: Boolean(props.websetting?.superadmin_visibility),
    patient_panel: Boolean(props.websetting?.patient_panel),
    opd_invoice_header_footer: Boolean(props.websetting?.opd_invoice_header_footer ?? false),
    ipd_invoice_header_footer: Boolean(props.websetting?.ipd_invoice_header_footer ?? false),
    opd_prescription_header_footer: Boolean(props.websetting?.opd_prescription_header_footer ?? false),
    ipd_prescription_header_footer: Boolean(props.websetting?.ipd_prescription_header_footer ?? false),
    scan_type: props.websetting?.scan_type ?? 'Barcode',
    current_theme: props.websetting?.current_theme ?? 'default',
    sms_enabled: Boolean(props.websetting?.sms_enabled),
    sms_api_url: props.websetting?.sms_api_url ?? '',
    sms_api_key: props.websetting?.sms_api_key ?? '',
    sms_sender_id: props.websetting?.sms_sender_id ?? '',
    sms_route: props.websetting?.sms_route ?? '',
    sms_is_unicode: Boolean(props.websetting?.sms_is_unicode),
    sms_additional_params: props.websetting?.sms_additional_params ?? '',
    personal_bkash_number: props.websetting?.personal_bkash_number ?? '',
    personal_nagad_number: props.websetting?.personal_nagad_number ?? '',
    ipd_no_prefix: props.websetting?.ipd_no_prefix ?? 'IPDN',
    opd_no_prefix: props.websetting?.opd_no_prefix ?? 'OPDN',
    ipd_prescription_prefix: props.websetting?.ipd_prescription_prefix ?? 'IPDP',
    opd_prescription_prefix: props.websetting?.opd_prescription_prefix ?? 'OPDP',
    appointment_prefix: props.websetting?.appointment_prefix ?? 'APPN',
    pharmacy_bill_prefix: props.websetting?.pharmacy_bill_prefix ?? 'PHAB',
    billing_bill_prefix: props.websetting?.billing_bill_prefix ?? 'BILL',
    operation_reference_no_prefix: props.websetting?.operation_reference_no_prefix ?? 'OTRN',
    blood_bank_bill_prefix: props.websetting?.blood_bank_bill_prefix ?? 'BLBB',
    ambulance_call_bill_prefix: props.websetting?.ambulance_call_bill_prefix ?? 'AMCB',
    radiology_bill_prefix: props.websetting?.radiology_bill_prefix ?? 'RADB',
    pathology_bill_prefix: props.websetting?.pathology_bill_prefix ?? 'Bill',
    opd_checkup_id_prefix: props.websetting?.opd_checkup_id_prefix ?? 'OCID',
    pharmacy_purchase_no_prefix: props.websetting?.pharmacy_purchase_no_prefix ?? 'PHPN',
    transaction_id_prefix: props.websetting?.transaction_id_prefix ?? 'TRID',
    birth_record_reference_no_prefix: props.websetting?.birth_record_reference_no_prefix ?? 'BRRN',
    death_record_reference_no_prefix: props.websetting?.death_record_reference_no_prefix ?? 'DRRN',
    report_title: props.websetting?.report_title ?? props.websetting?.address ?? 'Mirpur, Dhaka.',
    website_hero_title: props.websetting?.website_hero_title ?? 'Welcome to Toamed Hospital',
    website_hero_subtitle: props.websetting?.website_hero_subtitle ?? 'Compassionate care, trusted doctors, and modern hospital services for your family.',
    website_about_text: props.websetting?.website_about_text ?? 'Toamed Hospital provides specialist consultation, diagnostics, emergency support, and day-to-day healthcare under one roof.',
    website_emergency_phone: props.websetting?.website_emergency_phone ?? props.websetting?.phone ?? '',
    website_enabled: props.websetting?.website_enabled !== false,
    website_cta_text: props.websetting?.website_cta_text ?? 'Book an Appointment Today',
    website_featured_doctors_json: props.websetting?.website_featured_doctors_json ?? '',
    website_featured_doctor_images: [],
    website_services_json: props.websetting?.website_services_json ?? '',
    website_facilities_json: props.websetting?.website_facilities_json ?? '',
    website_testimonials_en_json: props.websetting?.website_testimonials_en_json ?? '',
    website_testimonials_bn_json: props.websetting?.website_testimonials_bn_json ?? '',
    attendance_device_enabled: Boolean(props.websetting?.attendance_device_enabled ?? true),
    sidebar_menu_order: props.sidebarMenus ? props.sidebarMenus.map((menu) => menu.id) : [],
    attendance_device_type: props.websetting?.attendance_device_type ?? 'both',
    attendance_device_identifier: props.websetting?.attendance_device_identifier ?? '',
    attendance_device_ip: props.websetting?.attendance_device_ip ?? '',
    attendance_device_port: props.websetting?.attendance_device_port ?? '',
    attendance_device_secret: '',
    attendance_device_options: JSON.stringify(deviceOptions.value),
    website_template: (() => {
        try {
            const raw = props.websetting?.attendance_device_options ?? null;
            if (raw) {
                const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
                if (parsed && parsed.website_template) return parsed.website_template;
            }
        } catch (_) {}
        return (props.availableTemplates && props.availableTemplates.length) ? props.availableTemplates[0] : 'default';
    })(),

    logoPreview: props.websetting?.logo ?? currentWebSetting.value?.logo ?? '',
    iconPreview: props.websetting?.icon ?? currentWebSetting.value?.icon ?? '',
    mobileAppLogoPreview: getWebSettingValue('mobile_app_logo', ''),
    _method: 'post',
});

watch(sidebarMenus, (newList) => {
    form.sidebar_menu_order = newList.map((menu) => menu.id);
    refreshSidebarPositions();
}, { deep: true, immediate: true });

const featuredDoctorsRows = ref(parseFeaturedDoctorsRows(form.website_featured_doctors_json));
const serviceRows = ref(parseSimpleListRows(form.website_services_json));
const facilityRows = ref(parseSimpleListRows(form.website_facilities_json));
const testimonialEnRows = ref(parseTestimonialsRows(form.website_testimonials_en_json));
const testimonialBnRows = ref(parseTestimonialsRows(form.website_testimonials_bn_json));

const cmsStats = computed(() => ({
    doctors: featuredDoctorsRows.value.filter((row) => String(row.name ?? '').trim() !== '').length,
    services: serviceRows.value.filter((row) => String(row.label ?? '').trim() !== '').length,
    facilities: facilityRows.value.filter((row) => String(row.label ?? '').trim() !== '').length,
    testimonials: [...testimonialEnRows.value, ...testimonialBnRows.value]
        .filter((row) => String(row.name ?? '').trim() !== '' && String(row.quote ?? '').trim() !== '').length,
}));

const cmsCompletionPercent = computed(() => {
    const checkpoints = [
        String(form.website_hero_title ?? '').trim() !== '',
        String(form.website_hero_subtitle ?? '').trim() !== '',
        String(form.website_about_text ?? '').trim() !== '',
        String(form.website_cta_text ?? '').trim() !== '',
        String(form.website_emergency_phone ?? '').trim() !== '',
        cmsStats.value.doctors > 0,
        cmsStats.value.services > 0,
        cmsStats.value.facilities > 0,
    ];

    const completed = checkpoints.filter(Boolean).length;
    return Math.round((completed / checkpoints.length) * 100);
});

const cmsCompletionTone = computed(() => {
    if (cmsCompletionPercent.value >= 85) return 'text-emerald-700';
    if (cmsCompletionPercent.value >= 60) return 'text-amber-700';
    return 'text-rose-700';
});

if (featuredDoctorsRows.value.length === 0) {
    featuredDoctorsRows.value = [
        { name: '', specialty: '', designation: '', phone: '', experience: '', doctor_fee: '', bio: '', image_url: '' },
    ];
}

if (serviceRows.value.length === 0) {
    serviceRows.value = [
        { label: 'Emergency & Trauma' },
        { label: 'Diagnostics' },
        { label: 'Specialist Consultation' },
    ];
}

if (facilityRows.value.length === 0) {
    facilityRows.value = [
        { label: 'Digital Queue & Token' },
        { label: 'In-house Pharmacy' },
        { label: 'Cashless Billing Ready' },
        { label: 'Online Report Delivery' },
    ];
}

if (testimonialEnRows.value.length === 0) {
    testimonialEnRows.value = [
        { name: 'Mehjabin Rahman', role: 'Cardiac Care', quote: 'The consultant team listened carefully and explained every step of my treatment plan.' },
    ];
}

if (testimonialBnRows.value.length === 0) {
    testimonialBnRows.value = [
        { name: 'মেহজাবিন রহমান', role: 'কার্ডিয়াক কেয়ার', quote: 'ডাক্তার টিম খুব মনোযোগ দিয়ে শুনেছেন এবং চিকিৎসা পরিকল্পনা পরিষ্কারভাবে বুঝিয়েছেন।' },
    ];
}

const addFeaturedDoctorRow = () => {
    featuredDoctorsRows.value.push({
        name: '',
        specialty: '',
        designation: '',
        phone: '',
        experience: '',
        doctor_fee: '',
        bio: '',
        image_url: '',
    });
};

const addDoctorFromBooking = (doc) => {
    if (!doc || !doc.name) return;
    const exists = featuredDoctorsRows.value.some(r => {
        if (r.admin_id && r.admin_id === doc.id) return true;
        if (r.phone && r.phone === doc.phone && doc.phone) return true;
        if (r.name && r.name.trim() === doc.name.trim()) return true;
        return false;
    });
    if (exists) {
        displayWarning({ message: 'Doctor already present in featured list' });
        return;
    }

    featuredDoctorsRows.value.push({
        name: doc.name,
        specialty: '',
        designation: '',
        phone: doc.phone ?? '',
        experience: '',
        doctor_fee: String(doc.doctor_fee ?? doc.doctor_charge ?? ''),
        bio: '',
        image_url: '',
        admin_id: doc.id,
    });
};

const removeFeaturedDoctorRow = (index) => {
    if (featuredDoctorsRows.value.length <= 1) {
        return;
    }
    featuredDoctorsRows.value.splice(index, 1);
    if (Array.isArray(form.website_featured_doctor_images)) {
        form.website_featured_doctor_images.splice(index, 1);
    }
};

const handleFeaturedDoctorImageChange = (index, event) => {
    const file = event?.target?.files?.[0] ?? null;
    if (!Array.isArray(form.website_featured_doctor_images)) {
        form.website_featured_doctor_images = [];
    }

    form.website_featured_doctor_images[index] = file;

    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            featuredDoctorsRows.value[index].image_url = String(e.target?.result ?? '');
        };
        reader.readAsDataURL(file);
    }
};

const _toArray = (rowsRef) => {
    if (!rowsRef) return [];
    // detect Vue ref-like (has value)
    if (rowsRef && typeof rowsRef === 'object' && 'value' in rowsRef) {
        const v = rowsRef.value ?? [];
        return Array.isArray(v) ? v.slice() : [];
    }
    // plain array or reactive proxy -> return a shallow copy to avoid aliasing
    if (Array.isArray(rowsRef)) return rowsRef.slice();
    return [];
};

const _writeArray = (rowsRef, arr) => {
    if (!rowsRef) return;
    if (rowsRef && typeof rowsRef === 'object' && 'value' in rowsRef) {
        rowsRef.value = arr;
        return;
    }
    if (Array.isArray(rowsRef)) {
        rowsRef.length = 0;
        rowsRef.push(...arr);
    }
};

const addSimpleRow = (rowsRef) => {
    const arr = _toArray(rowsRef);
    console.debug('addSimpleRow before', { len: arr.length, rowsRef });
    arr.push({ label: '' });
    _writeArray(rowsRef, arr);
    console.debug('addSimpleRow after', { len: _toArray(rowsRef).length });
};

const removeSimpleRow = (rowsRef, index) => {
    const arr = _toArray(rowsRef);
    console.debug('removeSimpleRow before', { len: arr.length, index });
    if (arr.length <= 1) return;
    arr.splice(index, 1);
    _writeArray(rowsRef, arr);
    console.debug('removeSimpleRow after', { len: _toArray(rowsRef).length });
};

const addTestimonialRow = (rowsRef) => {
    const arr = _toArray(rowsRef);
    console.debug('addTestimonialRow before', { len: arr.length });
    arr.push({ name: '', role: '', quote: '' });
    _writeArray(rowsRef, arr);
    console.debug('addTestimonialRow after', { len: _toArray(rowsRef).length });
};

const removeTestimonialRow = (rowsRef, index) => {
    const arr = _toArray(rowsRef);
    console.debug('removeTestimonialRow before', { len: arr.length, index });
    if (arr.length <= 1) return;
    arr.splice(index, 1);
    _writeArray(rowsRef, arr);
    console.debug('removeTestimonialRow after', { len: _toArray(rowsRef).length });
};

const moveFeaturedDoctorUp = (index) => {
    if (index <= 0) {
        return;
    }
    const rows = [...featuredDoctorsRows.value];
    [rows[index - 1], rows[index]] = [rows[index], rows[index - 1]];
    featuredDoctorsRows.value = rows;
};

const moveFeaturedDoctorDown = (index) => {
    if (index >= featuredDoctorsRows.value.length - 1) {
        return;
    }
    const rows = [...featuredDoctorsRows.value];
    [rows[index], rows[index + 1]] = [rows[index + 1], rows[index]];
    featuredDoctorsRows.value = rows;
};

const moveSimpleRowUp = (rowsRef, index) => {
    const arr = _toArray(rowsRef);
    if (index <= 0 || index >= arr.length) return;
    [arr[index - 1], arr[index]] = [arr[index], arr[index - 1]];
    _writeArray(rowsRef, arr);
};

const moveSimpleRowDown = (rowsRef, index) => {
    const arr = _toArray(rowsRef);
    if (index < 0 || index >= arr.length - 1) return;
    [arr[index], arr[index + 1]] = [arr[index + 1], arr[index]];
    _writeArray(rowsRef, arr);
};

watch(featuredDoctorsRows, (rows) => {
    const normalized = rows
        .map((row) => ({
            name: String(row.name ?? '').trim(),
            specialty: String(row.specialty ?? '').trim(),
            designation: String(row.designation ?? '').trim(),
            phone: String(row.phone ?? '').trim(),
            experience: String(row.experience ?? '').trim(),
            doctor_fee: String(row.doctor_fee ?? '').trim(),
            bio: String(row.bio ?? '').trim(),
            image_url: String(row.image_url ?? '').trim(),
        }))
        .filter((row) => row.name !== '');

    form.website_featured_doctors_json = JSON.stringify(normalized, null, 2);
}, { deep: true, immediate: true });

watch(serviceRows, (rows) => {
    const normalized = rows
        .map((row) => String(row.label ?? '').trim())
        .filter((item) => item !== '');

    form.website_services_json = JSON.stringify(normalized, null, 2);
}, { deep: true, immediate: true });

watch(facilityRows, (rows) => {
    const normalized = rows
        .map((row) => String(row.label ?? '').trim())
        .filter((item) => item !== '');

    form.website_facilities_json = JSON.stringify(normalized, null, 2);
}, { deep: true, immediate: true });

const mapTestimonials = (rows) => rows
    .map((row) => ({
        name: String(row.name ?? '').trim(),
        role: String(row.role ?? '').trim(),
        quote: String(row.quote ?? '').trim(),
    }))
    .filter((row) => row.name !== '' && row.quote !== '');

watch(testimonialEnRows, (rows) => {
    form.website_testimonials_en_json = JSON.stringify(mapTestimonials(rows), null, 2);
}, { deep: true, immediate: true });

watch(testimonialBnRows, (rows) => {
    form.website_testimonials_bn_json = JSON.stringify(mapTestimonials(rows), null, 2);
}, { deep: true, immediate: true });

watch(() => form.company_name, (newValue) => {
    if (!newValue || form.company_short_name) {
        return;
    }

    const words = newValue.trim().split(/\s+/);
    const shortName = words.map((word) => word.charAt(0).toUpperCase()).join('');
    form.company_short_name = shortName.substring(0, 10);
});

watch(() => form.address, (newAddress) => {
    form.report_title = newAddress || '';
});

watch(deviceOptions, (newOptions) => {
    form.attendance_device_options = JSON.stringify(newOptions);
}, { deep: true });

watch(() => currentWebSetting.value, (settings) => {
    syncVatFieldsFromSettings(settings);
}, { immediate: true, deep: true });

watch(() => props.websetting, (settings) => {
    syncVatFieldsFromSettings(settings);
}, { immediate: true, deep: true });

onMounted(() => {
    syncVatFieldsFromSettings();
    persistVatValuesToStorage();
});

const setFilePreview = (file, previewKey, fileKey) => {
    if (!file) return;

    form[fileKey] = file;
    const reader = new FileReader();
    reader.onload = (e) => {
        form[previewKey] = e.target?.result;
    };
    reader.readAsDataURL(file);
};

const handleLogoChange = (event) => {
    setFilePreview(event.target.files?.[0], 'logoPreview', 'logo');
};

const handleIconChange = (event) => {
    setFilePreview(event.target.files?.[0], 'iconPreview', 'icon');
};

const handleMobileLogoChange = (event) => {
    setFilePreview(event.target.files?.[0], 'mobileAppLogoPreview', 'mobile_app_logo');
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        activeSection: activeSettingsSection.value,
        report_title: data.address,
        attendance_device_options: JSON.stringify(deviceOptions.value),
        sidebar_menu_order: sidebarMenus.value.map((menu) => menu.id),
    })).post(route('backend.websetting.store'), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: (response) => {
            displayResponse(response);

            try {
                const latestWebSetting = response?.props?.websetting ?? page.props?.websetting ?? props.websetting ?? null;
                if (latestWebSetting && typeof latestWebSetting === 'object') {
                    syncVatFieldsFromSettings(latestWebSetting);
                }
                persistVatValuesToStorage();
            } catch (e) {
                // ignore field sync failures
            }

            // Emit client-side branding update immediately so layout components
            // (Sidebar, Navbar) reflect the new company name without waiting
            // for the next server-provided branding payload.
            try {
                const payload = {
                    id: response?.props?.websetting?.id ?? page.props?.websetting?.id ?? null,
                    name: form.company_name ?? page.props?.companyInfo?.name ?? '',
                    short_name: form.company_short_name ?? page.props?.companyInfo?.short_name ?? '',
                    phone: form.phone ?? page.props?.companyInfo?.phone ?? '',
                    email: form.email ?? page.props?.companyInfo?.email ?? '',
                    logo: form.logoPreview ?? page.props?.companyInfo?.logo ?? '',
                    favicon: form.iconPreview ?? page.props?.companyInfo?.favicon ?? '',
                    address: form.address ?? page.props?.companyInfo?.address ?? '',
                    updated_at: new Date().toISOString(),
                };

                if (typeof window !== 'undefined') {
                    window.__last_branding_payload = payload;
                    try {
                        if (typeof localStorage !== 'undefined') {
                            localStorage.setItem('__last_branding_payload', JSON.stringify(payload));
                        }
                    } catch (e) {
                        // ignore localStorage write errors
                    }
                    if (typeof eventBus !== 'undefined' && eventBus && typeof eventBus.emit === 'function') {
                        eventBus.emit('branding.updated', payload);
                    }
                }
            } catch (e) {
                // ignore emit failures
            }
        },
        onError: (errorObject) => {
            displayWarning(errorObject);
        },
    });
};

const initialTheme = props.websetting?.current_theme ?? 'default';

watch(() => form.current_theme, (newTheme) => {
    applyLiveThemePreview(newTheme);
});

watch(activeSettingsSection, (newSection) => {
    if (isSingleSectionMode.value) {
        return;
    }

    if (typeof window === 'undefined') {
        return;
    }

    const normalizedSection = normalizeSection(newSection);
    window.localStorage.setItem(settingsSectionStorageKey, normalizedSection);

    const url = new URL(window.location.href);
    url.searchParams.set('section', normalizedSection);

    if (normalizedSection === 'module') {
        const selectedModule = lockedModuleSection.value || getSelectedModuleSection();
        if (selectedModule) {
            url.searchParams.set('module', selectedModule);
        } else {
            url.searchParams.delete('module');
        }
    } else {
        url.searchParams.delete('module');
    }

    window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
});

watch(moduleSections, () => {
    if (typeof window === 'undefined') {
        return;
    }

    if (lockedModuleSection.value) {
        setSingleModuleSection(lockedModuleSection.value);
    }

    const selectedModule = lockedModuleSection.value || getSelectedModuleSection();
    const url = new URL(window.location.href);
    if (selectedModule) {
        url.searchParams.set('module', selectedModule);
    } else {
        url.searchParams.delete('module');
    }
    window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
}, { deep: true });

watch(allowedSectionKeys, () => {
    activeSettingsSection.value = normalizeSection(activeSettingsSection.value);
}, { immediate: true });

onMounted(() => {
    applyLiveThemePreview(form.current_theme);
    applyModuleSelectionFromQuery();
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined' && sectionToastTimer) {
        window.clearTimeout(sectionToastTimer);
    }
    applyLiveThemePreview(initialTheme);
});
</script>

<template>
    <BackendLayout>
        <div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md">
            <div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md">
                <h1 class="p-4 text-xl font-bold dark:text-white">{{ $page.props.pageTitle || 'Settings' }}</h1>
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

            <div class="p-4">
                <div class="grid grid-cols-1 gap-6" :class="isSingleSectionMode ? 'lg:grid-cols-1' : 'lg:grid-cols-[250px_minmax(0,1fr)]'">
                    <aside v-if="!isSingleSectionMode" class="space-y-4 lg:sticky lg:top-6 lg:self-start">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 lg:hidden">
                            <div class="flex gap-2 overflow-x-auto pb-1">
                                <button
                                    v-for="section in visibleSettingsSections"
                                    :key="`mobile-${section.key}`"
                                    type="button"
                                    class="whitespace-nowrap rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                                    :class="sectionButtonClass(section.key)"
                                    @click="setActiveSettingsSection(section.key)"
                                >
                                    {{ section.label }}
                                </button>
                            </div>
                        </div>

                        <div class="hidden rounded-xl border border-slate-200 bg-gradient-to-b from-slate-50 to-white p-3 lg:block">
                            <h3 class="px-2 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Settings Sections</h3>
                            <div class="space-y-2">
                                <button
                                    v-for="section in visibleSettingsSections"
                                    :key="section.key"
                                    type="button"
                                    class="w-full rounded-lg border px-3 py-2 text-left transition"
                                    :class="sectionButtonClass(section.key)"
                                    @click="setActiveSettingsSection(section.key)"
                                >
                                    <p class="text-sm font-semibold">{{ section.label }}</p>
                                    <p class="mt-1 text-[11px]" :class="activeSettingsSection === section.key ? 'text-sky-100' : 'text-slate-500'">{{ section.hint }}</p>
                                </button>
                            </div>
                            <p class="px-2 pt-3 text-[11px] text-slate-500">Settings গুলো আলাদা করা হয়েছে যাতে বড় form দ্রুত manage করা যায়।</p>
                        </div>
                    </aside>

                    <div class="space-y-6">
                        <div v-if="!isSingleSectionMode" class="rounded-xl border border-sky-100 bg-sky-50/70 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">Active Section</p>
                            <p class="mt-1 text-sm font-semibold text-sky-900">{{ getSectionMeta(activeSettingsSection).label }}</p>
                            <p class="mt-1 text-xs text-sky-700">{{ getSectionMeta(activeSettingsSection).hint }}</p>
                        </div>

                <div v-show="activeSettingsSection === 'general'" class="border rounded-md p-4">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3">
                        <div>
                            <InputLabel for="company_name" value="Hospital Name *" />
                            <input id="company_name" v-model="form.company_name" type="text" placeholder="ToaMed"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" />
                            <InputError class="mt-2" :message="form.errors.company_name" />
                        </div>

                        <div>
                            <InputLabel for="hospital_code" value="Hospital Code" />
                            <input id="hospital_code" v-model="form.hospital_code" type="text" placeholder="Your Hospital Code"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" />
                            <InputError class="mt-2" :message="form.errors.hospital_code" />
                        </div>

                        <div>
                            <InputLabel for="company_short_name" value="Hospital Short Name" />
                            <input id="company_short_name" v-model="form.company_short_name" type="text"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" />
                            <InputError class="mt-2" :message="form.errors.company_short_name" />
                        </div>

                        <div>
                            <InputLabel for="address" value="Address *" />
                            <input id="address" v-model="form.address" type="text" placeholder="Mirpur, Dhaka."
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" />
                            <InputError class="mt-2" :message="form.errors.address" />
                        </div>

                        <div>
                            <InputLabel for="phone" value="Phone *" />
                            <input id="phone" v-model="form.phone" type="text" placeholder="01919592638"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" />
                            <InputError class="mt-2" :message="form.errors.phone" />
                        </div>

                        <div>
                            <InputLabel for="email" value="Email *" />
                            <input id="email" v-model="form.email" type="email" placeholder="toamedbd@gmail.com"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div>
                            <InputLabel for="login_banner" value="Login Banner (small uppercase)" />
                            <input id="login_banner" v-model="form.login_banner" type="text" placeholder="Hospital Management Suite"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" />
                            <InputError class="mt-2" :message="form.errors.login_banner" />
                        </div>

                        <div>
                            <InputLabel for="login_title" value="Login Title (large)" />
                            <input id="login_title" v-model="form.login_title" type="text" placeholder="Welcome Back."
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" />
                            <InputError class="mt-2" :message="form.errors.login_title" />
                        </div>

                        <div class="md:col-span-3">
                            <InputLabel for="login_subtitle" value="Login Subtitle (paragraph)" />
                            <input id="login_subtitle" v-model="form.login_subtitle" type="text" placeholder="Continue with your secure account..."
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" />
                            <InputError class="mt-2" :message="form.errors.login_subtitle" />
                        </div>
                    </div>
                </div>

                <div v-show="activeSettingsSection === 'general'" class="border rounded-md p-4">
                    <h2 class="text-lg font-semibold mb-4">Logos</h2>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <InputLabel for="logo" value="Hospital Logo *" />
                            <img v-if="form.logoPreview || getWebSettingValue('logo', '')" :src="form.logoPreview || getWebSettingValue('logo', '')" alt="Hospital Logo" class="mb-2 h-20 object-contain" />
                            <input id="logo" type="file" accept="image/*" @change="handleLogoChange"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.logo" />
                        </div>

                        <div>
                            <InputLabel for="icon" value="Hospital Small Logo *" />
                            <img v-if="form.iconPreview || getWebSettingValue('icon', '')" :src="form.iconPreview || getWebSettingValue('icon', '')" alt="Hospital Small Logo" class="mb-2 h-20 object-contain" />
                            <input id="icon" type="file" accept="image/*,.ico" @change="handleIconChange"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.icon" />
                        </div>

                        <div>
                            <InputLabel for="mobile_app_logo" value="Mobile App Logo" />
                            <img v-if="form.mobileAppLogoPreview || getWebSettingValue('mobile_app_logo', '')" :src="form.mobileAppLogoPreview || getWebSettingValue('mobile_app_logo', '')" alt="Mobile App Logo" class="mb-2 h-20 object-contain" />
                            <input id="mobile_app_logo" type="file" accept="image/*" @change="handleMobileLogoChange"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.mobile_app_logo" />
                        </div>
                    </div>
                </div>

                <div v-show="activeSettingsSection === 'general'" class="border rounded-md p-4">
                    <h2 class="text-lg font-semibold mb-4">Language</h2>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <InputLabel for="language" value="Language *" />
                            <select id="language" v-model="form.language"
                                class="block w-full p-2 text-sm rounded-md border-slate-300">
                                <option value="English">English</option>
                                <option value="Bangla">Bangla</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.language" />
                        </div>
                    </div>
                </div>

                <div v-show="activeSettingsSection === 'general'" class="border rounded-md p-4">
                    <h2 class="text-lg font-semibold mb-4">Date Time</h2>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div>
                            <InputLabel for="date_format" value="Date Format *" />
                            <input id="date_format" v-model="form.date_format" type="text" placeholder="dd-mm-yyyy"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.date_format" />
                        </div>

                        <div>
                            <InputLabel for="time_zone" value="Time Zone *" />
                            <input id="time_zone" v-model="form.time_zone" type="text" placeholder="(GMT+06:00) Asia, Dhaka"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.time_zone" />
                        </div>

                        <div>
                            <InputLabel for="time_format" value="Time Format *" />
                            <select id="time_format" v-model="form.time_format"
                                class="block w-full p-2 text-sm rounded-md border-slate-300">
                                <option value="12 Hour">12 Hour</option>
                                <option value="24 Hour">24 Hour</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.time_format" />
                        </div>
                    </div>
                </div>

                <div v-show="activeSettingsSection === 'general'" class="border rounded-md p-4">
                    <h2 class="text-lg font-semibold mb-4">Currency</h2>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                        <div>
                            <InputLabel for="currency" value="Currency *" />
                            <input id="currency" v-model="form.currency" type="text" placeholder="BDT"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.currency" />
                        </div>

                        <div>
                            <InputLabel for="currency_symbol" value="Currency Symbol *" />
                            <input id="currency_symbol" v-model="form.currency_symbol" type="text" placeholder="Tk."
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.currency_symbol" />
                        </div>

                        <div class="md:col-span-2">
                            <InputLabel for="vat_enabled" value="Enable VAT" />
                            <div class="flex items-center space-x-3">
                                <label class="flex items-center space-x-2">
                                    <input id="vat_enabled" type="checkbox" v-model="form.vat_enabled" class="h-4 w-4" />
                                    <span class="text-sm">Enable VAT for billing</span>
                                </label>
                                <div class="flex items-center">
                                    <input id="vat_percent" v-model.number="form.vat_percent" type="number" min="0" max="100" step="0.01"
                                        class="block w-28 p-2 text-sm rounded-md border-slate-300" />
                                    <span class="ml-2">%</span>
                                </div>
                            </div>
                            <InputError class="mt-2" :message="form.errors.vat_percent" />
                        </div>

                        <div>
                            <InputLabel for="credit_limit" value="Credit Limit *" />
                            <input id="credit_limit" v-model="form.credit_limit" type="number" min="0" step="0.01"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.credit_limit" />
                        </div>

                        <div>
                            <InputLabel for="max_billing_discount_percent" value="Max Billing Discount (%) *" />
                            <input id="max_billing_discount_percent" v-model="form.max_billing_discount_percent" type="number" min="0" max="100" step="0.01"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.max_billing_discount_percent" />
                        </div>

                        <div>
                            <InputLabel for="low_stock_threshold" value="Low Stock Threshold" />
                            <input id="low_stock_threshold" v-model="form.low_stock_threshold" type="number" min="0" step="1"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.low_stock_threshold" />
                        </div>
                    </div>
                </div>

                <div v-show="activeSettingsSection === 'general'" class="border rounded-md p-4">
                    <h2 class="text-lg font-semibold mb-4">Mobile App (Android App Purchase Code already registered)</h2>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div>
                            <InputLabel for="mobile_app_api_url" value="Mobile App Api URL" />
                            <input id="mobile_app_api_url" v-model="form.mobile_app_api_url" type="text"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.mobile_app_api_url" />
                        </div>

                        <div>
                            <InputLabel for="mobile_app_primary_color_code" value="Mobile App Primary Color Code" />
                            <input id="mobile_app_primary_color_code" v-model="form.mobile_app_primary_color_code" type="text" placeholder="444444"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.mobile_app_primary_color_code" />
                        </div>

                        <div>
                            <InputLabel for="mobile_app_secondary_color_code" value="Mobile App Secondary Color Code" />
                            <input id="mobile_app_secondary_color_code" v-model="form.mobile_app_secondary_color_code" type="text" placeholder="ffffff"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.mobile_app_secondary_color_code" />
                        </div>
                    </div>
                </div>

                <div v-show="activeSettingsSection === 'general'" class="rounded-md border border-slate-200 bg-slate-50 p-4">
                    <div class="flex justify-end">
                        <button type="button" @click.prevent="submit" class="inline-flex items-center rounded-md bg-green-600 px-6 py-3 text-base font-bold text-white shadow-sm transition hover:bg-green-700">
                            Save General Setting
                        </button>
                    </div>
                </div>

                <div v-show="activeSettingsSection === 'cms'" class="border rounded-md p-4">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold">Website Content (Dynamic CMS)</h2>
                            <p class="mt-1 text-xs text-slate-600">এই section-এ সব content no-code ভাবে edit/post করা যাবে, JSON manually edit করার দরকার নেই।</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="/" target="_blank" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Open Website</a>
                        </div>
                    </div>

                    <div class="mb-4 rounded-xl border border-sky-100 bg-gradient-to-r from-sky-50 via-cyan-50 to-emerald-50 p-4">
                        <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
                            <div class="rounded-lg bg-white/90 p-2 text-center">
                                <p class="text-[11px] uppercase tracking-wide text-slate-500">Doctors</p>
                                <p class="text-lg font-semibold text-slate-800">{{ cmsStats.doctors }}</p>
                            </div>
                            <div class="rounded-lg bg-white/90 p-2 text-center">
                                <p class="text-[11px] uppercase tracking-wide text-slate-500">Services</p>
                                <p class="text-lg font-semibold text-slate-800">{{ cmsStats.services }}</p>
                            </div>
                            <div class="rounded-lg bg-white/90 p-2 text-center">
                                <p class="text-[11px] uppercase tracking-wide text-slate-500">Facilities</p>
                                <p class="text-lg font-semibold text-slate-800">{{ cmsStats.facilities }}</p>
                            </div>
                            <div class="rounded-lg bg-white/90 p-2 text-center">
                                <p class="text-[11px] uppercase tracking-wide text-slate-500">Testimonials</p>
                                <p class="text-lg font-semibold text-slate-800">{{ cmsStats.testimonials }}</p>
                            </div>
                            <div class="rounded-lg bg-white/90 p-2 text-center">
                                <p class="text-[11px] uppercase tracking-wide text-slate-500">Completion</p>
                                <p class="text-lg font-semibold" :class="cmsCompletionTone">{{ cmsCompletionPercent }}%</p>
                            </div>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-white">
                            <div class="h-full rounded-full bg-gradient-to-r from-sky-500 to-emerald-500 transition-all duration-300" :style="{ width: `${cmsCompletionPercent}%` }"></div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                            <h3 class="text-sm font-semibold text-amber-800">Website Availability</h3>
                            <p class="mt-1 text-xs text-amber-700">Website disabled করলে root domain থেকে public website দেখাবে না, সরাসরি software login page open হবে।</p>
                            <div class="mt-3 max-w-sm">
                                <InputLabel for="website_enabled" value="Public Website" />
                                <select id="website_enabled" v-model="form.website_enabled"
                                    class="block w-full p-2 text-sm rounded-md border-slate-300">
                                    <option :value="true">Enabled</option>
                                    <option :value="false">Disabled</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.website_enabled" />
                            </div>
                            <div class="mt-4 max-w-sm">
                                <InputLabel for="website_template" value="Website Template" />
                                <select id="website_template" v-model="form.website_template"
                                    class="block w-full p-2 text-sm rounded-md border-slate-300">
                                    <option v-for="tpl in (props.availableTemplates || [])" :key="tpl" :value="tpl">{{ tpl }}</option>
                                </select>
                                <p class="mt-2 text-xs text-slate-500">Select a frontend template (file-based). Switch will apply after save.</p>
                                <InputError class="mt-2" :message="form.errors.website_template" />
                            </div>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <h3 class="text-sm font-semibold text-slate-700">Hero Section</h3>
                            <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div>
                                    <InputLabel for="website_hero_title" value="Website Hero Title" />
                                    <input id="website_hero_title" v-model="form.website_hero_title" type="text"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                    <InputError class="mt-2" :message="form.errors.website_hero_title" />
                                </div>

                                <div>
                                    <InputLabel for="website_cta_text" value="Website CTA Button Text" />
                                    <input id="website_cta_text" v-model="form.website_cta_text" type="text"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                    <InputError class="mt-2" :message="form.errors.website_cta_text" />
                                </div>

                                <div class="md:col-span-2">
                                    <InputLabel for="website_hero_subtitle" value="Website Hero Subtitle" />
                                    <textarea id="website_hero_subtitle" v-model="form.website_hero_subtitle" rows="2"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                    <InputError class="mt-2" :message="form.errors.website_hero_subtitle" />
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-white p-4">
                            <h3 class="text-sm font-semibold text-slate-700">About & Emergency</h3>
                            <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div>
                                    <InputLabel for="website_emergency_phone" value="Website Emergency Phone" />
                                    <input id="website_emergency_phone" v-model="form.website_emergency_phone" type="text"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                    <InputError class="mt-2" :message="form.errors.website_emergency_phone" />
                                </div>

                                <div class="md:col-span-2">
                                    <InputLabel for="website_about_text" value="Website About Text" />
                                    <textarea id="website_about_text" v-model="form.website_about_text" rows="3"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                    <InputError class="mt-2" :message="form.errors.website_about_text" />
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-sm font-semibold text-slate-700">Featured Doctors</h3>
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click.prevent.stop="addFeaturedDoctorRow"
                                            class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                                            Add Doctor
                                        </button>

                                        <div class="relative">
                                            <button type="button" @click.prevent.stop="bookingPanelOpen = !bookingPanelOpen"
                                                class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                                                Toggle Appointment Doctors
                                            </button>
                                            <div v-show="bookingPanelOpen" ref="bookingPanel" class="absolute right-0 z-10 mt-2 w-80 max-h-64 overflow-auto rounded border bg-white p-2 shadow">
                                                <template v-if="Array.isArray(bookingDoctors) && bookingDoctors.length">
                                                    <div v-for="doc in bookingDoctors" :key="`booking-doc-${doc.id}`" class="flex items-center justify-between gap-2 p-1 border-b last:border-b-0">
                                                        <div class="text-sm">{{ doc.name }} <div class="text-xs text-slate-500">{{ doc.phone }}</div></div>
                                                        <div>
                                                            <button type="button" @click.prevent.stop="addDoctorFromBooking(doc)" class="rounded border bg-sky-50 px-2 py-1 text-xs text-sky-700">Add</button>
                                                        </div>
                                                    </div>
                                                </template>
                                                <div v-else class="text-xs text-slate-500">No appointment doctors available</div>
                                            </div>
                                        </div>
                                    </div>
                            </div>

                            <div class="mt-3 overflow-x-auto">
                                <table class="min-w-full border border-slate-200 text-xs md:text-sm">
                                    <thead class="bg-slate-100 text-slate-700">
                                        <tr>
                                            <th class="border border-slate-200 px-2 py-2 text-left">Name</th>
                                                <th class="border border-slate-200 px-2 py-2 text-left">Specialty</th>
                                                <th class="border border-slate-200 px-2 py-2 text-left">Designation</th>
                                            <th class="border border-slate-200 px-2 py-2 text-left">Phone</th>
                                            <th class="border border-slate-200 px-2 py-2 text-left">Experience</th>
                                                <th class="border border-slate-200 px-2 py-2 text-left">Doctor Fee</th>
                                                <th class="border border-slate-200 px-2 py-2 text-left">Image URL</th>
                                            <th class="border border-slate-200 px-2 py-2 text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(doctor, index) in featuredDoctorsRows" :key="`doctor-row-${index}`" class="bg-white">
                                            <td class="border border-slate-200 p-1">
                                                <input v-model="doctor.name" type="text" placeholder="Dr. Example"
                                                    class="block w-full rounded border-slate-300 p-1 text-xs md:text-sm" />
                                            </td>
                                                <td class="border border-slate-200 p-1">
                                                    <textarea v-model="doctor.specialty" rows="3" placeholder="Cardiology (one line per specialty)"
                                                        class="block w-full rounded border-slate-300 p-1 text-xs md:text-sm"></textarea>
                                                </td>
                                                <td class="border border-slate-200 p-1">
                                                    <textarea v-model="doctor.designation" rows="2" placeholder="MBBS, FCPS\nSenior Consultant"
                                                        class="block w-full rounded border-slate-300 p-1 text-xs md:text-sm"></textarea>
                                                </td>
                                            <td class="border border-slate-200 p-1">
                                                <input v-model="doctor.phone" type="text" placeholder="017XXXXXXXX"
                                                    class="block w-full rounded border-slate-300 p-1 text-xs md:text-sm" />
                                            </td>
                                            <td class="border border-slate-200 p-1">
                                                <input v-model="doctor.experience" type="text" placeholder="10 years"
                                                    class="block w-full rounded border-slate-300 p-1 text-xs md:text-sm" />
                                            </td>
                                            <td class="border border-slate-200 p-1">
                                                <input v-model="doctor.doctor_fee" type="number" min="0" step="0.01" placeholder="500"
                                                    class="block w-full rounded border-slate-300 p-1 text-xs md:text-sm" />
                                            </td>
                                            <td class="border border-slate-200 p-1">
                                                <div class="space-y-1">
                                                    <input v-model="doctor.image_url" type="text" placeholder="Existing image URL"
                                                        class="block w-full rounded border-slate-300 p-1 text-xs md:text-sm" />
                                                    <input type="file" accept="image/*" @change="handleFeaturedDoctorImageChange(index, $event)"
                                                        class="block w-full rounded border-slate-300 p-1 text-xs md:text-sm" />
                                                </div>
                                            </td>
                                            <td class="border border-slate-200 p-1 text-center">
                                                <div class="flex items-center justify-center gap-1">
                                                <button type="button" @click="moveFeaturedDoctorUp(index)"
                                                    class="rounded border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100"
                                                    :disabled="index === 0">
                                                    Up
                                                </button>
                                                <button type="button" @click="moveFeaturedDoctorDown(index)"
                                                    class="rounded border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100"
                                                    :disabled="index === featuredDoctorsRows.length - 1">
                                                    Down
                                                </button>
                                                <button type="button" @click="removeFeaturedDoctorRow(index)"
                                                    class="rounded border border-red-200 bg-red-50 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-100">
                                                    Remove
                                                </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <input id="website_featured_doctors_json" v-model="form.website_featured_doctors_json" type="hidden" />
                            <p class="mt-2 text-xs text-slate-500">Doctor list auto-saves as JSON. Empty name rows are ignored.</p>
                            <InputError class="mt-2" :message="form.errors.website_featured_doctors_json" />
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-white p-4">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-sm font-semibold text-slate-700">Website Services</h3>
                                <button
                                    type="button"
                                    @click.prevent.stop="addSimpleRow(serviceRows)"
                                    class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                >
                                    Add Service
                                </button>
                            </div>

                            <div class="mt-3 space-y-2">
                                <div v-for="(service, index) in serviceRows" :key="`service-row-${index}`" class="flex items-center gap-2">
                                    <input
                                        v-model="service.label"
                                        type="text"
                                        placeholder="Emergency & Trauma"
                                        class="block w-full rounded border-slate-300 p-2 text-sm"
                                    />
                                    <div class="flex items-center gap-1">
                                        <button type="button" @click="moveSimpleRowUp(serviceRows, index)" class="rounded border bg-slate-50 px-2 py-1 text-xs text-slate-700">Up</button>
                                        <button type="button" @click="moveSimpleRowDown(serviceRows, index)" class="rounded border bg-slate-50 px-2 py-1 text-xs text-slate-700">Down</button>
                                        <button
                                            type="button"
                                            @click="removeSimpleRow(serviceRows, index)"
                                            class="rounded border border-red-200 bg-red-50 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-100"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <input id="website_services_json" v-model="form.website_services_json" type="hidden" />
                            <InputError class="mt-2" :message="form.errors.website_services_json" />
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-sm font-semibold text-slate-700">Website Facilities</h3>
                                <button
                                    type="button"
                                    @click.prevent.stop="addSimpleRow(facilityRows)"
                                    class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                >
                                    Add Facility
                                </button>
                            </div>

                            <div class="mt-3 space-y-2">
                                <div v-for="(facility, index) in facilityRows" :key="`facility-row-${index}`" class="flex items-center gap-2">
                                    <input
                                        v-model="facility.label"
                                        type="text"
                                        placeholder="Digital Queue & Token"
                                        class="block w-full rounded border-slate-300 p-2 text-sm"
                                    />
                                    <div class="flex items-center gap-1">
                                        <button type="button" @click="moveSimpleRowUp(facilityRows, index)" class="rounded border bg-slate-50 px-2 py-1 text-xs text-slate-700">Up</button>
                                        <button type="button" @click="moveSimpleRowDown(facilityRows, index)" class="rounded border bg-slate-50 px-2 py-1 text-xs text-slate-700">Down</button>
                                        <button
                                            type="button"
                                            @click="removeSimpleRow(facilityRows, index)"
                                            class="rounded border border-red-200 bg-red-50 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-100"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <input id="website_facilities_json" v-model="form.website_facilities_json" type="hidden" />
                            <InputError class="mt-2" :message="form.errors.website_facilities_json" />
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-white p-4">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-sm font-semibold text-slate-700">Website Testimonials (English)</h3>
                                <button
                                    type="button"
                                    @click.prevent.stop="addTestimonialRow(testimonialEnRows)"
                                    class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                >
                                    Add Testimonial
                                </button>
                            </div>

                            <div class="mt-3 space-y-3">
                                <div v-for="(item, index) in testimonialEnRows" :key="`test-en-${index}`" class="rounded border border-slate-200 p-2">
                                    <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                        <input v-model="item.name" type="text" placeholder="Patient Name" class="block w-full rounded border-slate-300 p-2 text-sm" />
                                        <input v-model="item.role" type="text" placeholder="Cardiac Care" class="block w-full rounded border-slate-300 p-2 text-sm" />
                                    </div>
                                    <textarea v-model="item.quote" rows="2" placeholder="Patient experience text"
                                        class="mt-2 block w-full rounded border-slate-300 p-2 text-sm"></textarea>
                                    <button type="button" @click="removeTestimonialRow(testimonialEnRows, index)"
                                        class="mt-2 rounded border border-red-200 bg-red-50 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-100">
                                        Remove
                                    </button>
                                </div>
                            </div>

                            <input id="website_testimonials_en_json" v-model="form.website_testimonials_en_json" type="hidden" />
                            <InputError class="mt-2" :message="form.errors.website_testimonials_en_json" />
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-sm font-semibold text-slate-700">Website Testimonials (Bangla)</h3>
                                <button
                                    type="button"
                                    @click.prevent.stop="addTestimonialRow(testimonialBnRows)"
                                    class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                >
                                    Add Testimonial
                                </button>
                            </div>

                            <div class="mt-3 space-y-3">
                                <div v-for="(item, index) in testimonialBnRows" :key="`test-bn-${index}`" class="rounded border border-slate-200 p-2 bg-white">
                                    <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                        <input v-model="item.name" type="text" placeholder="রোগীর নাম" class="block w-full rounded border-slate-300 p-2 text-sm" />
                                        <input v-model="item.role" type="text" placeholder="কার্ডিয়াক কেয়ার" class="block w-full rounded border-slate-300 p-2 text-sm" />
                                    </div>
                                    <textarea v-model="item.quote" rows="2" placeholder="রোগীর অভিজ্ঞতা"
                                        class="mt-2 block w-full rounded border-slate-300 p-2 text-sm"></textarea>
                                    <button type="button" @click="removeTestimonialRow(testimonialBnRows, index)"
                                        class="mt-2 rounded border border-red-200 bg-red-50 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-100">
                                        Remove
                                    </button>
                                </div>
                            </div>

                            <input id="website_testimonials_bn_json" v-model="form.website_testimonials_bn_json" type="hidden" />
                            <InputError class="mt-2" :message="form.errors.website_testimonials_bn_json" />
                        </div>
                    </div>
                </div>

                <div v-show="activeSettingsSection === 'sidebar'" class="border rounded-md p-4">
                    <div class="grid gap-4 sm:grid-cols-[auto_1fr] items-center mb-4">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                            <div class="font-semibold text-slate-800">Manual position</div>
                            <div class="mt-2 text-xs text-slate-500">
                                Type a new position and press Enter or leave the field to update order.
                            </div>
                        </div>
                        <div>
                            <label for="sidebar-menu-search" class="block text-sm font-medium text-slate-700">Search sidebar menu</label>
                            <input
                                id="sidebar-menu-search"
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search menu name..."
                                class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none"
                            />
                        </div>
                    </div>
                    <div class="space-y-3">
                        <template v-if="filteredSidebarMenus.length">
                            <div v-for="(menu, index) in filteredSidebarMenus" :key="menu.id" class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ menu.name }}</p>
                                    <p class="text-xs text-slate-500">Position {{ getSidebarMenuPosition(menu.id) }}</p>
                                </div>
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="moveSidebarMenuUp(getSidebarMenuPosition(menu.id) - 1)"
                                            class="rounded border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                                            :disabled="getSidebarMenuPosition(menu.id) === 1">
                                            Up
                                        </button>
                                        <button type="button" @click="moveSidebarMenuDown(getSidebarMenuPosition(menu.id) - 1)"
                                            class="rounded border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                                            :disabled="getSidebarMenuPosition(menu.id) === sidebarMenus.length">
                                            Down
                                        </button>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <label class="text-xs text-slate-600">Set position</label>
                                        <input
                                            type="number"
                                            min="1"
                                            :max="sidebarMenus.length"
                                            v-model="manualSidebarPositions[menu.id]"
                                            placeholder="Pos"
                                            @blur="() => setSidebarMenuPosition(menu.id, manualSidebarPositions[menu.id])"
                                            @keyup.enter.prevent="() => setSidebarMenuPosition(menu.id, manualSidebarPositions[menu.id])"
                                            class="w-20 rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-800 focus:border-sky-500 focus:outline-none"
                                        />
                                    </div>
                                </div>
                            </div>
                        </template>
                        <div v-else class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            কোনো সক্রিয় টপ-লেভেল সাইডবার মেনু আইটেম পাওয়া যায়নি।
                        </div>
                    </div>

                    <template v-for="menu in sidebarMenus" :key="`sidebar-order-${menu.id}`">
                        <input type="hidden" name="sidebar_menu_order[]" :value="menu.id" />
                    </template>

                    <div class="flex justify-end mt-6">
                        <button type="button" @click.prevent="submit" class="inline-flex items-center rounded-md bg-green-600 px-6 py-3 text-base font-bold text-white shadow-sm transition hover:bg-green-700">
                            Save Sidebar Setting
                        </button>
                    </div>
                </div>

                <div v-show="activeSettingsSection === 'prefix'" class="border rounded-md p-4">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <InputLabel for="ipd_no_prefix" value="IPD No" />
                            <input id="ipd_no_prefix" v-model="form.ipd_no_prefix" type="text" placeholder="IPDN"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.ipd_no_prefix" />
                        </div>

                        <div>
                            <InputLabel for="opd_no_prefix" value="OPD No" />
                            <input id="opd_no_prefix" v-model="form.opd_no_prefix" type="text" placeholder="OPDN"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.opd_no_prefix" />
                        </div>

                        <div>
                            <InputLabel for="ipd_prescription_prefix" value="IPD Prescription" />
                            <input id="ipd_prescription_prefix" v-model="form.ipd_prescription_prefix" type="text" placeholder="IPDP"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.ipd_prescription_prefix" />
                        </div>

                        <div>
                            <InputLabel for="opd_prescription_prefix" value="OPD Prescription" />
                            <input id="opd_prescription_prefix" v-model="form.opd_prescription_prefix" type="text" placeholder="OPDP"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.opd_prescription_prefix" />
                        </div>

                        <div>
                            <InputLabel for="appointment_prefix" value="Appointment" />
                            <input id="appointment_prefix" v-model="form.appointment_prefix" type="text" placeholder="APPN"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.appointment_prefix" />
                        </div>

                        <div>
                            <InputLabel for="pharmacy_bill_prefix" value="Pharmacy Bill" />
                            <input id="pharmacy_bill_prefix" v-model="form.pharmacy_bill_prefix" type="text" placeholder="PHAB"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.pharmacy_bill_prefix" />
                        </div>

                        <div>
                            <InputLabel for="billing_bill_prefix" value="Billing Module Bill" />
                            <input id="billing_bill_prefix" v-model="form.billing_bill_prefix" type="text" placeholder="BILL"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.billing_bill_prefix" />
                        </div>

                        <div>
                            <InputLabel for="operation_reference_no_prefix" value="Operation Reference No" />
                            <input id="operation_reference_no_prefix" v-model="form.operation_reference_no_prefix" type="text" placeholder="OTRN"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.operation_reference_no_prefix" />
                        </div>

                        <div>
                            <InputLabel for="blood_bank_bill_prefix" value="Blood Bank Bill" />
                            <input id="blood_bank_bill_prefix" v-model="form.blood_bank_bill_prefix" type="text" placeholder="BLBB"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.blood_bank_bill_prefix" />
                        </div>

                        <div>
                            <InputLabel for="ambulance_call_bill_prefix" value="Ambulance Call Bill" />
                            <input id="ambulance_call_bill_prefix" v-model="form.ambulance_call_bill_prefix" type="text" placeholder="AMCB"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.ambulance_call_bill_prefix" />
                        </div>

                        <div>
                            <InputLabel for="radiology_bill_prefix" value="Radiology Bill" />
                            <input id="radiology_bill_prefix" v-model="form.radiology_bill_prefix" type="text" placeholder="RADB"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.radiology_bill_prefix" />
                        </div>

                        <div>
                            <InputLabel for="pathology_bill_prefix" value="Pathology Bill" />
                            <input id="pathology_bill_prefix" v-model="form.pathology_bill_prefix" type="text" placeholder="Bill"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.pathology_bill_prefix" />
                        </div>

                        <div>
                            <InputLabel for="opd_checkup_id_prefix" value="OPD Checkup Id" />
                            <input id="opd_checkup_id_prefix" v-model="form.opd_checkup_id_prefix" type="text" placeholder="OCID"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.opd_checkup_id_prefix" />
                        </div>

                        <div>
                            <InputLabel for="pharmacy_purchase_no_prefix" value="Pharmacy Purchase No" />
                            <input id="pharmacy_purchase_no_prefix" v-model="form.pharmacy_purchase_no_prefix" type="text" placeholder="PHPN"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.pharmacy_purchase_no_prefix" />
                        </div>

                        <div>
                            <InputLabel for="transaction_id_prefix" value="Transaction ID" />
                            <input id="transaction_id_prefix" v-model="form.transaction_id_prefix" type="text" placeholder="TRID"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.transaction_id_prefix" />
                        </div>

                        <div>
                            <InputLabel for="birth_record_reference_no_prefix" value="Birth Record Reference No" />
                            <input id="birth_record_reference_no_prefix" v-model="form.birth_record_reference_no_prefix" type="text" placeholder="BRRN"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.birth_record_reference_no_prefix" />
                        </div>

                        <div>
                            <InputLabel for="death_record_reference_no_prefix" value="Death Record Reference No" />
                            <input id="death_record_reference_no_prefix" v-model="form.death_record_reference_no_prefix" type="text" placeholder="DRRN"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.death_record_reference_no_prefix" />
                        </div>
                    </div>

                    <div class="flex justify-end mt-6">
                        <button type="button" @click.prevent="submit" class="inline-flex items-center rounded-md bg-green-600 px-6 py-3 text-base font-bold text-white shadow-sm transition hover:bg-green-700">
                            Save Prefix Setting
                        </button>
                    </div>
                </div>

                <div v-show="activeSettingsSection === 'other'" class="border rounded-md p-4">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <h2 class="text-lg font-semibold">Other Setting</h2>
                    </div>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <InputLabel for="doctor_restriction_mode" value="Doctor Restriction Mode" />
                            <select id="doctor_restriction_mode" v-model="form.doctor_restriction_mode"
                                class="block w-full p-2 text-sm rounded-md border-slate-300">
                                <option :value="false">Disabled</option>
                                <option :value="true">Enabled</option>
                            </select>
                        </div>

                        <div>
                            <InputLabel for="superadmin_visibility" value="Superadmin Visibility" />
                            <select id="superadmin_visibility" v-model="form.superadmin_visibility"
                                class="block w-full p-2 text-sm rounded-md border-slate-300">
                                <option :value="false">Disabled</option>
                                <option :value="true">Enabled</option>
                            </select>
                        </div>

                        <div>
                            <InputLabel for="patient_panel" value="Patient Panel" />
                            <select id="patient_panel" v-model="form.patient_panel"
                                class="block w-full p-2 text-sm rounded-md border-slate-300">
                                <option :value="false">Disabled</option>
                                <option :value="true">Enabled</option>
                            </select>
                        </div>

                        <div>
                            <InputLabel for="scan_type" value="Scan Type" />
                            <select id="scan_type" v-model="form.scan_type"
                                class="block w-full p-2 text-sm rounded-md border-slate-300">
                                <option value="Barcode">Barcode</option>
                                <option value="QR Code">QR Code</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.scan_type" />
                        </div>

                        <div>
                            <InputLabel for="current_theme" value="Current Theme" />
                            <select id="current_theme" v-model="form.current_theme"
                                class="block w-full p-2 text-sm rounded-md border-slate-300">
                                <option v-for="theme in Object.keys(themePreviewTokens)" :key="theme" :value="theme">
                                    {{ prettyThemeName(theme) }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.current_theme" />
                        </div>
                        <div class="md:col-span-2 lg:col-span-3">
                            <h3 class="text-sm font-semibold mb-2">Header / Footer Options</h3>
                            <div class="grid grid-cols-1 gap-2">
                                <label class="flex items-center gap-2 text-sm">
                                    <input id="opd_invoice_header_footer" v-model="form.opd_invoice_header_footer" type="checkbox" class="rounded border-slate-300" />
                                    <span>OPD Invoice: Header/Footer দেখাবেন</span>
                                </label>

                                <label class="flex items-center gap-2 text-sm">
                                    <input id="ipd_invoice_header_footer" v-model="form.ipd_invoice_header_footer" type="checkbox" class="rounded border-slate-300" />
                                    <span>IPD Invoice: Header/Footer দেখাবেন</span>
                                </label>

                                <label class="flex items-center gap-2 text-sm">
                                    <input id="opd_prescription_header_footer" v-model="form.opd_prescription_header_footer" type="checkbox" class="rounded border-slate-300" />
                                    <span>OPD Prescription: Header/Footer দেখাবেন</span>
                                </label>

                                <label class="flex items-center gap-2 text-sm">
                                    <input id="ipd_prescription_header_footer" v-model="form.ipd_prescription_header_footer" type="checkbox" class="rounded border-slate-300" />
                                    <span>IPD Prescription: Header/Footer দেখাবেন</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end mt-6">
                        <button type="button" @click.prevent="submit" class="inline-flex items-center rounded-md bg-green-600 px-6 py-3 text-base font-bold text-white shadow-sm transition hover:bg-green-700">
                            Save Other Setting
                        </button>
                    </div>
                </div>

                <div v-show="activeSettingsSection === 'sms'" class="border rounded-md p-4">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <InputLabel for="sms_enabled" value="Enable SMS Gateway" />
                            <select id="sms_enabled" v-model="form.sms_enabled"
                                class="block w-full p-2 text-sm rounded-md border-slate-300">
                                <option :value="true">Enabled</option>
                                <option :value="false">Disabled</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.sms_enabled" />
                        </div>

                        <div>
                            <InputLabel for="sms_api_url" value="SMS API URL" />
                            <input id="sms_api_url" v-model="form.sms_api_url" type="text" placeholder="https://api.sms.gateway/send"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.sms_api_url" />
                        </div>

                        <form @submit.prevent class="md:col-span-2">
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div>
                                    <InputLabel for="sms_api_key" value="API Key / Secret" />
                                    <input id="sms_api_key" v-model="form.sms_api_key" type="password"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                    <InputError class="mt-2" :message="form.errors.sms_api_key" />
                                </div>

                                <div>
                                    <InputLabel for="sms_sender_id" value="Sender ID" />
                                    <input id="sms_sender_id" v-model="form.sms_sender_id" type="text" placeholder="TOAMED"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                    <InputError class="mt-2" :message="form.errors.sms_sender_id" />
                                </div>
                            </div>
                        </form>

                        <div>
                            <InputLabel for="sms_route" value="Route / DLT Template ID" />
                            <input id="sms_route" v-model="form.sms_route" type="text" placeholder="default"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                            <InputError class="mt-2" :message="form.errors.sms_route" />
                        </div>

                        <div class="flex items-center gap-2">
                            <input id="sms_is_unicode" v-model="form.sms_is_unicode" type="checkbox" class="rounded border-slate-300" />
                            <label for="sms_is_unicode" class="text-sm">Send as Unicode (for Bangla)</label>
                        </div>

                        <div class="md:col-span-2">
                            <InputLabel for="sms_additional_params" value="Additional Params" />
                            <textarea id="sms_additional_params" v-model="form.sms_additional_params" rows="3" placeholder="key1=value1&key2=value2"
                                class="block w-full p-2 text-sm rounded-md border-slate-300"></textarea>
                            <p class="mt-1 text-xs text-slate-500">Extra query string params appended to the SMS API request.</p>
                            <InputError class="mt-2" :message="form.errors.sms_additional_params" />
                        </div>

                        <div class="md:col-span-2 text-xs text-slate-600">
                            Bulk SMS management is in the <a href="/backend/bulk-sms" class="text-sky-600 underline">Bulk SMS</a> page.
                        </div>
                    </div>

                    <div class="flex justify-end mt-6">
                        <button type="button" @click.prevent="submit" class="inline-flex items-center rounded-md bg-green-600 px-6 py-3 text-base font-bold text-white shadow-sm transition hover:bg-green-700">
                            Save SMS Setting
                        </button>
                    </div>
                </div>

                <div v-show="activeSettingsSection === 'module' || lockedModuleSection" class="border rounded-md p-4">
                    <div class="mb-4 flex flex-wrap items-start gap-3">
                        <div v-if="!isModuleSectionLocked" class="grid grid-cols-1 gap-2 md:grid-cols-3">
                        <button
                            type="button"
                            class="w-full px-3 py-2 text-left text-xs font-semibold rounded border flex items-center justify-between"
                            :class="moduleSections.attendance ? 'bg-cyan-100 border-cyan-300 text-cyan-800' : 'bg-white border-slate-300 text-slate-700'"
                            @click="toggleModuleSection('attendance')"
                        >
                            <span>Attendance Module</span>
                            <span>{{ moduleSections.attendance ? '▲' : '▼' }}</span>
                        </button>
                        <button
                            type="button"
                            class="w-full px-3 py-2 text-left text-xs font-semibold rounded border flex items-center justify-between"
                            :class="moduleSections.pathology ? 'bg-emerald-100 border-emerald-300 text-emerald-800' : 'bg-white border-slate-300 text-slate-700'"
                            @click="toggleModuleSection('pathology')"
                        >
                            <span>Pathology Machine Module</span>
                            <span>{{ moduleSections.pathology ? '▲' : '▼' }}</span>
                        </button>
                        <button
                            type="button"
                            class="w-full px-3 py-2 text-left text-xs font-semibold rounded border flex items-center justify-between"
                            :class="moduleSections.payroll ? 'bg-violet-100 border-violet-300 text-violet-800' : 'bg-white border-slate-300 text-slate-700'"
                            @click="toggleModuleSection('payroll')"
                        >
                            <span>Payroll Module</span>
                            <span>{{ moduleSections.payroll ? '▲' : '▼' }}</span>
                        </button>
                        <button
                            type="button"
                            class="w-full px-3 py-2 text-left text-xs font-semibold rounded border flex items-center justify-between"
                            :class="moduleSections.reporting ? 'bg-indigo-100 border-indigo-300 text-indigo-800' : 'bg-white border-slate-300 text-slate-700'"
                            @click="toggleModuleSection('reporting')"
                        >
                            <span>Reporting Module</span>
                            <span>{{ moduleSections.reporting ? '▲' : '▼' }}</span>
                        </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">


                        <div v-show="moduleSections.pathology">
                            <InputLabel for="pathology_enabled" value="Pathology Integration" />
                            <select id="pathology_enabled" v-model="deviceOptions.pathology.enabled"
                                class="block w-full p-2 text-sm rounded-md border-slate-300">
                                <option :value="true">Enabled</option>
                                <option :value="false">Disabled</option>
                            </select>
                        </div>

                        <div v-show="moduleSections.pathology">
                            <InputLabel for="pathology_communication_mode" value="Communication Mode" />
                            <select id="pathology_communication_mode" v-model="deviceOptions.pathology.communication_mode"
                                class="block w-full p-2 text-sm rounded-md border-slate-300">
                                <option value="hl7_mllp">HL7 MLLP</option>
                                <option value="astm_tcp">ASTM over TCP</option>
                                <option value="rest_api">REST API</option>
                                <option value="file_drop">Shared Folder File Drop</option>
                            </select>
                        </div>

                        <div v-show="moduleSections.pathology">
                            <InputLabel for="pathology_timeout_seconds" value="Timeout (seconds)" />
                            <input id="pathology_timeout_seconds" v-model.number="deviceOptions.pathology.timeout_seconds" type="number" min="5" step="1"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                        </div>

                        <div v-show="moduleSections.pathology" class="md:col-span-2 lg:col-span-2">
                            <InputLabel for="pathology_webhook_endpoint_url" value="Webhook Endpoint URL" />
                            <input id="pathology_webhook_endpoint_url" v-model="deviceOptions.pathology.webhook_endpoint_url" type="text"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                        </div>

                        <div v-show="moduleSections.pathology">
                            <InputLabel for="pathology_ack_response_mode" value="ACK Response Mode" />
                            <select id="pathology_ack_response_mode" v-model="deviceOptions.pathology.ack_response_mode"
                                class="block w-full p-2 text-sm rounded-md border-slate-300">
                                <option value="auto">Auto (HL7/ASTM => Plain)</option>
                                <option value="json">JSON</option>
                                <option value="plain">Plain Text</option>
                                <option value="hl7">HL7 ACK</option>
                            </select>
                        </div>

                        <div v-show="moduleSections.pathology" class="flex items-end pb-2">
                            <label class="flex items-center gap-2 text-sm font-medium text-emerald-800">
                                <input v-model="deviceOptions.pathology.save_raw_payload" type="checkbox" class="rounded border-slate-300" />
                                Save Raw Payload to Logs
                            </label>
                        </div>

                        <div v-show="moduleSections.pathology">
                            <InputLabel for="pathology_ack_success_text" value="ACK Success Text" />
                            <input id="pathology_ack_success_text" v-model="deviceOptions.pathology.ack_success_text" type="text"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                        </div>

                        <div v-show="moduleSections.pathology">
                            <InputLabel for="pathology_ack_failure_text" value="ACK Failure Text" />
                            <input id="pathology_ack_failure_text" v-model="deviceOptions.pathology.ack_failure_text" type="text"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                        </div>

                        <div v-show="moduleSections.pathology">
                            <InputLabel for="pathology_retry_limit" value="Retry Limit" />
                            <input id="pathology_retry_limit" v-model.number="deviceOptions.pathology.retry_limit" type="number" min="0" step="1"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                        </div>

                        <div v-show="moduleSections.pathology" class="flex items-end pb-2">
                            <label class="flex items-center gap-2 text-sm font-medium text-emerald-800">
                                <input v-model="deviceOptions.pathology.auto_import_results" type="checkbox" class="rounded border-slate-300" />
                                Auto Import Result
                            </label>
                        </div>

                        <div v-show="moduleSections.pathology" class="flex items-end pb-2">
                            <label class="flex items-center gap-2 text-sm font-medium text-emerald-800">
                                <input v-model="deviceOptions.pathology.require_acknowledgement" type="checkbox" class="rounded border-slate-300" />
                                Require ACK from Software
                            </label>
                        </div>

                        <div v-show="moduleSections.pathology" class="md:col-span-2 lg:col-span-3 p-3 rounded-md border border-teal-200 bg-teal-50">
                            <h3 class="text-sm font-semibold text-teal-800 mb-2">Hematology Analyzer</h3>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <InputLabel for="hematology_enabled" value="Enable Analyzer" />
                                    <select id="hematology_enabled" v-model="deviceOptions.pathology.hematology.enabled"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300">
                                        <option :value="true">Enabled</option>
                                        <option :value="false">Disabled</option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel for="hematology_vendor" value="Vendor" />
                                    <input id="hematology_vendor" v-model="deviceOptions.pathology.hematology.vendor" type="text" placeholder="Mindray / Sysmex"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>
                                <div>
                                    <InputLabel for="hematology_model" value="Model" />
                                    <input id="hematology_model" v-model="deviceOptions.pathology.hematology.model" type="text" placeholder="BC-30s"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>
                                <div>
                                    <InputLabel for="hematology_protocol" value="Protocol" />
                                    <select id="hematology_protocol" v-model="deviceOptions.pathology.hematology.protocol"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300">
                                        <option value="hl7_mllp">HL7 MLLP</option>
                                        <option value="astm_tcp">ASTM TCP</option>
                                        <option value="rest_api">REST API</option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel for="hematology_host" value="Host/IP" />
                                    <input id="hematology_host" v-model="deviceOptions.pathology.hematology.host" type="text" placeholder="192.168.1.25"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>
                                <div>
                                    <InputLabel for="hematology_port" value="Port" />
                                    <input id="hematology_port" v-model="deviceOptions.pathology.hematology.port" type="text" placeholder="2575"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>
                                <div>
                                    <InputLabel for="hematology_api_url" value="API URL (if REST)" />
                                    <input id="hematology_api_url" v-model="deviceOptions.pathology.hematology.api_url" type="text" placeholder="http://analyzer.local/api/results"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>
                                <div>
                                    <InputLabel for="hematology_device_identifier" value="Device Identifier" />
                                    <input id="hematology_device_identifier" v-model="deviceOptions.pathology.hematology.device_identifier" type="text" placeholder="hema-01"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>
                            </div>
                        </div>

                        <div v-show="moduleSections.pathology" class="md:col-span-2 lg:col-span-3 p-3 rounded-md border border-lime-200 bg-lime-50">
                            <h3 class="text-sm font-semibold text-lime-800 mb-2">Ultrasound Integration</h3>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <InputLabel for="ultrasound_enabled" value="Enable Ultrasound" />
                                    <select id="ultrasound_enabled" v-model="deviceOptions.pathology.ultrasound.enabled"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300">
                                        <option :value="true">Enabled</option>
                                        <option :value="false">Disabled</option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel for="ultrasound_vendor" value="Vendor" />
                                    <input id="ultrasound_vendor" v-model="deviceOptions.pathology.ultrasound.vendor" type="text" placeholder="GE / Philips"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>
                                <div>
                                    <InputLabel for="ultrasound_model" value="Model" />
                                    <input id="ultrasound_model" v-model="deviceOptions.pathology.ultrasound.model" type="text" placeholder="LOGIQ P9"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>
                                <div>
                                    <InputLabel for="ultrasound_protocol" value="Protocol" />
                                    <select id="ultrasound_protocol" v-model="deviceOptions.pathology.ultrasound.protocol"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300">
                                        <option value="dicom_mwl">DICOM MWL</option>
                                        <option value="hl7_orm_oru">HL7 ORM/ORU</option>
                                        <option value="rest_api">REST API</option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel for="ultrasound_host" value="Host/IP" />
                                    <input id="ultrasound_host" v-model="deviceOptions.pathology.ultrasound.host" type="text" placeholder="192.168.1.30"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>
                                <div>
                                    <InputLabel for="ultrasound_port" value="Port" />
                                    <input id="ultrasound_port" v-model="deviceOptions.pathology.ultrasound.port" type="text" placeholder="104"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>
                                <div>
                                    <InputLabel for="ultrasound_ae_title" value="AE Title" />
                                    <input id="ultrasound_ae_title" v-model="deviceOptions.pathology.ultrasound.ae_title" type="text" placeholder="HMS"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>
                                <div>
                                    <InputLabel for="ultrasound_accession_key" value="Accession Key" />
                                    <input id="ultrasound_accession_key" v-model="deviceOptions.pathology.ultrasound.accession_key" type="text" placeholder="accession_no"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>
                            </div>
                        </div>

                        <form @submit.prevent v-show="moduleSections.pathology" class="md:col-span-2 lg:col-span-3 p-3 rounded-md border border-amber-200 bg-amber-50">
                            <h3 class="text-sm font-semibold text-amber-800 mb-2">Security + Field Mapping</h3>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <InputLabel for="pathology_tls_enabled" value="TLS" />
                                    <select id="pathology_tls_enabled" v-model="deviceOptions.pathology.security.tls_enabled"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300">
                                        <option :value="true">Enabled</option>
                                        <option :value="false">Disabled</option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel for="pathology_api_token" value="API Token" />
                                    <input id="pathology_api_token" v-model="deviceOptions.pathology.security.api_token" type="password"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>
                                <div>
                                    <InputLabel for="pathology_shared_secret" value="Shared Secret" />
                                    <input id="pathology_shared_secret" v-model="deviceOptions.pathology.security.shared_secret" type="password"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>
                                <div>
                                    <InputLabel for="mapping_sample_id_key" value="Sample ID Key" />
                                    <input id="mapping_sample_id_key" v-model="deviceOptions.pathology.mapping.sample_id_key" type="text"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>
                                <div>
                                    <InputLabel for="mapping_patient_id_key" value="Patient ID Key" />
                                    <input id="mapping_patient_id_key" v-model="deviceOptions.pathology.mapping.patient_id_key" type="text"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>
                                <div>
                                    <InputLabel for="mapping_test_code_key" value="Test Code Key" />
                                    <input id="mapping_test_code_key" v-model="deviceOptions.pathology.mapping.test_code_key" type="text"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>
                                <div>
                                    <InputLabel for="mapping_result_value_key" value="Result Value Key" />
                                    <input id="mapping_result_value_key" v-model="deviceOptions.pathology.mapping.result_value_key" type="text"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>
                                <div>
                                    <InputLabel for="mapping_result_unit_key" value="Result Unit Key" />
                                    <input id="mapping_result_unit_key" v-model="deviceOptions.pathology.mapping.result_unit_key" type="text"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>
                                <div>
                                    <InputLabel for="mapping_reference_range_key" value="Reference Range Key" />
                                    <input id="mapping_reference_range_key" v-model="deviceOptions.pathology.mapping.reference_range_key" type="text"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>
                                <div>
                                    <InputLabel for="mapping_result_time_key" value="Result Time Key" />
                                    <input id="mapping_result_time_key" v-model="deviceOptions.pathology.mapping.result_time_key" type="text"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-amber-700">
                                Recommended: Hematology-তে HL7/ASTM, Ultrasound-এ DICOM MWL ব্যবহার করুন। এগুলো worldwide widely adopted এবং vendor compatible।
                            </p>
                        </form>

                        <div v-show="moduleSections.attendance">
                            <InputLabel for="attendance_device_enabled" value="Enable Device" />
                            <select id="attendance_device_enabled" v-model="form.attendance_device_enabled"
                                class="block w-full p-2 text-sm rounded-md border-slate-300">
                                <option :value="false">Disabled</option>
                                <option :value="true">Enabled</option>
                            </select>
                        </div>

                        <div v-show="moduleSections.attendance">
                            <InputLabel for="attendance_device_type" value="Device Type" />
                            <select id="attendance_device_type" v-model="form.attendance_device_type"
                                class="block w-full p-2 text-sm rounded-md border-slate-300">
                                <option value="face">Face</option>
                                <option value="fingerprint">Fingerprint</option>
                                <option value="both">Both</option>
                            </select>
                        </div>

                        <div v-show="moduleSections.attendance">
                            <InputLabel for="attendance_device_identifier" value="Identifier (optional)" />
                            <input id="attendance_device_identifier" v-model="form.attendance_device_identifier" type="text"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                        </div>

                        <div v-show="moduleSections.attendance">
                            <InputLabel for="attendance_device_ip" value="Device IP (optional)" />
                            <input id="attendance_device_ip" v-model="form.attendance_device_ip" type="text"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                        </div>

                        <div v-show="moduleSections.attendance">
                            <InputLabel for="attendance_device_port" value="Device Port (optional)" />
                            <input id="attendance_device_port" v-model="form.attendance_device_port" type="text"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                        </div>

                        <form @submit.prevent v-show="moduleSections.attendance">
                            <InputLabel for="attendance_device_secret" value="Device Secret (optional)" />
                            <input id="attendance_device_secret" v-model="form.attendance_device_secret" type="password"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                        </form>

                        <div v-show="moduleSections.attendance" class="md:col-span-2 lg:col-span-3 p-3 rounded-md border border-sky-200 bg-sky-50">
                            <h3 class="text-sm font-semibold text-sky-800 mb-2">Device Integration Modules</h3>
                            <p class="text-xs text-sky-700 mb-3">
                                ডিভাইস থেকে attendance sync হলে কোন কোন মডিউলে data ব্যবহার হবে তা নির্বাচন করুন।
                            </p>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3 text-sm">
                                <label class="flex items-center gap-2">
                                    <input v-model="deviceOptions.modules.fingerprint" type="checkbox" class="rounded border-slate-300" />
                                    <span>Fingerprint Attendance</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input v-model="deviceOptions.modules.face_attendance" type="checkbox" class="rounded border-slate-300" />
                                    <span>Face Attendance</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input v-model="deviceOptions.modules.leave" type="checkbox" class="rounded border-slate-300" />
                                    <span>Leave</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input v-model="deviceOptions.modules.duty_roster" type="checkbox" class="rounded border-slate-300" />
                                    <span>Duty Roster</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input v-model="deviceOptions.modules.salary_sheet" type="checkbox" class="rounded border-slate-300" />
                                    <span>Salary Sheet</span>
                                </label>
                            </div>
                        </div>

                        <div v-show="moduleSections.payroll" class="md:col-span-2 lg:col-span-3 p-3 rounded-md border border-violet-200 bg-violet-50">
                            <h3 class="text-sm font-semibold text-violet-800 mb-2">Payroll Module Settings (Salary Sheet Default)</h3>
                            <p class="text-xs text-violet-700 mb-3">
                                Face attendance + duty roster payroll calculation এর default setting এখানে দিন। Salary Sheet page এই মানগুলো auto load হবে।
                            </p>

                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <InputLabel for="payroll_late_fee_per_late" value="Late Fee / Late Day" />
                                    <input
                                        id="payroll_late_fee_per_late"
                                        v-model.number="deviceOptions.payroll.salary_sheet.late_fee_per_late"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300"
                                    />
                                </div>

                                <div>
                                    <InputLabel for="payroll_overtime_multiplier" value="Overtime Multiplier" />
                                    <input
                                        id="payroll_overtime_multiplier"
                                        v-model.number="deviceOptions.payroll.salary_sheet.overtime_multiplier"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300"
                                    />
                                </div>

                                <div>
                                    <InputLabel for="payroll_late_grace_days" value="Late Grace Days" />
                                    <input
                                        id="payroll_late_grace_days"
                                        v-model.number="deviceOptions.payroll.salary_sheet.late_grace_days"
                                        type="number"
                                        min="0"
                                        step="1"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300"
                                    />
                                </div>

                                <div>
                                    <InputLabel for="payroll_late_deduction_rate" value="Late Deduction Rate" />
                                    <input
                                        id="payroll_late_deduction_rate"
                                        v-model.number="deviceOptions.payroll.salary_sheet.late_deduction_rate"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300"
                                    />
                                </div>

                                <div>
                                    <InputLabel for="payroll_late_highlight_limit" value="Late Alert Limit" />
                                    <input
                                        id="payroll_late_highlight_limit"
                                        v-model.number="deviceOptions.payroll.salary_sheet.late_highlight_limit"
                                        type="number"
                                        min="0"
                                        step="1"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300"
                                    />
                                </div>

                                <div>
                                    <InputLabel for="payroll_unpaid_highlight_limit" value="Unpaid Alert Limit" />
                                    <input
                                        id="payroll_unpaid_highlight_limit"
                                        v-model.number="deviceOptions.payroll.salary_sheet.unpaid_highlight_limit"
                                        type="number"
                                        min="0"
                                        step="1"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300"
                                    />
                                </div>

                                <div>
                                    <InputLabel for="payroll_short_late_limit_minutes" value="Short Late Limit (Min)" />
                                    <input
                                        id="payroll_short_late_limit_minutes"
                                        v-model.number="deviceOptions.payroll.salary_sheet.short_late_limit_minutes"
                                        type="number"
                                        min="0"
                                        step="1"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300"
                                    />
                                </div>

                                <div class="flex items-end pb-2">
                                    <label class="flex items-center gap-2 text-sm font-medium text-violet-800">
                                        <input
                                            v-model="deviceOptions.payroll.salary_sheet.waive_short_late"
                                            type="checkbox"
                                            class="rounded border-slate-300"
                                        />
                                        Manual Off: short late salary কাটবে না
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div v-show="moduleSections.reporting" class="md:col-span-2 lg:col-span-3 p-3 rounded-md border border-indigo-200 bg-indigo-50">
                            <h3 class="text-sm font-semibold text-indigo-800 mb-2">Reporting Module Settings</h3>
                            <p class="text-xs text-indigo-700 mb-3">
                                Report print page এ signature block এর উপরে-নিচে এবং পুরো report content এর left-right position এখান থেকে adjust করুন।
                            </p>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div>
                                    <InputLabel for="report_page_margin_top" value="Report Page Top Margin (px)" />
                                    <input
                                        id="report_page_margin_top"
                                        v-model.number="deviceOptions.reporting.layout.page_margin_top"
                                        type="number"
                                        min="0"
                                        step="1"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300"
                                    />
                                    <p class="mt-1 text-xs text-indigo-700">এই মান পুরো রিপোর্ট কনটেন্টকে নিচের দিকে নামাবে।</p>
                                </div>
                                <div>
                                    <InputLabel for="report_signature_margin_top" value="Signature Margin Top (px)" />
                                    <input
                                        id="report_signature_margin_top"
                                        v-model.number="deviceOptions.reporting.signature.margin_top"
                                        type="number"
                                        min="0"
                                        step="1"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300"
                                    />
                                </div>
                                <div>
                                    <InputLabel for="report_signature_margin_left" value="Signature Margin Left (px)" />
                                    <input
                                        id="report_signature_margin_left"
                                        v-model.number="deviceOptions.reporting.signature.margin_left"
                                        type="number"
                                        min="0"
                                        step="1"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300"
                                    />
                                    <p class="mt-1 text-xs text-indigo-700">এই মান পুরো report content কে ডানদিকে সরাবে।</p>
                                    <p class="mt-1 text-xs text-indigo-700">কিন্তু কাটবে না, safe page padding হিসেবেই apply হবে।</p>
                                </div>
                            </div>
                        </div>

                        <div v-show="moduleSections.attendance" class="md:col-span-2 lg:col-span-3 p-3 rounded-md border border-emerald-200 bg-emerald-50">
                            <h3 class="text-sm font-semibold text-emerald-800 mb-2">ZKTeco + Webhook Integration</h3>
                            <p class="text-xs text-emerald-700 mb-3">
                                ZKTeco device থেকে webhook push করার জন্য এই ফিল্ডগুলো কনফিগার করুন।
                            </p>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                                <div>
                                    <InputLabel for="zkteco_enabled" value="ZKTeco Integration" />
                                    <select id="zkteco_enabled" v-model="deviceOptions.zkteco.enabled"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300">
                                        <option :value="true">Enabled</option>
                                        <option :value="false">Disabled</option>
                                    </select>
                                </div>

                                <div>
                                    <InputLabel for="zkteco_protocol" value="Data Flow" />
                                    <select id="zkteco_protocol" v-model="deviceOptions.zkteco.protocol"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300">
                                        <option value="push_webhook">Push (Webhook)</option>
                                        <option value="pull_sync">Pull (Sync Command)</option>
                                    </select>
                                </div>

                                <div>
                                    <InputLabel for="zkteco_model_hint" value="Model Hint" />
                                    <input id="zkteco_model_hint" v-model="deviceOptions.zkteco.model_hint" type="text"
                                        placeholder="ZKTeco K40/uFace/F18"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>

                                <div class="md:col-span-2 lg:col-span-3">
                                    <InputLabel for="webhook_endpoint_url" value="Webhook URL" />
                                    <input id="webhook_endpoint_url" v-model="deviceOptions.webhook.endpoint_url" type="text"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                    <p class="mt-1 text-xs text-slate-600">
                                        ডিভাইসে এই URL সেট করুন। Example payload keys: device_id, employee_code, type, timestamp.
                                    </p>
                                </div>

                                <div>
                                    <InputLabel for="webhook_signature_algorithm" value="Signature Algorithm" />
                                    <select id="webhook_signature_algorithm" v-model="deviceOptions.webhook.signature_algorithm"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300">
                                        <option value="sha256">sha256</option>
                                        <option value="sha1">sha1</option>
                                    </select>
                                </div>

                                <div>
                                    <InputLabel for="webhook_signature_header" value="Signature Header" />
                                    <input id="webhook_signature_header" v-model="deviceOptions.webhook.signature_header" type="text"
                                        placeholder="X-Device-Signature"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>

                                <div>
                                    <InputLabel for="webhook_secret_header" value="Secret Header" />
                                    <input id="webhook_secret_header" v-model="deviceOptions.webhook.secret_header" type="text"
                                        placeholder="X-Device-Secret"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>

                                <div>
                                    <InputLabel for="payload_device_key" value="Payload Device Key" />
                                    <input id="payload_device_key" v-model="deviceOptions.webhook.payload_device_key" type="text"
                                        placeholder="device_id"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>

                                <div>
                                    <InputLabel for="payload_employee_key" value="Payload Employee Key" />
                                    <input id="payload_employee_key" v-model="deviceOptions.webhook.payload_employee_key" type="text"
                                        placeholder="employee_code"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>

                                <div>
                                    <InputLabel for="payload_type_key" value="Payload Type Key" />
                                    <input id="payload_type_key" v-model="deviceOptions.webhook.payload_type_key" type="text"
                                        placeholder="type"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>

                                <div>
                                    <InputLabel for="payload_timestamp_key" value="Payload Timestamp Key" />
                                    <input id="payload_timestamp_key" v-model="deviceOptions.webhook.payload_timestamp_key" type="text"
                                        placeholder="timestamp"
                                        class="block w-full p-2 text-sm rounded-md border-slate-300" />
                                </div>
                            </div>
                        </div>

                        <div v-show="moduleSections.attendance">
                            <InputLabel for="sync_mode" value="Sync Mode" />
                            <select id="sync_mode" v-model="deviceOptions.sync_mode"
                                class="block w-full p-2 text-sm rounded-md border-slate-300">
                                <option value="realtime">Realtime</option>
                                <option value="batched">Batched</option>
                            </select>
                        </div>

                        <div v-show="moduleSections.attendance">
                            <InputLabel for="sync_interval_minutes" value="Sync Interval (minutes)" />
                            <input id="sync_interval_minutes" v-model.number="deviceOptions.sync_interval_minutes" type="number" min="1"
                                class="block w-full p-2 text-sm rounded-md border-slate-300" />
                        </div>

                        <div v-show="moduleSections.attendance" class="md:col-span-2 lg:col-span-3">
                            <InputLabel for="attendance_device_options" value="Options (JSON Preview)" />
                            <textarea id="attendance_device_options" v-model="form.attendance_device_options" rows="3" readonly
                                class="block w-full p-2 text-sm rounded-md border-slate-300 bg-slate-50"></textarea>
                        </div>

                        <!-- Save buttons for each module -->
                        <div v-show="moduleSections.pathology" class="md:col-span-2 lg:col-span-3 flex justify-start pt-4 border-t border-slate-200">
                            <button type="button" @click.prevent="submit" class="inline-flex items-center rounded-md px-6 py-3 text-base font-bold text-white bg-green-600 hover:bg-green-700 transition-colors">Save Machine Integration Setting</button>
                        </div>

                        <div v-show="moduleSections.attendance" class="md:col-span-2 lg:col-span-3 flex justify-start pt-4 border-t border-slate-200">
                            <button type="button" @click.prevent="submit" class="inline-flex items-center rounded-md px-6 py-3 text-base font-bold text-white bg-green-600 hover:bg-green-700 transition-colors">Save Attendance Module Setting</button>
                        </div>

                        <div v-show="moduleSections.payroll" class="md:col-span-2 lg:col-span-3 flex justify-start pt-4 border-t border-slate-200">
                            <button type="button" @click.prevent="submit" class="inline-flex items-center rounded-md px-6 py-3 text-base font-bold text-white bg-green-600 hover:bg-green-700 transition-colors">Save Payroll Module Setting</button>
                        </div>

                        <div v-show="moduleSections.reporting" class="md:col-span-2 lg:col-span-3 flex justify-start pt-4 border-t border-slate-200">
                            <button type="button" @click.prevent="submit" class="inline-flex items-center rounded-md px-6 py-3 text-base font-bold text-white bg-green-600 hover:bg-green-700 transition-colors">Save Report Settings</button>
                        </div>
                    </div>
                </div>

                    </div>
                </div>

                <transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="translate-y-2 opacity-0"
                    enter-to-class="translate-y-0 opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="translate-y-0 opacity-100"
                    leave-to-class="translate-y-2 opacity-0"
                >
                    <div
                        v-if="showSectionToast"
                        class="fixed bottom-5 right-5 z-50 rounded-lg border border-sky-300 bg-white px-4 py-2 text-sm font-semibold text-sky-800 shadow-lg"
                    >
                        {{ sectionToastMessage }}
                    </div>
                </transition>
            </div>
        </div>
    </BackendLayout>
</template>