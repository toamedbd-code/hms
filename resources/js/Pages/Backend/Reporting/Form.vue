<script setup>
import { computed, nextTick, reactive } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { warningMessage, errorMessage } from '@/responseMessage.js';

const props = defineProps({
  billing: Object,
  billItems: Array,
  pageTitle: {
    type: String,
    default: 'Report Entry',
  },
});

const items = computed(() => props.billItems ?? []);

const itemForms = reactive({});
const editorRefs = reactive({});
const reportFieldRefs = reactive({});
const fileUiState = reactive({});
const editorUiState = reactive({});
const FULL_PAGE_MARKER = '[[FULL_PAGE]]';
const UNIVERSAL_FONT_STACK = "'Noto Sans Bengali', 'Hind Siliguri', 'SolaimanLipi', 'Kalpurush', 'Noto Sans', 'Noto Serif Bengali', 'Noto Serif', 'Arial Unicode MS', 'Segoe UI', sans-serif";

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
    return [
      'EXAMINATION: ULTRASONOGRAPHY',
      '',
      'FINDINGS:',
      '- Liver:',
      '- Gall bladder:',
      '- Pancreas:',
      '- Spleen:',
      '- Both kidneys:',
      '- Urinary bladder:',
      '- Uterus/Prostate:',
      '',
      'IMPRESSION:',
      '- ',
    ].join('\n');
  }

  if (text.includes('xray') || text.includes('x-ray') || text.includes('x ray')) {
    return [
      'EXAMINATION: X-RAY',
      '',
      'FINDINGS:',
      '- ',
      '',
      'IMPRESSION:',
      '- ',
    ].join('\n');
  }

  if (text.includes('cbc')) {
    return [
      'TEST: COMPLETE BLOOD COUNT (CBC)',
      '',
      'PARAMETERS:',
      '- Hemoglobin (Hb):',
      '- Total WBC count:',
      '- Neutrophils:',
      '- Lymphocytes:',
      '- Monocytes:',
      '- Eosinophils:',
      '- Basophils:',
      '- Platelet count:',
      '- ESR:',
      '',
      'COMMENT:',
      '- ',
    ].join('\n');
  }

  return '';
};

items.value.forEach((item) => {
  const initialUseFullPage = hasFullPageMarker(item.report_note ?? '')
    || /<[^>]+>/.test(String(item.report_note ?? ''));
  const initialNote = stripFullPageMarker(item.report_note ?? '');

  itemForms[item.id] = useForm({
    report_note: initialUseFullPage && !/<[^>]+>/.test(initialNote)
      ? plainTextToHtml(initialNote)
      : initialNote,
    report_range: resolveInitialRange(item),
    report_file: null,
    // Always use direct editable result box for simpler workflow.
    use_full_page: initialUseFullPage,
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

  if (!String(itemForms[item.id].report_note ?? '').trim()) {
    const narrativeTemplate = resolveNarrativeTemplate(item);
    if (narrativeTemplate) {
      itemForms[item.id].report_note = narrativeTemplate;
    }
  }
});

const shouldRenderLargeResultBox = (itemId) => {
  return Boolean(fileUiState[itemId]?.selectedName) || Boolean(itemForms[itemId]?.use_full_page);
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

const submitItem = (itemId) => {
  const useFullPage = !!itemForms[itemId].use_full_page;

  if (useFullPage) {
    itemForms[itemId].report_note = withFullPageMarker(itemForms[itemId].report_note);
  } else {
    const unit = getUnitFromRange(itemForms[itemId].report_range);
    itemForms[itemId].report_note = normalizeReportNote(itemForms[itemId].report_note, unit);
  }

  itemForms[itemId].post(route('backend.reporting.item.update', itemId), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      const selected = itemForms[itemId].report_file;
      if (selected instanceof File) {
        fileUiState[itemId].savedName = selected.name;
        fileUiState[itemId].savedUrl = fileUiState[itemId].previewUrl || fileUiState[itemId].savedUrl;
      }

      itemForms[itemId].report_note = stripFullPageMarker(itemForms[itemId].report_note);
    },
  });
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

</script>

<template>
  <BackendLayout>
    <div class="w-full p-4 mt-3 bg-white rounded shadow-md">
      <div class="flex items-center justify-between p-4 bg-gray-100 rounded">
        <div>
          <h1 class="text-lg font-semibold text-gray-800">{{ pageTitle }}</h1>
          <p class="text-sm text-gray-600">Bill No: {{ billing.bill_number ?? 'N/A' }}</p>
        </div>
        <Link :href="route('backend.reporting.index')" class="text-sm text-blue-600 hover:underline">Back</Link>
      </div>

      <div class="mt-4 space-y-4">
          <div v-for="item in items" :key="item.id" class="p-4 border rounded">
            <div v-if="shouldRenderLargeResultBox(item.id)" class="mb-3">
              <div class="mb-2">
                <div class="text-sm font-semibold text-gray-800">{{ item.item_name }}</div>
                <div class="text-xs text-gray-500">{{ item.category }} | Sample: {{ formatSampleDateTime(item.sample_collected_at) }}</div>
              </div>
              <div class="flex flex-wrap items-center gap-2 mb-2">
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
                class="block w-full min-h-[46px] p-3 text-sm leading-7 rounded-md shadow-sm border-slate-300 focus:border-indigo-300 overflow-hidden"
                :style="`font-family: ${UNIVERSAL_FONT_STACK}; line-height: ${editorUiState[item.id]?.lineHeight || '1.75'};`"
                @input="(e) => onEditorInput(e, item.id)"
              ></div>
              <InputError class="mt-1" :message="itemForms[item.id].errors.report_note" />
            </div>

            <div :class="shouldRenderLargeResultBox(item.id) ? 'grid grid-cols-1 gap-3 lg:grid-cols-2' : 'grid grid-cols-1 gap-3 lg:grid-cols-3'">
              <div v-if="!shouldRenderLargeResultBox(item.id)">
                <div class="text-sm font-medium text-gray-800">{{ item.item_name }}</div>
                <div class="text-xs text-gray-500">{{ item.category }} | Sample: {{ formatSampleDateTime(item.sample_collected_at) }}</div>
              </div>

              <div v-if="!shouldRenderLargeResultBox(item.id)">
                <InputLabel :for="`range_top_${item.id}`" value="Normal Range" />
                <input
                  :id="`range_top_${item.id}`"
                  v-model="itemForms[item.id].report_range"
                  type="text"
                  class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300"
                  placeholder="Enter normal range"
                />
                <InputError class="mt-1" :message="itemForms[item.id].errors.report_range" />
              </div>

              <div>
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
                <input
                  :id="`file_top_${item.id}`"
                  type="file"
                  accept=".txt,.md,.html,.htm,.pdf,.doc,.docx,image/*"
                  class="block w-full text-sm"
                  @change="(e) => handleFileChange(e, item.id)"
                />
                <div v-if="fileUiState[item.id]?.selectedName" class="mt-1 text-xs text-green-700">
                  Selected: {{ fileUiState[item.id].selectedName }}
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
                <p v-if="fileUiState[item.id]?.docHint" class="mt-1 text-xs text-amber-700">
                  DOC detected। যদি clean import না হয়, নিচের Result বক্সে editable fallback টেক্সট দেয়া হবে।
                </p>
                <InputError class="mt-1" :message="itemForms[item.id].errors.report_file" />
              </div>
            </div>

            <div v-if="!shouldRenderLargeResultBox(item.id)" class="grid grid-cols-1 gap-4 mt-3 sm:grid-cols-2 lg:grid-cols-3">
              <div class="lg:col-span-3">
                <InputLabel :for="`note_${item.id}`" value="Result" />
                <textarea
                  :id="`note_${item.id}`"
                  :ref="(el) => setReportFieldRef(el, item.id)"
                  :value="itemForms[item.id].report_note"
                  rows="1"
                  class="block w-full min-h-[46px] p-3 text-sm leading-7 rounded-md shadow-sm border-slate-300 focus:border-indigo-300 resize-none overflow-hidden"
                  :style="`font-family: ${UNIVERSAL_FONT_STACK};`"
                  @input="(e) => onPlainReportInput(e, item.id)"
                  :placeholder="getReportNotePlaceholder(item.id)"
                ></textarea>

                <p v-if="getUnitFromRange(itemForms[item.id].report_range)" class="mt-1 text-xs text-gray-500">
                  Unit: {{ getUnitFromRange(itemForms[item.id].report_range) }}
                </p>
                <InputError class="mt-1" :message="itemForms[item.id].errors.report_note" />

              </div>
            </div>

            <div class="flex items-center justify-end gap-2 mt-4">
              <PrimaryButton
                type="button"
                :class="{ 'opacity-25': itemForms[item.id].processing }"
                :disabled="itemForms[item.id].processing"
                @click="submitItem(item.id)"
              >
                Save Report
              </PrimaryButton>
              <Link
                class="px-3 py-1 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700"
                :href="route('backend.reporting.print', item.id)"
                target="_blank"
                rel="noopener noreferrer"
              >
                Print
              </Link>
            </div>
          </div>
      </div>
    </div>
  </BackendLayout>
</template>
