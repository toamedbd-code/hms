<script setup>
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { Link, router } from '@inertiajs/vue3';
const props = defineProps(['datas','currencies']);
const csrfToken = (typeof document !== 'undefined') ? (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '') : '';

const deleteExchangeRate = (id) => {
  if (!confirm('Are you sure you want to delete this exchange rate?')) return;
  router.delete(route('backend.exchange-rate.destroy', id));
};
</script>

<template>
  <BackendLayout>
    <div class="p-4 bg-white rounded shadow">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold">Exchange Rates</h1>
        <Link :href="route('backend.currency.index')" class="px-3 py-2 text-sm text-white bg-red-600 rounded hover:bg-red-700">Back</Link>
      </div>
      <form method="post" :action="route('backend.exchange-rate.store')" class="mt-4">
        <input type="hidden" name="_token" :value="csrfToken" />
        <div class="grid grid-cols-3 gap-2">
          <div>
            <label>From</label>
            <select name="from_currency_id" class="w-full p-2 border rounded">
              <option v-for="c in props.currencies" :value="c.id">{{ c.code }}</option>
            </select>
          </div>
          <div>
            <label>To</label>
            <select name="to_currency_id" class="w-full p-2 border rounded">
              <option v-for="c in props.currencies" :value="c.id">{{ c.code }}</option>
            </select>
          </div>
          <div>
            <label>Rate</label>
            <input name="rate" class="w-full p-2 border rounded" />
          </div>
        </div>
        <div class="mt-2"><button class="btn-colorful-sm">Save</button></div>
      </form>

      <table class="w-full mt-4 table-auto">
        <thead>
          <tr>
            <th class="border p-2">#</th>
            <th class="border p-2">From</th>
            <th class="border p-2">To</th>
            <th class="border p-2">Rate</th>
            <th class="border p-2">Date</th>
            <th class="border p-2">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(r, idx) in props.datas.data" :key="r.id">
            <td class="border p-2">{{ idx + 1 }}</td>
            <td class="border p-2">{{ r.from_currency?.code }}</td>
            <td class="border p-2">{{ r.to_currency?.code }}</td>
            <td class="border p-2">{{ r.rate }}</td>
            <td class="border p-2">{{ r.date }}</td>
            <td class="border p-2">
              <button @click.prevent="deleteExchangeRate(r.id)" class="btn btn-sm btn-danger">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </BackendLayout>
</template>
