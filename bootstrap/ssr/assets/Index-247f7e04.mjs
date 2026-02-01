import { ref, withCtx, createVNode, toDisplayString, openBlock, createBlock, createTextVNode, withDirectives, vModelText, vModelSelect, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrRenderStyle, ssrRenderAttr, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import { _ as _sfc_main$2, a as _sfc_main$3 } from "./Pagination-b34cb0ee.mjs";
import { router } from "@inertiajs/vue3";
import "mitt";
import "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
import "./responseMessage-d505224b.mjs";
import "toastr";
import "sweetalert2";
import "jquery";
const _sfc_main = {
  __name: "Index",
  __ssrInlineRender: true,
  props: {
    filters: Object
  },
  setup(__props) {
    var _a;
    let props = __props;
    const filters = ref({
      numOfData: ((_a = props.filters) == null ? void 0 : _a.numOfData) ?? 10
    });
    const applyFilter = () => {
      router.get(route("backend.chargecategory.index"), filters.value, { preserveState: true });
    };
    const goToChargeCategoryAdd = () => {
      router.get(route("backend.chargecategory.create"));
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="w-full p-2 duration-1000 ease-in-out bg-white rounded-md dark:bg-slate-900"${_scopeId}><div class="flex mb-2 items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md dark:bg-gray-800 dark:text-gray-200 shadow-gray-800/50"${_scopeId}><div${_scopeId}><h1 class="p-4 text-xl font-bold dark:text-white"${_scopeId}>${ssrInterpolate(_ctx.$page.props.pageTitle)}</h1></div><div class="p-4 py-2 flex items-center space-x-2"${_scopeId}><div class="flex items-center space-x-3"${_scopeId}><div class="flex items-center bg-gray-50 dark:bg-gray-800 rounded-lg p-1 shadow-inner"${_scopeId}><button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out" style="${ssrRenderStyle({ "background": "linear-gradient(to right, #3b82f6, #60a5fa)" })}" onmouseover="this.style.background=&#39;linear-gradient(to right, #2563eb, #3b82f6)&#39;;" onmouseout="this.style.background=&#39;linear-gradient(to right, #3b82f6, #60a5fa)&#39;;"${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"${_scopeId}></path></svg> Charge Category Add </button></div></div></div></div><div class="flex justify-between w-full p-2 py-3 space-x-2 text-gray-700 rounded-md bg-slate-300 shadow-gray-800/50 dark:bg-gray-700 dark:text-gray-200"${_scopeId}><div class="grid w-full grid-cols-1 gap-2 md:grid-cols-5"${_scopeId}><div class="flex space-x-2"${_scopeId}><div class="w-full"${_scopeId}><input id="name"${ssrRenderAttr("value", filters.value.name)} class="block w-full p-2 text-sm rounded-md border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" type="text" placeholder="Name"${_scopeId}></div><div class="block min-w-24 md:hidden"${_scopeId}><select class="w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value="10"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "10") : ssrLooseEqual(filters.value.numOfData, "10")) ? " selected" : ""}${_scopeId}>Show 10</option><option value="20"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "20") : ssrLooseEqual(filters.value.numOfData, "20")) ? " selected" : ""}${_scopeId}>Show 20</option><option value="30"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "30") : ssrLooseEqual(filters.value.numOfData, "30")) ? " selected" : ""}${_scopeId}>Show 30</option><option value="40"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "40") : ssrLooseEqual(filters.value.numOfData, "40")) ? " selected" : ""}${_scopeId}>Show 40</option><option value="100"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "100") : ssrLooseEqual(filters.value.numOfData, "100")) ? " selected" : ""}${_scopeId}>Show 100</option><option value="150"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "150") : ssrLooseEqual(filters.value.numOfData, "150")) ? " selected" : ""}${_scopeId}>Show 150</option><option value="500"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "500") : ssrLooseEqual(filters.value.numOfData, "500")) ? " selected" : ""}${_scopeId}>Show 500</option></select></div></div></div><div class="hidden min-w-24 md:block"${_scopeId}><select class="w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value="10"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "10") : ssrLooseEqual(filters.value.numOfData, "10")) ? " selected" : ""}${_scopeId}>show 10</option><option value="20"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "20") : ssrLooseEqual(filters.value.numOfData, "20")) ? " selected" : ""}${_scopeId}>show 20</option><option value="30"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "30") : ssrLooseEqual(filters.value.numOfData, "30")) ? " selected" : ""}${_scopeId}>show 30</option><option value="40"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "40") : ssrLooseEqual(filters.value.numOfData, "40")) ? " selected" : ""}${_scopeId}>show 40</option><option value="100"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "100") : ssrLooseEqual(filters.value.numOfData, "100")) ? " selected" : ""}${_scopeId}>show 100</option><option value="150"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "150") : ssrLooseEqual(filters.value.numOfData, "150")) ? " selected" : ""}${_scopeId}>show 150</option><option value="500"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "500") : ssrLooseEqual(filters.value.numOfData, "500")) ? " selected" : ""}${_scopeId}>show 500</option></select></div></div><div class="w-full my-3 overflow-x-auto"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, null, null, _parent2, _scopeId));
            _push2(`</div>`);
            _push2(ssrRenderComponent(_sfc_main$3, null, null, _parent2, _scopeId));
            _push2(`</div>`);
          } else {
            return [
              createVNode("div", { class: "w-full p-2 duration-1000 ease-in-out bg-white rounded-md dark:bg-slate-900" }, [
                createVNode("div", { class: "flex mb-2 items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md dark:bg-gray-800 dark:text-gray-200 shadow-gray-800/50" }, [
                  createVNode("div", null, [
                    createVNode("h1", { class: "p-4 text-xl font-bold dark:text-white" }, toDisplayString(_ctx.$page.props.pageTitle), 1)
                  ]),
                  createVNode("div", { class: "p-4 py-2 flex items-center space-x-2" }, [
                    createVNode("div", { class: "flex items-center space-x-3" }, [
                      createVNode("div", { class: "flex items-center bg-gray-50 dark:bg-gray-800 rounded-lg p-1 shadow-inner" }, [
                        createVNode("button", {
                          onClick: goToChargeCategoryAdd,
                          class: "inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out",
                          style: { "background": "linear-gradient(to right, #3b82f6, #60a5fa)" },
                          onmouseover: "this.style.background='linear-gradient(to right, #2563eb, #3b82f6)';",
                          onmouseout: "this.style.background='linear-gradient(to right, #3b82f6, #60a5fa)';"
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
                              d: "M12 4.5v15m7.5-7.5h-15"
                            })
                          ])),
                          createTextVNode(" Charge Category Add ")
                        ])
                      ])
                    ])
                  ])
                ]),
                createVNode("div", { class: "flex justify-between w-full p-2 py-3 space-x-2 text-gray-700 rounded-md bg-slate-300 shadow-gray-800/50 dark:bg-gray-700 dark:text-gray-200" }, [
                  createVNode("div", { class: "grid w-full grid-cols-1 gap-2 md:grid-cols-5" }, [
                    createVNode("div", { class: "flex space-x-2" }, [
                      createVNode("div", { class: "w-full" }, [
                        withDirectives(createVNode("input", {
                          id: "name",
                          "onUpdate:modelValue": ($event) => filters.value.name = $event,
                          class: "block w-full p-2 text-sm rounded-md border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                          type: "text",
                          placeholder: "Name",
                          onInput: applyFilter
                        }, null, 40, ["onUpdate:modelValue"]), [
                          [vModelText, filters.value.name]
                        ])
                      ]),
                      createVNode("div", { class: "block min-w-24 md:hidden" }, [
                        withDirectives(createVNode("select", {
                          "onUpdate:modelValue": ($event) => filters.value.numOfData = $event,
                          onChange: applyFilter,
                          class: "w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, [
                          createVNode("option", { value: "10" }, "Show 10"),
                          createVNode("option", { value: "20" }, "Show 20"),
                          createVNode("option", { value: "30" }, "Show 30"),
                          createVNode("option", { value: "40" }, "Show 40"),
                          createVNode("option", { value: "100" }, "Show 100"),
                          createVNode("option", { value: "150" }, "Show 150"),
                          createVNode("option", { value: "500" }, "Show 500")
                        ], 40, ["onUpdate:modelValue"]), [
                          [vModelSelect, filters.value.numOfData]
                        ])
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "hidden min-w-24 md:block" }, [
                    withDirectives(createVNode("select", {
                      "onUpdate:modelValue": ($event) => filters.value.numOfData = $event,
                      onChange: applyFilter,
                      class: "w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                    }, [
                      createVNode("option", { value: "10" }, "show 10"),
                      createVNode("option", { value: "20" }, "show 20"),
                      createVNode("option", { value: "30" }, "show 30"),
                      createVNode("option", { value: "40" }, "show 40"),
                      createVNode("option", { value: "100" }, "show 100"),
                      createVNode("option", { value: "150" }, "show 150"),
                      createVNode("option", { value: "500" }, "show 500")
                    ], 40, ["onUpdate:modelValue"]), [
                      [vModelSelect, filters.value.numOfData]
                    ])
                  ])
                ]),
                createVNode("div", { class: "w-full my-3 overflow-x-auto" }, [
                  createVNode(_sfc_main$2)
                ]),
                createVNode(_sfc_main$3)
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/ChargeCategory/Index.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
