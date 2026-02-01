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
      return {
        labels: ["OPD", "IPD", "Pharmacy", "Pathology", "Radiology", "Pending"],
        datasets: [
          {
            data: [
              this.dashboardData.opdIncome || 0,
              this.dashboardData.ipdIncome || 0,
              this.dashboardData.pharmacyIncome || 0,
              this.dashboardData.pathologyIncome || 0,
              this.dashboardData.radiologyIncome || 0,
              this.dashboardData.pendingIncome || 0
            ],

            backgroundColor: [
              "#4F46E5",
              "#10B981",
              "#3B82F6",
              "#F59E0B",
              "#EF4444",
              "#6B7280"
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