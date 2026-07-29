<script setup>
import { ref, computed } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  datas: Object,
  mode: { type: String, default: 'doctor' },
  filters: Object,
  term: String,
  meta: Object,
});

const filtersLocal = ref({
  q: props.filters?.q ?? props.term ?? '',
  from: props.filters?.from ?? '',
  to: props.filters?.to ?? '',
  mode: props.mode ?? 'doctor',
  subcat: props.filters?.subcat ?? null,
  numOfData: props.filters?.numOfData ?? 10,
});

const applyFilter = () => {
  const modeMeta = selectedCategoryMeta.value;
  filtersLocal.value.mode = modeMeta.mode;
  filtersLocal.value.subcat = modeMeta.subcat;

  const params = { ...filtersLocal.value };
  if (params.mode !== 'test' || !params.subcat) {
    delete params.subcat;
  }
  router.get(route('backend.report-summary.index'), params, { preserveState: true });
};

const rows = computed(() => props.datas?.data ?? []);

const indexOffset = computed(() => Math.max(Number(props.datas?.from ?? 1) - 1, 0));

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
  return { tests, amount };
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

// Category dropdown state
const categories = [
  { label: 'Doctor', value: 'doctor' },
  { label: 'Test Name', value: 'test' },
  { label: 'Pathologist', value: 'pathologist' },
  { label: 'Technologist', value: 'technologist' },
  { label: 'Collector', value: 'collector' },
  { label: 'Referrer', value: 'referrer' },
  { label: 'Radiology Tests', value: 'test:Radiology', subcat: 'Radiology' },
  { label: 'Pathology Tests', value: 'test:Pathology', subcat: 'Pathology' },
];

const selectedCategory = ref(
  filtersLocal.value.mode === 'test' && filtersLocal.value.subcat
    ? `test:${filtersLocal.value.subcat}`
    : (filtersLocal.value.mode ?? 'doctor')
);

const selectedCategoryMeta = computed(() => {
  if (typeof selectedCategory.value !== 'string') {
    return { mode: filtersLocal.value.mode, subcat: null };
  }

  if (selectedCategory.value.includes(':')) {
    const [mode, subcat] = selectedCategory.value.split(':');
    return { mode, subcat };
  }

  return { mode: selectedCategory.value, subcat: null };
});

const activeMode = computed(() => {
  return selectedCategoryMeta.value.mode ?? filtersLocal.value.mode ?? props.mode ?? 'doctor';
});

const titleColumnLabel = computed(() => {
  const mode = activeMode.value;
  if (mode === 'doctor') return 'Doctor Name';
  if (mode === 'pathologist') return 'Pathologist Name';
  if (mode === 'technologist') return 'Technologist Name';
  if (mode === 'collector') return 'Collector Name';
  if (mode === 'referrer') return 'Referrer Name';
  return 'Test Name / Item';
});

const rowTitle = (r) => {
  const mode = activeMode.value;
  if (mode === 'doctor') return r.doctor_name ?? r.name ?? '—';
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
  return r.bill_number ?? r.case_number ?? r.billing_id ?? r.id ?? '-';
};

const rowBillingDate = (r) => {
  return r.billing_date ?? r.reported_at ?? r.sample_collected_at ?? r.created_at ?? '';
};

const rowPatientName = (r) => {
  return r.patient_name ?? r.patient?.name ?? '-';
};

const rowQuantity = (r) => {
  const mode = activeMode.value;
  if (mode === 'test') return Number(r.quantity ?? 0);
  if (mode === 'doctor') return Number(r.pathology_count ?? r.quantity ?? 0);
  if (mode === 'technologist' || mode === 'collector' || mode === 'pathologist') return Number(r.count ?? r.quantity ?? 1);
  if (mode === 'referrer') return Number(r.count ?? r.quantity ?? 0);
  return Number(r.quantity ?? r.count ?? 0);
};

const rowAmount = (r) => {
  const mode = activeMode.value;
  if (mode === 'doctor') return Number(r.total_amount ?? r.price ?? 0);
  return Number(r.price ?? r.total_amount ?? 0);
};

const totalQuantity = computed(() => Number(overallTotals.value.tests ?? pageTotals.value.tests ?? 0));

const csvFileName = computed(() => {
  const now = new Date();
  const y = now.getFullYear();
  const m = String(now.getMonth() + 1).padStart(2, '0');
  const d = String(now.getDate()).padStart(2, '0');
  return `report-summary-${filtersLocal.value.mode || 'all'}-${y}${m}${d}.csv`;
});

const exportCsv = () => {
  const header = ['S/N', titleColumnLabel.value, 'Bill Number', 'Billing Date', 'Patient Name', 'Quantity', 'Amount'];

  const lines = [header.map(csvSafe).join(',')];
  rows.value.forEach((r, idx) => {
    const row = [
      indexOffset.value + idx + 1,
      rowTitle(r),
      rowBillNumber(r),
      formatDate(rowBillingDate(r)),
      rowPatientName(r),
      rowQuantity(r),
      Number(rowAmount(r)).toFixed(2),
    ];

    lines.push(row.map(csvSafe).join(','));
  });

  lines.push([
    csvSafe('Grand Total'),
    '',
    '',
    '',
    '',
    csvSafe(totalQuantity.value),
    csvSafe(Number(overallTotals.value.amount !== null ? overallTotals.value.amount : pageTotals.value.amount).toFixed(2)),
  ].join(','));

  lines.push([
    csvSafe('Total Quantity'),
    '',
    '',
    '',
    '',
    csvSafe(totalQuantity.value),
    '',
  ].join(','));

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
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
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

        <div class="mt-5 rounded-2xl border border-slate-200 bg-white/90 p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900/80 md:p-4">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-6">
            <div class="xl:col-span-2">
              <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Search</label>
              <div class="flex items-center gap-2">
                <input
                  v-model="filtersLocal.q"
                  @keyup.enter="applyFilter"
                  @keydown.esc.prevent="filtersLocal.q = ''"
                  class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-emerald-300 focus:ring-2 focus:ring-emerald-100 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:border-emerald-400"
                  placeholder="Search by doctor, test, referrer..."
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

            <div>
              <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Select Category</label>
              <select
                v-model="selectedCategory"
                class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-300 focus:ring-2 focus:ring-emerald-100 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
              >
                <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
              </select>
            </div>
          </div>

        </div>

        <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
          <div class="rounded-xl border border-blue-100 bg-gradient-to-br from-blue-50 to-white px-4 py-3 shadow-sm dark:border-blue-500/20 dark:from-blue-900/20 dark:to-slate-900">
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Total Tests: {{ overallTotals.tests ?? pageTotals.tests }}</p>
          </div>

          <div class="rounded-xl border border-amber-100 bg-gradient-to-br from-amber-50 to-white px-4 py-3 shadow-sm dark:border-amber-500/20 dark:from-amber-900/20 dark:to-slate-900">
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Total Cases: {{ props.meta?.total_cases ?? props.meta?.distinct_cases ?? props.datas?.total ?? '-' }}</p>
          </div>

          <div class="rounded-xl border border-emerald-100 bg-gradient-to-br from-emerald-50 to-white px-4 py-3 shadow-sm dark:border-emerald-500/20 dark:from-emerald-900/20 dark:to-slate-900">
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Total Amount: {{ overallTotals.amount !== null ? formatCurrency(overallTotals.amount) : formatCurrency(pageTotals.amount) }}</p>
          </div>
        </div>

        <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <div class="overflow-x-auto">
            <table class="min-w-full table-auto border-collapse text-sm">
              <thead>
                <tr class="bg-slate-100 text-left text-[11px] uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                  <th class="border-b border-slate-200 px-3 py-3 font-semibold dark:border-slate-700">S/N</th>
                  <th class="border-b border-slate-200 px-3 py-3 font-semibold dark:border-slate-700">{{ titleColumnLabel }}</th>
                  <th class="border-b border-slate-200 px-3 py-3 font-semibold dark:border-slate-700">Bill Number</th>
                  <th class="border-b border-slate-200 px-3 py-3 font-semibold dark:border-slate-700">Billing Date</th>
                  <th class="border-b border-slate-200 px-3 py-3 font-semibold dark:border-slate-700">Patient Name</th>
                  <th class="border-b border-slate-200 px-3 py-3 text-right font-semibold dark:border-slate-700">Quantity</th>
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

                  <td class="border-b border-slate-100 px-3 py-3 align-top text-right font-semibold text-slate-800 dark:border-slate-800 dark:text-slate-100">
                    {{ rowQuantity(r) }}
                  </td>

                  <td class="border-b border-slate-100 px-3 py-3 align-top text-right font-semibold text-slate-800 dark:border-slate-800 dark:text-slate-100">
                    {{ formatCurrency(rowAmount(r)) }}
                  </td>
                </tr>

                <tr v-if="rows.length === 0">
                  <td class="px-3 py-8 text-center text-sm text-slate-500" colspan="7">
                    No records found for this filter.
                  </td>
                </tr>
              </tbody>

              <tfoot>
                <tr class="bg-slate-50 text-slate-600 dark:bg-slate-900 dark:text-slate-300">
                  <td class="border-t border-slate-200 px-3 py-3" colspan="5">Grand Total</td>
                  <td class="border-t border-slate-200 px-3 py-3 text-right">{{ totalQuantity }}</td>
                  <td class="border-t border-slate-200 px-3 py-3 text-right font-bold text-slate-800 dark:text-slate-100">{{ formatCurrency(overallTotals.amount !== null ? overallTotals.amount : pageTotals.amount) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <div class="mt-4">
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

@media print {
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
    width: 100%;
    margin: 0;
    padding: 0;
    border: 0 !important;
    box-shadow: none !important;
  }

  .no-print {
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
    border: 1px solid #d1d5db !important;
    padding: 12px !important;
  }

  table {
    font-size: 11px !important;
  }

  th,
  td {
    padding: 6px !important;
  }
}
</style>
