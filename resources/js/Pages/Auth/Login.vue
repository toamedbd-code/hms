<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import InputError from '@/Components/InputError.vue';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);
const page = usePage();

watch(() => form.password, password => {
    if (!password) {
        showPassword.value = false;
    }
});

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
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
                                class="group relative inline-flex items-center justify-center gap-2 w-full overflow-hidden rounded-xl bg-[#1f5f5b] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#174946] disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <svg v-if="form.processing" class="w-4 h-4 animate-spin flex-shrink-0" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                                </svg>
                                <span>{{ form.processing ? 'Signing in...' : 'Log In' }}</span>
                            </button>
                        </form>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
