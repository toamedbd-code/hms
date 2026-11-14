<template>
    <Bar id="income-bar-chart" :options="chartOptions" :data="chartData" />
</template>

<script>
import { Bar } from 'vue-chartjs'
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale } from 'chart.js'

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale)

export default {
    name: 'DynamicBarChart',
    components: { Bar },
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
            const get = (obj, key) => {
                if (!obj) return 0;
                if (typeof obj[key] !== 'undefined' && obj[key] !== null) return Number(obj[key] || 0);
                const snake = key.replace(/([A-Z])/g, '_$1').toLowerCase();
                if (typeof obj[snake] !== 'undefined' && obj[snake] !== null) return Number(obj[snake] || 0);
                return 0;
            };
            const incomeItems = Array.isArray(this.dashboardData.chartIncomeItems)
                ? this.dashboardData.chartIncomeItems
                : [
                    { name: 'OPD Income', amount: get(this.dashboardData, 'opdIncome') },
                    { name: 'IPD Income', amount: get(this.dashboardData, 'ipdIncome') },
                    { name: 'Pharmacy Income', amount: get(this.dashboardData, 'pharmacyIncome') },
                    { name: 'Disposable Income', amount: get(this.dashboardData, 'disposableIncome') },
                    { name: 'Pathology Income', amount: get(this.dashboardData, 'pathologyIncome') },
                    { name: 'Radiology Income', amount: get(this.dashboardData, 'radiologyIncome') }
                ];
            const palette = ['#10B981', '#3B82F6', '#8B5CF6', '#A78BFA', '#F59E0B', '#EF4444', '#06B6D4', '#84CC16'];
            const supplementaryItems = Array.isArray(this.dashboardData.chartSupplementaryItems)
                ? this.dashboardData.chartSupplementaryItems
                : [];
            const labels = [
                ...incomeItems.map((item) => item.name),
                ...supplementaryItems.map((item) => item.name)
            ];
            const data = [
                ...incomeItems.map((item) => Number(item.amount) || 0),
                ...supplementaryItems.map((item) => Number(item.amount) || 0)
            ];

            return {
                labels,
                datasets: [{
                    label: 'Amount (Tk.)',
                    backgroundColor: labels.map((_, index) => palette[index % palette.length]),
                    borderColor: labels.map((_, index) => palette[index % palette.length]),
                    borderWidth: 1,
                    data
                }]
            }
        },
        chartOptions() {
            const textColor = this.isDark ? '#e2e8f0' : '#334155';
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Hospital Income and Expense by Department',
                        color: textColor,
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    },
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.label}: Tk.${context.parsed.y.toLocaleString()}`
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: textColor,
                            callback: function(value) {
                                return 'Tk.' + value.toLocaleString()
                            }
                        },
                        title: {
                            display: true,
                            text: 'Amount (Tk.)',
                            color: textColor
                        }
                    },
                    x: {
                        ticks: {
                            color: textColor,
                            font: {
                                size: 9
                            },
                            autoSkip: false,
                            maxRotation: 60,
                            minRotation: 60
                        },
                        title: {
                            display: true,
                            text: 'Department',
                            color: textColor
                        }
                    }
                }
            }
        }
    }
}
</script>