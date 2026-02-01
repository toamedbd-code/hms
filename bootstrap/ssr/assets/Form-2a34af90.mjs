import { withCtx, unref, openBlock, createBlock, createVNode, createTextVNode, toDisplayString, withModifiers, withDirectives, vModelSelect, createCommentVNode, vModelText, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderAttr } from "vue/server-renderer";
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
  props: ["invoicedesign", "id"],
  setup(__props) {
    var _a, _b, _c, _d, _e;
    const props = __props;
    const form = useForm({
      footer_content: ((_a = props.invoicedesign) == null ? void 0 : _a.footer_content) ?? "",
      module: ((_b = props.invoicedesign) == null ? void 0 : _b.module) ?? "",
      headerPhoto: null,
      footerPhoto: null,
      headerPhotoPreview: ((_c = props.invoicedesign) == null ? void 0 : _c.header_photo_path) ?? null,
      footerPhotoPreview: ((_d = props.invoicedesign) == null ? void 0 : _d.footer_photo_path) ?? null,
      _method: ((_e = props.invoicedesign) == null ? void 0 : _e.id) ? "put" : "post"
    });
    const handlePhotoChange = (event, field) => {
      if (event.target.files.length > 0) {
        const file = event.target.files[0];
        form[field] = file;
        const reader = new FileReader();
        reader.onload = (e) => {
          form[`${field}Preview`] = e.target.result;
        };
        reader.readAsDataURL(file);
      }
    };
    const removePhoto = (field) => {
      form[field] = null;
      form[`${field}Preview`] = null;
      document.getElementById(field).value = "";
    };
    const submit = () => {
      const routeName = props.id ? route("backend.invoicedesign.update", props.id) : route("backend.invoicedesign.store");
      form.post(routeName, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: (response) => {
          if (!props.id) {
            form.reset();
            document.getElementById("headerPhoto").value = "";
            document.getElementById("footerPhoto").value = "";
          }
          displayResponse(response);
        },
        onError: (errors) => {
          displayWarning(errors);
        }
      });
    };
    const goToInvoiceDesignList = () => {
      router.visit(route("backend.invoicedesign.index"));
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md"${_scopeId}><div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md"${_scopeId}><div${_scopeId}><h1 class="p-4 text-xl font-bold dark:text-white"${_scopeId}>${ssrInterpolate(_ctx.$page.props.pageTitle)}</h1></div><div class="p-2 py-2 flex items-center space-x-2"${_scopeId}><div class="flex items-center space-x-3"${_scopeId}><button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2"${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"${_scopeId}></path></svg> Invoice Design List </button></div></div></div><form class="p-6"${_scopeId}><div class="grid grid-cols-1 gap-6 md:grid-cols-2"${_scopeId}><div class="col-span-3 md:col-span-3"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "module",
              value: "Module *"
            }, null, _parent2, _scopeId));
            _push2(`<select id="module" class="w-[20%] p-2 mt-1 text-sm border rounded-md shadow-sm border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:focus:ring-slate-500 dark:focus:border-slate-500"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).module) ? ssrLooseContain(unref(form).module, "") : ssrLooseEqual(unref(form).module, "")) ? " selected" : ""}${_scopeId}>Select Module</option><option value="opd"${ssrIncludeBooleanAttr(Array.isArray(unref(form).module) ? ssrLooseContain(unref(form).module, "opd") : ssrLooseEqual(unref(form).module, "opd")) ? " selected" : ""}${_scopeId}>OPD</option><option value="ipd"${ssrIncludeBooleanAttr(Array.isArray(unref(form).module) ? ssrLooseContain(unref(form).module, "ipd") : ssrLooseEqual(unref(form).module, "ipd")) ? " selected" : ""}${_scopeId}>IPD</option><option value="pathology"${ssrIncludeBooleanAttr(Array.isArray(unref(form).module) ? ssrLooseContain(unref(form).module, "pathology") : ssrLooseEqual(unref(form).module, "pathology")) ? " selected" : ""}${_scopeId}>Pathology</option><option value="radiology"${ssrIncludeBooleanAttr(Array.isArray(unref(form).module) ? ssrLooseContain(unref(form).module, "radiology") : ssrLooseEqual(unref(form).module, "radiology")) ? " selected" : ""}${_scopeId}>Radiology</option><option value="pharmacy"${ssrIncludeBooleanAttr(Array.isArray(unref(form).module) ? ssrLooseContain(unref(form).module, "pharmacy") : ssrLooseEqual(unref(form).module, "pharmacy")) ? " selected" : ""}${_scopeId}>Pharmacy</option><option value="appointment"${ssrIncludeBooleanAttr(Array.isArray(unref(form).module) ? ssrLooseContain(unref(form).module, "appointment") : ssrLooseEqual(unref(form).module, "appointment")) ? " selected" : ""}${_scopeId}>Appointment</option><option value="billing"${ssrIncludeBooleanAttr(Array.isArray(unref(form).module) ? ssrLooseContain(unref(form).module, "billing") : ssrLooseEqual(unref(form).module, "billing")) ? " selected" : ""}${_scopeId}>Billing</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-1",
              message: unref(form).errors.module
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1 md:col-span-2"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "headerPhoto",
              value: "Header Image"
            }, null, _parent2, _scopeId));
            if (unref(form).headerPhotoPreview) {
              _push2(`<div class="relative w-full mb-2"${_scopeId}><img${ssrRenderAttr("src", unref(form).headerPhotoPreview)} alt="Header preview" class="object-contain w-full h-32 border rounded-md"${_scopeId}><button type="button" class="absolute p-1 text-white bg-red-500 rounded-full -top-2 -right-2 hover:bg-red-600"${_scopeId}><svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"${_scopeId}><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"${_scopeId}></path></svg></button></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<input id="headerPhoto" type="file" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-gray-600 dark:file:text-gray-200"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-1",
              message: unref(form).errors.headerPhoto
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1 md:col-span-2"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "footer_content",
              value: "Footer Content *"
            }, null, _parent2, _scopeId));
            _push2(`<textarea id="footer_content" rows="4" class="w-full p-2 mt-1 text-sm border rounded-md shadow-sm border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:focus:ring-slate-500 dark:focus:border-slate-500" placeholder="Enter Footer Contents"${_scopeId}>${ssrInterpolate(unref(form).footer_content)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-1",
              message: unref(form).errors.footer_content
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1 md:col-span-2"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "footerPhoto",
              value: "Footer Image"
            }, null, _parent2, _scopeId));
            if (unref(form).footerPhotoPreview) {
              _push2(`<div class="relative w-full mb-2"${_scopeId}><img${ssrRenderAttr("src", unref(form).footerPhotoPreview)} alt="Footer preview" class="object-contain w-full h-32 border rounded-md"${_scopeId}><button type="button" class="absolute p-1 text-white bg-red-500 rounded-full -top-2 -right-2 hover:bg-red-600"${_scopeId}><svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"${_scopeId}><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"${_scopeId}></path></svg></button></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<input id="footerPhoto" type="file" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-gray-600 dark:file:text-gray-200"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-1",
              message: unref(form).errors.footerPhoto
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="flex items-center justify-end pt-6 space-x-3 border-t border-gray-200 dark:border-gray-700"${_scopeId}><button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-700"${_scopeId}> Cancel </button>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              type: "submit",
              class: ["px-4 py-2 text-sm", { "opacity-50 cursor-not-allowed": unref(form).processing }],
              disabled: unref(form).processing
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  if (unref(form).processing) {
                    _push3(`<span${_scopeId2}><svg class="w-5 h-5 mr-2 -ml-1 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"${_scopeId2}><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"${_scopeId2}></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"${_scopeId2}></path></svg> Processing... </span>`);
                  } else {
                    _push3(`<span${_scopeId2}>${ssrInterpolate(props.id ? "Update Design" : "Create Design")}</span>`);
                  }
                } else {
                  return [
                    unref(form).processing ? (openBlock(), createBlock("span", { key: 0 }, [
                      (openBlock(), createBlock("svg", {
                        class: "w-5 h-5 mr-2 -ml-1 animate-spin",
                        xmlns: "http://www.w3.org/2000/svg",
                        fill: "none",
                        viewBox: "0 0 24 24"
                      }, [
                        createVNode("circle", {
                          class: "opacity-25",
                          cx: "12",
                          cy: "12",
                          r: "10",
                          stroke: "currentColor",
                          "stroke-width": "4"
                        }),
                        createVNode("path", {
                          class: "opacity-75",
                          fill: "currentColor",
                          d: "M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        })
                      ])),
                      createTextVNode(" Processing... ")
                    ])) : (openBlock(), createBlock("span", { key: 1 }, toDisplayString(props.id ? "Update Design" : "Create Design"), 1))
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
                        onClick: goToInvoiceDesignList,
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
                        createTextVNode(" Invoice Design List ")
                      ])
                    ])
                  ])
                ]),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"]),
                  class: "p-6"
                }, [
                  createVNode("div", { class: "grid grid-cols-1 gap-6 md:grid-cols-2" }, [
                    createVNode("div", { class: "col-span-3 md:col-span-3" }, [
                      createVNode(_sfc_main$2, {
                        for: "module",
                        value: "Module *"
                      }),
                      withDirectives(createVNode("select", {
                        id: "module",
                        "onUpdate:modelValue": ($event) => unref(form).module = $event,
                        class: "w-[20%] p-2 mt-1 text-sm border rounded-md shadow-sm border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:focus:ring-slate-500 dark:focus:border-slate-500"
                      }, [
                        createVNode("option", { value: "" }, "Select Module"),
                        createVNode("option", { value: "opd" }, "OPD"),
                        createVNode("option", { value: "ipd" }, "IPD"),
                        createVNode("option", { value: "pathology" }, "Pathology"),
                        createVNode("option", { value: "radiology" }, "Radiology"),
                        createVNode("option", { value: "pharmacy" }, "Pharmacy"),
                        createVNode("option", { value: "appointment" }, "Appointment"),
                        createVNode("option", { value: "billing" }, "Billing")
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).module]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-1",
                        message: unref(form).errors.module
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1 md:col-span-2" }, [
                      createVNode(_sfc_main$2, {
                        for: "headerPhoto",
                        value: "Header Image"
                      }),
                      unref(form).headerPhotoPreview ? (openBlock(), createBlock("div", {
                        key: 0,
                        class: "relative w-full mb-2"
                      }, [
                        createVNode("img", {
                          src: unref(form).headerPhotoPreview,
                          alt: "Header preview",
                          class: "object-contain w-full h-32 border rounded-md"
                        }, null, 8, ["src"]),
                        createVNode("button", {
                          type: "button",
                          onClick: ($event) => removePhoto("headerPhoto"),
                          class: "absolute p-1 text-white bg-red-500 rounded-full -top-2 -right-2 hover:bg-red-600"
                        }, [
                          (openBlock(), createBlock("svg", {
                            xmlns: "http://www.w3.org/2000/svg",
                            class: "w-3 h-3",
                            viewBox: "0 0 20 20",
                            fill: "currentColor"
                          }, [
                            createVNode("path", {
                              "fill-rule": "evenodd",
                              d: "M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z",
                              "clip-rule": "evenodd"
                            })
                          ]))
                        ], 8, ["onClick"])
                      ])) : createCommentVNode("", true),
                      createVNode("input", {
                        id: "headerPhoto",
                        type: "file",
                        accept: "image/*",
                        class: "block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-gray-600 dark:file:text-gray-200",
                        onChange: (e) => handlePhotoChange(e, "headerPhoto")
                      }, null, 40, ["onChange"]),
                      createVNode(_sfc_main$3, {
                        class: "mt-1",
                        message: unref(form).errors.headerPhoto
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1 md:col-span-2" }, [
                      createVNode(_sfc_main$2, {
                        for: "footer_content",
                        value: "Footer Content *"
                      }),
                      withDirectives(createVNode("textarea", {
                        id: "footer_content",
                        "onUpdate:modelValue": ($event) => unref(form).footer_content = $event,
                        rows: "4",
                        class: "w-full p-2 mt-1 text-sm border rounded-md shadow-sm border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:focus:ring-slate-500 dark:focus:border-slate-500",
                        placeholder: "Enter Footer Contents"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).footer_content]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-1",
                        message: unref(form).errors.footer_content
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1 md:col-span-2" }, [
                      createVNode(_sfc_main$2, {
                        for: "footerPhoto",
                        value: "Footer Image"
                      }),
                      unref(form).footerPhotoPreview ? (openBlock(), createBlock("div", {
                        key: 0,
                        class: "relative w-full mb-2"
                      }, [
                        createVNode("img", {
                          src: unref(form).footerPhotoPreview,
                          alt: "Footer preview",
                          class: "object-contain w-full h-32 border rounded-md"
                        }, null, 8, ["src"]),
                        createVNode("button", {
                          type: "button",
                          onClick: ($event) => removePhoto("footerPhoto"),
                          class: "absolute p-1 text-white bg-red-500 rounded-full -top-2 -right-2 hover:bg-red-600"
                        }, [
                          (openBlock(), createBlock("svg", {
                            xmlns: "http://www.w3.org/2000/svg",
                            class: "w-3 h-3",
                            viewBox: "0 0 20 20",
                            fill: "currentColor"
                          }, [
                            createVNode("path", {
                              "fill-rule": "evenodd",
                              d: "M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z",
                              "clip-rule": "evenodd"
                            })
                          ]))
                        ], 8, ["onClick"])
                      ])) : createCommentVNode("", true),
                      createVNode("input", {
                        id: "footerPhoto",
                        type: "file",
                        accept: "image/*",
                        class: "block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-gray-600 dark:file:text-gray-200",
                        onChange: (e) => handlePhotoChange(e, "footerPhoto")
                      }, null, 40, ["onChange"]),
                      createVNode(_sfc_main$3, {
                        class: "mt-1",
                        message: unref(form).errors.footerPhoto
                      }, null, 8, ["message"])
                    ])
                  ]),
                  createVNode("div", { class: "flex items-center justify-end pt-6 space-x-3 border-t border-gray-200 dark:border-gray-700" }, [
                    createVNode("button", {
                      type: "button",
                      onClick: ($event) => unref(router).visit(_ctx.route("backend.invoicedesign.index")),
                      class: "px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-700"
                    }, " Cancel ", 8, ["onClick"]),
                    createVNode(_sfc_main$4, {
                      type: "submit",
                      class: ["px-4 py-2 text-sm", { "opacity-50 cursor-not-allowed": unref(form).processing }],
                      disabled: unref(form).processing
                    }, {
                      default: withCtx(() => [
                        unref(form).processing ? (openBlock(), createBlock("span", { key: 0 }, [
                          (openBlock(), createBlock("svg", {
                            class: "w-5 h-5 mr-2 -ml-1 animate-spin",
                            xmlns: "http://www.w3.org/2000/svg",
                            fill: "none",
                            viewBox: "0 0 24 24"
                          }, [
                            createVNode("circle", {
                              class: "opacity-25",
                              cx: "12",
                              cy: "12",
                              r: "10",
                              stroke: "currentColor",
                              "stroke-width": "4"
                            }),
                            createVNode("path", {
                              class: "opacity-75",
                              fill: "currentColor",
                              d: "M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            })
                          ])),
                          createTextVNode(" Processing... ")
                        ])) : (openBlock(), createBlock("span", { key: 1 }, toDisplayString(props.id ? "Update Design" : "Create Design"), 1))
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/InvoiceDesign/Form.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
