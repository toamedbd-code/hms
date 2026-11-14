<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'Department Inventory' },
  stocks: { type: Object, default: () => ({ data: [] }) },
  lots: { type: Object, default: () => ({ data: [] }) },
  movements: { type: Array, default: () => [] },
  departments: { type: Array, default: () => [] },
  timezone: { type: String, default: 'Asia/Dhaka' },
  filters: { type: Object, default: () => ({}) },
});

const departmentId = ref(props.filters?.department_id ?? '');
const selectedStock = ref(null);
const usageForm = useForm({ quantity: 1, reason: '', reference_no: '' });

function applyFilter() {
  router.get(route('backend.stock.department-reagents'), {
    department_id: departmentId.value || undefined,
  }, { preserveState: true, preserveScroll: true, replace: true });
}

function quantity(value) {
  return Number(value ?? 0).toFixed(2);
}

function formatDateTime(value) {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);

  return date.toLocaleString('en-GB', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: true,
    timeZone: props.timezone,
  });
}

function expiryStatus(value) {
  if (!value) return 'No expiry';
  const expiry = String(value).slice(0, 10);
  const today = new Date().toISOString().slice(0, 10);
  if (expiry < today) return 'Expired';
  const daysRemaining = Math.ceil((new Date(`${expiry}T00:00:00Z`) - new Date(`${today}T00:00:00Z`)) / 86400000);
  return daysRemaining <= 30 ? 'Expiring soon' : 'Valid';
}

function openUsageForm(stock) {
  selectedStock.value = stock;
  usageForm.reset();
  usageForm.quantity = 1;
  usageForm.clearErrors();
}

function closeUsageForm() {
  if (usageForm.processing) return;
  selectedStock.value = null;
}

function recordUsage() {
  if (!selectedStock.value) return;

  usageForm.post(route('backend.stock.department-reagents.usage', selectedStock.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      selectedStock.value = null;
      usageForm.reset();
    },
  });
}
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">{{ pageTitle }}</h1>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link :href="route('backend.stock.index')" class="px-3 py-2 text-sm border rounded">Store Inventory</Link>
          <Link :href="route('backend.stock.requisition.create', { department_id: departmentId || undefined })" class="px-3 py-2 text-sm text-white bg-teal-600 rounded">Create Department Indent</Link>
          <Link :href="route('backend.stock.requisitions')" class="px-3 py-2 text-sm border rounded">Indent History</Link>
        </div>
      </div>

      <div class="flex flex-wrap items-end gap-3 mt-4">
        <label class="text-sm text-slate-700">
          Department
          <select v-model="departmentId" class="block min-w-56 mt-1 p-2 border rounded" @change="applyFilter">
            <option value="">All departments</option>
            <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
          </select>
        </label>
      </div>

      <section class="mt-5">
        <h2 class="mb-2 text-base font-semibold text-slate-800">Current Department Balances</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-sm border border-slate-200">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-3 py-2 text-left border">Code</th>
                <th class="px-3 py-2 text-left border">Department</th>
                <th class="px-3 py-2 text-left border">Item</th>
                <th class="px-3 py-2 text-right border">Transferred</th>
                <th class="px-3 py-2 text-right border">Used</th>
                <th class="px-3 py-2 text-right border">Available</th>
                <th class="px-3 py-2 text-right border">Reorder Level</th>
                <th class="px-3 py-2 text-center border">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="stock in stocks.data" :key="stock.id" class="hover:bg-slate-50">
                <td class="px-3 py-2 border">{{ stock.store_item?.item_code || '-' }}</td>
                <td class="px-3 py-2 border">{{ stock.department?.name || '-' }}</td>
                <td class="px-3 py-2 border">{{ stock.store_item?.item_name || '-' }}</td>
                <td class="px-3 py-2 text-right border">{{ quantity(stock.issued_quantity) }} {{ stock.store_item?.unit || '' }}</td>
                <td class="px-3 py-2 text-right border">{{ quantity(Number(stock.consumed_quantity ?? 0) + Number(stock.department_use_quantity ?? 0)) }} {{ stock.store_item?.unit || '' }}</td>
                <td class="px-3 py-2 text-right border">{{ quantity(stock.available_stock) }} {{ stock.store_item?.unit || '' }}</td>
                <td class="px-3 py-2 text-right border">{{ quantity(stock.reorder_level) }}</td>
                <td class="px-3 py-2 text-center border">
                  <button
                    v-if="stock.store_item?.stock_behavior !== 'reusable'"
                    type="button"
                    class="px-2 py-1 text-xs font-semibold text-white bg-rose-600 rounded hover:bg-rose-700"
                    @click="openUsageForm(stock)"
                    title="Deduct supplies used by the department and record the reason in the movement ledger"
                  >Record Use</button>
                  <span v-else class="text-xs text-slate-500">Reusable</span>
                </td>
              </tr>
              <tr v-if="stocks.data.length === 0">
                <td colspan="8" class="px-3 py-6 text-center text-slate-500">No department inventory balances found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <nav v-if="stocks.links?.length" class="flex flex-wrap justify-end gap-2 mt-3">
          <Link v-for="(link, index) in stocks.links" :key="`${index}-${link.label}`" :href="link.url || ''" preserve-scroll class="px-3 py-1 text-sm border rounded" :class="[link.active ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-300', !link.url ? 'pointer-events-none opacity-50' : '']">
            <span v-html="link.label"></span>
          </Link>
        </nav>
      </section>

      <section class="mt-6">
        <h2 class="mb-2 text-base font-semibold text-slate-800">Batch, Expiry & Asset Register</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-sm border border-slate-200">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-3 py-2 text-left border">Location</th>
                <th class="px-3 py-2 text-left border">Item</th>
                <th class="px-3 py-2 text-left border">Batch / Lot</th>
                <th class="px-3 py-2 text-left border">Expiry</th>
                <th class="px-3 py-2 text-left border">Serial / Asset Tag</th>
                <th class="px-3 py-2 text-right border">Qty</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="lot in lots.data" :key="lot.id">
                <td class="px-3 py-2 border">{{ lot.department?.name || 'Central Store' }}</td>
                <td class="px-3 py-2 border">{{ lot.store_item?.item_name || '-' }}</td>
                <td class="px-3 py-2 border">{{ lot.batch_no || 'Unlabeled lot' }}</td>
                <td class="px-3 py-2 border" :class="expiryStatus(lot.expiry_date) === 'Expired' ? 'font-semibold text-red-700' : expiryStatus(lot.expiry_date) === 'Expiring soon' ? 'font-semibold text-amber-700' : ''">
                  {{ lot.expiry_date ? String(lot.expiry_date).slice(0, 10) : 'N/A' }} · {{ expiryStatus(lot.expiry_date) }}
                </td>
                <td class="px-3 py-2 border">{{ lot.serial_number || '-' }} / {{ lot.asset_tag || '-' }}</td>
                <td class="px-3 py-2 text-right border">{{ quantity(lot.quantity) }} {{ lot.store_item?.unit || '' }}</td>
              </tr>
              <tr v-if="lots.data.length === 0">
                <td colspan="6" class="px-3 py-6 text-center text-slate-500">No batch or asset records found.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <nav v-if="lots.links?.length" class="flex flex-wrap justify-end gap-2 mt-3">
          <Link v-for="(link, index) in lots.links" :key="`lot-${index}-${link.label}`" :href="link.url || ''" preserve-scroll class="px-3 py-1 text-sm border rounded" :class="[link.active ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-300', !link.url ? 'pointer-events-none opacity-50' : '']">
            <span v-html="link.label"></span>
          </Link>
        </nav>
      </section>

      <section class="mt-6">
        <h2 class="mb-2 text-base font-semibold text-slate-800">Recent Movement Ledger</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-sm border border-slate-200">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-3 py-2 text-left border">Date</th>
                <th class="px-3 py-2 text-left border">Department</th>
                  <th class="px-3 py-2 text-left border">Item</th>
                <th class="px-3 py-2 text-left border">Batch / Asset</th>
                <th class="px-3 py-2 text-left border">Movement</th>
                <th class="px-3 py-2 text-right border">Quantity</th>
                <th class="px-3 py-2 text-left border">Reference</th>
                <th class="px-3 py-2 text-left border">Recorded By</th>
                <th class="px-3 py-2 text-left border">Reason</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="movement in movements" :key="movement.id">
                <td class="px-3 py-2 border">{{ formatDateTime(movement.created_at || movement.movement_date) }}</td>
                <td class="px-3 py-2 border">{{ movement.department?.name || '-' }}</td>
                  <td class="px-3 py-2 border">{{ movement.store_item?.item_name || '-' }}</td>
                <td class="px-3 py-2 border">
                  {{ movement.inventory_lot?.batch_no || '-' }}
                  <div v-if="movement.inventory_lot?.expiry_date" class="text-xs text-slate-500">Exp: {{ String(movement.inventory_lot.expiry_date).slice(0, 10) }}</div>
                  <div v-if="movement.inventory_lot?.serial_number || movement.inventory_lot?.asset_tag" class="text-xs text-slate-500">{{ movement.inventory_lot?.serial_number || '' }} {{ movement.inventory_lot?.asset_tag || '' }}</div>
                </td>
                <td class="px-3 py-2 border">{{ movement.movement_type === 'transfer_in' ? 'Indent issue' : movement.movement_type === 'consumption' ? 'Test billing' : movement.movement_type === 'department_use' ? 'Department use' : movement.movement_type }}</td>
                <td class="px-3 py-2 text-right border">{{ quantity(movement.quantity) }}</td>
                <td class="px-3 py-2 border">{{ movement.reference_no || '-' }}</td>
                <td class="px-3 py-2 border">{{ movement.created_by_admin?.name || '-' }}</td>
                <td class="px-3 py-2 border">{{ movement.reason || '-' }}</td>
              </tr>
              <tr v-if="movements.length === 0">
                  <td colspan="9" class="px-3 py-6 text-center text-slate-500">No department inventory movements recorded.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <div v-if="selectedStock" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/40 p-4" @click.self="closeUsageForm">
      <form class="w-full max-w-lg rounded-lg bg-white p-5 shadow-xl" @submit.prevent="recordUsage">
        <h2 class="text-lg font-semibold text-slate-800">Record Department Use</h2>
        <p class="mt-1 text-sm text-slate-600">
          {{ selectedStock.store_item?.item_name }} · Balance: {{ quantity(selectedStock.current_stock) }} {{ selectedStock.store_item?.unit || '' }}
        </p>

        <label class="block mt-4 mb-1 text-sm font-semibold text-slate-700">Quantity used</label>
        <input v-model.number="usageForm.quantity" type="number" min="0.01" :max="selectedStock.current_stock" step="0.01" required class="w-full rounded border p-2 text-sm" />
        <InputError class="mt-1" :message="usageForm.errors.quantity" />

        <label class="block mt-3 mb-1 text-sm font-semibold text-slate-700">Purpose / reason</label>
        <input v-model="usageForm.reason" type="text" maxlength="1000" required class="w-full rounded border p-2 text-sm" placeholder="e.g. Tissue used in outpatient department" />
        <InputError class="mt-1" :message="usageForm.errors.reason" />

        <label class="block mt-3 mb-1 text-sm font-semibold text-slate-700">Reference (optional)</label>
        <input v-model="usageForm.reference_no" type="text" maxlength="100" class="w-full rounded border p-2 text-sm" placeholder="Voucher / note number" />
        <InputError class="mt-1" :message="usageForm.errors.reference_no" />

        <div class="mt-5 flex justify-end gap-2">
          <button type="button" class="rounded border px-4 py-2 text-sm" :disabled="usageForm.processing" @click="closeUsageForm">Cancel</button>
          <button type="submit" class="rounded bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50" :disabled="usageForm.processing">
            {{ usageForm.processing ? 'Saving...' : 'Record Use & Update Stock' }}
          </button>
        </div>
      </form>
    </div>
  </BackendLayout>
</template>