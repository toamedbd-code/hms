<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
import BarChart from '@/Components/Chart/BarChart.vue';
import PieChart from '@/Components/Chart/PieChart.vue';

const props = defineProps(['dashboardData']);

// Format Tk.0.00
const tkFormat = (value) => {
  const num = Number(value) || 0;
  return "Tk." + num.toFixed(2);
};
</script>

<template>
  <BackendLayout>

    <!-- ===================== Stats Cards ===================== -->
    <section class="w-full transition duration-700 ease-in-out">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3 mb-4">

        <!-- Card Component -->
        <template v-for="card in [
          { name:'OPD Income', icon:'📄', key:'opdIncome', link:'backend.opdpatient.index' },
          { name:'IPD Income', icon:'🏥', key:'ipdIncome', link:'backend.ipdpatient.index' },
          { name:'Pharmacy Income', icon:'💊', key:'pharmacyIncome', link:'backend.report.index' },
          { name:'Pathology Income', icon:'🧪', key:'pathologyIncome', link:'backend.pathology.index' },
          { name:'Radiology Income', icon:'📷', key:'radiologyIncome', link:'backend.radiology.index' },
          { name:'Blood Bank Income', icon:'💉', key:'bloodBankIncome', link:'backend.bloodbank.index' },
          { name:'Expenses', icon:'💰', key:'expenses', link:'backend.expense.index' },
          { name:'Pending Income', icon:'⏳', key:'pendingIncome', link:'backend.pending.list' },
          { name:'Total Net Income', icon:'💵', key:'netIncome', link:'backend.report.index' },
          { name:'Total Discount', icon:'🏷️', key:'totalDiscountAmount', link:'backend.report.index' },
        ]" :key="card.key">

          <a :href="route(card.link)" target="_blank"
            class="bg-white rounded-lg shadow-md hover:shadow-lg transition-all p-4 flex items-center hover:-translate-y-1">

            <!-- Icon -->
            <div
              class="w-12 h-12 bg-green-500 text-white text-xl rounded-lg flex items-center justify-center mr-3">
              {{ card.icon }}
            </div>

            <!-- Text -->
            <div>
              <p class="text-sm font-medium text-gray-600">{{ card.name }}</p>
              <p class="text-lg font-bold text-gray-900">
                {{ tkFormat(props.dashboardData[card.key]) }}
              </p>
            </div>

          </a>

        </template>
      </div>
    </section>

    <!-- ===================== Charts Section ===================== -->
    <section class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">

      <!-- Bar Chart -->
      <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 font-semibold border-b">Income by Department (Bar Chart)</div>
        <div class="p-4 h-80">
          <BarChart :dashboardData="props.dashboardData" />
        </div>
      </div>

      <!-- Pie Chart -->
      <div class="bg-white rounded-lg shadow">
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