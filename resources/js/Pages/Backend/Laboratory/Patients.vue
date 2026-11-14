<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
  pageTitle: { type: String, default: 'Lab Patients' },
  patients: { type: Object, default: () => ({ data: [], links: [] }) },
  filters: { type: Object, default: () => ({}) },
});
const search = ref(props.filters?.search ?? '');
const form = useForm({ name: '', phone: '', date_of_birth: '', gender: '', address: '' });
const submit = () => form.post(route('backend.lab.patients.store'), { onSuccess: () => form.reset() });
const applySearch = () => router.get(route('backend.lab.patients'), { search: search.value || undefined }, { preserveState: true, replace: true });
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 space-y-4 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
        <a :href="route('backend.lab.dashboard')" class="px-3 py-2 text-sm text-white bg-emerald-600 rounded">Lab Dashboard</a>
      </div>
      <form class="p-4 border rounded-lg" @submit.prevent="submit">
        <h2 class="mb-3 font-semibold">Register Patient</h2>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
          <div><label class="block mb-1 text-sm">Patient name *</label><input v-model="form.name" required maxlength="255" class="w-full p-2 border rounded" /><InputError :message="form.errors.name" /></div>
          <div><label class="block mb-1 text-sm">Phone</label><input v-model="form.phone" maxlength="30" class="w-full p-2 border rounded" /><InputError :message="form.errors.phone" /></div>
          <div><label class="block mb-1 text-sm">Date of birth</label><input v-model="form.date_of_birth" type="date" class="w-full p-2 border rounded" /><InputError :message="form.errors.date_of_birth" /></div>
          <div><label class="block mb-1 text-sm">Gender</label><select v-model="form.gender" class="w-full p-2 border rounded"><option value="">Select</option><option>Female</option><option>Male</option><option>Other</option></select><InputError :message="form.errors.gender" /></div>
          <div class="md:col-span-2"><label class="block mb-1 text-sm">Address</label><input v-model="form.address" class="w-full p-2 border rounded" /><InputError :message="form.errors.address" /></div>
        </div>
        <div class="flex justify-end mt-3"><button :disabled="form.processing" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded disabled:opacity-50">{{ form.processing ? 'Saving...' : 'Register Patient' }}</button></div>
      </form>
      <div class="flex gap-2">
        <input v-model="search" class="p-2 text-sm border rounded" placeholder="Search patient, phone or number" @keyup.enter="applySearch" />
        <button class="px-3 py-2 text-sm text-white bg-emerald-600 rounded" @click="applySearch">Search</button>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm border"><thead class="bg-slate-50"><tr><th class="p-2 text-left border">Patient No</th><th class="p-2 text-left border">Name</th><th class="p-2 border">Phone</th><th class="p-2 border">DOB</th><th class="p-2 border">Gender</th></tr></thead>
          <tbody><tr v-for="patient in patients.data" :key="patient.id"><td class="p-2 border">{{ patient.patient_no }}</td><td class="p-2 border">{{ patient.name }}</td><td class="p-2 text-center border">{{ patient.phone || '-' }}</td><td class="p-2 text-center border">{{ patient.date_of_birth || '-' }}</td><td class="p-2 text-center border">{{ patient.gender || '-' }}</td></tr>
            <tr v-if="patients.data.length === 0"><td colspan="5" class="p-5 text-center border text-slate-500">No lab patients found.</td></tr></tbody>
        </table>
      </div>
      <div class="flex justify-end gap-2"><a v-for="(link, index) in patients.links" :key="index" :href="link.url || '#'" class="px-3 py-1 text-sm border rounded" :class="link.active ? 'bg-emerald-600 text-white' : ''" v-html="link.label" /></div>
    </div>
  </BackendLayout>
</template>
