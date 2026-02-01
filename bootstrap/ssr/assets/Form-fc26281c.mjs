import { ref, onMounted, computed, watch, withCtx, unref, createTextVNode, toDisplayString, createVNode, openBlock, createBlock, withModifiers, Fragment, renderList, withDirectives, vModelText, createCommentVNode, withKeys, vModelSelect, useSSRContext } from "vue";
import { ssrRenderComponent, ssrRenderList, ssrRenderAttr, ssrRenderClass, ssrInterpolate, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import { useForm, router } from "@inertiajs/vue3";
import { _ as _sfc_main$2 } from "./InputError-6aeb8d97.mjs";
import { _ as _sfc_main$3 } from "./InputLabel-70ca52d1.mjs";
import { _ as _sfc_main$4 } from "./PrimaryButton-b82fb16e.mjs";
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
const Form_vue_vue_type_style_index_0_scoped_ac352850_lang = "";
const _sfc_main = {
  __name: "Form",
  __ssrInlineRender: true,
  props: ["radiology", "id", "radiologyTests", "patients", "doctors", "radiologyNo", "billNo", "lastCaseId"],
  setup(__props) {
    var _a, _b, _c, _d, _e, _f, _g, _h, _i, _j, _k, _l;
    const props = __props;
    const searchQueries = ref({});
    const showDropdowns = ref({});
    const selectedTestIndex = ref({});
    ref({});
    const createTestRow = () => ({
      id: Date.now() + Math.random(),
      testId: "",
      test_name: "",
      reportDays: "",
      reportDate: "",
      tax: 0,
      amount: 0
    });
    const testRows = ref([]);
    const form = useForm({
      case_id: ((_a = props.radiology) == null ? void 0 : _a.case_id) ?? "",
      patient_id: ((_b = props.radiology) == null ? void 0 : _b.patient_id) ?? "",
      referral_doctor_id: ((_c = props.radiology) == null ? void 0 : _c.referral_doctor_id) ?? "",
      doctor_name: ((_d = props.radiology) == null ? void 0 : _d.doctor_name) ?? "",
      note: ((_e = props.radiology) == null ? void 0 : _e.note) ?? "",
      // Financial fields
      total_amount: ((_f = props.radiology) == null ? void 0 : _f.total_amount) ?? 0,
      tax_amount: ((_g = props.radiology) == null ? void 0 : _g.tax_amount) ?? 0,
      discount_amount: ((_h = props.radiology) == null ? void 0 : _h.discount_amount) ?? 0,
      discount_percentage: ((_i = props.radiology) == null ? void 0 : _i.discount_percentage) ?? 0,
      net_amount: ((_j = props.radiology) == null ? void 0 : _j.net_amount) ?? 0,
      // Payment fields
      payment_mode: ((_k = props.radiology) == null ? void 0 : _k.payment_mode) ?? "Cash",
      payment_amount: ((_l = props.radiology) == null ? void 0 : _l.payment_amount) ?? 0,
      // Test details will be JSON
      tests: [],
      _method: props.id ? "put" : "post"
    });
    onMounted(() => {
      if (props.radiology && props.radiology.tests && props.radiology.tests.length > 0) {
        testRows.value = props.radiology.tests.map((test, index) => ({
          id: test.id || Date.now() + index,
          testId: test.testId || "",
          test_name: test.test_name || "",
          reportDays: test.reportDays || "",
          reportDate: test.reportDate || "",
          tax: parseFloat(test.tax || 0),
          amount: parseFloat(test.amount || 0)
        }));
        testRows.value.forEach((test) => {
          if (test.testId) {
            searchQueries.value[test.id] = test.test_name;
          }
        });
      } else {
        testRows.value = [createTestRow()];
      }
      testRows.value.forEach((test) => {
        if (!searchQueries.value[test.id]) {
          searchQueries.value[test.id] = "";
        }
        showDropdowns.value[test.id] = false;
        selectedTestIndex.value[test.id] = -1;
      });
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
        }
      });
    };
    const isTestAlreadySelected = (testId, currentTestRowId) => {
      return testRows.value.some((row) => row.testId === testId && row.id !== currentTestRowId);
    };
    const getFilteredTests = (testId) => {
      var _a2;
      const query = ((_a2 = searchQueries.value[testId]) == null ? void 0 : _a2.toLowerCase()) || "";
      if (!query)
        return props.radiologyTests || [];
      return (props.radiologyTests || []).filter(
        (test) => {
          var _a3;
          return test.test_name.toLowerCase().includes(query) || ((_a3 = test.test_short_name) == null ? void 0 : _a3.toLowerCase().includes(query));
        }
      );
    };
    const handleTestSearch = (index, value) => {
      const test = testRows.value[index];
      searchQueries.value[test.id] = value;
      selectedTestIndex.value[test.id] = -1;
      showDropdowns.value[test.id] = true;
      if (!value) {
        test.testId = "";
        test.test_name = "";
        test.reportDays = "";
        test.reportDate = "";
        test.tax = 0;
        test.amount = 0;
      }
    };
    const handleTestFocus = (index) => {
      const test = testRows.value[index];
      showDropdowns.value[test.id] = true;
      selectedTestIndex.value[test.id] = -1;
    };
    const handleTestBlur = (index, event) => {
      const test = testRows.value[index];
      const isClickingDropdown = event.relatedTarget && event.relatedTarget.closest(`[data-dropdown-id="${test.id}"]`);
      if (!isClickingDropdown) {
        setTimeout(() => {
          showDropdowns.value[test.id] = false;
          selectedTestIndex.value[test.id] = -1;
        }, 200);
      }
    };
    const handleTestKeydown = (index, event) => {
      const test = testRows.value[index];
      const filteredTests = getFilteredTests(test.id);
      if (!showDropdowns.value[test.id] || filteredTests.length === 0) {
        return;
      }
      switch (event.key) {
        case "ArrowDown":
          event.preventDefault();
          selectedTestIndex.value[test.id] = Math.min(
            selectedTestIndex.value[test.id] + 1,
            filteredTests.length - 1
          );
          scrollToSelectedOption(test.id);
          break;
        case "ArrowUp":
          event.preventDefault();
          selectedTestIndex.value[test.id] = Math.max(
            selectedTestIndex.value[test.id] - 1,
            0
          );
          scrollToSelectedOption(test.id);
          break;
        case "Enter":
          event.preventDefault();
          const selectedIndex = selectedTestIndex.value[test.id];
          if (selectedIndex >= 0 && selectedIndex < filteredTests.length) {
            handleDropdownClick(index, filteredTests[selectedIndex]);
          } else if (filteredTests.length > 0) {
            handleDropdownClick(index, filteredTests[0]);
          }
          break;
        case "Escape":
          event.preventDefault();
          showDropdowns.value[test.id] = false;
          selectedTestIndex.value[test.id] = -1;
          break;
      }
    };
    const scrollToSelectedOption = (testId) => {
      const selectedIndex = selectedTestIndex.value[testId];
      if (selectedIndex >= 0) {
        setTimeout(() => {
          const dropdownElement = document.querySelector(`[data-dropdown-id="${testId}"]`);
          const selectedOption = dropdownElement == null ? void 0 : dropdownElement.querySelector(`[data-option-index="${selectedIndex}"]`);
          if (selectedOption && dropdownElement) {
            const optionTop = selectedOption.offsetTop;
            const optionHeight = selectedOption.offsetHeight;
            const dropdownScrollTop = dropdownElement.scrollTop;
            const dropdownHeight = dropdownElement.clientHeight;
            if (optionTop < dropdownScrollTop) {
              dropdownElement.scrollTop = optionTop;
            } else if (optionTop + optionHeight > dropdownScrollTop + dropdownHeight) {
              dropdownElement.scrollTop = optionTop + optionHeight - dropdownHeight;
            }
          }
        }, 0);
      }
    };
    const handleDropdownClick = (index, selectedTest) => {
      const test = testRows.value[index];
      if (isTestAlreadySelected(selectedTest.id, test.id)) {
        alert("This test is already selected. Please choose a different test.");
        return;
      }
      test.testId = selectedTest.id;
      test.test_name = selectedTest.test_name;
      test.reportDays = selectedTest.report_days || 0;
      test.tax = parseFloat(selectedTest.tax || 0);
      test.amount = parseFloat(selectedTest.amount || selectedTest.standard_charge || 0);
      searchQueries.value[test.id] = selectedTest.test_name;
      showDropdowns.value[test.id] = false;
      selectedTestIndex.value[test.id] = -1;
      if (selectedTest.report_days && selectedTest.report_days > 0) {
        const currentDate = /* @__PURE__ */ new Date();
        currentDate.setDate(currentDate.getDate() + parseInt(selectedTest.report_days));
        test.reportDate = currentDate.toISOString().split("T")[0];
      } else {
        test.reportDate = (/* @__PURE__ */ new Date()).toISOString().split("T")[0];
      }
      setTimeout(() => {
        var _a2;
        (_a2 = document.getElementById(`reportDate_${index}`)) == null ? void 0 : _a2.focus();
      }, 100);
    };
    const handleReportDateEnter = (index) => {
      const test = testRows.value[index];
      if (!test.testId || !test.reportDate) {
        if (!test.testId) {
          alert("Please select a test first.");
          setTimeout(() => {
            var _a2;
            (_a2 = document.getElementById(`testSearch_${index}`)) == null ? void 0 : _a2.focus();
          }, 100);
        }
        return;
      }
      if (index < testRows.value.length - 1) {
        const nextIndex = index + 1;
        setTimeout(() => {
          var _a2;
          (_a2 = document.getElementById(`testSearch_${nextIndex}`)) == null ? void 0 : _a2.focus();
        }, 100);
      } else {
        addTest();
      }
    };
    const totalAmount = computed(() => {
      return testRows.value.reduce((sum, row) => sum + (parseFloat(row.amount) || 0), 0);
    });
    const taxAmount = computed(() => {
      return testRows.value.reduce((sum, row) => {
        const rowAmount = parseFloat(row.amount) || 0;
        const rowTax = parseFloat(row.tax) || 0;
        return sum + rowAmount * rowTax / 100;
      }, 0);
    });
    const onDiscountPercentageChange = () => {
      if (form.discount_percentage > 0) {
        form.discount_amount = 0;
      }
    };
    const onDiscountAmountChange = () => {
      if (form.discount_amount > 0) {
        form.discount_percentage = 0;
      }
    };
    const discountAmount = computed(() => {
      if (form.discount_percentage > 0) {
        return totalAmount.value * parseFloat(form.discount_percentage) / 100;
      }
      return parseFloat(form.discount_amount) || 0;
    });
    const netAmount = computed(() => {
      return totalAmount.value + taxAmount.value - discountAmount.value;
    });
    watch(netAmount, (newAmount) => {
      if (!form.payment_amount || form.payment_amount === 0) {
        form.payment_amount = newAmount;
      }
    });
    watch([totalAmount, taxAmount, discountAmount, netAmount], ([total, tax, discount, net]) => {
      form.total_amount = total;
      form.tax_amount = tax;
      form.discount_amount = discount;
      form.net_amount = net;
    });
    const addTest = () => {
      const newTest = createTestRow();
      testRows.value.push(newTest);
      searchQueries.value[newTest.id] = "";
      showDropdowns.value[newTest.id] = false;
      selectedTestIndex.value[newTest.id] = -1;
      setTimeout(() => {
        var _a2;
        const newIndex = testRows.value.length - 1;
        (_a2 = document.getElementById(`testSearch_${newIndex}`)) == null ? void 0 : _a2.focus();
      }, 100);
    };
    const removeTest = (testId) => {
      if (testRows.value.length > 1) {
        testRows.value = testRows.value.filter((row) => row.id !== testId);
        delete searchQueries.value[testId];
        delete showDropdowns.value[testId];
        delete selectedTestIndex.value[testId];
      }
    };
    const submit = () => {
      const routeName = props.id ? route("backend.radiology.update", props.id) : route("backend.radiology.store");
      const validTests = testRows.value.filter((row) => row.testId && row.amount > 0);
      if (validTests.length === 0) {
        alert("Please select at least one test with valid amount.");
        return;
      }
      const testIds = validTests.map((test) => test.testId);
      const uniqueTestIds = [...new Set(testIds)];
      if (testIds.length !== uniqueTestIds.length) {
        alert("Duplicate tests found. Please remove duplicate entries.");
        return;
      }
      const testsData = validTests.map((test) => ({
        testId: test.testId,
        testName: test.test_name,
        reportDays: test.reportDays || 0,
        reportDate: test.reportDate,
        tax: test.tax || 0,
        amount: test.amount
      }));
      const formData = {
        ...form.data(),
        tests: testsData,
        subtotal: totalAmount.value,
        net_amount: netAmount.value
      };
      form.transform(() => ({
        ...formData,
        patient_id: typeof formData.patient_id === "object" ? formData.patient_id.id : formData.patient_id
      })).post(routeName, {
        onSuccess: (response) => {
          var _a2, _b2, _c2, _d2;
          if (props.id) {
            router.reload({
              only: ["radiology"],
              preserveScroll: true,
              onSuccess: () => {
                var _a3, _b3, _c3, _d3;
                const successMessage = (_b3 = (_a3 = response == null ? void 0 : response.props) == null ? void 0 : _a3.flash) == null ? void 0 : _b3.successMessage;
                const billId = (_d3 = (_c3 = response == null ? void 0 : response.props) == null ? void 0 : _c3.flash) == null ? void 0 : _d3.billId;
                if (successMessage && billId) {
                  window.open(route("backend.download.invoice", { id: billId, module: "radiology" }), "_blank");
                }
              }
            });
          } else {
            form.reset();
            testRows.value = [createTestRow()];
            searchQueries.value = {};
            showDropdowns.value = {};
            selectedTestIndex.value = {};
            const successMessage = (_b2 = (_a2 = response == null ? void 0 : response.props) == null ? void 0 : _a2.flash) == null ? void 0 : _b2.successMessage;
            const billId = (_d2 = (_c2 = response == null ? void 0 : response.props) == null ? void 0 : _c2.flash) == null ? void 0 : _d2.billId;
            if (successMessage && billId) {
              window.open(route("backend.download.invoice", { id: billId, module: "radiology" }), "_blank");
            }
          }
          displayResponse(response);
        },
        onError: (errorObject) => {
          displayWarning(errorObject);
        }
      });
    };
    const formatCurrency = (amount) => {
      return parseFloat(amount || 0).toFixed(2);
    };
    const goToRadiologyList = () => {
      router.visit(route("backend.radiology.index"));
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(PatientModal, {
              isOpen: isPatientModalOpen.value,
              onClose: closePatientModal,
              onPatientCreated: handlePatientCreated
            }, null, _parent2, _scopeId));
            _push2(`<div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md" data-v-ac352850${_scopeId}><div class="flex items-center justify-between w-full px-4 bg-gray-100 rounded-md" data-v-ac352850${_scopeId}><div class="flex items-center space-x-4" data-v-ac352850${_scopeId}><div class="relative min-w-[280px]" data-v-ac352850${_scopeId}><div class="relative" data-v-ac352850${_scopeId}><div class="col-span-1" data-v-ac352850${_scopeId}>`);
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
            _push2(`</div></div></div><button class="px-3 py-2.5 text-sm text-white bg-blue-600 rounded hover:bg-blue-700" data-v-ac352850${_scopeId}> + New Patient </button></div><div class="p-2 py-2 flex items-center space-x-2" data-v-ac352850${_scopeId}><div class="flex items-center space-x-3" data-v-ac352850${_scopeId}><button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2" data-v-ac352850${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" data-v-ac352850${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" data-v-ac352850${_scopeId}></path></svg> Radiology List </button></div></div></div><form class="p-3" data-v-ac352850${_scopeId}><div class="mb-6" data-v-ac352850${_scopeId}><!--[-->`);
            ssrRenderList(testRows.value, (test, index) => {
              _push2(`<div class="mb-4" data-v-ac352850${_scopeId}><div class="grid grid-cols-5 gap-6" data-v-ac352850${_scopeId}><div class="relative" data-v-ac352850${_scopeId}><div class="mb-2" data-v-ac352850${_scopeId}><span class="text-sm font-medium text-black" data-v-ac352850${_scopeId}>Test Name</span><span class="text-red-500 ml-1" data-v-ac352850${_scopeId}>*</span></div><input${ssrRenderAttr("id", `testSearch_${index}`)}${ssrRenderAttr("value", searchQueries.value[test.id])} type="text" placeholder="Search test..." class="block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none" autocomplete="off" data-v-ac352850${_scopeId}>`);
              if (showDropdowns.value[test.id] && getFilteredTests(test.id).length > 0) {
                _push2(`<div${ssrRenderAttr("data-dropdown-id", test.id)} class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto" data-v-ac352850${_scopeId}><!--[-->`);
                ssrRenderList(getFilteredTests(test.id), (radiologyTest, optionIndex) => {
                  _push2(`<div${ssrRenderAttr("data-option-index", optionIndex)} class="${ssrRenderClass([
                    "px-3 py-2 text-sm cursor-pointer border-b border-gray-100 last:border-b-0",
                    selectedTestIndex.value[test.id] === optionIndex ? "bg-blue-100 text-blue-700" : "hover:bg-blue-50 hover:text-blue-700",
                    isTestAlreadySelected(radiologyTest.id, test.id) ? "opacity-50 cursor-not-allowed" : ""
                  ])}" data-v-ac352850${_scopeId}><div class="font-medium" data-v-ac352850${_scopeId}>${ssrInterpolate(radiologyTest.test_name)} `);
                  if (isTestAlreadySelected(radiologyTest.id, test.id)) {
                    _push2(`<span class="text-red-500 text-xs ml-2" data-v-ac352850${_scopeId}>(Already Selected)</span>`);
                  } else {
                    _push2(`<!---->`);
                  }
                  _push2(`</div><div class="text-xs text-gray-500" data-v-ac352850${_scopeId}>Amount: ${ssrInterpolate(radiologyTest.amount || radiologyTest.standard_charge || "N/A")} Tk.</div></div>`);
                });
                _push2(`<!--]--></div>`);
              } else {
                _push2(`<!---->`);
              }
              if (showDropdowns.value[test.id] && getFilteredTests(test.id).length === 0 && searchQueries.value[test.id]) {
                _push2(`<div class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg" data-v-ac352850${_scopeId}><div class="px-3 py-2 text-sm text-gray-500 text-center" data-v-ac352850${_scopeId}> No tests found </div></div>`);
              } else {
                _push2(`<!---->`);
              }
              _push2(ssrRenderComponent(_sfc_main$2, {
                class: "mt-1",
                message: unref(form).errors[`tests.${index}.testId`]
              }, null, _parent2, _scopeId));
              _push2(`</div><div data-v-ac352850${_scopeId}><div class="mb-2" data-v-ac352850${_scopeId}><span class="text-sm font-medium text-black" data-v-ac352850${_scopeId}>Report Days</span></div><input${ssrRenderAttr("value", test.reportDays)} type="number" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none" data-v-ac352850${_scopeId}>`);
              _push2(ssrRenderComponent(_sfc_main$2, {
                class: "mt-1",
                message: unref(form).errors[`tests.${index}.reportDays`]
              }, null, _parent2, _scopeId));
              _push2(`</div><div data-v-ac352850${_scopeId}><div class="mb-2" data-v-ac352850${_scopeId}><span class="text-sm font-medium text-black" data-v-ac352850${_scopeId}>Report Date</span><span class="text-red-500 ml-1" data-v-ac352850${_scopeId}>*</span></div><input${ssrRenderAttr("id", `reportDate_${index}`)}${ssrRenderAttr("value", test.reportDate)} type="date" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none" data-v-ac352850${_scopeId}>`);
              _push2(ssrRenderComponent(_sfc_main$2, {
                class: "mt-1",
                message: unref(form).errors[`tests.${index}.reportDate`]
              }, null, _parent2, _scopeId));
              _push2(`</div><div data-v-ac352850${_scopeId}><div class="mb-2" data-v-ac352850${_scopeId}><span class="text-sm font-medium text-black" data-v-ac352850${_scopeId}>Tax</span></div><div class="relative" data-v-ac352850${_scopeId}><input${ssrRenderAttr("value", test.tax)} type="number" step="0.01" class="block w-full px-3 py-2 pr-8 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none" data-v-ac352850${_scopeId}><span class="absolute right-3 top-2 text-sm text-gray-500" data-v-ac352850${_scopeId}>%</span></div>`);
              _push2(ssrRenderComponent(_sfc_main$2, {
                class: "mt-1",
                message: unref(form).errors[`tests.${index}.tax`]
              }, null, _parent2, _scopeId));
              _push2(`</div><div data-v-ac352850${_scopeId}><div class="mb-1 flex justify-between items-center" data-v-ac352850${_scopeId}><span class="text-sm font-medium text-black" data-v-ac352850${_scopeId}>Amount (Tk.)</span><button type="button"${ssrIncludeBooleanAttr(testRows.value.length === 1) ? " disabled" : ""} class="${ssrRenderClass([testRows.value.length === 1 ? "text-gray-300 cursor-not-allowed" : "text-red-500 hover:text-red-700", "text-lg font-bold"])}" data-v-ac352850${_scopeId}>×</button></div><input${ssrRenderAttr("value", test.amount)} type="number" step="0.01" disabled class="block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none" data-v-ac352850${_scopeId}>`);
              _push2(ssrRenderComponent(_sfc_main$2, {
                class: "mt-1",
                message: unref(form).errors[`tests.${index}.amount`]
              }, null, _parent2, _scopeId));
              _push2(`</div></div></div>`);
            });
            _push2(`<!--]--></div><div class="mb-8" data-v-ac352850${_scopeId}><button type="button" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-green-600 rounded hover:bg-green-700" data-v-ac352850${_scopeId}><span class="mr-1" data-v-ac352850${_scopeId}>+</span> Add Test </button></div><div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-6" data-v-ac352850${_scopeId}><div class="space-y-6" data-v-ac352850${_scopeId}><div data-v-ac352850${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "referral_doctor_id",
              value: "Referral Doctor"
            }, null, _parent2, _scopeId));
            _push2(`<select id="referral_doctor_id" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-ac352850${_scopeId}><option value="" data-v-ac352850${ssrIncludeBooleanAttr(Array.isArray(unref(form).referral_doctor_id) ? ssrLooseContain(unref(form).referral_doctor_id, "") : ssrLooseEqual(unref(form).referral_doctor_id, "")) ? " selected" : ""}${_scopeId}>Select</option><!--[-->`);
            ssrRenderList(__props.doctors, (doctor) => {
              _push2(`<option${ssrRenderAttr("value", doctor.id)} data-v-ac352850${ssrIncludeBooleanAttr(Array.isArray(unref(form).referral_doctor_id) ? ssrLooseContain(unref(form).referral_doctor_id, doctor.id) : ssrLooseEqual(unref(form).referral_doctor_id, doctor.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(doctor.name)}</option>`);
            });
            _push2(`<!--]--></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-2",
              message: unref(form).errors.referral_doctor_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-ac352850${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "doctor_name",
              value: "Doctor Name"
            }, null, _parent2, _scopeId));
            _push2(`<input id="doctor_name"${ssrRenderAttr("value", unref(form).doctor_name)} type="text" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-ac352850${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-2",
              message: unref(form).errors.doctor_name
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-ac352850${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "note",
              value: "Note"
            }, null, _parent2, _scopeId));
            _push2(`<textarea id="note" rows="4" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-ac352850${_scopeId}>${ssrInterpolate(unref(form).note)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-2",
              message: unref(form).errors.note
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="space-y-4 bg-gray-50 dark:bg-gray-800 p-4 rounded-lg" data-v-ac352850${_scopeId}><h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 border-b pb-2" data-v-ac352850${_scopeId}>Bill Summary </h3><div class="grid grid-cols-2 gap-4 items-center" data-v-ac352850${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, { value: "Subtotal (Tk.)" }, null, _parent2, _scopeId));
            _push2(`<div class="text-right font-semibold text-lg dark:text-white" data-v-ac352850${_scopeId}>${ssrInterpolate(formatCurrency(totalAmount.value))}</div></div><div class="grid grid-cols-2 gap-4 items-center" data-v-ac352850${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, { value: "Tax Amount (Tk.)" }, null, _parent2, _scopeId));
            _push2(`<div class="text-right font-semibold text-orange-600 dark:text-orange-400" data-v-ac352850${_scopeId}>${ssrInterpolate(formatCurrency(taxAmount.value))}</div></div><div class="grid grid-cols-2 gap-4 items-center" data-v-ac352850${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, { value: "Discount (%)" }, null, _parent2, _scopeId));
            _push2(`<div class="flex items-center justify-end space-x-2" data-v-ac352850${_scopeId}><input${ssrRenderAttr("value", unref(form).discount_percentage)} type="number" step="0.01" min="0" max="100" placeholder="0" class="w-20 p-1 text-sm rounded border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 text-right" data-v-ac352850${_scopeId}><span class="text-xs text-gray-500" data-v-ac352850${_scopeId}>%</span><span class="font-semibold text-red-600 dark:text-red-400 min-w-[80px] text-right" data-v-ac352850${_scopeId}>${ssrInterpolate(unref(form).discount_percentage > 0 ? "-" + formatCurrency(totalAmount.value * unref(form).discount_percentage / 100) : "0.00")}</span></div></div><div class="grid grid-cols-2 gap-4 items-center" data-v-ac352850${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, { value: "Discount (Tk.)" }, null, _parent2, _scopeId));
            _push2(`<div class="flex items-center justify-end space-x-2" data-v-ac352850${_scopeId}><input${ssrRenderAttr("value", unref(form).discount_amount)} type="number" step="0.01" min="0" placeholder="0" class="w-24 p-1 text-sm rounded border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 text-right" data-v-ac352850${_scopeId}><span class="text-xs text-gray-500" data-v-ac352850${_scopeId}>Tk</span><span class="font-semibold text-red-600 dark:text-red-400 min-w-[80px] text-right" data-v-ac352850${_scopeId}>${ssrInterpolate(unref(form).discount_amount > 0 ? "-" + formatCurrency(unref(form).discount_amount) : "0.00")}</span></div></div><div class="grid grid-cols-2 gap-4 items-center bg-red-50 dark:bg-red-900/20 p-2 rounded" data-v-ac352850${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              value: "Total Discount",
              class: "font-semibold"
            }, null, _parent2, _scopeId));
            _push2(`<div class="text-right font-bold text-red-600 dark:text-red-400" data-v-ac352850${_scopeId}> -${ssrInterpolate(formatCurrency(discountAmount.value))}</div></div><div class="grid grid-cols-2 gap-4 items-center border-t pt-3 dark:border-slate-600" data-v-ac352850${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              value: "Net Amount (Tk.)",
              class: "text-lg font-bold"
            }, null, _parent2, _scopeId));
            _push2(`<div class="text-right font-bold text-xl text-green-600 dark:text-green-400" data-v-ac352850${_scopeId}>${ssrInterpolate(formatCurrency(netAmount.value))}</div></div><div class="grid grid-cols-2 gap-4 mt-4" data-v-ac352850${_scopeId}><div data-v-ac352850${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "payment_mode",
              value: "Payment Mode"
            }, null, _parent2, _scopeId));
            _push2(`<select id="payment_mode" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-ac352850${_scopeId}><option value="Cash" data-v-ac352850${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, "Cash") : ssrLooseEqual(unref(form).payment_mode, "Cash")) ? " selected" : ""}${_scopeId}>Cash</option><option value="Card" data-v-ac352850${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, "Card") : ssrLooseEqual(unref(form).payment_mode, "Card")) ? " selected" : ""}${_scopeId}>Card</option><option value="Bank Transfer" data-v-ac352850${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, "Bank Transfer") : ssrLooseEqual(unref(form).payment_mode, "Bank Transfer")) ? " selected" : ""}${_scopeId}>Bank Transfer</option><option value="Mobile Banking" data-v-ac352850${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, "Mobile Banking") : ssrLooseEqual(unref(form).payment_mode, "Mobile Banking")) ? " selected" : ""}${_scopeId}>Mobile Banking</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-2",
              message: unref(form).errors.payment_mode
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-ac352850${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "payment_amount",
              value: "Payment Amount (Tk.)"
            }, null, _parent2, _scopeId));
            _push2(`<input id="payment_amount"${ssrRenderAttr("value", unref(form).payment_amount)} type="number" step="0.01" min="0" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-ac352850${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-2",
              message: unref(form).errors.payment_amount
            }, null, _parent2, _scopeId));
            if (unref(form).payment_amount < netAmount.value) {
              _push2(`<div class="text-xs text-orange-600 mt-1" data-v-ac352850${_scopeId}> Due: ${ssrInterpolate(formatCurrency(netAmount.value - unref(form).payment_amount))}</div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div></div></div></div><div class="flex items-center justify-end mt-6 pt-4 border-t dark:border-slate-600" data-v-ac352850${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              type: "submit",
              class: ["ms-4", { "opacity-25": unref(form).processing }],
              disabled: unref(form).processing
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`${ssrInterpolate(props.id ?? false ? "Update Bill" : "Create Bill")}`);
                } else {
                  return [
                    createTextVNode(toDisplayString(props.id ?? false ? "Update Bill" : "Create Bill"), 1)
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
                onClose: closePatientModal,
                onPatientCreated: handlePatientCreated
              }, null, 8, ["isOpen"]),
              createVNode("div", { class: "w-full transition duration-1000 ease-in-out transform bg-white rounded-md" }, [
                createVNode("div", { class: "flex items-center justify-between w-full px-4 bg-gray-100 rounded-md" }, [
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
                    }, " + New Patient ")
                  ]),
                  createVNode("div", { class: "p-2 py-2 flex items-center space-x-2" }, [
                    createVNode("div", { class: "flex items-center space-x-3" }, [
                      createVNode("button", {
                        onClick: goToRadiologyList,
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
                        createTextVNode(" Radiology List ")
                      ])
                    ])
                  ])
                ]),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"]),
                  class: "p-3"
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
                              onInput: ($event) => handleTestSearch(index, $event.target.value),
                              onFocus: ($event) => handleTestFocus(index),
                              onBlur: ($event) => handleTestBlur(index, $event),
                              onKeydown: ($event) => handleTestKeydown(index, $event),
                              type: "text",
                              placeholder: "Search test...",
                              class: "block w-full px-3 py-2 text-sm border border-gray-300 rounded focus:border-blue-500 focus:outline-none",
                              autocomplete: "off"
                            }, null, 40, ["id", "onUpdate:modelValue", "onInput", "onFocus", "onBlur", "onKeydown"]), [
                              [vModelText, searchQueries.value[test.id]]
                            ]),
                            showDropdowns.value[test.id] && getFilteredTests(test.id).length > 0 ? (openBlock(), createBlock("div", {
                              key: 0,
                              "data-dropdown-id": test.id,
                              class: "absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto"
                            }, [
                              (openBlock(true), createBlock(Fragment, null, renderList(getFilteredTests(test.id), (radiologyTest, optionIndex) => {
                                return openBlock(), createBlock("div", {
                                  key: radiologyTest.id,
                                  "data-option-index": optionIndex,
                                  onClick: ($event) => handleDropdownClick(index, radiologyTest),
                                  class: [
                                    "px-3 py-2 text-sm cursor-pointer border-b border-gray-100 last:border-b-0",
                                    selectedTestIndex.value[test.id] === optionIndex ? "bg-blue-100 text-blue-700" : "hover:bg-blue-50 hover:text-blue-700",
                                    isTestAlreadySelected(radiologyTest.id, test.id) ? "opacity-50 cursor-not-allowed" : ""
                                  ]
                                }, [
                                  createVNode("div", { class: "font-medium" }, [
                                    createTextVNode(toDisplayString(radiologyTest.test_name) + " ", 1),
                                    isTestAlreadySelected(radiologyTest.id, test.id) ? (openBlock(), createBlock("span", {
                                      key: 0,
                                      class: "text-red-500 text-xs ml-2"
                                    }, "(Already Selected)")) : createCommentVNode("", true)
                                  ]),
                                  createVNode("div", { class: "text-xs text-gray-500" }, "Amount: " + toDisplayString(radiologyTest.amount || radiologyTest.standard_charge || "N/A") + " Tk.", 1)
                                ], 10, ["data-option-index", "onClick"]);
                              }), 128))
                            ], 8, ["data-dropdown-id"])) : createCommentVNode("", true),
                            showDropdowns.value[test.id] && getFilteredTests(test.id).length === 0 && searchQueries.value[test.id] ? (openBlock(), createBlock("div", {
                              key: 1,
                              class: "absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg"
                            }, [
                              createVNode("div", { class: "px-3 py-2 text-sm text-gray-500 text-center" }, " No tests found ")
                            ])) : createCommentVNode("", true),
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
                              onKeydown: withKeys(withModifiers(($event) => handleReportDateEnter(index), ["prevent"]), ["enter"]),
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
                              disabled: "",
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
                  createVNode("div", { class: "grid grid-cols-1 md:grid-cols-2 gap-8 mb-6" }, [
                    createVNode("div", { class: "space-y-6" }, [
                      createVNode("div", null, [
                        createVNode(_sfc_main$3, {
                          for: "referral_doctor_id",
                          value: "Referral Doctor"
                        }),
                        withDirectives(createVNode("select", {
                          id: "referral_doctor_id",
                          "onUpdate:modelValue": ($event) => unref(form).referral_doctor_id = $event,
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, [
                          createVNode("option", { value: "" }, "Select"),
                          (openBlock(true), createBlock(Fragment, null, renderList(__props.doctors, (doctor) => {
                            return openBlock(), createBlock("option", {
                              key: doctor.id,
                              value: doctor.id
                            }, toDisplayString(doctor.name), 9, ["value"]);
                          }), 128))
                        ], 8, ["onUpdate:modelValue"]), [
                          [vModelSelect, unref(form).referral_doctor_id]
                        ]),
                        createVNode(_sfc_main$2, {
                          class: "mt-2",
                          message: unref(form).errors.referral_doctor_id
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", null, [
                        createVNode(_sfc_main$3, {
                          for: "doctor_name",
                          value: "Doctor Name"
                        }),
                        withDirectives(createVNode("input", {
                          id: "doctor_name",
                          "onUpdate:modelValue": ($event) => unref(form).doctor_name = $event,
                          type: "text",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).doctor_name]
                        ]),
                        createVNode(_sfc_main$2, {
                          class: "mt-2",
                          message: unref(form).errors.doctor_name
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", null, [
                        createVNode(_sfc_main$3, {
                          for: "note",
                          value: "Note"
                        }),
                        withDirectives(createVNode("textarea", {
                          id: "note",
                          "onUpdate:modelValue": ($event) => unref(form).note = $event,
                          rows: "4",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).note]
                        ]),
                        createVNode(_sfc_main$2, {
                          class: "mt-2",
                          message: unref(form).errors.note
                        }, null, 8, ["message"])
                      ])
                    ]),
                    createVNode("div", { class: "space-y-4 bg-gray-50 dark:bg-gray-800 p-4 rounded-lg" }, [
                      createVNode("h3", { class: "text-lg font-semibold text-gray-800 dark:text-gray-200 border-b pb-2" }, "Bill Summary "),
                      createVNode("div", { class: "grid grid-cols-2 gap-4 items-center" }, [
                        createVNode(_sfc_main$3, { value: "Subtotal (Tk.)" }),
                        createVNode("div", { class: "text-right font-semibold text-lg dark:text-white" }, toDisplayString(formatCurrency(totalAmount.value)), 1)
                      ]),
                      createVNode("div", { class: "grid grid-cols-2 gap-4 items-center" }, [
                        createVNode(_sfc_main$3, { value: "Tax Amount (Tk.)" }),
                        createVNode("div", { class: "text-right font-semibold text-orange-600 dark:text-orange-400" }, toDisplayString(formatCurrency(taxAmount.value)), 1)
                      ]),
                      createVNode("div", { class: "grid grid-cols-2 gap-4 items-center" }, [
                        createVNode(_sfc_main$3, { value: "Discount (%)" }),
                        createVNode("div", { class: "flex items-center justify-end space-x-2" }, [
                          withDirectives(createVNode("input", {
                            "onUpdate:modelValue": ($event) => unref(form).discount_percentage = $event,
                            onInput: onDiscountPercentageChange,
                            type: "number",
                            step: "0.01",
                            min: "0",
                            max: "100",
                            placeholder: "0",
                            class: "w-20 p-1 text-sm rounded border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 text-right"
                          }, null, 40, ["onUpdate:modelValue"]), [
                            [
                              vModelText,
                              unref(form).discount_percentage,
                              void 0,
                              { number: true }
                            ]
                          ]),
                          createVNode("span", { class: "text-xs text-gray-500" }, "%"),
                          createVNode("span", { class: "font-semibold text-red-600 dark:text-red-400 min-w-[80px] text-right" }, toDisplayString(unref(form).discount_percentage > 0 ? "-" + formatCurrency(totalAmount.value * unref(form).discount_percentage / 100) : "0.00"), 1)
                        ])
                      ]),
                      createVNode("div", { class: "grid grid-cols-2 gap-4 items-center" }, [
                        createVNode(_sfc_main$3, { value: "Discount (Tk.)" }),
                        createVNode("div", { class: "flex items-center justify-end space-x-2" }, [
                          withDirectives(createVNode("input", {
                            "onUpdate:modelValue": ($event) => unref(form).discount_amount = $event,
                            onInput: onDiscountAmountChange,
                            type: "number",
                            step: "0.01",
                            min: "0",
                            placeholder: "0",
                            class: "w-24 p-1 text-sm rounded border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 text-right"
                          }, null, 40, ["onUpdate:modelValue"]), [
                            [
                              vModelText,
                              unref(form).discount_amount,
                              void 0,
                              { number: true }
                            ]
                          ]),
                          createVNode("span", { class: "text-xs text-gray-500" }, "Tk"),
                          createVNode("span", { class: "font-semibold text-red-600 dark:text-red-400 min-w-[80px] text-right" }, toDisplayString(unref(form).discount_amount > 0 ? "-" + formatCurrency(unref(form).discount_amount) : "0.00"), 1)
                        ])
                      ]),
                      createVNode("div", { class: "grid grid-cols-2 gap-4 items-center bg-red-50 dark:bg-red-900/20 p-2 rounded" }, [
                        createVNode(_sfc_main$3, {
                          value: "Total Discount",
                          class: "font-semibold"
                        }),
                        createVNode("div", { class: "text-right font-bold text-red-600 dark:text-red-400" }, " -" + toDisplayString(formatCurrency(discountAmount.value)), 1)
                      ]),
                      createVNode("div", { class: "grid grid-cols-2 gap-4 items-center border-t pt-3 dark:border-slate-600" }, [
                        createVNode(_sfc_main$3, {
                          value: "Net Amount (Tk.)",
                          class: "text-lg font-bold"
                        }),
                        createVNode("div", { class: "text-right font-bold text-xl text-green-600 dark:text-green-400" }, toDisplayString(formatCurrency(netAmount.value)), 1)
                      ]),
                      createVNode("div", { class: "grid grid-cols-2 gap-4 mt-4" }, [
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "payment_mode",
                            value: "Payment Mode"
                          }),
                          withDirectives(createVNode("select", {
                            id: "payment_mode",
                            "onUpdate:modelValue": ($event) => unref(form).payment_mode = $event,
                            class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                          }, [
                            createVNode("option", { value: "Cash" }, "Cash"),
                            createVNode("option", { value: "Card" }, "Card"),
                            createVNode("option", { value: "Bank Transfer" }, "Bank Transfer"),
                            createVNode("option", { value: "Mobile Banking" }, "Mobile Banking")
                          ], 8, ["onUpdate:modelValue"]), [
                            [vModelSelect, unref(form).payment_mode]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-2",
                            message: unref(form).errors.payment_mode
                          }, null, 8, ["message"])
                        ]),
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "payment_amount",
                            value: "Payment Amount (Tk.)"
                          }),
                          withDirectives(createVNode("input", {
                            id: "payment_amount",
                            "onUpdate:modelValue": ($event) => unref(form).payment_amount = $event,
                            type: "number",
                            step: "0.01",
                            min: "0",
                            class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                          }, null, 8, ["onUpdate:modelValue"]), [
                            [
                              vModelText,
                              unref(form).payment_amount,
                              void 0,
                              { number: true }
                            ]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-2",
                            message: unref(form).errors.payment_amount
                          }, null, 8, ["message"]),
                          unref(form).payment_amount < netAmount.value ? (openBlock(), createBlock("div", {
                            key: 0,
                            class: "text-xs text-orange-600 mt-1"
                          }, " Due: " + toDisplayString(formatCurrency(netAmount.value - unref(form).payment_amount)), 1)) : createCommentVNode("", true)
                        ])
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "flex items-center justify-end mt-6 pt-4 border-t dark:border-slate-600" }, [
                    createVNode(_sfc_main$4, {
                      type: "submit",
                      class: ["ms-4", { "opacity-25": unref(form).processing }],
                      disabled: unref(form).processing
                    }, {
                      default: withCtx(() => [
                        createTextVNode(toDisplayString(props.id ?? false ? "Update Bill" : "Create Bill"), 1)
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/Radiology/Form.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const Form = /* @__PURE__ */ _export_sfc(_sfc_main, [["__scopeId", "data-v-ac352850"]]);
export {
  Form as default
};
