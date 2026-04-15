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
        }
    },
    computed: {
        chartData() {
            return {
                labels: [
                    'OPD Income',
                    'IPD Income', 
                    'Pharmacy Income',
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
                        this.dashboardData.opdIncome || 0,
                        this.dashboardData.ipdIncome || 0,
                        this.dashboardData.pharmacyIncome || 0,
                        this.dashboardData.pathologyIncome || 0,
                        this.dashboardData.radiologyIncome || 0,
                        this.dashboardData.pendingIncome || 0,
                        this.dashboardData.expenses || 0,
                        this.dashboardData.netIncome || 0
                    ]
                }]
            }
        },
        chartOptions() {
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Hospital Income and Expense by Department',
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
                            callback: function(value) {
                                return 'Tk.' + value.toLocaleString()
                            }
                        },
                        title: {
                            display: true,
                            text: 'Amount (Tk.)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Department'
                        }
                    }
                }
            }
        }
    }
}
</script>