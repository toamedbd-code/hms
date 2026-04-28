<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const APP_TIMEZONE = 'Asia/Dhaka';

const props = defineProps({
  returns: {
    type: Object,
    default: () => ({ data: [] }),
  },
  returnType: {
    type: String,
    default: 'supplier',
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
});

const rows = props.returns?.data ?? [];

const filterForm = ref({
  search: props.filters?.search ?? '',
  numOfData: props.filters?.numOfData ?? props.returns?.per_page ?? 10,
  return_type: props.returnType,
});

const applyFilter = () => {
  router.get(route('backend.productreturn.index'), filterForm.value, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const isSupplierMode = props.returnType === 'supplier';

const clearSearch = () => {
  filterForm.value.search = '';
  applyFilter();
};

const deleteReturn = (id) => {
  if (!window.confirm('Are you sure you want to delete this return?')) return;
  router.delete(route('backend.productreturn.destroy', id), { preserveScroll: true });
};

const formatMoney = (value) => Number(value ?? 0).toFixed(2);

const getPaidAmount = (item) => Number(item?.paid_amount ?? 0);
const getTotalAmount = (item) => Number(item?.total_amount ?? 0);
const getDueAmount = (item) => Math.max(0, getTotalAmount(item) - getPaidAmount(item));

const showPayModal = ref(false);
const isSubmittingPay = ref(false);
const payForm = ref({
  rowId: null,
  returnNo: 'N/A',
  customerName: 'N/A',
  dueAmount: 0,
  amount: '',
});

const openPayModal = (item) => {
  const dueAmount = getDueAmount(item);
  if (dueAmount <= 0) {
    window.alert('This return is already fully paid.');
    return;
  }

  payForm.value.rowId = item?.id;
  payForm.value.returnNo = item?.return_number || 'N/A';
  payForm.value.customerName = item?.customer_name || item?.supplier?.name || 'N/A';
  payForm.value.dueAmount = dueAmount;
  payForm.value.amount = '';
  showPayModal.value = true;
};

const closePayModal = (force = false) => {
  if (isSubmittingPay.value && !force) return;
  showPayModal.value = false;
  payForm.value.amount = '';
};

const submitPay = () => {
  if (isSubmittingPay.value) return;

  const amount = Number(payForm.value.amount || 0);
  const dueAmount = Number(payForm.value.dueAmount || 0);

  if (!Number.isFinite(amount) || amount <= 0 || amount > dueAmount) {
    window.alert('Invalid amount.');
    return;
  }

  isSubmittingPay.value = true;
  closePayModal(true);

  router.post(route('backend.productreturn.pay', payForm.value.rowId), {
    pay_amount: amount,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      // Force full reload right after payment to minimize accidental double-submit windows.
      window.location.reload();
    },
    onFinish: () => {
      isSubmittingPay.value = false;
    },
  });
};

const formatReturnDateTime = (returnDate, createdAt) => {
  const hasTimeInReturnDate = typeof returnDate === 'string'
    && (returnDate.includes('T') || /\d{2}:\d{2}/.test(returnDate));

  const sourceValue = hasTimeInReturnDate ? returnDate : (createdAt || returnDate);
  if (!sourceValue) return '-';

  const date = new Date(sourceValue);
  if (Number.isNaN(date.getTime())) return sourceValue;

  return date.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
    timeZone: APP_TIMEZONE,
  });
};

const getReturnDateTime = (item) => {
  if (item?.return_datetime_local) return item.return_datetime_local;
  return formatReturnDateTime(item?.return_date, item?.created_at);
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-2 p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ isSupplierMode ? 'Supplier Product Return List' : 'Customer Product Return List' }}</h1>
        <div class="flex items-center gap-2">
          <input
            v-model="filterForm.search"
            type="text"
            :placeholder="isSupplierMode ? 'Search return no, supplier, status' : 'Search return no, customer, status'"
            class="px-3 py-2 text-sm border border-gray-300 rounded w-72 max-w-full"
            @input="applyFilter"
          >
          <button
            type="button"
            class="px-3 py-2 text-sm text-gray-700 bg-gray-200 rounded hover:bg-gray-300"
            @click="clearSearch"
          >
            Clear
          </button>
          <select v-model="filterForm.numOfData" class="px-2 py-2 text-sm border border-gray-300 rounded" @change="applyFilter">
            <option value="10">Show 10</option>
            <option value="20">Show 20</option>
            <option value="30">Show 30</option>
            <option value="50">Show 50</option>
            <option value="100">Show 100</option>
          </select>
          <Link :href="route('backend.productreturn.create', { return_type: props.returnType })" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded hover:bg-indigo-700">
            {{ isSupplierMode ? 'Add Supplier Return' : 'Add Customer Return' }}
          </Link>
        </div>
      </div>

      <div class="w-full my-4 overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700 border border-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 border">Return No</th>
              <th class="px-3 py-2 border">Type</th>
              <th class="px-3 py-2 border">Supplier</th>
              <th class="px-3 py-2 border">Reference</th>
              <th class="px-3 py-2 border">Date & Time</th>
              <th class="px-3 py-2 border">Total</th>
              <th class="px-3 py-2 border">Paid / Due</th>
              <th class="px-3 py-2 border">Status</th>
              <th class="px-3 py-2 border">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in rows" :key="item.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 border">{{ item.return_number }}</td>
              <td class="px-3 py-2 border capitalize">{{ item.return_type }}</td>
              <td class="px-3 py-2 border">{{ item.supplier?.name ?? 'N/A' }}</td>
              <td class="px-3 py-2 border">{{ item.source_bill_no ?? item.customer_name ?? '-' }}</td>
              <td class="px-3 py-2 border">{{ getReturnDateTime(item) }}</td>
              <td class="px-3 py-2 border">{{ formatMoney(item.total_amount) }}</td>
              <td class="px-3 py-2 border">
                <div class="leading-5">
                  <div>Paid: {{ formatMoney(getPaidAmount(item)) }}</div>
                  <div>Due: {{ formatMoney(getDueAmount(item)) }}</div>
                </div>
              </td>
              <td class="px-3 py-2 border">
                <div class="flex flex-wrap gap-1">
                  <span
                    class="px-2 py-1 text-xs rounded capitalize"
                    :class="item.status === 'processed'
                      ? 'text-emerald-700 bg-emerald-100'
                      : item.status === 'approved'
                        ? 'text-blue-700 bg-blue-100'
                        : 'text-amber-700 bg-amber-100'"
                  >
                    {{ item.status }}
                  </span>
                  <span
                    class="px-2 py-1 text-xs rounded capitalize"
                    :class="(item.payment_status ?? 'unpaid') === 'paid'
                      ? 'text-emerald-700 bg-emerald-100'
                      : (item.payment_status ?? 'unpaid') === 'partial'
                        ? 'text-blue-700 bg-blue-100'
                        : 'text-red-700 bg-red-100'"
                  >
                    {{ item.payment_status ?? 'unpaid' }}
                  </span>
                </div>
              </td>
              <td class="px-3 py-2 border">
                <div class="flex flex-wrap gap-2">
                  <Link :href="route('backend.productreturn.show', item.id)" class="px-2 py-1 text-xs text-white bg-teal-600 rounded hover:bg-teal-700">
                    View
                  </Link>
                  <Link :href="route('backend.productreturn.edit', item.id)" class="px-2 py-1 text-xs text-black bg-yellow-400 rounded hover:bg-yellow-500">
                    Edit
                  </Link>
                  <button type="button" class="px-2 py-1 text-xs text-white bg-red-600 rounded hover:bg-red-700" @click="deleteReturn(item.id)">
                    Delete
                  </button>
                  <button
                    v-if="!isSupplierMode && getDueAmount(item) > 0"
                    type="button"
                    class="px-2 py-1 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700"
                    @click="openPayModal(item)"
                  >
                    Pay
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="9" class="px-3 py-6 text-center text-gray-500 border">No returns found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="props.returns?.links?.length" class="grid grid-cols-1 gap-4 pt-2 my-4 md:grid-cols-2 items-center">
        <p class="text-sm text-gray-600 text-center md:text-left">
          Displaying {{ props.returns?.from ?? 0 }} to {{ props.returns?.to ?? 0 }} of {{ props.returns?.total ?? 0 }} items
        </p>

        <nav>
          <ul class="flex items-center justify-center md:justify-end gap-2">
            <li v-for="(link, index) in props.returns.links" :key="`${index}-${link.label}`">
              <Link
                v-if="link.url"
                :href="link.url"
                class="px-3 py-2 text-sm border border-gray-300 rounded hover:bg-gray-100"
                :class="{ 'bg-indigo-600 text-white border-indigo-600 hover:bg-indigo-700': link.active }"
              >
                <span v-html="link.label"></span>
              </Link>
              <span v-else class="px-3 py-2 text-sm text-gray-400 border border-gray-200 rounded" v-html="link.label"></span>
            </li>
          </ul>
        </nav>
      </div>
    </div>

    <div v-if="showPayModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b px-5 py-3">
          <h3 class="text-base font-semibold text-gray-800">Product Return Payment</h3>
          <button
            type="button"
            class="text-gray-500 hover:text-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="isSubmittingPay"
            @click="closePayModal"
          >
            ✕
          </button>
        </div>

        <div class="px-5 py-4">
          <table class="w-full text-sm text-gray-700">
            <tr>
              <td class="py-1 font-semibold">Bill No</td>
              <td class="py-1">{{ payForm.returnNo }}</td>
            </tr>
            <tr>
              <td class="py-1 font-semibold">Patient</td>
              <td class="py-1">{{ payForm.customerName }}</td>
            </tr>
            <tr>
              <td class="py-1 font-semibold">Return Amount</td>
              <td class="py-1 text-red-600 font-semibold">Tk {{ formatMoney(payForm.dueAmount) }}</td>
            </tr>
          </table>

          <div class="mt-4">
            <label class="mb-1 block text-sm font-semibold text-gray-700">Refund Amount</label>
            <input
              v-model="payForm.amount"
              type="number"
              step="0.01"
              min="0.01"
              :max="payForm.dueAmount"
              :disabled="isSubmittingPay"
              @keydown.enter.prevent="submitPay"
              class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none disabled:bg-gray-100 disabled:cursor-not-allowed"
            >
            <p class="mt-1 text-xs text-gray-500">Max: Tk {{ formatMoney(payForm.dueAmount) }}</p>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 border-t px-5 py-3">
          <button
            type="button"
            class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 disabled:opacity-60 disabled:cursor-not-allowed"
            :disabled="isSubmittingPay"
            @click="closePayModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60 disabled:cursor-not-allowed"
            :disabled="isSubmittingPay"
            @click="submitPay"
          >
            {{ isSubmittingPay ? 'Processing...' : 'Process Refund' }}
          </button>
        </div>
      </div>
    </div>
  </BackendLayout>
</template>
