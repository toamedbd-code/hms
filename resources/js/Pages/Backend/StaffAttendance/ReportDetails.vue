<script setup>
import { ref, computed, watch } from "vue";
import BackendLayout from "@/Layouts/BackendLayout.vue";

const props = defineProps({
  dateDatas: { type: Object, default: () => ({ attendanceData: [] }) },
  filters: { type: Object, default: () => ({}) },
  staffName: { type: String, default: "" },
  staffId: { type: String, default: "" },
  datas: { type: Array, default: () => [] },
  leaves: { type: Object, default: () => ({ leaves: [] }) },
});

const filters = ref({
  staff_id: props.filters.staff_id || "",
  name: props.filters.name || "",
  numOfData: props.filters.numOfData || 10,
  month: new Date().toISOString().split("T")[0].slice(0, 7),
});

const selectedYear = computed(() => filters.value.month.split("-")[0]);
const currentMonth = computed(() => parseInt(filters.value.month.split("-")[1]));

const daysInMonth = (month, year) => new Date(year, month, 0).getDate();
const getFirstDayOfMonth = (month, year) => new Date(year, month - 1, 1).getDay();

const generateMonthGrid = (year, month) => {
  const totalDays = daysInMonth(month, year);
  const firstDay = getFirstDayOfMonth(month, year);
  const weeks = [];
  let currentWeek = Array(7).fill(null);

  for (let day = 1; day <= totalDays; day++) {
    const dayOfWeek = (firstDay + day - 1) % 7;
    currentWeek[dayOfWeek] = day;
    if (dayOfWeek === 6 || day === totalDays) {
      weeks.push(currentWeek);
      currentWeek = Array(7).fill(null);
    }
  }
  return weeks;
};

const monthGrid = computed(() =>
  generateMonthGrid(selectedYear.value, currentMonth.value)
);
const attendanceData = computed(() => props.dateDatas.attendanceData || []);

const getAttendanceStatus = (day) => {
  const date = `${filters.value.month}-${day.toString().padStart(2, "0")}`;
  const record = attendanceData.value.find((record) => record.attendance_date === date);
  console.log(props.leaves);
  const leaveRecord = props.leaves?.leaves?.find((leave) => {
    const leaveStart = new Date(leave.from);
    const leaveEnd = new Date(leave.to);
    const currentDate = new Date(date);
    return currentDate >= leaveStart && currentDate <= leaveEnd;
  });

  if (record) {
    if (record.attendance_status === "Absent" && leaveRecord) {
      return `Absent (${leaveRecord?.leave_type?.type_name})`;
    }
    return record.attendance_status;
  } else if (leaveRecord) {
    return `Leave (${leaveRecord?.leave_type?.type_name})`;
  }
  return "--";
};

const formattedMonthYear = computed(() => {
  const [year, month] = filters.value.month.split("-");
  const normalizedMonth = month.replace(/^0+/, "");
  const date = new Date(year, normalizedMonth - 1);
  return date.toLocaleString("default", { month: "long", year: "numeric" });
});

const totalAttendance = computed(() => {
  let total = {
    present: 0,
    absent: 0,
    late: 0,
    holiday: 0,
    paidDays: 0,
    unpaidDays: 0,
    approvedLeaves: 0,
    rejectedLeaves: 0,
  };

  const daysInCurrentMonth = daysInMonth(currentMonth.value, selectedYear.value);

  for (let day = 1; day <= daysInCurrentMonth; day++) {
    const date = `${selectedYear.value}-${currentMonth.value
      .toString()
      .padStart(2, "0")}-${day.toString().padStart(2, "0")}`;
    const record = attendanceData.value.find((r) => r.attendance_date === date);
    const leaveRecord = props.leaves?.leaves?.find((leave) => {
      const leaveStart = new Date(leave.from);
      const leaveEnd = new Date(leave.to);
      const currentDate = new Date(date);
      return currentDate >= leaveStart && currentDate <= leaveEnd;
    });

    if (record) {
      if (record.attendance_status === "Present") {
        total.present++;
        total.paidDays++;
      } else if (record.attendance_status === "Late") {
        total.late++;
        total.paidDays++;
      } else if (record.attendance_status === "Holiday") {
        total.holiday++;
        total.paidDays++;
      } else if (record.attendance_status === "Absent") {
        if (leaveRecord) {
          if (leaveRecord.status === "Approved") {
            total.approvedLeaves++;
            total.paidDays++;
          //  total.present++;
          } else {
            total.rejectedLeaves++;
            total.unpaidDays++;
            total.absent++;
          }
        } else {
          total.absent++;
          total.unpaidDays++;
        }
      }
    } else if (leaveRecord) {
      if (leaveRecord.status === "Approved") {
        total.approvedLeaves++;
        total.paidDays++;
       // total.present++;
      } else {
        total.rejectedLeaves++;
        total.unpaidDays++;
        total.absent++;
      }
    } else {
      total.absent++;
      total.unpaidDays++;
    }
  }

  return total;
});

const attendancePercentage = computed(() => {
  const totalDays = daysInMonth(currentMonth.value, selectedYear.value);
  return {
    present: ((totalAttendance.value.present / totalDays) * 100).toFixed(2),
    absent: ((totalAttendance.value.absent / totalDays) * 100).toFixed(2),
    late: ((totalAttendance.value.late / totalDays) * 100).toFixed(2),
    holiday: ((totalAttendance.value.holiday / totalDays) * 100).toFixed(2),
    paidDays: ((totalAttendance.value.paidDays / totalDays) * 100).toFixed(2),
    unpaidDays: ((totalAttendance.value.unpaidDays / totalDays) * 100).toFixed(2),
  };
});

const applyFilter = () => {
  const month = filters.value.month.split("-")[1];
  const year = selectedYear.value;
  const testUrl = `/backend/staffattendance/report/${props.staffId}?numOfData=${filters.value.numOfData}&month=${month}&year=${year}`;
};

watch(filters, applyFilter, { deep: true });
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded-md shadow-md dark:bg-slate-900">
      <div class="flex items-center justify-between">
        <h1 class="py-2 text-xl font-bold dark:text-white">
          {{ $page.props.pageTitle }}
        </h1>
        <a
          :href="`/backend/staff/payslip/${staffId}?month=${filters.month}&unpaidDays=${totalAttendance.unpaidDays}`"
          class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700"
        >
          Payslip
        </a>
      </div>

      <div class="grid w-full grid-cols-1 gap-2 md:grid-cols-5 my-4">
        <input
          id="month"
          v-model="filters.month"
          class="block w-full p-2 text-sm rounded-md"
          type="month"
          @change="applyFilter"
        />
      </div>
      <!--<pre>{{ leaves }}</pre>-->
      <div class="w-full mt-4">
        <table class="w-full text-xs text-gray-700 dark:text-gray-200">
          <caption class="font-medium font-bold text-left text-lg">
            Attendance Details of
            {{
              formattedMonthYear
            }}
          </caption>
          <thead class="bg-gray-200 dark:bg-gray-700">
            <tr>
              <th
                v-for="day in [
                  'Sunday',
                  'Monday',
                  'Tuesday',
                  'Wednesday',
                  'Thursday',
                  'Friday',
                  'Saturday',
                ]"
                :key="day"
                class="text-sm text-center border border-slate-700"
              >
                {{ day }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(week, index) in monthGrid" :key="index">
              <td
                v-for="(day, dayIndex) in week"
                :key="dayIndex"
                class="text-center border border-slate-700"
              >
                <div v-if="day">
                  {{ day }}
                  <div class="text-xs">
                    {{ getAttendanceStatus(day) }}
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="w-full mt-6">
        <table class="w-full text-xs text-gray-700 dark:text-gray-200">
          <caption class="font-medium font-bold text-left text-lg">
            Salary Calculation for
            {{
              formattedMonthYear
            }}
          </caption>
          <thead class="bg-gray-200 dark:bg-gray-700">
            <tr>
              <th class="text-sm text-center border border-slate-700">Staff Id</th>
              <th class="text-sm text-center border border-slate-700">Staff Name</th>
              <th class="text-sm text-center border border-slate-700">Total Days</th>
              <th class="text-sm text-center border border-slate-700">Present</th>
              <th class="text-sm text-center border border-slate-700">Absent</th>
              <th class="text-sm text-center border border-slate-700">Lates</th>
              <th class="text-sm text-center border border-slate-700">Holidays</th>
              <th class="text-sm text-center border border-slate-700">Paid Days</th>
              <th class="text-sm text-center border border-slate-700">Unpaid Days</th>
              <th class="text-sm text-center border border-slate-700">Approved Leaves</th>
              <th class="text-sm text-center border border-slate-700">Rejected Leaves</th>
              <th class="text-sm text-center border border-slate-700">Paid %</th>
              <th class="text-sm text-center border border-slate-700">Unpaid %</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="text-center border border-slate-700">{{ staffId }}</td>
              <td class="text-center border border-slate-700">{{ staffName }}</td>
              <td class="text-center border border-slate-700">
                {{ daysInMonth(currentMonth, selectedYear) }}
              </td>
              <td class="text-center border border-slate-700">
                {{ totalAttendance.present }}
              </td>
              <td class="text-center border border-slate-700">
                {{ totalAttendance.absent }}
              </td>
              <td class="text-center border border-slate-700">
                {{ totalAttendance.late }}
              </td>
              <td class="text-center border border-slate-700">
                {{ totalAttendance.holiday }}
              </td>
              <td class="text-center border border-slate-700">
                {{ totalAttendance.paidDays }}
              </td>
              <td class="text-center border border-slate-700">
                {{ totalAttendance.unpaidDays }}
              </td>
              <td class="text-center border border-slate-700">
                {{ totalAttendance.approvedLeaves }}
              </td>
              <td class="text-center border border-slate-700">
                {{ totalAttendance.rejectedLeaves }}
              </td>
              <td class="text-center border border-slate-700">
                {{ attendancePercentage.paidDays }}%
              </td>
              <td class="text-center border border-slate-700">
                {{ attendancePercentage.unpaidDays }}%
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </BackendLayout>
</template>
