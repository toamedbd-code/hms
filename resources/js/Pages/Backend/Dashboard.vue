<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useDark } from '@vueuse/core';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import BarChart from '@/Components/Chart/BarChart.vue';
import PieChart from '@/Components/Chart/PieChart.vue';

const props = defineProps(['dashboardData', 'dashboardCardPermissions', 'dashboardFilter', 'departmentScope']);
const isDark = useDark();

// Format Tk.0.00
const tkFormat = (value) => {
  const num = Number(value);
  const safeValue = Number.isFinite(num) ? num : 0;
  return 'Tk.' + safeValue.toFixed(2);
};

const countFormat = (value) => {
  const num = Number(value);
  return Number.isFinite(num) ? num : 0;
};

const dashboardStats = computed(() => props.dashboardData || {});

const getStatValue = (key) => {
  const value = dashboardStats.value?.[key];
  const num = Number(value);
  return Number.isFinite(num) ? num : 0;
};

const getCategoryIcon = (name) => {
  const category = String(name || '').trim().toLowerCase();
  if (category.includes('ambulance')) return '🚑';
  if (category.includes('appointment')) return '📅';
  if (category.includes('blood')) return '🩸';
  if (category.includes('ecg') || category.includes('echo')) return '❤️';
  if (category.includes('ultrasound') || category.includes('sonogram')) return '🔊';
  if (category.includes('radiology') || category.includes('x-ray') || category.includes('xray')) return '📷';
  if (category.includes('pathology')) return '🧪';
  if (category.includes('micro') || category.includes('biology')) return '🔬';
  if (category.includes('pharmacy') || category.includes('medicine')) return '💊';
  if (category.includes('disposable')) return '🧴';
  if (category.includes('opd')) return '📄';
  if (category.includes('ipd')) return '🏥';
  return '📊';
};

const getPermissionSlug = (name) => String(name || '')
  .trim()
  .toLowerCase()
  .replace(/[^a-z0-9]+/g, '-')
  .replace(/^-+|-+$/g, '');

const statsCards = computed(() => {
  const scopedReport = {
    link: 'backend.finance-report.index',
    params: {
      report_type: props.departmentScope === 'pharmacy' ? 'pharmacy-transaction' : 'department-transaction',
      date_from: props.dashboardFilter?.from,
      date_to: props.dashboardFilter?.to,
    },
  };
  const cards = [
    { name:'Expenses', icon:'💰', key:'expenses', link:'backend.expense.index' },
    {
      name:'Pharmacy Income',
      icon:'💊',
      key:'pharmacyIncome',
      link: props.departmentScope ? 'backend.finance-report.index' : 'backend.pharmacybill.index',
      params: props.departmentScope ? {
        report_type: props.departmentScope === 'pharmacy' ? 'pharmacy-transaction' : 'department-transaction',
        date_from: props.dashboardFilter?.from,
        date_to: props.dashboardFilter?.to,
      } : {},
    },
    {
      name:'Pathology Income',
      icon:'🧪',
      key:'pathologyIncome',
      link: props.departmentScope ? 'backend.finance-report.index' : 'backend.billing.list',
      params: props.departmentScope ? {
        report_type: 'department-transaction',
        date_from: props.dashboardFilter?.from,
        date_to: props.dashboardFilter?.to,
      } : {},
    },
    {
      name:'Radiology Income',
      icon:'📷',
      key:'radiologyIncome',
      link: props.departmentScope ? 'backend.finance-report.index' : 'backend.billing.list',
      params: props.departmentScope ? {
        report_type: 'department-transaction',
        date_from: props.dashboardFilter?.from,
        date_to: props.dashboardFilter?.to,
      } : {},
    },
    { name:'Total Doctor Visit Appointment', icon:'🩺', key:'doctorVisitAppointment', value: dashboardStats.value?.doctorVisitAppointment, link:'backend.appoinment.index' },
    {
      name:'Due Pending Income',
      icon:'⏳',
      key:'pendingIncome',
      ...(props.departmentScope ? scopedReport : { link: 'backend.pending.list' }),
    },
    {
      name:'Referral Commission',
      icon:'🤝',
      key:'referralCommission',
      link:'backend.referral.index',
    },
    {
      name: props.departmentScope
        ? `${props.departmentScope.charAt(0).toUpperCase()}${props.departmentScope.slice(1)} Net Income`
        : 'Total Net Income',
      icon:'💵',
      key:'netIncome',
      ...(props.departmentScope ? scopedReport : { link: 'backend.report.index' }),
    },
    {
      name:'Total Refunds',
      icon:'🔄',
      key:'refunds',
      ...(props.departmentScope ? scopedReport : { link: 'backend.refunds.list' }),
    },
    {
      name:'Product Returns',
      icon:'↩️',
      key:'productReturns',
      link:'backend.productreturn.index',
      params: {
        return_type: 'all',
        date_from: props.dashboardFilter?.from,
        date_to: props.dashboardFilter?.to,
      },
    },
    {
      name:'Total Discount',
      icon:'🏷️',
      key:'totalDiscountAmount',
      ...(props.departmentScope ? scopedReport : { link: 'backend.report.index' }),
    },
  ];

  const visibleCards = cards.filter((card) => props.dashboardCardPermissions?.[card.key]);
  const visibleCardNames = new Set(visibleCards.map((card) => String(card.name || '').trim().toLowerCase()));
  const categoryCards = (dashboardStats.value?.categoryIncomes || [])
    .filter((category) => {
      const name = String(category.name || '').trim();
      const permissionKey = `custom-category-${getPermissionSlug(name)}`;
      return props.dashboardCardPermissions?.[permissionKey] && !visibleCardNames.has(`${name} Income`.toLowerCase());
    })
    .map((category) => ({
    name: `${String(category.name || '').trim()} Income`,
    icon: getCategoryIcon(category.name),
    key: `custom-category-${getPermissionSlug(category.name)}`,
    value: Number(category.amount) || 0,
    link: 'backend.billing.list',
    params: { category: category.name },
    }));
  const categoryCardNames = new Set([
    ...visibleCardNames,
    ...categoryCards.map((card) => card.name.toLowerCase()),
  ]);
  const mainCategoryCards = (dashboardStats.value?.mainCategoryIncomes || [])
    .filter((category) => {
      const name = String(category.name || '').trim();
      const permissionKey = `main-category-${getPermissionSlug(name)}`;
      return props.dashboardCardPermissions?.[permissionKey]
        && !categoryCardNames.has(`${name} Income`.toLowerCase());
    })
    .map((category) => ({
      name: `${String(category.name || '').trim()} Income`,
      icon: getCategoryIcon(category.name),
      key: `main-category-${getPermissionSlug(category.name)}-${category.id}`,
      value: Number(category.amount) || 0,
      link: 'backend.billing.list',
      params: { main_category: category.name },
    }));
  return [...visibleCards, ...categoryCards, ...mainCategoryCards].sort((firstCard, secondCard) => (
    String(firstCard.name || '').localeCompare(String(secondCard.name || ''), undefined, {
      sensitivity: 'base',
    })
  ));
});

const canViewExpiredMedicines = computed(() => Boolean(props.dashboardCardPermissions?.expiredMedicines));
const canViewExpiringMedicines = computed(() => Boolean(props.dashboardCardPermissions?.expiringMedicines));

const handleRefresh = (event) => {
  if (event.key === 'dashboard:refresh') {
    router.reload({ only: ['dashboardData'] });
  }
};

const handleSameTabRefresh = () => {
  router.reload({ only: ['dashboardData'] });
};

// Auto-refresh dashboard data when window regains focus
// This ensures data stays fresh after operations in invoice/billing windows
const handleWindowFocus = () => {
  console.log('[Dashboard] Window regained focus, refreshing dashboard data...');
  router.reload({ only: ['dashboardData'] });
};

onMounted(() => {
  try {
    // print full dashboardData and keys for debugging
        // console logs removed to reduce noise in browser console
  } catch (e) {
    console.log('dashboardData (raw)', props.dashboardData);
  }
  window.addEventListener('storage', handleRefresh);
  window.addEventListener('dashboard:refresh', handleSameTabRefresh);
  window.addEventListener('focus', handleWindowFocus);
});

onUnmounted(() => {
  window.removeEventListener('storage', handleRefresh);
  window.removeEventListener('dashboard:refresh', handleSameTabRefresh);
  window.removeEventListener('focus', handleWindowFocus);
});
</script>

<template>
  <BackendLayout>
    <section class="w-full transition duration-700 ease-in-out">
      <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        <template v-for="card in statsCards" :key="card.key">
          <Link
            :href="route(card.link, card.params || {})"
            class="flex min-w-0 items-center gap-2 rounded-lg border border-slate-200 bg-white p-2.5 text-left shadow-sm transition hover:shadow-md dark:border-slate-700 dark:bg-slate-900"
          >
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-green-500 text-xl text-white">
              {{ card.icon }}
            </div>

            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium leading-tight text-slate-900 dark:text-slate-100" :title="card.name">{{ card.name }}</p>
              <p class="mt-1 break-all text-base font-bold leading-tight text-slate-900 dark:text-slate-100">
                {{ tkFormat(card.value ?? getStatValue(card.key)) }}
              </p>
            </div>
          </Link>
        </template>
      </div>

      <div v-if="props.dashboardCardPermissions?.doctorVisitAppointment" class="mb-4 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-slate-200 px-4 py-3 font-semibold text-slate-800 dark:border-slate-700 dark:text-slate-100">
          Doctor-wise Visit Appointment
        </div>
        <div class="overflow-x-auto">
          <table class="w-full border-collapse text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-300">
              <tr>
                <th class="px-4 py-3">Doctor</th>
                <th class="px-4 py-3 text-center">Appointments</th>
                <th class="px-4 py-3 text-right">Visit Fee</th>
                <th class="px-4 py-3 text-right">Paid</th>
                <th class="px-4 py-3 text-right">Due</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="doctor in dashboardStats.doctorVisitAppointmentByDoctor || []" :key="doctor.doctor_id" class="border-t border-slate-100 dark:border-slate-700">
                <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ doctor.doctor_name }}</td>
                <td class="px-4 py-3 text-center text-slate-600 dark:text-slate-300">{{ countFormat(doctor.appointment_count) }}</td>
                <td class="px-4 py-3 text-right text-slate-600 dark:text-slate-300">{{ tkFormat(doctor.fee) }}</td>
                <td class="px-4 py-3 text-right font-semibold text-slate-800 dark:text-slate-100">{{ tkFormat(doctor.amount) }}</td>
                <td class="px-4 py-3 text-right text-slate-600 dark:text-slate-300">{{ tkFormat(doctor.due_amount) }}</td>
              </tr>
              <tr v-if="!(dashboardStats.doctorVisitAppointmentByDoctor || []).length">
                <td class="px-4 py-4 text-center text-slate-500 dark:text-slate-400" colspan="5">No doctor visit appointments in this date range.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
        <Link v-if="canViewExpiredMedicines" :href="route('backend.medicineinventory.index', { expiry_filter: 'expired' })"
          class="flex items-center rounded-lg border border-rose-200 bg-rose-50 p-4 shadow-sm transition hover:shadow-md dark:border-rose-500/40 dark:bg-slate-900">
          <div class="mr-3 flex h-12 w-12 items-center justify-center rounded-lg bg-rose-500 text-xl text-white">
            !
          </div>
          <div>
            <p class="text-sm font-medium text-rose-700 dark:text-rose-300">Expired Medicines</p>
            <p class="text-lg font-bold text-rose-900 dark:text-rose-100">
              {{ countFormat($page.props.pharmacyAlerts?.medicineExpiry?.expired_count) }}
            </p>
          </div>
        </Link>

        <Link v-if="canViewExpiringMedicines" :href="route('backend.medicineinventory.index', { expiry_filter: 'expiring_soon' })"
          class="flex items-center rounded-lg border border-rose-200 bg-rose-50 p-4 shadow-sm transition hover:shadow-md dark:border-rose-500/40 dark:bg-slate-900">
          <div class="mr-3 flex h-12 w-12 items-center justify-center rounded-lg bg-rose-500 text-xl text-white">
            !!
          </div>
          <div>
            <p class="text-sm font-medium text-rose-700 dark:text-rose-300">Expiring In 30 Days</p>
            <p class="text-lg font-bold text-rose-900 dark:text-rose-100">
              {{ countFormat($page.props.pharmacyAlerts?.medicineExpiry?.expiring_soon_count) }}
            </p>
          </div>
        </Link>
      </div>
    </section>

    <section class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
      <div v-if="props.dashboardCardPermissions?.chartIncomeByDepartment" class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-slate-200 px-6 py-4 font-semibold text-slate-800 dark:border-slate-700 dark:text-slate-100">Income by Department (Bar Chart)</div>
        <div class="h-80 p-4">
          <BarChart :dashboardData="props.dashboardData" :isDark="isDark" />
        </div>
      </div>

      <div v-if="props.dashboardCardPermissions?.chartIncomeDistribution" class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-slate-200 px-6 py-4 font-semibold text-slate-800 dark:border-slate-700 dark:text-slate-100">Income Distribution (Pie Chart)</div>
        <div class="h-80 p-4">
          <PieChart :dashboardData="props.dashboardData" :isDark="isDark" />
        </div>
      </div>
    </section>

    <div class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">
      © {{ new Date().getFullYear() }} — Developed by ToaMed. All rights reserved.
    </div>
  </BackendLayout>
</template>