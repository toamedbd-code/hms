import { ref, computed, watch, mergeProps, unref, useSSRContext, withCtx, createTextVNode, createVNode, withModifiers, withDirectives, vModelText, openBlock, createBlock, createCommentVNode, Fragment, renderList, toDisplayString, vModelSelect } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderAttr, ssrRenderClass, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderList, ssrInterpolate } from "vue/server-renderer";
import { _ as _sfc_main$e } from "./BackendLayout-d755bdca.mjs";
import { useForm, router } from "@inertiajs/vue3";
import { _ as _sfc_main$5 } from "./InputError-6aeb8d97.mjs";
import { _ as _sfc_main$4 } from "./InputLabel-70ca52d1.mjs";
import { _ as _sfc_main$c } from "./PrimaryButton-b82fb16e.mjs";
import { A as AlertMessage } from "./AlertMessage-0f422981.mjs";
import { d as displayResponse, a as displayWarning } from "./responseMessage-d505224b.mjs";
import { _ as _sfc_main$6, a as _sfc_main$7, b as _sfc_main$8, c as _sfc_main$9 } from "./TaxCategoryModal-315ec389.mjs";
import { _ as _sfc_main$b } from "./SecondaryButton-0974b11b.mjs";
import { _ as _sfc_main$a } from "./Modal-452973b5.mjs";
import { _ as _sfc_main$d } from "./UnitModal-7a5b03b4.mjs";
import { _ as _export_sfc } from "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "mitt";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
import "toastr";
import "sweetalert2";
const _sfc_main$3 = {
  __name: "CategoryModal",
  __ssrInlineRender: true,
  props: {
    isOpen: {
      type: Boolean,
      default: false
    },
    categories: {
      type: Array,
      default: () => []
    }
  },
  emits: ["close", "categoryCreated"],
  setup(__props, { emit: __emit }) {
    const props = __props;
    const showAlert = ref(false);
    const form = useForm({
      parent_id: "",
      name: "",
      _method: "post"
    });
    function buildCategoryTree(categories, parentId = null) {
      return categories.filter((category) => category.parent_id === parentId).map((category) => ({
        ...category,
        children: buildCategoryTree(categories, category.id)
      }));
    }
    function generateIndentedOptions(categories, prefix = "") {
      let result = [];
      for (let category of categories) {
        result.push({ id: category.id, name: prefix + category.name });
        if (category.children && category.children.length) {
          result = result.concat(generateIndentedOptions(category.children, prefix + "— "));
        }
      }
      return result;
    }
    const treeOptions = computed(() => {
      const tree = buildCategoryTree(props.categories);
      return generateIndentedOptions(tree);
    });
    watch(() => props.isOpen, (newValue) => {
      if (newValue) {
        form.reset();
        form.clearErrors();
        showAlert.value = false;
      }
    });
    return (_ctx, _push, _parent, _attrs) => {
      if (__props.isOpen) {
        _push(`<div${ssrRenderAttrs(mergeProps({
          class: "fixed inset-0 z-50 overflow-y-auto",
          "aria-labelledby": "modal-title",
          role: "dialog",
          "aria-modal": "true"
        }, _attrs))}><div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0"><div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" aria-hidden="true"></div><span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">​</span><div class="inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6"><div class="sm:flex sm:items-start"><div class="w-full mt-3 text-center sm:mt-0 sm:text-left"><h3 class="text-lg font-medium leading-6 text-gray-900" id="modal-title"> Create New Category </h3><div class="mt-4"><form>`);
        if (showAlert.value) {
          _push(ssrRenderComponent(AlertMessage, null, null, _parent));
        } else {
          _push(`<!---->`);
        }
        _push(`<div class="space-y-4"><div>`);
        _push(ssrRenderComponent(_sfc_main$4, {
          for: "modal_name",
          value: "Category Name"
        }, null, _parent));
        _push(`<input id="modal_name"${ssrRenderAttr("value", unref(form).name)} type="text" placeholder="Enter category name" class="${ssrRenderClass([{ "border-red-500": unref(form).errors.name }, "block w-full p-2 text-sm border rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"])}">`);
        _push(ssrRenderComponent(_sfc_main$5, {
          class: "mt-1",
          message: unref(form).errors.name
        }, null, _parent));
        _push(`</div><div>`);
        _push(ssrRenderComponent(_sfc_main$4, {
          for: "modal_parent_id",
          value: "Parent Category (optional)"
        }, null, _parent));
        _push(`<select id="modal_parent_id" class="${ssrRenderClass([{ "border-red-500": unref(form).errors.parent_id }, "block w-full p-2 text-sm border rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"])}"><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).parent_id) ? ssrLooseContain(unref(form).parent_id, "") : ssrLooseEqual(unref(form).parent_id, "")) ? " selected" : ""}>-- No Parent (Top Level) --</option><!--[-->`);
        ssrRenderList(treeOptions.value, (option) => {
          _push(`<option${ssrRenderAttr("value", option.id)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).parent_id) ? ssrLooseContain(unref(form).parent_id, option.id) : ssrLooseEqual(unref(form).parent_id, option.id)) ? " selected" : ""}>${ssrInterpolate(option.name)}</option>`);
        });
        _push(`<!--]--></select>`);
        _push(ssrRenderComponent(_sfc_main$5, {
          class: "mt-1",
          message: unref(form).errors.parent_id
        }, null, _parent));
        _push(`</div></div><div class="mt-6 sm:flex sm:flex-row-reverse"><button type="submit"${ssrIncludeBooleanAttr(unref(form).processing) ? " disabled" : ""} class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed">`);
        if (unref(form).processing) {
          _push(`<svg class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>`);
        } else {
          _push(`<!---->`);
        }
        _push(` ${ssrInterpolate(unref(form).processing ? "Creating..." : "Create Category")}</button><button type="button"${ssrIncludeBooleanAttr(unref(form).processing) ? " disabled" : ""} class="inline-flex justify-center w-full px-4 py-2 mt-3 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"> Cancel </button></div></form></div></div></div></div></div></div>`);
      } else {
        _push(`<!---->`);
      }
    };
  }
};
const _sfc_setup$3 = _sfc_main$3.setup;
_sfc_main$3.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/CategoryModal.vue");
  return _sfc_setup$3 ? _sfc_setup$3(props, ctx) : void 0;
};
const _sfc_main$2 = {
  __name: "HospitalChargeModal",
  __ssrInlineRender: true,
  props: {
    isOpen: {
      type: Boolean,
      default: false
    },
    charge: {
      type: Object,
      default: null
    },
    chargeTypes: {
      type: Array,
      default: () => []
    },
    chargeCategories: {
      type: Array,
      default: () => []
    },
    chargeUnits: {
      type: Array,
      default: () => []
    },
    taxCategories: {
      type: Array,
      default: () => []
    }
  },
  emits: ["close", "created", "updated"],
  setup(__props, { emit: __emit }) {
    const props = __props;
    const form = useForm({
      name: "",
      charge_type_id: "",
      charge_category_id: "",
      unit_type_id: "",
      tax_category_id: "",
      tax: "",
      standard_charge: "",
      description: "",
      _method: "post"
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
    watch(() => props.charge, (newCharge) => {
      if (newCharge) {
        form.name = newCharge.name || "";
        form.charge_type_id = newCharge.charge_type_id || "";
        form.charge_category_id = newCharge.charge_category_id || "";
        form.unit_type_id = newCharge.unit_type_id || "";
        form.tax_category_id = newCharge.tax_category_id || "";
        form.tax = newCharge.tax || "";
        form.standard_charge = newCharge.standard_charge || "";
        form.description = newCharge.description || "";
        form._method = "put";
      } else {
        form.reset();
        form._method = "post";
      }
    }, { immediate: true });
    watch(() => props.chargeTypes, (newTypes) => {
      modalData.value.chargeTypes = [...newTypes];
    });
    watch(() => props.chargeCategories, (newCategories) => {
      modalData.value.chargeCategories = [...newCategories];
    });
    watch(() => props.chargeUnits, (newUnits) => {
      modalData.value.chargeUnits = [...newUnits];
    });
    watch(() => props.taxCategories, (newTaxCategories) => {
      modalData.value.taxCategories = [...newTaxCategories];
    });
    const closeSubModal = (modalName) => {
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
    return (_ctx, _push, _parent, _attrs) => {
      var _a, _b;
      if (__props.isOpen) {
        _push(`<div${ssrRenderAttrs(mergeProps({
          class: "fixed inset-0 z-50 overflow-y-auto",
          "aria-labelledby": "modal-title",
          role: "dialog",
          "aria-modal": "true"
        }, _attrs))}><div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0"><div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" aria-hidden="true"></div><span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">​</span><div class="inline-block w-full max-w-6xl px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:p-6 dark:bg-gray-800"><div class="flex items-center justify-between mb-4"><h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white" id="modal-title">${ssrInterpolate(((_a = __props.charge) == null ? void 0 : _a.id) ? "Edit Hospital Charge" : "Create Hospital Charge")}</h3><button class="text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600 dark:hover:text-gray-300" aria-label="Close"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button></div><form class="space-y-6"><div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4"><div class="col-span-1"><label for="charge_type_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300"> Charge Type * </label><div class="flex items-center space-x-2 mt-1"><select id="charge_type_id" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).charge_type_id) ? ssrLooseContain(unref(form).charge_type_id, "") : ssrLooseEqual(unref(form).charge_type_id, "")) ? " selected" : ""}>Select Charge Type</option><!--[-->`);
        ssrRenderList(modalData.value.chargeTypes, (type) => {
          _push(`<option${ssrRenderAttr("value", type.id)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).charge_type_id) ? ssrLooseContain(unref(form).charge_type_id, type.id) : ssrLooseEqual(unref(form).charge_type_id, type.id)) ? " selected" : ""}>${ssrInterpolate(type.name)}</option>`);
        });
        _push(`<!--]--></select><button type="button" class="flex-shrink-0 p-2 text-white bg-green-500 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-300"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg></button></div>`);
        if (unref(form).errors.charge_type_id) {
          _push(`<p class="mt-2 text-sm text-red-600">${ssrInterpolate(unref(form).errors.charge_type_id)}</p>`);
        } else {
          _push(`<!---->`);
        }
        _push(`</div><div class="col-span-1"><label for="charge_category_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300"> Charge Category * </label><div class="flex items-center space-x-2 mt-1"><select id="charge_category_id" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).charge_category_id) ? ssrLooseContain(unref(form).charge_category_id, "") : ssrLooseEqual(unref(form).charge_category_id, "")) ? " selected" : ""}>Select Charge Category</option><!--[-->`);
        ssrRenderList(modalData.value.chargeCategories, (category) => {
          _push(`<option${ssrRenderAttr("value", category.id)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).charge_category_id) ? ssrLooseContain(unref(form).charge_category_id, category.id) : ssrLooseEqual(unref(form).charge_category_id, category.id)) ? " selected" : ""}>${ssrInterpolate(category.name)}</option>`);
        });
        _push(`<!--]--></select><button type="button" class="flex-shrink-0 p-2 text-white bg-green-500 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-300"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg></button></div>`);
        if (unref(form).errors.charge_category_id) {
          _push(`<p class="mt-2 text-sm text-red-600">${ssrInterpolate(unref(form).errors.charge_category_id)}</p>`);
        } else {
          _push(`<!---->`);
        }
        _push(`</div><div class="col-span-1"><label for="unit_type_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300"> Unit Type * </label><div class="flex items-center space-x-2 mt-1"><select id="unit_type_id" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).unit_type_id) ? ssrLooseContain(unref(form).unit_type_id, "") : ssrLooseEqual(unref(form).unit_type_id, "")) ? " selected" : ""}>Select</option><!--[-->`);
        ssrRenderList(modalData.value.chargeUnits, (unitType) => {
          _push(`<option${ssrRenderAttr("value", unitType.id)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).unit_type_id) ? ssrLooseContain(unref(form).unit_type_id, unitType.id) : ssrLooseEqual(unref(form).unit_type_id, unitType.id)) ? " selected" : ""}>${ssrInterpolate(unitType.name)}</option>`);
        });
        _push(`<!--]--></select><button type="button" class="flex-shrink-0 p-2 text-white bg-green-500 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-300"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg></button></div>`);
        if (unref(form).errors.unit_type_id) {
          _push(`<p class="mt-2 text-sm text-red-600">${ssrInterpolate(unref(form).errors.unit_type_id)}</p>`);
        } else {
          _push(`<!---->`);
        }
        _push(`</div><div class="col-span-1"><label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300"> Charge Name * </label><input id="name" class="mt-1 block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).name)} type="text" placeholder="Charge Name">`);
        if (unref(form).errors.name) {
          _push(`<p class="mt-2 text-sm text-red-600">${ssrInterpolate(unref(form).errors.name)}</p>`);
        } else {
          _push(`<!---->`);
        }
        _push(`</div></div><div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4"><div class="col-span-1"><label for="tax_category_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300"> Tax Category * </label><div class="flex items-center space-x-2 mt-1"><select id="tax_category_id" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).tax_category_id) ? ssrLooseContain(unref(form).tax_category_id, "") : ssrLooseEqual(unref(form).tax_category_id, "")) ? " selected" : ""}>Select Tax Category</option><!--[-->`);
        ssrRenderList(modalData.value.taxCategories, (tax) => {
          _push(`<option${ssrRenderAttr("value", tax.id)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).tax_category_id) ? ssrLooseContain(unref(form).tax_category_id, tax.id) : ssrLooseEqual(unref(form).tax_category_id, tax.id)) ? " selected" : ""}>${ssrInterpolate(tax.name)} (${ssrInterpolate(tax.percentage)}%) </option>`);
        });
        _push(`<!--]--></select><button type="button" class="flex-shrink-0 p-2 text-white bg-green-500 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-300"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg></button></div>`);
        if (unref(form).errors.tax_category_id) {
          _push(`<p class="mt-2 text-sm text-red-600">${ssrInterpolate(unref(form).errors.tax_category_id)}</p>`);
        } else {
          _push(`<!---->`);
        }
        _push(`</div><div class="col-span-1"><label for="tax" class="block text-sm font-medium text-gray-700 dark:text-gray-300"> Tax </label><div class="relative mt-1"><input id="tax" disabled class="block w-full p-2 pr-8 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).tax)} type="number" step="0.01" min="0" max="100" placeholder="Tax"><span class="absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-500">%</span></div>`);
        if (unref(form).errors.tax) {
          _push(`<p class="mt-2 text-sm text-red-600">${ssrInterpolate(unref(form).errors.tax)}</p>`);
        } else {
          _push(`<!---->`);
        }
        _push(`</div><div class="col-span-1"><label for="standard_charge" class="block text-sm font-medium text-gray-700 dark:text-gray-300"> Standard Charge (Tk.) * </label><input id="standard_charge" class="mt-1 block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).standard_charge)} type="number" step="0.01" min="0" placeholder="Standard Charge">`);
        if (unref(form).errors.standard_charge) {
          _push(`<p class="mt-2 text-sm text-red-600">${ssrInterpolate(unref(form).errors.standard_charge)}</p>`);
        } else {
          _push(`<!---->`);
        }
        _push(`</div></div><div><label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300"> Description </label><textarea id="description" class="mt-1 block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" rows="3" placeholder="Description">${ssrInterpolate(unref(form).description)}</textarea>`);
        if (unref(form).errors.description) {
          _push(`<p class="mt-2 text-sm text-red-600">${ssrInterpolate(unref(form).errors.description)}</p>`);
        } else {
          _push(`<!---->`);
        }
        _push(`</div><div class="flex items-center justify-end space-x-3 pt-4 border-t"><button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-600 dark:text-gray-300 dark:border-gray-500 dark:hover:bg-gray-700"> Cancel </button><button type="submit" class="${ssrRenderClass([{ "opacity-25": unref(form).processing }, "inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"])}"${ssrIncludeBooleanAttr(unref(form).processing) ? " disabled" : ""}>${ssrInterpolate(((_b = __props.charge) == null ? void 0 : _b.id) ? "Update" : "Create")}</button></div></form>`);
        _push(ssrRenderComponent(_sfc_main$6, {
          isOpen: modals.value.chargeType,
          onClose: ($event) => closeSubModal("chargeType"),
          onCreated: handleChargeTypeCreated,
          onUpdated: handleChargeTypeCreated
        }, null, _parent));
        _push(ssrRenderComponent(_sfc_main$7, {
          isOpen: modals.value.chargeCategory,
          chargeTypes: modalData.value.chargeTypes,
          onClose: ($event) => closeSubModal("chargeCategory"),
          onCreated: handleChargeCategoryCreated,
          onUpdated: handleChargeCategoryCreated
        }, null, _parent));
        _push(ssrRenderComponent(_sfc_main$8, {
          isOpen: modals.value.unitType,
          onClose: ($event) => closeSubModal("unitType"),
          onCreated: handleUnitTypeCreated,
          onUpdated: handleUnitTypeCreated
        }, null, _parent));
        _push(ssrRenderComponent(_sfc_main$9, {
          isOpen: modals.value.taxCategory,
          onClose: ($event) => closeSubModal("taxCategory"),
          onCreated: handleTaxCategoryCreated,
          onUpdated: handleTaxCategoryCreated
        }, null, _parent));
        _push(`</div></div></div>`);
      } else {
        _push(`<!---->`);
      }
    };
  }
};
const _sfc_setup$2 = _sfc_main$2.setup;
_sfc_main$2.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/HospitalChargeModal.vue");
  return _sfc_setup$2 ? _sfc_setup$2(props, ctx) : void 0;
};
const _sfc_main$1 = {
  __name: "ChargeParameterModal",
  __ssrInlineRender: true,
  props: {
    show: Boolean,
    units: {
      type: Array,
      default: () => []
    },
    existingParameters: {
      type: Array,
      default: () => []
    }
  },
  emits: ["close", "parameter-created"],
  setup(__props, { emit: __emit }) {
    const props = __props;
    const emit = __emit;
    const form = useForm({
      name: "",
      referance_from: "",
      referance_to: "",
      pathology_unit_id: "",
      description: ""
    });
    const showUnitModal = ref(false);
    const isDuplicate = computed(() => {
      if (!form.name)
        return false;
      return props.existingParameters.some(
        (param) => param.name.toLowerCase() === form.name.toLowerCase()
      );
    });
    watch(() => props.show, (newVal) => {
      if (newVal) {
        form.reset();
      }
    });
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
          var _a;
          const newUnitId = (_a = response.data) == null ? void 0 : _a.id;
          if (newUnitId) {
            form.pathology_unit_id = newUnitId;
          }
        }
      });
    };
    const submit = () => {
      if (isDuplicate.value) {
        displayWarning({ message: "A parameter with this name already exists." });
        return;
      }
      form.post(route("backend.parameterofpathology.store"), {
        onSuccess: (response) => {
          form.reset();
          displayResponse(response);
          emit("parameter-created", response);
          emit("close");
        },
        onError: (errors) => {
          displayWarning(errors);
        }
      });
    };
    const close = () => {
      form.reset();
      emit("close");
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<!--[-->`);
      _push(ssrRenderComponent(_sfc_main$a, {
        show: __props.show,
        onClose: close,
        "max-width": "2xl"
      }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="p-6"${_scopeId}><h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4"${_scopeId}> Add New Charge Parameter </h2><form${_scopeId}><div class="grid grid-cols-1 gap-4"${_scopeId}><div${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              for: "name",
              value: "Parameter Name*"
            }, null, _parent2, _scopeId));
            _push2(`<input id="name" type="text" required${ssrRenderAttr("value", unref(form).name)} class="${ssrRenderClass([{ "border-red-500": isDuplicate.value }, "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"])}"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$5, {
              class: "mt-2",
              message: unref(form).errors.name
            }, null, _parent2, _scopeId));
            if (isDuplicate.value) {
              _push2(`<p class="mt-1 text-sm text-red-600"${_scopeId}> A parameter with this name already exists. </p>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div><div class="grid grid-cols-2 gap-4"${_scopeId}><div${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              for: "referance_from",
              value: "Reference Range From*"
            }, null, _parent2, _scopeId));
            _push2(`<input id="referance_from" type="text" required class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).referance_from)}${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$5, {
              class: "mt-2",
              message: unref(form).errors.referance_from
            }, null, _parent2, _scopeId));
            _push2(`</div><div${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              for: "referance_to",
              value: "Reference Range To*"
            }, null, _parent2, _scopeId));
            _push2(`<input id="referance_to" type="text" required class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).referance_to)}${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$5, {
              class: "mt-2",
              message: unref(form).errors.referance_to
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="relative"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              for: "pathology_unit_id",
              value: "Unit*"
            }, null, _parent2, _scopeId));
            _push2(`<div class="flex items-center space-x-2"${_scopeId}><select id="pathology_unit_id" required class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600 flex-1"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).pathology_unit_id) ? ssrLooseContain(unref(form).pathology_unit_id, "") : ssrLooseEqual(unref(form).pathology_unit_id, "")) ? " selected" : ""}${_scopeId}>Select Unit</option><!--[-->`);
            ssrRenderList(props.units, (unit) => {
              _push2(`<option${ssrRenderAttr("value", unit.id)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).pathology_unit_id) ? ssrLooseContain(unref(form).pathology_unit_id, unit.id) : ssrLooseEqual(unref(form).pathology_unit_id, unit.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(unit.name)}</option>`);
            });
            _push2(`<!--]--></select><button type="button" class="flex-shrink-0 inline-flex items-center justify-center w-10 h-10 text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200" title="Add New Unit"${_scopeId}><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"${_scopeId}></path></svg></button></div>`);
            _push2(ssrRenderComponent(_sfc_main$5, {
              class: "mt-2",
              message: unref(form).errors.pathology_unit_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              for: "description",
              value: "Description"
            }, null, _parent2, _scopeId));
            _push2(`<textarea id="description" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" rows="3"${_scopeId}>${ssrInterpolate(unref(form).description)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$5, {
              class: "mt-2",
              message: unref(form).errors.description
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="mt-6 flex justify-end space-x-3"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$b, { onClick: close }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(` Cancel `);
                } else {
                  return [
                    createTextVNode(" Cancel ")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$c, {
              type: "submit",
              class: { "opacity-25": unref(form).processing },
              disabled: unref(form).processing || isDuplicate.value
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(` Create Parameter `);
                } else {
                  return [
                    createTextVNode(" Create Parameter ")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</div></form></div>`);
          } else {
            return [
              createVNode("div", { class: "p-6" }, [
                createVNode("h2", { class: "text-lg font-medium text-gray-900 dark:text-gray-100 mb-4" }, " Add New Charge Parameter "),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"])
                }, [
                  createVNode("div", { class: "grid grid-cols-1 gap-4" }, [
                    createVNode("div", null, [
                      createVNode(_sfc_main$4, {
                        for: "name",
                        value: "Parameter Name*"
                      }),
                      withDirectives(createVNode("input", {
                        id: "name",
                        type: "text",
                        required: "",
                        class: ["block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600", { "border-red-500": isDuplicate.value }],
                        "onUpdate:modelValue": ($event) => unref(form).name = $event
                      }, null, 10, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).name]
                      ]),
                      createVNode(_sfc_main$5, {
                        class: "mt-2",
                        message: unref(form).errors.name
                      }, null, 8, ["message"]),
                      isDuplicate.value ? (openBlock(), createBlock("p", {
                        key: 0,
                        class: "mt-1 text-sm text-red-600"
                      }, " A parameter with this name already exists. ")) : createCommentVNode("", true)
                    ]),
                    createVNode("div", { class: "grid grid-cols-2 gap-4" }, [
                      createVNode("div", null, [
                        createVNode(_sfc_main$4, {
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
                        createVNode(_sfc_main$5, {
                          class: "mt-2",
                          message: unref(form).errors.referance_from
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", null, [
                        createVNode(_sfc_main$4, {
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
                        createVNode(_sfc_main$5, {
                          class: "mt-2",
                          message: unref(form).errors.referance_to
                        }, null, 8, ["message"])
                      ])
                    ]),
                    createVNode("div", { class: "relative" }, [
                      createVNode(_sfc_main$4, {
                        for: "pathology_unit_id",
                        value: "Unit*"
                      }),
                      createVNode("div", { class: "flex items-center space-x-2" }, [
                        withDirectives(createVNode("select", {
                          id: "pathology_unit_id",
                          required: "",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600 flex-1",
                          "onUpdate:modelValue": ($event) => unref(form).pathology_unit_id = $event
                        }, [
                          createVNode("option", { value: "" }, "Select Unit"),
                          (openBlock(true), createBlock(Fragment, null, renderList(props.units, (unit) => {
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
                      createVNode(_sfc_main$5, {
                        class: "mt-2",
                        message: unref(form).errors.pathology_unit_id
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$4, {
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
                      createVNode(_sfc_main$5, {
                        class: "mt-2",
                        message: unref(form).errors.description
                      }, null, 8, ["message"])
                    ])
                  ]),
                  createVNode("div", { class: "mt-6 flex justify-end space-x-3" }, [
                    createVNode(_sfc_main$b, { onClick: close }, {
                      default: withCtx(() => [
                        createTextVNode(" Cancel ")
                      ]),
                      _: 1
                    }),
                    createVNode(_sfc_main$c, {
                      type: "submit",
                      class: { "opacity-25": unref(form).processing },
                      disabled: unref(form).processing || isDuplicate.value
                    }, {
                      default: withCtx(() => [
                        createTextVNode(" Create Parameter ")
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
      _push(ssrRenderComponent(_sfc_main$d, {
        show: showUnitModal.value,
        "existing-units": __props.units,
        onClose: closeUnitModal,
        onUnitCreated: handleUnitCreated
      }, null, _parent));
      _push(`<!--]-->`);
    };
  }
};
const _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/ChargeParameterModal.vue");
  return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
const Form_vue_vue_type_style_index_0_scoped_7b32f883_lang = "";
const _sfc_main = {
  __name: "Form",
  __ssrInlineRender: true,
  props: [
    "pathologytest",
    "id",
    "testCategories",
    "charges",
    "pathologyUnits",
    "testParameters",
    "chargeTypes",
    "chargeCategories",
    "chargeUnits",
    "taxCategories"
  ],
  setup(__props) {
    var _a, _b, _c, _d, _e, _f, _g, _h, _i, _j, _k, _l, _m, _n, _o;
    const props = __props;
    const showCategoryModal = ref(false);
    const showChargeModal = ref(false);
    const showParameterModal = ref(false);
    const categories = ref([...props.testCategories]);
    const form = useForm({
      category_type: ((_a = props.pathologytest) == null ? void 0 : _a.category_type) ?? "",
      test_name: ((_b = props.pathologytest) == null ? void 0 : _b.test_name) ?? "",
      test_short_name: ((_c = props.pathologytest) == null ? void 0 : _c.test_short_name) ?? "",
      test_type: ((_d = props.pathologytest) == null ? void 0 : _d.test_type) ?? "",
      test_category_id: ((_e = props.pathologytest) == null ? void 0 : _e.test_category_id) ?? "",
      test_sub_category_id: ((_f = props.pathologytest) == null ? void 0 : _f.test_sub_category_id) ?? "",
      method: ((_g = props.pathologytest) == null ? void 0 : _g.method) ?? "",
      report_days: ((_h = props.pathologytest) == null ? void 0 : _h.report_days) ?? "",
      charge_id: ((_i = props.pathologytest) == null ? void 0 : _i.charge_category_id) ?? "",
      // Fixed field mapping
      charge_name: ((_j = props.pathologytest) == null ? void 0 : _j.charge_name) ?? "",
      tax: ((_k = props.pathologytest) == null ? void 0 : _k.tax) ?? "",
      standard_charge: ((_l = props.pathologytest) == null ? void 0 : _l.standard_charge) ?? "",
      amount: ((_m = props.pathologytest) == null ? void 0 : _m.amount) ?? "",
      parameters: ((_n = props.pathologytest) == null ? void 0 : _n.parameters) ?? [{
        test_parameter_id: "",
        referance_from: "",
        referance_to: "",
        pathology_unit_id: "",
        name: ""
      }],
      _method: ((_o = props.pathologytest) == null ? void 0 : _o.id) ? "put" : "post"
    });
    const mainCategories = computed(() => {
      if (!categories.value)
        return [];
      return categories.value.filter((category) => !category.parent_id);
    });
    const availableSubCategories = computed(() => {
      if (!form.test_category_id || !categories.value) {
        return [];
      }
      const getAllDescendants = (parentId) => {
        const directChildren = categories.value.filter((cat) => cat.parent_id == parentId);
        let allDescendants = [...directChildren];
        directChildren.forEach((child) => {
          allDescendants = [...allDescendants, ...getAllDescendants(child.id)];
        });
        return allDescendants;
      };
      return getAllDescendants(form.test_category_id);
    });
    const getCategoryDisplayName = (category) => {
      if (!category.parent_id)
        return category.name;
      const parent = categories.value.find((cat) => cat.id == category.parent_id);
      if (parent) {
        return `${getCategoryDisplayName(parent)} > ${category.name}`;
      }
      return category.name;
    };
    watch(() => form.test_category_id, (newCategoryId) => {
      form.test_sub_category_id = "";
    });
    watch(() => form.test_name, (newTestName) => {
      var _a2;
      if (newTestName && !((_a2 = props.pathologytest) == null ? void 0 : _a2.id)) {
        const words = newTestName.trim().split(/\s+/);
        form.test_short_name = words.map((word) => word.charAt(0).toUpperCase()).join("");
        form.test_type = newTestName;
      } else if (!newTestName) {
        form.test_short_name = "";
        form.test_type = "";
      }
    });
    const openCategoryModal = () => {
      showCategoryModal.value = true;
    };
    const closeCategoryModal = () => {
      showCategoryModal.value = false;
    };
    const handleCategoryCreated = (newCategory) => {
      router.reload({
        only: ["testCategories"],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
          categories.value = page.props.testCategories || [];
        }
      });
    };
    const addParameter = () => {
      form.parameters.push({
        test_parameter_id: "",
        referance_from: "",
        referance_to: "",
        pathology_unit_id: "",
        name: ""
      });
    };
    const removeParameter = (index) => {
      if (form.parameters.length > 1) {
        form.parameters.splice(index, 1);
      }
    };
    const onParameterSelect = (index, parameterId) => {
      if (parameterId) {
        const selectedParameter = props.testParameters.find((param) => param.id == parameterId);
        if (selectedParameter) {
          form.parameters[index].test_parameter_id = parameterId;
          form.parameters[index].name = selectedParameter.name;
          form.parameters[index].referance_from = selectedParameter.referance_from;
          form.parameters[index].referance_to = selectedParameter.referance_to;
          form.parameters[index].pathology_unit_id = selectedParameter.pathology_unit_id;
        }
      } else {
        form.parameters[index].name = "";
        form.parameters[index].referance_from = "";
        form.parameters[index].referance_to = "";
        form.parameters[index].pathology_unit_id = "";
      }
    };
    const onChargeSelect = (chargeId) => {
      if (chargeId) {
        const selectedCharge = props.charges.find((charge) => charge.id == chargeId);
        if (selectedCharge) {
          form.charge_name = selectedCharge.name;
          form.tax = selectedCharge.tax;
          form.standard_charge = selectedCharge.standard_charge;
          const standardCharge = parseFloat(selectedCharge.standard_charge) || 0;
          const taxPercentage = parseFloat(selectedCharge.tax) || 0;
          if (taxPercentage > 0) {
            const taxAmount = standardCharge * taxPercentage / 100;
            form.amount = (standardCharge + taxAmount).toFixed(2);
          } else {
            form.amount = standardCharge.toFixed(2);
          }
        }
      } else {
        form.charge_name = "";
        form.tax = "";
        form.standard_charge = "";
        form.amount = "";
      }
    };
    const openChargeModal = () => {
      showChargeModal.value = true;
    };
    const closeChargeModal = () => {
      showChargeModal.value = false;
    };
    const handleChargeCreated = (response) => {
      router.reload({
        only: ["charges"],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
          displayResponse(response);
        }
      });
    };
    const openParameterModal = () => {
      showParameterModal.value = true;
    };
    const closeParameterModal = () => {
      showParameterModal.value = false;
    };
    const handleParameterCreated = (response) => {
      router.reload({
        only: ["testParameters"],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
          displayResponse(response);
        }
      });
    };
    const submit = () => {
      const routeName = props.id ? route("backend.testpathology.update", props.id) : route("backend.testpathology.store");
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
    const goToTestList = () => {
      router.visit(route("backend.testpathology.index"));
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$e, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          var _a2, _b2, _c2, _d2, _e2, _f2, _g2, _h2, _i2, _j2;
          if (_push2) {
            _push2(`<div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md" data-v-7b32f883${_scopeId}><div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md" data-v-7b32f883${_scopeId}><div data-v-7b32f883${_scopeId}><h1 class="p-4 text-xl font-bold dark:text-white" data-v-7b32f883${_scopeId}>${ssrInterpolate(_ctx.$page.props.pageTitle)}</h1></div><div class="p-2 py-2 flex items-center space-x-2" data-v-7b32f883${_scopeId}><div class="flex items-center space-x-3" data-v-7b32f883${_scopeId}><button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2" data-v-7b32f883${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" data-v-7b32f883${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" data-v-7b32f883${_scopeId}></path></svg> Test List </button></div></div></div><form class="p-4" data-v-7b32f883${_scopeId}><div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4" data-v-7b32f883${_scopeId}><div class="col-span-1 md:col-span-1" data-v-7b32f883${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              for: "category_type",
              value: "Category Type"
            }, null, _parent2, _scopeId));
            _push2(`<select id="category_type" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-7b32f883${_scopeId}><option value="" data-v-7b32f883${ssrIncludeBooleanAttr(Array.isArray(unref(form).category_type) ? ssrLooseContain(unref(form).category_type, "") : ssrLooseEqual(unref(form).category_type, "")) ? " selected" : ""}${_scopeId}>-- Select Type --</option><option value="Pathology" data-v-7b32f883${ssrIncludeBooleanAttr(Array.isArray(unref(form).category_type) ? ssrLooseContain(unref(form).category_type, "Pathology") : ssrLooseEqual(unref(form).category_type, "Pathology")) ? " selected" : ""}${_scopeId}>Pathology</option><option value="Radiology" data-v-7b32f883${ssrIncludeBooleanAttr(Array.isArray(unref(form).category_type) ? ssrLooseContain(unref(form).category_type, "Radiology") : ssrLooseEqual(unref(form).category_type, "Radiology")) ? " selected" : ""}${_scopeId}>Radiology</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$5, {
              class: "mt-2",
              message: unref(form).errors.category_type
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-7b32f883${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              for: "test_name",
              value: "Test Name"
            }, null, _parent2, _scopeId));
            _push2(`<input id="test_name"${ssrRenderAttr("value", unref(form).test_name)} type="text" class="form-input" data-v-7b32f883${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$5, {
              message: unref(form).errors.test_name
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-7b32f883${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              for: "test_short_name",
              value: "Test Short Name"
            }, null, _parent2, _scopeId));
            _push2(`<input id="test_short_name"${ssrRenderAttr("value", unref(form).test_short_name)} type="text" class="form-input" data-v-7b32f883${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$5, {
              message: unref(form).errors.test_short_name
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-7b32f883${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              for: "test_type",
              value: "Test Type"
            }, null, _parent2, _scopeId));
            _push2(`<input id="test_type"${ssrRenderAttr("value", unref(form).test_type)} type="text" class="form-input" data-v-7b32f883${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$5, {
              message: unref(form).errors.test_type
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="relative" data-v-7b32f883${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              for: "test_category_id",
              value: "Main Category"
            }, null, _parent2, _scopeId));
            _push2(`<div class="flex items-center space-x-2" data-v-7b32f883${_scopeId}><select id="test_category_id" class="form-input flex-1" data-v-7b32f883${_scopeId}><option value="" data-v-7b32f883${ssrIncludeBooleanAttr(Array.isArray(unref(form).test_category_id) ? ssrLooseContain(unref(form).test_category_id, "") : ssrLooseEqual(unref(form).test_category_id, "")) ? " selected" : ""}${_scopeId}>Select Main Category</option><!--[-->`);
            ssrRenderList(mainCategories.value, (cat) => {
              _push2(`<option${ssrRenderAttr("value", cat.id)} data-v-7b32f883${ssrIncludeBooleanAttr(Array.isArray(unref(form).test_category_id) ? ssrLooseContain(unref(form).test_category_id, cat.id) : ssrLooseEqual(unref(form).test_category_id, cat.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(cat.name)}</option>`);
            });
            _push2(`<!--]--></select><button type="button" class="flex-shrink-0 inline-flex items-center justify-center w-10 h-10 text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200" title="Add New Category" data-v-7b32f883${_scopeId}><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" data-v-7b32f883${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" data-v-7b32f883${_scopeId}></path></svg></button></div>`);
            _push2(ssrRenderComponent(_sfc_main$5, {
              message: unref(form).errors.test_category_id
            }, null, _parent2, _scopeId));
            if (!((_a2 = mainCategories.value) == null ? void 0 : _a2.length)) {
              _push2(`<small class="text-red-500" data-v-7b32f883${_scopeId}> No categories available. Check if testCategories prop is passed correctly. </small>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div>`);
            if (availableSubCategories.value.length > 0) {
              _push2(`<div data-v-7b32f883${_scopeId}>`);
              _push2(ssrRenderComponent(_sfc_main$4, {
                for: "test_sub_category_id",
                value: "Sub Category"
              }, null, _parent2, _scopeId));
              _push2(`<select id="test_sub_category_id" class="form-input" data-v-7b32f883${_scopeId}><option value="" data-v-7b32f883${ssrIncludeBooleanAttr(Array.isArray(unref(form).test_sub_category_id) ? ssrLooseContain(unref(form).test_sub_category_id, "") : ssrLooseEqual(unref(form).test_sub_category_id, "")) ? " selected" : ""}${_scopeId}>Select Sub Category</option><!--[-->`);
              ssrRenderList(availableSubCategories.value, (subCat) => {
                _push2(`<option${ssrRenderAttr("value", subCat.id)} data-v-7b32f883${ssrIncludeBooleanAttr(Array.isArray(unref(form).test_sub_category_id) ? ssrLooseContain(unref(form).test_sub_category_id, subCat.id) : ssrLooseEqual(unref(form).test_sub_category_id, subCat.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(getCategoryDisplayName(subCat))}</option>`);
              });
              _push2(`<!--]--></select>`);
              _push2(ssrRenderComponent(_sfc_main$5, {
                message: unref(form).errors.test_sub_category_id
              }, null, _parent2, _scopeId));
              _push2(`</div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<div data-v-7b32f883${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              for: "method",
              value: "Method"
            }, null, _parent2, _scopeId));
            _push2(`<input id="method"${ssrRenderAttr("value", unref(form).method)} type="text" class="form-input" data-v-7b32f883${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$5, {
              message: unref(form).errors.method
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-7b32f883${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              for: "report_days",
              value: "Report Days"
            }, null, _parent2, _scopeId));
            _push2(`<input id="report_days"${ssrRenderAttr("value", unref(form).report_days)} type="number" class="form-input" data-v-7b32f883${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$5, {
              message: unref(form).errors.report_days
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-7b32f883${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              for: "charge_id",
              value: "Select Charge"
            }, null, _parent2, _scopeId));
            _push2(`<div class="flex items-center space-x-2" data-v-7b32f883${_scopeId}><select id="charge_id" class="form-input flex-1" data-v-7b32f883${_scopeId}><option value="" data-v-7b32f883${ssrIncludeBooleanAttr(Array.isArray(unref(form).charge_id) ? ssrLooseContain(unref(form).charge_id, "") : ssrLooseEqual(unref(form).charge_id, "")) ? " selected" : ""}${_scopeId}>Select Charge</option><!--[-->`);
            ssrRenderList(props.charges, (charge) => {
              _push2(`<option${ssrRenderAttr("value", charge.id)} data-v-7b32f883${ssrIncludeBooleanAttr(Array.isArray(unref(form).charge_id) ? ssrLooseContain(unref(form).charge_id, charge.id) : ssrLooseEqual(unref(form).charge_id, charge.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(charge.name)}</option>`);
            });
            _push2(`<!--]--></select><button type="button" class="flex-shrink-0 inline-flex items-center justify-center w-10 h-10 text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200" title="Add New Charge" data-v-7b32f883${_scopeId}><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" data-v-7b32f883${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" data-v-7b32f883${_scopeId}></path></svg></button></div>`);
            _push2(ssrRenderComponent(_sfc_main$5, {
              message: unref(form).errors.charge_id
            }, null, _parent2, _scopeId));
            if (!((_b2 = props.charges) == null ? void 0 : _b2.length)) {
              _push2(`<small class="text-red-500" data-v-7b32f883${_scopeId}> No charges available. Check if charges prop is passed correctly. </small>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div><div data-v-7b32f883${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              for: "charge_name",
              value: "Charge Name"
            }, null, _parent2, _scopeId));
            _push2(`<input id="charge_name"${ssrRenderAttr("value", unref(form).charge_name)} type="text" class="form-input disabled-input"${ssrIncludeBooleanAttr(!!unref(form).charge_id) ? " disabled" : ""} readonly data-v-7b32f883${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$5, {
              message: unref(form).errors.charge_name
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-7b32f883${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              for: "tax",
              value: "Tax %"
            }, null, _parent2, _scopeId));
            _push2(`<input id="tax"${ssrRenderAttr("value", unref(form).tax)} type="text" class="form-input disabled-input"${ssrIncludeBooleanAttr(!!unref(form).charge_id) ? " disabled" : ""} readonly data-v-7b32f883${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$5, {
              message: unref(form).errors.tax
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-7b32f883${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              for: "standard_charge",
              value: "Standard Charge"
            }, null, _parent2, _scopeId));
            _push2(`<input id="standard_charge"${ssrRenderAttr("value", unref(form).standard_charge)} type="number" step="0.01" class="form-input disabled-input"${ssrIncludeBooleanAttr(!!unref(form).charge_id) ? " disabled" : ""} readonly data-v-7b32f883${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$5, {
              message: unref(form).errors.standard_charge
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-7b32f883${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              for: "amount",
              value: "Total Amount"
            }, null, _parent2, _scopeId));
            _push2(`<input id="amount"${ssrRenderAttr("value", unref(form).amount)} type="number" step="0.01" class="form-input disabled-input"${ssrIncludeBooleanAttr(!!unref(form).charge_id) ? " disabled" : ""} readonly data-v-7b32f883${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$5, {
              message: unref(form).errors.amount
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="mt-8" data-v-7b32f883${_scopeId}><div class="flex items-center justify-between mb-4" data-v-7b32f883${_scopeId}><h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300" data-v-7b32f883${_scopeId}>Test Parameters</h3><button type="button" class="px-4 py-2 text-sm text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500" data-v-7b32f883${_scopeId}> Add New Parameter </button></div>`);
            if (!((_c2 = props.testParameters) == null ? void 0 : _c2.length)) {
              _push2(`<small class="text-red-500 block mb-4" data-v-7b32f883${_scopeId}> No test parameters available. Check if testParameters prop is passed correctly. </small>`);
            } else {
              _push2(`<!---->`);
            }
            if (!((_d2 = props.pathologyUnits) == null ? void 0 : _d2.length)) {
              _push2(`<small class="text-red-500 block mb-4" data-v-7b32f883${_scopeId}> No pathology units available. Check if pathologyUnits prop is passed correctly. </small>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<!--[-->`);
            ssrRenderList(unref(form).parameters, (parameter, index) => {
              _push2(`<div class="grid grid-cols-1 gap-3 mb-4 p-4 border border-gray-200 rounded-lg sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6" data-v-7b32f883${_scopeId}><div class="lg:col-span-2" data-v-7b32f883${_scopeId}>`);
              _push2(ssrRenderComponent(_sfc_main$4, {
                for: `test_parameter_id_${index}`,
                value: "Parameter Name"
              }, null, _parent2, _scopeId));
              _push2(`<select${ssrRenderAttr("id", `test_parameter_id_${index}`)} class="form-input" data-v-7b32f883${_scopeId}><option value="" data-v-7b32f883${ssrIncludeBooleanAttr(Array.isArray(unref(form).parameters[index].test_parameter_id) ? ssrLooseContain(unref(form).parameters[index].test_parameter_id, "") : ssrLooseEqual(unref(form).parameters[index].test_parameter_id, "")) ? " selected" : ""}${_scopeId}>-- Select Parameter --</option><!--[-->`);
              ssrRenderList(props.testParameters, (data) => {
                _push2(`<option${ssrRenderAttr("value", data.id)} data-v-7b32f883${ssrIncludeBooleanAttr(Array.isArray(unref(form).parameters[index].test_parameter_id) ? ssrLooseContain(unref(form).parameters[index].test_parameter_id, data.id) : ssrLooseEqual(unref(form).parameters[index].test_parameter_id, data.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(data.name)}</option>`);
              });
              _push2(`<!--]--></select>`);
              _push2(ssrRenderComponent(_sfc_main$5, {
                message: unref(form).errors[`parameters.${index}.test_parameter_id`]
              }, null, _parent2, _scopeId));
              _push2(`</div><div data-v-7b32f883${_scopeId}>`);
              _push2(ssrRenderComponent(_sfc_main$4, {
                for: `reference_from_${index}`,
                value: "Reference From"
              }, null, _parent2, _scopeId));
              _push2(`<input${ssrRenderAttr("id", `reference_from_${index}`)}${ssrRenderAttr("value", unref(form).parameters[index].referance_from)} type="text" class="${ssrRenderClass([{ "bg-gray-100 dark:bg-gray-600": unref(form).parameters[index].test_parameter_id }, "form-input"])}"${ssrIncludeBooleanAttr(!!unref(form).parameters[index].test_parameter_id) ? " disabled" : ""} placeholder="e.g., 12.0" data-v-7b32f883${_scopeId}>`);
              _push2(ssrRenderComponent(_sfc_main$5, {
                message: unref(form).errors[`parameters.${index}.referance_from`]
              }, null, _parent2, _scopeId));
              _push2(`</div><div data-v-7b32f883${_scopeId}>`);
              _push2(ssrRenderComponent(_sfc_main$4, {
                for: `reference_to_${index}`,
                value: "Reference To"
              }, null, _parent2, _scopeId));
              _push2(`<input${ssrRenderAttr("id", `reference_to_${index}`)}${ssrRenderAttr("value", unref(form).parameters[index].referance_to)} type="text" class="${ssrRenderClass([{ "bg-gray-100 dark:bg-gray-600": unref(form).parameters[index].test_parameter_id }, "form-input"])}"${ssrIncludeBooleanAttr(!!unref(form).parameters[index].test_parameter_id) ? " disabled" : ""} placeholder="e.g., 16.0" data-v-7b32f883${_scopeId}>`);
              _push2(ssrRenderComponent(_sfc_main$5, {
                message: unref(form).errors[`parameters.${index}.referance_to`]
              }, null, _parent2, _scopeId));
              _push2(`</div><div data-v-7b32f883${_scopeId}>`);
              _push2(ssrRenderComponent(_sfc_main$4, {
                for: `unit_${index}`,
                value: "Unit"
              }, null, _parent2, _scopeId));
              _push2(`<select${ssrRenderAttr("id", `unit_${index}`)} class="${ssrRenderClass([{ "bg-gray-100 dark:bg-gray-600": unref(form).parameters[index].test_parameter_id }, "form-input"])}"${ssrIncludeBooleanAttr(!!unref(form).parameters[index].test_parameter_id) ? " disabled" : ""} data-v-7b32f883${_scopeId}><option value="" data-v-7b32f883${ssrIncludeBooleanAttr(Array.isArray(unref(form).parameters[index].pathology_unit_id) ? ssrLooseContain(unref(form).parameters[index].pathology_unit_id, "") : ssrLooseEqual(unref(form).parameters[index].pathology_unit_id, "")) ? " selected" : ""}${_scopeId}>Select Unit</option><!--[-->`);
              ssrRenderList(props.pathologyUnits, (unit) => {
                _push2(`<option${ssrRenderAttr("value", unit.id)} data-v-7b32f883${ssrIncludeBooleanAttr(Array.isArray(unref(form).parameters[index].pathology_unit_id) ? ssrLooseContain(unref(form).parameters[index].pathology_unit_id, unit.id) : ssrLooseEqual(unref(form).parameters[index].pathology_unit_id, unit.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(unit.name)}</option>`);
              });
              _push2(`<!--]--></select>`);
              _push2(ssrRenderComponent(_sfc_main$5, {
                message: unref(form).errors[`parameters.${index}.pathology_unit_id`]
              }, null, _parent2, _scopeId));
              _push2(`</div><div class="flex items-end gap-2" data-v-7b32f883${_scopeId}><button type="button" class="px-3 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500" title="Add Parameter" data-v-7b32f883${_scopeId}><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" data-v-7b32f883${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" data-v-7b32f883${_scopeId}></path></svg></button>`);
              if (unref(form).parameters.length > 1) {
                _push2(`<button type="button" class="px-3 py-2 text-sm text-white bg-red-600 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500" title="Remove Parameter" data-v-7b32f883${_scopeId}><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" data-v-7b32f883${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" data-v-7b32f883${_scopeId}></path></svg></button>`);
              } else {
                _push2(`<!---->`);
              }
              _push2(`</div></div>`);
            });
            _push2(`<!--]-->`);
            if (!((_e2 = unref(form).parameters) == null ? void 0 : _e2.length)) {
              _push2(`<div class="text-center py-4 text-gray-500" data-v-7b32f883${_scopeId}> No parameters added yet. Click &quot;Add New Parameter&quot; to add one. </div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div><div class="flex justify-end mt-6" data-v-7b32f883${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$c, {
              type: "submit",
              class: { "opacity-25": unref(form).processing },
              disabled: unref(form).processing
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`${ssrInterpolate(props.id ? "Update Test" : "Create Test")}`);
                } else {
                  return [
                    createTextVNode(toDisplayString(props.id ? "Update Test" : "Create Test"), 1)
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</div></form></div>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              "is-open": showCategoryModal.value,
              categories: categories.value,
              onClose: closeCategoryModal,
              onCategoryCreated: handleCategoryCreated
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$2, {
              isOpen: showChargeModal.value,
              charge: null,
              chargeTypes: props.chargeTypes || [],
              chargeCategories: props.chargeCategories || [],
              chargeUnits: props.chargeUnits || [],
              taxCategories: props.taxCategories || [],
              onClose: closeChargeModal,
              onCreated: handleChargeCreated
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$1, {
              show: showParameterModal.value,
              units: props.pathologyUnits,
              "existing-parameters": props.testParameters,
              onClose: closeParameterModal,
              onParameterCreated: handleParameterCreated
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
                        onClick: goToTestList,
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
                        createTextVNode(" Test List ")
                      ])
                    ])
                  ])
                ]),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"]),
                  class: "p-4"
                }, [
                  createVNode("div", { class: "grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4" }, [
                    createVNode("div", { class: "col-span-1 md:col-span-1" }, [
                      createVNode(_sfc_main$4, {
                        for: "category_type",
                        value: "Category Type"
                      }),
                      withDirectives(createVNode("select", {
                        id: "category_type",
                        "onUpdate:modelValue": ($event) => unref(form).category_type = $event,
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                      }, [
                        createVNode("option", { value: "" }, "-- Select Type --"),
                        createVNode("option", { value: "Pathology" }, "Pathology"),
                        createVNode("option", { value: "Radiology" }, "Radiology")
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).category_type]
                      ]),
                      createVNode(_sfc_main$5, {
                        class: "mt-2",
                        message: unref(form).errors.category_type
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$4, {
                        for: "test_name",
                        value: "Test Name"
                      }),
                      withDirectives(createVNode("input", {
                        id: "test_name",
                        "onUpdate:modelValue": ($event) => unref(form).test_name = $event,
                        type: "text",
                        class: "form-input"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).test_name]
                      ]),
                      createVNode(_sfc_main$5, {
                        message: unref(form).errors.test_name
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$4, {
                        for: "test_short_name",
                        value: "Test Short Name"
                      }),
                      withDirectives(createVNode("input", {
                        id: "test_short_name",
                        "onUpdate:modelValue": ($event) => unref(form).test_short_name = $event,
                        type: "text",
                        class: "form-input"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).test_short_name]
                      ]),
                      createVNode(_sfc_main$5, {
                        message: unref(form).errors.test_short_name
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$4, {
                        for: "test_type",
                        value: "Test Type"
                      }),
                      withDirectives(createVNode("input", {
                        id: "test_type",
                        "onUpdate:modelValue": ($event) => unref(form).test_type = $event,
                        type: "text",
                        class: "form-input"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).test_type]
                      ]),
                      createVNode(_sfc_main$5, {
                        message: unref(form).errors.test_type
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "relative" }, [
                      createVNode(_sfc_main$4, {
                        for: "test_category_id",
                        value: "Main Category"
                      }),
                      createVNode("div", { class: "flex items-center space-x-2" }, [
                        withDirectives(createVNode("select", {
                          id: "test_category_id",
                          "onUpdate:modelValue": ($event) => unref(form).test_category_id = $event,
                          class: "form-input flex-1"
                        }, [
                          createVNode("option", { value: "" }, "Select Main Category"),
                          (openBlock(true), createBlock(Fragment, null, renderList(mainCategories.value, (cat) => {
                            return openBlock(), createBlock("option", {
                              key: cat.id,
                              value: cat.id
                            }, toDisplayString(cat.name), 9, ["value"]);
                          }), 128))
                        ], 8, ["onUpdate:modelValue"]), [
                          [vModelSelect, unref(form).test_category_id]
                        ]),
                        createVNode("button", {
                          type: "button",
                          onClick: openCategoryModal,
                          class: "flex-shrink-0 inline-flex items-center justify-center w-10 h-10 text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200",
                          title: "Add New Category"
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
                      createVNode(_sfc_main$5, {
                        message: unref(form).errors.test_category_id
                      }, null, 8, ["message"]),
                      !((_f2 = mainCategories.value) == null ? void 0 : _f2.length) ? (openBlock(), createBlock("small", {
                        key: 0,
                        class: "text-red-500"
                      }, " No categories available. Check if testCategories prop is passed correctly. ")) : createCommentVNode("", true)
                    ]),
                    availableSubCategories.value.length > 0 ? (openBlock(), createBlock("div", { key: 0 }, [
                      createVNode(_sfc_main$4, {
                        for: "test_sub_category_id",
                        value: "Sub Category"
                      }),
                      withDirectives(createVNode("select", {
                        id: "test_sub_category_id",
                        "onUpdate:modelValue": ($event) => unref(form).test_sub_category_id = $event,
                        class: "form-input"
                      }, [
                        createVNode("option", { value: "" }, "Select Sub Category"),
                        (openBlock(true), createBlock(Fragment, null, renderList(availableSubCategories.value, (subCat) => {
                          return openBlock(), createBlock("option", {
                            key: subCat.id,
                            value: subCat.id
                          }, toDisplayString(getCategoryDisplayName(subCat)), 9, ["value"]);
                        }), 128))
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).test_sub_category_id]
                      ]),
                      createVNode(_sfc_main$5, {
                        message: unref(form).errors.test_sub_category_id
                      }, null, 8, ["message"])
                    ])) : createCommentVNode("", true),
                    createVNode("div", null, [
                      createVNode(_sfc_main$4, {
                        for: "method",
                        value: "Method"
                      }),
                      withDirectives(createVNode("input", {
                        id: "method",
                        "onUpdate:modelValue": ($event) => unref(form).method = $event,
                        type: "text",
                        class: "form-input"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).method]
                      ]),
                      createVNode(_sfc_main$5, {
                        message: unref(form).errors.method
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$4, {
                        for: "report_days",
                        value: "Report Days"
                      }),
                      withDirectives(createVNode("input", {
                        id: "report_days",
                        "onUpdate:modelValue": ($event) => unref(form).report_days = $event,
                        type: "number",
                        class: "form-input"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).report_days]
                      ]),
                      createVNode(_sfc_main$5, {
                        message: unref(form).errors.report_days
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$4, {
                        for: "charge_id",
                        value: "Select Charge"
                      }),
                      createVNode("div", { class: "flex items-center space-x-2" }, [
                        withDirectives(createVNode("select", {
                          id: "charge_id",
                          "onUpdate:modelValue": ($event) => unref(form).charge_id = $event,
                          class: "form-input flex-1",
                          onChange: ($event) => onChargeSelect(unref(form).charge_id)
                        }, [
                          createVNode("option", { value: "" }, "Select Charge"),
                          (openBlock(true), createBlock(Fragment, null, renderList(props.charges, (charge) => {
                            return openBlock(), createBlock("option", {
                              key: charge.id,
                              value: charge.id
                            }, toDisplayString(charge.name), 9, ["value"]);
                          }), 128))
                        ], 40, ["onUpdate:modelValue", "onChange"]), [
                          [vModelSelect, unref(form).charge_id]
                        ]),
                        createVNode("button", {
                          type: "button",
                          onClick: openChargeModal,
                          class: "flex-shrink-0 inline-flex items-center justify-center w-10 h-10 text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200",
                          title: "Add New Charge"
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
                      createVNode(_sfc_main$5, {
                        message: unref(form).errors.charge_id
                      }, null, 8, ["message"]),
                      !((_g2 = props.charges) == null ? void 0 : _g2.length) ? (openBlock(), createBlock("small", {
                        key: 0,
                        class: "text-red-500"
                      }, " No charges available. Check if charges prop is passed correctly. ")) : createCommentVNode("", true)
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$4, {
                        for: "charge_name",
                        value: "Charge Name"
                      }),
                      withDirectives(createVNode("input", {
                        id: "charge_name",
                        "onUpdate:modelValue": ($event) => unref(form).charge_name = $event,
                        type: "text",
                        class: "form-input disabled-input",
                        disabled: !!unref(form).charge_id,
                        readonly: ""
                      }, null, 8, ["onUpdate:modelValue", "disabled"]), [
                        [vModelText, unref(form).charge_name]
                      ]),
                      createVNode(_sfc_main$5, {
                        message: unref(form).errors.charge_name
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$4, {
                        for: "tax",
                        value: "Tax %"
                      }),
                      withDirectives(createVNode("input", {
                        id: "tax",
                        "onUpdate:modelValue": ($event) => unref(form).tax = $event,
                        type: "text",
                        class: "form-input disabled-input",
                        disabled: !!unref(form).charge_id,
                        readonly: ""
                      }, null, 8, ["onUpdate:modelValue", "disabled"]), [
                        [vModelText, unref(form).tax]
                      ]),
                      createVNode(_sfc_main$5, {
                        message: unref(form).errors.tax
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$4, {
                        for: "standard_charge",
                        value: "Standard Charge"
                      }),
                      withDirectives(createVNode("input", {
                        id: "standard_charge",
                        "onUpdate:modelValue": ($event) => unref(form).standard_charge = $event,
                        type: "number",
                        step: "0.01",
                        class: "form-input disabled-input",
                        disabled: !!unref(form).charge_id,
                        readonly: ""
                      }, null, 8, ["onUpdate:modelValue", "disabled"]), [
                        [vModelText, unref(form).standard_charge]
                      ]),
                      createVNode(_sfc_main$5, {
                        message: unref(form).errors.standard_charge
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$4, {
                        for: "amount",
                        value: "Total Amount"
                      }),
                      withDirectives(createVNode("input", {
                        id: "amount",
                        "onUpdate:modelValue": ($event) => unref(form).amount = $event,
                        type: "number",
                        step: "0.01",
                        class: "form-input disabled-input",
                        disabled: !!unref(form).charge_id,
                        readonly: ""
                      }, null, 8, ["onUpdate:modelValue", "disabled"]), [
                        [vModelText, unref(form).amount]
                      ]),
                      createVNode(_sfc_main$5, {
                        message: unref(form).errors.amount
                      }, null, 8, ["message"])
                    ])
                  ]),
                  createVNode("div", { class: "mt-8" }, [
                    createVNode("div", { class: "flex items-center justify-between mb-4" }, [
                      createVNode("h3", { class: "text-lg font-semibold text-gray-700 dark:text-gray-300" }, "Test Parameters"),
                      createVNode("button", {
                        type: "button",
                        onClick: openParameterModal,
                        class: "px-4 py-2 text-sm text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
                      }, " Add New Parameter ")
                    ]),
                    !((_h2 = props.testParameters) == null ? void 0 : _h2.length) ? (openBlock(), createBlock("small", {
                      key: 0,
                      class: "text-red-500 block mb-4"
                    }, " No test parameters available. Check if testParameters prop is passed correctly. ")) : createCommentVNode("", true),
                    !((_i2 = props.pathologyUnits) == null ? void 0 : _i2.length) ? (openBlock(), createBlock("small", {
                      key: 1,
                      class: "text-red-500 block mb-4"
                    }, " No pathology units available. Check if pathologyUnits prop is passed correctly. ")) : createCommentVNode("", true),
                    (openBlock(true), createBlock(Fragment, null, renderList(unref(form).parameters, (parameter, index) => {
                      return openBlock(), createBlock("div", {
                        key: index,
                        class: "grid grid-cols-1 gap-3 mb-4 p-4 border border-gray-200 rounded-lg sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6"
                      }, [
                        createVNode("div", { class: "lg:col-span-2" }, [
                          createVNode(_sfc_main$4, {
                            for: `test_parameter_id_${index}`,
                            value: "Parameter Name"
                          }, null, 8, ["for"]),
                          withDirectives(createVNode("select", {
                            id: `test_parameter_id_${index}`,
                            class: "form-input",
                            "onUpdate:modelValue": ($event) => unref(form).parameters[index].test_parameter_id = $event,
                            onChange: ($event) => onParameterSelect(index, unref(form).parameters[index].test_parameter_id)
                          }, [
                            createVNode("option", { value: "" }, "-- Select Parameter --"),
                            (openBlock(true), createBlock(Fragment, null, renderList(props.testParameters, (data) => {
                              return openBlock(), createBlock("option", {
                                key: data.id,
                                value: data.id
                              }, toDisplayString(data.name), 9, ["value"]);
                            }), 128))
                          ], 40, ["id", "onUpdate:modelValue", "onChange"]), [
                            [vModelSelect, unref(form).parameters[index].test_parameter_id]
                          ]),
                          createVNode(_sfc_main$5, {
                            message: unref(form).errors[`parameters.${index}.test_parameter_id`]
                          }, null, 8, ["message"])
                        ]),
                        createVNode("div", null, [
                          createVNode(_sfc_main$4, {
                            for: `reference_from_${index}`,
                            value: "Reference From"
                          }, null, 8, ["for"]),
                          withDirectives(createVNode("input", {
                            id: `reference_from_${index}`,
                            "onUpdate:modelValue": ($event) => unref(form).parameters[index].referance_from = $event,
                            type: "text",
                            class: ["form-input", { "bg-gray-100 dark:bg-gray-600": unref(form).parameters[index].test_parameter_id }],
                            disabled: !!unref(form).parameters[index].test_parameter_id,
                            placeholder: "e.g., 12.0"
                          }, null, 10, ["id", "onUpdate:modelValue", "disabled"]), [
                            [vModelText, unref(form).parameters[index].referance_from]
                          ]),
                          createVNode(_sfc_main$5, {
                            message: unref(form).errors[`parameters.${index}.referance_from`]
                          }, null, 8, ["message"])
                        ]),
                        createVNode("div", null, [
                          createVNode(_sfc_main$4, {
                            for: `reference_to_${index}`,
                            value: "Reference To"
                          }, null, 8, ["for"]),
                          withDirectives(createVNode("input", {
                            id: `reference_to_${index}`,
                            "onUpdate:modelValue": ($event) => unref(form).parameters[index].referance_to = $event,
                            type: "text",
                            class: ["form-input", { "bg-gray-100 dark:bg-gray-600": unref(form).parameters[index].test_parameter_id }],
                            disabled: !!unref(form).parameters[index].test_parameter_id,
                            placeholder: "e.g., 16.0"
                          }, null, 10, ["id", "onUpdate:modelValue", "disabled"]), [
                            [vModelText, unref(form).parameters[index].referance_to]
                          ]),
                          createVNode(_sfc_main$5, {
                            message: unref(form).errors[`parameters.${index}.referance_to`]
                          }, null, 8, ["message"])
                        ]),
                        createVNode("div", null, [
                          createVNode(_sfc_main$4, {
                            for: `unit_${index}`,
                            value: "Unit"
                          }, null, 8, ["for"]),
                          withDirectives(createVNode("select", {
                            id: `unit_${index}`,
                            "onUpdate:modelValue": ($event) => unref(form).parameters[index].pathology_unit_id = $event,
                            class: ["form-input", { "bg-gray-100 dark:bg-gray-600": unref(form).parameters[index].test_parameter_id }],
                            disabled: !!unref(form).parameters[index].test_parameter_id
                          }, [
                            createVNode("option", { value: "" }, "Select Unit"),
                            (openBlock(true), createBlock(Fragment, null, renderList(props.pathologyUnits, (unit) => {
                              return openBlock(), createBlock("option", {
                                key: unit.id,
                                value: unit.id
                              }, toDisplayString(unit.name), 9, ["value"]);
                            }), 128))
                          ], 10, ["id", "onUpdate:modelValue", "disabled"]), [
                            [vModelSelect, unref(form).parameters[index].pathology_unit_id]
                          ]),
                          createVNode(_sfc_main$5, {
                            message: unref(form).errors[`parameters.${index}.pathology_unit_id`]
                          }, null, 8, ["message"])
                        ]),
                        createVNode("div", { class: "flex items-end gap-2" }, [
                          createVNode("button", {
                            type: "button",
                            onClick: addParameter,
                            class: "px-3 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500",
                            title: "Add Parameter"
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
                          ]),
                          unref(form).parameters.length > 1 ? (openBlock(), createBlock("button", {
                            key: 0,
                            type: "button",
                            onClick: ($event) => removeParameter(index),
                            class: "px-3 py-2 text-sm text-white bg-red-600 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500",
                            title: "Remove Parameter"
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
                                d: "M6 18L18 6M6 6l12 12"
                              })
                            ]))
                          ], 8, ["onClick"])) : createCommentVNode("", true)
                        ])
                      ]);
                    }), 128)),
                    !((_j2 = unref(form).parameters) == null ? void 0 : _j2.length) ? (openBlock(), createBlock("div", {
                      key: 2,
                      class: "text-center py-4 text-gray-500"
                    }, ' No parameters added yet. Click "Add New Parameter" to add one. ')) : createCommentVNode("", true)
                  ]),
                  createVNode("div", { class: "flex justify-end mt-6" }, [
                    createVNode(_sfc_main$c, {
                      type: "submit",
                      class: { "opacity-25": unref(form).processing },
                      disabled: unref(form).processing
                    }, {
                      default: withCtx(() => [
                        createTextVNode(toDisplayString(props.id ? "Update Test" : "Create Test"), 1)
                      ]),
                      _: 1
                    }, 8, ["class", "disabled"])
                  ])
                ], 32)
              ]),
              createVNode(_sfc_main$3, {
                "is-open": showCategoryModal.value,
                categories: categories.value,
                onClose: closeCategoryModal,
                onCategoryCreated: handleCategoryCreated
              }, null, 8, ["is-open", "categories"]),
              createVNode(_sfc_main$2, {
                isOpen: showChargeModal.value,
                charge: null,
                chargeTypes: props.chargeTypes || [],
                chargeCategories: props.chargeCategories || [],
                chargeUnits: props.chargeUnits || [],
                taxCategories: props.taxCategories || [],
                onClose: closeChargeModal,
                onCreated: handleChargeCreated
              }, null, 8, ["isOpen", "chargeTypes", "chargeCategories", "chargeUnits", "taxCategories"]),
              createVNode(_sfc_main$1, {
                show: showParameterModal.value,
                units: props.pathologyUnits,
                "existing-parameters": props.testParameters,
                onClose: closeParameterModal,
                onParameterCreated: handleParameterCreated
              }, null, 8, ["show", "units", "existing-parameters"])
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/PathologyTest/Form.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const Form = /* @__PURE__ */ _export_sfc(_sfc_main, [["__scopeId", "data-v-7b32f883"]]);
export {
  Form as default
};
