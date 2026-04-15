<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import BackendLayout from "@/Layouts/BackendLayout.vue";
import { Link, router, useForm } from "@inertiajs/vue3";

const APP_TIMEZONE = "Asia/Dhaka";

const formatDateTime = (value) => {
  if (!value) return "N/A";

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);

  return date.toLocaleString("en-GB", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    hour12: true,
    timeZone: APP_TIMEZONE,
  });
};

const props = defineProps({
  ipdpatient: Object,
  latestPrescription: Object,
  payments: Array,
  overviewTotals: Object,
  runningBill: Object,
});

const tabs = [
  { key: "overview", label: "Overview" },
  { key: "nurse_notes", label: "Nurse Notes" },
  { key: "medication", label: "Medication" },
  { key: "consultant_register", label: "Consultant Register" },
  { key: "operations", label: "Operations" },
  { key: "charges", label: "Charges" },
  { key: "payments", label: "Payments" },
  { key: "live_consultation", label: "Live Consultation" },
  { key: "bed_history", label: "Bed History" },
];

const activeTab = ref("overview");
const showPrintMenu = ref(false);
const printMenuRef = ref(null);

const patientName = computed(() => props.ipdpatient?.patient?.name ?? "N/A");
const admissionDate = computed(() => {
  const value = props.ipdpatient?.admission_date;
  return formatDateTime(value);
});

const statusLabel = computed(() => {
  const status = props.ipdpatient?.status;
  if (status === "Inactive") return "Discharged";
  return status ?? "N/A";
});

const medicineItems = computed(() => props.latestPrescription?.medicines ?? []);
const testItems = computed(() => props.latestPrescription?.tests ?? []);

const notes = computed(() => props.ipdpatient?.ipd_notes ?? []);
const nurseNotes = computed(() => (notes.value || []).filter((n) => n.type === 'nurse_note'));
const consultantRegister = computed(() => (notes.value || []).filter((n) => n.type === 'consultant_register'));
const operationsList = computed(() => (notes.value || []).filter((n) => n.type === 'operation'));
const bedHistoryList = computed(() => (notes.value || []).filter((n) => n.type === 'bed_history'));

const nurseForm = useForm({ content: '', type: 'nurse_note' });
const consultantForm = useForm({ content: '', type: 'consultant_register' });
const operationForm = useForm({ content: '', type: 'operation' });
const bedHistoryForm = useForm({ content: '', type: 'bed_history' });

const submitNote = (form) => {
  if (!props.ipdpatient?.id) return;
  form.post(route('backend.ipdpatient.notes.store', props.ipdpatient.id), {
    preserveScroll: true,
    onSuccess: () => form.reset('content'),
  });
};

const liveForm = useForm({ live_consultation: props.ipdpatient?.live_consultation ?? '' });
const submitLiveConsultation = () => {
  if (!props.ipdpatient?.id) return;
  liveForm.post(route('backend.ipdpatient.live_consultation.update', props.ipdpatient.id), {
    preserveScroll: true,
  });
};

const roomRentCharges = computed(() => props.ipdpatient?.room_rent_charges ?? []);
const bedCharges = computed(() => props.ipdpatient?.bed_charges ?? []);
const otCharges = computed(() => props.ipdpatient?.ot_charges ?? []);
const doctorVisitCharges = computed(() => props.ipdpatient?.doctor_visit_charges ?? []);
const overviewTotals = computed(() => props.overviewTotals ?? {});
const runningBill = computed(() => props.runningBill ?? {});

const formatMoney = (value) => {
  const amount = Number(value ?? 0);
  if (Number.isNaN(amount)) return "Tk 0.00";
  return `Tk ${new Intl.NumberFormat("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount)}`;
};

const refreshRunningBill = () => {
  router.reload({ preserveScroll: true });
};

const roomRentForm = useForm({
  bed_id: props.ipdpatient?.bed_id ?? null,
  started_at: "",
  ended_at: "",
  rate_per_day: "",
  notes: "",
});

const bedChargeForm = useForm({
  bed_id: props.ipdpatient?.bed_id ?? null,
  started_at: "",
  ended_at: "",
  rate_per_day: "",
  notes: "",
});

const otForm = useForm({
  charge_name: "",
  procedure_name: "",
  performed_at: "",
  unit_price: "",
  quantity: 1,
});

const doctorVisitForm = useForm({
  doctor_id: props.ipdpatient?.consultant_doctor_id ?? null,
  doctor_name: "",
  visited_at: "",
  fee_per_visit: "",
  visit_count: 1,
  notes: "",
});

const paymentForm = useForm({
  amount: '',
  payment_method: '',
  transaction_id: '',
  notes: '',
});

const submitPayment = () => {
  if (!props.ipdpatient?.id) return;
  paymentForm.post(route('backend.ipdpatient.payments.store', props.ipdpatient.id), {
    preserveScroll: true,
    onSuccess: () => paymentForm.reset('amount', 'payment_method', 'transaction_id', 'notes'),
  });
};

const submitRoomRent = () => {
  if (!props.ipdpatient?.id) return;
  roomRentForm.post(route("backend.ipdpatient.charges.room-rent.store", props.ipdpatient.id), {
    preserveScroll: true,
    onSuccess: () => roomRentForm.reset("started_at", "ended_at", "rate_per_day", "notes"),
  });
};

const deleteRoomRent = (chargeId) => {
  if (!props.ipdpatient?.id || !chargeId) return;
  const ok = window.confirm("Delete this room rent charge?");
  if (!ok) return;

  router.delete(
    route("backend.ipdpatient.charges.room-rent.destroy", {
      id: props.ipdpatient.id,
      chargeId,
    }),
    { preserveScroll: true }
  );
};

const submitBedCharge = () => {
  if (!props.ipdpatient?.id) return;
  bedChargeForm.post(route("backend.ipdpatient.charges.bed.store", props.ipdpatient.id), {
    preserveScroll: true,
    onSuccess: () => bedChargeForm.reset("started_at", "ended_at", "rate_per_day", "notes"),
  });
};

const deleteBedCharge = (chargeId) => {
  if (!props.ipdpatient?.id || !chargeId) return;
  const ok = window.confirm("Delete this bed charge?");
  if (!ok) return;

  router.delete(
    route("backend.ipdpatient.charges.bed.destroy", {
      id: props.ipdpatient.id,
      chargeId,
    }),
    { preserveScroll: true }
  );
};

const submitOtCharge = () => {
  if (!props.ipdpatient?.id) return;
  otForm.post(route("backend.ipdpatient.charges.ot.store", props.ipdpatient.id), {
    preserveScroll: true,
    onSuccess: () => otForm.reset("charge_name", "procedure_name", "performed_at", "unit_price", "quantity"),
  });
};

const deleteOtCharge = (chargeId) => {
  if (!props.ipdpatient?.id || !chargeId) return;
  const ok = window.confirm("Delete this OT charge?");
  if (!ok) return;

  router.delete(
    route("backend.ipdpatient.charges.ot.destroy", {
      id: props.ipdpatient.id,
      chargeId,
    }),
    { preserveScroll: true }
  );
};

const submitDoctorVisitCharge = () => {
  if (!props.ipdpatient?.id) return;
  doctorVisitForm.post(route("backend.ipdpatient.charges.doctor-visit.store", props.ipdpatient.id), {
    preserveScroll: true,
    onSuccess: () => doctorVisitForm.reset("doctor_name", "visited_at", "fee_per_visit", "visit_count", "notes"),
  });
};

const deleteDoctorVisitCharge = (chargeId) => {
  if (!props.ipdpatient?.id || !chargeId) return;
  const ok = window.confirm("Delete this doctor visit charge?");
  if (!ok) return;

  router.delete(
    route("backend.ipdpatient.charges.doctor-visit.destroy", {
      id: props.ipdpatient.id,
      chargeId,
    }),
    { preserveScroll: true }
  );
};

const regenerateBilling = () => {
  if (!props.ipdpatient?.id) return;
  const ok = window.confirm(
    "Regenerate discharge billing?\n\nThis will rebuild Bill Items using latest IPD charges (room rent/bed/OT/doctor visit) + Pathology/Radiology/Medicine."
  );
  if (!ok) return;

  router.post(route("backend.ipdpatient.discharge-billing.regenerate", props.ipdpatient.id), {}, {
    preserveScroll: true,
  });
};

const togglePrintMenu = () => {
  showPrintMenu.value = !showPrintMenu.value;
};

const handleOutsideClick = (event) => {
  if (!showPrintMenu.value) return;
  if (!printMenuRef.value) return;
  if (!printMenuRef.value.contains(event.target)) {
    showPrintMenu.value = false;
  }
};

onMounted(() => {
  window.addEventListener("click", handleOutsideClick);
});

onBeforeUnmount(() => {
  window.removeEventListener("click", handleOutsideClick);
});
</script>

<template>
  <BackendLayout>
    <div class="w-full p-2 bg-white rounded-md dark:bg-slate-900">
      <div class="mb-3 flex items-start justify-between gap-3">
        <div>
          <div class="text-xl font-bold text-gray-800 dark:text-gray-100">IPD Admission Patient</div>
          <div class="mt-1 text-xs text-gray-600 dark:text-gray-300">
            <span class="font-semibold">{{ patientName }}</span>
            <span v-if="props.ipdpatient?.id"> (IPD: {{ props.ipdpatient.id }})</span>
            <span class="ml-2 inline-flex items-center rounded px-2 py-0.5 text-[11px] font-semibold"
              :class="props.ipdpatient?.status === 'Inactive'
                ? 'bg-amber-100 text-amber-700'
                : props.ipdpatient?.status === 'Active'
                  ? 'bg-emerald-100 text-emerald-700'
                  : 'bg-gray-200 text-gray-700'">
              {{ statusLabel }}
            </span>
          </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
          <div class="flex items-center gap-2">
            <Link :href="route('backend.ipdpatient.index')"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-gray-700 text-white rounded">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12l7.5-7.5M3 12h18" />
              </svg>
              Back
            </Link>

            <Link v-if="props.ipdpatient?.id" :href="route('backend.ipdpatient.prescription', props.ipdpatient.id)"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-indigo-600 text-white rounded">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M9 12h6m-6 4h6M7.5 3.75h9A2.25 2.25 0 0 1 18.75 6v12A2.25 2.25 0 0 1 16.5 20.25h-9A2.25 2.25 0 0 1 5.25 18V6A2.25 2.25 0 0 1 7.5 3.75Z" />
              </svg>
              Prescription
            </Link>
          </div>

          <div class="flex items-center gap-2">
            <a v-if="props.ipdpatient?.id" :href="route('backend.download.ipd.invoice', { id: props.ipdpatient.id })"
              target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-rose-600 text-white rounded">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M9 12h6m-6 4h6M7.5 3.75h9A2.25 2.25 0 0 1 18.75 6v12A2.25 2.25 0 0 1 16.5 20.25h-9A2.25 2.25 0 0 1 5.25 18V6A2.25 2.25 0 0 1 7.5 3.75Z" />
              </svg>
              IPD Invoice
            </a>

            <a v-if="props.ipdpatient?.status === 'Inactive' && props.ipdpatient?.id"
              :href="route('backend.download.ipd.final-bill', { id: props.ipdpatient.id })" target="_blank" rel="noopener"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-fuchsia-700 text-white rounded">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M9 12h6m-6 4h6M7.5 3.75h9A2.25 2.25 0 0 1 18.75 6v12A2.25 2.25 0 0 1 16.5 20.25h-9A2.25 2.25 0 0 1 5.25 18V6A2.25 2.25 0 0 1 7.5 3.75Z" />
              </svg>
              Final Bill
            </a>
          </div>

          <div class="flex items-center gap-2">
            <button v-if="props.ipdpatient?.status === 'Inactive' && props.ipdpatient?.id" type="button"
              @click="regenerateBilling" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-orange-600 text-white rounded">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M16.023 9.348h4.992m0 0v4.992m0-4.992-4.992 4.992M7.977 14.652H3m0 0v-4.992m0 4.992 4.992-4.992" />
              </svg>
              Regenerate
            </button>

            <div class="relative" ref="printMenuRef">
              <button type="button" @click="togglePrintMenu"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-gray-200 text-gray-700 rounded">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                  stroke="currentColor" class="w-4 h-4">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M6.75 7.5V3.75h10.5V7.5m-10.5 0h10.5m-10.5 0A2.25 2.25 0 0 0 4.5 9.75v6a2.25 2.25 0 0 0 2.25 2.25h10.5A2.25 2.25 0 0 0 19.5 15.75v-6A2.25 2.25 0 0 0 17.25 7.5m-7.5 8.25h4.5" />
                </svg>
                Print
              </button>

              <div v-if="showPrintMenu" class="absolute right-0 mt-2 w-56 bg-white border border-gray-200 rounded shadow-lg z-50">
                <a v-if="props.ipdpatient?.id" :href="route('backend.print.ipd.invoice', { id: props.ipdpatient.id })"
                  target="_blank" rel="noopener" class="block px-3 py-2 text-xs text-gray-700 hover:bg-gray-50">
                  Print Invoice
                </a>
                <a v-if="props.ipdpatient?.status === 'Inactive' && props.ipdpatient?.id"
                  :href="route('backend.print.ipd.final-bill', { id: props.ipdpatient.id })" target="_blank" rel="noopener"
                  class="block px-3 py-2 text-xs text-gray-700 hover:bg-gray-50">
                  Print Final Bill
                </a>
                <a v-if="props.ipdpatient?.id" :href="route('backend.ipdpatient.running-bill.print', props.ipdpatient.id)"
                  target="_blank" rel="noopener" class="block px-3 py-2 text-xs text-gray-700 hover:bg-gray-50">
                  Print Running Bill
                </a>
                <a v-if="props.ipdpatient?.status === 'Inactive' && props.ipdpatient?.id"
                  :href="route('backend.ipdpatient.discharge-certificate.pdf', props.ipdpatient.id)" target="_blank" rel="noopener"
                  class="block px-3 py-2 text-xs text-gray-700 hover:bg-gray-50">
                  Discharge Certificate
                </a>
                <a v-if="props.ipdpatient?.status === 'Inactive' && props.ipdpatient?.id"
                  :href="route('backend.ipdpatient.discharge-certificate.print', props.ipdpatient.id)" target="_blank" rel="noopener"
                  class="block px-3 py-2 text-xs text-gray-700 hover:bg-gray-50">
                  Print Certificate
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="mb-3 overflow-x-auto">
        <div class="inline-flex gap-2 border-b border-gray-200 dark:border-gray-700 min-w-full">
          <button v-for="tab in tabs" :key="tab.key" type="button" @click="activeTab = tab.key"
            class="px-3 py-2 text-xs font-semibold whitespace-nowrap border-b-2 transition-colors"
            :class="activeTab === tab.key
              ? 'border-blue-600 text-blue-700 dark:text-blue-300'
              : 'border-transparent text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-gray-100'">
            {{ tab.label }}
          </button>
        </div>
      </div>

      <!-- Overview -->
      <div v-if="activeTab === 'overview'" class="grid grid-cols-1 gap-3 md:grid-cols-2">
        <div class="border border-gray-200 dark:border-gray-700 rounded p-3">
          <div class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-2">Patient</div>
          <div class="space-y-1 text-xs text-gray-700 dark:text-gray-200">
            <div><span class="font-semibold">Name:</span> {{ props.ipdpatient?.patient?.name ?? 'N/A' }}</div>
            <div><span class="font-semibold">Gender:</span> {{ props.ipdpatient?.patient?.gender ?? 'N/A' }}</div>
            <div><span class="font-semibold">Age:</span> {{ props.ipdpatient?.patient?.age ?? 'N/A' }}</div>
            <div><span class="font-semibold">Phone:</span> {{ props.ipdpatient?.patient?.phone ?? 'N/A' }}</div>
          </div>
        </div>

        <div class="border border-gray-200 dark:border-gray-700 rounded p-3 md:col-span-2">
          <div class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-2">Summary</div>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
            <div class="p-2 border rounded">
              <div class="font-semibold">Nurse Notes</div>
              <div class="text-sm">{{ overviewTotals.nurse_notes ?? 0 }}</div>
            </div>
            <div class="p-2 border rounded">
              <div class="font-semibold">Consultant Register</div>
              <div class="text-sm">{{ overviewTotals.consultant_register ?? 0 }}</div>
            </div>
            <div class="p-2 border rounded">
              <div class="font-semibold">Operations</div>
              <div class="text-sm">{{ overviewTotals.operations ?? 0 }}</div>
            </div>
            <div class="p-2 border rounded">
              <div class="font-semibold">Bed History</div>
              <div class="text-sm">{{ overviewTotals.bed_history ?? 0 }}</div>
            </div>

            <div class="p-2 border rounded">
              <div class="font-semibold">Medicines</div>
              <div class="text-sm">{{ overviewTotals.medicines ?? 0 }}</div>
            </div>
            <div class="p-2 border rounded">
              <div class="font-semibold">Tests</div>
              <div class="text-sm">{{ overviewTotals.tests ?? 0 }}</div>
            </div>
            <div class="p-2 border rounded">
              <div class="font-semibold">Charges</div>
              <div class="text-sm">{{ (overviewTotals.room_rent_charges ?? 0) + (overviewTotals.bed_charges ?? 0) + (overviewTotals.ot_charges ?? 0) + (overviewTotals.doctor_visit_charges ?? 0) }}</div>
            </div>
            <div class="p-2 border rounded">
              <div class="font-semibold">Payments</div>
              <div class="text-sm">{{ overviewTotals.payments ?? 0 }}</div>
            </div>

            <div class="p-2 border rounded md:col-span-2">
              <div class="font-semibold">Live Consultation</div>
              <div class="text-sm">{{ overviewTotals.live_consultation ?? 'Not set' }}</div>
            </div>
          </div>
        </div>

        <div class="border border-gray-200 dark:border-gray-700 rounded p-3 md:col-span-2">
          <div class="flex items-center justify-between mb-2">
            <div class="text-xs font-semibold text-gray-700 dark:text-gray-200">Running Bill (Before Discharge)</div>
            <button type="button" @click="refreshRunningBill"
              class="px-2 py-1 text-[11px] bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
              Refresh
            </button>
          </div>
          <div class="text-[11px] text-gray-500 mb-2">As of {{ runningBill.as_of ?? 'N/A' }}</div>
          <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-xs">
            <div class="p-2 border rounded">
              <div class="font-semibold">Total</div>
              <div class="text-sm">{{ formatMoney(runningBill.total) }}</div>
            </div>
            <div class="p-2 border rounded">
              <div class="font-semibold">Paid</div>
              <div class="text-sm">{{ formatMoney(runningBill.paid) }}</div>
            </div>
            <div class="p-2 border rounded">
              <div class="font-semibold">Due</div>
              <div class="text-sm">{{ formatMoney(runningBill.due) }}</div>
            </div>
            <div class="p-2 border rounded">
              <div class="font-semibold">Change</div>
              <div class="text-sm">{{ formatMoney(runningBill.change) }}</div>
            </div>
            <div class="p-2 border rounded">
              <div class="font-semibold">Status</div>
              <div class="text-sm">{{ runningBill.payment_status ?? 'N/A' }}</div>
            </div>
          </div>
        </div>

        <div class="border border-gray-200 dark:border-gray-700 rounded p-3">
          <div class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-2">Admission</div>
          <div class="space-y-1 text-xs text-gray-700 dark:text-gray-200">
            <div><span class="font-semibold">Admission Date:</span> {{ admissionDate }}</div>
            <div><span class="font-semibold">Case:</span> {{ props.ipdpatient?.case ?? 'N/A' }}</div>
            <div><span class="font-semibold">Consultant:</span> {{ props.ipdpatient?.doctor?.name ?? 'N/A' }}</div>
            <div><span class="font-semibold">Bed:</span> {{ props.ipdpatient?.bed?.name ?? 'N/A' }}</div>
            <div><span class="font-semibold">Live Consultation:</span> {{ props.ipdpatient?.live_consultation ?? 'N/A' }}</div>
          </div>
        </div>
      </div>

      <!-- Nurse Notes -->
      <div v-else-if="activeTab === 'nurse_notes'" class="border border-gray-200 dark:border-gray-700 rounded p-3">
        <form @submit.prevent="submitNote(nurseForm)" class="mb-3">
          <textarea v-model="nurseForm.content" class="w-full p-2 text-xs border rounded" rows="3" placeholder="Add nurse note"></textarea>
          <div class="mt-2"><button type="submit" :disabled="nurseForm.processing" class="px-3 py-1.5 text-xs bg-blue-600 text-white rounded">Add Nurse Note</button></div>
        </form>

        <div v-if="nurseNotes.length" class="space-y-2 text-xs">
          <div v-for="n in nurseNotes" :key="n.id" class="p-2 border rounded">
            <div class="text-[12px] text-gray-700 dark:text-gray-200">{{ n.content }}</div>
            <div class="text-[11px] text-gray-500">{{ formatDateTime(n.created_at) }}</div>
          </div>
        </div>
        <div v-else class="text-xs text-gray-600 dark:text-gray-300">No nurse notes yet.</div>
      </div>

      <!-- Medication -->
      <div v-else-if="activeTab === 'medication'" class="space-y-3">
        <div class="border border-gray-200 dark:border-gray-700 rounded p-3">
          <div class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-2">Latest Prescription Medicines</div>

          <div v-if="medicineItems.length" class="overflow-x-auto">
            <table class="min-w-full text-xs border-collapse">
              <thead>
                <tr class="bg-gray-100 text-gray-700 dark:bg-slate-800 dark:text-gray-200">
                  <th class="px-2 py-2 border">Medicine</th>
                  <th class="px-2 py-2 border">Dose</th>
                  <th class="px-2 py-2 border">Frequency</th>
                  <th class="px-2 py-2 border">Duration</th>
                  <th class="px-2 py-2 border">Instructions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in medicineItems" :key="item.id">
                  <td class="px-2 py-2 border">{{ item.medicine_name ?? 'N/A' }}</td>
                  <td class="px-2 py-2 border">{{ item.dose ?? '' }}</td>
                  <td class="px-2 py-2 border">{{ item.frequency ?? '' }}</td>
                  <td class="px-2 py-2 border">{{ item.duration ?? '' }}</td>
                  <td class="px-2 py-2 border">{{ item.instructions ?? '' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="text-xs text-gray-600 dark:text-gray-300">No medicines found.</div>
        </div>

        <div class="border border-gray-200 dark:border-gray-700 rounded p-3">
          <div class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-2">Recommended Tests</div>
          <ul v-if="testItems.length" class="list-disc ml-5 text-xs text-gray-700 dark:text-gray-200">
            <li v-for="test in testItems" :key="test.id">{{ test.test_name ?? 'N/A' }}</li>
          </ul>
          <div v-else class="text-xs text-gray-600 dark:text-gray-300">No tests found.</div>
        </div>
      </div>

      <!-- Consultant Register -->
      <div v-else-if="activeTab === 'consultant_register'" class="border border-gray-200 dark:border-gray-700 rounded p-3">
        <form @submit.prevent="submitNote(consultantForm)" class="mb-3">
          <textarea v-model="consultantForm.content" class="w-full p-2 text-xs border rounded" rows="3" placeholder="Add consultant register entry"></textarea>
          <div class="mt-2"><button type="submit" :disabled="consultantForm.processing" class="px-3 py-1.5 text-xs bg-blue-600 text-white rounded">Add Entry</button></div>
        </form>

        <div v-if="consultantRegister.length" class="space-y-2 text-xs">
          <div v-for="c in consultantRegister" :key="c.id" class="p-2 border rounded">
            <div class="text-[12px] text-gray-700 dark:text-gray-200">{{ c.content }}</div>
            <div class="text-[11px] text-gray-500">{{ formatDateTime(c.created_at) }}</div>
          </div>
        </div>
        <div v-else class="text-xs text-gray-600 dark:text-gray-300">No consultant register entries yet.</div>
      </div>

      <!-- Operations -->
      <div v-else-if="activeTab === 'operations'" class="border border-gray-200 dark:border-gray-700 rounded p-3">
        <form @submit.prevent="submitNote(operationForm)" class="mb-3">
          <textarea v-model="operationForm.content" class="w-full p-2 text-xs border rounded" rows="3" placeholder="Add operation / OT note"></textarea>
          <div class="mt-2"><button type="submit" :disabled="operationForm.processing" class="px-3 py-1.5 text-xs bg-blue-600 text-white rounded">Add Operation</button></div>
        </form>

        <div v-if="operationsList.length" class="space-y-2 text-xs">
          <div v-for="o in operationsList" :key="o.id" class="p-2 border rounded">
            <div class="text-[12px] text-gray-700 dark:text-gray-200">{{ o.content }}</div>
            <div class="text-[11px] text-gray-500">{{ formatDateTime(o.created_at) }}</div>
          </div>
        </div>
        <div v-else class="text-xs text-gray-600 dark:text-gray-300">No operations recorded yet.</div>
      </div>

      <!-- Charges -->
      <div v-else-if="activeTab === 'charges'" class="space-y-4">
        <!-- Room Rent -->
        <div class="border border-gray-200 dark:border-gray-700 rounded p-3">
          <div class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-2">Room Rent</div>

          <form @submit.prevent="submitRoomRent" class="grid grid-cols-1 md:grid-cols-5 gap-2 items-end">
            <input v-model="roomRentForm.started_at" type="datetime-local" class="p-2 text-xs border rounded"
              placeholder="Start" />
            <input v-model="roomRentForm.ended_at" type="datetime-local" class="p-2 text-xs border rounded"
              placeholder="End" />
            <input v-model="roomRentForm.rate_per_day" type="number" step="0.01" class="p-2 text-xs border rounded"
              placeholder="Rate/day" />
            <input v-model="roomRentForm.notes" type="text" class="p-2 text-xs border rounded"
              placeholder="Notes" />
            <button type="submit" :disabled="roomRentForm.processing"
              class="px-3 py-2 text-xs bg-blue-600 text-white rounded">
              Add
            </button>
          </form>

          <div v-if="roomRentForm.errors.started_at || roomRentForm.errors.rate_per_day" class="mt-2 text-xs text-red-600">
            <div v-if="roomRentForm.errors.started_at">{{ roomRentForm.errors.started_at }}</div>
            <div v-if="roomRentForm.errors.rate_per_day">{{ roomRentForm.errors.rate_per_day }}</div>
          </div>

          <div class="mt-3 overflow-x-auto" v-if="roomRentCharges.length">
            <table class="min-w-full text-xs border-collapse">
              <thead>
                <tr class="bg-gray-100 text-gray-700 dark:bg-slate-800 dark:text-gray-200">
                  <th class="px-2 py-2 border">Start</th>
                  <th class="px-2 py-2 border">End</th>
                  <th class="px-2 py-2 border">Rate/day</th>
                  <th class="px-2 py-2 border">Bed</th>
                  <th class="px-2 py-2 border">Notes</th>
                  <th class="px-2 py-2 border">Action</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="c in roomRentCharges" :key="c.id">
                  <td class="px-2 py-2 border">{{ formatDateTime(c.started_at) }}</td>
                  <td class="px-2 py-2 border">{{ c.ended_at ? formatDateTime(c.ended_at) : '' }}</td>
                  <td class="px-2 py-2 border">{{ c.rate_per_day }}</td>
                  <td class="px-2 py-2 border">{{ c.bed?.name ?? '' }}</td>
                  <td class="px-2 py-2 border">{{ c.notes ?? '' }}</td>
                  <td class="px-2 py-2 border">
                    <button type="button" @click="deleteRoomRent(c.id)" class="px-2 py-1 text-[11px] bg-red-600 text-white rounded">
                      Delete
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="mt-2 text-xs text-gray-600 dark:text-gray-300">No room rent charges.</div>
        </div>

        <!-- Bed Charge -->
        <div class="border border-gray-200 dark:border-gray-700 rounded p-3">
          <div class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-2">Bed Charge</div>

          <form @submit.prevent="submitBedCharge" class="grid grid-cols-1 md:grid-cols-5 gap-2 items-end">
            <input v-model="bedChargeForm.started_at" type="datetime-local" class="p-2 text-xs border rounded"
              placeholder="Start" />
            <input v-model="bedChargeForm.ended_at" type="datetime-local" class="p-2 text-xs border rounded"
              placeholder="End" />
            <input v-model="bedChargeForm.rate_per_day" type="number" step="0.01" class="p-2 text-xs border rounded"
              placeholder="Rate/day" />
            <input v-model="bedChargeForm.notes" type="text" class="p-2 text-xs border rounded"
              placeholder="Notes" />
            <button type="submit" :disabled="bedChargeForm.processing"
              class="px-3 py-2 text-xs bg-blue-600 text-white rounded">
              Add
            </button>
          </form>

          <div v-if="bedChargeForm.errors.started_at || bedChargeForm.errors.rate_per_day" class="mt-2 text-xs text-red-600">
            <div v-if="bedChargeForm.errors.started_at">{{ bedChargeForm.errors.started_at }}</div>
            <div v-if="bedChargeForm.errors.rate_per_day">{{ bedChargeForm.errors.rate_per_day }}</div>
          </div>

          <div class="mt-3 overflow-x-auto" v-if="bedCharges.length">
            <table class="min-w-full text-xs border-collapse">
              <thead>
                <tr class="bg-gray-100 text-gray-700 dark:bg-slate-800 dark:text-gray-200">
                  <th class="px-2 py-2 border">Start</th>
                  <th class="px-2 py-2 border">End</th>
                  <th class="px-2 py-2 border">Rate/day</th>
                  <th class="px-2 py-2 border">Bed</th>
                  <th class="px-2 py-2 border">Notes</th>
                  <th class="px-2 py-2 border">Action</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="c in bedCharges" :key="c.id">
                  <td class="px-2 py-2 border">{{ formatDateTime(c.started_at) }}</td>
                  <td class="px-2 py-2 border">{{ c.ended_at ? formatDateTime(c.ended_at) : '' }}</td>
                  <td class="px-2 py-2 border">{{ c.rate_per_day }}</td>
                  <td class="px-2 py-2 border">{{ c.bed?.name ?? '' }}</td>
                  <td class="px-2 py-2 border">{{ c.notes ?? '' }}</td>
                  <td class="px-2 py-2 border">
                    <button type="button" @click="deleteBedCharge(c.id)" class="px-2 py-1 text-[11px] bg-red-600 text-white rounded">
                      Delete
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="mt-2 text-xs text-gray-600 dark:text-gray-300">No bed charges.</div>
        </div>

        <!-- Doctor Visit / Consultation Fee -->
        <div class="border border-gray-200 dark:border-gray-700 rounded p-3">
          <div class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-2">Consultation / Doctor Visit Fee</div>

          <form @submit.prevent="submitDoctorVisitCharge" class="grid grid-cols-1 md:grid-cols-6 gap-2 items-end">
            <input v-model="doctorVisitForm.visited_at" type="datetime-local" class="p-2 text-xs border rounded" placeholder="Visited at" />
            <input v-model="doctorVisitForm.fee_per_visit" type="number" step="0.01" class="p-2 text-xs border rounded" placeholder="Fee/visit" />
            <input v-model="doctorVisitForm.visit_count" type="number" step="1" class="p-2 text-xs border rounded" placeholder="Visit count" />
            <input v-model="doctorVisitForm.doctor_name" type="text" class="p-2 text-xs border rounded" placeholder="Doctor name (optional)" />
            <input v-model="doctorVisitForm.notes" type="text" class="p-2 text-xs border rounded" placeholder="Notes" />
            <button type="submit" :disabled="doctorVisitForm.processing" class="px-3 py-2 text-xs bg-blue-600 text-white rounded">
              Add
            </button>
          </form>

          <div v-if="doctorVisitForm.errors.visited_at || doctorVisitForm.errors.fee_per_visit" class="mt-2 text-xs text-red-600">
            <div v-if="doctorVisitForm.errors.visited_at">{{ doctorVisitForm.errors.visited_at }}</div>
            <div v-if="doctorVisitForm.errors.fee_per_visit">{{ doctorVisitForm.errors.fee_per_visit }}</div>
          </div>

          <div class="mt-3 overflow-x-auto" v-if="doctorVisitCharges.length">
            <table class="min-w-full text-xs border-collapse">
              <thead>
                <tr class="bg-gray-100 text-gray-700 dark:bg-slate-800 dark:text-gray-200">
                  <th class="px-2 py-2 border">Visited</th>
                  <th class="px-2 py-2 border">Doctor</th>
                  <th class="px-2 py-2 border">Fee</th>
                  <th class="px-2 py-2 border">Count</th>
                  <th class="px-2 py-2 border">Total</th>
                  <th class="px-2 py-2 border">Notes</th>
                  <th class="px-2 py-2 border">Action</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="c in doctorVisitCharges" :key="c.id">
                  <td class="px-2 py-2 border">{{ formatDateTime(c.visited_at) }}</td>
                  <td class="px-2 py-2 border">{{ c.doctor_name ?? c.doctor?.name ?? '' }}</td>
                  <td class="px-2 py-2 border">{{ c.fee_per_visit }}</td>
                  <td class="px-2 py-2 border">{{ c.visit_count }}</td>
                  <td class="px-2 py-2 border">{{ c.total_amount }}</td>
                  <td class="px-2 py-2 border">{{ c.notes ?? '' }}</td>
                  <td class="px-2 py-2 border">
                    <button type="button" @click="deleteDoctorVisitCharge(c.id)" class="px-2 py-1 text-[11px] bg-red-600 text-white rounded">
                      Delete
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="mt-2 text-xs text-gray-600 dark:text-gray-300">No doctor visit charges.</div>
        </div>

        <!-- OT / Operation / Professional Fee -->
        <div class="border border-gray-200 dark:border-gray-700 rounded p-3">
          <div class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-2">OT / Operation / Professional Fees</div>

          <form @submit.prevent="submitOtCharge" class="grid grid-cols-1 md:grid-cols-6 gap-2 items-end">
            <input v-model="otForm.performed_at" type="datetime-local" class="p-2 text-xs border rounded"
              placeholder="Performed at" />
            <input v-model="otForm.charge_name" type="text" class="p-2 text-xs border rounded"
              placeholder="Charge name" />
            <input v-model="otForm.procedure_name" type="text" class="p-2 text-xs border rounded"
              placeholder="Procedure" />
            <input v-model="otForm.unit_price" type="number" step="0.01" class="p-2 text-xs border rounded"
              placeholder="Unit price" />
            <input v-model="otForm.quantity" type="number" step="1" class="p-2 text-xs border rounded"
              placeholder="Qty" />
            <button type="submit" :disabled="otForm.processing" class="px-3 py-2 text-xs bg-blue-600 text-white rounded">
              Add
            </button>
          </form>

          <div v-if="otForm.errors.charge_name || otForm.errors.performed_at" class="mt-2 text-xs text-red-600">
            <div v-if="otForm.errors.performed_at">{{ otForm.errors.performed_at }}</div>
            <div v-if="otForm.errors.charge_name">{{ otForm.errors.charge_name }}</div>
          </div>

          <div class="mt-3 overflow-x-auto" v-if="otCharges.length">
            <table class="min-w-full text-xs border-collapse">
              <thead>
                <tr class="bg-gray-100 text-gray-700 dark:bg-slate-800 dark:text-gray-200">
                  <th class="px-2 py-2 border">Performed</th>
                  <th class="px-2 py-2 border">Charge</th>
                                    <th class="px-2 py-2 border">Procedure</th> 
                  <th class="px-2 py-2 border">Unit</th> 
                  <th class="px-2 py-2 border">Qty</th> 
                  <th class="px-2 py-2 border">Total</th> 
                  <th class="px-2 py-2 border">Action</th> 
                </tr> 
              </thead> 
              <tbody> 
                <tr v-for="c in otCharges" :key="c.id"> 
                  <td class="px-2 py-2 border">{{ formatDateTime(c.performed_at) }}</td> 
                  <td class="px-2 py-2 border">{{ c.charge_name ?? '' }}</td> 
                  <td class="px-2 py-2 border">{{ c.procedure_name ?? '' }}</td> 
                  <td class="px-2 py-2 border">{{ c.unit_price }}</td> 
                  <td class="px-2 py-2 border">{{ c.quantity }}</td> 
                  <td class="px-2 py-2 border">{{ c.total_amount }}</td> 
                  <td class="px-2 py-2 border"> 
                    <button type="button" @click="deleteOtCharge(c.id)" class="px-2 py-1 text-[11px] bg-red-600 text-white rounded"> 
                      Delete 
                    </button> 
                  </td> 
                </tr> 
              </tbody> 
            </table> 
          </div> 
          <div v-else class="mt-2 text-xs text-gray-600 dark:text-gray-300">No OT charges.</div> 
        </div> 
      </div> 
      <!-- Payments --> 

      <div v-else-if="activeTab === 'payments'" class="border border-gray-200 dark:border-gray-700 rounded p-3">
        <div class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-2">Payments</div>
        <form @submit.prevent="submitPayment" class="grid grid-cols-1 md:grid-cols-6 gap-2 items-end mb-3">
          <input v-model="paymentForm.amount" type="number" step="0.01" class="p-2 text-xs border rounded" placeholder="Amount" />
          <input v-model="paymentForm.payment_method" type="text" class="p-2 text-xs border rounded" placeholder="Method" />
          <input v-model="paymentForm.transaction_id" type="text" class="p-2 text-xs border rounded" placeholder="Transaction ID" />
          <input v-model="paymentForm.notes" type="text" class="p-2 text-xs border rounded" placeholder="Notes" />
          <div></div>
          <button type="submit" :disabled="paymentForm.processing" class="px-3 py-2 text-xs bg-blue-600 text-white rounded">Add Payment</button>
        </form>

        <div v-if="(props.payments ?? []).length" class="overflow-x-auto">
          <table class="min-w-full text-xs border-collapse">
            <thead>
              <tr class="bg-gray-100 text-gray-700 dark:bg-slate-800 dark:text-gray-200">
                <th class="px-2 py-2 border">Date</th>
                <th class="px-2 py-2 border">Amount</th>
                <th class="px-2 py-2 border">Method</th>
                <th class="px-2 py-2 border">Transaction ID</th>
                <th class="px-2 py-2 border">Payment Status</th>
                <th class="px-2 py-2 border">Notes</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="payment in props.payments" :key="payment.id">
                <td class="px-2 py-2 border">
                  {{ formatDateTime(payment.created_at) }}
                </td>
                <td class="px-2 py-2 border">{{ payment.amount ?? '0.00' }}</td>
                <td class="px-2 py-2 border">{{ payment.payment_method ?? 'N/A' }}</td>
                <td class="px-2 py-2 border">{{ payment.transaction_id ?? '' }}</td>
                <td class="px-2 py-2 border">{{ payment.payment_status ?? '' }}</td>
                <td class="px-2 py-2 border">{{ payment.notes ?? '' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="text-xs text-gray-600 dark:text-gray-300">No payments found.</div>
      </div>

      <!-- Live Consultation -->
      <div v-else-if="activeTab === 'live_consultation'" class="border border-gray-200 dark:border-gray-700 rounded p-3">
        <form @submit.prevent="submitLiveConsultation" class="grid grid-cols-1 md:grid-cols-3 gap-2 items-end">
          <input v-model="liveForm.live_consultation" type="text" class="p-2 text-xs border rounded" placeholder="Live consultation details or URL" />
          <div></div>
          <button type="submit" :disabled="liveForm.processing" class="px-3 py-1.5 text-xs bg-blue-600 text-white rounded">Update</button>
        </form>
        <div class="mt-3 text-xs text-gray-600 dark:text-gray-300">Current: {{ props.ipdpatient?.live_consultation ?? 'Not set' }}</div>
      </div>

      <!-- Bed History -->
      <div v-else-if="activeTab === 'bed_history'" class="border border-gray-200 dark:border-gray-700 rounded p-3">
        <form @submit.prevent="submitNote(bedHistoryForm)" class="mb-3">
          <textarea v-model="bedHistoryForm.content" class="w-full p-2 text-xs border rounded" rows="3" placeholder="Add bed history entry"></textarea>
          <div class="mt-2"><button type="submit" :disabled="bedHistoryForm.processing" class="px-3 py-1.5 text-xs bg-blue-600 text-white rounded">Add Entry</button></div>
        </form>

        <div v-if="bedHistoryList.length" class="space-y-2 text-xs">
          <div v-for="b in bedHistoryList" :key="b.id" class="p-2 border rounded">
            <div class="text-[12px] text-gray-700 dark:text-gray-200">{{ b.content }}</div>
            <div class="text-[11px] text-gray-500">{{ formatDateTime(b.created_at) }}</div>
          </div>
        </div>
        <div v-else class="text-xs text-gray-600 dark:text-gray-300">No bed history entries yet.</div>
      </div>
    </div>
  </BackendLayout>
</template>
