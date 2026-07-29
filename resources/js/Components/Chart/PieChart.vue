<template>
  <Pie :data="chartData" :options="chartOptions" />
</template>

<script>
import { Pie } from "vue-chartjs";
import {
  Chart as ChartJS,
  ArcElement,
  Tooltip,
  Legend
} from "chart.js";

ChartJS.register(ArcElement, Tooltip, Legend);

export default {
  name: "PieChart",
  components: { Pie },

  props: {
    dashboardData: {
      type: Object,
      required: true
    }
  },

  computed: {
    chartData() {
      const val = v => Number(v || 0);
      const get = (obj, key) => {
        if (!obj) return 0;
        // direct
        if (typeof obj[key] !== 'undefined' && obj[key] !== null) return Number(obj[key] || 0);
        // try snake_case fallback (disposable_income)
        const snake = key.replace(/([A-Z])/g, '_$1').toLowerCase();
        if (typeof obj[snake] !== 'undefined' && obj[snake] !== null) return Number(obj[snake] || 0);
        return 0;
      };
      // debug value
      try {
        // eslint-disable-next-line no-console
        // removed console.debug to avoid noisy logs
      } catch (e) {}
      return {
        labels: ["OPD", "IPD", "Pharmacy", "Disposable", "Pathology", "Radiology", "Pending", "Expense", "Final Income"],
        datasets: [
          {
            data: [
              val(this.dashboardData.opdIncome),
              val(this.dashboardData.ipdIncome),
              val(this.dashboardData.pharmacyIncome),
              get(this.dashboardData, 'disposableIncome'),
              val(this.dashboardData.pathologyIncome),
              val(this.dashboardData.radiologyIncome),
              val(this.dashboardData.pendingIncome),
              val(this.dashboardData.expenses),
              val(this.dashboardData.netIncome)
            ],

            backgroundColor: [
              "#4F46E5",
              "#10B981",
              "#3B82F6",
              "#A78BFA", // Disposable - lavender
              "#F59E0B",
              "#EF4444",
              "#6B7280",
              "#0F172A",
              "#22C55E"
            ],

            borderWidth: 1
          }
        ]
      };
    },

    chartOptions() {
      return {
        responsive: true,
        maintainAspectRatio: false,

        plugins: {
          legend: {
            position: "right"
          },

          tooltip: {
            callbacks: {
              label: function (context) {
                const label = context.label || "";
                const value = Number(context.raw) || 0;

                // Total income calculation
                const total = context.dataset.data.reduce(
                  (sum, v) => sum + Number(v || 0),
                  0
                );

                // Prevent NaN%
                const percentage =
                  total > 0 ? ((value / total) * 100).toFixed(1) : "0.0";

                return `${label}: Tk.${value.toLocaleString()} (${percentage}%)`;
              }
            }
          }
        }
      };
    }
  }
};
</script>