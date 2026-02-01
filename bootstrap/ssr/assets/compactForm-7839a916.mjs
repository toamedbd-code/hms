import { ref, computed, onMounted, watch, withCtx, unref, createTextVNode, toDisplayString, createVNode, withDirectives, openBlock, createBlock, Fragment, renderList, vModelSelect, vModelText, vModelCheckbox, createCommentVNode, withModifiers, useSSRContext } from "vue";
import { ssrRenderComponent, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderList, ssrRenderAttr, ssrInterpolate, ssrRenderClass, ssrRenderStyle } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import { useForm } from "@inertiajs/vue3";
import { _ as _sfc_main$2 } from "./InputError-6aeb8d97.mjs";
import "./InputLabel-70ca52d1.mjs";
import { _ as _sfc_main$3 } from "./PrimaryButton-b82fb16e.mjs";
import { A as AlertMessage } from "./AlertMessage-0f422981.mjs";
import { d as displayResponse, a as displayWarning } from "./responseMessage-d505224b.mjs";
import { _ as _export_sfc } from "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "mitt";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
import "toastr";
import "sweetalert2";
const compactForm_vue_vue_type_style_index_0_scoped_80d98db5_lang = "";
const _sfc_main = {
  __name: "compactForm",
  __ssrInlineRender: true,
  props: ["pathology", "id", "patients", "doctors", "billnumber", "pathologyTests"],
  setup(__props) {
    var _a, _b, _c, _d, _e, _f, _g, _h, _i, _j, _k, _l, _m, _n, _o, _p, _q, _r, _s, _t, _u, _v, _w, _x, _y;
    const props = __props;
    const createTestRow = () => ({
      id: Date.now() + Math.random(),
      testId: "",
      testName: "",
      reportDays: "",
      reportDate: "",
      tax: "",
      amount: ""
    });
    const testRows = ref([createTestRow()]);
    const form = useForm({
      case_id: ((_a = props.pathology) == null ? void 0 : _a.case_id) ?? "",
      patient_id: ((_b = props.pathology) == null ? void 0 : _b.patient_id) ?? "",
      doctor_id: ((_c = props.pathology) == null ? void 0 : _c.doctor_id) ?? "",
      apply_tpa: Boolean((_d = props.pathology) == null ? void 0 : _d.apply_tpa) || false,
      bill_no: ((_e = props.pathology) == null ? void 0 : _e.bill_no) ?? "",
      date: ((_f = props.pathology) == null ? void 0 : _f.date) ?? (/* @__PURE__ */ new Date()).toISOString().split("T")[0],
      tests: [],
      note: ((_g = props.pathology) == null ? void 0 : _g.note) ?? "",
      payee: ((_h = props.pathology) == null ? void 0 : _h.payee) ?? "",
      commission_percentage: ((_i = props.pathology) == null ? void 0 : _i.commission_percentage) ?? "",
      commission_amount: ((_j = props.pathology) == null ? void 0 : _j.commission_amount) ?? "",
      subtotal: ((_k = props.pathology) == null ? void 0 : _k.subtotal) ?? 0,
      discount_percentage: ((_l = props.pathology) == null ? void 0 : _l.discount_percentage) ?? 0,
      discount_amount: ((_m = props.pathology) == null ? void 0 : _m.discount_amount) ?? 0,
      vat_percentage: ((_n = props.pathology) == null ? void 0 : _n.vat_percentage) ?? 0,
      vat_amount: ((_o = props.pathology) == null ? void 0 : _o.vat_amount) ?? 0,
      tax_percentage: ((_p = props.pathology) == null ? void 0 : _p.tax_percentage) ?? 0,
      tax_amount: ((_q = props.pathology) == null ? void 0 : _q.tax_amount) ?? 0,
      extra_vat_percentage: ((_r = props.pathology) == null ? void 0 : _r.extra_vat_percentage) ?? 0,
      extra_vat_amount: ((_s = props.pathology) == null ? void 0 : _s.extra_vat_amount) ?? 0,
      extra_discount: ((_t = props.pathology) == null ? void 0 : _t.extra_discount) ?? 0,
      net_amount: ((_u = props.pathology) == null ? void 0 : _u.net_amount) ?? 0,
      payment_mode: ((_v = props.pathology) == null ? void 0 : _v.payment_mode) ?? "Cash",
      payment_amount: ((_w = props.pathology) == null ? void 0 : _w.payment_amount) ?? 0,
      doctor_name: ((_x = props.pathology) == null ? void 0 : _x.doctor_name) ?? "",
      _method: ((_y = props.pathology) == null ? void 0 : _y.id) ? "put" : "post"
    });
    const onTestChange = (testRowIndex, selectedTestId) => {
      const selectedTest = props.pathologyTests.find((test) => test.id == selectedTestId);
      if (selectedTest) {
        testRows.value[testRowIndex].testId = selectedTestId;
        testRows.value[testRowIndex].testName = selectedTest.test_name;
        testRows.value[testRowIndex].reportDays = selectedTest.report_days || "";
        testRows.value[testRowIndex].tax = selectedTest.tax || "";
        testRows.value[testRowIndex].amount = selectedTest.amount || selectedTest.standard_charge || "";
        if (selectedTest.report_days) {
          const today = /* @__PURE__ */ new Date();
          const reportDate = new Date(today.getTime() + selectedTest.report_days * 24 * 60 * 60 * 1e3);
          testRows.value[testRowIndex].reportDate = reportDate.toISOString().split("T")[0];
        }
      }
    };
    const generateBillNumber = async () => {
      try {
        const lastBillNumber = props.billnumber ? props.billnumber : "";
        let newNumber = 1;
        if (lastBillNumber && lastBillNumber.startsWith("PATB")) {
          const numberPart = parseInt(lastBillNumber.replace("PATB", ""), 10);
          if (!isNaN(numberPart)) {
            newNumber = numberPart + 1;
          }
        }
        return `PATB${newNumber}`;
      } catch (error) {
        console.error("Error generating bill number:", error);
        return "PATB1";
      }
    };
    const generateCaseId = () => {
      const timestamp = Date.now().toString().slice(-6);
      const random = Math.floor(Math.random() * 1e3).toString().padStart(3, "0");
      return `CASE${timestamp}${random}`;
    };
    const billStatus = computed(() => {
      var _a2;
      if ((_a2 = props.pathology) == null ? void 0 : _a2.bill_no) {
        return {
          text: props.pathology.bill_no,
          class: "text-blue-600 font-medium",
          showSpinner: false
        };
      }
      if (form.bill_no) {
        return {
          text: form.bill_no,
          class: "text-blue-600 font-medium",
          showSpinner: false
        };
      }
      return {
        text: "Generating...",
        class: "text-amber-600 font-medium",
        showSpinner: true
      };
    });
    const caseStatus = computed(() => {
      if (form.case_id) {
        return {
          text: form.case_id,
          class: "text-blue-600 font-medium",
          showSpinner: false
        };
      }
      return {
        text: "Generating...",
        class: "text-amber-600 font-medium",
        showSpinner: true
      };
    });
    onMounted(async () => {
      var _a2, _b2;
      if (!((_a2 = props.pathology) == null ? void 0 : _a2.id)) {
        form.case_id = generateCaseId();
        setTimeout(async () => {
          form.bill_no = await generateBillNumber();
        }, 1e3);
      }
      if (((_b2 = props.pathology) == null ? void 0 : _b2.tests) && props.pathology.tests.length > 0) {
        testRows.value = props.pathology.tests.map((test) => ({
          id: test.id || Date.now() + Math.random(),
          testId: test.testId || "",
          testName: test.test_name || "",
          reportDays: test.report_days || "",
          reportDate: test.report_date || "",
          tax: test.tax || "",
          amount: test.amount || ""
        }));
      }
      updateCalculations();
    });
    const updateCalculations = () => {
      let subtotal = 0;
      testRows.value.forEach((test) => {
        const amount = parseFloat(test.amount) || 0;
        subtotal += amount;
      });
      form.subtotal = parseFloat(subtotal.toFixed(2));
      const discount_percentage = parseFloat(form.discount_percentage) || 0;
      form.discount_amount = parseFloat((subtotal * discount_percentage / 100).toFixed(2));
      const vat_percentage = parseFloat(form.vat_percentage) || 0;
      const afterDiscount = subtotal - form.discount_amount;
      form.vat_amount = parseFloat((afterDiscount * vat_percentage / 100).toFixed(2));
      const tax_percentage = parseFloat(form.tax_percentage) || 0;
      form.tax_amount = parseFloat((afterDiscount * tax_percentage / 100).toFixed(2));
      const extra_vat_percentage = parseFloat(form.extra_vat_percentage) || 0;
      form.extra_vat_amount = parseFloat((afterDiscount * extra_vat_percentage / 100).toFixed(2));
      const extra_discount = parseFloat(form.extra_discount) || 0;
      form.net_amount = parseFloat((subtotal - form.discount_amount + form.vat_amount + form.tax_amount + form.extra_vat_amount - extra_discount).toFixed(2));
      if (form.net_amount < 0) {
        form.net_amount = 0;
      }
    };
    watch(() => form.discount_percentage, updateCalculations);
    watch(() => form.vat_percentage, updateCalculations);
    watch(() => form.tax_percentage, updateCalculations);
    watch(() => form.extra_vat_percentage, updateCalculations);
    watch(() => form.extra_discount, updateCalculations);
    watch(testRows, () => {
      updateFormTests();
      updateCalculations();
    }, { deep: true });
    const updateFormTests = () => {
      form.tests = testRows.value.map((test) => ({
        testId: test.testId,
        testName: test.testName,
        reportDays: test.reportDays,
        reportDate: test.reportDate,
        tax: test.tax,
        amount: test.amount
      }));
    };
    const submit = () => {
      updateFormTests();
      const routeName = props.id ? route("backend.pathology.update", props.id) : route("backend.pathology.store");
      form.transform((data) => ({
        ...data,
        remember: "",
        isDirty: false
      })).post(routeName, {
        onSuccess: (response) => {
          if (!props.id) {
            form.reset();
            testRows.value = [createTestRow()];
          }
          displayResponse(response);
        },
        onError: (errorObject) => {
          displayWarning(errorObject);
        }
      });
    };
    const addTest = () => {
      testRows.value.push(createTestRow());
      console.log("Test row added. Total rows:", testRows.value.length);
    };
    const removeTest = (testId) => {
      if (testRows.value.length > 1) {
        testRows.value = testRows.value.filter((test) => test.id !== testId);
        console.log("Test row removed. Remaining rows:", testRows.value.length);
      }
    };
    watch(() => form.commission_percentage, (newVal) => {
      if (newVal && form.net_amount) {
        const percentage = parseFloat(newVal) || 0;
        const net_amount = parseFloat(form.net_amount) || 0;
        form.commission_amount = parseFloat((net_amount * percentage / 100).toFixed(2));
      }
    });
    watch(() => form.net_amount, (newVal) => {
      if (newVal) {
        form.payment_amount = parseFloat(newVal).toFixed(2) || 0;
      } else {
        form.payment_amount = "";
      }
    });
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="w-full mt-2 bg-white border border-gray-300 rounded-md shadow-lg" data-v-80d98db5${_scopeId}><div class="flex items-center justify-between w-full px-3 py-1.5 bg-gray-100 border-b border-gray-300" data-v-80d98db5${_scopeId}><div class="flex items-center space-x-3" data-v-80d98db5${_scopeId}><div class="relative min-w-[260px]" data-v-80d98db5${_scopeId}><select id="patient_id" class="block w-full p-1 text-xs rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-80d98db5${_scopeId}><option value="" data-v-80d98db5${ssrIncludeBooleanAttr(Array.isArray(unref(form).patient_id) ? ssrLooseContain(unref(form).patient_id, "") : ssrLooseEqual(unref(form).patient_id, "")) ? " selected" : ""}${_scopeId}>Select Patient</option><!--[-->`);
            ssrRenderList(__props.patients, (data) => {
              _push2(`<option${ssrRenderAttr("value", data.id)} data-v-80d98db5${ssrIncludeBooleanAttr(Array.isArray(unref(form).patient_id) ? ssrLooseContain(unref(form).patient_id, data.id) : ssrLooseEqual(unref(form).patient_id, data.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(data.name)}</option>`);
            });
            _push2(`<!--]--></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-0.5",
              message: unref(form).errors.patient_id
            }, null, _parent2, _scopeId));
            _push2(`</div><button class="px-2 py-1 text-xs text-white bg-blue-600 rounded hover:bg-blue-700" data-v-80d98db5${_scopeId}> + New Patient </button><div class="flex items-center" data-v-80d98db5${_scopeId}><input${ssrRenderAttr("value", unref(form).prescription_no)} type="text" placeholder="Prescription No" class="px-2 py-1 text-xs border border-gray-300 rounded-l focus:outline-none focus:border-blue-500" data-v-80d98db5${_scopeId}><button class="px-2 py-1 text-xs bg-gray-200 border border-l-0 border-gray-300 rounded-r hover:bg-gray-300" data-v-80d98db5${_scopeId}> 🔍 </button></div><div class="flex items-center h-6" data-v-80d98db5${_scopeId}><input${ssrIncludeBooleanAttr(Array.isArray(unref(form).apply_tpa) ? ssrLooseContain(unref(form).apply_tpa, null) : unref(form).apply_tpa) ? " checked" : ""} type="checkbox" id="apply_tpa" class="w-3 h-3 text-blue-600 border-gray-300 rounded focus:ring-blue-500" data-v-80d98db5${_scopeId}><label for="apply_tpa" class="ml-1 text-xs font-medium text-black" data-v-80d98db5${_scopeId}>Apply TPA</label></div></div></div><div class="flex items-center justify-between px-3 py-1.5 bg-gray-50 border-b border-gray-200" data-v-80d98db5${_scopeId}><div class="flex space-x-6" data-v-80d98db5${_scopeId}><div class="flex items-center space-x-2" data-v-80d98db5${_scopeId}><span class="text-xs font-semibold" data-v-80d98db5${_scopeId}>Bill No</span><div class="flex items-center" data-v-80d98db5${_scopeId}>`);
            if (billStatus.value.showSpinner) {
              _push2(`<svg class="animate-spin -ml-1 mr-1 h-3 w-3 text-amber-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" data-v-80d98db5${_scopeId}><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" data-v-80d98db5${_scopeId}></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" data-v-80d98db5${_scopeId}></path></svg>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<span class="${ssrRenderClass([billStatus.value.class, "text-xs"])}" data-v-80d98db5${_scopeId}>${ssrInterpolate(billStatus.value.text)}</span></div></div><div class="flex items-center space-x-2" data-v-80d98db5${_scopeId}><span class="text-xs font-semibold" data-v-80d98db5${_scopeId}>Case ID</span><div class="flex items-center" data-v-80d98db5${_scopeId}>`);
            if (caseStatus.value.showSpinner) {
              _push2(`<svg class="animate-spin -ml-1 mr-1 h-3 w-3 text-amber-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" data-v-80d98db5${_scopeId}><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" data-v-80d98db5${_scopeId}></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" data-v-80d98db5${_scopeId}></path></svg>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<span class="${ssrRenderClass([caseStatus.value.class, "text-xs"])}" data-v-80d98db5${_scopeId}>${ssrInterpolate(caseStatus.value.text)}</span></div></div></div><span class="text-xs text-gray-600" data-v-80d98db5${_scopeId}>Date ${ssrInterpolate((/* @__PURE__ */ new Date()).toLocaleDateString())}</span></div><form class="p-3" data-v-80d98db5${_scopeId}>`);
            _push2(ssrRenderComponent(AlertMessage, null, null, _parent2, _scopeId));
            _push2(`<div class="mb-3" data-v-80d98db5${_scopeId}><!--[-->`);
            ssrRenderList(testRows.value, (test, index) => {
              _push2(`<div class="mb-2" data-v-80d98db5${_scopeId}><div class="grid grid-cols-5 gap-3" data-v-80d98db5${_scopeId}><div data-v-80d98db5${_scopeId}><div class="mb-1" data-v-80d98db5${_scopeId}><span class="text-xs font-medium text-black" data-v-80d98db5${_scopeId}>Test Name</span><span class="text-red-500 ml-0.5 text-xs" data-v-80d98db5${_scopeId}>*</span></div><select class="block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none bg-white" data-v-80d98db5${_scopeId}><option value="" data-v-80d98db5${ssrIncludeBooleanAttr(Array.isArray(test.testId) ? ssrLooseContain(test.testId, "") : ssrLooseEqual(test.testId, "")) ? " selected" : ""}${_scopeId}>Select Test</option><!--[-->`);
              ssrRenderList(__props.pathologyTests, (pathTest) => {
                _push2(`<option${ssrRenderAttr("value", pathTest.id)} data-v-80d98db5${ssrIncludeBooleanAttr(Array.isArray(test.testId) ? ssrLooseContain(test.testId, pathTest.id) : ssrLooseEqual(test.testId, pathTest.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(pathTest.test_name)}</option>`);
              });
              _push2(`<!--]--></select>`);
              _push2(ssrRenderComponent(_sfc_main$2, {
                class: "mt-0.5 text-xs",
                message: unref(form).errors[`tests.${index}.testId`]
              }, null, _parent2, _scopeId));
              _push2(`</div><div data-v-80d98db5${_scopeId}><div class="mb-1" data-v-80d98db5${_scopeId}><span class="text-xs font-medium text-black" data-v-80d98db5${_scopeId}>Report Days</span></div><input${ssrRenderAttr("value", test.reportDays)} type="number" class="block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none" data-v-80d98db5${_scopeId}>`);
              _push2(ssrRenderComponent(_sfc_main$2, {
                class: "mt-0.5 text-xs",
                message: unref(form).errors[`tests.${index}.reportDays`]
              }, null, _parent2, _scopeId));
              _push2(`</div><div data-v-80d98db5${_scopeId}><div class="mb-1" data-v-80d98db5${_scopeId}><span class="text-xs font-medium text-black" data-v-80d98db5${_scopeId}>Report Date</span><span class="text-red-500 ml-0.5 text-xs" data-v-80d98db5${_scopeId}>*</span></div><input${ssrRenderAttr("value", test.reportDate)} type="date" class="block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none" data-v-80d98db5${_scopeId}>`);
              _push2(ssrRenderComponent(_sfc_main$2, {
                class: "mt-0.5 text-xs",
                message: unref(form).errors[`tests.${index}.reportDate`]
              }, null, _parent2, _scopeId));
              _push2(`</div><div data-v-80d98db5${_scopeId}><div class="mb-1" data-v-80d98db5${_scopeId}><span class="text-xs font-medium text-black" data-v-80d98db5${_scopeId}>Tax</span></div><div class="relative" data-v-80d98db5${_scopeId}><input${ssrRenderAttr("value", test.tax)} type="number" step="0.01" class="block w-full px-2 py-1 pr-5 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none" data-v-80d98db5${_scopeId}><span class="absolute right-2 top-1 text-xs text-gray-500" data-v-80d98db5${_scopeId}>%</span></div>`);
              _push2(ssrRenderComponent(_sfc_main$2, {
                class: "mt-0.5 text-xs",
                message: unref(form).errors[`tests.${index}.tax`]
              }, null, _parent2, _scopeId));
              _push2(`</div><div data-v-80d98db5${_scopeId}><div class="mb-1 flex justify-between items-center" data-v-80d98db5${_scopeId}><span class="text-xs font-medium text-black" data-v-80d98db5${_scopeId}>Amount (Tk.)</span><button type="button"${ssrIncludeBooleanAttr(testRows.value.length === 1) ? " disabled" : ""} class="${ssrRenderClass([testRows.value.length === 1 ? "text-gray-300 cursor-not-allowed" : "text-red-500 hover:text-red-700", "text-sm font-bold"])}" data-v-80d98db5${_scopeId}>×</button></div><input${ssrRenderAttr("value", test.amount)} type="number" step="0.01" class="block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none" data-v-80d98db5${_scopeId}>`);
              _push2(ssrRenderComponent(_sfc_main$2, {
                class: "mt-0.5 text-xs",
                message: unref(form).errors[`tests.${index}.amount`]
              }, null, _parent2, _scopeId));
              _push2(`</div></div></div>`);
            });
            _push2(`<!--]--></div><div class="mb-3" data-v-80d98db5${_scopeId}><button type="button" class="inline-flex items-center px-3 py-1 text-xs font-medium text-white bg-green-600 rounded hover:bg-green-700" data-v-80d98db5${_scopeId}><span class="mr-0.5" data-v-80d98db5${_scopeId}>+</span> Add Test </button></div><div class="grid grid-cols-2 gap-4 mb-3" data-v-80d98db5${_scopeId}><div class="space-y-3" data-v-80d98db5${_scopeId}><div class="grid grid-cols-1 gap-3" data-v-80d98db5${_scopeId}><div data-v-80d98db5${_scopeId}><div class="mb-1" data-v-80d98db5${_scopeId}><span class="text-xs font-medium text-black" data-v-80d98db5${_scopeId}>Referral Doctor</span></div><select id="referralDoctor" class="block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none bg-white" data-v-80d98db5${_scopeId}><option value="" data-v-80d98db5${ssrIncludeBooleanAttr(Array.isArray(unref(form).doctor_id) ? ssrLooseContain(unref(form).doctor_id, "") : ssrLooseEqual(unref(form).doctor_id, "")) ? " selected" : ""}${_scopeId}>Select Doctor</option><!--[-->`);
            ssrRenderList(__props.doctors, (data) => {
              _push2(`<option${ssrRenderAttr("value", data.id)} data-v-80d98db5${ssrIncludeBooleanAttr(Array.isArray(unref(form).doctor_id) ? ssrLooseContain(unref(form).doctor_id, data.id) : ssrLooseEqual(unref(form).doctor_id, data.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(data.name)}</option>`);
            });
            _push2(`<!--]--></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-0.5 text-xs",
              message: unref(form).errors.doctor_id
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="grid grid-cols-2 gap-3" data-v-80d98db5${_scopeId}><div data-v-80d98db5${_scopeId}><div class="mb-1" data-v-80d98db5${_scopeId}><span class="text-xs font-medium text-black" data-v-80d98db5${_scopeId}>Doctor Name</span></div><input id="doctor_name"${ssrRenderAttr("value", unref(form).doctor_name)} type="text" class="block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none" data-v-80d98db5${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-0.5 text-xs",
              message: unref(form).errors.doctor_name
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-80d98db5${_scopeId}><div class="mb-1" data-v-80d98db5${_scopeId}><span class="text-xs font-medium text-black" data-v-80d98db5${_scopeId}>Payee</span><span class="text-red-500 ml-0.5 text-xs" data-v-80d98db5${_scopeId}>*</span></div><select id="payee" class="block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none bg-white" data-v-80d98db5${_scopeId}><option value="" data-v-80d98db5${ssrIncludeBooleanAttr(Array.isArray(unref(form).payee) ? ssrLooseContain(unref(form).payee, "") : ssrLooseEqual(unref(form).payee, "")) ? " selected" : ""}${_scopeId}>Select Payee</option><option value="patient" data-v-80d98db5${ssrIncludeBooleanAttr(Array.isArray(unref(form).payee) ? ssrLooseContain(unref(form).payee, "patient") : ssrLooseEqual(unref(form).payee, "patient")) ? " selected" : ""}${_scopeId}>Patient</option><option value="insurance" data-v-80d98db5${ssrIncludeBooleanAttr(Array.isArray(unref(form).payee) ? ssrLooseContain(unref(form).payee, "insurance") : ssrLooseEqual(unref(form).payee, "insurance")) ? " selected" : ""}${_scopeId}>Insurance</option><option value="company" data-v-80d98db5${ssrIncludeBooleanAttr(Array.isArray(unref(form).payee) ? ssrLooseContain(unref(form).payee, "company") : ssrLooseEqual(unref(form).payee, "company")) ? " selected" : ""}${_scopeId}>Company</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-0.5 text-xs",
              message: unref(form).errors.payee
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="grid grid-cols-2 gap-3" data-v-80d98db5${_scopeId}><div data-v-80d98db5${_scopeId}><div class="mb-1" data-v-80d98db5${_scopeId}><span class="text-xs font-medium text-black" data-v-80d98db5${_scopeId}>Commission %</span><span class="text-red-500 ml-0.5 text-xs" data-v-80d98db5${_scopeId}>*</span></div><input id="commission_percentage"${ssrRenderAttr("value", unref(form).commission_percentage)} type="number" step="0.01" placeholder="Percentage" class="block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none" data-v-80d98db5${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-0.5 text-xs",
              message: unref(form).errors.commission_percentage
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-80d98db5${_scopeId}><div class="mb-1" data-v-80d98db5${_scopeId}><span class="text-xs font-medium text-black" data-v-80d98db5${_scopeId}>Commission Amount (Tk.)</span><span class="text-red-500 ml-0.5 text-xs" data-v-80d98db5${_scopeId}>*</span></div><input id="commission_amount"${ssrRenderAttr("value", unref(form).commission_amount)} type="number" step="0.01" placeholder="Commission Amount" class="block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none" data-v-80d98db5${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-0.5 text-xs",
              message: unref(form).errors.commission_amount
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div data-v-80d98db5${_scopeId}><div class="mb-1" data-v-80d98db5${_scopeId}><span class="text-xs font-medium text-black" data-v-80d98db5${_scopeId}>Note</span></div><textarea id="note" rows="4" class="block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none resize-none" data-v-80d98db5${_scopeId}>${ssrInterpolate(unref(form).note)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-0.5 text-xs",
              message: unref(form).errors.note
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="space-y-2" data-v-80d98db5${_scopeId}><div class="space-y-2" data-v-80d98db5${_scopeId}><div class="flex justify-between items-center" data-v-80d98db5${_scopeId}><span class="text-xs text-black" data-v-80d98db5${_scopeId}>Subtotal (Tk.)</span><span class="text-xs font-medium" data-v-80d98db5${_scopeId}>${ssrInterpolate((parseFloat(unref(form).subtotal) || 0).toFixed(2))}</span></div><div class="flex justify-between items-center" data-v-80d98db5${_scopeId}><span class="text-xs text-black" data-v-80d98db5${_scopeId}>Discount</span><div class="flex items-center space-x-1" data-v-80d98db5${_scopeId}><div class="flex items-center" data-v-80d98db5${_scopeId}><input${ssrRenderAttr("value", unref(form).discount_percentage)} type="number" step="0.01" placeholder="0" class="w-12 px-1 py-0.5 text-xs border border-gray-300 rounded-l focus:border-blue-500 focus:outline-none text-center" data-v-80d98db5${_scopeId}><span class="px-1 py-0.5 text-xs bg-gray-100 border border-l-0 border-gray-300 rounded-r" data-v-80d98db5${_scopeId}>%</span></div><span class="text-xs text-gray-400" data-v-80d98db5${_scopeId}>=</span><span class="text-xs font-medium" data-v-80d98db5${_scopeId}>${ssrInterpolate((parseFloat(unref(form).discount_amount) || 0).toFixed(2))}</span></div></div><div class="flex justify-between items-center" data-v-80d98db5${_scopeId}><span class="text-xs text-black" data-v-80d98db5${_scopeId}>VAT</span><div class="flex items-center space-x-1" data-v-80d98db5${_scopeId}><div class="flex items-center" data-v-80d98db5${_scopeId}><input${ssrRenderAttr("value", unref(form).vat_percentage)} type="number" step="0.01" placeholder="0" class="w-12 px-1 py-0.5 text-xs border border-gray-300 rounded-l focus:border-blue-500 focus:outline-none text-center" data-v-80d98db5${_scopeId}><span class="px-1 py-0.5 text-xs bg-gray-100 border border-l-0 border-gray-300 rounded-r" data-v-80d98db5${_scopeId}>%</span></div><span class="text-xs text-gray-400" data-v-80d98db5${_scopeId}>=</span><span class="text-xs font-medium" data-v-80d98db5${_scopeId}>${ssrInterpolate((parseFloat(unref(form).vat_amount) || 0).toFixed(2))}</span></div></div><div class="flex justify-between items-center" data-v-80d98db5${_scopeId}><span class="text-xs text-black" data-v-80d98db5${_scopeId}>Tax</span><div class="flex items-center space-x-1" data-v-80d98db5${_scopeId}><div class="flex items-center" data-v-80d98db5${_scopeId}><input${ssrRenderAttr("value", unref(form).tax_percentage)} type="number" step="0.01" placeholder="0" class="w-12 px-1 py-0.5 text-xs border border-gray-300 rounded-l focus:border-blue-500 focus:outline-none text-center" data-v-80d98db5${_scopeId}><span class="px-1 py-0.5 text-xs bg-gray-100 border border-l-0 border-gray-300 rounded-r" data-v-80d98db5${_scopeId}>%</span></div><span class="text-xs text-gray-400" data-v-80d98db5${_scopeId}>=</span><span class="text-xs font-medium" data-v-80d98db5${_scopeId}>${ssrInterpolate((parseFloat(unref(form).tax_amount) || 0).toFixed(2))}</span></div></div><div class="flex justify-between items-center" data-v-80d98db5${_scopeId}><span class="text-xs text-black" data-v-80d98db5${_scopeId}>Extra VAT</span><div class="flex items-center space-x-1" data-v-80d98db5${_scopeId}><div class="flex items-center" data-v-80d98db5${_scopeId}><input${ssrRenderAttr("value", unref(form).extra_vat_percentage)} type="number" step="0.01" placeholder="0" class="w-12 px-1 py-0.5 text-xs border border-gray-300 rounded-l focus:border-blue-500 focus:outline-none text-center" data-v-80d98db5${_scopeId}><span class="px-1 py-0.5 text-xs bg-gray-100 border border-l-0 border-gray-300 rounded-r" data-v-80d98db5${_scopeId}>%</span></div><span class="text-xs text-gray-400" data-v-80d98db5${_scopeId}>=</span><span class="text-sm font-medium" data-v-80d98db5${_scopeId}>${ssrInterpolate((parseFloat(unref(form).extra_vat_amount) || 0).toFixed(2))} Tk.</span></div></div><div class="flex justify-between items-center" data-v-80d98db5${_scopeId}><span class="text-sm text-black" data-v-80d98db5${_scopeId}>Extra Discount</span><div class="flex items-center space-x-2" data-v-80d98db5${_scopeId}><input${ssrRenderAttr("value", unref(form).extra_discount)} type="number" step="0.01" placeholder="0" class="w-20 px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none text-center" data-v-80d98db5${_scopeId}><span class="text-sm text-gray-500" data-v-80d98db5${_scopeId}>Tk.</span></div></div><div class="flex justify-between items-center border-t pt-3" data-v-80d98db5${_scopeId}><span class="text-sm font-medium text-black" data-v-80d98db5${_scopeId}>Net Amount (Tk.)</span><span class="text-lg font-bold text-green-600" data-v-80d98db5${_scopeId}>${ssrInterpolate((parseFloat(unref(form).net_amount) || 0).toFixed(2))}</span></div></div><div class="pt-4" data-v-80d98db5${_scopeId}><div class="flex justify-between items-center mb-3" data-v-80d98db5${_scopeId}><span class="text-sm font-medium text-black" data-v-80d98db5${_scopeId}>Payment Mode</span><div class="flex items-center" data-v-80d98db5${_scopeId}><span class="text-sm font-medium text-black" data-v-80d98db5${_scopeId}>Amount (Tk.)</span><span class="text-red-500 ml-1" style="${ssrRenderStyle({ "margin-right": "190px" })}" data-v-80d98db5${_scopeId}>*</span></div></div><div class="grid grid-cols-2 gap-3" data-v-80d98db5${_scopeId}><select class="px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none bg-white" data-v-80d98db5${_scopeId}><option value="Cash" data-v-80d98db5${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, "Cash") : ssrLooseEqual(unref(form).payment_mode, "Cash")) ? " selected" : ""}${_scopeId}>Cash</option><option value="Card" data-v-80d98db5${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, "Card") : ssrLooseEqual(unref(form).payment_mode, "Card")) ? " selected" : ""}${_scopeId}>Card</option><option value="Bank Transfer" data-v-80d98db5${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, "Bank Transfer") : ssrLooseEqual(unref(form).payment_mode, "Bank Transfer")) ? " selected" : ""}${_scopeId}>Bank Transfer</option></select><input${ssrRenderAttr("value", unref(form).payment_amount)} type="text" step="0.01" class="px-3 py-2 text-sm text-end border border-gray-300 rounded focus:border-blue-500 focus:outline-none" data-v-80d98db5${_scopeId}></div></div></div></div><div class="flex items-center justify-end" data-v-80d98db5${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              type: "submit",
              class: ["px-6 py-2", { "opacity-25": unref(form).processing }],
              disabled: unref(form).processing
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`${ssrInterpolate(props.id ?? false ? "Update" : "Save")}`);
                } else {
                  return [
                    createTextVNode(toDisplayString(props.id ?? false ? "Update" : "Save"), 1)
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</div></form></div>`);
          } else {
            return [
              createVNode("div", { class: "w-full mt-2 bg-white border border-gray-300 rounded-md shadow-lg" }, [
                createVNode("div", { class: "flex items-center justify-between w-full px-3 py-1.5 bg-gray-100 border-b border-gray-300" }, [
                  createVNode("div", { class: "flex items-center space-x-3" }, [
                    createVNode("div", { class: "relative min-w-[260px]" }, [
                      withDirectives(createVNode("select", {
                        id: "patient_id",
                        "onUpdate:modelValue": ($event) => unref(form).patient_id = $event,
                        class: "block w-full p-1 text-xs rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                      }, [
                        createVNode("option", { value: "" }, "Select Patient"),
                        (openBlock(true), createBlock(Fragment, null, renderList(__props.patients, (data) => {
                          return openBlock(), createBlock("option", {
                            key: data.id,
                            value: data.id
                          }, toDisplayString(data.name), 9, ["value"]);
                        }), 128))
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).patient_id]
                      ]),
                      createVNode(_sfc_main$2, {
                        class: "mt-0.5",
                        message: unref(form).errors.patient_id
                      }, null, 8, ["message"])
                    ]),
                    createVNode("button", { class: "px-2 py-1 text-xs text-white bg-blue-600 rounded hover:bg-blue-700" }, " + New Patient "),
                    createVNode("div", { class: "flex items-center" }, [
                      withDirectives(createVNode("input", {
                        "onUpdate:modelValue": ($event) => unref(form).prescription_no = $event,
                        type: "text",
                        placeholder: "Prescription No",
                        class: "px-2 py-1 text-xs border border-gray-300 rounded-l focus:outline-none focus:border-blue-500"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).prescription_no]
                      ]),
                      createVNode("button", { class: "px-2 py-1 text-xs bg-gray-200 border border-l-0 border-gray-300 rounded-r hover:bg-gray-300" }, " 🔍 ")
                    ]),
                    createVNode("div", { class: "flex items-center h-6" }, [
                      withDirectives(createVNode("input", {
                        "onUpdate:modelValue": ($event) => unref(form).apply_tpa = $event,
                        type: "checkbox",
                        id: "apply_tpa",
                        class: "w-3 h-3 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelCheckbox, unref(form).apply_tpa]
                      ]),
                      createVNode("label", {
                        for: "apply_tpa",
                        class: "ml-1 text-xs font-medium text-black"
                      }, "Apply TPA")
                    ])
                  ])
                ]),
                createVNode("div", { class: "flex items-center justify-between px-3 py-1.5 bg-gray-50 border-b border-gray-200" }, [
                  createVNode("div", { class: "flex space-x-6" }, [
                    createVNode("div", { class: "flex items-center space-x-2" }, [
                      createVNode("span", { class: "text-xs font-semibold" }, "Bill No"),
                      createVNode("div", { class: "flex items-center" }, [
                        billStatus.value.showSpinner ? (openBlock(), createBlock("svg", {
                          key: 0,
                          class: "animate-spin -ml-1 mr-1 h-3 w-3 text-amber-600",
                          xmlns: "http://www.w3.org/2000/svg",
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
                        createVNode("span", {
                          class: [billStatus.value.class, "text-xs"]
                        }, toDisplayString(billStatus.value.text), 3)
                      ])
                    ]),
                    createVNode("div", { class: "flex items-center space-x-2" }, [
                      createVNode("span", { class: "text-xs font-semibold" }, "Case ID"),
                      createVNode("div", { class: "flex items-center" }, [
                        caseStatus.value.showSpinner ? (openBlock(), createBlock("svg", {
                          key: 0,
                          class: "animate-spin -ml-1 mr-1 h-3 w-3 text-amber-600",
                          xmlns: "http://www.w3.org/2000/svg",
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
                        createVNode("span", {
                          class: [caseStatus.value.class, "text-xs"]
                        }, toDisplayString(caseStatus.value.text), 3)
                      ])
                    ])
                  ]),
                  createVNode("span", { class: "text-xs text-gray-600" }, "Date " + toDisplayString((/* @__PURE__ */ new Date()).toLocaleDateString()), 1)
                ]),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"]),
                  class: "p-3"
                }, [
                  createVNode(AlertMessage),
                  createVNode("div", { class: "mb-3" }, [
                    (openBlock(true), createBlock(Fragment, null, renderList(testRows.value, (test, index) => {
                      return openBlock(), createBlock("div", {
                        key: test.id,
                        class: "mb-2"
                      }, [
                        createVNode("div", { class: "grid grid-cols-5 gap-3" }, [
                          createVNode("div", null, [
                            createVNode("div", { class: "mb-1" }, [
                              createVNode("span", { class: "text-xs font-medium text-black" }, "Test Name"),
                              createVNode("span", { class: "text-red-500 ml-0.5 text-xs" }, "*")
                            ]),
                            withDirectives(createVNode("select", {
                              "onUpdate:modelValue": ($event) => test.testId = $event,
                              onChange: ($event) => onTestChange(index, test.testId),
                              class: "block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none bg-white"
                            }, [
                              createVNode("option", { value: "" }, "Select Test"),
                              (openBlock(true), createBlock(Fragment, null, renderList(__props.pathologyTests, (pathTest) => {
                                return openBlock(), createBlock("option", {
                                  key: pathTest.id,
                                  value: pathTest.id
                                }, toDisplayString(pathTest.test_name), 9, ["value"]);
                              }), 128))
                            ], 40, ["onUpdate:modelValue", "onChange"]), [
                              [vModelSelect, test.testId]
                            ]),
                            createVNode(_sfc_main$2, {
                              class: "mt-0.5 text-xs",
                              message: unref(form).errors[`tests.${index}.testId`]
                            }, null, 8, ["message"])
                          ]),
                          createVNode("div", null, [
                            createVNode("div", { class: "mb-1" }, [
                              createVNode("span", { class: "text-xs font-medium text-black" }, "Report Days")
                            ]),
                            withDirectives(createVNode("input", {
                              "onUpdate:modelValue": ($event) => test.reportDays = $event,
                              type: "number",
                              class: "block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none"
                            }, null, 8, ["onUpdate:modelValue"]), [
                              [vModelText, test.reportDays]
                            ]),
                            createVNode(_sfc_main$2, {
                              class: "mt-0.5 text-xs",
                              message: unref(form).errors[`tests.${index}.reportDays`]
                            }, null, 8, ["message"])
                          ]),
                          createVNode("div", null, [
                            createVNode("div", { class: "mb-1" }, [
                              createVNode("span", { class: "text-xs font-medium text-black" }, "Report Date"),
                              createVNode("span", { class: "text-red-500 ml-0.5 text-xs" }, "*")
                            ]),
                            withDirectives(createVNode("input", {
                              "onUpdate:modelValue": ($event) => test.reportDate = $event,
                              type: "date",
                              class: "block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none"
                            }, null, 8, ["onUpdate:modelValue"]), [
                              [vModelText, test.reportDate]
                            ]),
                            createVNode(_sfc_main$2, {
                              class: "mt-0.5 text-xs",
                              message: unref(form).errors[`tests.${index}.reportDate`]
                            }, null, 8, ["message"])
                          ]),
                          createVNode("div", null, [
                            createVNode("div", { class: "mb-1" }, [
                              createVNode("span", { class: "text-xs font-medium text-black" }, "Tax")
                            ]),
                            createVNode("div", { class: "relative" }, [
                              withDirectives(createVNode("input", {
                                "onUpdate:modelValue": ($event) => test.tax = $event,
                                type: "number",
                                step: "0.01",
                                class: "block w-full px-2 py-1 pr-5 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none"
                              }, null, 8, ["onUpdate:modelValue"]), [
                                [vModelText, test.tax]
                              ]),
                              createVNode("span", { class: "absolute right-2 top-1 text-xs text-gray-500" }, "%")
                            ]),
                            createVNode(_sfc_main$2, {
                              class: "mt-0.5 text-xs",
                              message: unref(form).errors[`tests.${index}.tax`]
                            }, null, 8, ["message"])
                          ]),
                          createVNode("div", null, [
                            createVNode("div", { class: "mb-1 flex justify-between items-center" }, [
                              createVNode("span", { class: "text-xs font-medium text-black" }, "Amount (Tk.)"),
                              createVNode("button", {
                                type: "button",
                                onClick: ($event) => removeTest(test.id),
                                disabled: testRows.value.length === 1,
                                class: [testRows.value.length === 1 ? "text-gray-300 cursor-not-allowed" : "text-red-500 hover:text-red-700", "text-sm font-bold"]
                              }, "×", 10, ["onClick", "disabled"])
                            ]),
                            withDirectives(createVNode("input", {
                              "onUpdate:modelValue": ($event) => test.amount = $event,
                              type: "number",
                              step: "0.01",
                              class: "block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none"
                            }, null, 8, ["onUpdate:modelValue"]), [
                              [vModelText, test.amount]
                            ]),
                            createVNode(_sfc_main$2, {
                              class: "mt-0.5 text-xs",
                              message: unref(form).errors[`tests.${index}.amount`]
                            }, null, 8, ["message"])
                          ])
                        ])
                      ]);
                    }), 128))
                  ]),
                  createVNode("div", { class: "mb-3" }, [
                    createVNode("button", {
                      type: "button",
                      onClick: addTest,
                      class: "inline-flex items-center px-3 py-1 text-xs font-medium text-white bg-green-600 rounded hover:bg-green-700"
                    }, [
                      createVNode("span", { class: "mr-0.5" }, "+"),
                      createTextVNode(" Add Test ")
                    ])
                  ]),
                  createVNode("div", { class: "grid grid-cols-2 gap-4 mb-3" }, [
                    createVNode("div", { class: "space-y-3" }, [
                      createVNode("div", { class: "grid grid-cols-1 gap-3" }, [
                        createVNode("div", null, [
                          createVNode("div", { class: "mb-1" }, [
                            createVNode("span", { class: "text-xs font-medium text-black" }, "Referral Doctor")
                          ]),
                          withDirectives(createVNode("select", {
                            id: "referralDoctor",
                            "onUpdate:modelValue": ($event) => unref(form).doctor_id = $event,
                            class: "block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none bg-white"
                          }, [
                            createVNode("option", { value: "" }, "Select Doctor"),
                            (openBlock(true), createBlock(Fragment, null, renderList(__props.doctors, (data) => {
                              return openBlock(), createBlock("option", {
                                key: data.id,
                                value: data.id
                              }, toDisplayString(data.name), 9, ["value"]);
                            }), 128))
                          ], 8, ["onUpdate:modelValue"]), [
                            [vModelSelect, unref(form).doctor_id]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-0.5 text-xs",
                            message: unref(form).errors.doctor_id
                          }, null, 8, ["message"])
                        ])
                      ]),
                      createVNode("div", { class: "grid grid-cols-2 gap-3" }, [
                        createVNode("div", null, [
                          createVNode("div", { class: "mb-1" }, [
                            createVNode("span", { class: "text-xs font-medium text-black" }, "Doctor Name")
                          ]),
                          withDirectives(createVNode("input", {
                            id: "doctor_name",
                            "onUpdate:modelValue": ($event) => unref(form).doctor_name = $event,
                            type: "text",
                            class: "block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none"
                          }, null, 8, ["onUpdate:modelValue"]), [
                            [vModelText, unref(form).doctor_name]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-0.5 text-xs",
                            message: unref(form).errors.doctor_name
                          }, null, 8, ["message"])
                        ]),
                        createVNode("div", null, [
                          createVNode("div", { class: "mb-1" }, [
                            createVNode("span", { class: "text-xs font-medium text-black" }, "Payee"),
                            createVNode("span", { class: "text-red-500 ml-0.5 text-xs" }, "*")
                          ]),
                          withDirectives(createVNode("select", {
                            id: "payee",
                            "onUpdate:modelValue": ($event) => unref(form).payee = $event,
                            class: "block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none bg-white"
                          }, [
                            createVNode("option", { value: "" }, "Select Payee"),
                            createVNode("option", { value: "patient" }, "Patient"),
                            createVNode("option", { value: "insurance" }, "Insurance"),
                            createVNode("option", { value: "company" }, "Company")
                          ], 8, ["onUpdate:modelValue"]), [
                            [vModelSelect, unref(form).payee]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-0.5 text-xs",
                            message: unref(form).errors.payee
                          }, null, 8, ["message"])
                        ])
                      ]),
                      createVNode("div", { class: "grid grid-cols-2 gap-3" }, [
                        createVNode("div", null, [
                          createVNode("div", { class: "mb-1" }, [
                            createVNode("span", { class: "text-xs font-medium text-black" }, "Commission %"),
                            createVNode("span", { class: "text-red-500 ml-0.5 text-xs" }, "*")
                          ]),
                          withDirectives(createVNode("input", {
                            id: "commission_percentage",
                            "onUpdate:modelValue": ($event) => unref(form).commission_percentage = $event,
                            type: "number",
                            step: "0.01",
                            placeholder: "Percentage",
                            class: "block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none"
                          }, null, 8, ["onUpdate:modelValue"]), [
                            [vModelText, unref(form).commission_percentage]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-0.5 text-xs",
                            message: unref(form).errors.commission_percentage
                          }, null, 8, ["message"])
                        ]),
                        createVNode("div", null, [
                          createVNode("div", { class: "mb-1" }, [
                            createVNode("span", { class: "text-xs font-medium text-black" }, "Commission Amount (Tk.)"),
                            createVNode("span", { class: "text-red-500 ml-0.5 text-xs" }, "*")
                          ]),
                          withDirectives(createVNode("input", {
                            id: "commission_amount",
                            "onUpdate:modelValue": ($event) => unref(form).commission_amount = $event,
                            type: "number",
                            step: "0.01",
                            placeholder: "Commission Amount",
                            class: "block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none"
                          }, null, 8, ["onUpdate:modelValue"]), [
                            [vModelText, unref(form).commission_amount]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-0.5 text-xs",
                            message: unref(form).errors.commission_amount
                          }, null, 8, ["message"])
                        ])
                      ]),
                      createVNode("div", null, [
                        createVNode("div", { class: "mb-1" }, [
                          createVNode("span", { class: "text-xs font-medium text-black" }, "Note")
                        ]),
                        withDirectives(createVNode("textarea", {
                          id: "note",
                          "onUpdate:modelValue": ($event) => unref(form).note = $event,
                          rows: "4",
                          class: "block w-full px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none resize-none"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).note]
                        ]),
                        createVNode(_sfc_main$2, {
                          class: "mt-0.5 text-xs",
                          message: unref(form).errors.note
                        }, null, 8, ["message"])
                      ])
                    ]),
                    createVNode("div", { class: "space-y-2" }, [
                      createVNode("div", { class: "space-y-2" }, [
                        createVNode("div", { class: "flex justify-between items-center" }, [
                          createVNode("span", { class: "text-xs text-black" }, "Subtotal (Tk.)"),
                          createVNode("span", { class: "text-xs font-medium" }, toDisplayString((parseFloat(unref(form).subtotal) || 0).toFixed(2)), 1)
                        ]),
                        createVNode("div", { class: "flex justify-between items-center" }, [
                          createVNode("span", { class: "text-xs text-black" }, "Discount"),
                          createVNode("div", { class: "flex items-center space-x-1" }, [
                            createVNode("div", { class: "flex items-center" }, [
                              withDirectives(createVNode("input", {
                                "onUpdate:modelValue": ($event) => unref(form).discount_percentage = $event,
                                type: "number",
                                step: "0.01",
                                placeholder: "0",
                                class: "w-12 px-1 py-0.5 text-xs border border-gray-300 rounded-l focus:border-blue-500 focus:outline-none text-center"
                              }, null, 8, ["onUpdate:modelValue"]), [
                                [vModelText, unref(form).discount_percentage]
                              ]),
                              createVNode("span", { class: "px-1 py-0.5 text-xs bg-gray-100 border border-l-0 border-gray-300 rounded-r" }, "%")
                            ]),
                            createVNode("span", { class: "text-xs text-gray-400" }, "="),
                            createVNode("span", { class: "text-xs font-medium" }, toDisplayString((parseFloat(unref(form).discount_amount) || 0).toFixed(2)), 1)
                          ])
                        ]),
                        createVNode("div", { class: "flex justify-between items-center" }, [
                          createVNode("span", { class: "text-xs text-black" }, "VAT"),
                          createVNode("div", { class: "flex items-center space-x-1" }, [
                            createVNode("div", { class: "flex items-center" }, [
                              withDirectives(createVNode("input", {
                                "onUpdate:modelValue": ($event) => unref(form).vat_percentage = $event,
                                type: "number",
                                step: "0.01",
                                placeholder: "0",
                                class: "w-12 px-1 py-0.5 text-xs border border-gray-300 rounded-l focus:border-blue-500 focus:outline-none text-center"
                              }, null, 8, ["onUpdate:modelValue"]), [
                                [vModelText, unref(form).vat_percentage]
                              ]),
                              createVNode("span", { class: "px-1 py-0.5 text-xs bg-gray-100 border border-l-0 border-gray-300 rounded-r" }, "%")
                            ]),
                            createVNode("span", { class: "text-xs text-gray-400" }, "="),
                            createVNode("span", { class: "text-xs font-medium" }, toDisplayString((parseFloat(unref(form).vat_amount) || 0).toFixed(2)), 1)
                          ])
                        ]),
                        createVNode("div", { class: "flex justify-between items-center" }, [
                          createVNode("span", { class: "text-xs text-black" }, "Tax"),
                          createVNode("div", { class: "flex items-center space-x-1" }, [
                            createVNode("div", { class: "flex items-center" }, [
                              withDirectives(createVNode("input", {
                                "onUpdate:modelValue": ($event) => unref(form).tax_percentage = $event,
                                type: "number",
                                step: "0.01",
                                placeholder: "0",
                                class: "w-12 px-1 py-0.5 text-xs border border-gray-300 rounded-l focus:border-blue-500 focus:outline-none text-center"
                              }, null, 8, ["onUpdate:modelValue"]), [
                                [vModelText, unref(form).tax_percentage]
                              ]),
                              createVNode("span", { class: "px-1 py-0.5 text-xs bg-gray-100 border border-l-0 border-gray-300 rounded-r" }, "%")
                            ]),
                            createVNode("span", { class: "text-xs text-gray-400" }, "="),
                            createVNode("span", { class: "text-xs font-medium" }, toDisplayString((parseFloat(unref(form).tax_amount) || 0).toFixed(2)), 1)
                          ])
                        ]),
                        createVNode("div", { class: "flex justify-between items-center" }, [
                          createVNode("span", { class: "text-xs text-black" }, "Extra VAT"),
                          createVNode("div", { class: "flex items-center space-x-1" }, [
                            createVNode("div", { class: "flex items-center" }, [
                              withDirectives(createVNode("input", {
                                "onUpdate:modelValue": ($event) => unref(form).extra_vat_percentage = $event,
                                type: "number",
                                step: "0.01",
                                placeholder: "0",
                                class: "w-12 px-1 py-0.5 text-xs border border-gray-300 rounded-l focus:border-blue-500 focus:outline-none text-center"
                              }, null, 8, ["onUpdate:modelValue"]), [
                                [vModelText, unref(form).extra_vat_percentage]
                              ]),
                              createVNode("span", { class: "px-1 py-0.5 text-xs bg-gray-100 border border-l-0 border-gray-300 rounded-r" }, "%")
                            ]),
                            createVNode("span", { class: "text-xs text-gray-400" }, "="),
                            createVNode("span", { class: "text-sm font-medium" }, toDisplayString((parseFloat(unref(form).extra_vat_amount) || 0).toFixed(2)) + " Tk.", 1)
                          ])
                        ]),
                        createVNode("div", { class: "flex justify-between items-center" }, [
                          createVNode("span", { class: "text-sm text-black" }, "Extra Discount"),
                          createVNode("div", { class: "flex items-center space-x-2" }, [
                            withDirectives(createVNode("input", {
                              "onUpdate:modelValue": ($event) => unref(form).extra_discount = $event,
                              type: "number",
                              step: "0.01",
                              placeholder: "0",
                              class: "w-20 px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none text-center"
                            }, null, 8, ["onUpdate:modelValue"]), [
                              [vModelText, unref(form).extra_discount]
                            ]),
                            createVNode("span", { class: "text-sm text-gray-500" }, "Tk.")
                          ])
                        ]),
                        createVNode("div", { class: "flex justify-between items-center border-t pt-3" }, [
                          createVNode("span", { class: "text-sm font-medium text-black" }, "Net Amount (Tk.)"),
                          createVNode("span", { class: "text-lg font-bold text-green-600" }, toDisplayString((parseFloat(unref(form).net_amount) || 0).toFixed(2)), 1)
                        ])
                      ]),
                      createVNode("div", { class: "pt-4" }, [
                        createVNode("div", { class: "flex justify-between items-center mb-3" }, [
                          createVNode("span", { class: "text-sm font-medium text-black" }, "Payment Mode"),
                          createVNode("div", { class: "flex items-center" }, [
                            createVNode("span", { class: "text-sm font-medium text-black" }, "Amount (Tk.)"),
                            createVNode("span", {
                              class: "text-red-500 ml-1",
                              style: { "margin-right": "190px" }
                            }, "*")
                          ])
                        ]),
                        createVNode("div", { class: "grid grid-cols-2 gap-3" }, [
                          withDirectives(createVNode("select", {
                            "onUpdate:modelValue": ($event) => unref(form).payment_mode = $event,
                            class: "px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none bg-white"
                          }, [
                            createVNode("option", { value: "Cash" }, "Cash"),
                            createVNode("option", { value: "Card" }, "Card"),
                            createVNode("option", { value: "Bank Transfer" }, "Bank Transfer")
                          ], 8, ["onUpdate:modelValue"]), [
                            [vModelSelect, unref(form).payment_mode]
                          ]),
                          withDirectives(createVNode("input", {
                            "onUpdate:modelValue": ($event) => unref(form).payment_amount = $event,
                            type: "text",
                            step: "0.01",
                            class: "px-3 py-2 text-sm text-end border border-gray-300 rounded focus:border-blue-500 focus:outline-none"
                          }, null, 8, ["onUpdate:modelValue"]), [
                            [vModelText, unref(form).payment_amount]
                          ])
                        ])
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "flex items-center justify-end" }, [
                    createVNode(_sfc_main$3, {
                      type: "submit",
                      class: ["px-6 py-2", { "opacity-25": unref(form).processing }],
                      disabled: unref(form).processing
                    }, {
                      default: withCtx(() => [
                        createTextVNode(toDisplayString(props.id ?? false ? "Update" : "Save"), 1)
                      ]),
                      _: 1
                    }, 8, ["class", "disabled"])
                  ])
                ], 32)
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/Pathology/compactForm.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const compactForm = /* @__PURE__ */ _export_sfc(_sfc_main, [["__scopeId", "data-v-80d98db5"]]);
export {
  compactForm as default
};
