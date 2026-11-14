<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'Lab Orders & Worklist' },
  orders: { type: Object, default: () => ({ data: [], links: [] }) },
  patients: { type: Array, default: () => [] },
  tests: { type: Array, default: () => [] },
  staff: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
});
const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');
const form = useForm({ lab_patient_id: '', assigned_to: '', notes: '', tests: [{ lab_test_id: '', quantity: 1 }] });
const addTest = () => form.tests.push({ lab_test_id: '', quantity: 1 });
const removeTest = (index) => { if (form.tests.length > 1) form.tests.splice(index, 1); };
const orderTotal = computed(() => form.tests.reduce((sum, line) => {
  const test = props.tests.find((entry) => Number(entry.id) === Number(line.lab_test_id));
  return sum + Number(test?.price ?? 0) * Number(line.quantity ?? 0);
}, 0));
const submit = () => form.post(route('backend.lab.orders.store'));
const applyFilters = () => router.get(route('backend.lab.orders'), { search: search.value || undefined, status: status.value || undefined }, { preserveState: true, replace: true });
const money = (value) => Number(value ?? 0).toFixed(2);
const paid = (order) => Number(order.paid_amount ?? 0);
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 space-y-4 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold">{{ pageTitle }}</h1>
        <a :href="route('backend.lab.dashboard')" class="px-3 py-2 text-sm text-white bg-emerald-600 rounded">Lab Dashboard</a>
      </div>
      <form class="p-4 border rounded-lg" @submit.prevent="submit">
        <h2 class="mb-3 font-semibold">Register Lab Order</h2>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
          <div><label class="block mb-1 text-sm">Patient *</label><select v-model="form.lab_patient_id" required class="w-full p-2 border rounded"><option value="">Select patient</option><option v-for="patient in patients" :key="patient.id" :value="patient.id">{{ patient.patient_no }} · {{ patient.name }} {{ patient.phone ? `· ${patient.phone}` : '' }}</option></select><InputError :message="form.errors.lab_patient_id" /></div>
          <div><label class="block mb-1 text-sm">Assign technician</label><select v-model="form.assigned_to" class="w-full p-2 border rounded"><option value="">Unassigned</option><option v-for="member in staff" :key="member.id" :value="member.id">{{ member.first_name }} {{ member.last_name }}</option></select><InputError :message="form.errors.assigned_to" /></div>
          <div><label class="block mb-1 text-sm">Order notes</label><input v-model="form.notes" class="w-full p-2 border rounded" /><InputError :message="form.errors.notes" /></div>
        </div>
        <div class="mt-4 space-y-2">
          <div v-for="(line, index) in form.tests" :key="index" class="grid grid-cols-1 gap-2 md:grid-cols-12">
            <select v-model="line.lab_test_id" required class="p-2 border rounded md:col-span-8"><option value="">Select test</option><option v-for="test in tests" :key="test.id" :value="test.id">{{ test.test_code }} · {{ test.name }}{{ test.specimen ? ` · ${test.specimen}` : '' }} · {{ money(test.price) }}</option></select>
            <input v-model.number="line.quantity" type="number" min="0.01" step="0.01" required class="p-2 border rounded md:col-span-2" placeholder="Quantity" />
            <button type="button" class="p-2 text-sm text-white bg-rose-600 rounded md:col-span-2" @click="removeTest(index)">Remove</button>
            <InputError class="md:col-span-12" :message="form.errors[`tests.${index}.lab_test_id`] || form.errors[`tests.${index}.quantity`]" />
          </div>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 mt-3">
          <button type="button" class="px-3 py-2 text-sm text-white bg-slate-600 rounded" @click="addTest">Add Test</button>
          <div class="flex items-center gap-3"><span class="font-semibold">Order total: {{ money(orderTotal) }}</span><button :disabled="form.processing" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded disabled:opacity-50">{{ form.processing ? 'Saving...' : 'Register Order' }}</button></div>
        </div>
        <InputError class="mt-2" :message="form.errors.tests" />
      </form>
      <div class="flex flex-wrap gap-2">
        <input v-model="search" class="p-2 text-sm border rounded" placeholder="Search order or patient" @keyup.enter="applyFilters" />
        <select v-model="status" class="p-2 text-sm border rounded" @change="applyFilters"><option value="">All statuses</option><option>Registered</option><option>Processing</option><option>Completed</option><option>Cancelled</option></select>
        <button class="px-3 py-2 text-sm text-white bg-emerald-600 rounded" @click="applyFilters">Search</button>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm border"><thead class="bg-slate-50"><tr><th class="p-2 text-left border">Order</th><th class="p-2 text-left border">Patient</th><th class="p-2 border">Date</th><th class="p-2 border">Technician</th><th class="p-2 border">Workflow</th><th class="p-2 text-right border">Total</th><th class="p-2 text-right border">Due</th><th class="p-2 border">Open</th></tr></thead>
          <tbody><tr v-for="order in orders.data" :key="order.id"><td class="p-2 border">{{ order.order_no }}</td><td class="p-2 border">{{ order.patient_name }}<div class="text-xs text-slate-500">{{ order.patient_no }}</div></td><td class="p-2 text-center border">{{ String(order.ordered_at).slice(0, 10) }}</td><td class="p-2 text-center border">{{ [order.staff_first_name, order.staff_last_name].filter(Boolean).join(' ') || '-' }}</td><td class="p-2 text-center border">{{ order.status }}</td><td class="p-2 text-right border">{{ money(order.total_amount) }}</td><td class="p-2 text-right border">{{ money(Math.max(0, Number(order.total_amount) - paid(order))) }}</td><td class="p-2 text-center border"><a :href="route('backend.lab.orders.show', order.id)" class="px-3 py-1 text-white bg-emerald-600 rounded">Open</a></td></tr>
            <tr v-if="orders.data.length === 0"><td colspan="8" class="p-5 text-center border text-slate-500">No lab orders found.</td></tr></tbody>
        </table>
      </div>
      <div class="flex justify-end gap-2"><a v-for="(link, index) in orders.links" :key="index" :href="link.url || '#'" class="px-3 py-1 text-sm border rounded" :class="link.active ? 'bg-emerald-600 text-white' : ''" v-html="link.label" /></div>
    </div>
  </BackendLayout>
</template>
