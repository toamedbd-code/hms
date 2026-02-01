import { watch, mergeProps, unref, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrInterpolate, ssrRenderAttr, ssrRenderList, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual } from "vue/server-renderer";
import { useForm } from "@inertiajs/vue3";
const _sfc_main$3 = {
  __name: "ChargeTypeModal",
  __ssrInlineRender: true,
  props: {
    isOpen: Boolean,
    chargeType: Object
  },
  emits: ["close", "created", "updated"],
  setup(__props, { emit: __emit }) {
    const props = __props;
    const moduleOptions = [
      "Appointment",
      "OPD",
      "IPD",
      "Pathology",
      "Radiology",
      "Blood Bank",
      "Ambulance"
    ];
    const form = useForm({
      name: "",
      modules: []
    });
    watch(() => props.chargeType, (newChargeType) => {
      if (newChargeType) {
        form.name = newChargeType.name || "";
        form.modules = newChargeType.modules ? JSON.parse(newChargeType.modules) : [];
      } else {
        form.reset();
      }
    }, { immediate: true });
    return (_ctx, _push, _parent, _attrs) => {
      var _a, _b;
      if (__props.isOpen) {
        _push(`<div${ssrRenderAttrs(mergeProps({ class: "fixed inset-0 z-50 overflow-y-auto" }, _attrs))}><div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0"><div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"></div><span class="hidden sm:inline-block sm:align-middle sm:h-screen">​</span><div class="inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6"><div class="sm:flex sm:items-start"><div class="w-full mt-3 text-center sm:mt-0 sm:text-left"><h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">${ssrInterpolate(((_a = __props.chargeType) == null ? void 0 : _a.id) ? "Edit Charge Type" : "Add New Charge Type")}</h3><form class="space-y-4"><div><label for="modal-name" class="block text-sm font-medium text-gray-700 mb-1"> Charge Type Name * </label><input id="modal-name"${ssrRenderAttr("value", unref(form).name)} type="text" placeholder="Enter charge type name" class="block w-full p-2 text-sm rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" required>`);
        if (unref(form).errors.name) {
          _push(`<div class="mt-1 text-sm text-red-600">${ssrInterpolate(unref(form).errors.name)}</div>`);
        } else {
          _push(`<!---->`);
        }
        _push(`</div><div><label class="block text-sm font-medium text-gray-700 mb-2"> Module * </label><div class="grid grid-cols-2 gap-2"><!--[-->`);
        ssrRenderList(moduleOptions, (module) => {
          _push(`<div class="flex items-center"><input${ssrRenderAttr("id", "modal_module_" + module)} type="checkbox"${ssrRenderAttr("value", module)}${ssrIncludeBooleanAttr(unref(form).modules.includes(module)) ? " checked" : ""} class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"><label${ssrRenderAttr("for", "modal_module_" + module)} class="ml-2 text-sm text-gray-700">${ssrInterpolate(module)}</label></div>`);
        });
        _push(`<!--]--></div>`);
        if (unref(form).errors.modules) {
          _push(`<div class="mt-1 text-sm text-red-600">${ssrInterpolate(unref(form).errors.modules)}</div>`);
        } else {
          _push(`<!---->`);
        }
        _push(`</div><div class="flex justify-end space-x-3 pt-4"><button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500"> Cancel </button><button type="submit"${ssrIncludeBooleanAttr(unref(form).processing) ? " disabled" : ""} class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50">${ssrInterpolate(unref(form).processing ? "Saving..." : ((_b = __props.chargeType) == null ? void 0 : _b.id) ? "Update" : "Create")}</button></div></form></div></div></div></div></div>`);
      } else {
        _push(`<!---->`);
      }
    };
  }
};
const _sfc_setup$3 = _sfc_main$3.setup;
_sfc_main$3.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/ChargeTypeModal.vue");
  return _sfc_setup$3 ? _sfc_setup$3(props, ctx) : void 0;
};
const _sfc_main$2 = {
  __name: "ChargeCategoryModal",
  __ssrInlineRender: true,
  props: {
    isOpen: Boolean,
    chargeCategory: Object,
    chargeTypes: Array
  },
  emits: ["close", "created", "updated"],
  setup(__props, { emit: __emit }) {
    const props = __props;
    const form = useForm({
      charge_type_id: "",
      name: "",
      description: ""
    });
    watch(() => props.chargeCategory, (newChargeCategory) => {
      if (newChargeCategory) {
        form.charge_type_id = newChargeCategory.charge_type_id || "";
        form.name = newChargeCategory.name || "";
        form.description = newChargeCategory.description || "";
      } else {
        form.reset();
      }
    }, { immediate: true });
    return (_ctx, _push, _parent, _attrs) => {
      var _a, _b;
      if (__props.isOpen) {
        _push(`<div${ssrRenderAttrs(mergeProps({ class: "fixed inset-0 z-50 overflow-y-auto" }, _attrs))}><div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0"><div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"></div><span class="hidden sm:inline-block sm:align-middle sm:h-screen">​</span><div class="inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6"><div class="sm:flex sm:items-start"><div class="w-full mt-3 text-center sm:mt-0 sm:text-left"><h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">${ssrInterpolate(((_a = __props.chargeCategory) == null ? void 0 : _a.id) ? "Edit Charge Category" : "Add New Charge Category")}</h3><form class="space-y-4"><div><label for="modal-charge-type" class="block text-sm font-medium text-gray-700 mb-1"> Charge Type * </label><select id="modal-charge-type" class="block w-full p-2 text-sm rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" required><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).charge_type_id) ? ssrLooseContain(unref(form).charge_type_id, "") : ssrLooseEqual(unref(form).charge_type_id, "")) ? " selected" : ""}>Select Charge Type</option><!--[-->`);
        ssrRenderList(__props.chargeTypes, (chargeType) => {
          _push(`<option${ssrRenderAttr("value", chargeType.id)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).charge_type_id) ? ssrLooseContain(unref(form).charge_type_id, chargeType.id) : ssrLooseEqual(unref(form).charge_type_id, chargeType.id)) ? " selected" : ""}>${ssrInterpolate(chargeType.name)}</option>`);
        });
        _push(`<!--]--></select>`);
        if (unref(form).errors.charge_type_id) {
          _push(`<div class="mt-1 text-sm text-red-600">${ssrInterpolate(unref(form).errors.charge_type_id)}</div>`);
        } else {
          _push(`<!---->`);
        }
        _push(`</div><div><label for="modal-category-name" class="block text-sm font-medium text-gray-700 mb-1"> Category Name * </label><input id="modal-category-name"${ssrRenderAttr("value", unref(form).name)} type="text" placeholder="Enter category name" class="block w-full p-2 text-sm rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" required>`);
        if (unref(form).errors.name) {
          _push(`<div class="mt-1 text-sm text-red-600">${ssrInterpolate(unref(form).errors.name)}</div>`);
        } else {
          _push(`<!---->`);
        }
        _push(`</div><div><label for="modal-description" class="block text-sm font-medium text-gray-700 mb-1"> Description * </label><textarea id="modal-description" rows="3" placeholder="Enter description" class="block w-full p-2 text-sm rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" required>${ssrInterpolate(unref(form).description)}</textarea>`);
        if (unref(form).errors.description) {
          _push(`<div class="mt-1 text-sm text-red-600">${ssrInterpolate(unref(form).errors.description)}</div>`);
        } else {
          _push(`<!---->`);
        }
        _push(`</div><div class="flex justify-end space-x-3 pt-4"><button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500"> Cancel </button><button type="submit"${ssrIncludeBooleanAttr(unref(form).processing) ? " disabled" : ""} class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50">${ssrInterpolate(unref(form).processing ? "Saving..." : ((_b = __props.chargeCategory) == null ? void 0 : _b.id) ? "Update" : "Create")}</button></div></form></div></div></div></div></div>`);
      } else {
        _push(`<!---->`);
      }
    };
  }
};
const _sfc_setup$2 = _sfc_main$2.setup;
_sfc_main$2.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/ChargeCategoryModal.vue");
  return _sfc_setup$2 ? _sfc_setup$2(props, ctx) : void 0;
};
const _sfc_main$1 = {
  __name: "UnitTypeModal",
  __ssrInlineRender: true,
  props: {
    isOpen: Boolean,
    unitType: Object
  },
  emits: ["close", "created", "updated"],
  setup(__props, { emit: __emit }) {
    const props = __props;
    const form = useForm({
      name: ""
    });
    watch(() => props.unitType, (newUnitType) => {
      if (newUnitType) {
        form.name = newUnitType.name || "";
      } else {
        form.reset();
      }
    }, { immediate: true });
    return (_ctx, _push, _parent, _attrs) => {
      var _a, _b;
      if (__props.isOpen) {
        _push(`<div${ssrRenderAttrs(mergeProps({ class: "fixed inset-0 z-50 overflow-y-auto" }, _attrs))}><div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0"><div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"></div><span class="hidden sm:inline-block sm:align-middle sm:h-screen">​</span><div class="inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6"><div class="sm:flex sm:items-start"><div class="w-full mt-3 text-center sm:mt-0 sm:text-left"><h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">${ssrInterpolate(((_a = __props.unitType) == null ? void 0 : _a.id) ? "Edit Unit Type" : "Add New Unit Type")}</h3><form class="space-y-4"><div><label for="modal-unit-name" class="block text-sm font-medium text-gray-700 mb-1"> Unit Name * </label><input id="modal-unit-name"${ssrRenderAttr("value", unref(form).name)} type="text" placeholder="Enter unit name (e.g., Per Hour, Per Day, Per Test)" class="block w-full p-2 text-sm rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" required>`);
        if (unref(form).errors.name) {
          _push(`<div class="mt-1 text-sm text-red-600">${ssrInterpolate(unref(form).errors.name)}</div>`);
        } else {
          _push(`<!---->`);
        }
        _push(`</div><div class="flex justify-end space-x-3 pt-4"><button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500"> Cancel </button><button type="submit"${ssrIncludeBooleanAttr(unref(form).processing) ? " disabled" : ""} class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50">${ssrInterpolate(unref(form).processing ? "Saving..." : ((_b = __props.unitType) == null ? void 0 : _b.id) ? "Update" : "Create")}</button></div></form></div></div></div></div></div>`);
      } else {
        _push(`<!---->`);
      }
    };
  }
};
const _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/UnitTypeModal.vue");
  return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
const _sfc_main = {
  __name: "TaxCategoryModal",
  __ssrInlineRender: true,
  props: {
    isOpen: Boolean,
    taxCategory: Object
  },
  emits: ["close", "created", "updated"],
  setup(__props, { emit: __emit }) {
    const props = __props;
    const form = useForm({
      name: "",
      percentage: ""
    });
    watch(() => props.taxCategory, (newTaxCategory) => {
      if (newTaxCategory) {
        form.name = newTaxCategory.name || "";
        form.percentage = newTaxCategory.percentage || "";
      } else {
        form.reset();
      }
    }, { immediate: true });
    return (_ctx, _push, _parent, _attrs) => {
      var _a, _b;
      if (__props.isOpen) {
        _push(`<div${ssrRenderAttrs(mergeProps({ class: "fixed inset-0 z-50 overflow-y-auto" }, _attrs))}><div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0"><div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"></div><span class="hidden sm:inline-block sm:align-middle sm:h-screen">​</span><div class="inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6"><div class="sm:flex sm:items-start"><div class="w-full mt-3 text-center sm:mt-0 sm:text-left"><h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">${ssrInterpolate(((_a = __props.taxCategory) == null ? void 0 : _a.id) ? "Edit Tax Category" : "Add New Tax Category")}</h3><form class="space-y-4"><div><label for="modal-tax-name" class="block text-sm font-medium text-gray-700 mb-1"> Tax Name * </label><input id="modal-tax-name"${ssrRenderAttr("value", unref(form).name)} type="text" placeholder="Enter tax name (e.g., VAT, GST)" class="block w-full p-2 text-sm rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" required>`);
        if (unref(form).errors.name) {
          _push(`<div class="mt-1 text-sm text-red-600">${ssrInterpolate(unref(form).errors.name)}</div>`);
        } else {
          _push(`<!---->`);
        }
        _push(`</div><div><label for="modal-percentage" class="block text-sm font-medium text-gray-700 mb-1"> Tax Percentage * </label><div class="relative"><input id="modal-percentage"${ssrRenderAttr("value", unref(form).percentage)} type="number" step="0.01" min="0" max="100" placeholder="Enter percentage" class="block w-full p-2 pr-8 text-sm rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" required><span class="absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-500">%</span></div>`);
        if (unref(form).errors.percentage) {
          _push(`<div class="mt-1 text-sm text-red-600">${ssrInterpolate(unref(form).errors.percentage)}</div>`);
        } else {
          _push(`<!---->`);
        }
        _push(`</div><div class="flex justify-end space-x-3 pt-4"><button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500"> Cancel </button><button type="submit"${ssrIncludeBooleanAttr(unref(form).processing) ? " disabled" : ""} class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50">${ssrInterpolate(unref(form).processing ? "Saving..." : ((_b = __props.taxCategory) == null ? void 0 : _b.id) ? "Update" : "Create")}</button></div></form></div></div></div></div></div>`);
      } else {
        _push(`<!---->`);
      }
    };
  }
};
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/TaxCategoryModal.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main$3 as _,
  _sfc_main$2 as a,
  _sfc_main$1 as b,
  _sfc_main as c
};
