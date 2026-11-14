<script setup>
import { computed, reactive } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'Lab Order Details' },
  order: { type: Object, required: true },
});
const page = usePage();
const resultForms = reactive(Object.fromEntries(props.order.items.map((item) => [
  item.id,
  useForm({ result_text: item.result_text ?? '' }),
])));
const paymentForm = useForm({
  amount: Number(props.order.due_amount ?? 0).toFixed(2),
  payment_date: new Date().toISOString().slice(0, 10),
  payment_method: 'Cash',
  reference_no: '',
  notes: '',
});
const money = (value) => Number(value ?? 0).toFixed(2);
const dateTime = (value) => value ? String(value).slice(0, 16).replace('T', ' ') : '-';
const paymentStatus = computed(() => Number(props.order.due_amount) <= 0.005 ? 'Paid' : Number(props.order.paid_amount) > 0 ? 'Partial' : 'Due');
const collect = (item) => router.post(route('backend.lab.samples.collect', item.id));
const saveResult = (item) => resultForms[item.id].post(route('backend.lab.results.store', item.id));
const verify = (item) => router.post(route('backend.lab.results.verify', item.id));
const pay = () => paymentForm.post(route('backend.lab.payments.store', props.order.id));
const printReport = () => window.print();
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 space-y-4 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-3 p-4 bg-gray-100 rounded print:hidden">
        <div><h1 class="text-xl font-semibold">{{ pageTitle }}</h1><p class="text-sm text-slate-600">{{ order.order_no }} · {{ order.patient_name }} · {{ order.patient_no }}</p></div>
        <div class="flex gap-2"><button class="px-3 py-2 text-sm text-white bg-slate-600 rounded" @click="printReport">Print</button><a :href="route('backend.lab.orders')" class="px-3 py-2 text-sm text-white bg-emerald-600 rounded">Worklist</a></div>
      </div>

      <section class="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
        <div class="p-3 border rounded"><span class="text-slate-500">Patient</span><div class="font-semibold">{{ order.patient_name }}</div></div>
        <div class="p-3 border rounded"><span class="text-slate-500">Phone / Gender</span><div class="font-semibold">{{ order.patient_phone || '-' }} · {{ order.patient_gender || '-' }}</div></div>
        <div class="p-3 border rounded"><span class="text-slate-500">Order status</span><div class="font-semibold">{{ order.status }}</div></div>
        <div class="p-3 border rounded"><span class="text-slate-500">Assigned technician</span><div class="font-semibold">{{ [order.staff_first_name, order.staff_last_name].filter(Boolean).join(' ') || 'Unassigned' }}</div></div>
        <div class="p-3 border rounded"><span class="text-slate-500">Total</span><div class="font-semibold">{{ money(order.total_amount) }}</div></div>
        <div class="p-3 border rounded"><span class="text-slate-500">Paid</span><div class="font-semibold text-emerald-700">{{ money(order.paid_amount) }}</div></div>
        <div class="p-3 border rounded"><span class="text-slate-500">Due</span><div class="font-semibold" :class="order.due_amount > 0.005 ? 'text-rose-700' : 'text-emerald-700'">{{ money(order.due_amount) }}</div></div>
        <div class="p-3 border rounded"><span class="text-slate-500">Payment status</span><div class="font-semibold">{{ paymentStatus }}</div></div>
      </section>

      <section v-for="item in order.items" :key="item.id" class="p-4 border rounded-lg">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div><h2 class="font-semibold text-slate-800">{{ item.test_name }} <span class="text-sm font-normal text-slate-500">{{ item.test_code }}</span></h2><p class="mt-1 text-sm text-slate-600">Specimen: {{ item.specimen || '-' }} · Method: {{ item.method || '-' }} · Reference: {{ item.reference_range || '-' }} {{ item.unit || '' }}</p></div>
          <div class="text-right text-sm"><div>Qty {{ Number(item.quantity).toFixed(2) }} × {{ money(item.price) }}</div><div class="font-semibold">{{ money(Number(item.quantity) * Number(item.price)) }}</div></div>
        </div>
        <div class="grid grid-cols-1 gap-3 mt-3 md:grid-cols-3">
          <div class="p-3 bg-slate-50 rounded"><div class="text-xs text-slate-500">Sample barcode</div><div class="font-mono font-semibold">{{ item.sample_code }}</div><div class="mt-1 text-sm">Status: {{ item.sample_status }} <span v-if="item.collected_at">· {{ dateTime(item.collected_at) }}</span></div><div v-if="item.collected_by" class="text-xs text-slate-500">Collected by {{ [item.collector_first_name, item.collector_last_name].filter(Boolean).join(' ') }}</div></div>
          <div class="p-3 bg-slate-50 rounded"><div class="text-xs text-slate-500">Result workflow</div><div class="font-semibold">{{ item.result_status }}</div><div v-if="item.entered_by" class="mt-1 text-xs text-slate-500">Entered by {{ [item.enterer_first_name, item.enterer_last_name].filter(Boolean).join(' ') }}</div><div v-if="item.verified_at" class="mt-1 text-xs text-slate-500">Verified {{ dateTime(item.verified_at) }} by {{ [item.verifier_first_name, item.verifier_last_name].filter(Boolean).join(' ') }}</div></div>
          <div class="flex items-center justify-end">
            <button v-if="item.sample_status === 'Pending'" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded print:hidden" @click="collect(item)">Collect Sample</button>
          </div>
        </div>
        <div v-if="item.sample_status === 'Collected' && item.result_status !== 'Verified'" class="mt-3 print:hidden">
          <label class="block mb-1 text-sm font-semibold">Result / observations</label>
          <textarea v-model="resultForms[item.id].result_text" rows="4" class="w-full p-2 text-sm border rounded" :disabled="item.result_status === 'Verified'" placeholder="Enter laboratory result and observations"></textarea>
          <InputError :message="resultForms[item.id].errors.result_text" />
          <InputError :message="page.props.errors?.result" />
          <div class="flex flex-wrap justify-end gap-2 mt-2">
            <button class="px-3 py-2 text-sm font-semibold text-white bg-indigo-600 rounded" :disabled="resultForms[item.id].processing" @click="saveResult(item)">Save Result</button>
            <button v-if="item.result_status === 'Entered'" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-700 rounded" @click="verify(item)">Verify & Finalize</button>
          </div>
        </div>
        <div v-if="item.result_text" class="p-3 mt-3 border rounded whitespace-pre-wrap"><div class="mb-1 text-xs font-semibold uppercase text-slate-500">Laboratory Result</div>{{ item.result_text }}</div>
      </section>

      <section v-if="Number(order.due_amount) > 0.005" class="p-4 border rounded-lg print:hidden">
        <h2 class="mb-3 font-semibold">Record Payment · Due {{ money(order.due_amount) }}</h2>
        <form class="grid grid-cols-1 gap-3 md:grid-cols-5" @submit.prevent="pay">
          <div><label class="block mb-1 text-sm">Amount *</label><input v-model.number="paymentForm.amount" type="number" min="0.01" :max="order.due_amount" step="0.01" required class="w-full p-2 border rounded" /><InputError :message="paymentForm.errors.amount" /></div>
          <div><label class="block mb-1 text-sm">Date *</label><input v-model="paymentForm.payment_date" type="date" required class="w-full p-2 border rounded" /><InputError :message="paymentForm.errors.payment_date" /></div>
          <div><label class="block mb-1 text-sm">Method *</label><select v-model="paymentForm.payment_method" class="w-full p-2 border rounded"><option>Cash</option><option>Card</option><option>Bank</option><option>Mobile Banking</option><option>Other</option></select></div>
          <div><label class="block mb-1 text-sm">Reference</label><input v-model="paymentForm.reference_no" class="w-full p-2 border rounded" /><InputError :message="paymentForm.errors.reference_no" /></div>
          <div class="flex items-end"><button :disabled="paymentForm.processing" class="w-full px-3 py-2 font-semibold text-white bg-emerald-600 rounded disabled:opacity-50">{{ paymentForm.processing ? 'Saving...' : 'Record Payment' }}</button></div>
          <div class="md:col-span-5"><label class="block mb-1 text-sm">Notes</label><input v-model="paymentForm.notes" class="w-full p-2 border rounded" /></div>
        </form>
      </section>

      <section>
        <h2 class="mb-2 font-semibold">Payment History</h2>
        <div class="overflow-x-auto"><table class="w-full text-sm border"><thead class="bg-slate-50"><tr><th class="p-2 border">Date</th><th class="p-2 border">Method</th><th class="p-2 border">Reference</th><th class="p-2 text-right border">Amount</th></tr></thead>
          <tbody><tr v-for="payment in order.payments" :key="payment.id"><td class="p-2 text-center border">{{ payment.payment_date }}</td><td class="p-2 text-center border">{{ payment.payment_method }}</td><td class="p-2 border">{{ payment.reference_no || '-' }}</td><td class="p-2 text-right border">{{ money(payment.amount) }}</td></tr><tr v-if="order.payments.length === 0"><td colspan="4" class="p-4 text-center border text-slate-500">No payments recorded.</td></tr></tbody>
        </table></div>
      </section>
      <p v-if="order.notes" class="text-sm text-slate-600">Notes: {{ order.notes }}</p>
    </div>
  </BackendLayout>
</template>

<style scoped>
@media print {
  :global(body *) { visibility: hidden !important; }
  .w-full, .w-full * { visibility: visible !important; }
  .w-full { position: absolute !important; inset: 0 auto auto 0 !important; width: 100% !important; margin: 0 !important; box-shadow: none !important; }
}
</style>
