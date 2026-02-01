import { ref, withCtx, createVNode, withDirectives, vModelText, vModelSelect, useSSRContext } from "vue";
import { ssrRenderComponent, ssrRenderAttr, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual } from "vue/server-renderer";
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
      router.get(route("backend.birthdeathrecord.index"), filters.value, { preserveState: true });
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="w-full p-4 mt-3 duration-1000 ease-in-out bg-white rounded shadow-md shadow-gray-800/50 dark:bg-slate-900"${_scopeId}><div class="flex justify-between w-full p-4 space-x-2 text-gray-700 rounded shadow-md bg-slate-600 shadow-gray-800/50 dark:bg-gray-700 dark:text-gray-200"${_scopeId}><div class="grid w-full grid-cols-1 gap-2 md:grid-cols-5"${_scopeId}><div class="flex space-x-2"${_scopeId}><div class="w-full"${_scopeId}><input id="name"${ssrRenderAttr("value", filters.value.name)} class="block w-full p-2 text-sm bg-gray-300 rounded shadow-sm border-slate-100 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" type="text" placeholder="Title"${_scopeId}></div></div></div><div class="hidden min-w-24 md:block"${_scopeId}><select class="w-full p-2 text-sm bg-gray-300 rounded shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value="10"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "10") : ssrLooseEqual(filters.value.numOfData, "10")) ? " selected" : ""}${_scopeId}>show 10</option><option value="20"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "20") : ssrLooseEqual(filters.value.numOfData, "20")) ? " selected" : ""}${_scopeId}>show 20</option><option value="30"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "30") : ssrLooseEqual(filters.value.numOfData, "30")) ? " selected" : ""}${_scopeId}>show 30</option><option value="40"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "40") : ssrLooseEqual(filters.value.numOfData, "40")) ? " selected" : ""}${_scopeId}>show 40</option><option value="100"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "100") : ssrLooseEqual(filters.value.numOfData, "100")) ? " selected" : ""}${_scopeId}>show 100</option><option value="150"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "150") : ssrLooseEqual(filters.value.numOfData, "150")) ? " selected" : ""}${_scopeId}>show 150</option><option value="500"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "500") : ssrLooseEqual(filters.value.numOfData, "500")) ? " selected" : ""}${_scopeId}>show 500</option></select></div></div><div class="w-full my-3 overflow-x-auto"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, null, null, _parent2, _scopeId));
            _push2(`</div>`);
            _push2(ssrRenderComponent(_sfc_main$3, null, null, _parent2, _scopeId));
            _push2(`</div>`);
          } else {
            return [
              createVNode("div", { class: "w-full p-4 mt-3 duration-1000 ease-in-out bg-white rounded shadow-md shadow-gray-800/50 dark:bg-slate-900" }, [
                createVNode("div", { class: "flex justify-between w-full p-4 space-x-2 text-gray-700 rounded shadow-md bg-slate-600 shadow-gray-800/50 dark:bg-gray-700 dark:text-gray-200" }, [
                  createVNode("div", { class: "grid w-full grid-cols-1 gap-2 md:grid-cols-5" }, [
                    createVNode("div", { class: "flex space-x-2" }, [
                      createVNode("div", { class: "w-full" }, [
                        withDirectives(createVNode("input", {
                          id: "name",
                          "onUpdate:modelValue": ($event) => filters.value.name = $event,
                          class: "block w-full p-2 text-sm bg-gray-300 rounded shadow-sm border-slate-100 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                          type: "text",
                          placeholder: "Title",
                          onInput: applyFilter
                        }, null, 40, ["onUpdate:modelValue"]), [
                          [vModelText, filters.value.name]
                        ])
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "hidden min-w-24 md:block" }, [
                    withDirectives(createVNode("select", {
                      "onUpdate:modelValue": ($event) => filters.value.numOfData = $event,
                      onChange: applyFilter,
                      class: "w-full p-2 text-sm bg-gray-300 rounded shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/BirthDeathRecord/Index.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
