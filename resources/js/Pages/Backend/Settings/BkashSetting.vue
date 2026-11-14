<script setup>
import { computed, ref } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { displayResponse, successMessage, errorMessage } from '@/responseMessage.js';

const props = defineProps({
    setting: Object,
    bkashEnv: Object,
    monthlyAmount: Number,
    yearlyAmount: Number,
    subscriptionActive: Boolean,
    subscriptionDurationDays: Number,
    canInitiatePayment: { type: Boolean, default: false },
    pageTitle: { type: String, default: 'bKash Settings' },
});

// Main settings form
const form = useForm({
    merchant_account: props.bkashEnv?.merchant_account ?? '',
    app_key: props.bkashEnv?.app_key ?? '',
    app_secret: props.bkashEnv?.app_secret ?? '',
    username: props.bkashEnv?.username ?? '',
    password: props.bkashEnv?.password ?? '',
    merchant_number: props.bkashEnv?.merchant_number ?? '',
    sandbox_base_url: props.bkashEnv?.sandbox_base_url ?? '',
    production_base_url: props.bkashEnv?.production_base_url ?? '',
    token_endpoint: props.bkashEnv?.token_endpoint ?? '',
    create_payment_endpoint: props.bkashEnv?.create_payment_endpoint ?? '',
    execute_payment_endpoint: props.bkashEnv?.execute_payment_endpoint ?? '',
    query_payment_endpoint: props.bkashEnv?.query_payment_endpoint ?? '',
    callback_url: props.bkashEnv?.callback_url ?? '',
    http_timeout: props.bkashEnv?.http_timeout ?? 30,
    http_verify_ssl: props.bkashEnv?.http_verify_ssl === 'true' ? '1' : '0',
    use_production: props.bkashEnv?.use_production === 'true' ? '1' : '0',
    is_enabled: props.setting?.is_enabled ? '1' : '0',
    monthly_amount: props.monthlyAmount ?? 0,
});

const submitForm = () => {
    form.post(route('backend.settings.payment.bkash.store'), {
        onSuccess: (response) => displayResponse(response),
        onError: () => errorMessage('Please fix the validation errors.'),
    });
};

// Subscription toggle form
const subscriptionForm = useForm({
    mode: 'on',
    duration_days: props.subscriptionDurationDays ?? 30,
});

const toggleSubscription = (mode) => {
    subscriptionForm.mode = mode;
    subscriptionForm.post(route('backend.settings.payment.bkash.subscription.toggle'), {
        onSuccess: (response) => displayResponse(response),
    });
};

// Test connection
const testLoading = ref(false);
const testResult = ref(null);

const testConnection = async () => {
    testLoading.value = true;
    testResult.value = null;
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const res = await fetch(route('backend.settings.payment.bkash.test'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({}),
        });
        const data = await res.json().catch(() => ({ ok: false, message: 'Invalid response' }));
        testResult.value = data;
        if (data.ok) successMessage(data.message ?? 'Connection OK');
        else errorMessage(data.message ?? 'Connection failed');
    } catch (err) {
        const msg = 'Network error: ' + (err.message || err);
        testResult.value = { ok: false, message: msg };
        errorMessage(msg);
    } finally {
        testLoading.value = false;
    }
};

// Pay monthly
const payMonthly = () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const checkoutForm = document.createElement('form');
    checkoutForm.method = 'POST';
    checkoutForm.action = route('backend.payment.bkash.initiate');
    checkoutForm.style.display = 'none';

    [
        ['_token', csrfToken],
        ['amount', String(form.monthly_amount ?? 0)],
        ['admin_test', '1'],
    ].forEach(([name, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        checkoutForm.appendChild(input);
    });

    document.body.appendChild(checkoutForm);
    checkoutForm.submit();
    checkoutForm.remove();
};

const gatewayFee = 1.5;
const monthlyWithFee = computed(() => +((Number(form.monthly_amount || 0) * (1 + gatewayFee / 100)).toFixed(2)));

const goBack = () => router.visit(route('backend.dashboard'));
</script>

<template>
    <BackendLayout>
        <div class="w-full bg-white rounded-md dark:bg-gray-900">

            <!-- Header bar -->
            <div class="flex items-center justify-between bg-gray-100 dark:bg-gray-800 rounded-t-md px-4 py-2">
                <h1 class="text-base font-bold text-gray-700 dark:text-white">bKash Settings</h1>
                <div class="flex items-center gap-2">
                    <button type="button" @click="goBack"
                        class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-red-600 rounded shadow hover:bg-red-700 active:scale-95 transition-all">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back
                    </button>
                </div>
            </div>

            <div class="p-4 space-y-4">

                <!-- Subscription Control -->
                <div class="section-card">
                    <div class="section-header">Subscription Control</div>
                    <div class="p-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Current Status:
                                <span :class="subscriptionActive ? 'text-green-600' : 'text-red-500'" class="font-bold">
                                    {{ subscriptionActive ? 'ACTIVE' : 'INACTIVE' }}
                                </span>
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="toggleSubscription('off')"
                                :disabled="subscriptionForm.processing"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-red-600 border border-red-400 rounded hover:bg-red-50 transition disabled:opacity-50">
                                <svg v-if="subscriptionForm.processing && subscriptionForm.mode === 'off'" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                </svg>
                                Inactive
                            </button>
                            <div class="flex items-center gap-1">
                                <div class="flex flex-col">
                                    <label class="text-xs text-gray-500 dark:text-gray-400">Active days</label>
                                    <input type="number" v-model="subscriptionForm.duration_days"
                                        min="1" max="3650"
                                        class="form-input w-20 text-center" />
                                </div>
                                <button type="button" @click="toggleSubscription('on')"
                                    :disabled="subscriptionForm.processing"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-green-600 rounded hover:bg-green-700 transition disabled:opacity-50">
                                    <svg v-if="subscriptionForm.processing && subscriptionForm.mode === 'on'" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                    </svg>
                                    Active
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Form -->
                <form @submit.prevent="submitForm" class="space-y-4">

                    <!-- Merchant Credentials -->
                    <div class="section-card">
                        <div class="section-header">Merchant Credentials</div>
                        <div class="p-3 grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="md:col-span-2">
                                <InputLabel for="merchant_account" value="Merchant Account" class="field-label" />
                                <input id="merchant_account" class="form-input" v-model="form.merchant_account" type="text" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.merchant_account" />
                            </div>
                            <div>
                                <InputLabel for="app_key" value="App Key" class="field-label" />
                                <input id="app_key" class="form-input" v-model="form.app_key" type="text" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.app_key" />
                            </div>
                            <div>
                                <InputLabel for="app_secret" value="App Secret" class="field-label" />
                                <input id="app_secret" class="form-input" v-model="form.app_secret" type="text" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.app_secret" />
                            </div>
                            <div>
                                <InputLabel for="username" value="Merchant Username" class="field-label" />
                                <input id="username" class="form-input" v-model="form.username" type="text" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.username" />
                            </div>
                            <div>
                                <InputLabel for="merchant_number" value="Merchant Number" class="field-label" />
                                <input id="merchant_number" class="form-input" v-model="form.merchant_number" type="text" placeholder="Leave blank to hide on checkout" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.merchant_number" />
                            </div>
                            <div class="md:col-span-2">
                                <InputLabel for="password" value="Merchant Password" class="field-label" />
                                <input id="password" class="form-input" v-model="form.password" type="text" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.password" />
                            </div>
                        </div>
                    </div>

                    <!-- Connection Settings -->
                    <div class="section-card">
                        <div class="section-header">bKash Connection Settings</div>
                        <div class="p-3 grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <InputLabel for="sandbox_base_url" value="Sandbox Base URL" class="field-label" />
                                <input id="sandbox_base_url" class="form-input" v-model="form.sandbox_base_url" type="text" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.sandbox_base_url" />
                            </div>
                            <div>
                                <InputLabel for="production_base_url" value="Production Base URL" class="field-label" />
                                <input id="production_base_url" class="form-input" v-model="form.production_base_url" type="text" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.production_base_url" />
                            </div>
                            <div>
                                <InputLabel for="token_endpoint" value="Token Endpoint" class="field-label" />
                                <input id="token_endpoint" class="form-input" v-model="form.token_endpoint" type="text" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.token_endpoint" />
                            </div>
                            <div>
                                <InputLabel for="create_payment_endpoint" value="Create Payment Endpoint" class="field-label" />
                                <input id="create_payment_endpoint" class="form-input" v-model="form.create_payment_endpoint" type="text" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.create_payment_endpoint" />
                            </div>
                            <div>
                                <InputLabel for="execute_payment_endpoint" value="Execute Payment Endpoint" class="field-label" />
                                <input id="execute_payment_endpoint" class="form-input" v-model="form.execute_payment_endpoint" type="text" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.execute_payment_endpoint" />
                            </div>
                            <div>
                                <InputLabel for="query_payment_endpoint" value="Query Payment Endpoint" class="field-label" />
                                <input id="query_payment_endpoint" class="form-input" v-model="form.query_payment_endpoint" type="text" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.query_payment_endpoint" />
                            </div>
                            <div class="md:col-span-2">
                                <InputLabel for="callback_url" value="Callback URL" class="field-label" />
                                <input id="callback_url" class="form-input" v-model="form.callback_url" type="text" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.callback_url" />
                            </div>
                            <div>
                                <InputLabel for="http_timeout" value="HTTP Timeout (seconds)" class="field-label" />
                                <input id="http_timeout" class="form-input" v-model="form.http_timeout" type="number" min="1" max="300" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.http_timeout" />
                            </div>
                            <div>
                                <InputLabel for="http_verify_ssl" value="Verify SSL" class="field-label" />
                                <select id="http_verify_ssl" class="form-input" v-model="form.http_verify_ssl">
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel for="use_production" value="Sandbox Mode" class="field-label" />
                                <select id="use_production" class="form-input" v-model="form.use_production">
                                    <option value="0">Yes (use sandbox)</option>
                                    <option value="1">No (use production)</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel for="is_enabled" value="Enable bKash Payments" class="field-label" />
                                <select id="is_enabled" class="form-input" v-model="form.is_enabled">
                                    <option value="1">Enabled</option>
                                    <option value="0">Disabled</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Billing -->
                    <div class="section-card">
                        <div class="section-header">Subscription Billing</div>
                        <div class="p-3 grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <InputLabel for="monthly_amount" value="Monthly Amount (BDT)" class="field-label" />
                                <input id="monthly_amount" class="form-input" v-model="form.monthly_amount" type="number" step="0.01" min="0" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.monthly_amount" />
                            </div>
                        </div>
                    </div>

                    <!-- Save button -->
                    <div class="flex items-center justify-end gap-3">
                        <button type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center px-5 py-2 text-sm font-semibold text-white bg-green-600 rounded shadow hover:bg-green-700 active:scale-95 transition disabled:opacity-50">
                            <svg v-if="form.processing" class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                            Save Settings
                        </button>
                    </div>
                </form>

                <!-- Actions: Test + Pay -->
                <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-gray-200 dark:border-gray-700">
                    <!-- Pay Monthly -->
                    <button v-if="Number(form.monthly_amount) > 0"
                        type="button" @click="payMonthly"
                        :disabled="!canInitiatePayment || form.is_enabled !== '1' || form.isDirty"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-green-600 rounded shadow hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                        Pay Monthly Now (BDT {{ monthlyWithFee.toFixed(2) }})
                    </button>
                    <p v-if="Number(form.monthly_amount) > 0 && (!canInitiatePayment || form.is_enabled !== '1' || form.isDirty)"
                        class="w-full text-xs text-amber-700 dark:text-amber-300">
                        Enable bKash payments and save settings before starting checkout.
                    </p>

                    <!-- Test connection -->
                    <button type="button" @click="testConnection"
                        :disabled="testLoading"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-blue-700 border border-blue-400 rounded hover:bg-blue-50 transition disabled:opacity-50">
                        <svg v-if="testLoading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                        </svg>
                        {{ testLoading ? 'Testing...' : 'Test Connection' }}
                    </button>
                </div>

            </div>
        </div>
    </BackendLayout>
</template>

<style scoped>
@reference "../../../../css/app.css";

.section-card {
    @apply border border-gray-200 dark:border-gray-700 rounded-md overflow-hidden;
}
.section-header {
    @apply bg-gray-50 dark:bg-gray-800 px-4 py-2 text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wide border-b border-gray-200 dark:border-gray-700;
}
.form-input {
    @apply mt-0.5 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-2.5 py-1.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none transition;
}
.field-label {
    @apply text-xs font-medium text-gray-600 dark:text-gray-400;
}
</style>
