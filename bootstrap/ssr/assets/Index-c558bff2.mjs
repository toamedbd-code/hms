import { ref, withCtx, createVNode, toDisplayString, openBlock, createBlock, createTextVNode, withDirectives, vModelText, vModelSelect, createCommentVNode, useSSRContext } from "vue";
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
    var _a, _b;
    const props = __props;
    const filters = ref({
      name: ((_a = props.filters) == null ? void 0 : _a.name) ?? "",
      numOfData: ((_b = props.filters) == null ? void 0 : _b.numOfData) ?? 10
    });
    const applyFilter = () => {
      router.get(
        route("backend.medicineinventory.index"),
        filters.value,
        {
          preserveState: true,
          replace: true
        }
      );
    };
    const goToInventoryAdd = () => {
      router.visit(route("backend.medicineinventory.create"));
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          var _a2, _b2;
          if (_push2) {
            _push2(`<div class="w-full p-3 bg-white rounded-md dark:bg-slate-900"${_scopeId}><div class="flex items-center justify-between mb-3 bg-gray-100 rounded-md dark:bg-gray-800"${_scopeId}><h1 class="p-4 text-xl font-bold dark:text-white"${_scopeId}>${ssrInterpolate(_ctx.$page.props.pageTitle)}</h1><div class="p-4"${_scopeId}><button class="inline-flex items-center px-4 py-2 text-sm font-semibold text-white rounded-md shadow" style="${ssrRenderStyle({ "background": "linear-gradient(to right, #3b82f6, #60a5fa)" })}"${_scopeId}><svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"${_scopeId}></path></svg> Medicine Inventory Add </button></div></div><div class="grid grid-cols-1 gap-3 p-3 mb-3 bg-slate-200 rounded-md md:grid-cols-5 dark:bg-gray-700"${_scopeId}><div class="md:col-span-3"${_scopeId}><input${ssrRenderAttr("value", filters.value.name)} type="text" placeholder="Search by Medicine Name" class="w-full p-2 text-sm border rounded-md dark:bg-slate-700 dark:text-white"${_scopeId}></div><div class="md:col-span-2"${_scopeId}><select class="w-full p-2 text-sm border rounded-md dark:bg-slate-700 dark:text-white"${_scopeId}><option value="10"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "10") : ssrLooseEqual(filters.value.numOfData, "10")) ? " selected" : ""}${_scopeId}>Show 10</option><option value="20"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "20") : ssrLooseEqual(filters.value.numOfData, "20")) ? " selected" : ""}${_scopeId}>Show 20</option><option value="30"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "30") : ssrLooseEqual(filters.value.numOfData, "30")) ? " selected" : ""}${_scopeId}>Show 30</option><option value="50"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "50") : ssrLooseEqual(filters.value.numOfData, "50")) ? " selected" : ""}${_scopeId}>Show 50</option><option value="100"${ssrIncludeBooleanAttr(Array.isArray(filters.value.numOfData) ? ssrLooseContain(filters.value.numOfData, "100") : ssrLooseEqual(filters.value.numOfData, "100")) ? " selected" : ""}${_scopeId}>Show 100</option></select></div></div><div class="w-full overflow-x-auto"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, null, null, _parent2, _scopeId));
            _push2(`</div>`);
            if ((_a2 = _ctx.$page.props.data) == null ? void 0 : _a2.links) {
              _push2(ssrRenderComponent(_sfc_main$3, null, null, _parent2, _scopeId));
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div>`);
          } else {
            return [
              createVNode("div", { class: "w-full p-3 bg-white rounded-md dark:bg-slate-900" }, [
                createVNode("div", { class: "flex items-center justify-between mb-3 bg-gray-100 rounded-md dark:bg-gray-800" }, [
                  createVNode("h1", { class: "p-4 text-xl font-bold dark:text-white" }, toDisplayString(_ctx.$page.props.pageTitle), 1),
                  createVNode("div", { class: "p-4" }, [
                    createVNode("button", {
                      onClick: goToInventoryAdd,
                      class: "inline-flex items-center px-4 py-2 text-sm font-semibold text-white rounded-md shadow",
                      style: { "background": "linear-gradient(to right, #3b82f6, #60a5fa)" }
                    }, [
                      (openBlock(), createBlock("svg", {
                        class: "w-4 h-4 mr-2",
                        fill: "none",
                        stroke: "currentColor",
                        viewBox: "0 0 24 24"
                      }, [
                        createVNode("path", {
                          "stroke-linecap": "round",
                          "stroke-linejoin": "round",
                          "stroke-width": "2",
                          d: "M12 4.5v15m7.5-7.5h-15"
                        })
                      ])),
                      createTextVNode(" Medicine Inventory Add ")
                    ])
                  ])
                ]),
                createVNode("div", { class: "grid grid-cols-1 gap-3 p-3 mb-3 bg-slate-200 rounded-md md:grid-cols-5 dark:bg-gray-700" }, [
                  createVNode("div", { class: "md:col-span-3" }, [
                    withDirectives(createVNode("input", {
                      "onUpdate:modelValue": ($event) => filters.value.name = $event,
                      onInput: applyFilter,
                      type: "text",
                      placeholder: "Search by Medicine Name",
                      class: "w-full p-2 text-sm border rounded-md dark:bg-slate-700 dark:text-white"
                    }, null, 40, ["onUpdate:modelValue"]), [
                      [vModelText, filters.value.name]
                    ])
                  ]),
                  createVNode("div", { class: "md:col-span-2" }, [
                    withDirectives(createVNode("select", {
                      "onUpdate:modelValue": ($event) => filters.value.numOfData = $event,
                      onChange: applyFilter,
                      class: "w-full p-2 text-sm border rounded-md dark:bg-slate-700 dark:text-white"
                    }, [
                      createVNode("option", { value: "10" }, "Show 10"),
                      createVNode("option", { value: "20" }, "Show 20"),
                      createVNode("option", { value: "30" }, "Show 30"),
                      createVNode("option", { value: "50" }, "Show 50"),
                      createVNode("option", { value: "100" }, "Show 100")
                    ], 40, ["onUpdate:modelValue"]), [
                      [vModelSelect, filters.value.numOfData]
                    ])
                  ])
                ]),
                createVNode("div", { class: "w-full overflow-x-auto" }, [
                  createVNode(_sfc_main$2)
                ]),
                ((_b2 = _ctx.$page.props.data) == null ? void 0 : _b2.links) ? (openBlock(), createBlock(_sfc_main$3, { key: 0 })) : createCommentVNode("", true)
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/MedicineInventory/Index.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
