import { watch, withCtx, unref, createTextVNode, toDisplayString, createVNode, withModifiers, withDirectives, vModelText, openBlock, createBlock, createCommentVNode, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrRenderAttr } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import { useForm, router } from "@inertiajs/vue3";
import { _ as _sfc_main$3 } from "./InputError-6aeb8d97.mjs";
import { _ as _sfc_main$2 } from "./InputLabel-70ca52d1.mjs";
import { _ as _sfc_main$4 } from "./PrimaryButton-b82fb16e.mjs";
import "./AlertMessage-0f422981.mjs";
import { d as displayResponse, a as displayWarning } from "./responseMessage-d505224b.mjs";
import "jquery";
import "mitt";
import "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
import "toastr";
import "sweetalert2";
const _sfc_main = {
  __name: "Form",
  __ssrInlineRender: true,
  props: ["websetting", "id"],
  setup(__props) {
    var _a, _b, _c, _d, _e, _f, _g, _h, _i;
    const props = __props;
    const form = useForm({
      company_name: ((_a = props.websetting) == null ? void 0 : _a.company_name) ?? "",
      company_short_name: ((_b = props.websetting) == null ? void 0 : _b.company_short_name) ?? "",
      phone: ((_c = props.websetting) == null ? void 0 : _c.phone) ?? "",
      logo: ((_d = props.websetting) == null ? void 0 : _d.logo) ?? "",
      icon: ((_e = props.websetting) == null ? void 0 : _e.icon) ?? "",
      report_title: ((_f = props.websetting) == null ? void 0 : _f.report_title) ?? "",
      logoPreview: ((_g = props.websetting) == null ? void 0 : _g.logo) ?? "",
      iconPreview: ((_h = props.websetting) == null ? void 0 : _h.icon) ?? "",
      _method: ((_i = props.websetting) == null ? void 0 : _i.id) ? "post" : "post"
    });
    watch(() => form.company_name, (newValue) => {
      if (newValue) {
        const words = newValue.trim().split(/\s+/);
        const shortName = words.map((word) => word.charAt(0).toUpperCase()).join("");
        form.company_short_name = shortName.substring(0, 10);
      }
    });
    const handleLogoChange = (event) => {
      const file = event.target.files[0];
      form.logo = file;
      const reader = new FileReader();
      reader.onload = (e) => {
        form.logoPreview = e.target.result;
      };
      reader.readAsDataURL(file);
    };
    const handleIconChange = (event) => {
      const file = event.target.files[0];
      form.icon = file;
      const reader = new FileReader();
      reader.onload = (e) => {
        form.iconPreview = e.target.result;
      };
      reader.readAsDataURL(file);
    };
    const submit = () => {
      const routeName = route("backend.websetting.store");
      form.transform((data) => ({
        ...data,
        remember: "",
        isDirty: false
      })).post(routeName, {
        onSuccess: (response) => {
          displayResponse(response);
          router.reload({
            only: ["websetting"],
            preserveScroll: true,
            preserveState: true
          });
        },
        onError: (errorObject) => {
          displayWarning(errorObject);
        }
      });
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md"${_scopeId}><div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md"${_scopeId}><div${_scopeId}><h1 class="p-4 text-xl font-bold dark:text-white"${_scopeId}>${ssrInterpolate(_ctx.$page.props.pageTitle)}</h1></div><div class="p-4 py-2"${_scopeId}></div></div><form class="p-4"${_scopeId}><div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3"${_scopeId}><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "company_name",
              value: "Company Name"
            }, null, _parent2, _scopeId));
            _push2(`<input id="company_name" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).company_name)} type="text" placeholder="Enter company name"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.company_name
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "company_short_name",
              value: "Company Short Name"
            }, null, _parent2, _scopeId));
            _push2(`<input id="company_short_name" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).company_short_name)} type="text" placeholder="Auto-generated or custom"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.company_short_name
            }, null, _parent2, _scopeId));
            _push2(`<p class="text-xs text-gray-500 mt-1"${_scopeId}>Auto-generated from company name or enter custom</p></div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "phone",
              value: "Phone"
            }, null, _parent2, _scopeId));
            _push2(`<input id="phone" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).phone)} type="text" placeholder="Enter phone number"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.phone
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="grid grid-cols-1 gap-3 mt-3"${_scopeId}><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "report_title",
              value: "Report Title"
            }, null, _parent2, _scopeId));
            _push2(`<input id="report_title" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).report_title)} type="text" placeholder="Enter report title"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.report_title
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6"${_scopeId}><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "logo",
              value: "Company Logo"
            }, null, _parent2, _scopeId));
            if (unref(form).logoPreview) {
              _push2(`<div class="mb-3"${_scopeId}><img${ssrRenderAttr("src", unref(form).logoPreview)} alt="Logo Preview" class="max-w-xs rounded-md border shadow-sm" width="150" height="120"${_scopeId}></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<input id="logo" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" type="file" accept="image/*"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.logo
            }, null, _parent2, _scopeId));
            _push2(`<p class="text-xs text-gray-500 mt-1"${_scopeId}>Recommended: 300x200px, Max: 2MB</p></div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "icon",
              value: "Company Icon"
            }, null, _parent2, _scopeId));
            if (unref(form).iconPreview) {
              _push2(`<div class="mb-3"${_scopeId}><img${ssrRenderAttr("src", unref(form).iconPreview)} alt="Icon Preview" class="max-w-xs rounded-md border shadow-sm" width="80" height="80"${_scopeId}></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<input id="icon" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" type="file" accept="image/*,.ico"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.icon
            }, null, _parent2, _scopeId));
            _push2(`<p class="text-xs text-gray-500 mt-1"${_scopeId}>Recommended: 64x64px, Max: 1MB</p></div></div><div class="flex items-center justify-end mt-6"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              type: "submit",
              class: ["ms-4", { "opacity-25": unref(form).processing }],
              disabled: unref(form).processing
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`${ssrInterpolate(props.id ? "Update Settings" : "Save Settings")}`);
                } else {
                  return [
                    createTextVNode(toDisplayString(props.id ? "Update Settings" : "Save Settings"), 1)
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
                  createVNode("div", { class: "p-4 py-2" })
                ]),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"]),
                  class: "p-4"
                }, [
                  createVNode("div", { class: "grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3" }, [
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "company_name",
                        value: "Company Name"
                      }),
                      withDirectives(createVNode("input", {
                        id: "company_name",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).company_name = $event,
                        type: "text",
                        placeholder: "Enter company name"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).company_name]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.company_name
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "company_short_name",
                        value: "Company Short Name"
                      }),
                      withDirectives(createVNode("input", {
                        id: "company_short_name",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).company_short_name = $event,
                        type: "text",
                        placeholder: "Auto-generated or custom"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).company_short_name]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.company_short_name
                      }, null, 8, ["message"]),
                      createVNode("p", { class: "text-xs text-gray-500 mt-1" }, "Auto-generated from company name or enter custom")
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "phone",
                        value: "Phone"
                      }),
                      withDirectives(createVNode("input", {
                        id: "phone",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).phone = $event,
                        type: "text",
                        placeholder: "Enter phone number"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).phone]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.phone
                      }, null, 8, ["message"])
                    ])
                  ]),
                  createVNode("div", { class: "grid grid-cols-1 gap-3 mt-3" }, [
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "report_title",
                        value: "Report Title"
                      }),
                      withDirectives(createVNode("input", {
                        id: "report_title",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).report_title = $event,
                        type: "text",
                        placeholder: "Enter report title"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).report_title]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.report_title
                      }, null, 8, ["message"])
                    ])
                  ]),
                  createVNode("div", { class: "grid grid-cols-1 md:grid-cols-2 gap-6 mt-6" }, [
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "logo",
                        value: "Company Logo"
                      }),
                      unref(form).logoPreview ? (openBlock(), createBlock("div", {
                        key: 0,
                        class: "mb-3"
                      }, [
                        createVNode("img", {
                          src: unref(form).logoPreview,
                          alt: "Logo Preview",
                          class: "max-w-xs rounded-md border shadow-sm",
                          width: "150",
                          height: "120"
                        }, null, 8, ["src"])
                      ])) : createCommentVNode("", true),
                      createVNode("input", {
                        id: "logo",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        type: "file",
                        accept: "image/*",
                        onChange: handleLogoChange
                      }, null, 32),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.logo
                      }, null, 8, ["message"]),
                      createVNode("p", { class: "text-xs text-gray-500 mt-1" }, "Recommended: 300x200px, Max: 2MB")
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "icon",
                        value: "Company Icon"
                      }),
                      unref(form).iconPreview ? (openBlock(), createBlock("div", {
                        key: 0,
                        class: "mb-3"
                      }, [
                        createVNode("img", {
                          src: unref(form).iconPreview,
                          alt: "Icon Preview",
                          class: "max-w-xs rounded-md border shadow-sm",
                          width: "80",
                          height: "80"
                        }, null, 8, ["src"])
                      ])) : createCommentVNode("", true),
                      createVNode("input", {
                        id: "icon",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        type: "file",
                        accept: "image/*,.ico",
                        onChange: handleIconChange
                      }, null, 32),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.icon
                      }, null, 8, ["message"]),
                      createVNode("p", { class: "text-xs text-gray-500 mt-1" }, "Recommended: 64x64px, Max: 1MB")
                    ])
                  ]),
                  createVNode("div", { class: "flex items-center justify-end mt-6" }, [
                    createVNode(_sfc_main$4, {
                      type: "submit",
                      class: ["ms-4", { "opacity-25": unref(form).processing }],
                      disabled: unref(form).processing
                    }, {
                      default: withCtx(() => [
                        createTextVNode(toDisplayString(props.id ? "Update Settings" : "Save Settings"), 1)
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/WebSetting/Form.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
