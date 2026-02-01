import { ref, computed, onMounted, watch, withCtx, unref, createTextVNode, toDisplayString, createVNode, withDirectives, vModelText, vModelCheckbox, openBlock, createBlock, createCommentVNode, withModifiers, Fragment, renderList, withKeys, vModelSelect, useSSRContext, nextTick } from "vue";
import { ssrRenderComponent, ssrRenderAttr, ssrIncludeBooleanAttr, ssrLooseContain, ssrInterpolate, ssrRenderList, ssrRenderClass, ssrLooseEqual, ssrRenderStyle } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import { useForm, router } from "@inertiajs/vue3";
import { _ as _sfc_main$2 } from "./InputError-6aeb8d97.mjs";
import "./InputLabel-70ca52d1.mjs";
import { _ as _sfc_main$3 } from "./PrimaryButton-b82fb16e.mjs";
import "./AlertMessage-0f422981.mjs";
import { d as displayResponse, a as displayWarning } from "./responseMessage-d505224b.mjs";
import { P as PatientModal } from "./PatientModal-85d06e3d.mjs";
import Multiselect from "vue-multiselect";
/* empty css                           */import { _ as _export_sfc } from "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "mitt";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
import "toastr";
import "sweetalert2";
import "date-fns";
const Form_vue_vue_type_style_index_0_scoped_703c124f_lang = "";
const _sfc_main = {
  __name: "Form",
  __ssrInlineRender: true,
  props: ["pathology", "id", "patients", "raferrers", "doctors", "billNo", "lastCaseId", "billnumber", "pathologyTests"],
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
    const searchQueries = ref({});
    const showDropdowns = ref({});
    const focusedRowIndex = ref(0);
    const form = useForm({
      case_id: ((_a = props.pathology) == null ? void 0 : _a.case_id) ?? "",
      patient_id: ((_b = props.pathology) == null ? void 0 : _b.patient_id) ?? "",
      doctor_id: ((_c = props.pathology) == null ? void 0 : _c.doctor_id) ?? "",
      apply_tpa: Boolean((_d = props.pathology) == null ? void 0 : _d.apply_tpa) || false,
      bill_no: ((_e = props.pathology) == null ? void 0 : _e.bill_no) ?? "",
      date: ((_f = props.pathology) == null ? void 0 : _f.date) ?? (/* @__PURE__ */ new Date()).toISOString().split("T")[0],
      tests: [],
      note: ((_g = props.pathology) == null ? void 0 : _g.note) ?? "",
      payee_id: ((_h = props.pathology) == null ? void 0 : _h.payee_id) ?? "",
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
      payment_amount: ((_w = props.pathology) == null ? void 0 : _w.payment_amount) ?? "",
      doctor_name: ((_x = props.pathology) == null ? void 0 : _x.doctor_name) ?? "",
      _method: ((_y = props.pathology) == null ? void 0 : _y.id) ? "put" : "post"
    });
    const getFilteredTests = (rowId) => {
      const query = searchQueries.value[rowId] || "";
      if (!query)
        return props.pathologyTests;
      return props.pathologyTests.filter(
        (test) => test.test_name.toLowerCase().includes(query.toLowerCase())
      );
    };
    const isTestAlreadySelected = (testId, currentRowId) => {
      return testRows.value.some((row) => row.id !== currentRowId && row.testId === testId);
    };
    const handleTestSearch = (rowIndex, value) => {
      const rowId = testRows.value[rowIndex].id;
      searchQueries.value[rowId] = value;
      showDropdowns.value[rowId] = true;
    };
    const highlightedIndex = ref(-1);
    const handleTestSearchKeyDown = (rowIndex, event) => {
      const rowId = testRows.value[rowIndex].id;
      const filteredTests = getFilteredTests(rowId);
      if (event.key === "ArrowDown") {
        event.preventDefault();
        highlightedIndex.value = Math.min(highlightedIndex.value + 1, filteredTests.length - 1);
        scrollToHighlighted(rowIndex);
      } else if (event.key === "ArrowUp") {
        event.preventDefault();
        highlightedIndex.value = Math.max(highlightedIndex.value - 1, -1);
        scrollToHighlighted(rowIndex);
      } else if (event.key === "Enter" && highlightedIndex.value >= 0) {
        event.preventDefault();
        selectTest(rowIndex, filteredTests[highlightedIndex.value]);
      }
    };
    const scrollToHighlighted = (rowIndex) => {
      nextTick(() => {
        const dropdown = document.querySelector(`#dropdown_${rowIndex}`);
        const highlightedItem = dropdown == null ? void 0 : dropdown.querySelector(".highlighted");
        if (highlightedItem) {
          highlightedItem.scrollIntoView({ block: "nearest" });
        }
      });
    };
    const resetHighlight = () => {
      highlightedIndex.value = -1;
    };
    const selectTest = async (rowIndex, test) => {
      const row = testRows.value[rowIndex];
      if (isTestAlreadySelected(test.id, row.id)) {
        alert("This test is already added to the cart!");
        await focusTestField(rowIndex);
        return;
      }
      row.testId = test.id;
      row.testName = test.test_name;
      row.reportDays = test.report_days || "";
      row.tax = test.tax || "";
      row.amount = test.amount || test.standard_charge || "";
      if (test.report_days) {
        const today = /* @__PURE__ */ new Date();
        const reportDate = new Date(today.getTime() + test.report_days * 24 * 60 * 60 * 1e3);
        row.reportDate = reportDate.toISOString().split("T")[0];
      }
      searchQueries.value[row.id] = test.test_name;
      showDropdowns.value[row.id] = false;
      await nextTick();
      const reportDateField = document.querySelector(`#reportDate_${rowIndex}`);
      if (reportDateField) {
        reportDateField.focus();
      }
    };
    const handleTestSearchEnter = async (rowIndex) => {
      const rowId = testRows.value[rowIndex].id;
      searchQueries.value[rowId] || "";
      const filteredTests = getFilteredTests(rowId);
      if (filteredTests.length > 0) {
        await selectTest(rowIndex, filteredTests[0]);
      }
    };
    const handleReportDateEnter = async (rowIndex) => {
      addTest();
      await nextTick();
      const newRowIndex = testRows.value.length - 1;
      await focusTestField(newRowIndex);
    };
    const focusTestField = async (rowIndex) => {
      await nextTick();
      const testField = document.querySelector(`#testSearch_${rowIndex}`);
      if (testField) {
        testField.focus();
        testField.select();
      }
    };
    const handleDropdownClick = async (rowIndex, test) => {
      await selectTest(rowIndex, test);
    };
    const handleTestFocus = (rowIndex) => {
      const rowId = testRows.value[rowIndex].id;
      focusedRowIndex.value = rowIndex;
      showDropdowns.value[rowId] = true;
    };
    const handleTestBlur = (rowIndex) => {
      const rowId = testRows.value[rowIndex].id;
      setTimeout(() => {
        showDropdowns.value[rowId] = false;
        highlightedIndex.value = -1;
      }, 200);
    };
    const previousPaidAmount = computed(() => {
      var _a2;
      return ((_a2 = props.pathology) == null ? void 0 : _a2.payment_amount) ? parseFloat(props.pathology.payment_amount) : 0;
    });
    const totalPaidAmount = computed(() => {
      return parseFloat(form.payment_amount) || 0;
    });
    computed(() => {
      const netAmount = parseFloat(form.net_amount) || 0;
      return netAmount - totalPaidAmount.value;
    });
    const isPatientModalOpen = ref(false);
    const patientsList = ref([...props.patients]);
    const openPatientModal = () => {
      isPatientModalOpen.value = true;
    };
    const closePatientModal = () => {
      isPatientModalOpen.value = false;
    };
    const handlePatientCreated = (newPatient) => {
      patientsList.value.push(newPatient);
      form.patient_id = newPatient.id;
      router.reload({
        only: ["patients"],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
          patientsList.value = [...page.props.patients];
          if (!form.patient_id && newPatient.id) {
            form.patient_id = newPatient.id;
          }
        }
      });
    };
    const generatePathologyNumber = async () => {
      try {
        const lastPathologyNo = props.pathologyNo ? props.pathologyNo : "";
        let newNumber = 1;
        if (lastPathologyNo && lastPathologyNo.startsWith("PATB")) {
          const numberPart = parseInt(lastPathologyNo.replace("PATB", ""), 10);
          if (!isNaN(numberPart)) {
            newNumber = numberPart + 1;
          }
        }
        return `PATB${newNumber}`;
      } catch (error) {
        console.error("Error generating pathology number:", error);
        return "PATB1";
      }
    };
    const generateBillNumber = async () => {
      try {
        const lastBillNo = props.billNo ? props.billNo : "";
        let newNumber = "0001";
        const yearMonth = (/* @__PURE__ */ new Date()).toISOString().slice(0, 7).replace("-", "");
        if (lastBillNo && lastBillNo.startsWith("BILL")) {
          const lastNumber = lastBillNo.slice(8);
          const num = parseInt(lastNumber, 10);
          if (!isNaN(num)) {
            newNumber = String(num + 1).padStart(4, "0");
          }
        }
        return `BILL${yearMonth}${newNumber}`;
      } catch (error) {
        console.error("Error generating bill number:", error);
        return `BILL${(/* @__PURE__ */ new Date()).toISOString().slice(0, 7).replace("-", "")}0001`;
      }
    };
    const generateCaseId = () => {
      const timestamp = Date.now().toString().slice(-6);
      const random = Math.floor(Math.random() * 1e3).toString().padStart(3, "0");
      return `CASE${timestamp}${random}`;
    };
    computed(() => {
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
    computed(() => {
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
        form.payment_amount = "";
        setTimeout(async () => {
          form.pathology_no = await generatePathologyNumber();
          form.bill_no = await generateBillNumber();
        }, 1e3);
      } else {
        form.payment_amount = props.pathology.payment_amount || "";
      }
      if (((_b2 = props.pathology) == null ? void 0 : _b2.tests) && props.pathology.tests.length > 0) {
        testRows.value = props.pathology.tests.map((test) => ({
          id: test.id || Date.now() + Math.random(),
          testId: test.testId || "",
          testName: test.test_name || "",
          reportDays: test.report_days || "",
          reportDate: test.reportDate || "",
          tax: test.tax || "",
          amount: test.amount || ""
        }));
        testRows.value.forEach((row) => {
          if (row.testName) {
            searchQueries.value[row.id] = row.testName;
          }
        });
      }
      updateCalculations();
      await focusTestField(0);
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
      if (!form.patient_id) {
        alert("Please select a patient");
        return;
      }
      if (testRows.value.length === 0 || !testRows.value[0].testId) {
        alert("Please add at least one test");
        return;
      }
      updateFormTests();
      const routeName = props.id ? route("backend.pathology.update", props.id) : route("backend.pathology.store");
      form.transform((data) => ({
        ...data,
        patient_id: typeof data.patient_id === "object" ? data.patient_id.id : data.patient_id,
        payment_amount: parseFloat(data.payment_amount) || 0,
        remember: "",
        isDirty: false
      })).post(routeName, {
        onSuccess: (response) => {
          var _a2, _b2, _c2, _d2;
          if (props.id) {
            router.reload({
              only: ["pathology"],
              preserveScroll: true,
              onSuccess: () => {
                var _a3, _b3, _c3, _d3;
                const successMessage = (_b3 = (_a3 = response == null ? void 0 : response.props) == null ? void 0 : _a3.flash) == null ? void 0 : _b3.successMessage;
                const billId = (_d3 = (_c3 = response == null ? void 0 : response.props) == null ? void 0 : _c3.flash) == null ? void 0 : _d3.billId;
                if (successMessage && billId) {
                  window.open(route("backend.download.invoice", { id: billId, module: "pathology" }), "_blank");
                }
              }
            });
          } else {
            form.reset();
            testRows.value = [createTestRow()];
            searchQueries.value = {};
            showDropdowns.value = {};
            const successMessage = (_b2 = (_a2 = response == null ? void 0 : response.props) == null ? void 0 : _a2.flash) == null ? void 0 : _b2.successMessage;
            const billId = (_d2 = (_c2 = response == null ? void 0 : response.props) == null ? void 0 : _c2.flash) == null ? void 0 : _d2.billId;
            if (successMessage && billId) {
              window.open(route("backend.download.invoice", { id: billId, module: "pathology" }), "_blank");
            }
          }
          displayResponse(response);
        },
        onError: (errorObject) => {
          console.log("Submission error:", errorObject);
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
        delete searchQueries.value[testId];
        delete showDropdowns.value[testId];
        console.log("Test row removed. Remaining rows:", testRows.value.length);
      }
    };
    const selectedPayee = computed(() => {
      if (!form.payee_id)
        return null;
      return props.raferrers.find((referrer) => referrer.id === form.payee_id);
    });
    watch(() => form.payee_id, (newPayeeId) => {
      if (newPayeeId && selectedPayee.value) {
        form.commission_percentage = selectedPayee.value.pathology_commission || 0;
        if (form.net_amount && form.commission_percentage) {
          const percentage = parseFloat(form.commission_percentage) || 0;
          const net_amount = parseFloat(form.net_amount) || 0;
          form.commission_amount = parseFloat((net_amount * percentage / 100).toFixed(2));
        }
      } else {
        form.commission_percentage = "";
        form.commission_amount = "";
      }
    });
    watch(() => form.commission_percentage, (newVal) => {
      if (newVal && form.net_amount) {
        const percentage = parseFloat(newVal) || 0;
        const net_amount = parseFloat(form.net_amount) || 0;
        form.commission_amount = parseFloat((net_amount * percentage / 100).toFixed(2));
      } else {
        form.commission_amount = "";
      }
    });
    watch(() => form.net_amount, (newVal) => {
      if (newVal && form.commission_percentage) {
        const percentage = parseFloat(form.commission_percentage) || 0;
        const net_amount = parseFloat(newVal) || 0;
        form.commission_amount = parseFloat((net_amount * percentage / 100).toFixed(2));
      }
    });
    const goToPathologyList = () => {
      router.visit(route("backend.pathology.index"));
    };
    const nextBillNumber = computed(() => {
      var _a2, _b2;
      if (!props.billNo)
        return "BILL" + (/* @__PURE__ */ new Date()).toISOString().slice(0, 7).replace("-", "") + "0001";
      const prefix = ((_a2 = props.billNo.match(/^[A-Za-z]+/)) == null ? void 0 : _a2[0]) || "BILL";
      const numbers = ((_b2 = props.billNo.match(/\d+$/)) == null ? void 0 : _b2[0]) || "0000";
      const nextNum = String(parseInt(numbers) + 1).padStart(numbers.length, "0");
      return `${prefix}${nextNum}`;
    });
    const nextCaseId = computed(() => {
      var _a2, _b2;
      if (!props.lastCaseId)
        return "CASE" + Date.now().toString().slice(-6) + Math.floor(Math.random() * 1e3).toString().padStart(3, "0");
      const prefix = ((_a2 = props.lastCaseId.match(/^[A-Za-z]+/)) == null ? void 0 : _a2[0]) || "CASE";
      const numbers = ((_b2 = props.lastCaseId.match(/\d+$/)) == null ? void 0 : _b2[0]) || "0000";
      const nextNum = String(parseInt(numbers) + 1).padStart(numbers.length, "0");
      return `${prefix}${nextNum}`;
    });
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(PatientModal, {
              isOpen: isPatientModalOpen.value,
              tpas: props.tpas,
              onClose: closePatientModal,
              onPatientCreated: handlePatientCreated
            }, null, _parent2, _scopeId));
            _push2(`<div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-lg shadow-lg" data-v-703c124f${_scopeId}><div class="flex items-center justify-between w-full px-4 py-2 bg-gray-100 border-b rounded-md border-gray-300" data-v-703c124f${_scopeId}><div class="flex items-center space-x-4" data-v-703c124f${_scopeId}><div class="relative min-w-[280px]" data-v-703c124f${_scopeId}><div class="relative" data-v-703c124f${_scopeId}><div class="col-span-1" data-v-703c124f${_scopeId}>`);
            _push2(ssrRenderComponent(unref(Multiselect), {
              modelValue: unref(form).patient_id,
              "onUpdate:modelValue": ($event) => unref(form).patient_id = $event,
              options: __props.patients,
              "track-by": "id",
              label: "name",
              placeholder: "Search and select a patient",
              class: "w-full text-sm h-[30px] rounded-md border border-slate-300"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.patient_id
            }, null, _parent2, _scopeId));
            _push2(`</div></div></div><button class="px-3 py-2.5 text-sm text-white bg-blue-600 rounded hover:bg-blue-700" data-v-703c124f${_scopeId}> + New Patient </button><div class="flex items-center" data-v-703c124f${_scopeId}><input${ssrRenderAttr("value", unref(form).prescription_no)} type="text" placeholder="Prescription No" class="px-3 py-2.5 text-sm border border-gray-300 rounded-l focus:outline-none focus:border-blue-500" data-v-703c124f${_scopeId}><button class="px-3 py-2.5 text-sm bg-gray-200 border border-l-0 border-gray-300 rounded-r hover:bg-gray-300" data-v-703c124f${_scopeId}> 🔍 </button></div><div class="col-span-2" data-v-703c124f${_scopeId}><div class="flex items-center h-8" data-v-703c124f${_scopeId}><input${ssrIncludeBooleanAttr(Array.isArray(unref(form).apply_tpa) ? ssrLooseContain(unref(form).apply_tpa, null) : unref(form).apply_tpa) ? " checked" : ""} type="checkbox" id="apply_tpa" class="w-3 h-3 text-blue-600 border-gray-300 rounded focus:ring-blue-500" data-v-703c124f${_scopeId}><label for="apply_tpa" class="ml-1 text-xs font-medium text-black" data-v-703c124f${_scopeId}>Apply TPA</label></div></div></div><div class="p-2 py-2 flex items-center space-x-2" data-v-703c124f${_scopeId}><div class="flex items-center space-x-3" data-v-703c124f${_scopeId}><button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2" data-v-703c124f${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" data-v-703c124f${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" data-v-703c124f${_scopeId}></path></svg> Pathology List </button></div></div></div><div class="flex items-center justify-between px-4 py-2 bg-gray-50 border-b border-gray-200" data-v-703c124f${_scopeId}><div class="flex space-x-8" data-v-703c124f${_scopeId}><div class="flex items-center space-x-2" data-v-703c124f${_scopeId}><span class="text-sm font-semibold" data-v-703c124f${_scopeId}>Bill No</span>`);
            if (!props.id) {
              _push2(`<span class="text-blue-600 font-medium" data-v-703c124f${_scopeId}>${ssrInterpolate(nextBillNumber.value ?? "")}</span>`);
            } else {
              _push2(`<!---->`);
            }
            if (props.id) {
              _push2(`<span class="text-blue-600 font-medium" data-v-703c124f${_scopeId}>${ssrInterpolate(props.billNo ?? "")}</span>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div><div class="flex items-center space-x-2" data-v-703c124f${_scopeId}><span class="text-sm font-semibold" data-v-703c124f${_scopeId}>Case ID</span><div class="flex items-center" data-v-703c124f${_scopeId}>`);
            if (!props.id) {
              _push2(`<span class="text-blue-600 font-medium" data-v-703c124f${_scopeId}>${ssrInterpolate(nextCaseId.value ?? "")}</span>`);
            } else {
              _push2(`<!---->`);
            }
            if (props.id) {
              _push2(`<span class="text-blue-600 font-medium" data-v-703c124f${_scopeId}>${ssrInterpolate(props.lastCaseId ?? "")}</span>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div></div></div><span class="text-sm text-gray-600" data-v-703c124f${_scopeId}>Date ${ssrInterpolate((/* @__PURE__ */ new Date()).toLocaleDateString())}</span></div><form class="p-6" data-v-703c124f${_scopeId}><div class="mb-6" data-v-703c124f${_scopeId}><!--[-->`);
            ssrRenderList(testRows.value, (test, index) => {
              _push2(`<div class="mb-4" data-v-703c124f${_scopeId}><div class="grid grid-cols-5 gap-6" data-v-703c124f${_scopeId}><div class="relative" data-v-703c124f${_scopeId}><div class="mb-2" data-v-703c124f${_scopeId}><span class="text-sm font-medium text-black" data-v-703c124f${_scopeId}>Test Name</span><span class="text-red-500 ml-1" data-v-703c124f${_scopeId}>*</span></div><input${ssrRenderAttr("id", `testSearch_${index}`)}${ssrRenderAttr("value", searchQueries.value[test.id])} type="text" placeholder="Search test..." class="block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none" autocomplete="off" data-v-703c124f${_scopeId}>`);
              if (showDropdowns.value[test.id]) {
                _push2(`<div${ssrRenderAttr("id", `dropdown_${index}`)} class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto" data-v-703c124f${_scopeId}><!--[-->`);
                ssrRenderList(getFilteredTests(test.id), (pathTest, testIndex) => {
                  _push2(`<div class="${ssrRenderClass([{ "bg-blue-100": highlightedIndex.value === testIndex, "highlighted": highlightedIndex.value === testIndex }, "px-3 py-2 text-sm cursor-pointer hover:bg-blue-50 hover:text-blue-700 border-b border-gray-100 last:border-b-0"])}" data-v-703c124f${_scopeId}><div class="font-medium" data-v-703c124f${_scopeId}>${ssrInterpolate(pathTest.test_name)}</div><div class="text-xs text-gray-500" data-v-703c124f${_scopeId}>Amount: ${ssrInterpolate(pathTest.amount || pathTest.standard_charge || "N/A")} Tk.</div></div>`);
                });
                _push2(`<!--]-->`);
                if (getFilteredTests(test.id).length === 0) {
                  _push2(`<div class="px-3 py-2 text-sm text-gray-500 text-center" data-v-703c124f${_scopeId}> No tests found </div>`);
                } else {
                  _push2(`<!---->`);
                }
                _push2(`</div>`);
              } else {
                _push2(`<!---->`);
              }
              _push2(ssrRenderComponent(_sfc_main$2, {
                class: "mt-1",
                message: unref(form).errors[`tests.${index}.testId`]
              }, null, _parent2, _scopeId));
              _push2(`</div><div data-v-703c124f${_scopeId}><div class="mb-2" data-v-703c124f${_scopeId}><span class="text-sm font-medium text-black" data-v-703c124f${_scopeId}>Report Days</span></div><input${ssrRenderAttr("value", test.reportDays)} type="number" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none" data-v-703c124f${_scopeId}>`);
              _push2(ssrRenderComponent(_sfc_main$2, {
                class: "mt-1",
                message: unref(form).errors[`tests.${index}.reportDays`]
              }, null, _parent2, _scopeId));
              _push2(`</div><div data-v-703c124f${_scopeId}><div class="mb-2" data-v-703c124f${_scopeId}><span class="text-sm font-medium text-black" data-v-703c124f${_scopeId}>Report Date</span><span class="text-red-500 ml-1" data-v-703c124f${_scopeId}>*</span></div><input${ssrRenderAttr("id", `reportDate_${index}`)}${ssrRenderAttr("value", test.reportDate)} type="date" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none" data-v-703c124f${_scopeId}>`);
              _push2(ssrRenderComponent(_sfc_main$2, {
                class: "mt-1",
                message: unref(form).errors[`tests.${index}.reportDate`]
              }, null, _parent2, _scopeId));
              _push2(`</div><div data-v-703c124f${_scopeId}><div class="mb-2" data-v-703c124f${_scopeId}><span class="text-sm font-medium text-black" data-v-703c124f${_scopeId}>Tax</span></div><div class="relative" data-v-703c124f${_scopeId}><input${ssrRenderAttr("value", test.tax)} type="number" step="0.01" class="block w-full px-3 py-2 pr-8 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none" data-v-703c124f${_scopeId}><span class="absolute right-3 top-2 text-sm text-gray-500" data-v-703c124f${_scopeId}>%</span></div>`);
              _push2(ssrRenderComponent(_sfc_main$2, {
                class: "mt-1",
                message: unref(form).errors[`tests.${index}.tax`]
              }, null, _parent2, _scopeId));
              _push2(`</div><div data-v-703c124f${_scopeId}><div class="mb-1 flex justify-between items-center" data-v-703c124f${_scopeId}><span class="text-sm font-medium text-black" data-v-703c124f${_scopeId}>Amount (Tk.)</span><button type="button"${ssrIncludeBooleanAttr(testRows.value.length === 1) ? " disabled" : ""} class="${ssrRenderClass([testRows.value.length === 1 ? "text-gray-300 cursor-not-allowed" : "text-red-500 hover:text-red-700", "text-lg font-bold"])}" data-v-703c124f${_scopeId}>×</button></div><input${ssrRenderAttr("value", test.amount)} type="number" step="0.01" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none" data-v-703c124f${_scopeId}>`);
              _push2(ssrRenderComponent(_sfc_main$2, {
                class: "mt-1",
                message: unref(form).errors[`tests.${index}.amount`]
              }, null, _parent2, _scopeId));
              _push2(`</div></div></div>`);
            });
            _push2(`<!--]--></div><div class="mb-8" data-v-703c124f${_scopeId}><button type="button" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-green-600 rounded hover:bg-green-700" data-v-703c124f${_scopeId}><span class="mr-1" data-v-703c124f${_scopeId}>+</span> Add Test </button></div><div class="grid grid-cols-3 gap-6 mb-6" data-v-703c124f${_scopeId}><div data-v-703c124f${_scopeId}><div class="mb-2" data-v-703c124f${_scopeId}><span class="text-sm font-medium text-black" data-v-703c124f${_scopeId}>Referral Doctor</span></div><select id="referralDoctor" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none bg-white" data-v-703c124f${_scopeId}><option value="" data-v-703c124f${ssrIncludeBooleanAttr(Array.isArray(unref(form).doctor_id) ? ssrLooseContain(unref(form).doctor_id, "") : ssrLooseEqual(unref(form).doctor_id, "")) ? " selected" : ""}${_scopeId}>Select Doctor</option><!--[-->`);
            ssrRenderList(__props.doctors, (data) => {
              _push2(`<option${ssrRenderAttr("value", data.id)} data-v-703c124f${ssrIncludeBooleanAttr(Array.isArray(unref(form).doctor_id) ? ssrLooseContain(unref(form).doctor_id, data.id) : ssrLooseEqual(unref(form).doctor_id, data.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(data.name)}</option>`);
            });
            _push2(`<!--]--></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.doctor_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-703c124f${_scopeId}><div class="mb-2" data-v-703c124f${_scopeId}><span class="text-sm font-medium text-black" data-v-703c124f${_scopeId}>Doctor Name</span></div><input id="doctor_name"${ssrRenderAttr("value", unref(form).doctor_name)} type="text" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none" data-v-703c124f${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.doctor_name
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-703c124f${_scopeId}><div class="mb-2" data-v-703c124f${_scopeId}><span class="text-sm font-medium text-black" data-v-703c124f${_scopeId}>Payee</span></div><select id="payee_id" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none bg-white" data-v-703c124f${_scopeId}><option value="" data-v-703c124f${ssrIncludeBooleanAttr(Array.isArray(unref(form).payee_id) ? ssrLooseContain(unref(form).payee_id, "") : ssrLooseEqual(unref(form).payee_id, "")) ? " selected" : ""}${_scopeId}>Select Payee</option><!--[-->`);
            ssrRenderList(__props.raferrers, (data) => {
              _push2(`<option${ssrRenderAttr("value", data.id)} data-v-703c124f${ssrIncludeBooleanAttr(Array.isArray(unref(form).payee_id) ? ssrLooseContain(unref(form).payee_id, data.id) : ssrLooseEqual(unref(form).payee_id, data.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(data.name)}</option>`);
            });
            _push2(`<!--]--></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.payee_id
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="grid grid-cols-2 gap-6 mb-6" data-v-703c124f${_scopeId}><div data-v-703c124f${_scopeId}><div class="mb-2" data-v-703c124f${_scopeId}><span class="text-sm font-medium text-black" data-v-703c124f${_scopeId}>Commission Percentage (%)</span></div><input id="commission_percentage"${ssrRenderAttr("value", unref(form).commission_percentage)} type="number" step="0.01" placeholder="Percentage" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none" data-v-703c124f${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.commission_percentage
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-703c124f${_scopeId}><div class="mb-2" data-v-703c124f${_scopeId}><span class="text-sm font-medium text-black" data-v-703c124f${_scopeId}>Commission Amount (Tk.)</span></div><input id="commission_amount"${ssrRenderAttr("value", unref(form).commission_amount)} type="number" step="0.01" placeholder="Commission Amount" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none" data-v-703c124f${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.commission_amount
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="grid grid-cols-2 gap-8 mb-6" data-v-703c124f${_scopeId}><div data-v-703c124f${_scopeId}><div class="mb-2" data-v-703c124f${_scopeId}><span class="text-sm font-medium text-black" data-v-703c124f${_scopeId}>Note</span></div><textarea id="note" rows="8" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none resize-none" data-v-703c124f${_scopeId}>${ssrInterpolate(unref(form).note)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.note
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="space-y-4" data-v-703c124f${_scopeId}><div class="space-y-3" data-v-703c124f${_scopeId}><div class="flex justify-between items-center" data-v-703c124f${_scopeId}><span class="text-sm text-black" data-v-703c124f${_scopeId}>Subtotal (Tk.)</span><span class="text-sm font-medium" data-v-703c124f${_scopeId}>${ssrInterpolate((parseFloat(unref(form).subtotal) || 0).toFixed(2))}</span></div><div class="flex justify-between items-center" data-v-703c124f${_scopeId}><span class="text-sm text-black" data-v-703c124f${_scopeId}>Discount</span><div class="flex items-center space-x-2" data-v-703c124f${_scopeId}><div class="flex items-center" data-v-703c124f${_scopeId}><input${ssrRenderAttr("value", unref(form).discount_percentage)} type="number" step="0.01" placeholder="0" class="w-16 px-2 py-1 text-xs border border-gray-300 rounded-l focus:border-blue-500 focus:outline-none text-center" data-v-703c124f${_scopeId}><span class="px-2 py-1 text-xs bg-gray-100 border border-l-0 border-gray-300 rounded-r" data-v-703c124f${_scopeId}>%</span></div><span class="text-sm text-gray-400" data-v-703c124f${_scopeId}>=</span><span class="text-sm font-medium" data-v-703c124f${_scopeId}>${ssrInterpolate((parseFloat(unref(form).discount_amount) || 0).toFixed(2))} Tk.</span></div></div><div class="flex justify-between items-center" data-v-703c124f${_scopeId}><span class="text-sm text-black" data-v-703c124f${_scopeId}>VAT</span><div class="flex items-center space-x-2" data-v-703c124f${_scopeId}><div class="flex items-center" data-v-703c124f${_scopeId}><input${ssrRenderAttr("value", unref(form).vat_percentage)} type="number" step="0.01" placeholder="0" class="w-16 px-2 py-1 text-xs border border-gray-300 rounded-l focus:border-blue-500 focus:outline-none text-center" data-v-703c124f${_scopeId}><span class="px-2 py-1 text-xs bg-gray-100 border border-l-0 border-gray-300 rounded-r" data-v-703c124f${_scopeId}>%</span></div><span class="text-sm text-gray-400" data-v-703c124f${_scopeId}>=</span><span class="text-sm font-medium" data-v-703c124f${_scopeId}>${ssrInterpolate((parseFloat(unref(form).vat_amount) || 0).toFixed(2))} Tk.</span></div></div><div class="flex justify-between items-center" data-v-703c124f${_scopeId}><span class="text-sm text-black" data-v-703c124f${_scopeId}>Tax</span><div class="flex items-center space-x-2" data-v-703c124f${_scopeId}><div class="flex items-center" data-v-703c124f${_scopeId}><input${ssrRenderAttr("value", unref(form).tax_percentage)} type="number" step="0.01" placeholder="0" class="w-16 px-2 py-1 text-xs border border-gray-300 rounded-l focus:border-blue-500 focus:outline-none text-center" data-v-703c124f${_scopeId}><span class="px-2 py-1 text-xs bg-gray-100 border border-l-0 border-gray-300 rounded-r" data-v-703c124f${_scopeId}>%</span></div><span class="text-sm text-gray-400" data-v-703c124f${_scopeId}>=</span><span class="text-sm font-medium" data-v-703c124f${_scopeId}>${ssrInterpolate((parseFloat(unref(form).tax_amount) || 0).toFixed(2))} Tk.</span></div></div><div class="flex justify-between items-center" data-v-703c124f${_scopeId}><span class="text-sm text-black" data-v-703c124f${_scopeId}>Extra VAT</span><div class="flex items-center space-x-2" data-v-703c124f${_scopeId}><div class="flex items-center" data-v-703c124f${_scopeId}><input${ssrRenderAttr("value", unref(form).extra_vat_percentage)} type="number" step="0.01" placeholder="0" class="w-16 px-2 py-1 text-xs border border-gray-300 rounded-l focus:border-blue-500 focus:outline-none text-center" data-v-703c124f${_scopeId}><span class="px-2 py-1 text-xs bg-gray-100 border border-l-0 border-gray-300 rounded-r" data-v-703c124f${_scopeId}>%</span></div><span class="text-sm text-gray-400" data-v-703c124f${_scopeId}>=</span><span class="text-sm font-medium" data-v-703c124f${_scopeId}>${ssrInterpolate((parseFloat(unref(form).extra_vat_amount) || 0).toFixed(2))} Tk.</span></div></div><div class="flex justify-between items-center" data-v-703c124f${_scopeId}><span class="text-sm text-black" data-v-703c124f${_scopeId}>Extra Discount</span><div class="flex items-center space-x-2" data-v-703c124f${_scopeId}><input${ssrRenderAttr("value", unref(form).extra_discount)} type="number" step="0.01" placeholder="0" class="w-20 px-2 py-1 text-xs border border-gray-300 rounded focus:border-blue-500 focus:outline-none text-center" data-v-703c124f${_scopeId}><span class="text-sm text-gray-500" data-v-703c124f${_scopeId}>Tk.</span></div></div><div class="flex justify-between items-center border-t pt-3" data-v-703c124f${_scopeId}><span class="text-sm font-medium text-black" data-v-703c124f${_scopeId}>Net Amount (Tk.)</span><span class="text-lg font-bold text-green-600" data-v-703c124f${_scopeId}>${ssrInterpolate((parseFloat(unref(form).net_amount) || 0).toFixed(2))}</span></div>`);
            if (props.id && previousPaidAmount.value > 0) {
              _push2(`<div class="flex justify-between items-center" data-v-703c124f${_scopeId}><span class="text-sm text-gray-600" data-v-703c124f${_scopeId}>Previous Paid Amount (Tk.)</span><span class="text-sm text-gray-600" data-v-703c124f${_scopeId}>${ssrInterpolate(previousPaidAmount.value.toFixed(2))}</span></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div><div class="pt-4" data-v-703c124f${_scopeId}><div class="flex justify-between items-center mb-3" data-v-703c124f${_scopeId}><span class="text-sm font-medium text-black" data-v-703c124f${_scopeId}>Payment Mode</span><div class="flex items-center" data-v-703c124f${_scopeId}><span class="text-sm font-medium text-black" data-v-703c124f${_scopeId}>${ssrInterpolate(props.id ? "Payment Amount (Tk.)" : "Amount (Tk.)")}</span><span class="text-red-500 ml-1" style="${ssrRenderStyle({ "margin-right": "140px" })}" data-v-703c124f${_scopeId}>*</span></div></div><div class="grid grid-cols-2 gap-3" data-v-703c124f${_scopeId}><select class="px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none bg-white" data-v-703c124f${_scopeId}><option value="Cash" data-v-703c124f${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, "Cash") : ssrLooseEqual(unref(form).payment_mode, "Cash")) ? " selected" : ""}${_scopeId}>Cash</option><option value="Card" data-v-703c124f${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, "Card") : ssrLooseEqual(unref(form).payment_mode, "Card")) ? " selected" : ""}${_scopeId}>Card</option><option value="Bank Transfer" data-v-703c124f${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, "Bank Transfer") : ssrLooseEqual(unref(form).payment_mode, "Bank Transfer")) ? " selected" : ""}${_scopeId}>Bank Transfer</option></select><input${ssrRenderAttr("value", unref(form).payment_amount)} type="number" step="0.01" placeholder="Enter payment amount" class="px-3 py-2 text-sm text-end border border-gray-300 rounded focus:border-blue-500 focus:outline-none" data-v-703c124f${_scopeId}></div>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.payment_amount
            }, null, _parent2, _scopeId));
            _push2(`</div></div></div><div class="flex items-center justify-end" data-v-703c124f${_scopeId}>`);
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
              createVNode(PatientModal, {
                isOpen: isPatientModalOpen.value,
                tpas: props.tpas,
                onClose: closePatientModal,
                onPatientCreated: handlePatientCreated
              }, null, 8, ["isOpen", "tpas"]),
              createVNode("div", { class: "w-full transition duration-1000 ease-in-out transform bg-white rounded-lg shadow-lg" }, [
                createVNode("div", { class: "flex items-center justify-between w-full px-4 py-2 bg-gray-100 border-b rounded-md border-gray-300" }, [
                  createVNode("div", { class: "flex items-center space-x-4" }, [
                    createVNode("div", { class: "relative min-w-[280px]" }, [
                      createVNode("div", { class: "relative" }, [
                        createVNode("div", { class: "col-span-1" }, [
                          createVNode(unref(Multiselect), {
                            modelValue: unref(form).patient_id,
                            "onUpdate:modelValue": ($event) => unref(form).patient_id = $event,
                            options: __props.patients,
                            "track-by": "id",
                            label: "name",
                            placeholder: "Search and select a patient",
                            class: "w-full text-sm h-[30px] rounded-md border border-slate-300"
                          }, null, 8, ["modelValue", "onUpdate:modelValue", "options"]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.patient_id
                          }, null, 8, ["message"])
                        ])
                      ])
                    ]),
                    createVNode("button", {
                      onClick: openPatientModal,
                      class: "px-3 py-2.5 text-sm text-white bg-blue-600 rounded hover:bg-blue-700"
                    }, " + New Patient "),
                    createVNode("div", { class: "flex items-center" }, [
                      withDirectives(createVNode("input", {
                        "onUpdate:modelValue": ($event) => unref(form).prescription_no = $event,
                        type: "text",
                        placeholder: "Prescription No",
                        class: "px-3 py-2.5 text-sm border border-gray-300 rounded-l focus:outline-none focus:border-blue-500"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).prescription_no]
                      ]),
                      createVNode("button", { class: "px-3 py-2.5 text-sm bg-gray-200 border border-l-0 border-gray-300 rounded-r hover:bg-gray-300" }, " 🔍 ")
                    ]),
                    createVNode("div", { class: "col-span-2" }, [
                      createVNode("div", { class: "flex items-center h-8" }, [
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
                  createVNode("div", { class: "p-2 py-2 flex items-center space-x-2" }, [
                    createVNode("div", { class: "flex items-center space-x-3" }, [
                      createVNode("button", {
                        onClick: goToPathologyList,
                        class: "inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2"
                      }, [
                        (openBlock(), createBlock("svg", {
                          class: "w-4 h-4 mr-2 -ml-1",
                          fill: "none",
                          stroke: "currentColor",
                          viewBox: "0 0 24 24",
                          "stroke-width": "2"
                        }, [
                          createVNode("path", {
                            "stroke-linecap": "round",
                            "stroke-linejoin": "round",
                            d: "M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"
                          })
                        ])),
                        createTextVNode(" Pathology List ")
                      ])
                    ])
                  ])
                ]),
                createVNode("div", { class: "flex items-center justify-between px-4 py-2 bg-gray-50 border-b border-gray-200" }, [
                  createVNode("div", { class: "flex space-x-8" }, [
                    createVNode("div", { class: "flex items-center space-x-2" }, [
                      createVNode("span", { class: "text-sm font-semibold" }, "Bill No"),
                      !props.id ? (openBlock(), createBlock("span", {
                        key: 0,
                        class: "text-blue-600 font-medium"
                      }, toDisplayString(nextBillNumber.value ?? ""), 1)) : createCommentVNode("", true),
                      props.id ? (openBlock(), createBlock("span", {
                        key: 1,
                        class: "text-blue-600 font-medium"
                      }, toDisplayString(props.billNo ?? ""), 1)) : createCommentVNode("", true)
                    ]),
                    createVNode("div", { class: "flex items-center space-x-2" }, [
                      createVNode("span", { class: "text-sm font-semibold" }, "Case ID"),
                      createVNode("div", { class: "flex items-center" }, [
                        !props.id ? (openBlock(), createBlock("span", {
                          key: 0,
                          class: "text-blue-600 font-medium"
                        }, toDisplayString(nextCaseId.value ?? ""), 1)) : createCommentVNode("", true),
                        props.id ? (openBlock(), createBlock("span", {
                          key: 1,
                          class: "text-blue-600 font-medium"
                        }, toDisplayString(props.lastCaseId ?? ""), 1)) : createCommentVNode("", true)
                      ])
                    ])
                  ]),
                  createVNode("span", { class: "text-sm text-gray-600" }, "Date " + toDisplayString((/* @__PURE__ */ new Date()).toLocaleDateString()), 1)
                ]),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"]),
                  class: "p-6"
                }, [
                  createVNode("div", { class: "mb-6" }, [
                    (openBlock(true), createBlock(Fragment, null, renderList(testRows.value, (test, index) => {
                      return openBlock(), createBlock("div", {
                        key: test.id,
                        class: "mb-4"
                      }, [
                        createVNode("div", { class: "grid grid-cols-5 gap-6" }, [
                          createVNode("div", { class: "relative" }, [
                            createVNode("div", { class: "mb-2" }, [
                              createVNode("span", { class: "text-sm font-medium text-black" }, "Test Name"),
                              createVNode("span", { class: "text-red-500 ml-1" }, "*")
                            ]),
                            withDirectives(createVNode("input", {
                              id: `testSearch_${index}`,
                              "onUpdate:modelValue": ($event) => searchQueries.value[test.id] = $event,
                              onInput: (e) => {
                                handleTestSearch(index, e.target.value);
                                resetHighlight();
                              },
                              onFocus: ($event) => handleTestFocus(index),
                              onBlur: ($event) => handleTestBlur(index),
                              onKeydown: [
                                withKeys(withModifiers(($event) => handleTestSearchEnter(index), ["prevent"]), ["enter"]),
                                ($event) => handleTestSearchKeyDown(index, $event)
                              ],
                              type: "text",
                              placeholder: "Search test...",
                              class: "block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none",
                              autocomplete: "off"
                            }, null, 40, ["id", "onUpdate:modelValue", "onInput", "onFocus", "onBlur", "onKeydown"]), [
                              [vModelText, searchQueries.value[test.id]]
                            ]),
                            showDropdowns.value[test.id] ? (openBlock(), createBlock("div", {
                              key: 0,
                              id: `dropdown_${index}`,
                              class: "absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto"
                            }, [
                              (openBlock(true), createBlock(Fragment, null, renderList(getFilteredTests(test.id), (pathTest, testIndex) => {
                                return openBlock(), createBlock("div", {
                                  key: pathTest.id,
                                  onClick: ($event) => handleDropdownClick(index, pathTest),
                                  class: [{ "bg-blue-100": highlightedIndex.value === testIndex, "highlighted": highlightedIndex.value === testIndex }, "px-3 py-2 text-sm cursor-pointer hover:bg-blue-50 hover:text-blue-700 border-b border-gray-100 last:border-b-0"]
                                }, [
                                  createVNode("div", { class: "font-medium" }, toDisplayString(pathTest.test_name), 1),
                                  createVNode("div", { class: "text-xs text-gray-500" }, "Amount: " + toDisplayString(pathTest.amount || pathTest.standard_charge || "N/A") + " Tk.", 1)
                                ], 10, ["onClick"]);
                              }), 128)),
                              getFilteredTests(test.id).length === 0 ? (openBlock(), createBlock("div", {
                                key: 0,
                                class: "px-3 py-2 text-sm text-gray-500 text-center"
                              }, " No tests found ")) : createCommentVNode("", true)
                            ], 8, ["id"])) : createCommentVNode("", true),
                            createVNode(_sfc_main$2, {
                              class: "mt-1",
                              message: unref(form).errors[`tests.${index}.testId`]
                            }, null, 8, ["message"])
                          ]),
                          createVNode("div", null, [
                            createVNode("div", { class: "mb-2" }, [
                              createVNode("span", { class: "text-sm font-medium text-black" }, "Report Days")
                            ]),
                            withDirectives(createVNode("input", {
                              "onUpdate:modelValue": ($event) => test.reportDays = $event,
                              type: "number",
                              class: "block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none"
                            }, null, 8, ["onUpdate:modelValue"]), [
                              [vModelText, test.reportDays]
                            ]),
                            createVNode(_sfc_main$2, {
                              class: "mt-1",
                              message: unref(form).errors[`tests.${index}.reportDays`]
                            }, null, 8, ["message"])
                          ]),
                          createVNode("div", null, [
                            createVNode("div", { class: "mb-2" }, [
                              createVNode("span", { class: "text-sm font-medium text-black" }, "Report Date"),
                              createVNode("span", { class: "text-red-500 ml-1" }, "*")
                            ]),
                            withDirectives(createVNode("input", {
                              id: `reportDate_${index}`,
                              "onUpdate:modelValue": ($event) => test.reportDate = $event,
                              type: "date",
                              onKeydown: withKeys(withModifiers(($event) => handleReportDateEnter(), ["prevent"]), ["enter"]),
                              class: "block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none"
                            }, null, 40, ["id", "onUpdate:modelValue", "onKeydown"]), [
                              [vModelText, test.reportDate]
                            ]),
                            createVNode(_sfc_main$2, {
                              class: "mt-1",
                              message: unref(form).errors[`tests.${index}.reportDate`]
                            }, null, 8, ["message"])
                          ]),
                          createVNode("div", null, [
                            createVNode("div", { class: "mb-2" }, [
                              createVNode("span", { class: "text-sm font-medium text-black" }, "Tax")
                            ]),
                            createVNode("div", { class: "relative" }, [
                              withDirectives(createVNode("input", {
                                "onUpdate:modelValue": ($event) => test.tax = $event,
                                type: "number",
                                step: "0.01",
                                class: "block w-full px-3 py-2 pr-8 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none"
                              }, null, 8, ["onUpdate:modelValue"]), [
                                [vModelText, test.tax]
                              ]),
                              createVNode("span", { class: "absolute right-3 top-2 text-sm text-gray-500" }, "%")
                            ]),
                            createVNode(_sfc_main$2, {
                              class: "mt-1",
                              message: unref(form).errors[`tests.${index}.tax`]
                            }, null, 8, ["message"])
                          ]),
                          createVNode("div", null, [
                            createVNode("div", { class: "mb-1 flex justify-between items-center" }, [
                              createVNode("span", { class: "text-sm font-medium text-black" }, "Amount (Tk.)"),
                              createVNode("button", {
                                type: "button",
                                onClick: ($event) => removeTest(test.id),
                                disabled: testRows.value.length === 1,
                                class: [testRows.value.length === 1 ? "text-gray-300 cursor-not-allowed" : "text-red-500 hover:text-red-700", "text-lg font-bold"]
                              }, "×", 10, ["onClick", "disabled"])
                            ]),
                            withDirectives(createVNode("input", {
                              "onUpdate:modelValue": ($event) => test.amount = $event,
                              type: "number",
                              step: "0.01",
                              class: "block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none"
                            }, null, 8, ["onUpdate:modelValue"]), [
                              [vModelText, test.amount]
                            ]),
                            createVNode(_sfc_main$2, {
                              class: "mt-1",
                              message: unref(form).errors[`tests.${index}.amount`]
                            }, null, 8, ["message"])
                          ])
                        ])
                      ]);
                    }), 128))
                  ]),
                  createVNode("div", { class: "mb-8" }, [
                    createVNode("button", {
                      type: "button",
                      onClick: addTest,
                      class: "inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-green-600 rounded hover:bg-green-700"
                    }, [
                      createVNode("span", { class: "mr-1" }, "+"),
                      createTextVNode(" Add Test ")
                    ])
                  ]),
                  createVNode("div", { class: "grid grid-cols-3 gap-6 mb-6" }, [
                    createVNode("div", null, [
                      createVNode("div", { class: "mb-2" }, [
                        createVNode("span", { class: "text-sm font-medium text-black" }, "Referral Doctor")
                      ]),
                      withDirectives(createVNode("select", {
                        id: "referralDoctor",
                        "onUpdate:modelValue": ($event) => unref(form).doctor_id = $event,
                        class: "block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none bg-white"
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
                        class: "mt-1",
                        message: unref(form).errors.doctor_id
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode("div", { class: "mb-2" }, [
                        createVNode("span", { class: "text-sm font-medium text-black" }, "Doctor Name")
                      ]),
                      withDirectives(createVNode("input", {
                        id: "doctor_name",
                        "onUpdate:modelValue": ($event) => unref(form).doctor_name = $event,
                        type: "text",
                        class: "block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).doctor_name]
                      ]),
                      createVNode(_sfc_main$2, {
                        class: "mt-1",
                        message: unref(form).errors.doctor_name
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode("div", { class: "mb-2" }, [
                        createVNode("span", { class: "text-sm font-medium text-black" }, "Payee")
                      ]),
                      withDirectives(createVNode("select", {
                        id: "payee_id",
                        "onUpdate:modelValue": ($event) => unref(form).payee_id = $event,
                        class: "block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none bg-white"
                      }, [
                        createVNode("option", { value: "" }, "Select Payee"),
                        (openBlock(true), createBlock(Fragment, null, renderList(__props.raferrers, (data) => {
                          return openBlock(), createBlock("option", {
                            key: data.id,
                            value: data.id
                          }, toDisplayString(data.name), 9, ["value"]);
                        }), 128))
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).payee_id]
                      ]),
                      createVNode(_sfc_main$2, {
                        class: "mt-1",
                        message: unref(form).errors.payee_id
                      }, null, 8, ["message"])
                    ])
                  ]),
                  createVNode("div", { class: "grid grid-cols-2 gap-6 mb-6" }, [
                    createVNode("div", null, [
                      createVNode("div", { class: "mb-2" }, [
                        createVNode("span", { class: "text-sm font-medium text-black" }, "Commission Percentage (%)")
                      ]),
                      withDirectives(createVNode("input", {
                        id: "commission_percentage",
                        "onUpdate:modelValue": ($event) => unref(form).commission_percentage = $event,
                        type: "number",
                        step: "0.01",
                        placeholder: "Percentage",
                        class: "block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).commission_percentage]
                      ]),
                      createVNode(_sfc_main$2, {
                        class: "mt-1",
                        message: unref(form).errors.commission_percentage
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode("div", { class: "mb-2" }, [
                        createVNode("span", { class: "text-sm font-medium text-black" }, "Commission Amount (Tk.)")
                      ]),
                      withDirectives(createVNode("input", {
                        id: "commission_amount",
                        "onUpdate:modelValue": ($event) => unref(form).commission_amount = $event,
                        type: "number",
                        step: "0.01",
                        placeholder: "Commission Amount",
                        class: "block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).commission_amount]
                      ]),
                      createVNode(_sfc_main$2, {
                        class: "mt-1",
                        message: unref(form).errors.commission_amount
                      }, null, 8, ["message"])
                    ])
                  ]),
                  createVNode("div", { class: "grid grid-cols-2 gap-8 mb-6" }, [
                    createVNode("div", null, [
                      createVNode("div", { class: "mb-2" }, [
                        createVNode("span", { class: "text-sm font-medium text-black" }, "Note")
                      ]),
                      withDirectives(createVNode("textarea", {
                        id: "note",
                        "onUpdate:modelValue": ($event) => unref(form).note = $event,
                        rows: "8",
                        class: "block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none resize-none"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).note]
                      ]),
                      createVNode(_sfc_main$2, {
                        class: "mt-1",
                        message: unref(form).errors.note
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "space-y-4" }, [
                      createVNode("div", { class: "space-y-3" }, [
                        createVNode("div", { class: "flex justify-between items-center" }, [
                          createVNode("span", { class: "text-sm text-black" }, "Subtotal (Tk.)"),
                          createVNode("span", { class: "text-sm font-medium" }, toDisplayString((parseFloat(unref(form).subtotal) || 0).toFixed(2)), 1)
                        ]),
                        createVNode("div", { class: "flex justify-between items-center" }, [
                          createVNode("span", { class: "text-sm text-black" }, "Discount"),
                          createVNode("div", { class: "flex items-center space-x-2" }, [
                            createVNode("div", { class: "flex items-center" }, [
                              withDirectives(createVNode("input", {
                                "onUpdate:modelValue": ($event) => unref(form).discount_percentage = $event,
                                type: "number",
                                step: "0.01",
                                placeholder: "0",
                                class: "w-16 px-2 py-1 text-xs border border-gray-300 rounded-l focus:border-blue-500 focus:outline-none text-center"
                              }, null, 8, ["onUpdate:modelValue"]), [
                                [vModelText, unref(form).discount_percentage]
                              ]),
                              createVNode("span", { class: "px-2 py-1 text-xs bg-gray-100 border border-l-0 border-gray-300 rounded-r" }, "%")
                            ]),
                            createVNode("span", { class: "text-sm text-gray-400" }, "="),
                            createVNode("span", { class: "text-sm font-medium" }, toDisplayString((parseFloat(unref(form).discount_amount) || 0).toFixed(2)) + " Tk.", 1)
                          ])
                        ]),
                        createVNode("div", { class: "flex justify-between items-center" }, [
                          createVNode("span", { class: "text-sm text-black" }, "VAT"),
                          createVNode("div", { class: "flex items-center space-x-2" }, [
                            createVNode("div", { class: "flex items-center" }, [
                              withDirectives(createVNode("input", {
                                "onUpdate:modelValue": ($event) => unref(form).vat_percentage = $event,
                                type: "number",
                                step: "0.01",
                                placeholder: "0",
                                class: "w-16 px-2 py-1 text-xs border border-gray-300 rounded-l focus:border-blue-500 focus:outline-none text-center"
                              }, null, 8, ["onUpdate:modelValue"]), [
                                [vModelText, unref(form).vat_percentage]
                              ]),
                              createVNode("span", { class: "px-2 py-1 text-xs bg-gray-100 border border-l-0 border-gray-300 rounded-r" }, "%")
                            ]),
                            createVNode("span", { class: "text-sm text-gray-400" }, "="),
                            createVNode("span", { class: "text-sm font-medium" }, toDisplayString((parseFloat(unref(form).vat_amount) || 0).toFixed(2)) + " Tk.", 1)
                          ])
                        ]),
                        createVNode("div", { class: "flex justify-between items-center" }, [
                          createVNode("span", { class: "text-sm text-black" }, "Tax"),
                          createVNode("div", { class: "flex items-center space-x-2" }, [
                            createVNode("div", { class: "flex items-center" }, [
                              withDirectives(createVNode("input", {
                                "onUpdate:modelValue": ($event) => unref(form).tax_percentage = $event,
                                type: "number",
                                step: "0.01",
                                placeholder: "0",
                                class: "w-16 px-2 py-1 text-xs border border-gray-300 rounded-l focus:border-blue-500 focus:outline-none text-center"
                              }, null, 8, ["onUpdate:modelValue"]), [
                                [vModelText, unref(form).tax_percentage]
                              ]),
                              createVNode("span", { class: "px-2 py-1 text-xs bg-gray-100 border border-l-0 border-gray-300 rounded-r" }, "%")
                            ]),
                            createVNode("span", { class: "text-sm text-gray-400" }, "="),
                            createVNode("span", { class: "text-sm font-medium" }, toDisplayString((parseFloat(unref(form).tax_amount) || 0).toFixed(2)) + " Tk.", 1)
                          ])
                        ]),
                        createVNode("div", { class: "flex justify-between items-center" }, [
                          createVNode("span", { class: "text-sm text-black" }, "Extra VAT"),
                          createVNode("div", { class: "flex items-center space-x-2" }, [
                            createVNode("div", { class: "flex items-center" }, [
                              withDirectives(createVNode("input", {
                                "onUpdate:modelValue": ($event) => unref(form).extra_vat_percentage = $event,
                                type: "number",
                                step: "0.01",
                                placeholder: "0",
                                class: "w-16 px-2 py-1 text-xs border border-gray-300 rounded-l focus:border-blue-500 focus:outline-none text-center"
                              }, null, 8, ["onUpdate:modelValue"]), [
                                [vModelText, unref(form).extra_vat_percentage]
                              ]),
                              createVNode("span", { class: "px-2 py-1 text-xs bg-gray-100 border border-l-0 border-gray-300 rounded-r" }, "%")
                            ]),
                            createVNode("span", { class: "text-sm text-gray-400" }, "="),
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
                        ]),
                        props.id && previousPaidAmount.value > 0 ? (openBlock(), createBlock("div", {
                          key: 0,
                          class: "flex justify-between items-center"
                        }, [
                          createVNode("span", { class: "text-sm text-gray-600" }, "Previous Paid Amount (Tk.)"),
                          createVNode("span", { class: "text-sm text-gray-600" }, toDisplayString(previousPaidAmount.value.toFixed(2)), 1)
                        ])) : createCommentVNode("", true)
                      ]),
                      createVNode("div", { class: "pt-4" }, [
                        createVNode("div", { class: "flex justify-between items-center mb-3" }, [
                          createVNode("span", { class: "text-sm font-medium text-black" }, "Payment Mode"),
                          createVNode("div", { class: "flex items-center" }, [
                            createVNode("span", { class: "text-sm font-medium text-black" }, toDisplayString(props.id ? "Payment Amount (Tk.)" : "Amount (Tk.)"), 1),
                            createVNode("span", {
                              class: "text-red-500 ml-1",
                              style: { "margin-right": "140px" }
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
                            type: "number",
                            step: "0.01",
                            placeholder: "Enter payment amount",
                            class: "px-3 py-2 text-sm text-end border border-gray-300 rounded focus:border-blue-500 focus:outline-none"
                          }, null, 8, ["onUpdate:modelValue"]), [
                            [vModelText, unref(form).payment_amount]
                          ])
                        ]),
                        createVNode(_sfc_main$2, {
                          class: "mt-1",
                          message: unref(form).errors.payment_amount
                        }, null, 8, ["message"])
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/Pathology/Form.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const Form = /* @__PURE__ */ _export_sfc(_sfc_main, [["__scopeId", "data-v-703c124f"]]);
export {
  Form as default
};
