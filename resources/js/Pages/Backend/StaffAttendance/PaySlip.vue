<script setup>
import { ref } from "vue";
import { usePage } from "@inertiajs/vue3";
import BackendLayout from '@/Layouts/BackendLayout.vue';

const { props: pageProps } = usePage();
const props = defineProps({
    staffDetails: Object,
    grossSalary: Number,
    netSalary: Number,
    attendanceInfo: Object,
    websetting: {
        type: Object,
        default: () => ({})
    }
});

// Correct CSRF token from Inertia props
const csrfToken = usePage().props._token;

const formattedGrossSalary = Number(props.grossSalary).toFixed(2);
const formattedNetSalary = Number(props.netSalary).toFixed(2);

const previousMonth = new Date().getMonth() === 0 ? 11 : new Date().getMonth() - 1;
const previousYear = previousMonth === 11 ? new Date().getFullYear() - 1 : new Date().getFullYear();
const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
const formattedPeriod = `${monthNames[previousMonth]} ${previousYear}`;

const payslipData = ref({
    companyName: props.websetting?.company_name || 'Hospital',
    location: props.websetting?.address || 'N/A',
    period: formattedPeriod,
    payslipNumber: '1',
    paymentDate: new Date().toLocaleDateString(),
    staffId: props.staffDetails.id,
    designation: props.staffDetails.designation?.name || 'N/A',
    branch: props.staffDetails.branch || 'N/A',
    basicSalary: props.staffDetails.salary,
    area: 'Dhaka Area',
    paymentMode: 'Cash',
    grossSalary: formattedGrossSalary,
    netSalary: formattedNetSalary,
    name: props.staffDetails.name
});

const downloadPayslip = () => {
    const params = new URLSearchParams({
        payslipData: JSON.stringify(payslipData.value),
    });
    window.open(route('backend.download.payslip') + '?' + params.toString(), '_blank');
};
</script>

<template>
    <BackendLayout>
        <div class="max-w-lg mx-auto bg-white p-6 rounded-lg shadow-md mt-6">
            <div class="relative text-center mb-6 border-b pb-4">
                <a @click="downloadPayslip"
                    class="absolute top-0 right-0 flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 cursor-pointer">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z"
                            clip-rule="evenodd" />
                    </svg>
                    Print
                </a>
                <h1 class="text-2xl font-semibold">{{ payslipData.companyName }}</h1>
                <p class="text-gray-600">{{ payslipData.location }}</p>
                <p class="text-lg font-medium mt-2">Payslip for the period of {{ payslipData.period }}</p>
            </div>

            <div class="flex justify-between mb-6">
                <div>
                    <p><strong>Payslip #:</strong> {{ payslipData.payslipNumber }}</p>
                    <p><strong>Staff ID:</strong> {{ payslipData.staffId }}</p>
                    <p><strong>Designation:</strong> {{ payslipData.designation }}</p>
                    <p><strong>Payment In:</strong> {{ payslipData.paymentMode }}</p>
                    <p><strong>Basic Salary:</strong> {{ payslipData.basicSalary }}</p>
                </div>
                <div>
                    <p><strong>Payment Date:</strong> {{ payslipData.paymentDate }}</p>
                    <p><strong>Name:</strong> {{ payslipData.name }}</p>
                    <p><strong>Branch:</strong> {{ payslipData.branch }}</p>
                    <p><strong>Area:</strong> {{ payslipData.area }}</p>
                    <p><strong>Gross Salary:</strong> {{ payslipData.grossSalary }}</p>
                </div>
            </div>

            <div class="bg-gray-100 p-4 rounded-md">
                <p class="font-bold text-lg text-center">Summary</p>
                <div class="flex justify-between mt-3">
                    <p>Basic Salary:</p>
                    <p>{{ payslipData.basicSalary }}</p>
                </div>
                <div class="flex justify-between">
                    <p>Gross Salary:</p>
                    <p>{{ payslipData.grossSalary }}</p>
                </div>
                <div class="flex justify-between border-t border-gray-300 mt-3 pt-3">
                    <p class="font-bold text-xl">Net Salary:</p>
                    <p class="font-bold text-xl">{{ payslipData.netSalary }}</p>
                </div>
            </div>
        </div>
    </BackendLayout>
</template>
