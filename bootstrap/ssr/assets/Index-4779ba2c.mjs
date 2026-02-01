import { ref, computed, onMounted, onUnmounted, withCtx, createVNode, toDisplayString, openBlock, createBlock, createTextVNode, Fragment, renderList, createCommentVNode, withDirectives, vModelSelect, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrRenderAttr, ssrRenderClass, ssrRenderList, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import { router } from "@inertiajs/vue3";
import { _ as _export_sfc } from "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "mitt";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
const Index_vue_vue_type_style_index_0_scoped_8faa13fa_lang = "";
const _sfc_main = {
  __name: "Index",
  __ssrInlineRender: true,
  props: {
    filters: Object,
    hasData: Boolean,
    reportData: Object
  },
  setup(__props) {
    var _a, _b, _c, _d;
    const props = __props;
    const loading = ref(false);
    const downloadingPdf = ref(false);
    const hasFiltered = ref(false);
    const showDatePicker = ref(false);
    const currentMonth = ref((/* @__PURE__ */ new Date()).getMonth());
    const currentYear = ref((/* @__PURE__ */ new Date()).getFullYear());
    const selectingFrom = ref(true);
    const selectionMode = ref("single");
    const filters = ref({
      dateFrom: ((_a = props.filters) == null ? void 0 : _a.dateFrom) ?? "",
      dateTo: ((_b = props.filters) == null ? void 0 : _b.dateTo) ?? "",
      module: ((_c = props.filters) == null ? void 0 : _c.module) ?? "",
      singleDate: ((_d = props.filters) == null ? void 0 : _d.singleDate) ?? ""
    });
    const modules = [
      { id: "all_module", name: "All Module" },
      { id: "pathology", name: "Pathology" },
      { id: "radiology", name: "Radiology" },
      { id: "medicine", name: "Pharmacy" },
      { id: "opd", name: "OPD" },
      { id: "ipd", name: "IPD" }
    ];
    const dateRangeDisplay = computed(() => {
      if (selectionMode.value === "single" && filters.value.singleDate) {
        const date = createDateFromString(filters.value.singleDate);
        return date.toLocaleDateString("en-GB");
      } else if (selectionMode.value === "range") {
        if (filters.value.dateFrom && filters.value.dateTo) {
          const fromDate = createDateFromString(filters.value.dateFrom);
          const toDate = createDateFromString(filters.value.dateTo);
          return `${fromDate.toLocaleDateString("en-GB")} - ${toDate.toLocaleDateString("en-GB")}`;
        } else if (filters.value.dateFrom) {
          const fromDate = createDateFromString(filters.value.dateFrom);
          return `From ${fromDate.toLocaleDateString("en-GB")}`;
        } else if (filters.value.dateTo) {
          const toDate = createDateFromString(filters.value.dateTo);
          return `Until ${toDate.toLocaleDateString("en-GB")}`;
        }
      }
      return "Select date";
    });
    const formatDate = (date) => {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, "0");
      const day = String(date.getDate()).padStart(2, "0");
      return `${year}-${month}-${day}`;
    };
    const createDateFromString = (dateString) => {
      if (!dateString)
        return null;
      const [year, month, day] = dateString.split("-").map(Number);
      return new Date(year, month - 1, day, 12, 0, 0);
    };
    const createLocalDate = (year, month, day) => {
      return new Date(year, month, day, 12, 0, 0);
    };
    const applyFilter = () => {
      loading.value = true;
      hasFiltered.value = true;
      let filterData = { ...filters.value };
      if (selectionMode.value === "single") {
        filterData.dateFrom = "";
        filterData.dateTo = "";
      } else {
        filterData.singleDate = "";
      }
      router.get(route("backend.report.index"), filterData, {
        preserveState: true,
        onFinish: () => {
          loading.value = false;
        }
      });
    };
    const downloadPdf = async () => {
      downloadingPdf.value = true;
      let filterData = { ...filters.value };
      if (selectionMode.value === "single") {
        filterData.dateFrom = "";
        filterData.dateTo = "";
      } else {
        filterData.singleDate = "";
      }
      try {
        const params = new URLSearchParams();
        Object.keys(filterData).forEach((key) => {
          if (filterData[key]) {
            params.append(key, filterData[key]);
          }
        });
        const url = `${route("backend.report.generate-pdf")}?${params.toString()}`;
        window.location.href = url;
      } catch (error) {
        console.error("Download error:", error);
        const form = document.createElement("form");
        form.method = "GET";
        form.action = route("backend.report.generate-pdf");
        Object.keys(filterData).forEach((key) => {
          if (filterData[key]) {
            const input = document.createElement("input");
            input.type = "hidden";
            input.name = key;
            input.value = filterData[key];
            form.appendChild(input);
          }
        });
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
      }
      setTimeout(() => {
        downloadingPdf.value = false;
      }, 2e3);
    };
    const resetFilters = () => {
      filters.value = {
        dateFrom: "",
        dateTo: "",
        module: "",
        singleDate: ""
      };
      hasFiltered.value = false;
      selectionMode.value = "single";
      selectingFrom.value = true;
      router.get(route("backend.report.index"), {
        dateFrom: "",
        dateTo: "",
        module: "",
        singleDate: ""
      }, {
        preserveState: true
      });
    };
    const monthNames = [
      "January",
      "February",
      "March",
      "April",
      "May",
      "June",
      "July",
      "August",
      "September",
      "October",
      "November",
      "December"
    ];
    const daysInMonth = (month, year) => {
      return new Date(year, month + 1, 0).getDate();
    };
    const firstDayOfMonth = (month, year) => {
      return new Date(year, month, 1).getDay();
    };
    const getMonthDays = (month, year) => {
      const days = [];
      const daysInCurrentMonth = daysInMonth(month, year);
      const firstDay = firstDayOfMonth(month, year);
      const prevMonth2 = month === 0 ? 11 : month - 1;
      const prevYear = month === 0 ? year - 1 : year;
      const daysInPrevMonth = daysInMonth(prevMonth2, prevYear);
      for (let i = firstDay - 1; i >= 0; i--) {
        days.push({
          day: daysInPrevMonth - i,
          isCurrentMonth: false,
          isOtherMonth: true,
          date: createLocalDate(prevYear, prevMonth2, daysInPrevMonth - i)
        });
      }
      for (let day = 1; day <= daysInCurrentMonth; day++) {
        days.push({
          day,
          isCurrentMonth: true,
          isOtherMonth: false,
          date: createLocalDate(year, month, day)
        });
      }
      const totalCells = 42;
      const remainingCells = totalCells - days.length;
      const nextMonth2 = month === 11 ? 0 : month + 1;
      const nextYear = month === 11 ? year + 1 : year;
      for (let day = 1; day <= remainingCells; day++) {
        days.push({
          day,
          isCurrentMonth: false,
          isOtherMonth: true,
          date: createLocalDate(nextYear, nextMonth2, day)
        });
      }
      return days;
    };
    const getDaysArray = computed(() => {
      return getMonthDays(currentMonth.value, currentYear.value);
    });
    const getNextMonthDays = computed(() => {
      const nextMonth2 = currentMonth.value === 11 ? 0 : currentMonth.value + 1;
      const nextYear = currentMonth.value === 11 ? currentYear.value + 1 : currentYear.value;
      return getMonthDays(nextMonth2, nextYear);
    });
    const isDateInRange = (date) => {
      if (selectionMode.value === "single") {
        return formatDate(date) === filters.value.singleDate;
      }
      if (!filters.value.dateFrom || !filters.value.dateTo)
        return false;
      const dateStr = formatDate(date);
      return dateStr >= filters.value.dateFrom && dateStr <= filters.value.dateTo;
    };
    const isDateSelected = (date) => {
      const dateStr = formatDate(date);
      if (selectionMode.value === "single") {
        return dateStr === filters.value.singleDate;
      }
      return dateStr === filters.value.dateFrom || dateStr === filters.value.dateTo;
    };
    const isDateStart = (date) => {
      if (selectionMode.value === "single")
        return false;
      const dateStr = formatDate(date);
      return dateStr === filters.value.dateFrom;
    };
    const isDateEnd = (date) => {
      if (selectionMode.value === "single")
        return false;
      const dateStr = formatDate(date);
      return dateStr === filters.value.dateTo;
    };
    const selectDate = (date) => {
      const dateStr = formatDate(date);
      if (selectionMode.value === "single") {
        filters.value.singleDate = dateStr;
        filters.value.dateFrom = "";
        filters.value.dateTo = "";
      } else {
        if (selectingFrom.value || !filters.value.dateFrom) {
          filters.value.dateFrom = dateStr;
          filters.value.dateTo = "";
          filters.value.singleDate = "";
          selectingFrom.value = false;
        } else {
          if (dateStr < filters.value.dateFrom) {
            filters.value.dateTo = filters.value.dateFrom;
            filters.value.dateFrom = dateStr;
          } else {
            filters.value.dateTo = dateStr;
          }
          selectingFrom.value = true;
        }
      }
    };
    const switchToSingleMode = () => {
      selectionMode.value = "single";
      filters.value.dateFrom = "";
      filters.value.dateTo = "";
      selectingFrom.value = true;
    };
    const switchToRangeMode = () => {
      selectionMode.value = "range";
      filters.value.singleDate = "";
      selectingFrom.value = true;
    };
    const prevMonth = () => {
      if (currentMonth.value === 0) {
        currentMonth.value = 11;
        currentYear.value--;
      } else {
        currentMonth.value--;
      }
    };
    const nextMonth = () => {
      if (currentMonth.value === 11) {
        currentMonth.value = 0;
        currentYear.value++;
      } else {
        currentMonth.value++;
      }
    };
    const toggleDatePicker = () => {
      showDatePicker.value = !showDatePicker.value;
    };
    const closeDatePicker = () => {
      showDatePicker.value = false;
    };
    const clearDates = () => {
      filters.value.dateFrom = "";
      filters.value.dateTo = "";
      filters.value.singleDate = "";
      selectingFrom.value = true;
    };
    const handleClickOutside = (event) => {
      if (!event.target.closest(".date-picker-container")) {
        showDatePicker.value = false;
      }
    };
    onMounted(() => {
      if (props.filters && Object.keys(props.filters).some((key) => props.filters[key])) {
        hasFiltered.value = true;
        if (props.filters.singleDate) {
          selectionMode.value = "single";
        } else if (props.filters.dateFrom || props.filters.dateTo) {
          selectionMode.value = "range";
        }
      }
      document.addEventListener("click", handleClickOutside);
    });
    onUnmounted(() => {
      document.removeEventListener("click", handleClickOutside);
    });
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="report-generator" data-v-8faa13fa${_scopeId}><div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6" data-v-8faa13fa${_scopeId}><div class="mb-6" data-v-8faa13fa${_scopeId}><h2 class="text-2xl font-bold text-gray-800 mb-2" data-v-8faa13fa${_scopeId}>Report Generator</h2><p class="text-gray-600" data-v-8faa13fa${_scopeId}>Generate reports by selecting date range and module</p></div><div class="bg-gray-50 rounded-lg p-4 mb-6" data-v-8faa13fa${_scopeId}><div class="flex flex-wrap items-end gap-4 mb-4" data-v-8faa13fa${_scopeId}><div class="flex-1 min-w-[250px] relative date-picker-container" data-v-8faa13fa${_scopeId}><label class="block text-sm font-medium text-gray-700 mb-1" data-v-8faa13fa${_scopeId}>${ssrInterpolate(selectionMode.value === "single" ? "Date" : "Date Range")}</label><div class="relative" data-v-8faa13fa${_scopeId}><input type="text" readonly${ssrRenderAttr("value", dateRangeDisplay.value)} class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent cursor-pointer bg-white"${ssrRenderAttr("placeholder", selectionMode.value === "single" ? "Select date" : "Select date range")} data-v-8faa13fa${_scopeId}><div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none" data-v-8faa13fa${_scopeId}><svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" data-v-8faa13fa${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" data-v-8faa13fa${_scopeId}></path></svg></div></div>`);
            if (showDatePicker.value) {
              _push2(`<div class="absolute z-10 mt-1 bg-white border border-gray-200 rounded-lg shadow-xl min-w-[680px] max-w-[800px]" data-v-8faa13fa${_scopeId}><div class="flex border-b border-gray-200 bg-gray-50 rounded-t-lg" data-v-8faa13fa${_scopeId}><button class="${ssrRenderClass([
                "flex-1 px-4 py-3 text-sm font-medium transition-colors relative",
                selectionMode.value === "single" ? "bg-white text-blue-600 border-b-2 border-blue-600 shadow-sm" : "text-gray-600 hover:text-gray-900 hover:bg-gray-100"
              ])}" data-v-8faa13fa${_scopeId}><span class="flex items-center justify-center gap-2" data-v-8faa13fa${_scopeId}><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" data-v-8faa13fa${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" data-v-8faa13fa${_scopeId}></path></svg> Single Date </span></button><button class="${ssrRenderClass([
                "flex-1 px-4 py-3 text-sm font-medium transition-colors relative",
                selectionMode.value === "range" ? "bg-white text-blue-600 border-b-2 border-blue-600 shadow-sm" : "text-gray-600 hover:text-gray-900 hover:bg-gray-100"
              ])}" data-v-8faa13fa${_scopeId}><span class="flex items-center justify-center gap-2" data-v-8faa13fa${_scopeId}><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" data-v-8faa13fa${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" data-v-8faa13fa${_scopeId}></path></svg> Date Range </span></button></div><div class="flex" data-v-8faa13fa${_scopeId}><div class="flex-1 p-4" data-v-8faa13fa${_scopeId}><div class="flex items-center justify-between mb-4" data-v-8faa13fa${_scopeId}><button class="p-2 hover:bg-gray-100 rounded-full transition-colors" data-v-8faa13fa${_scopeId}><svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" data-v-8faa13fa${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" data-v-8faa13fa${_scopeId}></path></svg></button><h3 class="text-lg font-semibold text-gray-900" data-v-8faa13fa${_scopeId}>${ssrInterpolate(monthNames[currentMonth.value])} ${ssrInterpolate(currentYear.value)}</h3><div class="w-9" data-v-8faa13fa${_scopeId}></div></div><div class="grid grid-cols-7 gap-1 mb-2" data-v-8faa13fa${_scopeId}><div class="text-center text-xs font-medium text-gray-500 py-2" data-v-8faa13fa${_scopeId}>Su</div><div class="text-center text-xs font-medium text-gray-500 py-2" data-v-8faa13fa${_scopeId}>Mo</div><div class="text-center text-xs font-medium text-gray-500 py-2" data-v-8faa13fa${_scopeId}>Tu</div><div class="text-center text-xs font-medium text-gray-500 py-2" data-v-8faa13fa${_scopeId}>We</div><div class="text-center text-xs font-medium text-gray-500 py-2" data-v-8faa13fa${_scopeId}>Th</div><div class="text-center text-xs font-medium text-gray-500 py-2" data-v-8faa13fa${_scopeId}>Fr</div><div class="text-center text-xs font-medium text-gray-500 py-2" data-v-8faa13fa${_scopeId}>Sa</div></div><div class="grid grid-cols-7 gap-1" data-v-8faa13fa${_scopeId}><!--[-->`);
              ssrRenderList(getDaysArray.value, (dayObj) => {
                _push2(`<button class="${ssrRenderClass([
                  "w-9 h-9 text-sm flex items-center justify-center transition-all duration-200",
                  {
                    "text-gray-400": dayObj.isOtherMonth,
                    "text-gray-900": dayObj.isCurrentMonth && !isDateSelected(dayObj.date) && !isDateInRange(dayObj.date),
                    "bg-blue-600 text-white font-medium": isDateSelected(dayObj.date),
                    "bg-blue-100 text-blue-600": isDateInRange(dayObj.date) && !isDateSelected(dayObj.date) && selectionMode.value === "range",
                    "hover:bg-gray-100": !isDateSelected(dayObj.date) && !isDateInRange(dayObj.date) && dayObj.isCurrentMonth,
                    "hover:bg-gray-50": !isDateSelected(dayObj.date) && !isDateInRange(dayObj.date) && dayObj.isOtherMonth,
                    "rounded-l-full": isDateStart(dayObj.date) && filters.value.dateFrom !== filters.value.dateTo,
                    "rounded-r-full": isDateEnd(dayObj.date) && filters.value.dateFrom !== filters.value.dateTo,
                    "rounded-full": isDateSelected(dayObj.date) || isDateInRange(dayObj.date) && selectionMode.value === "single",
                    "cursor-pointer": dayObj.isCurrentMonth
                  }
                ])}" data-v-8faa13fa${_scopeId}>${ssrInterpolate(dayObj.day)}</button>`);
              });
              _push2(`<!--]--></div></div><div class="w-px bg-gray-200" data-v-8faa13fa${_scopeId}></div><div class="flex-1 p-4" data-v-8faa13fa${_scopeId}><div class="flex items-center justify-between mb-4" data-v-8faa13fa${_scopeId}><div class="w-9" data-v-8faa13fa${_scopeId}></div><h3 class="text-lg font-semibold text-gray-900" data-v-8faa13fa${_scopeId}>${ssrInterpolate(monthNames[currentMonth.value === 11 ? 0 : currentMonth.value + 1])} ${ssrInterpolate(currentMonth.value === 11 ? currentYear.value + 1 : currentYear.value)}</h3><button class="p-2 hover:bg-gray-100 rounded-full transition-colors" data-v-8faa13fa${_scopeId}><svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" data-v-8faa13fa${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" data-v-8faa13fa${_scopeId}></path></svg></button></div><div class="grid grid-cols-7 gap-1 mb-2" data-v-8faa13fa${_scopeId}><div class="text-center text-xs font-medium text-gray-500 py-2" data-v-8faa13fa${_scopeId}>Su</div><div class="text-center text-xs font-medium text-gray-500 py-2" data-v-8faa13fa${_scopeId}>Mo</div><div class="text-center text-xs font-medium text-gray-500 py-2" data-v-8faa13fa${_scopeId}>Tu</div><div class="text-center text-xs font-medium text-gray-500 py-2" data-v-8faa13fa${_scopeId}>We</div><div class="text-center text-xs font-medium text-gray-500 py-2" data-v-8faa13fa${_scopeId}>Th</div><div class="text-center text-xs font-medium text-gray-500 py-2" data-v-8faa13fa${_scopeId}>Fr</div><div class="text-center text-xs font-medium text-gray-500 py-2" data-v-8faa13fa${_scopeId}>Sa</div></div><div class="grid grid-cols-7 gap-1" data-v-8faa13fa${_scopeId}><!--[-->`);
              ssrRenderList(getNextMonthDays.value, (dayObj) => {
                _push2(`<button class="${ssrRenderClass([
                  "w-9 h-9 text-sm flex items-center justify-center transition-all duration-200",
                  {
                    "text-gray-400": dayObj.isOtherMonth,
                    "text-gray-900": dayObj.isCurrentMonth && !isDateSelected(dayObj.date) && !isDateInRange(dayObj.date),
                    "bg-blue-600 text-white font-medium": isDateSelected(dayObj.date),
                    "bg-blue-100 text-blue-600": isDateInRange(dayObj.date) && !isDateSelected(dayObj.date) && selectionMode.value === "range",
                    "hover:bg-gray-100": !isDateSelected(dayObj.date) && !isDateInRange(dayObj.date) && dayObj.isCurrentMonth,
                    "hover:bg-gray-50": !isDateSelected(dayObj.date) && !isDateInRange(dayObj.date) && dayObj.isOtherMonth,
                    "rounded-l-full": isDateStart(dayObj.date) && filters.value.dateFrom !== filters.value.dateTo,
                    "rounded-r-full": isDateEnd(dayObj.date) && filters.value.dateFrom !== filters.value.dateTo,
                    "rounded-full": isDateSelected(dayObj.date) || isDateInRange(dayObj.date) && selectionMode.value === "single",
                    "cursor-pointer": dayObj.isCurrentMonth
                  }
                ])}" data-v-8faa13fa${_scopeId}>${ssrInterpolate(dayObj.day)}</button>`);
              });
              _push2(`<!--]--></div></div></div><div class="flex justify-between items-center p-4 border-t border-gray-200 bg-gray-50" data-v-8faa13fa${_scopeId}><div class="flex gap-2" data-v-8faa13fa${_scopeId}><button class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-md transition-colors" data-v-8faa13fa${_scopeId}> Clear </button></div><div class="flex gap-2" data-v-8faa13fa${_scopeId}><button class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 border border-gray-300 rounded-md hover:bg-gray-100 transition-colors" data-v-8faa13fa${_scopeId}> Cancel </button><button class="px-4 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors" data-v-8faa13fa${_scopeId}> Apply </button></div></div></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div><div class="flex-1 min-w-[200px]" data-v-8faa13fa${_scopeId}><label class="block text-sm font-medium text-gray-700 mb-1" data-v-8faa13fa${_scopeId}>Module</label><select class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" data-v-8faa13fa${_scopeId}><option value="" data-v-8faa13fa${ssrIncludeBooleanAttr(Array.isArray(filters.value.module) ? ssrLooseContain(filters.value.module, "") : ssrLooseEqual(filters.value.module, "")) ? " selected" : ""}${_scopeId}>Select Module</option><!--[-->`);
            ssrRenderList(modules, (module) => {
              _push2(`<option${ssrRenderAttr("value", module.id)} data-v-8faa13fa${ssrIncludeBooleanAttr(Array.isArray(filters.value.module) ? ssrLooseContain(filters.value.module, module.id) : ssrLooseEqual(filters.value.module, module.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(module.name)}</option>`);
            });
            _push2(`<!--]--></select></div><div class="flex gap-2" data-v-8faa13fa${_scopeId}><button${ssrIncludeBooleanAttr(loading.value) ? " disabled" : ""} class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 transition-colors" data-v-8faa13fa${_scopeId}>`);
            if (loading.value) {
              _push2(`<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" data-v-8faa13fa${_scopeId}><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" data-v-8faa13fa${_scopeId}></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" data-v-8faa13fa${_scopeId}></path></svg>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(` ${ssrInterpolate(loading.value ? "Generating..." : "Generate Report")}</button>`);
            if (hasFiltered.value) {
              _push2(`<button class="px-6 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors" data-v-8faa13fa${_scopeId}> Reset </button>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div></div></div>`);
            if (__props.hasData && props.reportData) {
              _push2(`<div class="bg-white rounded-lg border border-gray-200" data-v-8faa13fa${_scopeId}><div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center" data-v-8faa13fa${_scopeId}><h3 class="text-lg font-semibold text-gray-800" data-v-8faa13fa${_scopeId}>Report Results</h3><button${ssrIncludeBooleanAttr(downloadingPdf.value) ? " disabled" : ""} class="px-4 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 transition-colors" data-v-8faa13fa${_scopeId}>`);
              if (downloadingPdf.value) {
                _push2(`<svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24" data-v-8faa13fa${_scopeId}><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" data-v-8faa13fa${_scopeId}></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" data-v-8faa13fa${_scopeId}></path></svg>`);
              } else {
                _push2(`<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" data-v-8faa13fa${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" data-v-8faa13fa${_scopeId}></path></svg>`);
              }
              _push2(` ${ssrInterpolate(downloadingPdf.value ? "Generating PDF..." : "Download PDF")}</button></div><div class="p-6" data-v-8faa13fa${_scopeId}><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6" data-v-8faa13fa${_scopeId}><div class="bg-blue-50 rounded-lg p-4" data-v-8faa13fa${_scopeId}><div class="flex items-center" data-v-8faa13fa${_scopeId}><div class="flex-shrink-0" data-v-8faa13fa${_scopeId}><div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center" data-v-8faa13fa${_scopeId}><svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" data-v-8faa13fa${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" data-v-8faa13fa${_scopeId}></path></svg></div></div><div class="ml-4" data-v-8faa13fa${_scopeId}><p class="text-sm font-medium text-blue-600" data-v-8faa13fa${_scopeId}>Total Records</p><p class="text-2xl font-bold text-blue-900" data-v-8faa13fa${_scopeId}>${ssrInterpolate(props.reportData.total || 0)}</p></div></div></div><div class="bg-green-50 rounded-lg p-4" data-v-8faa13fa${_scopeId}><div class="flex items-center" data-v-8faa13fa${_scopeId}><div class="flex-shrink-0" data-v-8faa13fa${_scopeId}><div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center" data-v-8faa13fa${_scopeId}><svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" data-v-8faa13fa${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" data-v-8faa13fa${_scopeId}></path></svg></div></div><div class="ml-4" data-v-8faa13fa${_scopeId}><p class="text-sm font-medium text-green-600" data-v-8faa13fa${_scopeId}>Revenue</p><p class="text-2xl font-bold text-green-900" data-v-8faa13fa${_scopeId}>$${ssrInterpolate((props.reportData.revenue || 0).toLocaleString())}</p></div></div></div><div class="bg-yellow-50 rounded-lg p-4" data-v-8faa13fa${_scopeId}><div class="flex items-center" data-v-8faa13fa${_scopeId}><div class="flex-shrink-0" data-v-8faa13fa${_scopeId}><div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center" data-v-8faa13fa${_scopeId}><svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" data-v-8faa13fa${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" data-v-8faa13fa${_scopeId}></path></svg></div></div><div class="ml-4" data-v-8faa13fa${_scopeId}><p class="text-sm font-medium text-yellow-600" data-v-8faa13fa${_scopeId}>Average</p><p class="text-2xl font-bold text-yellow-900" data-v-8faa13fa${_scopeId}>$${ssrInterpolate((props.reportData.average || 0).toLocaleString())}</p></div></div></div></div>`);
              if (props.reportData.data && props.reportData.data.length > 0) {
                _push2(`<div class="overflow-x-auto" data-v-8faa13fa${_scopeId}><table class="min-w-full divide-y divide-gray-200" data-v-8faa13fa${_scopeId}><thead class="bg-gray-50" data-v-8faa13fa${_scopeId}><tr data-v-8faa13fa${_scopeId}><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-v-8faa13fa${_scopeId}> Date </th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-v-8faa13fa${_scopeId}> Module </th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-v-8faa13fa${_scopeId}> Records </th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-v-8faa13fa${_scopeId}> Revenue </th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-v-8faa13fa${_scopeId}> Status </th></tr></thead><tbody class="bg-white divide-y divide-gray-200" data-v-8faa13fa${_scopeId}><!--[-->`);
                ssrRenderList(props.reportData.data, (item, index) => {
                  _push2(`<tr class="hover:bg-gray-50" data-v-8faa13fa${_scopeId}><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" data-v-8faa13fa${_scopeId}>${ssrInterpolate(new Date(item.date).toLocaleDateString())}</td><td class="px-6 py-4 whitespace-nowrap" data-v-8faa13fa${_scopeId}><span class="${ssrRenderClass([{
                    "bg-blue-100 text-blue-800": item.module === "pathology",
                    "bg-green-100 text-green-800": item.module === "radiology",
                    "bg-yellow-100 text-yellow-800": item.module === "medicine",
                    "bg-purple-100 text-purple-800": item.module === "opd",
                    "bg-pink-100 text-pink-800": item.module === "ipd",
                    "bg-gray-100 text-gray-800": !item.module
                  }, "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"])}" data-v-8faa13fa${_scopeId}>${ssrInterpolate(item.module ? item.module.charAt(0).toUpperCase() + item.module.slice(1) : "N/A")}</span></td><td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900" data-v-8faa13fa${_scopeId}>${ssrInterpolate(item.records || 0)}</td><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" data-v-8faa13fa${_scopeId}> $${ssrInterpolate((item.revenue || 0).toLocaleString())}</td><td class="px-6 py-4 whitespace-nowrap" data-v-8faa13fa${_scopeId}><span class="${ssrRenderClass([{
                    "bg-green-100 text-green-800": item.status === "completed",
                    "bg-yellow-100 text-yellow-800": item.status === "pending",
                    "bg-red-100 text-red-800": item.status === "failed",
                    "bg-gray-100 text-gray-800": !item.status
                  }, "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"])}" data-v-8faa13fa${_scopeId}>${ssrInterpolate(item.status ? item.status.charAt(0).toUpperCase() + item.status.slice(1) : "N/A")}</span></td></tr>`);
                });
                _push2(`<!--]--></tbody></table></div>`);
              } else {
                _push2(`<div class="text-center py-12" data-v-8faa13fa${_scopeId}><svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" data-v-8faa13fa${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" data-v-8faa13fa${_scopeId}></path></svg><h3 class="mt-2 text-sm font-medium text-gray-900" data-v-8faa13fa${_scopeId}>No data available</h3><p class="mt-1 text-sm text-gray-500" data-v-8faa13fa${_scopeId}>No records found for the selected criteria.</p></div>`);
              }
              _push2(`</div></div>`);
            } else if (hasFiltered.value && !__props.hasData) {
              _push2(`<div class="text-center py-12 bg-gray-50 rounded-lg border border-gray-200" data-v-8faa13fa${_scopeId}><svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" data-v-8faa13fa${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" data-v-8faa13fa${_scopeId}></path></svg><h3 class="mt-2 text-sm font-medium text-gray-900" data-v-8faa13fa${_scopeId}>No results found</h3><p class="mt-1 text-sm text-gray-500" data-v-8faa13fa${_scopeId}>Try adjusting your filters to see more results.</p><button class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200" data-v-8faa13fa${_scopeId}> Clear filters </button></div>`);
            } else {
              _push2(`<div class="text-center py-12 bg-gray-50 rounded-lg border border-gray-200" data-v-8faa13fa${_scopeId}><svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" data-v-8faa13fa${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" data-v-8faa13fa${_scopeId}></path></svg><h3 class="mt-2 text-sm font-medium text-gray-900" data-v-8faa13fa${_scopeId}>Generate a report</h3><p class="mt-1 text-sm text-gray-500" data-v-8faa13fa${_scopeId}>Select your filters and click &quot;Generate Report&quot; to get started.</p></div>`);
            }
            _push2(`</div></div>`);
          } else {
            return [
              createVNode("div", { class: "report-generator" }, [
                createVNode("div", { class: "bg-white rounded-lg shadow-sm border border-gray-200 p-6" }, [
                  createVNode("div", { class: "mb-6" }, [
                    createVNode("h2", { class: "text-2xl font-bold text-gray-800 mb-2" }, "Report Generator"),
                    createVNode("p", { class: "text-gray-600" }, "Generate reports by selecting date range and module")
                  ]),
                  createVNode("div", { class: "bg-gray-50 rounded-lg p-4 mb-6" }, [
                    createVNode("div", { class: "flex flex-wrap items-end gap-4 mb-4" }, [
                      createVNode("div", { class: "flex-1 min-w-[250px] relative date-picker-container" }, [
                        createVNode("label", { class: "block text-sm font-medium text-gray-700 mb-1" }, toDisplayString(selectionMode.value === "single" ? "Date" : "Date Range"), 1),
                        createVNode("div", { class: "relative" }, [
                          createVNode("input", {
                            type: "text",
                            readonly: "",
                            value: dateRangeDisplay.value,
                            onClick: toggleDatePicker,
                            class: "w-full px-3 py-2 pr-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent cursor-pointer bg-white",
                            placeholder: selectionMode.value === "single" ? "Select date" : "Select date range"
                          }, null, 8, ["value", "placeholder"]),
                          createVNode("div", { class: "absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none" }, [
                            (openBlock(), createBlock("svg", {
                              class: "w-5 h-5 text-gray-400",
                              fill: "none",
                              stroke: "currentColor",
                              viewBox: "0 0 24 24"
                            }, [
                              createVNode("path", {
                                "stroke-linecap": "round",
                                "stroke-linejoin": "round",
                                "stroke-width": "2",
                                d: "M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                              })
                            ]))
                          ])
                        ]),
                        showDatePicker.value ? (openBlock(), createBlock("div", {
                          key: 0,
                          class: "absolute z-10 mt-1 bg-white border border-gray-200 rounded-lg shadow-xl min-w-[680px] max-w-[800px]"
                        }, [
                          createVNode("div", { class: "flex border-b border-gray-200 bg-gray-50 rounded-t-lg" }, [
                            createVNode("button", {
                              onClick: switchToSingleMode,
                              class: [
                                "flex-1 px-4 py-3 text-sm font-medium transition-colors relative",
                                selectionMode.value === "single" ? "bg-white text-blue-600 border-b-2 border-blue-600 shadow-sm" : "text-gray-600 hover:text-gray-900 hover:bg-gray-100"
                              ]
                            }, [
                              createVNode("span", { class: "flex items-center justify-center gap-2" }, [
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
                                    d: "M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                  })
                                ])),
                                createTextVNode(" Single Date ")
                              ])
                            ], 2),
                            createVNode("button", {
                              onClick: switchToRangeMode,
                              class: [
                                "flex-1 px-4 py-3 text-sm font-medium transition-colors relative",
                                selectionMode.value === "range" ? "bg-white text-blue-600 border-b-2 border-blue-600 shadow-sm" : "text-gray-600 hover:text-gray-900 hover:bg-gray-100"
                              ]
                            }, [
                              createVNode("span", { class: "flex items-center justify-center gap-2" }, [
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
                                    d: "M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                  })
                                ])),
                                createTextVNode(" Date Range ")
                              ])
                            ], 2)
                          ]),
                          createVNode("div", { class: "flex" }, [
                            createVNode("div", { class: "flex-1 p-4" }, [
                              createVNode("div", { class: "flex items-center justify-between mb-4" }, [
                                createVNode("button", {
                                  onClick: prevMonth,
                                  class: "p-2 hover:bg-gray-100 rounded-full transition-colors"
                                }, [
                                  (openBlock(), createBlock("svg", {
                                    class: "w-5 h-5 text-gray-600",
                                    fill: "none",
                                    stroke: "currentColor",
                                    viewBox: "0 0 24 24"
                                  }, [
                                    createVNode("path", {
                                      "stroke-linecap": "round",
                                      "stroke-linejoin": "round",
                                      "stroke-width": "2",
                                      d: "M15 19l-7-7 7-7"
                                    })
                                  ]))
                                ]),
                                createVNode("h3", { class: "text-lg font-semibold text-gray-900" }, toDisplayString(monthNames[currentMonth.value]) + " " + toDisplayString(currentYear.value), 1),
                                createVNode("div", { class: "w-9" })
                              ]),
                              createVNode("div", { class: "grid grid-cols-7 gap-1 mb-2" }, [
                                createVNode("div", { class: "text-center text-xs font-medium text-gray-500 py-2" }, "Su"),
                                createVNode("div", { class: "text-center text-xs font-medium text-gray-500 py-2" }, "Mo"),
                                createVNode("div", { class: "text-center text-xs font-medium text-gray-500 py-2" }, "Tu"),
                                createVNode("div", { class: "text-center text-xs font-medium text-gray-500 py-2" }, "We"),
                                createVNode("div", { class: "text-center text-xs font-medium text-gray-500 py-2" }, "Th"),
                                createVNode("div", { class: "text-center text-xs font-medium text-gray-500 py-2" }, "Fr"),
                                createVNode("div", { class: "text-center text-xs font-medium text-gray-500 py-2" }, "Sa")
                              ]),
                              createVNode("div", { class: "grid grid-cols-7 gap-1" }, [
                                (openBlock(true), createBlock(Fragment, null, renderList(getDaysArray.value, (dayObj) => {
                                  return openBlock(), createBlock("button", {
                                    key: `${dayObj.date.getTime()}`,
                                    onClick: ($event) => selectDate(dayObj.date),
                                    class: [
                                      "w-9 h-9 text-sm flex items-center justify-center transition-all duration-200",
                                      {
                                        "text-gray-400": dayObj.isOtherMonth,
                                        "text-gray-900": dayObj.isCurrentMonth && !isDateSelected(dayObj.date) && !isDateInRange(dayObj.date),
                                        "bg-blue-600 text-white font-medium": isDateSelected(dayObj.date),
                                        "bg-blue-100 text-blue-600": isDateInRange(dayObj.date) && !isDateSelected(dayObj.date) && selectionMode.value === "range",
                                        "hover:bg-gray-100": !isDateSelected(dayObj.date) && !isDateInRange(dayObj.date) && dayObj.isCurrentMonth,
                                        "hover:bg-gray-50": !isDateSelected(dayObj.date) && !isDateInRange(dayObj.date) && dayObj.isOtherMonth,
                                        "rounded-l-full": isDateStart(dayObj.date) && filters.value.dateFrom !== filters.value.dateTo,
                                        "rounded-r-full": isDateEnd(dayObj.date) && filters.value.dateFrom !== filters.value.dateTo,
                                        "rounded-full": isDateSelected(dayObj.date) || isDateInRange(dayObj.date) && selectionMode.value === "single",
                                        "cursor-pointer": dayObj.isCurrentMonth
                                      }
                                    ]
                                  }, toDisplayString(dayObj.day), 11, ["onClick"]);
                                }), 128))
                              ])
                            ]),
                            createVNode("div", { class: "w-px bg-gray-200" }),
                            createVNode("div", { class: "flex-1 p-4" }, [
                              createVNode("div", { class: "flex items-center justify-between mb-4" }, [
                                createVNode("div", { class: "w-9" }),
                                createVNode("h3", { class: "text-lg font-semibold text-gray-900" }, toDisplayString(monthNames[currentMonth.value === 11 ? 0 : currentMonth.value + 1]) + " " + toDisplayString(currentMonth.value === 11 ? currentYear.value + 1 : currentYear.value), 1),
                                createVNode("button", {
                                  onClick: nextMonth,
                                  class: "p-2 hover:bg-gray-100 rounded-full transition-colors"
                                }, [
                                  (openBlock(), createBlock("svg", {
                                    class: "w-5 h-5 text-gray-600",
                                    fill: "none",
                                    stroke: "currentColor",
                                    viewBox: "0 0 24 24"
                                  }, [
                                    createVNode("path", {
                                      "stroke-linecap": "round",
                                      "stroke-linejoin": "round",
                                      "stroke-width": "2",
                                      d: "M9 5l7 7-7 7"
                                    })
                                  ]))
                                ])
                              ]),
                              createVNode("div", { class: "grid grid-cols-7 gap-1 mb-2" }, [
                                createVNode("div", { class: "text-center text-xs font-medium text-gray-500 py-2" }, "Su"),
                                createVNode("div", { class: "text-center text-xs font-medium text-gray-500 py-2" }, "Mo"),
                                createVNode("div", { class: "text-center text-xs font-medium text-gray-500 py-2" }, "Tu"),
                                createVNode("div", { class: "text-center text-xs font-medium text-gray-500 py-2" }, "We"),
                                createVNode("div", { class: "text-center text-xs font-medium text-gray-500 py-2" }, "Th"),
                                createVNode("div", { class: "text-center text-xs font-medium text-gray-500 py-2" }, "Fr"),
                                createVNode("div", { class: "text-center text-xs font-medium text-gray-500 py-2" }, "Sa")
                              ]),
                              createVNode("div", { class: "grid grid-cols-7 gap-1" }, [
                                (openBlock(true), createBlock(Fragment, null, renderList(getNextMonthDays.value, (dayObj) => {
                                  return openBlock(), createBlock("button", {
                                    key: `${dayObj.date.getTime()}`,
                                    onClick: ($event) => selectDate(dayObj.date),
                                    class: [
                                      "w-9 h-9 text-sm flex items-center justify-center transition-all duration-200",
                                      {
                                        "text-gray-400": dayObj.isOtherMonth,
                                        "text-gray-900": dayObj.isCurrentMonth && !isDateSelected(dayObj.date) && !isDateInRange(dayObj.date),
                                        "bg-blue-600 text-white font-medium": isDateSelected(dayObj.date),
                                        "bg-blue-100 text-blue-600": isDateInRange(dayObj.date) && !isDateSelected(dayObj.date) && selectionMode.value === "range",
                                        "hover:bg-gray-100": !isDateSelected(dayObj.date) && !isDateInRange(dayObj.date) && dayObj.isCurrentMonth,
                                        "hover:bg-gray-50": !isDateSelected(dayObj.date) && !isDateInRange(dayObj.date) && dayObj.isOtherMonth,
                                        "rounded-l-full": isDateStart(dayObj.date) && filters.value.dateFrom !== filters.value.dateTo,
                                        "rounded-r-full": isDateEnd(dayObj.date) && filters.value.dateFrom !== filters.value.dateTo,
                                        "rounded-full": isDateSelected(dayObj.date) || isDateInRange(dayObj.date) && selectionMode.value === "single",
                                        "cursor-pointer": dayObj.isCurrentMonth
                                      }
                                    ]
                                  }, toDisplayString(dayObj.day), 11, ["onClick"]);
                                }), 128))
                              ])
                            ])
                          ]),
                          createVNode("div", { class: "flex justify-between items-center p-4 border-t border-gray-200 bg-gray-50" }, [
                            createVNode("div", { class: "flex gap-2" }, [
                              createVNode("button", {
                                onClick: clearDates,
                                class: "px-4 py-2 text-sm text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-md transition-colors"
                              }, " Clear ")
                            ]),
                            createVNode("div", { class: "flex gap-2" }, [
                              createVNode("button", {
                                onClick: closeDatePicker,
                                class: "px-4 py-2 text-sm text-gray-600 hover:text-gray-800 border border-gray-300 rounded-md hover:bg-gray-100 transition-colors"
                              }, " Cancel "),
                              createVNode("button", {
                                onClick: closeDatePicker,
                                class: "px-4 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                              }, " Apply ")
                            ])
                          ])
                        ])) : createCommentVNode("", true)
                      ]),
                      createVNode("div", { class: "flex-1 min-w-[200px]" }, [
                        createVNode("label", { class: "block text-sm font-medium text-gray-700 mb-1" }, "Module"),
                        withDirectives(createVNode("select", {
                          "onUpdate:modelValue": ($event) => filters.value.module = $event,
                          class: "w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        }, [
                          createVNode("option", { value: "" }, "Select Module"),
                          (openBlock(), createBlock(Fragment, null, renderList(modules, (module) => {
                            return createVNode("option", {
                              key: module.id,
                              value: module.id
                            }, toDisplayString(module.name), 9, ["value"]);
                          }), 64))
                        ], 8, ["onUpdate:modelValue"]), [
                          [vModelSelect, filters.value.module]
                        ])
                      ]),
                      createVNode("div", { class: "flex gap-2" }, [
                        createVNode("button", {
                          onClick: applyFilter,
                          disabled: loading.value,
                          class: "px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 transition-colors"
                        }, [
                          loading.value ? (openBlock(), createBlock("svg", {
                            key: 0,
                            class: "animate-spin -ml-1 mr-2 h-4 w-4 text-white",
                            fill: "none",
                            viewBox: "0 0 24 24"
                          }, [
                            createVNode("circle", {
                              class: "opacity-25",
                              cx: "12",
                              cy: "12",
                              r: "10",
                              stroke: "currentColor",
                              "stroke-width": "4"
                            }),
                            createVNode("path", {
                              class: "opacity-75",
                              fill: "currentColor",
                              d: "M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            })
                          ])) : createCommentVNode("", true),
                          createTextVNode(" " + toDisplayString(loading.value ? "Generating..." : "Generate Report"), 1)
                        ], 8, ["disabled"]),
                        hasFiltered.value ? (openBlock(), createBlock("button", {
                          key: 0,
                          onClick: resetFilters,
                          class: "px-6 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                        }, " Reset ")) : createCommentVNode("", true)
                      ])
                    ])
                  ]),
                  __props.hasData && props.reportData ? (openBlock(), createBlock("div", {
                    key: 0,
                    class: "bg-white rounded-lg border border-gray-200"
                  }, [
                    createVNode("div", { class: "px-6 py-4 border-b border-gray-200 flex justify-between items-center" }, [
                      createVNode("h3", { class: "text-lg font-semibold text-gray-800" }, "Report Results"),
                      createVNode("button", {
                        onClick: downloadPdf,
                        disabled: downloadingPdf.value,
                        class: "px-4 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 transition-colors"
                      }, [
                        downloadingPdf.value ? (openBlock(), createBlock("svg", {
                          key: 0,
                          class: "animate-spin h-4 w-4",
                          fill: "none",
                          viewBox: "0 0 24 24"
                        }, [
                          createVNode("circle", {
                            class: "opacity-25",
                            cx: "12",
                            cy: "12",
                            r: "10",
                            stroke: "currentColor",
                            "stroke-width": "4"
                          }),
                          createVNode("path", {
                            class: "opacity-75",
                            fill: "currentColor",
                            d: "M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                          })
                        ])) : (openBlock(), createBlock("svg", {
                          key: 1,
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
                        createTextVNode(" " + toDisplayString(downloadingPdf.value ? "Generating PDF..." : "Download PDF"), 1)
                      ], 8, ["disabled"])
                    ]),
                    createVNode("div", { class: "p-6" }, [
                      createVNode("div", { class: "grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6" }, [
                        createVNode("div", { class: "bg-blue-50 rounded-lg p-4" }, [
                          createVNode("div", { class: "flex items-center" }, [
                            createVNode("div", { class: "flex-shrink-0" }, [
                              createVNode("div", { class: "w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center" }, [
                                (openBlock(), createBlock("svg", {
                                  class: "w-5 h-5 text-white",
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
                                ]))
                              ])
                            ]),
                            createVNode("div", { class: "ml-4" }, [
                              createVNode("p", { class: "text-sm font-medium text-blue-600" }, "Total Records"),
                              createVNode("p", { class: "text-2xl font-bold text-blue-900" }, toDisplayString(props.reportData.total || 0), 1)
                            ])
                          ])
                        ]),
                        createVNode("div", { class: "bg-green-50 rounded-lg p-4" }, [
                          createVNode("div", { class: "flex items-center" }, [
                            createVNode("div", { class: "flex-shrink-0" }, [
                              createVNode("div", { class: "w-8 h-8 bg-green-500 rounded-md flex items-center justify-center" }, [
                                (openBlock(), createBlock("svg", {
                                  class: "w-5 h-5 text-white",
                                  fill: "none",
                                  stroke: "currentColor",
                                  viewBox: "0 0 24 24"
                                }, [
                                  createVNode("path", {
                                    "stroke-linecap": "round",
                                    "stroke-linejoin": "round",
                                    "stroke-width": "2",
                                    d: "M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                                  })
                                ]))
                              ])
                            ]),
                            createVNode("div", { class: "ml-4" }, [
                              createVNode("p", { class: "text-sm font-medium text-green-600" }, "Revenue"),
                              createVNode("p", { class: "text-2xl font-bold text-green-900" }, "$" + toDisplayString((props.reportData.revenue || 0).toLocaleString()), 1)
                            ])
                          ])
                        ]),
                        createVNode("div", { class: "bg-yellow-50 rounded-lg p-4" }, [
                          createVNode("div", { class: "flex items-center" }, [
                            createVNode("div", { class: "flex-shrink-0" }, [
                              createVNode("div", { class: "w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center" }, [
                                (openBlock(), createBlock("svg", {
                                  class: "w-5 h-5 text-white",
                                  fill: "none",
                                  stroke: "currentColor",
                                  viewBox: "0 0 24 24"
                                }, [
                                  createVNode("path", {
                                    "stroke-linecap": "round",
                                    "stroke-linejoin": "round",
                                    "stroke-width": "2",
                                    d: "M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"
                                  })
                                ]))
                              ])
                            ]),
                            createVNode("div", { class: "ml-4" }, [
                              createVNode("p", { class: "text-sm font-medium text-yellow-600" }, "Average"),
                              createVNode("p", { class: "text-2xl font-bold text-yellow-900" }, "$" + toDisplayString((props.reportData.average || 0).toLocaleString()), 1)
                            ])
                          ])
                        ])
                      ]),
                      props.reportData.data && props.reportData.data.length > 0 ? (openBlock(), createBlock("div", {
                        key: 0,
                        class: "overflow-x-auto"
                      }, [
                        createVNode("table", { class: "min-w-full divide-y divide-gray-200" }, [
                          createVNode("thead", { class: "bg-gray-50" }, [
                            createVNode("tr", null, [
                              createVNode("th", { class: "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" }, " Date "),
                              createVNode("th", { class: "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" }, " Module "),
                              createVNode("th", { class: "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" }, " Records "),
                              createVNode("th", { class: "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" }, " Revenue "),
                              createVNode("th", { class: "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" }, " Status ")
                            ])
                          ]),
                          createVNode("tbody", { class: "bg-white divide-y divide-gray-200" }, [
                            (openBlock(true), createBlock(Fragment, null, renderList(props.reportData.data, (item, index) => {
                              return openBlock(), createBlock("tr", {
                                key: index,
                                class: "hover:bg-gray-50"
                              }, [
                                createVNode("td", { class: "px-6 py-4 whitespace-nowrap text-sm text-gray-900" }, toDisplayString(new Date(item.date).toLocaleDateString()), 1),
                                createVNode("td", { class: "px-6 py-4 whitespace-nowrap" }, [
                                  createVNode("span", {
                                    class: ["inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium", {
                                      "bg-blue-100 text-blue-800": item.module === "pathology",
                                      "bg-green-100 text-green-800": item.module === "radiology",
                                      "bg-yellow-100 text-yellow-800": item.module === "medicine",
                                      "bg-purple-100 text-purple-800": item.module === "opd",
                                      "bg-pink-100 text-pink-800": item.module === "ipd",
                                      "bg-gray-100 text-gray-800": !item.module
                                    }]
                                  }, toDisplayString(item.module ? item.module.charAt(0).toUpperCase() + item.module.slice(1) : "N/A"), 3)
                                ]),
                                createVNode("td", { class: "px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900" }, toDisplayString(item.records || 0), 1),
                                createVNode("td", { class: "px-6 py-4 whitespace-nowrap text-sm text-gray-900" }, " $" + toDisplayString((item.revenue || 0).toLocaleString()), 1),
                                createVNode("td", { class: "px-6 py-4 whitespace-nowrap" }, [
                                  createVNode("span", {
                                    class: ["inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium", {
                                      "bg-green-100 text-green-800": item.status === "completed",
                                      "bg-yellow-100 text-yellow-800": item.status === "pending",
                                      "bg-red-100 text-red-800": item.status === "failed",
                                      "bg-gray-100 text-gray-800": !item.status
                                    }]
                                  }, toDisplayString(item.status ? item.status.charAt(0).toUpperCase() + item.status.slice(1) : "N/A"), 3)
                                ])
                              ]);
                            }), 128))
                          ])
                        ])
                      ])) : (openBlock(), createBlock("div", {
                        key: 1,
                        class: "text-center py-12"
                      }, [
                        (openBlock(), createBlock("svg", {
                          class: "mx-auto h-12 w-12 text-gray-400",
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
                        createVNode("h3", { class: "mt-2 text-sm font-medium text-gray-900" }, "No data available"),
                        createVNode("p", { class: "mt-1 text-sm text-gray-500" }, "No records found for the selected criteria.")
                      ]))
                    ])
                  ])) : hasFiltered.value && !__props.hasData ? (openBlock(), createBlock("div", {
                    key: 1,
                    class: "text-center py-12 bg-gray-50 rounded-lg border border-gray-200"
                  }, [
                    (openBlock(), createBlock("svg", {
                      class: "mx-auto h-12 w-12 text-gray-400",
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
                    createVNode("h3", { class: "mt-2 text-sm font-medium text-gray-900" }, "No results found"),
                    createVNode("p", { class: "mt-1 text-sm text-gray-500" }, "Try adjusting your filters to see more results."),
                    createVNode("button", {
                      onClick: resetFilters,
                      class: "mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200"
                    }, " Clear filters ")
                  ])) : (openBlock(), createBlock("div", {
                    key: 2,
                    class: "text-center py-12 bg-gray-50 rounded-lg border border-gray-200"
                  }, [
                    (openBlock(), createBlock("svg", {
                      class: "mx-auto h-12 w-12 text-gray-400",
                      fill: "none",
                      stroke: "currentColor",
                      viewBox: "0 0 24 24"
                    }, [
                      createVNode("path", {
                        "stroke-linecap": "round",
                        "stroke-linejoin": "round",
                        "stroke-width": "2",
                        d: "M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                      })
                    ])),
                    createVNode("h3", { class: "mt-2 text-sm font-medium text-gray-900" }, "Generate a report"),
                    createVNode("p", { class: "mt-1 text-sm text-gray-500" }, 'Select your filters and click "Generate Report" to get started.')
                  ]))
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/Report/Index.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const Index = /* @__PURE__ */ _export_sfc(_sfc_main, [["__scopeId", "data-v-8faa13fa"]]);
export {
  Index as default
};
