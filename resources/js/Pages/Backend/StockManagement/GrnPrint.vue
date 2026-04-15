<script setup>
import { computed, onMounted, ref } from 'vue';
import html2pdf from 'html2pdf.js';
import QRCode from 'qrcode';

const props = defineProps({
  pageTitle: { type: String, default: 'GRN Receive Slip' },
  grn: { type: Object, required: true },
});

const printableEl = ref(null);
const qrDataUrl = ref('');

const hospitalLogo = computed(() => {
  return (
    window?.Laravel?.page?.props?.webSetting?.logo
    || ''
  );
});

const signatureImage = computed(() => {
  const setting = window?.Laravel?.page?.props?.webSetting ?? {};
  return setting.pathologist_signature || setting.technologist_signature || setting.sample_collected_by_signature || '';
});

const qrValue = computed(() => {
  const grnNo = props.grn?.grn_no ?? 'N/A';
  const invoice = props.grn?.invoice_no ?? '';
  return encodeURIComponent(`${grnNo} | ${invoice}`);
});

const generateQrData = async () => {
  try {
    qrDataUrl.value = await QRCode.toDataURL(decodeURIComponent(qrValue.value), {
      width: 92,
      margin: 1,
      errorCorrectionLevel: 'M',
    });
  } catch (error) {
    qrDataUrl.value = '';
  }
};

const formatDateTime = (value) => {
  if (!value) return '-';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);

  return date.toLocaleString('en-GB', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  });
};

const downloadPdf = async () => {
  if (!printableEl.value) return;

  await html2pdf()
    .set({
      margin: [8, 8, 8, 8],
      filename: `grn-slip-${props.grn?.grn_no || 'document'}.pdf`,
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: { scale: 2, useCORS: true },
      jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
    })
    .from(printableEl.value)
    .save();
};

onMounted(() => {
  generateQrData();
  window.setTimeout(() => window.print(), 300);
});
</script>

<template>
  <div class="w-full p-4 mt-3 bg-white rounded shadow-md print:shadow-none">
    <div class="flex items-center justify-between mb-4 print:hidden">
      <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
      <div class="flex items-center gap-2">
        <button type="button" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700" @click="downloadPdf">Download PDF</button>
        <a :href="route('backend.stock.grns')" class="px-3 py-2 text-sm font-semibold text-white bg-gray-600 rounded hover:bg-gray-700">Back</a>
      </div>
    </div>

    <div ref="printableEl" class="print-sheet border rounded p-4 bg-white">
      <div class="flex items-start justify-between mb-3">
        <div class="w-20 h-20 border rounded overflow-hidden flex items-center justify-center bg-white">
          <img v-if="hospitalLogo" :src="hospitalLogo" alt="Hospital Logo" class="max-w-full max-h-full object-contain" />
          <span v-else class="text-[10px] text-slate-400">Logo</span>
        </div>

        <div class="text-center flex-1 px-3">
          <h2 class="text-xl font-bold">{{ $page.props.webSetting?.company_name || 'Hospital Management System' }}</h2>
          <p class="text-xs text-slate-500">{{ $page.props.webSetting?.address || '' }}</p>
          <p class="text-xs text-slate-500">{{ $page.props.webSetting?.phone || '' }} {{ $page.props.webSetting?.email ? `| ${$page.props.webSetting?.email}` : '' }}</p>
          <h3 class="text-base font-semibold mt-2 uppercase tracking-wide">Goods Receive Note (GRN)</h3>
        </div>

        <div class="w-20 h-20 border rounded overflow-hidden flex items-center justify-center bg-white">
          <img v-if="qrDataUrl" :src="qrDataUrl" alt="Reference QR" class="max-w-full max-h-full object-contain" />
          <span v-else class="text-[10px] text-slate-400">QR</span>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-2 text-sm mb-4">
        <p><strong>GRN No:</strong> {{ grn.grn_no }}</p>
        <p><strong>Date:</strong> {{ formatDateTime(grn.receive_date) }}</p>
        <p><strong>Supplier:</strong> {{ grn.supplier_name || '-' }}</p>
        <p><strong>Invoice:</strong> {{ grn.invoice_no || '-' }}</p>
      </div>

      <table class="w-full text-sm border border-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-3 py-2 border">Item</th>
            <th class="px-3 py-2 border">Qty</th>
            <th class="px-3 py-2 border">Unit Cost</th>
            <th class="px-3 py-2 border">Line Total</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="line in grn.items" :key="line.id">
            <td class="px-3 py-2 border">{{ line.store_item?.item_name || '-' }}</td>
            <td class="px-3 py-2 border text-center">{{ Number(line.quantity).toFixed(2) }}</td>
            <td class="px-3 py-2 border text-right">{{ Number(line.unit_cost).toFixed(2) }}</td>
            <td class="px-3 py-2 border text-right">{{ Number(line.line_total).toFixed(2) }}</td>
          </tr>
        </tbody>
      </table>

      <div class="mt-3 text-right text-sm font-semibold">
        Total: {{ Number((grn.items || []).reduce((sum, row) => sum + Number(row.line_total || 0), 0)).toFixed(2) }}
      </div>

      <div class="grid grid-cols-2 gap-8 mt-8 text-sm">
        <div>
          <p class="border-t pt-1">Received By</p>
        </div>
        <div>
          <div class="h-12 flex items-end justify-center mb-1">
            <img v-if="signatureImage" :src="signatureImage" alt="Authorized Signature" class="max-h-12 object-contain" />
          </div>
          <p class="border-t pt-1 text-center">Store Manager / Accounts</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@page {
  size: A4;
  margin: 10mm;
}

.print-sheet {
  min-height: 277mm;
}
</style>
