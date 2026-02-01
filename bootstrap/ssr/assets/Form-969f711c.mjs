import { ref, computed, watch, onMounted, withCtx, unref, createTextVNode, toDisplayString, createVNode, openBlock, createBlock, withModifiers, createCommentVNode, withDirectives, vModelText, vModelSelect, Fragment, renderList, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrRenderAttr, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderList } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import { useForm, router } from "@inertiajs/vue3";
import { _ as _sfc_main$3 } from "./InputError-6aeb8d97.mjs";
import { _ as _sfc_main$2 } from "./InputLabel-70ca52d1.mjs";
import { _ as _sfc_main$4 } from "./PrimaryButton-b82fb16e.mjs";
import "./AlertMessage-0f422981.mjs";
import { a as displayWarning, d as displayResponse } from "./responseMessage-d505224b.mjs";
import Multiselect from "vue-multiselect";
/* empty css                           */import axios from "axios";
import "mitt";
import "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
import "toastr";
import "sweetalert2";
const _sfc_main = {
  __name: "Form",
  __ssrInlineRender: true,
  props: ["referral", "id", "billings", "referrers"],
  setup(__props) {
    var _a, _b, _c, _d, _e, _f;
    const props = __props;
    const form = useForm({
      billing_id: ((_a = props.referral) == null ? void 0 : _a.billing_id) ?? "",
      payee_id: ((_b = props.referral) == null ? void 0 : _b.payee_id) ?? "",
      date: ((_c = props.referral) == null ? void 0 : _c.date) ?? (/* @__PURE__ */ new Date()).toISOString().split("T")[0],
      status: ((_d = props.referral) == null ? void 0 : _d.status) ?? "Active",
      remarks: ((_e = props.referral) == null ? void 0 : _e.remarks) ?? "",
      _method: ((_f = props.referral) == null ? void 0 : _f.id) ? "put" : "post"
    });
    const commissionPreview = ref({
      totalBillAmount: 0,
      totalCommission: 0,
      categoryBreakdown: {},
      loading: false
    });
    const selectedBilling = computed(() => {
      if (!form.billing_id || !props.billings)
        return null;
      return props.billings.find((billing) => billing.id === form.billing_id);
    });
    const selectedPayee = computed(() => {
      if (!form.payee_id || !props.referrers)
        return null;
      return props.referrers.find((payee) => payee.id === form.payee_id);
    });
    watch([() => form.billing_id, () => form.payee_id], async () => {
      if (form.billing_id && form.payee_id) {
        await calculateCommissionPreview();
      } else {
        resetCommissionPreview();
      }
    });
    const calculateCommissionPreview = async () => {
      var _a2, _b2, _c2, _d2, _e2;
      if (!form.billing_id || !form.payee_id)
        return;
      commissionPreview.value.loading = true;
      try {
        console.log("Calculating commission for:", {
          billing_id: form.billing_id,
          payee_id: form.payee_id
        });
        const billingId = typeof form.billing_id === "object" ? form.billing_id.id : form.billing_id;
        const payeeId = typeof form.payee_id === "object" ? form.payee_id.id : form.payee_id;
        const response = await axios.post(route("backend.referral.commission.preview"), {
          billing_id: billingId,
          payee_id: payeeId
        }, {
          headers: {
            "X-CSRF-TOKEN": ((_a2 = document.querySelector('meta[name="csrf-token"]')) == null ? void 0 : _a2.getAttribute("content")) || "",
            "Accept": "application/json",
            "Content-Type": "application/json"
          }
        });
        console.log("Commission data received:", response.data);
        commissionPreview.value = {
          totalBillAmount: response.data.total_bill_amount || 0,
          totalCommission: response.data.total_commission || 0,
          categoryBreakdown: response.data.category_breakdown || {},
          loading: false
        };
      } catch (error) {
        console.error("Error calculating commission preview:", error);
        resetCommissionPreview();
        if ((_c2 = (_b2 = error.response) == null ? void 0 : _b2.data) == null ? void 0 : _c2.message) {
          displayWarning({ message: error.response.data.message });
        } else if ((_e2 = (_d2 = error.response) == null ? void 0 : _d2.data) == null ? void 0 : _e2.error) {
          displayWarning({ message: error.response.data.error });
        } else {
          displayWarning({ message: "Failed to calculate commission preview" });
        }
      }
    };
    const resetCommissionPreview = () => {
      commissionPreview.value = {
        totalBillAmount: 0,
        totalCommission: 0,
        categoryBreakdown: {},
        loading: false
      };
    };
    const submit = () => {
      if (!form.billing_id) {
        alert("Please select a bill number");
        return;
      }
      if (!form.payee_id) {
        alert("Please select a payee");
        return;
      }
      const routeName = props.id ? route("backend.referral.update", props.id) : route("backend.referral.store");
      form.transform((data) => ({
        ...data,
        billing_id: typeof data.billing_id === "object" ? data.billing_id.id : data.billing_id,
        payee_id: typeof data.payee_id === "object" ? data.payee_id.id : data.payee_id,
        remember: "",
        isDirty: false
      })).post(routeName, {
        onSuccess: (response) => {
          if (!props.id) {
            form.reset();
            form.date = (/* @__PURE__ */ new Date()).toISOString().split("T")[0];
            form.status = "Active";
            resetCommissionPreview();
          }
          displayResponse(response);
        },
        onError: (errorObject) => {
          console.log("Submission error:", errorObject);
          displayWarning(errorObject);
        }
      });
    };
    onMounted(() => {
      if (props.id && form.billing_id && form.payee_id) {
        calculateCommissionPreview();
      }
    });
    const goToRefferalList = () => {
      router.get(route("backend.referral.index"));
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          var _a2, _b2;
          if (_push2) {
            _push2(`<div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md"${_scopeId}><div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md dark:bg-gray-800 dark:text-gray-200"${_scopeId}><div${_scopeId}><h1 class="p-4 text-xl font-bold dark:text-white"${_scopeId}>${ssrInterpolate(_ctx.$page.props.pageTitle)}</h1></div><div class="p-2 py-2 flex items-center space-x-2"${_scopeId}><div class="flex items-center space-x-3"${_scopeId}><button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2"${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"${_scopeId}></path></svg> Refferal List </button></div></div></div><form class="p-6"${_scopeId}><div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"${_scopeId}><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "billing_id",
              value: "Bill Number"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(unref(Multiselect), {
              modelValue: unref(form).billing_id,
              "onUpdate:modelValue": ($event) => unref(form).billing_id = $event,
              options: __props.billings,
              "track-by": "id",
              label: "label",
              placeholder: "Search and select a bill number",
              class: "w-full text-sm rounded-md border border-slate-300",
              searchable: true,
              "close-on-select": true,
              "show-labels": false
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.billing_id
            }, null, _parent2, _scopeId));
            if (selectedBilling.value) {
              _push2(`<div class="mt-2 p-2 bg-blue-50 rounded text-sm"${_scopeId}><p${_scopeId}><strong${_scopeId}>Invoice:</strong> ${ssrInterpolate(selectedBilling.value.invoice_number)}</p><p${_scopeId}><strong${_scopeId}>Patient:</strong> ${ssrInterpolate(selectedBilling.value.patient_mobile)}</p><p${_scopeId}><strong${_scopeId}>Amount:</strong> ৳${ssrInterpolate((_a2 = selectedBilling.value.amount) == null ? void 0 : _a2.toFixed(2))}</p></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "payee_id",
              value: "Referrer/Payee"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(unref(Multiselect), {
              modelValue: unref(form).payee_id,
              "onUpdate:modelValue": ($event) => unref(form).payee_id = $event,
              options: __props.referrers,
              "track-by": "id",
              label: "label",
              placeholder: "Search and select a referrer",
              class: "w-full text-sm rounded-md border border-slate-300",
              searchable: true,
              "close-on-select": true,
              "show-labels": false
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.payee_id
            }, null, _parent2, _scopeId));
            if (selectedPayee.value) {
              _push2(`<div class="mt-2 p-2 bg-green-50 rounded text-sm"${_scopeId}><p${_scopeId}><strong${_scopeId}>Name:</strong> ${ssrInterpolate(selectedPayee.value.name)}</p><p${_scopeId}><strong${_scopeId}>Phone:</strong> ${ssrInterpolate(selectedPayee.value.phone)}</p><div class="grid grid-cols-2 gap-1 mt-1 text-xs"${_scopeId}><span${_scopeId}>Standard: ${ssrInterpolate(selectedPayee.value.standard_commission)}%</span><span${_scopeId}>Pathology: ${ssrInterpolate(selectedPayee.value.pathology_commission)}%</span><span${_scopeId}>Radiology: ${ssrInterpolate(selectedPayee.value.radiology_commission)}%</span><span${_scopeId}>Pharmacy: ${ssrInterpolate(selectedPayee.value.pharmacy_commission)}%</span></div></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "date",
              value: "Date"
            }, null, _parent2, _scopeId));
            _push2(`<input id="date" class="block w-full p-2 mt-1 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).date)} type="date" required${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.date
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "status",
              value: "Status"
            }, null, _parent2, _scopeId));
            _push2(`<select id="status" class="block w-full p-2 mt-1 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" required${_scopeId}><option value="Active"${ssrIncludeBooleanAttr(Array.isArray(unref(form).status) ? ssrLooseContain(unref(form).status, "Active") : ssrLooseEqual(unref(form).status, "Active")) ? " selected" : ""}${_scopeId}>Active</option><option value="Inactive"${ssrIncludeBooleanAttr(Array.isArray(unref(form).status) ? ssrLooseContain(unref(form).status, "Inactive") : ssrLooseEqual(unref(form).status, "Inactive")) ? " selected" : ""}${_scopeId}>Inactive</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.status
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-2"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "remarks",
              value: "Remarks (Optional)"
            }, null, _parent2, _scopeId));
            _push2(`<textarea id="remarks" class="block w-full p-2 mt-1 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" rows="3" placeholder="Enter any additional remarks..."${_scopeId}>${ssrInterpolate(unref(form).remarks)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.remarks
            }, null, _parent2, _scopeId));
            _push2(`</div></div>`);
            if (unref(form).billing_id && unref(form).payee_id) {
              _push2(`<div class="mt-8 p-4 bg-gray-50 rounded-lg dark:bg-slate-800"${_scopeId}><h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200"${_scopeId}>Commission Preview</h3>`);
              if (commissionPreview.value.loading) {
                _push2(`<div class="text-center py-4"${_scopeId}><div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"${_scopeId}></div><p class="mt-2 text-gray-600"${_scopeId}>Calculating commission...</p></div>`);
              } else {
                _push2(`<div${_scopeId}><div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6"${_scopeId}><div class="text-center p-4 bg-white rounded-lg shadow dark:bg-slate-700"${_scopeId}><p class="text-sm text-gray-600 dark:text-gray-400"${_scopeId}>Total Bill Amount</p><p class="text-xl font-bold text-blue-600"${_scopeId}>৳${ssrInterpolate(commissionPreview.value.totalBillAmount.toFixed(2))}</p></div><div class="text-center p-4 bg-white rounded-lg shadow dark:bg-slate-700"${_scopeId}><p class="text-sm text-gray-600 dark:text-gray-400"${_scopeId}>Total Commission</p><p class="text-xl font-bold text-green-600"${_scopeId}>৳${ssrInterpolate(commissionPreview.value.totalCommission.toFixed(2))}</p></div><div class="text-center p-4 bg-white rounded-lg shadow dark:bg-slate-700"${_scopeId}><p class="text-sm text-gray-600 dark:text-gray-400"${_scopeId}>Commission Rate</p><p class="text-xl font-bold text-purple-600"${_scopeId}>${ssrInterpolate(commissionPreview.value.totalBillAmount > 0 ? (commissionPreview.value.totalCommission / commissionPreview.value.totalBillAmount * 100).toFixed(2) : 0)}% </p></div></div>`);
                if (Object.keys(commissionPreview.value.categoryBreakdown).length > 0) {
                  _push2(`<div class="bg-white rounded-lg shadow p-4 dark:bg-slate-700"${_scopeId}><h4 class="font-semibold mb-3 text-gray-800 dark:text-gray-200"${_scopeId}>Category-wise Commission Breakdown</h4><div class="overflow-x-auto"${_scopeId}><table class="w-full text-sm"${_scopeId}><thead${_scopeId}><tr class="border-b dark:border-slate-600"${_scopeId}><th class="text-left py-2"${_scopeId}>Category</th><th class="text-right py-2"${_scopeId}>Amount (৳)</th><th class="text-right py-2"${_scopeId}>Rate (%)</th><th class="text-right py-2"${_scopeId}>Commission (৳)</th></tr></thead><tbody${_scopeId}><!--[-->`);
                  ssrRenderList(commissionPreview.value.categoryBreakdown, (breakdown, category) => {
                    _push2(`<tr class="border-b dark:border-slate-600"${_scopeId}><td class="py-2 capitalize"${_scopeId}>${ssrInterpolate(category)}</td><td class="text-right py-2"${_scopeId}>${ssrInterpolate(breakdown.amount.toFixed(2))}</td><td class="text-right py-2"${_scopeId}>${ssrInterpolate(breakdown.commission_rate.toFixed(2))}</td><td class="text-right py-2 font-semibold text-green-600"${_scopeId}>${ssrInterpolate(breakdown.commission_amount.toFixed(2))}</td></tr>`);
                  });
                  _push2(`<!--]--></tbody></table></div></div>`);
                } else {
                  _push2(`<!---->`);
                }
                _push2(`</div>`);
              }
              _push2(`</div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<div class="flex items-center justify-end mt-6"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              type: "submit",
              class: ["px-6 py-2", { "opacity-25": unref(form).processing }],
              disabled: unref(form).processing
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`${ssrInterpolate(props.id ?? false ? "Update" : "Create")} Referral `);
                } else {
                  return [
                    createTextVNode(toDisplayString(props.id ?? false ? "Update" : "Create") + " Referral ", 1)
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</div></form></div>`);
          } else {
            return [
              createVNode("div", { class: "w-full transition duration-1000 ease-in-out transform bg-white rounded-md" }, [
                createVNode("div", { class: "flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md dark:bg-gray-800 dark:text-gray-200" }, [
                  createVNode("div", null, [
                    createVNode("h1", { class: "p-4 text-xl font-bold dark:text-white" }, toDisplayString(_ctx.$page.props.pageTitle), 1)
                  ]),
                  createVNode("div", { class: "p-2 py-2 flex items-center space-x-2" }, [
                    createVNode("div", { class: "flex items-center space-x-3" }, [
                      createVNode("button", {
                        onClick: goToRefferalList,
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
                        createTextVNode(" Refferal List ")
                      ])
                    ])
                  ])
                ]),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"]),
                  class: "p-6"
                }, [
                  createVNode("div", { class: "grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3" }, [
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "billing_id",
                        value: "Bill Number"
                      }),
                      createVNode(unref(Multiselect), {
                        modelValue: unref(form).billing_id,
                        "onUpdate:modelValue": ($event) => unref(form).billing_id = $event,
                        options: __props.billings,
                        "track-by": "id",
                        label: "label",
                        placeholder: "Search and select a bill number",
                        class: "w-full text-sm rounded-md border border-slate-300",
                        searchable: true,
                        "close-on-select": true,
                        "show-labels": false
                      }, null, 8, ["modelValue", "onUpdate:modelValue", "options"]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.billing_id
                      }, null, 8, ["message"]),
                      selectedBilling.value ? (openBlock(), createBlock("div", {
                        key: 0,
                        class: "mt-2 p-2 bg-blue-50 rounded text-sm"
                      }, [
                        createVNode("p", null, [
                          createVNode("strong", null, "Invoice:"),
                          createTextVNode(" " + toDisplayString(selectedBilling.value.invoice_number), 1)
                        ]),
                        createVNode("p", null, [
                          createVNode("strong", null, "Patient:"),
                          createTextVNode(" " + toDisplayString(selectedBilling.value.patient_mobile), 1)
                        ]),
                        createVNode("p", null, [
                          createVNode("strong", null, "Amount:"),
                          createTextVNode(" ৳" + toDisplayString((_b2 = selectedBilling.value.amount) == null ? void 0 : _b2.toFixed(2)), 1)
                        ])
                      ])) : createCommentVNode("", true)
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "payee_id",
                        value: "Referrer/Payee"
                      }),
                      createVNode(unref(Multiselect), {
                        modelValue: unref(form).payee_id,
                        "onUpdate:modelValue": ($event) => unref(form).payee_id = $event,
                        options: __props.referrers,
                        "track-by": "id",
                        label: "label",
                        placeholder: "Search and select a referrer",
                        class: "w-full text-sm rounded-md border border-slate-300",
                        searchable: true,
                        "close-on-select": true,
                        "show-labels": false
                      }, null, 8, ["modelValue", "onUpdate:modelValue", "options"]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.payee_id
                      }, null, 8, ["message"]),
                      selectedPayee.value ? (openBlock(), createBlock("div", {
                        key: 0,
                        class: "mt-2 p-2 bg-green-50 rounded text-sm"
                      }, [
                        createVNode("p", null, [
                          createVNode("strong", null, "Name:"),
                          createTextVNode(" " + toDisplayString(selectedPayee.value.name), 1)
                        ]),
                        createVNode("p", null, [
                          createVNode("strong", null, "Phone:"),
                          createTextVNode(" " + toDisplayString(selectedPayee.value.phone), 1)
                        ]),
                        createVNode("div", { class: "grid grid-cols-2 gap-1 mt-1 text-xs" }, [
                          createVNode("span", null, "Standard: " + toDisplayString(selectedPayee.value.standard_commission) + "%", 1),
                          createVNode("span", null, "Pathology: " + toDisplayString(selectedPayee.value.pathology_commission) + "%", 1),
                          createVNode("span", null, "Radiology: " + toDisplayString(selectedPayee.value.radiology_commission) + "%", 1),
                          createVNode("span", null, "Pharmacy: " + toDisplayString(selectedPayee.value.pharmacy_commission) + "%", 1)
                        ])
                      ])) : createCommentVNode("", true)
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "date",
                        value: "Date"
                      }),
                      withDirectives(createVNode("input", {
                        id: "date",
                        class: "block w-full p-2 mt-1 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).date = $event,
                        type: "date",
                        required: ""
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).date]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.date
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "status",
                        value: "Status"
                      }),
                      withDirectives(createVNode("select", {
                        id: "status",
                        class: "block w-full p-2 mt-1 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).status = $event,
                        required: ""
                      }, [
                        createVNode("option", { value: "Active" }, "Active"),
                        createVNode("option", { value: "Inactive" }, "Inactive")
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).status]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.status
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-2" }, [
                      createVNode(_sfc_main$2, {
                        for: "remarks",
                        value: "Remarks (Optional)"
                      }),
                      withDirectives(createVNode("textarea", {
                        id: "remarks",
                        class: "block w-full p-2 mt-1 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).remarks = $event,
                        rows: "3",
                        placeholder: "Enter any additional remarks..."
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).remarks]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.remarks
                      }, null, 8, ["message"])
                    ])
                  ]),
                  unref(form).billing_id && unref(form).payee_id ? (openBlock(), createBlock("div", {
                    key: 0,
                    class: "mt-8 p-4 bg-gray-50 rounded-lg dark:bg-slate-800"
                  }, [
                    createVNode("h3", { class: "text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200" }, "Commission Preview"),
                    commissionPreview.value.loading ? (openBlock(), createBlock("div", {
                      key: 0,
                      class: "text-center py-4"
                    }, [
                      createVNode("div", { class: "inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600" }),
                      createVNode("p", { class: "mt-2 text-gray-600" }, "Calculating commission...")
                    ])) : (openBlock(), createBlock("div", { key: 1 }, [
                      createVNode("div", { class: "grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6" }, [
                        createVNode("div", { class: "text-center p-4 bg-white rounded-lg shadow dark:bg-slate-700" }, [
                          createVNode("p", { class: "text-sm text-gray-600 dark:text-gray-400" }, "Total Bill Amount"),
                          createVNode("p", { class: "text-xl font-bold text-blue-600" }, "৳" + toDisplayString(commissionPreview.value.totalBillAmount.toFixed(2)), 1)
                        ]),
                        createVNode("div", { class: "text-center p-4 bg-white rounded-lg shadow dark:bg-slate-700" }, [
                          createVNode("p", { class: "text-sm text-gray-600 dark:text-gray-400" }, "Total Commission"),
                          createVNode("p", { class: "text-xl font-bold text-green-600" }, "৳" + toDisplayString(commissionPreview.value.totalCommission.toFixed(2)), 1)
                        ]),
                        createVNode("div", { class: "text-center p-4 bg-white rounded-lg shadow dark:bg-slate-700" }, [
                          createVNode("p", { class: "text-sm text-gray-600 dark:text-gray-400" }, "Commission Rate"),
                          createVNode("p", { class: "text-xl font-bold text-purple-600" }, toDisplayString(commissionPreview.value.totalBillAmount > 0 ? (commissionPreview.value.totalCommission / commissionPreview.value.totalBillAmount * 100).toFixed(2) : 0) + "% ", 1)
                        ])
                      ]),
                      Object.keys(commissionPreview.value.categoryBreakdown).length > 0 ? (openBlock(), createBlock("div", {
                        key: 0,
                        class: "bg-white rounded-lg shadow p-4 dark:bg-slate-700"
                      }, [
                        createVNode("h4", { class: "font-semibold mb-3 text-gray-800 dark:text-gray-200" }, "Category-wise Commission Breakdown"),
                        createVNode("div", { class: "overflow-x-auto" }, [
                          createVNode("table", { class: "w-full text-sm" }, [
                            createVNode("thead", null, [
                              createVNode("tr", { class: "border-b dark:border-slate-600" }, [
                                createVNode("th", { class: "text-left py-2" }, "Category"),
                                createVNode("th", { class: "text-right py-2" }, "Amount (৳)"),
                                createVNode("th", { class: "text-right py-2" }, "Rate (%)"),
                                createVNode("th", { class: "text-right py-2" }, "Commission (৳)")
                              ])
                            ]),
                            createVNode("tbody", null, [
                              (openBlock(true), createBlock(Fragment, null, renderList(commissionPreview.value.categoryBreakdown, (breakdown, category) => {
                                return openBlock(), createBlock("tr", {
                                  key: category,
                                  class: "border-b dark:border-slate-600"
                                }, [
                                  createVNode("td", { class: "py-2 capitalize" }, toDisplayString(category), 1),
                                  createVNode("td", { class: "text-right py-2" }, toDisplayString(breakdown.amount.toFixed(2)), 1),
                                  createVNode("td", { class: "text-right py-2" }, toDisplayString(breakdown.commission_rate.toFixed(2)), 1),
                                  createVNode("td", { class: "text-right py-2 font-semibold text-green-600" }, toDisplayString(breakdown.commission_amount.toFixed(2)), 1)
                                ]);
                              }), 128))
                            ])
                          ])
                        ])
                      ])) : createCommentVNode("", true)
                    ]))
                  ])) : createCommentVNode("", true),
                  createVNode("div", { class: "flex items-center justify-end mt-6" }, [
                    createVNode(_sfc_main$4, {
                      type: "submit",
                      class: ["px-6 py-2", { "opacity-25": unref(form).processing }],
                      disabled: unref(form).processing
                    }, {
                      default: withCtx(() => [
                        createTextVNode(toDisplayString(props.id ?? false ? "Update" : "Create") + " Referral ", 1)
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/Referral/Form.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
