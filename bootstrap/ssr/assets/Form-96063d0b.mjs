import { withCtx, unref, createTextVNode, toDisplayString, createVNode, openBlock, createBlock, withModifiers, withDirectives, vModelText, Fragment, renderList, vModelSelect, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrRenderAttr, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderList } from "vue/server-renderer";
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
  props: ["applyleave", "id", "leaveTypes", "employeeDetails"],
  setup(__props) {
    var _a, _b, _c, _d, _e, _f, _g, _h;
    const props = __props;
    const form = useForm({
      apply_date: ((_a = props.applyleave) == null ? void 0 : _a.apply_date) ?? "",
      leave_type_id: ((_b = props.applyleave) == null ? void 0 : _b.leave_type_id) ?? "",
      employee_id: ((_c = props.applyleave) == null ? void 0 : _c.employee_id) ?? "",
      from: ((_d = props.applyleave) == null ? void 0 : _d.from) ?? "",
      to: ((_e = props.applyleave) == null ? void 0 : _e.to) ?? "",
      reason: ((_f = props.applyleave) == null ? void 0 : _f.reason) ?? "",
      attachment: ((_g = props.applyleave) == null ? void 0 : _g.attachment) ?? "",
      _method: ((_h = props.applyleave) == null ? void 0 : _h.id) ? "put" : "post"
    });
    const handlefileChange = (event) => {
      const file = event.target.files[0];
      form.attachment = file;
    };
    const submit = () => {
      const routeName = props.id ? route("backend.applyleave.update", props.id) : route("backend.applyleave.store");
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
    const goToApplyLeaveList = () => {
      router.visit(route("backend.applyleave.index"));
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md"${_scopeId}><div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md"${_scopeId}><div${_scopeId}><h1 class="p-4 text-xl font-bold"${_scopeId}>${ssrInterpolate(_ctx.$page.props.pageTitle)}</h1></div><div class="flex items-center p-3 py-2 space-x-1"${_scopeId}><div class="p-2 py-2 flex items-center space-x-2"${_scopeId}><div class="flex items-center space-x-3"${_scopeId}><button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2"${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"${_scopeId}></path></svg> Apply Leave List </button></div></div></div></div><form class="p-4"${_scopeId}>`);
            _push2(ssrRenderComponent(AlertMessage, null, null, _parent2, _scopeId));
            _push2(`<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4"${_scopeId}><div class="col-span-1 md:col-span-2"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "apply_date",
              value: "Apply date"
            }, null, _parent2, _scopeId));
            _push2(`<input id="apply_date" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).apply_date)} type="date" placeholder="Apply date"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.apply_date
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1 md:col-span-2"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "leave_type_id",
              value: "Leave type"
            }, null, _parent2, _scopeId));
            _push2(`<select id="leave_type_id" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" type="text" placeholder="Leave type"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).leave_type_id) ? ssrLooseContain(unref(form).leave_type_id, "") : ssrLooseEqual(unref(form).leave_type_id, "")) ? " selected" : ""}${_scopeId}>Select A Type</option><!--[-->`);
            ssrRenderList(__props.leaveTypes, (leaveType) => {
              _push2(`<option${ssrRenderAttr("value", leaveType.id)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).leave_type_id) ? ssrLooseContain(unref(form).leave_type_id, leaveType.id) : ssrLooseEqual(unref(form).leave_type_id, leaveType.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(leaveType.type_name)}</option>`);
            });
            _push2(`<!--]--></select>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.leave_type_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1 md:col-span-2"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "employee_id",
              value: "Employee Name"
            }, null, _parent2, _scopeId));
            _push2(`<select id="employee_id" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" type="text" placeholder="Employee Name"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).employee_id) ? ssrLooseContain(unref(form).employee_id, "") : ssrLooseEqual(unref(form).employee_id, "")) ? " selected" : ""}${_scopeId}>Select Name</option><!--[-->`);
            ssrRenderList(__props.employeeDetails, (user) => {
              _push2(`<option${ssrRenderAttr("value", user.id)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).employee_id) ? ssrLooseContain(unref(form).employee_id, user.id) : ssrLooseEqual(unref(form).employee_id, user.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(user.first_name)}${ssrInterpolate(user.last_name)}</option>`);
            });
            _push2(`<!--]--></select>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.employee_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1 md:col-span-2"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "from",
              value: "From"
            }, null, _parent2, _scopeId));
            _push2(`<input id="from" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).from)} type="date" placeholder="From"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.from
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1 md:col-span-2"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "to",
              value: "To"
            }, null, _parent2, _scopeId));
            _push2(`<input id="to" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).to)} type="date" placeholder="To"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.to
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1 md:col-span-2"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "reason",
              value: "Reason"
            }, null, _parent2, _scopeId));
            _push2(`<textarea id="reason" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" type="text" placeholder="Reason"${_scopeId}>${ssrInterpolate(unref(form).reason)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.reason
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1 md:col-span-2"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "attachment",
              value: "Attachment"
            }, null, _parent2, _scopeId));
            _push2(`<input id="attachment" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" type="file" placeholder="Attachment"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.attachment
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
                          onClick: goToApplyLeaveList,
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
                          createTextVNode(" Apply Leave List ")
                        ])
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
                    createVNode("div", { class: "col-span-1 md:col-span-2" }, [
                      createVNode(_sfc_main$2, {
                        for: "apply_date",
                        value: "Apply date"
                      }),
                      withDirectives(createVNode("input", {
                        id: "apply_date",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).apply_date = $event,
                        type: "date",
                        placeholder: "Apply date"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).apply_date]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.apply_date
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1 md:col-span-2" }, [
                      createVNode(_sfc_main$2, {
                        for: "leave_type_id",
                        value: "Leave type"
                      }),
                      withDirectives(createVNode("select", {
                        id: "leave_type_id",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).leave_type_id = $event,
                        type: "text",
                        placeholder: "Leave type"
                      }, [
                        createVNode("option", { value: "" }, "Select A Type"),
                        (openBlock(true), createBlock(Fragment, null, renderList(__props.leaveTypes, (leaveType) => {
                          return openBlock(), createBlock("option", {
                            key: leaveType.id,
                            value: leaveType.id
                          }, toDisplayString(leaveType.type_name), 9, ["value"]);
                        }), 128))
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).leave_type_id]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.leave_type_id
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1 md:col-span-2" }, [
                      createVNode(_sfc_main$2, {
                        for: "employee_id",
                        value: "Employee Name"
                      }),
                      withDirectives(createVNode("select", {
                        id: "employee_id",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).employee_id = $event,
                        type: "text",
                        placeholder: "Employee Name"
                      }, [
                        createVNode("option", { value: "" }, "Select Name"),
                        (openBlock(true), createBlock(Fragment, null, renderList(__props.employeeDetails, (user) => {
                          return openBlock(), createBlock("option", {
                            key: user.id,
                            value: user.id
                          }, toDisplayString(user.first_name) + toDisplayString(user.last_name), 9, ["value"]);
                        }), 128))
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).employee_id]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.employee_id
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1 md:col-span-2" }, [
                      createVNode(_sfc_main$2, {
                        for: "from",
                        value: "From"
                      }),
                      withDirectives(createVNode("input", {
                        id: "from",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).from = $event,
                        type: "date",
                        placeholder: "From"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).from]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.from
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1 md:col-span-2" }, [
                      createVNode(_sfc_main$2, {
                        for: "to",
                        value: "To"
                      }),
                      withDirectives(createVNode("input", {
                        id: "to",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).to = $event,
                        type: "date",
                        placeholder: "To"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).to]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.to
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1 md:col-span-2" }, [
                      createVNode(_sfc_main$2, {
                        for: "reason",
                        value: "Reason"
                      }),
                      withDirectives(createVNode("textarea", {
                        id: "reason",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).reason = $event,
                        type: "text",
                        placeholder: "Reason"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).reason]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.reason
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1 md:col-span-2" }, [
                      createVNode(_sfc_main$2, {
                        for: "attachment",
                        value: "Attachment"
                      }),
                      createVNode("input", {
                        id: "attachment",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        type: "file",
                        onChange: handlefileChange,
                        placeholder: "Attachment"
                      }, null, 32),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.attachment
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/ApplyLeave/Form.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
