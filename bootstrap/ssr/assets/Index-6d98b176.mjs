import { ref, computed, watch, withCtx, createVNode, openBlock, createBlock, Fragment, renderList, toDisplayString, createTextVNode, createCommentVNode, withDirectives, vModelText, useSSRContext } from "vue";
import { ssrRenderComponent, ssrRenderList, ssrRenderClass, ssrInterpolate, ssrIncludeBooleanAttr, ssrRenderAttr } from "vue/server-renderer";
import { usePage, router } from "@inertiajs/vue3";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import "jspdf";
import "html2canvas";
import "file-saver";
import * as XLSX from "xlsx";
import { _ as _export_sfc } from "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "mitt";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
const Index_vue_vue_type_style_index_0_scoped_a71e7cb0_lang = "";
const _sfc_main = {
  __name: "Index",
  __ssrInlineRender: true,
  props: {
    reportData: {
      type: Array,
      default: () => []
    },
    reportType: {
      type: String,
      default: "daily-transaction"
    },
    dateFrom: {
      type: String,
      default: ""
    },
    dateTo: {
      type: String,
      default: ""
    },
    footerTotals: {
      type: Object,
      default: () => ({})
    },
    tableHeaders: {
      type: Array,
      default: () => []
    },
    dataFields: {
      type: Array,
      default: () => []
    }
  },
  setup(__props) {
    usePage();
    const props = __props;
    const selectedReport = ref(props.reportType || "daily-transaction");
    const dateFrom = ref(props.dateFrom || "");
    const dateTo = ref(props.dateTo || "");
    const isLoading = ref(false);
    const reports = [
      { id: "daily-transaction", label: "Daily Transaction Report" },
      { id: "all-transaction", label: "All Transaction Report" },
      { id: "income", label: "Income Report" },
      { id: "expense", label: "Expense Report" },
      { id: "referral", label: "Referral Report" },
      { id: "pending-transaction", label: "Pending Transaction Report" }
    ];
    const selectReport = (reportId) => {
      selectedReport.value = reportId;
    };
    const handleSearch = () => {
      if (!dateFrom.value || !dateTo.value) {
        alert("Please select both date from and date to");
        return;
      }
      router.get("/finance/report", {
        report_type: selectedReport.value,
        date_from: dateFrom.value,
        date_to: dateTo.value
      }, {
        preserveState: false,
        preserveScroll: false
      });
    };
    const getReportTitle = () => {
      const report = reports.find((r) => r.id === selectedReport.value);
      return report ? report.label : "";
    };
    const hasReportData = computed(() => {
      return props.reportData && props.reportData.length > 0;
    });
    const formatCurrency = (amount) => {
      const num = Number(amount || 0);
      return `৳${num.toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    };
    const formatDate = (dateStr) => {
      if (!dateStr)
        return "N/A";
      const date = new Date(dateStr);
      const day = String(date.getDate()).padStart(2, "0");
      const month = String(date.getMonth() + 1).padStart(2, "0");
      const year = date.getFullYear();
      return `${day}/${month}/${year}`;
    };
    const getCurrentDateTime = () => {
      const now = /* @__PURE__ */ new Date();
      const day = String(now.getDate()).padStart(2, "0");
      const month = String(now.getMonth() + 1).padStart(2, "0");
      const year = now.getFullYear();
      const hours = String(now.getHours()).padStart(2, "0");
      const minutes = String(now.getMinutes()).padStart(2, "0");
      return `${day}/${month}/${year} ${hours}:${minutes}`;
    };
    const downloadPDF = async () => {
      if (!hasReportData.value) {
        alert("No data available to download");
        return;
      }
      isLoading.value = true;
      try {
        const params = new URLSearchParams({
          report_type: selectedReport.value,
          date_from: dateFrom.value,
          date_to: dateTo.value
        });
        const response = await fetch(`/finance/report/download-pdf?${params}`, {
          method: "GET",
          headers: {
            "Accept": "application/pdf",
            "X-Requested-With": "XMLHttpRequest"
          }
        });
        if (!response.ok) {
          throw new Error("Failed to download PDF");
        }
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        const fileName = `${selectedReport.value}_report_${dateFrom.value}_to_${dateTo.value}.pdf`;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
      } catch (error) {
        console.error("Error downloading PDF:", error);
        alert("Error downloading PDF. Please try again.");
      } finally {
        isLoading.value = false;
      }
    };
    const downloadExcelWithRawData = () => {
      if (!hasReportData.value) {
        alert("No data available to download");
        return;
      }
      try {
        const wb = XLSX.utils.book_new();
        const excelData = [];
        excelData.push([getReportTitle().toUpperCase()]);
        excelData.push([`Generated on: ${getCurrentDateTime()}`]);
        excelData.push([`Date Range: ${formatDate(dateFrom.value)} to ${formatDate(dateTo.value)}`]);
        excelData.push([]);
        excelData.push([`Total Records: ${props.reportData.length}`]);
        if (props.footerTotals.total_transactions) {
          excelData.push([`Total Transactions: ${props.footerTotals.total_transactions}`]);
        }
        excelData.push([]);
        excelData.push(props.tableHeaders);
        props.reportData.forEach((row) => {
          const dataRow = [];
          props.dataFields.forEach((field) => {
            let value = row[field.fieldName];
            const rawFieldName = `${field.fieldName}_raw`;
            if (row[rawFieldName] !== void 0) {
              value = row[rawFieldName];
            }
            if (typeof value === "string" && value.includes("<")) {
              const tempDiv = document.createElement("div");
              tempDiv.innerHTML = value;
              value = tempDiv.textContent || tempDiv.innerText || "";
            }
            if (typeof value === "string" && value.startsWith("৳")) {
              value = value.replace("৳", "").replace(/,/g, "");
            }
            dataRow.push(value);
          });
          excelData.push(dataRow);
        });
        if (showFooterTotals.value) {
          excelData.push([]);
          const summaryRow = [];
          props.tableHeaders.forEach((header, index) => {
            var _a;
            if (index < getFooterColumnSpan.value) {
              summaryRow.push(index === 0 ? "GRAND TOTAL" : "");
            } else {
              const fieldName = (_a = props.dataFields[index]) == null ? void 0 : _a.fieldName;
              if (fieldName && props.footerTotals[fieldName.replace("_raw", "")]) {
                summaryRow.push(props.footerTotals[fieldName.replace("_raw", "")]);
              } else {
                summaryRow.push("");
              }
            }
          });
          excelData.push(summaryRow);
        }
        const ws = XLSX.utils.aoa_to_sheet(excelData);
        const colWidths = props.tableHeaders.map(() => ({ width: 15 }));
        ws["!cols"] = colWidths;
        const mergeRanges = [];
        const headerRowCount = 6 + (props.footerTotals.total_transactions ? 1 : 0);
        const totalCols = props.tableHeaders.length;
        mergeRanges.push({ s: { r: 0, c: 0 }, e: { r: 0, c: totalCols - 1 } });
        mergeRanges.push({ s: { r: 1, c: 0 }, e: { r: 1, c: totalCols - 1 } });
        mergeRanges.push({ s: { r: 2, c: 0 }, e: { r: 2, c: totalCols - 1 } });
        mergeRanges.push({ s: { r: 4, c: 0 }, e: { r: 4, c: totalCols - 1 } });
        if (props.footerTotals.total_transactions) {
          mergeRanges.push({ s: { r: 5, c: 0 }, e: { r: 5, c: totalCols - 1 } });
        }
        ws["!merges"] = mergeRanges;
        const range = XLSX.utils.decode_range(ws["!ref"]);
        for (let r = 0; r <= 5; r++) {
          for (let c = 0; c < totalCols; c++) {
            const cell = XLSX.utils.encode_cell({ r, c });
            if (ws[cell]) {
              if (r === 0) {
                ws[cell].s = {
                  font: { bold: true, sz: 16 },
                  alignment: { horizontal: "center", vertical: "center" }
                };
              } else if (r <= 5) {
                ws[cell].s = {
                  font: { bold: true, sz: 12 },
                  alignment: { horizontal: "center", vertical: "center" }
                };
              }
            }
          }
        }
        const headerRow = props.footerTotals.total_transactions ? 7 : 6;
        for (let C = range.s.c; C <= range.e.c; ++C) {
          const cell = XLSX.utils.encode_cell({ r: headerRow, c: C });
          if (ws[cell]) {
            ws[cell].s = {
              font: { bold: true, color: { rgb: "FFFFFF" } },
              fill: { fgColor: { rgb: "4F81BD" } },
              alignment: { horizontal: "center", vertical: "center" }
            };
          }
        }
        XLSX.utils.book_append_sheet(wb, ws, "Finance Report");
        const fileName = `${selectedReport.value}_report_${dateFrom.value}_to_${dateTo.value}.xlsx`;
        XLSX.writeFile(wb, fileName);
      } catch (error) {
        console.error("Error generating Excel:", error);
        alert("Error generating Excel file. Please try again.");
      }
    };
    watch(() => props.reportType, (newVal) => {
      if (newVal)
        selectedReport.value = newVal;
    });
    watch(() => props.dateFrom, (newVal) => {
      if (newVal)
        dateFrom.value = newVal;
    });
    watch(() => props.dateTo, (newVal) => {
      if (newVal)
        dateTo.value = newVal;
    });
    const showFooterTotals = computed(() => {
      return hasReportData.value && Object.keys(props.footerTotals).length > 0;
    });
    const getFooterColumnSpan = computed(() => {
      const spans = {
        "daily-transaction": 2,
        "all-transaction": 6,
        "income": 2,
        "expense": 3,
        "referral": 4,
        "pending-transaction": 6
      };
      return spans[props.reportType] || 1;
    });
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="container mx-auto px-3 sm:px-4 py-4 sm:py-6" data-v-a71e7cb0${_scopeId}><div class="mb-4 sm:mb-6" data-v-a71e7cb0${_scopeId}><h1 class="text-xl sm:text-2xl font-semibold text-gray-800" data-v-a71e7cb0${_scopeId}>Finance Reports</h1><p class="text-sm text-gray-600 mt-1" data-v-a71e7cb0${_scopeId}>Generate and view comprehensive financial reports</p></div><div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 mb-4 sm:mb-6" data-v-a71e7cb0${_scopeId}><!--[-->`);
            ssrRenderList(reports, (report) => {
              _push2(`<button class="${ssrRenderClass([
                "flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 sm:py-3 rounded-lg text-left transition-all duration-200 text-sm sm:text-base",
                selectedReport.value === report.id ? "bg-blue-100 text-blue-900 shadow-sm" : "bg-white text-gray-700 hover:bg-gray-50 border border-gray-200"
              ])}" data-v-a71e7cb0${_scopeId}><svg class="w-4 h-4 sm:w-5 sm:h-5 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" data-v-a71e7cb0${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" data-v-a71e7cb0${_scopeId}></path></svg><span class="font-medium truncate" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(report.label)}</span></button>`);
            });
            _push2(`<!--]--></div><div class="bg-white rounded-lg shadow-sm border border-gray-200" data-v-a71e7cb0${_scopeId}><div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 bg-gray-50" data-v-a71e7cb0${_scopeId}><div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3" data-v-a71e7cb0${_scopeId}><h2 class="text-lg sm:text-xl font-semibold text-gray-800" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(getReportTitle())}</h2>`);
            if (hasReportData.value) {
              _push2(`<div class="flex gap-2" data-v-a71e7cb0${_scopeId}><button${ssrIncludeBooleanAttr(isLoading.value) ? " disabled" : ""} class="flex items-center gap-2 px-3 sm:px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed" data-v-a71e7cb0${_scopeId}><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" data-v-a71e7cb0${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" data-v-a71e7cb0${_scopeId}></path></svg> ${ssrInterpolate(isLoading.value ? "Generating PDF..." : "PDF")}</button><button class="flex items-center gap-2 px-3 sm:px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors text-sm font-medium" data-v-a71e7cb0${_scopeId}><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" data-v-a71e7cb0${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" data-v-a71e7cb0${_scopeId}></path></svg> Excel </button></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div></div><div class="px-4 sm:px-6 py-4 sm:py-6" data-v-a71e7cb0${_scopeId}><div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6" data-v-a71e7cb0${_scopeId}><div data-v-a71e7cb0${_scopeId}><label class="block text-sm font-medium text-gray-700 mb-2" data-v-a71e7cb0${_scopeId}> Date From <span class="text-red-500" data-v-a71e7cb0${_scopeId}>*</span></label><input${ssrRenderAttr("value", dateFrom.value)} type="date" class="w-full px-3 sm:px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-sm sm:text-base" required data-v-a71e7cb0${_scopeId}></div><div data-v-a71e7cb0${_scopeId}><label class="block text-sm font-medium text-gray-700 mb-2" data-v-a71e7cb0${_scopeId}> Date To <span class="text-red-500" data-v-a71e7cb0${_scopeId}>*</span></label><input${ssrRenderAttr("value", dateTo.value)} type="date" class="w-full px-3 sm:px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-sm sm:text-base" required data-v-a71e7cb0${_scopeId}></div><div class="flex items-end" data-v-a71e7cb0${_scopeId}><button class="w-full lg:w-auto flex items-center justify-center gap-2 px-4 sm:px-6 py-2.5 bg-gray-700 hover:bg-gray-800 text-white font-medium rounded-lg transition-colors duration-200 shadow-sm text-sm sm:text-base disabled:opacity-50 disabled:cursor-not-allowed"${ssrIncludeBooleanAttr(!dateFrom.value || !dateTo.value) ? " disabled" : ""} data-v-a71e7cb0${_scopeId}><svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" data-v-a71e7cb0${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" data-v-a71e7cb0${_scopeId}></path></svg> Generate Report </button></div></div></div><div class="px-2 sm:px-4 py-4 bg-gray-50 border-t border-gray-200" data-v-a71e7cb0${_scopeId}>`);
            if (hasReportData.value) {
              _push2(`<div data-v-a71e7cb0${_scopeId}><div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 px-2" data-v-a71e7cb0${_scopeId}><div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm" data-v-a71e7cb0${_scopeId}><div class="text-sm text-gray-600 mb-1" data-v-a71e7cb0${_scopeId}>Total Records</div><div class="text-2xl font-bold text-gray-800" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(__props.reportData.length)}</div></div><div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm" data-v-a71e7cb0${_scopeId}><div class="text-sm text-gray-600 mb-1" data-v-a71e7cb0${_scopeId}>Date Range</div><div class="text-sm font-semibold text-gray-800" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatDate(dateFrom.value))} - ${ssrInterpolate(formatDate(dateTo.value))}</div></div>`);
              if (__props.footerTotals.total_transactions) {
                _push2(`<div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm" data-v-a71e7cb0${_scopeId}><div class="text-sm text-gray-600 mb-1" data-v-a71e7cb0${_scopeId}>Total Transactions</div><div class="text-2xl font-bold text-gray-800" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(__props.footerTotals.total_transactions)}</div></div>`);
              } else {
                _push2(`<!---->`);
              }
              if (__props.footerTotals.total_bills) {
                _push2(`<div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm" data-v-a71e7cb0${_scopeId}><div class="text-sm text-gray-600 mb-1" data-v-a71e7cb0${_scopeId}>Total Bills</div><div class="text-2xl font-bold text-gray-800" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(__props.footerTotals.total_bills)}</div></div>`);
              } else {
                _push2(`<!---->`);
              }
              _push2(`</div><div id="report-table" class="overflow-x-auto bg-white rounded-lg shadow border border-gray-200" data-v-a71e7cb0${_scopeId}><table class="min-w-full divide-y divide-gray-200" data-v-a71e7cb0${_scopeId}><thead class="bg-gray-50" data-v-a71e7cb0${_scopeId}><tr data-v-a71e7cb0${_scopeId}><!--[-->`);
              ssrRenderList(__props.tableHeaders, (header, index) => {
                var _a;
                _push2(`<th class="${ssrRenderClass([
                  "px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                  ((_a = __props.dataFields[index]) == null ? void 0 : _a.class) || ""
                ])}" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(header)}</th>`);
              });
              _push2(`<!--]--></tr></thead><tbody class="bg-white divide-y divide-gray-200" data-v-a71e7cb0${_scopeId}><!--[-->`);
              ssrRenderList(__props.reportData, (row, rowIndex) => {
                _push2(`<tr class="${ssrRenderClass(rowIndex % 2 === 0 ? "bg-white" : "bg-gray-50")}" data-v-a71e7cb0${_scopeId}><!--[-->`);
                ssrRenderList(__props.dataFields, (field, fieldIndex) => {
                  _push2(`<td class="${ssrRenderClass(["px-4 py-3 whitespace-nowrap text-sm", field.class])}" data-v-a71e7cb0${_scopeId}>${row[field.fieldName] ?? ""}</td>`);
                });
                _push2(`<!--]--></tr>`);
              });
              _push2(`<!--]--></tbody>`);
              if (showFooterTotals.value) {
                _push2(`<tfoot class="bg-gray-800" data-v-a71e7cb0${_scopeId}><tr data-v-a71e7cb0${_scopeId}><td${ssrRenderAttr("colspan", getFooterColumnSpan.value)} class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white" data-v-a71e7cb0${_scopeId}> GRAND TOTAL </td>`);
                if (__props.reportType === "daily-transaction") {
                  _push2(`<!--[--><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-center" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(__props.footerTotals.total_transactions || 0)}</td><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatCurrency(__props.footerTotals.total_amount || 0))}</td><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatCurrency(__props.footerTotals.total_discount || 0))}</td><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatCurrency(__props.footerTotals.amount_after_discount || 0))}</td><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatCurrency(__props.footerTotals.cash_amount || 0))}</td><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatCurrency(__props.footerTotals.non_cash_amount || 0))}</td><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatCurrency(__props.footerTotals.total_commission || 0))}</td><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatCurrency(__props.footerTotals.total_paid || 0))}</td><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatCurrency(__props.footerTotals.total_due || 0))}</td><!--]-->`);
                } else if (__props.reportType === "all-transaction") {
                  _push2(`<!--[--><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatCurrency(__props.footerTotals.total || 0))}</td><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatCurrency(__props.footerTotals.discount || 0))}</td><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatCurrency(__props.footerTotals.payable_amount || 0))}</td><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatCurrency(__props.footerTotals.paid_amt || 0))}</td><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatCurrency(__props.footerTotals.due_amount || 0))}</td><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-center" data-v-a71e7cb0${_scopeId}> — </td><!--]-->`);
                } else if (__props.reportType === "income") {
                  _push2(`<!--[--><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-center" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(__props.footerTotals.total_bills || 0)}</td><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white" data-v-a71e7cb0${_scopeId}> — </td><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatCurrency(__props.footerTotals.total_bill || 0))}</td><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatCurrency(__props.footerTotals.total_discount || 0))}</td><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatCurrency(__props.footerTotals.total_income || 0))}</td><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatCurrency(__props.footerTotals.net_income || 0))}</td><!--]-->`);
                } else if (__props.reportType === "expense") {
                  _push2(`<!--[--><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-center" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(__props.footerTotals.total_transactions || 0)}</td><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatCurrency(__props.footerTotals.total_expense || 0))}</td><!--]-->`);
                } else if (__props.reportType === "referral") {
                  _push2(`<!--[--><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatCurrency(__props.footerTotals.total_bill_amount || 0))}</td><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatCurrency(__props.footerTotals.total_commission || 0))}</td><!--]-->`);
                } else if (__props.reportType === "pending-transaction") {
                  _push2(`<!--[--><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatCurrency(__props.footerTotals.total || 0))}</td><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatCurrency(__props.footerTotals.discount || 0))}</td><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatCurrency(__props.footerTotals.payable_amount || 0))}</td><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatCurrency(__props.footerTotals.paid_amt || 0))}</td><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" data-v-a71e7cb0${_scopeId}>${ssrInterpolate(formatCurrency(__props.footerTotals.due_amount || 0))}</td><td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-center" data-v-a71e7cb0${_scopeId}> — </td><!--]-->`);
                } else {
                  _push2(`<!---->`);
                }
                _push2(`</tr></tfoot>`);
              } else {
                _push2(`<!---->`);
              }
              _push2(`</table></div></div>`);
            } else {
              _push2(`<div class="text-center text-gray-500 py-8 sm:py-12" data-v-a71e7cb0${_scopeId}><svg class="w-12 h-12 sm:w-16 sm:h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" data-v-a71e7cb0${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" data-v-a71e7cb0${_scopeId}></path></svg><p class="text-base sm:text-lg font-medium" data-v-a71e7cb0${_scopeId}>No data to display</p><p class="text-sm mt-1" data-v-a71e7cb0${_scopeId}>Select date range and click &quot;Generate Report&quot; to view data</p></div>`);
            }
            _push2(`</div></div></div>`);
          } else {
            return [
              createVNode("div", { class: "container mx-auto px-3 sm:px-4 py-4 sm:py-6" }, [
                createVNode("div", { class: "mb-4 sm:mb-6" }, [
                  createVNode("h1", { class: "text-xl sm:text-2xl font-semibold text-gray-800" }, "Finance Reports"),
                  createVNode("p", { class: "text-sm text-gray-600 mt-1" }, "Generate and view comprehensive financial reports")
                ]),
                createVNode("div", { class: "grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 mb-4 sm:mb-6" }, [
                  (openBlock(), createBlock(Fragment, null, renderList(reports, (report) => {
                    return createVNode("button", {
                      key: report.id,
                      onClick: ($event) => selectReport(report.id),
                      class: [
                        "flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 sm:py-3 rounded-lg text-left transition-all duration-200 text-sm sm:text-base",
                        selectedReport.value === report.id ? "bg-blue-100 text-blue-900 shadow-sm" : "bg-white text-gray-700 hover:bg-gray-50 border border-gray-200"
                      ]
                    }, [
                      (openBlock(), createBlock("svg", {
                        class: "w-4 h-4 sm:w-5 sm:h-5 text-gray-500 flex-shrink-0",
                        fill: "none",
                        stroke: "currentColor",
                        viewBox: "0 0 24 24"
                      }, [
                        createVNode("path", {
                          "stroke-linecap": "round",
                          "stroke-linejoin": "round",
                          "stroke-width": "2",
                          d: "M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                        })
                      ])),
                      createVNode("span", { class: "font-medium truncate" }, toDisplayString(report.label), 1)
                    ], 10, ["onClick"]);
                  }), 64))
                ]),
                createVNode("div", { class: "bg-white rounded-lg shadow-sm border border-gray-200" }, [
                  createVNode("div", { class: "px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 bg-gray-50" }, [
                    createVNode("div", { class: "flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3" }, [
                      createVNode("h2", { class: "text-lg sm:text-xl font-semibold text-gray-800" }, toDisplayString(getReportTitle()), 1),
                      hasReportData.value ? (openBlock(), createBlock("div", {
                        key: 0,
                        class: "flex gap-2"
                      }, [
                        createVNode("button", {
                          onClick: downloadPDF,
                          disabled: isLoading.value,
                          class: "flex items-center gap-2 px-3 sm:px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                        }, [
                          (openBlock(), createBlock("svg", {
                            class: "w-4 h-4",
                            fill: "none",
                            stroke: "currentColor",
                            viewBox: "0 0 24 24"
                          }, [
                            createVNode("path", {
                              "stroke-linecap": "round",
                              "stroke-linejoin": "round",
                              "stroke-width": "2",
                              d: "M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                            })
                          ])),
                          createTextVNode(" " + toDisplayString(isLoading.value ? "Generating PDF..." : "PDF"), 1)
                        ], 8, ["disabled"]),
                        createVNode("button", {
                          onClick: downloadExcelWithRawData,
                          class: "flex items-center gap-2 px-3 sm:px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors text-sm font-medium"
                        }, [
                          (openBlock(), createBlock("svg", {
                            class: "w-4 h-4",
                            fill: "none",
                            stroke: "currentColor",
                            viewBox: "0 0 24 24"
                          }, [
                            createVNode("path", {
                              "stroke-linecap": "round",
                              "stroke-linejoin": "round",
                              "stroke-width": "2",
                              d: "M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                            })
                          ])),
                          createTextVNode(" Excel ")
                        ])
                      ])) : createCommentVNode("", true)
                    ])
                  ]),
                  createVNode("div", { class: "px-4 sm:px-6 py-4 sm:py-6" }, [
                    createVNode("div", { class: "grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6" }, [
                      createVNode("div", null, [
                        createVNode("label", { class: "block text-sm font-medium text-gray-700 mb-2" }, [
                          createTextVNode(" Date From "),
                          createVNode("span", { class: "text-red-500" }, "*")
                        ]),
                        withDirectives(createVNode("input", {
                          "onUpdate:modelValue": ($event) => dateFrom.value = $event,
                          type: "date",
                          class: "w-full px-3 sm:px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-sm sm:text-base",
                          required: ""
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, dateFrom.value]
                        ])
                      ]),
                      createVNode("div", null, [
                        createVNode("label", { class: "block text-sm font-medium text-gray-700 mb-2" }, [
                          createTextVNode(" Date To "),
                          createVNode("span", { class: "text-red-500" }, "*")
                        ]),
                        withDirectives(createVNode("input", {
                          "onUpdate:modelValue": ($event) => dateTo.value = $event,
                          type: "date",
                          class: "w-full px-3 sm:px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-sm sm:text-base",
                          required: ""
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, dateTo.value]
                        ])
                      ]),
                      createVNode("div", { class: "flex items-end" }, [
                        createVNode("button", {
                          onClick: handleSearch,
                          class: "w-full lg:w-auto flex items-center justify-center gap-2 px-4 sm:px-6 py-2.5 bg-gray-700 hover:bg-gray-800 text-white font-medium rounded-lg transition-colors duration-200 shadow-sm text-sm sm:text-base disabled:opacity-50 disabled:cursor-not-allowed",
                          disabled: !dateFrom.value || !dateTo.value
                        }, [
                          (openBlock(), createBlock("svg", {
                            class: "w-4 h-4 sm:w-5 sm:h-5",
                            fill: "none",
                            stroke: "currentColor",
                            viewBox: "0 0 24 24"
                          }, [
                            createVNode("path", {
                              "stroke-linecap": "round",
                              "stroke-linejoin": "round",
                              "stroke-width": "2",
                              d: "M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                            })
                          ])),
                          createTextVNode(" Generate Report ")
                        ], 8, ["disabled"])
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "px-2 sm:px-4 py-4 bg-gray-50 border-t border-gray-200" }, [
                    hasReportData.value ? (openBlock(), createBlock("div", { key: 0 }, [
                      createVNode("div", { class: "grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 px-2" }, [
                        createVNode("div", { class: "bg-white p-4 rounded-lg border border-gray-200 shadow-sm" }, [
                          createVNode("div", { class: "text-sm text-gray-600 mb-1" }, "Total Records"),
                          createVNode("div", { class: "text-2xl font-bold text-gray-800" }, toDisplayString(__props.reportData.length), 1)
                        ]),
                        createVNode("div", { class: "bg-white p-4 rounded-lg border border-gray-200 shadow-sm" }, [
                          createVNode("div", { class: "text-sm text-gray-600 mb-1" }, "Date Range"),
                          createVNode("div", { class: "text-sm font-semibold text-gray-800" }, toDisplayString(formatDate(dateFrom.value)) + " - " + toDisplayString(formatDate(dateTo.value)), 1)
                        ]),
                        __props.footerTotals.total_transactions ? (openBlock(), createBlock("div", {
                          key: 0,
                          class: "bg-white p-4 rounded-lg border border-gray-200 shadow-sm"
                        }, [
                          createVNode("div", { class: "text-sm text-gray-600 mb-1" }, "Total Transactions"),
                          createVNode("div", { class: "text-2xl font-bold text-gray-800" }, toDisplayString(__props.footerTotals.total_transactions), 1)
                        ])) : createCommentVNode("", true),
                        __props.footerTotals.total_bills ? (openBlock(), createBlock("div", {
                          key: 1,
                          class: "bg-white p-4 rounded-lg border border-gray-200 shadow-sm"
                        }, [
                          createVNode("div", { class: "text-sm text-gray-600 mb-1" }, "Total Bills"),
                          createVNode("div", { class: "text-2xl font-bold text-gray-800" }, toDisplayString(__props.footerTotals.total_bills), 1)
                        ])) : createCommentVNode("", true)
                      ]),
                      createVNode("div", {
                        id: "report-table",
                        class: "overflow-x-auto bg-white rounded-lg shadow border border-gray-200"
                      }, [
                        createVNode("table", { class: "min-w-full divide-y divide-gray-200" }, [
                          createVNode("thead", { class: "bg-gray-50" }, [
                            createVNode("tr", null, [
                              (openBlock(true), createBlock(Fragment, null, renderList(__props.tableHeaders, (header, index) => {
                                var _a;
                                return openBlock(), createBlock("th", {
                                  key: index,
                                  class: [
                                    "px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider",
                                    ((_a = __props.dataFields[index]) == null ? void 0 : _a.class) || ""
                                  ]
                                }, toDisplayString(header), 3);
                              }), 128))
                            ])
                          ]),
                          createVNode("tbody", { class: "bg-white divide-y divide-gray-200" }, [
                            (openBlock(true), createBlock(Fragment, null, renderList(__props.reportData, (row, rowIndex) => {
                              return openBlock(), createBlock("tr", {
                                key: rowIndex,
                                class: rowIndex % 2 === 0 ? "bg-white" : "bg-gray-50"
                              }, [
                                (openBlock(true), createBlock(Fragment, null, renderList(__props.dataFields, (field, fieldIndex) => {
                                  return openBlock(), createBlock("td", {
                                    key: fieldIndex,
                                    class: ["px-4 py-3 whitespace-nowrap text-sm", field.class],
                                    innerHTML: row[field.fieldName]
                                  }, null, 10, ["innerHTML"]);
                                }), 128))
                              ], 2);
                            }), 128))
                          ]),
                          showFooterTotals.value ? (openBlock(), createBlock("tfoot", {
                            key: 0,
                            class: "bg-gray-800"
                          }, [
                            createVNode("tr", null, [
                              createVNode("td", {
                                colspan: getFooterColumnSpan.value,
                                class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white"
                              }, " GRAND TOTAL ", 8, ["colspan"]),
                              __props.reportType === "daily-transaction" ? (openBlock(), createBlock(Fragment, { key: 0 }, [
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-center" }, toDisplayString(__props.footerTotals.total_transactions || 0), 1),
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" }, toDisplayString(formatCurrency(__props.footerTotals.total_amount || 0)), 1),
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" }, toDisplayString(formatCurrency(__props.footerTotals.total_discount || 0)), 1),
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" }, toDisplayString(formatCurrency(__props.footerTotals.amount_after_discount || 0)), 1),
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" }, toDisplayString(formatCurrency(__props.footerTotals.cash_amount || 0)), 1),
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" }, toDisplayString(formatCurrency(__props.footerTotals.non_cash_amount || 0)), 1),
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" }, toDisplayString(formatCurrency(__props.footerTotals.total_commission || 0)), 1),
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" }, toDisplayString(formatCurrency(__props.footerTotals.total_paid || 0)), 1),
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" }, toDisplayString(formatCurrency(__props.footerTotals.total_due || 0)), 1)
                              ], 64)) : __props.reportType === "all-transaction" ? (openBlock(), createBlock(Fragment, { key: 1 }, [
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" }, toDisplayString(formatCurrency(__props.footerTotals.total || 0)), 1),
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" }, toDisplayString(formatCurrency(__props.footerTotals.discount || 0)), 1),
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" }, toDisplayString(formatCurrency(__props.footerTotals.payable_amount || 0)), 1),
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" }, toDisplayString(formatCurrency(__props.footerTotals.paid_amt || 0)), 1),
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" }, toDisplayString(formatCurrency(__props.footerTotals.due_amount || 0)), 1),
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-center" }, " — ")
                              ], 64)) : __props.reportType === "income" ? (openBlock(), createBlock(Fragment, { key: 2 }, [
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-center" }, toDisplayString(__props.footerTotals.total_bills || 0), 1),
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white" }, " — "),
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" }, toDisplayString(formatCurrency(__props.footerTotals.total_bill || 0)), 1),
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" }, toDisplayString(formatCurrency(__props.footerTotals.total_discount || 0)), 1),
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" }, toDisplayString(formatCurrency(__props.footerTotals.total_income || 0)), 1),
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" }, toDisplayString(formatCurrency(__props.footerTotals.net_income || 0)), 1)
                              ], 64)) : __props.reportType === "expense" ? (openBlock(), createBlock(Fragment, { key: 3 }, [
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-center" }, toDisplayString(__props.footerTotals.total_transactions || 0), 1),
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" }, toDisplayString(formatCurrency(__props.footerTotals.total_expense || 0)), 1)
                              ], 64)) : __props.reportType === "referral" ? (openBlock(), createBlock(Fragment, { key: 4 }, [
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" }, toDisplayString(formatCurrency(__props.footerTotals.total_bill_amount || 0)), 1),
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" }, toDisplayString(formatCurrency(__props.footerTotals.total_commission || 0)), 1)
                              ], 64)) : __props.reportType === "pending-transaction" ? (openBlock(), createBlock(Fragment, { key: 5 }, [
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" }, toDisplayString(formatCurrency(__props.footerTotals.total || 0)), 1),
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" }, toDisplayString(formatCurrency(__props.footerTotals.discount || 0)), 1),
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" }, toDisplayString(formatCurrency(__props.footerTotals.payable_amount || 0)), 1),
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" }, toDisplayString(formatCurrency(__props.footerTotals.paid_amt || 0)), 1),
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-right" }, toDisplayString(formatCurrency(__props.footerTotals.due_amount || 0)), 1),
                                createVNode("td", { class: "px-4 py-3 whitespace-nowrap text-sm font-semibold text-white text-center" }, " — ")
                              ], 64)) : createCommentVNode("", true)
                            ])
                          ])) : createCommentVNode("", true)
                        ])
                      ])
                    ])) : (openBlock(), createBlock("div", {
                      key: 1,
                      class: "text-center text-gray-500 py-8 sm:py-12"
                    }, [
                      (openBlock(), createBlock("svg", {
                        class: "w-12 h-12 sm:w-16 sm:h-16 mx-auto mb-4 text-gray-400",
                        fill: "none",
                        stroke: "currentColor",
                        viewBox: "0 0 24 24"
                      }, [
                        createVNode("path", {
                          "stroke-linecap": "round",
                          "stroke-linejoin": "round",
                          "stroke-width": "2",
                          d: "M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                        })
                      ])),
                      createVNode("p", { class: "text-base sm:text-lg font-medium" }, "No data to display"),
                      createVNode("p", { class: "text-sm mt-1" }, 'Select date range and click "Generate Report" to view data')
                    ]))
                  ])
                ])
              ])
            ];
          }
        }),
        _: 1
      }, _parent));
    };
  }
};
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/FinanceReport/Index.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const Index = /* @__PURE__ */ _export_sfc(_sfc_main, [["__scopeId", "data-v-a71e7cb0"]]);
export {
  Index as default
};
