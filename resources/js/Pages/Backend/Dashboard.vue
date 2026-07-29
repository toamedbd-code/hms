<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import BarChart from '@/Components/Chart/BarChart.vue';
import PieChart from '@/Components/Chart/PieChart.vue';

const props = defineProps(['dashboardData', 'dashboardCardPermissions']);

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

    <!-- ===================== Stats Cards ===================== -->
    <section class="w-full transition duration-700 ease-in-out">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3 mb-4">

        <!-- Card Component -->
        <template v-for="card in statsCards" :key="card.key">

          <Link :href="route(card.link, card.params || {})"
            class="bg-white rounded-lg shadow-md hover:shadow-lg transition-all p-4 flex items-center hover:-translate-y-1">

            <!-- Icon -->
            <div
              class="w-12 h-12 bg-green-500 text-white text-xl rounded-lg flex items-center justify-center mr-3">
              {{ card.icon }}
            </div>

            <!-- Text -->
            <div class="min-w-0">
              <p class="text-sm font-medium text-gray-600">{{ card.name }}</p>
              <p class="text-lg font-bold text-gray-900 truncate">
                {{ tkFormat(getStatValue(card.key)) }}
              </p>
            </div>

          </Link>

        </template>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
        <Link v-if="canViewExpiredMedicines" :href="route('backend.medicineinventory.index', { expiry_filter: 'expired' })"
          class="bg-rose-50 border border-rose-200 rounded-lg shadow-sm hover:shadow-md transition-all p-4 flex items-center hover:-translate-y-1">
          <div class="w-12 h-12 bg-rose-500 text-white text-xl rounded-lg flex items-center justify-center mr-3">
            !
          </div>
          <div>
            <p class="text-sm font-medium text-rose-700">Expired Medicines</p>
            <p class="text-lg font-bold text-rose-900">
              {{ countFormat($page.props.pharmacyAlerts?.medicineExpiry?.expired_count) }}
            </p>
          </div>
        </Link>

        <Link v-if="canViewExpiringMedicines" :href="route('backend.medicineinventory.index', { expiry_filter: 'expiring_soon' })"
          class="bg-rose-50 border border-rose-200 rounded-lg shadow-sm hover:shadow-md transition-all p-4 flex items-center hover:-translate-y-1">
          <div class="w-12 h-12 bg-amber-400 text-white text-xl rounded-lg flex items-center justify-center mr-3">
            !!
          </div>
          <div>
            <p class="text-sm font-medium text-amber-600">Expiring In 30 Days</p>
            <p class="text-lg font-bold text-amber-700">
              {{ countFormat($page.props.pharmacyAlerts?.medicineExpiry?.expiring_soon_count) }}
            </p>
          </div>
        </Link>
      </div>
    </section>

    <!-- ===================== Charts Section ===================== -->
    <section class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">

        <!-- Bar Chart -->
        <div v-if="props.dashboardCardPermissions?.chartIncomeByDepartment" class="bg-white rounded-lg shadow">
          <div class="px-6 py-4 font-semibold border-b">Income by Department (Bar Chart)</div>
          <div class="p-4 h-80">
            <BarChart :dashboardData="props.dashboardData" />
          </div>
        </div>

        <!-- Pie Chart -->
        <div v-if="props.dashboardCardPermissions?.chartIncomeDistribution" class="bg-white rounded-lg shadow">
          <div class="px-6 py-4 font-semibold border-b">Income Distribution (Pie Chart)</div>
          <div class="p-4 h-80">
            <PieChart :dashboardData="props.dashboardData" />
          </div>
        </div>

    </section>

    <!-- ===================== Footer ===================== -->
    <div class="text-center text-gray-500 text-sm py-6">
      © {{ new Date().getFullYear() }} — Developed by ToaMed. All rights reserved.
    </div>

  </BackendLayout>
</template>