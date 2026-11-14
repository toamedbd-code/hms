<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    canResetPassword: Boolean,
    errorMessage: String,
    successMessage: String,
    warningMessage: String,
    subscriptionEnforced: Boolean,
    subscriptionActive: Boolean,
    subscriptionDaysRemaining: { type: Number, default: 0 },
    showSubscriptionRenewal: Boolean,
    bkashEnabled: Boolean,
    bkashMonthlyAmount: [Number, String],
    bkashMonthlyPayableAmount: [Number, String],
    bkashRenewalMonths: { type: Number, default: 1 },
    bkashYearlyAmount: [Number, String],
    bkashYearlyPayableAmount: [Number, String],
    subscriptionDefaultPeriod: { type: String, default: 'monthly' },
    isSandbox: { type: Boolean, default: true },
    pendingBkashPaymentId: [Number, String],
    rememberEmail: { type: String, default: '' },
    hasValidRememberToken: { type: Boolean, default: false },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const paying = ref(false);
const showPassword = ref(false);
const selectedPeriod = ref(props.subscriptionDefaultPeriod || 'monthly');
const page = usePage();
const status = page.props.status;

watch(() => form.password, password => {
    if (!password) {
        showPassword.value = false;
    }
});

const flashSuccess = computed(
    () => props.successMessage || page.props.flash?.successMessage || null
);
const flashError = computed(
    () => props.errorMessage || page.props.flash?.errorMessage || null
);
const flashWarning = computed(() => props.warningMessage || null);

const needsRenewal = computed(
    () =>
        props.showSubscriptionRenewal ||
        (props.subscriptionEnforced && !props.subscriptionActive)
);

const showExpiryReminder = computed(
    () => props.subscriptionActive && props.subscriptionDaysRemaining >= 1 && props.subscriptionDaysRemaining <= 5
);

const canRenewActiveSubscription = computed(
    () => props.subscriptionActive && props.subscriptionDaysRemaining >= 1 && props.subscriptionDaysRemaining <= 5
);

const expiryReminderText = computed(() => {
    const days = props.subscriptionDaysRemaining;
    return `Subscription renewal reminder: ${days} day${days === 1 ? '' : 's'} remaining. You can renew now or continue using the system until expiry.`;
});

const showPaymentGateway = computed(() => {
    const hasPricing = Number(props.bkashMonthlyAmount || 0) > 0 || Number(props.bkashYearlyAmount || 0) > 0;
    return Boolean(
        (props.pendingBkashPaymentId && !props.subscriptionActive) ||
        props.showSubscriptionRenewal ||
        (props.bkashEnabled && hasPricing && (!props.subscriptionActive || canRenewActiveSubscription.value))
    );
});

const monthlyAmount = computed(() => Number(props.bkashMonthlyAmount || 0));
const yearlyAmount = computed(() => Number(props.bkashYearlyAmount || 0));
const monthlyPayableAmount = computed(() => Number(props.bkashMonthlyPayableAmount || monthlyAmount.value));
const yearlyPayableAmount = computed(() => Number(props.bkashYearlyPayableAmount || yearlyAmount.value));

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('backend.auth.login'), {
        onFinish: () => form.reset('password'),
    });
};

const paymentUrl = (period = null) => {
    const p = period || selectedPeriod.value || props.subscriptionDefaultPeriod || 'monthly';

    try {
        if (typeof window !== 'undefined' && window.location && window.location.origin) {
            const params = new URLSearchParams({ period: p });
            return `${window.location.origin}/payment/bkash/renew?${params.toString()}`;
        }
    } catch (e) {
        // keep fallback below
    }

    return `/payment/bkash/renew?period=${encodeURIComponent(p)}`;
};

// Pre-fill email if user has a valid remember token
onMounted(() => {
    if (props.rememberEmail && props.hasValidRememberToken) {
        form.email = props.rememberEmail;
        // Auto-check remember me checkbox when loading page with remember token
        form.remember = true;
    }
});

</script>

<template>
    <Head title="Log in" />

    <div class="relative min-h-screen overflow-hidden bg-[#f6f3ee]">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -top-20 -left-24 h-80 w-80 rounded-full bg-[#d96f32]/20 blur-3xl"></div>
            <div class="absolute top-1/3 -right-16 h-96 w-96 rounded-full bg-[#1f5f5b]/15 blur-3xl"></div>
            <div class="absolute -bottom-20 left-1/3 h-72 w-72 rounded-full bg-[#e2b95f]/20 blur-3xl"></div>
        </div>

        <div class="relative z-10 grid min-h-screen grid-cols-1 lg:grid-cols-2">
            <section class="hidden lg:flex flex-col justify-between p-12 xl:p-16 bg-gradient-to-br from-[#0f3a37] via-[#16524f] to-[#1f5f5b] text-white">
                <div>
                    <p class="inline-flex items-center rounded-full bg-white/15 px-4 py-1 text-xs tracking-[0.25em] uppercase">{{ page.props.loginTexts.banner }}</p>
                    <h1 class="mt-8 text-5xl xl:text-6xl leading-[1.05] font-semibold">{{ page.props.loginTexts.title }}</h1>
                    <p class="mt-6 max-w-xl text-base xl:text-lg text-white/85">
                        {{ page.props.loginTexts.subtitle }}
                    </p>
                </div>
                <div class="grid grid-cols-3 gap-3 text-sm">
                    <div class="rounded-2xl bg-white/10 p-4 backdrop-blur-sm border border-white/20">
                        <p class="text-white/70">Security</p>
                        <p class="mt-1 text-2xl font-semibold">Strong</p>
                    </div>
                    <div class="rounded-2xl bg-white/10 p-4 backdrop-blur-sm border border-white/20">
                        <p class="text-white/70">Modules</p>
                        <p class="mt-1 text-2xl font-semibold">45+</p>
                    </div>
                    <div class="rounded-2xl bg-white/10 p-4 backdrop-blur-sm border border-white/20">
                        <p class="text-white/70">Support</p>
                        <p class="mt-1 text-2xl font-semibold">24/7</p>
                    </div>
                </div>
            </section>

            <section class="flex items-center justify-center px-5 py-10 sm:px-8 lg:px-12">
                <div class="w-full max-w-md">
                    <div class="mb-8 text-center lg:text-left">
                        <p class="text-xs uppercase tracking-[0.22em] text-[#1f5f5b] font-semibold">Secure Sign In</p>
                        <h2 class="mt-2 text-3xl sm:text-4xl font-semibold text-[#15211f]">Account Login</h2>
                        <p class="mt-3 text-sm text-[#4f5e5b]">Enter your credentials to continue.</p>
                    </div>

                    <div class="rounded-3xl border border-[#d5d0c7] bg-white/90 p-6 sm:p-8 shadow-[0_20px_70px_-35px_rgba(20,33,31,0.45)] backdrop-blur">
                        <div v-if="status" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">
                            {{ status }}
                        </div>
                        <div v-if="flashError" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
                            {{ flashError }}
                        </div>
                        <div v-if="flashSuccess" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">
                            {{ flashSuccess }}
                        </div>
                        <div v-if="flashWarning" class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700">
                            {{ flashWarning }}
                        </div>

                        <div v-if="showExpiryReminder" class="mb-5 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
                            {{ expiryReminderText }}
                        </div>

                        <div v-if="hasValidRememberToken" class="mb-5 rounded-xl border border-blue-300 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-800">
                            <span class="inline-flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 8a6 6 0 01-7.743 5.743L11 14H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1118 8zm-6-4a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd" />
                                </svg>
                                আপনার "Remember Me" টোকেন থেকে ইমেইল লোড হয়েছে (2 ঘন্টার মধ্যে ব্যবহার করুন)। শুধু পাসওয়ার্ড দিয়ে লগইন করুন।
                            </span>
                        </div>

                        <!-- bKash Subscription Payment Gateway -->
                        <div
                            v-if="showPaymentGateway"
                            class="mb-5 overflow-hidden rounded-2xl border border-[#e8b4c4] bg-gradient-to-br from-[#fff7f9] to-[#fde8ef]"
                        >
                            <div class="flex items-center justify-between gap-3 border-b border-[#f0c9d6] px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-[#e2136e] text-xs font-bold text-white">bK</span>
                                    <div>
                                        <p class="text-sm font-semibold text-[#2a1a22]">bKash Payment Gateway</p>
                                        <p class="text-[11px] text-[#7a4a5c]">
                                            {{ needsRenewal ? 'Subscription inactive — pay to unlock login' : 'Renew or extend subscription' }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3 p-4">
                                <div
                                    v-if="needsRenewal"
                                    class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800"
                                >
                                    Pay with bKash, then return here and log in with your admin account.
                                </div>

                                <div class="grid gap-2" :class="yearlyAmount > 0 ? 'sm:grid-cols-2' : 'grid-cols-1'">
                                    <a
                                        v-if="monthlyAmount > 0"
                                        :href="paymentUrl('monthly')"
                                        class="!text-white inline-flex items-center justify-center gap-2 rounded-xl bg-[#e2136e] px-4 py-2.5 text-sm font-semibold transition hover:bg-[#c41060]"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                        Pay Now = ৳{{ monthlyAmount.toFixed(2) }}
                                        <span v-if="bkashRenewalMonths > 1" class="text-xs font-normal">({{ bkashRenewalMonths }} months)</span>
                                    </a>
                                    <a
                                        v-if="yearlyAmount > 0"
                                        :href="paymentUrl('yearly')"
                                        class="!text-white inline-flex items-center justify-center gap-2 rounded-xl bg-[#e2136e] px-4 py-2.5 text-sm font-semibold transition hover:bg-[#c41060]"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                        Pay Yearly = ৳{{ yearlyAmount.toFixed(2) }}
                                    </a>
                                </div>
                                <p class="text-[11px] text-[#7a4a5c]">
                                    Payment will open in this tab. After payment and invoice printing, you will return to the refreshed login page.
                                </p>

                                <p v-if="isSandbox" class="text-[11px] leading-relaxed text-[#7a4a5c]">
                                </p>
                            </div>
                        </div>

                        <form @submit.prevent="submit" class="space-y-5">
                            <div>
                                <label for="email" class="mb-1.5 block text-sm font-medium text-[#2c3b38]">Email</label>
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    placeholder="admin@hospital.com"
                                    class="block w-full rounded-xl border border-[#d2cbc0] bg-[#fcfbf9] px-4 py-2.5 text-sm text-[#1d2b28] outline-none transition focus:border-[#1f5f5b] focus:ring-4 focus:ring-[#1f5f5b]/15"
                                >
                                <InputError class="mt-2" :message="form.errors.email" />
                            </div>

                            <div>
                                <label for="password" class="mb-1.5 block text-sm font-medium text-[#2c3b38]">Password</label>
                                <div class="relative">
                                    <input
                                        id="password"
                                        v-model="form.password"
                                        :type="showPassword ? 'text' : 'password'"
                                        required
                                        autocomplete="current-password"
                                        placeholder="Your password"
                                        class="block w-full rounded-xl border border-[#d2cbc0] bg-transparent px-4 py-2.5 pr-12 text-sm text-[#1d2b28] outline-none transition focus:border-[#1f5f5b] focus:ring-4 focus:ring-[#1f5f5b]/15"
                                    >
                                    <button
                                        v-if="form.password"
                                        type="button"
                                        @click="showPassword = !showPassword"
                                        class="password-toggle absolute inset-y-0 right-0 flex items-center border-0 bg-transparent p-0 pr-3 text-black shadow-none transition hover:bg-transparent hover:text-black"
                                    >
                                        <svg v-if="showPassword" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                            <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                        </svg>
                                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd" />
                                            <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" />
                                        </svg>
                                    </button>
                                </div>
                                <InputError class="mt-2" :message="form.errors.password" />
                            </div>

                            <div class="flex items-center justify-between gap-4">
                                <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-[#4f5e5b]">
                                    <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-[#c8c0b3] text-[#1f5f5b] focus:ring-[#1f5f5b]/30">
                                    <span>Remember me</span>
                                </label>

                                <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm font-medium text-[#1f5f5b] hover:text-[#133d3a] underline-offset-2 hover:underline">
                                    Forgot password?
                                </Link>
                            </div>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="group relative w-full overflow-hidden rounded-xl bg-[#1f5f5b] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#174946] disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <span class="relative">{{ form.processing ? 'Signing in...' : 'Log In' }}</span>
                            </button>
                        </form>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
