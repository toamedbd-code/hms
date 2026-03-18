<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const props = defineProps({
  pageTitle: {
    type: String,
    default: 'Salary Sheet',
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
  websetting: {
    type: Object,
    default: () => ({}),
  },
  rows: {
    type: Array,
    default: () => [],
  },
  totals: {
    type: Object,
    default: () => ({}),
  },
});

const selectedMonth = ref(props.filters?.month ?? new Date().toISOString().slice(0, 7));

// Safely read CSRF token only in browser
const csrfToken = (typeof document !== 'undefined') ? (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '') : '';

const applyFilter = () => {
  router.get(
    route('backend.staffattendance.salary-sheet'),
    { month: selectedMonth.value },
    { preserveState: true, preserveScroll: true, replace: true }
  );
};

const formattedMonth = computed(() => {
  const [year, month] = String(selectedMonth.value || '').split('-');
  if (!year || !month) return '';
  const date = new Date(Number(year), Number(month) - 1, 1);
  return date.toLocaleString('en-US', { month: 'long', year: 'numeric' });
});

const integrationOptions = computed(() => {
  const defaults = {
    modules: {
      fingerprint: true,
      face_attendance: true,
      leave: true,
      duty_roster: true,
      salary_sheet: true,
    },
  };

  const raw = props.websetting?.attendance_device_options;
  if (!raw) return defaults;

  try {
    const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
    return {
      ...defaults,
      ...parsed,
      modules: {
        ...defaults.modules,
        ...(parsed?.modules ?? {}),
      },
    };
  } catch (error) {
    return defaults;
  }
});

const money = (value) => Number(value ?? 0).toFixed(2);

const printSheet = () => {
  const targetMonth = selectedMonth.value || props.filters?.month || new Date().toISOString().slice(0, 7);
  const printUrl = route('backend.staffattendance.salary-sheet.print', { month: targetMonth });
  window.open(printUrl, '_blank');
};

const exportCsv = () => {
  const headers = [
    'SL',
    'Staff ID',
    'Name',
    'Department',
    'Designation',
    'Basic Salary',
    'Workable Days',
    'Paid Days',
    'Unpaid Days',
    'Biometric Deduction',
    'Overtime Bonus',
    'Advance Paid',
    'Deduction',
    'Payable Salary',
  ];

  const lines = [headers.join(',')];

  props.rows.forEach((row) => {
    const values = [
      row.sl,
      row.staff_id,
      row.name,
      row.department,
      row.designation,
      money(row.basic_salary),
      row.workable_days,
      row.paid_days,
      row.unpaid_days,
      money(row.biometric_deduction),
      money(row.overtime_bonus),
      money(row.advance_paid),
      money(row.deduction),
      money(row.payable_salary),
    ].map((value) => `"${String(value ?? '').replaceAll('"', '""')}"`);

    lines.push(values.join(','));
  });

  lines.push(
    [
      'TOTAL',
      '',
      '',
      '',
      `${props.totals?.staff_count ?? 0} staff`,
      money(props.totals?.basic_salary),
      '',
      '',
      '',
      '',
      '',
      money(props.totals?.deduction),
      money(props.totals?.payable_salary),
    ]
      .map((value) => `"${String(value ?? '').replaceAll('"', '""')}"`)
      .join(',')
  );

  const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `salary-sheet-${selectedMonth.value || 'month'}.csv`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
};

const paySalary = async (staffId, defaultAmount) => {
  const amount = window.prompt('Enter amount to pay (TK)', String(defaultAmount ?? ''));
  if (!amount) return;
  const numeric = parseFloat(amount);
  if (Number.isNaN(numeric) || numeric < 0) {
    alert('Invalid amount');
    return;
  }

  const isAdvance = window.confirm('Mark this payment as Advance Salary?');

  const payload = new FormData();
  payload.append('staff_id', staffId);
  payload.append('month', selectedMonth.value || '');
  payload.append('amount', numeric);
  payload.append('is_advance', isAdvance ? '1' : '0');

  try {
    await fetch(route('backend.staffattendance.salary-sheet.pay'), {
      method: 'POST',
      body: payload,
      headers: { 'X-CSRF-TOKEN': csrfToken },
    });
    location.reload();
  } catch (err) {
    console.error(err);
    alert('Failed to record payment');
  }
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded-md shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-3 p-4 bg-gray-100 rounded">
        <div>
          <h1 class="text-xl font-bold text-gray-800">{{ pageTitle }}</h1>
          <p class="text-sm text-gray-600">Human Resource + Payroll ভিত্তিক মাসিক সেলারি শীট</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 print:hidden">
          <input
            v-model="selectedMonth"
            type="month"
            class="p-2 text-sm border border-gray-300 rounded"
            @change="applyFilter"
          />
          <button
            type="button"
            class="px-3 py-2 text-sm text-white bg-indigo-600 rounded hover:bg-indigo-700"
            @click="applyFilter"
          >
            Search
          </button>
          <button
            type="button"
            class="px-3 py-2 text-sm text-white bg-emerald-600 rounded hover:bg-emerald-700"
            @click="exportCsv"
          >
            Export CSV
          </button>
          <button
            type="button"
            class="px-3 py-2 text-sm text-white bg-sky-600 rounded hover:bg-sky-700"
            @click="printSheet"
          >
            Print
          </button>
          <Link
            :href="route('backend.staffattendance.report')"
            class="px-3 py-2 text-sm text-white bg-gray-600 rounded hover:bg-gray-700"
          >
            Back
          </Link>
        </div>
      </div>

      <div class="mt-4 text-sm text-gray-700">
        <div class="font-semibold text-base text-gray-800">{{ props.websetting?.company_name || 'Hospital' }}</div>
        <div class="text-gray-600">{{ props.websetting?.address || 'N/A' }}</div>
        <span class="font-semibold">Month:</span> {{ formattedMonth || 'N/A' }}
      </div>

      <div class="mt-3 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900 print:hidden">
        <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold bg-emerald-600 text-white mr-2">Auto Synced</span>
        Salary computation is fed by auto-updated attendance IN/OUT with roster and leave integration.
      </div>

      <div class="grid grid-cols-1 gap-3 mt-4 sm:grid-cols-2 lg:grid-cols-5 print:hidden">
        <Link v-if="integrationOptions.modules.face_attendance" :href="route('backend.attendance.face')" class="p-3 rounded-md border border-violet-200 bg-violet-50 hover:bg-violet-100 transition">
          <div class="text-xs font-semibold text-violet-700">Attendance</div>
          <div class="text-sm font-bold text-violet-900 mt-1">Face Attendance</div>
        </Link>

        <a v-if="integrationOptions.modules.fingerprint || integrationOptions.modules.face_attendance" href="/admin/attendance/devices" class="p-3 rounded-md border border-cyan-200 bg-cyan-50 hover:bg-cyan-100 transition">
          <div class="text-xs font-semibold text-cyan-700">Device</div>
          <div class="text-sm font-bold text-cyan-900 mt-1">Fingerprint/Face Devices</div>
        </a>

        <Link v-if="integrationOptions.modules.leave" :href="route('backend.pending.request')" class="p-3 rounded-md border border-amber-200 bg-amber-50 hover:bg-amber-100 transition">
          <div class="text-xs font-semibold text-amber-700">Leave</div>
          <div class="text-sm font-bold text-amber-900 mt-1">Leave Requests</div>
        </Link>

        <Link v-if="integrationOptions.modules.duty_roster" :href="route('backend.staffattendance.duty-roster')" class="p-3 rounded-md border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 transition">
          <div class="text-xs font-semibold text-emerald-700">Roster</div>
          <div class="text-sm font-bold text-emerald-900 mt-1">Duty Roster</div>
        </Link>

        <Link v-if="integrationOptions.modules.salary_sheet" :href="route('backend.staffattendance.report')" class="p-3 rounded-md border border-slate-200 bg-slate-50 hover:bg-slate-100 transition">
          <div class="text-xs font-semibold text-slate-700">Summary</div>
          <div class="text-sm font-bold text-slate-900 mt-1">Attendance Report</div>
        </Link>
      </div>

      <div class="w-full my-4 overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700 border border-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 border">SL</th>
              <th class="px-3 py-2 border">Staff ID</th>
              <th class="px-3 py-2 border">Name</th>
              <th class="px-3 py-2 border">Department</th>
              <th class="px-3 py-2 border">Designation</th>
              <th class="px-3 py-2 border">Basic Salary</th>
              <th class="px-3 py-2 border">Workable Days</th>
              <th class="px-3 py-2 border">Paid Days</th>
              <th class="px-3 py-2 border">Unpaid Days</th>
              <th class="px-3 py-2 border">Biometric Deduction</th>
              <th class="px-3 py-2 border">Overtime Bonus</th>
              <th class="px-3 py-2 border">Advance Paid</th>
              <th class="px-3 py-2 border">Deduction</th>
              <th class="px-3 py-2 border">Payable Salary</th>
              <th class="px-3 py-2 border">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="`${row.staff_id}-${row.sl}`" class="hover:bg-gray-50">
              <td class="px-3 py-2 border">{{ row.sl }}</td>
              <td class="px-3 py-2 border">{{ row.staff_id }}</td>
              <td class="px-3 py-2 border">{{ row.name }}</td>
              <td class="px-3 py-2 border">{{ row.workable_days }}</td>
              <td class="px-3 py-2 border">{{ row.department }}</td>
              <td class="px-3 py-2 border">{{ row.designation }}</td>
              <td class="px-3 py-2 border">{{ money(row.biometric_deduction) }}</td>
              <td class="px-3 py-2 border">{{ money(row.overtime_bonus) }}</td>
              <td class="px-3 py-2 border">{{ money(row.advance_paid) }}</td>
              <td class="px-3 py-2 border">{{ money(row.basic_salary) }}</td>
              <td class="px-3 py-2 border">{{ row.paid_days }}</td>
              <td class="px-3 py-2 border">{{ row.unpaid_days }}</td>
              <td class="px-3 py-2 border">{{ money(row.deduction) }}</td>
              <td class="px-3 py-2 font-semibold text-emerald-700 border">{{ money(row.payable_salary) }}</td>
              <td class="px-3 py-2 border">
                  @click.prevent="paySalary(row.staff_admin_id, row.payable_salary)"
                  type="button"
                  class="px-3 py-1 text-sm text-white bg-emerald-600 rounded hover:bg-emerald-700"
                  @click.prevent="paySalary(row.staff_id, row.payable_salary)"
                >
                  Pay
                </button>
              <td colspan="15" class="px-3 py-6 text-center text-gray-500 border">No staff data found for this month.</td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="11" class="px-3 py-6 text-center text-gray-500 border">No staff data found for this month.</td>
            </tr>
              <td colspan="5" class="px-3 py-2 font-semibold border">Total ({{ totals.staff_count ?? 0 }} staff)</td>
          <tfoot v-if="rows.length > 0" class="bg-gray-100">
            <tr>
              <td colspan="5" class="px-3 py-2 font-semibold border">Total ({{ totals.staff_count ?? 0 }} staff)</td>
              <td class="px-3 py-2 font-semibold border">{{ money(totals.basic_salary) }}</td>
              <td class="px-3 py-2 border"></td>
              <td class="px-3 py-2 border"></td>
              <td class="px-3 py-2 border"></td>
              <td class="px-3 py-2 border"></td>
              <td class="px-3 py-2 border"></td>
              <td class="px-3 py-2 font-semibold border">{{ money(totals.deduction) }}</td>
              <td class="px-3 py-2 font-semibold text-emerald-700 border">{{ money(totals.payable_salary) }}</td>
              <td class="px-3 py-2 border"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </BackendLayout>
</template>
