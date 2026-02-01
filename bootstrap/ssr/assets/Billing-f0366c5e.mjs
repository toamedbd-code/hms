import { ref, watch, mergeProps, useSSRContext, withCtx, createVNode, openBlock, createBlock, createTextVNode, Fragment, renderList, toDisplayString, withModifiers, withDirectives, vModelText, createCommentVNode, unref } from "vue";
import { ssrRenderAttrs, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderAttr, ssrRenderList, ssrInterpolate, ssrRenderComponent } from "vue/server-renderer";
import { _ as _sfc_main$2 } from "./BackendLayout-d755bdca.mjs";
import { useForm, router } from "@inertiajs/vue3";
import { _ as _sfc_main$4 } from "./InputError-6aeb8d97.mjs";
import { _ as _sfc_main$3 } from "./InputLabel-70ca52d1.mjs";
import { _ as _export_sfc } from "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "mitt";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
const BillingModal_vue_vue_type_style_index_0_scoped_ce7215b7_lang = "";
const _sfc_main$1 = {
  __name: "BillingModal",
  __ssrInlineRender: true,
  props: {
    show: {
      type: Boolean,
      default: false
    }
  },
  emits: ["close", "save"],
  setup(__props, { emit: __emit }) {
    const itemForm = ref({
      category: "",
      itemName: "CBC",
      unitPrice: 600,
      quantity: 1,
      totalAmount: 600
    });
    const patientForm = ref({
      selDoctor: "",
      pc: "",
      patientMobile: "",
      gender: "",
      cardType: "Cash",
      payMode: "Cash",
      cardType2: ""
    });
    const summary = ref({
      total: 600,
      corpDueAmt: 0,
      pvtPcAmt: 600,
      paidAmt: 600,
      changeAmt: 0,
      receivingAmt: 0,
      deliveryDate: "01-May-2023 95:00 PM",
      remarks: ""
    });
    const commission = ref({
      total: 0,
      physystAmt: 0,
      slider: 50
    });
    const items = ref([
      {
        name: "Anti HAV",
        unitPrice: 600,
        quantity: 1,
        totalAmount: 600,
        discount: 0,
        rugound: 0,
        netAmount: 600
      }
    ]);
    watch([() => itemForm.value.quantity, () => itemForm.value.unitPrice], () => {
      itemForm.value.totalAmount = itemForm.value.quantity * itemForm.value.unitPrice;
    });
    return (_ctx, _push, _parent, _attrs) => {
      if (__props.show) {
        _push(`<div${ssrRenderAttrs(mergeProps({ class: "fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" }, _attrs))} data-v-ce7215b7><div class="bg-white rounded-lg shadow-2xl max-w-[95vw] w-full mx-4 max-h-[95vh] overflow-y-auto" data-v-ce7215b7><div class="p-4" data-v-ce7215b7><div class="mb-4" data-v-ce7215b7><div class="flex justify-between items-center bg-[#053855] text-white px-4 py-2 text-sm font-semibold rounded-t" data-v-ce7215b7><div data-v-ce7215b7>ITEM DETAILS</div><div class="flex items-center space-x-2" data-v-ce7215b7><button class="text-white hover:text-gray-300 p-1" data-v-ce7215b7>📋</button><button class="text-white hover:text-gray-300 p-1" data-v-ce7215b7>🖨</button><button class="text-white hover:text-gray-300 p-1" data-v-ce7215b7>👤</button><button class="text-red-500 hover:text-red-700 p-1" data-v-ce7215b7>X</button></div></div><div class="border border-gray-300 border-t-0 p-4 bg-gray-50 rounded-b" data-v-ce7215b7><div class="flex items-center space-x-6 text-sm" data-v-ce7215b7><span class="font-medium text-gray-700" data-v-ce7215b7><strong data-v-ce7215b7>UNIT:</strong> Toamed Ltd.</span><span class="font-medium text-gray-700" data-v-ce7215b7><strong data-v-ce7215b7>Counter:</strong> Pharmacy</span><span class="font-medium text-gray-700" data-v-ce7215b7><strong data-v-ce7215b7>Sales Person:</strong> System Adm</span></div><div class="grid grid-cols-12 gap-3 items-end mt-4" data-v-ce7215b7><div class="col-span-2" data-v-ce7215b7><label class="block text-xs font-medium text-gray-700 mb-1" data-v-ce7215b7>Category</label><select class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:border-blue-500 focus:outline-none" data-v-ce7215b7><option value="" data-v-ce7215b7${ssrIncludeBooleanAttr(Array.isArray(itemForm.value.category) ? ssrLooseContain(itemForm.value.category, "") : ssrLooseEqual(itemForm.value.category, "")) ? " selected" : ""}>Select</option><option value="pathology" data-v-ce7215b7${ssrIncludeBooleanAttr(Array.isArray(itemForm.value.category) ? ssrLooseContain(itemForm.value.category, "pathology") : ssrLooseEqual(itemForm.value.category, "pathology")) ? " selected" : ""}>Pathology</option><option value="radiology" data-v-ce7215b7${ssrIncludeBooleanAttr(Array.isArray(itemForm.value.category) ? ssrLooseContain(itemForm.value.category, "radiology") : ssrLooseEqual(itemForm.value.category, "radiology")) ? " selected" : ""}>Radiology</option></select></div><div class="col-span-2" data-v-ce7215b7><label class="block text-xs font-medium text-gray-700 mb-1" data-v-ce7215b7>Item Name</label><select class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:border-blue-500 focus:outline-none" data-v-ce7215b7><option value="CBC" data-v-ce7215b7${ssrIncludeBooleanAttr(Array.isArray(itemForm.value.itemName) ? ssrLooseContain(itemForm.value.itemName, "CBC") : ssrLooseEqual(itemForm.value.itemName, "CBC")) ? " selected" : ""}>CBC</option><option value="Anti HAV" data-v-ce7215b7${ssrIncludeBooleanAttr(Array.isArray(itemForm.value.itemName) ? ssrLooseContain(itemForm.value.itemName, "Anti HAV") : ssrLooseEqual(itemForm.value.itemName, "Anti HAV")) ? " selected" : ""}>Anti HAV</option></select></div><div class="col-span-2" data-v-ce7215b7><label class="block text-xs font-medium text-gray-700 mb-1" data-v-ce7215b7>U/Price</label><div class="flex" data-v-ce7215b7><input${ssrRenderAttr("value", itemForm.value.unitPrice)} type="number" class="w-20 px-2 py-1.5 border border-gray-300 rounded-l text-sm bg-yellow-100 focus:bg-yellow-200 focus:outline-none" data-v-ce7215b7><span class="px-2 py-1.5 bg-gray-200 border-t border-b border-r border-gray-300 rounded-r text-xs" data-v-ce7215b7>%</span></div></div><div class="col-span-1" data-v-ce7215b7><label class="block text-xs font-medium text-gray-700 mb-1" data-v-ce7215b7>Qty</label><input${ssrRenderAttr("value", itemForm.value.quantity)} type="number" step="0.01" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:border-blue-500 focus:outline-none" data-v-ce7215b7></div><div class="col-span-2" data-v-ce7215b7><label class="block text-xs font-medium text-gray-700 mb-1" data-v-ce7215b7>T.Amt</label><input${ssrRenderAttr("value", itemForm.value.totalAmount)} type="number" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm bg-gray-100" readonly data-v-ce7215b7></div><div class="col-span-1" data-v-ce7215b7><button class="w-full h-8 bg-teal-600 text-white rounded hover:bg-teal-700 flex items-center justify-center font-bold text-lg" data-v-ce7215b7> ✚ </button></div></div></div></div><div class="mb-4" data-v-ce7215b7><div class="bg-slate-700 text-white px-4 py-2 text-sm font-semibold rounded-t" data-v-ce7215b7> ITEM LIST </div><div class="border border-gray-300 border-t-0 rounded-b overflow-hidden" data-v-ce7215b7><table class="w-full text-sm" data-v-ce7215b7><thead class="bg-teal-700 text-white" data-v-ce7215b7><tr data-v-ce7215b7><th class="px-3 py-2 text-left font-semibold" data-v-ce7215b7>Item Name</th><th class="px-3 py-2 text-center font-semibold" data-v-ce7215b7>U/Price</th><th class="px-3 py-2 text-center font-semibold" data-v-ce7215b7>Qty</th><th class="px-3 py-2 text-center font-semibold" data-v-ce7215b7>T.Amt</th><th class="px-3 py-2 text-center font-semibold" data-v-ce7215b7>Disc%</th><th class="px-3 py-2 text-center font-semibold" data-v-ce7215b7>Rugound</th><th class="px-3 py-2 text-center font-semibold" data-v-ce7215b7>Net Amt</th><th class="px-3 py-2 text-center font-semibold" data-v-ce7215b7>Action</th></tr></thead><tbody class="bg-white" data-v-ce7215b7><!--[-->`);
        ssrRenderList(items.value, (item, index) => {
          _push(`<tr class="border-b border-gray-200 hover:bg-gray-50" data-v-ce7215b7><td class="px-3 py-2 font-medium" data-v-ce7215b7>${ssrInterpolate(item.name)}</td><td class="px-3 py-2 text-center" data-v-ce7215b7>${ssrInterpolate(item.unitPrice)}</td><td class="px-3 py-2 text-center" data-v-ce7215b7>${ssrInterpolate(item.quantity)}</td><td class="px-3 py-2 text-center" data-v-ce7215b7>${ssrInterpolate(item.totalAmount)}</td><td class="px-3 py-2 text-center" data-v-ce7215b7>${ssrInterpolate(item.discount)}</td><td class="px-3 py-2 text-center" data-v-ce7215b7>${ssrInterpolate(item.rugound)}</td><td class="px-3 py-2 text-center font-semibold" data-v-ce7215b7>${ssrInterpolate(item.netAmount)}</td><td class="px-3 py-2 text-center" data-v-ce7215b7><button class="bg-red-500 text-white px-2 py-1 rounded text-xs hover:bg-green-600" data-v-ce7215b7> 🗑 </button></td></tr>`);
        });
        _push(`<!--]--></tbody></table></div></div><div class="grid grid-cols-3 gap-4" data-v-ce7215b7><div data-v-ce7215b7><div class="bg-teal-600 text-white px-4 py-2 text-sm font-semibold rounded-t" data-v-ce7215b7> PATIENT DETAILS </div><div class="border border-gray-300 border-t-0 rounded-b p-3 bg-white space-y-2" data-v-ce7215b7><div class="flex items-center justify-between" data-v-ce7215b7><label class="text-xs font-medium text-gray-700 w-20" data-v-ce7215b7>Sel. Doctor</label><select class="flex-1 ml-2 px-2 py-1.5 border border-gray-300 rounded text-sm focus:border-blue-500 focus:outline-none" data-v-ce7215b7><option value="" data-v-ce7215b7${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.selDoctor) ? ssrLooseContain(patientForm.value.selDoctor, "") : ssrLooseEqual(patientForm.value.selDoctor, "")) ? " selected" : ""}>Select</option></select></div><div class="flex items-center justify-between" data-v-ce7215b7><label class="text-xs font-medium text-gray-700 w-20" data-v-ce7215b7>PC</label><select class="flex-1 ml-2 px-2 py-1.5 border border-gray-300 rounded text-sm focus:border-blue-500 focus:outline-none" data-v-ce7215b7><option value="" data-v-ce7215b7${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.pc) ? ssrLooseContain(patientForm.value.pc, "") : ssrLooseEqual(patientForm.value.pc, "")) ? " selected" : ""}>Select</option></select></div><div class="flex items-center justify-between" data-v-ce7215b7><label class="text-xs font-medium text-gray-700 w-20" data-v-ce7215b7>Patient Mobile</label><input${ssrRenderAttr("value", patientForm.value.patientMobile)} type="text" placeholder="Enter Patient Mobile" class="flex-1 ml-2 px-2 py-1.5 border border-gray-300 rounded text-sm bg-yellow-100 focus:bg-yellow-200 focus:outline-none" data-v-ce7215b7></div><div class="flex items-center justify-between" data-v-ce7215b7><label class="text-xs font-medium text-gray-700 w-20" data-v-ce7215b7>Gender</label><select class="flex-1 ml-2 px-2 py-1.5 border border-gray-300 rounded text-sm focus:border-blue-500 focus:outline-none" data-v-ce7215b7><option value="" data-v-ce7215b7${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.gender) ? ssrLooseContain(patientForm.value.gender, "") : ssrLooseEqual(patientForm.value.gender, "")) ? " selected" : ""}>Select</option><option value="male" data-v-ce7215b7${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.gender) ? ssrLooseContain(patientForm.value.gender, "male") : ssrLooseEqual(patientForm.value.gender, "male")) ? " selected" : ""}>Male</option><option value="female" data-v-ce7215b7${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.gender) ? ssrLooseContain(patientForm.value.gender, "female") : ssrLooseEqual(patientForm.value.gender, "female")) ? " selected" : ""}>Female</option></select></div><div class="flex items-center justify-between" data-v-ce7215b7><label class="text-xs font-medium text-gray-700 w-20" data-v-ce7215b7>Card type</label><select class="flex-1 ml-2 px-2 py-1.5 border border-gray-300 rounded text-sm focus:border-blue-500 focus:outline-none" data-v-ce7215b7><option value="Cash" data-v-ce7215b7${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.cardType) ? ssrLooseContain(patientForm.value.cardType, "Cash") : ssrLooseEqual(patientForm.value.cardType, "Cash")) ? " selected" : ""}>Cash</option><option value="Card" data-v-ce7215b7${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.cardType) ? ssrLooseContain(patientForm.value.cardType, "Card") : ssrLooseEqual(patientForm.value.cardType, "Card")) ? " selected" : ""}>Card</option></select></div><div class="flex items-center justify-between" data-v-ce7215b7><label class="text-xs font-medium text-gray-700 w-20" data-v-ce7215b7>Pay Mode</label><select class="flex-1 ml-2 px-2 py-1.5 border border-gray-300 rounded text-sm focus:border-blue-500 focus:outline-none" data-v-ce7215b7><option value="Cash" data-v-ce7215b7${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.payMode) ? ssrLooseContain(patientForm.value.payMode, "Cash") : ssrLooseEqual(patientForm.value.payMode, "Cash")) ? " selected" : ""}>Cash</option><option value="Card" data-v-ce7215b7${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.payMode) ? ssrLooseContain(patientForm.value.payMode, "Card") : ssrLooseEqual(patientForm.value.payMode, "Card")) ? " selected" : ""}>Card</option></select></div><div class="flex items-center justify-between" data-v-ce7215b7><label class="text-xs font-medium text-gray-700 w-20" data-v-ce7215b7>Card type</label><select class="flex-1 ml-2 px-2 py-1.5 border border-gray-300 rounded text-sm focus:border-blue-500 focus:outline-none" data-v-ce7215b7><option value="" data-v-ce7215b7${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.cardType2) ? ssrLooseContain(patientForm.value.cardType2, "") : ssrLooseEqual(patientForm.value.cardType2, "")) ? " selected" : ""}>Select</option></select></div></div></div><div data-v-ce7215b7><div class="bg-teal-600 text-white px-4 py-2 text-sm font-semibold rounded-t" data-v-ce7215b7> TOTAL SUMMARY </div><div class="border border-gray-300 border-t-0 rounded-b p-3 bg-white space-y-2" data-v-ce7215b7><div class="flex justify-between items-center" data-v-ce7215b7><label class="text-xs font-medium text-gray-700" data-v-ce7215b7>Total:</label><input${ssrRenderAttr("value", summary.value.total)} type="number" step="0.01" class="w-24 px-2 py-1 border border-gray-300 rounded text-xs bg-yellow-100 text-right font-semibold" readonly data-v-ce7215b7></div><div class="flex justify-between items-center" data-v-ce7215b7><label class="text-xs font-medium text-gray-700" data-v-ce7215b7>Corp. Due Amt.</label><input${ssrRenderAttr("value", summary.value.corpDueAmt)} type="number" step="0.01" class="w-24 px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-right" readonly data-v-ce7215b7></div><div class="flex justify-between items-center" data-v-ce7215b7><label class="text-xs font-medium text-gray-700" data-v-ce7215b7>Pvt./PC Amt.</label><input${ssrRenderAttr("value", summary.value.pvtPcAmt)} type="number" step="0.01" class="w-24 px-2 py-1 border border-gray-300 rounded text-xs bg-green-200 text-right font-semibold" readonly data-v-ce7215b7></div><div class="flex justify-between items-center" data-v-ce7215b7><label class="text-xs font-medium text-gray-700" data-v-ce7215b7>Paid Amt.</label><input${ssrRenderAttr("value", summary.value.paidAmt)} type="number" step="0.01" class="w-24 px-2 py-1 border border-gray-300 rounded text-xs bg-yellow-100 text-right font-semibold" data-v-ce7215b7></div><div class="flex justify-between items-center" data-v-ce7215b7><label class="text-xs font-medium text-gray-700" data-v-ce7215b7>Change Amt.</label><input${ssrRenderAttr("value", summary.value.changeAmt)} type="number" step="0.01" class="w-24 px-2 py-1 border border-gray-300 rounded text-xs bg-red-100 text-right" readonly data-v-ce7215b7></div><div class="flex justify-between items-center" data-v-ce7215b7><label class="text-xs font-medium text-gray-700" data-v-ce7215b7>Receiving Amt.</label><input${ssrRenderAttr("value", summary.value.receivingAmt)} type="number" step="0.01" class="w-24 px-2 py-1 border border-gray-300 rounded text-xs bg-red-100 text-right" readonly data-v-ce7215b7></div><div class="flex justify-between items-center" data-v-ce7215b7><label class="text-xs font-medium text-gray-700" data-v-ce7215b7>Delivery Date</label><div class="flex" data-v-ce7215b7><input${ssrRenderAttr("value", summary.value.deliveryDate)} type="text" class="w-32 px-2 py-1 border border-gray-300 rounded-l text-xs bg-yellow-100" data-v-ce7215b7><button class="px-2 py-1 bg-gray-200 border border-l-0 border-gray-300 rounded-r text-xs hover:bg-gray-300" data-v-ce7215b7> 📅 </button></div></div><div data-v-ce7215b7><label class="block text-xs font-medium text-gray-700 mb-1" data-v-ce7215b7>Remarks</label><textarea placeholder="Enter remarks (If any)" class="w-full px-2 py-1 border border-gray-300 rounded text-xs h-12 resize-none focus:border-blue-500 focus:outline-none" data-v-ce7215b7>${ssrInterpolate(summary.value.remarks)}</textarea></div></div></div><div data-v-ce7215b7><div class="bg-teal-600 text-white px-4 py-2 text-sm font-semibold rounded-t" data-v-ce7215b7> COMMISSION FOR PC </div><div class="border border-gray-300 border-t-0 rounded-b p-3 bg-white space-y-3" data-v-ce7215b7><div class="flex justify-between items-center" data-v-ce7215b7><label class="text-xs font-medium text-gray-700" data-v-ce7215b7>Total</label><input${ssrRenderAttr("value", commission.value.total)} type="number" step="0.01" class="w-20 px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-right" readonly data-v-ce7215b7></div><div class="flex justify-between items-center" data-v-ce7215b7><label class="text-xs font-medium text-gray-700" data-v-ce7215b7>Phy./Syst. Amt</label><input${ssrRenderAttr("value", commission.value.physystAmt)} type="number" step="0.01" class="w-20 px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-right" readonly data-v-ce7215b7></div><div class="mt-4" data-v-ce7215b7><input type="range"${ssrRenderAttr("value", commission.value.slider)} min="0" max="100" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer slider" data-v-ce7215b7></div></div></div></div><div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-300" data-v-ce7215b7><button class="px-4 py-2 text-gray-600 hover:text-gray-800 border border-gray-300 rounded-md text-sm font-medium" data-v-ce7215b7> Cancel </button><button class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-medium" data-v-ce7215b7> Save Bill </button></div></div></div></div>`);
      } else {
        _push(`<!---->`);
      }
    };
  }
};
const _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/BillingModal.vue");
  return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
const BillingModal = /* @__PURE__ */ _export_sfc(_sfc_main$1, [["__scopeId", "data-v-ce7215b7"]]);
const Billing_vue_vue_type_style_index_0_scoped_6a2bc034_lang = "";
const _sfc_main = {
  __name: "Billing",
  __ssrInlineRender: true,
  props: {
    filters: Object
  },
  setup(__props) {
    var _a, _b;
    useForm({
      case_id: ""
    });
    const showAddBillModal = ref(false);
    const searchResults = ref(null);
    const isLoading = ref(false);
    const errorMessage = ref("");
    let searchTimeout = null;
    const billingTypes = [
      {
        id: "appointment",
        name: "Appointment",
        icon: "📅",
        route: "backend.appoinment.create"
      },
      {
        id: "opd",
        name: "OPD",
        icon: "🩺",
        route: "backend.opdpatient.create"
      },
      {
        id: "pathology",
        name: "Pathology",
        icon: "🧪",
        route: "backend.pathology.create"
      },
      {
        id: "radiology",
        name: "Radiology",
        icon: "🔬",
        route: "backend.radiology.create"
      },
      {
        id: "blood_issue",
        name: "Blood Issue",
        icon: "🩸",
        route: "backend.bloodissue.create"
      },
      // {
      //     id: 'blood_component',
      //     name: 'Blood Component Issue',
      //     icon: '🩸',
      //     route: 'backend.bloodcomponentissue.create'
      // },
      {
        id: "pharmacy",
        name: "Pharmacy",
        icon: "💊",
        route: "backend.pharmacybill.create"
      }
    ];
    const navigateToBillingType = (type) => {
      router.visit(route(type.route));
    };
    let props = __props;
    const filters = ref({
      case_id: ((_a = props.filters) == null ? void 0 : _a.case_id) ?? "",
      numOfData: ((_b = props.filters) == null ? void 0 : _b.numOfData) ?? 10
    });
    const debouncedSearch = () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        if (filters.value.case_id.trim().length >= 2) {
          performSearch();
        } else if (filters.value.case_id.trim().length === 0) {
          searchResults.value = null;
          errorMessage.value = "";
        }
      }, 300);
    };
    const performSearch = async () => {
      var _a2, _b2;
      if (!filters.value.case_id.trim()) {
        errorMessage.value = "Please enter a Case ID";
        searchResults.value = null;
        return;
      }
      isLoading.value = true;
      errorMessage.value = "";
      try {
        const response = await axios.get(route("backend.billing.search"), {
          params: { case_id: filters.value.case_id }
        });
        searchResults.value = response.data;
      } catch (error) {
        errorMessage.value = ((_b2 = (_a2 = error.response) == null ? void 0 : _a2.data) == null ? void 0 : _b2.message) || "Failed to search. Please try again.";
        searchResults.value = null;
      } finally {
        isLoading.value = false;
      }
    };
    const searchByCase = async () => {
      await performSearch();
    };
    watch(() => filters.value.case_id, () => {
      debouncedSearch();
    });
    const closeAddBillModal = () => {
      showAddBillModal.value = false;
    };
    const handleBillSave = (billData) => {
      console.log("Bill saved:", billData);
      showAddBillModal.value = false;
      alert("Bill saved successfully!");
    };
    const openAddBillButton = () => {
      router.visit(route("backend.billing.view"));
    };
    const openListBillButton = () => {
      router.visit(route("backend.billing.list"));
    };
    const selectBillingTypeFromModal = (type) => {
      closeAddBillModal();
      navigateToBillingType(type);
    };
    const clearSearch = () => {
      filters.value.case_id = "";
      searchResults.value = null;
      errorMessage.value = "";
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$2, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="w-full" data-v-6a2bc034${_scopeId}><div class="grid grid-cols-1 lg:grid-cols-2 gap-2" data-v-6a2bc034${_scopeId}><div data-v-6a2bc034${_scopeId}><div class="bg-white border border-gray-200 rounded-md shadow-sm dark:bg-slate-900 dark:border-gray-700 h-full" data-v-6a2bc034${_scopeId}><div class="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center flex-wrap" data-v-6a2bc034${_scopeId}><h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200" data-v-6a2bc034${_scopeId}>Single Module Billing </h2><div class="flex gap-2" data-v-6a2bc034${_scopeId}><button class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors duration-200 flex items-center gap-2" data-v-6a2bc034${_scopeId}><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-v-6a2bc034${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" data-v-6a2bc034${_scopeId}></path></svg> Bill List </button><button class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors duration-200 flex items-center gap-2" data-v-6a2bc034${_scopeId}><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-v-6a2bc034${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" data-v-6a2bc034${_scopeId}></path></svg> Add Bill </button></div></div><div class="p-6" data-v-6a2bc034${_scopeId}><div class="grid grid-cols-1 sm:grid-cols-3 gap-4" data-v-6a2bc034${_scopeId}><!--[-->`);
            ssrRenderList(billingTypes, (type) => {
              _push2(`<div class="group cursor-pointer border rounded-lg p-6 text-center transition-all duration-200 hover:shadow-md hover:border-blue-300 border-gray-200 dark:border-gray-600 bg-white dark:bg-slate-800" data-v-6a2bc034${_scopeId}><div class="text-3xl mb-3" data-v-6a2bc034${_scopeId}>${ssrInterpolate(type.icon)}</div><h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-2" data-v-6a2bc034${_scopeId}>${ssrInterpolate(type.name)}</h3></div>`);
            });
            _push2(`<!--]--></div></div></div></div><div data-v-6a2bc034${_scopeId}><div class="bg-white border border-gray-200 rounded-md shadow-sm dark:bg-slate-900 dark:border-gray-700 h-full" data-v-6a2bc034${_scopeId}><div class="p-4 border-b border-gray-200 dark:border-gray-700" data-v-6a2bc034${_scopeId}><h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200" data-v-6a2bc034${_scopeId}>OPD/IPD Billing Through Case Id</h2></div><div class="p-6" data-v-6a2bc034${_scopeId}><form class="space-y-4" data-v-6a2bc034${_scopeId}><div data-v-6a2bc034${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "case_id",
              value: "Case ID"
            }, null, _parent2, _scopeId));
            _push2(`<div class="flex mt-2" data-v-6a2bc034${_scopeId}><div class="relative flex-1" data-v-6a2bc034${_scopeId}><input id="case_id" type="text" placeholder="Enter Case ID "${ssrRenderAttr("value", filters.value.case_id)} class="block w-full px-3 py-2 pr-8 text-sm rounded-l-md border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-slate-200 focus:border-blue-500 focus:outline-none" autocomplete="off" data-v-6a2bc034${_scopeId}>`);
            if (filters.value.case_id) {
              _push2(`<button type="button" class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" data-v-6a2bc034${_scopeId}><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" data-v-6a2bc034${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" data-v-6a2bc034${_scopeId}></path></svg></button>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div><button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-r-md hover:bg-blue-700 transition-colors duration-200 flex items-center"${ssrIncludeBooleanAttr(isLoading.value) ? " disabled" : ""} data-v-6a2bc034${_scopeId}>`);
            if (isLoading.value) {
              _push2(`<span data-v-6a2bc034${_scopeId}>🔍 Searching...</span>`);
            } else {
              _push2(`<span data-v-6a2bc034${_scopeId}>🔍 Search</span>`);
            }
            _push2(`</button></div>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: errorMessage.value
            }, null, _parent2, _scopeId));
            _push2(`</div></form>`);
            if (isLoading.value) {
              _push2(`<div class="mt-4 text-center py-4" data-v-6a2bc034${_scopeId}><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto" data-v-6a2bc034${_scopeId}></div><p class="mt-2 text-gray-600 dark:text-gray-300" data-v-6a2bc034${_scopeId}>Searching...</p></div>`);
            } else if (searchResults.value) {
              _push2(`<div class="mt-6" data-v-6a2bc034${_scopeId}><div class="flex justify-between items-center mb-4" data-v-6a2bc034${_scopeId}><h3 class="text-md font-semibold text-gray-800 dark:text-gray-200" data-v-6a2bc034${_scopeId}>Search Results </h3><span class="text-sm text-gray-500 dark:text-gray-400" data-v-6a2bc034${_scopeId}>${ssrInterpolate(searchResults.value.length)} result(s) found </span></div>`);
              if (searchResults.value.length === 0) {
                _push2(`<div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4 dark:bg-yellow-900/20 dark:border-yellow-500" data-v-6a2bc034${_scopeId}><div class="flex" data-v-6a2bc034${_scopeId}><div class="flex-shrink-0" data-v-6a2bc034${_scopeId}><svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" data-v-6a2bc034${_scopeId}><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" data-v-6a2bc034${_scopeId}></path></svg></div><div class="ml-3" data-v-6a2bc034${_scopeId}><p class="text-sm text-yellow-700 dark:text-yellow-300" data-v-6a2bc034${_scopeId}> No records found for Case ID: &quot;${ssrInterpolate(filters.value.case_id)}&quot; </p></div></div></div>`);
              } else {
                _push2(`<div class="overflow-x-auto" data-v-6a2bc034${_scopeId}><table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700" data-v-6a2bc034${_scopeId}><thead class="bg-gray-50 dark:bg-slate-800" data-v-6a2bc034${_scopeId}><tr data-v-6a2bc034${_scopeId}><th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider" data-v-6a2bc034${_scopeId}> Case ID</th><th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider" data-v-6a2bc034${_scopeId}> Patient</th><th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider" data-v-6a2bc034${_scopeId}> Payment Status</th><th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider" data-v-6a2bc034${_scopeId}> Action</th></tr></thead><tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-gray-700" data-v-6a2bc034${_scopeId}><!--[-->`);
                ssrRenderList(searchResults.value, (result) => {
                  _push2(`<tr class="hover:bg-gray-50 dark:hover:bg-slate-700" data-v-6a2bc034${_scopeId}><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200" data-v-6a2bc034${_scopeId}>${ssrInterpolate(result.case_number)}</td><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200" data-v-6a2bc034${_scopeId}>${ssrInterpolate(result.patient_name)} <br data-v-6a2bc034${_scopeId}><span class="text-xs text-gray-500" data-v-6a2bc034${_scopeId}>(${ssrInterpolate(result.patient_mobile)})</span></td><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200" data-v-6a2bc034${_scopeId}>${ssrInterpolate(result.payment_status)} <br data-v-6a2bc034${_scopeId}></td><td class="px-6 py-4 whitespace-nowrap text-sm font-medium" data-v-6a2bc034${_scopeId}>`);
                  if (result.payment_status == "Partial" || result.payment_status == "Pending") {
                    _push2(`<button class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300" data-v-6a2bc034${_scopeId}> Edit </button>`);
                  } else {
                    _push2(`<!---->`);
                  }
                  _push2(`<a${ssrRenderAttr("href", _ctx.route("backend.download.invoice", { id: result.id, module: "billing" }))} target="_blank" class="inline-block text-white bg-teal-500 rounded p-1 hover:text-black-900 dark:text-white dark:hover:text-white" data-v-6a2bc034${_scopeId}> Invoice </a></td></tr>`);
                });
                _push2(`<!--]--></tbody></table></div>`);
              }
              _push2(`</div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div></div></div></div></div>`);
            _push2(ssrRenderComponent(BillingModal, {
              show: showAddBillModal.value,
              onClose: closeAddBillModal,
              onSave: handleBillSave,
              onSelectBillingType: selectBillingTypeFromModal
            }, null, _parent2, _scopeId));
          } else {
            return [
              createVNode("div", { class: "w-full" }, [
                createVNode("div", { class: "grid grid-cols-1 lg:grid-cols-2 gap-2" }, [
                  createVNode("div", null, [
                    createVNode("div", { class: "bg-white border border-gray-200 rounded-md shadow-sm dark:bg-slate-900 dark:border-gray-700 h-full" }, [
                      createVNode("div", { class: "p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center flex-wrap" }, [
                        createVNode("h2", { class: "text-lg font-semibold text-gray-800 dark:text-gray-200" }, "Single Module Billing "),
                        createVNode("div", { class: "flex gap-2" }, [
                          createVNode("button", {
                            onClick: openListBillButton,
                            class: "px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors duration-200 flex items-center gap-2"
                          }, [
                            (openBlock(), createBlock("svg", {
                              xmlns: "http://www.w3.org/2000/svg",
                              class: "h-5 w-5",
                              fill: "none",
                              viewBox: "0 0 24 24",
                              stroke: "currentColor"
                            }, [
                              createVNode("path", {
                                "stroke-linecap": "round",
                                "stroke-linejoin": "round",
                                "stroke-width": "2",
                                d: "M4 6h16M4 10h16M4 14h16M4 18h16"
                              })
                            ])),
                            createTextVNode(" Bill List ")
                          ]),
                          createVNode("button", {
                            onClick: openAddBillButton,
                            class: "px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors duration-200 flex items-center gap-2"
                          }, [
                            (openBlock(), createBlock("svg", {
                              xmlns: "http://www.w3.org/2000/svg",
                              class: "h-5 w-5",
                              fill: "none",
                              viewBox: "0 0 24 24",
                              stroke: "currentColor"
                            }, [
                              createVNode("path", {
                                "stroke-linecap": "round",
                                "stroke-linejoin": "round",
                                "stroke-width": "2",
                                d: "M12 4v16m8-8H4"
                              })
                            ])),
                            createTextVNode(" Add Bill ")
                          ])
                        ])
                      ]),
                      createVNode("div", { class: "p-6" }, [
                        createVNode("div", { class: "grid grid-cols-1 sm:grid-cols-3 gap-4" }, [
                          (openBlock(), createBlock(Fragment, null, renderList(billingTypes, (type) => {
                            return createVNode("div", {
                              key: type.id,
                              onClick: ($event) => navigateToBillingType(type),
                              class: "group cursor-pointer border rounded-lg p-6 text-center transition-all duration-200 hover:shadow-md hover:border-blue-300 border-gray-200 dark:border-gray-600 bg-white dark:bg-slate-800"
                            }, [
                              createVNode("div", { class: "text-3xl mb-3" }, toDisplayString(type.icon), 1),
                              createVNode("h3", { class: "font-semibold text-gray-800 dark:text-gray-200 mb-2" }, toDisplayString(type.name), 1)
                            ], 8, ["onClick"]);
                          }), 64))
                        ])
                      ])
                    ])
                  ]),
                  createVNode("div", null, [
                    createVNode("div", { class: "bg-white border border-gray-200 rounded-md shadow-sm dark:bg-slate-900 dark:border-gray-700 h-full" }, [
                      createVNode("div", { class: "p-4 border-b border-gray-200 dark:border-gray-700" }, [
                        createVNode("h2", { class: "text-lg font-semibold text-gray-800 dark:text-gray-200" }, "OPD/IPD Billing Through Case Id")
                      ]),
                      createVNode("div", { class: "p-6" }, [
                        createVNode("form", {
                          onSubmit: withModifiers(searchByCase, ["prevent"]),
                          class: "space-y-4"
                        }, [
                          createVNode("div", null, [
                            createVNode(_sfc_main$3, {
                              for: "case_id",
                              value: "Case ID"
                            }),
                            createVNode("div", { class: "flex mt-2" }, [
                              createVNode("div", { class: "relative flex-1" }, [
                                withDirectives(createVNode("input", {
                                  id: "case_id",
                                  type: "text",
                                  placeholder: "Enter Case ID ",
                                  "onUpdate:modelValue": ($event) => filters.value.case_id = $event,
                                  class: "block w-full px-3 py-2 pr-8 text-sm rounded-l-md border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-slate-200 focus:border-blue-500 focus:outline-none",
                                  autocomplete: "off"
                                }, null, 8, ["onUpdate:modelValue"]), [
                                  [vModelText, filters.value.case_id]
                                ]),
                                filters.value.case_id ? (openBlock(), createBlock("button", {
                                  key: 0,
                                  type: "button",
                                  onClick: clearSearch,
                                  class: "absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
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
                                ])) : createCommentVNode("", true)
                              ]),
                              createVNode("button", {
                                type: "submit",
                                class: "px-4 py-2 bg-blue-600 text-white text-sm rounded-r-md hover:bg-blue-700 transition-colors duration-200 flex items-center",
                                disabled: isLoading.value
                              }, [
                                isLoading.value ? (openBlock(), createBlock("span", { key: 0 }, "🔍 Searching...")) : (openBlock(), createBlock("span", { key: 1 }, "🔍 Search"))
                              ], 8, ["disabled"])
                            ]),
                            createVNode(_sfc_main$4, {
                              class: "mt-2",
                              message: errorMessage.value
                            }, null, 8, ["message"])
                          ])
                        ], 32),
                        isLoading.value ? (openBlock(), createBlock("div", {
                          key: 0,
                          class: "mt-4 text-center py-4"
                        }, [
                          createVNode("div", { class: "animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto" }),
                          createVNode("p", { class: "mt-2 text-gray-600 dark:text-gray-300" }, "Searching...")
                        ])) : searchResults.value ? (openBlock(), createBlock("div", {
                          key: 1,
                          class: "mt-6"
                        }, [
                          createVNode("div", { class: "flex justify-between items-center mb-4" }, [
                            createVNode("h3", { class: "text-md font-semibold text-gray-800 dark:text-gray-200" }, "Search Results "),
                            createVNode("span", { class: "text-sm text-gray-500 dark:text-gray-400" }, toDisplayString(searchResults.value.length) + " result(s) found ", 1)
                          ]),
                          searchResults.value.length === 0 ? (openBlock(), createBlock("div", {
                            key: 0,
                            class: "bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4 dark:bg-yellow-900/20 dark:border-yellow-500"
                          }, [
                            createVNode("div", { class: "flex" }, [
                              createVNode("div", { class: "flex-shrink-0" }, [
                                (openBlock(), createBlock("svg", {
                                  class: "h-5 w-5 text-yellow-400",
                                  xmlns: "http://www.w3.org/2000/svg",
                                  viewBox: "0 0 20 20",
                                  fill: "currentColor"
                                }, [
                                  createVNode("path", {
                                    "fill-rule": "evenodd",
                                    d: "M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z",
                                    "clip-rule": "evenodd"
                                  })
                                ]))
                              ]),
                              createVNode("div", { class: "ml-3" }, [
                                createVNode("p", { class: "text-sm text-yellow-700 dark:text-yellow-300" }, ' No records found for Case ID: "' + toDisplayString(filters.value.case_id) + '" ', 1)
                              ])
                            ])
                          ])) : (openBlock(), createBlock("div", {
                            key: 1,
                            class: "overflow-x-auto"
                          }, [
                            createVNode("table", { class: "min-w-full divide-y divide-gray-200 dark:divide-gray-700" }, [
                              createVNode("thead", { class: "bg-gray-50 dark:bg-slate-800" }, [
                                createVNode("tr", null, [
                                  createVNode("th", {
                                    scope: "col",
                                    class: "px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                  }, " Case ID"),
                                  createVNode("th", {
                                    scope: "col",
                                    class: "px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                  }, " Patient"),
                                  createVNode("th", {
                                    scope: "col",
                                    class: "px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                  }, " Payment Status"),
                                  createVNode("th", {
                                    scope: "col",
                                    class: "px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                  }, " Action")
                                ])
                              ]),
                              createVNode("tbody", { class: "bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-gray-700" }, [
                                (openBlock(true), createBlock(Fragment, null, renderList(searchResults.value, (result) => {
                                  return openBlock(), createBlock("tr", {
                                    key: result.id,
                                    class: "hover:bg-gray-50 dark:hover:bg-slate-700"
                                  }, [
                                    createVNode("td", { class: "px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200" }, toDisplayString(result.case_number), 1),
                                    createVNode("td", { class: "px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200" }, [
                                      createTextVNode(toDisplayString(result.patient_name) + " ", 1),
                                      createVNode("br"),
                                      createVNode("span", { class: "text-xs text-gray-500" }, "(" + toDisplayString(result.patient_mobile) + ")", 1)
                                    ]),
                                    createVNode("td", { class: "px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200" }, [
                                      createTextVNode(toDisplayString(result.payment_status) + " ", 1),
                                      createVNode("br")
                                    ]),
                                    createVNode("td", { class: "px-6 py-4 whitespace-nowrap text-sm font-medium" }, [
                                      result.payment_status == "Partial" || result.payment_status == "Pending" ? (openBlock(), createBlock("button", {
                                        key: 0,
                                        onClick: ($event) => unref(router).visit(_ctx.route("backend.billing.edit", { id: result.id })),
                                        class: "text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                      }, " Edit ", 8, ["onClick"])) : createCommentVNode("", true),
                                      createVNode("a", {
                                        href: _ctx.route("backend.download.invoice", { id: result.id, module: "billing" }),
                                        target: "_blank",
                                        class: "inline-block text-white bg-teal-500 rounded p-1 hover:text-black-900 dark:text-white dark:hover:text-white"
                                      }, " Invoice ", 8, ["href"])
                                    ])
                                  ]);
                                }), 128))
                              ])
                            ])
                          ]))
                        ])) : createCommentVNode("", true)
                      ])
                    ])
                  ])
                ])
              ]),
              createVNode(BillingModal, {
                show: showAddBillModal.value,
                onClose: closeAddBillModal,
                onSave: handleBillSave,
                onSelectBillingType: selectBillingTypeFromModal
              }, null, 8, ["show"])
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/Billing/Billing.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const Billing = /* @__PURE__ */ _export_sfc(_sfc_main, [["__scopeId", "data-v-6a2bc034"]]);
export {
  Billing as default
};
