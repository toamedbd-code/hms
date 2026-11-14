<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { warningMessage, errorMessage, showToastIfNoFlash, successMessage } from '@/responseMessage.js';

const props = defineProps({
  billing: Object,
  billItems: Array,
  pageTitle: {
    type: String,
    default: 'Report Entry',
  },
  department: {
    type: String,
    default: '',
  },
});

const hiddenItemIds = ref([]);
const items = computed(() => (props.billItems ?? []).filter((it) => !hiddenItemIds.value.includes(it.id)));

const itemForms = reactive({});
const editorRefs = reactive({});
const reportFieldRefs = reactive({});
const fileUiState = reactive({});
const editorUiState = reactive({});
const FULL_PAGE_MARKER = '[[FULL_PAGE]]';
const CBC_HISTOGRAM_MARKER_REGEX = /\[\[CBC_HISTOGRAM:(SHOW|HIDE)\]\]/i;
const UNIVERSAL_FONT_STACK = "'Noto Sans Bengali', 'Hind Siliguri', 'SolaimanLipi', 'Kalpurush', 'Noto Sans', 'Noto Serif Bengali', 'Noto Serif', 'Arial Unicode MS', 'Segoe UI', sans-serif";
let sessionHeartbeatTimer = null;

const isCbcReportItem = (item) => {
  const keyText = `${item?.item_name ?? ''} ${item?.category ?? ''} ${item?.report_function ?? ''}`.toLowerCase();
  return keyText.includes('cbc')
    || keyText.includes('complete blood count')
    || keyText.includes('hematology')
    || keyText.includes('haematology')
    || keyText.includes('blood count');
};

const stripCbcHistogramMarker = (value) => String(value ?? '')
  .replace(CBC_HISTOGRAM_MARKER_REGEX, '')
  .trim();

const updateCsrfToken = (token) => {
  if (typeof token !== 'string' || token === '') return;
  document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);
};

const refreshReportingSession = async () => {
  try {
    const response = await fetch(route('backend.reporting.session'), {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    if (response.status === 401 || response.redirected) {
      window.location.assign(response.url || route('backend.auth.login2'));
      return null;
    }

    if (!response.ok) return false;

    const payload = await response.json();
    updateCsrfToken(payload?.csrf_token);
    return typeof payload?.csrf_token === 'string' && payload.csrf_token !== '';
  } catch (error) {
    console.warn('[Reporting] Could not refresh the session before saving.', error);
    return false;
  }
};

const postReportItem = async (itemId, formData) => {
  const sessionReady = await refreshReportingSession();
  if (sessionReady === null) return null;
  if (!sessionReady) {
    errorMessage('রিপোর্ট সেভ করা যায়নি। সংযোগ পরীক্ষা করে আবার চেষ্টা করুন।');
    return null;
  }

  const send = () => {
    formData.set('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
    return fetch(route('backend.reporting.item.update', itemId), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: formData,
    });
  };

  let response = await send();
  if (response.status === 419) {
    const sessionRefreshed = await refreshReportingSession();
    if (sessionRefreshed === null) return null;
    if (sessionRefreshed) response = await send();
  }

  if (response.status === 401 || response.redirected) {
    window.location.assign(response.url || route('backend.auth.login2'));
    return null;
  }

  return response;
};

const refreshSessionWhenVisible = () => {
  if (document.visibilityState === 'visible') {
    refreshReportingSession();
  }
};

onMounted(() => {
  document.addEventListener('visibilitychange', refreshSessionWhenVisible);
  sessionHeartbeatTimer = window.setInterval(() => {
    refreshSessionWhenVisible();
  }, 5 * 60 * 1000);
});

onUnmounted(() => {
  document.removeEventListener('visibilitychange', refreshSessionWhenVisible);
  if (sessionHeartbeatTimer !== null) {
    window.clearInterval(sessionHeartbeatTimer);
    sessionHeartbeatTimer = null;
  }
});

const resolveInitialRange = (item) => {
  const savedRange = String(item?.report_range ?? '').trim();
  if (savedRange) return savedRange;

  const suggestedRange = String(item?.default_report_range ?? '').trim();
  return suggestedRange;
};

const fileNameFromUrl = (url) => {
  const value = String(url ?? '').trim();
  if (!value) return '';

  try {
    const parsed = new URL(value, window.location.origin);
    const parts = parsed.pathname.split('/').filter(Boolean);
    return decodeURIComponent(parts[parts.length - 1] ?? '');
  } catch {
    const parts = value.split('/').filter(Boolean);
    return decodeURIComponent(parts[parts.length - 1] ?? '');
  }
};

const hasFullPageMarker = (value) => String(value ?? '').trim().startsWith(FULL_PAGE_MARKER);

const stripFullPageMarker = (value) => {
  const text = String(value ?? '').trim();
  return text.startsWith(FULL_PAGE_MARKER)
    ? text.slice(FULL_PAGE_MARKER.length).trim()
    : text;
};

const withFullPageMarker = (value) => `${FULL_PAGE_MARKER}\n${String(value ?? '').trim()}`;

const textIncludesAny = (text, keywords) => {
  const source = String(text ?? '').toLowerCase();
  return keywords.some((keyword) => source.includes(keyword));
};

const isFullPageEligible = (item) => {
  const itemName = String(item?.item_name ?? '').toLowerCase();
  const category = String(item?.category ?? '').toLowerCase();
  const reportTitle = String(item?.report_title ?? '').toLowerCase();

  const ultraKeywords = ['ultrasonogram', 'ultrasonography', 'usg'];
  const xrayKeywords = ['xray', 'x-ray', 'x ray', 'radiography', 'xr'];

  if (category.includes('radiology')) return true;

  return (
    textIncludesAny(itemName, ultraKeywords)
    || textIncludesAny(category, ultraKeywords)
    || textIncludesAny(reportTitle, ultraKeywords)
    || textIncludesAny(itemName, xrayKeywords)
    || textIncludesAny(category, xrayKeywords)
    || textIncludesAny(reportTitle, xrayKeywords)
  );
};

const resolveNarrativeTemplate = (item) => {
  const text = `${item?.item_name ?? ''} ${item?.category ?? ''}`.toLowerCase();

  if (text.includes('ultrason') || text.includes('usg')) {
    // Thyroid / neck
    if (text.includes('thyroid') || text.includes('neck') || text.includes('parathyroid')) {
      return [
        'EXAMINATION: ULTRASONOGRAPHY OF THYROID',
        '',
        'FINDINGS:',
        '- Thyroid gland:',
        '  - Right lobe:',
        '  - Left lobe:',
        '  - Isthmus:',
        '- Any nodule:',
        '- Lymph nodes in neck:',
        '',
        'IMPRESSION:',
        '- ',
      ].join('\n');
    }

    // Pelvis / gynaecology / obstetrics
    if (
      text.includes('pelvi') || text.includes('obstet') || text.includes('gynae') ||
      text.includes('gynaec') || text.includes('lower abdomen') || text.includes('uterus') ||
      text.includes('ovari')
    ) {
      return [
        'EXAMINATION: ULTRASONOGRAPHY OF PELVIS',
        '',
        'FINDINGS:',
        '- Uterus:',
        '- Endometrium:',
        '- Right ovary:',
        '- Left ovary:',
        '- Urinary bladder:',
        '- Pouch of Douglas:',
        '- Any free fluid:',
        '',
        'IMPRESSION:',
        '- ',
      ].join('\n');
    }

    // Renal / KUB / kidney
    if (
      text.includes('renal') || text.includes('kub') || text.includes('kidney') ||
      text.includes('urinary') || text.includes('bladder') || text.includes('ureter')
    ) {
      return [
        'EXAMINATION: ULTRASONOGRAPHY OF KUB (KIDNEYS, URETERS & BLADDER)',
        '',
        'FINDINGS:',
        '- Right kidney:',
        '- Left kidney:',
        '- Ureters:',
        '- Urinary bladder:',
        '',
        'IMPRESSION:',
        '- ',
      ].join('\n');
    }

    // Scrotal / testes
    if (text.includes('scrot') || text.includes('testi') || text.includes('testis')) {
      return [
        'EXAMINATION: ULTRASONOGRAPHY OF SCROTUM',
        '',
        'FINDINGS:',
        '- Right testis:',
        '- Left testis:',
        '- Epididymis:',
        '- Any hydrocele/varicocele:',
        '',
        'IMPRESSION:',
        '- ',
      ].join('\n');
    }

    // Breast
    if (text.includes('breast')) {
      return [
        'EXAMINATION: ULTRASONOGRAPHY OF BREAST',
        '',
        'FINDINGS:',
        '- Right breast:',
        '- Left breast:',
        '- Axillary lymph nodes:',
        '',
        'IMPRESSION:',
        '- ',
      ].join('\n');
    }

    // Hepatobiliary / upper abdomen / liver
    if (text.includes('hepat') || text.includes('liver') || text.includes('gallbl') || text.includes('gall bl') || text.includes('biliary') || text.includes('upper abdomen')) {
      return [
        'EXAMINATION: ULTRASONOGRAPHY OF UPPER ABDOMEN',
        '',
        'FINDINGS:',
        '- Liver:',
        '- Gall bladder:',
        '- Common bile duct:',
        '- Pancreas:',
        '- Spleen:',
        '- Portal vein:',
        '',
        'IMPRESSION:',
        '- ',
      ].join('\n');
    }

    // Whole abdomen (default USG template)
    return [
      'EXAMINATION: ULTRASONOGRAPHY OF WHOLE ABDOMEN',
      '',
      'FINDINGS:',
      '- Liver:',
      '- Gall bladder:',
      '- Common bile duct:',
      '- Pancreas:',
      '- Spleen:',
      '- Both kidneys:',
      '- Urinary bladder:',
      '- Uterus/Prostate:',
      '- Any free fluid:',
      '',
      'IMPRESSION:',
      '- ',
    ].join('\n');
  }

  if (text.includes('xray') || text.includes('x-ray') || text.includes('x ray') || text.includes('radiograph')) {
    // Detect body part for X-ray
    let part = '';
    if (text.includes('chest') || text.includes('cxr')) part = 'CHEST';
    else if (text.includes('hand') || text.includes('wrist') || text.includes('finger')) part = 'HAND/WRIST';
    else if (text.includes('knee')) part = 'KNEE';
    else if (text.includes('shoulder')) part = 'SHOULDER';
    else if (text.includes('hip')) part = 'HIP';
    else if (text.includes('spine') || text.includes('lumbar') || text.includes('cervical') || text.includes('dorsal')) part = 'SPINE';
    else if (text.includes('foot') || text.includes('ankle')) part = 'FOOT/ANKLE';
    else if (text.includes('skull') || text.includes('head')) part = 'SKULL';
    else if (text.includes('abdomen')) part = 'ABDOMEN';
    else if (text.includes('elbow')) part = 'ELBOW';
    const label = part ? `X-RAY ${part}` : 'X-RAY';
    return [
      `EXAMINATION: ${label}`,
      '',
      'FINDINGS:',
      '- ',
      '',
      'IMPRESSION:',
      '- ',
    ].join('\n');
  }

  if (text.includes('cbc') || text.includes('complete blood')) {
    return [
      'TEST: COMPLETE BLOOD COUNT (CBC)',
      '',
      'COMMENT:',
      '- ',
    ].join('\n');
  }

  return '';
};

items.value.forEach((item) => {
  const storedNote = String(item.report_note ?? '');
  const histogramPreference = storedNote.match(CBC_HISTOGRAM_MARKER_REGEX)?.[1]?.toUpperCase();
  const initialUseFullPage = hasFullPageMarker(item.report_note ?? '')
    || /<[^>]+>/.test(String(item.report_note ?? ''));
  const initialNote = stripFullPageMarker(stripCbcHistogramMarker(storedNote));

  itemForms[item.id] = useForm({
    report_note: initialUseFullPage && !/<[^>]+>/.test(initialNote)
      ? plainTextToHtml(initialNote)
      : initialNote,
    report_range: resolveInitialRange(item),
    report_file: null,
    // Always use direct editable result box for simpler workflow.
    use_full_page: initialUseFullPage,
    cbc_show_histogram: histogramPreference !== 'HIDE',
    // parameter_values will be populated below (empty by default)
    parameter_values: {},
    department: props.department || '',
  });

  fileUiState[item.id] = {
    selectedName: '',
    previewUrl: '',
    savedName: fileNameFromUrl(item.report_file_url),
    savedUrl: String(item.report_file_url ?? ''),
    docHint: false,
    importingStored: false,
  };

  editorUiState[item.id] = {
    lineHeight: '1.75',
    tableBorderColor: '#cbd5e1',
    tableBorderWidth: '1',
  };

  // Initialize parameter_values from grouped parameters (preferred) or flat list
  const savedVals = item.saved_parameter_values ?? {};
  if (Array.isArray(item.parameter_groups) && item.parameter_groups.length) {
    itemForms[item.id].parameter_values = {};
    item.parameter_groups.forEach((g) => {
      (g.parameters || []).forEach((p) => {
        itemForms[item.id].parameter_values[p.id] = String(savedVals[p.id] ?? '');
      });
    });
  } else if (Array.isArray(item.parameters) && item.parameters.length) {
    itemForms[item.id].parameter_values = {};
    item.parameters.forEach((p) => {
      itemForms[item.id].parameter_values[p.id] = String(savedVals[p.id] ?? '');
    });
  }

  if (!String(itemForms[item.id].report_note ?? '').trim()) {
    const narrativeTemplate = resolveNarrativeTemplate(item);
    if (narrativeTemplate) {
      itemForms[item.id].report_note = narrativeTemplate;
    }
  }
});

const shouldRenderLargeResultBox = (itemId) => {
  return Boolean(fileUiState[itemId]?.selectedName)
    || Boolean(fileUiState[itemId]?.savedName)
    || Boolean(itemForms[itemId]?.use_full_page);
};

const hasParameterRows = (item) => {
  if (!item) return false;

  if (Array.isArray(item.parameter_groups) && item.parameter_groups.some((group) => Array.isArray(group.parameters) && group.parameters.length > 0)) {
    return true;
  }

  return Array.isArray(item.parameters) && item.parameters.length > 0;
};

const countTotalParameters = (item) => {
  if (!item) return 0;
  if (Array.isArray(item.parameter_groups) && item.parameter_groups.length) {
    return item.parameter_groups.reduce((sum, g) => sum + (Array.isArray(g.parameters) ? g.parameters.length : 0), 0);
  }
  return Array.isArray(item.parameters) ? item.parameters.length : 0;
};

const shouldShowParameterSection = (item) => {
  if (!item) return false;

  // Only show the parameter table when there are 2+ parameters.
  // Single-parameter items use the plain Result textarea directly.
  if (hasParameterRows(item) && countTotalParameters(item) > 1) {
    return true;
  }

  const keyText = `${item.item_name ?? ''} ${item.category ?? ''} ${item.report_function ?? ''}`.toLowerCase();
  const isCbcTest = keyText.includes('cbc')
    || keyText.includes('complete blood count')
    || keyText.includes('hematology')
    || keyText.includes('haematology')
    || keyText.includes('blood count');

  return isCbcTest && countTotalParameters(item) > 1;
};

const getCbcParameterLabel = (item, param) => {
  if (!isCbcReportItem(item)) return param?.name ?? '';

  const normalizedName = String(param?.name ?? '').toLowerCase().replace(/[^a-z0-9]+/g, '');
  if (['atypicalcell', 'blastatypical', 'blastatypicalcell', 'blastyatypical', 'blastyatypicalcell'].includes(normalizedName)) return 'Blast/Atypical Cell';
  if (['myelocytes', 'myelocytespercent'].includes(normalizedName)) return 'Myelocytes';
  return param?.name ?? '';
};

const getBloodFilmParameter = (item) => (item?.parameters ?? [])
  .find((param) => String(param.name ?? '').trim().toLowerCase() === 'blood film') ?? null;

const getReportNoteWithHistogramPreference = (itemId, item, note) => {
  const cleanNote = stripCbcHistogramMarker(note);
  if (!isCbcReportItem(item)) return cleanNote;

  const preference = itemForms[itemId]?.cbc_show_histogram ? 'SHOW' : 'HIDE';
  return `${cleanNote}\n[[CBC_HISTOGRAM:${preference}]]`;
};

const deactivateFullPageEditor = async (itemId) => {
  if (!itemForms[itemId]?.use_full_page) return;

  itemForms[itemId].report_note = htmlToPlainText(itemForms[itemId].report_note);
  itemForms[itemId].use_full_page = false;
  await nextTick();
  autoGrowReportField(itemId);
};

const activateFullPageEditor = async (itemId) => {
  const current = String(itemForms[itemId]?.report_note ?? '');
  const looksLikeHtml = /<[^>]+>/.test(current);

  itemForms[itemId].use_full_page = true;
  if (!looksLikeHtml) {
    itemForms[itemId].report_note = plainTextToHtml(current);
  }

  await nextTick();
  if (editorRefs[itemId]) {
    editorRefs[itemId].innerHTML = itemForms[itemId].report_note;
    autoGrowEditorField(itemId);
  }
};

const removeAttachmentMarkerFromText = (value) => String(value ?? '')
  .replace(/\n?Attachment:\s.*$/gim, '')
  .trim();

const applyAttachmentToResultBox = async (itemId, fileName) => {
  const cleanName = String(fileName ?? '').trim();
  if (!cleanName) return;

  if (itemForms[itemId].use_full_page) {
    const editor = editorRefs[itemId];
    const markerHtml = `<p data-attachment-marker="true" style="margin-top:12px; font-weight:600;">Attachment: ${escapeHtml(cleanName)}</p>`;

    if (editor) {
      const wrapper = document.createElement('div');
      wrapper.innerHTML = editor.innerHTML || '';
      wrapper.querySelectorAll('[data-attachment-marker="true"]').forEach((node) => node.remove());
      wrapper.insertAdjacentHTML('beforeend', markerHtml);
      editor.innerHTML = wrapper.innerHTML;
      itemForms[itemId].report_note = editor.innerHTML;
      return;
    }

    const wrapper = document.createElement('div');
    wrapper.innerHTML = itemForms[itemId].report_note || '';
    wrapper.querySelectorAll('[data-attachment-marker="true"]').forEach((node) => node.remove());
    wrapper.insertAdjacentHTML('beforeend', markerHtml);
    itemForms[itemId].report_note = wrapper.innerHTML;
    return;
  }

  const base = removeAttachmentMarkerFromText(itemForms[itemId].report_note);
  itemForms[itemId].report_note = base ? `${base}\nAttachment: ${cleanName}` : `Attachment: ${cleanName}`;
  await nextTick();
  autoGrowReportField(itemId);
};

const clearSelectedFilePreview = (itemId) => {
  const currentUrl = fileUiState[itemId]?.previewUrl;
  if (currentUrl && currentUrl.startsWith('blob:')) {
    URL.revokeObjectURL(currentUrl);
  }

  if (fileUiState[itemId]) {
    fileUiState[itemId].selectedName = '';
    fileUiState[itemId].previewUrl = '';
    fileUiState[itemId].docHint = false;
  }

  if (itemForms[itemId].use_full_page) {
    const editor = editorRefs[itemId];
    if (editor) {
      const wrapper = document.createElement('div');
      wrapper.innerHTML = editor.innerHTML || '';
      wrapper.querySelectorAll('[data-attachment-marker="true"]').forEach((node) => node.remove());
      editor.innerHTML = wrapper.innerHTML;
      itemForms[itemId].report_note = editor.innerHTML;
    }
  } else {
    itemForms[itemId].report_note = removeAttachmentMarkerFromText(itemForms[itemId].report_note);
    autoGrowReportField(itemId);
  }
};

const setEditorRef = (el, itemId) => {
  if (!el) return;
  editorRefs[itemId] = el;
  if (el.innerHTML !== itemForms[itemId].report_note) {
    el.innerHTML = itemForms[itemId].report_note;
  }
  nextTick(() => {
    autoGrowEditorField(itemId);
  });
};

const autoGrowEditorField = (itemId) => {
  const el = editorRefs[itemId];
  if (!el) return;
  el.style.height = 'auto';
  el.style.height = `${Math.max(el.scrollHeight, 46)}px`;
};

const applyEditorCommand = (itemId, command) => {
  const editor = editorRefs[itemId];
  if (!editor) return;

  editor.focus();
  document.execCommand(command, false, null);
  itemForms[itemId].report_note = editor.innerHTML;
  autoGrowEditorField(itemId);
};

const applyEditorCommandWithValue = (itemId, command, value) => {
  const editor = editorRefs[itemId];
  if (!editor) return;

  editor.focus();
  document.execCommand(command, false, value);
  itemForms[itemId].report_note = editor.innerHTML;
  autoGrowEditorField(itemId);
};

const insertEditorHtml = (itemId, html) => {
  const editor = editorRefs[itemId];
  if (!editor) return;

  editor.focus();
  document.execCommand('insertHTML', false, html);
  itemForms[itemId].report_note = editor.innerHTML;
  autoGrowEditorField(itemId);
};

const insertTable = (itemId) => {
  const tableHtml = `
    <table style="width:100%; border-collapse:collapse; margin:12px 0; font-size:13px; table-layout:fixed;">
      <colgroup>
        <col style="width:35%;" />
        <col style="width:65%;" />
      </colgroup>
      <tr>
        <th style="border:1px solid #cbd5e1; padding:8px; text-align:left;">Field</th>
        <th style="border:1px solid #cbd5e1; padding:8px; text-align:left;">Findings</th>
      </tr>
      <tr>
        <td style="border:1px solid #cbd5e1; padding:8px;">-</td>
        <td style="border:1px solid #cbd5e1; padding:8px; min-height: 60px;">-</td>
      </tr>
    </table>
  `;

  insertEditorHtml(itemId, tableHtml);
};

const insertPageBreak = (itemId) => {
  const pageBreakHtml = '<div style="page-break-after: always; border-top: 1px dashed #94a3b8; margin: 12px 0;"></div>';
  insertEditorHtml(itemId, pageBreakHtml);
};

const onEditorInput = (event, itemId) => {
  itemForms[itemId].report_note = event.target?.innerHTML ?? '';
  autoGrowEditorField(itemId);
};

const increaseEditorFontSize = (itemId) => {
  applyEditorCommandWithValue(itemId, 'fontSize', '4');
};

const decreaseEditorFontSize = (itemId) => {
  applyEditorCommandWithValue(itemId, 'fontSize', '2');
};

const setEditorLineHeight = (itemId, lineHeight) => {
  const value = String(lineHeight ?? '').trim() || '1.75';
  editorUiState[itemId].lineHeight = value;

  const editor = editorRefs[itemId];
  if (editor) {
    editor.style.lineHeight = value;
  }
};

const setEditorBlockFormat = (itemId, blockTag) => {
  applyEditorCommandWithValue(itemId, 'formatBlock', blockTag);
};

const setEditorTextColor = (itemId, color) => {
  applyEditorCommandWithValue(itemId, 'foreColor', color);
};

const setEditorHighlightColor = (itemId, color) => {
  applyEditorCommandWithValue(itemId, 'hiliteColor', color);
};

const insertEditorLink = (itemId) => {
  const url = window.prompt('Enter URL');
  if (!url) return;
  applyEditorCommandWithValue(itemId, 'createLink', url.trim());
};

const removeEditorFormat = (itemId) => {
  applyEditorCommand(itemId, 'removeFormat');
};

const syncEditorFromDom = (itemId) => {
  const editor = editorRefs[itemId];
  if (!editor) return;
  itemForms[itemId].report_note = editor.innerHTML;
  autoGrowEditorField(itemId);
};

const getActiveEditorCellContext = (itemId) => {
  const editor = editorRefs[itemId];
  if (!editor) return null;

  const selection = window.getSelection();
  const node = selection?.anchorNode ?? null;
  if (!node) return null;

  const element = node.nodeType === Node.TEXT_NODE
    ? node.parentElement
    : node;

  if (!(element instanceof Element) || !editor.contains(element)) {
    return null;
  }

  const cell = element.closest('td, th');
  const table = element.closest('table');
  if (!cell || !table) return null;

  return { editor, table, cell };
};

const addTableRowNearSelection = (itemId, position = 'after') => {
  const ctx = getActiveEditorCellContext(itemId);
  if (!ctx) {
    warningMessage('টেবিলের একটি সেলের ভিতরে কার্সর রেখে আবার চেষ্টা করুন।');
    return;
  }

  const row = ctx.cell.parentElement;
  if (!(row instanceof HTMLTableRowElement)) return;

  const newRow = document.createElement('tr');
  const cells = Array.from(row.cells);
  cells.forEach((sourceCell) => {
    const tag = sourceCell.tagName.toLowerCase() === 'th' ? 'th' : 'td';
    const nextCell = document.createElement(tag);
    nextCell.innerHTML = '-';
    nextCell.style.border = sourceCell.style.border || '1px solid #cbd5e1';
    nextCell.style.padding = sourceCell.style.padding || '8px';
    nextCell.style.verticalAlign = sourceCell.style.verticalAlign || 'top';
    newRow.appendChild(nextCell);
  });

  if (position === 'before') {
    row.insertAdjacentElement('beforebegin', newRow);
  } else {
    row.insertAdjacentElement('afterend', newRow);
  }

  syncEditorFromDom(itemId);
};

const addTableColumnNearSelection = (itemId, position = 'after') => {
  const ctx = getActiveEditorCellContext(itemId);
  if (!ctx) {
    warningMessage('টেবিলের একটি সেলের ভিতরে কার্সর রেখে আবার চেষ্টা করুন।');
    return;
  }

  const row = ctx.cell.parentElement;
  if (!(row instanceof HTMLTableRowElement)) return;
  const index = Array.from(row.cells).indexOf(ctx.cell);
  if (index < 0) return;

  Array.from(ctx.table.rows).forEach((tr) => {
    const refCell = tr.cells[index] ?? tr.cells[tr.cells.length - 1] ?? null;
    if (!refCell) return;

    const tag = refCell.tagName.toLowerCase() === 'th' ? 'th' : 'td';
    const newCell = document.createElement(tag);
    newCell.innerHTML = '-';
    newCell.style.border = refCell.style.border || '1px solid #cbd5e1';
    newCell.style.padding = refCell.style.padding || '8px';
    newCell.style.verticalAlign = refCell.style.verticalAlign || 'top';

    if (position === 'before') {
      refCell.insertAdjacentElement('beforebegin', newCell);
    } else {
      refCell.insertAdjacentElement('afterend', newCell);
    }
  });

  syncEditorFromDom(itemId);
};

const deleteTableRowAtSelection = (itemId) => {
  const ctx = getActiveEditorCellContext(itemId);
  if (!ctx) {
    warningMessage('টেবিলের একটি সেলের ভিতরে কার্সর রেখে আবার চেষ্টা করুন।');
    return;
  }

  const row = ctx.cell.parentElement;
  if (!(row instanceof HTMLTableRowElement)) return;

  if (ctx.table.rows.length <= 1) {
    warningMessage('এটা শেষ row, delete করা যাবে না।');
    return;
  }

  row.remove();
  syncEditorFromDom(itemId);
};

const deleteTableColumnAtSelection = (itemId) => {
  const ctx = getActiveEditorCellContext(itemId);
  if (!ctx) {
    warningMessage('টেবিলের একটি সেলের ভিতরে কার্সর রেখে আবার চেষ্টা করুন।');
    return;
  }

  const row = ctx.cell.parentElement;
  if (!(row instanceof HTMLTableRowElement)) return;
  if (row.cells.length <= 1) {
    warningMessage('এটা শেষ column, delete করা যাবে না।');
    return;
  }

  const index = Array.from(row.cells).indexOf(ctx.cell);
  if (index < 0) return;

  Array.from(ctx.table.rows).forEach((tr) => {
    if (tr.cells[index]) {
      tr.cells[index].remove();
    }
  });

  syncEditorFromDom(itemId);
};

const mergeCellWithRight = (itemId) => {
  const ctx = getActiveEditorCellContext(itemId);
  if (!ctx) {
    warningMessage('টেবিলের একটি সেলের ভিতরে কার্সর রেখে আবার চেষ্টা করুন।');
    return;
  }

  const next = ctx.cell.nextElementSibling;
  if (!(next instanceof HTMLTableCellElement)) {
    warningMessage('ডানের cell নেই, merge করা সম্ভব না।');
    return;
  }

  const leftSpan = Number(ctx.cell.getAttribute('colspan') || 1);
  const rightSpan = Number(next.getAttribute('colspan') || 1);
  ctx.cell.setAttribute('colspan', String(leftSpan + rightSpan));

  const rightHtml = String(next.innerHTML || '').trim();
  if (rightHtml) {
    ctx.cell.innerHTML = `${ctx.cell.innerHTML}<br>${rightHtml}`;
  }

  next.remove();
  syncEditorFromDom(itemId);
};

const splitSelectedCell = (itemId) => {
  const ctx = getActiveEditorCellContext(itemId);
  if (!ctx) {
    warningMessage('টেবিলের একটি সেলের ভিতরে কার্সর রেখে আবার চেষ্টা করুন।');
    return;
  }

  const span = Number(ctx.cell.getAttribute('colspan') || 1);
  if (span <= 1) {
    warningMessage('এই cell এ split করার মতো colspan নেই।');
    return;
  }

  ctx.cell.setAttribute('colspan', String(span - 1));
  const newCell = document.createElement(ctx.cell.tagName.toLowerCase());
  newCell.innerHTML = '-';
  newCell.style.border = ctx.cell.style.border || '1px solid #cbd5e1';
  newCell.style.padding = ctx.cell.style.padding || '8px';
  newCell.style.verticalAlign = ctx.cell.style.verticalAlign || 'top';
  ctx.cell.insertAdjacentElement('afterend', newCell);

  syncEditorFromDom(itemId);
};

const applyTableBorderStyle = (itemId) => {
  const editor = editorRefs[itemId];
  if (!editor) return;

  const color = String(editorUiState[itemId]?.tableBorderColor || '#cbd5e1');
  const width = String(editorUiState[itemId]?.tableBorderWidth || '1');
  const border = `${width}px solid ${color}`;

  editor.querySelectorAll('table').forEach((table) => {
    table.style.borderCollapse = 'collapse';
    table.style.width = '100%';
    table.style.border = border;
  });

  editor.querySelectorAll('th, td').forEach((cell) => {
    cell.style.border = border;
    if (!cell.style.padding) {
      cell.style.padding = '8px';
    }
  });

  syncEditorFromDom(itemId);
};

const setReportFieldRef = (el, itemId) => {
  if (!el) return;
  reportFieldRefs[itemId] = el;
  nextTick(() => {
    el.style.height = 'auto';
    el.style.height = `${el.scrollHeight}px`;
  });
};

const autoGrowReportField = (itemId) => {
  const el = reportFieldRefs[itemId];
  if (!el) return;
  el.style.height = 'auto';
  el.style.height = `${el.scrollHeight}px`;
};

const onPlainReportInput = (event, itemId) => {
  itemForms[itemId].report_note = event.target?.value ?? '';
  autoGrowReportField(itemId);
};

const escapeHtml = (value) => String(value ?? '')
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')
  .replace(/\"/g, '&quot;')
  .replace(/'/g, '&#039;');

const plainTextToHtml = (value) => escapeHtml(value).replace(/\n/g, '<br>');

const htmlToPlainText = (value) => {
  const holder = document.createElement('div');
  holder.innerHTML = String(value ?? '');
  return String(holder.textContent ?? holder.innerText ?? '').trim();
};

const isLikelyCorruptedExtraction = (value) => {
  const text = String(value ?? '');
  if (!text) return true;

  const total = text.length;
  const badCharCount = (text.match(/[\u0000\uFFFD\uFFFF]/g) ?? []).length;
  const readableCount = (text.match(/[\u0980-\u09FFA-Za-z0-9.,;:()\-_/\s]/g) ?? []).length;
  const suspiciousRun = /(?:[\uFFFD\uFFFF]){4,}|(?:[\uE000-\uF8FF]){3,}/.test(text);

  return suspiciousRun
    || (badCharCount / total) > 0.02
    || (readableCount / total) < 0.35;
};

const normalizeUnicodeText = (value) => String(value ?? '')
  .normalize('NFC')
  .replace(/\uFFFD/g, ' ')
  .replace(/[\x00-\x08\x0B\x0C\x0E-\x1F]/g, ' ')
  // Preserve line breaks; collapse only horizontal spaces.
  .replace(/[^\S\r\n]{2,}/g, ' ')
  .replace(/\n{3,}/g, '\n\n')
  .trim();

const setDocImportFallbackText = async (itemId, fileName = '') => {
  const item = items.value.find((entry) => entry.id === itemId) ?? null;
  const current = String(itemForms[itemId]?.report_note ?? '').trim();
  const baselineTemplate = String(resolveNarrativeTemplate(item) ?? '').trim();
  const fileLabel = String(fileName ?? '').trim();

  const fallbackLines = [
    'DOC ফাইল থেকে clean text import করা যায়নি।',
    'DOCX আপলোড করুন অথবা নিচে রিপোর্ট লিখুন।',
  ];

  if (fileLabel) {
    fallbackLines.push(`Uploaded file: ${fileLabel}`);
  }

  fallbackLines.push(
    '',
    'FINDINGS:',
    '- ',
    '',
    'IMPRESSION:',
    '- ',
  );

  const fallbackText = fallbackLines.join('\n');
  const shouldReplace = !current
    || current === baselineTemplate
    || current.startsWith('DOC ফাইল থেকে clean text import করা যায়নি।');

  const fallbackBody = shouldReplace
    ? fallbackText
    : `${current}\n\n${fallbackText}`;

  if (itemForms[itemId]?.use_full_page) {
    itemForms[itemId].report_note = plainTextToHtml(fallbackBody);
    await nextTick();
    if (editorRefs[itemId]) {
      editorRefs[itemId].innerHTML = itemForms[itemId].report_note;
      autoGrowEditorField(itemId);
    }
    return;
  }

  itemForms[itemId].report_note = fallbackBody;
  await nextTick();
  autoGrowReportField(itemId);
};

const wrapWithUniversalFont = (html) => `<div style="font-family:${UNIVERSAL_FONT_STACK}; line-height:1.75;">${String(html ?? '')}</div>`;

const enhanceImportedDocxHtml = (html) => {
  const holder = document.createElement('div');
  holder.innerHTML = String(html ?? '');

  holder.querySelectorAll('table').forEach((table) => {
    const current = table.getAttribute('style') || '';
    table.setAttribute('style', `${current}; width:100%; border-collapse:collapse; table-layout:auto; margin:10px 0;`);
  });

  holder.querySelectorAll('th, td').forEach((cell) => {
    const current = cell.getAttribute('style') || '';
    cell.setAttribute('style', `${current}; border:1px solid #cbd5e1; padding:6px 8px; vertical-align:top;`);
  });

  holder.querySelectorAll('img').forEach((img) => {
    const current = img.getAttribute('style') || '';
    img.setAttribute('style', `${current}; max-width:100%; height:auto;`);
  });

  return holder.innerHTML;
};

const decodeBestEffortText = async (file) => {
  const bytes = new Uint8Array(await file.arrayBuffer());
  const tryDecode = (encoding) => {
    try {
      return new TextDecoder(encoding, { fatal: false }).decode(bytes);
    } catch {
      return '';
    }
  };

  const scoreText = (text) => {
    const value = String(text ?? '');
    if (!value) return -1;

    const bangla = (value.match(/[\u0980-\u09FF]/g) ?? []).length;
    const latin = (value.match(/[A-Za-z]/g) ?? []).length;
    const printable = (value.match(/[\x20-\x7E\u00A0-\u024F\u0980-\u09FF\n\r\t]/g) ?? []).length;
    const control = (value.match(/[\x00-\x08\x0B\x0C\x0E-\x1F]/g) ?? []).length;

    return (bangla * 4) + (latin * 1.5) + printable - (control * 5);
  };

  const candidates = [
    tryDecode('utf-8'),
    tryDecode('utf-16le'),
    tryDecode('utf-16be'),
    tryDecode('windows-1252'),
    tryDecode('iso-8859-1'),
  ];

  let best = candidates[0] ?? '';
  let bestScore = scoreText(best);

  candidates.forEach((candidate) => {
    const score = scoreText(candidate);
    if (score > bestScore) {
      best = candidate;
      bestScore = score;
    }
  });

  return normalizeUnicodeText(best);
};

const isZipBasedOfficeFile = async (file) => {
  const bytes = new Uint8Array(await file.slice(0, 4).arrayBuffer());
  return bytes.length === 4
    && bytes[0] === 0x50
    && bytes[1] === 0x4b
    && bytes[2] === 0x03
    && bytes[3] === 0x04;
};

const extractTextFromTxtOrHtml = async (file) => {
  const ext = file.name.toLowerCase().split('.').pop();
  const raw = await decodeBestEffortText(file);

  if (ext === 'html' || ext === 'htm') {
    const parser = new DOMParser();
    const doc = parser.parseFromString(raw, 'text/html');
    const text = doc?.body?.innerText ?? raw;
    return wrapWithUniversalFont(plainTextToHtml(normalizeUnicodeText(text)));
  }

  return wrapWithUniversalFont(plainTextToHtml(raw));
};

const extractTextFromDocx = async (file) => {
  const mammoth = await import('mammoth/mammoth.browser');
  const arrayBuffer = await file.arrayBuffer();
  const htmlResult = await mammoth.convertToHtml({
    arrayBuffer,
    includeDefaultStyleMap: true,
  });
  const html = String(htmlResult?.value ?? '').trim();

  if (html) {
    return wrapWithUniversalFont(enhanceImportedDocxHtml(html));
  }

  const textResult = await mammoth.extractRawText({ arrayBuffer });
  return wrapWithUniversalFont(plainTextToHtml(normalizeUnicodeText(textResult?.value ?? '')));
};

const extractTextFromDoc = async (file) => {
  const normalized = await decodeBestEffortText(file);
  const lower = normalized.toLowerCase();

  // Many legacy .doc files are actually RTF containers.
  if (lower.includes('{\\rtf')) {
    const decodeCp1252Hex = (hex) => {
      try {
        const byte = parseInt(hex, 16);
        return new TextDecoder('windows-1252').decode(new Uint8Array([byte]));
      } catch {
        return '';
      }
    };

    const rtfToText = (rtf) => {
      return String(rtf ?? '')
        .replace(/\\\r\n|\r\n|\r/g, '\n')
        .replace(/\\par[d]?\b ?/gi, '\n')
        .replace(/\\line\b ?/gi, '\n')
        .replace(/\\tab\b ?/gi, '\t')
        .replace(/\\u(-?\d+)\??/g, (_, num) => {
          let code = Number(num);
          if (!Number.isFinite(code)) return '';
          if (code < 0) code += 65536;
          try {
            return String.fromCharCode(code);
          } catch {
            return '';
          }
        })
        .replace(/\\'([0-9a-fA-F]{2})/g, (_, hex) => decodeCp1252Hex(hex))
        .replace(/\\[a-zA-Z]+-?\d* ?/g, '')
        .replace(/[{}]/g, ' ');
    };

    const rtfText = normalizeUnicodeText(rtfToText(normalized));
    return wrapWithUniversalFont(plainTextToHtml(rtfText));
  }

  return wrapWithUniversalFont(plainTextToHtml(normalized));
};

const extractTextFromPdf = async (file) => {
  const pdfjs = await import('pdfjs-dist/legacy/build/pdf.mjs');
  const data = await file.arrayBuffer();
  const loadingTask = pdfjs.getDocument({ data, disableWorker: true });
  const pdf = await loadingTask.promise;

  let combined = '';
  for (let i = 1; i <= pdf.numPages; i += 1) {
    const page = await pdf.getPage(i);
    const content = await page.getTextContent();
    const line = content.items.map((item) => item.str).join(' ');
    combined += `${line}\n\n`;
  }

  return wrapWithUniversalFont(plainTextToHtml(normalizeUnicodeText(combined.trim())));
};

const autoFillResultFromFile = async (file, itemId) => {
  const ext = file.name.toLowerCase().split('.').pop();
  const supported = ['txt', 'md', 'html', 'htm', 'doc', 'docx', 'pdf'];

  if (!supported.includes(ext)) {
    warningMessage('Auto fill supports TXT/MD/HTML/DOC/DOCX/PDF. Selected file will still upload normally.');
    return false;
  }

  try {
    let extracted = '';

    if (['txt', 'md', 'html', 'htm'].includes(ext)) {
      extracted = await extractTextFromTxtOrHtml(file);
    } else if (ext === 'doc') {
      const zipLike = await isZipBasedOfficeFile(file);
      if (zipLike) {
        extracted = await extractTextFromDocx(file);
      } else {
        // Best-effort parse for legacy .doc; if it fails, caller will show filename fallback.
        extracted = await extractTextFromDoc(file);
      }
    } else if (ext === 'docx') {
      extracted = await extractTextFromDocx(file);
    } else if (ext === 'pdf') {
      extracted = await extractTextFromPdf(file);
    }

    if (!String(extracted).trim()) {
      if (ext === 'doc') {
        await setDocImportFallbackText(itemId, file.name);
      }
      return false;
    }

    const extractedPlain = htmlToPlainText(extracted);
    if (!extractedPlain) {
      return false;
    }

    // Legacy DOC often yields binary/garbled output in browser-side decoding.
    // In that case, skip autofill and let filename fallback handle visibility.
    if (ext === 'doc' && isLikelyCorruptedExtraction(extractedPlain)) {
      warningMessage('DOC ফাইলের টেক্সট unreadable ছিল, তাই auto-fill করা হয়নি। DOCX ব্যবহার করুন।');
      await setDocImportFallbackText(itemId, file.name);
      return false;
    }

    if (itemForms[itemId].use_full_page) {
      itemForms[itemId].report_note = extracted;
      if (editorRefs[itemId]) {
        editorRefs[itemId].innerHTML = extracted;
        autoGrowEditorField(itemId);
      }
    } else {
      // Keep plain mode content clean from HTML tags.
      const temp = document.createElement('div');
      temp.innerHTML = extracted;
      itemForms[itemId].report_note = temp.textContent ?? temp.innerText ?? '';
      await nextTick();
      autoGrowReportField(itemId);
    }

    return true;
  } catch (err) {
    console.error(err);
    return false;
  }
};

const isUploadableReportFile = (file) => {
  const ext = file.name.toLowerCase().split('.').pop();
  const uploadableExt = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp'];
  return uploadableExt.includes(ext);
};

const handleFileChange = async (event, itemId) => {
  const file = event.target.files?.[0] ?? null;
  if (!file) {
    itemForms[itemId].report_file = null;
    clearSelectedFilePreview(itemId);
    await deactivateFullPageEditor(itemId);
    return;
  }

  const ext = file.name.toLowerCase().split('.').pop();

  itemForms[itemId].report_file = isUploadableReportFile(file) ? file : null;

  clearSelectedFilePreview(itemId);
  if (itemForms[itemId].report_file) {
    fileUiState[itemId].selectedName = file.name;
    fileUiState[itemId].previewUrl = URL.createObjectURL(file);
    fileUiState[itemId].docHint = ext === 'doc';
    await activateFullPageEditor(itemId);
  }

  await autoFillResultFromFile(file, itemId);
};

const importSavedFileToResult = async (itemId) => {
  if (fileUiState[itemId]?.importingStored) return;

  fileUiState[itemId].importingStored = true;
  try {
    const response = await fetch(route('backend.reporting.item.import-text', itemId), {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    });

    const payload = await response.json().catch(() => ({}));
    if (!response.ok || !payload?.ok) {
      warningMessage(payload?.message || 'Saved file text import failed.');
      return;
    }

    const text = String(payload?.text ?? '').trim();
    if (!text) {
      warningMessage('Saved file থেকে readable text পাওয়া যায়নি।');
      return;
    }

    await activateFullPageEditor(itemId);
    itemForms[itemId].report_note = plainTextToHtml(text);
    await nextTick();
    if (editorRefs[itemId]) {
      editorRefs[itemId].innerHTML = itemForms[itemId].report_note;
      autoGrowEditorField(itemId);
    }

    const source = String(payload?.source ?? '').trim();
    if (source) {
      warningMessage(`Result box updated from saved file (${source}).`);
    }
  } catch (error) {
    console.error(error);
    errorMessage('Saved file import request failed.');
  } finally {
    fileUiState[itemId].importingStored = false;
  }
};

const getUnitFromRange = (range) => {
  const value = String(range ?? '').trim();
  if (!value) return '';

  const isSkippableUnit = (unitValue) => {
    const normalized = String(unitValue ?? '').trim().toLowerCase();
    return ['n/a', 'na', 'none', 'nil', '-', '--'].includes(normalized);
  };

  const parts = value.split(/\s+/);
  for (let i = parts.length - 1; i >= 0; i -= 1) {
    if (/[a-zA-Z%/]/.test(parts[i])) {
      return isSkippableUnit(parts[i]) ? '' : parts[i];
    }
  }

  return '';
};

const normalizeReportNote = (note, unit) => {
  const trimmed = String(note ?? '').trim();
  const normalizedUnit = String(unit ?? '').trim().toLowerCase();
  if (!unit || ['n/a', 'na', 'none', 'nil', '-', '--'].includes(normalizedUnit)) return trimmed;
  if (!trimmed) return unit;

  // Do not append unit to narrative/multiline report blocks.
  if (trimmed.includes('\n') || /\b(findings|impression|examination|parameters|comment)\b/i.test(trimmed)) {
    return trimmed;
  }

  const lowerNote = trimmed.toLowerCase();
  const lowerUnit = unit.toLowerCase();
  if (lowerNote.endsWith(lowerUnit)) {
    return trimmed;
  }

  return `${trimmed} ${unit}`;
};

const getReportNotePlaceholder = (itemId) => {
  const unit = getUnitFromRange(itemForms[itemId]?.report_range);
  return unit ? `Enter result (${unit})` : 'Enter result';
};

const getParameterNoteFor = (itemId) => {
  const item = (items.value || []).find((i) => i.id === itemId) || null;
  if (!item || !Array.isArray(item.parameters) || item.parameters.length === 0) return null;

  const values = itemForms[itemId]?.parameter_values || {};
  const lines = [];
  item.parameters.forEach((p) => {
    const raw = String(values[p.id] ?? '').trim();
    if (raw !== '') {
      const unit = String(p.unit ?? '').trim();
      lines.push(`${p.name}: ${raw}${unit ? ' ' + unit : ''}`);
    }
  });

  return lines.length ? lines.join('\n') : null;
};

const parseParameterNumber = (value) => {
  if (value === null || value === undefined) return null;

  const cleaned = String(value).trim().replace(/,/g, '');
  if (!cleaned) return null;

  const numeric = Number(cleaned.replace(/[^0-9.\-]/g, ''));
  return Number.isFinite(numeric) ? numeric : null;
};

const getParameterByName = (item, name) => {
  if (!item) return null;

  const allParams = Array.isArray(item.parameter_groups) && item.parameter_groups.length
    ? item.parameter_groups.flatMap((group) => group.parameters || [])
    : (item.parameters || []);
  const normalize = (value) => String(value ?? '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '');
  const aliases = {
    atypicalcell: 'blastatypical',
    blastatypicalcell: 'blastatypical',
    blastatypicalpercent: 'blastatypical',
    blastyatypical: 'blastatypical',
    blastyatypicalcell: 'blastatypical',
    blastyatypicalpercent: 'blastatypical',
    myelocytespercent: 'myelocytes',
  };
  const normalizedName = normalize(name);
  const canonicalName = aliases[normalizedName] ?? normalizedName;

  return allParams.find((param) => {
    const normalizedParamName = normalize(param.name);
    return normalizedParamName === normalizedName
      || (aliases[normalizedParamName] ?? normalizedParamName) === canonicalName;
  }) || null;
};

const setParameterValueByName = (itemId, name, value) => {
  const item = (items.value || []).find((i) => i.id === itemId) || null;
  if (!item || !itemForms[itemId]) return;

  const param = getParameterByName(item, name);
  if (!param) return;

  itemForms[itemId].parameter_values[param.id] = value;
};

const applyCbcDifferentialRules = (itemId) => {
  const item = (items.value || []).find((i) => i.id === itemId) || null;
  if (!item || !shouldShowParameterSection(item)) return;

  const diffNames = [
    'Neutrophils',
    'Lymphocytes',
    'Monocytes',
    'Eosinophils',
    'Basophils',
    'Blast/Atypical %',
    'Myelocytes %',
  ];
  const diffValues = diffNames.map((name) => ({
    name,
    param: getParameterByName(item, name),
  }));

  const otherDifferentialValues = diffValues
    .filter((entry) => entry.name !== 'Basophils')
    .map((entry) => parseParameterNumber(itemForms[itemId]?.parameter_values?.[entry.param?.id ?? ''] ?? ''));
  if (otherDifferentialValues.length === 6 && otherDifferentialValues.every((value) => value !== null)) {
    const otherSum = otherDifferentialValues.reduce((sum, value) => sum + value, 0);
    const computedBasophils = Number((100 - otherSum).toFixed(2));
    if (Number.isFinite(computedBasophils) && computedBasophils >= 0) {
      const basophilsParam = getParameterByName(item, 'Basophils');
      if (basophilsParam) {
        itemForms[itemId].parameter_values[basophilsParam.id] = String(computedBasophils);
      }
    }
  }

  const wbcParam = getParameterByName(item, 'Total WBC Count');
  const eosParam = getParameterByName(item, 'Eosinophils');
  const totalEosinophilsParam = getParameterByName(item, 'Total Cir. Eosinophils');

  if (wbcParam && eosParam && totalEosinophilsParam) {
    const wbcCount = parseParameterNumber(itemForms[itemId]?.parameter_values?.[wbcParam.id] ?? '');
    const eosinophilsPercent = parseParameterNumber(itemForms[itemId]?.parameter_values?.[eosParam.id] ?? '');

    if (wbcCount !== null && eosinophilsPercent !== null) {
      const calculatedValue = Number(((wbcCount * eosinophilsPercent) / 100).toFixed(2));
      itemForms[itemId].parameter_values[totalEosinophilsParam.id] = String(calculatedValue);
    }
  }
};

const getCbcDifferentialSummary = (itemId, item) => {
  const names = [
    'Neutrophils',
    'Lymphocytes',
    'Monocytes',
    'Eosinophils',
    'Basophils',
    'Blast/Atypical %',
    'Myelocytes %',
  ];
  const values = names.map((name) => {
    const param = getParameterByName(item, name);
    return param ? parseParameterNumber(itemForms[itemId]?.parameter_values?.[param.id] ?? '') : null;
  });
  const total = values.reduce((sum, value) => sum + (value ?? 0), 0);
  const complete = values.every((value) => value !== null);

  return {
    total: Number(total.toFixed(2)),
    complete,
    isBalanced: complete && Math.abs(total - 100) < 0.01,
  };
};

const handleCbcParameterInput = (itemId, paramName, event) => {
  const item = (items.value || []).find((i) => i.id === itemId) || null;
  if (!item || !itemForms[itemId]) return;

  const target = getParameterByName(item, paramName);
  if (!target) return;

  itemForms[itemId].parameter_values[target.id] = event?.target?.value ?? '';
  applyCbcDifferentialRules(itemId);
};

items.value.forEach((item) => {
  if (shouldShowParameterSection(item)) {
    applyCbcDifferentialRules(item.id);
  }
});

const submitItem = (itemId) => {
  if (!itemForms[itemId]) {
    console.warn('[Reporting] submitItem called for uninitialized itemId', itemId);
    errorMessage('Report form uninitialized — পেজ রিফ্রেশ করে চেষ্টা করুন।');
    return;
  }

  const useFullPage = !!itemForms[itemId].use_full_page;

  if (useFullPage) {
    itemForms[itemId].report_note = withFullPageMarker(itemForms[itemId].report_note);
  } else {
    // If parameter inputs exist for this item, prefer them as the report_note
    const parameterNote = getParameterNoteFor(itemId);
    if (parameterNote) {
      itemForms[itemId].report_note = parameterNote;
    }
    const unit = getUnitFromRange(itemForms[itemId].report_range);
    itemForms[itemId].report_note = normalizeReportNote(itemForms[itemId].report_note, unit);
  }

  (async () => {
    itemForms[itemId].processing = true;
    itemForms[itemId].errors = {};

    const fd = new FormData();
    const item = (items.value || []).find((entry) => String(entry.id) === String(itemId));
    fd.append(
      'report_note',
      getReportNoteWithHistogramPreference(itemId, item, itemForms[itemId].report_note || '')
    );
    fd.append('report_range', itemForms[itemId].report_range || '');
    fd.append('department', itemForms[itemId].department || props.department || '');

    const paramVals = itemForms[itemId].parameter_values || {};
    Object.keys(paramVals).forEach((k) => {
      fd.append(`parameter_values[${k}]`, paramVals[k] ?? '');
    });

    const selectedFile = itemForms[itemId].report_file;
    if (selectedFile instanceof File) {
      fd.append('report_file', selectedFile);
    }

    try {
      const res = await postReportItem(itemId, fd);
      if (!res) return;

      const payload = await res.json().catch(() => null);

      if (!res.ok) {
        if (res.status === 422 && payload && payload.errors) {
          itemForms[itemId].errors = payload.errors;
          warningMessage('Validation failed. ঠিক করে চেক করুন।');
        } else if (res.status === 419) {
          errorMessage('সেশন যাচাই করা যায়নি। রিপোর্টের লেখা কপি করে আবার লগইন করুন।');
        } else {
          errorMessage(payload?.message || 'Save failed.');
        }
        return;
      }

      // Success: show toast and update UI
      try {
        if (payload?.successMessage) {
          successMessage(payload.successMessage);
        } else {
          showToastIfNoFlash({ props: { flash: { successMessage: 'Report saved successfully.' } } });
        }
      } catch (e) {
        // ignore
      }

      if (selectedFile instanceof File) {
        try {
          fileUiState[itemId].savedName = selectedFile.name;
          fileUiState[itemId].savedUrl = fileUiState[itemId].previewUrl || fileUiState[itemId].savedUrl;
        } catch (_) {}
      }

      itemForms[itemId].report_note = stripFullPageMarker(itemForms[itemId].report_note);

      if (!hiddenItemIds.value.includes(itemId)) hiddenItemIds.value.push(itemId);

      try { delete itemForms[itemId]; } catch (_) {}
      try { delete fileUiState[itemId]; } catch (_) {}
      try { delete editorUiState[itemId]; } catch (_) {}
      try { delete editorRefs[itemId]; } catch (_) {}
    } catch (err) {
      console.error(err);
      errorMessage('Save request failed.');
    } finally {
      if (itemForms[itemId]) itemForms[itemId].processing = false;
    }
  })();
};

const formatSampleDateTime = (value) => {
  if (!value) return 'N/A';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);

  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Asia/Dhaka',
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: true,
  }).formatToParts(date);

  const getPart = (type) => parts.find((part) => part.type === type)?.value ?? '';

  const day = getPart('day');
  const month = getPart('month');
  const year = getPart('year');
  const hour = getPart('hour');
  const minute = getPart('minute');
  const second = getPart('second');
  const dayPeriod = getPart('dayPeriod');

  return `${day}-${month}-${year} ${hour}:${minute}:${second} ${dayPeriod}`;
};

const backRoute = computed(() => {
  const dept = String(props.department || '').trim();
  if (dept === 'ultrasound') return route('backend.reporting.ultrasound');
  if (dept === 'xray') return route('backend.reporting.xray');
  if (dept === 'pathology') return route('backend.reporting.pathology');
  return route('backend.reporting.index');
});

/**
 * Open a print window for the given bill item ID.
 * The print page handles its own single auto-print after loading.
 */
const openPrintWindow = (itemId) => {
  try {
    const url = new URL(route('backend.reporting.print', itemId), window.location.href);
    url.searchParams.set('single_item', '1');
    const printWindow = window.open(url, '_blank');
    if (printWindow) printWindow.opener = null;
  } catch (e) {
    console.warn('Print failed', e);
  }
};

/**
 * Save a single item's report data without hiding it from the form,
 * then open the print window. Used by the per-item Print button.
 */
const saveAndPrintItem = async (itemId) => {
  if (!itemForms[itemId]) {
    // Item already saved – print directly
    openPrintWindow(itemId);
    return;
  }

  const useFullPage = !!itemForms[itemId].use_full_page;
  let reportNote = itemForms[itemId].report_note || '';

  if (useFullPage) {
    reportNote = withFullPageMarker(reportNote);
  } else {
    const parameterNote = getParameterNoteFor(itemId);
    if (parameterNote) reportNote = parameterNote;
    const unit = getUnitFromRange(itemForms[itemId].report_range);
    reportNote = normalizeReportNote(reportNote, unit);
  }

  itemForms[itemId].processing = true;

  try {
    const fd = new FormData();
    const item = (items.value || []).find((entry) => String(entry.id) === String(itemId));
    fd.append('report_note', getReportNoteWithHistogramPreference(itemId, item, reportNote));
    fd.append('report_range', itemForms[itemId].report_range || '');
    fd.append('department', itemForms[itemId].department || props.department || '');

    const paramVals = itemForms[itemId].parameter_values || {};
    Object.keys(paramVals).forEach((k) => {
      fd.append(`parameter_values[${k}]`, paramVals[k] ?? '');
    });

    const selectedFile = itemForms[itemId].report_file;
    if (selectedFile instanceof File) fd.append('report_file', selectedFile);

    const res = await postReportItem(itemId, fd);

    if (!res?.ok) {
      const payload = await res?.json().catch(() => null);
      if (res?.status === 422) {
        itemForms[itemId].errors = payload?.errors || {};
        warningMessage(payload?.message || 'রিপোর্ট প্রিন্টের আগে রেজাল্ট সেভ করুন।');
      } else if (res?.status === 419) {
        errorMessage('সেশন যাচাই করা যায়নি। রিপোর্টের লেখা কপি করে আবার লগইন করুন।');
      } else if (res) {
        errorMessage(payload?.message || 'রিপোর্ট সেভ করা যায়নি, তাই প্রিন্ট করা হয়নি।');
      }
      return;
    }

    try { successMessage('রিপোর্ট সেভ হয়েছে। প্রিন্ট হচ্ছে...'); } catch (_) {}
    if (!hiddenItemIds.value.some((id) => String(id) === String(itemId))) {
      hiddenItemIds.value.push(itemId);
    }
    delete itemForms[itemId];
    delete fileUiState[itemId];
    delete editorUiState[itemId];
    delete editorRefs[itemId];
  } catch (err) {
    console.warn('Auto-save before print failed', err);
    errorMessage('রিপোর্ট সেভ করা যায়নি, তাই প্রিন্ট করা হয়নি।');
    return;
  } finally {
    if (itemForms[itemId]) itemForms[itemId].processing = false;
  }

  openPrintWindow(itemId);
};

/**
 * Print only a report item that has already been saved.
 */
const printPage = () => {
  const allItems = props.billItems ?? [];
  const savedItems = allItems.filter((item) => (
    item.reported_at || hiddenItemIds.value.some((id) => String(id) === String(item.id))
  ));

  if (!savedItems.length) {
    warningMessage('প্রিন্ট করার আগে অন্তত একটি আইটেমের রেজাল্ট সেভ করুন।');
    return;
  }

  savedItems.forEach((item) => openPrintWindow(item.id));
};

</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <div>
          <h1 class="text-lg font-semibold text-gray-800">{{ pageTitle }}</h1>
          <p class="text-sm text-gray-600">Bill No: {{ billing.bill_number ?? 'N/A' }}</p>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" @click="printPage" class="text-sm px-3 py-1 bg-white border border-slate-300 rounded text-slate-700 hover:bg-slate-50">Print</button>
          <Link :href="backRoute" class="text-sm text-blue-600 hover:underline">Back</Link>
        </div>
      </div>

      <div class="mt-4 space-y-4">
          <div v-for="item in items" :key="item.id" class="p-4 border rounded">
            <div v-if="shouldRenderLargeResultBox(item.id)" class="mb-3">
              <div class="mb-2">
                <div class="text-sm font-semibold text-gray-800">{{ item.item_name }}</div>
                <div class="text-xs text-gray-500">{{ item.category }} | Sample: {{ formatSampleDateTime(item.sample_collected_at) }}</div>
              </div>
              <div class="flex flex-wrap items-center gap-2 mb-2">
                <div class="flex items-center gap-2 ml-auto">
                  <button type="button" class="px-2 py-1 text-xs border rounded" @click="deactivateFullPageEditor(item.id)" :disabled="!itemForms[item.id]?.use_full_page">প্যারামিটার</button>
                  <button type="button" class="px-2 py-1 text-xs border rounded" @click="activateFullPageEditor(item.id)" :disabled="itemForms[item.id]?.use_full_page">ফুল পেজ</button>
                </div>
                <select class="px-2 py-1 text-xs border rounded" @change="(e) => setEditorBlockFormat(item.id, e.target.value)">
                  <option value="P">Paragraph</option>
                  <option value="H1">Heading 1</option>
                  <option value="H2">Heading 2</option>
                  <option value="H3">Heading 3</option>
                  <option value="BLOCKQUOTE">Quote</option>
                </select>
                <button type="button" class="px-2 py-1 text-xs border rounded hover:bg-gray-50" @click="decreaseEditorFontSize(item.id)">A-</button>
                <button type="button" class="px-2 py-1 text-xs border rounded hover:bg-gray-50" @click="increaseEditorFontSize(item.id)">A+</button>
                <button type="button" class="px-2 py-1 text-xs font-semibold border rounded hover:bg-gray-50" @click="applyEditorCommand(item.id, 'bold')">B</button>
                <button type="button" class="px-2 py-1 text-xs italic border rounded hover:bg-gray-50" @click="applyEditorCommand(item.id, 'italic')">I</button>
                <button type="button" class="px-2 py-1 text-xs underline border rounded hover:bg-gray-50" @click="applyEditorCommand(item.id, 'underline')">U</button>
                <button type="button" class="px-2 py-1 text-xs line-through border rounded hover:bg-gray-50" @click="applyEditorCommand(item.id, 'strikeThrough')">S</button>
                <button type="button" class="px-2 py-1 text-xs border rounded hover:bg-gray-50" @click="applyEditorCommand(item.id, 'insertUnorderedList')">• List</button>
                <button type="button" class="px-2 py-1 text-xs border rounded hover:bg-gray-50" @click="applyEditorCommand(item.id, 'insertOrderedList')">1. List</button>
                <button type="button" class="px-2 py-1 text-xs border rounded hover:bg-gray-50" @click="applyEditorCommand(item.id, 'outdent')">Outdent</button>
                <button type="button" class="px-2 py-1 text-xs border rounded hover:bg-gray-50" @click="applyEditorCommand(item.id, 'indent')">Indent</button>
                <button type="button" class="px-2 py-1 text-xs border rounded hover:bg-gray-50" @click="applyEditorCommand(item.id, 'justifyLeft')">Left</button>
                <button type="button" class="px-2 py-1 text-xs border rounded hover:bg-gray-50" @click="applyEditorCommand(item.id, 'justifyCenter')">Center</button>
                <button type="button" class="px-2 py-1 text-xs border rounded hover:bg-gray-50" @click="applyEditorCommand(item.id, 'justifyRight')">Right</button>
                <label class="flex items-center gap-1 px-2 py-1 text-xs border rounded">
                  Text
                  <input type="color" value="#111827" @input="(e) => setEditorTextColor(item.id, e.target.value)" />
                </label>
                <label class="flex items-center gap-1 px-2 py-1 text-xs border rounded">
                  Highlight
                  <input type="color" value="#fff59d" @input="(e) => setEditorHighlightColor(item.id, e.target.value)" />
                </label>
                <select
                  class="px-2 py-1 text-xs border rounded"
                  :value="editorUiState[item.id]?.lineHeight"
                  @change="(e) => setEditorLineHeight(item.id, e.target.value)"
                >
                  <option value="1.4">Line 1.4</option>
                  <option value="1.6">Line 1.6</option>
                  <option value="1.75">Line 1.75</option>
                  <option value="2">Line 2.0</option>
                </select>
                <button type="button" class="px-2 py-1 text-xs border rounded hover:bg-gray-50" @click="insertEditorLink(item.id)">Link</button>
                <button type="button" class="px-2 py-1 text-xs border rounded hover:bg-gray-50" @click="applyEditorCommand(item.id, 'unlink')">Unlink</button>
                <button type="button" class="px-2 py-1 text-xs border rounded hover:bg-gray-50" @click="insertTable(item.id)">Table</button>
                <button type="button" class="px-2 py-1 text-xs border rounded hover:bg-gray-50" @click="addTableRowNearSelection(item.id, 'before')">Row +Top</button>
                <button type="button" class="px-2 py-1 text-xs border rounded hover:bg-gray-50" @click="addTableRowNearSelection(item.id, 'after')">Row +Bottom</button>
                <button type="button" class="px-2 py-1 text-xs border rounded hover:bg-gray-50" @click="deleteTableRowAtSelection(item.id)">Row -</button>
                <button type="button" class="px-2 py-1 text-xs border rounded hover:bg-gray-50" @click="addTableColumnNearSelection(item.id, 'before')">Col +Left</button>
                <button type="button" class="px-2 py-1 text-xs border rounded hover:bg-gray-50" @click="addTableColumnNearSelection(item.id, 'after')">Col +Right</button>
                <button type="button" class="px-2 py-1 text-xs border rounded hover:bg-gray-50" @click="deleteTableColumnAtSelection(item.id)">Col -</button>
                <button type="button" class="px-2 py-1 text-xs border rounded hover:bg-gray-50" @click="mergeCellWithRight(item.id)">Merge →</button>
                <button type="button" class="px-2 py-1 text-xs border rounded hover:bg-gray-50" @click="splitSelectedCell(item.id)">Split</button>
                <label class="flex items-center gap-1 px-2 py-1 text-xs border rounded">
                  Border
                  <input
                    type="color"
                    :value="editorUiState[item.id]?.tableBorderColor"
                    @input="(e) => { editorUiState[item.id].tableBorderColor = e.target.value; applyTableBorderStyle(item.id); }"
                  />
                </label>
                <select
                  class="px-2 py-1 text-xs border rounded"
                  :value="editorUiState[item.id]?.tableBorderWidth"
                  @change="(e) => { editorUiState[item.id].tableBorderWidth = e.target.value; applyTableBorderStyle(item.id); }"
                >
                  <option value="1">Border 1px</option>
                  <option value="2">Border 2px</option>
                  <option value="3">Border 3px</option>
                </select>
                <button type="button" class="px-2 py-1 text-xs border rounded hover:bg-gray-50" @click="removeEditorFormat(item.id)">Clear</button>
                <button type="button" class="px-2 py-1 text-xs border rounded hover:bg-gray-50" @click="applyEditorCommand(item.id, 'undo')">Undo</button>
                <button type="button" class="px-2 py-1 text-xs border rounded hover:bg-gray-50" @click="applyEditorCommand(item.id, 'redo')">Redo</button>
              </div>
              <InputLabel :for="`note_top_${item.id}`" value="Result" />
              <div
                :id="`note_top_${item.id}`"
                :ref="(el) => setEditorRef(el, item.id)"
                contenteditable="true"
                class="report-editable-field block w-full min-h-[46px] p-3 text-sm leading-7 rounded-md shadow-sm border-slate-300 focus:border-indigo-300 overflow-hidden"
                :style="`font-family: ${UNIVERSAL_FONT_STACK}; line-height: ${editorUiState[item.id]?.lineHeight || '1.75'};`"
                @input="(e) => onEditorInput(e, item.id)"
                @click="(e) => e.currentTarget?.focus()"
              ></div>
              <InputError class="mt-1" :message="itemForms[item.id].errors.report_note" />
            </div>

            <div :class="shouldRenderLargeResultBox(item.id) ? 'grid grid-cols-1 gap-3 lg:grid-cols-2' : 'grid grid-cols-1 gap-3 lg:grid-cols-3'">
              <div v-if="!shouldRenderLargeResultBox(item.id)">
                <div class="text-sm font-medium text-gray-800">{{ item.item_name }}</div>
                <div v-if="item.report_function" class="text-xs font-semibold text-indigo-700">{{ item.report_function }}</div>
                <div class="text-xs text-gray-500">{{ item.category }} | Sample: {{ formatSampleDateTime(item.sample_collected_at) }}</div>
              </div>

                <div v-if="!shouldRenderLargeResultBox(item.id)">
                <InputLabel v-if="department !== 'ultrasound' && department !== 'xray'" :for="`range_top_${item.id}`" value="Normal Range" />
                <input
                  v-if="department !== 'ultrasound' && department !== 'xray'"
                  :id="`range_top_${item.id}`"
                  v-model="itemForms[item.id].report_range"
                  type="text"
                  class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300"
                  placeholder="Enter normal range"
                />
                <InputError v-if="department !== 'ultrasound' && department !== 'xray'" class="mt-1" :message="itemForms[item.id].errors.report_range" />
              </div>

              <div class="rounded-md border border-slate-200 bg-slate-50 p-3 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                  <InputLabel :for="`file_top_${item.id}`" value="Report File" />
                  <div v-if="item.report_file_url" class="flex items-center gap-2">
                    <button
                      type="button"
                      class="text-xs text-emerald-700 hover:underline disabled:opacity-60"
                      :disabled="fileUiState[item.id]?.importingStored"
                      @click="importSavedFileToResult(item.id)"
                    >
                      {{ fileUiState[item.id]?.importingStored ? 'Importing...' : 'Open to Result' }}
                    </button>
                    <a
                      :href="item.report_file_url"
                      target="_blank"
                      rel="noopener"
                      class="text-xs text-blue-600 hover:underline"
                    >
                      View File
                    </a>
                  </div>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                  <label
                    :for="`file_top_${item.id}`"
                    class="inline-flex cursor-pointer items-center rounded-md bg-slate-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800"
                  >
                    Choose File
                  </label>
                  <span v-if="!fileUiState[item.id]?.selectedName && !fileUiState[item.id]?.savedName" class="text-sm text-slate-500">
                    No file chosen
                  </span>
                  <span v-if="fileUiState[item.id]?.selectedName" class="text-sm font-medium text-green-700">
                    Selected: {{ fileUiState[item.id].selectedName }}
                  </span>
                  <a
                    v-if="fileUiState[item.id]?.previewUrl"
                    :href="fileUiState[item.id].previewUrl"
                    target="_blank"
                    rel="noopener"
                    class="ml-2 text-blue-600 hover:underline"
                  >
                    Open
                  </a>
                </div>
                <input
                  :id="`file_top_${item.id}`"
                  type="file"
                  accept=".txt,.md,.html,.htm,.pdf,.doc,.docx,image/*"
                  class="sr-only"
                  @change="(e) => handleFileChange(e, item.id)"
                />
                <p v-if="fileUiState[item.id]?.docHint" class="mt-1 text-xs text-amber-700">
                  DOC detected। যদি clean import না হয়, নিচের Result বক্সে editable fallback টেক্সট দেয়া হবে।
                </p>
                <InputError class="mt-1" :message="itemForms[item.id].errors.report_file" />
              </div>
            </div>

            <div v-if="!shouldRenderLargeResultBox(item.id)" class="grid grid-cols-1 gap-4 mt-3 sm:grid-cols-2 lg:grid-cols-3">
              <div class="lg:col-span-3">
                <div v-if="shouldShowParameterSection(item)" class="mb-3">
                  <div class="text-sm font-semibold mb-1">Parameters</div>

                  <!-- Grouped parameter view (preferred) -->
                  <div v-if="item.parameter_groups && item.parameter_groups.length" class="space-y-4">
                    <div v-for="group in item.parameter_groups.filter((group) => group.title !== 'Blood Film' && (group.parameters || []).length)" :key="group.title">
                      <div class="w-full overflow-auto">
                        <table class="param-table w-full" style="border-collapse: collapse;">
                          <colgroup>
                            <col style="width:70%" />
                            <col style="width:30%" />
                          </colgroup>
                          <thead>
                            <tr>
                              <th colspan="2" class="param-table-header">{{ group.title }}</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="param in (group.parameters || [])" :key="param.id">
                              <td class="param-name-cell">
                                <div class="text-sm font-medium">{{ getCbcParameterLabel(item, param) }}</div>
                                <div class="text-xs text-gray-500">
                                  <span v-if="param.reference_from || param.reference_to">{{ param.reference_from }}<span v-if="param.reference_from && param.reference_to"> - </span>{{ param.reference_to }}</span>
                                  <span v-if="param.unit"> {{ param.unit }}</span>
                                </div>
                              </td>
                              <td class="param-value-cell">
                                <input
                                  type="text"
                                  v-model="itemForms[item.id].parameter_values[param.id]"
                                  @input="handleCbcParameterInput(item.id, param.name, $event)"
                                  placeholder="Result"
                                  class="w-40 p-2 text-sm border rounded text-center"
                                />
                              </td>
                            </tr>
                            <tr v-if="group.title === 'Differential Count' && isCbcReportItem(item)">
                              <td class="border-t bg-slate-50 px-3 py-2 text-sm font-semibold">Total Differential Count</td>
                              <td
                                class="border-t bg-slate-50 px-3 py-2 text-sm font-semibold"
                                :class="getCbcDifferentialSummary(item.id, item).isBalanced ? 'text-emerald-700' : 'text-amber-700'"
                              >
                                {{ getCbcDifferentialSummary(item.id, item).total }}% / 100%
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>

                  <!-- Fallback flat parameter list -->
                  <div v-else class="grid grid-cols-1 gap-2">
                    <div v-for="param in item.parameters.filter((parameter) => parameter.name !== 'Blood Film')" :key="param.id" class="flex items-center gap-2">
                      <div class="flex-1">
                        <div class="text-xs font-medium">{{ getCbcParameterLabel(item, param) }}</div>
                        <div class="text-xs text-gray-500">
                          <span v-if="param.reference_from || param.reference_to">{{ param.reference_from }}<span v-if="param.reference_from && param.reference_to"> - </span>{{ param.reference_to }}</span>
                          <span v-if="param.unit"> {{ param.unit }}</span>
                        </div>
                      </div>
                      <input
                        type="text"
                        v-model="itemForms[item.id].parameter_values[param.id]"
                        @input="handleCbcParameterInput(item.id, param.name, $event)"
                        placeholder="Result"
                        class="w-36 p-2 text-sm border rounded"
                      />
                    </div>
                  </div>
                  <div v-if="getBloodFilmParameter(item)" class="mt-4">
                    <InputLabel :for="`blood_film_${item.id}`" value="Blood Film" />
                    <textarea
                      :id="`blood_film_${item.id}`"
                      v-model="itemForms[item.id].parameter_values[getBloodFilmParameter(item).id]"
                      rows="3"
                      placeholder="Enter blood film findings"
                      class="mt-1 block w-full resize-y rounded-md border-slate-300 p-3 text-sm shadow-sm focus:border-indigo-300"
                    ></textarea>
                  </div>
                </div>
                <InputLabel :for="`note_${item.id}`" value="Result" />
                <textarea
                  :id="`note_${item.id}`"
                  :ref="(el) => setReportFieldRef(el, item.id)"
                  :value="itemForms[item.id].report_note"
                  rows="1"
                  class="report-editable-field block w-full min-h-[46px] p-3 text-sm leading-7 rounded-md shadow-sm border-slate-300 focus:border-indigo-300 resize-none overflow-hidden"
                  :style="`font-family: ${UNIVERSAL_FONT_STACK};`"
                  @input="(e) => onPlainReportInput(e, item.id)"
                  :placeholder="getReportNotePlaceholder(item.id)"
                ></textarea>

                <p v-if="department !== 'ultrasound' && department !== 'xray' && getUnitFromRange(itemForms[item.id].report_range)" class="mt-1 text-xs text-gray-500">
                  Unit: {{ getUnitFromRange(itemForms[item.id].report_range) }}
                </p>
                <InputError class="mt-1" :message="itemForms[item.id].errors.report_note" />

              </div>
            </div>

            <label
              v-if="isCbcReportItem(item)"
              class="mt-3 inline-flex items-center gap-2 text-sm text-slate-700"
            >
              <input
                v-model="itemForms[item.id].cbc_show_histogram"
                type="checkbox"
                class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
              />
              <span>Show Histogram (only when analyzer data is available)</span>
            </label>

            <div class="flex items-center justify-end gap-2 mt-4">
              <PrimaryButton
                type="button"
                :class="{ 'opacity-25': itemForms[item.id].processing }"
                :disabled="itemForms[item.id].processing"
                @click="submitItem(item.id)"
              >
                Save Report
              </PrimaryButton>
              <button
                type="button"
                class="px-3 py-1 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700 disabled:opacity-50"
                :disabled="itemForms[item.id]?.processing"
                @click="saveAndPrintItem(item.id)"
              >
                Print
              </button>
            </div>
          </div>
      </div>
    </div>
  </BackendLayout>
</template>

<style scoped>
.report-editable-field {
  background-color: #ffffff !important;
  color: #111827 !important;
  caret-color: #111827 !important;
  cursor: text !important;
  user-select: text !important;
  -webkit-user-select: text !important;
  pointer-events: auto !important;
}

.report-editable-field:focus {
  outline: none;
}

/* Parameter table styles to resemble printed lab format */
.param-table {
  border: 1px solid #e5e7eb;
  border-radius: 4px;
  overflow: hidden;
  font-size: 13px;
}
.param-table thead .param-table-header {
  text-align: center;
  font-weight: 800;
  padding: 8px 12px;
  background: #fff;
  border-bottom: 1px solid #e5e7eb;
}
.param-table td {
  border-bottom: 1px solid #eef2f6;
  padding: 8px 12px;
  vertical-align: middle;
}
.param-name-cell { text-align: left; }
.param-value-cell { text-align: center; width: 30%; }
.param-table tbody tr:last-child td { border-bottom: none; }

</style>
