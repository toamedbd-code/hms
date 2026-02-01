import { computed, withCtx, unref, createTextVNode, toDisplayString, createVNode, openBlock, createBlock, withModifiers, withDirectives, vModelText, Fragment, renderList, vModelCheckbox, createCommentVNode, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrRenderAttr, ssrRenderList, ssrIncludeBooleanAttr, ssrLooseContain, ssrRenderClass } from "vue/server-renderer";
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
  props: ["role", "permissions", "id"],
  setup(__props) {
    var _a, _b, _c, _d;
    const props = __props;
    const form = useForm({
      name: ((_a = props.role) == null ? void 0 : _a.name) ?? "",
      guard_name: ((_b = props.role) == null ? void 0 : _b.guard_name) ?? "admin",
      permission_ids: ((_c = props.role) == null ? void 0 : _c.permission_ids) ?? [],
      _method: ((_d = props.role) == null ? void 0 : _d.id) ? "put" : "post"
    });
    const submit = () => {
      const routeName = props.id ? route("backend.role.update", props.id) : route("backend.role.store");
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
    const checkedPermissions = computed({
      get: () => form.permission_ids,
      set: (newValue) => form.permission_ids = newValue
    });
    function formatLabel(label) {
      if (!label)
        return "";
      return label.replace(/[-_ ]/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
    }
    const goToRoleList = () => {
      router.visit(route("backend.role.index"));
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md"${_scopeId}><div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md"${_scopeId}><div${_scopeId}><h1 class="p-4 text-xl font-bold dark:text-white"${_scopeId}>${ssrInterpolate(_ctx.$page.props.pageTitle)}</h1></div><div class="p-2 py-2 flex items-center space-x-2"${_scopeId}><div class="flex items-center space-x-3"${_scopeId}><button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2"${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"${_scopeId}></path></svg> Role List </button></div></div></div><form class="p-4"${_scopeId}><div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4"${_scopeId}><div class="col-span-1 md:col-span-2"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "name",
              value: "Role Name"
            }, null, _parent2, _scopeId));
            _push2(`<input id="name" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).name)} type="text" placeholder="Role Name"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.name
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="w-full mt-4"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "Permissions",
              value: "Permissions",
              class: "text-black"
            }, null, _parent2, _scopeId));
            _push2(`<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4"${_scopeId}><!--[-->`);
            ssrRenderList(__props.permissions, (permissionInfo) => {
              _push2(`<div${_scopeId}><ul class="ml-4"${_scopeId}><li${_scopeId}><input${ssrIncludeBooleanAttr(Array.isArray(checkedPermissions.value) ? ssrLooseContain(checkedPermissions.value, permissionInfo.id) : checkedPermissions.value) ? " checked" : ""}${ssrRenderAttr("value", permissionInfo.id)} type="checkbox" class="cursor-pointer"${ssrRenderAttr("id", "permission_" + permissionInfo.id)}${_scopeId}><label${ssrRenderAttr("for", "permission_" + permissionInfo.id)} class="${ssrRenderClass([checkedPermissions.value.includes(permissionInfo.id) ? "text-green-500" : "text-gray-700", "ml-2 cursor-pointer font-bold"])}"${_scopeId}>${ssrInterpolate(formatLabel(permissionInfo.name))}</label>`);
              if (permissionInfo.child) {
                _push2(`<ul class="ml-4"${_scopeId}><!--[-->`);
                ssrRenderList(permissionInfo.child, (childInfo) => {
                  _push2(`<li class="ml-4"${_scopeId}><input${ssrIncludeBooleanAttr(Array.isArray(checkedPermissions.value) ? ssrLooseContain(checkedPermissions.value, childInfo.id) : checkedPermissions.value) ? " checked" : ""}${ssrRenderAttr("value", childInfo.id)} type="checkbox" class="cursor-pointer"${ssrRenderAttr("id", "permission_" + childInfo.id)}${_scopeId}><label${ssrRenderAttr("for", "permission_" + childInfo.id)} class="${ssrRenderClass([checkedPermissions.value.includes(childInfo.id) ? "text-green-500" : "text-gray-700", "ml-2 cursor-pointer"])}"${_scopeId}>${ssrInterpolate(formatLabel(childInfo.name))}</label>`);
                  if (childInfo.child) {
                    _push2(`<ul class="ml-4"${_scopeId}><!--[-->`);
                    ssrRenderList(childInfo.child, (childChildInfo) => {
                      _push2(`<li class="ml-4"${_scopeId}><input${ssrIncludeBooleanAttr(Array.isArray(checkedPermissions.value) ? ssrLooseContain(checkedPermissions.value, childChildInfo.id) : checkedPermissions.value) ? " checked" : ""}${ssrRenderAttr("value", childChildInfo.id)} type="checkbox" class="cursor-pointer"${ssrRenderAttr("id", "permission_" + childChildInfo.id)}${_scopeId}><label${ssrRenderAttr("for", "permission_" + childChildInfo.id)} class="${ssrRenderClass([checkedPermissions.value.includes(childChildInfo.id) ? "text-green-500" : "text-black-500", "ml-2 cursor-pointer"])}"${_scopeId}>${ssrInterpolate(formatLabel(childChildInfo.name))}</label></li>`);
                    });
                    _push2(`<!--]--></ul>`);
                  } else {
                    _push2(`<!---->`);
                  }
                  _push2(`</li>`);
                });
                _push2(`<!--]--></ul>`);
              } else {
                _push2(`<!---->`);
              }
              _push2(`</li></ul></div>`);
            });
            _push2(`<!--]--></div></div><div class="flex items-center justify-end mt-4"${_scopeId}>`);
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
                        onClick: goToRoleList,
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
                        createTextVNode(" Role List ")
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
                        for: "name",
                        value: "Role Name"
                      }),
                      withDirectives(createVNode("input", {
                        id: "name",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).name = $event,
                        type: "text",
                        placeholder: "Role Name"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).name]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.name
                      }, null, 8, ["message"])
                    ])
                  ]),
                  createVNode("div", { class: "w-full mt-4" }, [
                    createVNode(_sfc_main$2, {
                      for: "Permissions",
                      value: "Permissions",
                      class: "text-black"
                    }),
                    createVNode("div", { class: "grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4" }, [
                      (openBlock(true), createBlock(Fragment, null, renderList(__props.permissions, (permissionInfo) => {
                        return openBlock(), createBlock("div", {
                          key: permissionInfo.id
                        }, [
                          createVNode("ul", { class: "ml-4" }, [
                            createVNode("li", null, [
                              withDirectives(createVNode("input", {
                                "onUpdate:modelValue": ($event) => checkedPermissions.value = $event,
                                value: permissionInfo.id,
                                type: "checkbox",
                                class: "cursor-pointer",
                                id: "permission_" + permissionInfo.id
                              }, null, 8, ["onUpdate:modelValue", "value", "id"]), [
                                [vModelCheckbox, checkedPermissions.value]
                              ]),
                              createVNode("label", {
                                for: "permission_" + permissionInfo.id,
                                class: ["ml-2 cursor-pointer font-bold", checkedPermissions.value.includes(permissionInfo.id) ? "text-green-500" : "text-gray-700"]
                              }, toDisplayString(formatLabel(permissionInfo.name)), 11, ["for"]),
                              permissionInfo.child ? (openBlock(), createBlock("ul", {
                                key: 0,
                                class: "ml-4"
                              }, [
                                (openBlock(true), createBlock(Fragment, null, renderList(permissionInfo.child, (childInfo) => {
                                  return openBlock(), createBlock("li", {
                                    key: childInfo.id,
                                    class: "ml-4"
                                  }, [
                                    withDirectives(createVNode("input", {
                                      "onUpdate:modelValue": ($event) => checkedPermissions.value = $event,
                                      value: childInfo.id,
                                      type: "checkbox",
                                      class: "cursor-pointer",
                                      id: "permission_" + childInfo.id
                                    }, null, 8, ["onUpdate:modelValue", "value", "id"]), [
                                      [vModelCheckbox, checkedPermissions.value]
                                    ]),
                                    createVNode("label", {
                                      for: "permission_" + childInfo.id,
                                      class: ["ml-2 cursor-pointer", checkedPermissions.value.includes(childInfo.id) ? "text-green-500" : "text-gray-700"]
                                    }, toDisplayString(formatLabel(childInfo.name)), 11, ["for"]),
                                    childInfo.child ? (openBlock(), createBlock("ul", {
                                      key: 0,
                                      class: "ml-4"
                                    }, [
                                      (openBlock(true), createBlock(Fragment, null, renderList(childInfo.child, (childChildInfo) => {
                                        return openBlock(), createBlock("li", {
                                          key: childChildInfo.id,
                                          class: "ml-4"
                                        }, [
                                          withDirectives(createVNode("input", {
                                            "onUpdate:modelValue": ($event) => checkedPermissions.value = $event,
                                            value: childChildInfo.id,
                                            type: "checkbox",
                                            class: "cursor-pointer",
                                            id: "permission_" + childChildInfo.id
                                          }, null, 8, ["onUpdate:modelValue", "value", "id"]), [
                                            [vModelCheckbox, checkedPermissions.value]
                                          ]),
                                          createVNode("label", {
                                            for: "permission_" + childChildInfo.id,
                                            class: ["ml-2 cursor-pointer", checkedPermissions.value.includes(childChildInfo.id) ? "text-green-500" : "text-black-500"]
                                          }, toDisplayString(formatLabel(childChildInfo.name)), 11, ["for"])
                                        ]);
                                      }), 128))
                                    ])) : createCommentVNode("", true)
                                  ]);
                                }), 128))
                              ])) : createCommentVNode("", true)
                            ])
                          ])
                        ]);
                      }), 128))
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/Role/Form.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
