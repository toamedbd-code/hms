import { watch, withCtx, unref, createTextVNode, toDisplayString, createVNode, openBlock, createBlock, withModifiers, withDirectives, Fragment, renderList, vModelSelect, vModelText, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderList, ssrRenderAttr } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import { useForm, router } from "@inertiajs/vue3";
import { _ as _sfc_main$3 } from "./InputError-6aeb8d97.mjs";
import { _ as _sfc_main$2 } from "./InputLabel-70ca52d1.mjs";
import { _ as _sfc_main$4 } from "./PrimaryButton-b82fb16e.mjs";
import "./AlertMessage-0f422981.mjs";
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
  props: ["defineleave", "id", "leaveDetails", "roleDetails"],
  setup(__props) {
    var _a, _b, _c, _d, _e, _f, _g;
    const props = __props;
    const form = useForm({
      role_id: ((_a = props.defineleave) == null ? void 0 : _a.role_id) ?? "",
      type_id: ((_b = props.defineleave) == null ? void 0 : _b.type_id) ?? "",
      days: ((_c = props.defineleave) == null ? void 0 : _c.days) ?? "",
      date: ((_d = props.defineleave) == null ? void 0 : _d.date) ?? "",
      imagePreview: ((_e = props.defineleave) == null ? void 0 : _e.image) ?? "",
      filePreview: ((_f = props.defineleave) == null ? void 0 : _f.file) ?? "",
      _method: ((_g = props.defineleave) == null ? void 0 : _g.id) ? "put" : "post"
    });
    const submit = () => {
      const routeName = props.id ? route("backend.defineleave.update", props.id) : route("backend.defineleave.store");
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
    watch(() => form.type_id, (newType) => {
      const selectedType = props.leaveDetails.find((leaveType) => leaveType.id === newType);
      if (selectedType) {
        form.days = selectedType.days;
      } else {
        form.days = "";
      }
    });
    const goToDefineLeaveList = () => {
      router.visit(route("backend.defineleave.index"));
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md"${_scopeId}><div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md"${_scopeId}><div${_scopeId}><h1 class="p-4 text-xl font-bold"${_scopeId}>${ssrInterpolate(_ctx.$page.props.pageTitle)}</h1></div><div class="flex items-center p-3 py-2 space-x-1"${_scopeId}><div class="p-2 py-2 flex items-center space-x-2"${_scopeId}><div class="flex items-center space-x-3"${_scopeId}><button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2"${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"${_scopeId}></path></svg> Define Leave List </button></div></div></div></div><form class="p-4"${_scopeId}><div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4"${_scopeId}><div class="col-span-1 md:col-span-2"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "role",
              value: "Role"
            }, null, _parent2, _scopeId));
            _push2(`<select id="role" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).role_id) ? ssrLooseContain(unref(form).role_id, "") : ssrLooseEqual(unref(form).role_id, "")) ? " selected" : ""}${_scopeId}>Select A Role</option><!--[-->`);
            ssrRenderList(__props.roleDetails, (role) => {
              _push2(`<option${ssrRenderAttr("value", role.id)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).role_id) ? ssrLooseContain(unref(form).role_id, role.id) : ssrLooseEqual(unref(form).role_id, role.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(role.name)}</option>`);
            });
            _push2(`<!--]--></select>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.role_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1 md:col-span-2"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "type",
              value: "Type"
            }, null, _parent2, _scopeId));
            _push2(`<select id="type" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).type_id) ? ssrLooseContain(unref(form).type_id, "") : ssrLooseEqual(unref(form).type_id, "")) ? " selected" : ""}${_scopeId}>Select A Type</option><!--[-->`);
            ssrRenderList(__props.leaveDetails, (leaveType) => {
              _push2(`<option${ssrRenderAttr("value", leaveType.id)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).type_id) ? ssrLooseContain(unref(form).type_id, leaveType.id) : ssrLooseEqual(unref(form).type_id, leaveType.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(leaveType.type_name)}</option>`);
            });
            _push2(`<!--]--></select>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.type_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1 md:col-span-2"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "days",
              value: "Days"
            }, null, _parent2, _scopeId));
            _push2(`<input id="days" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).days)} type="text" placeholder="Days" readonly${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.days
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
                    createVNode("h1", { class: "p-4 text-xl font-bold" }, toDisplayString(_ctx.$page.props.pageTitle), 1)
                  ]),
                  createVNode("div", { class: "flex items-center p-3 py-2 space-x-1" }, [
                    createVNode("div", { class: "p-2 py-2 flex items-center space-x-2" }, [
                      createVNode("div", { class: "flex items-center space-x-3" }, [
                        createVNode("button", {
                          onClick: goToDefineLeaveList,
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
                          createTextVNode(" Define Leave List ")
                        ])
                      ])
                    ])
                  ])
                ]),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"]),
                  class: "p-4"
                }, [
                  createVNode("div", { class: "grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4" }, [
                    createVNode("div", { class: "col-span-1 md:col-span-2" }, [
                      createVNode(_sfc_main$2, {
                        for: "role",
                        value: "Role"
                      }),
                      withDirectives(createVNode("select", {
                        id: "role",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).role_id = $event
                      }, [
                        createVNode("option", { value: "" }, "Select A Role"),
                        (openBlock(true), createBlock(Fragment, null, renderList(__props.roleDetails, (role) => {
                          return openBlock(), createBlock("option", {
                            key: role.id,
                            value: role.id
                          }, toDisplayString(role.name), 9, ["value"]);
                        }), 128))
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).role_id]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.role_id
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1 md:col-span-2" }, [
                      createVNode(_sfc_main$2, {
                        for: "type",
                        value: "Type"
                      }),
                      withDirectives(createVNode("select", {
                        id: "type",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).type_id = $event
                      }, [
                        createVNode("option", { value: "" }, "Select A Type"),
                        (openBlock(true), createBlock(Fragment, null, renderList(__props.leaveDetails, (leaveType) => {
                          return openBlock(), createBlock("option", {
                            key: leaveType.id,
                            value: leaveType.id
                          }, toDisplayString(leaveType.type_name), 9, ["value"]);
                        }), 128))
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).type_id]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.type_id
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1 md:col-span-2" }, [
                      createVNode(_sfc_main$2, {
                        for: "days",
                        value: "Days"
                      }),
                      withDirectives(createVNode("input", {
                        id: "days",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).days = $event,
                        type: "text",
                        placeholder: "Days",
                        readonly: ""
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).days]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.days
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/DefineLeave/Form.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
