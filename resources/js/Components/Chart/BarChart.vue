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

            try {
                // eslint-disable-next-line no-console
                // removed console.debug to reduce console noise
            } catch (e) {}

            return {
                labels: [
                    'OPD Income',
                    'IPD Income', 
                    'Pharmacy Income',
                    'Disposable Income',
                    'Pathology Income',
                    'Radiology Income',
                    'Pending Income',
                    'Expense',
                    'Final Income'
                ],
                datasets: [{
                    label: 'Amount (Tk.)',
                    backgroundColor: [
                        '#10B981', // Green
                        '#3B82F6', // Blue
                        '#8B5CF6', // Purple
                        '#A78BFA', // Disposable - lavender
                        '#F59E0B', // Yellow
                        '#EF4444', // Red
                        '#6B7280',  // Gray
                        '#0F172A',  // Slate
                        '#22C55E'   // Green
                    ],
                    borderColor: [
                        '#059669',
                        '#2563EB',
                        '#7C3AED',
                        '#D97706',
                        '#DC2626',
                        '#4B5563',
                        '#0F172A',
                        '#16A34A'
                    ],
                    borderWidth: 1,
                    data: [
                        get(this.dashboardData, 'opdIncome'),
                        get(this.dashboardData, 'ipdIncome'),
                        get(this.dashboardData, 'pharmacyIncome'),
                        get(this.dashboardData, 'disposableIncome'),
                        get(this.dashboardData, 'pathologyIncome'),
                        get(this.dashboardData, 'radiologyIncome'),
                        get(this.dashboardData, 'pendingIncome'),
                        get(this.dashboardData, 'expenses'),
                        get(this.dashboardData, 'netIncome')
                    ]
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
                            color: textColor
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