<script setup>
import { computed, ref, watch } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, usePage } from '@inertiajs/vue3';

const goBack = () => {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit(route('backend.dashboard'));
    }
};

const page = usePage();
const sessions = computed(() => page.props.sessions || []);
const selectedSessionId = ref(page.props.selectedSession?.id ?? null);
const flash = computed(() => page.props.flash || {});
const sessionSummary = computed(() => page.props.sessionSummary ?? null);
const counter = ref({
    counter_name: 'Counter 1',
    user_name: '',
    shift_name: 'Morning',
    opening_amount: 0,
    opening_note: '',
});

const inputAmount = ref(0);
const inputNote = ref('');
const closeAmount = ref(0);
const handoverAmount = ref(0);
const handoverTarget = ref(null);
const handoverNote = ref('');
const message = ref('');
const handoverSessions = computed(() => sessions.value.filter((session) => session.id !== selectedSessionId.value));

watch(selectedSessionId, (id, prevId) => {
    if (prevId === undefined || !id || id === prevId) {
        return;
    }

    router.get(route('backend.cash-counter.index'), { session_id: id }, {
        preserveState: false,
        replace: true,
    });
});

const startSession = () => {
    router.post(route('backend.cash-counter.start'), {
        ...counter.value,
        opening_amount: Number(counter.value.opening_amount || 0),
    });
};

const recordInput = () => {
    if (!selectedSessionId.value) {
        message.value = 'Select an active session first.';
        return;
    }

    router.post(route('backend.cash-counter.input'), {
        session_id: selectedSessionId.value,
        amount: Number(inputAmount.value || 0),
        note: inputNote.value,
    });
};

const recordHandover = () => {
    if (!selectedSessionId.value || !handoverTarget.value) {
        message.value = 'Select an active session and target session.';
        return;
    }

    router.post(route('backend.cash-counter.handover'), {
        from_session_id: selectedSessionId.value,
        to_session_id: Number(handoverTarget.value),
        amount: Number(handoverAmount.value || 0),
        note: handoverNote.value,
    });
};

const closeSession = () => {
    if (!selectedSessionId.value) {
        message.value = 'Select an active session first.';
        return;
    }

    const printWindow = window.open('', '_blank');

    router.post(route('backend.cash-counter.close'), {
        session_id: selectedSessionId.value,
        closing_amount: Number(closeAmount.value || 0),
    }, {
        onSuccess: (responsePage) => {
            const printUrl = responsePage?.props?.flash?.cashCounterPrintUrl || '';
            if (!printUrl) {
                try { printWindow?.close(); } catch (e) {}
                message.value = 'Report URL not found.';
                return;
            }

            if (printWindow && !printWindow.closed) {
                printWindow.location.href = printUrl;
                try { printWindow.focus(); } catch (e) {}
            } else {
                window.open(printUrl, '_blank');
            }
        },
        onError: () => {
            try { printWindow?.close(); } catch (e) {}
        },
    });
};

const printCurrentSessionSummary = () => {
    if (!selectedSessionId.value) {
        message.value = 'Select an active session first.';
        return;
    }

    const printUrl = route('backend.cash-counter.handover-print', { sessionId: selectedSessionId.value });
    window.open(printUrl, '_blank');
};
</script>

<template>
    <BackendLayout>
        <div class="p-4 bg-white rounded-lg shadow">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-xl font-bold">Cash Counter</h1>
                <button type="button" @click="goBack" class="px-3 py-2 text-sm font-semibold text-white bg-red-600 rounded hover:bg-red-700">
                    Back
                </button>
            </div>
            <div v-if="flash.successMessage || flash.errorMessage || message" class="space-y-2 mb-4">
                <div v-if="flash.successMessage" class="rounded border border-green-300 bg-green-50 p-3 text-sm text-green-800">
                    {{ flash.successMessage }}
                </div>
                <div v-if="flash.errorMessage" class="rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">
                    {{ flash.errorMessage }}
                </div>
                <div v-if="message" class="rounded border border-blue-300 bg-blue-50 p-3 text-sm text-blue-800">
                    {{ message }}
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-[280px_minmax(0,1fr)]">
                <div class="border rounded p-4">
                    <h2 class="font-semibold mb-3">Active Sessions</h2>
                    <div class="space-y-2">
                        <label class="block text-sm font-medium">Select Session</label>
                        <select v-model="selectedSessionId" class="w-full border rounded p-2">
                            <option :value="null">-- Select active session --</option>
                            <option v-for="session in sessions" :key="session.id" :value="session.id">
                                {{ session.counter_name }} — {{ session.user_name }}
                            </option>
                        </select>
                    </div>

                    <div class="text-sm text-slate-600 mt-3">
                        Active sessions: {{ sessions.length }}
                    </div>

                    <div v-if="!sessions.length" class="mt-4 rounded border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-600">
                        No active sessions found. Start a new counter session to begin cash input, handover, or close.
                    </div>

                    <div v-if="sessionSummary" class="mt-4 space-y-2 text-sm text-slate-700">
                        <div><strong>Opening:</strong> {{ sessionSummary.opening_amount }}</div>
                        <div><strong>Expected:</strong> {{ sessionSummary.expected_amount }}</div>
                        <div><strong>Handover In:</strong> {{ sessionSummary.handover_in_amount }}</div>
                        <div><strong>Handover Out:</strong> {{ sessionSummary.handover_out_amount }}</div>
                        <div><strong>Difference:</strong> {{ sessionSummary.difference_amount }}</div>
                        <div v-if="sessionSummary.transactions?.length" class="mt-3">
                            <h3 class="font-semibold">Recent Transactions</h3>
                            <ul class="list-disc list-inside text-sm text-slate-600">
                                <li v-for="transaction in sessionSummary.transactions" :key="transaction.id">
                                    {{ transaction.type }} — {{ transaction.amount }} {{ transaction.note ? `(${transaction.note})` : '' }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="border rounded p-4">
                        <h2 class="font-semibold mb-2">Open Counter</h2>
                        <input v-model="counter.counter_name" class="w-full border rounded p-2 mb-2" placeholder="Counter name" />
                        <input v-model="counter.user_name" class="w-full border rounded p-2 mb-2" placeholder="User name" />
                        <input v-model="counter.shift_name" class="w-full border rounded p-2 mb-2" placeholder="Shift" />
                        <input v-model.number="counter.opening_amount" type="number" class="w-full border rounded p-2 mb-2" placeholder="Opening amount" />
                        <textarea v-model="counter.opening_note" class="w-full border rounded p-2 mb-2" placeholder="Opening note"></textarea>
                        <button class="bg-blue-600 text-white px-3 py-2 rounded" @click="startSession">Start Session</button>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div class="border rounded p-4">
                            <h2 class="font-semibold mb-2">Cash Input</h2>
                            <input v-model.number="inputAmount" type="number" class="w-full border rounded p-2 mb-2" placeholder="Amount" />
                            <input v-model="inputNote" class="w-full border rounded p-2 mb-2" placeholder="Note" />
                            <button :disabled="!selectedSessionId" class="bg-green-600 text-white px-3 py-2 rounded disabled:opacity-50" @click="recordInput">Record Input</button>
                        </div>

                        <div class="border rounded p-4">
                            <h2 class="font-semibold mb-2">Cash Hand Over</h2>
                            <input v-model.number="handoverAmount" type="number" class="w-full border rounded p-2 mb-2" placeholder="Amount" />
                            <select v-model="handoverTarget" class="w-full border rounded p-2 mb-2">
                                <option :value="null">-- Target session --</option>
                                <option v-for="session in handoverSessions" :key="session.id" :value="session.id">
                                    {{ session.counter_name }} — {{ session.user_name }}
                                </option>
                            </select>
                            <textarea v-model="handoverNote" class="w-full border rounded p-2 mb-2" placeholder="Handover note"></textarea>
                            <button :disabled="!selectedSessionId || !handoverTarget" class="bg-purple-600 text-white px-3 py-2 rounded disabled:opacity-50" @click="recordHandover">Record Handover</button>
                        </div>
                    </div>

                    <div class="border rounded p-4">
                        <h2 class="font-semibold mb-2">Cash Close</h2>
                        <input v-model.number="closeAmount" type="number" class="w-full border rounded p-2 mb-2" placeholder="Closing amount" />
                        <div class="flex flex-wrap gap-2">
                            <button :disabled="!selectedSessionId" class="bg-slate-700 text-white px-3 py-2 rounded disabled:opacity-50" @click="printCurrentSessionSummary">Print Summary</button>
                            <button :disabled="!selectedSessionId" class="bg-red-600 text-white px-3 py-2 rounded disabled:opacity-50" @click="closeSession">Close & Print</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </BackendLayout>
</template>
