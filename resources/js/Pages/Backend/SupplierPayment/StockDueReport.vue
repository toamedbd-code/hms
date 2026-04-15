<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const props = defineProps({
  report: {
    type: Array,
    default: () => [],
  },
  suppliers: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
});

const printSectionRef = ref(null);

const money = (value) => Number(value ?? 0).toFixed(2);

const totals = computed(() => {
  return props.report.reduce((carry, item) => {
    carry.total_due += Number(item.total_due ?? 0);
    carry.stock_value += Number(item.stock_value ?? 0);
    carry.total_sold_quantity += Number(item.total_sold_quantity ?? 0);
    return carry;
  }, {
    total_due: 0,
    stock_value: 0,
    total_sold_quantity: 0,
  });
});

const qty = (value) => Number(value ?? 0).toFixed(2);

const safeFilePart = (value) => String(value ?? '').replace(/[^a-zA-Z0-9_-]/g, '-');

const exportFileName = (extension) => {
  const from = filterForm.from_date ? safeFilePart(filterForm.from_date) : 'all';
  const to = filterForm.to_date ? safeFilePart(filterForm.to_date) : 'all';
  return `supplier-stock-due-report-${from}-to-${to}.${extension}`;
};

const downloadTextFile = (content, mimeType, extension) => {
  const blob = new Blob([content], { type: mimeType });
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = exportFileName(extension);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  window.URL.revokeObjectURL(url);
};

const escapeCsvCell = (value) => {
  const text = String(value ?? '');
  if (text.includes('"') || text.includes(',') || text.includes('\n')) {
    return `"${text.replace(/"/g, '""')}"`;
  }
  return text;
};

const buildDetailRows = () => {
  const rows = [];

  props.report.forEach((supplier) => {
    if (!supplier.medicines || supplier.medicines.length === 0) {
      rows.push([
        supplier.supplier_name,
        '',
        '0.00',
        '0.00',
        '0.00',
        '0.00',
      ]);
      return;
    }

    supplier.medicines.forEach((medicine) => {
      rows.push([
        supplier.supplier_name,
        medicine.medicine_name,
        qty(medicine.stock_quantity),
        qty(medicine.sold_quantity),
        money(medicine.unit_price),
        money(medicine.total_value),
      ]);
    });
  });

  return rows;
};

const exportCsv = () => {
  const headers = ['Supplier', 'Medicine Name', 'In Stock Qty', 'Sold Qty', 'Unit Price', 'Stock Value'];
  const rows = buildDetailRows();

  rows.push(['', '', '', '', '', '']);
  rows.push([
    'Grand Total',
    '',
    '',
    qty(totals.value.total_sold_quantity),
    '',
    money(totals.value.stock_value),
  ]);

  const lines = [
    headers.map(escapeCsvCell).join(','),
    ...rows.map((row) => row.map(escapeCsvCell).join(',')),
  ];

  downloadTextFile(lines.join('\n'), 'text/csv;charset=utf-8', 'csv');
};

const exportExcel = () => {
  const headers = ['Supplier', 'Medicine Name', 'In Stock Qty', 'Sold Qty', 'Unit Price', 'Stock Value'];
  const rows = buildDetailRows();

  rows.push(['', '', '', '', '', '']);
  rows.push([
    'Grand Total',
    '',
    '',
    qty(totals.value.total_sold_quantity),
    '',
    money(totals.value.stock_value),
  ]);

  const lines = [
    headers.join('\t'),
    ...rows.map((row) => row.join('\t')),
  ];

  downloadTextFile(lines.join('\n'), 'application/vnd.ms-excel;charset=utf-8', 'xls');
};

const printReport = () => {
  const printContents = printSectionRef.value?.innerHTML;
  if (!printContents) {
    return;
  }

  const printWindow = window.open('', '_blank', 'width=1200,height=800');
  if (!printWindow) {
    return;
  }

  printWindow.document.write(`
    <html>
      <head>
        <title>Supplier Stock & Due Report</title>
        <style>
          body { font-family: Arial, sans-serif; margin: 16px; color: #111827; }
          h1 { font-size: 20px; margin: 0 0 12px 0; }
          table { width: 100%; border-collapse: collapse; margin-top: 10px; }
          th, td { border: 1px solid #d1d5db; padding: 6px; font-size: 12px; text-align: left; }
          thead { background: #f3f4f6; }
          .no-print { display: none !important; }
        </style>
      </head>
      <body>
        ${printContents}
      </body>
    </html>
  `);

  printWindow.document.close();
  printWindow.focus();
  printWindow.print();
  printWindow.close();
};

const filterForm = reactive({
  supplier_id: props.filters?.supplier_id ?? '',
  from_date: props.filters?.from_date ?? '',
  to_date: props.filters?.to_date ?? '',
});

const applyFilter = () => {
  router.get(route('backend.supplierpayment.report.stock-due'), filterForm, {
    preserveState: true,
    preserveScroll: true,
  });
};

const resetFilter = () => {
  filterForm.supplier_id = '';
  filterForm.from_date = '';
  filterForm.to_date = '';

  router.get(route('backend.supplierpayment.report.stock-due'), {}, {
    preserveState: true,
    preserveScroll: true,
  });
};
</script>

<template>
  <BackendLayout>
    <div ref="printSectionRef" class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <h1 class="text-xl font-semibold text-gray-800">Supplier Stock & Due Report</h1>
        <Link :href="route('backend.supplierpayment.index')" class="px-3 py-2 text-sm text-white bg-gray-600 rounded hover:bg-gray-700">
          Back
        </Link>
      </div>

      <div class="grid grid-cols-1 gap-3 p-4 mt-4 border border-gray-200 rounded md:grid-cols-5 no-print">
        <div>
          <label class="block mb-1 text-sm font-medium text-gray-700">Supplier</label>
          <select v-model="filterForm.supplier_id" class="w-full p-2 border border-gray-300 rounded">
            <option value="">All Suppliers</option>
            <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
              {{ supplier.name }}
            </option>
          </select>
        </div>

        <div>
          <label class="block mb-1 text-sm font-medium text-gray-700">From Date</label>
          <input v-model="filterForm.from_date" type="date" class="w-full p-2 border border-gray-300 rounded">
        </div>

        <div>
          <label class="block mb-1 text-sm font-medium text-gray-700">To Date</label>
          <input v-model="filterForm.to_date" type="date" class="w-full p-2 border border-gray-300 rounded">
        </div>

        <div class="flex items-end gap-2 md:col-span-2">
          <button type="button" class="px-4 py-2 text-sm text-white rounded bg-emerald-600 hover:bg-emerald-700" @click="applyFilter">
            Filter
          </button>
          <button type="button" class="px-4 py-2 text-sm text-white bg-gray-500 rounded hover:bg-gray-600" @click="resetFilter">
            Reset
          </button>
          <button type="button" class="px-4 py-2 text-sm text-white bg-blue-600 rounded hover:bg-blue-700" @click="exportCsv">
            CSV
          </button>
          <button type="button" class="px-4 py-2 text-sm text-white bg-indigo-600 rounded hover:bg-indigo-700" @click="exportExcel">
            Excel
          </button>
          <button type="button" class="px-4 py-2 text-sm text-white bg-purple-600 rounded hover:bg-purple-700" @click="printReport">
            Print
          </button>
        </div>
      </div>

      <div class="w-full my-4 overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700 border border-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 border">Supplier</th>
              <th class="px-3 py-2 border">Total Due</th>
              <th class="px-3 py-2 border">Stock Value</th>
              <th class="px-3 py-2 border">Total Sold Qty</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="item in report" :key="item.supplier_id">
              <tr class="hover:bg-gray-50">
              <td class="px-3 py-2 border">{{ item.supplier_name }}</td>
              <td class="px-3 py-2 border">{{ money(item.total_due) }}</td>
              <td class="px-3 py-2 border">{{ money(item.stock_value) }}</td>
              <td class="px-3 py-2 border">{{ qty(item.total_sold_quantity) }}</td>
              </tr>

              <tr>
                <td colspan="4" class="p-0 border">
                  <div class="p-3 bg-gray-50">
                    <h3 class="mb-2 text-xs font-semibold tracking-wide text-gray-700 uppercase">Medicine List</h3>
                    <div class="overflow-x-auto">
                      <table class="w-full text-xs border border-gray-200">
                        <thead class="bg-white">
                          <tr>
                            <th class="px-2 py-2 border">Medicine</th>
                            <th class="px-2 py-2 border">In Stock Qty</th>
                            <th class="px-2 py-2 border">Sold Qty</th>
                            <th class="px-2 py-2 border">Unit Price</th>
                            <th class="px-2 py-2 border">Stock Value</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="medicine in item.medicines" :key="`${item.supplier_id}-${medicine.medicine_name}`">
                            <td class="px-2 py-2 border">{{ medicine.medicine_name }}</td>
                            <td class="px-2 py-2 border">{{ qty(medicine.stock_quantity) }}</td>
                            <td class="px-2 py-2 border">{{ qty(medicine.sold_quantity) }}</td>
                            <td class="px-2 py-2 border">{{ money(medicine.unit_price) }}</td>
                            <td class="px-2 py-2 border">{{ money(medicine.total_value) }}</td>
                          </tr>
                          <tr v-if="!item.medicines || item.medicines.length === 0">
                            <td colspan="5" class="px-2 py-3 text-center text-gray-500 border">No medicines found for this supplier.</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </td>
              </tr>
            </template>

            <tr v-if="report.length > 0" class="font-semibold bg-gray-100">
              <td class="px-3 py-2 border">Grand Total</td>
              <td class="px-3 py-2 border">{{ money(totals.total_due) }}</td>
              <td class="px-3 py-2 border">{{ money(totals.stock_value) }}</td>
              <td class="px-3 py-2 border">{{ qty(totals.total_sold_quantity) }}</td>
            </tr>

            <tr v-if="report.length === 0">
              <td colspan="4" class="px-3 py-6 text-center text-gray-500 border">No report data found.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </BackendLayout>
</template>
