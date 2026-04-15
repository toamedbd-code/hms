<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { displayResponse, displayWarning, deleteConfirmation } from '@/responseMessage.js';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const props = defineProps({
  pageTitle: String,
  filters: Object,
  rows: Object,
  staffList: Array,
});

const selectedMonth = ref(props.filters?.month ?? new Date().toISOString().slice(0,7));
const searchTerm = ref(props.filters?.staff_search ?? '');
const dateFromFilter = ref(props.filters?.date_from ?? '');
const dateToFilter = ref(props.filters?.date_to ?? '');

const applyFilter = () => {
  router.get('/backend/staffattendance/duty-roster', {
    month: selectedMonth.value,
    staff_search: searchTerm.value,
    date_from: dateFromFilter.value,
    date_to: dateToFilter.value,
  }, { preserveState: true });
};

const newEntry = ref({ staff_id: '', date: '', date_from: '', date_to: '', start_time: '', end_time: '', shift_name: '', note: '' });

// Safely read CSRF token only in browser (avoid SSR errors)
const csrfToken = (typeof document !== 'undefined')
  ? (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '')
  : '';

// Toggle to apply roster to a date range instead of a single date
const useRange = ref(false);

const submit = () => {
  const payload = {};
  if (newEntry.value.id) payload.id = newEntry.value.id;
  if (useRange.value && newEntry.value.date_from && newEntry.value.date_to) {
    payload.date_from = newEntry.value.date_from;
    payload.date_to = newEntry.value.date_to;
  } else if (!useRange.value && newEntry.value.date) {
    payload.date = newEntry.value.date;
  }
  payload.staff_id = newEntry.value.staff_id;
  if (newEntry.value.start_time) payload.start_time = newEntry.value.start_time;
  if (newEntry.value.end_time) payload.end_time = newEntry.value.end_time;
  if (newEntry.value.shift_name) payload.shift_name = newEntry.value.shift_name;
  if (newEntry.value.note) payload.note = newEntry.value.note;

  router.post('/backend/staffattendance/duty-roster', payload, {
    onSuccess: (response) => {
      displayResponse(response);
      newEntry.value = { staff_id: '', date: '', date_from: '', date_to: '', start_time: '', end_time: '', shift_name: '', note: '' };
      useRange.value = false;
      applyFilter();
    },
    onError: (errorObject) => {
      displayWarning(errorObject);
    }
  });
};

const editEntry = (row, date) => {
  // populate form for editing single roster
  newEntry.value.id = row.id;
  newEntry.value.staff_id = row.staff_id;
  // row.date may include a time portion (e.g. "2026-03-05 00:00:00").
  // HTML `input[type=date]` requires `YYYY-MM-DD` — strip time if present.
  const raw = date || row.date || '';
  let dateOnly = '';
  if (raw) {
    const s = String(raw).trim();
    // If format is YYYY-MM-DD... keep as-is (strip time)
    if (/^\d{4}-\d{2}-\d{2}/.test(s)) {
      dateOnly = s.split(' ')[0].split('T')[0];
    } else if (/^\d{2}-\d{2}-\d{4}/.test(s)) {
      // If incoming is DD-MM-YYYY, convert to YYYY-MM-DD for input[type=date]
      const parts = s.split(' ')[0].split('T')[0].split('-');
      if (parts.length === 3) {
        dateOnly = `${parts[2]}-${parts[1]}-${parts[0]}`;
      }
    } else {
      // fallback: take first token
      dateOnly = s.split(' ')[0];
    }
  }
  newEntry.value.date = dateOnly;
  // clear any range values when editing a single saved entry
  newEntry.value.date_from = '';
  newEntry.value.date_to = '';
  newEntry.value.start_time = row.start_time;
  newEntry.value.end_time = row.end_time;
  newEntry.value.shift_name = row.shift_name;
  newEntry.value.note = row.note;
  useRange.value = false;
  // scroll into view
  window.scrollTo({ top: 0, behavior: 'smooth' });
};

const cancelEdit = () => {
  newEntry.value = { staff_id: '', date: '', date_from: '', date_to: '', start_time: '', end_time: '', shift_name: '', note: '' };
  useRange.value = false;
};

const printRoster = () => {
  // open the printable roster page in a new tab for better print output
  const params = new URLSearchParams();
  if (selectedMonth.value) params.append('month', selectedMonth.value);
  if (searchTerm.value) params.append('staff_search', searchTerm.value);
  if (dateFromFilter.value) params.append('date_from', dateFromFilter.value);
  if (dateToFilter.value) params.append('date_to', dateToFilter.value);
  const url = `/backend/staffattendance/duty-roster/print?${params.toString()}`;
  window.open(url, '_blank');
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded-md shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <div>
          <h1 class="text-xl font-bold">{{ pageTitle }}</h1>
          <p class="text-sm text-gray-600">ডিউটি রোস্টার পরিচালনা করুন</p>
        </div>
        <div class="flex items-center gap-2">
          <input type="month" v-model="selectedMonth" @change="applyFilter" class="p-2 border rounded" />
          <input v-model="searchTerm" type="text" placeholder="Search name or employee id" class="p-2 border rounded" />
          <input v-model="dateFromFilter" type="date" class="p-2 border rounded" />
          <input v-model="dateToFilter" type="date" class="p-2 border rounded" />
          <button class="px-3 py-2 text-sm text-white bg-indigo-600 rounded" @click="applyFilter">Search</button>
        </div>
      </div>

      <div class="mt-4">
        <h2 class="font-semibold mb-2">Add / Update Roster</h2>
        <div class="grid grid-cols-6 gap-2 items-center">
          <select v-model="newEntry.staff_id" class="col-span-2 p-2 border rounded">
            <option value="">Select Staff</option>
            <option v-for="s in staffList" :value="s.id">{{ s.name }}</option>
          </select>

          <div class="col-span-1 flex items-center gap-2">
            <input id="useRange" type="checkbox" v-model="useRange" class="w-4 h-4" />
            <label for="useRange" class="text-sm">Apply to range</label>
          </div>

          <template v-if="useRange">
            <input v-model="newEntry.date_from" type="date" class="p-2 border rounded" />
            <input v-model="newEntry.date_to" type="date" class="p-2 border rounded" />
          </template>
          <template v-else>
            <input v-model="newEntry.date" type="date" class="p-2 border rounded" />
            <div></div>
          </template>

          <input v-model="newEntry.start_time" type="time" class="p-2 border rounded" />
          <input v-model="newEntry.end_time" type="time" class="p-2 border rounded" />
          <input v-model="newEntry.shift_name" placeholder="Shift" class="p-2 border rounded" />
        </div>
        <div class="mt-2">
          <textarea v-model="newEntry.note" rows="2" class="w-full p-2 border rounded" placeholder="Note"></textarea>
        </div>
        <div class="mt-2 flex items-center gap-2">
          <button class="px-3 py-2 text-white bg-emerald-600 rounded" @click="submit">Save</button>
          <button v-if="newEntry.id" class="px-3 py-2 text-sm text-white bg-gray-500 rounded" @click.prevent="cancelEdit">Cancel</button>
          <button class="px-3 py-2 text-sm text-white bg-sky-600 rounded" @click.prevent="printRoster">Print</button>
        </div>
      </div>

      <div class="mt-6">
        <h2 class="font-semibold mb-2">Roster for month</h2>
        <div v-for="(group, date) in rows" :key="date" class="mb-4">
          <div class="font-medium">{{ date }}</div>
          <div class="mt-2 grid grid-cols-1 gap-1">
            <div v-for="r in group" :key="r.id" class="flex items-center justify-between p-2 bg-gray-50 border rounded">
              <div>
                <div class="font-semibold">{{ r.staff_name }}</div>
                <div class="text-sm text-gray-600">{{ r.shift_name }} — {{ r.start_time }} to {{ r.end_time }}</div>
                <div class="text-xs text-gray-500">Created: {{ r.created_at ?? 'N/A' }}</div>
              </div>
              <div class="flex items-center gap-2">
                <button @click.prevent="deleteRoster(r.id)" class="px-2 py-1 text-sm text-white bg-red-500 rounded">Remove</button>
                <button @click.prevent="editEntry(r, date)" class="px-2 py-1 text-sm text-white bg-yellow-400 rounded">Edit</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </BackendLayout>
</template>

<script>
// additional script block for delete action using fetch
export default {
  methods: {
    deleteRoster(id) {
      const url = `/backend/staffattendance/duty-roster/${id}`;
      deleteConfirmation(url);
    }
  }
}
</script>
