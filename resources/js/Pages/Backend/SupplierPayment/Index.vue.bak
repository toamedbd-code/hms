<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
  payments: {
    type: Object,
    default: () => ({}),
  },
  suppliers: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
});

const rows = props.payments?.data ?? [];

const filterForm = ref({
  supplier_id: props.filters?.supplier_id ?? '',
  status: props.filters?.status ?? '',
  from_date: props.filters?.from_date ?? '',
  to_date: props.filters?.to_date ?? '',
  search: props.filters?.search ?? '',
  numOfData: props.filters?.numOfData ?? 10,
});

const applyFilter = () => {
  router.get(route('backend.supplierpayment.index'), filterForm.value, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const clearFilter = () => {
  filterForm.value = {
    supplier_id: '',
    status: '',
    from_date: '',
    to_date: '',
    search: '',
    numOfData: 10,
  };
  applyFilter();
};

const showPartialModal = ref(false);
const isSubmittingPartial = ref(false);
const partialForm = ref({
  paymentId: null,
  supplierName: '',
  paymentDate: '',
  dueAmount: 0,
  amount: '',
});

const closePartialModal = (force = false) => {
  if (isSubmittingPartial.value && !force) return;

  showPartialModal.value = false;
  partialForm.value = {
    paymentId: null,
    supplierName: '',
    paymentDate: '',
    dueAmount: 0,
    amount: '',
  };
};

const addPartial = (payment) => {
  const maxDue = Number(payment?.due_amount ?? 0);
  if (maxDue <= 0) return;

  partialForm.value = {
    paymentId: payment.id,
    supplierName: payment?.supplier?.name ?? 'N/A',
    paymentDate: payment?.payment_date ?? '-',
    dueAmount: maxDue,
    amount: maxDue,
  };
  showPartialModal.value = true;
};

const submitPartial = () => {
  const paymentId = partialForm.value.paymentId;
  const maxDue = Number(partialForm.value.dueAmount ?? 0);
  const amount = Number(partialForm.value.amount ?? 0);

  if (!paymentId) return;

  if (Number.isNaN(amount) || amount <= 0 || amount > maxDue) {
    window.alert('Invalid amount.');
    return;
  }

  isSubmittingPartial.value = true;
  router.post(
    route('backend.supplierpayment.partial', paymentId),
    { amount },
    {
      preserveScroll: true,
      onFinish: () => {
        isSubmittingPartial.value = false;
      },
      onSuccess: () => {
        closePartialModal(true);
      },
    },
  );
};

const destroyPayment = (paymentId) => {
  if (!window.confirm('Are you sure you want to delete this payment?')) return;
  router.delete(route('backend.supplierpayment.destroy', paymentId), { preserveScroll: true });
};

const money = (value) => Number(value ?? 0).toFixed(2);

const purchaseNumberFromNotes = (notes) => {
  const text = String(notes ?? '').trim();
  if (!text) return null;

  const match = text.match(/Initial\s+payment\s+from\s+purchase\s+([A-Za-z0-9-]+)/i);
  return match?.[1] ?? null;
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-2 p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">Supplier Payment List</h1>
        <div class="flex items-center gap-2">
          <Link :href="route('backend.supplierpayment.report.stock-due')" class="px-3 py-2 text-sm text-white bg-amber-600 rounded hover:bg-amber-700">
            Stock Due Report
          </Link>
          <Link :href="route('backend.supplierpayment.create')" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded hover:bg-indigo-700">
            Add Supplier Payment
          </Link>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-2 p-3 mt-3 border rounded md:grid-cols-6">
        <input
          v-model="filterForm.search"
          type="text"
          placeholder="Search supplier/phone"
          class="p-2 text-sm border border-gray-300 rounded"
          @keyup.enter="applyFilter"
        />
        <select v-model="filterForm.supplier_id" class="p-2 text-sm border border-gray-300 rounded" @change="applyFilter">
          <option value="">All Suppliers</option>
          <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
            {{ supplier.name }}
          </option>
        </select>
        <select v-model="filterForm.status" class="p-2 text-sm border border-gray-300 rounded" @change="applyFilter">
          <option value="">All Status</option>
          <option value="paid">Paid</option>
          <option value="pending">Pending</option>
        </select>
        <input v-model="filterForm.from_date" type="date" class="p-2 text-sm border border-gray-300 rounded" @change="applyFilter" />
        <input v-model="filterForm.to_date" type="date" class="p-2 text-sm border border-gray-300 rounded" @change="applyFilter" />
        <div class="flex items-center gap-2">
          <button type="button" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded hover:bg-indigo-700" @click="applyFilter">
            Search
          </button>
          <button type="button" class="px-3 py-2 text-sm text-gray-800 bg-gray-200 rounded hover:bg-gray-300" @click="clearFilter">
            Clear
          </button>
        </div>
      </div>

      <div class="w-full my-4 overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700 border border-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 border">Date</th>
              <th class="px-3 py-2 border">Supplier</th>
              <th class="px-3 py-2 border">Total</th>
              <th class="px-3 py-2 border">Paid</th>
              <th class="px-3 py-2 border">Due</th>
              <th class="px-3 py-2 border">Type</th>
              <th class="px-3 py-2 border">Status</th>
              <th class="px-3 py-2 border">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="payment in rows" :key="payment.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 border">{{ payment.payment_date }}</td>
              <td class="px-3 py-2 border">
                <div>{{ payment.supplier?.name ?? 'N/A' }}</div>
                <div v-if="purchaseNumberFromNotes(payment.notes)" class="mt-1">
                  <span class="inline-flex items-center rounded bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700 border border-blue-200">
                    Source: Purchase {{ purchaseNumberFromNotes(payment.notes) }}
                  </span>
                </div>
              </td>
              <td class="px-3 py-2 border">{{ money(payment.total_amount) }}</td>
              <td class="px-3 py-2 border">{{ money(payment.paid_amount) }}</td>
              <td class="px-3 py-2 border">{{ money(payment.due_amount) }}</td>
              <td class="px-3 py-2 border">{{ payment.payment_type }}</td>
              <td class="px-3 py-2 border">
                <span
                  class="px-2 py-1 text-xs rounded"
                  :class="payment.status === 'paid' ? 'text-emerald-700 bg-emerald-100' : 'text-amber-700 bg-amber-100'"
                >
                  {{ payment.status }}
                </span>
              </td>
              <td class="px-3 py-2 border">
                <div class="flex flex-wrap gap-2">
                  <Link :href="route('backend.supplierpayment.edit', payment.id)" class="px-2 py-1 text-xs text-black bg-yellow-400 rounded hover:bg-yellow-500">
                    Edit
                  </Link>
                  <button
                    type="button"
                    class="px-2 py-1 text-xs text-white bg-blue-600 rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="Number(payment.due_amount) <= 0"
                    @click="addPartial(payment)"
                  >
                    Add Partial
                  </button>
                  <button type="button" class="px-2 py-1 text-xs text-white bg-red-600 rounded hover:bg-red-700" @click="destroyPayment(payment.id)">
                    Delete
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="8" class="px-3 py-6 text-center text-gray-500 border">No supplier payments found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination />
    </div>

    <div v-if="showPartialModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b px-5 py-3">
          <h3 class="text-base font-semibold text-gray-800">Add Partial Payment</h3>
          <button
            type="button"
            class="text-gray-500 hover:text-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="isSubmittingPartial"
            @click="closePartialModal"
          >
            ✕
          </button>
        </div>

        <div class="px-5 py-4">
          <table class="w-full text-sm text-gray-700">
            <tr>
              <td class="py-1 font-semibold">Supplier</td>
              <td class="py-1">{{ partialForm.supplierName }}</td>
            </tr>
            <tr>
              <td class="py-1 font-semibold">Date</td>
              <td class="py-1">{{ partialForm.paymentDate }}</td>
            </tr>
            <tr>
              <td class="py-1 font-semibold">Due Amount</td>
              <td class="py-1 text-red-600 font-semibold">Tk {{ money(partialForm.dueAmount) }}</td>
            </tr>
          </table>

          <div class="mt-4">
            <label class="mb-1 block text-sm font-semibold text-gray-700">Pay Amount</label>
            <input
              v-model="partialForm.amount"
              type="number"
              step="0.01"
              min="0.01"
              :max="partialForm.dueAmount"
              :disabled="isSubmittingPartial"
              @keydown.enter.prevent="submitPartial"
              class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none disabled:bg-gray-100 disabled:cursor-not-allowed"
            >
            <p class="mt-1 text-xs text-gray-500">Max: Tk {{ money(partialForm.dueAmount) }}</p>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 border-t px-5 py-3">
          <button
            type="button"
            class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 disabled:opacity-60 disabled:cursor-not-allowed"
            :disabled="isSubmittingPartial"
            @click="closePartialModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60 disabled:cursor-not-allowed"
            :disabled="isSubmittingPartial"
            @click="submitPartial"
          >
            {{ isSubmittingPartial ? 'Saving...' : 'Add Partial' }}
          </button>
        </div>
      </div>
    </div>
  </BackendLayout>
</template>
