<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
  payment: {
    type: Object,
    default: null,
  },
  prefill: {
    type: Object,
    default: null,
  },
  suppliers: {
    type: Array,
    default: () => [],
  },
  isEdit: {
    type: Boolean,
    default: false,
  },
});

const form = useForm({
  supplier_id: props.payment?.supplier_id ?? props.prefill?.supplier_id ?? '',
  total_amount: props.payment?.total_amount ?? props.prefill?.total_amount ?? '',
  paid_amount: props.payment?.paid_amount ?? props.prefill?.paid_amount ?? '',
  payment_date: props.payment?.payment_date ?? props.prefill?.payment_date ?? new Date().toISOString().slice(0, 10),
  payment_type: props.payment?.payment_type ?? props.prefill?.payment_type ?? 'partial',
  notes: props.payment?.notes ?? props.prefill?.notes ?? '',
  _method: props.isEdit ? 'put' : 'post',
});

const dueAmount = computed(() => {
  const total = Number(form.total_amount || 0);
  const paid = Number(form.paid_amount || 0);
  const value = total - paid;
  return value > 0 ? value : 0;
});

const submit = () => {
  if (props.isEdit) {
    form.post(route('backend.supplierpayment.update', props.payment.id));
    return;
  }

  form.post(route('backend.supplierpayment.store'));
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-2 p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">
          {{ isEdit ? 'Edit Supplier Payment' : 'Add Supplier Payment' }}
        </h1>
        <Link :href="route('backend.supplierpayment.index')" class="px-3 py-2 text-sm text-white bg-gray-600 rounded hover:bg-gray-700">
          Back
        </Link>
      </div>

      <form class="grid grid-cols-1 gap-4 mt-4 md:grid-cols-2" @submit.prevent="submit">
        <div>
          <InputLabel for="supplier" value="Supplier" />
          <select id="supplier" v-model="form.supplier_id" class="w-full p-2 border border-gray-300 rounded">
            <option value="">Select Supplier</option>
            <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
              {{ supplier.name }}
            </option>
          </select>
          <InputError class="mt-1" :message="form.errors.supplier_id" />
        </div>

        <div>
          <InputLabel for="payment_date" value="Payment Date" />
          <input id="payment_date" v-model="form.payment_date" type="date" class="w-full p-2 border border-gray-300 rounded" />
          <InputError class="mt-1" :message="form.errors.payment_date" />
        </div>

        <div>
          <InputLabel for="total_amount" value="Total Amount" />
          <input id="total_amount" v-model="form.total_amount" type="number" min="0" step="0.01" class="w-full p-2 border border-gray-300 rounded" />
          <InputError class="mt-1" :message="form.errors.total_amount" />
        </div>

        <div>
          <InputLabel for="paid_amount" value="Paid Amount" />
          <input id="paid_amount" v-model="form.paid_amount" type="number" min="0" step="0.01" class="w-full p-2 border border-gray-300 rounded" />
          <InputError class="mt-1" :message="form.errors.paid_amount" />
        </div>

        <div>
          <InputLabel for="payment_type" value="Payment Type" />
          <select id="payment_type" v-model="form.payment_type" class="w-full p-2 border border-gray-300 rounded">
            <option value="full">Full</option>
            <option value="partial">Partial</option>
          </select>
          <InputError class="mt-1" :message="form.errors.payment_type" />
        </div>

        <div>
          <InputLabel for="due" value="Due Amount" />
          <input id="due" :value="dueAmount.toFixed(2)" type="text" readonly class="w-full p-2 bg-gray-100 border border-gray-300 rounded" />
        </div>

        <div class="md:col-span-2">
          <InputLabel for="notes" value="Notes" />
          <textarea id="notes" v-model="form.notes" rows="3" class="w-full p-2 border border-gray-300 rounded"></textarea>
          <InputError class="mt-1" :message="form.errors.notes" />
        </div>

        <div class="md:col-span-2">
          <button type="submit" class="px-4 py-2 text-white bg-indigo-600 rounded hover:bg-indigo-700" :disabled="form.processing">
            {{ isEdit ? 'Update Payment' : 'Save Payment' }}
          </button>
        </div>
      </form>
    </div>
  </BackendLayout>
</template>
