<script setup>
import { ref, computed, watch, onBeforeUnmount, onMounted } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';

const page = usePage();
const props = defineProps({
  datas: Object,
  mode: { type: String, default: 'doctor' },
  companyName: { type: String, default: '' },
  filters: Object,
  term: String,
  meta: Object,
});

const filtersLocal = ref({
  q: '',
  from: '',
  to: '',
  mode: props.mode ?? 'doctor',
  numOfData: 10,
  subcat: '',
});

const categories = ref([]);
const categorySuggestionsOpen = ref(false);
const activeCategorySuggestion = ref(-1);
const filteredCategorySuggestions = computed(() => {
  const search = String(filtersLocal.value.subcat ?? '').trim().toLowerCase();
  const source = Array.isArray(categories.value) ? categories.value : [];
  if (!search) return source;

  return source.filter((category) => String(category ?? '').toLowerCase().includes(search));
});
let searchTimer;
let skipNextSearchWatch = false;

const loadCategoryOptions = async () => {
  try {
    const response = await axios.get(route('backend.report-summary.index'), {
      params: { list: 1, category: 'categories' },
      headers: { Accept: 'application/json' },
    });

    const payload = response?.data ?? {};
    const names = Array.isArray(payload?.names)
      ? payload.names
      : Array.isArray(payload) ? payload : [];

    categories.value = names
      .map((value) => String(value ?? '').trim())
      .filter((value) => value !== '')
      .filter((value, index, arr) => arr.findIndex((item) => item.toLowerCase() === value.toLowerCase()) === index)
      .sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' }));
  } catch (error) {
    console.error('Failed to load report summary categories:', error);
    categories.value = [];
  }
};

const applyFilter = () => {
  clearTimeout(searchTimer);
  categorySuggestionsOpen.value = false;
  activeCategorySuggestion.value = -1;

  const query = { ...filtersLocal.value };
  const hasCategoryFilter = String(query.subcat ?? '').trim() !== '';
  const hasSearchTerm = String(query.q ?? '').trim() !== '';

  if (hasCategoryFilter && !hasSearchTerm) {
    query.mode = 'test';
  } else if (['doctor_patient', 'doctor_daily'].includes(query.mode)) {
    query.mode = 'doctor';
  }

  router.get(route('backend.report-summary.index'), query, { preserveState: true });
};

const selectCategorySuggestion = (category) => {
  filtersLocal.value.subcat = category;
  categorySuggestionsOpen.value = false;
  activeCategorySuggestion.value = -1;
  applyFilter();
};

const moveCategorySuggestion = (direction) => {
  const count = filteredCategorySuggestions.value.length;
  if (!count) return;

  categorySuggestionsOpen.value = true;
  activeCategorySuggestion.value = activeCategorySuggestion.value < 0
    ? (direction > 0 ? 0 : count - 1)
    : (activeCategorySuggestion.value + direction + count) % count;
};

const handleCategoryKeydown = (event) => {
  if (event.key === 'ArrowDown') {
    event.preventDefault();
    moveCategorySuggestion(1);
  } else if (event.key === 'ArrowUp') {
    event.preventDefault();
    moveCategorySuggestion(-1);
  } else if (event.key === 'Enter') {
    event.preventDefault();
    const activeOption = filteredCategorySuggestions.value[activeCategorySuggestion.value];
    if (categorySuggestionsOpen.value && activeOption) {
      selectCategorySuggestion(activeOption);
      return;
    }

    categorySuggestionsOpen.value = false;
    applyFilter();
  } else if (event.key === 'Escape') {
    categorySuggestionsOpen.value = false;
    activeCategorySuggestion.value = -1;
  }
};

const handleCategoryInput = () => {
  categorySuggestionsOpen.value = true;
  activeCategorySuggestion.value = filteredCategorySuggestions.value.length ? 0 : -1;
};

const handleCategoryBlur = () => {
  setTimeout(() => {
    const activeElement = document.activeElement;
    const isInsideSuggestionList = activeElement && activeElement.closest('[data-category-option]');

    if (!isInsideSuggestionList) {
      categorySuggestionsOpen.value = false;
      activeCategorySuggestion.value = -1;
    }
  }, 60);
};

onMounted(async () => {
  const navigationType = performance.getEntriesByType('navigation')[0]?.type;
  if (navigationType === 'reload' && filtersLocal.value.q) {
    skipNextSearchWatch = true;
    filtersLocal.value.q = '';
    filtersLocal.value.mode = 'doctor';
    const query = { ...filtersLocal.value };
    delete query.q;
    router.get(route('backend.report-summary.index'), query, { replace: true });
  }

  await loadCategoryOptions();
});

watch(() => filtersLocal.value.q, () => {
  if (skipNextSearchWatch) {
    skipNextSearchWatch = false;
    return;
  }

  clearTimeout(searchTimer);
  searchTimer = setTimeout(applyFilter, 350);
});

onBeforeUnmount(() => clearTimeout(searchTimer));

const rows = computed(() => props.datas?.data ?? []);

const indexOffset = computed(() => Math.max(Number(props.datas?.from ?? 1) - 1, 0));
const tableColumnCount = computed(() => activeMode.value === 'doctor_patient' ? 10 : 7);

const pageTotals = computed(() => {
  const mode = activeMode.value;
  let tests = 0;
  let amount = 0;

  (rows.value || []).forEach(r => {
    if (mode === 'test') {
      tests += Number(r.quantity ?? 0);
      amount += Number(r.price ?? 0);
    } else if (mode === 'doctor') {
      tests += Number(r.pathology_count ?? 0);
      amount += Number(r.total_amount ?? 0);
    } else if (mode === 'doctor_daily') {
      tests += Number(r.test_count ?? 0);
      amount += Number(r.total_amount ?? 0);
    } else if (mode === 'doctor_patient') {
      tests += Number(r.quantity ?? 0);
      amount += Number(r.price ?? 0);
    } else if (mode === 'technologist' || mode === 'collector' || mode === 'pathologist') {
      tests += Number(r.count ?? 1);
      amount += Number(r.price ?? 0);
    } else if (mode === 'referrer') {
      tests += Number(r.count ?? 0);
    } else {
      tests += Number(r.count ?? 0);
      amount += Number(r.price ?? 0);
    }
  });

  return { tests, amount };
});

const overallTotals = computed(() => {
  const m = props.meta ?? {};
  const tests = m.total_items ?? m.total_reports ?? m.total_collected ?? m.distinct_cases ?? props.datas?.total ?? 0;
  const amount = m.grand_total ?? null;
  const dueAmount = m.due_amount ?? null;
  const dueCases = m.due_cases ?? null;
  return { tests, amount, dueAmount, dueCases };
});

const formatCurrency = (v) => {
  const n = Number(v ?? 0);
  return new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
};

const formatDate = (value) => {
  if (!value) return '-';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  return d.toLocaleDateString();
};

const csvSafe = (value) => {
  const text = String(value ?? '');
  if (/[",\n]/.test(text)) {
    return `"${text.replace(/"/g, '""')}"`;
  }
  return text;
};

const activeMode = computed(() => {
  return props.mode ?? filtersLocal.value.mode ?? 'doctor';
});

const printCompanyName = computed(() => {
  const sharedName = props.companyName
    || page.props?.companyInfo?.name
    || page.props?.companyInfo?.company_name
    || page.props?.webSetting?.company_name
    || page.props?.websetting?.company_name
    || '';

  return String(sharedName ?? '').trim() || 'ToaMed';
});

const titleColumnLabel = computed(() => {
  const mode = activeMode.value;
  if (mode === 'doctor') return 'Doctor Name';
  if (mode === 'doctor_daily') return 'Doctor Name';
  if (mode === 'doctor_patient') return 'Doctor Name';
  if (mode === 'pathologist') return 'Pathologist Name';
  if (mode === 'technologist') return 'Technologist Name';
  if (mode === 'collector') return 'Collector Name';
  if (mode === 'referrer') return 'Referrer Name';
  return 'Test Name / Item';
});

const rowTitle = (r) => {
  const mode = activeMode.value;
  if (mode === 'doctor' || mode === 'doctor_daily' || mode === 'doctor_patient') return r.doctor_name ?? r.name ?? '—';
  if (mode === 'test') {
    if (Array.isArray(r.matched_tests) && r.matched_tests.length) return r.matched_tests.join(', ');
    return r.item_name ?? r.item ?? r.name ?? '—';
  }
  if (mode === 'pathologist') return r.reporter_name ?? r.pathologist_name ?? r.name ?? '—';
  if (mode === 'technologist') return r.reporter_name ?? r.technologist_name ?? r.name ?? '—';
  if (mode === 'collector') return r.collector_name ?? r.name ?? '—';
  if (mode === 'referrer') return r.referrer_name ?? r.name ?? '—';
  return r.name ?? r.title ?? '—';
};

const rowBillNumber = (r) => {
  if (activeMode.value === 'doctor_daily') return '-';
  return r.bill_number ?? r.case_number ?? r.billing_id ?? r.id ?? '-';
};

const rowBillingDate = (r) => {
  return r.billing_date ?? r.reported_at ?? r.sample_collected_at ?? r.created_at ?? '';
};

const rowPatientName = (r) => {
  if (activeMode.value === 'doctor_daily') return Number(r.patient_count ?? 0);
  return r.patient_name ?? r.patient?.name ?? '-';
};

const rowQuantity = (r) => {
  const mode = activeMode.value;
  if (mode === 'test') return Number(r.quantity ?? 0);
  if (mode === 'doctor') return Number(r.pathology_count ?? r.quantity ?? 0);
  if (mode === 'doctor_daily') return Number(r.test_count ?? 0);
  if (mode === 'doctor_patient') return Number(r.quantity ?? 0);
  if (mode === 'technologist' || mode === 'collector' || mode === 'pathologist') return Number(r.count ?? r.quantity ?? 1);
  if (mode === 'referrer') return Number(r.count ?? r.quantity ?? 0);
  return Number(r.quantity ?? r.count ?? 0);
};

const rowAmount = (r) => {
  const mode = activeMode.value;
  if (mode === 'doctor' || mode === 'doctor_daily') return Number(r.total_amount ?? r.price ?? 0);
  if (mode === 'doctor_patient') return Number(r.price ?? r.total_amount ?? 0);
  return Number(r.price ?? r.total_amount ?? 0);
};

const totalQuantity = computed(() => Number(overallTotals.value.tests ?? pageTotals.value.tests ?? 0));
const generatedAt = new Intl.DateTimeFormat(undefined, {
  dateStyle: 'medium',
  timeStyle: 'short',
}).format(new Date());

const csvFileName = computed(() => {
  const now = new Date();
  const y = now.getFullYear();
  const m = String(now.getMonth() + 1).padStart(2, '0');
  const d = String(now.getDate()).padStart(2, '0');
  return `report-summary-${filtersLocal.value.mode || 'all'}-${y}${m}${d}.csv`;
});

const exportCsv = () => {
  const patientColumnLabel = activeMode.value === 'doctor_daily' ? 'Patient Count' : 'Patient Name';
  const quantityColumnLabel = activeMode.value === 'doctor_daily' ? 'Test Count' : 'Quantity';
  const header = [
    'S/N',
    titleColumnLabel.value,
    'Bill Number',
    'Billing Date',
    patientColumnLabel,
    ...(activeMode.value === 'doctor_patient' ? ['Patient ID'] : []),
    ...(activeMode.value === 'doctor_patient' ? ['Category', 'Test'] : []),
    quantityColumnLabel,
    'Amount',
  ];

  const lines = [header.map(csvSafe).join(',')];
  rows.value.forEach((r, idx) => {
    const row = [
      indexOffset.value + idx + 1,
      rowTitle(r),
      rowBillNumber(r),
      formatDate(rowBillingDate(r)),
      rowPatientName(r),
      ...(activeMode.value === 'doctor_patient' ? [r.patient_id ?? ''] : []),
      ...(activeMode.value === 'doctor_patient' ? [r.category ?? '', Array.isArray(r.item_names) ? r.item_names.join(', ') : (r.item_name ?? '')] : []),
      rowQuantity(r),
      Number(rowAmount(r)).toFixed(2),
    ];

    lines.push(row.map(csvSafe).join(','));
  });

  const summaryEmptyColumns = Array(Math.max(0, header.length - 3)).fill('');
  lines.push([
    csvSafe('Grand Total'),
    ...summaryEmptyColumns,
    csvSafe(totalQuantity.value),
    csvSafe(Number(overallTotals.value.amount !== null ? overallTotals.value.amount : pageTotals.value.amount).toFixed(2)),
  ].join(','));

  lines.push([
    csvSafe('Total Quantity'),
    ...summaryEmptyColumns,
    csvSafe(totalQuantity.value),
    '',
  ].join(','));

  if (overallTotals.value.dueAmount !== null) {
    lines.push([
      csvSafe('Due Amount'),
      ...summaryEmptyColumns,
      csvSafe(overallTotals.value.dueCases),
      csvSafe(Number(overallTotals.value.dueAmount).toFixed(2)),
    ].join(','));
  }

  const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.setAttribute('download', csvFileName.value);
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
};

const printReport = () => {
  window.print();
};

const goBack = () => {
  if (window.history.length > 1) {
    window.history.back();
    return;
  }
  router.visit(route('backend.dashboard'));
};

</script>

<template>
  <BackendLayout>
    <div class="w-full mt-3">
      <section class="print-report soft-grid overflow-hidden rounded-2xl border border-slate-200/80 bg-gradient-to-br from-slate-50 via-white to-emerald-50 p-4 shadow-xl shadow-slate-200/60 dark:border-slate-700 dark:from-slate-900 dark:via-slate-900 dark:to-slate-800 dark:shadow-none md:p-6">
        <div class="print-only print-heading">
          <div>
            <p class="print-brand">{{ printCompanyName }}</p>
            <h1>Report Summary</h1>
            <p class="print-subtitle">Filtered billing and test summary</p>
          </div>
          <div class="print-meta">
            <p><strong>Printed:</strong> {{ generatedAt }}</p>
            <p><strong>Period:</strong> {{ filtersLocal.from || 'All dates' }} — {{ filtersLocal.to || 'All dates' }}</p>
            <p v-if="filtersLocal.q"><strong>Search:</strong> {{ filtersLocal.q }}</p>
            <p v-if="filtersLocal.subcat"><strong>Category:</strong> {{ filtersLocal.subcat }}</p>
          </div>
        </div>

        <div class="no-print flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div class="space-y-2">
            <h2 class="text-2xl font-bold leading-tight text-slate-800 dark:text-slate-100 md:text-3xl">
              Report Summary
            </h2>
          </div>

          <div class="flex w-full flex-col gap-2 lg:w-auto lg:flex-row lg:items-stretch">
            <button
              type="button"
              @click="goBack"
              class="no-print inline-flex items-center justify-center rounded-lg border-0 bg-red-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-red-700"
            >
              <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
              </svg>
              Back
            </button>
            <div class="no-print flex flex-wrap items-center gap-2 lg:pr-1">
              <button
                type="button"
                @click="exportCsv"
                class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300"
              >
                Export CSV
              </button>

              <button
                type="button"
                @click="printReport"
                class="inline-flex items-center rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-sky-700 transition hover:bg-sky-100 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-300"
              >
                Print View
              </button>
            </div>
          </div>
        </div>

        <div class="report-filters mt-5 rounded-2xl border border-slate-200 bg-white/90 p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900/80 md:p-4">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-7">
            <div class="xl:col-span-2">
              <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Search</label>
              <div class="flex items-center gap-2">
                <input
                  v-model="filtersLocal.q"
                  @keyup.enter="applyFilter"
                  @keydown.esc.prevent="filtersLocal.q = ''"
                  class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-emerald-300 focus:ring-2 focus:ring-emerald-100 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:border-emerald-400"
                  placeholder="Search by doctor, item, category, referrer..."
                />
                <button
                  type="button"
                  @click="applyFilter"
                  class="no-print inline-flex shrink-0 items-center rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300"
                >
                  Search
                </button>
              </div>
            </div>

            <div>
              <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">From Date</label>
              <input
                type="date"
                v-model="filtersLocal.from"
                class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-300 focus:ring-2 focus:ring-emerald-100 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
              />
            </div>

            <div>
              <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">To Date</label>
              <input
                type="date"
                v-model="filtersLocal.to"
                class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-300 focus:ring-2 focus:ring-emerald-100 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
              />
            </div>

            <div class="relative xl:col-span-2">
              <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Category</label>
              <input
                v-model="filtersLocal.subcat"
                role="combobox"
                aria-controls="report-summary-category-options"
                :aria-expanded="categorySuggestionsOpen"
                @focus="categorySuggestionsOpen = true"
                @input="handleCategoryInput"
                @keydown="handleCategoryKeydown"
                @blur="handleCategoryBlur"
                class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-emerald-300 focus:ring-2 focus:ring-emerald-100 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:border-emerald-400"
                placeholder="Search category..."
              />
              <ul
                v-if="categorySuggestionsOpen && filteredCategorySuggestions.length"
                id="report-summary-category-options"
                role="listbox"
                class="absolute z-30 mt-1 max-h-60 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-600 dark:bg-slate-800"
              >
                <li
                  v-for="(category, index) in filteredCategorySuggestions"
                  :key="category"
                  role="option"
                  :aria-selected="index === activeCategorySuggestion"
                  data-category-option
                  @mousedown.prevent="selectCategorySuggestion(category)"
                  @mouseenter="activeCategorySuggestion = index"
                  class="cursor-pointer px-3 py-2 text-sm text-slate-700 hover:bg-blue-600 hover:text-white dark:text-slate-100 dark:hover:bg-blue-600 dark:hover:text-white"
                  :class="index === activeCategorySuggestion ? 'bg-blue-600 text-white dark:bg-blue-600 dark:text-white' : ''"
                >
                  {{ category }}
                </li>
              </ul>
            </div>

            <div>
              <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Per Page</label>
              <select
                v-model="filtersLocal.numOfData"
                @change="applyFilter"
                class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-300 focus:ring-2 focus:ring-emerald-100 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
              >
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="500">500</option>
                <option value="all">All</option>
              </select>
            </div>

          </div>

        </div>

        <div
          class="report-summary-cards mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2"
          :class="activeMode === 'test' && overallTotals.dueAmount !== null ? 'xl:grid-cols-4 has-due-summary' : 'xl:grid-cols-3 without-due-summary'"
        >
          <div class="rounded-xl border border-blue-100 bg-gradient-to-br from-blue-50 to-white px-4 py-3 shadow-sm dark:border-blue-500/20 dark:from-blue-900/20 dark:to-slate-900">
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Total Tests: {{ overallTotals.tests ?? pageTotals.tests }}</p>
          </div>

          <div class="rounded-xl border border-amber-100 bg-gradient-to-br from-amber-50 to-white px-4 py-3 shadow-sm dark:border-amber-500/20 dark:from-amber-900/20 dark:to-slate-900">
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Total Cases: {{ props.meta?.total_cases ?? props.meta?.distinct_cases ?? props.datas?.total ?? '-' }}</p>
          </div>

          <div v-if="activeMode === 'test' && overallTotals.dueAmount !== null" class="due-summary-card rounded-xl border border-rose-100 bg-gradient-to-br from-rose-50 to-white px-4 py-3 shadow-sm dark:border-rose-500/20 dark:from-rose-900/20 dark:to-slate-900">
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Due Amount: {{ formatCurrency(overallTotals.dueAmount) }}</p>
            <p class="mt-1 text-xs font-medium text-slate-600 dark:text-slate-300">Due Cases: {{ overallTotals.dueCases }}</p>
          </div>

          <div class="rounded-xl border border-emerald-100 bg-gradient-to-br from-emerald-50 to-white px-4 py-3 shadow-sm dark:border-emerald-500/20 dark:from-emerald-900/20 dark:to-slate-900">
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Total Amount: {{ overallTotals.amount !== null ? formatCurrency(overallTotals.amount) : formatCurrency(pageTotals.amount) }}</p>
          </div>
        </div>

        <div class="report-table-wrap mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <div class="report-table-scroll overflow-x-auto">
            <table class="report-table min-w-full table-auto border-collapse text-sm">
              <thead>
                <tr class="bg-slate-100 text-left text-[11px] uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                  <th class="border-b border-slate-200 px-3 py-3 font-semibold dark:border-slate-700">S/N</th>
                  <th class="border-b border-slate-200 px-3 py-3 font-semibold dark:border-slate-700">{{ titleColumnLabel }}</th>
                  <th class="border-b border-slate-200 px-3 py-3 font-semibold dark:border-slate-700">Bill Number</th>
                  <th class="border-b border-slate-200 px-3 py-3 font-semibold dark:border-slate-700">Billing Date</th>
                  <th class="border-b border-slate-200 px-3 py-3 font-semibold dark:border-slate-700">{{ activeMode === 'doctor_daily' ? 'Patient Count' : 'Patient Name' }}</th>
                  <th v-if="activeMode === 'doctor_patient'" class="border-b border-slate-200 px-3 py-3 font-semibold dark:border-slate-700">Patient ID</th>
                  <th v-if="activeMode === 'doctor_patient'" class="border-b border-slate-200 px-3 py-3 font-semibold dark:border-slate-700">Category</th>
                  <th v-if="activeMode === 'doctor_patient'" class="border-b border-slate-200 px-3 py-3 font-semibold dark:border-slate-700">Tests</th>
                  <th class="border-b border-slate-200 px-3 py-3 text-right font-semibold dark:border-slate-700">{{ activeMode === 'doctor_daily' ? 'Test Count' : 'Quantity' }}</th>
                  <th class="border-b border-slate-200 px-3 py-3 text-right font-semibold dark:border-slate-700">Amount</th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="(r, idx) in rows"
                  :key="idx"
                  class="transition hover:bg-emerald-50/50 dark:hover:bg-emerald-900/10"
                >
                  <td class="border-b border-slate-100 px-3 py-3 align-top text-slate-600 dark:border-slate-800 dark:text-slate-300">
                    {{ indexOffset + idx + 1 }}
                  </td>

                  <td class="border-b border-slate-100 px-3 py-3 align-top dark:border-slate-800">
                    <p class="font-semibold text-slate-800 dark:text-slate-100">{{ rowTitle(r) }}</p>
                  </td>

                  <td class="border-b border-slate-100 px-3 py-3 align-top text-slate-700 dark:border-slate-800 dark:text-slate-200">
                    {{ rowBillNumber(r) }}
                  </td>

                  <td class="border-b border-slate-100 px-3 py-3 align-top text-slate-700 dark:border-slate-800 dark:text-slate-200">
                    {{ formatDate(rowBillingDate(r)) }}
                  </td>

                  <td class="border-b border-slate-100 px-3 py-3 align-top text-slate-700 dark:border-slate-800 dark:text-slate-200">
                    {{ rowPatientName(r) }}
                  </td>

                  <td v-if="activeMode === 'doctor_patient'" class="border-b border-slate-100 px-3 py-3 align-top text-slate-700 dark:border-slate-800 dark:text-slate-200">
                    {{ r.patient_id || '—' }}
                  </td>

                  <td v-if="activeMode === 'doctor_patient'" class="border-b border-slate-100 px-3 py-3 align-top text-slate-700 dark:border-slate-800 dark:text-slate-200">
                    {{ r.category || '—' }}
                  </td>

                  <td v-if="activeMode === 'doctor_patient'" class="border-b border-slate-100 px-3 py-3 align-top text-slate-700 dark:border-slate-800 dark:text-slate-200">
                    {{ Array.isArray(r.item_names) ? r.item_names.join(', ') : (r.item_name || '—') }}
                  </td>

                  <td class="border-b border-slate-100 px-3 py-3 align-top text-right font-semibold text-slate-800 dark:border-slate-800 dark:text-slate-100">
                    {{ rowQuantity(r) }}
                  </td>

                  <td class="border-b border-slate-100 px-3 py-3 align-top text-right font-semibold text-slate-800 dark:border-slate-800 dark:text-slate-100">
                    {{ formatCurrency(rowAmount(r)) }}
                  </td>
                </tr>

                <tr v-if="rows.length === 0">
                  <td class="px-3 py-8 text-center text-sm text-slate-500" :colspan="tableColumnCount">
                    No records found for this filter.
                  </td>
                </tr>
              </tbody>

              <tfoot>
                <tr class="bg-slate-50 text-slate-600 dark:bg-slate-900 dark:text-slate-300">
                  <td class="border-t border-slate-200 px-3 py-3" :colspan="tableColumnCount - 2">Grand Total</td>
                  <td class="border-t border-slate-200 px-3 py-3 text-right">{{ totalQuantity }}</td>
                  <td class="border-t border-slate-200 px-3 py-3 text-right font-bold text-slate-800 dark:text-slate-100">{{ formatCurrency(overallTotals.amount !== null ? overallTotals.amount : pageTotals.amount) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <div class="report-pagination no-print mt-4">
          <Pagination />
        </div>
      </section>
    </div>
  </BackendLayout>
</template>

<style scoped>
.soft-grid {
  background-image:
    radial-gradient(circle at 12% 18%, rgba(16, 185, 129, 0.12), transparent 38%),
    radial-gradient(circle at 88% 8%, rgba(14, 165, 233, 0.14), transparent 36%);
}

.print-only {
  display: none;
}

@media print {
  @page {
    size: landscape;
    margin: 12mm;
  }

  :global(body *) {
    visibility: hidden !important;
  }

  .print-report,
  .print-report * {
    visibility: visible !important;
  }

  .print-report {
    position: absolute;
    left: 0;
    top: 0;
    width: 100% !important;
    margin: 0;
    padding: 0 !important;
    border: 0 !important;
    box-shadow: none !important;
    border-radius: 0 !important;
    overflow: visible !important;
    background: #fff !important;
    color: #111827 !important;
  }

  .no-print,
  .report-filters,
  .report-pagination {
    display: none !important;
  }

  :global(.sidebar),
  :global(header),
  :global(nav) {
    display: none !important;
  }

  .soft-grid {
    background: #fff !important;
    box-shadow: none !important;
    border: 0 !important;
    padding: 0 !important;
  }

  .print-only {
    display: flex !important;
  }

  .print-heading {
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 18px;
    padding-bottom: 12px;
    border-bottom: 2px solid #0f766e;
    color: #111827 !important;
  }

  .print-brand {
    margin: 0 0 3px;
    color: #0f766e !important;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
  }

  .print-heading h1 {
    margin: 0;
    color: #111827 !important;
    font-size: 22px;
    font-weight: 700;
  }

  .print-subtitle {
    margin: 3px 0 0;
    color: #64748b !important;
    font-size: 10px;
  }

  .print-meta {
    color: #334155 !important;
    font-size: 10px;
    line-height: 1.6;
    text-align: right;
  }

  .print-meta p {
    margin: 0;
  }

  .report-summary-cards {
    display: grid !important;
    gap: 8px !important;
    margin: 0 0 14px !important;
  }

  .report-summary-cards.has-due-summary {
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
  }

  .report-summary-cards.without-due-summary {
    grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
  }

  .report-summary-cards > div {
    break-inside: avoid;
    border: 1px solid #cbd5e1 !important;
    border-radius: 6px !important;
    background: #f8fafc !important;
    box-shadow: none !important;
    padding: 9px 11px !important;
  }

  .report-summary-cards p {
    color: #0f172a !important;
    font-size: 10px !important;
  }

  .report-table-wrap {
    overflow: visible !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 6px !important;
    box-shadow: none !important;
    break-inside: auto;
  }

  .report-table-scroll {
    overflow: visible !important;
  }

  .report-table {
    width: 100% !important;
    border-collapse: collapse !important;
    font-size: 9px !important;
    color: #111827 !important;
  }

  .report-table thead {
    display: table-header-group;
  }

  .report-table tfoot {
    display: table-row-group;
  }

  .report-table tr {
    break-inside: avoid;
  }

  .report-table th {
    background: #e2e8f0 !important;
    color: #0f172a !important;
    font-size: 8px !important;
    letter-spacing: 0.04em;
    text-align: left;
  }

  .report-table th,
  .report-table td {
    border: 1px solid #cbd5e1 !important;
    color: #111827 !important;
  }

  .report-table td {
    background: #fff !important;
    padding: 6px 7px !important;
  }

  .report-table tfoot td {
    background: #f1f5f9 !important;
    font-weight: 700;
  }
}
</style>
