<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useDark } from '@vueuse/core';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import BarChart from '@/Components/Chart/BarChart.vue';
import PieChart from '@/Components/Chart/PieChart.vue';

const props = defineProps(['dashboardData', 'dashboardCardPermissions']);
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

const statsCards = computed(() => {
  const cards = [
    { name:'OPD Income', icon:'📄', key:'opdIncome', link:'backend.opdpatient.index' },
    { name:'IPD Income', icon:'🏥', key:'ipdIncome', link:'backend.ipdpatient.index' },
    { name:'Pharmacy Income', icon:'💊', key:'pharmacyIncome', link:'backend.pharmacybill.index' },
    { name:'Disposable Income', icon:'🧴', key:'disposableIncome', link:'backend.itemcharge.index' },
    { name:'Pathology Income', icon:'🧪', key:'pathologyIncome', link:'backend.pathology.index' },
    { name:'Radiology Income', icon:'📷', key:'radiologyIncome', link:'backend.radiology.index' },
    { name:'ECG Income', icon:'❤️', key:'ecgIncome', link:'backend.itemcharge.index' },
    { name:'Ultrasound Income', icon:'🔊', key:'ultrasoundIncome', link:'backend.itemcharge.index' },
    { name:'Blood Bank Income', icon:'💉', key:'bloodBankIncome', link:'backend.bloodbank.index' },
    { name:'Expenses', icon:'💰', key:'expenses', link:'backend.expense.index' },
    { name:'Due Pending Income', icon:'⏳', key:'pendingIncome', link:'backend.pending.list' },
    {
      name:'Referral Commission',
      icon:'🤝',
      key:'referralCommission',
      link:'backend.referral.index',
    },
    { name:'Total Net Income', icon:'💵', key:'netIncome', link:'backend.report.index' },
    { name:'Total Refunds', icon:'🔄', key:'refunds', link:'backend.refunds.list' },
    { name:'Total Discount', icon:'🏷️', key:'totalDiscountAmount', link:'backend.report.index' },
  ];

  return cards.filter((card) => props.dashboardCardPermissions?.[card.key]);
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
            class="flex items-center rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition hover:shadow-md dark:border-slate-700 dark:bg-slate-900"
          >
            <div class="mr-3 flex h-12 w-12 items-center justify-center rounded-lg bg-green-500 text-xl text-white">
              {{ card.icon }}
            </div>

            <div class="min-w-0">
              <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ card.name }}</p>
              <p class="truncate text-lg font-bold text-slate-900 dark:text-slate-100">
                {{ tkFormat(getStatValue(card.key)) }}
              </p>
            </div>
          </Link>
        </template>
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