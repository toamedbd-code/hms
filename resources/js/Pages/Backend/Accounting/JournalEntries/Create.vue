<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { ref, onMounted } from 'vue';

const form = useForm({
  entry_date: new Date().toISOString().slice(0, 10),
  reference: '',
  description: '',
  status: 'Draft',
  lines: [
    { account_id: '', debit: '', credit: '', narration: '' },
    { account_id: '', debit: '', credit: '', narration: '' },
  ],
});

const accounts = ref([]);

const accountsListUrl = (typeof route === 'function' ? route('backend.accounts.list') : (window.route ? window.route('backend.accounts.list') : '/accounts/list'));

onMounted(async () => {
  try {
    const res = await axios.get(accountsListUrl, { params: { numOfData: 1000 } });
    const rows = res.data && res.data.data ? res.data.data : res.data;
    accounts.value = (rows || []).map(a => ({ id: a.id, code: a.code, name: a.name }));
  } catch (e) {
    // ignore
  }
});

function addLine() {
  form.lines.push({ account_id: '', debit: '', credit: '', narration: '' });
}

function removeLine(i) {
  if (form.lines.length > 1) form.lines.splice(i, 1);
}

function save(status = 'Draft') {
  form.status = status;
  form.post(route('backend.journal-entry.store'));
}
</script>

<template>
  <Head title="Create Journal Entry" />

  <div class="p-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Create Journal Entry</h1>
      <Link :href="route('backend.journal-entry.index')" class="btn-colorful text-sm">Back to list</Link>
    </div>

    <div class="mt-6 rounded border bg-white p-4 shadow-sm">
      <div v-if="form.errors.lines" class="mb-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        {{ form.errors.lines }}
      </div>

      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Date</label>
          <input v-model="form.entry_date" type="date" class="mt-1 block w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Reference</label>
          <input v-model="form.reference" type="text" class="mt-1 block w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Status</label>
          <select v-model="form.status" class="mt-1 block w-full">
            <option>Draft</option>
            <option>Posted</option>
            <option>Cancelled</option>
          </select>
        </div>
      </div>

      <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700">Description</label>
        <textarea v-model="form.description" rows="2" class="mt-1 block w-full"></textarea>
      </div>

      <div class="mt-6 overflow-x-auto">
        <table class="w-full table-auto">
          <thead>
            <tr class="text-left">
              <th class="px-2 py-1">Account</th>
              <th class="px-2 py-1">Debit</th>
              <th class="px-2 py-1">Credit</th>
              <th class="px-2 py-1">Narration</th>
              <th class="px-2 py-1"> </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(line, idx) in form.lines" :key="idx">
              <td class="px-2 py-1">
                <select v-model="line.account_id" class="block w-full">
                  <option value="">-- select account --</option>
                  <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name }}</option>
                </select>
              </td>
              <td class="px-2 py-1"><input v-model="line.debit" type="number" step="0.01" class="block w-full" /></td>
              <td class="px-2 py-1"><input v-model="line.credit" type="number" step="0.01" class="block w-full" /></td>
              <td class="px-2 py-1"><input v-model="line.narration" type="text" class="block w-full" /></td>
              <td class="px-2 py-1 text-center"><button type="button" class="text-red-600" @click="removeLine(idx)">Remove</button></td>
            </tr>
          </tbody>
        </table>

        <div class="mt-2">
          <button type="button" class="btn-colorful-sm" @click="addLine">Add line</button>
        </div>
      </div>

      <div class="mt-6 flex gap-2">
        <button type="button" class="btn-colorful" @click="save('Posted')">Post</button>
        <button type="button" class="btn-colorful-sm" @click="save('Draft')">Save Draft</button>
        <Link :href="route('backend.journal-entry.index')" class="btn-colorful-sm">Cancel</Link>
      </div>
    </div>
  </div>
</template>
