<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import { reactive } from 'vue';

const props = defineProps({
    pageTitle: { type: String, default: 'Bulk SMS' },
    activePatientCount: { type: Number, default: 0 },
    logs: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({ total: 0, queued: 0, retrying: 0, sent: 0, failed: 0 }) },
});

const form = useForm({
    recipient_scope: 'all_active',
    patient_ids: '',
    message: '',
});

const submit = () => {
    form.post(route('backend.bulk-sms.send'));
};

const filterForm = reactive({
    status: props.filters?.status ?? '',
    batch_id: props.filters?.batch_id ?? '',
    phone: props.filters?.phone ?? '',
    from_date: props.filters?.from_date ?? '',
    to_date: props.filters?.to_date ?? '',
});

const applyFilters = () => {
    router.get(route('backend.bulk-sms.index'), filterForm, {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    filterForm.status = '';
    filterForm.batch_id = '';
    filterForm.phone = '';
    filterForm.from_date = '';
    filterForm.to_date = '';
    applyFilters();
};

const visitPage = (url) => {
    if (!url) return;
    const page = new URL(url).searchParams.get('page') || 1;
    router.get(route('backend.bulk-sms.index'), {
        ...filterForm,
        page,
    }, {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <BackendLayout>
        <div class="w-full rounded-md bg-white p-4">
            <div class="mb-4 rounded-md bg-slate-100 p-4">
                <h1 class="text-xl font-bold text-slate-800">{{ pageTitle }}</h1>
                <p class="mt-1 text-xs text-slate-600">
                    Active patient with phone: {{ activePatientCount }}
                </p>
                <p class="mt-1 text-xs text-slate-600">
                    Queue worker চালু থাকলে SMS background-এ retry সহ যাবে।
                </p>
            </div>

            <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-5">
                <div class="rounded-md border bg-slate-50 p-3">
                    <div class="text-[11px] font-medium text-slate-500">Total</div>
                    <div class="mt-1 text-lg font-bold text-slate-800">{{ summary.total ?? 0 }}</div>
                </div>
                <div class="rounded-md border bg-amber-50 p-3">
                    <div class="text-[11px] font-medium text-amber-700">Queued</div>
                    <div class="mt-1 text-lg font-bold text-amber-800">{{ summary.queued ?? 0 }}</div>
                </div>
                <div class="rounded-md border bg-yellow-50 p-3">
                    <div class="text-[11px] font-medium text-yellow-700">Retrying</div>
                    <div class="mt-1 text-lg font-bold text-yellow-800">{{ summary.retrying ?? 0 }}</div>
                </div>
                <div class="rounded-md border bg-emerald-50 p-3">
                    <div class="text-[11px] font-medium text-emerald-700">Sent</div>
                    <div class="mt-1 text-lg font-bold text-emerald-800">{{ summary.sent ?? 0 }}</div>
                </div>
                <div class="rounded-md border bg-rose-50 p-3">
                    <div class="text-[11px] font-medium text-rose-700">Failed</div>
                    <div class="mt-1 text-lg font-bold text-rose-800">{{ summary.failed ?? 0 }}</div>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-4 rounded-md border p-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Recipient Scope</label>
                        <select v-model="form.recipient_scope" class="block w-full rounded-md border-slate-300 p-2 text-sm">
                            <option value="all_active">All Active Patients</option>
                            <option value="selected">Selected Patient IDs</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.recipient_scope" />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Patient IDs (comma separated)</label>
                        <input
                            v-model="form.patient_ids"
                            type="text"
                            class="block w-full rounded-md border-slate-300 p-2 text-sm"
                            placeholder="12, 14, 18"
                            :disabled="form.recipient_scope !== 'selected'"
                        />
                        <InputError class="mt-1" :message="form.errors.patient_ids" />
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Message</label>
                    <textarea
                        v-model="form.message"
                        rows="5"
                        class="block w-full rounded-md border-slate-300 p-2 text-sm"
                        placeholder="Write your bulk SMS message"
                    />
                    <InputError class="mt-1" :message="form.errors.message" />
                </div>

                <div class="flex items-center justify-end">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                    >
                        {{ form.processing ? 'Sending...' : 'Send Bulk SMS' }}
                    </button>
                </div>
            </form>

            <div class="mt-6 rounded-md border p-4">
                <h2 class="text-sm font-semibold text-slate-800">Recent SMS History</h2>

                <form @submit.prevent="applyFilters" class="mt-3 rounded-md border bg-slate-50 p-3">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                        <div>
                            <label class="mb-1 block text-[11px] font-medium text-slate-600">Status</label>
                            <select v-model="filterForm.status" class="block w-full rounded-md border-slate-300 p-2 text-xs">
                                <option value="">All</option>
                                <option value="queued">Queued</option>
                                <option value="retrying">Retrying</option>
                                <option value="sent">Sent</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-medium text-slate-600">Batch ID</label>
                            <input v-model="filterForm.batch_id" type="text" class="block w-full rounded-md border-slate-300 p-2 text-xs" placeholder="uuid" />
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-medium text-slate-600">Phone</label>
                            <input v-model="filterForm.phone" type="text" class="block w-full rounded-md border-slate-300 p-2 text-xs" placeholder="01..." />
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-medium text-slate-600">From</label>
                            <input v-model="filterForm.from_date" type="date" class="block w-full rounded-md border-slate-300 p-2 text-xs" />
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-medium text-slate-600">To</label>
                            <input v-model="filterForm.to_date" type="date" class="block w-full rounded-md border-slate-300 p-2 text-xs" />
                        </div>
                    </div>
                    <div class="mt-3 flex items-center justify-end gap-2">
                        <button type="button" @click="resetFilters" class="rounded-md border bg-white px-3 py-1.5 text-xs font-semibold text-slate-700">Reset</button>
                        <button type="submit" class="rounded-md bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white">Apply Filter</button>
                    </div>
                </form>

                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-100 text-left text-slate-700">
                                <th class="border px-2 py-2">Time</th>
                                <th class="border px-2 py-2">Batch</th>
                                <th class="border px-2 py-2">Phone</th>
                                <th class="border px-2 py-2">Status</th>
                                <th class="border px-2 py-2">Attempts</th>
                                <th class="border px-2 py-2">Provider</th>
                                <th class="border px-2 py-2">Message</th>
                                <th class="border px-2 py-2">Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in logs.data" :key="item.id" class="text-slate-700">
                                <td class="border px-2 py-2">{{ item.created_at }}</td>
                                <td class="border px-2 py-2">{{ item.batch_id }}</td>
                                <td class="border px-2 py-2">{{ item.phone }}</td>
                                <td class="border px-2 py-2">
                                    <span
                                        class="rounded px-2 py-1"
                                        :class="{
                                            'bg-emerald-100 text-emerald-700': item.status === 'sent',
                                            'bg-amber-100 text-amber-700': item.status === 'retrying' || item.status === 'queued',
                                            'bg-rose-100 text-rose-700': item.status === 'failed',
                                        }"
                                    >
                                        {{ item.status }}
                                    </span>
                                </td>
                                <td class="border px-2 py-2">{{ item.attempts }}</td>
                                <td class="border px-2 py-2">{{ item.provider_status_code ?? '-' }}</td>
                                <td class="border px-2 py-2">{{ item.message }}</td>
                                <td class="border px-2 py-2">{{ item.error_message ?? '-' }}</td>
                            </tr>
                            <tr v-if="!logs.data?.length">
                                <td class="border px-2 py-3 text-center text-slate-500" colspan="8">No SMS history yet</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 flex flex-wrap items-center justify-end gap-1" v-if="logs.links?.length">
                    <button
                        v-for="link in logs.links"
                        :key="link.label"
                        type="button"
                        class="rounded border px-2 py-1 text-xs"
                        :class="link.active ? 'border-slate-800 bg-slate-800 text-white' : 'border-slate-300 bg-white text-slate-700 disabled:opacity-50'"
                        :disabled="!link.url"
                        @click="visitPage(link.url)"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </BackendLayout>
</template>
