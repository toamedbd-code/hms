<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';

const page = usePage();
const accountsProp = page.props?.accounts || [];
const form = ref({ code: '', name: '', type: 'asset', parent_id: '' });
const loading = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const flatAccounts = computed(() => {
  const out = [];
  accountsProp.forEach(a => {
    out.push({ id: a.id, name: a.name, code: a.code });
    if (a.children && a.children.length) {
      a.children.forEach(c => out.push({ id: c.id, name: `${a.name} > ${c.name}`, code: c.code }));
    }
  });
  return out;
});

async function saveAccount() {
  loading.value = true;
  try {
    const payload = { ...form.value };
    if (payload.parent_id === '') delete payload.parent_id;

    if (isEditing.value && editingId.value) {
      await axios.put(route('backend.accounts.update', editingId.value), payload);
    } else {
      await axios.post(route('backend.accounts.store'), payload);
    }

    window.location.reload();
  } catch (err) {
    console.error(err);
    alert(err?.response?.data?.message || 'Failed to save account');
  } finally {
    loading.value = false;
  }
}

function editAccount(acc) {
  isEditing.value = true;
  editingId.value = acc.id;
  form.value.code = acc.code || '';
  form.value.name = acc.name || '';
  form.value.type = acc.type || 'asset';
  form.value.parent_id = acc.parent ? acc.parent.id : '';
}

function cancelEdit() {
  isEditing.value = false;
  editingId.value = null;
  form.value = { code: '', name: '', type: 'asset', parent_id: '' };
}

async function deleteAccount(id) {
  if (!confirm('Are you sure you want to delete this account?')) return;
  try {
    await axios.delete(route('backend.accounts.destroy', id));
    window.location.reload();
  } catch (err) {
    console.error(err);
    alert(err?.response?.data?.message || 'Failed to delete account');
  }
}
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 bg-white rounded-md dark:bg-slate-900">
      <h1 class="text-xl font-bold">{{ $page.props.pageTitle }}</h1>
      <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">Chart of Accounts — add, view and manage accounts.</p>

      <div class="mt-4 grid grid-cols-2 gap-6">
        <div>
          <h2 class="font-semibold mb-2">Existing Accounts</h2>
          <div class="space-y-2">
            <div v-for="acc in accountsProp" :key="acc.id" class="p-3 border rounded">
              <div class="flex justify-between items-start">
                <div>
                  <div class="font-medium">{{ acc.code }} — {{ acc.name }} ({{ acc.type }})</div>
                  <div class="text-sm mt-1" v-if="acc.children && acc.children.length">
                    <div v-for="child in acc.children" :key="child.id" class="text-sm">- {{ child.code }} — {{ child.name }}</div>
                  </div>
                </div>
                <div class="space-x-2">
                  <button @click="editAccount(acc)" class="px-2 py-1 bg-yellow-400 text-black rounded text-sm">Edit</button>
                  <button @click="deleteAccount(acc.id)" class="px-2 py-1 bg-red-500 text-white rounded text-sm">Delete</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div>
          <h2 class="font-semibold mb-2">{{ isEditing ? 'Edit Account' : 'Create Account' }}</h2>
          <div class="space-y-2">
            <div>
              <label class="block text-sm">Code</label>
              <input v-model="form.code" class="w-full border rounded p-2" placeholder="e.g. CASH" />
            </div>
            <div>
              <label class="block text-sm">Name</label>
              <input v-model="form.name" class="w-full border rounded p-2" placeholder="Cash" />
            </div>
            <div>
              <label class="block text-sm">Type</label>
              <select v-model="form.type" class="w-full border rounded p-2">
                <option value="asset">Asset</option>
                <option value="liability">Liability</option>
                <option value="income">Income</option>
                <option value="expense">Expense</option>
              </select>
            </div>
            <div>
              <label class="block text-sm">Parent (optional)</label>
              <select v-model="form.parent_id" class="w-full border rounded p-2">
                <option value="">-- none --</option>
                <option v-for="a in flatAccounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
              </select>
            </div>
            <div class="flex items-center gap-2">
              <button :disabled="loading" @click.prevent="saveAccount" class="px-4 py-2 bg-blue-600 text-white rounded">{{ isEditing ? 'Save' : 'Create' }}</button>
              <button v-if="isEditing" @click.prevent="cancelEdit" class="px-4 py-2 bg-gray-300 text-black rounded">Cancel</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </BackendLayout>
</template>
