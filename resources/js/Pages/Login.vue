<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
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

const page = usePage();

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
            <div class="absolute -top-24 -left-24 h-80 w-80 rounded-full bg-[#d96f32]/20 blur-3xl"></div>
            <div class="absolute top-1/4 -right-16 h-80 w-80 rounded-full bg-[#1f5f5b]/15 blur-3xl"></div>
            <div class="absolute -bottom-24 left-1/4 h-72 w-72 rounded-full bg-[#e2b95f]/15 blur-3xl"></div>
        </div>

        <div class="relative z-10 mx-auto flex min-h-screen max-w-6xl items-center px-4 py-6 sm:px-6 lg:px-8">
            <div class="w-full">
                <div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
                    <section class="hidden rounded-[32px] border border-white/20 bg-gradient-to-br from-[#0f3a37] via-[#16524f] to-[#1f5f5b] p-6 text-white shadow-2xl backdrop-blur-xl lg:grid">
                        <div class="flex h-full flex-col justify-center gap-6">
                            <div>
                                <span class="inline-flex rounded-full bg-white/15 px-4 py-1 text-xs uppercase tracking-[0.25em]">{{ page.props.loginTexts.banner }}</span>
                                <h1 class="mt-8 text-5xl font-semibold leading-tight">{{ page.props.loginTexts.title }}</h1>
                                <p class="mt-6 max-w-md text-base text-white/80">{{ page.props.loginTexts.subtitle }}</p>
                            </div>

                            <div class="grid gap-4">
                                <div class="rounded-3xl bg-white/10 p-5 backdrop-blur-sm border border-white/15">
                                    <p class="text-sm text-white/70">Smooth workflow</p>
                                    <p class="mt-3 text-2xl font-semibold">45+</p>
                                </div>
                                <div class="rounded-3xl bg-white/10 p-5 backdrop-blur-sm border border-white/15">
                                    <p class="text-sm text-white/70">Uptime</p>
                                    <p class="mt-3 text-2xl font-semibold">99.9%</p>
                                </div>
                                <div class="rounded-3xl bg-white/10 p-5 backdrop-blur-sm border border-white/15">
                                    <p class="text-sm text-white/70">Support</p>
                                    <p class="mt-3 text-2xl font-semibold">24/7</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[32px] border border-[#dcd7cf] bg-white/95 p-5 shadow-[0_30px_90px_-40px_rgba(20,33,31,0.35)] sm:p-6 lg:p-8 h-full">
                        <div class="flex h-full flex-col gap-4 justify-between">
                            <div class="text-center lg:text-left">
                                <p class="text-xs uppercase tracking-[0.25em] text-[#1f5f5b] font-semibold">Secure Sign In</p>
                                <h2 class="mt-3 text-3xl font-semibold sm:text-4xl">Admin Login</h2>
                                <p class="mt-3 text-sm text-[#4f5e5b]">Enter your credentials to access the dashboard and renew subscription if required.</p>
                            </div>

                            <div class="space-y-3">
                                <div v-if="errorMessage" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ errorMessage }}</div>
                                <div v-if="successMessage" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ successMessage }}</div>
                                <div v-if="warningMessage" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700">{{ warningMessage }}</div>
                            </div>

                            <div v-if="(showSubscriptionRenewal || (subscriptionEnforced && !subscriptionActive)) && bkashEnabled" class="rounded-[20px] border border-slate-200 bg-slate-50 p-4 text-slate-700 shadow-sm">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">Subscription Payment</p>
                                    <p class="mt-2 text-sm text-slate-600">Renew your access using bKash and continue using the system seamlessly.</p>
                                </div>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <button
                                        v-if="bkashMonthlyAmount"
                                        @click.prevent="renew('monthly')"
                                        class="rounded-2xl bg-[#1f5f5b] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#174946]"
                                    >
                                        Pay Monthly — {{ bkashMonthlyAmount }}
                                    </button>
                                    <button
                                        v-if="bkashYearlyAmount"
                                        @click.prevent="renew('yearly')"
                                        class="rounded-2xl border border-[#1f5f5b] bg-white px-4 py-3 text-sm font-semibold text-[#1f5f5b] transition hover:bg-[#f5faf8]"
                                    >
                                        Pay Yearly — {{ bkashYearlyAmount }}
                                    </button>
                                </div>
                            </div>

                            <form @submit.prevent="submit" class="space-y-4">
                                <div>
                                    <label for="email" class="mb-2 block text-sm font-medium text-[#2c3b38]">Email</label>
                                    <input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        required
                                        autofocus
                                        autocomplete="username"
                                        placeholder="admin@hospital.com"
                                        class="block w-full rounded-2xl border border-[#d2cbc0] bg-[#fcfbf9] px-4 py-3 text-sm text-[#1d2b28] outline-none transition focus:border-[#1f5f5b] focus:ring-4 focus:ring-[#1f5f5b]/15"
                                    />
                                    <InputError class="mt-2" :message="form.errors.email" />
                                </div>

                                <div>
                                    <label for="password" class="mb-2 block text-sm font-medium text-[#2c3b38]">Password</label>
                                    <input
                                        id="password"
                                        v-model="form.password"
                                        type="password"
                                        required
                                        autocomplete="current-password"
                                        placeholder="Your password"
                                        class="block w-full rounded-2xl border border-[#d2cbc0] bg-[#fcfbf9] px-4 py-3 text-sm text-[#1d2b28] outline-none transition focus:border-[#1f5f5b] focus:ring-4 focus:ring-[#1f5f5b]/15"
                                    />
                                    <InputError class="mt-2" :message="form.errors.password" />
                                </div>

                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-[#4f5e5b]">
                                        <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-[#c8c0b3] text-[#1f5f5b] focus:ring-[#1f5f5b]/30" />
                                        <span>Remember me</span>
                                    </label>

                                    <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm font-medium text-[#1f5f5b] hover:text-[#133d3a] underline-offset-2 hover:underline">
                                        Forgot password?
                                    </Link>
                                </div>

                                <button type="submit" :disabled="form.processing" class="group relative w-full overflow-hidden rounded-2xl bg-[#1f5f5b] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#174946] disabled:cursor-not-allowed disabled:opacity-60">
                                    <span class="relative">{{ form.processing ? 'Signing in...' : 'Log In' }}</span>
                                </button>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>
