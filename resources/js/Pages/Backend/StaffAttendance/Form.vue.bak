<script setup>
import { ref, watch } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AlertMessage from '@/Components/AlertMessage.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';

const props = defineProps(["staffattendance", "id", "users"]);

const initialRecords = props.staffattendance
    ? [{
        staff_id: props.staffattendance.staff_id,
        name: props.staffattendance.name,
        attendance_status: props.staffattendance.attendance_status || '',
        in_time: props.staffattendance.in_time || '',
        out_time: props.staffattendance.out_time || '',
        note: props.staffattendance.note || '',
    }]
    : props.users.map(user => ({
        staff_id: user.id,
        name: `${user.first_name} ${user.last_name}`,
        attendance_status: '',
        in_time: '',
        out_time: '',
        note: '',
    }));


const form = useForm({
    attendance_date: props.staffattendance?.attendance_date ?? '',
    records: initialRecords,

    _method: props.staffattendance?.id ? "put" : "post",
});

watch(() => form.attendance_date, async (newDate) => {
    if (newDate) {
        await fetchAttendanceRecords(newDate);
    } else {
        // Reset records when the date is cleared
        form.records = initialRecords.map(user => ({
            staff_id: user.staff_id,
            name: user.name,
            attendance_status: '',
            in_time: '',
            out_time: '',
            note: '',
        }));
    }
});

const fetchAttendanceRecords = async (date) => {
    try {
        const response = await fetch(route('backend.staffattendance.fetch', { date }), {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        });
        const data = await response.json();
        if (response.ok) {
            form.records = data.records.length ? data.records : initialRecords;
        } else {
            displayWarning(data.message || "Failed to fetch records.");
        }
    } catch (error) {
        displayWarning("An error occurred while fetching records.");
    }
};

const isTimeFieldsActive = (status) => {
    return status === 'Present' || status === 'Late';
};

const setCurrentTime = (record, field) => {
    if (!record[field] && isTimeFieldsActive(record.attendance_status)) {
        const now = new Date();
        const hours = now.getHours().toString().padStart(2, '0');
        const minutes = now.getMinutes().toString().padStart(2, '0');
        record[field] = `${hours}:${minutes}`;
    }
};

const submit = () => {
    const routeName = props.id
        ? route("backend.staffattendance.update", props.id)
        : route("backend.staffattendance.store");
    form.post(routeName, {
        onSuccess: (response) => {
            if (!props.id) form.reset();
            displayResponse(response);
        },
        onError: (errorObject) => {
            displayWarning(errorObject);
        },
    });
};

const goToStaffAttendanceList = () => {
    router.visit(route('backend.staffattendance.index'));
};

</script>

<template>
    <BackendLayout>
        <div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md">

            <div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md">
                <div>
                    <h1 class="p-4 text-xl font-bold dark:text-white">{{ $page.props.pageTitle }}</h1>
                </div>

                <div class="p-2 py-2 flex items-center space-x-2">
                    <div class="flex items-center space-x-3">
                        <button @click="goToStaffAttendanceList"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z">
                                </path>
                            </svg>
                            Attendance List
                        </button>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="p-4">
                <InputLabel for="attendance_date" value="Attendance Date" />
                <input id="attendance_date"
                    class="block p-2 mb-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                    v-model="form.attendance_date" type="date" />
                <table
                    class="w-full grid-cols-1 gap-3 text-xs text-gray-700 border border-gray-300 rid sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="px-2 py-2 border border-gray-400">Sl/No</th>
                            <th class="px-2 py-2 border border-gray-400">Staff Id</th>
                            <th class="px-2 py-2 border border-gray-400">Name</th>
                            <th class="px-2 py-2 border border-gray-400">Attendance Status</th>
                            <th class="px-2 py-2 border border-gray-400">In Time</th>
                            <th class="px-2 py-2 border border-gray-400">Out Time</th>
                            <th class="px-2 py-2 border border-gray-400">Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(record, index) in form.records" :key="index">
                            <tr>
                                <td class="px-2 py-1 text-center border border-gray-300">{{ index + 1 }}</td>
                                <td class="px-2 py-1 text-center border border-gray-300">{{ record.staff_id }}</td>
                                <td class="px-2 py-1 text-center border border-gray-300">{{ record.name }}</td>
                                <td class="px-2 py-1 border border-gray-300">
                                    <select
                                        class="w-full block p-2 mb-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                        v-model="record.attendance_status">
                                        <option value="">Select Attendance</option>
                                        <option value="Present">Present</option>
                                        <option value="Late">Late</option>
                                        <option value="Absent">Absent</option>
                                        <option value="Holiday">Holiday</option>
                                    </select>
                                </td>
                                <td class="px-2 py-1 border border-gray-300">
                                    <input v-model="record.in_time" type="time"
                                        :disabled="!isTimeFieldsActive(record.attendance_status)"
                                        @click="setCurrentTime(record, 'in_time')"
                                        class="w-full block p-2 mb-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                                </td>
                                <td class="px-2 py-1 border border-gray-300">
                                    <input v-model="record.out_time" type="time"
                                        :disabled="!isTimeFieldsActive(record.attendance_status)"
                                        @click="setCurrentTime(record, 'out_time')"
                                        class="w-full block p-2 mb-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                                </td>
                                <td class="px-2 py-1 border border-gray-300">
                                    <textarea v-model="record.note" placeholder="Note"
                                        class="w-full block p-2 mb-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"></textarea>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <div class="flex items-center justify-end mt-4">
                    <PrimaryButton type="submit" :disabled="form.processing">
                        Submit
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </BackendLayout>
</template>
