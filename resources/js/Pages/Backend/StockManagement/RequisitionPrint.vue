<script setup>
import { computed, onMounted, ref } from 'vue';
import html2pdf from 'html2pdf.js';
import QRCode from 'qrcode';

const props = defineProps({
  pageTitle: { type: String, default: 'Requisition Slip' },
  requisition: { type: Object, required: true },
  isIssueSlip: { type: Boolean, default: false },
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
  const ref = props.requisition?.requisition_no ?? 'N/A';
  const dep = props.requisition?.department ?? '';
  return encodeURIComponent(`${ref} | ${dep}`);
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
      filename: `${props.isIssueSlip ? 'issue-slip' : 'requisition-slip'}-${props.requisition?.requisition_no || 'document'}.pdf`,
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: { scale: 2, useCORS: true },
      jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
    })
    .from(printableEl.value)
    .save();
};

const printNow = () => {
  window.print();
};

const goBack = () => {
  window.location.href = route('backend.stock.requisitions');
};

onMounted(() => {
  generateQrData();
});
</script>

<template>
  <div class="w-full p-4 mt-3 bg-white rounded shadow-md print:shadow-none">
    <div class="flex items-center justify-between mb-4 print:hidden">
      <h1 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h1>
      <div class="flex items-center gap-2">
        <button type="button" class="px-3 py-2 text-sm font-semibold text-white bg-sky-600 rounded hover:bg-sky-700" @click="printNow">Print</button>
        <button type="button" class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700" @click="downloadPdf">Download PDF</button>
        <button type="button" class="px-3 py-2 text-sm font-semibold text-white bg-gray-600 rounded hover:bg-gray-700" @click="goBack">Back</button>
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
          <h3 class="text-base font-semibold mt-2 uppercase tracking-wide">{{ isIssueSlip ? 'Store Issue Slip' : 'Department Requisition Slip' }}</h3>
        </div>

        <div class="w-20 h-20 border rounded overflow-hidden flex items-center justify-center bg-white">
          <img v-if="qrDataUrl" :src="qrDataUrl" alt="Reference QR" class="max-w-full max-h-full object-contain" />
          <span v-else class="text-[10px] text-slate-400">QR</span>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-2 text-sm mb-4">
        <p><strong>Requisition No:</strong> {{ requisition.requisition_no }}</p>
        <p><strong>Status:</strong> {{ requisition.status }}</p>
        <p><strong>Department:</strong> {{ requisition.department }}</p>
        <p><strong>Needed Date:</strong> {{ formatDateTime(requisition.needed_date) }}</p>
      </div>

      <table class="w-full text-sm border border-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-3 py-2 border">Item</th>
            <th class="px-3 py-2 border">Requested Qty</th>
            <th class="px-3 py-2 border">Issued Qty</th>
            <th class="px-3 py-2 border">Remarks</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="line in requisition.items" :key="line.id">
            <td class="px-3 py-2 border">{{ line.store_item?.item_name || '-' }}</td>
            <td class="px-3 py-2 border text-center">{{ Number(line.requested_qty).toFixed(2) }}</td>
            <td class="px-3 py-2 border text-center">{{ Number(line.issued_qty).toFixed(2) }}</td>
            <td class="px-3 py-2 border">{{ line.remarks || '-' }}</td>
          </tr>
        </tbody>
      </table>

      <div class="grid grid-cols-2 gap-8 mt-10 text-sm">
        <div>
          <p class="border-t pt-1">Requested By / Department In-Charge</p>
        </div>
        <div>
          <div class="h-12 flex items-end justify-center mb-1">
            <img v-if="signatureImage" :src="signatureImage" alt="Authorized Signature" class="max-h-12 object-contain" />
          </div>
          <p class="border-t pt-1 text-center">Store In-Charge / Approved By</p>
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
