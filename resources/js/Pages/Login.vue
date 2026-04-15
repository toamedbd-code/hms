<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    canResetPassword: Boolean,
    errorMessage: String,
    successMessage: String,
    warningMessage: String,
    subscriptionEnforced: Boolean,
    subscriptionActive: Boolean,
    showSubscriptionRenewal: Boolean,
    bkashEnabled: Boolean,
    bkashMonthlyAmount: [Number, String],
    bkashYearlyAmount: [Number, String],
    subscriptionDefaultPeriod: { type: String, default: 'monthly' },
});
const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('backend.auth.login'), {
        onFinish: () => form.reset('password'),
        onSuccess: () => {

        },
    });
};

const renew = (period = null) => {
    const p = period || props.subscriptionDefaultPeriod || 'monthly';
    const amount = p === 'yearly' ? (props.bkashYearlyAmount || props.bkashMonthlyAmount || '') : (props.bkashMonthlyAmount || '');
    const url = route('payment.bkash.initiate.public', { amount: amount, email: form.email, period: p });
    window.location.href = url;
};
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
                    <p class="inline-flex items-center rounded-full bg-white/15 px-4 py-1 text-xs tracking-[0.25em] uppercase">Hospital Management Suite</p>
                    <h1 class="mt-8 text-5xl xl:text-6xl leading-[1.05] font-semibold">Welcome Back.</h1>
                    <p class="mt-6 max-w-xl text-base xl:text-lg text-white/85">
                        Sign in to continue managing patients, billing, attendance, and reports from one place.
                    </p>
                </div>
                <div class="grid grid-cols-3 gap-3 text-sm">
                    <div class="rounded-2xl bg-white/10 p-4 backdrop-blur-sm border border-white/20">
                        <p class="text-white/70">Modules</p>
                        <p class="mt-1 text-2xl font-semibold">45+</p>
                    </div>
                    <div class="rounded-2xl bg-white/10 p-4 backdrop-blur-sm border border-white/20">
                        <p class="text-white/70">Uptime</p>
                        <p class="mt-1 text-2xl font-semibold">99.9%</p>
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
                        <h2 class="mt-2 text-3xl sm:text-4xl font-semibold text-[#15211f]">Admin Login</h2>
                        <p class="mt-3 text-sm text-[#4f5e5b]">Enter your credentials to access the dashboard.</p>
                    </div>

                    <div class="rounded-3xl border border-[#d5d0c7] bg-white/90 p-6 sm:p-8 shadow-[0_20px_70px_-35px_rgba(20,33,31,0.45)] backdrop-blur">
                        <div v-if="errorMessage" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
                            {{ errorMessage }}
                        </div>
                        <div v-if="successMessage" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">
                            {{ successMessage }}
                        </div>
                        <div v-if="warningMessage" class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700">
                            {{ warningMessage }}
                        </div>

                        <div v-if="(showSubscriptionRenewal || (subscriptionEnforced && !subscriptionActive)) && bkashEnabled" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-3 text-sm text-rose-700">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium">Subscription required</p>
                                    <p class="text-xs mt-1">Your system subscription is inactive. Renew the monthly subscription to continue.</p>
                                </div>
                                        <div class="ml-4 flex gap-2">
                                            <button v-if="bkashMonthlyAmount" @click.prevent="renew('monthly')" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">
                                                Pay Monthly — {{ bkashMonthlyAmount }}
                                            </button>
                                            <button v-if="bkashYearlyAmount" @click.prevent="renew('yearly')" class="rounded-lg bg-rose-500 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-600">
                                                Pay Yearly — {{ bkashYearlyAmount }}
                                            </button>
                                        </div>
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
                                <input
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    required
                                    autocomplete="current-password"
                                    placeholder="Your password"
                                    class="block w-full rounded-xl border border-[#d2cbc0] bg-[#fcfbf9] px-4 py-2.5 text-sm text-[#1d2b28] outline-none transition focus:border-[#1f5f5b] focus:ring-4 focus:ring-[#1f5f5b]/15"
                                >
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
