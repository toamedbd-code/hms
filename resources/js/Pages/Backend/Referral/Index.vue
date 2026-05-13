<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import BackendLayout from '@/Layouts/BackendLayout.vue';
import BaseTable from '@/Components/BaseTable.vue';
import Pagination from '@/Components/Pagination.vue';
import { router, usePage } from '@inertiajs/vue3';
import { route }  from 'ziggy-js';

let props = defineProps({
    filters: Object,
});

const filters = ref({

    numOfData: props.filters?.numOfData ?? 10,
});

const isProcessing = ref(false);
const toastMessage = ref('');
let toastTimer = null;
const page = usePage();
let lastActionToken = '';
let lastActionAt = 0;
const ACTION_DEDUP_MS = 200;
const showPartialModal = ref(false);
const partialForm = ref({
    payeeId: null,
    payeeName: 'N/A',
    payeePhone: 'N/A',
    commissionAmount: '৳0.00',
    paidAmount: '৳0.00',
    pendingDisplay: '৳0.00',
    pendingAmount: 0,
    amount: ''
});

const resolveRoute = (name, params = {}) => {
    try {
        return route(name, params);
    } catch (error) {
        const globalRoute = typeof window !== 'undefined' ? window.route : null;
        if (typeof globalRoute === 'function') {
            try {
                return globalRoute(name, params);
            } catch (innerError) {
                console.error('Ziggy global route() failed', innerError);
            }
        }
        console.error('Ziggy route() unavailable', { name, params, error });
        return null;
    }
};

onMounted(() => {
    console.log('Referral Index mounted');
});

onUnmounted(() => {
});

const showToast = (message) => {
    toastMessage.value = message;
    if (toastTimer) {
        clearTimeout(toastTimer);
    }
    toastTimer = setTimeout(() => {
        toastMessage.value = '';
    }, 3000);
};

const applyFilter = () => {
    const url = resolveRoute('backend.referral.index');
    if (!url) return;
    router.get(url, filters.value, { preserveState: true });
};
const goToRefferalAdd = () => {
    const url = resolveRoute('backend.referral.create');
    if (!url) return;
    router.get(url)
}

const handleAction = (actionName, actionId) => {
    lastActionToken = `${actionName}|${actionId}`;
    lastActionAt = Date.now();
    console.log('Referral Index handleAction', { actionName, actionId, source: 'vue' });
    if (isProcessing.value) return;
    if (!actionId) return;

    const [payeeId, pendingStr] = String(actionId).split('|');
    const pendingAmount = Number(pendingStr || 0);
    const hasPending = Number.isFinite(pendingAmount) && pendingAmount > 0;

    if (actionName === 'commission-pay-full') {
        if (!hasPending) {
            const proceed = confirm('No pending amount detected. Proceed to sync paid status?');
            if (!proceed) return;
        }

        const postUrl = resolveRoute('backend.referral.commission.payment.payee', { payeeId });
        if (!postUrl) return;

        console.log('Payment button clicked', {
            payeeId,
            paymentType: 'paid',
            amount: hasPending ? pendingAmount : null,
            url: postUrl
        });

        console.log('About to POST commission payment', { payeeId, paymentType: 'paid' });

        const payload = {
            payment_type: 'paid'
        };
        if (hasPending) {
            payload.amount = pendingAmount;
        }

        router.post(postUrl, payload, {
            preserveScroll: true,
            onStart: () => {
                console.log('Commission payment POST started', { payeeId, paymentType: 'paid' });
                isProcessing.value = true;
            },
            onSuccess: () => {
                localStorage.setItem('dashboard:refresh', String(Date.now()));
                window.dispatchEvent(new Event('dashboard:refresh'));
                showToast(page.props?.flash?.successMessage || 'Commission payment updated.');
                router.reload({ only: ['datas'] });
            },
            onError: (errors) => {
                console.error('Commission payment POST failed', errors);
            },
            onFinish: () => {
                console.log('Commission payment POST finished', { payeeId, paymentType: 'paid' });
                isProcessing.value = false;
            }
        });
        return;
    }

    if (actionName === 'commission-pay-partial') {
        if (!hasPending) {
            alert('No pending amount available.');
            return;
        }

        openPartialModal(payeeId, pendingAmount);
    }
};

const openPartialModal = (payeeId, pendingAmount) => {
    const rows = page.props?.datas?.data || [];
    const row = rows.find((item) => String(item.payee_id) === String(payeeId));

    partialForm.value.payeeId = payeeId;
    partialForm.value.payeeName = row?.payee_name || 'N/A';
    partialForm.value.payeePhone = row?.payee_phone || 'N/A';
    partialForm.value.commissionAmount = row?.commission_amount || '৳0.00';
    partialForm.value.paidAmount = row?.paid_amount || '৳0.00';
    partialForm.value.pendingDisplay = row?.pending_amount || `৳${Number(pendingAmount || 0).toFixed(2)}`;
    partialForm.value.pendingAmount = Number(pendingAmount || 0);
    partialForm.value.amount = '';
    showPartialModal.value = true;
};

const closePartialModal = () => {
    showPartialModal.value = false;
    partialForm.value.amount = '';
};

const submitPartialPayment = () => {
    const pendingAmount = Number(partialForm.value.pendingAmount || 0);
    const amount = Number(partialForm.value.amount || 0);

    if (!Number.isFinite(amount) || amount <= 0 || amount > pendingAmount) {
        alert('Invalid amount.');
        return;
    }

    const postUrl = resolveRoute('backend.referral.commission.payment.payee', { payeeId: partialForm.value.payeeId });
    if (!postUrl) return;

    router.post(postUrl, {
        payment_type: 'partial',
        amount
    }, {
        preserveScroll: true,
        onStart: () => {
            isProcessing.value = true;
        },
        onSuccess: () => {
            localStorage.setItem('dashboard:refresh', String(Date.now()));
            window.dispatchEvent(new Event('dashboard:refresh'));
            showToast(page.props?.flash?.successMessage || 'Commission payment updated.');
            closePartialModal();
            router.reload({ only: ['datas'] });
        },
        onError: (errors) => {
            console.error('Commission payment POST failed', errors);
        },
        onFinish: () => {
            isProcessing.value = false;
        }
    });
};

</script>

<template>
    <BackendLayout>

        <div class="w-full p-2 duration-1000 ease-in-out bg-white rounded-md dark:bg-slate-900">

            <div v-if="$page.props.flash?.successMessage"
                class="mb-3 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ $page.props.flash.successMessage }}
            </div>
            <div v-if="$page.props.flash?.errorMessage"
                class="mb-3 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $page.props.flash.errorMessage }}
            </div>
            <div v-if="isProcessing"
                class="mb-3 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
                Processing commission payment...
            </div>
            <div v-if="toastMessage"
                class="fixed right-6 top-20 z-50 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 shadow">
                {{ toastMessage }}
            </div>

            <div
                class="flex mb-2 items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md dark:bg-gray-800 dark:text-gray-200 shadow-gray-800/50">
                <div>
                    <h1 class="p-4 text-xl font-bold dark:text-white">{{ $page.props.pageTitle }}</h1>
                </div>

                <div class="p-4 py-2 flex items-center space-x-2">
                    <div class="flex items-center space-x-3">
                        <button @click="goToRefferalAdd"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out"
                            style="background: linear-gradient(to right, #3b82f6, #60a5fa);"
                            onmouseover="this.style.background='linear-gradient(to right, #2563eb, #3b82f6)';"
                            onmouseout="this.style.background='linear-gradient(to right, #3b82f6, #60a5fa)';">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15">
                                </path>
                            </svg>
                            Refferal Add
                        </button>
                    </div>
                </div>
            </div>
            <div
                class="flex justify-between w-full p-2 py-3 space-x-2 text-gray-700 rounded-md bg-slate-300 shadow-gray-800/50 dark:bg-gray-700 dark:text-gray-200">

                <div class="grid w-full grid-cols-1 gap-2 md:grid-cols-5">

                    <div class="flex space-x-2">
                        <div class="w-full">
                            <input id="name" v-model="filters.name"
                                class="block w-full p-2 text-sm rounded-md border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                type="text" placeholder="Name" @input="applyFilter" />
                        </div>

                        <div class="block min-w-24 md:hidden">
                            <select v-model="filters.numOfData" @change="applyFilter"
                                class="w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                                <option value="10">Show 10</option>
                                <option value="20">Show 20</option>
                                <option value="30">Show 30</option>
                                <option value="40">Show 40</option>
                                <option value="100">Show 100</option>
                                <option value="150">Show 150</option>
                                <option value="500">Show 500</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="hidden min-w-24 md:block">
                    <select v-model="filters.numOfData" @change="applyFilter"
                        class="w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                        <option value="10">show 10</option>
                        <option value="20">show 20</option>
                        <option value="30">show 30</option>
                        <option value="40">show 40</option>
                        <option value="100">show 100</option>
                        <option value="150">show 150</option>
                        <option value="500">show 500</option>
                    </select>
                </div>
            </div>

            <div class="w-full my-3 overflow-x-auto">
                <BaseTable @action="handleAction" :isProcessing="isProcessing" />
            </div>
            <Pagination />
        </div>

        <div v-if="showPartialModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b px-5 py-3">
                    <h3 class="text-base font-semibold text-gray-800">Partial Commission Payment</h3>
                    <button type="button" class="text-gray-500 hover:text-gray-700" @click="closePartialModal">✕</button>
                </div>
                <div class="px-5 py-4">
                    <table class="w-full text-sm text-gray-700">
                        <tr>
                            <td class="py-1 font-semibold">Payee</td>
                            <td class="py-1">{{ partialForm.payeeName }}</td>
                        </tr>
                        <tr>
                            <td class="py-1 font-semibold">Phone</td>
                            <td class="py-1">{{ partialForm.payeePhone }}</td>
                        </tr>
                        <tr>
                            <td class="py-1 font-semibold">Total Commission</td>
                            <td class="py-1">{{ partialForm.commissionAmount }}</td>
                        </tr>
                        <tr>
                            <td class="py-1 font-semibold">Paid</td>
                            <td class="py-1">{{ partialForm.paidAmount }}</td>
                        </tr>
                        <tr>
                            <td class="py-1 font-semibold">Pending</td>
                            <td class="py-1 text-red-600 font-semibold">{{ partialForm.pendingDisplay }}</td>
                        </tr>
                    </table>

                    <div class="mt-4">
                        <label class="mb-1 block text-sm font-semibold text-gray-700">Pay Amount</label>
                        <input
                            v-model="partialForm.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            :max="partialForm.pendingAmount"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                        >
                        <p class="mt-1 text-xs text-gray-500">Max: ৳{{ Number(partialForm.pendingAmount || 0).toFixed(2) }}</p>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 border-t px-5 py-3">
                    <button type="button" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700" @click="closePartialModal">Cancel</button>
                    <button
                        type="button"
                        class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                        :disabled="isProcessing"
                        @click="submitPartialPayment"
                    >
                        Collect Due
                    </button>
                </div>
            </div>
        </div>
    </BackendLayout>
</template>
