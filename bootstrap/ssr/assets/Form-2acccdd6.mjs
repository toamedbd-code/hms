import { ref, withCtx, unref, createTextVNode, toDisplayString, createVNode, openBlock, createBlock, withModifiers, withDirectives, vModelText, Fragment, renderList, vModelSelect, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrRenderAttr, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderList } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import { useForm, router } from "@inertiajs/vue3";
import { _ as _sfc_main$3 } from "./InputError-6aeb8d97.mjs";
import { _ as _sfc_main$2 } from "./InputLabel-70ca52d1.mjs";
import { _ as _sfc_main$4 } from "./PrimaryButton-b82fb16e.mjs";
import "./AlertMessage-0f422981.mjs";
import { d as displayResponse, a as displayWarning } from "./responseMessage-d505224b.mjs";
import { _ as _sfc_main$5 } from "./UnitModal-7a5b03b4.mjs";
import "mitt";
import "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
import "toastr";
import "sweetalert2";
import "./SecondaryButton-0974b11b.mjs";
import "./Modal-452973b5.mjs";
const _sfc_main = {
  __name: "Form",
  __ssrInlineRender: true,
  props: ["pathologyparameter", "id", "units"],
  setup(__props) {
    var _a, _b, _c, _d, _e, _f;
    const props = __props;
    const form = useForm({
      name: ((_a = props.pathologyparameter) == null ? void 0 : _a.name) ?? "",
      referance_from: ((_b = props.pathologyparameter) == null ? void 0 : _b.referance_from) ?? "",
      referance_to: ((_c = props.pathologyparameter) == null ? void 0 : _c.referance_to) ?? "",
      pathology_unit_id: ((_d = props.pathologyparameter) == null ? void 0 : _d.pathology_unit_id) ?? "",
      description: ((_e = props.pathologyparameter) == null ? void 0 : _e.description) ?? "",
      _method: ((_f = props.pathologyparameter) == null ? void 0 : _f.id) ? "put" : "post"
    });
    const showUnitModal = ref(false);
    const openUnitModal = () => {
      showUnitModal.value = true;
    };
    const closeUnitModal = () => {
      showUnitModal.value = false;
    };
    const handleUnitCreated = (response) => {
      router.reload({
        only: ["units"],
        onSuccess: () => {
          var _a2;
          const newUnitId = (_a2 = response.data) == null ? void 0 : _a2.id;
          if (newUnitId) {
            form.pathology_unit_id = newUnitId;
          }
        }
      });
    };
    const submit = () => {
      const routeName = props.id ? route("backend.parameterofpathology.update", props.id) : route("backend.parameterofpathology.store");
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
    const goToTestParameterList = () => {
      router.get(route("backend.parameterofpathology.index"));
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md"${_scopeId}><div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md"${_scopeId}><div${_scopeId}><h1 class="p-4 text-xl font-bold dark:text-white"${_scopeId}>${ssrInterpolate(_ctx.$page.props.pageTitle)}</h1></div><div class="p-2 py-2 flex items-center space-x-2"${_scopeId}><div class="flex items-center space-x-3"${_scopeId}><button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2"${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"${_scopeId}></path></svg> Test Parameter List </button></div></div></div><form class="p-4"${_scopeId}><div class="grid grid-cols-1 gap-3 sm:grid-cols-2"${_scopeId}><div class="col-span-1 sm:col-span-2"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "name",
              value: "Parameter Name*"
            }, null, _parent2, _scopeId));
            _push2(`<input id="name" type="text" required class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).name)}${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.name
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "referance_from",
              value: "Reference Range From*"
            }, null, _parent2, _scopeId));
            _push2(`<input id="referance_from" type="text" required class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).referance_from)}${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.referance_from
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "referance_to",
              value: "Reference Range To*"
            }, null, _parent2, _scopeId));
            _push2(`<input id="referance_to" type="text" required class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).referance_to)}${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.referance_to
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "pathology_unit_id",
              value: "Unit*"
            }, null, _parent2, _scopeId));
            _push2(`<div class="flex items-center space-x-2"${_scopeId}><select id="pathology_unit_id" required class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).pathology_unit_id) ? ssrLooseContain(unref(form).pathology_unit_id, "") : ssrLooseEqual(unref(form).pathology_unit_id, "")) ? " selected" : ""}${_scopeId}>Select Unit</option><!--[-->`);
            ssrRenderList(__props.units, (unit) => {
              _push2(`<option${ssrRenderAttr("value", unit.id)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).pathology_unit_id) ? ssrLooseContain(unref(form).pathology_unit_id, unit.id) : ssrLooseEqual(unref(form).pathology_unit_id, unit.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(unit.name)}</option>`);
            });
            _push2(`<!--]--></select><button type="button" class="flex-shrink-0 inline-flex items-center justify-center w-10 h-10 text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200" title="Add New Unit"${_scopeId}><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"${_scopeId}></path></svg></button></div>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.pathology_unit_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1 sm:col-span-2"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "description",
              value: "Description"
            }, null, _parent2, _scopeId));
            _push2(`<textarea id="description" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" rows="3"${_scopeId}>${ssrInterpolate(unref(form).description)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.description
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
            _push2(ssrRenderComponent(_sfc_main$5, {
              show: showUnitModal.value,
              "existing-units": __props.units,
              onClose: closeUnitModal,
              onUnitCreated: handleUnitCreated
            }, null, _parent2, _scopeId));
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
                        onClick: goToTestParameterList,
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
                        createTextVNode(" Test Parameter List ")
                      ])
                    ])
                  ])
                ]),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"]),
                  class: "p-4"
                }, [
                  createVNode("div", { class: "grid grid-cols-1 gap-3 sm:grid-cols-2" }, [
                    createVNode("div", { class: "col-span-1 sm:col-span-2" }, [
                      createVNode(_sfc_main$2, {
                        for: "name",
                        value: "Parameter Name*"
                      }),
                      withDirectives(createVNode("input", {
                        id: "name",
                        type: "text",
                        required: "",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).name = $event
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).name]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.name
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "referance_from",
                        value: "Reference Range From*"
                      }),
                      withDirectives(createVNode("input", {
                        id: "referance_from",
                        type: "text",
                        required: "",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).referance_from = $event
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).referance_from]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.referance_from
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "referance_to",
                        value: "Reference Range To*"
                      }),
                      withDirectives(createVNode("input", {
                        id: "referance_to",
                        type: "text",
                        required: "",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).referance_to = $event
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).referance_to]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.referance_to
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "pathology_unit_id",
                        value: "Unit*"
                      }),
                      createVNode("div", { class: "flex items-center space-x-2" }, [
                        withDirectives(createVNode("select", {
                          id: "pathology_unit_id",
                          required: "",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                          "onUpdate:modelValue": ($event) => unref(form).pathology_unit_id = $event
                        }, [
                          createVNode("option", { value: "" }, "Select Unit"),
                          (openBlock(true), createBlock(Fragment, null, renderList(__props.units, (unit) => {
                            return openBlock(), createBlock("option", {
                              key: unit.id,
                              value: unit.id
                            }, toDisplayString(unit.name), 9, ["value"]);
                          }), 128))
                        ], 8, ["onUpdate:modelValue"]), [
                          [vModelSelect, unref(form).pathology_unit_id]
                        ]),
                        createVNode("button", {
                          type: "button",
                          onClick: openUnitModal,
                          class: "flex-shrink-0 inline-flex items-center justify-center w-10 h-10 text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200",
                          title: "Add New Unit"
                        }, [
                          (openBlock(), createBlock("svg", {
                            class: "w-5 h-5",
                            fill: "none",
                            stroke: "currentColor",
                            viewBox: "0 0 24 24",
                            "stroke-width": "2"
                          }, [
                            createVNode("path", {
                              "stroke-linecap": "round",
                              "stroke-linejoin": "round",
                              d: "M12 6v6m0 0v6m0-6h6m-6 0H6"
                            })
                          ]))
                        ])
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.pathology_unit_id
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1 sm:col-span-2" }, [
                      createVNode(_sfc_main$2, {
                        for: "description",
                        value: "Description"
                      }),
                      withDirectives(createVNode("textarea", {
                        id: "description",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).description = $event,
                        rows: "3"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).description]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.description
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
              ]),
              createVNode(_sfc_main$5, {
                show: showUnitModal.value,
                "existing-units": __props.units,
                onClose: closeUnitModal,
                onUnitCreated: handleUnitCreated
              }, null, 8, ["show", "existing-units"])
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/PathologyParameter/Form.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
