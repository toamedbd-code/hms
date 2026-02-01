import { ref, withCtx, unref, createTextVNode, toDisplayString, createVNode, openBlock, createBlock, withModifiers, withDirectives, Fragment, renderList, vModelSelect, vModelText, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderList, ssrRenderAttr } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import { useForm, router } from "@inertiajs/vue3";
import { _ as _sfc_main$3 } from "./InputError-6aeb8d97.mjs";
import { _ as _sfc_main$2 } from "./InputLabel-70ca52d1.mjs";
import { _ as _sfc_main$4 } from "./PrimaryButton-b82fb16e.mjs";
import "./AlertMessage-0f422981.mjs";
import { d as displayResponse, a as displayWarning } from "./responseMessage-d505224b.mjs";
import { _ as _sfc_main$5, a as _sfc_main$6, b as _sfc_main$7, c as _sfc_main$8 } from "./TaxCategoryModal-315ec389.mjs";
import "mitt";
import "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
import "toastr";
import "sweetalert2";
const _sfc_main = {
  __name: "Form",
  __ssrInlineRender: true,
  props: ["charge", "id", "chargeTypes", "chargeCategories", "chargeUnits", "taxCategories"],
  setup(__props) {
    var _a, _b, _c, _d, _e, _f, _g, _h, _i;
    const props = __props;
    const form = useForm({
      name: ((_a = props.charge) == null ? void 0 : _a.name) ?? "",
      charge_type_id: ((_b = props.charge) == null ? void 0 : _b.charge_type_id) ?? "",
      charge_category_id: ((_c = props.charge) == null ? void 0 : _c.charge_category_id) ?? "",
      unit_type_id: ((_d = props.charge) == null ? void 0 : _d.unit_type_id) ?? "",
      tax_category_id: ((_e = props.charge) == null ? void 0 : _e.tax_category_id) ?? "",
      tax: ((_f = props.charge) == null ? void 0 : _f.tax) ?? "",
      standard_charge: ((_g = props.charge) == null ? void 0 : _g.standard_charge) ?? "",
      description: ((_h = props.charge) == null ? void 0 : _h.description) ?? "",
      _method: ((_i = props.charge) == null ? void 0 : _i.id) ? "put" : "post"
    });
    const modals = ref({
      chargeType: false,
      chargeCategory: false,
      unitType: false,
      taxCategory: false
    });
    const modalData = ref({
      chargeTypes: [...props.chargeTypes],
      chargeCategories: [...props.chargeCategories],
      chargeUnits: [...props.chargeUnits],
      taxCategories: [...props.taxCategories]
    });
    const updateTaxPercentage = () => {
      if (form.tax_category_id) {
        const selectedTaxCategory = modalData.value.taxCategories.find(
          (category) => category.id == form.tax_category_id
        );
        if (selectedTaxCategory) {
          form.tax = selectedTaxCategory.percentage;
        }
      } else {
        form.tax = "";
      }
    };
    const openModal = (modalName) => {
      modals.value[modalName] = true;
    };
    const closeModal = (modalName) => {
      modals.value[modalName] = false;
    };
    const handleChargeTypeCreated = (response) => {
      router.reload({
        only: ["chargeTypes"],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
          modalData.value.chargeTypes = page.props.chargeTypes || [];
          displayResponse(response);
        }
      });
    };
    const handleChargeCategoryCreated = (response) => {
      router.reload({
        only: ["chargeCategories"],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
          modalData.value.chargeCategories = page.props.chargeCategories || [];
          displayResponse(response);
        }
      });
    };
    const handleUnitTypeCreated = (response) => {
      router.reload({
        only: ["chargeUnits"],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
          modalData.value.chargeUnits = page.props.chargeUnits || [];
          displayResponse(response);
        }
      });
    };
    const handleTaxCategoryCreated = (response) => {
      router.reload({
        only: ["taxCategories"],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
          modalData.value.taxCategories = page.props.taxCategories || [];
          displayResponse(response);
        }
      });
    };
    const submit = () => {
      const routeName = props.id ? route("backend.hospitalcharge.update", props.id) : route("backend.hospitalcharge.store");
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
    const goToChargeList = () => {
      router.visit(route("backend.hospitalcharge.index"));
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md"${_scopeId}><div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md"${_scopeId}><div${_scopeId}><h1 class="p-4 text-xl font-bold dark:text-white"${_scopeId}>${ssrInterpolate(_ctx.$page.props.pageTitle)}</h1></div><div class="p-2 py-2 flex items-center space-x-2"${_scopeId}><div class="flex items-center space-x-3"${_scopeId}><button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2"${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"${_scopeId}></path></svg> Hospital Charge List </button></div></div></div><form class="p-4"${_scopeId}><div class="grid grid-cols-1 gap-3 mb-4 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4"${_scopeId}><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "charge_type_id",
              value: "Charge Type *"
            }, null, _parent2, _scopeId));
            _push2(`<div class="flex items-center space-x-2"${_scopeId}><select id="charge_type_id" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).charge_type_id) ? ssrLooseContain(unref(form).charge_type_id, "") : ssrLooseEqual(unref(form).charge_type_id, "")) ? " selected" : ""}${_scopeId}>Select Charge Type</option><!--[-->`);
            ssrRenderList(modalData.value.chargeTypes, (type) => {
              _push2(`<option${ssrRenderAttr("value", type.id)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).charge_type_id) ? ssrLooseContain(unref(form).charge_type_id, type.id) : ssrLooseEqual(unref(form).charge_type_id, type.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(type.name)}</option>`);
            });
            _push2(`<!--]--></select><button type="button" class="flex-shrink-0 p-2 text-white bg-green-500 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-300"${_scopeId}><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"${_scopeId}></path></svg></button></div>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.charge_type_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "charge_category_id",
              value: "Charge Category *"
            }, null, _parent2, _scopeId));
            _push2(`<div class="flex items-center space-x-2"${_scopeId}><select id="charge_category_id" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).charge_category_id) ? ssrLooseContain(unref(form).charge_category_id, "") : ssrLooseEqual(unref(form).charge_category_id, "")) ? " selected" : ""}${_scopeId}>Select Charge Category</option><!--[-->`);
            ssrRenderList(modalData.value.chargeCategories, (category) => {
              _push2(`<option${ssrRenderAttr("value", category.id)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).charge_category_id) ? ssrLooseContain(unref(form).charge_category_id, category.id) : ssrLooseEqual(unref(form).charge_category_id, category.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(category.name)}</option>`);
            });
            _push2(`<!--]--></select><button type="button" class="flex-shrink-0 p-2 text-white bg-green-500 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-300"${_scopeId}><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"${_scopeId}></path></svg></button></div>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.charge_category_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "unit_type_id",
              value: "Unit Type *"
            }, null, _parent2, _scopeId));
            _push2(`<div class="flex items-center space-x-2"${_scopeId}><select id="unit_type_id" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).unit_type_id) ? ssrLooseContain(unref(form).unit_type_id, "") : ssrLooseEqual(unref(form).unit_type_id, "")) ? " selected" : ""}${_scopeId}>Select</option><!--[-->`);
            ssrRenderList(modalData.value.chargeUnits, (unitType) => {
              _push2(`<option${ssrRenderAttr("value", unitType.id)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).unit_type_id) ? ssrLooseContain(unref(form).unit_type_id, unitType.id) : ssrLooseEqual(unref(form).unit_type_id, unitType.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(unitType.name)}</option>`);
            });
            _push2(`<!--]--></select><button type="button" class="flex-shrink-0 p-2 text-white bg-green-500 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-300"${_scopeId}><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"${_scopeId}></path></svg></button></div>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.unit_type_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "name",
              value: "Charge Name *"
            }, null, _parent2, _scopeId));
            _push2(`<input id="name" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).name)} type="text" placeholder="Charge Name"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.name
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="grid grid-cols-1 gap-3 mb-4 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4"${_scopeId}><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "tax_category_id",
              value: "Tax Category *"
            }, null, _parent2, _scopeId));
            _push2(`<div class="flex items-center space-x-2"${_scopeId}><select id="tax_category_id" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).tax_category_id) ? ssrLooseContain(unref(form).tax_category_id, "") : ssrLooseEqual(unref(form).tax_category_id, "")) ? " selected" : ""}${_scopeId}>Select Tax Category</option><!--[-->`);
            ssrRenderList(modalData.value.taxCategories, (tax) => {
              _push2(`<option${ssrRenderAttr("value", tax.id)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).tax_category_id) ? ssrLooseContain(unref(form).tax_category_id, tax.id) : ssrLooseEqual(unref(form).tax_category_id, tax.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(tax.name)} (${ssrInterpolate(tax.percentage)}%) </option>`);
            });
            _push2(`<!--]--></select><button type="button" class="flex-shrink-0 p-2 text-white bg-green-500 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-300"${_scopeId}><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"${_scopeId}></path></svg></button></div>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.tax_category_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "tax",
              value: "Tax"
            }, null, _parent2, _scopeId));
            _push2(`<div class="relative"${_scopeId}><input id="tax" disabled class="block w-full p-2 pr-8 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).tax)} type="number" step="0.01" min="0" max="100" placeholder="Tax"${_scopeId}><span class="absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-500"${_scopeId}>%</span></div>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.tax
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "standard_charge",
              value: "Standard Charge (Tk.) *"
            }, null, _parent2, _scopeId));
            _push2(`<input id="standard_charge" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).standard_charge)} type="number" step="0.01" min="0" placeholder="Standard Charge"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.standard_charge
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="mb-4"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "description",
              value: "Description"
            }, null, _parent2, _scopeId));
            _push2(`<textarea id="description" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" rows="3" placeholder="Description"${_scopeId}>${ssrInterpolate(unref(form).description)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.description
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="flex items-center justify-end mt-4"${_scopeId}>`);
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
              isOpen: modals.value.chargeType,
              onClose: ($event) => closeModal("chargeType"),
              onCreated: handleChargeTypeCreated,
              onUpdated: handleChargeTypeCreated
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$6, {
              isOpen: modals.value.chargeCategory,
              chargeTypes: modalData.value.chargeTypes,
              onClose: ($event) => closeModal("chargeCategory"),
              onCreated: handleChargeCategoryCreated,
              onUpdated: handleChargeCategoryCreated
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$7, {
              isOpen: modals.value.unitType,
              onClose: ($event) => closeModal("unitType"),
              onCreated: handleUnitTypeCreated,
              onUpdated: handleUnitTypeCreated
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$8, {
              isOpen: modals.value.taxCategory,
              onClose: ($event) => closeModal("taxCategory"),
              onCreated: handleTaxCategoryCreated,
              onUpdated: handleTaxCategoryCreated
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
                        onClick: goToChargeList,
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
                        createTextVNode(" Hospital Charge List ")
                      ])
                    ])
                  ])
                ]),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"]),
                  class: "p-4"
                }, [
                  createVNode("div", { class: "grid grid-cols-1 gap-3 mb-4 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4" }, [
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "charge_type_id",
                        value: "Charge Type *"
                      }),
                      createVNode("div", { class: "flex items-center space-x-2" }, [
                        withDirectives(createVNode("select", {
                          id: "charge_type_id",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                          "onUpdate:modelValue": ($event) => unref(form).charge_type_id = $event
                        }, [
                          createVNode("option", { value: "" }, "Select Charge Type"),
                          (openBlock(true), createBlock(Fragment, null, renderList(modalData.value.chargeTypes, (type) => {
                            return openBlock(), createBlock("option", {
                              key: type.id,
                              value: type.id
                            }, toDisplayString(type.name), 9, ["value"]);
                          }), 128))
                        ], 8, ["onUpdate:modelValue"]), [
                          [vModelSelect, unref(form).charge_type_id]
                        ]),
                        createVNode("button", {
                          type: "button",
                          onClick: ($event) => openModal("chargeType"),
                          class: "flex-shrink-0 p-2 text-white bg-green-500 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-300"
                        }, [
                          (openBlock(), createBlock("svg", {
                            class: "w-4 h-4",
                            fill: "none",
                            stroke: "currentColor",
                            viewBox: "0 0 24 24"
                          }, [
                            createVNode("path", {
                              "stroke-linecap": "round",
                              "stroke-linejoin": "round",
                              "stroke-width": "2",
                              d: "M12 6v6m0 0v6m0-6h6m-6 0H6"
                            })
                          ]))
                        ], 8, ["onClick"])
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.charge_type_id
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "charge_category_id",
                        value: "Charge Category *"
                      }),
                      createVNode("div", { class: "flex items-center space-x-2" }, [
                        withDirectives(createVNode("select", {
                          id: "charge_category_id",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                          "onUpdate:modelValue": ($event) => unref(form).charge_category_id = $event
                        }, [
                          createVNode("option", { value: "" }, "Select Charge Category"),
                          (openBlock(true), createBlock(Fragment, null, renderList(modalData.value.chargeCategories, (category) => {
                            return openBlock(), createBlock("option", {
                              key: category.id,
                              value: category.id
                            }, toDisplayString(category.name), 9, ["value"]);
                          }), 128))
                        ], 8, ["onUpdate:modelValue"]), [
                          [vModelSelect, unref(form).charge_category_id]
                        ]),
                        createVNode("button", {
                          type: "button",
                          onClick: ($event) => openModal("chargeCategory"),
                          class: "flex-shrink-0 p-2 text-white bg-green-500 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-300"
                        }, [
                          (openBlock(), createBlock("svg", {
                            class: "w-4 h-4",
                            fill: "none",
                            stroke: "currentColor",
                            viewBox: "0 0 24 24"
                          }, [
                            createVNode("path", {
                              "stroke-linecap": "round",
                              "stroke-linejoin": "round",
                              "stroke-width": "2",
                              d: "M12 6v6m0 0v6m0-6h6m-6 0H6"
                            })
                          ]))
                        ], 8, ["onClick"])
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.charge_category_id
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "unit_type_id",
                        value: "Unit Type *"
                      }),
                      createVNode("div", { class: "flex items-center space-x-2" }, [
                        withDirectives(createVNode("select", {
                          id: "unit_type_id",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                          "onUpdate:modelValue": ($event) => unref(form).unit_type_id = $event
                        }, [
                          createVNode("option", { value: "" }, "Select"),
                          (openBlock(true), createBlock(Fragment, null, renderList(modalData.value.chargeUnits, (unitType) => {
                            return openBlock(), createBlock("option", {
                              key: unitType.id,
                              value: unitType.id
                            }, toDisplayString(unitType.name), 9, ["value"]);
                          }), 128))
                        ], 8, ["onUpdate:modelValue"]), [
                          [vModelSelect, unref(form).unit_type_id]
                        ]),
                        createVNode("button", {
                          type: "button",
                          onClick: ($event) => openModal("unitType"),
                          class: "flex-shrink-0 p-2 text-white bg-green-500 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-300"
                        }, [
                          (openBlock(), createBlock("svg", {
                            class: "w-4 h-4",
                            fill: "none",
                            stroke: "currentColor",
                            viewBox: "0 0 24 24"
                          }, [
                            createVNode("path", {
                              "stroke-linecap": "round",
                              "stroke-linejoin": "round",
                              "stroke-width": "2",
                              d: "M12 6v6m0 0v6m0-6h6m-6 0H6"
                            })
                          ]))
                        ], 8, ["onClick"])
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.unit_type_id
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "name",
                        value: "Charge Name *"
                      }),
                      withDirectives(createVNode("input", {
                        id: "name",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).name = $event,
                        type: "text",
                        placeholder: "Charge Name"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).name]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.name
                      }, null, 8, ["message"])
                    ])
                  ]),
                  createVNode("div", { class: "grid grid-cols-1 gap-3 mb-4 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4" }, [
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "tax_category_id",
                        value: "Tax Category *"
                      }),
                      createVNode("div", { class: "flex items-center space-x-2" }, [
                        withDirectives(createVNode("select", {
                          id: "tax_category_id",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                          "onUpdate:modelValue": ($event) => unref(form).tax_category_id = $event,
                          onChange: updateTaxPercentage
                        }, [
                          createVNode("option", { value: "" }, "Select Tax Category"),
                          (openBlock(true), createBlock(Fragment, null, renderList(modalData.value.taxCategories, (tax) => {
                            return openBlock(), createBlock("option", {
                              key: tax.id,
                              value: tax.id
                            }, toDisplayString(tax.name) + " (" + toDisplayString(tax.percentage) + "%) ", 9, ["value"]);
                          }), 128))
                        ], 40, ["onUpdate:modelValue"]), [
                          [vModelSelect, unref(form).tax_category_id]
                        ]),
                        createVNode("button", {
                          type: "button",
                          onClick: ($event) => openModal("taxCategory"),
                          class: "flex-shrink-0 p-2 text-white bg-green-500 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-300"
                        }, [
                          (openBlock(), createBlock("svg", {
                            class: "w-4 h-4",
                            fill: "none",
                            stroke: "currentColor",
                            viewBox: "0 0 24 24"
                          }, [
                            createVNode("path", {
                              "stroke-linecap": "round",
                              "stroke-linejoin": "round",
                              "stroke-width": "2",
                              d: "M12 6v6m0 0v6m0-6h6m-6 0H6"
                            })
                          ]))
                        ], 8, ["onClick"])
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.tax_category_id
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "tax",
                        value: "Tax"
                      }),
                      createVNode("div", { class: "relative" }, [
                        withDirectives(createVNode("input", {
                          id: "tax",
                          disabled: "",
                          class: "block w-full p-2 pr-8 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                          "onUpdate:modelValue": ($event) => unref(form).tax = $event,
                          type: "number",
                          step: "0.01",
                          min: "0",
                          max: "100",
                          placeholder: "Tax"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).tax]
                        ]),
                        createVNode("span", { class: "absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-500" }, "%")
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.tax
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "standard_charge",
                        value: "Standard Charge (Tk.) *"
                      }),
                      withDirectives(createVNode("input", {
                        id: "standard_charge",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).standard_charge = $event,
                        type: "number",
                        step: "0.01",
                        min: "0",
                        placeholder: "Standard Charge"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).standard_charge]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.standard_charge
                      }, null, 8, ["message"])
                    ])
                  ]),
                  createVNode("div", { class: "mb-4" }, [
                    createVNode(_sfc_main$2, {
                      for: "description",
                      value: "Description"
                    }),
                    withDirectives(createVNode("textarea", {
                      id: "description",
                      class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                      "onUpdate:modelValue": ($event) => unref(form).description = $event,
                      rows: "3",
                      placeholder: "Description"
                    }, null, 8, ["onUpdate:modelValue"]), [
                      [vModelText, unref(form).description]
                    ]),
                    createVNode(_sfc_main$3, {
                      class: "mt-2",
                      message: unref(form).errors.description
                    }, null, 8, ["message"])
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
                isOpen: modals.value.chargeType,
                onClose: ($event) => closeModal("chargeType"),
                onCreated: handleChargeTypeCreated,
                onUpdated: handleChargeTypeCreated
              }, null, 8, ["isOpen", "onClose"]),
              createVNode(_sfc_main$6, {
                isOpen: modals.value.chargeCategory,
                chargeTypes: modalData.value.chargeTypes,
                onClose: ($event) => closeModal("chargeCategory"),
                onCreated: handleChargeCategoryCreated,
                onUpdated: handleChargeCategoryCreated
              }, null, 8, ["isOpen", "chargeTypes", "onClose"]),
              createVNode(_sfc_main$7, {
                isOpen: modals.value.unitType,
                onClose: ($event) => closeModal("unitType"),
                onCreated: handleUnitTypeCreated,
                onUpdated: handleUnitTypeCreated
              }, null, 8, ["isOpen", "onClose"]),
              createVNode(_sfc_main$8, {
                isOpen: modals.value.taxCategory,
                onClose: ($event) => closeModal("taxCategory"),
                onCreated: handleTaxCategoryCreated,
                onUpdated: handleTaxCategoryCreated
              }, null, 8, ["isOpen", "onClose"])
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/Charge/Form.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
