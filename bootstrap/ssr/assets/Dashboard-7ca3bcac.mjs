import { resolveComponent, mergeProps, useSSRContext, withCtx, createVNode, openBlock, createBlock, Fragment, renderList, toDisplayString } from "vue";
import { ssrRenderComponent, ssrRenderList, ssrRenderAttr, ssrInterpolate } from "vue/server-renderer";
import { _ as _sfc_main$3 } from "./BackendLayout-d755bdca.mjs";
import { Bar, Pie } from "vue-chartjs";
import { Chart, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement } from "chart.js";
import { _ as _export_sfc } from "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "@inertiajs/vue3";
import "mitt";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
Chart.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale);
const _sfc_main$2 = {
  name: "DynamicBarChart",
  components: { Bar },
  props: {
    dashboardData: {
      type: Object,
      required: true
    }
  },
  computed: {
    chartData() {
      return {
        labels: [
          "OPD Income",
          "IPD Income",
          "Pharmacy Income",
          "Pathology Income",
          "Radiology Income",
          "Pending Income"
        ],
        datasets: [{
          label: "Income (Tk.)",
          backgroundColor: [
            "#10B981",
            // Green
            "#3B82F6",
            // Blue
            "#8B5CF6",
            // Purple
            "#F59E0B",
            // Yellow
            "#EF4444",
            // Red
            "#6B7280"
            // Gray
          ],
          borderColor: [
            "#059669",
            "#2563EB",
            "#7C3AED",
            "#D97706",
            "#DC2626",
            "#4B5563"
          ],
          borderWidth: 1,
          data: [
            this.dashboardData.opdIncome || 0,
            this.dashboardData.ipdIncome || 0,
            this.dashboardData.pharmacyIncome || 0,
            this.dashboardData.pathologyIncome || 0,
            this.dashboardData.radiologyIncome || 0,
            this.dashboardData.pendingIncome || 0
          ]
        }]
      };
    },
    chartOptions() {
      return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          title: {
            display: true,
            text: "Hospital Income by Department",
            font: {
              size: 16,
              weight: "bold"
            }
          },
          legend: {
            display: false
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                return `${context.label}: Tk.${context.parsed.y.toLocaleString()}`;
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function(value) {
                return "Tk." + value.toLocaleString();
              }
            },
            title: {
              display: true,
              text: "Amount (Tk.)"
            }
          },
          x: {
            title: {
              display: true,
              text: "Department"
            }
          }
        }
      };
    }
  }
};
function _sfc_ssrRender$1(_ctx, _push, _parent, _attrs, $props, $setup, $data, $options) {
  const _component_Bar = resolveComponent("Bar");
  _push(ssrRenderComponent(_component_Bar, mergeProps({
    id: "income-bar-chart",
    options: $options.chartOptions,
    data: $options.chartData
  }, _attrs), null, _parent));
}
const _sfc_setup$2 = _sfc_main$2.setup;
_sfc_main$2.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Chart/BarChart.vue");
  return _sfc_setup$2 ? _sfc_setup$2(props, ctx) : void 0;
};
const BarChart = /* @__PURE__ */ _export_sfc(_sfc_main$2, [["ssrRender", _sfc_ssrRender$1]]);
Chart.register(ArcElement, Tooltip, Legend);
const _sfc_main$1 = {
  name: "PieChart",
  components: { Pie },
  props: {
    dashboardData: {
      type: Object,
      required: true
    }
  },
  computed: {
    chartData() {
      return {
        labels: ["OPD", "IPD", "Pharmacy", "Pathology", "Radiology", "Pending"],
        datasets: [
          {
            data: [
              this.dashboardData.opdIncome || 0,
              this.dashboardData.ipdIncome || 0,
              this.dashboardData.pharmacyIncome || 0,
              this.dashboardData.pathologyIncome || 0,
              this.dashboardData.radiologyIncome || 0,
              this.dashboardData.pendingIncome || 0
            ],
            backgroundColor: [
              "#4F46E5",
              "#10B981",
              "#3B82F6",
              "#F59E0B",
              "#EF4444",
              "#6B7280"
            ],
            borderWidth: 1
          }
        ]
      };
    },
    chartOptions() {
      return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "right"
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                const label = context.label || "";
                const value = Number(context.raw) || 0;
                const total = context.dataset.data.reduce(
                  (sum, v) => sum + Number(v || 0),
                  0
                );
                const percentage = total > 0 ? (value / total * 100).toFixed(1) : "0.0";
                return `${label}: Tk.${value.toLocaleString()} (${percentage}%)`;
              }
            }
          }
        }
      };
    }
  }
};
function _sfc_ssrRender(_ctx, _push, _parent, _attrs, $props, $setup, $data, $options) {
  const _component_Pie = resolveComponent("Pie");
  _push(ssrRenderComponent(_component_Pie, mergeProps({
    data: $options.chartData,
    options: $options.chartOptions
  }, _attrs), null, _parent));
}
const _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Chart/PieChart.vue");
  return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
const PieChart = /* @__PURE__ */ _export_sfc(_sfc_main$1, [["ssrRender", _sfc_ssrRender]]);
const _sfc_main = {
  __name: "Dashboard",
  __ssrInlineRender: true,
  props: ["dashboardData"],
  setup(__props) {
    const props = __props;
    const tkFormat = (value) => {
      const num = Number(value) || 0;
      return "Tk." + num.toFixed(2);
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$3, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<section class="w-full transition duration-700 ease-in-out"${_scopeId}><div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3 mb-4"${_scopeId}><!--[-->`);
            ssrRenderList([
              { name: "OPD Income", icon: "📄", key: "opdIncome", link: "backend.opdpatient.index" },
              { name: "IPD Income", icon: "🏥", key: "ipdIncome", link: "backend.ipdpatient.index" },
              { name: "Pharmacy Income", icon: "💊", key: "pharmacyIncome", link: "backend.report.index" },
              { name: "Pathology Income", icon: "🧪", key: "pathologyIncome", link: "backend.pathology.index" },
              { name: "Radiology Income", icon: "📷", key: "radiologyIncome", link: "backend.radiology.index" },
              { name: "Blood Bank Income", icon: "💉", key: "bloodBankIncome", link: "backend.bloodbank.index" },
              { name: "Expenses", icon: "💰", key: "expenses", link: "backend.expense.index" },
              { name: "Pending Income", icon: "⏳", key: "pendingIncome", link: "backend.pending.list" },
              { name: "Total Net Income", icon: "💵", key: "netIncome", link: "backend.report.index" },
              { name: "Total Discount", icon: "🏷️", key: "totalDiscountAmount", link: "backend.report.index" }
            ], (card) => {
              _push2(`<a${ssrRenderAttr("href", _ctx.route(card.link))} target="_blank" class="bg-white rounded-lg shadow-md hover:shadow-lg transition-all p-4 flex items-center hover:-translate-y-1"${_scopeId}><div class="w-12 h-12 bg-green-500 text-white text-xl rounded-lg flex items-center justify-center mr-3"${_scopeId}>${ssrInterpolate(card.icon)}</div><div${_scopeId}><p class="text-sm font-medium text-gray-600"${_scopeId}>${ssrInterpolate(card.name)}</p><p class="text-lg font-bold text-gray-900"${_scopeId}>${ssrInterpolate(tkFormat(props.dashboardData[card.key]))}</p></div></a>`);
            });
            _push2(`<!--]--></div></section><section class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4"${_scopeId}><div class="bg-white rounded-lg shadow"${_scopeId}><div class="px-6 py-4 font-semibold border-b"${_scopeId}>Income by Department (Bar Chart)</div><div class="p-4 h-80"${_scopeId}>`);
            _push2(ssrRenderComponent(BarChart, {
              dashboardData: props.dashboardData
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="bg-white rounded-lg shadow"${_scopeId}><div class="px-6 py-4 font-semibold border-b"${_scopeId}>Income Distribution (Pie Chart)</div><div class="p-4 h-80"${_scopeId}>`);
            _push2(ssrRenderComponent(PieChart, {
              dashboardData: props.dashboardData
            }, null, _parent2, _scopeId));
            _push2(`</div></div></section><div class="text-center text-gray-500 text-sm py-6"${_scopeId}> © ${ssrInterpolate((/* @__PURE__ */ new Date()).getFullYear())} — Developed by ToaMed. All rights reserved. </div>`);
          } else {
            return [
              createVNode("section", { class: "w-full transition duration-700 ease-in-out" }, [
                createVNode("div", { class: "grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3 mb-4" }, [
                  (openBlock(), createBlock(Fragment, null, renderList([
                    { name: "OPD Income", icon: "📄", key: "opdIncome", link: "backend.opdpatient.index" },
                    { name: "IPD Income", icon: "🏥", key: "ipdIncome", link: "backend.ipdpatient.index" },
                    { name: "Pharmacy Income", icon: "💊", key: "pharmacyIncome", link: "backend.report.index" },
                    { name: "Pathology Income", icon: "🧪", key: "pathologyIncome", link: "backend.pathology.index" },
                    { name: "Radiology Income", icon: "📷", key: "radiologyIncome", link: "backend.radiology.index" },
                    { name: "Blood Bank Income", icon: "💉", key: "bloodBankIncome", link: "backend.bloodbank.index" },
                    { name: "Expenses", icon: "💰", key: "expenses", link: "backend.expense.index" },
                    { name: "Pending Income", icon: "⏳", key: "pendingIncome", link: "backend.pending.list" },
                    { name: "Total Net Income", icon: "💵", key: "netIncome", link: "backend.report.index" },
                    { name: "Total Discount", icon: "🏷️", key: "totalDiscountAmount", link: "backend.report.index" }
                  ], (card) => {
                    return createVNode("a", {
                      key: card.key,
                      href: _ctx.route(card.link),
                      target: "_blank",
                      class: "bg-white rounded-lg shadow-md hover:shadow-lg transition-all p-4 flex items-center hover:-translate-y-1"
                    }, [
                      createVNode("div", { class: "w-12 h-12 bg-green-500 text-white text-xl rounded-lg flex items-center justify-center mr-3" }, toDisplayString(card.icon), 1),
                      createVNode("div", null, [
                        createVNode("p", { class: "text-sm font-medium text-gray-600" }, toDisplayString(card.name), 1),
                        createVNode("p", { class: "text-lg font-bold text-gray-900" }, toDisplayString(tkFormat(props.dashboardData[card.key])), 1)
                      ])
                    ], 8, ["href"]);
                  }), 64))
                ])
              ]),
              createVNode("section", { class: "grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4" }, [
                createVNode("div", { class: "bg-white rounded-lg shadow" }, [
                  createVNode("div", { class: "px-6 py-4 font-semibold border-b" }, "Income by Department (Bar Chart)"),
                  createVNode("div", { class: "p-4 h-80" }, [
                    createVNode(BarChart, {
                      dashboardData: props.dashboardData
                    }, null, 8, ["dashboardData"])
                  ])
                ]),
                createVNode("div", { class: "bg-white rounded-lg shadow" }, [
                  createVNode("div", { class: "px-6 py-4 font-semibold border-b" }, "Income Distribution (Pie Chart)"),
                  createVNode("div", { class: "p-4 h-80" }, [
                    createVNode(PieChart, {
                      dashboardData: props.dashboardData
                    }, null, 8, ["dashboardData"])
                  ])
                ])
              ]),
              createVNode("div", { class: "text-center text-gray-500 text-sm py-6" }, " © " + toDisplayString((/* @__PURE__ */ new Date()).getFullYear()) + " — Developed by ToaMed. All rights reserved. ", 1)
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/Dashboard.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
