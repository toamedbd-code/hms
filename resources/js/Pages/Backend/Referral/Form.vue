<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AlertMessage from '@/Components/AlertMessage.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';
import axios from 'axios';

const props = defineProps(['referral', 'id', 'billings', 'referrers']);

const form = useForm({
    billing_id: props.referral?.billing_id ?? '',
    payee_id: props.referral?.payee_id ?? '',
    date: props.referral?.date ?? new Date().toISOString().split('T')[0],
    status: props.referral?.status ?? 'Active',
    remarks: props.referral?.remarks ?? '',
    
    _method: props.referral?.id ? 'put' : 'post',
});

const commissionPreview = ref({
    totalBillAmount: 0,
    totalCommission: 0,
    categoryBreakdown: {},
    loading: false
});

const selectedBilling = computed(() => {
    if (!form.billing_id || !props.billings) return null;
    return props.billings.find(billing => billing.id === form.billing_id);
});

const selectedPayee = computed(() => {
    if (!form.payee_id || !props.referrers) return null;
    return props.referrers.find(payee => payee.id === form.payee_id);
});

watch([() => form.billing_id, () => form.payee_id], async () => {
    if (form.billing_id && form.payee_id) {
        await calculateCommissionPreview();
    } else {
        resetCommissionPreview();
    }
});

// Safely read CSRF token in browser
const csrfToken = (typeof document !== 'undefined') ? (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '') : '';

const calculateCommissionPreview = async () => {
    if (!form.billing_id || !form.payee_id) return;
    
    commissionPreview.value.loading = true;
    
    try {
        console.log('Calculating commission for:', {
            billing_id: form.billing_id,
            payee_id: form.payee_id
        });

        const billingId = typeof form.billing_id === 'object' ? form.billing_id.id : form.billing_id;
        const payeeId = typeof form.payee_id === 'object' ? form.payee_id.id : form.payee_id;

        const response = await axios.post(route('backend.referral.commission.preview'), {
            billing_id: billingId,
            payee_id: payeeId,
        }, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            }
        });

        console.log('Commission data received:', response.data);
        
        commissionPreview.value = {
            totalBillAmount: response.data.total_bill_amount || 0,
            totalCommission: response.data.total_commission || 0,
            categoryBreakdown: response.data.category_breakdown || {},
            loading: false
        };
        
    } catch (error) {
        console.error('Error calculating commission preview:', error);
        resetCommissionPreview();
        
        if (error.response?.data?.message) {
            displayWarning({ message: error.response.data.message });
        } else if (error.response?.data?.error) {
            displayWarning({ message: error.response.data.error });
        } else {
            displayWarning({ message: 'Failed to calculate commission preview' });
        }
    }
};

const resetCommissionPreview = () => {
    commissionPreview.value = {
        totalBillAmount: 0,
        totalCommission: 0,
        categoryBreakdown: {},
        loading: false
    };
};

const submit = () => {
    if (!form.billing_id) {
        alert('Please select a bill number');
        return;
    }

    if (!form.payee_id) {
        alert('Please select a payee');
        return;
    }

    const routeName = props.id ? route('backend.referral.update', props.id) : route('backend.referral.store');
    
    form.transform(data => ({
        ...data,
        billing_id: typeof data.billing_id === 'object' ? data.billing_id.id : data.billing_id,
        payee_id: typeof data.payee_id === 'object' ? data.payee_id.id : data.payee_id,
        remember: '',
        isDirty: false,
    })).post(routeName, {
        onSuccess: (response) => {
            if (!props.id) {
                form.reset();
                form.date = new Date().toISOString().split('T')[0];
                form.status = 'Active';
                resetCommissionPreview();
            }
            displayResponse(response);
        },
        onError: (errorObject) => {
            console.log('Submission error:', errorObject);
            displayWarning(errorObject);
        },
    });
};

const goToReferralList = () => {
    router.visit(route('backend.referral.index'));
};

onMounted(() => {
    if (props.id && form.billing_id && form.payee_id) {
        calculateCommissionPreview();
    }
});

const goToRefferalList = () => {
    router.get(route('backend.referral.index'));
};


</script>

<template>
    <BackendLayout>
        <div
            class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md">
            <div
                class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md dark:bg-gray-800 dark:text-gray-200">
                <div>
                    <h1 class="p-4 text-xl font-bold dark:text-white">{{ $page.props.pageTitle }}</h1>
                </div>

                <div class="p-2 py-2 flex items-center space-x-2">
                    <div class="flex items-center space-x-3">

                        <button @click="goToRefferalList"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z">
                                </path>
                            </svg>
                            Refferal List
                        </button>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="p-6">

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    
                    <!-- Bill Number -->
                    <div class="col-span-1">
                        <InputLabel for="billing_id" value="Bill Number" />
                        <Multiselect 
                            v-model="form.billing_id" 
                            :options="billings" 
                            :track-by="'id'"
                            :label="'label'" 
                            placeholder="Search and select a bill number"
                            class="w-full text-sm rounded-md border border-slate-300"
                            :searchable="true"
                            :close-on-select="true"
                            :show-labels="false" />
                        <InputError class="mt-2" :message="form.errors.billing_id" />
                        
                        <!-- Selected Bill Info -->
                        <div v-if="selectedBilling" class="mt-2 p-2 bg-blue-50 rounded text-sm">
                            <p><strong>Invoice:</strong> {{ selectedBilling.invoice_number }}</p>
                            <p><strong>Patient:</strong> {{ selectedBilling.patient_mobile }}</p>
                            <p><strong>Amount:</strong> ৳{{ selectedBilling.amount?.toFixed(2) }}</p>
                        </div>
                    </div>

                    <!-- Payee Selection -->
                    <div class="col-span-1">
                        <InputLabel for="payee_id" value="Referrer/Payee" />
                        <Multiselect 
                            v-model="form.payee_id" 
                            :options="referrers" 
                            :track-by="'id'"
                            :label="'label'" 
                            placeholder="Search and select a referrer"
                            class="w-full text-sm rounded-md border border-slate-300"
                            :searchable="true"
                            :close-on-select="true"
                            :show-labels="false" />
                        <InputError class="mt-2" :message="form.errors.payee_id" />
                        
                        <!-- Selected Payee Commission Rates -->
                        <div v-if="selectedPayee" class="mt-2 p-2 bg-green-50 rounded text-sm">
                            <p><strong>Name:</strong> {{ selectedPayee.name }}</p>
                            <p><strong>Phone:</strong> {{ selectedPayee.phone }}</p>
                            <div class="grid grid-cols-2 gap-1 mt-1 text-xs">
                                <span>Standard: {{ selectedPayee.standard_commission }}%</span>
                                <span>Pathology: {{ selectedPayee.pathology_commission }}%</span>
                                <span>Radiology: {{ selectedPayee.radiology_commission }}%</span>
                                <span>Pharmacy: {{ selectedPayee.pharmacy_commission }}%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Date -->
                    <div class="col-span-1">
                        <InputLabel for="date" value="Date" />
                        <input id="date"
                            class="block w-full p-2 mt-1 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.date" type="date" required />
                        <InputError class="mt-2" :message="form.errors.date" />
                    </div>

                    <!-- Status -->
                    <div class="col-span-1">
                        <InputLabel for="status" value="Status" />
                        <select id="status"
                            class="block w-full p-2 mt-1 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.status" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.status" />
                    </div>

                    <!-- Remarks -->
                    <div class="col-span-2">
                        <InputLabel for="remarks" value="Remarks (Optional)" />
                        <textarea id="remarks"
                            class="block w-full p-2 mt-1 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.remarks" 
                            rows="3"
                            placeholder="Enter any additional remarks..."></textarea>
                        <InputError class="mt-2" :message="form.errors.remarks" />
                    </div>

                </div>

                <!-- Commission Preview Section -->
                <div v-if="form.billing_id && form.payee_id" class="mt-8 p-4 bg-gray-50 rounded-lg dark:bg-slate-800">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">Commission Preview</h3>
                    
                    <div v-if="commissionPreview.loading" class="text-center py-4">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                        <p class="mt-2 text-gray-600">Calculating commission...</p>
                    </div>
                    
                    <div v-else>
                        <!-- Summary Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                            <div class="text-center p-4 bg-white rounded-lg shadow dark:bg-slate-700">
                                <p class="text-sm text-gray-600 dark:text-gray-400">Total Bill Amount</p>
                                <p class="text-xl font-bold text-blue-600">৳{{ commissionPreview.totalBillAmount.toFixed(2) }}</p>
                            </div>
                            <div class="text-center p-4 bg-white rounded-lg shadow dark:bg-slate-700">
                                <p class="text-sm text-gray-600 dark:text-gray-400">Total Commission</p>
                                <p class="text-xl font-bold text-green-600">৳{{ commissionPreview.totalCommission.toFixed(2) }}</p>
                            </div>
                            <div class="text-center p-4 bg-white rounded-lg shadow dark:bg-slate-700">
                                <p class="text-sm text-gray-600 dark:text-gray-400">Commission Rate</p>
                                <p class="text-xl font-bold text-purple-600">
                                    {{ commissionPreview.totalBillAmount > 0 ? ((commissionPreview.totalCommission / commissionPreview.totalBillAmount) * 100).toFixed(2) : 0 }}%
                                </p>
                            </div>
                        </div>

                        <!-- Category Breakdown -->
                        <div v-if="Object.keys(commissionPreview.categoryBreakdown).length > 0" class="bg-white rounded-lg shadow p-4 dark:bg-slate-700">
                            <h4 class="font-semibold mb-3 text-gray-800 dark:text-gray-200">Category-wise Commission Breakdown</h4>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b dark:border-slate-600">
                                            <th class="text-left py-2">Category</th>
                                            <th class="text-right py-2">Amount (৳)</th>
                                            <th class="text-right py-2">Rate (%)</th>
                                            <th class="text-right py-2">Commission (৳)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(breakdown, category) in commissionPreview.categoryBreakdown" :key="category" 
                                            class="border-b dark:border-slate-600">
                                            <td class="py-2 capitalize">{{ category }}</td>
                                            <td class="text-right py-2">{{ breakdown.amount.toFixed(2) }}</td>
                                            <td class="text-right py-2">{{ breakdown.commission_rate.toFixed(2) }}</td>
                                            <td class="text-right py-2 font-semibold text-green-600">
                                                {{ breakdown.commission_amount.toFixed(2) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex items-center justify-end mt-6">
                    <PrimaryButton type="submit" class="px-6 py-2" :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing">
                        {{ ((props.id ?? false) ? 'Update' : 'Create') }} Referral
                    </PrimaryButton>
                </div>
            </form>

        </div>
    </BackendLayout>
</template>