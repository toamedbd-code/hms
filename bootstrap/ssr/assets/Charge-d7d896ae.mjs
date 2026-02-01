import { ref, withCtx, createVNode, openBlock, createBlock, toDisplayString, createCommentVNode, Fragment, renderList, withModifiers, createTextVNode, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrRenderList, ssrRenderStyle } from "vue/server-renderer";
import { router } from "@inertiajs/vue3";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import "./responseMessage-d505224b.mjs";
import "mitt";
import "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
import "toastr";
import "sweetalert2";
const _sfc_main = {
  __name: "Charge",
  __ssrInlineRender: true,
  props: {
    importErrors: Array,
    success: String,
    errors: Object
  },
  setup(__props) {
    const csvFile = ref(null);
    const submitForm = () => {
      if (!csvFile.value) {
        alert("Please select a CSV file first");
        return;
      }
      const formData = new FormData();
      formData.append("csv_file", csvFile.value);
      router.post(route("backend.charges.import.process"), formData, {
        preserveScroll: true,
        onSuccess: (response) => {
          csvFile.value = null;
          const fileInput = document.getElementById("csv_file");
          if (fileInput) {
            fileInput.value = "";
          }
        },
        onError: (errors) => {
          console.error("Import errors:", errors);
        }
      });
    };
    const downloadSample = () => {
      const link = document.createElement("a");
      link.href = route("backend.charges.import.sample");
      link.download = "charge_import_sample.csv";
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    };
    const handleFileChange = (event) => {
      csvFile.value = event.target.files[0];
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="w-full p-2 duration-1000 ease-in-out bg-white rounded-md dark:bg-slate-900"${_scopeId}><div class="flex mb-2 items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md dark:bg-gray-800 dark:text-gray-200 shadow-gray-800/50"${_scopeId}><div${_scopeId}><h1 class="p-4 text-xl font-bold dark:text-white"${_scopeId}>Bulk Import Charges</h1></div></div><div class="w-full p-4 bg-white rounded-md dark:bg-slate-800 dark:text-gray-200 shadow-gray-800/50"${_scopeId}>`);
            if (__props.success) {
              _push2(`<div class="p-4 mb-4 text-green-800 bg-green-100 rounded-md dark:bg-green-900 dark:text-green-200"${_scopeId}>${ssrInterpolate(__props.success)}</div>`);
            } else {
              _push2(`<!---->`);
            }
            if (__props.importErrors && __props.importErrors.length) {
              _push2(`<div class="p-4 mb-4 text-yellow-800 bg-yellow-100 rounded-md dark:bg-yellow-900 dark:text-yellow-200"${_scopeId}><h5 class="font-bold"${_scopeId}>Import completed with errors:</h5><ul class="mt-2 ml-4 list-disc"${_scopeId}><!--[-->`);
              ssrRenderList(__props.importErrors, (error) => {
                _push2(`<li${_scopeId}>${ssrInterpolate(error)}</li>`);
              });
              _push2(`<!--]--></ul></div>`);
            } else {
              _push2(`<!---->`);
            }
            if (__props.errors && Object.keys(__props.errors).length) {
              _push2(`<div class="p-4 mb-4 text-red-800 bg-red-100 rounded-md dark:bg-red-900 dark:text-red-200"${_scopeId}><h5 class="font-bold"${_scopeId}>Validation Errors:</h5><ul class="mt-2 ml-4 list-disc"${_scopeId}><!--[-->`);
              ssrRenderList(__props.errors, (error, field) => {
                _push2(`<li${_scopeId}>${ssrInterpolate(error[0])}</li>`);
              });
              _push2(`<!--]--></ul></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<form class="space-y-4"${_scopeId}><div${_scopeId}><label for="csv_file" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"${_scopeId}>CSV File</label><input id="csv_file" type="file" accept=".csv" required class="block w-full p-2 text-sm rounded-md border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><p class="mt-1 text-sm text-gray-500 dark:text-gray-400"${_scopeId}>Please upload a CSV file with the correct format.</p></div><div class="flex justify-end space-x-2"${_scopeId}><button type="button" class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-200 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"${_scopeId}></path></svg> Download Sample CSV </button><button type="submit" class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out" style="${ssrRenderStyle({ "background": "linear-gradient(to right, #3b82f6, #60a5fa)" })}"${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"${_scopeId}></path></svg> Import Data </button></div></form><div class="mt-6 p-4 bg-gray-50 rounded-md dark:bg-gray-700"${_scopeId}><h5 class="font-bold text-gray-700 dark:text-gray-200 mb-2"${_scopeId}>CSV Format Requirements:</h5><ul class="text-sm text-gray-600 dark:text-gray-300 space-y-1"${_scopeId}><li${_scopeId}>File must be in CSV format with the following columns in order:</li><ul class="ml-4 list-disc space-y-1"${_scopeId}><li${_scopeId}>charge_type_name (required)</li><li${_scopeId}>charge_type_modules (required, comma-separated)</li><li${_scopeId}>charge_category_name (required)</li><li${_scopeId}>charge_category_description (required)</li><li${_scopeId}>charge_unit_type_name (required)</li><li${_scopeId}>charge_tax_category_name (required)</li><li${_scopeId}>tax_category_percentage (required, numeric)</li><li${_scopeId}>charge_name (required)</li><li${_scopeId}>charge_tax (optional, numeric)</li><li${_scopeId}>charge_standard_charge (optional, numeric)</li><li${_scopeId}>charge_description (optional)</li><li${_scopeId}>status (required: Active, Inactive, or Deleted)</li></ul><li${_scopeId}>The first row must contain the header names exactly as shown above</li><li${_scopeId}>Use the &quot;Download Sample CSV&quot; button to get a properly formatted file</li></ul></div></div></div>`);
          } else {
            return [
              createVNode("div", { class: "w-full p-2 duration-1000 ease-in-out bg-white rounded-md dark:bg-slate-900" }, [
                createVNode("div", { class: "flex mb-2 items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md dark:bg-gray-800 dark:text-gray-200 shadow-gray-800/50" }, [
                  createVNode("div", null, [
                    createVNode("h1", { class: "p-4 text-xl font-bold dark:text-white" }, "Bulk Import Charges")
                  ])
                ]),
                createVNode("div", { class: "w-full p-4 bg-white rounded-md dark:bg-slate-800 dark:text-gray-200 shadow-gray-800/50" }, [
                  __props.success ? (openBlock(), createBlock("div", {
                    key: 0,
                    class: "p-4 mb-4 text-green-800 bg-green-100 rounded-md dark:bg-green-900 dark:text-green-200"
                  }, toDisplayString(__props.success), 1)) : createCommentVNode("", true),
                  __props.importErrors && __props.importErrors.length ? (openBlock(), createBlock("div", {
                    key: 1,
                    class: "p-4 mb-4 text-yellow-800 bg-yellow-100 rounded-md dark:bg-yellow-900 dark:text-yellow-200"
                  }, [
                    createVNode("h5", { class: "font-bold" }, "Import completed with errors:"),
                    createVNode("ul", { class: "mt-2 ml-4 list-disc" }, [
                      (openBlock(true), createBlock(Fragment, null, renderList(__props.importErrors, (error) => {
                        return openBlock(), createBlock("li", { key: error }, toDisplayString(error), 1);
                      }), 128))
                    ])
                  ])) : createCommentVNode("", true),
                  __props.errors && Object.keys(__props.errors).length ? (openBlock(), createBlock("div", {
                    key: 2,
                    class: "p-4 mb-4 text-red-800 bg-red-100 rounded-md dark:bg-red-900 dark:text-red-200"
                  }, [
                    createVNode("h5", { class: "font-bold" }, "Validation Errors:"),
                    createVNode("ul", { class: "mt-2 ml-4 list-disc" }, [
                      (openBlock(true), createBlock(Fragment, null, renderList(__props.errors, (error, field) => {
                        return openBlock(), createBlock("li", { key: field }, toDisplayString(error[0]), 1);
                      }), 128))
                    ])
                  ])) : createCommentVNode("", true),
                  createVNode("form", {
                    onSubmit: withModifiers(submitForm, ["prevent"]),
                    class: "space-y-4"
                  }, [
                    createVNode("div", null, [
                      createVNode("label", {
                        for: "csv_file",
                        class: "block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                      }, "CSV File"),
                      createVNode("input", {
                        id: "csv_file",
                        type: "file",
                        accept: ".csv",
                        onChange: handleFileChange,
                        required: "",
                        class: "block w-full p-2 text-sm rounded-md border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                      }, null, 32),
                      createVNode("p", { class: "mt-1 text-sm text-gray-500 dark:text-gray-400" }, "Please upload a CSV file with the correct format.")
                    ]),
                    createVNode("div", { class: "flex justify-end space-x-2" }, [
                      createVNode("button", {
                        type: "button",
                        onClick: downloadSample,
                        class: "inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-200 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
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
                            d: "M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                          })
                        ])),
                        createTextVNode(" Download Sample CSV ")
                      ]),
                      createVNode("button", {
                        type: "submit",
                        class: "inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out",
                        style: { "background": "linear-gradient(to right, #3b82f6, #60a5fa)" },
                        onMouseover: ($event) => $event.target.style.background = "linear-gradient(to right, #2563eb, #3b82f6)",
                        onMouseout: ($event) => $event.target.style.background = "linear-gradient(to right, #3b82f6, #60a5fa)"
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
                            d: "M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"
                          })
                        ])),
                        createTextVNode(" Import Data ")
                      ], 40, ["onMouseover", "onMouseout"])
                    ])
                  ], 32),
                  createVNode("div", { class: "mt-6 p-4 bg-gray-50 rounded-md dark:bg-gray-700" }, [
                    createVNode("h5", { class: "font-bold text-gray-700 dark:text-gray-200 mb-2" }, "CSV Format Requirements:"),
                    createVNode("ul", { class: "text-sm text-gray-600 dark:text-gray-300 space-y-1" }, [
                      createVNode("li", null, "File must be in CSV format with the following columns in order:"),
                      createVNode("ul", { class: "ml-4 list-disc space-y-1" }, [
                        createVNode("li", null, "charge_type_name (required)"),
                        createVNode("li", null, "charge_type_modules (required, comma-separated)"),
                        createVNode("li", null, "charge_category_name (required)"),
                        createVNode("li", null, "charge_category_description (required)"),
                        createVNode("li", null, "charge_unit_type_name (required)"),
                        createVNode("li", null, "charge_tax_category_name (required)"),
                        createVNode("li", null, "tax_category_percentage (required, numeric)"),
                        createVNode("li", null, "charge_name (required)"),
                        createVNode("li", null, "charge_tax (optional, numeric)"),
                        createVNode("li", null, "charge_standard_charge (optional, numeric)"),
                        createVNode("li", null, "charge_description (optional)"),
                        createVNode("li", null, "status (required: Active, Inactive, or Deleted)")
                      ]),
                      createVNode("li", null, "The first row must contain the header names exactly as shown above"),
                      createVNode("li", null, 'Use the "Download Sample CSV" button to get a properly formatted file')
                    ])
                  ])
                ])
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/BulkImport/Charge.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
