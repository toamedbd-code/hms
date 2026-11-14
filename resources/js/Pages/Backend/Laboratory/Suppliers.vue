<script setup>
import { useForm, router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';

defineProps({
  suppliers: { type: Object, default: () => ({ data: [], links: [] }) },
});

const form = useForm({ name: '', phone: '', contact_person: '', email: '', address: '' });
const submit = () => form.post(route('backend.lab.suppliers.store'), { onSuccess: () => form.reset() });
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 space-y-4 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold">Lab Suppliers</h1>
        <div class="flex gap-2">
          <a :href="route('backend.lab.purchase-orders')" class="px-3 py-2 text-sm text-white bg-emerald-600 rounded">Purchase Orders</a>
          <a :href="route('backend.lab.dashboard')" class="px-3 py-2 text-sm text-white bg-emerald-600 rounded">Lab Dashboard</a>
        </div>
      </div>
      <form class="p-4 border rounded-lg" @submit.prevent="submit">
        <h2 class="mb-3 font-semibold">Add Supplier</h2>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
          <div><label class="block mb-1 text-sm">Supplier name *</label><input v-model="form.name" required maxlength="255" class="w-full p-2 border rounded" /><InputError :message="form.errors.name" /></div>
          <div><label class="block mb-1 text-sm">Phone</label><input v-model="form.phone" maxlength="40" class="w-full p-2 border rounded" /><InputError :message="form.errors.phone" /></div>
          <div><label class="block mb-1 text-sm">Contact person</label><input v-model="form.contact_person" maxlength="150" class="w-full p-2 border rounded" /><InputError :message="form.errors.contact_person" /></div>
          <div><label class="block mb-1 text-sm">Email</label><input v-model="form.email" type="email" maxlength="190" class="w-full p-2 border rounded" /><InputError :message="form.errors.email" /></div>
          <div class="md:col-span-2"><label class="block mb-1 text-sm">Address</label><input v-model="form.address" class="w-full p-2 border rounded" /><InputError :message="form.errors.address" /></div>
        </div>
        <div class="flex justify-end mt-3"><button :disabled="form.processing" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded disabled:opacity-50">{{ form.processing ? 'Saving...' : 'Add Supplier' }}</button></div>
      </form>
      <div class="overflow-x-auto">
        <table class="w-full text-sm border">
          <thead class="bg-slate-50"><tr><th class="p-2 text-left border">Supplier</th><th class="p-2 text-left border">Contact person</th><th class="p-2 border">Phone</th><th class="p-2 border">Email</th><th class="p-2 border">Status</th><th class="p-2 border">Action</th></tr></thead>
          <tbody>
            <tr v-for="supplier in suppliers.data" :key="supplier.id">
              <td class="p-2 border">{{ supplier.name }}<div class="text-xs text-slate-500">{{ supplier.address || '' }}</div></td>
              <td class="p-2 border">{{ supplier.contact_person || '-' }}</td><td class="p-2 text-center border">{{ supplier.phone || '-' }}</td><td class="p-2 text-center border">{{ supplier.email || '-' }}</td><td class="p-2 text-center border">{{ supplier.is_active ? 'Active' : 'Inactive' }}</td>
              <td class="p-2 text-center border"><button class="text-indigo-700 hover:underline" @click="router.post(route('backend.lab.suppliers.toggle', supplier.id))">{{ supplier.is_active ? 'Deactivate' : 'Activate' }}</button></td>
            </tr>
            <tr v-if="suppliers.data.length === 0"><td colspan="6" class="p-5 text-center border text-slate-500">No Lab suppliers found.</td></tr>
          </tbody>
        </table>
      </div>
      <div class="flex justify-end gap-2"><a v-for="(link, index) in suppliers.links" :key="index" :href="link.url || '#'" class="px-3 py-1 text-sm border rounded" :class="link.active ? 'bg-emerald-600 text-white' : ''" v-html="link.label" /></div>
    </div>
  </BackendLayout>
</template>
