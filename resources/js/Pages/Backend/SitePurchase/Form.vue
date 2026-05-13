<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';

const props = defineProps({
  purchase: {
    type: Object,
    default: null,
  },
  isEdit: {
    type: Boolean,
    default: false,
  },
});

const form = useForm({
  site_name: props.purchase?.site_name ?? '',
  vendor_name: props.purchase?.vendor_name ?? '',
  item_name: props.purchase?.item_name ?? '',
  category_name: props.purchase?.category_name ?? '',
  purchase_nature: props.purchase?.purchase_nature ?? 'expense',
  purchase_date: props.purchase?.purchase_date ?? '',
  quantity: props.purchase?.quantity ?? 1,
  unit_price: props.purchase?.unit_price ?? 0,
  paid_amount: props.purchase?.paid_amount ?? 0,
  notes: props.purchase?.notes ?? '',
  _method: props.isEdit ? 'put' : 'post',
});

const submit = () => {
  const callbacks = {
    onSuccess: (response) => {
      displayResponse(response);
    },
    onError: (errorObject) => {
      displayWarning(errorObject);
    },
  };

  if (props.isEdit) {
    form.post(route('backend.sitepurchase.update', props.purchase.id), callbacks);
    return;
  }

  form.post(route('backend.sitepurchase.store'), callbacks);
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">
          {{ isEdit ? 'Edit Site Purchase' : 'Add Site Purchase' }}
        </h1>
        <Link :href="route('backend.sitepurchase.index')" class="px-3 py-2 text-sm text-white bg-gray-600 rounded hover:bg-gray-700">
          Site Purchase List
        </Link>
      </div>

      <form @submit.prevent="submit" class="grid grid-cols-1 gap-3 p-4 mt-3 md:grid-cols-2 lg:grid-cols-4">
        <div class="col-span-1">
          <InputLabel for="site_name" value="Site Name *" />
          <input id="site_name" v-model="form.site_name" type="text" class="block w-full p-2 text-sm rounded-md border-slate-300" />
          <InputError class="mt-2" :message="form.errors.site_name" />
        </div>

        <div class="col-span-1">
          <InputLabel for="vendor_name" value="Vendor" />
          <input id="vendor_name" v-model="form.vendor_name" type="text" class="block w-full p-2 text-sm rounded-md border-slate-300" />
          <InputError class="mt-2" :message="form.errors.vendor_name" />
        </div>

        <div class="col-span-1">
          <InputLabel for="item_name" value="Item Name *" />
          <input id="item_name" v-model="form.item_name" type="text" class="block w-full p-2 text-sm rounded-md border-slate-300" />
          <InputError class="mt-2" :message="form.errors.item_name" />
        </div>

        <div class="col-span-1">
          <InputLabel for="category_name" value="Category" />
          <input id="category_name" v-model="form.category_name" type="text" class="block w-full p-2 text-sm rounded-md border-slate-300" />
          <InputError class="mt-2" :message="form.errors.category_name" />
        </div>

        <div class="col-span-1">
          <InputLabel for="purchase_nature" value="Purchase Nature *" />
          <select id="purchase_nature" v-model="form.purchase_nature" class="block w-full p-2 text-sm rounded-md border-slate-300">
            <option value="expense">Expense</option>
            <option value="asset">Asset</option>
          </select>
          <InputError class="mt-2" :message="form.errors.purchase_nature" />
        </div>

        <div class="col-span-1">
          <InputLabel for="purchase_date" value="Purchase Date *" />
          <input id="purchase_date" v-model="form.purchase_date" type="date" class="block w-full p-2 text-sm rounded-md border-slate-300" />
          <InputError class="mt-2" :message="form.errors.purchase_date" />
        </div>

        <div class="col-span-1">
          <InputLabel for="quantity" value="Quantity *" />
          <input id="quantity" v-model="form.quantity" type="number" step="0.01" min="0.01" class="block w-full p-2 text-sm rounded-md border-slate-300" />
          <InputError class="mt-2" :message="form.errors.quantity" />
        </div>

        <div class="col-span-1">
          <InputLabel for="unit_price" value="Unit Price *" />
          <input id="unit_price" v-model="form.unit_price" type="number" step="0.01" min="0" class="block w-full p-2 text-sm rounded-md border-slate-300" />
          <InputError class="mt-2" :message="form.errors.unit_price" />
        </div>

        <div class="col-span-1">
          <InputLabel for="paid_amount" value="Paid Amount" />
          <input id="paid_amount" v-model="form.paid_amount" type="number" step="0.01" min="0" class="block w-full p-2 text-sm rounded-md border-slate-300" />
          <InputError class="mt-2" :message="form.errors.paid_amount" />
        </div>

        <div class="col-span-1 md:col-span-2 lg:col-span-3">
          <InputLabel for="notes" value="Notes" />
          <textarea id="notes" v-model="form.notes" rows="2" class="block w-full p-2 text-sm rounded-md border-slate-300"></textarea>
          <InputError class="mt-2" :message="form.errors.notes" />
        </div>

        <div class="col-span-1 md:col-span-2 lg:col-span-4 flex justify-end mt-2">
          <PrimaryButton type="submit" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
            {{ isEdit ? 'Update' : 'Create' }}
          </PrimaryButton>
        </div>
      </form>
    </div>
  </BackendLayout>
</template>
