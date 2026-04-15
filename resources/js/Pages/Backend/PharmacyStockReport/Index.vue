<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import * as XLSX from 'xlsx';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const page = usePage();

const props = defineProps({
  pageTitle: { type: String, default: 'Pharmacy Stock Report' },
  items: { type: Object, default: () => ({ data: [] }) },
  summary: { type: Object, default: () => ({}) },
  filters: { type: Object, default: () => ({}) },
});

const filters = ref({
  name: props.filters?.name ?? '',
  status: props.filters?.status ?? '',
  per_page: Number(props.filters?.per_page ?? props.items?.per_page ?? 20),
});

const rows = computed(() => props.items?.data ?? []);
const companyName = computed(() => page.props?.webSetting?.company_name || '');

const applyFilter = () => {
  router.get(route('backend.pharmacy.stock.report'), filters.value, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const formatMoney = (value) => Number(value ?? 0).toFixed(2);

const formatDateOnly = (value) => {
  if (!value) return '-';

  const raw = String(value);
  if (/^\d{4}-\d{2}-\d{2}/.test(raw)) {
    return raw.slice(0, 10);
  }

  const date = new Date(raw);
  if (Number.isNaN(date.getTime())) return raw;

  return date.toISOString().slice(0, 10);
};

const printReport = () => {
  const reportTitle = companyName.value || 'Pharmacy Stock Report';
  const header = `
    <h2 style="margin:0 0 4px 0; text-align:center;">${reportTitle}</h2>
    <p style="margin:0 0 10px 0; text-align:center; font-size:12px;">Pharmacy Stock Report</p>
  `;

  const rowsHtml = rows.value.map((item) => `
    <tr>
      <td>${item.medicine_name ?? '-'}</td>
      <td>${item.category?.name ?? '-'}</td>
      <td>${item.supplier?.name ?? '-'}</td>
      <td style="text-align:right;">${Number(item.medicine_quantity ?? 0).toFixed(2)}</td>
      <td style="text-align:right;">${formatMoney(item.medicine_unit_purchase_price)}</td>
      <td style="text-align:right;">${formatMoney(item.medicine_unit_selling_price)}</td>
      <td style="text-align:center;">${formatDateOnly(item.expiry_date)}</td>
      <td style="text-align:center;">${item.status ?? '-'}</td>
    </tr>
  `).join('');

  const footerHtml = `
    <tr style="font-weight:700; background:#f8fafc;">
      <td colspan="3">Grand Total</td>
      <td style="text-align:right;">${Number(props.summary?.total_qty ?? 0).toFixed(2)}</td>
      <td style="text-align:right;">${formatMoney(props.summary?.total_purchase_value)}</td>
      <td style="text-align:right;">${formatMoney(props.summary?.total_selling_value)}</td>
      <td colspan="2"></td>
    </tr>
  `;

  const html = `
    <html>
      <head>
        <title>Pharmacy Stock Report</title>
        <style>
          body { font-family: Arial, sans-serif; padding: 16px; }
          table { width:100%; border-collapse: collapse; font-size:12px; }
          th, td { border:1px solid #d1d5db; padding:6px; }
          th { background:#f3f4f6; }
        </style>
      </head>
      <body>
        ${header}
        <table>
          <thead>
            <tr>
              <th>Medicine</th>
              <th>Category</th>
              <th>Supplier</th>
              <th>Qty</th>
              <th>Unit Buy</th>
              <th>Unit Sell</th>
              <th>Expiry</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            ${rowsHtml}
            ${footerHtml}
          </tbody>
        </table>
      </body>
    </html>
  `;

  const printWindow = window.open('', '_blank', 'width=1100,height=750');
  if (!printWindow) return;
  printWindow.document.open();
  printWindow.document.write(html);
  printWindow.document.close();
  printWindow.focus();
  printWindow.print();
};

const exportExcel = () => {
  const exportRows = rows.value.map((item) => ({
    Medicine: item.medicine_name ?? '-',
    Category: item.category?.name ?? '-',
    Supplier: item.supplier?.name ?? '-',
    Qty: Number(item.medicine_quantity ?? 0),
    UnitBuy: Number(item.medicine_unit_purchase_price ?? 0),
    UnitSell: Number(item.medicine_unit_selling_price ?? 0),
    Expiry: formatDateOnly(item.expiry_date),
    Status: item.status ?? '-',
  }));

  exportRows.push({
    Medicine: 'Grand Total',
    Category: '',
    Supplier: '',
    Qty: Number(props.summary?.total_qty ?? 0),
    UnitBuy: Number(props.summary?.total_purchase_value ?? 0),
    UnitSell: Number(props.summary?.total_selling_value ?? 0),
    Expiry: '',
    Status: '',
  });

  const worksheet = XLSX.utils.json_to_sheet(exportRows);
  const workbook = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(workbook, worksheet, 'PharmacyStock');
  XLSX.writeFile(workbook, 'pharmacy-stock-report.xlsx');
};
</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex flex-wrap items-center justify-between gap-2 p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
        <div class="flex items-center gap-2">
          <button type="button" class="px-3 py-2 text-sm font-semibold text-white bg-sky-600 rounded hover:bg-sky-700" @click="printReport">Print</button>
          <button type="button" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700" @click="exportExcel">Excel</button>
          <a :href="route('backend.dashboard')" class="px-3 py-2 text-sm font-semibold text-white bg-gray-600 rounded hover:bg-gray-700">Back Dashboard</a>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-4 gap-2 p-3 mt-3 bg-slate-100 rounded">
        <div>
          <input v-model="filters.name" type="text" placeholder="Medicine name" class="w-full p-2 text-sm border rounded" @input="applyFilter" />
        </div>
        <div>
          <select v-model="filters.status" class="w-full p-2 text-sm border rounded" @change="applyFilter">
            <option value="">All Status</option>
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
          </select>
        </div>
        <div>
          <select v-model="filters.per_page" class="w-full p-2 text-sm border rounded" @change="applyFilter">
            <option :value="10">Show 10</option>
            <option :value="20">Show 20</option>
            <option :value="50">Show 50</option>
            <option :value="100">Show 100</option>
          </select>
        </div>
      </div>

      <div class="w-full mt-3 overflow-x-auto">
        <table class="w-full text-sm border border-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 border">Medicine</th>
              <th class="px-3 py-2 border">Category</th>
              <th class="px-3 py-2 border">Supplier</th>
              <th class="px-3 py-2 border">Qty</th>
              <th class="px-3 py-2 border">Unit Buy</th>
              <th class="px-3 py-2 border">Unit Sell</th>
              <th class="px-3 py-2 border">Expiry</th>
              <th class="px-3 py-2 border">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in rows" :key="item.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 border">{{ item.medicine_name }}</td>
              <td class="px-3 py-2 border">{{ item.category?.name || '-' }}</td>
              <td class="px-3 py-2 border">{{ item.supplier?.name || '-' }}</td>
              <td class="px-3 py-2 border text-center">{{ Number(item.medicine_quantity ?? 0).toFixed(2) }}</td>
              <td class="px-3 py-2 border text-right">{{ formatMoney(item.medicine_unit_purchase_price) }}</td>
              <td class="px-3 py-2 border text-right">{{ formatMoney(item.medicine_unit_selling_price) }}</td>
              <td class="px-3 py-2 border text-center">{{ formatDateOnly(item.expiry_date) }}</td>
              <td class="px-3 py-2 border text-center">
                <span class="px-2 py-1 text-xs rounded" :class="item.status === 'Active' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'">{{ item.status }}</span>
              </td>
            </tr>
            <tr v-if="rows.length > 0" class="bg-slate-50 font-semibold">
              <td class="px-3 py-2 border" colspan="3">Grand Total</td>
              <td class="px-3 py-2 border text-center">{{ Number(props.summary?.total_qty ?? 0).toFixed(2) }}</td>
              <td class="px-3 py-2 border text-right">{{ formatMoney(props.summary?.total_purchase_value) }}</td>
              <td class="px-3 py-2 border text-right">{{ formatMoney(props.summary?.total_selling_value) }}</td>
              <td class="px-3 py-2 border" colspan="2"></td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="8" class="px-3 py-6 text-center text-gray-500 border">No pharmacy stock found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="props.items?.links?.length" class="grid grid-cols-1 gap-4 pt-3 my-2 md:grid-cols-2 items-center">
        <p class="text-sm text-gray-600 text-center md:text-left">
          Displaying {{ props.items?.from ?? 0 }} to {{ props.items?.to ?? 0 }} of {{ props.items?.total ?? 0 }} items
        </p>
        <nav>
          <ul class="flex items-center justify-center md:justify-end gap-2">
            <li v-for="(link, index) in props.items.links" :key="`${index}-${link.label}`">
              <a v-if="link.url" :href="link.url" class="px-3 py-1 text-sm border rounded" :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'hover:bg-gray-100 border-gray-300'">
                <span v-html="link.label"></span>
              </a>
              <span v-else class="px-3 py-1 text-sm text-gray-400 border border-gray-200 rounded" v-html="link.label"></span>
            </li>
          </ul>
        </nav>
      </div>
    </div>
  </BackendLayout>
</template>
