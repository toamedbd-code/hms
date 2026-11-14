<script setup>
import { useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
  order: { type: Object, required: true },
  accounts: { type: Array, default: () => [] },
});
const form = useForm({
  amount: Number(props.order.received_amount) - Number(props.order.paid_amount),
  payment_date: new Date().toISOString().slice(0, 10),
  payment_account_id: '',
  reference_no: '',
  notes: '',
});
const submit = () => form.post(route('backend.lab.purchase-payments.store', props.order.id));
const money = (value) => Number(value ?? 0).toFixed(2);
</script>

<template>
  <BackendLayout>
    <div class="w-full max-w-3xl p-4 mx-auto mt-3 space-y-4 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded"><div><h1 class="text-xl font-semibold">Pay Lab Supplier</h1><p class="mt-1 text-sm text-slate-600">{{ order.order_no }} · {{ order.supplier_name }}</p></div><a :href="route('backend.lab.purchase-payments')" class="px-3 py-2 text-sm text-white bg-emerald-600 rounded">Back</a></div>
      <div class="grid grid-cols-3 gap-3 text-sm">
        <div class="p-3 border rounded"><div class="text-slate-500">Received</div><strong>{{ money(order.received_amount) }}</strong></div>
        <div class="p-3 border rounded"><div class="text-slate-500">Already paid</div><strong>{{ money(order.paid_amount) }}</strong></div>
        <div class="p-3 border rounded"><div class="text-slate-500">Due</div><strong>{{ money(Number(order.received_amount) - Number(order.paid_amount)) }}</strong></div>
      </div>
      <form class="space-y-3" @submit.prevent="submit">
        <div><label class="block mb-1 text-sm">Amount *</label><input v-model.number="form.amount" type="number" min="0.01" :max="Number(order.received_amount) - Number(order.paid_amount)" step="0.01" required class="w-full p-2 border rounded" /><InputError :message="form.errors.amount" /></div>
        <div><label class="block mb-1 text-sm">Pay from *</label><select v-model="form.payment_account_id" required class="w-full p-2 border rounded"><option value="">Select Cash / Bank account</option><option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }} ({{ account.code }})</option></select><InputError :message="form.errors.payment_account_id" /><p v-if="accounts.length === 0" class="mt-1 text-sm text-amber-700">Create an active Cash or Bank account before paying this supplier.</p></div>
        <div><label class="block mb-1 text-sm">Payment date *</label><input v-model="form.payment_date" type="date" required class="w-full p-2 border rounded" /><InputError :message="form.errors.payment_date" /></div>
        <div><label class="block mb-1 text-sm">Reference</label><input v-model="form.reference_no" maxlength="100" class="w-full p-2 border rounded" /><InputError :message="form.errors.reference_no" /></div>
        <div><label class="block mb-1 text-sm">Notes</label><textarea v-model="form.notes" rows="2" class="w-full p-2 border rounded" /><InputError :message="form.errors.notes" /></div>
        <div class="flex justify-end"><button :disabled="form.processing || accounts.length === 0" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded disabled:opacity-50">{{ form.processing ? 'Saving...' : 'Record Payment' }}</button></div>
      </form>
    </div>
  </BackendLayout>
</template>
