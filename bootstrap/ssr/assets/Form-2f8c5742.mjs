import { computed, onMounted, ref, withCtx, unref, createTextVNode, toDisplayString, createVNode, openBlock, createBlock, withModifiers, withDirectives, Fragment, renderList, vModelSelect, vModelText, vModelCheckbox, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderList, ssrRenderAttr } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import { useForm, router } from "@inertiajs/vue3";
import { _ as _sfc_main$2 } from "./InputError-6aeb8d97.mjs";
import "./InputLabel-70ca52d1.mjs";
import { _ as _sfc_main$3 } from "./PrimaryButton-b82fb16e.mjs";
import { A as AlertMessage } from "./AlertMessage-0f422981.mjs";
import { d as displayResponse, a as displayWarning } from "./responseMessage-d505224b.mjs";
import { P as PatientModal } from "./PatientModal-85d06e3d.mjs";
import { _ as _export_sfc } from "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "mitt";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
import "toastr";
import "sweetalert2";
import "date-fns";
const Form_vue_vue_type_style_index_0_scoped_1cea881e_lang = "";
const _sfc_main = {
  __name: "Form",
  __ssrInlineRender: true,
  props: ["bloodissue", "id", "patients", "doctors"],
  setup(__props) {
    var _a, _b, _c, _d, _e, _f, _g, _h, _i, _j, _k, _l, _m, _n, _o, _p, _q, _r, _s, _t, _u, _v;
    const props = __props;
    const form = useForm({
      case_id: ((_a = props.bloodissue) == null ? void 0 : _a.case_id) ?? "",
      patient_id: ((_b = props.bloodissue) == null ? void 0 : _b.patient_id) ?? "",
      issue_date: ((_c = props.bloodissue) == null ? void 0 : _c.issue_date) ?? "",
      doctor_id: ((_d = props.bloodissue) == null ? void 0 : _d.doctor_id) ?? "",
      reference_name: ((_e = props.bloodissue) == null ? void 0 : _e.reference_name) ?? "",
      technician: ((_f = props.bloodissue) == null ? void 0 : _f.technician) ?? "",
      blood_group: ((_g = props.bloodissue) == null ? void 0 : _g.blood_group) ?? "",
      bag: ((_h = props.bloodissue) == null ? void 0 : _h.bag) ?? "",
      charge_category: ((_i = props.bloodissue) == null ? void 0 : _i.charge_category) ?? "",
      charge_name: ((_j = props.bloodissue) == null ? void 0 : _j.charge_name) ?? "",
      standard_charge: parseFloat((_k = props.bloodissue) == null ? void 0 : _k.standard_charge) || 0,
      note: ((_l = props.bloodissue) == null ? void 0 : _l.note) ?? "",
      total: parseFloat((_m = props.bloodissue) == null ? void 0 : _m.total) || 0,
      discount: parseFloat((_n = props.bloodissue) == null ? void 0 : _n.discount) || 0,
      discount_percent: parseFloat((_o = props.bloodissue) == null ? void 0 : _o.discount_percent) || 0,
      tax: parseFloat((_p = props.bloodissue) == null ? void 0 : _p.tax) || 0,
      tax_percent: parseFloat((_q = props.bloodissue) == null ? void 0 : _q.tax_percent) || 0,
      net_amount: parseFloat((_r = props.bloodissue) == null ? void 0 : _r.net_amount) || 0,
      payment_mode: ((_s = props.bloodissue) == null ? void 0 : _s.payment_mode) ?? "Cash",
      payment_amount: parseFloat((_t = props.bloodissue) == null ? void 0 : _t.payment_amount) || 0,
      apply_tpa: Boolean((_u = props.bloodissue) == null ? void 0 : _u.apply_tpa) || false,
      _method: ((_v = props.bloodissue) == null ? void 0 : _v.id) ? "put" : "post"
    });
    const calculatedTotal = computed(() => {
      return parseFloat(form.standard_charge) || 0;
    });
    const calculatedDiscount = computed(() => {
      const total = calculatedTotal.value;
      const discountPercent = parseFloat(form.discount_percent) || 0;
      return total * discountPercent / 100;
    });
    const calculatedTax = computed(() => {
      const total = calculatedTotal.value;
      const discount = calculatedDiscount.value;
      const taxPercent = parseFloat(form.tax_percent) || 0;
      return (total - discount) * taxPercent / 100;
    });
    const calculatedNetAmount = computed(() => {
      const total = calculatedTotal.value;
      const discount = calculatedDiscount.value;
      const tax = calculatedTax.value;
      return total - discount + tax;
    });
    const updateCalculations = () => {
      form.total = calculatedTotal.value;
      form.discount = calculatedDiscount.value;
      form.tax = calculatedTax.value;
      form.net_amount = calculatedNetAmount.value;
    };
    const handleStandardChargeChange = () => {
      updateCalculations();
    };
    const handleDiscountPercentChange = () => {
      updateCalculations();
    };
    const handleTaxPercentChange = () => {
      updateCalculations();
    };
    const submit = () => {
      updateCalculations();
      const routeName = props.id ? route("backend.bloodissue.update", props.id) : route("backend.bloodissue.store");
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
    onMounted(() => {
      updateCalculations();
    });
    const isPatientModalOpen = ref(false);
    const patientsList = ref([...props.patients]);
    const openPatientModal = () => {
      isPatientModalOpen.value = true;
    };
    const closePatientModal = () => {
      isPatientModalOpen.value = false;
    };
    const handlePatientCreated = (newPatient) => {
      patientsList.value.push(newPatient);
      form.patient_id = newPatient.id;
      router.reload({
        only: ["patients"],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
          patientsList.value = [...page.props.patients];
        }
      });
    };
    const goToBloodIssueList = () => {
      router.get(route("backend.bloodissue.index"));
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(PatientModal, {
              isOpen: isPatientModalOpen.value,
              tpas: props.tpas,
              onClose: closePatientModal,
              onPatientCreated: handlePatientCreated
            }, null, _parent2, _scopeId));
            _push2(`<div class="w-full p-2 transition duration-1000 ease-in-out transform bg-white border rounded-md dark:bg-slate-500" data-v-1cea881e${_scopeId}><div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md" data-v-1cea881e${_scopeId}><div data-v-1cea881e${_scopeId}><h1 class="p-4 text-xl font-bold dark:text-white" data-v-1cea881e${_scopeId}>${ssrInterpolate(_ctx.$page.props.pageTitle)}</h1></div><div class="p-2 py-2 flex items-center space-x-2" data-v-1cea881e${_scopeId}><div class="flex items-center space-x-3" data-v-1cea881e${_scopeId}><button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2" data-v-1cea881e${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" data-v-1cea881e${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" data-v-1cea881e${_scopeId}></path></svg> Blood Issue List </button></div></div></div><form class="spaced-form" data-v-1cea881e${_scopeId}>`);
            _push2(ssrRenderComponent(AlertMessage, null, null, _parent2, _scopeId));
            _push2(`<div class="grid grid-cols-12 gap-3 items-end" data-v-1cea881e${_scopeId}><div class="col-span-4" data-v-1cea881e${_scopeId}><label class="block text-xs font-medium text-gray-700 mb-1" data-v-1cea881e${_scopeId}>Select Patient</label><div class="flex" data-v-1cea881e${_scopeId}><select class="flex-1 px-2 py-1.5 border border-gray-300 rounded-l-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs" data-v-1cea881e${_scopeId}><option value="" data-v-1cea881e${ssrIncludeBooleanAttr(Array.isArray(unref(form).patient_id) ? ssrLooseContain(unref(form).patient_id, "") : ssrLooseEqual(unref(form).patient_id, "")) ? " selected" : ""}${_scopeId}>Select Patient</option><!--[-->`);
            ssrRenderList(__props.patients, (data) => {
              _push2(`<option${ssrRenderAttr("value", data.id)} data-v-1cea881e${ssrIncludeBooleanAttr(Array.isArray(unref(form).patient_id) ? ssrLooseContain(unref(form).patient_id, data.id) : ssrLooseEqual(unref(form).patient_id, data.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(data.name)}</option>`);
            });
            _push2(`<!--]--></select><button type="button" class="px-2 py-1.5 bg-green-500 text-white rounded-r-md hover:bg-green-600 text-xs flex items-center gap-1" data-v-1cea881e${_scopeId}><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" data-v-1cea881e${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" data-v-1cea881e${_scopeId}></path></svg> New </button></div>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-0.5",
              message: unref(form).errors.patient_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-3" data-v-1cea881e${_scopeId}><label class="block text-xs font-medium text-gray-700 mb-1" data-v-1cea881e${_scopeId}>Case ID</label><div class="flex" data-v-1cea881e${_scopeId}><input${ssrRenderAttr("value", unref(form).case_id)} type="text" placeholder="Case ID" class="flex-1 px-2 py-1.5 border border-gray-300 rounded-l-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs" data-v-1cea881e${_scopeId}><button type="button" class="px-2 py-1.5 bg-gray-100 border border-l-0 border-gray-300 rounded-r-md hover:bg-gray-200" data-v-1cea881e${_scopeId}><svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" data-v-1cea881e${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" data-v-1cea881e${_scopeId}></path></svg></button></div>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-0.5",
              message: unref(form).errors.case_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-2" data-v-1cea881e${_scopeId}><div class="flex items-center h-8" data-v-1cea881e${_scopeId}><input${ssrIncludeBooleanAttr(Array.isArray(unref(form).apply_tpa) ? ssrLooseContain(unref(form).apply_tpa, null) : unref(form).apply_tpa) ? " checked" : ""} type="checkbox" id="apply_tpa" class="w-3 h-3 text-blue-600 border-gray-300 rounded focus:ring-blue-500" data-v-1cea881e${_scopeId}><label for="apply_tpa" class="ml-1 text-xs font-medium text-gray-700" data-v-1cea881e${_scopeId}>Apply TPA</label></div></div></div><div class="grid grid-cols-4 gap-3" data-v-1cea881e${_scopeId}><div data-v-1cea881e${_scopeId}><label class="block text-xs font-medium text-gray-700 mb-1" data-v-1cea881e${_scopeId}> Issue Date <span class="text-red-500" data-v-1cea881e${_scopeId}>*</span></label><input${ssrRenderAttr("value", unref(form).issue_date)} type="date" placeholder="mm/dd/yyyy" class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs" data-v-1cea881e${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-0.5",
              message: unref(form).errors.issue_date
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-1cea881e${_scopeId}><label class="block text-xs font-medium text-gray-700 mb-1" data-v-1cea881e${_scopeId}>Hospital Doctor</label><select class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs" data-v-1cea881e${_scopeId}><option value="" data-v-1cea881e${ssrIncludeBooleanAttr(Array.isArray(unref(form).doctor_id) ? ssrLooseContain(unref(form).doctor_id, "") : ssrLooseEqual(unref(form).doctor_id, "")) ? " selected" : ""}${_scopeId}>Select</option><!--[-->`);
            ssrRenderList(__props.doctors, (data) => {
              _push2(`<option${ssrRenderAttr("value", data.id)} data-v-1cea881e${ssrIncludeBooleanAttr(Array.isArray(unref(form).doctor_id) ? ssrLooseContain(unref(form).doctor_id, data.id) : ssrLooseEqual(unref(form).doctor_id, data.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(data.name)}</option>`);
            });
            _push2(`<!--]--></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-0.5",
              message: unref(form).errors.doctor_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-1cea881e${_scopeId}><label class="block text-xs font-medium text-gray-700 mb-1" data-v-1cea881e${_scopeId}> Reference Name <span class="text-red-500" data-v-1cea881e${_scopeId}>*</span></label><input${ssrRenderAttr("value", unref(form).reference_name)} type="text" placeholder="Reference Name" class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs" data-v-1cea881e${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-0.5",
              message: unref(form).errors.reference_name
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-1cea881e${_scopeId}><label class="block text-xs font-medium text-gray-700 mb-1" data-v-1cea881e${_scopeId}>Technician</label><input${ssrRenderAttr("value", unref(form).technician)} type="text" placeholder="Technician" class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs" data-v-1cea881e${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-0.5",
              message: unref(form).errors.technician
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="grid grid-cols-4 gap-3" data-v-1cea881e${_scopeId}><div data-v-1cea881e${_scopeId}><label class="block text-xs font-medium text-gray-700 mb-1" data-v-1cea881e${_scopeId}>Blood Group</label><select class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs" data-v-1cea881e${_scopeId}><option value="" data-v-1cea881e${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "") : ssrLooseEqual(unref(form).blood_group, "")) ? " selected" : ""}${_scopeId}>Select</option><option value="A+" data-v-1cea881e${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "A+") : ssrLooseEqual(unref(form).blood_group, "A+")) ? " selected" : ""}${_scopeId}>A+</option><option value="A-" data-v-1cea881e${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "A-") : ssrLooseEqual(unref(form).blood_group, "A-")) ? " selected" : ""}${_scopeId}>A-</option><option value="B+" data-v-1cea881e${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "B+") : ssrLooseEqual(unref(form).blood_group, "B+")) ? " selected" : ""}${_scopeId}>B+</option><option value="B-" data-v-1cea881e${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "B-") : ssrLooseEqual(unref(form).blood_group, "B-")) ? " selected" : ""}${_scopeId}>B-</option><option value="AB+" data-v-1cea881e${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "AB+") : ssrLooseEqual(unref(form).blood_group, "AB+")) ? " selected" : ""}${_scopeId}>AB+</option><option value="AB-" data-v-1cea881e${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "AB-") : ssrLooseEqual(unref(form).blood_group, "AB-")) ? " selected" : ""}${_scopeId}>AB-</option><option value="O+" data-v-1cea881e${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "O+") : ssrLooseEqual(unref(form).blood_group, "O+")) ? " selected" : ""}${_scopeId}>O+</option><option value="O-" data-v-1cea881e${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "O-") : ssrLooseEqual(unref(form).blood_group, "O-")) ? " selected" : ""}${_scopeId}>O-</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-0.5",
              message: unref(form).errors.blood_group
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-1cea881e${_scopeId}><label class="block text-xs font-medium text-gray-700 mb-1" data-v-1cea881e${_scopeId}> Bag <span class="text-red-500" data-v-1cea881e${_scopeId}>*</span></label><select class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs" data-v-1cea881e${_scopeId}><option value="" data-v-1cea881e${ssrIncludeBooleanAttr(Array.isArray(unref(form).bag) ? ssrLooseContain(unref(form).bag, "") : ssrLooseEqual(unref(form).bag, "")) ? " selected" : ""}${_scopeId}>Select</option><option value="bag1" data-v-1cea881e${ssrIncludeBooleanAttr(Array.isArray(unref(form).bag) ? ssrLooseContain(unref(form).bag, "bag1") : ssrLooseEqual(unref(form).bag, "bag1")) ? " selected" : ""}${_scopeId}>Bag 001</option><option value="bag2" data-v-1cea881e${ssrIncludeBooleanAttr(Array.isArray(unref(form).bag) ? ssrLooseContain(unref(form).bag, "bag2") : ssrLooseEqual(unref(form).bag, "bag2")) ? " selected" : ""}${_scopeId}>Bag 002</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-0.5",
              message: unref(form).errors.bag
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-1cea881e${_scopeId}><label class="block text-xs font-medium text-gray-700 mb-1" data-v-1cea881e${_scopeId}> Charge Category <span class="text-red-500" data-v-1cea881e${_scopeId}>*</span></label><select class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs" data-v-1cea881e${_scopeId}><option value="" data-v-1cea881e${ssrIncludeBooleanAttr(Array.isArray(unref(form).charge_category) ? ssrLooseContain(unref(form).charge_category, "") : ssrLooseEqual(unref(form).charge_category, "")) ? " selected" : ""}${_scopeId}>Select</option><option value="category1" data-v-1cea881e${ssrIncludeBooleanAttr(Array.isArray(unref(form).charge_category) ? ssrLooseContain(unref(form).charge_category, "category1") : ssrLooseEqual(unref(form).charge_category, "category1")) ? " selected" : ""}${_scopeId}>Blood Transfusion</option><option value="category2" data-v-1cea881e${ssrIncludeBooleanAttr(Array.isArray(unref(form).charge_category) ? ssrLooseContain(unref(form).charge_category, "category2") : ssrLooseEqual(unref(form).charge_category, "category2")) ? " selected" : ""}${_scopeId}>Blood Test</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-0.5",
              message: unref(form).errors.charge_category
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-1cea881e${_scopeId}><label class="block text-xs font-medium text-gray-700 mb-1" data-v-1cea881e${_scopeId}> Charge Name <span class="text-red-500" data-v-1cea881e${_scopeId}>*</span></label><select class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs" data-v-1cea881e${_scopeId}><option value="" data-v-1cea881e${ssrIncludeBooleanAttr(Array.isArray(unref(form).charge_name) ? ssrLooseContain(unref(form).charge_name, "") : ssrLooseEqual(unref(form).charge_name, "")) ? " selected" : ""}${_scopeId}>Select</option><option value="charge1" data-v-1cea881e${ssrIncludeBooleanAttr(Array.isArray(unref(form).charge_name) ? ssrLooseContain(unref(form).charge_name, "charge1") : ssrLooseEqual(unref(form).charge_name, "charge1")) ? " selected" : ""}${_scopeId}>Standard Charge</option><option value="charge2" data-v-1cea881e${ssrIncludeBooleanAttr(Array.isArray(unref(form).charge_name) ? ssrLooseContain(unref(form).charge_name, "charge2") : ssrLooseEqual(unref(form).charge_name, "charge2")) ? " selected" : ""}${_scopeId}>Premium Charge</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-0.5",
              message: unref(form).errors.charge_name
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="grid grid-cols-12 gap-3" data-v-1cea881e${_scopeId}><div class="col-span-4" data-v-1cea881e${_scopeId}><label class="block text-xs font-medium text-gray-700 mb-1" data-v-1cea881e${_scopeId}>Standard Charge (Tk.)</label><input${ssrRenderAttr("value", unref(form).standard_charge)} type="number" step="0.01" placeholder="0" class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs" data-v-1cea881e${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-0.5",
              message: unref(form).errors.standard_charge
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-8" data-v-1cea881e${_scopeId}><label class="block text-xs font-medium text-gray-700 mb-1" data-v-1cea881e${_scopeId}>Note</label><textarea rows="2" placeholder="Enter note here..." class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs resize-none" data-v-1cea881e${_scopeId}>${ssrInterpolate(unref(form).note)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-0.5",
              message: unref(form).errors.note
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="grid grid-cols-12 gap-3" data-v-1cea881e${_scopeId}><div class="col-span-4" data-v-1cea881e${_scopeId}></div><div class="col-span-8 space-y-2" data-v-1cea881e${_scopeId}><div class="flex justify-between items-center py-1" data-v-1cea881e${_scopeId}><span class="text-xs font-medium text-gray-700" data-v-1cea881e${_scopeId}>Total (Tk.)</span><span class="text-sm font-semibold" data-v-1cea881e${_scopeId}>${ssrInterpolate((parseFloat(unref(form).total) || 0).toFixed(2))}</span></div><div class="flex justify-between items-center py-1" data-v-1cea881e${_scopeId}><span class="text-xs font-medium text-gray-700" data-v-1cea881e${_scopeId}>Discount (Tk.)</span><div class="flex items-center gap-2" data-v-1cea881e${_scopeId}><input${ssrRenderAttr("value", unref(form).discount_percent)} type="number" step="0.01" placeholder="0" class="w-12 px-1 py-1 border border-gray-300 rounded text-xs text-center" data-v-1cea881e${_scopeId}><span class="text-xs" data-v-1cea881e${_scopeId}>%</span><span class="text-sm font-semibold min-w-[60px] text-right" data-v-1cea881e${_scopeId}>${ssrInterpolate((parseFloat(unref(form).discount) || 0).toFixed(2))}</span></div></div><div class="flex justify-between items-center py-1" data-v-1cea881e${_scopeId}><span class="text-xs font-medium text-gray-700" data-v-1cea881e${_scopeId}>Tax (Tk.)</span><div class="flex items-center gap-2" data-v-1cea881e${_scopeId}><input${ssrRenderAttr("value", unref(form).tax_percent)} type="number" step="0.01" placeholder="0" class="w-12 px-1 py-1 border border-gray-300 rounded text-xs text-center" data-v-1cea881e${_scopeId}><span class="text-xs" data-v-1cea881e${_scopeId}>%</span><span class="text-sm font-semibold min-w-[60px] text-right" data-v-1cea881e${_scopeId}>${ssrInterpolate((parseFloat(unref(form).tax) || 0).toFixed(2))}</span></div></div><div class="flex justify-between items-center py-2 border-t border-gray-300" data-v-1cea881e${_scopeId}><span class="text-sm font-semibold text-gray-700" data-v-1cea881e${_scopeId}>Net Amount (Tk.)</span><span class="text-base font-bold" data-v-1cea881e${_scopeId}>${ssrInterpolate((parseFloat(unref(form).net_amount) || 0).toFixed(2))}</span></div><div class="flex justify-between items-center py-1" data-v-1cea881e${_scopeId}><span class="text-xs font-medium text-gray-700" data-v-1cea881e${_scopeId}>Payment Mode</span><select class="px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs min-w-[100px]" data-v-1cea881e${_scopeId}><option value="Cash" data-v-1cea881e${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, "Cash") : ssrLooseEqual(unref(form).payment_mode, "Cash")) ? " selected" : ""}${_scopeId}>Cash</option><option value="Card" data-v-1cea881e${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, "Card") : ssrLooseEqual(unref(form).payment_mode, "Card")) ? " selected" : ""}${_scopeId}>Card</option><option value="Cheque" data-v-1cea881e${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, "Cheque") : ssrLooseEqual(unref(form).payment_mode, "Cheque")) ? " selected" : ""}${_scopeId}>Cheque</option></select></div><div class="flex justify-between items-center py-1" data-v-1cea881e${_scopeId}><span class="text-xs font-medium text-gray-700" data-v-1cea881e${_scopeId}> Payment Amount (Tk.) <span class="text-red-500" data-v-1cea881e${_scopeId}>*</span></span><input${ssrRenderAttr("value", unref(form).payment_amount)} type="number" step="0.01" placeholder="0" class="px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs min-w-[100px] text-right" data-v-1cea881e${_scopeId}></div></div></div><div class="flex justify-end pt-3" data-v-1cea881e${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              type: "submit",
              class: ["px-6 py-1.5 bg-gray-800 text-white rounded-md hover:bg-gray-900 text-xs font-medium", { "opacity-25": unref(form).processing }],
              disabled: unref(form).processing
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`${ssrInterpolate(props.id ?? false ? "UPDATE" : "CREATE")}`);
                } else {
                  return [
                    createTextVNode(toDisplayString(props.id ?? false ? "UPDATE" : "CREATE"), 1)
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</div></form></div>`);
          } else {
            return [
              createVNode(PatientModal, {
                isOpen: isPatientModalOpen.value,
                tpas: props.tpas,
                onClose: closePatientModal,
                onPatientCreated: handlePatientCreated
              }, null, 8, ["isOpen", "tpas"]),
              createVNode("div", { class: "w-full p-2 transition duration-1000 ease-in-out transform bg-white border rounded-md dark:bg-slate-500" }, [
                createVNode("div", { class: "flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md" }, [
                  createVNode("div", null, [
                    createVNode("h1", { class: "p-4 text-xl font-bold dark:text-white" }, toDisplayString(_ctx.$page.props.pageTitle), 1)
                  ]),
                  createVNode("div", { class: "p-2 py-2 flex items-center space-x-2" }, [
                    createVNode("div", { class: "flex items-center space-x-3" }, [
                      createVNode("button", {
                        onClick: goToBloodIssueList,
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
                        createTextVNode(" Blood Issue List ")
                      ])
                    ])
                  ])
                ]),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"]),
                  class: "spaced-form"
                }, [
                  createVNode(AlertMessage),
                  createVNode("div", { class: "grid grid-cols-12 gap-3 items-end" }, [
                    createVNode("div", { class: "col-span-4" }, [
                      createVNode("label", { class: "block text-xs font-medium text-gray-700 mb-1" }, "Select Patient"),
                      createVNode("div", { class: "flex" }, [
                        withDirectives(createVNode("select", {
                          "onUpdate:modelValue": ($event) => unref(form).patient_id = $event,
                          class: "flex-1 px-2 py-1.5 border border-gray-300 rounded-l-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs"
                        }, [
                          createVNode("option", { value: "" }, "Select Patient"),
                          (openBlock(true), createBlock(Fragment, null, renderList(__props.patients, (data) => {
                            return openBlock(), createBlock("option", {
                              key: data.id,
                              value: data.id
                            }, toDisplayString(data.name), 9, ["value"]);
                          }), 128))
                        ], 8, ["onUpdate:modelValue"]), [
                          [vModelSelect, unref(form).patient_id]
                        ]),
                        createVNode("button", {
                          type: "button",
                          onClick: openPatientModal,
                          class: "px-2 py-1.5 bg-green-500 text-white rounded-r-md hover:bg-green-600 text-xs flex items-center gap-1"
                        }, [
                          (openBlock(), createBlock("svg", {
                            class: "w-3 h-3",
                            fill: "none",
                            stroke: "currentColor",
                            viewBox: "0 0 24 24"
                          }, [
                            createVNode("path", {
                              "stroke-linecap": "round",
                              "stroke-linejoin": "round",
                              "stroke-width": "2",
                              d: "M12 4v16m8-8H4"
                            })
                          ])),
                          createTextVNode(" New ")
                        ])
                      ]),
                      createVNode(_sfc_main$2, {
                        class: "mt-0.5",
                        message: unref(form).errors.patient_id
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-3" }, [
                      createVNode("label", { class: "block text-xs font-medium text-gray-700 mb-1" }, "Case ID"),
                      createVNode("div", { class: "flex" }, [
                        withDirectives(createVNode("input", {
                          "onUpdate:modelValue": ($event) => unref(form).case_id = $event,
                          type: "text",
                          placeholder: "Case ID",
                          class: "flex-1 px-2 py-1.5 border border-gray-300 rounded-l-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).case_id]
                        ]),
                        createVNode("button", {
                          type: "button",
                          class: "px-2 py-1.5 bg-gray-100 border border-l-0 border-gray-300 rounded-r-md hover:bg-gray-200"
                        }, [
                          (openBlock(), createBlock("svg", {
                            class: "w-3 h-3 text-gray-500",
                            fill: "none",
                            stroke: "currentColor",
                            viewBox: "0 0 24 24"
                          }, [
                            createVNode("path", {
                              "stroke-linecap": "round",
                              "stroke-linejoin": "round",
                              "stroke-width": "2",
                              d: "M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                            })
                          ]))
                        ])
                      ]),
                      createVNode(_sfc_main$2, {
                        class: "mt-0.5",
                        message: unref(form).errors.case_id
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-2" }, [
                      createVNode("div", { class: "flex items-center h-8" }, [
                        withDirectives(createVNode("input", {
                          "onUpdate:modelValue": ($event) => unref(form).apply_tpa = $event,
                          type: "checkbox",
                          id: "apply_tpa",
                          class: "w-3 h-3 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelCheckbox, unref(form).apply_tpa]
                        ]),
                        createVNode("label", {
                          for: "apply_tpa",
                          class: "ml-1 text-xs font-medium text-gray-700"
                        }, "Apply TPA")
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "grid grid-cols-4 gap-3" }, [
                    createVNode("div", null, [
                      createVNode("label", { class: "block text-xs font-medium text-gray-700 mb-1" }, [
                        createTextVNode(" Issue Date "),
                        createVNode("span", { class: "text-red-500" }, "*")
                      ]),
                      withDirectives(createVNode("input", {
                        "onUpdate:modelValue": ($event) => unref(form).issue_date = $event,
                        type: "date",
                        placeholder: "mm/dd/yyyy",
                        class: "w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).issue_date]
                      ]),
                      createVNode(_sfc_main$2, {
                        class: "mt-0.5",
                        message: unref(form).errors.issue_date
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode("label", { class: "block text-xs font-medium text-gray-700 mb-1" }, "Hospital Doctor"),
                      withDirectives(createVNode("select", {
                        "onUpdate:modelValue": ($event) => unref(form).doctor_id = $event,
                        class: "w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs"
                      }, [
                        createVNode("option", { value: "" }, "Select"),
                        (openBlock(true), createBlock(Fragment, null, renderList(__props.doctors, (data) => {
                          return openBlock(), createBlock("option", {
                            key: data.id,
                            value: data.id
                          }, toDisplayString(data.name), 9, ["value"]);
                        }), 128))
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).doctor_id]
                      ]),
                      createVNode(_sfc_main$2, {
                        class: "mt-0.5",
                        message: unref(form).errors.doctor_id
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode("label", { class: "block text-xs font-medium text-gray-700 mb-1" }, [
                        createTextVNode(" Reference Name "),
                        createVNode("span", { class: "text-red-500" }, "*")
                      ]),
                      withDirectives(createVNode("input", {
                        "onUpdate:modelValue": ($event) => unref(form).reference_name = $event,
                        type: "text",
                        placeholder: "Reference Name",
                        class: "w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).reference_name]
                      ]),
                      createVNode(_sfc_main$2, {
                        class: "mt-0.5",
                        message: unref(form).errors.reference_name
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode("label", { class: "block text-xs font-medium text-gray-700 mb-1" }, "Technician"),
                      withDirectives(createVNode("input", {
                        "onUpdate:modelValue": ($event) => unref(form).technician = $event,
                        type: "text",
                        placeholder: "Technician",
                        class: "w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).technician]
                      ]),
                      createVNode(_sfc_main$2, {
                        class: "mt-0.5",
                        message: unref(form).errors.technician
                      }, null, 8, ["message"])
                    ])
                  ]),
                  createVNode("div", { class: "grid grid-cols-4 gap-3" }, [
                    createVNode("div", null, [
                      createVNode("label", { class: "block text-xs font-medium text-gray-700 mb-1" }, "Blood Group"),
                      withDirectives(createVNode("select", {
                        "onUpdate:modelValue": ($event) => unref(form).blood_group = $event,
                        class: "w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs"
                      }, [
                        createVNode("option", { value: "" }, "Select"),
                        createVNode("option", { value: "A+" }, "A+"),
                        createVNode("option", { value: "A-" }, "A-"),
                        createVNode("option", { value: "B+" }, "B+"),
                        createVNode("option", { value: "B-" }, "B-"),
                        createVNode("option", { value: "AB+" }, "AB+"),
                        createVNode("option", { value: "AB-" }, "AB-"),
                        createVNode("option", { value: "O+" }, "O+"),
                        createVNode("option", { value: "O-" }, "O-")
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).blood_group]
                      ]),
                      createVNode(_sfc_main$2, {
                        class: "mt-0.5",
                        message: unref(form).errors.blood_group
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode("label", { class: "block text-xs font-medium text-gray-700 mb-1" }, [
                        createTextVNode(" Bag "),
                        createVNode("span", { class: "text-red-500" }, "*")
                      ]),
                      withDirectives(createVNode("select", {
                        "onUpdate:modelValue": ($event) => unref(form).bag = $event,
                        class: "w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs"
                      }, [
                        createVNode("option", { value: "" }, "Select"),
                        createVNode("option", { value: "bag1" }, "Bag 001"),
                        createVNode("option", { value: "bag2" }, "Bag 002")
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).bag]
                      ]),
                      createVNode(_sfc_main$2, {
                        class: "mt-0.5",
                        message: unref(form).errors.bag
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode("label", { class: "block text-xs font-medium text-gray-700 mb-1" }, [
                        createTextVNode(" Charge Category "),
                        createVNode("span", { class: "text-red-500" }, "*")
                      ]),
                      withDirectives(createVNode("select", {
                        "onUpdate:modelValue": ($event) => unref(form).charge_category = $event,
                        class: "w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs"
                      }, [
                        createVNode("option", { value: "" }, "Select"),
                        createVNode("option", { value: "category1" }, "Blood Transfusion"),
                        createVNode("option", { value: "category2" }, "Blood Test")
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).charge_category]
                      ]),
                      createVNode(_sfc_main$2, {
                        class: "mt-0.5",
                        message: unref(form).errors.charge_category
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode("label", { class: "block text-xs font-medium text-gray-700 mb-1" }, [
                        createTextVNode(" Charge Name "),
                        createVNode("span", { class: "text-red-500" }, "*")
                      ]),
                      withDirectives(createVNode("select", {
                        "onUpdate:modelValue": ($event) => unref(form).charge_name = $event,
                        class: "w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs"
                      }, [
                        createVNode("option", { value: "" }, "Select"),
                        createVNode("option", { value: "charge1" }, "Standard Charge"),
                        createVNode("option", { value: "charge2" }, "Premium Charge")
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).charge_name]
                      ]),
                      createVNode(_sfc_main$2, {
                        class: "mt-0.5",
                        message: unref(form).errors.charge_name
                      }, null, 8, ["message"])
                    ])
                  ]),
                  createVNode("div", { class: "grid grid-cols-12 gap-3" }, [
                    createVNode("div", { class: "col-span-4" }, [
                      createVNode("label", { class: "block text-xs font-medium text-gray-700 mb-1" }, "Standard Charge (Tk.)"),
                      withDirectives(createVNode("input", {
                        "onUpdate:modelValue": ($event) => unref(form).standard_charge = $event,
                        type: "number",
                        step: "0.01",
                        placeholder: "0",
                        onInput: handleStandardChargeChange,
                        class: "w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs"
                      }, null, 40, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).standard_charge]
                      ]),
                      createVNode(_sfc_main$2, {
                        class: "mt-0.5",
                        message: unref(form).errors.standard_charge
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-8" }, [
                      createVNode("label", { class: "block text-xs font-medium text-gray-700 mb-1" }, "Note"),
                      withDirectives(createVNode("textarea", {
                        "onUpdate:modelValue": ($event) => unref(form).note = $event,
                        rows: "2",
                        placeholder: "Enter note here...",
                        class: "w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs resize-none"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).note]
                      ]),
                      createVNode(_sfc_main$2, {
                        class: "mt-0.5",
                        message: unref(form).errors.note
                      }, null, 8, ["message"])
                    ])
                  ]),
                  createVNode("div", { class: "grid grid-cols-12 gap-3" }, [
                    createVNode("div", { class: "col-span-4" }),
                    createVNode("div", { class: "col-span-8 space-y-2" }, [
                      createVNode("div", { class: "flex justify-between items-center py-1" }, [
                        createVNode("span", { class: "text-xs font-medium text-gray-700" }, "Total (Tk.)"),
                        createVNode("span", { class: "text-sm font-semibold" }, toDisplayString((parseFloat(unref(form).total) || 0).toFixed(2)), 1)
                      ]),
                      createVNode("div", { class: "flex justify-between items-center py-1" }, [
                        createVNode("span", { class: "text-xs font-medium text-gray-700" }, "Discount (Tk.)"),
                        createVNode("div", { class: "flex items-center gap-2" }, [
                          withDirectives(createVNode("input", {
                            "onUpdate:modelValue": ($event) => unref(form).discount_percent = $event,
                            type: "number",
                            step: "0.01",
                            placeholder: "0",
                            onInput: handleDiscountPercentChange,
                            class: "w-12 px-1 py-1 border border-gray-300 rounded text-xs text-center"
                          }, null, 40, ["onUpdate:modelValue"]), [
                            [vModelText, unref(form).discount_percent]
                          ]),
                          createVNode("span", { class: "text-xs" }, "%"),
                          createVNode("span", { class: "text-sm font-semibold min-w-[60px] text-right" }, toDisplayString((parseFloat(unref(form).discount) || 0).toFixed(2)), 1)
                        ])
                      ]),
                      createVNode("div", { class: "flex justify-between items-center py-1" }, [
                        createVNode("span", { class: "text-xs font-medium text-gray-700" }, "Tax (Tk.)"),
                        createVNode("div", { class: "flex items-center gap-2" }, [
                          withDirectives(createVNode("input", {
                            "onUpdate:modelValue": ($event) => unref(form).tax_percent = $event,
                            type: "number",
                            step: "0.01",
                            placeholder: "0",
                            onInput: handleTaxPercentChange,
                            class: "w-12 px-1 py-1 border border-gray-300 rounded text-xs text-center"
                          }, null, 40, ["onUpdate:modelValue"]), [
                            [vModelText, unref(form).tax_percent]
                          ]),
                          createVNode("span", { class: "text-xs" }, "%"),
                          createVNode("span", { class: "text-sm font-semibold min-w-[60px] text-right" }, toDisplayString((parseFloat(unref(form).tax) || 0).toFixed(2)), 1)
                        ])
                      ]),
                      createVNode("div", { class: "flex justify-between items-center py-2 border-t border-gray-300" }, [
                        createVNode("span", { class: "text-sm font-semibold text-gray-700" }, "Net Amount (Tk.)"),
                        createVNode("span", { class: "text-base font-bold" }, toDisplayString((parseFloat(unref(form).net_amount) || 0).toFixed(2)), 1)
                      ]),
                      createVNode("div", { class: "flex justify-between items-center py-1" }, [
                        createVNode("span", { class: "text-xs font-medium text-gray-700" }, "Payment Mode"),
                        withDirectives(createVNode("select", {
                          "onUpdate:modelValue": ($event) => unref(form).payment_mode = $event,
                          class: "px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs min-w-[100px]"
                        }, [
                          createVNode("option", { value: "Cash" }, "Cash"),
                          createVNode("option", { value: "Card" }, "Card"),
                          createVNode("option", { value: "Cheque" }, "Cheque")
                        ], 8, ["onUpdate:modelValue"]), [
                          [vModelSelect, unref(form).payment_mode]
                        ])
                      ]),
                      createVNode("div", { class: "flex justify-between items-center py-1" }, [
                        createVNode("span", { class: "text-xs font-medium text-gray-700" }, [
                          createTextVNode(" Payment Amount (Tk.) "),
                          createVNode("span", { class: "text-red-500" }, "*")
                        ]),
                        withDirectives(createVNode("input", {
                          "onUpdate:modelValue": ($event) => unref(form).payment_amount = $event,
                          type: "number",
                          step: "0.01",
                          placeholder: "0",
                          class: "px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs min-w-[100px] text-right"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).payment_amount]
                        ])
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "flex justify-end pt-3" }, [
                    createVNode(_sfc_main$3, {
                      type: "submit",
                      class: ["px-6 py-1.5 bg-gray-800 text-white rounded-md hover:bg-gray-900 text-xs font-medium", { "opacity-25": unref(form).processing }],
                      disabled: unref(form).processing
                    }, {
                      default: withCtx(() => [
                        createTextVNode(toDisplayString(props.id ?? false ? "UPDATE" : "CREATE"), 1)
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/BloodIssue/Form.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const Form = /* @__PURE__ */ _export_sfc(_sfc_main, [["__scopeId", "data-v-1cea881e"]]);
export {
  Form as default
};
