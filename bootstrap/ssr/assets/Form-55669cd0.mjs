import { withCtx, unref, createTextVNode, toDisplayString, createVNode, openBlock, createBlock, withModifiers, withDirectives, vModelText, Fragment, renderList, vModelSelect, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrRenderAttr, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderList } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import { useForm, router } from "@inertiajs/vue3";
import { _ as _sfc_main$3 } from "./InputError-6aeb8d97.mjs";
import { _ as _sfc_main$2 } from "./InputLabel-70ca52d1.mjs";
import { _ as _sfc_main$4 } from "./PrimaryButton-b82fb16e.mjs";
import { A as AlertMessage } from "./AlertMessage-0f422981.mjs";
import { d as displayResponse, a as displayWarning } from "./responseMessage-d505224b.mjs";
import { _ as _export_sfc } from "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "mitt";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
import "toastr";
import "sweetalert2";
const Form_vue_vue_type_style_index_0_scoped_7bdaf079_lang = "";
const _sfc_main = {
  __name: "Form",
  __ssrInlineRender: true,
  props: ["bed", "id", "bedTypes", "bedGroups"],
  setup(__props) {
    var _a, _b, _c, _d;
    const props = __props;
    const form = useForm({
      name: ((_a = props.bed) == null ? void 0 : _a.name) ?? "",
      bed_type_id: ((_b = props.bed) == null ? void 0 : _b.bed_type_id) ?? "",
      bed_group_id: ((_c = props.bed) == null ? void 0 : _c.bed_group_id) ?? "",
      _method: ((_d = props.bed) == null ? void 0 : _d.id) ? "put" : "post"
    });
    const submit = () => {
      const routeName = props.id ? route("backend.bed.update", props.id) : route("backend.bed.store");
      form.post(routeName, {
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
    const goToBedList = () => {
      router.visit(route("backend.bed.index"));
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md" data-v-7bdaf079${_scopeId}><div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md" data-v-7bdaf079${_scopeId}><div data-v-7bdaf079${_scopeId}><h1 class="p-4 text-xl font-bold dark:text-white" data-v-7bdaf079${_scopeId}>${ssrInterpolate(_ctx.$page.props.pageTitle)}</h1></div><div class="p-2 py-2 flex items-center space-x-2" data-v-7bdaf079${_scopeId}><div class="flex items-center space-x-3" data-v-7bdaf079${_scopeId}><button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2" data-v-7bdaf079${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" data-v-7bdaf079${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" data-v-7bdaf079${_scopeId}></path></svg> Bed List </button></div></div></div><form class="p-4" data-v-7bdaf079${_scopeId}>`);
            _push2(ssrRenderComponent(AlertMessage, null, null, _parent2, _scopeId));
            _push2(`<div class="w-full grid grid-cols-1 gap-6 md:grid-cols-3" data-v-7bdaf079${_scopeId}><div data-v-7bdaf079${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "name",
              value: "Bed Name",
              class: "required"
            }, null, _parent2, _scopeId));
            _push2(`<input id="name"${ssrRenderAttr("value", unref(form).name)} type="text" required class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" placeholder="Enter bed name (e.g., Bed 101)" data-v-7bdaf079${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.name
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-7bdaf079${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "bed_type_id",
              value: "Bed Type",
              class: "required"
            }, null, _parent2, _scopeId));
            _push2(`<select id="bed_type_id" required class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-7bdaf079${_scopeId}><option value="" data-v-7bdaf079${ssrIncludeBooleanAttr(Array.isArray(unref(form).bed_type_id) ? ssrLooseContain(unref(form).bed_type_id, "") : ssrLooseEqual(unref(form).bed_type_id, "")) ? " selected" : ""}${_scopeId}>Select Bed Type</option><!--[-->`);
            ssrRenderList(__props.bedTypes, (bedType) => {
              _push2(`<option${ssrRenderAttr("value", bedType.id)} data-v-7bdaf079${ssrIncludeBooleanAttr(Array.isArray(unref(form).bed_type_id) ? ssrLooseContain(unref(form).bed_type_id, bedType.id) : ssrLooseEqual(unref(form).bed_type_id, bedType.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(bedType.name)}</option>`);
            });
            _push2(`<!--]--></select>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.bed_type_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-7bdaf079${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "bed_group_id",
              value: "Bed Group",
              class: "required"
            }, null, _parent2, _scopeId));
            _push2(`<select id="bed_group_id" required class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-7bdaf079${_scopeId}><option value="" data-v-7bdaf079${ssrIncludeBooleanAttr(Array.isArray(unref(form).bed_group_id) ? ssrLooseContain(unref(form).bed_group_id, "") : ssrLooseEqual(unref(form).bed_group_id, "")) ? " selected" : ""}${_scopeId}>Select Bed Group</option><!--[-->`);
            ssrRenderList(__props.bedGroups, (bedGroup) => {
              _push2(`<option${ssrRenderAttr("value", bedGroup.id)} data-v-7bdaf079${ssrIncludeBooleanAttr(Array.isArray(unref(form).bed_group_id) ? ssrLooseContain(unref(form).bed_group_id, bedGroup.id) : ssrLooseEqual(unref(form).bed_group_id, bedGroup.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(bedGroup.name)}</option>`);
            });
            _push2(`<!--]--></select>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.bed_group_id
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="flex items-center justify-end mt-6" data-v-7bdaf079${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              type: "submit",
              class: ["ms-4", { "opacity-25": unref(form).processing }],
              disabled: unref(form).processing
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`${ssrInterpolate(props.id ? "Update" : "Create")} Bed `);
                } else {
                  return [
                    createTextVNode(toDisplayString(props.id ? "Update" : "Create") + " Bed ", 1)
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
                        onClick: goToBedList,
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
                        createTextVNode(" Bed List ")
                      ])
                    ])
                  ])
                ]),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"]),
                  class: "p-4"
                }, [
                  createVNode(AlertMessage),
                  createVNode("div", { class: "w-full grid grid-cols-1 gap-6 md:grid-cols-3" }, [
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "name",
                        value: "Bed Name",
                        class: "required"
                      }),
                      withDirectives(createVNode("input", {
                        id: "name",
                        "onUpdate:modelValue": ($event) => unref(form).name = $event,
                        type: "text",
                        required: "",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        placeholder: "Enter bed name (e.g., Bed 101)"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).name]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.name
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "bed_type_id",
                        value: "Bed Type",
                        class: "required"
                      }),
                      withDirectives(createVNode("select", {
                        id: "bed_type_id",
                        "onUpdate:modelValue": ($event) => unref(form).bed_type_id = $event,
                        required: "",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                      }, [
                        createVNode("option", { value: "" }, "Select Bed Type"),
                        (openBlock(true), createBlock(Fragment, null, renderList(__props.bedTypes, (bedType) => {
                          return openBlock(), createBlock("option", {
                            key: bedType.id,
                            value: bedType.id
                          }, toDisplayString(bedType.name), 9, ["value"]);
                        }), 128))
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).bed_type_id]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.bed_type_id
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "bed_group_id",
                        value: "Bed Group",
                        class: "required"
                      }),
                      withDirectives(createVNode("select", {
                        id: "bed_group_id",
                        "onUpdate:modelValue": ($event) => unref(form).bed_group_id = $event,
                        required: "",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                      }, [
                        createVNode("option", { value: "" }, "Select Bed Group"),
                        (openBlock(true), createBlock(Fragment, null, renderList(__props.bedGroups, (bedGroup) => {
                          return openBlock(), createBlock("option", {
                            key: bedGroup.id,
                            value: bedGroup.id
                          }, toDisplayString(bedGroup.name), 9, ["value"]);
                        }), 128))
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).bed_group_id]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.bed_group_id
                      }, null, 8, ["message"])
                    ])
                  ]),
                  createVNode("div", { class: "flex items-center justify-end mt-6" }, [
                    createVNode(_sfc_main$4, {
                      type: "submit",
                      class: ["ms-4", { "opacity-25": unref(form).processing }],
                      disabled: unref(form).processing
                    }, {
                      default: withCtx(() => [
                        createTextVNode(toDisplayString(props.id ? "Update" : "Create") + " Bed ", 1)
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/Bed/Form.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const Form = /* @__PURE__ */ _export_sfc(_sfc_main, [["__scopeId", "data-v-7bdaf079"]]);
export {
  Form as default
};
