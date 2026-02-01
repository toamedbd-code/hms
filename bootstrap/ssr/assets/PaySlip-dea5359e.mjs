import { ref, withCtx, createVNode, openBlock, createBlock, createTextVNode, toDisplayString, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate } from "vue/server-renderer";
import { usePage } from "@inertiajs/vue3";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import "mitt";
import "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
const _sfc_main = {
  __name: "PaySlip",
  __ssrInlineRender: true,
  props: {
    staffDetails: Object,
    grossSalary: Number,
    netSalary: Number,
    attendanceInfo: Object
  },
  setup(__props) {
    var _a;
    usePage();
    const props = __props;
    usePage().props._token;
    const formattedGrossSalary = Number(props.grossSalary).toFixed(2);
    const formattedNetSalary = Number(props.netSalary).toFixed(2);
    const previousMonth = (/* @__PURE__ */ new Date()).getMonth() === 0 ? 11 : (/* @__PURE__ */ new Date()).getMonth() - 1;
    const previousYear = previousMonth === 11 ? (/* @__PURE__ */ new Date()).getFullYear() - 1 : (/* @__PURE__ */ new Date()).getFullYear();
    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    const formattedPeriod = `${monthNames[previousMonth]} ${previousYear}`;
    const payslipData = ref({
      companyName: "Workzen Group",
      location: "Dhaka, Bangladesh",
      period: formattedPeriod,
      payslipNumber: "1",
      paymentDate: (/* @__PURE__ */ new Date()).toLocaleDateString(),
      staffId: props.staffDetails.id,
      designation: ((_a = props.staffDetails.designation) == null ? void 0 : _a.name) || "N/A",
      branch: props.staffDetails.branch || "N/A",
      basicSalary: props.staffDetails.salary,
      area: "Dhaka Area",
      paymentMode: "Cash",
      grossSalary: formattedGrossSalary,
      netSalary: formattedNetSalary,
      name: props.staffDetails.name
    });
    const downloadPayslip = () => {
      const params = new URLSearchParams({
        payslipData: JSON.stringify(payslipData.value)
      });
      window.open(route("backend.download.payslip") + "?" + params.toString(), "_blank");
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="max-w-lg mx-auto bg-white p-6 rounded-lg shadow-md mt-6"${_scopeId}><div class="relative text-center mb-6 border-b pb-4"${_scopeId}><a class="absolute top-0 right-0 flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 cursor-pointer"${_scopeId}><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"${_scopeId}><path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd"${_scopeId}></path></svg> Print </a><h1 class="text-2xl font-semibold"${_scopeId}>${ssrInterpolate(payslipData.value.companyName)}</h1><p class="text-gray-600"${_scopeId}>${ssrInterpolate(payslipData.value.location)}</p><p class="text-lg font-medium mt-2"${_scopeId}>Payslip for the period of ${ssrInterpolate(payslipData.value.period)}</p></div><div class="flex justify-between mb-6"${_scopeId}><div${_scopeId}><p${_scopeId}><strong${_scopeId}>Payslip #:</strong> ${ssrInterpolate(payslipData.value.payslipNumber)}</p><p${_scopeId}><strong${_scopeId}>Staff ID:</strong> ${ssrInterpolate(payslipData.value.staffId)}</p><p${_scopeId}><strong${_scopeId}>Designation:</strong> ${ssrInterpolate(payslipData.value.designation)}</p><p${_scopeId}><strong${_scopeId}>Payment In:</strong> ${ssrInterpolate(payslipData.value.paymentMode)}</p><p${_scopeId}><strong${_scopeId}>Basic Salary:</strong> ${ssrInterpolate(payslipData.value.basicSalary)}</p></div><div${_scopeId}><p${_scopeId}><strong${_scopeId}>Payment Date:</strong> ${ssrInterpolate(payslipData.value.paymentDate)}</p><p${_scopeId}><strong${_scopeId}>Name:</strong> ${ssrInterpolate(payslipData.value.name)}</p><p${_scopeId}><strong${_scopeId}>Branch:</strong> ${ssrInterpolate(payslipData.value.branch)}</p><p${_scopeId}><strong${_scopeId}>Area:</strong> ${ssrInterpolate(payslipData.value.area)}</p><p${_scopeId}><strong${_scopeId}>Gross Salary:</strong> ${ssrInterpolate(payslipData.value.grossSalary)}</p></div></div><div class="bg-gray-100 p-4 rounded-md"${_scopeId}><p class="font-bold text-lg text-center"${_scopeId}>Summary</p><div class="flex justify-between mt-3"${_scopeId}><p${_scopeId}>Basic Salary:</p><p${_scopeId}>${ssrInterpolate(payslipData.value.basicSalary)}</p></div><div class="flex justify-between"${_scopeId}><p${_scopeId}>Gross Salary:</p><p${_scopeId}>${ssrInterpolate(payslipData.value.grossSalary)}</p></div><div class="flex justify-between border-t border-gray-300 mt-3 pt-3"${_scopeId}><p class="font-bold text-xl"${_scopeId}>Net Salary:</p><p class="font-bold text-xl"${_scopeId}>${ssrInterpolate(payslipData.value.netSalary)}</p></div></div></div>`);
          } else {
            return [
              createVNode("div", { class: "max-w-lg mx-auto bg-white p-6 rounded-lg shadow-md mt-6" }, [
                createVNode("div", { class: "relative text-center mb-6 border-b pb-4" }, [
                  createVNode("a", {
                    onClick: downloadPayslip,
                    class: "absolute top-0 right-0 flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 cursor-pointer"
                  }, [
                    (openBlock(), createBlock("svg", {
                      xmlns: "http://www.w3.org/2000/svg",
                      class: "h-5 w-5",
                      viewBox: "0 0 20 20",
                      fill: "currentColor"
                    }, [
                      createVNode("path", {
                        "fill-rule": "evenodd",
                        d: "M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z",
                        "clip-rule": "evenodd"
                      })
                    ])),
                    createTextVNode(" Print ")
                  ]),
                  createVNode("h1", { class: "text-2xl font-semibold" }, toDisplayString(payslipData.value.companyName), 1),
                  createVNode("p", { class: "text-gray-600" }, toDisplayString(payslipData.value.location), 1),
                  createVNode("p", { class: "text-lg font-medium mt-2" }, "Payslip for the period of " + toDisplayString(payslipData.value.period), 1)
                ]),
                createVNode("div", { class: "flex justify-between mb-6" }, [
                  createVNode("div", null, [
                    createVNode("p", null, [
                      createVNode("strong", null, "Payslip #:"),
                      createTextVNode(" " + toDisplayString(payslipData.value.payslipNumber), 1)
                    ]),
                    createVNode("p", null, [
                      createVNode("strong", null, "Staff ID:"),
                      createTextVNode(" " + toDisplayString(payslipData.value.staffId), 1)
                    ]),
                    createVNode("p", null, [
                      createVNode("strong", null, "Designation:"),
                      createTextVNode(" " + toDisplayString(payslipData.value.designation), 1)
                    ]),
                    createVNode("p", null, [
                      createVNode("strong", null, "Payment In:"),
                      createTextVNode(" " + toDisplayString(payslipData.value.paymentMode), 1)
                    ]),
                    createVNode("p", null, [
                      createVNode("strong", null, "Basic Salary:"),
                      createTextVNode(" " + toDisplayString(payslipData.value.basicSalary), 1)
                    ])
                  ]),
                  createVNode("div", null, [
                    createVNode("p", null, [
                      createVNode("strong", null, "Payment Date:"),
                      createTextVNode(" " + toDisplayString(payslipData.value.paymentDate), 1)
                    ]),
                    createVNode("p", null, [
                      createVNode("strong", null, "Name:"),
                      createTextVNode(" " + toDisplayString(payslipData.value.name), 1)
                    ]),
                    createVNode("p", null, [
                      createVNode("strong", null, "Branch:"),
                      createTextVNode(" " + toDisplayString(payslipData.value.branch), 1)
                    ]),
                    createVNode("p", null, [
                      createVNode("strong", null, "Area:"),
                      createTextVNode(" " + toDisplayString(payslipData.value.area), 1)
                    ]),
                    createVNode("p", null, [
                      createVNode("strong", null, "Gross Salary:"),
                      createTextVNode(" " + toDisplayString(payslipData.value.grossSalary), 1)
                    ])
                  ])
                ]),
                createVNode("div", { class: "bg-gray-100 p-4 rounded-md" }, [
                  createVNode("p", { class: "font-bold text-lg text-center" }, "Summary"),
                  createVNode("div", { class: "flex justify-between mt-3" }, [
                    createVNode("p", null, "Basic Salary:"),
                    createVNode("p", null, toDisplayString(payslipData.value.basicSalary), 1)
                  ]),
                  createVNode("div", { class: "flex justify-between" }, [
                    createVNode("p", null, "Gross Salary:"),
                    createVNode("p", null, toDisplayString(payslipData.value.grossSalary), 1)
                  ]),
                  createVNode("div", { class: "flex justify-between border-t border-gray-300 mt-3 pt-3" }, [
                    createVNode("p", { class: "font-bold text-xl" }, "Net Salary:"),
                    createVNode("p", { class: "font-bold text-xl" }, toDisplayString(payslipData.value.netSalary), 1)
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/StaffAttendance/PaySlip.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
