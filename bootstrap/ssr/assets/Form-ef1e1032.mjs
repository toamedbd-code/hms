import { withCtx, unref, createTextVNode, toDisplayString, createVNode, openBlock, createBlock, withModifiers, withDirectives, vModelText, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrRenderAttr } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import { useForm, router } from "@inertiajs/vue3";
import { _ as _sfc_main$3 } from "./InputError-6aeb8d97.mjs";
import { _ as _sfc_main$2 } from "./InputLabel-70ca52d1.mjs";
import { _ as _sfc_main$4 } from "./PrimaryButton-b82fb16e.mjs";
import { A as AlertMessage } from "./AlertMessage-0f422981.mjs";
import { d as displayResponse, a as displayWarning } from "./responseMessage-d505224b.mjs";
import "mitt";
import "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
import "toastr";
import "sweetalert2";
const _sfc_main = {
  __name: "Form",
  __ssrInlineRender: true,
  props: ["medicineunit", "id"],
  setup(__props) {
    var _a, _b;
    const props = __props;
    const form = useForm({
      name: ((_a = props.medicineunit) == null ? void 0 : _a.name) ?? "",
      _method: ((_b = props.medicineunit) == null ? void 0 : _b.id) ? "put" : "post"
    });
    const submit = () => {
      const routeName = props.id ? route("backend.medicineunit.update", props.id) : route("backend.medicineunit.store");
      form.transform((data) => ({
        ...data,
        remember: "",
        isDirty: false
      })).post(routeName, {
        onSuccess: (response) => {
          if (!props.id)
            form.reset();
          displayResponse(response);
        },
        onError: (errorObject) => {
          displayWarning(errorObject);
        }
      });
    };
    const goToUnitList = () => {
      router.get(route("backend.medicineunit.index"));
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md"${_scopeId}><div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md"${_scopeId}><div${_scopeId}><h1 class="p-4 text-xl font-bold dark:text-white"${_scopeId}>${ssrInterpolate(_ctx.$page.props.pageTitle)}</h1></div><div class="p-2 py-2 flex items-center space-x-2"${_scopeId}><div class="flex items-center space-x-3"${_scopeId}><button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2"${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"${_scopeId}></path></svg> Medicine Unit List </button></div></div></div><form class="p-4"${_scopeId}>`);
            _push2(ssrRenderComponent(AlertMessage, null, null, _parent2, _scopeId));
            _push2(`<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4"${_scopeId}><div class="col-span-1 md:col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "name",
              value: "Name"
            }, null, _parent2, _scopeId));
            _push2(`<input id="name" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).name)} type="text" placeholder="Name"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.name
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="flex items-center justify-end mt-4"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              type: "submit",
              class: ["ms-4", { "opacity-25": unref(form).processing }],
              disabled: unref(form).processing
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`${ssrInterpolate(props.id ?? false ? "Update" : "Create")}`);
                } else {
                  return [
                    createTextVNode(toDisplayString(props.id ?? false ? "Update" : "Create"), 1)
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</div></form></div>`);
          } else {
            return [
              createVNode("div", { class: "w-full transition duration-1000 ease-in-out transform bg-white rounded-md" }, [
                createVNode("div", { class: "flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md" }, [
                  createVNode("div", null, [
                    createVNode("h1", { class: "p-4 text-xl font-bold dark:text-white" }, toDisplayString(_ctx.$page.props.pageTitle), 1)
                  ]),
                  createVNode("div", { class: "p-2 py-2 flex items-center space-x-2" }, [
                    createVNode("div", { class: "flex items-center space-x-3" }, [
                      createVNode("button", {
                        onClick: goToUnitList,
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
                        createTextVNode(" Medicine Unit List ")
                      ])
                    ])
                  ])
                ]),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"]),
                  class: "p-4"
                }, [
                  createVNode(AlertMessage),
                  createVNode("div", { class: "grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4" }, [
                    createVNode("div", { class: "col-span-1 md:col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "name",
                        value: "Name"
                      }),
                      withDirectives(createVNode("input", {
                        id: "name",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).name = $event,
                        type: "text",
                        placeholder: "Name"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).name]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.name
                      }, null, 8, ["message"])
                    ])
                  ]),
                  createVNode("div", { class: "flex items-center justify-end mt-4" }, [
                    createVNode(_sfc_main$4, {
                      type: "submit",
                      class: ["ms-4", { "opacity-25": unref(form).processing }],
                      disabled: unref(form).processing
                    }, {
                      default: withCtx(() => [
                        createTextVNode(toDisplayString(props.id ?? false ? "Update" : "Create"), 1)
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/MedicineUnit/Form.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
