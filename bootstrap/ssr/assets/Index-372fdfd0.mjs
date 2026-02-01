import { ref, withCtx, createVNode, toDisplayString, openBlock, createBlock, createTextVNode, withDirectives, vModelText, vModelSelect, Fragment, renderList, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrRenderStyle, ssrRenderAttr, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderList, ssrRenderClass } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import { a as _sfc_main$2 } from "./Pagination-b34cb0ee.mjs";
import { router } from "@inertiajs/vue3";
import { d as displayResponse } from "./responseMessage-d505224b.mjs";
import { _ as _export_sfc } from "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "mitt";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
import "jquery";
import "toastr";
import "sweetalert2";
const Index_vue_vue_type_style_index_0_scoped_602b1cde_lang = "";
const _sfc_main = {
  __name: "Index",
  __ssrInlineRender: true,
  props: {
    filters: Object,
    datas: Object,
    tableHeaders: Array,
    dataFields: Array
  },
  setup(__props) {
    var _a;
    let props = __props;
    const filters = ref({
      numOfData: ((_a = props.filters) == null ? void 0 : _a.numOfData) ?? 10
    });
    const applyFilter = () => {
      router.get(route("backend.chargetype.index"), filters.value, { preserveState: true });
    };
    const toggleModule = (chargeTypeId, moduleName, currentStatus) => {
      const action = currentStatus ? "remove" : "add";
      router.post(route("backend.chargetype.toggle-module"), {
        charge_type_id: chargeTypeId,
        module: moduleName,
        action
      }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (response) => {
          displayResponse(response);
        },
        onError: (errors) => {
          console.error("Error updating module:", errors);
        }
      });
    };
    const isModuleActive = (chargeType, moduleName) => {
      const modules = JSON.parse(chargeType.modules || "[]");
      return modules.includes(moduleName);
    };
    const allModules = ["Appointment", "OPD", "IPD", "Pathology", "Radiology", "Blood Bank", "Ambulance"];
    const goToChargeTypeAdd = () => {
      router.get(route("backend.chargetype.create"));
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="w-full p-2 duration-1000 ease-in-out bg-white rounded-md dark:bg-slate-900" data-v-602b1cde${_scopeId}><div class="flex mb-2 items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md dark:bg-gray-800 dark:text-gray-200 shadow-gray-800/50" data-v-602b1cde${_scopeId}><div data-v-602b1cde${_scopeId}><h1 class="p-4 text-xl font-bold dark:text-white" data-v-602b1cde${_scopeId}>${ssrInterpolate(_ctx.$page.props.pageTitle)}</h1></div><div class="p-4 py-2 flex items-center space-x-2" data-v-602b1cde${_scopeId}><div class="flex items-center space-x-3" data-v-602b1cde${_scopeId}><div class="flex items-center bg-gray-50 dark:bg-gray-800 rounded-lg p-1 shadow-inner" data-v-602b1cde${_scopeId}><button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out" style="${ssrRenderStyle({ "background": "linear-gradient(to right, #3b82f6, #60a5fa)" })}" onmouseover="this.style.background=&#39;linear-gradient(to right, #2563eb, #3b82f6)&#39;;" onmouseout="this.style.background=&#39;linear-gradient(to right, #3b82f6, #60a5fa)&#39;;" data-v-602b1cde${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" data-v-602b1cde${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" data-v-602b1cde${_scopeId}></path></svg> Charge Type Add </button></div></div></div></div><div class="flex justify-between w-full p-2 py-3 space-x-2 text-gray-700 rounded-md bg-slate-300 shadow-gray-800/50 dark:bg-gray-700 dark:text-gray-200" data-v-602b1cde${_scopeId}><div class="grid w-full grid-cols-1 gap-2 md:grid-cols-5" data-v-602b1cde${_scopeId}><div class="flex space-x-2" data-v-602b1cde${_scopeId}><div class="w-full" data-v-602b1cde${_scopeId}><input id="name"${ssrRenderAttr("value", filters.value.name)} class="block w-full p-2 text-sm rounded-md border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" type="text" placeholder="Name" data-v-602b1cde${_scopeId}></div><div class="block min-w-24 md:hidden" data-v-602b1cde${_scopeId}><select class="w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-602b1cde${_scopeId}><option value="10" data-v-602b1cde${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "10") : ssrLooseEqual(filters.value.numOfData, "10")) ? " selected" : ""}${_scopeId}>Show 10</option><option value="20" data-v-602b1cde${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "20") : ssrLooseEqual(filters.value.numOfData, "20")) ? " selected" : ""}${_scopeId}>Show 20</option><option value="30" data-v-602b1cde${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "30") : ssrLooseEqual(filters.value.numOfData, "30")) ? " selected" : ""}${_scopeId}>Show 30</option><option value="40" data-v-602b1cde${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "40") : ssrLooseEqual(filters.value.numOfData, "40")) ? " selected" : ""}${_scopeId}>Show 40</option><option value="100" data-v-602b1cde${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "100") : ssrLooseEqual(filters.value.numOfData, "100")) ? " selected" : ""}${_scopeId}>Show 100</option><option value="150" data-v-602b1cde${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "150") : ssrLooseEqual(filters.value.numOfData, "150")) ? " selected" : ""}${_scopeId}>Show 150</option><option value="500" data-v-602b1cde${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "500") : ssrLooseEqual(filters.value.numOfData, "500")) ? " selected" : ""}${_scopeId}>Show 500</option></select></div></div></div><div class="hidden min-w-24 md:block" data-v-602b1cde${_scopeId}><select class="w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-602b1cde${_scopeId}><option value="10" data-v-602b1cde${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "10") : ssrLooseEqual(filters.value.numOfData, "10")) ? " selected" : ""}${_scopeId}>show 10</option><option value="20" data-v-602b1cde${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "20") : ssrLooseEqual(filters.value.numOfData, "20")) ? " selected" : ""}${_scopeId}>show 20</option><option value="30" data-v-602b1cde${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "30") : ssrLooseEqual(filters.value.numOfData, "30")) ? " selected" : ""}${_scopeId}>show 30</option><option value="40" data-v-602b1cde${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "40") : ssrLooseEqual(filters.value.numOfData, "40")) ? " selected" : ""}${_scopeId}>show 40</option><option value="100" data-v-602b1cde${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "100") : ssrLooseEqual(filters.value.numOfData, "100")) ? " selected" : ""}${_scopeId}>show 100</option><option value="150" data-v-602b1cde${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "150") : ssrLooseEqual(filters.value.numOfData, "150")) ? " selected" : ""}${_scopeId}>show 150</option><option value="500" data-v-602b1cde${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "500") : ssrLooseEqual(filters.value.numOfData, "500")) ? " selected" : ""}${_scopeId}>show 500</option></select></div></div><div class="w-full my-3 overflow-x-auto" data-v-602b1cde${_scopeId}><table class="min-w-full bg-white border border-gray-200 dark:bg-slate-800 dark:border-slate-600" data-v-602b1cde${_scopeId}><thead class="bg-gray-50 dark:bg-slate-700" data-v-602b1cde${_scopeId}><tr data-v-602b1cde${_scopeId}><th class="px-4 py-3 text-left text-xs font-medium text-black uppercase tracking-wider dark:text-gray-300" data-v-602b1cde${_scopeId}> Sl/No </th><th class="px-4 py-3 text-left text-xs font-medium text-black uppercase tracking-wider dark:text-gray-300" data-v-602b1cde${_scopeId}> Charge Type </th><!--[-->`);
            ssrRenderList(allModules, (module) => {
              _push2(`<th class="px-4 py-3 text-center text-xs font-medium text-black uppercase tracking-wider dark:text-gray-300" data-v-602b1cde${_scopeId}>${ssrInterpolate(module)}</th>`);
            });
            _push2(`<!--]--><th class="px-4 py-3 text-center text-xs font-medium text-black uppercase tracking-wider dark:text-gray-300" data-v-602b1cde${_scopeId}> Action </th></tr></thead><tbody class="bg-white divide-y divide-gray-200 dark:bg-slate-800 dark:divide-slate-600" data-v-602b1cde${_scopeId}><!--[-->`);
            ssrRenderList(__props.datas.data, (item, index) => {
              _push2(`<tr class="hover:bg-gray-50 dark:hover:bg-slate-700" data-v-602b1cde${_scopeId}><td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100" data-v-602b1cde${_scopeId}>${ssrInterpolate(index + 1 + (__props.datas.current_page - 1) * __props.datas.per_page)}</td><td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100" data-v-602b1cde${_scopeId}>${ssrInterpolate(item.name)}</td><!--[-->`);
              ssrRenderList(allModules, (module) => {
                _push2(`<td class="px-4 py-4 whitespace-nowrap text-center" data-v-602b1cde${_scopeId}><label class="inline-flex items-center cursor-pointer" data-v-602b1cde${_scopeId}><input type="checkbox"${ssrIncludeBooleanAttr(isModuleActive(item, module)) ? " checked" : ""} class="form-checkbox h-5 w-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500 focus:ring-2 dark:bg-slate-700 dark:border-slate-500" data-v-602b1cde${_scopeId}><span class="sr-only" data-v-602b1cde${_scopeId}>Toggle ${ssrInterpolate(module)} for ${ssrInterpolate(item.name)}</span></label></td>`);
              });
              _push2(`<!--]--><td class="px-4 py-4 whitespace-nowrap text-center text-sm font-medium" data-v-602b1cde${_scopeId}><div class="flex justify-center space-x-2" data-v-602b1cde${_scopeId}><a${ssrRenderAttr("href", _ctx.route("backend.chargetype.status.change", { id: item.id, status: item.status === "Active" ? "Inactive" : "Active" }))} class="${ssrRenderClass([
                "px-3 py-1 rounded text-xs font-semibold text-white",
                item.status === "Active" ? "bg-gray-500 hover:bg-gray-600" : "bg-green-500 hover:bg-green-600"
              ])}" data-v-602b1cde${_scopeId}>${ssrInterpolate(item.status === "Active" ? "Inactive" : "Active")}</a><a${ssrRenderAttr("href", _ctx.route("backend.chargetype.edit", item.id))} class="px-3 py-1 rounded text-xs font-semibold bg-yellow-400 text-black hover:bg-yellow-500" data-v-602b1cde${_scopeId}> Edit </a><button class="px-3 py-1 rounded text-xs font-semibold bg-red-500 text-white hover:bg-red-600" data-v-602b1cde${_scopeId}> Delete </button></div></td></tr>`);
            });
            _push2(`<!--]--></tbody></table></div>`);
            _push2(ssrRenderComponent(_sfc_main$2, { data: __props.datas }, null, _parent2, _scopeId));
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
                          onClick: goToChargeTypeAdd,
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
                          createTextVNode(" Charge Type Add ")
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
                  createVNode("table", { class: "min-w-full bg-white border border-gray-200 dark:bg-slate-800 dark:border-slate-600" }, [
                    createVNode("thead", { class: "bg-gray-50 dark:bg-slate-700" }, [
                      createVNode("tr", null, [
                        createVNode("th", { class: "px-4 py-3 text-left text-xs font-medium text-black uppercase tracking-wider dark:text-gray-300" }, " Sl/No "),
                        createVNode("th", { class: "px-4 py-3 text-left text-xs font-medium text-black uppercase tracking-wider dark:text-gray-300" }, " Charge Type "),
                        (openBlock(), createBlock(Fragment, null, renderList(allModules, (module) => {
                          return createVNode("th", {
                            key: module,
                            class: "px-4 py-3 text-center text-xs font-medium text-black uppercase tracking-wider dark:text-gray-300"
                          }, toDisplayString(module), 1);
                        }), 64)),
                        createVNode("th", { class: "px-4 py-3 text-center text-xs font-medium text-black uppercase tracking-wider dark:text-gray-300" }, " Action ")
                      ])
                    ]),
                    createVNode("tbody", { class: "bg-white divide-y divide-gray-200 dark:bg-slate-800 dark:divide-slate-600" }, [
                      (openBlock(true), createBlock(Fragment, null, renderList(__props.datas.data, (item, index) => {
                        return openBlock(), createBlock("tr", {
                          key: item.id,
                          class: "hover:bg-gray-50 dark:hover:bg-slate-700"
                        }, [
                          createVNode("td", { class: "px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100" }, toDisplayString(index + 1 + (__props.datas.current_page - 1) * __props.datas.per_page), 1),
                          createVNode("td", { class: "px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100" }, toDisplayString(item.name), 1),
                          (openBlock(), createBlock(Fragment, null, renderList(allModules, (module) => {
                            return createVNode("td", {
                              key: module,
                              class: "px-4 py-4 whitespace-nowrap text-center"
                            }, [
                              createVNode("label", { class: "inline-flex items-center cursor-pointer" }, [
                                createVNode("input", {
                                  type: "checkbox",
                                  checked: isModuleActive(item, module),
                                  onChange: ($event) => toggleModule(item.id, module, isModuleActive(item, module)),
                                  class: "form-checkbox h-5 w-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500 focus:ring-2 dark:bg-slate-700 dark:border-slate-500"
                                }, null, 40, ["checked", "onChange"]),
                                createVNode("span", { class: "sr-only" }, "Toggle " + toDisplayString(module) + " for " + toDisplayString(item.name), 1)
                              ])
                            ]);
                          }), 64)),
                          createVNode("td", { class: "px-4 py-4 whitespace-nowrap text-center text-sm font-medium" }, [
                            createVNode("div", { class: "flex justify-center space-x-2" }, [
                              createVNode("a", {
                                href: _ctx.route("backend.chargetype.status.change", { id: item.id, status: item.status === "Active" ? "Inactive" : "Active" }),
                                class: [
                                  "px-3 py-1 rounded text-xs font-semibold text-white",
                                  item.status === "Active" ? "bg-gray-500 hover:bg-gray-600" : "bg-green-500 hover:bg-green-600"
                                ]
                              }, toDisplayString(item.status === "Active" ? "Inactive" : "Active"), 11, ["href"]),
                              createVNode("a", {
                                href: _ctx.route("backend.chargetype.edit", item.id),
                                class: "px-3 py-1 rounded text-xs font-semibold bg-yellow-400 text-black hover:bg-yellow-500"
                              }, " Edit ", 8, ["href"]),
                              createVNode("button", {
                                onClick: ($event) => _ctx.deleteChargeType(item.id),
                                class: "px-3 py-1 rounded text-xs font-semibold bg-red-500 text-white hover:bg-red-600"
                              }, " Delete ", 8, ["onClick"])
                            ])
                          ])
                        ]);
                      }), 128))
                    ])
                  ])
                ]),
                createVNode(_sfc_main$2, { data: __props.datas }, null, 8, ["data"])
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/ChargeType/Index.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const Index = /* @__PURE__ */ _export_sfc(_sfc_main, [["__scopeId", "data-v-602b1cde"]]);
export {
  Index as default
};
