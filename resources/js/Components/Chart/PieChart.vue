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
    },
    isDark: {
      type: Boolean,
      default: false
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
      const incomeItems = Array.isArray(this.dashboardData.chartIncomeItems)
        ? this.dashboardData.chartIncomeItems
        : [
          { name: 'OPD', amount: val(this.dashboardData.opdIncome) },
          { name: 'IPD', amount: val(this.dashboardData.ipdIncome) },
          { name: 'Pharmacy', amount: val(this.dashboardData.pharmacyIncome) },
          { name: 'Disposable', amount: get(this.dashboardData, 'disposableIncome') },
          { name: 'Pathology', amount: val(this.dashboardData.pathologyIncome) },
          { name: 'Radiology', amount: val(this.dashboardData.radiologyIncome) }
        ];
      const palette = ['#4F46E5', '#10B981', '#3B82F6', '#A78BFA', '#F59E0B', '#EF4444', '#06B6D4', '#84CC16'];
      return {
        labels: incomeItems.map((item) => item.name),
        datasets: [
          {
            data: incomeItems.map((item) => Number(item.amount) || 0),
            backgroundColor: incomeItems.map((_, index) => palette[index % palette.length]),
            borderWidth: 1
          }
        ]
      };
    },

    chartOptions() {
      const textColor = this.isDark ? '#e2e8f0' : '#334155';
      return {
        responsive: true,
        maintainAspectRatio: false,

        plugins: {
          legend: {
            position: "right",
            labels: {
              color: textColor,
              boxWidth: 10,
              boxHeight: 10,
              padding: 4,
              font: {
                size: 9
              }
            }
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