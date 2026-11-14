<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from "vue";
import { progress, router, useForm, usePage } from "@inertiajs/vue3";
import axios from 'axios';
import InputLabel from "@/Components/InputLabel.vue";
import { Head } from "@inertiajs/vue3";
import PatientModal from "@/Components/PatientModal.vue";
import Modal from "@/Components/Modal.vue";
import PathologyForm from "@/Pages/Backend/PathologyTest/Form.vue";
import { displayResponse, displayWarning, successMessage } from "@/responseMessage.js";
import { debounce } from 'lodash';
import QRCode from 'qrcode';
import { parse, format, isValid, differenceInYears, differenceInMonths, differenceInDays, subYears, subMonths, subDays } from 'date-fns';
import {
  createOfflineRequestId,
  formatOfflineBillReference,
  clearOfflineBillingDraft,
  deleteOfflineBillingRequest,
  getOfflineBillingDraft,
  getOfflineBillingEncryptionStatus,
  getOfflineInvoiceDesignAssets,
  getOfflineBillingRequests,
  isOfflineBillingUnlocked,
  prepareOfflineBillingPage,
  saveOfflineBillingRequest,
  saveOfflineBillingDraft,
  syncOfflineBillingRequests,
} from '@/offlineBillingQueue.js';

// Silence console output for this module to avoid noisy logs during billing UI
const console = { log: () => {}, warn: () => {}, error: () => {} };

const props = defineProps({
  pageTitle: String,
  prescriptionSearchValue: {
    type: String,
    default: '',
  },
  pathologyAndRadiologyTests: {
    type: Array,
    default: () => [],
  },
  reagentTestAvailability: {
    type: Object,
    default: () => ({}),
  },
  hospitalCharges: {
    type: Array,
    default: () => [],
  },
  departmentStoreItems: {
    type: Array,
    default: () => [],
  },
  billingCatalogLoaded: {
    type: Boolean,
    default: false,
  },
  isPharmacyDepartment: {
    type: Boolean,
    default: false,
  },
  canAccessPharmacyBillList: {
    type: Boolean,
    default: false,
  },
  doctors: {
    type: Array,
    default: () => [],
  },
  patients: {
    type: Array,
    default: () => [],
  },
  id: [String, Number],
  editData: {
    type: Object,
    default: () => ({}),
  },
  referrers: {
    type: Array,
    default: () => [],
  },
  authInfo: {
    type: Object,
    default: () => ({}),
  },
  billing: {
    type: Object,
    default: () => ({}),
  },
  billingDoctors: {
    type: Array,
    default: () => [],
  },
  testCategories: {
    type: Array,
    default: () => [],
  },
  categoryTypeOptions: {
    type: Array,
    default: () => [],
  },
  mainCategories: {
    type: Array,
    default: () => [],
  },
  pathologyUnits: {
    type: Array,
    default: () => [],
  },
  testParameters: {
    type: Array,
    default: () => [],
  },
  chargeTypes: {
    type: Array,
    default: () => [],
  },
  chargeCategories: {
    type: Array,
    default: () => [],
  },
  chargeUnits: {
    type: Array,
    default: () => [],
  },
  taxCategories: {
    type: Array,
    default: () => [],
  },
});

const reagentAvailabilityForTest = (testId) => {
  const availability = props.reagentTestAvailability?.[String(testId)];
  return availability?.configured && availability.remaining_tests !== null
    ? Math.max(0, Number(availability.remaining_tests) || 0)
    : null;
};
const reagentStockItemsForTest = (testId) => (
  props.reagentTestAvailability?.[String(testId)]?.stock_items ?? []
);
const reagentCountUnitForCategory = (category, quantity) => {
  const normalized = String(category || '').toLowerCase().replace(/[^a-z]/g, '').replace(/test$/, '');

  if (['ultrasound', 'ultrasonogram', 'ultrasonography', 'usg'].includes(normalized)) return 'Paper';
  if (['radiology', 'xray', 'imaging'].includes(normalized)) return 'Film';
  return Number(quantity) === 1 ? 'Test' : 'Tests';
};
const reagentFilmSummaryForTest = (testId, testCategory) => {
  const availability = props.reagentTestAvailability?.[String(testId)];
  if (!availability?.film_category || Number(availability.film_usage_quantity) <= 0) return null;

  const category = availability.film_category;
  const quantityUsed = items.value
    .filter((item) => item.type === 'test' && item.reagentFilmCategory?.key === category.key)
    .reduce((total, item) => total + (Number(item.quantity) || 0), 0);
  const available = Number(category.available_quantity) || 0;

  return {
    unit: reagentCountUnitForCategory(testCategory, quantityUsed),
    remaining: Math.max(0, available - quantityUsed),
  };
};
const formatStockQuantity = (quantity) => Number(quantity ?? 0).toLocaleString(undefined, {
  maximumFractionDigits: 2,
});
const isReagentTrackedTestCategory = (category) => {
  const normalized = String(category || '').toLowerCase().replace(/[^a-z]/g, '').replace(/test$/, '');
  return ['pathology', 'radiology', 'imaging', 'ecg', 'ultrasound', 'xray', 'ultrasonogram', 'ultrasonography'].includes(normalized);
};
const isPharmacyBillingItem = (item) => (
  item?.type === 'medicine'
  || [item?.category, ...(item?.normalizedCategories || [])]
    .some((category) => ['medicine', 'pharmacy'].includes(normalizeForMatch(category)))
);
const canShowBillingItem = (item) => (
  props.isPharmacyDepartment ? isPharmacyBillingItem(item) : !isPharmacyBillingItem(item)
);

const page = usePage();
const requestedBillingDraftId = typeof window !== 'undefined'
  ? new URLSearchParams(window.location.search).get('billing_draft')
  : null;
const billingDraftId = requestedBillingDraftId
  && /^[\da-f]{8}-[\da-f]{4}-4[\da-f]{3}-[89ab][\da-f]{3}-[\da-f]{12}$/i.test(requestedBillingDraftId)
  ? requestedBillingDraftId
  : null;
const unitCompanyName = computed(() => (page.props.websetting ?? page.props.webSetting)?.company_name || 'ToaMed.');
const offlineInvoiceDesign = computed(() => page.props.offlineInvoiceDesign || {});
const isOfflineBillingDeveloper = computed(() => Boolean(page.props.auth?.admin?.is_developer));
const offlineAdminId = computed(() => Number(page.props.auth?.admin?.id || 0));
const isBrowserOnline = ref(typeof navigator === 'undefined' || navigator.onLine);
const offlineBillingRequests = ref([]);
const offlineBillingStorageError = ref('');
const offlineBillingCapabilityMessage = ref('');
const offlineBillingUnlocked = ref(false);
const pendingOfflineBillingRequests = computed(() => offlineBillingRequests.value.filter((item) => (
  item.status === 'queued' || item.status === 'syncing'
)));
const isOfflineBillingSyncing = computed(() => pendingOfflineBillingRequests.value.some(
  (entry) => entry.status === 'syncing'
));
const reviewOfflineBillingRequests = computed(() => offlineBillingRequests.value.filter((item) => item.status === 'needs_review'));
let billingSessionHeartbeatTimer = null;
let billingSessionHeartbeatPromise = null;

const refreshBillingSession = () => {
  if (!navigator.onLine) return Promise.resolve(null);
  if (billingSessionHeartbeatPromise) return billingSessionHeartbeatPromise;

  billingSessionHeartbeatPromise = (async () => {
    try {
      const response = await fetch(route('backend.session.heartbeat'), {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      if (response.status === 401 || response.redirected) {
        window.location.assign(response.url || route('backend.auth.login2'));
        return false;
      }
      if (!response.ok) return null;

      const payload = await response.json();
      const csrfToken = String(payload?.csrf_token || '');
      if (!csrfToken) return null;
      document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', csrfToken);
      return true;
    } catch (error) {
      console.warn('Billing session heartbeat failed; attempting to use the current session.', error);
      return null;
    } finally {
      billingSessionHeartbeatPromise = null;
    }
  })();

  return billingSessionHeartbeatPromise;
};

const refreshBillingSessionWhenVisible = () => {
  if (document.visibilityState === 'visible') refreshBillingSession();
};

const refreshOfflineBillingRequests = async () => {
  if (!offlineAdminId.value) return;
  try {
    offlineBillingUnlocked.value = isOfflineBillingUnlocked(offlineAdminId.value);
    offlineBillingRequests.value = await getOfflineBillingRequests(offlineAdminId.value);
    offlineBillingStorageError.value = '';
  } catch (error) {
    offlineBillingStorageError.value = error instanceof Error ? error.message : 'Offline billing queue could not be read.';
  }
};

const syncOfflineBillingQueue = async () => {
  isBrowserOnline.value = navigator.onLine;
  if (!isBrowserOnline.value || !offlineBillingUnlocked.value) return;
  try {
    await syncOfflineBillingRequests(
      offlineAdminId.value,
      document.querySelector('meta[name="csrf-token"]')?.content ?? ''
    );
  } finally {
    await refreshOfflineBillingRequests();
  }
};

const sendBillingRequestWithSessionRefresh = async (requestConfig) => {
  const send = () => axios({
    ...requestConfig,
    headers: {
      ...requestConfig.headers,
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
    },
  });

  try {
    return await send();
  } catch (error) {
    if (error?.response?.status === 401) {
      window.location.assign(route('backend.auth.login2'));
      return null;
    }
    if (error?.response?.status !== 419) throw error;

    const refreshed = await refreshBillingSession();
    if (refreshed === false) return null;
    if (!refreshed) throw error;
    return send();
  }
};

const removeReviewedOfflineRequest = async (requestId) => {
  try {
    await deleteOfflineBillingRequest(requestId);
    await refreshOfflineBillingRequests();
    if (navigator.onLine) syncOfflineBillingQueue();
  } catch (error) {
    offlineBillingStorageError.value = error instanceof Error ? error.message : 'Could not dismiss this request.';
  }
};

const handleBrowserOnline = () => {
  isBrowserOnline.value = true;
  syncOfflineBillingQueue();
};
const handleBrowserOffline = () => {
  isBrowserOnline.value = false;
};
const handleOfflineQueueChanged = () => refreshOfflineBillingRequests();
const handleOfflineSupportSetupFailed = (event) => {
  offlineBillingCapabilityMessage.value = event.detail?.message || 'Offline support could not be prepared.';
};
const handleOfflineUnlocked = async () => {
  offlineBillingUnlocked.value = true;
  const restoredDraft = await restoreOfflineBillingDraft();
  if (!restoredDraft) await restoreLegacyBillingItemsDraft();
  await nextTick();
  await prepareOfflineBillingPage(offlineAdminId.value, offlineInvoiceDesign.value);
  const draftSaved = await persistOfflineBillDraftDebounced.flush();
  if (draftSaved) {
    try {
      sessionStorage.removeItem(billingDraftStorageKey);
    } catch (error) {
      offlineBillingStorageError.value = error instanceof Error
        ? error.message
        : 'The old billing draft could not be removed from temporary browser storage.';
    }
  }
  await refreshOfflineBillingRequests();
  if (navigator.onLine) syncOfflineBillingQueue();
};
const handleOfflineLocked = () => {
  offlineBillingUnlocked.value = false;
  offlineBillingRequests.value = offlineBillingRequests.value.map((entry) => ({
    id: entry.id,
    status: entry.status,
    createdAt: entry.createdAt,
    label: 'Locked offline request',
    lastError: '',
  }));
};
const handleOfflineDataLocked = () => {
  handleOfflineLocked();
};
const refreshOfflineEncryptionStatus = async () => {
  if (!offlineAdminId.value) return;
  try {
    const status = await getOfflineBillingEncryptionStatus(offlineAdminId.value);
    offlineBillingUnlocked.value = status.unlocked;
    if (status.unlocked && navigator.onLine) {
      await prepareOfflineBillingPage(offlineAdminId.value, offlineInvoiceDesign.value);
    }
  } catch (error) {
    offlineBillingStorageError.value = error instanceof Error ? error.message : 'Could not read encryption settings.';
  }
};

const escapeReceiptText = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;',
}[character]));

const createOfflineBarcodeSvg = (value) => {
  const patterns = [
    '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
    '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
    '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
    '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
    '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
    '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
    '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
    '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
    '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
    '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
    '114131', '311141', '411131', '211412', '211214', '211232', '233111',
  ];
  const values = Array.from(String(value), (character) => character.charCodeAt(0) - 32);
  if (values.some((code) => code < 0 || code > 95)) return '';
  const checksum = values.reduce((sum, code, index) => sum + code * (index + 1), 104) % 103;
  const encoded = [patterns[104], ...values.map((code) => patterns[code]), patterns[checksum], patterns[106]];
  let x = 0;
  const bars = encoded.map((pattern) => Array.from(pattern, (width, index) => {
    const rect = index % 2 === 0
      ? `<div style="background-color:#000;width:${width}px;height:30px;position:absolute;left:${x}px;top:0">&nbsp;</div>`
      : '';
    x += Number(width);
    return rect;
  }).join('')).join('');
  const stopBar = `<div style="background-color:#000;width:1px;height:30px;position:absolute;left:${x}px;top:0">&nbsp;</div>`;
  x += 1;
  const scaleX = (100 / x).toFixed(6);
  return `<div class="barcode-image" role="img" aria-label="Offline bill reference barcode" style="font-size:0;position:relative;overflow:hidden;width:100px;height:21px"><div style="position:absolute;left:0;top:0;width:${x}px;height:30px;transform:scale(${scaleX},0.7);transform-origin:top left">${bars}${stopBar}</div></div>`;
};

const amountInWords = (amount) => {
  const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
    'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
  const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
  const convertHundreds = (value) => {
    let result = '';
    if (value >= 100) {
      result += `${ones[Math.floor(value / 100)]} Hundred `;
      value %= 100;
    }
    if (value >= 20) {
      result += tens[Math.floor(value / 10)];
      value %= 10;
      if (value) result += ` ${ones[value]}`;
    } else if (value) {
      result += ones[value];
    }
    return result.trim();
  };
  let value = Math.max(0, Math.floor(Number(amount) || 0));
  const parts = [];
  if (value >= 100000) {
    parts.push(`${convertHundreds(Math.floor(value / 100000))} Lakh`);
    value %= 100000;
  }
  if (value >= 1000) {
    parts.push(`${convertHundreds(Math.floor(value / 1000))} Thousand`);
    value %= 1000;
  }
  if (value) parts.push(convertHundreds(value));
  return `In Words: ${parts.join(' ') || 'Zero'} Only`;
};

const printOfflineProvisionalReceipt = async (receiptWindow, requestId, data) => {
  if (!receiptWindow || receiptWindow.closed) return;
  const invoiceDesign = offlineInvoiceDesign.value;
  const designAssets = await getOfflineInvoiceDesignAssets(invoiceDesign);
  const money = (value) => (Number(value) || 0).toFixed(2);
  const total = Number(data.total) || 0;
  const vat = Number(data.vat_amount) || 0;
  const extraDiscount = Number(data.extra_flat_discount) || 0;
  const payable = Number(data.payable_amount) || 0;
  const paid = Number(data.paid_amt) || 0;
  const discount = Math.max(0, total + vat - extraDiscount - payable);
  const discountLabel = String(data.discount_type || '').toLowerCase() === 'percentage'
    ? `Discount (${total > 0 ? ((discount / total) * 100).toFixed(2) : '0.00'}%)`
    : 'Flat Discount';
  const invoiceDateTime = [data.billing_date, data.billing_time].filter(Boolean).join(' ')
    || new Date().toLocaleString();
  const due = Math.max(0, Number(data.due_amount ?? payable - paid) || 0);
  const patientPhone = data.patient_phone || data.patient_mobile || 'N/A';
  const patientAge = data.patient_age || 'N/A';
  const patientGender = data.patient_gender || data.gender || 'N/A';
  const referrer = data.doctor_name || 'N/A';
  const preparedBy = page.props.auth?.admin?.name || '';
  const configuredHeaderHeight = Math.max(0, Number(invoiceDesign.header_height) || 115);
  const footerHeight = Math.max(0, Number(invoiceDesign.footer_height) || 70);
  const footerContentOffset = Math.min(50, Math.max(-50, Number(invoiceDesign.footer_content_offset) || 0));
  const footerContentPosition = ['above', 'below'].includes(String(invoiceDesign.footer_content_position || '').toLowerCase())
    ? String(invoiceDesign.footer_content_position).toLowerCase()
    : 'above';
  const fullPagePadOffset = Math.min(100, Math.max(0, Number(invoiceDesign.full_page_pad_content_offset) || 0));
  const headerImage = designAssets.header_image;
  const footerImage = designAssets.footer_image;
  const fullPagePadImage = invoiceDesign.active_pad_type === 'institution'
    ? designAssets.full_page_pad_image
    : '';
  const headerHeight = fullPagePadImage ? 0 : configuredHeaderHeight;
  const footerContent = String(invoiceDesign.footer_content || '')
    .replace(/<[^>]*>/g, ' ')
    .replace(/[\u200B-\u200D\uFEFF]/g, '')
    .replace(/রুম\s*(নং|নম্বর|নাম্বার)\s*/gu, 'রুম নং ')
    .replace(/প্যা\s*থা?\s*ল\s*জ[ীি]?/gu, 'প্যাথলজি')
    .replace(/[،٬﹐，]/g, ',')
    .split(',')
    .map((part) => part.trim())
    .filter(Boolean)
    .join(', ')
    .replace(/[,.;:!?।॥]+$/u, '');
  const footerDateParts = new Intl.DateTimeFormat('en-GB', {
    day: '2-digit', month: 'long', year: 'numeric',
    hour: '2-digit', minute: '2-digit', second: '2-digit',
    hour12: true, timeZone: 'Asia/Dhaka',
  }).formatToParts(new Date());
  const footerPart = (type) => footerDateParts.find((part) => part.type === type)?.value || '';
  const printedAt = `${footerPart('day')} ${footerPart('month')}, ${footerPart('year')} ${footerPart('hour')}:${footerPart('minute')}:${footerPart('second')} ${footerPart('dayPeriod').toLowerCase()}`;
  const footerFallbackLine = String(
    invoiceDesign.footer_fallback_line || 'Powered By: www.toamedit.com Support: 01919-592638'
  );
  const footerHtml = `<footer class="invoice-footer">
        <div class="footer-image-area">
          ${footerImage ? `<img class="footer-image" src="${escapeReceiptText(footerImage)}" alt="" onerror="this.style.display='none'">` : ''}
          ${footerContent ? `<div class="footer-content-area ${footerContentPosition}"><div class="footer-content">${escapeReceiptText(footerContent)}</div></div>` : ''}
        </div>
        <div class="footer-meta-row"><span>${escapeReceiptText(footerFallbackLine)}</span>
          <span>Printing Date &amp; Time: ${escapeReceiptText(printedAt)}</span></div>
      </footer>`;
  const headerHtml = headerImage
    ? `<img class="header-image" src="${escapeReceiptText(headerImage)}" alt="" onerror="this.style.display='none';this.nextElementSibling.style.visibility='hidden'"><div class="header-placeholder"></div>`
    : '<div class="header-placeholder"></div>';
  const items = data.items || [];
  const hasRoomNo = items.some((item) => String(item.room_no || '').trim() !== '');
  const medicineTotal = items
    .filter((item) => String(item.category || '').trim().toLowerCase() === 'medicine')
    .reduce((sum, item) => sum + (Number(item.total_amount) || 0), 0);
  const lines = (data.items || []).map((item, index) => `
    <tr>
      <td class="center">${index + 1}</td>
      <td class="test-col">${escapeReceiptText(item.name || item.item_name || 'Item')}</td>
      ${hasRoomNo ? `<td class="center">${escapeReceiptText(item.room_no || '')}</td>` : ''}
      <td class="center">${escapeReceiptText(item.quantity || 1)}</td>
      <td class="right">${money(item.total_amount ?? item.net_amount)}</td>
    </tr>
  `).join('');
  const patientName = data.patient_name || 'Walk-in Patient';
  const barcodeValue = formatOfflineBillReference(requestId);
  const barcode = createOfflineBarcodeSvg(barcodeValue);
  const offlineQrCode = await QRCode.toDataURL(barcodeValue, {
    errorCorrectionLevel: 'M',
    margin: 1,
    width: 160,
  });
  const formatInvoiceDate = (value, time = '', includeSeconds = true) => {
    const rawDate = String(value || '').trim();
    const match = rawDate.match(/^(\d{4})-(\d{2})-(\d{2})(?:[T\s](\d{2}):(\d{2})(?::(\d{2}))?)?$/);
    const timeMatch = String(time || '').match(/^(\d{2}):(\d{2})(?::(\d{2}))?/);
    const date = match
      ? new Date(Date.UTC(
        Number(match[1]),
        Number(match[2]) - 1,
        Number(match[3]),
        Number(match[4] ?? timeMatch?.[1] ?? 0),
        Number(match[5] ?? timeMatch?.[2] ?? 0),
        Number(match[6] ?? timeMatch?.[3] ?? 0),
      ))
      : new Date(rawDate);
    if (Number.isNaN(date.getTime())) return rawDate;
    const parts = new Intl.DateTimeFormat('en-GB', {
      day: '2-digit', month: 'short', year: 'numeric',
      hour: '2-digit', minute: '2-digit', second: includeSeconds ? '2-digit' : undefined,
      hour12: true, timeZone: match ? 'UTC' : 'Asia/Dhaka',
    }).formatToParts(date);
    const part = (type) => parts.find((entry) => entry.type === type)?.value || '';
    const timeParts = [part('hour'), part('minute')];
    if (includeSeconds) timeParts.push(part('second'));
    const dateTimeSeparator = includeSeconds ? ' ' : ', ';
    return `${part('day')}-${part('month')}-${part('year')}${dateTimeSeparator}${timeParts.join(':')} ${part('dayPeriod').toUpperCase()}`;
  };
  const rawInvoiceDateTime = data.billing_date && data.billing_time
    ? `${data.billing_date}T${data.billing_time}`
    : invoiceDateTime;
  const displayInvoiceDateTime = formatInvoiceDate(rawInvoiceDateTime, '', true);
  const deliveryDate = data.delivery_date
    ? `Delivery Date &amp; Time: ${escapeReceiptText(formatInvoiceDate(data.delivery_date, data.delivery_time, false))}`
    : '';
  const body = `<!doctype html><html lang="en"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Hospital Management System - Billing Invoice</title>
    <style>
      *{box-sizing:border-box}body{margin:0;padding:14px;font:17px/1.3 Arial,sans-serif;color:#111}
      .sheet{width:100%;border:1px solid #dadada;padding:10px}.header-wrap{height:${headerHeight}px;width:100%;
        text-align:center;margin:0;display:flex;align-items:center;justify-content:center;overflow:hidden}
      .header-placeholder{width:100%;height:100%;visibility:hidden}.header-image{width:100%;height:100%;object-fit:cover}
      .content-section{padding:0 5mm ${footerHeight + 6}px;${fullPagePadImage ? `padding-top:${(fullPagePadOffset * 0.75).toFixed(2)}mm` : ''}}
      .invoice-footer{position:fixed;bottom:0;left:0;right:0;height:${footerHeight + 28}px;text-align:center;pointer-events:none;z-index:9999}
      .footer-image-area{position:absolute;left:0;right:0;bottom:0;height:${footerHeight}px;overflow:visible}
      .footer-image{position:absolute;bottom:0;left:0;width:100%;height:${footerHeight}px;object-fit:contain;object-position:bottom center;z-index:0}
      .footer-content-area{position:absolute;left:0;right:0;z-index:2;transform:translateY(${-footerContentOffset}mm);
        display:flex;align-items:center;justify-content:center;padding:4px 6mm;white-space:nowrap;overflow:hidden}
      .footer-content-area.above{bottom:calc(${footerHeight}px - 5mm);justify-content:space-between}
      .footer-content-area.below{bottom:${Math.round(footerHeight * 0.08)}px}
      .footer-content{position:relative;z-index:1;width:100%;margin:0 auto;text-align:center;line-height:1.2;
        font-size:${Math.min(72, Math.max(6, Number(invoiceDesign.footer_font_size) || 14))}px}
      .footer-meta-row{position:absolute;left:0;right:0;bottom:0;z-index:2;display:flex;justify-content:space-between;
        gap:12px;padding:0 4mm;transform:translateY(${-footerContentOffset}mm);font-size:9px;line-height:1.15}
      .invoice-institution-pad{position:fixed;inset:0;width:100%;height:100%;object-fit:fill;z-index:-1}
      .title-wrap{width:100%;border-collapse:collapse;margin-bottom:12px}.title-wrap td{vertical-align:top}
      .barcode-cell-left{width:20%;text-align:left}.barcode-cell-right{width:20%;text-align:right}
      .title-cell-center{width:60%;text-align:center}.barcode-image{display:block}
      .receipt-title{font-size:20px;font-weight:bold;font-family:Verdana,Geneva,Tahoma,sans-serif;letter-spacing:2px}
      .meta{width:100%;border-collapse:collapse;table-layout:fixed;margin-bottom:10px;font-size:inherit}
      .meta td{padding:2px 6px;vertical-align:top}.meta .label{box-sizing:border-box;font-weight:bold;width:18%;white-space:nowrap;padding-right:0}
      .meta .colon{box-sizing:border-box;width:2%;padding-left:0;padding-right:0;text-align:center;vertical-align:top}
      .meta .value{box-sizing:border-box;width:30%;padding-left:6px;padding-right:6px;overflow-wrap:anywhere}
      .meta .bill-reference-text{display:block;max-width:100%;overflow:hidden;font:inherit;white-space:nowrap;letter-spacing:normal}
      .items-table{width:100%;border-collapse:collapse;margin:12px 0;font-size:inherit;border:1px solid #bdbdbd}
      .items-table th{border:1px solid #b8b8b8;padding:5px 3px;text-align:left;font-weight:bold;background:#f2f2f2}
      .items-table td{border:1px solid #c4c4c4;padding:4px;vertical-align:top}
      .items-table .sl-col{width:10%;text-align:center}.items-table .test-col{width:45%}.items-table .room-col{width:12%;text-align:center}
      .items-table .qty-col{width:10%;text-align:center}.items-table .price-col{width:15%;text-align:right}
      .items-table thead{display:table-header-group}.items-table tr{break-inside:avoid;page-break-inside:avoid}
      .center{text-align:center}.right{text-align:right}.delivery-date{margin:8px 0;font-weight:bold}
      .portal-qr{margin-top:8px;display:inline-block;text-align:left}.portal-qr img{width:92px;height:92px;background:#fff;image-rendering:pixelated}
      .summary-wrap{width:100%;margin-top:12px;border-collapse:collapse}.summary-wrap td{width:50%;padding:0;vertical-align:top;border:0}
      .summary-left{padding-right:15px!important}.summary-right{padding-left:15px!important}
      .status-badge{display:inline-block;background:${due > 0 ? '#ff4444' : '#28a745'};color:#fff;font-weight:bold;
        padding:8px 16px;margin-bottom:8px;font-size:32px}.thanks{margin:8px 0}.prepared-by{margin-top:8px}
      .totals{width:100%;border-collapse:collapse;font-size:inherit}.totals td{border-bottom:1px solid #c3c3c3;padding:2px 4px}
      .totals td:first-child{width:60%}.totals td:last-child{text-align:right;width:40%}
      .amount-words{padding-top:4px;text-align:center;font-weight:bold;font-size:16px;line-height:1.2}
      .reference{font-size:inherit;color:#333;margin-top:5px}
      @media screen{body{background:#f5f7fa}.sheet{background:#fff;max-width:900px;margin:12px auto;
        box-shadow:0 2px 8px #00000014}}
      @page{size:auto;margin:5mm}
      @media print{body{margin:0!important;padding:0!important;font-size:17px!important;
        -webkit-print-color-adjust:exact;print-color-adjust:exact}
        .sheet{width:100%;border:0;padding:0}
        .header-wrap{height:${headerHeight}px}.content-section{padding:0 5mm ${footerHeight + 6}px;
          ${fullPagePadImage ? `padding-top:${(fullPagePadOffset * 0.75).toFixed(2)}mm` : ''}}
        .invoice-institution-pad{position:fixed}
      }
      @media print and (max-height:8.3in) and (max-width:5.8in){
        body{font-size:11px!important;margin:0!important;padding:0!important}
        .receipt-title{font-size:12px!important}.header-wrap{height:${headerHeight}px!important;margin:0!important}
        .header-image{object-fit:fill!important}
        .content-section{padding:0 5mm!important;padding-bottom:${Math.max(24, footerHeight + 2)}px!important}
        ${fullPagePadImage ? `.sheet.with-institution-pad .content-section{padding-top:${(fullPagePadOffset * 0.75).toFixed(2)}mm!important}` : ''}
        .items-table,.totals{font-size:11px!important}.items-table{margin:8px 0!important}
        .items-table th{padding:1px 2px!important;background:#f4f4f4!important}
        .items-table td{padding:2px!important;line-height:1.15!important}
        .title-wrap{margin-bottom:4px!important}.status-badge{font-size:22px!important;padding:4px 8px!important;margin-bottom:4px!important}
        .portal-qr img{width:52px!important;height:52px!important}
        .amount-words{font-size:11px!important;margin-top:1px!important;padding-top:1px!important;line-height:1.1!important}
        .delivery-date{margin:2px 0!important;font-size:11px!important}.summary-wrap{margin-top:2px!important}
        .summary-wrap td{padding:0!important}.thanks{margin:2px 0!important}.prepared-by{margin-top:2px!important;font-size:11px!important}
        .totals td{padding:1px 2px!important}.invoice-footer{height:${footerHeight + 28}px!important}
        .footer-image-area,.footer-image{height:${footerHeight}px!important}
        .footer-content{font-size:clamp(6px,1.5vw,${Math.min(12, Math.max(6, Number(invoiceDesign.footer_font_size) || 14))}px)!important}
        .footer-meta-row{font-size:clamp(6px,1.2vw,9px)!important}
        .footer-meta-row{font-size:clamp(6px,1.5vw,9px)!important}
      }
    </style></head><body>
    ${fullPagePadImage ? `<img class="invoice-institution-pad" src="${escapeReceiptText(fullPagePadImage)}" alt="" onerror="this.style.display='none'">` : ''}
    <main class="sheet${fullPagePadImage ? ' with-institution-pad' : ''}" role="document">
      <div class="header-wrap">${headerHtml}</div>
      <div class="content-section">
      <table class="title-wrap"><tr><td class="barcode-cell-left">${barcode}</td>
        <td class="title-cell-center"><div class="receipt-title">MONEY RECEIPT</div></td>
        <td class="barcode-cell-right">${barcode}</td></tr></table>
      <table class="meta">
        <tr>
          <td class="label">Bill No</td><td class="colon">:</td><td class="value bill-reference"><span class="bill-reference-text">${escapeReceiptText(barcodeValue)}</span></td>
          <td class="label">Date &amp; Time</td><td class="colon">:</td><td class="value">${escapeReceiptText(displayInvoiceDateTime)}</td>
        </tr>
        <tr>
          <td class="label">Patient Name</td><td class="colon">:</td><td class="value">${escapeReceiptText(patientName)}</td>
          <td class="label">Age</td><td class="colon">:</td><td class="value">${escapeReceiptText(patientAge)}</td>
        </tr>
        <tr>
          <td class="label">Gender</td><td class="colon">:</td><td class="value">${escapeReceiptText(patientGender)}</td>
          <td class="label">Phone</td><td class="colon">:</td><td class="value">${escapeReceiptText(patientPhone)}</td>
        </tr>
        <tr>
          <td class="label">Refd. By</td><td class="colon">:</td><td class="value" colspan="4">${escapeReceiptText(referrer)}</td>
        </tr>
      </table>
      <table class="items-table">
        <thead><tr><th class="center" style="width:10%">SL</th><th class="test-col">Item Name</th>
          ${hasRoomNo ? '<th class="room-col">Room No</th>' : ''}
          <th class="qty-col">Qty</th><th class="price-col">Price (Tk.)</th></tr></thead>
        <tbody>${lines || `<tr><td class="center">-</td><td>No items found</td>${hasRoomNo ? '<td></td>' : ''}<td class="center">-</td><td class="right">0.00</td></tr>`}</tbody>
      </table>
      ${!props.isPharmacyDepartment && deliveryDate ? `<div class="delivery-date">${deliveryDate}</div>` : ''}
      <table class="summary-wrap"><tr>
        <td class="summary-left"><div class="due-section">
          ${data.remarks ? `<div class="reference"><strong>Remarks:</strong> ${escapeReceiptText(data.remarks)}</div>` : ''}
          <div class="status-badge">${due > 0 ? 'DUE' : 'PAID'}</div><div class="thanks">Thank You</div></div>
          <div class="prepared-by"><strong>Prepared By:</strong> ${escapeReceiptText(preparedBy)}</div>
          <div class="portal-qr"><img src="${offlineQrCode}" alt="Offline bill reference QR"></div></td>
        <td class="summary-right"><table class="totals">
          <tr><td><strong>Total Amount Tk.</strong></td><td><strong>${money(total)}</strong></td></tr>
          ${medicineTotal > 0 ? `<tr><td>Medicine Bill</td><td>${money(medicineTotal)}</td></tr>` : ''}
          ${vat !== 0 ? `<tr><td>Vat Tk.</td><td>${money(vat)}</td></tr>
            <tr><td><strong>Total With VAT Tk.</strong></td><td><strong>${money(total + vat)}</strong></td></tr>` : ''}
          ${discount > 0 ? `<tr><td>${escapeReceiptText(discountLabel)}</td><td>${money(discount)}</td></tr>` : ''}
          ${extraDiscount > 0 ? `<tr><td>Extra Discount Tk.</td><td>${money(extraDiscount)}</td></tr>` : ''}
          <tr><td><strong>Net Payable Tk.</strong></td><td><strong>${money(payable)}</strong></td></tr>
          ${paid > 0 ? `<tr><td>Paid (Invoice Time)</td><td>${money(paid)}</td></tr>` : ''}
          ${paid > 0 ? `<tr><td>Total Paid Tk.</td><td>${money(paid)}</td></tr>` : ''}
          ${Number(data.return_amt) > 0 ? `<tr><td>Return Amount Tk.</td><td>${money(data.return_amt)}</td></tr>` : ''}
          <tr><td><strong>Due Tk.</strong></td><td><strong>${money(due)}</strong></td></tr>
        </table></td>
      </tr><tr><td colspan="2" class="amount-words">${escapeReceiptText(amountInWords(payable))}</td></tr></table>
      </div>
    </main>
    ${footerHtml}
    <script>
      (() => {
        let printStarted = false;
        let closeStarted = false;
        const closeAfterPrint = () => {
          if (closeStarted) return;
          closeStarted = true;
          setTimeout(() => {
            try { window.open('', '_self'); } catch (error) {}
            try { window.close(); } catch (error) {}
          }, 150);
        };
        const startPrint = () => {
          if (printStarted) return;
          printStarted = true;
          try { window.focus(); } catch (error) {}
          try { window.print(); } catch (error) { closeAfterPrint(); }
        };
        window.addEventListener('afterprint', closeAfterPrint, { once: true });
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
          setTimeout(startPrint, 600);
          setTimeout(startPrint, 1400);
        } else {
          window.addEventListener('load', () => {
            setTimeout(startPrint, 600);
            setTimeout(startPrint, 1400);
          }, { once: true });
        }
      })();
    </scr${'ipt'}>
    </body></html>`;
  receiptWindow.document.open();
  receiptWindow.document.write(body);
  receiptWindow.document.close();
};
const showPharmacyProductReturn = computed(() => (
  props.isPharmacyDepartment
  || normalizeForMatch(props.authInfo?.department?.name).includes('pharmacy')
));
const hasOpenCashCounterSession = computed(() => String(page.props?.cashCounterInfo?.session?.status || '').toLowerCase() === 'open');
const activeCashCounterSessionId = computed(() => Number(page.props?.cashCounterInfo?.session?.id || 0) || null);
const cashCounterExpectedAmount = computed(() => {
  const raw = Number(
    page.props?.cashCounterInfo?.drawer_expected_amount
    ?? page.props?.cashCounterInfo?.expected_amount
    ?? 0
  );
  return Number.isFinite(raw) ? raw : 0;
});
const cashCounterStartBusy = ref(false);
const showCounterStartModal = ref(false);
const counterStartForm = ref({
  openingAmount: '',
  note: 'Carry forward from previous shift',
});
const cashCounterCloseBusy = ref(false);
const showCounterCloseModal = ref(false);
const counterCloseForm = ref({
  closingAmount: '',
  note: 'Closed from Billing page quick action',
});
const cashCounterPrintUrl = computed(() => page.props.flash?.cashCounterPrintUrl || '');
const websetting = computed(() => page.props.websetting ?? page.props.webSetting ?? {});
const maxBillingDiscountPercent = computed(() => {
  const raw = Number(websetting.value?.max_billing_discount_percent ?? 100);
  if (!Number.isFinite(raw)) return 100;
  return Math.min(100, Math.max(0, raw));
});

const maxAllowedFlatDiscountAmount = computed(() => {
  const total = Number(summary.value.total) || 0;
  const vatAmount = billingVatEnabled.value && billingVatPercent.value > 0
    ? (total * billingVatPercent.value) / 100
    : 0;
  const totalWithVat = total + vatAmount;
  const globalLimit = (totalWithVat * Number(maxBillingDiscountPercent.value || 0)) / 100;
  const itemDiscountCap = items.value.reduce((sum, item) => {
    if (isDisposableBillingItem(item) || isDiscountExemptBillingItem(item)) {
      return sum;
    }

    const itemTotal = Number(item.totalAmount || 0);
    if (itemTotal <= 0) return sum;

    const itemPercent = Math.min(100, Math.max(0, Number(item.discountPercentage || 0)));
    const isLocked = Boolean(item.discountRateLocked || item.discount_rate_locked);
    if (isLocked && itemPercent <= 0) {
      return sum;
    }
    const categoryCap = resolveCategoryDiscountCap(item.category);
    const effectivePercent = isLocked
      ? itemPercent
      : itemPercent > 0
        ? itemPercent
        : (categoryDiscountCapEnabled.value && categoryCap !== null ? categoryCap : maxBillingDiscountPercent.value);

    return sum + (itemTotal * effectivePercent) / 100;
  }, 0);

  if (!categoryDiscountCapEnabled.value) {
    return Math.max(0, Math.min(globalLimit, itemDiscountCap));
  }

  const categoryLimit = items.value.reduce((sum, item) => {
    if (isDisposableBillingItem(item)) {
      return sum;
    }

    const categoryCap = resolveCategoryDiscountCap(item.category);
    if (categoryCap === null) {
      return sum;
    }

    return sum + Math.min(Number(item.totalAmount || 0), (Number(item.totalAmount || 0) * categoryCap) / 100);
  }, 0);

  return Math.max(0, Math.min(globalLimit, categoryLimit || globalLimit, itemDiscountCap));
});

const effectiveBillingDiscountPercentLimit = computed(() => {
  const globalCap = Number(maxBillingDiscountPercent.value) || 0;

  if (!categoryDiscountCapEnabled.value) {
    return globalCap;
  }

  const eligibleItems = items.value.filter(
    (item) => !isDisposableBillingItem(item) && !isDiscountExemptBillingItem(item)
  );

  if (!eligibleItems.length) {
    return globalCap;
  }

  let weightedTotal = 0;
  let totalWeight = 0;

  eligibleItems.forEach((item) => {
    const categoryCap = resolveCategoryDiscountCap(item.category);
    if (categoryCap === null || !Number.isFinite(categoryCap)) {
      return;
    }

    const itemWeight = Number(item.totalAmount || 0);
    if (itemWeight <= 0) {
      return;
    }

    weightedTotal += categoryCap * itemWeight;
    totalWeight += itemWeight;
  });

  if (totalWeight <= 0) {
    return globalCap;
  }

  const weightedAverageCap = weightedTotal / totalWeight;
  return Math.max(0, Math.min(globalCap, weightedAverageCap));
});

const categoryDiscountCapConfig = computed(() => {
  const source = websetting.value?.attendance_device_options ?? {};
  const caps = source?.billing_category_discount_caps ?? {};
  return caps && typeof caps === 'object' ? caps : {};
});
const categoryDiscountCapEnabled = computed(() => Boolean(
  websetting.value?.attendance_device_options?.billing_discount_cap_by_category_enabled
  ?? false
));
const categoryRoomNumberConfig = computed(() => {
  const source = websetting.value?.attendance_device_options ?? {};
  const roomNumbers = source?.billing_category_room_numbers ?? {};
  return roomNumbers && typeof roomNumbers === 'object' ? roomNumbers : {};
});
const categoryRoomNumberEnabled = computed(() => Boolean(
  websetting.value?.attendance_device_options?.billing_room_no_by_category_enabled
  ?? false
));
const resolveCategoryRoomNumber = (category, fallback = '') => {
  const rawCategory = String(category ?? '').trim();
  if (!rawCategory || !categoryRoomNumberEnabled.value) {
    return fallback;
  }

  const roomNumbers = categoryRoomNumberConfig.value;
  const matchingKey = Object.keys(roomNumbers).find((key) => key.toLowerCase() === rawCategory.toLowerCase());
  const configuredRoomNumber = matchingKey ? String(roomNumbers[matchingKey] ?? '').trim() : '';
  return configuredRoomNumber || fallback;
};
const resolveCategoryDiscountCap = (category) => {
  const rawCategory = String(category ?? '').trim();
  if (!rawCategory || !categoryDiscountCapEnabled.value) {
    return null;
  }

  const caps = categoryDiscountCapConfig.value || {};
  const directValue = caps[rawCategory];
  if (directValue !== undefined && directValue !== null && directValue !== '') {
    const parsed = Number(directValue);
    if (Number.isFinite(parsed)) {
      return Math.min(100, Math.max(0, parsed));
    }
  }

  const equivalentKey = Object.keys(caps).find((key) => key.toLowerCase() === rawCategory.toLowerCase());
  if (!equivalentKey) {
    return null;
  }

  const parsed = Number(caps[equivalentKey]);
  if (!Number.isFinite(parsed)) {
    return null;
  }

  return Math.min(100, Math.max(0, parsed));
};
const billingVatEnabled = computed(() => Boolean(websetting.value?.vat_enabled ?? false));
const billingVatPercent = computed(() => {
  const raw = Number(websetting.value?.vat_percent ?? 0);
  return Number.isFinite(raw) ? Math.max(0, raw) : 0;
});
const totalNetIncomeValue = computed(() => {
  if (props.isPharmacyDepartment) {
    const pharmacyIncome = Number(page.props?.dashboardPharmacyIncome ?? 0);
    return Number.isFinite(pharmacyIncome) ? pharmacyIncome : 0;
  }

  const raw = Number(
    page.props?.dashboardNetIncome
    ?? page.props?.cashCounterInfo?.expected_amount
    ?? page.props?.cashCounterInfo?.totals?.total_collection
    ?? page.props?.total_net_income
    ?? 0
  );
  return Number.isFinite(raw) ? raw : 0;
});
const formattedTotalNetIncome = computed(() => {
  try {
    return new Intl.NumberFormat('en-BD', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(totalNetIncomeValue.value);
  } catch (e) {
    return Number(totalNetIncomeValue.value || 0).toFixed(2);
  }
});

const openNetIncomeReport = () => {
  try {
    const url = route(props.isPharmacyDepartment ? 'backend.pharmacy.profit-loss.report' : 'backend.report.index');
    if (url) {
      window.open(url, '_blank');
      return;
    }
  } catch (e) {
    // ignore and use fallback
  }
  window.open('/backend/report', '_blank');
};

const goBack = () => {
  if (window.history.length > 1) {
    progress.start();
    window.setTimeout(() => progress.finish(), 800);
    window.history.back();
    return;
  }
  router.visit(route('backend.dashboard'));
};

const reloadNetIncomeValue = () => {
  try {
    router.reload({
      only: ['dashboardNetIncome', 'dashboardPharmacyIncome', 'reagentTestAvailability'],
      preserveState: true,
      preserveScroll: true,
    });
  } catch (e) {
    // silent fallback
  }
};

const handleDashboardStorageRefresh = (event) => {
  if (event?.key === 'dashboard:refresh') reloadNetIncomeValue();
};

// Invoice preview windows close themselves after printing/canceling. The billing
// page must stay open, so do not close this tab when the invoice window emits the
// same storage event.
const handleCloseInvoiceTabsStorage = (event) => {
  try {
    if (event?.key === 'billing:close_invoice_tabs') {
      // Intentional no-op: the invoice tab closes itself, and the billing page must
      // remain open for the user to continue editing or creating another bill.
    }
  } catch (e) { /* ignore */ }
};

const handleDashboardSameTabRefresh = () => {
  reloadNetIncomeValue();
};

const handleBillingWindowFocus = () => {
  reloadNetIncomeValue();
};

const isPatientModalOpen = ref(false);
const patientsList = ref([...props.patients]);

// Refs for form fields to enable Enter key navigation
const patientSearchRef = ref(null);
const doctorSearchRef = ref(null);
const patientMobileRef = ref(null);
const genderSelectRef = ref(null);
const showGenderDropdown = ref(false);
const genderSelectedIndex = ref(-1);
const genderOptions = [
  { value: 'Male', label: 'Male' },
  { value: 'Female', label: 'Female' },
  { value: 'Others', label: 'Others' }
];
const payModeRef = ref(null);
const genderWrapperRef = ref(null);
const patientMobileWrapperRef = ref(null);
const cardNumberRef = ref(null);
const discountRef = ref(null);
const extraDiscountRef = ref(null);
const receivingAmtRef = ref(null);
const deliveryDateRef = ref(null);
const deliveryTimeRef = ref(null);
const billingDateRef = ref(null);
const billingTimeRef = ref(null);
const remarksRef = ref(null);
const referrerSelectRef = ref(null);
const referrerSearch = ref("");
const showReferrerDropdown = ref(false);
const filteredReferrers = ref([]);
const highlightedRefIndex = ref(0);
const referrerJustSelected = ref(false);
const normalizeReferrerMatchText = (value) => String(value ?? '')
  .toLowerCase()
  .replace(/^(dr|doctor|prof|professor)\.?\s*/g, '')
  .replace(/\b(mbbs|mbs|bcs|fcps|frcs|ms|md|mds|mph|mphil|mr|mrs|miss|phd)\b/gi, '')
  .replace(/[^a-z0-9]+/g, ' ')
  .replace(/\s+/g, ' ')
  .trim();
const referrerTextMatchesSelection = (inputValue, referrer) => {
  if (!referrer) return false;
  const rawInput = String(inputValue ?? '').trim();
  if (!rawInput) return false;

  const targetCandidates = [referrer.name, referrer.phone].filter(Boolean);
  return targetCandidates.some((candidate) => {
    const normalizedInput = normalizeReferrerMatchText(rawInput);
    const normalizedCandidate = normalizeReferrerMatchText(candidate);

    if (!normalizedInput || !normalizedCandidate) {
      return false;
    }

    return normalizedInput === normalizedCandidate
      || normalizedInput.startsWith(normalizedCandidate)
      || normalizedCandidate.startsWith(normalizedInput);
  });
};
const resolveSelectedReferrer = () => {
  if (!commission.value.referrer_id) return null;
  return (props.referrers || []).find((referrer) => String(referrer.id) === String(commission.value.referrer_id)) || null;
};
const syncReferrerSelectionState = () => {
  const selectedReferrer = resolveSelectedReferrer();

  if (!selectedReferrer) {
    if (commission.value.referrer_id) {
      commission.value.referrer_id = '';
      referrerSearch.value = '';
    }
    commission.value.commissionRate = 0;
    commission.value.slider = 0;
    resetCommissionDetails();
    updateCommission();
    return;
  }

  const nextDisplayName = String(selectedReferrer.name || '').trim();
  if (nextDisplayName && !referrerTextMatchesSelection(referrerSearch.value, selectedReferrer)) {
    referrerSearch.value = nextDisplayName;
  }

  updateCommissionRate(selectedReferrer);
};
const commissionSliderRef = ref(null);
const savePrintButtonRef = ref(null);
const submitOnNextReferrerEnter = ref(false);
const ageYears = ref('');
const ageMonths = ref('');
const ageDays = ref('');
const ageYearsInput = ref(null);
const ageMonthsInput = ref(null);
const ageDaysInput = ref(null);
const dobInput = ref(null);
const updatingFrom = ref(null);
const isEditMode = ref(false);
const isInitializingEditMode = ref(false);

const doctorSearchQuery = ref("");
const doctorSelectedIndex = ref(-1);
const showDoctorDropdown = ref(false);
const filteredDoctors = ref([]);
const isDoctorLoading = ref(false);
const isNewPatientFlag = ref(false);

// Initialize all reactive variables first
const patientSearchQuery = ref("");
const patientSelectedIndex = ref(-1);
const showPatientDropdown = ref(false);
const filteredPatients = ref([]);
const patientMobileSuggestions = ref([]);
const showPatientMobileDropdown = ref(false);
const patientMobileSelectedIndex = ref(-1);
const isNewPatient = ref(false);
const newPatientForm = ref({
  name: "",
  phone: "",
  gender: "",
  dob: "",
});

const itemForm = ref({
  category: "",
  itemName: "",
  itemId: null,
  unitPrice: 0,
  quantity: 1.0,
  totalAmount: 0.0,
  discountExempt: false,
});

// Remember last non-empty category to guard against accidental clears
const lastSelectedCategory = ref('');
let _restoringCategory = false;

const patientForm = ref({
  patient_id: "",
  patientMobile: "",
  gender: "",
  dob: "",
  cardType: "Cash",
  payMode: "Cash",
  cardNumber: "",
});

const createDefaultSummaryState = (overrides = {}) => ({
  total: 0,
  discount: 0,
  discountType: "flat",
  extraFlatDiscount: 0,
  vatPercentage: 0,
  vatAmount: 0,
  payableAmount: 0,
  paidAmt: 0,
  changeAmt: 0.0,
  dueAmount: 0.0,
  receivingAmt: 0.0,
  returnAmt: 0.0,
  deliveryDate: "",
  deliveryTime: "",
  remarks: "",
  ...overrides,
});

const summary = ref(createDefaultSummaryState());

const billingDate = ref("");
const billingTime = ref("");
const billingDateTouched = ref(false);
const billingEditing = ref(false);
const billingLiveText = ref("");

// Reactive clock ticker to force re-evaluation every second
const clockNow = ref(Date.now());
let billingClockTimer = null;

// Computed display that updates every second (depends on `clockNow`)
const billingLiveDisplay = computed(() => {
  try {
    if (billingDate.value && billingTime.value) {
      const selectedDateTime = new Date(`${billingDate.value}T${billingTime.value}`);
      if (isValid(selectedDateTime)) {
        return format(selectedDateTime, 'dd-MMM-yyyy hh:mm:ss a');
      }
    }

    if (billingLiveText.value) {
      return billingLiveText.value;
    }

    return format(new Date(clockNow.value), 'dd-MMM-yyyy hh:mm:ss a');
  } catch (e) {
    return '';
  }
});
const prescriptionSearchId = ref('');
const prescriptionIpdPatientId = ref(null);
const prescriptionIpdPrescriptionId = ref(null);
const prescriptionSearchLoading = ref(false);
const prescriptionSuggestions = ref([]);
const prescriptionSuggestionLoading = ref(false);
const showPrescriptionSuggestions = ref(false);
const prescriptionSearchInputRef = ref(null);
let billingLiveTimer = null;
let invoiceMonitorTimer = null;

const deliveryDateTouched = ref(false);
let deliveryLiveTimer = null;

const commission = ref({
  total: 0.0,
  physystAmt: 0.0,
  slider: 0,
  referrer_id: "",
  commissionRate: 0,
});

const items = ref([]);
const reagentStockAlerts = computed(() => {
  const selectedTests = new Map();

  for (const item of items.value) {
    if (!isReagentTrackedTestCategory(item.category)) continue;
    const available = reagentAvailabilityForTest(item.id);
    if (available === null) continue;

    const key = `${String(item.category).toLowerCase()}:${item.id}`;
    const current = selectedTests.get(key) || {
      id: item.id,
      name: item.name,
      available,
      quantity: 0,
    };
    current.quantity += Number(item.quantity) || 0;
    selectedTests.set(key, current);
  }

  return [...selectedTests.values()]
    .map((test) => ({
      ...test,
      remaining: Math.max(0, test.available - test.quantity),
    }))
    .filter((test) => test.remaining < 20);
});
const billingDraftStorageKey = 'billing-page-draft-items';
let persistOfflineBillDraftDebounced;

const clearBillingItemsDraft = () => {
  try {
    sessionStorage.removeItem(billingDraftStorageKey);
  } catch (error) {
    offlineBillingStorageError.value = error instanceof Error
      ? error.message
      : 'The old temporary billing draft could not be removed.';
  }
  if (offlineBillingUnlocked.value) {
    clearOfflineBillingDraft(offlineAdminId.value, billingDraftId).catch((error) => {
      offlineBillingStorageError.value = error instanceof Error ? error.message : 'The encrypted draft could not be cleared.';
    });
  }
};

const restoreOfflineBillingDraft = async () => {
  if (props.id || !offlineAdminId.value || !offlineBillingUnlocked.value) return false;
  try {
    const draft = await getOfflineBillingDraft(offlineAdminId.value, billingDraftId);
    if (!draft) return false;
    if (draft?.version !== 1 || !Array.isArray(draft.items)) return false;

    items.value = draft.items;
    if (draft.patientForm && typeof draft.patientForm === 'object') {
      patientForm.value = { ...patientForm.value, ...draft.patientForm };
    }
    if (draft.newPatientForm && typeof draft.newPatientForm === 'object') {
      newPatientForm.value = { ...newPatientForm.value, ...draft.newPatientForm };
    }
    if (draft.summary && typeof draft.summary === 'object') {
      summary.value = createDefaultSummaryState(draft.summary);
    }
    if (draft.commission && typeof draft.commission === 'object') {
      commission.value = { ...commission.value, ...draft.commission };
    }

    patientSearchQuery.value = String(draft.patientSearchQuery || '');
    doctorSearchQuery.value = String(draft.doctorSearchQuery || '');
    referrerSearch.value = String(draft.referrerSearch || '');
    isNewPatient.value = Boolean(draft.isNewPatient);
    ageYears.value = String(draft.ageYears || '');
    ageMonths.value = String(draft.ageMonths || '');
    ageDays.value = String(draft.ageDays || '');
    billingDate.value = String(draft.billingDate || '');
    billingTime.value = String(draft.billingTime || '');
    billingDateTouched.value = Boolean(draft.billingDateTouched);
    deliveryDateTouched.value = Boolean(draft.deliveryDateTouched);
    prescriptionSearchId.value = String(draft.prescriptionSearchId || '');
    prescriptionIpdPatientId.value = draft.prescriptionIpdPatientId || null;
    prescriptionIpdPrescriptionId.value = draft.prescriptionIpdPrescriptionId || null;

    nextTick(() => updateSummary());
    return true;
  } catch (error) {
    offlineBillingStorageError.value = error instanceof Error ? error.message : 'The encrypted billing draft could not be restored.';
    return false;
  }
};

const restoreLegacyBillingItemsDraft = async () => {
  if (props.id || billingDraftId || !offlineAdminId.value || !offlineBillingUnlocked.value) return false;

  try {
    const savedItems = sessionStorage.getItem(billingDraftStorageKey);
    if (!savedItems) return false;

    const parsedItems = JSON.parse(savedItems);
    if (!Array.isArray(parsedItems) || parsedItems.length === 0) return false;

    items.value = parsedItems;
    await nextTick();
    const saved = await persistOfflineBillDraftDebounced.flush();
    if (!saved) {
      throw new Error('The previous billing draft could not be encrypted.');
    }
    sessionStorage.removeItem(billingDraftStorageKey);
    nextTick(() => updateSummary());
    return true;
  } catch (error) {
    offlineBillingStorageError.value = error instanceof Error
      ? error.message
      : 'The previous billing draft could not be migrated to encrypted storage.';
    return false;
  }
};

persistOfflineBillDraftDebounced = debounce(() => {
  if (props.id || !offlineAdminId.value || !offlineBillingUnlocked.value) return;

  const hasDraftContent = items.value.length > 0
    || Boolean(patientForm.value.patient_id || patientForm.value.patientMobile)
    || Boolean(newPatientForm.value.name || newPatientForm.value.phone)
    || Boolean(patientSearchQuery.value || doctorSearchQuery.value || referrerSearch.value)
    || Boolean(summary.value.discount || summary.value.extraFlatDiscount || summary.value.paidAmt || summary.value.remarks)
    || Boolean(prescriptionIpdPatientId.value);

  if (!hasDraftContent) {
    return clearOfflineBillingDraft(offlineAdminId.value, billingDraftId).then(() => true).catch((error) => {
      offlineBillingStorageError.value = error instanceof Error ? error.message : 'The old billing draft could not be cleared.';
      return false;
    });
  }

  return saveOfflineBillingDraft(offlineAdminId.value, {
      version: 1,
      savedAt: new Date().toISOString(),
      items: items.value,
      patientForm: patientForm.value,
      newPatientForm: newPatientForm.value,
      patientSearchQuery: patientSearchQuery.value,
      doctorSearchQuery: doctorSearchQuery.value,
      referrerSearch: referrerSearch.value,
      isNewPatient: isNewPatient.value,
      summary: summary.value,
      commission: commission.value,
      ageYears: ageYears.value,
      ageMonths: ageMonths.value,
      ageDays: ageDays.value,
      billingDate: billingDate.value,
      billingTime: billingTime.value,
      billingDateTouched: billingDateTouched.value,
      deliveryDateTouched: deliveryDateTouched.value,
      prescriptionSearchId: prescriptionSearchId.value,
      prescriptionIpdPatientId: prescriptionIpdPatientId.value,
      prescriptionIpdPrescriptionId: prescriptionIpdPrescriptionId.value,
    }, billingDraftId).catch((error) => {
    offlineBillingStorageError.value = error instanceof Error ? error.message : 'The billing draft could not be saved on this device.';
    return false;
  });
}, 250);
const flushOfflineBillingDraft = () => persistOfflineBillDraftDebounced?.flush();

watch(() => ({
  items: items.value,
  patientForm: patientForm.value,
  newPatientForm: newPatientForm.value,
  patientSearchQuery: patientSearchQuery.value,
  doctorSearchQuery: doctorSearchQuery.value,
  referrerSearch: referrerSearch.value,
  isNewPatient: isNewPatient.value,
  summary: summary.value,
  commission: commission.value,
  ageYears: ageYears.value,
  ageMonths: ageMonths.value,
  ageDays: ageDays.value,
  billingDate: billingDate.value,
  billingTime: billingTime.value,
  billingDateTouched: billingDateTouched.value,
  deliveryDateTouched: deliveryDateTouched.value,
  prescriptionSearchId: prescriptionSearchId.value,
  prescriptionIpdPatientId: prescriptionIpdPatientId.value,
  prescriptionIpdPrescriptionId: prescriptionIpdPrescriptionId.value,
}), () => persistOfflineBillDraftDebounced(), { deep: true });

onMounted(() => {
  window.addEventListener('pagehide', flushOfflineBillingDraft);
});

// Track attempts to reload server props per category to avoid reload loops
const commissionDetails = ref({
  hasPathologyCommission: false,
  hasRadiologyCommission: false,
  hasMedicineCommission: false,
  pathologyRate: 0,
  radiologyRate: 0,
  medicineRate: 0,
  manualCommissionEnabled: false,
  noCommissionMessage: "",
});

const itemNameInput = ref(null);
const quantityInput = ref(null);
const editingQuantityIndex = ref(null);
const editedQuantity = ref('');
const showItemChargeModal = ref(false);
const selectedItemRef = ref(null);
const searchQuery = ref("");
const medicineSearchResults = ref([]);
const medicineSearchLoading = ref(false);
const medicineSearchError = ref(false);
let medicineSearchController = null;
const selectedIndex = ref(-1);
const quickSearchDebounceMs = 100;

const reloadReferrers = () => {
  const isEditingExistingBill = Boolean(props.id && props.editData);

  if (isEditingExistingBill) {
    try {
      const ql = String(referrerSearch.value || '').trim().toLowerCase();
      const availableReferrers = Array.isArray(props.referrers) ? props.referrers : [];
      if (ql.length >= 2) {
        let filtered = availableReferrers.filter(r =>
          (r.name || '').toLowerCase().includes(ql) || (r.phone || '').toLowerCase().includes(ql)
        );
        filtered = smartSortSearchResults(filtered, referrerSearch.value, (referrer) => referrer.name);
        filteredReferrers.value = filtered;
      } else {
        filteredReferrers.value = [];
        showReferrerDropdown.value = false;
      }
    } catch (e) {
      filteredReferrers.value = [];
      showReferrerDropdown.value = false;
    }
    return;
  }

  try {
    router.reload({
      only: ["referrers"],
      preserveState: true,
      preserveScroll: true,
      onSuccess: (page) => {
        const ql = String(referrerSearch.value || '').trim().toLowerCase();
        if (ql.length >= 2) {
          let filtered = (Array.isArray(page?.props?.referrers) ? page.props.referrers : []).filter(r =>
            (r.name || '').toLowerCase().includes(ql) || (r.phone || '').toLowerCase().includes(ql)
          );
          // Apply smart sorting
          filtered = smartSortSearchResults(filtered, referrerSearch.value, (referrer) => referrer.name);
          filteredReferrers.value = filtered;
        } else {
          filteredReferrers.value = [];
          showReferrerDropdown.value = false;
        }
      },
    });
  } catch (e) {
    filteredReferrers.value = [];
    showReferrerDropdown.value = false;
  }
};

const openCounterStartModal = () => {
  if (hasOpenCashCounterSession.value) {
    displayWarning({ message: 'কাউন্টার ইতিমধ্যে ওপেন আছে।' });
    return;
  }

  cashCounterStartBusy.value = false;
  counterStartForm.value.openingAmount = '';
  counterStartForm.value.note = 'Carry forward from previous shift';
  showCounterStartModal.value = true;
};

const closeCounterStartModal = () => {
  if (cashCounterStartBusy.value) return;
  showCounterStartModal.value = false;
};

const startCounterFromBilling = () => {
  if (cashCounterStartBusy.value) return;

  const openingAmount = Number(counterStartForm.value.openingAmount || 0);
  if (!Number.isFinite(openingAmount) || openingAmount < 0) {
    displayWarning({ message: 'সঠিক opening amount দিন।' });
    return;
  }

  cashCounterStartBusy.value = true;

  router.post(route('backend.cash-counter.quick-start'), {
    opening_amount: openingAmount,
    opening_note: counterStartForm.value.note || 'Started from Billing page with carry amount',
  }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: (responsePage) => {
      const flash = responsePage?.props?.flash || {};
      if (flash.errorMessage) {
        displayResponse(responsePage);
        return;
      }
      showCounterStartModal.value = false;
      displayResponse(responsePage);
    },
    onError: (errors) => {
      const firstError = (() => {
        try {
          const values = Object.values(errors || {});
          return values.length ? String(values[0]) : 'Counter start ব্যর্থ হয়েছে। আবার চেষ্টা করুন।';
        } catch (e) {
          return 'Counter start ব্যর্থ হয়েছে। আবার চেষ্টা করুন।';
        }
      })();
      displayWarning({ message: firstError });
    },
    onFinish: () => {
      cashCounterStartBusy.value = false;
    },
  });
};

const openCounterCloseModal = () => {
  if (!hasOpenCashCounterSession.value) {
    displayWarning({ message: 'কোনো ওপেন কাউন্টার নেই। আগে Counter Start করুন।' });
    return;
  }

  cashCounterCloseBusy.value = false;
  counterCloseForm.value.closingAmount = '';
  counterCloseForm.value.note = 'Closed from Billing page quick action';
  showCounterCloseModal.value = true;
};

const closeCounterCloseModal = () => {
  if (cashCounterCloseBusy.value) return;
  showCounterCloseModal.value = false;
};

const closeCounterFromBilling = () => {
  if (cashCounterCloseBusy.value) return;

  const closingAmount = Number(counterCloseForm.value.closingAmount || 0);
  if (!Number.isFinite(closingAmount) || closingAmount < 0) {
    displayWarning({ message: 'সঠিক closing amount দিন।' });
    return;
  }

  cashCounterCloseBusy.value = true;
  const printWindow = window.open(window.location.href, '_blank');

  try {
    const closeUrl = route('backend.cash-counter.quick-close');

    router.post(closeUrl, {
      session_id: activeCashCounterSessionId.value,
      closing_amount: closingAmount,
      note: counterCloseForm.value.note || 'Closed from Billing page quick action',
    }, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: (responsePage) => {
        const flash = responsePage?.props?.flash || {};
        const backendErrorMessage = String(flash?.errorMessage || '').trim();
        if (backendErrorMessage) {
          showCounterCloseModal.value = true;
          displayWarning({ message: backendErrorMessage });
          if (printWindow && !printWindow.closed) printWindow.close();
          return;
        }

        showCounterCloseModal.value = false;
        displayResponse(responsePage);

        const printUrl = responsePage?.props?.flash?.cashCounterPrintUrl || cashCounterPrintUrl.value || '';
        if (!printUrl) {
          if (printWindow && !printWindow.closed) printWindow.close();
          displayWarning({ message: 'রিপোর্ট URL পাওয়া যায়নি।' });
          return;
        }

        if (printWindow && !printWindow.closed) {
          printWindow.location.replace(printUrl);
          try { printWindow.focus(); } catch (e) {}
        } else {
          window.open(printUrl, '_blank');
        }
      },
      onError: (errors) => {
        const firstError = (() => {
          try {
            const values = Object.values(errors || {});
            return values.length ? String(values[0]) : 'Counter close ব্যর্থ হয়েছে। আবার চেষ্টা করুন।';
          } catch (e) {
            return 'Counter close ব্যর্থ হয়েছে। আবার চেষ্টা করুন।';
          }
        })();

        displayWarning({ message: firstError });

        if (printWindow && !printWindow.closed) printWindow.close();
      },
      onFinish: () => {
        cashCounterCloseBusy.value = false;
      },
    });
  } catch (e) {
    cashCounterCloseBusy.value = false;
    displayWarning({ message: 'Counter close request তৈরি করা যায়নি। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।' });
    if (printWindow && !printWindow.closed) printWindow.close();
  }
};

const getCurrentDateTime = () => {
  const now = new Date();
  const date = format(now, 'yyyy-MM-dd');
  const time = format(now, 'HH:mm:ss');
  return { date, time };
};

const setCurrentBillingDateTime = () => {
  try {
    const { date, time } = getCurrentDateTime();
    billingDate.value = date;
    billingTime.value = time;
    // update human-friendly live text
    try {
      const dt = new Date(`${billingDate.value}T${billingTime.value}`);
      if (isValid(dt)) {
        billingLiveText.value = format(dt, 'dd-MMM-yyyy hh:mm:ss a');
      } else {
        billingLiveText.value = '';
      }
    } catch (e) {
      billingLiveText.value = '';
    }
  } catch (e) {
    // console.warn removed
  }

};


const startBillingLiveClock = () => {
  if (billingLiveTimer) return;
  billingLiveTimer = setInterval(() => {
    if (!billingDateTouched.value) setCurrentBillingDateTime();
  }, 1000);
};

const startBillingClockTicker = () => {
  if (billingClockTimer) return;
  billingClockTimer = setInterval(() => {
    clockNow.value = Date.now();
  }, 1000);
};

const parseBillingSourceDateTime = (source) => {
  if (!source) return null;
  const value = String(source).trim();
  let parsed = null;

  try {
    if (/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/.test(value)) {
      parsed = parse(value, 'yyyy-MM-dd HH:mm:ss', new Date());
    } else if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/.test(value)) {
      parsed = parse(value, "yyyy-MM-dd'T'HH:mm:ss", new Date());
    } else {
      parsed = new Date(value);
    }
  } catch (e) {
    parsed = new Date(value);
  }

  return isValid(parsed) ? parsed : null;
};

const handleBillingDateTimeInput = () => {
  billingDateTouched.value = true;
  try {
    const dt = new Date(`${billingDate.value}T${billingTime.value}`);
    if (isValid(dt)) {
      billingLiveText.value = format(dt, 'dd-MMM-yyyy hh:mm:ss a');
      // normalize stored values
      billingDate.value = format(dt, 'yyyy-MM-dd');
      billingTime.value = format(dt, 'HH:mm:ss');
    }
  } catch (e) { /* ignore */ }
};
  // Calculate age from DOB helper
  const calculateAgeFromDOB = (dob) => {
  if (!dob) {
    ageYears.value = '';
    ageMonths.value = '';
    ageDays.value = '';
    return;
  }

  const birthDate = parse(String(dob), 'yyyy-MM-dd', new Date());
  if (!isValid(birthDate)) {
    ageYears.value = '';
    ageMonths.value = '';
    ageDays.value = '';
    return;
  }

  const today = new Date();

  let years = differenceInYears(today, birthDate);
  let remainingDate = subYears(today, years);

  let months = differenceInMonths(remainingDate, birthDate);
  remainingDate = subMonths(remainingDate, months);

  let days = differenceInDays(remainingDate, birthDate);

  ageYears.value = years > 0 ? years.toString() : '';
  ageMonths.value = months > 0 ? months.toString() : '';
  ageDays.value = days > 0 ? days.toString() : '';
};

watch(patientSearchQuery, debounce((newQuery) => {
  if (newQuery.trim() == "") {
    filteredPatients.value = [];
    showPatientDropdown.value = false;
    patientSelectedIndex.value = -1;
    return;
  }

  if (isEditMode.value && !showPatientDropdown.value) {
    isEditMode.value = false;
    return;
  }

  const query = newQuery.toLowerCase();
  let filtered = props.patients.filter(patient =>
    patient.name.toLowerCase().includes(query) ||
    patient.phone.toLowerCase().includes(query)
  );

  // Apply smart sorting
  filtered = smartSortSearchResults(filtered, newQuery, (patient) => patient.name);

  filteredPatients.value = filtered;
  showPatientDropdown.value = filteredPatients.value.length > 0;
  patientSelectedIndex.value = filteredPatients.value.length > 0 ? 0 : -1;
}, quickSearchDebounceMs));

const handlePatientSearchFocus = () => {
  if (patientSearchQuery.value.trim() !== "" && filteredPatients.value.length > 0) {
    showPatientDropdown.value = true;
  }
};

const handlePatientSearchEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    if (patientSearchQuery.value.trim()) {
      createNewPatientFromSearch();
    } else {
      showPatientDropdown.value = false;
      nextTick(() => {
        setTimeout(() => {
          doctorSearchRef.value?.focus();
          doctorSearchRef.value?.select();
        }, 100);
      });
    }
  }
};

const handleDoctorEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();

    if (doctorSelectedIndex.value !== -1 && filteredDoctors.value[doctorSelectedIndex.value]) {
      selectDoctor(filteredDoctors.value[doctorSelectedIndex.value]);
      return;
    }

    showDoctorDropdown.value = false;

    nextTick(() => {
      if (patientForm.value.gender) {
        if (dobInput.value && typeof dobInput.value.focus === 'function') {
          dobInput.value.focus();
        }
      } else {
        genderSelectRef.value?.focus();
        setTimeout(() => openDropdown(genderSelectRef), 100);
      }
    });
  }
};

const openDropdown = (ref) => {
  try {
    const el = ref?.value ?? ref;
    if (!el) return;

    if (typeof el.focus === 'function') {
      el.focus();
    }

    if (typeof el.showPicker === 'function') {
      el.showPicker();
    } else if (typeof el.click === 'function') {
      el.click();
    }
  } catch (error) {
    // ignore browser-specific picker issues
  }
};

const focusNextField = (currentRef, nextRef) => {
  nextTick(() => {
    if (nextRef && nextRef.value && typeof nextRef.value.focus === 'function') {
      nextRef.value.focus();
      
      if (nextRef.value.tagName === 'SELECT') {
        setTimeout(() => {
          try {
            if (typeof nextRef.value.showPicker === 'function') {
              nextRef.value.showPicker();
            }
          } catch (error) {
            // console.log removed
          }
        }, 50);
      }
    }
  });
};

const handlePatientMobileEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();

    if (isNewPatient.value && patientForm.value.patientMobile) {
      newPatientForm.value.phone = patientForm.value.patientMobile;
    }

    const hasPatientMatch = Boolean(patientForm.value.patient_id) || patientMobileSuggestions.value.length > 0;

    if (hasPatientMatch) {
      focusNextPatientFieldFromMobile();
    } else {
      nextTick(() => {
        patientSearchRef.value?.focus();
      });
    }
  }
};

const focusNextPatientFieldFromGender = () => {
  if (!patientForm.value.dob) {
    dobInput.value?.focus();
  } else if (!patientForm.value.cardType) {
    // Continue with next field
  }
};

const selectGenderOption = (option) => {
  if (!option) return;

  patientForm.value.gender = option.value;
  genderSelectedIndex.value = genderOptions.findIndex((item) => item.value === option.value);
  showGenderDropdown.value = false;

  nextTick(() => {
    dobInput.value?.focus();
  });
};

const handleGenderEnter = (event) => {
  if (showGenderDropdown.value) {
    if (event.key === 'Enter') {
      event.preventDefault();
      const selectedOption = genderOptions[genderSelectedIndex.value] || genderOptions[0];
      selectGenderOption(selectedOption);
    } else if (event.key === 'ArrowDown') {
      event.preventDefault();
      genderSelectedIndex.value = Math.min(genderSelectedIndex.value + 1, genderOptions.length - 1);
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      genderSelectedIndex.value = Math.max(genderSelectedIndex.value - 1, 0);
    } else if (event.key === 'Escape') {
      event.preventDefault();
      showGenderDropdown.value = false;
    }
    return;
  }

  if (event.key === 'ArrowDown' || event.key === 'ArrowUp' || event.key === 'Enter') {
    event.preventDefault();
    showGenderDropdown.value = true;
    genderSelectedIndex.value = genderOptions.findIndex((option) => option.value === patientForm.value.gender);
    if (genderSelectedIndex.value < 0) {
      genderSelectedIndex.value = 0;
    }
  }
};

const toggleGenderDropdown = () => {
  try {
    if (!showGenderDropdown.value) {
      showGenderDropdown.value = true;
      genderSelectedIndex.value = genderOptions.findIndex((option) => option.value === patientForm.value.gender);
      if (genderSelectedIndex.value < 0) genderSelectedIndex.value = 0;
    }
  } catch (e) {
    // ignore
  }
};

const handleGenderEnterOriginal = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    
    if (isNewPatient.value && patientForm.value.gender) {
      newPatientForm.value.gender = patientForm.value.gender;
    }
    
    nextTick(() => {
      if (dobInput.value && typeof dobInput.value.focus === 'function') {
        dobInput.value.focus();
      }
    });
  }
};

const handleDobEnter = (event) => {
  showGenderDropdown.value = false;
  if (event.key === "Enter") {
    event.preventDefault();
    nextTick(() => {
      ageYearsInput.value?.focus();
    });
  }
};

const handleAgeYearsEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    nextTick(() => {
      ageMonthsInput.value?.focus();
    });
  }
};

const handleAgeMonthsEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    nextTick(() => {
      ageDaysInput.value?.focus();
    });
  }
};

const handleAgeDaysEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    nextTick(() => {
      payModeRef.value?.focus();
      setTimeout(() => openDropdown(payModeRef), 100);
    });
  }
};

const handlePayModeEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    if (patientForm.value.payMode !== 'Cash') {
      nextTick(() => {
        if (cardNumberRef.value && typeof cardNumberRef.value.focus === 'function') {
          cardNumberRef.value.focus();
        }
      });
    } else {
      nextTick(() => {
        if (discountRef.value && typeof discountRef.value.focus === 'function') {
          discountRef.value.focus();
        }
      });
    }
  }
};

const handleCardNumberEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    nextTick(() => {
      discountRef.value?.focus();
    });
  }
};

const selectAllOnFocus = (event) => {
  const el = event.target;
  nextTick(() => {
    el.select();
  });
};

const handleDiscountEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    nextTick(() => {
      extraDiscountRef.value?.focus();
    });
  }
};

const onDiscountWheel = (event) => {
  // Switch discount type to percentage when user scrolls on the discount input
  summary.value.discountType = 'percentage';
  // Clamp discount to allowed max percentage
  try {
    if (Number(summary.value.discount) > Number(maxBillingDiscountPercent.value)) {
      summary.value.discount = Number(maxBillingDiscountPercent.value);
    }
  } catch (e) { /* ignore */ }
  if (event && typeof event.preventDefault === 'function') event.preventDefault();
};

const handleExtraDiscountEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    nextTick(() => {
      receivingAmtRef.value?.focus();
    });
  }
};

const handleReceivingAmtEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    nextTick(() => {
      deliveryDateRef.value?.focus();
    });
  }
};

const handleDeliveryDateEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    ensureDeliveryDateTime();
    nextTick(() => {
      if (remarksRef.value && typeof remarksRef.value.focus === 'function') {
        remarksRef.value.focus();
      }
    });
  }
};

const handleDeliveryTimeEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    ensureDeliveryDateTime();

    nextTick(() => {
      if (remarksRef.value && typeof remarksRef.value.focus === 'function') {
        remarksRef.value.focus();
      }
    });
  }
};

const handleRemarksEnter = (event) => {
  if (event.key === "Enter" && !event.shiftKey) {
    event.preventDefault();
    nextTick(() => {
      referrerSelectRef.value?.focus();
      setTimeout(() => openDropdown(referrerSelectRef), 100);
    });
  }
};

// Close dropdowns when clicking outside the specific wrappers
const _onDocumentClick = (e) => {
  try {
    if (showGenderDropdown.value) {
      const wrapper = genderWrapperRef.value;
      if (wrapper && !wrapper.contains(e.target)) {
        showGenderDropdown.value = false;
      }
    }
    if (showPatientMobileDropdown.value) {
      const wrapper = patientMobileWrapperRef.value;
      if (wrapper && !wrapper.contains(e.target)) {
        showPatientMobileDropdown.value = false;
      }
    }
  } catch (err) {
    // ignore errors from DOM checks in non-browser environments
  }
};

// Close dropdowns when focus moves outside (handles Tab/keyboard focus)
const _onDocumentFocusIn = (e) => {
  try {
    if (showGenderDropdown.value) {
      const wrapper = genderWrapperRef.value;
      if (wrapper && !wrapper.contains(e.target)) {
        showGenderDropdown.value = false;
      }
    }
    if (showPatientMobileDropdown.value) {
      const wrapper = patientMobileWrapperRef.value;
      if (wrapper && !wrapper.contains(e.target)) {
        showPatientMobileDropdown.value = false;
      }
    }
  } catch (err) {
    // ignore
  }
};

const handleReferrerEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    if (commissionDetails.value.manualCommissionEnabled) {
      nextTick(() => {
        if (commissionSliderRef.value && typeof commissionSliderRef.value.focus === 'function') {
          commissionSliderRef.value.focus();
        }
      });
    } else {
      saveBill();
    }
  }
};

const handleSelectClick = (selectRef) => {
  if (selectRef && selectRef.value && typeof selectRef.value.focus === 'function') {
    try {
      selectRef.value.focus();

      setTimeout(() => {
        try {
          if (typeof selectRef.value.showPicker === 'function') {
            selectRef.value.showPicker();
          }
        } catch (error) {
          // console.log removed
        }
      }, 50);
    } catch (error) {
      // console.log removed
    }
  }
};

const handleSelectKeydown = (event, nextFieldRef) => {
  if (event.key === "Enter") {
    event.preventDefault();
    if (nextFieldRef && nextFieldRef.value && typeof nextFieldRef.value.focus === 'function') {
      nextTick(() => {
        nextFieldRef.value.focus();
      });
    }
  } else if (event.key === "Tab") {
    return;
  }
};

const handleCommissionSliderEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    saveBill();
  }
};

// Modal functions
const openPatientModal = () => {
  isPatientModalOpen.value = true;
};

const closePatientModal = () => {
  isPatientModalOpen.value = false;
};

const selectPatient = (patient, options = {}) => {
  // debug log removed

  patientForm.value.patient_id = patient.id;
  patientForm.value.patientMobile = patient.phone;
  patientForm.value.gender = patient.gender;
  patientForm.value.dob = patient.dob || '';
  patientSearchQuery.value = patient.name;

  showPatientDropdown.value = false;
  patientSelectedIndex.value = -1;
  showPatientMobileDropdown.value = false;
  patientMobileSelectedIndex.value = -1;
  showGenderDropdown.value = false;
  genderSelectedIndex.value = genderOptions.findIndex(opt => opt.value === patient.gender);
  isNewPatient.value = false;

  calculateAgeFromDOB(patient.dob);

  newPatientForm.value = {
    name: "",
    phone: "",
    gender: "",
    dob: "",
  };

  if (options.focusNextField !== false) {
    nextTick(() => {
      setTimeout(() => {
        if (doctorSearchRef.value && typeof doctorSearchRef.value.focus === 'function') {
          doctorSearchRef.value.focus();
          doctorSearchRef.value.select();
        }
      }, 100);
    });
  }
};

const updatePatientMobileSuggestions = (value) => {
  const query = String(value || '').trim().toLowerCase();
  const digitsOnly = String(value || '').replace(/\D/g, '');
  // require at least 3 digits for mobile search to activate
  if (digitsOnly.length < 3) {
    patientMobileSuggestions.value = [];
    showPatientMobileDropdown.value = false;
    patientMobileSelectedIndex.value = -1;
    return;
  }
  if (!query) {
    patientMobileSuggestions.value = [];
    showPatientMobileDropdown.value = false;
    patientMobileSelectedIndex.value = -1;
    return;
  }

  const matches = (props.patients || [])
    .filter((patient) => {
      const name = String(patient.name || '').trim().toLowerCase();
      const phone = String(patient.phone || patient.mobile || '').trim().toLowerCase();
      return name.includes(query) || phone.includes(query);
    })
    .map((patient) => ({
      ...patient,
      displayPhone: String(patient.phone || patient.mobile || '').trim(),
      displayName: String(patient.name || '').trim(),
    }));

  patientMobileSuggestions.value = matches;
  showPatientMobileDropdown.value = matches.length > 0;
  patientMobileSelectedIndex.value = matches.length > 0 ? 0 : -1;
};

const handlePatientMobileInput = (event) => {
  const value = String(event?.target?.value ?? '');
  patientForm.value.patientMobile = value;

  // If the field was cleared
  if (!value.trim()) {
    // Treat as 'new entry' if isNewPatient is true OR there's no selected patient_id
    if (isNewPatient.value || !patientForm.value.patient_id) {
      // For new patient entry, only clear the mobile field
      patientForm.value.patientMobile = '';
    } else {
      // For an existing (selected) patient, clear all patient fields
      patientForm.value.patient_id = '';
      patientForm.value.patientMobile = '';
      patientForm.value.gender = '';
      patientForm.value.dob = '';
      patientSearchQuery.value = '';
      genderSelectedIndex.value = 0;
      showGenderDropdown.value = false;
      isNewPatient.value = false;
    }
    // hide suggestions
    patientMobileSuggestions.value = [];
    showPatientMobileDropdown.value = false;
    patientMobileSelectedIndex.value = -1;
    return;
  }

  updatePatientMobileSuggestions(value);
};

const focusNextPatientFieldFromMobile = () => {
  if (!patientSearchQuery.value.trim()) {
    nextTick(() => {
      patientSearchRef.value?.focus();
    });
    return;
  }

  if (!doctorSearchQuery.value.trim() && !patientForm.value.doctor_id) {
    nextTick(() => {
      doctorSearchRef.value?.focus();
      doctorSearchRef.value?.select();
    });
    return;
  }

  if (!patientForm.value.gender) {
    nextTick(() => {
      genderSelectRef.value?.focus();
      setTimeout(() => openDropdown(genderSelectRef), 100);
    });
    return;
  }

  if (!patientForm.value.dob) {
    nextTick(() => {
      dobInput.value?.focus();
    });
    return;
  }

  nextTick(() => {
    ageYearsInput.value?.focus();
  });
};

const selectPatientFromMobileSuggestion = (patient) => {
  selectPatient(patient);
  patientForm.value.patientMobile = String(patient.phone || patient.mobile || '').trim();
  patientMobileSuggestions.value = [];
  showPatientMobileDropdown.value = false;
  patientMobileSelectedIndex.value = -1;
};

const handlePatientMobileKeydown = (event) => {
  if (event.key === 'ArrowDown') {
    event.preventDefault();
    if (patientMobileSuggestions.value.length > 0) {
      patientMobileSelectedIndex.value = Math.min(patientMobileSelectedIndex.value + 1, patientMobileSuggestions.value.length - 1);
    }
  } else if (event.key === 'ArrowUp') {
    event.preventDefault();
    if (patientMobileSuggestions.value.length > 0) {
      patientMobileSelectedIndex.value = Math.max(patientMobileSelectedIndex.value - 1, 0);
    }
  } else if (event.key === 'Enter') {
    if (patientMobileSuggestions.value.length > 0 && patientMobileSelectedIndex.value !== -1) {
      event.preventDefault();
      const selectedPatient = patientMobileSuggestions.value[patientMobileSelectedIndex.value];
      if (selectedPatient) {
        selectPatientFromMobileSuggestion(selectedPatient);
      }
    } else {
      handlePatientMobileEnter(event);
    }
  } else if (event.key === 'Escape') {
    patientMobileSuggestions.value = [];
    showPatientMobileDropdown.value = false;
    patientMobileSelectedIndex.value = -1;
  }
};

const handleAgeInput = (currentInput, nextInput) => {
  return (e) => {
    if (e.target.value.length >= 2 && nextInput) {
      nextTick(() => {
        nextInput.value.focus();
        nextInput.value.select();
      });
    }
  };
};

// DOB and Age watchers
watch(() => patientForm.value.dob, (newDob) => {
  if (updatingFrom.value === 'age') return;
  updatingFrom.value = 'dob';

  calculateAgeFromDOB(newDob);

  updatingFrom.value = null;
});

watch(patientSearchQuery, (newQuery) => {
  if (newQuery.trim() === "" && isNewPatient.value) {
    isNewPatient.value = false;
    newPatientForm.value = {
      name: "",
      phone: "",
      gender: "",
      dob: "",
    };
  }
});

watch([ageYears, ageMonths, ageDays], ([years, months, days]) => {
  if (updatingFrom.value === 'dob') return;
  updatingFrom.value = 'age';

  const yearsNum = parseInt(years) || 0;
  const monthsNum = parseInt(months) || 0;
  const daysNum = parseInt(days) || 0;

  if (yearsNum === 0 && monthsNum === 0 && daysNum === 0) {
    patientForm.value.dob = '';
    updatingFrom.value = null;
    return;
  }

  let dobDate = new Date();

  if (yearsNum > 0) dobDate = subYears(dobDate, yearsNum);
  if (monthsNum > 0) dobDate = subMonths(dobDate, monthsNum);
  if (daysNum > 0) dobDate = subDays(dobDate, daysNum);

  if (dobDate > new Date()) {
    patientForm.value.dob = '';
  } else {
    patientForm.value.dob = format(dobDate, 'yyyy-MM-dd');
  }

  updatingFrom.value = null;
}, { deep: true });

const createNewPatient = () => {
  isNewPatient.value = true;
  patientForm.value.patient_id = null;
  newPatientForm.value = {
    name: patientSearchQuery.value.trim() || "",
    phone: "",
    gender: "",
    dob: "",
  };
  showPatientDropdown.value = false;
};

const handlePatientSearchKeyDown = (event) => {
  if (event.key === "ArrowDown") {
    event.preventDefault();
    patientSelectedIndex.value = Math.min(
      patientSelectedIndex.value + 1,
      filteredPatients.value.length - 1
    );
  } else if (event.key === "ArrowUp") {
    event.preventDefault();
    patientSelectedIndex.value = Math.max(patientSelectedIndex.value - 1, 0);
  } else if (event.key === "Enter") {
    handlePatientSearchEnter(event);
  } else if (event.key === "Escape") {
    patientSelectedIndex.value = -1;
    showPatientDropdown.value = false;
  }
};

const createNewPatientFromSearch = () => {
  // debug log removed

  isNewPatient.value = true;
  patientForm.value.patient_id = null;

  const preservedPhone = patientForm.value.patientMobile || "";
  const preservedGender = patientForm.value.gender || "";
  const preservedDob = patientForm.value.dob || "";

  newPatientForm.value = {
    name: patientSearchQuery.value.trim(),
    phone: preservedPhone,
    gender: preservedGender,
    dob: preservedDob,
  };

  patientForm.value.patientMobile = preservedPhone;
  patientForm.value.gender = preservedGender;
  patientForm.value.dob = preservedDob;
  showGenderDropdown.value = false;
  genderSelectedIndex.value = -1;
  ageYears.value = '';
  ageMonths.value = '';
  ageDays.value = '';

  showPatientDropdown.value = false;

  // debug log removed

  nextTick(() => {
    setTimeout(() => {
      if (doctorSearchRef.value && typeof doctorSearchRef.value.focus === 'function') {
        doctorSearchRef.value.focus();
        doctorSearchRef.value.select();
      }
    }, 100);
  });
};

const handleDoctorSearchFocus = () => {
  const hasExactMatch = filteredDoctors.value.some(doctor => 
    doctor.name.toLowerCase() === doctorSearchQuery.value.trim().toLowerCase()
  );
  
  if (doctorSearchQuery.value.trim() !== "" && 
      filteredDoctors.value.length > 0 &&
      !hasExactMatch) {
    showDoctorDropdown.value = true;
  } else {
    showDoctorDropdown.value = false;
  }
};

const handlePatientCreated = (newPatient) => {
  // debug log removed

  patientsList.value.push(newPatient);

  patientForm.value.patient_id = newPatient.id;
  patientForm.value.patientMobile = newPatient.phone;
  patientForm.value.gender = newPatient.gender;
  patientForm.value.dob = newPatient.dob || '';
  patientSearchQuery.value = newPatient.name;

  calculateAgeFromDOB(newPatient.dob);

  isNewPatient.value = false;
  newPatientForm.value = {
    name: "",
    phone: "",
    gender: "",
    dob: "",
  };

  nextTick(() => {
    setTimeout(() => {
      if (doctorSearchRef.value && typeof doctorSearchRef.value.focus === 'function') {
        doctorSearchRef.value.focus();
        doctorSearchRef.value.select();
      }
    }, 100);
  });

  router.reload({
    only: ["patients"],
    preserveState: true,
    preserveScroll: true,
    onSuccess: (page) => {
      patientsList.value = [...page.props.patients];
    },
  });
};

watch(() => newPatientForm.value, (newPatientData) => {
  if (isNewPatient.value) {
    if (newPatientData.phone) {
      patientForm.value.patientMobile = newPatientData.phone;
    }
    if (newPatientData.gender) {
      patientForm.value.gender = newPatientData.gender;
    }
    if (newPatientData.name && newPatientData.name !== patientSearchQuery.value) {
      patientSearchQuery.value = newPatientData.name;
    }
    if (newPatientData.dob) {
      patientForm.value.dob = newPatientData.dob;
    }
  }
}, { deep: true });

watch(() => newPatientForm.value.phone, (newPhone) => {
  // debug log removed
  if (isNewPatient.value) {
    patientForm.value.patientMobile = newPhone;
  }
});

watch(() => newPatientForm.value.gender, (newGender) => {
  // debug log removed
  if (isNewPatient.value) {
    patientForm.value.gender = newGender;
  }
});

watch(() => newPatientForm.value.dob, (newDob) => {
  // debug log removed
  if (isNewPatient.value) {
    patientForm.value.dob = newDob;
    calculateAgeFromDOB(newDob);
  }
});

watch(() => patientForm.value.patientMobile, (newPhone) => {
  if (isNewPatient.value) {
    newPatientForm.value.phone = newPhone;
  }
});

watch(() => patientForm.value.gender, (newGender) => {
  if (isNewPatient.value) {
    newPatientForm.value.gender = newGender;
  }
});

watch(() => patientForm.value.dob, (newDob) => {
  if (isNewPatient.value && newDob !== newPatientForm.value.dob) {
    newPatientForm.value.dob = newDob;
    calculateAgeFromDOB(newDob);
  }
});

watch(() => newPatientForm.value.name, (newName) => {
  // debug log removed
  if (isNewPatient.value && newName) {
    patientSearchQuery.value = newName;
  }
});

watch(() => isNewPatient.value, (newValue) => {
  // debug log removed
  isNewPatientFlag.value = newValue;

  if (!newValue) {
    // debug log removed
    newPatientForm.value = {
      name: "",
      phone: "",
      gender: "",
      dob: "",
    };
    if (!patientForm.value.patient_id) {
      patientForm.value.patientMobile = "";
      patientForm.value.gender = "";
      patientForm.value.dob = "";
      showGenderDropdown.value = false;
      genderSelectedIndex.value = -1;
      patientSearchQuery.value = "";
      ageYears.value = '';
      ageMonths.value = '';
      ageDays.value = '';
    }
  } else {
    // debug log removed
    patientForm.value.patient_id = null;
  }
});

watch(() => itemForm.value.quantity, (newQuantity) => {
  if (newQuantity && itemForm.value.unitPrice) {
    itemForm.value.totalAmount = (newQuantity * itemForm.value.unitPrice).toFixed(2);
  }
});

watch([() => itemForm.value.quantity, () => itemForm.value.unitPrice], () => {
  itemForm.value.totalAmount = (
    itemForm.value.quantity * itemForm.value.unitPrice
  ).toFixed(2);
});

watch(() => patientForm.value.patient_id, (newPatientId, oldPatientId) => {
  if (oldPatientId && Number(newPatientId || 0) !== Number(oldPatientId)) {
    prescriptionIpdPatientId.value = null;
    prescriptionIpdPrescriptionId.value = null;
  }

  if (newPatientId && props.patients) {
    const selectedPatient = props.patients.find(
      (patient) => patient.id == newPatientId
    );
    if (selectedPatient) {
      patientForm.value.patientMobile =
        selectedPatient.phone || selectedPatient.mobile || "";
      patientForm.value.gender = selectedPatient.gender || "";
      patientForm.value.dob = selectedPatient.dob || "";
      patientSearchQuery.value = selectedPatient.name || "";

      calculateAgeFromDOB(selectedPatient.dob);
    }
  }
});

watch(() => commission.value.referrer_id, (newReferrerId) => {
  if (!newReferrerId) {
    commission.value.commissionRate = 0;
    commission.value.slider = 0;
    resetCommissionDetails();
    updateCommission();
    return;
  }

  const selectedReferrer = resolveSelectedReferrer();
  if (!selectedReferrer) {
    const typed = String(referrerSearch.value || '').trim();
    const fellBack = (props.referrers || []).find((referrer) => referrerTextMatchesSelection(typed, referrer));
    if (fellBack) {
      commission.value.referrer_id = fellBack.id;
      return;
    }
    return;
  }

  const currentInput = String(referrerSearch.value || '').trim();
  if (!currentInput || !referrerTextMatchesSelection(currentInput, selectedReferrer)) {
    referrerSearch.value = selectedReferrer.name || '';
  }

  updateCommissionRate(selectedReferrer);
});

watch(() => props.referrers, () => {
  if (commission.value.referrer_id) {
    syncReferrerSelectionState();
  }
}, { deep: true, immediate: true });

watch(() => props.referrers, (newReferrers) => {
  const isLockedEditSelection = Boolean(props.id && props.editData && commission.value.referrer_id);

  if (isLockedEditSelection) {
    filteredReferrers.value = [];
    showReferrerDropdown.value = false;
    const selectedReferrer = (newReferrers || []).find(
      (referrer) => String(referrer.id) === String(commission.value.referrer_id)
    );
    if (selectedReferrer) {
      referrerSearch.value = selectedReferrer.name || '';
    }
    return;
  }

  const ql = String(referrerSearch.value || '').trim().toLowerCase();
  if (ql) {
    let filtered = (newReferrers || []).filter(r => (r.name || '').toLowerCase().includes(ql) || (r.phone || '').toLowerCase().includes(ql));
    // Apply smart sorting
    filtered = smartSortSearchResults(filtered, referrerSearch.value, (referrer) => referrer.name);
    filteredReferrers.value = filtered;
    showReferrerDropdown.value = filteredReferrers.value.length > 0;
  } else {
    filteredReferrers.value = [];
    showReferrerDropdown.value = false;
  }

  if (!commission.value.referrer_id) {
    referrerSearch.value = '';
    return;
  }

  const selectedReferrer = (newReferrers || []).find(
    (referrer) => referrer.id == commission.value.referrer_id
  );

  if (selectedReferrer) {
    const currentInput = String(referrerSearch.value || '').trim();
    if (!currentInput || !referrerTextMatchesSelection(currentInput, selectedReferrer)) {
      referrerSearch.value = selectedReferrer.name || '';
    }
  } else {
    commission.value.referrer_id = '';
    referrerSearch.value = '';
  }
}, { deep: true });

// filter referrers as user types
watch(referrerSearch, debounce((q) => {
  const isLockedEditSelection = Boolean(props.id && props.editData && commission.value.referrer_id);
  if (isLockedEditSelection) {
    filteredReferrers.value = [];
    showReferrerDropdown.value = false;
    highlightedRefIndex.value = 0;
    return;
  }

  // If we just selected programmatically, don't reopen suggestions
  if (referrerJustSelected.value) {
    return;
  }

  const typed = String(q || '').trim();
  const selectedReferrer = (props.referrers || []).find(
    (referrer) => referrer.id == commission.value.referrer_id
  );

  if (selectedReferrer) {
    const typedMatchesSelected = referrerTextMatchesSelection(typed, selectedReferrer);
    if (typed && !typedMatchesSelected) {
      commission.value.referrer_id = '';
      commission.value.commissionRate = 0;
      commission.value.slider = 0;
      resetCommissionDetails();
      updateCommission();
    }
  }

  const ql = String(q || '').trim().toLowerCase();
  if (!ql) {
    filteredReferrers.value = [];
    showReferrerDropdown.value = false;
    highlightedRefIndex.value = 0;
    return;
  }
  let filtered = (props.referrers || []).filter(r => (r.name || '').toLowerCase().includes(ql) || (r.phone || '').toLowerCase().includes(ql));

  // Apply smart sorting
  filtered = smartSortSearchResults(filtered, q, (referrer) => referrer.name);

  filteredReferrers.value = filtered;
  highlightedRefIndex.value = 0;
  showReferrerDropdown.value = filteredReferrers.value.length > 0;
}, quickSearchDebounceMs));

const selectReferrer = (r) => {
  // set flag first so programmatic setting of referrerSearch doesn't reopen dropdown
  referrerJustSelected.value = true;
  commission.value.referrer_id = r.id;
  referrerSearch.value = r.name || '';
  showReferrerDropdown.value = false;
  // clear suggestions to ensure dropdown doesn't reappear
  filteredReferrers.value = [];
  // blur input to avoid double Enter behavior and hide dropdown reliably
  try {
    if (referrerSelectRef && referrerSelectRef.value && typeof referrerSelectRef.value.blur === 'function') {
      referrerSelectRef.value.blur();
    }
  } catch (e) {}
  updateCommissionRate(r);
  submitOnNextReferrerEnter.value = true;
  // Move focus to Save & Print so the next Enter submits quickly.
  nextTick(() => {
    setTimeout(() => {
      if (savePrintButtonRef.value && typeof savePrintButtonRef.value.focus === 'function') {
        savePrintButtonRef.value.focus();
      }
    }, 60);
  });
  // keep the flag a bit longer to avoid immediate reopen on focus
  setTimeout(() => { referrerJustSelected.value = false; }, 400);
};

const handleReferrerKeydown = (e) => {
  const isLockedEditSelection = Boolean(props.id && props.editData && commission.value.referrer_id);
  if (isLockedEditSelection) {
    if (['ArrowDown', 'ArrowUp', 'Enter', 'Escape'].includes(e.key)) {
      e.preventDefault();
      e.stopPropagation();
    }
    showReferrerDropdown.value = false;
    filteredReferrers.value = [];
    return;
  }

  if (e.key === 'ArrowDown') {
    e.preventDefault();
    if (!showReferrerDropdown.value && filteredReferrers.value.length>0) showReferrerDropdown.value = true;
    highlightedRefIndex.value = Math.min(highlightedRefIndex.value + 1, filteredReferrers.value.length - 1);
    return;
  }
  if (e.key === 'ArrowUp') {
    e.preventDefault();
    highlightedRefIndex.value = Math.max(highlightedRefIndex.value - 1, 0);
    return;
  }
  if (e.key === 'Enter') {
    if (showReferrerDropdown.value && filteredReferrers.value.length > 0) {
      e.preventDefault();
      e.stopPropagation();
      const pick = filteredReferrers.value[highlightedRefIndex.value] || filteredReferrers.value[0];
      if (pick) selectReferrer(pick);
      return;
    }
    e.preventDefault();
    e.stopPropagation();
    if (submitOnNextReferrerEnter.value) {
      submitOnNextReferrerEnter.value = false;
      saveBill();
      return;
    }

    showReferrerDropdown.value = false;
    submitOnNextReferrerEnter.value = true;
    nextTick(() => {
      setTimeout(() => {
        if (savePrintButtonRef.value) {
          if (typeof savePrintButtonRef.value.scrollIntoView === 'function') {
            savePrintButtonRef.value.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
          if (typeof savePrintButtonRef.value.focus === 'function') {
            savePrintButtonRef.value.focus();
          }
        }
      }, 60);
    });
    return;
  }
  if (e.key === 'Escape') {
    showReferrerDropdown.value = false;
  }
};

const handleReferrerFocus = (e) => {
  const isLockedEditSelection = Boolean(props.id && props.editData && commission.value.referrer_id);
  if (isLockedEditSelection || referrerJustSelected.value) {
    showReferrerDropdown.value = false;
    filteredReferrers.value = [];
    return;
  }
  // Close other open dropdowns (e.g., Gender) when focusing referrer
  showGenderDropdown.value = false;
  const ql = String(referrerSearch.value || '').trim().toLowerCase();
  if (ql.length < 2) {
    showReferrerDropdown.value = false;
    return;
  }
  showReferrerDropdown.value = filteredReferrers.value.length > 0;
};

const handleReferrerBlur = () => {
  setTimeout(() => {
    showReferrerDropdown.value = false;
  }, 120);
};

watch(() => commission.value.slider, (newValue) => {
  if (commissionDetails.value.manualCommissionEnabled) {
    commission.value.commissionRate = parseFloat(newValue);
  }
  updateCommission();
});

watch(() => summary.value.extraFlatDiscount, () => {
  updateSummary();
});

watch(() => summary.value.receivingAmt, () => {
  calculateChangeAndDue();
});

watch(() => summary.value.payableAmount, () => {
  calculateChangeAndDue();
});

watch(() => props.editData?.items, (editItems) => {
  if (!props.id || !Array.isArray(editItems) || editItems.length === 0) return;
  if (items.value.length === editItems.length && Number(summary.value.total || 0) > 0) return;

  initializeEditMode();
  nextTick(() => {
    if (items.value.length > 0 && Number(summary.value.total || 0) <= 0) {
      updateSummary();
    }
  });
}, { deep: true });

watch(() => items.value.reduce((sum, item) => sum + Number(item.totalAmount || 0), 0), (itemsTotal) => {
  if (props.id && itemsTotal > 0 && Number(summary.value.total || 0) <= 0) {
    updateSummary();
  }
});

watch(() => summary.value.discountType, (newType, oldType) => {
  if (isInitializingEditMode.value) return;
  convertDiscountType(newType, oldType);
  updateSummary();
});

watch(() => summary.value.discount, () => {
  updateSummary();
});

watch(() => commission.value.slider, () => {
  commission.value.commissionRate = commission.value.slider;
  updateCommission();
});

watch(selectedIndex, (newIndex) => {
  if (newIndex !== -1) {
    nextTick(() => {
      if (selectedItemRef.value) {
        selectedItemRef.value.scrollIntoView({ block: "nearest", behavior: "smooth" });
      }
    });
  }
});

let previousQuery = "";
watch(searchQuery, (newQuery) => {
  if (newQuery !== previousQuery) {
    selectedIndex.value = -1;
  }
  previousQuery = newQuery;
});

// Debug: log when category changes to trace unexpected updates
watch(() => itemForm.value.category, (newCat, oldCat) => {
  // debug log removed
  try {
    const sc = normalizeForMatch(String(newCat || ''));
    const availableCats = Array.from(new Set((allAvailableItems.value||[]).map(i => String(i.category||'').trim()).filter(Boolean)));
    const matches = (allAvailableItems.value||[]).filter(it => {
      const icat = normalizeForMatch(it.category || '');
      const iname = normalizeForMatch(it.name || '');
      const ialt = normalizeForMatch(it.alt || '');
      if (!sc) return false;
      if (icat && (icat === sc || icat.includes(sc) || sc.includes(icat))) return true;
      if (iname && iname.includes(sc)) return true;
      if (ialt && ialt.includes(sc)) return true;
      const scTokens = sc.split(' ').filter(Boolean);
      if (scTokens.length > 0) {
        if (scTokens.some(tok => icat.includes(tok) || iname.includes(tok) || ialt.includes(tok))) return true;
      }
      return false;
    });
    // debug log removed
    // Track last non-empty selection
    if (newCat && String(newCat).trim() !== '') {
      lastSelectedCategory.value = newCat;
      lastSelectedCategory._ts = Date.now();
    }
  } catch (e) { /* debug warn removed */ }
});

const getReferralCommissionBaseItems = () => {
  const targetCents = Math.max(0, Math.round((Number(summary.value.payableAmount) || 0) * 100));
  const itemWeights = items.value.map((item) =>
    Math.max(0, Math.round((Number(item.netAmount) || 0) * 100))
  );
  const totalNetCents = itemWeights.reduce((sum, amount) => sum + amount, 0);
  const weights = totalNetCents > 0
    ? itemWeights
    : items.value.map((item) => Math.max(0, Math.round((Number(item.totalAmount) || 0) * 100)));
  const weightTotal = weights.reduce((sum, amount) => sum + amount, 0);
  const baseCents = weights.map(() => 0);

  if (targetCents > 0 && weightTotal > 0) {
    const remainders = [];
    let allocatedCents = 0;

    weights.forEach((weight, index) => {
      const exactCents = (targetCents * weight) / weightTotal;
      baseCents[index] = Math.floor(exactCents);
      allocatedCents += baseCents[index];
      remainders.push({ index, remainder: exactCents - baseCents[index] });
    });

    remainders.sort((left, right) => right.remainder - left.remainder);
    for (let remaining = targetCents - allocatedCents, index = 0; remaining > 0; remaining -= 1, index += 1) {
      baseCents[remainders[index % remainders.length].index] += 1;
    }
  }

  return items.value.map((item, index) => ({
    ...item,
    netAmount: baseCents[index] / 100,
  }));
};

const calculateReferralCommission = (amount, rate) => {
  const amountCents = Math.max(0, Math.round((Number(amount) || 0) * 100));
  const commissionCents = Math.round((amountCents * (Number(rate) || 0)) / 100);
  return commissionCents / 100;
};

const getReferralCommissionAllocation = (referrer, billItems = [], payableAmount = 0) => {
  const normalizedItems = (billItems || []).map((item) => {
    const category = normalizeCommissionCategory(item.category);
    const itemNetAmount = Number(item.netAmount ?? item.totalAmount ?? 0);
    const itemLevelRate = Number(item.referral_percentage || 0);
    const rate = itemLevelRate > 0 ? itemLevelRate : getCategoryCommissionRate(referrer, category);
    return {
      ...item,
      category,
      rate,
      netAmount: itemNetAmount,
    };
  });

  const targetCents = Math.max(0, Math.round((Number(payableAmount) || 0) * 100));
  const weights = normalizedItems.map((item) => Math.max(0, Math.round((Number(item.netAmount) || 0) * 100)));
  const weightTotal = weights.reduce((sum, weight) => sum + weight, 0);
  const allocatedCents = weights.map(() => 0);

  if (targetCents > 0 && weightTotal > 0) {
    const remainders = [];
    let assignedCents = 0;

    weights.forEach((weight, index) => {
      const exactCents = (targetCents * weight) / weightTotal;
      allocatedCents[index] = Math.floor(exactCents);
      assignedCents += allocatedCents[index];
      remainders.push({ index, remainder: exactCents - allocatedCents[index] });
    });

    remainders.sort((left, right) => right.remainder - left.remainder);
    for (let remaining = targetCents - assignedCents, index = 0; remaining > 0; remaining -= 1, index += 1) {
      allocatedCents[remainders[index % remainders.length].index] += 1;
    }
  }

  const categories = {};
  let totalCommissionAmount = 0;

  normalizedItems.forEach((item, index) => {
    const allocatedAmount = allocatedCents[index] / 100;
    const commissionAmount = calculateReferralCommission(allocatedAmount, item.rate);
    const category = item.category || 'unknown';

    if (!categories[category]) {
      categories[category] = {
        total: 0,
        commission: 0,
        rate: item.rate,
      };
    }

    categories[category].total += allocatedAmount;
    categories[category].commission += commissionAmount;
    totalCommissionAmount += commissionAmount;
  });

  return {
    total: totalCommissionAmount,
    categories: Object.entries(categories).map(([category, values]) => ({
      category,
      rate: parseFloat((Number(values.rate) || 0).toFixed(2)),
      amount: parseFloat(values.total.toFixed(2)),
      commission: parseFloat(values.commission.toFixed(2)),
      hasCommission: values.commission > 0,
    })),
  };
};

const updateCommissionRate = (referrer) => {
  if (!referrer || items.value.length === 0) {
    commission.value.commissionRate = 0;
    commission.value.slider = 0;
    updateCommission();
    resetCommissionDetails();
    return;
  }

  const itemCategories = [
    ...new Set(items.value.map((item) => normalizeCommissionCategory(item.category))),
  ];

  const availableCommissions = {
    pathology: referrer.pathology_commission || 0,
    radiology: referrer.radiology_commission || 0,
    medicine: referrer.pharmacy_commission || 0,
    opd: referrer.opd_commission || 0,
    ipd: referrer.ipd_commission || 0,
    ecg: referrer.ecg_commission || referrer.radiology_commission || 0,
    ultrasound: referrer.ultrasound_commission || referrer.radiology_commission || 0,
    appointment: referrer.opd_commission || 0,
  };

  commissionDetails.value.hasPathologyCommission =
    itemCategories.includes("pathology") && availableCommissions.pathology > 0;
  commissionDetails.value.hasRadiologyCommission =
    itemCategories.includes("radiology") && availableCommissions.radiology > 0;
  commissionDetails.value.hasMedicineCommission =
    itemCategories.includes("medicine") && availableCommissions.medicine > 0;
  commissionDetails.value.hasECGCommission =
    itemCategories.includes("ecg") && availableCommissions.ecg > 0;
  commissionDetails.value.hasUltrasoundCommission =
    itemCategories.includes("ultrasound") && availableCommissions.ultrasound > 0;

  commissionDetails.value.pathologyRate = availableCommissions.pathology;
  commissionDetails.value.radiologyRate = availableCommissions.radiology;
  commissionDetails.value.medicineRate = availableCommissions.medicine;
  commissionDetails.value.ecgRate = availableCommissions.ecg;
  commissionDetails.value.ultrasoundRate = availableCommissions.ultrasound;

  const breakdown = getReferralCommissionAllocation(referrer, items.value, summary.value.payableAmount || summary.value.total || 0);
  const totalCommissionAmount = breakdown.total;
  const categoriesWithoutCommission = breakdown.categories.filter((entry) => !entry.hasCommission).map((entry) => entry.category);
  const categoriesWithCommission = breakdown.categories.filter((entry) => entry.hasCommission).map((entry) => entry.category);

  if (categoriesWithoutCommission.length > 0 && categoriesWithCommission.length === 0) {
    commissionDetails.value.noCommissionMessage = `This referrer doesn't have commission for: ${categoriesWithoutCommission.join(', ')}`;
    commission.value.commissionRate = 0;
    commission.value.slider = 0;
    commissionDetails.value.manualCommissionEnabled = true;
  } else {
    const totalBillAmount = summary.value.payableAmount || summary.value.total;
    const effectiveCommissionRate =
      totalBillAmount > 0 ? (totalCommissionAmount / totalBillAmount) * 100 : 0;

    commission.value.commissionRate = parseFloat(effectiveCommissionRate.toFixed(2));
    commission.value.slider = parseFloat(effectiveCommissionRate.toFixed(2));
    commissionDetails.value.manualCommissionEnabled = false;
    commissionDetails.value.noCommissionMessage = "";
  }

  updateCommission();
};

const resetCommissionDetails = () => {
  commissionDetails.value = {
    hasPathologyCommission: false,
    hasRadiologyCommission: false,
    hasMedicineCommission: false,
    hasECGCommission: false,
    hasUltrasoundCommission: false,
    pathologyRate: 0,
    radiologyRate: 0,
    medicineRate: 0,
    ecgRate: 0,
    ultrasoundRate: 0,
    manualCommissionEnabled: false,
    noCommissionMessage: "",
  };
};

const normalizeCommissionCategory = (rawCategory) => {
  const cat = String(rawCategory || "").trim().toLowerCase();
  if (!cat) return "";

  if (["pathology", "pathological"].includes(cat)) return "pathology";
  if (["medicine", "pharmacy", "drug"].includes(cat)) return "medicine";
  if (["ecg", "ekg"].includes(cat)) return "ecg";
  if (["ultrasound", "ultrasonogram", "ultrasonography", "usg", "sono"].includes(cat)) return "ultrasound";
  if (["radiology", "xray", "x-ray", "ct", "mri"].includes(cat)) return "radiology";
  if (["opd", "appointment"].includes(cat)) return cat === "appointment" ? "appointment" : "opd";
  if (["ipd"].includes(cat)) return "ipd";

  return cat;
};

const getCategoryCommissionRate = (referrer, rawCategory) => {
  const category = normalizeCommissionCategory(rawCategory);
  const radiologyFallback = Number(referrer?.radiology_commission || 0);
  switch (category) {
    case "pathology":
      return Number(referrer?.pathology_commission || 0);
    case "radiology":
      return Number(referrer?.radiology_commission || 0);
    case "ecg":
      return Number(referrer?.ecg_commission || radiologyFallback || 0);
    case "ultrasound":
      return Number(referrer?.ultrasound_commission || radiologyFallback || 0);
    case "medicine":
      return Number(referrer?.pharmacy_commission || 0);
    case "opd":
      return Number(referrer?.opd_commission || 0);
    case "ipd":
      return Number(referrer?.ipd_commission || 0);
    case "appointment":
      return Number(referrer?.opd_commission || 0);
    default:
      return 0;
  }
};

const updateCommission = () => {
  const totalAmount = summary.value.payableAmount || commission.value.total;
  const commissionPercent = commission.value.commissionRate / 100;
  // Prefer summing per-category rounded commissions when available to avoid tiny FP mismatches
  try {
    if (!commissionDetails.value.manualCommissionEnabled && commissionBreakdown.value && commissionBreakdown.value.length > 0) {
      const totalFromBreakdown = commissionBreakdown.value.reduce((s, b) => s + (parseFloat(b.commission) || 0), 0);
      commission.value.physystAmt = parseFloat(totalFromBreakdown.toFixed(2));
      return;
    }
  } catch (e) { /* fallback to percent calc */ }

  commission.value.physystAmt = parseFloat((totalAmount * commissionPercent).toFixed(2));
};

const calculateChangeAndDue = () => {
  const roundMoney = (value) => parseFloat((value || 0).toFixed(2));
  const payableAmount = Math.max(0, parseFloat(summary.value.payableAmount) || 0);
  const receivingAmount = Math.max(0, parseFloat(summary.value.receivingAmt) || 0);
  const isEditingExistingBill = Boolean(isEditMode.value || (props.id && props.editData));

  // Baseline is the persisted paid amount for this bill. When editing, we must
  // never discard that baseline just because the form is re-rendered or the user
  // hasn't entered a new amount yet.
  const persistedPaidAmount = isEditingExistingBill
    ? Math.max(0, parseFloat(props.editData?.paid_amt) || 0)
    : 0;
  const existingReturnAmount = isEditingExistingBill
    ? Math.max(0, parseFloat(props.editData?.return_amt) || 0)
    : 0;

  // Gross received should be the actual cash already collected minus any prior
  // refund/return that has already been issued.
  const grossReceived = Math.max(0, persistedPaidAmount - existingReturnAmount);

  // New receiving is only the amount currently entered in this edit session.
  // It must be added on top of the persisted paid total, not replace it.
  const totalGrossReceived = grossReceived + receivingAmount;
  const effectivePaid = Math.min(payableAmount, totalGrossReceived);
  const returnAmount = Math.max(0, totalGrossReceived - payableAmount);
  const dueAmount = Math.max(0, payableAmount - effectivePaid);

  // Keep the payment fields stable during edit mode to avoid the UI jumping
  // back to a wrong total when the user does not enter a fresh payment.
  summary.value.receivingAmt = roundMoney(receivingAmount);
  summary.value.paidAmt = roundMoney(effectivePaid);
  summary.value.returnAmt = roundMoney(returnAmount);
  summary.value.changeAmt = 0;
  summary.value.dueAmount = roundMoney(dueAmount);
};

const commissionBreakdown = computed(() => {
  if (!commission.value.referrer_id || items.value.length === 0) return [];

  const breakdown = [];
  const selectedReferrer = props.referrers.find(
    (referrer) => referrer.id == commission.value.referrer_id
  );

  if (!selectedReferrer) return breakdown;

  const categoryTotals = getReferralCommissionBaseItems().reduce((acc, item) => {
    const category = normalizeCommissionCategory(item.category);
    if (!acc[category]) {
      acc[category] = {
        total: 0,
        items: [],
      };
    }
    acc[category].total += item.netAmount;
    acc[category].items.push(item);
    return acc;
  }, {});

  Object.keys(categoryTotals).forEach((category) => {
    const itemsInCat = categoryTotals[category].items || [];
    let categoryAmount = 0;
    let categoryCommission = 0;
    let rateName = '';

    let weightedRateSum = 0;
    itemsInCat.forEach((it) => {
      const itemRate = (it.referral_percentage && Number(it.referral_percentage) > 0)
        ? Number(it.referral_percentage)
        : getCategoryCommissionRate(selectedReferrer, category);
      const amt = Number(it.netAmount || 0);
      categoryAmount += amt;
      categoryCommission += calculateReferralCommission(amt, itemRate);
      weightedRateSum += (itemRate * amt);
    });
    const categoryRate = categoryAmount > 0 ? (weightedRateSum / categoryAmount) : 0;

    switch (category) {
      case "pathology": rateName = "Pathology Commission"; break;
      case "radiology": rateName = "Radiology Commission"; break;
      case "ecg": rateName = "ECG Commission"; break;
      case "ultrasound": rateName = "Ultrasound Commission"; break;
      case "medicine": rateName = "Pharmacy Commission"; break;
      case "opd": rateName = "OPD Commission"; break;
      case "ipd": rateName = "IPD Commission"; break;
      case "appointment": rateName = "Appointment Commission"; break;
    }

    breakdown.push({
      category: category.charAt(0).toUpperCase() + category.slice(1),
      rateName,
      rate: parseFloat(categoryRate.toFixed(2)),
      amount: categoryAmount,
      commission: parseFloat(categoryCommission.toFixed(2)),
      hasCommission: categoryCommission > 0,
    });
  });

  return breakdown;
});

const normalizeBillingItemCategory = (category, itemName = '') => {
  const categoryText = String(category ?? '').trim();
  const nameText = String(itemName ?? '').trim();
  const haystack = `${categoryText} ${nameText}`.toLowerCase();

  if (/(ultrasound|ultrasonogram|ultrasonography|usg|sono|sonography)/.test(haystack)) {
    return 'Ultrasound';
  }

  if (/(ecg|ekg|electrocardiogram)/.test(haystack)) {
    return 'ECG';
  }

  if (/(radiology|xray|x-ray|x ray|mri|ct|imaging|radiolog)/.test(haystack)) {
    return 'Radiology';
  }

  if (!categoryText) {
    return 'Service';
  }

  return categoryText;
};

const normalizeForMatch = (value) => {
  if (value == null) return '';
  return String(value)
    .normalize('NFKC')
    .toLowerCase()
    .replace(/[^\p{L}\p{N}\s]/gu, ' ')
    .replace(/\s+/g, ' ')
    .trim();
};

const isDisposableValue = (value) => {
  if (value == null) return false;
  try {
    return /dispos|ডিসপ/.test(String(value).toLowerCase());
  } catch (error) {
    return false;
  }
};

const allAvailableItems = computed(() => {
  const tests = (props.pathologyAndRadiologyTests || []).map((test) => ({
    id: test.id,
    name: test.test_name,
    category: normalizeBillingItemCategory(test.category_type, test.test_name),
    unitPrice: test.amount,
    referral_percentage: test.referral_percentage ?? 0,
    type: "test",
    room_no: test.room_no ?? '',
    alt: [test.test_name, test.test_short_name ?? '', test.category_type ?? '']
      .filter(Boolean).join(' | '),
    normalizedCategories: [test.category_type, test.module, test.charge_category, test.main_category].filter(Boolean).map(normalizeForMatch),
    is_disposable: (isDisposableValue(test.test_name) || isDisposableValue(test.category_type) || isDisposableValue(test.short_name) || isDisposableValue(test.charge_name)),
    discountPercentage: Math.min(100, Math.max(0, Number(test.discount_percentage ?? 0) || 0)),
    discountRateLocked: Boolean(test.discount_rate_locked),
    discountExempt: Boolean(test.discount_rate_locked) && Number(test.discount_percentage ?? 0) <= 0,
    reagentRemainingTests: reagentAvailabilityForTest(test.id),
    reagentStockItems: reagentStockItemsForTest(test.id),
    reagentFilmCategory: props.reagentTestAvailability?.[String(test.id)]?.film_category ?? null,
    reagentFilmUsageQuantity: Number(props.reagentTestAvailability?.[String(test.id)]?.film_usage_quantity ?? 0),
  }));

  const medicines = medicineSearchResults.value
    .map((medicine) => ({
      id: medicine.id,
      name: medicine.medicine_name,
      category: "Medicine",
      unitPrice: medicine.medicine_unit_selling_price,
      stock: medicine.medicine_quantity,
      referral_percentage: medicine.referral_percentage ?? 0,
      type: "medicine",
      alt: [medicine.medicine_name, medicine.medicine_code ?? '']
        .filter(Boolean).join(' | '),
      normalizedCategories: [medicine.category || 'Medicine', medicine.sub_category || medicine.type].filter(Boolean).map(normalizeForMatch),
      is_disposable: (isDisposableValue(medicine.medicine_name) || Object.keys(medicine||{}).some(k=> /dispos|ডিসপ/i.test(String(k)))),
    }));

  const storeItems = (props.departmentStoreItems || [])
    .filter((item) => Number(item.stock) > 0)
    .map((item) => ({
      id: Number(item.id),
      name: item.name,
      category: 'Store Inventory',
      stockCategory: item.stock_category || '',
      unitPrice: Number(item.unit_price || 0),
      stock: Number(item.stock || 0),
      stockUnit: item.stock_unit || '',
      department_id: Number(item.department_id),
      departmentName: item.department_name || '',
      type: 'store',
      alt: [item.name, item.stock_category, item.department_name].filter(Boolean).join(' | '),
      normalizedCategories: ['Store Inventory', item.stock_category].filter(Boolean).map(normalizeForMatch),
      is_disposable: false,
    }));

    // Map any hospitalCharges provided by the backend into charge items
    const charges = (props.hospitalCharges || []).map((charge) => {
      // backend may provide `module` as a JSON-encoded array string or plain string
      let cat = '';
      try {
        if (charge.module) {
          try {
            const parsed = JSON.parse(charge.module);
            if (Array.isArray(parsed) && parsed.length > 0) cat = String(parsed[0]);
            else cat = String(parsed || '');
          } catch (e) {
            cat = String(charge.module || '');
          }
        }
      } catch (e) { cat = String(charge.module || ''); }

      return {
        id: charge.id,
        name: charge.name,
        category: cat || '',
        unitPrice: charge.amount ?? 0,
        type: 'charge',
        alt: [charge.name, charge.short_name ?? ''].filter(Boolean).join(' | '),
        normalizedCategories: (cat ? [normalizeForMatch(cat)] : []),
        is_disposable: /dispos|ডিসপ/i.test(String(charge.name || '') + String(cat || '')),
      };
    });

    // Post-process items to coerce disposable detection into category when needed
    const allItems = [...tests, ...medicines, ...storeItems, ...charges].filter(canShowBillingItem);
  const disposableKeywords = ['tube','v tube','v.tube','syringe','needle','glove','gauze','dispos'];
  allItems.forEach(it => {
    try {
      const rawHay = ((it.name||'') + ' ' + (it.alt||'')).toLowerCase();
      const normalizedHay = normalizeForMatch(rawHay);
      // Match disposable keywords as whole words (use word boundaries) on normalized text
      const detectedByKeyword = disposableKeywords.some(k => {
        const nk = normalizeForMatch(k);
        if (!nk) return false;
        const re = new RegExp('\\b' + nk.replace(/\\s+/g, '\\s+') + '\\b');
        return re.test(normalizedHay);
      });
      const detected = it.is_disposable || detectedByKeyword;
      if (detected) {
        // For non-medicine items, coerce category to Disposable so category filters work.
        // Do NOT coerce medicines into the Disposable category to avoid showing
        // pharmacy/medicine rows when user selects Disposable.
        try {
          it.normalizedCategories = Array.isArray(it.normalizedCategories) ? it.normalizedCategories : [];
        } catch(e) { it.normalizedCategories = []; }

        if (it.type !== 'medicine' && it.type !== 'store') {
          it.category = 'Disposable';
          if (!it.normalizedCategories.some(nc => nc.includes('dispos'))) {
            it.normalizedCategories.push(normalizeForMatch('Disposable'));
          }
        } else {
          // mark medicine as disposable-flagged but keep its category as Medicine
          it.is_disposable = true;
        }
      }
    } catch(e){}
  });

  allItems.forEach((item) => {
    item.room_no = resolveCategoryRoomNumber(item.category, item.room_no ?? '');
  });

  allItems.forEach((item) => {
    item.searchName = normalizeForMatch(item.name || '');
    item.searchAlt = normalizeForMatch(item.alt || '');
    item.searchText = normalizeForMatch(`${item.name || ''} ${item.alt || ''}`);
  });

  return allItems;
});

// Unique category types for dropdowns (includes known defaults + detected ones)
const categoryTypes = computed(() => {
  if (props.isPharmacyDepartment) {
    return ['Medicine'];
  }

  const set = new Set();

  allAvailableItems.value.filter((item) => item.type === 'test').forEach(t => {
    const v = (t.category || '').toString().trim();
    if (v) set.add(v);
  });

  if ((props.departmentStoreItems || []).length > 0) set.add('Store Inventory');

  // include hospitalCharges from props and any extra fetched charges
  // hospitalCharges categories removed; categories come from tests and medicines above

  // Ensure a set of common types are always present
  ['Pathology','Radiology','OPD','IPD','Appointment','ECG','Ultrasound','Blood Bank','Ambulance','Disposable']
    .forEach(s => set.add(s));

  return Array.from(set).filter((category) => (
    !['medicine', 'pharmacy'].includes(normalizeForMatch(category))
  ));
});

// debug logging removed to reduce console noise

const deliveryDateFormatted = computed(() => {
  const raw = summary.value.deliveryDate;
  if (!raw) return "";
  try {
    const d = new Date(raw);
    return format(d, 'dd-MMM-yyyy');
  } catch (e) {
    return "";
  }
});

// Expose debug helpers when running in browser DevTools so users can inspect
// normalized items and category types even when Vue internals aren't available.
// removed dev debug helpers to reduce startup work and console noise

// Fetch hospital charges on mount to ensure recently-created charges
// (e.g., Disposable items) created outside this page are available.
// hospitalCharges fetch removed — Billing uses Item/Test lists only

// Additional debug helpers to troubleshoot missing items
onMounted(() => {
  try {
    window.__billing_debug.getPropCounts = () => ({
      pathologyAndRadiologyTests: (props.pathologyAndRadiologyTests || []).length,
      medicineSearchResults: medicineSearchResults.value.length,
    });

    window.__billing_debug.search = (term) => {
      const raw = String(term || '').trim();
      if (!raw) return [];
      const tokens = raw.split(/\s+/).map(t => normalizeForMatch(t)).filter(Boolean);
      if (!tokens.length) return [];

      const hits = (allAvailableItems.value || []).filter(it => {
        try {
          const hay = normalizeForMatch(((it.name||'') + ' ' + (it.alt||'') + ' ' + (it.category||'') + ' ' + (JSON.stringify(it || {}))));
          // tokenized match: require every token to appear somewhere in the haystack
          const tokensMatch = tokens.every(tok => hay.includes(tok));
          const catsMatch = Array.isArray(it.normalizedCategories) && tokens.every(tok => it.normalizedCategories.some(nc => nc.includes(tok)));
          return tokensMatch || catsMatch;
        } catch (e) { return false; }
      });
      // debug log removed
      return hits.slice(0, 50);
    };
  } catch (e) {}
});

const filteredItems = computed(() => {
  const rawQuery = String(searchQuery.value || '').trim().toLowerCase();
  const tokens = rawQuery.length ? rawQuery.split(/\s+/).filter(Boolean) : [];
  const normalizedTokens = tokens.length ? tokens.map(t => normalizeForMatch(t)).filter(Boolean) : [];

  const buildTestItem = (test) => ({
    id: test.id,
    name: (test.test_name || '').trim(),
    category: test.category_type || '',
    unitPrice: test.amount ?? 0,
    referral_percentage: test.referral_percentage ?? 0,
    type: 'test',
    reagentRemainingTests: reagentAvailabilityForTest(test.id),
    reagentStockItems: reagentStockItemsForTest(test.id),
    reagentFilmCategory: props.reagentTestAvailability?.[String(test.id)]?.film_category ?? null,
    reagentFilmUsageQuantity: Number(props.reagentTestAvailability?.[String(test.id)]?.film_usage_quantity ?? 0),
    // include extra searchable fields
    alt: [test.test_name, test.short_name ?? '', test.charge_name ?? ''].filter(Boolean).join(' | '),
  });

  const buildMedicineItem = (medicine) => ({
    id: medicine.id,
    name: (medicine.medicine_name || '').trim(),
    category: 'Medicine',
    unitPrice: medicine.medicine_unit_selling_price ?? 0,
    referral_percentage: medicine.referral_percentage ?? 0,
    stock: medicine.medicine_quantity ?? 0,
    type: 'medicine',
    alt: [medicine.medicine_name, medicine.medicine_code ?? ''].filter(Boolean).join(' | '),
  });

  const buildChargeItem = (charge) => ({
    id: charge.id,
    name: (charge.name || '').trim(),
    category: charge.module || '',
    unitPrice: charge.amount ?? 0,
    type: 'charge',
    alt: [charge.name, charge.short_name ?? ''].filter(Boolean).join(' | '),
  });

  let itemsToFilter = [];

  if (itemForm.value.category) {
    const selectedCategory = String(itemForm.value.category || '').toLowerCase();

    // If user typed tokens, perform a global name-based match across all items
    // so typing a name will show matching items even when no category selected.
    if (tokens.length > 0) {
      const tokenMatchesAll = allAvailableItems.value.filter((item) =>
        normalizedTokens.length > 0 && normalizedTokens.every((token) => item.searchText.includes(token))
      );

      // Prefer token matches that also match the selected category.
      // Do NOT fall back to tokenMatchesAll when a category is selected;
      // instead perform strict category-only matching if tokens don't yield
      // category matches.
      const tokenCategoryMatches = tokenMatchesAll.filter((it) => {
        try {
          const icat = normalizeForMatch(it.category || '');
          const sc = normalizeForMatch(selectedCategory || '');
          // treat OPD and Appointment as synonyms for matching
          if ((sc === 'appointment' && icat === 'opd') || (sc === 'opd' && icat === 'appointment')) return true;
          if (icat && (icat === sc || icat.includes(sc) || sc.includes(icat))) return true;
          if (Array.isArray(it.normalizedCategories) && it.normalizedCategories.length > 0) {
            if (it.normalizedCategories.some(nc => nc === sc || nc.includes(sc) || sc.includes(nc))) return true;
          }
          return false;
        } catch (e) { return false; }
      });
      if (tokenCategoryMatches.length > 0) {
        // When a category is selected, prefer strict category-matching token
        // results only — do NOT include other-category token matches.
        itemsToFilter = tokenCategoryMatches;
      } else if (tokenMatchesAll.length > 0) {
        // No category-specific token matches, but there are token matches
        // across other categories — show them (name-based search).
        itemsToFilter = tokenMatchesAll;
      } else {
        // If tokens exist but none matched by name, fall back to strict
        // category-only matching so selecting a category filters items.
        itemsToFilter = allAvailableItems.value.filter((it) => {
          const icat = normalizeForMatch(it.category || '');
          const iname = normalizeForMatch(it.name || '');
          const ialt = normalizeForMatch(it.alt || '');
          const sc = normalizeForMatch(selectedCategory || '');
          if (!sc) return false;

          if (icat && (icat === sc || icat.includes(sc) || sc.includes(icat))) return true;
          // treat OPD and Appointment as synonyms for matching
          if ((sc === 'appointment' && icat === 'opd') || (sc === 'opd' && icat === 'appointment')) return true;
          try {
            if (Array.isArray(it.normalizedCategories) && it.normalizedCategories.length > 0) {
              if (it.normalizedCategories.some(nc => nc === sc || nc.includes(sc) || sc.includes(nc))) return true;
            }
          } catch (e) {}
          if (iname && iname.includes(sc)) return true;
          if (ialt && ialt.includes(sc)) return true;
          const scTokens = sc.split(' ').filter(Boolean);
          if (scTokens.length > 0) {
            const anyTokenMatch = scTokens.some(tok => iname.includes(tok) || ialt.includes(tok) || icat.includes(tok) || (Array.isArray(it.normalizedCategories) && it.normalizedCategories.some(nc => nc.includes(tok))));
            if (anyTokenMatch) return true;
          }
          try {
            if (sc.includes('dispos')) {
              // Exclude medicine items from Disposable matches unless they are
              // explicitly categorized as Disposable on the server side.
              if (it.type === 'medicine') {
                if (String(it.category || '').toLowerCase().includes('dispos')) return true;
              } else {
                if (it.is_disposable) return true;
                const hasDisposableFlag = Object.keys(it || {}).some(k => String(k || '').toLowerCase().includes('dispos') && Boolean(it[k]));
                if (hasDisposableFlag) return true;
              }
            }
          } catch (e) { /* ignore */ }
          return false;
        });
      }
    } else {
      // No tokens typed; perform category-only matching
      const sc = normalizeForMatch(String(selectedCategory || ''));
      itemsToFilter = allAvailableItems.value.filter((it) => {
        const icat = normalizeForMatch(it.category || '');
        const iname = normalizeForMatch(it.name || '');
        const ialt = normalizeForMatch(it.alt || '');
        if (!sc) return false;
        if (icat && (icat === sc || icat.includes(sc) || sc.includes(icat))) return true;
        try {
          if (Array.isArray(it.normalizedCategories) && it.normalizedCategories.length > 0) {
            if (it.normalizedCategories.some(nc => nc === sc || nc.includes(sc) || sc.includes(nc))) return true;
          }
        } catch (e) {}
        if (iname && iname.includes(sc)) return true;
        if (ialt && ialt.includes(sc)) return true;
        const scTokens = sc.split(' ').filter(Boolean);
        if (scTokens.length > 0) {
          const anyTokenMatch = scTokens.some(tok => iname.includes(tok) || ialt.includes(tok) || icat.includes(tok) || (Array.isArray(it.normalizedCategories) && it.normalizedCategories.some(nc => nc.includes(tok))));
          if (anyTokenMatch) return true;
        }
        try {
          if (sc.includes('dispos')) {
            if (it.is_disposable) return true;
            const hasDisposableFlag = Object.keys(it || {}).some(k => String(k || '').toLowerCase().includes('dispos') && Boolean(it[k]));
            if (hasDisposableFlag) return true;
          }
        } catch (e) { /* ignore */ }
        return false;
      });

      // Fallback: if user selected "disposable" but category-based lookup
      // found nothing, include any item that is flagged as disposable via
      // name/flags/normalizedCategories. This handles items whose explicit
      // `category` field isn't set to "Disposable" but are still disposable.
      try {
        if ((String(sc || '').includes('dispos')) && itemsToFilter.length === 0) {
          itemsToFilter = (allAvailableItems.value || []).filter(it => {
            try {
              if (!it) return false;
              // exclude medicines unless explicitly categorized as Disposable
              if (it.type === 'medicine') {
                if (String(it.category || '').toLowerCase().includes('dispos')) return true;
                return false;
              }
              // only include items explicitly flagged as disposable or categorized so
              if (it.is_disposable) return true;
              if (Array.isArray(it.normalizedCategories) && it.normalizedCategories.some(nc => nc.includes('dispos'))) return true;
              if (String(it.category || '').toLowerCase().includes('dispos')) return true;
            } catch (e) { /* ignore */ }
            return false;
          });
        }
      } catch (e) { /* ignore */ }
      // Extra fallback: search the full serialized object for 'dispos' token
      // Removed broad serialized-object fallback to avoid false-positive matches
    }

  } else {
    // Reuse pre-built unified list to avoid rebuilding arrays on every computed evaluation
    itemsToFilter = allAvailableItems.value;
  }

  // If no query, return items as-is (possibly filtered by category)
  if (!tokens.length) return itemsToFilter;

  // Match items where every token appears in either the name or alt fields
  if (!normalizedTokens.length) return [];
  
  // Filter items that match the search query
  const matchedItems = itemsToFilter.filter((item) => {
    return normalizedTokens.every((token) => item.searchText.includes(token));
  });

  // Smart sorting: prioritize by match quality and position
  const normalizedQuery = normalizeForMatch(rawQuery);
  
  return matchedItems.sort((a, b) => {
    const normalizedAName = a.searchName;
    const normalizedAAlt = a.searchAlt;
    const normalizedBName = b.searchName;
    const normalizedBAlt = b.searchAlt;
    
    // Helper function to calculate match score
    const getMatchScore = (itemName, itemAlt) => {
      // Priority 1: Name starts with exact query
      if (itemName.startsWith(normalizedQuery)) return 100;
      
      // Priority 2: Alt starts with exact query
      if (itemAlt.startsWith(normalizedQuery)) return 95;
      
      // Priority 3: Name starts with first character
      if (normalizedQuery.length > 0 && itemName.startsWith(normalizedQuery.charAt(0))) return 90;
      
      // Priority 4: Alt starts with first character
      if (normalizedQuery.length > 0 && itemAlt.startsWith(normalizedQuery.charAt(0))) return 85;
      
      // Priority 5: Query appears at word boundary in name
      const nameWords = itemName.split(/\s+/);
      if (nameWords.some(word => word.startsWith(normalizedQuery))) return 80;
      
      // Priority 6: Query appears at word boundary in alt
      const altWords = itemAlt.split(/\s+/);
      if (altWords.some(word => word.startsWith(normalizedQuery))) return 75;
      
      // Priority 7: Exact substring match in name
      if (itemName.includes(normalizedQuery)) return 70;
      
      // Priority 8: Exact substring match in alt
      if (itemAlt.includes(normalizedQuery)) return 65;
      
      // Priority 9: Token-based match (default, already filtered)
      return 50;
    };
    
    const scoreA = getMatchScore(normalizedAName, normalizedAAlt);
    const scoreB = getMatchScore(normalizedBName, normalizedBAlt);
    
    return scoreB - scoreA; // Higher score comes first
  });
});

watch(filteredItems, (matches) => {
  if (searchQuery.value && matches.length > 0 && selectedIndex.value < 0) {
    selectedIndex.value = 0;
  }
});

const addItem = () => {
  if (!itemForm.value.itemName || itemForm.value.unitPrice <= 0) {
    displayWarning({
      message: "Please select a valid item and ensure price is greater than 0.",
    });
    return;
  }

  const selectedCategory = String(itemForm.value.category || '').trim().toLowerCase();
  const selectedId = Number(itemForm.value.itemId);
  const existingItem = items.value.find((item) => {
    const existingCategory = String(item.category || '').trim().toLowerCase();
    const sameCategory = existingCategory === selectedCategory;
    const sameId = Number.isFinite(selectedId)
      && selectedId > 0
      && Number(item.id) === selectedId;

    return sameCategory && (
      sameId
      || String(item.name || '').trim().toLowerCase() === String(itemForm.value.itemName || '').trim().toLowerCase()
    );
  });

  if (existingItem) {
    const duplicateItemName = itemForm.value.itemName;
    requestMedicineSearch.cancel();
    medicineSearchController?.abort();
    medicineSearchResults.value = [];
    medicineSearchLoading.value = false;
    itemForm.value.category = '';
    itemForm.value.itemName = '';
    itemForm.value.itemId = null;
    itemForm.value.unitPrice = 0;
    itemForm.value.totalAmount = 0;
    searchQuery.value = '';
    selectedIndex.value = -1;
    displayWarning({
      message: `"${duplicateItemName}" is already in the bill. Duplicate items cannot be added.`,
    });
    itemNameInput.value?.focus();
    return;
  }

  if (itemForm.value.category.toLowerCase() === "medicine") {
    const selectedMedicine = medicineSearchResults.value.find(
      (medicine) => Number(medicine.id) === Number(itemForm.value.itemId)
    );

    if (selectedMedicine) {
      const totalInCart = items.value
        .filter((item) => item.id === selectedMedicine.id)
        .reduce((sum, item) => sum + item.quantity, 0);

      const requestedTotal = totalInCart + itemForm.value.quantity;

      if (requestedTotal > selectedMedicine.medicine_quantity) {
        const available = selectedMedicine.medicine_quantity - totalInCart;
        displayWarning({
          message: `Only ${available} units available in stock (${selectedMedicine.medicine_quantity} total, ${totalInCart} already in cart).`,
        });
        return;
      }
    }
  }

  const selectedInventoryItem = allAvailableItems.value.find(
    (availableItem) => Number(availableItem.id) === Number(itemForm.value.itemId)
      && availableItem.name === itemForm.value.itemName
      && availableItem.category === itemForm.value.category
  );
  if (selectedInventoryItem?.type === 'store') {
    const requestedQuantity = Number(itemForm.value.quantity || 0);
    if (requestedQuantity > Number(selectedInventoryItem.stock || 0)) {
      displayWarning({
        message: `Only ${Number(selectedInventoryItem.stock || 0).toFixed(2)} ${selectedInventoryItem.stockUnit || 'units'} available in ${selectedInventoryItem.departmentName || 'department'} stock.`,
      });
      return;
    }
  }

  let reagentTestsRemainingAfterAdd = null;
  if (
    selectedInventoryItem?.type === 'test'
    && isReagentTrackedTestCategory(selectedInventoryItem.category)
    && selectedInventoryItem.reagentRemainingTests !== null
  ) {
    const quantityInCart = items.value
      .filter((item) => Number(item.id) === Number(selectedInventoryItem.id)
        && String(item.category).toLowerCase() === String(selectedInventoryItem.category).toLowerCase())
      .reduce((total, item) => total + Number(item.quantity || 0), 0);
    const availableForThisBill = Math.max(0, selectedInventoryItem.reagentRemainingTests - quantityInCart);
    const requestedQuantity = Number(itemForm.value.quantity || 0);

    if (requestedQuantity > availableForThisBill) {
      displayWarning({
        message: `Only ${availableForThisBill} ${availableForThisBill === 1 ? 'test' : 'tests'} can be performed with current Lab reagent stock for "${selectedInventoryItem.name}". Send more stock to Lab before billing.`,
      });
      return;
    }

    reagentTestsRemainingAfterAdd = Math.max(0, availableForThisBill - requestedQuantity);
  }

  items.value.push({
    id: itemForm.value.itemId,
    name: itemForm.value.itemName,
    category: itemForm.value.category,
    type: selectedInventoryItem?.type || '',
    stock: selectedInventoryItem?.stock ?? null,
    stockUnit: selectedInventoryItem?.stockUnit || '',
    stockCategory: selectedInventoryItem?.stockCategory || '',
    department_id: selectedInventoryItem?.department_id ?? null,
    departmentName: selectedInventoryItem?.departmentName || '',
    reagentFilmCategory: selectedInventoryItem?.reagentFilmCategory ?? null,
    reagentFilmUsageQuantity: Number(selectedInventoryItem?.reagentFilmUsageQuantity ?? 0),
    unitPrice: parseFloat(itemForm.value.unitPrice),
    referral_percentage: selectedInventoryItem?.referral_percentage ?? 0,
    roomNo: selectedInventoryItem?.room_no ?? '',
    quantity: parseFloat(itemForm.value.quantity),
    totalAmount: parseFloat(itemForm.value.totalAmount),
    discount: 0,
    discountExempt: Boolean(selectedInventoryItem?.discountExempt),
    discountPercentage: Number(selectedInventoryItem?.discountPercentage || 0),
    discountRateLocked: Boolean(selectedInventoryItem?.discountRateLocked),
    rugound: 0,
    netAmount: parseFloat(itemForm.value.totalAmount),
  });

  if (reagentTestsRemainingAfterAdd !== null && reagentTestsRemainingAfterAdd <= 20) {
    displayWarning({
      message: `Low Lab reagent stock for "${selectedInventoryItem.name}": about ${reagentTestsRemainingAfterAdd} ${reagentTestsRemainingAfterAdd === 1 ? 'test' : 'tests'} will remain after this bill.`,
    });
  }

  itemForm.value = {
    category: "",
    itemName: "",
    itemId: null,
    unitPrice: 0,
    quantity: 1,
    totalAmount: 0,
  };

  updateSummary();

  nextTick(() => {
    itemNameInput.value.focus();
  });
};

const removeItem = (index) => {
  if (editingQuantityIndex.value === index) {
    editingQuantityIndex.value = null;
    editedQuantity.value = '';
  }
  items.value.splice(index, 1);
  updateSummary();
};

const startEditingItemQuantity = (index) => {
  editingQuantityIndex.value = index;
  editedQuantity.value = String(items.value[index]?.quantity ?? 1);
  nextTick(() => {
    const input = document.querySelector(`[data-bill-quantity-index="${index}"]`);
    input?.focus();
    input?.select();
  });
};

const commitItemQuantity = (index) => {
  if (editingQuantityIndex.value !== index) return;

  const item = items.value[index];
  const quantity = Number(editedQuantity.value);
  if (!item || !Number.isInteger(quantity) || quantity < 1) {
    displayWarning({ message: 'Quantity must be a whole number greater than zero.' });
    editedQuantity.value = String(item?.quantity ?? 1);
    return;
  }

  if (item.type === 'medicine' || item.type === 'store') {
    const otherQuantity = items.value.reduce((total, cartItem, cartIndex) =>
      cartIndex !== index && Number(cartItem.id) === Number(item.id)
        ? total + Number(cartItem.quantity || 0)
        : total, 0);
    const available = Number(item.stock || 0) - otherQuantity;
    if (quantity > available) {
      displayWarning({
        message: `Only ${Math.max(0, available)} ${item.type === 'store' ? (item.stockUnit || 'units') : 'units'} available in stock.`,
      });
      editedQuantity.value = String(item.quantity);
      return;
    }
  }

  item.quantity = quantity;
  item.totalAmount = Number((Number(item.unitPrice || 0) * quantity).toFixed(2));
  editingQuantityIndex.value = null;
  editedQuantity.value = '';
  updateSummary();
};

const cancelItemQuantityEdit = () => {
  editingQuantityIndex.value = null;
  editedQuantity.value = '';
};

const normalizeText = (value) => String(value ?? '').trim().toLowerCase();

const isDisposableBillingItem = (item) => {
  if (!item) return false;
  if (item.is_disposable || isDisposableValue(item.category)) return true;
  const name = String(item.name || '').toLowerCase();
  return /dispos|tube|butterfly|needle/.test(name);
};

const isDiscountExemptBillingItem = (item) => {
  if (Boolean(item?.discountExempt || item?.discount_exempt)) {
    return true;
  }

  const locked = Boolean(item?.discountRateLocked || item?.discount_rate_locked);
  const percent = Number(item?.discountPercentage ?? item?.discount_percentage ?? 0);

  return locked && percent <= 0;
};

const getDisplayedItemDiscountPercent = (item) => {
  if (isDisposableBillingItem(item) || isDiscountExemptBillingItem(item)) return 0;

  if (summary.value.discountType === 'flat') {
    const totalAmount = Number(item?.totalAmount || 0);
    return totalAmount > 0 ? (Number(item?.discount || 0) / totalAmount) * 100 : 0;
  }

  const billPercent = Math.max(0, Number(summary.value.discount || 0));
  if (billPercent <= 0) {
    return 0;
  }

  const itemPercent = Math.max(0, Number(item?.discountPercentage ?? item?.discount_percentage ?? 0));
  if (itemPercent > 0) {
    return Math.min(billPercent, itemPercent);
  }

  const categoryCap = resolveCategoryDiscountCap(item?.category);
  return categoryCap === null ? billPercent : Math.min(billPercent, categoryCap);
};

// Smart sorting helper for search results
const smartSortSearchResults = (items, queryString, getDisplayText = (item) => item.name || '') => {
  if (!queryString || !queryString.trim()) return items;

  const normalizedQuery = normalizeForMatch(queryString);
  const firstChar = normalizedQuery.charAt(0);

  return items.sort((a, b) => {
    const displayA = getDisplayText(a) || '';
    const displayB = getDisplayText(b) || '';

    const getMatchScore = (itemDisplay) => {
      const normalized = normalizeForMatch(itemDisplay);
      const words = normalized.split(/\s+/).filter(Boolean);
      const firstWord = words[0] || '';

      // Highest priority: query matches the beginning of the first name/word
      if (firstWord.startsWith(normalizedQuery)) return 120;

      // Next: full name starts with the query
      if (normalized.startsWith(normalizedQuery)) return 110;

      // Then: first word starts with the first character of the query
      if (firstChar && firstWord.startsWith(firstChar)) return 100;

      // Then: whole name starts with the first character of the query
      if (firstChar && normalized.startsWith(firstChar)) return 90;

      // Then: any word starts with the query
      if (words.some(word => word.startsWith(normalizedQuery))) return 80;

      // Then: the query appears anywhere in the text
      if (normalized.includes(normalizedQuery)) return 70;

      return 50;
    };

    const scoreA = getMatchScore(displayA);
    const scoreB = getMatchScore(displayB);

    return scoreB - scoreA;
  });
};

const applyPrescriptionPatient = (patient) => {
  if (!patient || typeof patient !== 'object') {
    return;
  }

  isNewPatient.value = false;
  isNewPatientFlag.value = false;

  if (patient.id) {
    patientForm.value.patient_id = patient.id;
  }

  patientSearchQuery.value = patient.name ?? patientSearchQuery.value;
  patientForm.value.patientMobile = patient.phone ?? patientForm.value.patientMobile;
  patientForm.value.gender = patient.gender ?? patientForm.value.gender;
  patientForm.value.dob = patient.dob ?? patientForm.value.dob;
  calculateAgeFromDOB(patientForm.value.dob);
};

const addPrescriptionTestsToItems = (tests = []) => {
  const availableTests = props.pathologyAndRadiologyTests.map((test) => ({
    id: test.id,
    name: String(test.test_name ?? '').trim(),
    category: String(test.category_type ?? 'Pathology').trim(),
    unitPrice: Number(test.amount ?? 0),
    room_no: test.room_no ?? '',
    referral_percentage: test.referral_percentage ?? 0,
  }));

  const existingKeys = new Set(
    items.value
      .filter((item) => String(item.category ?? '').toLowerCase() !== 'medicine')
      .map((item) => `${normalizeText(item.name)}::${normalizeText(item.category)}`)
  );

  let addedCount = 0;
  let skippedCount = 0;

  tests.forEach((rawName) => {
    const targetName = String(rawName ?? '').trim();
    if (!targetName) {
      return;
    }

    const matched = availableTests.find((test) => normalizeText(test.name) === normalizeText(targetName));
    if (!matched) {
      skippedCount += 1;
      return;
    }

    const uniqueKey = `${normalizeText(matched.name)}::${normalizeText(matched.category)}`;
    if (existingKeys.has(uniqueKey)) {
      skippedCount += 1;
      return;
    }

    items.value.push({
      id: matched.id,
      name: matched.name,
      category: matched.category,
      unitPrice: matched.unitPrice,
      roomNo: resolveCategoryRoomNumber(matched.category, matched.room_no || ''),
      referral_percentage: matched.referral_percentage ?? 0,
      discountPercentage: Number(matched.discount_percentage ?? 0),
      discountRateLocked: Boolean(matched.discount_rate_locked),
      quantity: 1,
      totalAmount: matched.unitPrice,
      discount: 0,
      rugound: 0,
      netAmount: matched.unitPrice,
    });

    existingKeys.add(uniqueKey);
    addedCount += 1;
  });

  if (addedCount > 0) {
    updateSummary();
  }

  return { addedCount, skippedCount };
};

const addPrescriptionMedicinesToItems = (medicines = []) => {
  let addedCount = 0;
  let skippedCount = 0;

  medicines.forEach((medicine) => {
    const medicineId = Number(medicine?.medicine_id || 0);
    const medicineName = String(medicine?.medicine_name || '').trim();
    const quantity = Math.max(1, Number(medicine?.quantity || 1));
    const availableQuantity = Number(medicine?.available_quantity || 0);
    const unitPrice = Number(medicine?.sale_price || 0);

    if (!medicineId || !medicineName || quantity > availableQuantity) {
      skippedCount += 1;
      return;
    }

    const alreadyAdded = items.value.some((item) => (
      String(item.category || '').toLowerCase() === 'medicine'
      && Number(item.id) === medicineId
    ));
    if (alreadyAdded) {
      skippedCount += 1;
      return;
    }

    const totalAmount = unitPrice * quantity;
    items.value.push({
      id: medicineId,
      name: medicineName,
      category: 'Medicine',
      unitPrice,
      stock: availableQuantity,
      quantity,
      totalAmount,
      discountPercentage: 0,
      discountRateLocked: false,
      discount: 0,
      rugound: 0,
      netAmount: totalAmount,
    });
    addedCount += 1;
  });

  if (addedCount > 0) {
    updateSummary();
  }

  return { addedCount, skippedCount };
};

const searchPrescriptionCharge = async () => {
  const trimmedId = String(prescriptionSearchId.value ?? '').trim();
  if (!trimmedId) {
    displayWarning({ message: 'Prescription ID দিন।' });
    return;
  }

  prescriptionSearchLoading.value = true;
  prescriptionIpdPatientId.value = null;
  prescriptionIpdPrescriptionId.value = null;

  try {
    const endpoint = route('backend.billing.prescriptions.search');
    const url = `${endpoint}?prescription_id=${encodeURIComponent(trimmedId)}`;
    const response = await fetch(url, {
      headers: {
        Accept: 'application/json',
      },
    });

    const payload = await response.json();

    if (!response.ok) {
      displayWarning({ message: payload?.message || 'Prescription পাওয়া যায়নি।' });
      return;
    }

    applyPrescriptionPatient(payload?.patient);
    prescriptionIpdPatientId.value = payload?.ipd_patient_id ?? null;
    prescriptionIpdPrescriptionId.value = payload?.ipd_prescription_id ?? null;

    const { addedCount = 0, skippedCount = 0 } = props.isPharmacyDepartment
      ? addPrescriptionMedicinesToItems(payload?.medicines || [])
      : addPrescriptionTestsToItems(payload?.tests || []);

    if (addedCount === 0) {
      displayWarning({
        message: props.isPharmacyDepartment
          ? 'Prescription থেকে কোনো medicine যোগ করা যায়নি।'
          : 'Prescription থেকে নতুন কোনো test add করা যায়নি।',
      });
      return;
    }

    const source = payload?.source ? ` (${payload.source})` : '';
    const skipText = skippedCount > 0 ? `, ${skippedCount} skip` : '';
    displayResponse({
      message: props.isPharmacyDepartment
        ? `${addedCount}টি medicine auto add হয়েছে${source}${skipText}.`
        : `${addedCount}টি test auto add হয়েছে${source}${skipText}.`,
    });

    // Reset input after successful load for faster next scan/search.
    prescriptionSearchId.value = '';
    prescriptionSuggestions.value = [];
    showPrescriptionSuggestions.value = false;
    nextTick(() => {
      prescriptionSearchInputRef.value?.focus();
    });
  } catch (error) {
    // console.error removed
    displayWarning({ message: 'Prescription search failed. আবার চেষ্টা করুন।' });
  } finally {
    prescriptionSearchLoading.value = false;
  }
};

const fetchPrescriptionSuggestions = debounce(async (query) => {
  const normalized = String(query ?? '').trim();
  if (!normalized) {
    prescriptionSuggestions.value = [];
    showPrescriptionSuggestions.value = false;
    return;
  }

  prescriptionSuggestionLoading.value = true;
  try {
    const endpoint = route('backend.billing.prescriptions.suggest');
    const response = await fetch(`${endpoint}?search=${encodeURIComponent(normalized)}`, {
      headers: {
        Accept: 'application/json',
      },
    });

    const payload = await response.json();
    if (!response.ok || !Array.isArray(payload)) {
      prescriptionSuggestions.value = [];
      showPrescriptionSuggestions.value = false;
      return;
    }

    prescriptionSuggestions.value = payload;
    showPrescriptionSuggestions.value = payload.length > 0;
  } catch (error) {
    // console.error removed
    prescriptionSuggestions.value = [];
    showPrescriptionSuggestions.value = false;
  } finally {
    prescriptionSuggestionLoading.value = false;
  }
}, 250);

const handlePrescriptionInput = () => {
  fetchPrescriptionSuggestions(prescriptionSearchId.value);
};

const handlePrescriptionBlur = () => {
  setTimeout(() => {
    showPrescriptionSuggestions.value = false;
  }, 180);
};

const selectPrescriptionSuggestion = (suggestion) => {
prescriptionSearchId.value = String(suggestion?.search_value ?? suggestion?.id ?? '').trim();
  showPrescriptionSuggestions.value = false;
  searchPrescriptionCharge();
};

const normalizeDiscountInputsByLimit = () => {
  const total = Number(summary.value.total) || 0;
  const vatAmount = billingVatEnabled.value && billingVatPercent.value > 0
    ? (total * billingVatPercent.value) / 100
    : 0;
  const totalWithVat = total + vatAmount;
  const maxPercent = Number(maxBillingDiscountPercent.value) || 0;
  const maxFlatCap = Number(maxAllowedFlatDiscountAmount.value) || 0;

  if (totalWithVat <= 0) {
    summary.value.discount = 0;
    summary.value.extraFlatDiscount = 0;
    return;
  }

  const maxDiscountAmount = (totalWithVat * maxPercent) / 100;
  const effectivePercentLimit = effectiveBillingDiscountPercentLimit.value;
  const discountType = summary.value.discountType;
  const rawDiscount = Math.max(0, Number(summary.value.discount) || 0);
  const rawExtra = Math.max(0, Number(summary.value.extraFlatDiscount) || 0);

  if (discountType === 'percentage') {
    const safePercent = Math.min(rawDiscount, effectivePercentLimit || maxPercent);

    if (summary.value.discount !== safePercent) {
      summary.value.discount = safePercent;
    }

    // Percentage discount and extra flat discount are independent fields.
    // Never auto-create an extra flat discount from the percentage cap,
    // otherwise edit mode double-counts the same discount.
    const normalizedExtra = Math.min(rawExtra, maxFlatCap || maxDiscountAmount || totalWithVat);
    if (rawExtra > 0 && summary.value.extraFlatDiscount !== normalizedExtra) {
      summary.value.extraFlatDiscount = normalizedExtra;
    } else if (rawExtra <= 0 && summary.value.extraFlatDiscount !== 0) {
      summary.value.extraFlatDiscount = 0;
    }
    return;
  }

  const safeFlatDiscount = Math.min(rawDiscount, maxFlatCap || maxDiscountAmount, totalWithVat);
  const allowedExtra = Math.max(0, maxDiscountAmount - safeFlatDiscount);

  if (summary.value.discount !== safeFlatDiscount) {
    summary.value.discount = safeFlatDiscount;
  }
  const normalizedExtra = Math.min(rawExtra, allowedExtra);
  if (summary.value.extraFlatDiscount !== normalizedExtra) {
    summary.value.extraFlatDiscount = normalizedExtra;
  }
};

const discountableItemsTotal = () => items.value.reduce(
  (sum, item) => sum + (isDisposableBillingItem(item) || isDiscountExemptBillingItem(item) ? 0 : Number(item.totalAmount || 0)),
  0
);

const convertDiscountType = (newType, oldType) => {
  if (!oldType || newType === oldType) return;

  const discountableTotal = discountableItemsTotal();
  const currentValue = Math.max(0, Number(summary.value.discount) || 0);
  if (discountableTotal <= 0 || currentValue <= 0) return;

  if (newType === 'percentage' && oldType === 'flat') {
    summary.value.discount = parseFloat(((currentValue / discountableTotal) * 100).toFixed(4));
  } else if (newType === 'flat' && oldType === 'percentage') {
    summary.value.discount = parseFloat((discountableTotal * currentValue / 100).toFixed(2));
  }
};

const totalWithVat = computed(() => {
  return parseFloat((summary.value.total + summary.value.vatAmount).toFixed(2));
});

const updateSummary = () => {
  const total = items.value.reduce((sum, item) => sum + item.totalAmount, 0);
  summary.value.total = parseFloat(total.toFixed(2));
  normalizeDiscountInputsByLimit();

  summary.value.vatPercentage = billingVatEnabled.value ? billingVatPercent.value : 0;
  summary.value.vatAmount = billingVatEnabled.value && billingVatPercent.value > 0
    ? parseFloat((summary.value.total * summary.value.vatPercentage / 100).toFixed(2))
    : 0;

  const amountToDiscount = totalWithVat.value;
  const discountableTotal = items.value.reduce(
    (sum, item) => sum + (isDisposableBillingItem(item) || isDiscountExemptBillingItem(item) ? 0 : Number(item.totalAmount || 0)),
    0
  );
  const maxAllowedFlatValue = Number(maxAllowedFlatDiscountAmount.value) || 0;
  const flatDiscountableTotal = items.value.reduce(
    (sum, item) => sum + (isDisposableBillingItem(item) || isDiscountExemptBillingItem(item) ? 0 : Number(item.totalAmount || 0)),
    0
  );
  let discountAmount = 0;

  const computeEffectiveItemDiscountPercent = (item) => {
    if (isDisposableBillingItem(item) || isDiscountExemptBillingItem(item)) {
      return 0;
    }

    const categoryLimit = resolveCategoryDiscountCap(item.category);
    const basePercent = Math.max(0, Number(summary.value.discount) || 0);
    const itemLimit = Math.min(100, Math.max(0, Number(item.discountPercentage || 0)));

    if (summary.value.discountType === 'percentage') {
      const percentWithItemRate = itemLimit > 0
        ? (basePercent > 0 ? Math.min(basePercent, itemLimit) : itemLimit)
        : (basePercent > 0 ? basePercent : 0);

      if (!categoryDiscountCapEnabled.value || categoryLimit === null) {
        return percentWithItemRate;
      }

      return Math.min(percentWithItemRate, categoryLimit);
    }

    if (itemLimit > 0) {
      return basePercent > 0 ? Math.min(basePercent, itemLimit) : itemLimit;
    }

    if (!categoryDiscountCapEnabled.value || categoryLimit === null) {
      return 0;
    }

    return Math.min(basePercent, categoryLimit);
  };

  if (summary.value.discountType === "percentage") {
    const enteredPercent = Math.min(
      Math.max(0, Number(summary.value.discount) || 0),
      effectiveBillingDiscountPercentLimit.value || Number(maxBillingDiscountPercent.value) || 100
    );

    const categoryAdjustedDiscountAmount = items.value.reduce((sum, item) => {
      if (isDisposableBillingItem(item) || isDiscountExemptBillingItem(item)) {
        return sum;
      }

      const itemPercent = computeEffectiveItemDiscountPercent(item);

      return sum + (Number(item.totalAmount || 0) * itemPercent) / 100;
    }, 0);

    discountAmount = Math.min(amountToDiscount, categoryAdjustedDiscountAmount);
  } else {
    const enteredFlatDiscount = Math.max(0, parseFloat(summary.value.discount) || 0);
    const safeFlatDiscount = Math.min(enteredFlatDiscount, maxAllowedFlatValue || flatDiscountableTotal, flatDiscountableTotal, amountToDiscount);
    discountAmount = safeFlatDiscount;
  }

  // Extra Discount is a separate flat amount and must apply in both discount modes.
  const totalDiscountAmount = Math.max(
    0,
    discountAmount + parseFloat(summary.value.extraFlatDiscount || 0)
  );
  const finalDiscountAmount = Math.min(totalDiscountAmount, amountToDiscount);
  const flatDiscountCapacity = (item) => {
    if (isDisposableBillingItem(item) || isDiscountExemptBillingItem(item)) return 0;

    const itemTotal = Number(item.totalAmount || 0);
    const itemPercent = Math.min(100, Math.max(0, Number(item.discountPercentage || 0)));
    const isLocked = Boolean(item.discountRateLocked || item.discount_rate_locked);
    if (isLocked && itemPercent <= 0) {
      return 0;
    }
    const categoryCap = resolveCategoryDiscountCap(item.category);
    const effectivePercent = isLocked
      ? itemPercent
      : itemPercent > 0
        ? itemPercent
        : (categoryDiscountCapEnabled.value && categoryCap !== null ? categoryCap : maxBillingDiscountPercent.value);

    return (itemTotal * effectivePercent) / 100;
  };
  const totalFlatDiscountCapacity = items.value.reduce(
    (sum, item) => sum + flatDiscountCapacity(item),
    0
  );

  items.value = items.value.map((item) => {
    const itemDiscount = isDisposableBillingItem(item) || isDiscountExemptBillingItem(item)
      ? 0
      : summary.value.discountType === "percentage"
        ? (item.totalAmount * computeEffectiveItemDiscountPercent(item)) / 100
        : (totalFlatDiscountCapacity > 0
          ? Math.min(discountAmount * flatDiscountCapacity(item) / totalFlatDiscountCapacity, flatDiscountCapacity(item))
          : 0);
    return {
      ...item,
      discount: itemDiscount,
      netAmount: item.totalAmount - itemDiscount,
    };
  });

  summary.value.payableAmount = Math.max(
    0,
    parseFloat((amountToDiscount - finalDiscountAmount).toFixed(2))
  );

  calculateChangeAndDue();
  commission.value.total = summary.value.payableAmount;

  if (commission.value.referrer_id && props.referrers) {
    const selectedReferrer = props.referrers.find(
      (referrer) => referrer.id == commission.value.referrer_id
    );
    if (selectedReferrer) {
      updateCommissionRate(selectedReferrer);
    }
  } else {
    updateCommission();
  }
};

const selectItem = (item) => {
  itemForm.value.category = item.category;
  // ensure a sensible fallback category if the item lacks one
  if (!itemForm.value.category || String(itemForm.value.category).trim() === '') {
    if (item.type === 'medicine') itemForm.value.category = 'Medicine';
    else if (item.type === 'test') itemForm.value.category = item.category || 'Pathology';
    else itemForm.value.category = item.category || 'Service';
  }
  itemForm.value.itemName = item.name;
  searchQuery.value = item.name;
  itemForm.value.itemId = item.id;
  itemForm.value.unitPrice = parseFloat(item.unitPrice);

  if (!itemForm.value.quantity || itemForm.value.quantity <= 0) {
    itemForm.value.quantity = 1;
  }

  itemForm.value.totalAmount = (
    itemForm.value.quantity * itemForm.value.unitPrice
  ).toFixed(2);

  searchQuery.value = "";
  selectedIndex.value = -1;
  nextTick(() => {
    quantityInput.value.focus();
    quantityInput.value.select();
  });
};

const handleKeyDown = (event, fieldName) => {
  if (fieldName === "itemName") {
    if (event.key === "ArrowDown") {
      event.preventDefault();
      selectedIndex.value = Math.min(
        selectedIndex.value + 1,
        filteredItems.value.length - 1
      );
    } else if (event.key === "ArrowUp") {
      event.preventDefault();
      selectedIndex.value = Math.max(selectedIndex.value - 1, 0);
    } else if (event.key === "Enter") {
      event.preventDefault();

      if (selectedIndex.value !== -1 && filteredItems.value[selectedIndex.value]) {
        selectItem(filteredItems.value[selectedIndex.value]);
      }
      requestMedicineSearch.cancel();
      medicineSearchController?.abort();
      medicineSearchLoading.value = false;
      searchQuery.value = "";
    } else if (event.key === "Escape") {
      requestMedicineSearch.cancel();
      medicineSearchController?.abort();
      medicineSearchLoading.value = false;
      selectedIndex.value = -1;
      searchQuery.value = "";
    }
  } else if (fieldName === "quantity" && event.key === "Enter") {
    event.preventDefault();
    try {
      addItem();
    } catch (e) {
      console.warn('[BillingPage] addItem on Enter error', e);
    }
  }
};

// Set the current item category and reset item selection state
const setCategory = (category) => {
  const cat = String(category ?? '').trim();
  itemForm.value.category = cat;
  requestMedicineSearch.cancel();
  medicineSearchController?.abort();
  medicineSearchResults.value = [];
  medicineSearchLoading.value = false;
  medicineSearchError.value = false;
  searchQuery.value = '';
  // Reset item-specific fields when category changes
  itemForm.value.itemName = '';
  itemForm.value.itemId = null;
  itemForm.value.unitPrice = 0;
  selectedIndex.value = -1;
  nextTick(() => {
    try { itemNameInput.value && itemNameInput.value.focus(); } catch (e) {}
  });
};

const openItemChargeCreate = () => {
  showItemChargeModal.value = true;
};

const closeItemChargeModal = () => {
  showItemChargeModal.value = false;
};

const goToItemChargeList = () => {
  closeItemChargeModal();
  router.visit(route('backend.itemcharge.index'));
};

const handleItemChargeCreated = () => {
  showItemChargeModal.value = false;
  router.reload({
    only: ['pathologyAndRadiologyTests'],
    preserveState: true,
    preserveScroll: true,
  });
};

const requestMedicineSearch = debounce(async (query) => {
  medicineSearchController?.abort();
  const controller = new AbortController();
  medicineSearchController = controller;

  try {
    const response = await axios.get(route('backend.billing.medicines.search'), {
      params: { search: query },
      signal: controller.signal,
    });
    if (searchQuery.value.trim() !== query) return;
    if (!Array.isArray(response.data)) {
      throw new Error('Medicine search returned an invalid response.');
    }
    medicineSearchResults.value = response.data;
  } catch (error) {
    if (error.name !== 'CanceledError' && error.name !== 'AbortError') {
      if (searchQuery.value.trim() === query) {
        medicineSearchResults.value = [];
        medicineSearchError.value = true;
      }
    }
  } finally {
    if (searchQuery.value.trim() === query) {
      medicineSearchLoading.value = false;
    }
  }
}, 180);

const handleItemInput = (event) => {
  const q = String(event.target.value ?? '').trim();
  selectedIndex.value = -1;

  if (q === '') {
    requestMedicineSearch.cancel();
    medicineSearchController?.abort();
    medicineSearchResults.value = [];
    medicineSearchLoading.value = false;
    medicineSearchError.value = false;
    // Do not clear selected category when input is emptied by user — preserve category
    itemForm.value.itemName = '';
    itemForm.value.itemId = null;
    itemForm.value.unitPrice = 0;
    searchQuery.value = '';
    selectedIndex.value = -1;
    return;
  }
  searchQuery.value = q;
  medicineSearchController?.abort();
  medicineSearchResults.value = [];
  medicineSearchError.value = false;
  medicineSearchLoading.value = true;
  requestMedicineSearch(q);
};

// Debug: watch searchQuery to see when it updates
watch(searchQuery, (val) => {
  console.log('[BillingPage] watch searchQuery ->', val, 'category:', itemForm.value.category);
});

const validateNewPatientForm = () => {
  if (!isNewPatient.value) return true;

  const errors = [];
  if (!newPatientForm.value.name?.trim()) errors.push('Patient name is required');
  if (!newPatientForm.value.phone?.trim()) errors.push('Patient phone is required');
  if (!newPatientForm.value.gender) errors.push('Patient gender is required');

  if (errors.length > 0) {
    displayWarning({ message: errors.join(', ') });
    return false;
  }

  return true;
};

const debugFormState = () => {
  console.log('=== FORM STATE DEBUG ===');
  console.log('isNewPatient:', isNewPatient.value);
  console.log('patientForm:', JSON.parse(JSON.stringify(patientForm.value)));
  console.log('newPatientForm:', JSON.parse(JSON.stringify(newPatientForm.value)));
  console.log('patientSearchQuery:', patientSearchQuery.value);
  console.log('========================');
};

const initializeEditMode = () => {
  if (props.id && props.editData) {
    isInitializingEditMode.value = true;
    isEditMode.value = true;

    const lastPaymentType = props.editData.payment_type || props.editData.pay_mode || "Cash";

    patientForm.value = {
      patient_id: props.editData.patient_id || "",
      doctor_id: props.editData.doctor_id || "",
      patientMobile: props.editData.patient_mobile || "",
      gender: props.editData.gender || "",
      dob: props.editData.dob || "",
      cardType: props.editData.card_type || lastPaymentType || "Cash",
      payMode: lastPaymentType,
      cardNumber: props.editData.card_number || "",
    };

    if (props.editData.patient_id && props.patients) {
      const selectedPatient = props.patients.find(
        (patient) => patient.id == props.editData.patient_id
      );
      if (selectedPatient) {
        patientSearchQuery.value = selectedPatient.name || "";
      }
    }

    calculateAgeFromDOB(props.editData.dob);

    billingDateTouched.value = true;

    if (props.editData.doctor_id) {
      const doctorId = props.editData.doctor_id;
      let doctorName = "";

      if (props.doctors && props.doctors.length > 0) {
        const adminDoctor = props.doctors.find(d => d.id == doctorId);
        if (adminDoctor) {
          doctorName = `${adminDoctor.first_name} ${adminDoctor.last_name}`;
          patientForm.value.doctor_id = `admin_${doctorId}`;
        }
      }

      if (!doctorName && props.billingDoctors && props.billingDoctors.length > 0) {
        const billingDoctor = props.billingDoctors.find(d => d.id == doctorId);
        if (billingDoctor) {
          doctorName = billingDoctor.name;
          patientForm.value.doctor_id = `billing_${doctorId}`;
        }
      }

      if (!doctorName && props.billing && props.billing.doctor_name) {
        doctorName = props.billing.doctor_name;
      }

      if (!doctorName && props.editData.doctor_name) {
        doctorName = props.editData.doctor_name;
      }

      if (doctorName) {
        doctorSearchQuery.value = doctorName;
        console.log('Setting doctor name in edit mode:', doctorName);
      }
    }

    let editAverageDiscountPercentage = 0;
    if (props.editData.items) {
      items.value = props.editData.items.map((item) => {
        // try to find item-level referral percentage from available props
        let refPercent = 0;
        try {
          const foundTest = (props.pathologyAndRadiologyTests || []).find(t => t.id == item.id);
          if (foundTest) refPercent = foundTest.referral_percentage ?? 0;
          else {
            refPercent = item.referral_percentage ?? 0;
          }
        } catch (e) {
          refPercent = item.referral_percentage ?? 0;
        }

        return {
          id: item.id,
          name: item.name,
          category: normalizeBillingItemCategory(item.category, item.name),
          unitPrice: parseFloat(item.unit_price),
          quantity: parseFloat(item.quantity),
          totalAmount: parseFloat(item.total_amount),
          discount: item.discount || 0,
          discountExempt: Boolean(item.discount_exempt),
          discountPercentage: Number(item.discount_percentage ?? (props.pathologyAndRadiologyTests || []).find(t => t.id == item.id)?.discount_percentage ?? 0),
          discountRateLocked: Boolean(item.discount_rate_locked),
          rugound: item.rugound || 0,
          netAmount: parseFloat(item.net_amount),
          referral_percentage: refPercent,
          roomNo: item.room_no ?? item.roomNo ?? '',
        };
      });

      const editEligibleSubtotal = items.value.reduce((sum, item) => {
        return sum + (isDisposableBillingItem(item) || isDiscountExemptBillingItem(item)
          ? 0
          : Number(item.totalAmount || 0));
      }, 0);
      const editAppliedDiscount = items.value.reduce((sum, item) => {
        return sum + Number(item.discount || 0);
      }, 0);
      editAverageDiscountPercentage = editEligibleSubtotal > 0
        ? Number(((editAppliedDiscount / editEligibleSubtotal) * 100).toFixed(4))
        : 0;
    }

    const persistedDiscountValue = Number(props.editData.discount || 0);
    const persistedDiscountType = String(props.editData.discount_type || 'flat');
    const legacyPercentEquivalent = persistedDiscountType === 'percentage' && Number(props.editData.total || 0) > 0 && persistedDiscountValue > 100
      ? Number(((persistedDiscountValue / Number(props.editData.total || 0)) * 100).toFixed(4))
      : persistedDiscountValue;
    const restoredDiscount = persistedDiscountType === 'percentage'
      ? (persistedDiscountValue > 0 ? legacyPercentEquivalent : editAverageDiscountPercentage)
      : persistedDiscountValue;

    const originalPaidAmount = parseFloat(props.editData.paid_amt || 0);
    const existingReturnAmount = parseFloat(props.editData.return_amt || 0);
    const payableAmount = parseFloat(props.editData.payable_amount || 0);
    const grossReceived = Math.max(0, originalPaidAmount - existingReturnAmount);
    const effectivePaid = Math.min(payableAmount, grossReceived);
    const computedDueAmount = Math.max(0, payableAmount - effectivePaid);

    // When editing, receivingAmount should always start at 0 (empty)
    // The original paid amount is tracked separately in calculateChangeAndDue()
    // as originalPaidAmount from editData.paid_amt
    // Only when user explicitly enters a new amount should receivingAmount change
    let receivingAmount = "";

    summary.value = createDefaultSummaryState({
      total: parseFloat(props.editData.total || 0),
      discount: restoredDiscount,
      discountType: persistedDiscountType,
      extraFlatDiscount: parseFloat(props.editData.extra_flat_discount || 0),
      vatPercentage: parseFloat(props.editData.vat_percentage || 0),
      vatAmount: parseFloat(props.editData.vat_amount || 0),
      payableAmount: payableAmount,
      paidAmt: effectivePaid,
      changeAmt: parseFloat(props.editData.change_amt || 0),
      dueAmount: computedDueAmount,
      receivingAmt: 0,
      returnAmt: existingReturnAmount,
      deliveryDate: props.editData.delivery_date || "",
      deliveryTime: props.editData.delivery_time || "",
      remarks: props.editData.remarks || "",
    });

    updateSummary();

    // Normalize deliveryDate into a `datetime-local` friendly format
    try {
      if (summary.value.deliveryDate) {
        const parsedDelivery = new Date(summary.value.deliveryDate);
        if (isValid(parsedDelivery)) {
          summary.value.deliveryDate = format(parsedDelivery, "yyyy-MM-dd'T'HH:mm");
          summary.value.deliveryTime = format(parsedDelivery, 'HH:mm');
        }
      }
    } catch (e) { /* ignore */ }

    commission.value = {
      total: parseFloat(props.editData.commission_total || 0),
      physystAmt: parseFloat(props.editData.physyst_amt || 0),
      slider: parseInt(props.editData.commission_slider || 0),
      referrer_id: props.editData.referrer_id || "",
      commissionRate: parseInt(props.editData.commission_slider || 0),
    };

    // Preserve existing invoice billing date/time in edit mode.
    let initialBillingDate = String(props.editData.billing_date || '').trim();
    let initialBillingTime = String(props.editData.billing_time || '').trim();
    if (!initialBillingDate && props.editData.created_at) {
      const parsedCreatedAt = parseBillingSourceDateTime(props.editData.created_at);
      if (parsedCreatedAt) {
        initialBillingDate = format(parsedCreatedAt, 'yyyy-MM-dd');
        if (!initialBillingTime) {
          initialBillingTime = format(parsedCreatedAt, 'HH:mm:ss');
        }
      }
    }
    billingDate.value = initialBillingDate;
    billingTime.value = initialBillingTime;
    billingDateTouched.value = true;

    nextTick(() => {
      isInitializingEditMode.value = false;
    });
  }
};

const saveBill = async (backendInvoice = false) => {
  if (!navigator.onLine && !offlineBillingUnlocked.value) {
    displayWarning({ message: 'অফলাইনে বিল করতে Settings থেকে encrypted offline billing চালু করুন, অথবা ইন্টারনেট সংযোগ ফিরে এলে চেষ্টা করুন।' });
    return;
  }

  if (!hasOpenCashCounterSession.value) {
    displayWarning({ message: 'কাউন্টার ক্লোজ আছে। বিল করার আগে Counter Start করুন।' });
    return;
  }

  // Defensive: if this function was used as an event handler without
  // parentheses (e.g. @click="saveBill"), the click Event object may be
  // passed as the first argument. Treat Event objects as falsy for our
  // backendInvoice flag so Save & Print flow still opens the invoice.
  if (backendInvoice && typeof backendInvoice === 'object' && backendInvoice instanceof Event) {
    backendInvoice = false;
  }

  if (items.value.length === 0) {
    displayWarning({ message: "Please add at least one item to the bill." });
    itemNameInput.value?.focus();
    return;
  }

  if (summary.value.payableAmount <= 0) {
    displayWarning({ message: "Payable amount must be greater than 0." });
    return;
  }

  // Recalculate payment status based on current summary values
  // When editing and adding items, ensure due_amount is properly reflected
  const dueAmount = parseFloat(summary.value.dueAmount) || 0;
  const paidAmount = parseFloat(summary.value.paidAmt) || 0;
  const payableAmount = parseFloat(summary.value.payableAmount) || 0;
  
  let paymentStatus = "Pending";
  
  if (dueAmount <= 0 && paidAmount >= payableAmount) {
    if (summary.value.returnAmt > 0) {
      paymentStatus = "Partial";
    } else {
      paymentStatus = "Paid";
    }
  } else if (paidAmount > 0 && dueAmount > 0) {
    paymentStatus = "Partial";
  } else if (dueAmount > 0) {
    paymentStatus = "Pending";
  }

  const itemsForBackend = items.value.map((item) => ({
    id: item.id,
    department_id: item.department_id ?? null,
    name: item.name,
    category: (() => {
      const normalizedCategory = normalizeBillingItemCategory(item.category, item.name);
      if (normalizedCategory) return normalizedCategory;
      try {
        const byId = allAvailableItems.value.find(i => i.id === item.id);
        if (byId && byId.category) return normalizeBillingItemCategory(byId.category, byId.name);
      } catch (e) {}
      try {
        const n = normalizeForMatch(item.name || '');
        const byName = (allAvailableItems.value || []).find(i => normalizeForMatch(i.name || '') === n);
        if (byName && byName.category) return normalizeBillingItemCategory(byName.category, byName.name);
      } catch (e) {}
      return 'Service';
    })(),
    unit_price: item.unitPrice,
    quantity: item.quantity,
    total_amount: item.totalAmount,
    discount: item.discount,
    discount_exempt: Boolean(item.discountExempt),
    discount_percentage: Number(item.discountPercentage || 0),
    discount_rate_locked: Boolean(item.discountRateLocked),
    rugound: item.rugound || 0,
    net_amount: item.netAmount,
    referral_percentage: Number(item.referral_percentage || 0),
    room_no: item.room_no ?? item.roomNo ?? null,
  }));

  // FIX: Calculate age string properly
  let ageString = '';
  const yearsNum = parseInt(ageYears.value) || 0;
  const monthsNum = parseInt(ageMonths.value) || 0;
  const daysNum = parseInt(ageDays.value) || 0;

  // Build age string only if any age component is provided
  if (yearsNum > 0 || monthsNum > 0 || daysNum > 0) {
    ageString = [
      yearsNum > 0 ? `${yearsNum} year${yearsNum !== 1 ? 's' : ''}` : '',
      monthsNum > 0 ? `${monthsNum} month${monthsNum !== 1 ? 's' : ''}` : '',
      daysNum > 0 ? `${daysNum} day${daysNum !== 1 ? 's' : ''}` : ''
    ].filter(Boolean).join(' ');
  }

  const submittedFlatDiscount = Math.max(0, Number(summary.value.discount) || 0);
  const normalizedSubmittedDiscount = summary.value.discountType === 'percentage' && Number(summary.value.total || 0) > 0 && Number(summary.value.discount || 0) > 100
    ? Number(((Number(summary.value.discount || 0) / Number(summary.value.total || 0)) * 100).toFixed(4))
    : Number(summary.value.discount || 0);
  const formData = {
    is_new_patient: Boolean(isNewPatient.value),
    pharmacy_mode: props.isPharmacyDepartment,
    ipd_patient_id: prescriptionIpdPatientId.value,
    ipd_prescription_id: prescriptionIpdPrescriptionId.value,
    
    patient_id: patientForm.value.patient_id || null,
    patient_mobile: patientForm.value.patientMobile?.trim() || '',
    gender: patientForm.value.gender || '',
    dob: patientForm.value.dob || null,

    card_type: patientForm.value.cardType,
    pay_mode: patientForm.value.payMode,
    card_number: patientForm.value.cardNumber || null,

    items: itemsForBackend,

    total: summary.value.total,
    discount: summary.value.discountType === 'flat'
      ? submittedFlatDiscount
      : normalizedSubmittedDiscount,
    discount_type: summary.value.discountType,
    discount_allocation: 'proportional',
    extra_flat_discount: summary.value.extraFlatDiscount || 0,
    payable_amount: summary.value.payableAmount,
    paid_amt: paidAmount,
    vat_percentage: summary.value.vatPercentage || 0,
    vat_amount: summary.value.vatAmount || 0,
    change_amt: summary.value.changeAmt || 0,
    due_amount: summary.value.dueAmount || 0,
    receiving_amt: summary.value.receivingAmt || 0,
    return_amt: summary.value.returnAmt || 0,

    delivery_date: summary.value.deliveryDate || null,
    delivery_time: summary.value.deliveryTime || null,
    remarks: summary.value.remarks || null,
    payment_status: paymentStatus,

    commission_total: commission.value.total || 0,
    physyst_amt: commission.value.physystAmt || 0,
    commission_slider: commission.value.commissionRate || 0,
    referrer_id: commission.value.referrer_id || null,

    doctor_name: doctorSearchQuery.value.trim() || null,
  };

  // Include billing date/time so backend can use it as the bill's created_at
  let billingTimeValue = String(billingTime.value || '').trim();
  if (!billingTimeValue && props.id && props.editData) {
    billingTimeValue = String(props.editData.billing_time || '').trim();
    if (!billingTimeValue && props.editData.created_at) {
      const backupParsed = parseBillingSourceDateTime(props.editData.created_at);
      if (backupParsed) {
        billingTimeValue = format(backupParsed, 'HH:mm:ss');
      }
    }
  }
  if (/^\d{2}:\d{2}$/.test(billingTimeValue)) {
    billingTimeValue = `${billingTimeValue}:00`;
  }
  formData.billing_date = billingDate.value || null;
  formData.billing_time = billingTimeValue || null;

  // FIX: Always include age data in the form
  formData.patient_age = ageString;

  if (isNewPatient.value && !patientForm.value.patient_id) {
    console.log('Creating NEW patient...');

    formData.patient_name = newPatientForm.value.name.trim();
    formData.patient_phone = newPatientForm.value.phone.trim();
    formData.patient_gender = newPatientForm.value.gender;
    formData.patient_dob = newPatientForm.value.dob || null;
    // Age is already set above

    formData.patient_mobile = newPatientForm.value.phone.trim();
    formData.gender = newPatientForm.value.gender;
    formData.dob = newPatientForm.value.dob || null;

    console.log('New Patient Data Added:', {
      patient_name: formData.patient_name,
      patient_phone: formData.patient_phone,
      patient_gender: formData.patient_gender,
      patient_dob: formData.patient_dob,
      patient_age: formData.patient_age
    });

  } else if (!isNewPatient.value && patientForm.value.patient_id) {
    console.log('Updating EXISTING patient...');
    
    const patientName = patientSearchQuery.value.trim();
    
    if (patientName) {
      formData.patient_name = patientName;
      console.log('Setting patient_name for existing patient:', patientName);
    } else {
      const selectedPatient = props.patients.find(p => p.id == patientForm.value.patient_id);
      if (selectedPatient) {
        formData.patient_name = selectedPatient.name;
        console.log('Found patient name from list:', selectedPatient.name);
      }
    }

    formData.patient_phone = patientForm.value.patientMobile?.trim() || '';
    formData.patient_gender = patientForm.value.gender || '';
    formData.patient_dob = patientForm.value.dob || null;
    // Age is already set above

    console.log('Existing Patient Update Data:', {
      patient_id: formData.patient_id,
      patient_name: formData.patient_name,
      patient_phone: formData.patient_phone,
      patient_gender: formData.patient_gender,
      patient_dob: formData.patient_dob,
      patient_age: formData.patient_age
    });

  } else if (!patientForm.value.patient_id && !isNewPatient.value) {
    console.log('Walk-in patient (no patient record)');
    formData.patient_name = patientSearchQuery.value.trim() || 'Walk-in Patient';
    formData.patient_phone = patientForm.value.patientMobile?.trim() || '';
    formData.patient_gender = patientForm.value.gender || '';
    formData.patient_dob = patientForm.value.dob || null;
    // Age is already set above
  }

  // include flag when saving as backend invoice
  if (backendInvoice) {
    formData.backend_invoice = true;
  }

  const requestId = createOfflineRequestId();
  const invoiceModule = props.isPharmacyDepartment ? 'pharmacy' : 'billing';
  formData.print_token = requestId;
  let submitUrl = '';
  try {
    submitUrl = props.id ? route("backend.billing.update", { billing: props.id }) : route("backend.billing.store");
    if (typeof submitUrl !== 'string' || submitUrl.includes('view-billing-page') || submitUrl.includes('view-billing-list-page')) {
      throw new Error('Invalid billing route.');
    }
  } catch (error) {
    submitUrl = props.id ? `/billing/${props.id}` : '/billing';
  }

  const queueBillLocally = async (receiptWindow = null) => {
    if (props.id) {
      throw new Error('Existing bills cannot be edited while offline. Reconnect before saving changes.');
    }

    await saveOfflineBillingRequest({
      id: requestId,
      adminId: offlineAdminId.value,
      url: submitUrl,
      method: 'POST',
      data: formData,
      routeName: 'backend.billing.store',
      label: props.isPharmacyDepartment ? 'Pharmacy bill' : 'Billing invoice',
    });

    try {
      await printOfflineProvisionalReceipt(receiptWindow, requestId, formData);
    } catch (error) {
      displayWarning({
        message: `Bill saved offline, but its print view could not be prepared. ${error instanceof Error ? error.message : ''}`.trim(),
      });
    }
    await refreshOfflineBillingRequests();
    successMessage('Bill saved on this device. It will sync automatically when internet returns; this is a provisional receipt.');
    resetAllForms();
  };

  if (!navigator.onLine) {
    let receiptWindow = null;
    if (!backendInvoice) {
      try {
        receiptWindow = window.open(window.location.href, '_blank');
        if (receiptWindow) receiptWindow.opener = null;
      } catch (error) {
        receiptWindow = null;
      }
    }

    try {
      await queueBillLocally(receiptWindow);
    } catch (error) {
      if (receiptWindow && !receiptWindow.closed) receiptWindow.close();
      displayWarning({ message: error instanceof Error ? error.message : 'The bill could not be saved offline.' });
    }
    return;
  }

  // Reserve a tab for the invoice, but keep the billing page in front while saving.
  let invoiceWindow = null;
  if (!backendInvoice) {
    try {
      invoiceWindow = window.open('', '_blank');
      if (invoiceWindow) {
        invoiceWindow.opener = null;
        window.focus();
      }
    } catch (e) {
      invoiceWindow = null;
    }
  }

  const form = useForm(formData);

    const submitOptions = {
      onSuccess: (response) => {
        const flashSuccessMessage = response?.message || response?.data?.message || response?.props?.flash?.successMessage || response?.props?.flash?.message || null;

        // Prefer direct invoiceUrl from server if provided
        let billId = null;
        const fastInvoiceHtml = typeof response?.fastInvoiceHtml === 'string' ? response.fastInvoiceHtml : '';
        if (!backendInvoice && fastInvoiceHtml && invoiceWindow && !invoiceWindow.closed) {
          try {
            invoiceWindow.document.open('text/html', 'replace');
            invoiceWindow.document.write(fastInvoiceHtml);
            invoiceWindow.document.close();
            invoiceWindow.focus();
          } catch (e) {
            // keep existing fallback URL navigation below
          }
        }

        if (response?.billId) billId = response.billId;
        if (!billId && response?.data?.billId) billId = response.data.billId;
        if (!billId && response?.data?.data?.billId) billId = response.data.data.billId;
        if (!billId && response?.props?.flash?.billId) billId = response.props.flash.billId;
        if (!billId && response?.props?.billId) billId = response.props.billId;
        if (!billId && typeof response === 'string') {
          try { const parsed = JSON.parse(response); if (parsed?.billId) billId = parsed.billId; } catch (e) { }
        }

        if (billId && !backendInvoice && !fastInvoiceHtml) {
          let invoiceUrl = '';
          try {
            invoiceUrl = route('backend.download.invoice', {
              id: billId,
              module: invoiceModule,
              fast_open: 1,
              auto_print: 1,
            });
          } catch (e) {
            invoiceUrl = `${window.location.origin}/download-invoice?id=${encodeURIComponent(billId)}&module=${encodeURIComponent(invoiceModule)}&fast_open=1&auto_print=1`;
          }

          try {
            if (invoiceWindow && !invoiceWindow.closed) {
              invoiceWindow.location.replace(invoiceUrl);
            } else {
              window.open(invoiceUrl, '_blank');
            }
          } catch (error) {
            console.error('Could not navigate to the saved invoice.', error);
          }
        }

        successMessage(flashSuccessMessage || 'Billing saved successfully.');

        // Dispatch dashboard refresh event to update Net Income card
        try {
          localStorage.setItem('dashboard:refresh', String(Date.now()));
          window.dispatchEvent(new Event('dashboard:refresh'));
          // Also notify billing list pages to refresh their data without a full reload
          try {
            localStorage.setItem('billing:list:refresh', String(Date.now()));
            window.dispatchEvent(new Event('billing:list:refresh'));
          } catch (innerErr) {
            // non-fatal
          }
        } catch (e) {
          console.warn('Failed to dispatch dashboard refresh event:', e);
        }

        // Keep the current billing page open after a regular save.
        if (billId) {
          const isEditingExistingBill = Boolean(props.id && props.editData);
          if (!isEditingExistingBill) {
            resetAllForms();
          } else {
            isEditMode.value = true;
            billingDateTouched.value = true;
            nextTick(() => {
              deliveryDateTouched.value = !!summary.value.deliveryDate;
            });
          }

          if (!backendInvoice) {
            // Keep current billing page state even after invoice tab is closed.
            if (invoiceMonitorTimer) {
              clearInterval(invoiceMonitorTimer);
              invoiceMonitorTimer = null;
            }
          }

          if (isEditingExistingBill && !backendInvoice) {
            router.visit(route(props.isPharmacyDepartment ? 'backend.pharmacybill.index' : 'backend.billing.list'));
            return;
          }

          window.setTimeout(() => {
            reloadReferrers();
            router.reload({ only: ['reagentTestAvailability'] });
          }, 1500);
        } else {
          if (!props.id) resetAllForms();
          const invoiceUrl = String(response?.invoiceUrl || '');
          if (!backendInvoice && invoiceUrl) {
            try {
              if (invoiceWindow && !invoiceWindow.closed) {
                invoiceWindow.location.replace(invoiceUrl);
              } else {
                window.open(invoiceUrl, '_blank');
              }
            } catch (error) {
              console.error('Could not open the saved invoice.', error);
            }
          } else if (!backendInvoice) {
            try { if (invoiceWindow && !invoiceWindow.closed) invoiceWindow.close(); } catch (error) { }
            displayWarning({ message: 'The bill was saved, but the invoice link was not returned. Please open the invoice from the billing list.' });
          }
        }
      },
      onError: (errors) => {
        console.error('Save bill errors:', errors);
        try { if (invoiceWindow && !invoiceWindow.closed) invoiceWindow.close(); } catch (e) { }

        const firstMessage = (value) => {
          if (Array.isArray(value)) return value.find((message) => typeof message === 'string' && message.trim());
          return typeof value === 'string' && value.trim() ? value : null;
        };
        const patientNameError = firstMessage(errors?.patient_name);
        const patientPhoneError = firstMessage(errors?.patient_phone);
        const patientGenderError = firstMessage(errors?.patient_gender);
        const patientIdError = firstMessage(errors?.patient_id);

        if (patientNameError) {
          displayWarning({ message: patientNameError });
          patientSearchRef.value?.focus();
        } else if (patientPhoneError) {
          displayWarning({ message: patientPhoneError });
          patientMobileRef.value?.focus();
        } else if (patientGenderError) {
          displayWarning({ message: patientGenderError });
          genderSelectRef.value?.focus();
          setTimeout(() => openDropdown(genderSelectRef), 100);
        } else if (patientIdError) {
          displayWarning({ message: patientIdError });
          patientSearchRef.value?.focus();
        } else {
          const errorMessages = typeof errors === 'string'
            ? [errors]
            : Object.entries(errors || {}).flatMap(([field, value]) => {
                const messages = Array.isArray(value) ? value : [value];
                return messages
                  .filter((message) => typeof message === 'string' && message.trim())
                  .map((message) => `${field}: ${message}`);
              });
          const errorMessage = errorMessages.length
            ? errorMessages.join('\n')
            : 'The bill could not be saved. Please try again or contact support.';
          displayWarning({ message: errorMessage });

          if (Object.keys(errors || {}).some((field) => field === 'items' || field.startsWith('items.'))) {
            itemNameInput.value?.focus();
          }
        }
      },
    };

    // Always use AJAX for both Save and Save & Print to avoid page redirect
    // Send raw AJAX request (axios) so server JSON won't be intercepted by Inertia
    const method = props.id ? 'put' : 'post';
    sendBillingRequestWithSessionRefresh({
      method,
      url: submitUrl,
      data: formData,
      headers: {
        'X-Offline-Request-Id': requestId,
        'X-Offline-Admin-Id': String(offlineAdminId.value),
      },
    })
      .then((res) => {
        if (!res) {
          try { if (invoiceWindow && !invoiceWindow.closed) invoiceWindow.close(); } catch (error) { }
          return;
        }
        const payload = res?.data ?? res;
        if (typeof payload === 'string' && /<!doctype html|<html/i.test(payload)) {
          throw { response: { data: { errors: 'Billing save failed: server returned an HTML response instead of JSON.' } } };
        }
        if (payload?.success === false) {
          throw { response: { data: { errors: payload?.errors || payload?.message || 'Billing save failed.' } } };
        }
        submitOptions.onSuccess(payload);
      })
      .catch(async (err) => {
        if (!err?.response && !props.id) {
          try {
            await queueBillLocally(invoiceWindow);
            return;
          } catch (queueError) {
            displayWarning({ message: queueError instanceof Error ? queueError.message : 'The bill could not be saved offline.' });
          }
        }
        const errors = err.response?.data?.errors ?? err.response?.data ?? err;
        submitOptions.onError(errors);
      })
      .finally(() => {
        submitOptions.onFinish?.();
      });
};

const shouldPreventPageRefresh = () => {
  // Prevent page refresh when saving - always stay on the edit page or list
  // unless explicitly navigating away
  return true;
};

const resetAllForms = () => {
  clearBillingItemsDraft();
  items.value = [];
  patientForm.value = {
    patient_id: "",
    doctor_id: "",
    patientMobile: "",
    gender: "",
    dob: "",
    cardType: "Cash",
    payMode: "Cash",
    cardNumber: "",
  };
  summary.value = createDefaultSummaryState({
    // Default delivery date/time: set a delivery slot based on the current hour.
    // Example: 2:00-2:59 -> 8:00, 3:00-3:59 -> 9:00, and so on.
    deliveryDate: (function(){ const value = getDefaultDeliveryDateTime(); return value.date; })(),
    deliveryTime: (function(){ const value = getDefaultDeliveryDateTime(); return value.time; })(),
    remarks: "",
  });
  commission.value = {
    total: 0.0,
    physystAmt: 0.0,
    slider: 0,
    referrer_id: "",
    commissionRate: 0,
  };

  isNewPatient.value = false;
  newPatientForm.value = {
    name: "",
    phone: "",
    gender: "",
    dob: "",
  };
  patientSearchQuery.value = "";
  showPatientDropdown.value = false;

  doctorSearchQuery.value = "";
  filteredDoctors.value = [];
  showDoctorDropdown.value = false;

  ageYears.value = '';
  ageMonths.value = '';
  ageDays.value = '';

  referrerSearch.value = '';
  showReferrerDropdown.value = false;
  highlightedRefIndex.value = 0;
  filteredReferrers.value = [];
  submitOnNextReferrerEnter.value = false;
};

// Delivery datetime helpers.
// Default to a delivery slot six hours ahead of the current hour (e.g. 2:00 -> 8:00, 3:00 -> 9:00).
const getDefaultDeliveryDateTime = (baseDate = new Date()) => {
  try {
    const now = baseDate instanceof Date ? new Date(baseDate) : new Date();
    const deliveryDate = new Date(now);
    deliveryDate.setHours(now.getHours() + 6, 0, 0, 0);

    return {
      date: format(deliveryDate, "yyyy-MM-dd'T'HH:mm"),
      time: format(deliveryDate, 'HH:mm'),
    };
  } catch (e) {
    console.warn('[BillingPage] getDefaultDeliveryDateTime error', e);
    const fallback = new Date();
    return {
      date: format(fallback, "yyyy-MM-dd'T'HH:mm"),
      time: format(fallback, 'HH:mm'),
    };
  }
};

const setCurrentDeliveryDateTime = () => {
  if (summary.value.deliveryDate) return;
  try {
    const defaultDelivery = getDefaultDeliveryDateTime();
    summary.value.deliveryDate = defaultDelivery.date;
    summary.value.deliveryTime = defaultDelivery.time;
  } catch (e) { console.warn('[BillingPage] setCurrentDeliveryDateTime error', e); }
};

const syncDeliveryDateTimeFromBilling = () => {
  if (deliveryDateTouched.value) return;

  const billDate = String(billingDate.value || '').trim();
  const billTime = String(billingTime.value || '').trim();
  if (!billDate) return;

  try {
    const baseDate = billTime ? new Date(`${billDate}T${billTime}`) : new Date(`${billDate}T00:00:00`);
    if (!isValid(baseDate)) return;

    const defaultDelivery = getDefaultDeliveryDateTime(baseDate);
    summary.value.deliveryDate = defaultDelivery.date;
    summary.value.deliveryTime = defaultDelivery.time;
  } catch (e) {
    console.warn('[BillingPage] syncDeliveryDateTimeFromBilling error', e);
  }
};

const startDeliveryLiveClock = () => {
  if (deliveryLiveTimer) return;
  deliveryLiveTimer = setInterval(() => { if (!deliveryDateTouched.value) setCurrentDeliveryDateTime(); }, 60 * 1000);
};

const ensureDeliveryDateTime = () => {
  deliveryDateTouched.value = true;
  if (!summary.value.deliveryDate) return setCurrentDeliveryDateTime();
  try {
    const parsed = new Date(summary.value.deliveryDate);
    if (isValid(parsed)) {
      summary.value.deliveryDate = format(parsed, "yyyy-MM-dd'T'HH:mm");
      summary.value.deliveryTime = format(parsed, 'HH:mm');
    }
  } catch (e) { console.warn('[BillingPage] ensureDeliveryDateTime error', e); }
};

const handleDeliveryDateInput = () => {
  deliveryDateTouched.value = true;
  try { const parsed = new Date(summary.value.deliveryDate); if (isValid(parsed)) summary.value.deliveryTime = format(parsed, 'HH:mm'); } catch (e) {}
};

watch([billingDate, billingTime], ([newBillingDate, newBillingTime]) => {
  if (!newBillingDate || deliveryDateTouched.value) return;
  if (!newBillingTime) return;

  syncDeliveryDateTimeFromBilling();
});

const handlePatientSearchBlur = (event) => {
  setTimeout(() => {
    showPatientDropdown.value = false;
  }, 200);
};

const cancelBill = () => {
  if (props.id && props.editData) {
    router.visit(route(props.isPharmacyDepartment ? 'backend.pharmacybill.index' : 'backend.billing.list'));
    return;
  }

  if (items.value.length > 0) {
    items.value = [];
    clearBillingItemsDraft();
    summary.value.discount = 0;
    summary.value.extraFlatDiscount = 0;
    updateSummary();
    return;
  }

  router.visit(route('backend.billing.Page'));
};

const restoreEditSummaryFromItems = () => {
  if (!props.id || items.value.length === 0) return;

  const itemsTotal = items.value.reduce((sum, item) => sum + Number(item.totalAmount || 0), 0);
  if (itemsTotal <= 0) return;

  if (Number(summary.value.total || 0) <= 0) {
    summary.value.total = Number(itemsTotal.toFixed(2));
  }

  updateSummary();
};

const openListBillButton = () => {
  let url;
  try {
    url = route(props.isPharmacyDepartment ? 'backend.pharmacybill.index' : 'backend.billing.list');
  } catch (e) {
    url = props.isPharmacyDepartment ? '/view-pharmacy-bill-list-page' : '/view-billing-list-page';
  }

  openPageInNewTab(url);
};

const openAddBillButton = () => {
  try {
    const url = route("backend.billing.view");
    if (url) {
      window.location.assign(url);
      return;
    }
  } catch (e) {
    // fallback when named route resolution fails
  }
  window.location.assign('/view-billing-page');
};

const openPageInNewTab = (url) => {
  progress.start();
  const pageWindow = window.open(url, '_blank');
  if (!pageWindow) {
    progress.finish();
    window.alert('The page could not be opened because the browser blocked the new window. Allow pop-ups and try again.');
    return;
  }

  pageWindow.opener = null;
  window.setTimeout(() => progress.finish(), 800);
};

const openBillingPage = (url, { freshBillingDraft = false } = {}) => {
  if (!url) return;

  const destination = new URL(url, window.location.href);
  if (freshBillingDraft) {
    destination.searchParams.set('billing_draft', createOfflineRequestId());
  }

  openPageInNewTab(destination.href);
};

// Initialize edit mode if needed
onMounted(() => {
  isBrowserOnline.value = navigator.onLine;
  document.addEventListener('visibilitychange', refreshBillingSessionWhenVisible);
  billingSessionHeartbeatTimer = window.setInterval(() => {
    refreshBillingSessionWhenVisible();
  }, 5 * 60 * 1000);
  refreshBillingSessionWhenVisible();
  window.addEventListener('online', handleBrowserOnline);
  window.addEventListener('offline', handleBrowserOffline);
  window.addEventListener('offline-billing:queue-updated', handleOfflineQueueChanged);
  window.addEventListener('offline-billing:setup-failed', handleOfflineSupportSetupFailed);
  window.addEventListener('offline-billing:unlocked', handleOfflineUnlocked);
  window.addEventListener('offline-billing:locked', handleOfflineDataLocked);
  refreshOfflineEncryptionStatus();
  refreshOfflineBillingRequests();
  syncOfflineBillingQueue();

  if (props.id && props.editData) {
    initializeEditMode();
    nextTick(() => {
      if (items.value.length > 0) {
        updateSummary();
      }
    });
    setTimeout(restoreEditSummaryFromItems, 100);
    setTimeout(restoreEditSummaryFromItems, 500);
    nextTick(() => {
      deliveryDateTouched.value = !!summary.value.deliveryDate;
    });
  }

  startDeliveryLiveClock();
  if (!deliveryDateTouched.value) {
    setCurrentDeliveryDateTime();
  }

  startBillingLiveClock();
  startBillingClockTicker();
  if (!billingDateTouched.value) {
    setCurrentBillingDateTime();
  }
  // initialize referrer suggestions as hidden until user types
  filteredReferrers.value = [];

  // attach global listeners to close dropdowns on outside click or focus
  document.addEventListener('click', _onDocumentClick, true);
  document.addEventListener('focusin', _onDocumentFocusIn, true);

  // Startup debug: log category counts across sources to help troubleshoot missing categories
  try {
    const catCounts = {};
    (props.pathologyAndRadiologyTests || []).forEach(t => {
      const c = String(t.category_type || '').trim();
      if (!c) return;
      catCounts[c] = (catCounts[c] || 0) + 1;
    });
    catCounts.Medicine = medicineSearchResults.value.length;
    // hospitalCharges removed from aggregated sources

    console.debug('[BillingPage] startup category counts', catCounts);
  } catch (e) { console.warn('[BillingPage] startup category counts failed', e); }

  window.addEventListener('storage', handleDashboardStorageRefresh);
  window.addEventListener('storage', handleCloseInvoiceTabsStorage);
  window.addEventListener('dashboard:refresh', handleDashboardSameTabRefresh);
  window.addEventListener('focus', handleBillingWindowFocus);
  reloadNetIncomeValue();
  if (props.isPharmacyDepartment && props.prescriptionSearchValue) {
    prescriptionSearchId.value = props.prescriptionSearchValue;
    nextTick(() => searchPrescriptionCharge());
  }
});

onUnmounted(() => {
  window.removeEventListener('pagehide', flushOfflineBillingDraft);
  document.removeEventListener('visibilitychange', refreshBillingSessionWhenVisible);
  if (billingSessionHeartbeatTimer !== null) {
    window.clearInterval(billingSessionHeartbeatTimer);
    billingSessionHeartbeatTimer = null;
  }
  flushOfflineBillingDraft();
  window.removeEventListener('online', handleBrowserOnline);
  window.removeEventListener('offline', handleBrowserOffline);
  window.removeEventListener('offline-billing:queue-updated', handleOfflineQueueChanged);
  window.removeEventListener('offline-billing:setup-failed', handleOfflineSupportSetupFailed);
  window.removeEventListener('offline-billing:unlocked', handleOfflineUnlocked);
  window.removeEventListener('offline-billing:locked', handleOfflineDataLocked);
  requestMedicineSearch.cancel();
  medicineSearchController?.abort();
  // cleanup timers
  if (deliveryLiveTimer) {
    clearInterval(deliveryLiveTimer);
    deliveryLiveTimer = null;
  }
  if (billingLiveTimer) {
    clearInterval(billingLiveTimer);
    billingLiveTimer = null;
  }
  if (billingClockTimer) {
    clearInterval(billingClockTimer);
    billingClockTimer = null;
  }
  if (invoiceMonitorTimer) {
    clearInterval(invoiceMonitorTimer);
    invoiceMonitorTimer = null;
  }

  // remove document click listener
  try {
    document.removeEventListener('click', _onDocumentClick, true);
    document.removeEventListener('focusin', _onDocumentFocusIn, true);
  } catch (e) { /* ignore */ }

  // cleanup window listeners
  window.removeEventListener('storage', handleDashboardStorageRefresh);
  window.removeEventListener('storage', handleCloseInvoiceTabsStorage);
  window.removeEventListener('dashboard:refresh', handleDashboardSameTabRefresh);
  window.removeEventListener('focus', handleBillingWindowFocus);
});

watch(doctorSearchQuery, debounce((newQuery) => {
  if (newQuery.trim() == "") {
    filteredDoctors.value = [];
    showDoctorDropdown.value = false;
    return;
  }
  const hasExactMatch = filteredDoctors.value.some(doctor => 
    doctor.name.toLowerCase() === newQuery.trim().toLowerCase()
  );
  
  if (hasExactMatch) {
    showDoctorDropdown.value = false;
    return;
  }

  searchDoctors(newQuery);
}, quickSearchDebounceMs));

const searchDoctors = async (query) => {
  if (!query || query.trim().length < 1) return;

  isDoctorLoading.value = true;
  try {
    const response = await axios.get(route('backend.billing.doctors.search'), {
      params: { search: query }
    });

    // Apply smart sorting to results
    let filtered = response.data || [];
    filtered = smartSortSearchResults(filtered, query, (doctor) => doctor.name);
    filteredDoctors.value = filtered;

    const hasExactMatch = filteredDoctors.value.some(doctor =>
      doctor.name.toLowerCase() === query.trim().toLowerCase()
    );

    if (filteredDoctors.value.length > 0 && !hasExactMatch) {
      showDoctorDropdown.value = true;
      doctorSelectedIndex.value = 0;
    } else {
      showDoctorDropdown.value = false;
      doctorSelectedIndex.value = -1;
    }
  } catch (error) {
    console.error('Error searching doctors:', error);
    filteredDoctors.value = [];
    showDoctorDropdown.value = false;
  } finally {
    isDoctorLoading.value = false;
  }
};

const selectDoctor = (doctor) => {
  console.log('Selecting doctor:', doctor.name);

  doctorSearchQuery.value = doctor.name;

  showDoctorDropdown.value = false;
  doctorSelectedIndex.value = -1;
  filteredDoctors.value = [];
  isDoctorLoading.value = false;

  nextTick(() => {
    setTimeout(() => {
      if (!patientForm.value.gender) {
        genderSelectRef.value?.focus();
        setTimeout(() => openDropdown(genderSelectRef), 100);
      } else if (!patientForm.value.dob) {
        dobInput.value?.focus();
      } else {
        ageYearsInput.value?.focus();
      }
    }, 50);
  });
};


const handleDoctorSearchKeyDown = (event) => {
  if (event.key === "ArrowDown") {
    event.preventDefault();
    doctorSelectedIndex.value = Math.min(
      doctorSelectedIndex.value + 1,
      filteredDoctors.value.length - 1
    );
  } else if (event.key === "ArrowUp") {
    event.preventDefault();
    doctorSelectedIndex.value = Math.max(doctorSelectedIndex.value - 1, 0);
  } else if (event.key === "Enter") {
    handleDoctorEnter(event);
  } else if (event.key === "Escape") {
    doctorSelectedIndex.value = -1;
    showDoctorDropdown.value = false;
  }
};

const createNewDoctor = async () => {
  if (!doctorSearchQuery.value.trim()) {
    displayWarning({ message: "Please enter a doctor name." });
    return;
  }

  try {
    const response = await axios.post(route('backend.billingdoctor.store'), {
      name: doctorSearchQuery.value.trim()
    });

    if (response.data.success) {
      selectDoctor(response.data.doctor);
      displayResponse({ message: "Doctor created successfully!" });
    } else {
      displayWarning({ message: response.data.message });
    }
  } catch (error) {
    console.error('Error creating doctor:', error);
    displayWarning({ message: "Failed to create doctor. Please try again." });
  }
};

const handleDoctorSearchBlur = (event) => {
  setTimeout(() => {
    showDoctorDropdown.value = false;
  }, 200);
};

const clearDoctorSelection = () => {
  doctorSearchQuery.value = "";
  filteredDoctors.value = [];
};

const handleDoctorSearchInput = (event) => {
  const query = event.target.value;
  
  const hasExactMatch = filteredDoctors.value.some(doctor => 
    doctor.name.toLowerCase() === query.trim().toLowerCase()
  );
  
  if (query.trim() !== "" && filteredDoctors.value.length > 0 && !hasExactMatch) {
    showDoctorDropdown.value = true;
  } else {
    showDoctorDropdown.value = false;
  }
};

</script>

<template>

  <Head :title="$page.props.pageTitle"/>
  <div class="billing-page-text min-h-screen bg-gray-50 dark:bg-gray-900 overflow-y-auto">
    <div class="w-full p-2">
      <section v-if="isOfflineBillingDeveloper
        && (pendingOfflineBillingRequests.some((entry) => entry.lastError)
          || reviewOfflineBillingRequests.length || offlineBillingStorageError || offlineBillingCapabilityMessage)"
        class="mb-3 rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-xs text-slate-800">
        <p v-if="offlineBillingCapabilityMessage" class="mt-1 text-amber-800">{{ offlineBillingCapabilityMessage }}</p>
        <p v-if="offlineBillingStorageError" class="mt-1 text-red-700">{{ offlineBillingStorageError }}</p>
        <p v-if="pendingOfflineBillingRequests.some((entry) => entry.lastError)" class="mt-1 text-amber-800">
          {{ pendingOfflineBillingRequests.find((entry) => entry.lastError)?.lastError }}
        </p>
        <div v-for="entry in (offlineBillingUnlocked ? reviewOfflineBillingRequests : [])" :key="entry.id"
          class="mt-2 flex flex-wrap items-center justify-between gap-2 rounded border border-red-200 bg-white px-2 py-1 text-red-800">
          <span>{{ entry.label }} · {{ entry.lastError || 'Server needs this request reviewed.' }} · Ref {{ entry.id.slice(0, 8) }}</span>
          <button type="button" class="underline" @click="removeReviewedOfflineRequest(entry.id)">Dismiss</button>
        </div>
      </section>
      <div class="bg-white rounded-lg shadow-lg dark:bg-slate-900 mb-4">
        <div class="mb-3">
          <div
            class="flex flex-wrap justify-between items-center bg-[#053855] text-white px-3 py-2 text-xs font-semibold rounded-t-lg gap-2">
            <div class="flex-1"></div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-2 text-white">
                  <!-- Display text normally; on hover show editable inputs -->
                  <div class="relative" @mouseenter="billingEditing = true" @mouseleave="billingEditing = false">
                      <template v-if="!billingEditing">
                      <span class="text-sm font-semibold text-white px-2">{{ billingLiveDisplay }}</span>
                    </template>
                    <template v-else>
                      <input
                        type="date"
                        v-model="billingDate"
                        class="px-2 py-1 border border-white/30 rounded text-[11px] text-white bg-white/10 focus:border-white focus:outline-none"
                        ref="billingDateRef"
                        @input="handleBillingDateTimeInput"
                      />
                      <input
                        type="time"
                        v-model="billingTime"
                        step="1"
                        class="px-2 py-1 border border-white/30 rounded text-[11px] text-white bg-white/10 focus:border-white focus:outline-none ml-2"
                        ref="billingTimeRef"
                        @input="handleBillingDateTimeInput"
                      />
                    </template>
                  </div>
                </div>
                <div class="flex items-center gap-2 flex-nowrap">
                  <div class="relative min-w-0">
                    <input
                      v-model="prescriptionSearchId"
                      ref="prescriptionSearchInputRef"
                      type="text"
                      class="w-[140px] md:w-[170px] px-2 py-1 border border-slate-200 rounded text-[11px] bg-white text-slate-900 placeholder-slate-600 focus:border-slate-400 focus:outline-none min-w-0"
                      :placeholder="props.isPharmacyDepartment ? 'Rx / OPD / IPD ID' : 'Prescription ID'"
                      @input="handlePrescriptionInput"
                      @focus="handlePrescriptionInput"
                      @blur="handlePrescriptionBlur"
                      @keydown.enter.prevent="searchPrescriptionCharge"
                    />
                    <div
                      v-if="showPrescriptionSuggestions"
                      class="absolute right-0 left-0 mt-1 z-20 bg-white text-slate-700 border border-slate-200 rounded shadow max-h-56 overflow-y-auto"
                    >
                      <button
                        v-for="item in prescriptionSuggestions"
                        :key="`${item.source}-${item.id}`"
                        type="button"
                        class="w-full text-left px-2 py-1.5 text-[11px] hover:bg-slate-100 border-b border-slate-100"
                        @click="selectPrescriptionSuggestion(item)"
                      >
                        <span class="font-semibold">{{ item.label }}</span>
                        <span class="text-slate-500"> | {{ item.patient_name || 'Unknown' }}</span>
                      </button>
                    </div>
                    <div
                      v-if="prescriptionSuggestionLoading"
                      class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-white/70"
                    >
                      ...
                    </div>
                  </div>
                  <button
                    type="button"
                    class="px-2 py-1 rounded text-[11px] bg-emerald-500 hover:bg-emerald-600 text-white disabled:opacity-60 whitespace-nowrap"
                    :disabled="prescriptionSearchLoading"
                    @click="searchPrescriptionCharge"
                  >
                    {{ prescriptionSearchLoading ? 'Searching...' : 'Load' }}
                  </button>
                </div>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700 transition duration-200 shadow-sm"
                @click="goBack"
                title="Back"
              >
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back
              </button>
              <button
                type="button"
                class="flex items-center px-3 py-1.5 text-white rounded-md text-xs font-semibold transition-colors duration-200 shadow-sm disabled:opacity-60"
                :class="hasOpenCashCounterSession
                  ? 'bg-emerald-700 cursor-default'
                  : 'bg-emerald-600 hover:bg-emerald-700'"
                :disabled="cashCounterStartBusy"
                @click="openCounterStartModal"
                :title="hasOpenCashCounterSession ? 'Counter is open' : 'Start My Counter'"
              >
                {{ hasOpenCashCounterSession ? 'Counter Open' : 'Counter Start' }}
              </button>
              <button
                type="button"
                class="flex items-center px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-md text-xs font-semibold transition-colors duration-200 shadow-sm disabled:opacity-60 disabled:cursor-not-allowed"
                :disabled="cashCounterCloseBusy || !hasOpenCashCounterSession"
                @click="openCounterCloseModal"
                title="Close My Counter"
              >
                Counter Close
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs transition hover:bg-emerald-100 dark:border-emerald-700 dark:bg-emerald-900/30 dark:hover:bg-emerald-900/50"
                :title="props.isPharmacyDepartment ? 'Open Pharmacy Profit & Loss Report' : 'Open Total Net Income Report'"
                @click="openNetIncomeReport"
              >
                <span class="font-semibold text-white">{{ props.isPharmacyDepartment ? 'Pharmacy Net Income' : 'Net Income' }}</span>
                <span class="font-bold text-white">৳{{ formattedTotalNetIncome }}</span>
              </button>
              <button type="button"
                @click="openBillingPage(route(props.isPharmacyDepartment ? 'backend.pharmacybill.create' : 'backend.billing.view'), { freshBillingDraft: !props.isPharmacyDepartment })"
                class="flex items-center px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-md text-xs transition-colors duration-200 shadow-sm"
                style="color: #ffffff !important"
                :title="props.isPharmacyDepartment ? 'Pharmacy Bill Create' : 'Billing Add'">
                {{ props.isPharmacyDepartment ? 'Pharmacy Add' : 'Billing Add' }}
              </button>
              <button v-if="!props.isPharmacyDepartment || props.canAccessPharmacyBillList"
                type="button"
                @click="openListBillButton"
                class="flex items-center px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-md text-xs transition-colors duration-200 shadow-sm"
                style="color: #ffffff !important"
                :title="props.isPharmacyDepartment ? 'Pharmacy Bill List' : 'Billing List'">
                {{ props.isPharmacyDepartment ? 'Pharmacy Bill List' : 'Billing List' }}
              </button>
            </div>
          </div>
          <div class="border border-gray-300 border-t-0 p-3 bg-gray-50 dark:bg-slate-800 dark:border-gray-600">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3 text-xs">
              <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                <span class="font-medium text-gray-700 dark:text-gray-300"><strong>UNIT:</strong> {{ unitCompanyName }}</span>
                <span class="font-medium text-gray-700 dark:text-gray-300"><strong>Counter:</strong> {{ authInfo?.department?.name ?? "" }}</span>
                <span class="font-medium text-gray-700 dark:text-gray-300"><strong>Sales Person:</strong> {{ authInfo?.admin?.name ?? "" }}</span>
              </div>
              <div class="ml-auto flex flex-wrap items-center justify-end gap-3">
                <span class="inline-flex items-center gap-1.5 font-semibold"
                  :class="isBrowserOnline ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400'"
                  role="status" aria-live="polite">
                  <span class="h-2 w-2 rounded-full"
                    :class="isBrowserOnline ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                  {{ isBrowserOnline ? 'Online' : 'Offline' }}
                </span>
                <button v-if="isBrowserOnline && pendingOfflineBillingRequests.length" type="button"
                  class="inline-flex h-7 w-7 items-center justify-center rounded bg-emerald-600 text-white shadow-sm transition-colors hover:bg-emerald-700 disabled:cursor-default disabled:opacity-60"
                  :disabled="!offlineBillingUnlocked"
                  :title="isOfflineBillingSyncing
                    ? 'Synchronizing offline bills'
                    : `Synchronize ${pendingOfflineBillingRequests.length} offline bill(s)`"
                  :aria-label="isOfflineBillingSyncing ? 'Synchronizing offline bills' : 'Synchronize offline bills'"
                  @click="syncOfflineBillingQueue">
                  <svg class="h-4 w-4" :class="{ 'animate-spin': isOfflineBillingSyncing }"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                    aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                      d="M20 7v5h-5M4 17v-5h5M5.6 9A7 7 0 0 1 18 6l2 2M4 16l2 2a7 7 0 0 0 12.4-3"></path>
                  </svg>
                </button>
                <a v-if="showPharmacyProductReturn"
                  :href="route('backend.productreturn.index', { return_type: 'customer' })"
                  class="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                  <span aria-hidden="true">↩</span> Product Return
                </a>
              </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-2 items-end">
              <div class="lg:col-span-2">
                <InputLabel for="category" value="Category" class="text-xs mb-1" />
                  <select v-model="itemForm.category" id="category"
                    class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200">
                    <option value="">Select</option>
                    <option v-for="type in categoryTypes" :key="type" :value="type">{{ type }}</option>
                  </select>
              </div>
              <div class="lg:col-span-4 relative">
                <InputLabel for="itemName" value="Item Name" class="text-xs mb-1" />
                <div class="flex items-start gap-2">
                  <div class="relative flex-1">
                    <input v-model="itemForm.itemName" @input="handleItemInput($event)"
                      @keydown="handleKeyDown($event, 'itemName')" @focus="selectedIndex = -1" id="itemName" type="text"
                      class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                      placeholder="Search items..." autocomplete="off" ref="itemNameInput" />
                    <div v-if="searchQuery || (itemForm.category && filteredItems.length > 0)"
                      class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto dark:bg-slate-700 dark:border-gray-600">
                      <ul>
                        <li v-for="(item, index) in filteredItems" :key="`${item.type}-${item.category}-${item.id}`" @click="selectItem(item)"
                          @keypress.enter="selectItem(item);" :class="['list-focus px-3 py-2 text-xs cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-600',
                            { 'dropdown-highlight-blue font-semibold': index === selectedIndex }]"
                          :ref="(el) => { if (index === selectedIndex) selectedItemRef = el }">
                          <div class="flex items-center justify-between gap-2">
                            <span class="flex min-w-0 items-center gap-2">
                                <span
                                  v-if="item.type === 'test' && reagentFilmSummaryForTest(item.id, item.category)"
                                  class="shrink-0 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-800"
                                  :title="`${reagentCountUnitForCategory(item.category, reagentFilmSummaryForTest(item.id, item.category).remaining)} remaining: ${formatStockQuantity(reagentFilmSummaryForTest(item.id, item.category).remaining)}`"
                                >
                                  {{ formatStockQuantity(reagentFilmSummaryForTest(item.id, item.category).remaining) }}
                                  {{ reagentFilmSummaryForTest(item.id, item.category).unit }}
                                </span>
                                <span
                                    v-if="item.type === 'test' && item.reagentRemainingTests !== null && !reagentFilmSummaryForTest(item.id, item.category)"
                                  :class="[
                                    'shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-semibold',
                                  item.reagentRemainingTests <= 20
                                    ? (index === selectedIndex ? 'bg-white text-amber-700' : 'bg-amber-100 text-amber-800')
                                    : (index === selectedIndex ? 'bg-white text-emerald-700' : 'bg-emerald-100 text-emerald-800')
                                ]"
                                :title="`${item.reagentRemainingTests} tests supported by current Lab reagent stock`"
                              >
                                {{ item.reagentRemainingTests }}
                                {{ reagentCountUnitForCategory(item.category, item.reagentRemainingTests) }}
                              </span>
                              <span class="truncate">{{ item.name }}</span>
                            </span>
                            <span :class="{ 'text-white': index === selectedIndex, 'text-gray-500 dark:text-gray-300': index !== selectedIndex }">
                              {{ item.type === "medicine" ? "Medicine" : item.type === "store" ? `${item.stockCategory || 'Store'} · ${item.departmentName || 'Department'}` : item.category }}
                              (৳{{ item.unitPrice }})
                            </span>
                          </div>
                          <div v-if="item.type === 'medicine' || item.type === 'store'" :class="['text-xs', { 'text-white': index === selectedIndex, 'text-gray-500 dark:text-gray-400': index !== selectedIndex }]">
                            Stock: {{ Number(item.stock || 0).toFixed(2) }} {{ item.stockUnit || '' }}
                          </div>
                        </li>
                        <li v-if="filteredItems.length === 0" class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">
                          {{ medicineSearchError ? 'Could not load medicines. Try searching again.' : medicineSearchLoading ? 'Searching medicines...' : billingCatalogLoaded ? 'No items found.' : 'Loading items...' }}
                        </li>
                      </ul>
                    </div>
                  </div>
                  <button type="button" @click="openItemChargeCreate"
                    class="flex-shrink-0 h-8 w-8 bg-emerald-600 text-white rounded hover:bg-emerald-700 flex items-center justify-center text-lg font-bold"
                    title="Add Item Charge" aria-label="Add Item Charge">
                    +
                  </button>
                </div>
              </div>

              <div class="lg:col-span-2">
                <InputLabel for="unitPrice" value="U/Price" class="text-xs mb-1" />
                <div class="flex">
                  <input v-model="itemForm.unitPrice" type="number" step="1" id="unitPrice" readonly
                    class="w-full px-2 py-1.5 border border-gray-300 rounded-l text-xs bg-yellow-100 focus:bg-yellow-200 focus:outline-none dark:bg-yellow-200 dark:text-gray-800" />
                  <span
                    class="px-2 py-1.5 bg-gray-200 border-t border-b border-r border-gray-300 rounded-r text-xs dark:bg-gray-600 dark:text-gray-200">৳</span>
                </div>
              </div>
              <div class="lg:col-span-1">
                <InputLabel for="quantity" value="Qty" class="text-xs mb-1" />
                <input v-model="itemForm.quantity" @keydown="handleKeyDown($event, 'quantity')" type="number" step="1"
                  min="1" id="quantity"
                  class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                  ref="quantityInput" />
              </div>
              <div class="lg:col-span-2">
                <InputLabel for="totalAmount" value="T.Amt" class="text-xs mb-1" />
                <input v-model="itemForm.totalAmount" type="number" step="1" id="totalAmount"
                  class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs bg-gray-100 dark:bg-gray-600 dark:text-gray-200"
                  readonly />
              </div>
              <div class="lg:col-span-1">
                <button @click="addItem"
                  class="w-full h-8 bg-teal-600 text-white rounded hover:bg-teal-700 flex items-center justify-center font-bold text-sm">
                  ✚
                </button>
              </div>
            </div>
          </div>
        </div>
        <div class="mb-0">
          <div class="border border-gray-300 border-t-0">
            <div>
              <table class="w-full text-xs">
                <thead class="bg-teal-700 text-white">
                  <tr>
                    <th class="px-2 py-2 text-left font-semibold">Item Name</th>
                    <th class="px-2 py-2 text-center font-semibold">Category</th>
                    <th class="px-2 py-2 text-center font-semibold">U/Price</th>
                    <th class="px-2 py-2 text-center font-semibold">Qty</th>
                    <th class="px-2 py-2 text-center font-semibold">T.Amt</th>
                    <th class="px-2 py-2 text-center font-semibold">Disc%</th>
                    <th class="px-2 py-2 text-center font-semibold">Net Amt</th>
                    <th class="px-2 py-2 text-center font-semibold">Action</th>
                  </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800">
                  <tr v-for="(item, index) in items" :key="index"
                    class="border-b border-gray-200 hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-slate-700">
                    <td class="px-2 py-2 font-medium dark:text-gray-200 cursor-pointer"
                      title="Click item name to edit quantity"
                      @click="startEditingItemQuantity(index)">
                      {{ item.name }}
                      <div v-if="item.type === 'store'" class="mt-1 text-[10px] font-normal text-slate-500">
                        Department stock: {{ Number(item.stock || 0).toFixed(2) }} {{ item.stockUnit || '' }}
                      </div>
                    </td>
                    <td class="px-2 py-2 text-center dark:text-gray-200">
                      <span :class="{
                        'bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs':
                          item.category === 'Pathology',
                        'bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs':
                          item.category === 'Radiology',
                        'bg-purple-100 text-purple-800 px-2 py-1 rounded-full text-xs':
                          item.category === 'Medicine',
                        'bg-amber-100 text-amber-800 px-2 py-1 rounded-full text-xs':
                          item.category === 'OPD',
                        'bg-cyan-100 text-cyan-800 px-2 py-1 rounded-full text-xs':
                          item.category === 'IPD',
                        'bg-rose-100 text-rose-800 px-2 py-1 rounded-full text-xs':
                          item.category === 'Appointment',
                      }">
                        {{ item.category }}
                      </span>
                    </td>
                    <td class="px-2 py-2 text-center dark:text-gray-200">
                      ৳{{ item.unitPrice.toFixed(2) }}
                    </td>
                    <td class="px-2 py-2 text-center dark:text-gray-200"
                      @click="startEditingItemQuantity(index)">
                      <input
                        v-if="editingQuantityIndex === index"
                        v-model="editedQuantity"
                        :data-bill-quantity-index="index"
                        type="number"
                        min="1"
                        step="1"
                        class="w-16 px-1 py-1 text-center border border-blue-400 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 dark:bg-slate-700 dark:text-gray-200"
                        @click.stop
                        @keydown.enter.prevent.stop="commitItemQuantity(index)"
                        @keydown.esc.prevent.stop="cancelItemQuantityEdit"
                        @blur="commitItemQuantity(index)"
                      />
                      <span v-else class="inline-block min-w-[2rem] cursor-pointer" title="Click to edit quantity">
                        {{ item.quantity }}
                      </span>
                    </td>
                    <td class="px-2 py-2 text-center dark:text-gray-200">
                      ৳{{ item.totalAmount.toFixed(2) }}
                    </td>
                    <td class="px-2 py-2 text-center dark:text-gray-200">
                      {{ getDisplayedItemDiscountPercent(item).toFixed(2) }}%
                    </td>
                    <td class="px-2 py-2 text-center font-semibold dark:text-gray-200">
                      ৳{{ item.netAmount.toFixed(2) }}
                    </td>
                    <td class="px-2 py-2 text-center">
                      <button @click="removeItem(index)"
                        class="bg-red-500 text-white px-1.5 py-0.5 rounded text-xs hover:bg-red-600">
                        🗑
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div v-if="reagentStockAlerts.length" class="mx-3 mt-2 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900" role="alert">
          <p class="font-semibold">Low Lab reagent stock — fewer than 20 tests remain:</p>
          <p v-for="test in reagentStockAlerts" :key="`${test.id}-${test.name}`" class="mt-1">
            {{ test.name }}: {{ test.remaining }} {{ test.remaining === 1 ? 'test' : 'tests' }} remaining after this bill. Send more reagent stock to Lab.
          </p>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 p-3">
          <!-- PATIENT DETAILS section -->
          <div>
            <div
              class="bg-teal-600 text-white px-3 py-2 text-xs font-semibold rounded-t flex justify-between items-center">
              <span>PATIENT DETAILS</span>
            </div>
            <div
              class="border border-gray-300 border-t-0 rounded-b p-3 bg-white dark:bg-slate-800 dark:border-gray-600 space-y-2">
              <!-- Patient Mobile -->
              <div class="grid grid-cols-3 gap-2 items-center">
                <InputLabel for="patientMobile" value="Patient Mobile" class="text-xs" />
                <div class="col-span-2 relative" ref="patientMobileWrapperRef">
                  <input v-model="patientForm.patientMobile" type="text" id="patientMobile"
                    placeholder="Search name and mobile number"
                    class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                    ref="patientMobileRef" @input="handlePatientMobileInput" @keydown="handlePatientMobileKeydown" />

                  <div v-if="showPatientMobileDropdown && patientMobileSuggestions.length > 0"
                    class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-48 overflow-auto dark:bg-slate-700 dark:border-gray-600">
                    <ul>
                      <li v-for="(patient, index) in patientMobileSuggestions" :key="patient.id"
                        @mousedown.prevent="selectPatientFromMobileSuggestion(patient)"
                        :class="[
                          'px-3 py-2 text-xs cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-600',
                          { 'dropdown-highlight-blue font-semibold': index === patientMobileSelectedIndex }
                        ]">
                        <div class="flex justify-between items-center gap-2">
                          <span class="font-medium">{{ patient.displayPhone }}</span>
                          <span :class="{ 'text-white': index === patientMobileSelectedIndex, 'text-gray-500 dark:text-gray-300': index !== patientMobileSelectedIndex }">{{ patient.displayName }}</span>
                        </div>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Patient Search -->
              <div class="grid grid-cols-3 gap-2 items-center">
                <InputLabel for="patient_id" value="Patient Name" class="text-xs" />
                <div class="col-span-2 relative">
                  <input v-model="patientSearchQuery" id="patientSearch" type="text"
                    class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                    placeholder="Enter patient name" ref="patientSearchRef"
                    @keydown="handlePatientSearchKeyDown" />

                  <div v-if="false"
                    class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-48 overflow-auto dark:bg-slate-700 dark:border-gray-600">
                    <ul>
                      <li v-for="(patient, index) in filteredPatients" :key="patient.id"
                        @mousedown.prevent="selectPatient(patient)"
                        :class="[
                          'px-3 py-2 text-xs cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-600',
                          { 'dropdown-highlight-blue font-semibold': index === patientSelectedIndex }
                        ]">
                        <div class="flex justify-between items-center gap-2">
                          <span class="font-medium">{{ patient.name }}</span>
                          <span :class="{ 'text-white': index === patientSelectedIndex, 'text-gray-500 dark:text-gray-300': index !== patientSelectedIndex }">
                            {{ patient.phone }}
                          </span>
                        </div>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Ref. Doctor Search Field -->
              <div class="grid grid-cols-3 gap-2 items-center">
                <InputLabel for="doctor_search" value="Ref. Doctor" class="text-xs" />
                <div class="col-span-2 relative">
                  <div class="relative">
                    <input 
                      v-model="doctorSearchQuery" 
                      @input="handleDoctorSearchInput"
                      @keydown="handleDoctorSearchKeyDown"
                      @focus="handleDoctorSearchFocus"
                      @blur="handleDoctorSearchBlur" 
                      id="doctor_search" 
                      type="text"
                      class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                      placeholder="Type doctor name and press Enter" 
                      autocomplete="off" 
                      ref="doctorSearchRef"
                      @keydown.enter="handleDoctorEnter"
                    />

                    <!-- Clear button -->
                    <button v-if="doctorSearchQuery" @click="clearDoctorSelection"
                            class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            type="button">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                      </svg>
                    </button>
                  </div>

                  <!-- Doctor dropdown list -->
                  <div v-if="showDoctorDropdown && filteredDoctors.length > 0"
                      class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto dark:bg-slate-700 dark:border-gray-600">
                    <ul>
                      <li v-for="(doctor, index) in filteredDoctors" 
                          :key="doctor.id" 
                          @mousedown.prevent="selectDoctor(doctor)" 
                          :class="[
                            'px-3 py-2 text-xs cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-600',
                            { 'dropdown-highlight-blue font-semibold': index === doctorSelectedIndex }
                          ]">
                        <div class="flex justify-between items-center">
                          <span>{{ doctor.name }}</span>
                        </div>
                      </li>
                    </ul>
                  </div>


                </div>
              </div>

              <!-- Hidden field to store the selected doctor ID -->
              <input type="hidden" v-model="patientForm.doctor_id" />

              <!-- Gender -->
              <div class="grid grid-cols-3 gap-2 items-center">
                <InputLabel for="gender" value="Gender" class="text-xs" />
                <div class="col-span-2 relative" ref="genderWrapperRef">
                  <input 
                    v-model="patientForm.gender" 
                    id="gender"
                    type="text"
                    readonly
                    @focus="showGenderDropdown = true; genderSelectedIndex = genderOptions.findIndex(opt => opt.value === patientForm.gender)"
                    @click="toggleGenderDropdown"
                    @keydown="handleGenderEnter"
                    class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200 cursor-pointer"
                    ref="genderSelectRef" 
                    :placeholder="patientForm.gender ? genderOptions.find(opt => opt.value === patientForm.gender)?.label : 'Select'" />
                  
                  <!-- Gender dropdown list -->
                  <div v-if="showGenderDropdown"
                    class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg dark:bg-slate-700 dark:border-gray-600">
                    <ul>
                      <li v-for="(option, index) in genderOptions" 
                        :key="option.value"
                        @mousedown.prevent="selectGenderOption(option)"
                        @mouseenter="genderSelectedIndex = index"
                        :class="[
                          'px-3 py-2 text-xs cursor-pointer',
                          index === genderSelectedIndex
                            ? 'dropdown-highlight-blue font-semibold'
                            : 'dropdown-default-white'
                        ]">
                        {{ option.label }}
                      </li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Date of Birth -->
              <div class="grid grid-cols-3 gap-2 items-center">
                <InputLabel for="dob" value="Date of Birth" class="text-xs" />
                <input v-model="patientForm.dob" type="date" id="dob"
                  class="col-span-2 px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                  ref="dobInput" @keydown="handleDobEnter" />
              </div>

              <!-- Age -->
              <div class="grid grid-cols-3 gap-2 items-center">
                <InputLabel value="Age" class="text-xs" />
                <div class="col-span-2 flex items-center space-x-1">
                  <input ref="ageYearsInput" v-model="ageYears" @input="handleAgeInput(ageYearsInput, ageMonthsInput)"
                    @keydown="handleAgeYearsEnter" type="number" min="0" max="120"
                    class="w-12 px-1 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                    placeholder="Y" @focus="$event.target.select()" />
                  <span class="text-xs text-gray-500">y</span>

                  <input ref="ageMonthsInput" v-model="ageMonths" @input="handleAgeInput(ageMonthsInput, ageDaysInput)"
                    @keydown="handleAgeMonthsEnter" type="number" min="0" max="11"
                    class="w-12 px-1 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                    placeholder="M" @focus="$event.target.select()" />
                  <span class="text-xs text-gray-500">m</span>

                  <input ref="ageDaysInput" v-model="ageDays" @input="handleAgeInput(ageDaysInput, null)"
                    @keydown="handleAgeDaysEnter" type="number" min="0" max="30"
                    class="w-12 px-1 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                    placeholder="D" @focus="$event.target.select()" />
                  <span class="text-xs text-gray-500">d</span>
                </div>
              </div>

              <!-- Pay Mode -->
              <div class="grid grid-cols-3 gap-2 items-center">
                <InputLabel for="payMode" value="Pay Mode" class="text-xs" />
                <select v-model="patientForm.payMode" id="payMode"
                  class="col-span-2 px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                  ref="payModeRef" @keydown="handlePayModeEnter" @change="patientForm.cardType = patientForm.payMode">
                  <option value="Cash">Cash</option>
                  <option value="Card">Card</option>
                  <option value="Bank">Bank</option>
                  <option value="Bkash">Bkash</option>
                  <option value="Nagad">Nagad</option>
                </select>
              </div>

              <!-- Card/Account/Mobile Number (conditional) -->
              <div class="grid grid-cols-3 gap-2 items-center" v-if="patientForm.payMode !== 'Cash'">
                <InputLabel for="cardNumber"
                  :value="['Bkash','Nagad'].includes(patientForm.payMode) ? 'Mobile No.' : 'Card/Account No.'"
                  class="text-xs" />
                <input v-model="patientForm.cardNumber" type="text" id="cardNumber"
                  :placeholder="['Bkash','Nagad'].includes(patientForm.payMode) ? 'Enter mobile number' : 'Enter card/account number'"
                  class="col-span-2 px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                  ref="cardNumberRef" @keydown="handleCardNumberEnter" />
              </div>
            </div>
          </div>

          <!-- Patient Modal -->
          <PatientModal :isOpen="isPatientModalOpen" :tpas="props.tpas" @close="closePatientModal"
            @patientCreated="handlePatientCreated" />

          <Modal :show="showItemChargeModal" @close="closeItemChargeModal" maxWidth="2xl">
            <div class="bg-white dark:bg-slate-800">
              <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Create Item</h3>
                <div class="flex items-center gap-2">
                  <button type="button" @click="goToItemChargeList" class="rounded border border-slate-300 bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:hover:bg-slate-600">
                    Item List
                  </button>
                  <button type="button" @click="closeItemChargeModal" class="rounded border border-red-200 bg-red-50 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-100 dark:border-red-700 dark:bg-red-900/20 dark:text-red-300 dark:hover:bg-red-800/30">
                    Delete
                  </button>
                  <button type="button" @click="closeItemChargeModal" aria-label="Close modal" class="flex h-8 w-8 items-center justify-center rounded-full text-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white">
                    ×
                  </button>
                </div>
              </div>
              <div class="p-4">
                <PathologyForm
                  embedded
                  :charges="props.hospitalCharges || []"
                  :categoryTypeOptions="props.categoryTypeOptions || []"
                  :mainCategories="props.mainCategories || []"
                  :testCategories="props.testCategories || []"
                  :chargeTypes="props.chargeTypes || []"
                  :chargeCategories="props.chargeCategories || []"
                  :chargeUnits="props.chargeUnits || []"
                  :taxCategories="props.taxCategories || []"
                  :pathologyUnits="props.pathologyUnits || []"
                  :testParameters="props.testParameters || []"
                  @created="handleItemChargeCreated"
                />
              </div>
            </div>
          </Modal>

          <!-- TOTAL SUMMARY section -->
          <div>
            <div class="bg-teal-600 text-white px-3 py-2 text-xs font-semibold rounded-t">
              TOTAL SUMMARY
            </div>
            <div
              class="border border-gray-300 border-t-0 rounded-b p-3 bg-white dark:bg-slate-800 dark:border-gray-600 space-y-2">
              <div v-if="billingVatEnabled" class="grid grid-cols-2 gap-2 items-center">
                <InputLabel for="vatAmount" :value="`VAT (${billingVatPercent.toFixed(2)}%)`" class="text-xs font-semibold" />
                <div class="flex">
                  <input v-model="summary.vatAmount" type="number" step="0.01" id="vatAmount" readonly
                    class="w-full px-2 py-1.5 border border-gray-300 rounded-l text-xs bg-gray-100 dark:bg-gray-600 dark:text-gray-200" />
                  <span
                    class="px-2 py-1.5 bg-gray-200 border-t border-b border-r border-gray-300 rounded-r text-xs dark:bg-gray-600 dark:text-gray-200">৳</span>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-2 items-center">
                <InputLabel for="total" value="Total Amount" class="text-xs font-semibold" />
                <div class="flex">
                  <input :value="summary.total.toFixed(2)" type="number" step="0.01" id="total" readonly
                    class="w-full px-2 py-1.5 border border-gray-300 rounded-l text-xs bg-gray-100 dark:bg-gray-600 dark:text-gray-200" />
                  <span
                    class="px-2 py-1.5 bg-gray-200 border-t border-b border-r border-gray-300 rounded-r text-xs dark:bg-gray-600 dark:text-gray-200">৳</span>
                </div>
              </div>
              <div v-if="billingVatEnabled" class="grid grid-cols-2 gap-2 items-center">
                <InputLabel for="totalWithVat" value="Total With VAT" class="text-xs font-semibold" />
                <div class="flex">
                  <input :value="totalWithVat.toFixed(2)" type="number" step="0.01" id="totalWithVat" readonly
                    class="w-full px-2 py-1.5 border border-gray-300 rounded-l text-xs bg-gray-100 dark:bg-gray-600 dark:text-gray-200" />
                  <span
                    class="px-2 py-1.5 bg-gray-200 border-t border-b border-r border-gray-300 rounded-r text-xs dark:bg-gray-600 dark:text-gray-200">৳</span>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-1">
                <div class="grid grid-cols-2 gap-1 items-center">
                  <InputLabel for="discount" value="Discount" class="text-xs" />
                  <select v-model="summary.discountType"
                    class="w-full min-w-max px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200 ml-[-1.5rem]"
                    style="-webkit-appearance:none; -moz-appearance:none; appearance:none; background-image:none;">
                    <option value="percentage">Percentage (%)</option>
                    <option value="flat">Flat Amount (৳)</option>
                  </select>
                </div>
                <div class="flex">
                  <input v-model="summary.discount" type="number" step="1" min="0" :max="summary.discountType === 'percentage' ? maxBillingDiscountPercent : maxAllowedFlatDiscountAmount" id="discount"
                    class="w-full px-2 py-1.5 border border-gray-300 rounded-l text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                    ref="discountRef" @input="updateSummary" @keydown="handleDiscountEnter" @wheel="onDiscountWheel" @click="selectAllOnFocus" @focus="selectAllOnFocus" />
                  <span
                    class="px-2 py-1.5 bg-gray-200 border-t border-b border-r border-gray-300 rounded-r text-xs dark:bg-gray-600 dark:text-gray-200">৳</span>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-2 items-center">
                <InputLabel for="extraFlatDiscount" value="Extra Discount" class="text-xs" />
                <div class="flex">
                  <input v-model="summary.extraFlatDiscount" @input="updateSummary" type="number" step="1" min="0"
                    id="extraFlatDiscount"
                    class="w-full px-2 py-1.5 border border-gray-300 rounded-l text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                    placeholder="Additional flat discount" ref="extraDiscountRef" @keydown="handleExtraDiscountEnter" @click="selectAllOnFocus" @focus="selectAllOnFocus" />
                  <span
                    class="px-2 py-1.5 bg-gray-200 border-t border-b border-r border-gray-300 rounded-r text-xs dark:bg-gray-600 dark:text-gray-200">৳</span>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-2 items-center">
                <InputLabel for="payableAmount" value="Payable Amount"
                  class="text-xs font-semibold text-green-700 dark:text-green-400" />
                <div class="flex">
                  <input v-model="summary.payableAmount" type="number" step="0.01" id="payableAmount" readonly
                    class="w-full px-2 py-1.5 border border-green-500 rounded-l text-xs bg-green-50 font-semibold dark:bg-green-900 dark:text-green-100" />
                  <span
                    class="px-2 py-1.5 bg-green-200 border-t border-b border-r border-green-500 rounded-r text-xs font-semibold dark:bg-green-700 dark:text-green-100">৳</span>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-2 items-center">
                <InputLabel for="receivingAmt" value="Receiving Amount"
                  class="text-xs font-semibold text-blue-700 dark:text-blue-400" />
                <div class="flex">
                  <input v-model="summary.receivingAmt" type="number" step="0.01" min="0" id="receivingAmt"
                    class="w-full px-2 py-1.5 border border-green-600 rounded-l text-xs focus:border-green-700 focus:outline-none bg-green-600 text-white placeholder-white/70 dark:bg-green-700 dark:border-green-500 dark:text-white"
                    placeholder="Amount given by customer" ref="receivingAmtRef" @keydown="handleReceivingAmtEnter" @click="selectAllOnFocus" @focus="selectAllOnFocus" />
                  <span
                    class="px-2 py-1.5 bg-green-700 border-t border-b border-r border-green-600 rounded-r text-xs font-semibold text-white dark:bg-green-800 dark:border-green-500 dark:text-white">৳</span>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-2 items-center" v-if="summary.returnAmt > 0">
                <InputLabel for="returnAmt" value="Return Amount"
                  class="text-xs font-semibold text-amber-700 dark:text-amber-400" />
                <div class="flex">
                  <input v-model="summary.returnAmt" type="number" step="0.01" id="returnAmt" readonly
                    class="w-full px-2 py-1.5 border border-amber-500 rounded-l text-xs bg-amber-50 font-semibold dark:bg-amber-900 dark:text-amber-100" />
                  <span
                    class="px-2 py-1.5 bg-amber-200 border-t border-b border-r border-amber-500 rounded-r text-xs font-semibold dark:bg-amber-700 dark:text-amber-100">৳</span>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-2 items-center">
                <InputLabel for="paidAmt" value="Paid Amount" class="text-xs font-semibold" />
                <div class="flex">
                  <input v-model="summary.paidAmt" type="number" step="0.01" id="paidAmt" readonly
                    class="w-full px-2 py-1.5 border border-gray-300 rounded-l text-xs bg-gray-100 font-semibold dark:bg-gray-600 dark:text-gray-200" />
                  <span
                    class="px-2 py-1.5 bg-gray-200 border-t border-b border-r border-gray-300 rounded-r text-xs dark:bg-gray-600 dark:text-gray-200">৳</span>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-2 items-center" v-if="summary.changeAmt > 0">
                <InputLabel for="changeAmt" value="Change Amount"
                  class="text-xs font-semibold text-purple-700 dark:text-purple-400" />
                <div class="flex">
                  <input v-model="summary.changeAmt" type="number" step="0.01" id="changeAmt" readonly
                    class="w-full px-2 py-1.5 border border-purple-500 rounded-l text-xs bg-purple-50 font-semibold dark:bg-purple-900 dark:text-purple-100" />
                  <span
                    class="px-2 py-1.5 bg-purple-200 border-t border-b border-r border-purple-500 rounded-r text-xs font-semibold dark:bg-purple-700 dark:text-purple-100">৳</span>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-2 items-center" v-if="summary.dueAmount > 0">
                <InputLabel for="dueAmount" value="Due Amount"
                  class="text-xs font-semibold text-red-700 dark:text-red-400" />
                <div class="flex">
                  <input v-model="summary.dueAmount" type="number" step="0.01" id="dueAmount" readonly
                    class="w-full px-2 py-1.5 border border-red-500 rounded-l text-xs bg-red-50 font-semibold dark:bg-red-900 dark:text-red-100" />
                  <span
                    class="px-2 py-1.5 bg-red-200 border-t border-b border-r border-red-500 rounded-r text-xs font-semibold dark:bg-red-700 dark:text-red-100">৳</span>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-2 items-center">
                <InputLabel for="deliveryDate" value="Delivery Date and Time" class="text-xs" />
                <input v-model="summary.deliveryDate" type="datetime-local" id="deliveryDate"
                  class="px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                  ref="deliveryDateRef" @keydown="handleDeliveryDateEnter" @focus="ensureDeliveryDateTime"
                  @input="handleDeliveryDateInput" />
                <!-- delivery date display moved to invoice page -->
              </div>
              <div>
                <InputLabel for="remarks" value="Remarks" class="text-xs mb-1" />
                <textarea v-model="summary.remarks" id="remarks" rows="2"
                  class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                  placeholder="Additional notes or remarks" ref="remarksRef" @keydown="handleRemarksEnter"></textarea>
              </div>
            </div>
          </div>

          <!-- COMMISSION section -->
          <div class="flex flex-col">
            <div>
              <div class="bg-teal-600 text-white px-3 py-2 text-xs font-semibold rounded-t">
                COMMISSION FOR PC
              </div>
              <div
                class="border border-gray-300 border-t-0 rounded-b p-3 bg-white dark:bg-slate-800 dark:border-gray-600 space-y-3">
                <div class="flex justify-between items-center">
                  <InputLabel for="referrer_id" value="Referrer Name" class="text-xs" />
                  <div class="relative w-64">
                      <input v-model="referrerSearch" id="referrer_id"
                      class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                      ref="referrerSelectRef" @keydown="handleReferrerKeydown"
                      @focus="handleReferrerFocus"
                      @blur="handleReferrerBlur"
                      placeholder="Search Referrer..." autocomplete="off" />
                    <ul v-if="showReferrerDropdown && filteredReferrers.length > 0" class="absolute left-0 right-0 z-50 mt-1 bg-white border border-gray-200 rounded shadow max-h-48 overflow-auto text-xs dark:bg-slate-700 dark:border-gray-600">
                      <li v-for="(r, idx) in filteredReferrers" :key="r.id" @mousedown.prevent="selectReferrer(r)" @mouseenter="highlightedRefIndex = idx"
                        :class="['px-2 py-1 cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-600', (idx===highlightedRefIndex) ? 'dropdown-highlight-blue font-semibold' : '']">{{ r.name }} <span :class="(idx===highlightedRefIndex) ? 'text-white' : 'text-gray-400 dark:text-gray-300'"> - {{ r.phone }}</span></li>
                    </ul>
                  </div>
                </div>
                <div v-if="commission.referrer_id && commissionBreakdown.length > 0"
                  class="bg-blue-50 border border-blue-200 rounded-lg p-2 text-xs">
                  <h4 class="font-medium text-blue-800 mb-2">Commission Breakdown:</h4>
                  <div class="space-y-1">
                    <div v-for="breakdown in commissionBreakdown" :key="breakdown.category"
                      class="flex justify-between items-center">
                      <span class="text-blue-700">
                        {{ breakdown.category }} ({{ breakdown.rate }}%):
                      </span>
                      <span :class="breakdown.hasCommission
                        ? 'text-green-600 font-medium'
                        : 'text-red-600'
                        ">
                        {{
                          breakdown.hasCommission
                            ? `৳${breakdown.commission.toFixed(2)}`
                            : "No Commission"
                        }}
                      </span>
                    </div>
                  </div>
                </div>
                <div class="flex justify-between items-center">
                  <InputLabel for="commissionTotal" value="Total:" class="text-xs" />
                  <input v-model="commission.total" type="number" step="1" id="commissionTotal"
                    class="w-24 px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-right dark:bg-gray-600 dark:text-gray-200"
                    readonly />
                </div>
                <div class="flex justify-between items-center">
                  <InputLabel for="physystAmt" value="Commission Amt:" class="text-xs" />
                  <input v-model="commission.physystAmt" type="number" step="1" id="physystAmt"
                    class="w-24 px-2 py-1 border border-gray-300 rounded text-xs bg-green-100 text-right font-semibold dark:bg-green-200 dark:text-gray-800"
                    readonly />
                </div>
                <div class="space-y-1">
                  <div class="flex justify-between items-center">
                    <InputLabel for="slider" value="Commission %:" class="text-xs" />
                    <span v-if="commissionDetails.manualCommissionEnabled"
                      class="text-xs text-orange-600 font-medium">Manual</span>
                    <span v-else-if="commission.referrer_id && commissionBreakdown.length > 0"
                      class="text-xs text-green-600 font-medium">Auto</span>
                  </div>
                  <div class="flex items-center space-x-2">
                    <input v-model="commission.slider" type="range" min="0" max="100" step="1" id="slider" :disabled="!commissionDetails.manualCommissionEnabled &&
                      !commission.referrer_id
                      " :class="[
                        'flex-1 h-2 rounded-lg appearance-none cursor-pointer',
                        commissionDetails.manualCommissionEnabled
                          ? 'bg-orange-200'
                          : 'bg-gray-200',
                        'dark:bg-gray-700',
                      ]" ref="commissionSliderRef" @keydown="handleCommissionSliderEnter" />
                    <span class="text-xs font-semibold w-8" :class="commissionDetails.manualCommissionEnabled
                      ? 'text-orange-600'
                      : 'text-gray-700 dark:text-gray-300'
                      ">
                      {{ commission.slider }}%
                    </span>
                  </div>
                  <div v-if="commissionDetails.manualCommissionEnabled" class="text-xs text-orange-600 mt-1">
                    Manual commission enabled - adjust as needed
                  </div>
                </div>
              </div>
            </div>
            <div class="p-4 bg-white dark:bg-slate-800 dark:border-gray-600">
              <div class="flex justify-end space-x-3">
                <button type="button" @click="cancelBill"
                  class="px-4 py-2 text-white bg-red-600 hover:bg-red-700 border border-red-700 rounded text-sm font-medium transition-colors">
                  Cancel
                </button>
                <button @click="saveBill()" @keydown.enter.prevent="saveBill()" ref="savePrintButtonRef"
                  :disabled="!hasOpenCashCounterSession"
                  class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm font-medium flex items-center space-x-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                  <span>💾</span>
                  <span>Save & Print Bill</span>
                </button>
                <button @click="saveBill(true)" @keydown.enter.prevent="saveBill(true)"
                  :disabled="!hasOpenCashCounterSession"
                  class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm font-medium flex items-center space-x-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                  <span>📥</span>
                  <span>Save</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="showCounterStartModal" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b px-5 py-3">
          <h3 class="text-base font-semibold text-gray-800">Counter Start</h3>
          <button
            type="button"
            class="text-gray-500 hover:text-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="cashCounterStartBusy"
            @click="closeCounterStartModal"
          >
            ✕
          </button>
        </div>

        <div class="px-5 py-4">
          <table class="w-full text-sm text-gray-700">
            <tr>
              <td class="py-1 font-semibold">User</td>
              <td class="py-1">{{ authInfo?.admin?.name || 'N/A' }}</td>
            </tr>
            <tr>
              <td class="py-1 font-semibold">Counter</td>
              <td class="py-1">{{ authInfo?.department?.name || 'N/A' }}</td>
            </tr>
          </table>

          <div class="mt-4">
            <label class="mb-1 block text-sm font-semibold text-gray-700">Opening Amount (Carry Cash)</label>
            <input
              v-model="counterStartForm.openingAmount"
              type="number"
              step="0.01"
              min="0"
              :disabled="cashCounterStartBusy"
              @keydown.enter.prevent="startCounterFromBilling"
              class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none disabled:bg-gray-100 disabled:cursor-not-allowed"
              placeholder=""
            >
          </div>

          <div class="mt-3">
            <label class="mb-1 block text-sm font-semibold text-gray-700">Note (optional)</label>
            <textarea
              v-model="counterStartForm.note"
              rows="2"
              :disabled="cashCounterStartBusy"
              class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none disabled:bg-gray-100 disabled:cursor-not-allowed"
              placeholder="Carry note"
            ></textarea>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 border-t px-5 py-3">
          <button
            type="button"
            class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="cashCounterStartBusy"
            @click="closeCounterStartModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="cashCounterStartBusy"
            @click="startCounterFromBilling"
          >
            {{ cashCounterStartBusy ? 'Starting...' : 'Start Counter' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="showCounterCloseModal" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b px-5 py-3">
          <h3 class="text-base font-semibold text-gray-800">Counter Close</h3>
          <button
            type="button"
            class="text-gray-500 hover:text-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="cashCounterCloseBusy"
            @click="closeCounterCloseModal"
          >
            ✕
          </button>
        </div>

        <div class="px-5 py-4">
          <table class="w-full text-sm text-gray-700">
            <tr>
              <td class="py-1 font-semibold">User</td>
              <td class="py-1">{{ authInfo?.admin?.name || 'N/A' }}</td>
            </tr>
            <tr>
              <td class="py-1 font-semibold">Counter</td>
              <td class="py-1">{{ authInfo?.department?.name || 'N/A' }}</td>
            </tr>
            <tr>
              <td class="py-1 font-semibold">Expected</td>
              <td class="py-1">{{ Number(cashCounterExpectedAmount || 0).toFixed(2) }}</td>
            </tr>
          </table>

          <div class="mt-4">
            <label class="mb-1 block text-sm font-semibold text-gray-700">Closing Amount</label>
            <input
              v-model="counterCloseForm.closingAmount"
              type="number"
              step="0.01"
              min="0"
              :disabled="cashCounterCloseBusy"
              @keydown.enter.prevent="closeCounterFromBilling"
              class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none disabled:bg-gray-100 disabled:cursor-not-allowed"
              placeholder=""
            >
          </div>

          <div class="mt-3">
            <label class="mb-1 block text-sm font-semibold text-gray-700">Note (optional)</label>
            <textarea
              v-model="counterCloseForm.note"
              rows="2"
              :disabled="cashCounterCloseBusy"
              class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none disabled:bg-gray-100 disabled:cursor-not-allowed"
              placeholder="Counter close note"
            ></textarea>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 border-t px-5 py-3">
          <button
            type="button"
            class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 disabled:opacity-60 disabled:cursor-not-allowed"
            :disabled="cashCounterCloseBusy"
            @click="closeCounterCloseModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60 disabled:cursor-not-allowed"
            :disabled="cashCounterCloseBusy"
            @click="closeCounterFromBilling"
          >
            {{ cashCounterCloseBusy ? 'Closing...' : 'Close & Print' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Dropdown highlight color - #4A90E2 (Light Accurate Blue) */
.dropdown-highlight-blue {
  background-color: #4A90E2 !important;
  color: white !important;
}

.dark .dropdown-highlight-blue {
  background-color: #4A90E2 !important;
  color: white !important;
}

.dropdown-hover-blue {
  background-color: #E8F0FE !important;
  color: #4A90E2 !important;
}

.dark .dropdown-hover-blue {
  background-color: #4A90E2 !important;
  color: white !important;
}

/* Default dropdown items for Gender: white background, black text */
.dropdown-default-white {
  background-color: #ffffff !important;
  color: #111827 !important;
}
.dropdown-default-white:hover {
  background-color: #f8fafc !important;
  color: #111827 !important;
}
.dark .dropdown-default-white {
  background-color: #1f2937 !important; /* slate-800 */
  color: #e5e7eb !important;
}
.dark .dropdown-default-white:hover {
  background-color: #374151 !important; /* slate-700 */
}

/* Custom Slider Styling */
.slider::-webkit-slider-thumb {
  appearance: none;
  height: 18px;
  width: 18px;
  border-radius: 50%;
  background: #10b981;
  cursor: pointer;
  border: 2px solid #ffffff;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.slider::-moz-range-thumb {
  height: 18px;
  width: 18px;
  border-radius: 50%;
  background: #10b981;
  cursor: pointer;
  border: 2px solid #ffffff;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

#receivingAmt::-webkit-outer-spin-button,
#receivingAmt::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

#receivingAmt {
  -moz-appearance: textfield;
  appearance: textfield;
}

/* Input focus improvements */
input:focus,
select:focus,
textarea:focus {
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
}

/* Billing page text size adjustment */
.billing-page-text,
.billing-page-text * {
  font-size: 0.95rem !important;
}

.billing-page-text input,
.billing-page-text select,
.billing-page-text textarea,
.billing-page-text button {
  line-height: 1.35 !important;
}

/* Table styling improvements */
table {
  border-collapse: separate;
  border-spacing: 0;
}

th,
td {
  border-bottom: 1px solid #e5e7eb;
}

th:first-child,
td:first-child {
  border-left: 1px solid #e5e7eb;
}

th:last-child,
td:last-child {
  border-right: 1px solid #e5e7eb;
}

/* Professional color scheme matching the image */
.bg-yellow-100 {
  background-color: #fef3c7 !important;
}

.bg-green-200 {
  background-color: #bbf7d0 !important;
}

.bg-red-100 {
  background-color: #fee2e2 !important;
}

.bg-gray-100 {
  background-color: #f3f4f6 !important;
}

/* Dark mode colors */
.dark .bg-yellow-200 {
  background-color: #fbbf24 !important;
}

.dark .bg-green-300 {
  background-color: #6ee7b7 !important;
}

.dark .bg-red-200 {
  background-color: #fca5a5 !important;
}

/* Hover effects */
button:hover {
  transform: translateY(-1px);
  transition: all 0.2s ease;
}

select:hover,
input:hover {
  border-color: #6b7280;
}

/* Scrollbar styling */
::-webkit-scrollbar {
  width: 10px;
  height: 10px;
}

::-webkit-scrollbar-track {
  background: color-mix(in srgb, var(--app-theme-soft) 26%, #e2e8f0);
  border-radius: 8px;
}

::-webkit-scrollbar-thumb {
  background: color-mix(in srgb, var(--app-theme-primary) 40%, #94a3b8);
  border-radius: 8px;
}

::-webkit-scrollbar-thumb:hover {
  background: color-mix(in srgb, var(--app-theme-primary) 56%, #64748b);
}

/* Dynamic height for item list */
.max-h-custom {
  max-height: calc(100vh - 500px);
  min-height: 100px;
}

/* Ensure main container allows scrolling */
.min-h-screen {
  overflow-y: auto;
  height: 100vh;
}

/* Make sure the grid layout doesn't restrict height */
.grid.grid-cols-1.lg\:grid-cols-3 {
  align-items: flex-start;
  min-height: 0;
  /* Fix for grid item overflow */
}

/* Fix for commission column layout */
.flex-col {
  min-height: 0;
  /* Fix for flex item overflow */
}

/* Responsive improvements */
@media (max-width: 1024px) {
  .grid.lg\:grid-cols-12 {
    grid-template-columns: repeat(1, minmax(0, 1fr));
  }

  .lg\:col-span-2,
  .lg\:col-span-1 {
    grid-column: span 1 / span 1;
  }
}

@media (max-width: 768px) {
  .grid.lg\:grid-cols-3 {
    grid-template-columns: repeat(1, minmax(0, 1fr));
  }

  .flex.justify-end {
    justify-content: center;
  }

  .max-h-custom {
    max-height: 300px;
  }
}
</style>