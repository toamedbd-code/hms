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
  printUrl: {
    type: String,
    default: '',
  },
  holidayAuditUrl: {
    type: String,
    default: '',
  },
  breakdownPrintUrl: {
    type: String,
    default: '',
  },
  breakdownPdfUrl: {
    type: String,
    default: '',
  },
  salaryPdfUrl: {
    type: String,
    default: '',
  },
  lockUrl: {
    type: String,
    default: '',
  },
  saveSettingsUrl: {
    type: String,
    default: '',
  },
  lockState: {
    type: Object,
    default: () => ({}),
  },
});

const selectedMonth = ref(props.filters?.month ?? new Date().toISOString().slice(0, 7));
const lateFeePerLate = ref(Number(props.filters?.late_fee_per_late ?? 0));
const overtimeMultiplier = ref(Number(props.filters?.overtime_multiplier ?? 1));
const lateGraceDays = ref(Number(props.filters?.late_grace_days ?? 3));
const lateDeductionRate = ref(Number(props.filters?.late_deduction_rate ?? 0.25));
const lateHighlightLimit = ref(Number(props.filters?.late_highlight_limit ?? 3));
const unpaidHighlightLimit = ref(Number(props.filters?.unpaid_highlight_limit ?? 2));
const waiveShortLate = ref(Boolean(props.filters?.waive_short_late ?? false));
const shortLateLimitMinutes = ref(Number(props.filters?.short_late_limit_minutes ?? 15));
const selectedBreakdownRow = ref(null);
const isLocked = ref(Boolean(props.lockState?.is_locked));
const lockMeta = ref(props.lockState || {});
const csrfToken = (typeof document !== 'undefined')
  ? (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '')
  : '';

const applyFilter = () => {
  const normalizedLateFee = Number.isFinite(Number(lateFeePerLate.value)) ? Math.max(Number(lateFeePerLate.value), 0) : 0;
  const normalizedOvertimeMultiplier = Number.isFinite(Number(overtimeMultiplier.value)) ? Math.max(Number(overtimeMultiplier.value), 0) : 0;
  const normalizedLateGraceDays = Number.isFinite(Number(lateGraceDays.value)) ? Math.max(parseInt(lateGraceDays.value, 10), 0) : 3;
  const normalizedLateDeductionRate = Number.isFinite(Number(lateDeductionRate.value)) ? Math.max(Number(lateDeductionRate.value), 0) : 0.25;
  const normalizedLateHighlightLimit = Number.isFinite(Number(lateHighlightLimit.value)) ? Math.max(parseInt(lateHighlightLimit.value, 10), 0) : 3;
  const normalizedUnpaidHighlightLimit = Number.isFinite(Number(unpaidHighlightLimit.value)) ? Math.max(parseInt(unpaidHighlightLimit.value, 10), 0) : 2;
  const normalizedShortLateLimitMinutes = Number.isFinite(Number(shortLateLimitMinutes.value)) ? Math.max(parseInt(shortLateLimitMinutes.value, 10), 0) : 15;

  lateFeePerLate.value = normalizedLateFee;
  overtimeMultiplier.value = normalizedOvertimeMultiplier;
  lateGraceDays.value = normalizedLateGraceDays;
  lateDeductionRate.value = normalizedLateDeductionRate;
  lateHighlightLimit.value = normalizedLateHighlightLimit;
  unpaidHighlightLimit.value = normalizedUnpaidHighlightLimit;
  shortLateLimitMinutes.value = normalizedShortLateLimitMinutes;

  router.get(
    route('backend.staffattendance.salary-sheet'),
    {
      month: selectedMonth.value,
      late_fee_per_late: normalizedLateFee,
      overtime_multiplier: normalizedOvertimeMultiplier,
      late_grace_days: normalizedLateGraceDays,
      late_deduction_rate: normalizedLateDeductionRate,
      late_highlight_limit: normalizedLateHighlightLimit,
      unpaid_highlight_limit: normalizedUnpaidHighlightLimit,
      waive_short_late: waiveShortLate.value ? 1 : 0,
      short_late_limit_minutes: normalizedShortLateLimitMinutes,
    },
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
  } catch {
    return defaults;
  }
});

const money = (value) => Number(value ?? 0).toFixed(2);

const minutesToHms = (value) => {
  const numeric = Number(value ?? 0);
  if (!Number.isFinite(numeric)) return '00:00:00';

  const totalSeconds = Math.max(Math.round(numeric * 60), 0);
  const hours = Math.floor(totalSeconds / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const seconds = totalSeconds % 60;

  return [hours, minutes, seconds].map((part) => String(part).padStart(2, '0')).join(':');
};

const openBreakdown = (row) => {
  selectedBreakdownRow.value = row;
};

const closeBreakdown = () => {
  selectedBreakdownRow.value = null;
};

const selectedBreakdownTotals = computed(() => {
  const items = selectedBreakdownRow.value?.attendance_breakdown || [];

  return items.reduce(
    (acc, item) => {
      acc.duration_minutes += Number(item?.duration_minutes ?? 0);
      acc.late_minutes += Number(item?.late_minutes ?? 0);
      acc.overtime_minutes += Number(item?.overtime_minutes ?? 0);
      acc.deduction_amount += Number(item?.deduction_amount ?? 0);
      acc.overtime_amount += Number(item?.overtime_amount ?? 0);
      return acc;
    },
    {
      duration_minutes: 0,
      late_minutes: 0,
      overtime_minutes: 0,
      deduction_amount: 0,
      overtime_amount: 0,
    }
  );
});

const printTargetUrl = computed(() => {
  const targetMonth = selectedMonth.value || props.filters?.month || new Date().toISOString().slice(0, 7);
  const base = props.printUrl || '/admin/backend/staffattendance/salary-sheet/print';
  const params = new URLSearchParams({
    month: targetMonth,
    late_fee_per_late: String(Math.max(Number(lateFeePerLate.value) || 0, 0)),
    overtime_multiplier: String(Math.max(Number(overtimeMultiplier.value) || 0, 0)),
    late_grace_days: String(Math.max(Number(lateGraceDays.value) || 0, 0)),
    late_deduction_rate: String(Math.max(Number(lateDeductionRate.value) || 0, 0)),
    late_highlight_limit: String(Math.max(Number(lateHighlightLimit.value) || 0, 0)),
    unpaid_highlight_limit: String(Math.max(Number(unpaidHighlightLimit.value) || 0, 0)),
    waive_short_late: waiveShortLate.value ? '1' : '0',
    short_late_limit_minutes: String(Math.max(parseInt(shortLateLimitMinutes.value, 10) || 0, 0)),
  });
  const separator = base.includes('?') ? '&' : '?';
  return `${base}${separator}${params.toString()}`;
});

const salaryPdfTargetUrl = computed(() => {
  const targetMonth = selectedMonth.value || props.filters?.month || new Date().toISOString().slice(0, 7);
  const base = props.salaryPdfUrl || '/admin/backend/staffattendance/salary-sheet/pdf';
  const params = new URLSearchParams({
    month: targetMonth,
    late_fee_per_late: String(Math.max(Number(lateFeePerLate.value) || 0, 0)),
    overtime_multiplier: String(Math.max(Number(overtimeMultiplier.value) || 0, 0)),
    late_grace_days: String(Math.max(Number(lateGraceDays.value) || 0, 0)),
    late_deduction_rate: String(Math.max(Number(lateDeductionRate.value) || 0, 0)),
    late_highlight_limit: String(Math.max(Number(lateHighlightLimit.value) || 0, 0)),
    unpaid_highlight_limit: String(Math.max(Number(unpaidHighlightLimit.value) || 0, 0)),
    waive_short_late: waiveShortLate.value ? '1' : '0',
    short_late_limit_minutes: String(Math.max(parseInt(shortLateLimitMinutes.value, 10) || 0, 0)),
  });
  const separator = base.includes('?') ? '&' : '?';
  return `${base}${separator}${params.toString()}`;
});

const holidayAuditTargetUrl = computed(() => {
  const targetMonth = selectedMonth.value || props.filters?.month || new Date().toISOString().slice(0, 7);
  const base = props.holidayAuditUrl || '/admin/backend/staffattendance/salary-sheet/holiday-audit';
  const params = new URLSearchParams({
    month: targetMonth,
    with_weekly: '1',
  });
  const separator = base.includes('?') ? '&' : '?';
  return `${base}${separator}${params.toString()}`;
});

const selectedBreakdownPrintUrl = computed(() => {
  const row = selectedBreakdownRow.value;
  if (!row) return '';

  const targetMonth = selectedMonth.value || props.filters?.month || new Date().toISOString().slice(0, 7);
  const base = props.breakdownPrintUrl || '/admin/backend/staffattendance/salary-sheet/breakdown-print';
  const params = new URLSearchParams({
    month: targetMonth,
    staff_id: String(row.staff_admin_id ?? ''),
    late_fee_per_late: String(Math.max(Number(lateFeePerLate.value) || 0, 0)),
    overtime_multiplier: String(Math.max(Number(overtimeMultiplier.value) || 0, 0)),
    late_grace_days: String(Math.max(Number(lateGraceDays.value) || 0, 0)),
    late_deduction_rate: String(Math.max(Number(lateDeductionRate.value) || 0, 0)),
    waive_short_late: waiveShortLate.value ? '1' : '0',
    short_late_limit_minutes: String(Math.max(parseInt(shortLateLimitMinutes.value, 10) || 0, 0)),
  });
  const separator = base.includes('?') ? '&' : '?';
  return `${base}${separator}${params.toString()}`;
});

const selectedBreakdownPdfUrl = computed(() => {
  const row = selectedBreakdownRow.value;
  if (!row) return '';

  const targetMonth = selectedMonth.value || props.filters?.month || new Date().toISOString().slice(0, 7);
  const base = props.breakdownPdfUrl || '/admin/backend/staffattendance/salary-sheet/breakdown-pdf';
  const params = new URLSearchParams({
    month: targetMonth,
    staff_id: String(row.staff_admin_id ?? ''),
    late_fee_per_late: String(Math.max(Number(lateFeePerLate.value) || 0, 0)),
    overtime_multiplier: String(Math.max(Number(overtimeMultiplier.value) || 0, 0)),
    late_grace_days: String(Math.max(Number(lateGraceDays.value) || 0, 0)),
    late_deduction_rate: String(Math.max(Number(lateDeductionRate.value) || 0, 0)),
    waive_short_late: waiveShortLate.value ? '1' : '0',
    short_late_limit_minutes: String(Math.max(parseInt(shortLateLimitMinutes.value, 10) || 0, 0)),
  });
  const separator = base.includes('?') ? '&' : '?';
  return `${base}${separator}${params.toString()}`;
});

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
    'Late Days',
    'Late (Deduction Days)',
    'Late (HH:MM:SS)',
    'Waived Short Late (HH:MM:SS)',
    'Overtime (HH:MM:SS)',
    'Unpaid Days',
    'Hourly Rate',
    'Late Deduction (Hourly)',
    'Overtime Bonus (Hourly)',
    'Biometric Deduction',
    'Late Fee',
    'Late Policy Deduction',
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
      row.late,
      row.late_for_deduction,
      minutesToHms(row.late_minutes),
      minutesToHms(row.waived_short_late_minutes),
      minutesToHms(row.overtime_minutes),
      row.unpaid_days,
      money(row.hourly_rate),
      money(row.late_deduction_hourly),
      money(row.overtime_bonus_hourly),
      money(row.biometric_deduction),
      money(row.late_fee),
      money(row.late_policy_deduction),
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
      minutesToHms(props.totals?.late_minutes ?? 0),
      '',
      minutesToHms(props.totals?.waived_short_late_minutes ?? 0),
      minutesToHms(props.totals?.overtime_minutes ?? 0),
      '',
      '',
      money(props.totals?.late_deduction_hourly),
      money(props.totals?.overtime_bonus_hourly),
      money(props.totals?.biometric_deduction),
      money(props.totals?.late_fee),
      money(props.totals?.late_policy_deduction),
      money(props.totals?.overtime_bonus),
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
  if (isLocked.value) {
    alert('Salary sheet is locked for this month. Payment is disabled.');
    return;
  }

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

const lockSalarySheet = async () => {
  if (isLocked.value) {
    alert('This month is already locked.');
    return;
  }

  const note = window.prompt('Optional lock note', '') || '';
  const payload = new FormData();
  payload.append('month', selectedMonth.value || '');
  payload.append('note', note);

  try {
    const response = await fetch(props.lockUrl || route('backend.staffattendance.salary-sheet.lock'), {
      method: 'POST',
      body: payload,
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        Accept: 'application/json',
      },
    });

    if (!response.ok) {
      const data = await response.json().catch(() => ({}));
      alert(data?.message || 'Failed to lock salary sheet.');
      return;
    }

    const data = await response.json();
    isLocked.value = true;
    lockMeta.value = data?.lock_state || {};
    alert(data?.message || 'Salary sheet locked successfully.');
  } catch (err) {
    console.error(err);
    alert('Failed to lock salary sheet.');
  }
};

const saveDefaultSettings = async () => {
  const payload = new FormData();
  payload.append('late_fee_per_late', String(Math.max(Number(lateFeePerLate.value) || 0, 0)));
  payload.append('overtime_multiplier', String(Math.max(Number(overtimeMultiplier.value) || 0, 0)));
  payload.append('late_grace_days', String(Math.max(parseInt(lateGraceDays.value, 10) || 0, 0)));
  payload.append('late_deduction_rate', String(Math.max(Number(lateDeductionRate.value) || 0, 0)));
  payload.append('late_highlight_limit', String(Math.max(parseInt(lateHighlightLimit.value, 10) || 0, 0)));
  payload.append('unpaid_highlight_limit', String(Math.max(parseInt(unpaidHighlightLimit.value, 10) || 0, 0)));
  payload.append('waive_short_late', waiveShortLate.value ? '1' : '0');
  payload.append('short_late_limit_minutes', String(Math.max(parseInt(shortLateLimitMinutes.value, 10) || 0, 0)));

  try {
    const response = await fetch(props.saveSettingsUrl || route('backend.staffattendance.salary-sheet.settings.save'), {
      method: 'POST',
      body: payload,
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        Accept: 'application/json',
      },
    });

    if (!response.ok) {
      const data = await response.json().catch(() => ({}));
      alert(data?.message || 'Failed to save settings.');
      return;
    }

    const data = await response.json().catch(() => ({}));
    alert(data?.message || 'Settings saved.');
  } catch (err) {
    console.error(err);
    alert('Failed to save settings.');
  }
};

const lateCellClass = (row) =>
  Number(row?.late ?? 0) > Number(lateHighlightLimit.value ?? 3)
    ? 'px-3 py-2 border bg-red-100 text-red-700 font-semibold'
    : 'px-3 py-2 border';

const unpaidCellClass = (row) =>
  Number(row?.unpaid_days ?? 0) > Number(unpaidHighlightLimit.value ?? 2)
    ? 'px-3 py-2 border bg-red-100 text-red-700 font-semibold'
    : 'px-3 py-2 border';
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded-md shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-3 p-4 bg-gray-100 rounded">
        <div>
          <h1 class="text-xl font-bold text-gray-800">{{ pageTitle }}</h1>
          <p class="text-sm text-gray-600">Human Resource + Payroll based monthly salary sheet</p>
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
            class="px-3 py-2 text-sm text-white bg-teal-600 rounded hover:bg-teal-700"
            @click="saveDefaultSettings"
          >
            Save Default Settings
          </button>
          <button
            type="button"
            class="px-3 py-2 text-sm text-white bg-emerald-600 rounded hover:bg-emerald-700"
            @click="exportCsv"
          >
            Export CSV
          </button>
          <a
            :href="printTargetUrl"
            target="_blank"
            rel="noopener"
            class="px-3 py-2 text-sm text-white bg-sky-600 rounded hover:bg-sky-700"
          >
            Print
          </a>
          <a
            :href="salaryPdfTargetUrl"
            class="px-3 py-2 text-sm text-white bg-rose-600 rounded hover:bg-rose-700"
          >
            Export PDF
          </a>
          <button
            type="button"
            class="px-3 py-2 text-sm text-white rounded"
            :class="isLocked ? 'bg-gray-400 cursor-not-allowed' : 'bg-red-600 hover:bg-red-700'"
            :disabled="isLocked"
            @click="lockSalarySheet"
          >
            {{ isLocked ? 'Locked' : 'Lock Month' }}
          </button>
          <a
            :href="holidayAuditTargetUrl"
            class="px-3 py-2 text-sm text-white bg-orange-600 rounded hover:bg-orange-700"
          >
            Holiday Audit CSV
          </a>
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
        <span
          v-if="isLocked"
          class="inline-flex items-center px-2 py-0.5 ml-2 text-xs font-semibold text-red-700 bg-red-100 rounded"
        >
          Locked
        </span>
      </div>

      <div class="mt-3 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900 print:hidden">
        <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold bg-emerald-600 text-white mr-2">Auto Synced</span>
        Salary computation is fed by auto-updated attendance IN/OUT with roster and leave integration.
        <div class="mt-2 text-emerald-800">
          Short late waiver: <span class="font-semibold">{{ waiveShortLate ? 'ON' : 'OFF' }}</span>
          <span v-if="waiveShortLate"> ({{ shortLateLimitMinutes }} min পর্যন্ত late-এ salary কাটবে না)</span>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-3 mt-3 md:grid-cols-7 print:hidden">
        <label class="flex flex-col text-sm text-gray-700">
          <span class="mb-1 font-semibold">Late Fee Per Late Day (Tk)</span>
          <input
            v-model.number="lateFeePerLate"
            type="number"
            min="0"
            step="0.01"
            class="p-2 border border-gray-300 rounded"
          />
        </label>
        <label class="flex flex-col text-sm text-gray-700">
          <span class="mb-1 font-semibold">Overtime Multiplier</span>
          <input
            v-model.number="overtimeMultiplier"
            type="number"
            min="0"
            step="0.01"
            class="p-2 border border-gray-300 rounded"
          />
        </label>
        <label class="flex flex-col text-sm text-gray-700">
          <span class="mb-1 font-semibold">Late Grace Days</span>
          <input
            v-model.number="lateGraceDays"
            type="number"
            min="0"
            step="1"
            class="p-2 border border-gray-300 rounded"
          />
        </label>
        <label class="flex flex-col text-sm text-gray-700">
          <span class="mb-1 font-semibold">Late Deduction Rate</span>
          <input
            v-model.number="lateDeductionRate"
            type="number"
            min="0"
            step="0.01"
            class="p-2 border border-gray-300 rounded"
          />
        </label>
        <label class="flex flex-col text-sm text-gray-700">
          <span class="mb-1 font-semibold">Unpaid Alert Limit</span>
          <input
            v-model.number="unpaidHighlightLimit"
            type="number"
            min="0"
            step="1"
            class="p-2 border border-gray-300 rounded"
          />
        </label>
        <label class="flex flex-col text-sm text-gray-700">
          <span class="mb-1 font-semibold">Short Late Limit (Min)</span>
          <input
            v-model.number="shortLateLimitMinutes"
            type="number"
            min="0"
            step="1"
            class="p-2 border border-gray-300 rounded"
          />
        </label>
        <label class="flex items-end gap-2 pb-2 text-sm text-gray-700">
          <input
            v-model="waiveShortLate"
            type="checkbox"
            class="w-4 h-4"
          />
          <span class="font-semibold">Manual Off: 10-15 min late salary কাটবে না</span>
        </label>
        <div class="flex items-end">
          <button
            type="button"
            class="px-3 py-2 text-sm text-white bg-blue-600 rounded hover:bg-blue-700"
            @click="applyFilter"
          >
            Apply Options
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-3 mt-4 sm:grid-cols-2 lg:grid-cols-5 print:hidden">
        <Link
          v-if="integrationOptions.modules.face_attendance"
          :href="route('backend.attendance.face')"
          class="p-3 rounded-md border border-violet-200 bg-violet-50 hover:bg-violet-100 transition"
        >
          <div class="text-xs font-semibold text-violet-700">Attendance</div>
          <div class="text-sm font-bold text-violet-900 mt-1">Face Attendance</div>
        </Link>

        <a
          v-if="integrationOptions.modules.fingerprint || integrationOptions.modules.face_attendance"
          href="/admin/attendance/devices"
          class="p-3 rounded-md border border-cyan-200 bg-cyan-50 hover:bg-cyan-100 transition"
        >
          <div class="text-xs font-semibold text-cyan-700">Device</div>
          <div class="text-sm font-bold text-cyan-900 mt-1">Fingerprint/Face Devices</div>
        </a>

        <Link
          v-if="integrationOptions.modules.leave"
          :href="route('backend.pending.request')"
          class="p-3 rounded-md border border-amber-200 bg-amber-50 hover:bg-amber-100 transition"
        >
          <div class="text-xs font-semibold text-amber-700">Leave</div>
          <div class="text-sm font-bold text-amber-900 mt-1">Leave Requests</div>
        </Link>

        <Link
          v-if="integrationOptions.modules.duty_roster"
          :href="route('backend.staffattendance.duty-roster')"
          class="p-3 rounded-md border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 transition"
        >
          <div class="text-xs font-semibold text-emerald-700">Roster</div>
          <div class="text-sm font-bold text-emerald-900 mt-1">Duty Roster</div>
        </Link>

        <Link
          v-if="integrationOptions.modules.salary_sheet"
          :href="route('backend.staffattendance.report')"
          class="p-3 rounded-md border border-slate-200 bg-slate-50 hover:bg-slate-100 transition"
        >
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
              <th class="px-3 py-2 border">Late Days</th>
              <th class="px-3 py-2 border">Late (Deduction)</th>
              <th class="px-3 py-2 border">Late (HH:MM:SS)</th>
              <th class="px-3 py-2 border">Short Late Waived</th>
              <th class="px-3 py-2 border">OT (HH:MM:SS)</th>
              <th class="px-3 py-2 border">Unpaid Days</th>
              <th class="px-3 py-2 border">Hourly Rate</th>
              <th class="px-3 py-2 border">Late Deduction (Hr)</th>
              <th class="px-3 py-2 border">OT Bonus (Hr)</th>
              <th class="px-3 py-2 border">Biometric Deduction</th>
              <th class="px-3 py-2 border">Late Fee</th>
              <th class="px-3 py-2 border">Late Policy Deduction</th>
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
              <td class="px-3 py-2 border">{{ row.department }}</td>
              <td class="px-3 py-2 border">{{ row.designation }}</td>
              <td class="px-3 py-2 border">{{ money(row.basic_salary) }}</td>
              <td class="px-3 py-2 border">{{ row.workable_days }}</td>
              <td class="px-3 py-2 border">{{ row.paid_days }}</td>
              <td :class="lateCellClass(row)">{{ row.late }}</td>
              <td class="px-3 py-2 border">{{ row.late_for_deduction }}</td>
              <td class="px-3 py-2 border">{{ minutesToHms(row.late_minutes) }}</td>
              <td class="px-3 py-2 border">{{ minutesToHms(row.waived_short_late_minutes) }}</td>
              <td class="px-3 py-2 border">{{ minutesToHms(row.overtime_minutes) }}</td>
              <td :class="unpaidCellClass(row)">{{ row.unpaid_days }}</td>
              <td class="px-3 py-2 border">{{ money(row.hourly_rate) }}</td>
              <td class="px-3 py-2 border">{{ money(row.late_deduction_hourly) }}</td>
              <td class="px-3 py-2 border">{{ money(row.overtime_bonus_hourly) }}</td>
              <td class="px-3 py-2 border">{{ money(row.biometric_deduction) }}</td>
              <td class="px-3 py-2 border">{{ money(row.late_fee) }}</td>
              <td class="px-3 py-2 border">{{ money(row.late_policy_deduction) }}</td>
              <td class="px-3 py-2 border">{{ money(row.overtime_bonus) }}</td>
              <td class="px-3 py-2 border">{{ money(row.advance_paid) }}</td>
              <td class="px-3 py-2 border">{{ money(row.deduction) }}</td>
              <td class="px-3 py-2 font-semibold text-emerald-700 border">{{ money(row.payable_salary) }}</td>
              <td class="px-3 py-2 border">
                <div class="flex flex-wrap items-center gap-2">
                  <button
                    type="button"
                    class="px-3 py-1 text-sm text-white bg-slate-600 rounded hover:bg-slate-700"
                    @click="openBreakdown(row)"
                  >
                    Breakdown
                  </button>
                  <button
                    type="button"
                    class="px-3 py-1 text-sm text-white rounded"
                    :class="isLocked ? 'bg-gray-400 cursor-not-allowed' : 'bg-emerald-600 hover:bg-emerald-700'"
                    :disabled="isLocked"
                    @click.prevent="paySalary(row.staff_admin_id, row.payable_salary)"
                  >
                    Pay
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="25" class="px-3 py-6 text-center text-gray-500 border">No staff data found for this month.</td>
            </tr>
          </tbody>
          <tfoot v-if="rows.length > 0" class="bg-gray-100">
            <tr>
              <td colspan="5" class="px-3 py-2 font-semibold border">Total ({{ totals.staff_count ?? 0 }} staff)</td>
              <td class="px-3 py-2 font-semibold border">{{ money(totals.basic_salary) }}</td>
              <td class="px-3 py-2 border"></td>
              <td class="px-3 py-2 border"></td>
              <td class="px-3 py-2 border"></td>
              <td class="px-3 py-2 font-semibold border">{{ minutesToHms(totals.late_minutes ?? 0) }}</td>
              <td class="px-3 py-2 border"></td>
              <td class="px-3 py-2 font-semibold border">{{ minutesToHms(totals.waived_short_late_minutes ?? 0) }}</td>
              <td class="px-3 py-2 font-semibold border">{{ minutesToHms(totals.overtime_minutes ?? 0) }}</td>
              <td class="px-3 py-2 border"></td>
              <td class="px-3 py-2 border"></td>
              <td class="px-3 py-2 font-semibold border">{{ money(totals.late_deduction_hourly) }}</td>
              <td class="px-3 py-2 font-semibold border">{{ money(totals.overtime_bonus_hourly) }}</td>
              <td class="px-3 py-2 font-semibold border">{{ money(totals.biometric_deduction) }}</td>
              <td class="px-3 py-2 font-semibold border">{{ money(totals.late_fee) }}</td>
              <td class="px-3 py-2 font-semibold border">{{ money(totals.late_policy_deduction) }}</td>
              <td class="px-3 py-2 font-semibold border">{{ money(totals.overtime_bonus) }}</td>
              <td class="px-3 py-2 border"></td>
              <td class="px-3 py-2 font-semibold border">{{ money(totals.deduction) }}</td>
              <td class="px-3 py-2 font-semibold text-emerald-700 border">{{ money(totals.payable_salary) }}</td>
              <td class="px-3 py-2 border"></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div
        v-if="selectedBreakdownRow"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 print:hidden"
      >
        <div class="w-full max-w-5xl bg-white rounded-lg shadow-xl">
          <div class="flex items-start justify-between p-4 border-b">
            <div>
              <h3 class="text-lg font-bold text-gray-800">Attendance Breakdown</h3>
              <p class="text-sm text-gray-600">
                {{ selectedBreakdownRow.name }} ({{ selectedBreakdownRow.staff_id }})
              </p>
            </div>
            <div class="flex items-center gap-2">
              <a
                v-if="selectedBreakdownPdfUrl"
                :href="selectedBreakdownPdfUrl"
                class="px-3 py-1 text-sm text-white bg-emerald-600 rounded hover:bg-emerald-700"
              >
                PDF
              </a>
              <a
                v-if="selectedBreakdownPrintUrl"
                :href="selectedBreakdownPrintUrl"
                target="_blank"
                rel="noopener"
                class="px-3 py-1 text-sm text-white bg-sky-600 rounded hover:bg-sky-700"
              >
                Print
              </a>
              <button
                type="button"
                class="px-3 py-1 text-sm text-white bg-gray-600 rounded hover:bg-gray-700"
                @click="closeBreakdown"
              >
                Close
              </button>
            </div>
          </div>

          <div class="p-4 overflow-x-auto max-h-[70vh]">
            <table class="w-full text-sm text-left text-gray-700 border border-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 border">Date</th>
                  <th class="px-3 py-2 border">IN</th>
                  <th class="px-3 py-2 border">OUT</th>
                  <th class="px-3 py-2 border">Duration (Min)</th>
                  <th class="px-3 py-2 border">Late (Min)</th>
                  <th class="px-3 py-2 border">OT (Min)</th>
                  <th class="px-3 py-2 border">Deduction</th>
                  <th class="px-3 py-2 border">Overtime</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(item, idx) in (selectedBreakdownRow.attendance_breakdown || [])"
                  :key="`${selectedBreakdownRow.staff_id}-${idx}-${item.date}`"
                >
                  <td class="px-3 py-2 border">{{ item.date || '-' }}</td>
                  <td class="px-3 py-2 border">{{ item.in_time || '-' }}</td>
                  <td class="px-3 py-2 border">{{ item.out_time || '-' }}</td>
                  <td class="px-3 py-2 border">{{ item.duration_minutes ?? 0 }}</td>
                  <td class="px-3 py-2 border">{{ minutesToHms(item.late_minutes ?? 0) }}</td>
                  <td class="px-3 py-2 border">{{ minutesToHms(item.overtime_minutes ?? 0) }}</td>
                  <td class="px-3 py-2 border">{{ money(item.deduction_amount) }}</td>
                  <td class="px-3 py-2 border">{{ money(item.overtime_amount) }}</td>
                </tr>
                <tr v-if="(selectedBreakdownRow.attendance_breakdown || []).length === 0">
                  <td colspan="8" class="px-3 py-6 text-center text-gray-500 border">No attendance breakdown available for this month.</td>
                </tr>
              </tbody>
              <tfoot v-if="(selectedBreakdownRow.attendance_breakdown || []).length > 0" class="bg-gray-100">
                <tr>
                  <td colspan="3" class="px-3 py-2 font-semibold border">Total</td>
                  <td class="px-3 py-2 font-semibold border">{{ selectedBreakdownTotals.duration_minutes }}</td>
                  <td class="px-3 py-2 font-semibold border">{{ minutesToHms(selectedBreakdownTotals.late_minutes) }}</td>
                  <td class="px-3 py-2 font-semibold border">{{ minutesToHms(selectedBreakdownTotals.overtime_minutes) }}</td>
                  <td class="px-3 py-2 font-semibold border">{{ money(selectedBreakdownTotals.deduction_amount) }}</td>
                  <td class="px-3 py-2 font-semibold border">{{ money(selectedBreakdownTotals.overtime_amount) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>
  </BackendLayout>
</template>
