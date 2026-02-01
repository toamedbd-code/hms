import { onMounted, withCtx, unref, createTextVNode, toDisplayString, createVNode, openBlock, createBlock, withModifiers, withDirectives, Fragment, renderList, vModelSelect, vModelText, createCommentVNode, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderList, ssrRenderAttr } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import { useForm, router } from "@inertiajs/vue3";
import { _ as _sfc_main$3 } from "./InputError-6aeb8d97.mjs";
import { _ as _sfc_main$2 } from "./InputLabel-70ca52d1.mjs";
import { _ as _sfc_main$4 } from "./PrimaryButton-b82fb16e.mjs";
import "./AlertMessage-0f422981.mjs";
import { d as displayResponse, a as displayWarning } from "./responseMessage-d505224b.mjs";
import { _ as _export_sfc } from "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "mitt";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
import "toastr";
import "sweetalert2";
const Form_vue_vue_type_style_index_0_scoped_d10d3d73_lang = "";
const _sfc_main = {
  __name: "Form",
  __ssrInlineRender: true,
  props: ["expense", "expenseHeads", "id"],
  setup(__props) {
    var _a, _b, _c, _d, _e, _f, _g, _h, _i;
    const props = __props;
    const form = useForm({
      expense_header_id: ((_a = props.expense) == null ? void 0 : _a.expense_header_id) ?? "",
      bill_number: ((_b = props.expense) == null ? void 0 : _b.bill_number) ?? "",
      case_id: ((_c = props.expense) == null ? void 0 : _c.case_id) ?? "",
      name: ((_d = props.expense) == null ? void 0 : _d.name) ?? "",
      document: null,
      documentPreview: ((_e = props.expense) == null ? void 0 : _e.document) ?? null,
      description: ((_f = props.expense) == null ? void 0 : _f.description) ?? "",
      amount: ((_g = props.expense) == null ? void 0 : _g.amount) ?? "",
      date: ((_h = props.expense) == null ? void 0 : _h.date) ? new Date(props.expense.date).toISOString().split("T")[0] : (/* @__PURE__ */ new Date()).toISOString().split("T")[0],
      _method: ((_i = props.expense) == null ? void 0 : _i.id) ? "put" : "post"
    });
    const handleDocumentChange = (event) => {
      const file = event.target.files[0];
      if (file) {
        form.document = file;
        const url = URL.createObjectURL(file);
        form.documentPreview = url;
      }
    };
    const removeDocument = () => {
      form.document = null;
      form.documentPreview = null;
      document.getElementById("document").value = "";
    };
    const submit = () => {
      const routeName = props.id ? route("backend.expense.update", props.id) : route("backend.expense.store");
      form.transform((data) => ({
        ...data,
        remember: "",
        isDirty: false
      })).post(routeName, {
        onSuccess: (response) => {
          if (!props.id) {
            form.reset();
            form.date = (/* @__PURE__ */ new Date()).toISOString().split("T")[0];
          }
          displayResponse(response);
        },
        onError: (errorObject) => {
          displayWarning(errorObject);
        }
      });
    };
    const formatAmount = (event) => {
      let value = event.target.value.replace(/[^\d.]/g, "");
      if (value) {
        const parts = value.split(".");
        if (parts.length > 2) {
          value = parts[0] + "." + parts[1];
        }
        if (parts[1] && parts[1].length > 2) {
          value = parts[0] + "." + parts[1].substring(0, 2);
        }
      }
      form.amount = value;
    };
    onMounted(() => {
      var _a2;
      if (!((_a2 = props.expense) == null ? void 0 : _a2.id)) {
        form.date = (/* @__PURE__ */ new Date()).toISOString().split("T")[0];
      }
    });
    const goToExpenseList = () => {
      router.visit(route("backend.expense.index"));
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          var _a2, _b2;
          if (_push2) {
            _push2(`<div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md" data-v-d10d3d73${_scopeId}><div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md" data-v-d10d3d73${_scopeId}><div data-v-d10d3d73${_scopeId}><h1 class="p-4 text-xl font-bold dark:text-white" data-v-d10d3d73${_scopeId}>${ssrInterpolate(_ctx.$page.props.pageTitle)}</h1></div><div class="p-2 py-2 flex items-center space-x-2" data-v-d10d3d73${_scopeId}><div class="flex items-center space-x-3" data-v-d10d3d73${_scopeId}><button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2" data-v-d10d3d73${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" data-v-d10d3d73${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" data-v-d10d3d73${_scopeId}></path></svg> Expense List </button></div></div></div><form class="p-4" data-v-d10d3d73${_scopeId}><div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4 mb-4" data-v-d10d3d73${_scopeId}><div class="col-span-1" data-v-d10d3d73${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "expense_header_id",
              value: "Expense Head *"
            }, null, _parent2, _scopeId));
            _push2(`<select id="expense_header_id" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-d10d3d73${_scopeId}><option value="" data-v-d10d3d73${ssrIncludeBooleanAttr(Array.isArray(unref(form).expense_header_id) ? ssrLooseContain(unref(form).expense_header_id, "") : ssrLooseEqual(unref(form).expense_header_id, "")) ? " selected" : ""}${_scopeId}>Select Expense Head</option><!--[-->`);
            ssrRenderList(__props.expenseHeads, (head) => {
              _push2(`<option${ssrRenderAttr("value", head.id)} data-v-d10d3d73${ssrIncludeBooleanAttr(Array.isArray(unref(form).expense_header_id) ? ssrLooseContain(unref(form).expense_header_id, head.id) : ssrLooseEqual(unref(form).expense_header_id, head.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(head.name)}</option>`);
            });
            _push2(`<!--]--></select>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.expense_header_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1" data-v-d10d3d73${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "name",
              value: "Name *"
            }, null, _parent2, _scopeId));
            _push2(`<input id="name"${ssrRenderAttr("value", unref(form).name)} type="text" placeholder="Expense Name" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-d10d3d73${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.name
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1" data-v-d10d3d73${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "bill_number",
              value: "Bill Number"
            }, null, _parent2, _scopeId));
            _push2(`<input id="bill_number"${ssrRenderAttr("value", unref(form).bill_number)} type="text" placeholder="Bill Number" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-d10d3d73${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.bill_number
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1" data-v-d10d3d73${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "date",
              value: "Date *"
            }, null, _parent2, _scopeId));
            _push2(`<input id="date"${ssrRenderAttr("value", unref(form).date)} type="date"${ssrRenderAttr("max", (/* @__PURE__ */ new Date()).toISOString().split("T")[0])} class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-d10d3d73${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.date
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-4" data-v-d10d3d73${_scopeId}><div class="col-span-1" data-v-d10d3d73${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "amount",
              value: "Amount (TK.) *"
            }, null, _parent2, _scopeId));
            _push2(`<input id="amount"${ssrRenderAttr("value", unref(form).amount)} type="text" placeholder="0.00" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-d10d3d73${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.amount
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1" data-v-d10d3d73${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "document",
              value: "Attach Document"
            }, null, _parent2, _scopeId));
            if (unref(form).documentPreview) {
              _push2(`<div class="mb-2" data-v-d10d3d73${_scopeId}><div class="flex items-center justify-between p-2 bg-gray-100 dark:bg-gray-800 rounded" data-v-d10d3d73${_scopeId}><span class="text-sm text-gray-600 dark:text-gray-400" data-v-d10d3d73${_scopeId}>${ssrInterpolate(((_a2 = unref(form).document) == null ? void 0 : _a2.name) || "Current Document")}</span><div class="flex gap-2" data-v-d10d3d73${_scopeId}>`);
              if (!unref(form).document && unref(form).documentPreview) {
                _push2(`<a${ssrRenderAttr("href", unref(form).documentPreview)} target="_blank" class="text-blue-600 hover:underline text-sm" data-v-d10d3d73${_scopeId}>View</a>`);
              } else {
                _push2(`<!---->`);
              }
              _push2(`<button type="button" class="text-red-600 hover:underline text-sm" data-v-d10d3d73${_scopeId}>Remove</button></div></div></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<div class="relative" data-v-d10d3d73${_scopeId}><input id="document" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-d10d3d73${_scopeId}><div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none" data-v-d10d3d73${_scopeId}><svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" data-v-d10d3d73${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" data-v-d10d3d73${_scopeId}></path></svg></div></div><p class="mt-1 text-xs text-gray-500 dark:text-gray-400" data-v-d10d3d73${_scopeId}> Supported formats: PDF, DOC, DOCX, JPG, JPEG, PNG (Max: 10MB) </p>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.document
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="grid grid-cols-1 mb-4" data-v-d10d3d73${_scopeId}><div class="col-span-1" data-v-d10d3d73${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "description",
              value: "Description"
            }, null, _parent2, _scopeId));
            _push2(`<textarea id="description" rows="4" placeholder="Enter description..." class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-d10d3d73${_scopeId}>${ssrInterpolate(unref(form).description)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.description
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="flex items-center justify-end mt-6" data-v-d10d3d73${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              type: "submit",
              class: ["ms-4", { "opacity-25": unref(form).processing }],
              disabled: unref(form).processing
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`${ssrInterpolate(unref(form).processing ? "Processing..." : props.id ?? false ? "Update Expense" : "Save Expense")}`);
                } else {
                  return [
                    createTextVNode(toDisplayString(unref(form).processing ? "Processing..." : props.id ?? false ? "Update Expense" : "Save Expense"), 1)
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
                        onClick: goToExpenseList,
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
                        createTextVNode(" Expense List ")
                      ])
                    ])
                  ])
                ]),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"]),
                  class: "p-4"
                }, [
                  createVNode("div", { class: "grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4 mb-4" }, [
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "expense_header_id",
                        value: "Expense Head *"
                      }),
                      withDirectives(createVNode("select", {
                        id: "expense_header_id",
                        "onUpdate:modelValue": ($event) => unref(form).expense_header_id = $event,
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                      }, [
                        createVNode("option", { value: "" }, "Select Expense Head"),
                        (openBlock(true), createBlock(Fragment, null, renderList(__props.expenseHeads, (head) => {
                          return openBlock(), createBlock("option", {
                            key: head.id,
                            value: head.id
                          }, toDisplayString(head.name), 9, ["value"]);
                        }), 128))
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).expense_header_id]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.expense_header_id
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "name",
                        value: "Name *"
                      }),
                      withDirectives(createVNode("input", {
                        id: "name",
                        "onUpdate:modelValue": ($event) => unref(form).name = $event,
                        type: "text",
                        placeholder: "Expense Name",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
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
                        for: "bill_number",
                        value: "Bill Number"
                      }),
                      withDirectives(createVNode("input", {
                        id: "bill_number",
                        "onUpdate:modelValue": ($event) => unref(form).bill_number = $event,
                        type: "text",
                        placeholder: "Bill Number",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).bill_number]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.bill_number
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "date",
                        value: "Date *"
                      }),
                      withDirectives(createVNode("input", {
                        id: "date",
                        "onUpdate:modelValue": ($event) => unref(form).date = $event,
                        type: "date",
                        max: (/* @__PURE__ */ new Date()).toISOString().split("T")[0],
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                      }, null, 8, ["onUpdate:modelValue", "max"]), [
                        [vModelText, unref(form).date]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.date
                      }, null, 8, ["message"])
                    ])
                  ]),
                  createVNode("div", { class: "grid grid-cols-1 gap-4 sm:grid-cols-2 mb-4" }, [
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "amount",
                        value: "Amount (TK.) *"
                      }),
                      withDirectives(createVNode("input", {
                        id: "amount",
                        "onUpdate:modelValue": ($event) => unref(form).amount = $event,
                        onInput: formatAmount,
                        type: "text",
                        placeholder: "0.00",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                      }, null, 40, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).amount]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.amount
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "document",
                        value: "Attach Document"
                      }),
                      unref(form).documentPreview ? (openBlock(), createBlock("div", {
                        key: 0,
                        class: "mb-2"
                      }, [
                        createVNode("div", { class: "flex items-center justify-between p-2 bg-gray-100 dark:bg-gray-800 rounded" }, [
                          createVNode("span", { class: "text-sm text-gray-600 dark:text-gray-400" }, toDisplayString(((_b2 = unref(form).document) == null ? void 0 : _b2.name) || "Current Document"), 1),
                          createVNode("div", { class: "flex gap-2" }, [
                            !unref(form).document && unref(form).documentPreview ? (openBlock(), createBlock("a", {
                              key: 0,
                              href: unref(form).documentPreview,
                              target: "_blank",
                              class: "text-blue-600 hover:underline text-sm"
                            }, "View", 8, ["href"])) : createCommentVNode("", true),
                            createVNode("button", {
                              type: "button",
                              onClick: removeDocument,
                              class: "text-red-600 hover:underline text-sm"
                            }, "Remove")
                          ])
                        ])
                      ])) : createCommentVNode("", true),
                      createVNode("div", { class: "relative" }, [
                        createVNode("input", {
                          id: "document",
                          type: "file",
                          accept: ".pdf,.doc,.docx,.jpg,.jpeg,.png",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                          onChange: handleDocumentChange
                        }, null, 32),
                        createVNode("div", { class: "absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none" }, [
                          (openBlock(), createBlock("svg", {
                            class: "w-5 h-5 text-gray-400",
                            fill: "none",
                            stroke: "currentColor",
                            viewBox: "0 0 24 24"
                          }, [
                            createVNode("path", {
                              "stroke-linecap": "round",
                              "stroke-linejoin": "round",
                              "stroke-width": "2",
                              d: "M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                            })
                          ]))
                        ])
                      ]),
                      createVNode("p", { class: "mt-1 text-xs text-gray-500 dark:text-gray-400" }, " Supported formats: PDF, DOC, DOCX, JPG, JPEG, PNG (Max: 10MB) "),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.document
                      }, null, 8, ["message"])
                    ])
                  ]),
                  createVNode("div", { class: "grid grid-cols-1 mb-4" }, [
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "description",
                        value: "Description"
                      }),
                      withDirectives(createVNode("textarea", {
                        id: "description",
                        "onUpdate:modelValue": ($event) => unref(form).description = $event,
                        rows: "4",
                        placeholder: "Enter description...",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).description]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.description
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
                        createTextVNode(toDisplayString(unref(form).processing ? "Processing..." : props.id ?? false ? "Update Expense" : "Save Expense"), 1)
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/Expense/Form.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const Form = /* @__PURE__ */ _export_sfc(_sfc_main, [["__scopeId", "data-v-d10d3d73"]]);
export {
  Form as default
};
