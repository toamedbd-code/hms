<script setup>
import { computed, ref } from "vue";
import BackendLayout from '@/Layouts/BackendLayout.vue';
import BaseTable from '@/Components/BaseTable.vue';
import Pagination from '@/Components/Pagination.vue';
import { router, usePage } from '@inertiajs/vue3';
import { useModalSubmissionGuard } from '@/Composables/useModalSubmissionGuard';

let props = defineProps({
    filters: Object,
    isWebsiteInbox: Boolean,
    doctors: Array,
});

const filters = ref({
    booking_source: props.filters?.booking_source ?? '',
    name: props.filters?.name ?? '',
    doctor_id: props.filters?.doctor_id ?? '',
    appointment_date: props.filters?.appointment_date ?? '',
    numOfData: props.filters?.numOfData ?? 10,
});
const doctorSearch = ref(
    (props.doctors ?? []).find((doctor) => String(doctor.id) === String(filters.value.doctor_id))?.name ?? ''
);
const showDoctorOptions = ref(false);
const doctorSelectedIndex = ref(-1);
const filteredDoctors = computed(() => {
    const search = doctorSearch.value.trim().toLowerCase();
    return (props.doctors ?? []).filter((doctor) =>
        String(doctor.name ?? '').toLowerCase().includes(search)
    );
});
const getDoctorNameParts = (name) => {
    const value = String(name ?? '');
    const search = doctorSearch.value.trim();
    const matchIndex = search ? value.toLowerCase().indexOf(search.toLowerCase()) : -1;

    if (matchIndex < 0) return { before: value, match: '', after: '' };
    return {
        before: value.slice(0, matchIndex),
        match: value.slice(matchIndex, matchIndex + search.length),
        after: value.slice(matchIndex + search.length),
    };
};
const page = usePage();
const showDueModal = ref(false);
const dueForm = ref({
    appointmentId: null,
    opdPatientId: null,
    invoice: 'N/A',
    patientName: 'N/A',
    total: 0,
    paid: 0,
    due: 0,
    amount: '',
});
const {
    isSubmitting: isSubmittingDue,
    prepareSubmissionToken,
    ensureSubmissionToken,
    beginSubmission,
    endSubmission,
    resetSubmissionToken,
} = useModalSubmissionGuard('appointment_due');

const applyFilter = () => {
    router.get(route('backend.appoinment.index'), filters.value, { preserveState: true });
};

const selectDoctor = (doctor) => {
    filters.value.doctor_id = doctor?.id ?? '';
    doctorSearch.value = doctor?.name ?? '';
    showDoctorOptions.value = false;
    applyFilter();
};

const selectFirstDoctor = () => {
    const firstMatch = filteredDoctors.value[doctorSelectedIndex.value] ?? filteredDoctors.value[0];
    if (firstMatch) selectDoctor(firstMatch);
    else showDoctorOptions.value = false;
};

const handleDoctorSearchKeydown = (event) => {
    if (event.key === 'ArrowDown' && filteredDoctors.value.length) {
        event.preventDefault();
        doctorSelectedIndex.value = Math.min(doctorSelectedIndex.value + 1, filteredDoctors.value.length - 1);
    } else if (event.key === 'ArrowUp' && filteredDoctors.value.length) {
        event.preventDefault();
        doctorSelectedIndex.value = Math.max(doctorSelectedIndex.value - 1, 0);
    } else if (event.key === 'Enter') {
        event.preventDefault();
        selectFirstDoctor();
    } else if (event.key === 'Escape') {
        showDoctorOptions.value = false;
        doctorSelectedIndex.value = -1;
    }
};

const printSerialList = () => {
    if (!filters.value.doctor_id || !filters.value.appointment_date) return;

    const url = route('backend.appoinment.serial-print', {
        doctor_id: filters.value.doctor_id,
        appointment_date: filters.value.appointment_date,
    });
    window.open(url, '_blank', 'noopener');
};

const printTokenList = () => {
    if (!filters.value.doctor_id || !filters.value.appointment_date) return;

    const url = route('backend.appoinment.token-print', {
        doctor_id: filters.value.doctor_id,
        appointment_date: filters.value.appointment_date,
    });
    window.open(url, '_blank', 'noopener');
};

const openDueModal = (appointmentId) => {
    const row = (page.props.datas?.data ?? []).find(
        (item) => String(item.appointment_id) === String(appointmentId)
    );
    if (!row?.opd_patient_id) return;

    dueForm.value = {
        appointmentId: row.appointment_id,
        opdPatientId: row.opd_patient_id,
        invoice: row.opd_invoice || 'N/A',
        patientName: row.patient_id || 'N/A',
        total: Number(row.total_amount || 0),
        paid: Number(row.paid_amount || 0),
        due: Number(row.due_amount || 0),
        amount: '',
    };
    prepareSubmissionToken();
    showDueModal.value = true;
};

const closeDueModal = (force = false) => {
    if (isSubmittingDue.value && !force) return;
    showDueModal.value = false;
    dueForm.value.amount = '';
    resetSubmissionToken();
};

const submitDueCollect = () => {
    if (isSubmittingDue.value) return;

    const amount = Number(dueForm.value.amount || 0);
    if (!Number.isFinite(amount) || amount <= 0 || amount > dueForm.value.due) {
        alert('Invalid amount.');
        return;
    }

    beginSubmission();
    router.post(route('backend.opd.due.collect.store', dueForm.value.opdPatientId), {
        amount,
        submission_token: ensureSubmissionToken(),
        return_to: window.location.href,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            closeDueModal(true);
            router.reload({ only: ['datas'] });
        },
        onFinish: endSubmission,
    });
};

const handleTableAction = (actionName, actionId) => {
    if (actionName === 'appointment-due-collect') openDueModal(actionId);
};

const formatMoney = (amount) => Number(amount || 0).toFixed(2);

const goToAppoinmentAdd = () => {
    router.visit(route('backend.appoinment.create'));
};

const goToWebsiteInbox = () => {
    router.visit(route('backend.appoinment.website-inbox'));
};

const goToAllAppointments = () => {
    router.visit(route('backend.appoinment.index'));
};

const goBack = () => {
    if (window.history.length > 1) {
        window.history.back();
        return;
    }
    router.visit(route('backend.dashboard'));
};

</script>

<template>
    <BackendLayout>

        <div class="w-full p-2 duration-1000 ease-in-out bg-white rounded-md dark:bg-slate-900">

            <div
                class="flex mb-2 items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md dark:bg-gray-800 dark:text-gray-200 shadow-gray-800/50">
                <div>
                    <h1 class="p-4 text-xl font-bold dark:text-white">{{ $page.props.pageTitle }}</h1>
                </div>

                <div class="p-4 py-2 flex items-center space-x-2">
                    <div class="flex items-center space-x-3">
                        <button @click="goBack"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gray-500 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gray-600">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Back
                        </button>

                        <button @click="goToAppoinmentAdd"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out"
                            style="background: linear-gradient(to right, #3b82f6, #60a5fa);"
                            onmouseover="this.style.background='linear-gradient(to right, #2563eb, #3b82f6)';"
                            onmouseout="this.style.background='linear-gradient(to right, #3b82f6, #60a5fa)';">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15">
                                </path>
                            </svg>
                            Appoinment Add
                        </button>

                        <button v-if="!isWebsiteInbox" @click="goToWebsiteInbox"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out bg-indigo-600 hover:bg-indigo-700">
                            Website Inbox
                        </button>

                        <button v-else @click="goToAllAppointments"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out bg-slate-600 hover:bg-slate-700">
                            All Appointments
                        </button>
                    </div>
                </div>
            </div>
            <div
                class="flex justify-between w-full p-2 py-3 space-x-2 text-gray-700 rounded-md bg-slate-300 shadow-gray-800/50 dark:bg-gray-700 dark:text-gray-200">

                <div class="grid w-full grid-cols-1 gap-2 md:grid-cols-2 lg:grid-cols-5">

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

                    <div class="relative w-full">
                        <input
                            v-model="doctorSearch"
                            type="text"
                            autocomplete="off"
                            role="combobox"
                            aria-label="Search doctor"
                            :aria-expanded="showDoctorOptions"
                            placeholder="Search doctor"
                            @focus="showDoctorOptions = true"
                            @input="showDoctorOptions = true"
                            @blur="showDoctorOptions = false"
                            @keydown="handleDoctorSearchKeydown"
                            class="block w-full rounded-md border-slate-300 p-2 text-sm dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200"
                        />
                        <div v-if="showDoctorOptions" class="absolute z-30 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-600 dark:bg-slate-800">
                            <button type="button" @mousedown.prevent="selectDoctor(null)" class="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700">All Doctors</button>
                            <button v-for="(doctor, index) in filteredDoctors" :key="doctor.id" type="button" @mousedown.prevent="selectDoctor(doctor)" @mouseenter="doctorSelectedIndex = index" :class="['block w-full px-3 py-2 text-left text-sm', index === doctorSelectedIndex ? 'bg-blue-600 font-semibold text-white' : 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700']">
                                <span>{{ getDoctorNameParts(doctor.name).before }}</span><mark v-if="getDoctorNameParts(doctor.name).match" class="rounded-sm bg-amber-200 px-0.5 text-slate-900">{{ getDoctorNameParts(doctor.name).match }}</mark><span>{{ getDoctorNameParts(doctor.name).after }}</span>
                            </button>
                            <div v-if="filteredDoctors.length === 0" class="px-3 py-2 text-sm text-slate-500">No doctors found</div>
                        </div>
                    </div>

                    <input v-model="filters.appointment_date" @change="applyFilter"
                        class="block w-full p-2 text-sm rounded-md border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200"
                        type="date" aria-label="Appointment date" />

                    <button type="button" @click="printSerialList"
                        :disabled="!filters.doctor_id || !filters.appointment_date"
                        class="inline-flex items-center justify-center gap-2 px-3 py-2 text-sm font-semibold text-white bg-slate-700 rounded-md hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v7H6z" />
                        </svg>
                        Print Serial List
                    </button>
                    <button type="button" @click="printTokenList"
                        :disabled="!filters.doctor_id || !filters.appointment_date"
                        class="inline-flex items-center justify-center gap-2 px-3 py-2 text-sm font-semibold text-white bg-teal-700 rounded-md hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 7V3h10v4M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2M7 14h10v7H7z" />
                        </svg>
                        Print Token List
                    </button>
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
                <BaseTable @action="handleTableAction" />
            </div>
            <Pagination />
        </div>

        <div v-if="showDueModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4" @click.self="closeDueModal">
            <div class="w-full max-w-xl overflow-hidden rounded-lg bg-white shadow-xl dark:bg-slate-900" role="dialog" aria-modal="true" aria-labelledby="due-modal-title">
                <h2 id="due-modal-title" class="bg-blue-600 px-4 py-3 text-lg font-semibold text-white">OPD Due Collection</h2>
                <div class="p-4">
                    <table class="mb-4 w-full border-collapse text-sm text-gray-800 dark:text-slate-100">
                        <tbody>
                            <tr><th class="border border-slate-200 px-2 py-2 text-left">OPD Invoice</th><td class="border border-slate-200 px-2 py-2">{{ dueForm.invoice }}</td></tr>
                            <tr><th class="border border-slate-200 px-2 py-2 text-left">Patient</th><td class="border border-slate-200 px-2 py-2">{{ dueForm.patientName }}</td></tr>
                            <tr><th class="border border-slate-200 px-2 py-2 text-left">Total</th><td class="border border-slate-200 px-2 py-2">{{ formatMoney(dueForm.total) }}</td></tr>
                            <tr><th class="border border-slate-200 px-2 py-2 text-left">Paid</th><td class="border border-slate-200 px-2 py-2">{{ formatMoney(dueForm.paid) }}</td></tr>
                            <tr><th class="border border-slate-200 px-2 py-2 text-left">Due</th><td class="border border-slate-200 px-2 py-2 font-semibold text-red-600">{{ formatMoney(dueForm.due) }}</td></tr>
                        </tbody>
                    </table>
                    <label for="due-pay-amount" class="mb-1 block text-sm font-semibold text-gray-700 dark:text-slate-200">Pay Amount</label>
                    <input id="due-pay-amount" v-model="dueForm.amount" type="number" min="0.01" step="0.01" :max="dueForm.due" :disabled="isSubmittingDue" @keydown.enter.prevent="submitDueCollect" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800" />
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 px-4 py-3">
                    <button type="button" @click="closeDueModal" :disabled="isSubmittingDue" class="rounded-md bg-slate-500 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">Back</button>
                    <button type="button" @click="submitDueCollect" :disabled="isSubmittingDue" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">{{ isSubmittingDue ? 'Collecting...' : 'Collect Due' }}</button>
                </div>
            </div>
        </div>
    </BackendLayout>
</template>
