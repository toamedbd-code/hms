<script setup>
import { useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';
import { displayWarning } from '@/responseMessage.js';

const props = defineProps({
  pageTitle: { type: String, default: 'Add Store Supplier' },
  supplier: { type: Object, default: null },
});

const form = useForm({
  name: props.supplier?.name ?? '',
  phone: props.supplier?.phone ?? '',
  contact_person_name: props.supplier?.contact_person_name ?? '',
  contact_person_phone: props.supplier?.contact_person_phone ?? '',
  address: props.supplier?.address ?? '',
  notes: props.supplier?.notes ?? '',
  status: props.supplier?.status ?? 'Active',
});

const submit = () => {
  const target = props.supplier
    ? route('backend.storesupplier.update', props.supplier.id)
    : route('backend.storesupplier.store');
  const options = {
    onError: (errors) => displayWarning(errors),
  };

  if (props.supplier) {
    form.put(target, options);
  } else {
    form.post(target, options);
  }
};
</script>

<template>
  <BackendLayout>
    <div class="w-full max-w-4xl p-4 mt-3 mx-auto bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <div>
          <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
          <p class="mt-1 text-xs text-slate-500">Store supplier list is separate from Pharmacy / Medicine suppliers.</p>
        </div>
        <a :href="route('backend.storesupplier.index')" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700">Back</a>
      </div>

      <form class="mt-4 space-y-4" @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
          <div>
            <label for="supplier-name" class="block mb-1 text-sm font-semibold">Supplier name *</label>
            <input id="supplier-name" v-model="form.name" type="text" maxlength="255" required class="w-full p-2 text-sm border rounded" />
            <InputError :message="form.errors.name" />
          </div>
          <div>
            <label for="supplier-phone" class="block mb-1 text-sm font-semibold">Phone</label>
            <input id="supplier-phone" v-model="form.phone" type="text" maxlength="255" class="w-full p-2 text-sm border rounded" />
            <InputError :message="form.errors.phone" />
          </div>
          <div>
            <label for="contact-name" class="block mb-1 text-sm font-semibold">Contact person</label>
            <input id="contact-name" v-model="form.contact_person_name" type="text" maxlength="255" class="w-full p-2 text-sm border rounded" />
            <InputError :message="form.errors.contact_person_name" />
          </div>
          <div>
            <label for="contact-phone" class="block mb-1 text-sm font-semibold">Contact phone</label>
            <input id="contact-phone" v-model="form.contact_person_phone" type="text" maxlength="255" class="w-full p-2 text-sm border rounded" />
            <InputError :message="form.errors.contact_person_phone" />
          </div>
          <div>
            <label for="supplier-address" class="block mb-1 text-sm font-semibold">Address</label>
            <input id="supplier-address" v-model="form.address" type="text" maxlength="255" class="w-full p-2 text-sm border rounded" />
            <InputError :message="form.errors.address" />
          </div>
          <div>
            <label for="supplier-status" class="block mb-1 text-sm font-semibold">Status</label>
            <select id="supplier-status" v-model="form.status" class="w-full p-2 text-sm border rounded">
              <option>Active</option>
              <option>Inactive</option>
            </select>
            <InputError :message="form.errors.status" />
          </div>
          <div class="md:col-span-2">
            <label for="supplier-notes" class="block mb-1 text-sm font-semibold">Notes</label>
            <textarea id="supplier-notes" v-model="form.notes" rows="3" class="w-full p-2 text-sm border rounded"></textarea>
            <InputError :message="form.errors.notes" />
          </div>
        </div>

        <div class="flex justify-end">
          <button type="submit" :disabled="form.processing" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700 disabled:opacity-50">
            {{ form.processing ? 'Saving...' : (supplier ? 'Update Store Supplier' : 'Save Store Supplier') }}
          </button>
        </div>
      </form>
    </div>
  </BackendLayout>
</template>
