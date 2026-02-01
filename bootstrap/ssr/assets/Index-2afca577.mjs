import { resolveComponent, withCtx, unref, createTextVNode, createVNode, toDisplayString, withModifiers, withDirectives, vModelText, openBlock, createBlock, Fragment, renderList, vModelSelect, createCommentVNode, vModelCheckbox, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrRenderAttr, ssrRenderList, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderStyle } from "vue/server-renderer";
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
  __name: "Index",
  __ssrInlineRender: true,
  props: ["fillableAttributes"],
  setup(__props) {
    const form = useForm({
      modelName: "",
      fields: [
        {
          field_name: "",
          data_type: "",
          default_value: "",
          is_nullable: "",
          is_fillable: "",
          is_relation: "",
          is_hidden: "",
          enum_values: [],
          relation_table: ""
        }
      ],
      formField: [],
      relationField: [],
      _method: "post"
    });
    const submit = () => {
      const routeName = route("backend.moduleMaker");
      form.transform((data) => ({
        ...data,
        remember: "",
        isDirty: false
      })).post(routeName, {
        onSuccess: (response) => {
          displayResponse(response);
        },
        onError: (errorObject) => {
          displayWarning(errorObject);
        }
      });
    };
    const addMoreField = () => {
      form.fields.push({
        field_name: "",
        data_type: "",
        data_length: "",
        default_value: "",
        is_nullable: "",
        is_relation: "",
        is_fillable: "",
        is_hidden: "",
        enum_values: [],
        relation_table: ""
      });
    };
    const onDataTypeChange = (index) => {
      const fieldType = form.fields[index].data_type;
      if (fieldType != "Enum")
        form.fields[index].enum_values = [];
      switch (fieldType) {
        case "Enum":
          form.fields[index].data_length = 255;
          form.fields[index].enum_values.push({ value: "" });
          break;
        case "String":
          form.fields[index].data_length = 255;
          break;
        case "Integer":
          form.fields[index].data_length = 11;
          break;
        case "TinyInteger":
          form.fields[index].data_length = 4;
          break;
        case "BigInteger":
          form.fields[index].data_length = 20;
          break;
        case "Decimal":
          form.fields[index].data_length = "11,2";
          break;
        case "Boolean":
          form.fields[index].data_length = 1;
          break;
        case "Image":
          form.fields[index].data_length = 255;
          break;
        case "File":
          form.fields[index].data_length = 255;
          break;
        case "Date":
          form.fields[index].data_length = "";
          break;
        case "Time":
          form.fields[index].data_length = "";
          break;
        case "DateTime":
          form.fields[index].data_length = "";
          break;
        default:
          form.fields[index].data_length = 255;
          break;
      }
    };
    const onModelChange = (index) => {
      const modelName = form.fields[index].relation_table;
      router.visit(
        route("backend.moduleMaker", { model: modelName }),
        {
          only: ["fillableAttributes"],
          preserveState: true
        }
      );
    };
    const addEnumValue = (fieldIndex) => {
      form.fields[fieldIndex].enum_values.push({ value: "" });
    };
    const removeEnumValue = (fieldIndex, index) => {
      form.fields[fieldIndex].enum_values.splice(index, 1);
    };
    const formattedModelName = () => {
      let modelName = form.modelName.trim();
      modelName = modelName.split(" ").map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join("");
      form.modelName = modelName;
    };
    const formattedFieldName = (index) => {
      let field_name = form.fields[index].field_name;
      field_name = field_name.split(" ").map((word) => word.charAt(0).toLowerCase() + word.slice(1)).join("_");
      form.fields[index].field_name = field_name.toLowerCase();
    };
    return (_ctx, _push, _parent, _attrs) => {
      const _component_FeatherIcon = resolveComponent("FeatherIcon");
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="w-full mt-3 transition duration-1000 ease-in-out transform bg-white border border-gray-700 rounded-md shadow-lg shadow-gray-800/50 dark:bg-slate-900"${_scopeId}><div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md shadow-md dark:bg-gray-800 dark:text-gray-200 shadow-gray-800/50"${_scopeId}><div${_scopeId}><h1 class="px-4 py-3 font-bold text-md dark:text-white"${_scopeId}>${ssrInterpolate(_ctx.$page.props.pageTitle)}</h1></div><div class="px-4 py-2"${_scopeId}></div></div><form class="p-4"${_scopeId}>`);
            _push2(ssrRenderComponent(AlertMessage, null, null, _parent2, _scopeId));
            _push2(`<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4"${_scopeId}><div class="col-span-1 md:col-span-2"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "modelName",
              value: "Model Name"
            }, null, _parent2, _scopeId));
            _push2(`<input id="modelName" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).modelName)} type="text" placeholder="Model Name"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.modelName
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="w-full mt-4"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "Database Fields",
              value: "Database Fields"
            }, null, _parent2, _scopeId));
            _push2(`<table class="w-full text-xs gray-700 text- dark:text-gray-200"${_scopeId}><thead${_scopeId}><tr class="text-gray-300 bg-slate-900"${_scopeId}><th class="p-2 border border-slate-600"${_scopeId}>Sl/No</th><th class="p-2 border border-slate-600"${_scopeId}>Field Name</th><th class="p-2 border border-slate-600"${_scopeId}>Data Type</th><th class="p-2 border border-slate-600"${_scopeId}>Data Length</th><th class="p-2 border border-slate-600"${_scopeId}>Default Value</th><th class="p-2 border border-slate-600"${_scopeId}>isNullable</th><th class="p-2 border border-slate-600"${_scopeId}>isRelation</th><th class="p-2 border border-slate-600"${_scopeId}>Action</th></tr></thead><tbody${_scopeId}><!--[-->`);
            ssrRenderList(unref(form).fields, (field, fieldIndex) => {
              _push2(`<tr${_scopeId}><td class="text-center border border-slate-600"${_scopeId}>${ssrInterpolate(fieldIndex + 1)}</td><td class="p-1 border border-slate-600"${_scopeId}><input type="text" placeholder="Database Field Name"${ssrRenderAttr("value", field.field_name)} class="block w-full px-2 py-1 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
              _push2(ssrRenderComponent(_sfc_main$3, {
                class: "mt-2",
                message: unref(form).errors["fields." + fieldIndex + ".field_name"]
              }, null, _parent2, _scopeId));
              _push2(`</td><td class="p-1 border border-slate-600"${_scopeId}><select class="block w-full px-2 py-1 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(field.data_type) ? ssrLooseContain(field.data_type, "") : ssrLooseEqual(field.data_type, "")) ? " selected" : ""}${_scopeId}>-- Select A Data Type --</option><!--[-->`);
              ssrRenderList(_ctx.$page.props.dataTypes, (dataType, dataIndex) => {
                _push2(`<option${ssrRenderAttr("value", dataType)}${ssrIncludeBooleanAttr(Array.isArray(field.data_type) ? ssrLooseContain(field.data_type, dataType) : ssrLooseEqual(field.data_type, dataType)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(dataType)}</option>`);
              });
              _push2(`<!--]--></select>`);
              _push2(ssrRenderComponent(_sfc_main$3, {
                class: "mt-2",
                message: unref(form).errors["fields." + fieldIndex + ".data_type"]
              }, null, _parent2, _scopeId));
              if (field.data_type === "Enum") {
                _push2(`<!--[--><!--[-->`);
                ssrRenderList(unref(form).fields[fieldIndex].enum_values, (enumValue, enumIndex) => {
                  _push2(`<!--[--><input type="text" placeholder="Add Enum Value"${ssrRenderAttr("value", enumValue.value)} class="block w-full px-2 py-1 mt-1 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><button type="button"${_scopeId}>`);
                  _push2(ssrRenderComponent(_component_FeatherIcon, {
                    name: "trash-2",
                    size: "1px",
                    class: "text-red-500"
                  }, null, _parent2, _scopeId));
                  _push2(`<path d="M21 4H8l-7 8 7 8h13a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z"${_scopeId}></path><line x1="18" x2="12" y1="9" y2="15"${_scopeId}></line><line x1="12" x2="18" y1="9" y2="15"${_scopeId}></line></button><!--]-->`);
                });
                _push2(`<!--]--><button type="button" class="px-2 py-1 font-bold text-gray-200 rounded"${_scopeId}>`);
                _push2(ssrRenderComponent(_component_FeatherIcon, {
                  name: "plus",
                  size: "1px",
                  class: "text-green-500"
                }, null, _parent2, _scopeId));
                _push2(`</button><!--]-->`);
              } else {
                _push2(`<!---->`);
              }
              _push2(`</td><td class="px-2 py-1 border border-slate-600 max-w-16"${_scopeId}><input type="text" step="0.01" placeholder="Data Length"${ssrRenderAttr("value", field.data_length)} min="1" class="block w-full px-2 py-1 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
              _push2(ssrRenderComponent(_sfc_main$3, {
                class: "mt-2",
                message: unref(form).errors["fields." + fieldIndex + ".data_length"]
              }, null, _parent2, _scopeId));
              _push2(`</td><td class="px-2 py-1 border border-slate-600"${_scopeId}><input type="text" placeholder="Default Value"${ssrRenderAttr("value", field.default_value)} class="block w-full px-2 py-1 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
              _push2(ssrRenderComponent(_sfc_main$3, {
                class: "mt-2",
                message: unref(form).errors["fields." + fieldIndex + ".default_value"]
              }, null, _parent2, _scopeId));
              _push2(`</td><td class="px-2 py-1 border border-slate-600" style="${ssrRenderStyle({ "text-align": "-webkit-center" })}"${_scopeId}><input type="checkbox"${ssrIncludeBooleanAttr(Array.isArray(field.is_nullable) ? ssrLooseContain(field.is_nullable, null) : field.is_nullable) ? " checked" : ""} class="block px-2 py-1 text-sm border-2 rounded-md shadow-sm border-slate-800 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
              _push2(ssrRenderComponent(_sfc_main$3, {
                class: "mt-2",
                message: unref(form).errors["fields." + fieldIndex + ".is_nullable"]
              }, null, _parent2, _scopeId));
              _push2(`</td><td class="px-2 py-1 border border-slate-600" style="${ssrRenderStyle({ "text-align": "-webkit-center" })}"${_scopeId}><div class="flex items-center justify-center w-full space-x-2"${_scopeId}><input type="checkbox"${ssrIncludeBooleanAttr(Array.isArray(field.is_relation) ? ssrLooseContain(field.is_relation, null) : field.is_relation) ? " checked" : ""} class="block px-2 py-1 text-sm border-2 rounded-md shadow-sm border-slate-800 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
              _push2(ssrRenderComponent(_sfc_main$3, {
                class: "mt-2",
                message: unref(form).errors["fields." + fieldIndex + ".is_relation"]
              }, null, _parent2, _scopeId));
              if (field.is_relation) {
                _push2(`<div class="w-full"${_scopeId}><select class="block w-full px-2 py-1 mt-1 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(field.relation_table) ? ssrLooseContain(field.relation_table, "") : ssrLooseEqual(field.relation_table, "")) ? " selected" : ""}${_scopeId}>-- Select a Model --</option><!--[-->`);
                ssrRenderList(_ctx.$page.props.modelsName, (modelType, modelIndex) => {
                  _push2(`<option${ssrRenderAttr("value", modelType)}${ssrIncludeBooleanAttr(Array.isArray(field.relation_table) ? ssrLooseContain(field.relation_table, modelType) : ssrLooseEqual(field.relation_table, modelType)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(modelType)}</option>`);
                });
                _push2(`<!--]--></select>`);
                _push2(ssrRenderComponent(_sfc_main$3, {
                  class: "mt-2",
                  message: unref(form).errors["fields." + fieldIndex + ".relation_table"]
                }, null, _parent2, _scopeId));
                _push2(`</div>`);
              } else {
                _push2(`<!---->`);
              }
              _push2(`</div></td><td class="px-2 py-1 border border-slate-600 text-end"${_scopeId}><button type="button" class="px-4 py-1 font-bold text-gray-200 rounded"${_scopeId}>`);
              _push2(ssrRenderComponent(_component_FeatherIcon, {
                name: "trash-2",
                class: "text-red-500"
              }, null, _parent2, _scopeId));
              _push2(`</button></td></tr>`);
            });
            _push2(`<!--]--><tr${_scopeId}><td colspan="8" class="px-2 py-1 border text-end border-slate-600"${_scopeId}><button type="button" class="px-4 py-1 font-bold text-gray-200 rounded"${_scopeId}>`);
            _push2(ssrRenderComponent(_component_FeatherIcon, {
              name: "file-plus",
              size: "1px",
              class: "text-green-500"
            }, null, _parent2, _scopeId));
            _push2(`</button></td></tr></tbody></table></div> ${ssrInterpolate(unref(form).formField)} ${ssrInterpolate(unref(form).relationField)} `);
            if (unref(form).fields) {
              _push2(`<div class="w-full"${_scopeId}><div class="flex pb-1 mt-5 mb-2 border-b"${_scopeId}><h1 class="text-xl font-bold"${_scopeId}>Select column name</h1></div><div class="grid grid-cols-2 gap-1 sm:grid-cols-3"${_scopeId}><!--[-->`);
              ssrRenderList(unref(form).fields, (field, Index) => {
                _push2(`<div class="flex items-center w-full mb-2 space-x-2"${_scopeId}>`);
                if (field.field_name) {
                  _push2(`<input${ssrRenderAttr("id", "from" + Index)} type="checkbox"${ssrIncludeBooleanAttr(Array.isArray(unref(form).formField) ? ssrLooseContain(unref(form).formField, field.field_name) : unref(form).formField) ? " checked" : ""}${ssrRenderAttr("value", field.field_name)} class="block px-2 py-1 text-sm border-2 rounded-md shadow-sm border-slate-800 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
                } else {
                  _push2(`<!---->`);
                }
                _push2(`<label${ssrRenderAttr("for", "from" + Index)}${_scopeId}>${ssrInterpolate(field.field_name)}</label></div>`);
              });
              _push2(`<!--]--></div></div>`);
            } else {
              _push2(`<!---->`);
            }
            if (__props.fillableAttributes) {
              _push2(`<div class="w-full"${_scopeId}><div class="flex pb-1 mt-5 mb-2 border-b"${_scopeId}><h1 class="text-xl font-bold"${_scopeId}>Select relation table column name</h1></div><div class="grid grid-cols-2 gap-1 mt-2 sm:grid-cols-3"${_scopeId}><!--[-->`);
              ssrRenderList(__props.fillableAttributes, (field, Index) => {
                _push2(`<div class="flex items-center w-full mb-2 space-x-2"${_scopeId}>`);
                if (field) {
                  _push2(`<input${ssrRenderAttr("id", "model" + Index)} type="checkbox"${ssrIncludeBooleanAttr(Array.isArray(unref(form).relationField) ? ssrLooseContain(unref(form).relationField, field) : unref(form).relationField) ? " checked" : ""}${ssrRenderAttr("value", field)} class="block px-2 py-1 text-sm border-2 rounded-md shadow-sm border-slate-800 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
                } else {
                  _push2(`<!---->`);
                }
                _push2(`<label${ssrRenderAttr("for", "model" + Index)}${_scopeId}>${ssrInterpolate(field)}</label></div>`);
              });
              _push2(`<!--]--></div></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<div class="flex items-center justify-end mt-4"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              type: "submit",
              class: ["ms-4", { "opacity-25": unref(form).processing }],
              disabled: unref(form).processing
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(` Create `);
                } else {
                  return [
                    createTextVNode(" Create ")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</div></form></div>`);
          } else {
            return [
              createVNode("div", { class: "w-full mt-3 transition duration-1000 ease-in-out transform bg-white border border-gray-700 rounded-md shadow-lg shadow-gray-800/50 dark:bg-slate-900" }, [
                createVNode("div", { class: "flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md shadow-md dark:bg-gray-800 dark:text-gray-200 shadow-gray-800/50" }, [
                  createVNode("div", null, [
                    createVNode("h1", { class: "px-4 py-3 font-bold text-md dark:text-white" }, toDisplayString(_ctx.$page.props.pageTitle), 1)
                  ]),
                  createVNode("div", { class: "px-4 py-2" })
                ]),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"]),
                  class: "p-4"
                }, [
                  createVNode(AlertMessage),
                  createVNode("div", { class: "grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4" }, [
                    createVNode("div", { class: "col-span-1 md:col-span-2" }, [
                      createVNode(_sfc_main$2, {
                        for: "modelName",
                        value: "Model Name"
                      }),
                      withDirectives(createVNode("input", {
                        id: "modelName",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).modelName = $event,
                        onInput: formattedModelName,
                        type: "text",
                        placeholder: "Model Name"
                      }, null, 40, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).modelName]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.modelName
                      }, null, 8, ["message"])
                    ])
                  ]),
                  createVNode("div", { class: "w-full mt-4" }, [
                    createVNode(_sfc_main$2, {
                      for: "Database Fields",
                      value: "Database Fields"
                    }),
                    createVNode("table", { class: "w-full text-xs gray-700 text- dark:text-gray-200" }, [
                      createVNode("thead", null, [
                        createVNode("tr", { class: "text-gray-300 bg-slate-900" }, [
                          createVNode("th", { class: "p-2 border border-slate-600" }, "Sl/No"),
                          createVNode("th", { class: "p-2 border border-slate-600" }, "Field Name"),
                          createVNode("th", { class: "p-2 border border-slate-600" }, "Data Type"),
                          createVNode("th", { class: "p-2 border border-slate-600" }, "Data Length"),
                          createVNode("th", { class: "p-2 border border-slate-600" }, "Default Value"),
                          createVNode("th", { class: "p-2 border border-slate-600" }, "isNullable"),
                          createVNode("th", { class: "p-2 border border-slate-600" }, "isRelation"),
                          createVNode("th", { class: "p-2 border border-slate-600" }, "Action")
                        ])
                      ]),
                      createVNode("tbody", null, [
                        (openBlock(true), createBlock(Fragment, null, renderList(unref(form).fields, (field, fieldIndex) => {
                          return openBlock(), createBlock("tr", { key: fieldIndex }, [
                            createVNode("td", { class: "text-center border border-slate-600" }, toDisplayString(fieldIndex + 1), 1),
                            createVNode("td", { class: "p-1 border border-slate-600" }, [
                              withDirectives(createVNode("input", {
                                type: "text",
                                placeholder: "Database Field Name",
                                "onUpdate:modelValue": ($event) => field.field_name = $event,
                                onInput: ($event) => formattedFieldName(fieldIndex),
                                class: "block w-full px-2 py-1 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                              }, null, 40, ["onUpdate:modelValue", "onInput"]), [
                                [vModelText, field.field_name]
                              ]),
                              createVNode(_sfc_main$3, {
                                class: "mt-2",
                                message: unref(form).errors["fields." + fieldIndex + ".field_name"]
                              }, null, 8, ["message"])
                            ]),
                            createVNode("td", { class: "p-1 border border-slate-600" }, [
                              withDirectives(createVNode("select", {
                                "onUpdate:modelValue": ($event) => field.data_type = $event,
                                onChange: ($event) => onDataTypeChange(fieldIndex),
                                class: "block w-full px-2 py-1 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                              }, [
                                createVNode("option", { value: "" }, "-- Select A Data Type --"),
                                (openBlock(true), createBlock(Fragment, null, renderList(_ctx.$page.props.dataTypes, (dataType, dataIndex) => {
                                  return openBlock(), createBlock("option", {
                                    key: dataIndex,
                                    value: dataType
                                  }, toDisplayString(dataType), 9, ["value"]);
                                }), 128))
                              ], 40, ["onUpdate:modelValue", "onChange"]), [
                                [vModelSelect, field.data_type]
                              ]),
                              createVNode(_sfc_main$3, {
                                class: "mt-2",
                                message: unref(form).errors["fields." + fieldIndex + ".data_type"]
                              }, null, 8, ["message"]),
                              field.data_type === "Enum" ? (openBlock(), createBlock(Fragment, { key: 0 }, [
                                (openBlock(true), createBlock(Fragment, null, renderList(unref(form).fields[fieldIndex].enum_values, (enumValue, enumIndex) => {
                                  return openBlock(), createBlock(Fragment, { key: enumIndex }, [
                                    withDirectives(createVNode("input", {
                                      type: "text",
                                      placeholder: "Add Enum Value",
                                      "onUpdate:modelValue": ($event) => enumValue.value = $event,
                                      class: "block w-full px-2 py-1 mt-1 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                    }, null, 8, ["onUpdate:modelValue"]), [
                                      [vModelText, enumValue.value]
                                    ]),
                                    createVNode("button", {
                                      type: "button",
                                      onClick: ($event) => removeEnumValue(fieldIndex, enumIndex)
                                    }, [
                                      createVNode(_component_FeatherIcon, {
                                        name: "trash-2",
                                        size: "1px",
                                        class: "text-red-500"
                                      }),
                                      createVNode("path", { d: "M21 4H8l-7 8 7 8h13a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z" }),
                                      createVNode("line", {
                                        x1: "18",
                                        x2: "12",
                                        y1: "9",
                                        y2: "15"
                                      }),
                                      createVNode("line", {
                                        x1: "12",
                                        x2: "18",
                                        y1: "9",
                                        y2: "15"
                                      })
                                    ], 8, ["onClick"])
                                  ], 64);
                                }), 128)),
                                createVNode("button", {
                                  type: "button",
                                  onClick: ($event) => addEnumValue(fieldIndex),
                                  class: "px-2 py-1 font-bold text-gray-200 rounded"
                                }, [
                                  createVNode(_component_FeatherIcon, {
                                    name: "plus",
                                    size: "1px",
                                    class: "text-green-500"
                                  })
                                ], 8, ["onClick"])
                              ], 64)) : createCommentVNode("", true)
                            ]),
                            createVNode("td", { class: "px-2 py-1 border border-slate-600 max-w-16" }, [
                              withDirectives(createVNode("input", {
                                type: "text",
                                step: "0.01",
                                placeholder: "Data Length",
                                "onUpdate:modelValue": ($event) => field.data_length = $event,
                                min: "1",
                                class: "block w-full px-2 py-1 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                              }, null, 8, ["onUpdate:modelValue"]), [
                                [vModelText, field.data_length]
                              ]),
                              createVNode(_sfc_main$3, {
                                class: "mt-2",
                                message: unref(form).errors["fields." + fieldIndex + ".data_length"]
                              }, null, 8, ["message"])
                            ]),
                            createVNode("td", { class: "px-2 py-1 border border-slate-600" }, [
                              withDirectives(createVNode("input", {
                                type: "text",
                                placeholder: "Default Value",
                                "onUpdate:modelValue": ($event) => field.default_value = $event,
                                class: "block w-full px-2 py-1 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                              }, null, 8, ["onUpdate:modelValue"]), [
                                [vModelText, field.default_value]
                              ]),
                              createVNode(_sfc_main$3, {
                                class: "mt-2",
                                message: unref(form).errors["fields." + fieldIndex + ".default_value"]
                              }, null, 8, ["message"])
                            ]),
                            createVNode("td", {
                              class: "px-2 py-1 border border-slate-600",
                              style: { "text-align": "-webkit-center" }
                            }, [
                              withDirectives(createVNode("input", {
                                type: "checkbox",
                                "onUpdate:modelValue": ($event) => field.is_nullable = $event,
                                class: "block px-2 py-1 text-sm border-2 rounded-md shadow-sm border-slate-800 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                              }, null, 8, ["onUpdate:modelValue"]), [
                                [vModelCheckbox, field.is_nullable]
                              ]),
                              createVNode(_sfc_main$3, {
                                class: "mt-2",
                                message: unref(form).errors["fields." + fieldIndex + ".is_nullable"]
                              }, null, 8, ["message"])
                            ]),
                            createVNode("td", {
                              class: "px-2 py-1 border border-slate-600",
                              style: { "text-align": "-webkit-center" }
                            }, [
                              createVNode("div", { class: "flex items-center justify-center w-full space-x-2" }, [
                                withDirectives(createVNode("input", {
                                  type: "checkbox",
                                  "onUpdate:modelValue": ($event) => field.is_relation = $event,
                                  class: "block px-2 py-1 text-sm border-2 rounded-md shadow-sm border-slate-800 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                }, null, 8, ["onUpdate:modelValue"]), [
                                  [vModelCheckbox, field.is_relation]
                                ]),
                                createVNode(_sfc_main$3, {
                                  class: "mt-2",
                                  message: unref(form).errors["fields." + fieldIndex + ".is_relation"]
                                }, null, 8, ["message"]),
                                field.is_relation ? (openBlock(), createBlock("div", {
                                  key: 0,
                                  class: "w-full"
                                }, [
                                  withDirectives(createVNode("select", {
                                    "onUpdate:modelValue": ($event) => field.relation_table = $event,
                                    onChange: ($event) => onModelChange(fieldIndex),
                                    class: "block w-full px-2 py-1 mt-1 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                  }, [
                                    createVNode("option", { value: "" }, "-- Select a Model --"),
                                    (openBlock(true), createBlock(Fragment, null, renderList(_ctx.$page.props.modelsName, (modelType, modelIndex) => {
                                      return openBlock(), createBlock("option", {
                                        key: modelIndex,
                                        value: modelType
                                      }, toDisplayString(modelType), 9, ["value"]);
                                    }), 128))
                                  ], 40, ["onUpdate:modelValue", "onChange"]), [
                                    [vModelSelect, field.relation_table]
                                  ]),
                                  createVNode(_sfc_main$3, {
                                    class: "mt-2",
                                    message: unref(form).errors["fields." + fieldIndex + ".relation_table"]
                                  }, null, 8, ["message"])
                                ])) : createCommentVNode("", true)
                              ])
                            ]),
                            createVNode("td", { class: "px-2 py-1 border border-slate-600 text-end" }, [
                              createVNode("button", {
                                type: "button",
                                onClick: ($event) => removeEnumValue(fieldIndex, _ctx.enumIndex),
                                class: "px-4 py-1 font-bold text-gray-200 rounded"
                              }, [
                                createVNode(_component_FeatherIcon, {
                                  name: "trash-2",
                                  class: "text-red-500"
                                })
                              ], 8, ["onClick"])
                            ])
                          ]);
                        }), 128)),
                        createVNode("tr", null, [
                          createVNode("td", {
                            colspan: "8",
                            class: "px-2 py-1 border text-end border-slate-600"
                          }, [
                            createVNode("button", {
                              type: "button",
                              onClick: addMoreField,
                              class: "px-4 py-1 font-bold text-gray-200 rounded"
                            }, [
                              createVNode(_component_FeatherIcon, {
                                name: "file-plus",
                                size: "1px",
                                class: "text-green-500"
                              })
                            ])
                          ])
                        ])
                      ])
                    ])
                  ]),
                  createTextVNode(" " + toDisplayString(unref(form).formField) + " " + toDisplayString(unref(form).relationField) + " ", 1),
                  unref(form).fields ? (openBlock(), createBlock("div", {
                    key: 0,
                    class: "w-full"
                  }, [
                    createVNode("div", { class: "flex pb-1 mt-5 mb-2 border-b" }, [
                      createVNode("h1", { class: "text-xl font-bold" }, "Select column name")
                    ]),
                    createVNode("div", { class: "grid grid-cols-2 gap-1 sm:grid-cols-3" }, [
                      (openBlock(true), createBlock(Fragment, null, renderList(unref(form).fields, (field, Index) => {
                        return openBlock(), createBlock("div", {
                          key: Index,
                          class: "flex items-center w-full mb-2 space-x-2"
                        }, [
                          field.field_name ? withDirectives((openBlock(), createBlock("input", {
                            key: 0,
                            id: "from" + Index,
                            type: "checkbox",
                            "onUpdate:modelValue": ($event) => unref(form).formField = $event,
                            value: field.field_name,
                            class: "block px-2 py-1 text-sm border-2 rounded-md shadow-sm border-slate-800 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                          }, null, 8, ["id", "onUpdate:modelValue", "value"])), [
                            [vModelCheckbox, unref(form).formField]
                          ]) : createCommentVNode("", true),
                          createVNode("label", {
                            for: "from" + Index
                          }, toDisplayString(field.field_name), 9, ["for"])
                        ]);
                      }), 128))
                    ])
                  ])) : createCommentVNode("", true),
                  __props.fillableAttributes ? (openBlock(), createBlock("div", {
                    key: 1,
                    class: "w-full"
                  }, [
                    createVNode("div", { class: "flex pb-1 mt-5 mb-2 border-b" }, [
                      createVNode("h1", { class: "text-xl font-bold" }, "Select relation table column name")
                    ]),
                    createVNode("div", { class: "grid grid-cols-2 gap-1 mt-2 sm:grid-cols-3" }, [
                      (openBlock(true), createBlock(Fragment, null, renderList(__props.fillableAttributes, (field, Index) => {
                        return openBlock(), createBlock("div", {
                          key: Index,
                          class: "flex items-center w-full mb-2 space-x-2"
                        }, [
                          field ? withDirectives((openBlock(), createBlock("input", {
                            key: 0,
                            id: "model" + Index,
                            type: "checkbox",
                            "onUpdate:modelValue": ($event) => unref(form).relationField = $event,
                            value: field,
                            class: "block px-2 py-1 text-sm border-2 rounded-md shadow-sm border-slate-800 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                          }, null, 8, ["id", "onUpdate:modelValue", "value"])), [
                            [vModelCheckbox, unref(form).relationField]
                          ]) : createCommentVNode("", true),
                          createVNode("label", {
                            for: "model" + Index
                          }, toDisplayString(field), 9, ["for"])
                        ]);
                      }), 128))
                    ])
                  ])) : createCommentVNode("", true),
                  createVNode("div", { class: "flex items-center justify-end mt-4" }, [
                    createVNode(_sfc_main$4, {
                      type: "submit",
                      class: ["ms-4", { "opacity-25": unref(form).processing }],
                      disabled: unref(form).processing
                    }, {
                      default: withCtx(() => [
                        createTextVNode(" Create ")
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/ModuleMaker/Index.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
