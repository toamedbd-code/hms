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

        <div class="relative z-10 mx-auto flex min-h-screen max-w-6xl items-center px-4 py-12 sm:px-6 lg:px-8">
            <div class="w-full">
                <div class="grid gap-8 items-center lg:grid-cols-[1.2fr_0.9fr]">
                    <section class="hidden overflow-hidden rounded-[36px] bg-[#0b4f43] p-10 text-white shadow-[0_40px_120px_-60px_rgba(15,45,40,0.7)] lg:block">
                        <div class="flex h-full flex-col justify-between gap-10">
                            <div>
                                <span class="inline-flex rounded-full bg-white/10 px-4 py-1 text-xs uppercase tracking-[0.3em] text-white/80">{{ page.props.loginTexts.banner }}</span>
                                <h1 class="mt-8 text-5xl font-black leading-tight tracking-[-0.03em]">{{ page.props.loginTexts.title }}</h1>
                                <p class="mt-5 max-w-lg text-lg leading-8 text-white/80">{{ page.props.loginTexts.subtitle }}</p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-3">
                                <div class="rounded-3xl bg-white/10 p-5 ring-1 ring-white/10">
                                    <p class="text-xs uppercase tracking-[0.24em] text-white/70">Modules</p>
                                    <p class="mt-4 text-3xl font-semibold">45+</p>
                                </div>
                                <div class="rounded-3xl bg-white/10 p-5 ring-1 ring-white/10">
                                    <p class="text-xs uppercase tracking-[0.24em] text-white/70">Uptime</p>
                                    <p class="mt-4 text-3xl font-semibold">99.9%</p>
                                </div>
                                <div class="rounded-3xl bg-white/10 p-5 ring-1 ring-white/10">
                                    <p class="text-xs uppercase tracking-[0.24em] text-white/70">Support</p>
                                    <p class="mt-4 text-3xl font-semibold">24/7</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="relative overflow-hidden rounded-[36px] bg-white/95 p-6 shadow-xl ring-1 ring-slate-200 sm:p-8 lg:p-10">
                        <div class="absolute -top-10 -right-10 h-36 w-36 rounded-full bg-[#ddece8]/60 blur-3xl"></div>
                        <div class="absolute bottom-0 left-0 h-40 w-40 rounded-full bg-[#daf0e8]/80 blur-3xl"></div>
                        <div class="relative mx-auto max-w-md lg:max-w-lg">
                            <div class="mb-8 text-center lg:text-left">
                                <p class="text-xs uppercase tracking-[0.28em] text-[#1b4b41] font-semibold">Secure Sign In</p>
                                <h2 class="mt-4 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">Admin Login</h2>
                                <p class="mt-3 text-sm text-slate-600">Enter your credentials to access the dashboard and manage subscriptions.</p>
                            </div>

                            <div class="space-y-4">
                                <div v-if="errorMessage" class="rounded-3xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ errorMessage }}</div>
                                <div v-if="successMessage" class="rounded-3xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ successMessage }}</div>
                                <div v-if="warningMessage" class="rounded-3xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700">{{ warningMessage }}</div>
                            </div>

                            <div v-if="bkashEnabled" class="mt-6 rounded-[28px] border border-slate-200 bg-slate-50 p-5 shadow-sm">
                                <div class="flex flex-wrap items-center justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">Subscription &amp; bKash</p>
                                        <p class="mt-1 text-sm text-slate-600">Renew or subscribe instantly from the login page.</p>
                                    </div>
                                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">Powered by bKash</span>
                                </div>
                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <button v-if="bkashMonthlyAmount" @click.prevent="renew('monthly')" class="w-full rounded-2xl bg-[#1f5f5b] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#174946]">
                                        Monthly ৳{{ bkashMonthlyAmount }}
                                    </button>
                                    <button v-if="bkashYearlyAmount" @click.prevent="renew('yearly')" class="w-full rounded-2xl border border-[#1f5f5b] bg-white px-4 py-3 text-sm font-semibold text-[#1f5f5b] transition hover:bg-[#f5faf8]">
                                        Yearly ৳{{ bkashYearlyAmount }}
                                    </button>
                                </div>
                                <div class="mt-4 text-sm text-slate-600">Need trial? <a :href="route('payment.bkash.unsubscribe.public')" class="font-medium text-[#175647] hover:underline">Cancel / go trial</a></div>
                            </div>

                            <form @submit.prevent="submit" class="mt-8 space-y-4">
                                <div>
                                    <label for="email" class="mb-2 block text-sm font-medium text-slate-800">Email</label>
                                    <input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        required
                                        autofocus
                                        autocomplete="username"
                                        placeholder="admin@hospital.com"
                                        class="block w-full rounded-[24px] border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-[#1f5f5b] focus:ring-4 focus:ring-[#1f5f5b]/15"
                                    />
                                    <InputError class="mt-2" :message="form.errors.email" />
                                </div>

                                <div>
                                    <label for="password" class="mb-2 block text-sm font-medium text-slate-800">Password</label>
                                    <input
                                        id="password"
                                        v-model="form.password"
                                        type="password"
                                        required
                                        autocomplete="current-password"
                                        placeholder="Your password"
                                        class="block w-full rounded-[24px] border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-[#1f5f5b] focus:ring-4 focus:ring-[#1f5f5b]/15"
                                    />
                                    <InputError class="mt-2" :message="form.errors.password" />
                                </div>

                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                                        <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-[#1f5f5b] focus:ring-[#1f5f5b]/30" />
                                        <span>Remember me</span>
                                    </label>

                                    <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm font-medium text-[#1f5f5b] hover:text-[#144d44] underline-offset-2 hover:underline">
                                        Forgot password?
                                    </Link>
                                </div>

                                <button type="submit" :disabled="form.processing" class="w-full rounded-[24px] bg-gradient-to-r from-[#1f5f5b] to-[#144d44] px-5 py-3 text-sm font-semibold text-white shadow-lg transition hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-60">
                                    <span>{{ form.processing ? 'Signing in...' : 'Log In' }}</span>
                                </button>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>
