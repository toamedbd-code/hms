import { computed, watch, ref, withCtx, unref, createTextVNode, toDisplayString, createVNode, openBlock, createBlock, withModifiers, withDirectives, vModelSelect, vModelText, vModelCheckbox, Fragment, renderList, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderAttr, ssrRenderList } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import { useForm, router } from "@inertiajs/vue3";
import { _ as _sfc_main$2 } from "./InputError-6aeb8d97.mjs";
import { _ as _sfc_main$3 } from "./InputLabel-70ca52d1.mjs";
import { _ as _sfc_main$4 } from "./PrimaryButton-b82fb16e.mjs";
import "./AlertMessage-0f422981.mjs";
import { d as displayResponse, a as displayWarning } from "./responseMessage-d505224b.mjs";
import { P as PatientModal } from "./PatientModal-85d06e3d.mjs";
import Multiselect from "vue-multiselect";
/* empty css                           */import { _ as _export_sfc } from "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "mitt";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
import "toastr";
import "sweetalert2";
import "date-fns";
const Form_vue_vue_type_style_index_0_scoped_ffaf9d12_lang = "";
const _sfc_main = {
  __name: "Form",
  __ssrInlineRender: true,
  props: ["opdpatient", "id", "patients", "doctors", "chargeTypes", "charges"],
  setup(__props) {
    var _a, _b, _c, _d, _e, _f, _g, _h, _i, _j, _k, _l, _m, _n, _o, _p, _q, _r, _s, _t, _u, _v, _w, _x, _y, _z;
    const props = __props;
    const form = useForm({
      patient_id: ((_a = props.opdpatient) == null ? void 0 : _a.patient_id) ? { id: props.opdpatient.patient_id, name: (_b = props.opdpatient.patient) == null ? void 0 : _b.name } : "",
      consultant_doctor_id: ((_c = props.opdpatient) == null ? void 0 : _c.consultant_doctor_id) ? { id: props.opdpatient.consultant_doctor_id, name: (_d = props.opdpatient.doctor) == null ? void 0 : _d.name } : "",
      // Left side fields
      symptom_type: ((_e = props.opdpatient) == null ? void 0 : _e.symptom_type) ?? "",
      symptom_title: ((_f = props.opdpatient) == null ? void 0 : _f.symptom_title) ?? "",
      symptom_description: ((_g = props.opdpatient) == null ? void 0 : _g.symptom_description) ?? "",
      note: ((_h = props.opdpatient) == null ? void 0 : _h.note) ?? "",
      allergies: ((_i = props.opdpatient) == null ? void 0 : _i.allergies) ?? "",
      // Right side fields
      appointment_date: ((_j = props.opdpatient) == null ? void 0 : _j.appointment_date) ?? "",
      case: ((_k = props.opdpatient) == null ? void 0 : _k.case) ?? "",
      casualty: ((_l = props.opdpatient) == null ? void 0 : _l.casualty) ?? "no",
      old_patient: ((_m = props.opdpatient) == null ? void 0 : _m.old_patient) ?? "no",
      reference: ((_n = props.opdpatient) == null ? void 0 : _n.reference) ?? "",
      apply_tpa: Boolean((_o = props.opdpatient) == null ? void 0 : _o.apply_tpa) || false,
      charge_id: ((_p = props.opdpatient) == null ? void 0 : _p.charge_id) ?? "",
      charge_type_id: ((_q = props.opdpatient) == null ? void 0 : _q.charge_type_id) ?? "",
      applied_charge: ((_r = props.opdpatient) == null ? void 0 : _r.applied_charge) ?? 0,
      standard_charge: ((_s = props.opdpatient) == null ? void 0 : _s.standard_charge) ?? 0,
      tax: ((_t = props.opdpatient) == null ? void 0 : _t.tax) ?? 0,
      discount: ((_u = props.opdpatient) == null ? void 0 : _u.discount) ?? 0,
      payment_mode: ((_v = props.opdpatient) == null ? void 0 : _v.payment_mode) ?? "cash",
      amount: ((_w = props.opdpatient) == null ? void 0 : _w.amount) ?? 0,
      live_consultation: ((_x = props.opdpatient) == null ? void 0 : _x.live_consultation) ?? "no",
      paid_amount: ((_y = props.opdpatient) == null ? void 0 : _y.paid_amount) ?? 0,
      _method: ((_z = props.opdpatient) == null ? void 0 : _z.id) ? "put" : "post"
    });
    const filteredCharges = computed(() => {
      if (!form.charge_type_id) {
        return [];
      }
      return props.charges.filter((charge) => charge.charge_type_id == form.charge_type_id);
    });
    const calculateAmount = () => {
      const appliedCharge = parseFloat(form.applied_charge) || 0;
      const tax = parseFloat(form.tax) || 0;
      const discount = parseFloat(form.discount) || 0;
      const taxAmount = appliedCharge * tax / 100;
      const discountAmount = appliedCharge * discount / 100;
      const totalAmount = appliedCharge + taxAmount - discountAmount;
      form.amount = parseFloat(totalAmount.toFixed(2));
    };
    watch(() => form.charge_type_id, (newChargeType) => {
      form.charge_id = "";
      form.standard_charge = 0;
      form.applied_charge = 0;
      form.tax = 0;
      calculateAmount();
    });
    watch(() => form.charge_id, (newChargeId) => {
      if (newChargeId) {
        const selectedCharge = props.charges.find((charge) => charge.id == newChargeId);
        if (selectedCharge) {
          form.standard_charge = selectedCharge.standard_charge || 0;
          form.applied_charge = selectedCharge.standard_charge || 0;
          form.tax = selectedCharge.tax || 0;
          calculateAmount();
        }
      } else {
        form.standard_charge = 0;
        form.applied_charge = 0;
        form.tax = 0;
        calculateAmount();
      }
    });
    watch([() => form.applied_charge, () => form.tax, () => form.discount], () => {
      calculateAmount();
    });
    const submit = () => {
      const routeName = props.id ? route("backend.opdpatient.update", props.id) : route("backend.opdpatient.store");
      form.transform((data) => {
        var _a2, _b2;
        return {
          ...data,
          patient_id: ((_a2 = data.patient_id) == null ? void 0 : _a2.id) || data.patient_id,
          consultant_doctor_id: ((_b2 = data.consultant_doctor_id) == null ? void 0 : _b2.id) || data.consultant_doctor_id,
          remember: "",
          isDirty: false
        };
      }).post(routeName, {
        onSuccess: (response) => {
          var _a2, _b2, _c2, _d2;
          if (!props.id) {
            form.reset();
          }
          const successMessage = (_b2 = (_a2 = response == null ? void 0 : response.props) == null ? void 0 : _a2.flash) == null ? void 0 : _b2.successMessage;
          const billId = (_d2 = (_c2 = response == null ? void 0 : response.props) == null ? void 0 : _c2.flash) == null ? void 0 : _d2.billId;
          if (successMessage && billId) {
            window.open(route("backend.download.opd.bill", { id: billId, module: "opd" }), "_blank");
          }
          displayResponse(response);
        },
        onError: (errorObject) => {
          displayWarning(errorObject);
        }
      });
    };
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
    const goToOpdList = () => {
      router.visit(route("backend.opdpatient.index"));
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<!--[-->`);
      _push(ssrRenderComponent(PatientModal, {
        isOpen: isPatientModalOpen.value,
        tpas: props.tpas,
        onClose: closePatientModal,
        onPatientCreated: handlePatientCreated
      }, null, _parent));
      _push(ssrRenderComponent(_sfc_main$1, null, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md" data-v-ffaf9d12${_scopeId}><div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md" data-v-ffaf9d12${_scopeId}><div data-v-ffaf9d12${_scopeId}><h1 class="p-4 text-xl font-bold" data-v-ffaf9d12${_scopeId}>${ssrInterpolate(_ctx.$page.props.pageTitle)}</h1></div><div class="flex items-center p-3 py-2 space-x-1" data-v-ffaf9d12${_scopeId}><div class="relative min-w-[280px]" data-v-ffaf9d12${_scopeId}><div class="relative" data-v-ffaf9d12${_scopeId}><div class="col-span-1" data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(unref(Multiselect), {
              modelValue: unref(form).patient_id,
              "onUpdate:modelValue": ($event) => unref(form).patient_id = $event,
              options: __props.patients,
              "track-by": "id",
              label: "name",
              placeholder: "Search and select a patient",
              class: "w-full text-sm h-[30px] rounded-md border border-slate-300"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.patient_id
            }, null, _parent2, _scopeId));
            _push2(`</div></div></div><div class="flex items-center space-x-1" data-v-ffaf9d12${_scopeId}><button class="px-3 py-2.5 text-sm text-white bg-green-600 rounded hover:bg-green-700 transition-colors" data-v-ffaf9d12${_scopeId}> + New Patient </button></div><div class="p-2 py-2 flex items-center space-x-2" data-v-ffaf9d12${_scopeId}><div class="flex items-center space-x-3" data-v-ffaf9d12${_scopeId}><button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2" data-v-ffaf9d12${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" data-v-ffaf9d12${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" data-v-ffaf9d12${_scopeId}></path></svg> Opd Patient List </button></div></div></div></div><form class="p-4" data-v-ffaf9d12${_scopeId}><div class="grid grid-cols-1 gap-6 lg:grid-cols-2" data-v-ffaf9d12${_scopeId}><div class="space-y-3" data-v-ffaf9d12${_scopeId}><div class="grid grid-cols-1 gap-3 md:grid-cols-2" data-v-ffaf9d12${_scopeId}><div data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "symptom_type",
              value: "Symptoms Type"
            }, null, _parent2, _scopeId));
            _push2(`<select id="symptom_type" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" data-v-ffaf9d12${_scopeId}><option value="" data-v-ffaf9d12${ssrIncludeBooleanAttr(Array.isArray(unref(form).symptom_type) ? ssrLooseContain(unref(form).symptom_type, "") : ssrLooseEqual(unref(form).symptom_type, "")) ? " selected" : ""}${_scopeId}>Select</option><option value="fever" data-v-ffaf9d12${ssrIncludeBooleanAttr(Array.isArray(unref(form).symptom_type) ? ssrLooseContain(unref(form).symptom_type, "fever") : ssrLooseEqual(unref(form).symptom_type, "fever")) ? " selected" : ""}${_scopeId}>Fever</option><option value="cough" data-v-ffaf9d12${ssrIncludeBooleanAttr(Array.isArray(unref(form).symptom_type) ? ssrLooseContain(unref(form).symptom_type, "cough") : ssrLooseEqual(unref(form).symptom_type, "cough")) ? " selected" : ""}${_scopeId}>Cough</option><option value="headache" data-v-ffaf9d12${ssrIncludeBooleanAttr(Array.isArray(unref(form).symptom_type) ? ssrLooseContain(unref(form).symptom_type, "headache") : ssrLooseEqual(unref(form).symptom_type, "headache")) ? " selected" : ""}${_scopeId}>Headache</option><option value="body_pain" data-v-ffaf9d12${ssrIncludeBooleanAttr(Array.isArray(unref(form).symptom_type) ? ssrLooseContain(unref(form).symptom_type, "body_pain") : ssrLooseEqual(unref(form).symptom_type, "body_pain")) ? " selected" : ""}${_scopeId}>Body Pain</option><option value="other" data-v-ffaf9d12${ssrIncludeBooleanAttr(Array.isArray(unref(form).symptom_type) ? ssrLooseContain(unref(form).symptom_type, "other") : ssrLooseEqual(unref(form).symptom_type, "other")) ? " selected" : ""}${_scopeId}>Other</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.symptom_type
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "symptom_title",
              value: "Symptoms Title"
            }, null, _parent2, _scopeId));
            _push2(`<input id="symptom_title"${ssrRenderAttr("value", unref(form).symptom_title)} type="text" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" placeholder="Enter symptom title" data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.symptom_title
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "symptom_description",
              value: "Symptoms Description"
            }, null, _parent2, _scopeId));
            _push2(`<textarea id="symptom_description" rows="2" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" placeholder="Describe symptoms in detail" data-v-ffaf9d12${_scopeId}>${ssrInterpolate(unref(form).symptom_description)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.symptom_description
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "note",
              value: "Note"
            }, null, _parent2, _scopeId));
            _push2(`<textarea id="note" rows="2" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" placeholder="Additional notes" data-v-ffaf9d12${_scopeId}>${ssrInterpolate(unref(form).note)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.note
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "allergies",
              value: "Any Known Allergies"
            }, null, _parent2, _scopeId));
            _push2(`<textarea id="allergies" rows="2" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" placeholder="List any known allergies" data-v-ffaf9d12${_scopeId}>${ssrInterpolate(unref(form).allergies)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.allergies
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="space-y-3" data-v-ffaf9d12${_scopeId}><div class="grid grid-cols-1 gap-3 md:grid-cols-2" data-v-ffaf9d12${_scopeId}><div data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "appointment_date",
              value: "Appointment Date",
              class: "required"
            }, null, _parent2, _scopeId));
            _push2(`<input id="appointment_date"${ssrRenderAttr("value", unref(form).appointment_date)} type="date" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" required data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.appointment_date
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "case",
              value: "Case"
            }, null, _parent2, _scopeId));
            _push2(`<select id="case" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" data-v-ffaf9d12${_scopeId}><option value="" data-v-ffaf9d12${ssrIncludeBooleanAttr(Array.isArray(unref(form).case) ? ssrLooseContain(unref(form).case, "") : ssrLooseEqual(unref(form).case, "")) ? " selected" : ""}${_scopeId}>Select</option><option value="new" data-v-ffaf9d12${ssrIncludeBooleanAttr(Array.isArray(unref(form).case) ? ssrLooseContain(unref(form).case, "new") : ssrLooseEqual(unref(form).case, "new")) ? " selected" : ""}${_scopeId}>New Case</option><option value="followup" data-v-ffaf9d12${ssrIncludeBooleanAttr(Array.isArray(unref(form).case) ? ssrLooseContain(unref(form).case, "followup") : ssrLooseEqual(unref(form).case, "followup")) ? " selected" : ""}${_scopeId}>Follow-up</option><option value="emergency" data-v-ffaf9d12${ssrIncludeBooleanAttr(Array.isArray(unref(form).case) ? ssrLooseContain(unref(form).case, "emergency") : ssrLooseEqual(unref(form).case, "emergency")) ? " selected" : ""}${_scopeId}>Emergency</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.case
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="grid grid-cols-1 gap-3 md:grid-cols-2" data-v-ffaf9d12${_scopeId}><div data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "casualty",
              value: "Casualty"
            }, null, _parent2, _scopeId));
            _push2(`<select id="casualty" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" data-v-ffaf9d12${_scopeId}><option value="no" data-v-ffaf9d12${ssrIncludeBooleanAttr(Array.isArray(unref(form).casualty) ? ssrLooseContain(unref(form).casualty, "no") : ssrLooseEqual(unref(form).casualty, "no")) ? " selected" : ""}${_scopeId}>No</option><option value="yes" data-v-ffaf9d12${ssrIncludeBooleanAttr(Array.isArray(unref(form).casualty) ? ssrLooseContain(unref(form).casualty, "yes") : ssrLooseEqual(unref(form).casualty, "yes")) ? " selected" : ""}${_scopeId}>Yes</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.casualty
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "old_patient",
              value: "Old Patient"
            }, null, _parent2, _scopeId));
            _push2(`<select id="old_patient" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" data-v-ffaf9d12${_scopeId}><option value="no" data-v-ffaf9d12${ssrIncludeBooleanAttr(Array.isArray(unref(form).old_patient) ? ssrLooseContain(unref(form).old_patient, "no") : ssrLooseEqual(unref(form).old_patient, "no")) ? " selected" : ""}${_scopeId}>No</option><option value="yes" data-v-ffaf9d12${ssrIncludeBooleanAttr(Array.isArray(unref(form).old_patient) ? ssrLooseContain(unref(form).old_patient, "yes") : ssrLooseEqual(unref(form).old_patient, "yes")) ? " selected" : ""}${_scopeId}>Yes</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.old_patient
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="grid grid-cols-1 gap-3 md:grid-cols-2" data-v-ffaf9d12${_scopeId}><div data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "reference",
              value: "Reference"
            }, null, _parent2, _scopeId));
            _push2(`<input id="reference"${ssrRenderAttr("value", unref(form).reference)} type="text" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" placeholder="Reference" data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.reference
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "consultant_doctor_id",
              value: "Consultant Doctor",
              class: "required"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(unref(Multiselect), {
              modelValue: unref(form).consultant_doctor_id,
              "onUpdate:modelValue": ($event) => unref(form).consultant_doctor_id = $event,
              options: __props.doctors,
              "track-by": "id",
              label: "name",
              placeholder: "Search and select a doctor",
              class: "w-full text-sm rounded-md border border-slate-300"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.consultant_doctor_id
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="flex items-center mt-1" data-v-ffaf9d12${_scopeId}><input id="apply_tpa"${ssrIncludeBooleanAttr(Array.isArray(unref(form).apply_tpa) ? ssrLooseContain(unref(form).apply_tpa, null) : unref(form).apply_tpa) ? " checked" : ""} type="checkbox" class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "apply_tpa",
              value: "Apply TPA",
              class: "ml-2"
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="grid grid-cols-1 gap-3 md:grid-cols-2" data-v-ffaf9d12${_scopeId}><div data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "charge_type_id",
              value: "Charge Category",
              class: "required"
            }, null, _parent2, _scopeId));
            _push2(`<select id="charge_type_id" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" required data-v-ffaf9d12${_scopeId}><option value="" data-v-ffaf9d12${ssrIncludeBooleanAttr(Array.isArray(unref(form).charge_type_id) ? ssrLooseContain(unref(form).charge_type_id, "") : ssrLooseEqual(unref(form).charge_type_id, "")) ? " selected" : ""}${_scopeId}>Select Charge Category</option><!--[-->`);
            ssrRenderList(__props.chargeTypes, (data) => {
              _push2(`<option${ssrRenderAttr("value", data.id)} data-v-ffaf9d12${ssrIncludeBooleanAttr(Array.isArray(unref(form).charge_type_id) ? ssrLooseContain(unref(form).charge_type_id, data.id) : ssrLooseEqual(unref(form).charge_type_id, data.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(data.name)}</option>`);
            });
            _push2(`<!--]--></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.charge_type_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "charge_id",
              value: "Charge",
              class: "required"
            }, null, _parent2, _scopeId));
            _push2(`<select id="charge_id" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300"${ssrIncludeBooleanAttr(!unref(form).charge_type_id) ? " disabled" : ""} required data-v-ffaf9d12${_scopeId}><option value="" data-v-ffaf9d12${ssrIncludeBooleanAttr(Array.isArray(unref(form).charge_id) ? ssrLooseContain(unref(form).charge_id, "") : ssrLooseEqual(unref(form).charge_id, "")) ? " selected" : ""}${_scopeId}>${ssrInterpolate(unref(form).charge_type_id ? "Select Charge" : "Select Charge Category First")}</option><!--[-->`);
            ssrRenderList(filteredCharges.value, (data) => {
              _push2(`<option${ssrRenderAttr("value", data.id)} data-v-ffaf9d12${ssrIncludeBooleanAttr(Array.isArray(unref(form).charge_id) ? ssrLooseContain(unref(form).charge_id, data.id) : ssrLooseEqual(unref(form).charge_id, data.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(data.name)} - ${ssrInterpolate(data.standard_charge)} Tk </option>`);
            });
            _push2(`<!--]--></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.charge_id
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="grid grid-cols-1 gap-3 md:grid-cols-2" data-v-ffaf9d12${_scopeId}><div data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "applied_charge",
              value: "Applied Charge (Tk.)",
              class: "required"
            }, null, _parent2, _scopeId));
            _push2(`<input id="applied_charge"${ssrRenderAttr("value", unref(form).applied_charge)} type="number" step="0.01" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" placeholder="0" required data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.applied_charge
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "standard_charge",
              value: "Standard Charge (Tk.)"
            }, null, _parent2, _scopeId));
            _push2(`<input id="standard_charge"${ssrRenderAttr("value", unref(form).standard_charge)} type="number" step="0.01" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" placeholder="0" readonly data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.standard_charge
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="grid grid-cols-1 gap-3 md:grid-cols-2" data-v-ffaf9d12${_scopeId}><div data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "tax",
              value: "Tax"
            }, null, _parent2, _scopeId));
            _push2(`<div class="relative" data-v-ffaf9d12${_scopeId}><input id="tax"${ssrRenderAttr("value", unref(form).tax)} type="number" step="0.01" class="block w-full p-1.5 pr-8 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" placeholder="0" data-v-ffaf9d12${_scopeId}><span class="absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-500" data-v-ffaf9d12${_scopeId}>%</span></div>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.tax
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "discount",
              value: "Discount"
            }, null, _parent2, _scopeId));
            _push2(`<div class="relative" data-v-ffaf9d12${_scopeId}><input id="discount"${ssrRenderAttr("value", unref(form).discount)} type="number" step="0.01" class="block w-full p-1.5 pr-8 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" placeholder="0" data-v-ffaf9d12${_scopeId}><span class="absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-500" data-v-ffaf9d12${_scopeId}>%</span></div>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.discount
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="grid grid-cols-1 gap-3 md:grid-cols-2" data-v-ffaf9d12${_scopeId}><div data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "payment_mode",
              value: "Payment Mode"
            }, null, _parent2, _scopeId));
            _push2(`<select id="payment_mode" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" data-v-ffaf9d12${_scopeId}><option value="cash" data-v-ffaf9d12${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, "cash") : ssrLooseEqual(unref(form).payment_mode, "cash")) ? " selected" : ""}${_scopeId}>Cash</option><option value="card" data-v-ffaf9d12${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, "card") : ssrLooseEqual(unref(form).payment_mode, "card")) ? " selected" : ""}${_scopeId}>Card</option><option value="online" data-v-ffaf9d12${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, "online") : ssrLooseEqual(unref(form).payment_mode, "online")) ? " selected" : ""}${_scopeId}>Online</option><option value="insurance" data-v-ffaf9d12${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, "insurance") : ssrLooseEqual(unref(form).payment_mode, "insurance")) ? " selected" : ""}${_scopeId}>Insurance</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.payment_mode
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "amount",
              value: "Amount (Tk.)",
              class: "required"
            }, null, _parent2, _scopeId));
            _push2(`<input id="amount"${ssrRenderAttr("value", unref(form).amount)} type="number" step="0.01" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" placeholder="0" required readonly data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.amount
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="grid grid-cols-1 gap-3 md:grid-cols-2" data-v-ffaf9d12${_scopeId}><div data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "live_consultation",
              value: "Live Consultation"
            }, null, _parent2, _scopeId));
            _push2(`<select id="live_consultation" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" data-v-ffaf9d12${_scopeId}><option value="no" data-v-ffaf9d12${ssrIncludeBooleanAttr(Array.isArray(unref(form).live_consultation) ? ssrLooseContain(unref(form).live_consultation, "no") : ssrLooseEqual(unref(form).live_consultation, "no")) ? " selected" : ""}${_scopeId}>No</option><option value="yes" data-v-ffaf9d12${ssrIncludeBooleanAttr(Array.isArray(unref(form).live_consultation) ? ssrLooseContain(unref(form).live_consultation, "yes") : ssrLooseEqual(unref(form).live_consultation, "yes")) ? " selected" : ""}${_scopeId}>Yes</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.live_consultation
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "paid_amount",
              value: "Paid Amount (Tk.)",
              class: "required"
            }, null, _parent2, _scopeId));
            _push2(`<input id="paid_amount"${ssrRenderAttr("value", unref(form).paid_amount)} type="number" step="0.01" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300" placeholder="0" required data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.paid_amount
            }, null, _parent2, _scopeId));
            _push2(`</div></div></div></div><div class="flex items-center justify-end mt-3" data-v-ffaf9d12${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              type: "submit",
              class: ["ms-3", { "opacity-25": unref(form).processing }],
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
          } else {
            return [
              createVNode("div", { class: "w-full transition duration-1000 ease-in-out transform bg-white rounded-md" }, [
                createVNode("div", { class: "flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md" }, [
                  createVNode("div", null, [
                    createVNode("h1", { class: "p-4 text-xl font-bold" }, toDisplayString(_ctx.$page.props.pageTitle), 1)
                  ]),
                  createVNode("div", { class: "flex items-center p-3 py-2 space-x-1" }, [
                    createVNode("div", { class: "relative min-w-[280px]" }, [
                      createVNode("div", { class: "relative" }, [
                        createVNode("div", { class: "col-span-1" }, [
                          createVNode(unref(Multiselect), {
                            modelValue: unref(form).patient_id,
                            "onUpdate:modelValue": ($event) => unref(form).patient_id = $event,
                            options: __props.patients,
                            "track-by": "id",
                            label: "name",
                            placeholder: "Search and select a patient",
                            class: "w-full text-sm h-[30px] rounded-md border border-slate-300"
                          }, null, 8, ["modelValue", "onUpdate:modelValue", "options"]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.patient_id
                          }, null, 8, ["message"])
                        ])
                      ])
                    ]),
                    createVNode("div", { class: "flex items-center space-x-1" }, [
                      createVNode("button", {
                        onClick: openPatientModal,
                        class: "px-3 py-2.5 text-sm text-white bg-green-600 rounded hover:bg-green-700 transition-colors"
                      }, " + New Patient ")
                    ]),
                    createVNode("div", { class: "p-2 py-2 flex items-center space-x-2" }, [
                      createVNode("div", { class: "flex items-center space-x-3" }, [
                        createVNode("button", {
                          onClick: goToOpdList,
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
                          createTextVNode(" Opd Patient List ")
                        ])
                      ])
                    ])
                  ])
                ]),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"]),
                  class: "p-4"
                }, [
                  createVNode("div", { class: "grid grid-cols-1 gap-6 lg:grid-cols-2" }, [
                    createVNode("div", { class: "space-y-3" }, [
                      createVNode("div", { class: "grid grid-cols-1 gap-3 md:grid-cols-2" }, [
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "symptom_type",
                            value: "Symptoms Type"
                          }),
                          withDirectives(createVNode("select", {
                            id: "symptom_type",
                            "onUpdate:modelValue": ($event) => unref(form).symptom_type = $event,
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300"
                          }, [
                            createVNode("option", { value: "" }, "Select"),
                            createVNode("option", { value: "fever" }, "Fever"),
                            createVNode("option", { value: "cough" }, "Cough"),
                            createVNode("option", { value: "headache" }, "Headache"),
                            createVNode("option", { value: "body_pain" }, "Body Pain"),
                            createVNode("option", { value: "other" }, "Other")
                          ], 8, ["onUpdate:modelValue"]), [
                            [vModelSelect, unref(form).symptom_type]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.symptom_type
                          }, null, 8, ["message"])
                        ]),
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "symptom_title",
                            value: "Symptoms Title"
                          }),
                          withDirectives(createVNode("input", {
                            id: "symptom_title",
                            "onUpdate:modelValue": ($event) => unref(form).symptom_title = $event,
                            type: "text",
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300",
                            placeholder: "Enter symptom title"
                          }, null, 8, ["onUpdate:modelValue"]), [
                            [vModelText, unref(form).symptom_title]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.symptom_title
                          }, null, 8, ["message"])
                        ])
                      ]),
                      createVNode("div", null, [
                        createVNode(_sfc_main$3, {
                          for: "symptom_description",
                          value: "Symptoms Description"
                        }),
                        withDirectives(createVNode("textarea", {
                          id: "symptom_description",
                          "onUpdate:modelValue": ($event) => unref(form).symptom_description = $event,
                          rows: "2",
                          class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300",
                          placeholder: "Describe symptoms in detail"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).symptom_description]
                        ]),
                        createVNode(_sfc_main$2, {
                          class: "mt-1",
                          message: unref(form).errors.symptom_description
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", null, [
                        createVNode(_sfc_main$3, {
                          for: "note",
                          value: "Note"
                        }),
                        withDirectives(createVNode("textarea", {
                          id: "note",
                          "onUpdate:modelValue": ($event) => unref(form).note = $event,
                          rows: "2",
                          class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300",
                          placeholder: "Additional notes"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).note]
                        ]),
                        createVNode(_sfc_main$2, {
                          class: "mt-1",
                          message: unref(form).errors.note
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", null, [
                        createVNode(_sfc_main$3, {
                          for: "allergies",
                          value: "Any Known Allergies"
                        }),
                        withDirectives(createVNode("textarea", {
                          id: "allergies",
                          "onUpdate:modelValue": ($event) => unref(form).allergies = $event,
                          rows: "2",
                          class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300",
                          placeholder: "List any known allergies"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).allergies]
                        ]),
                        createVNode(_sfc_main$2, {
                          class: "mt-1",
                          message: unref(form).errors.allergies
                        }, null, 8, ["message"])
                      ])
                    ]),
                    createVNode("div", { class: "space-y-3" }, [
                      createVNode("div", { class: "grid grid-cols-1 gap-3 md:grid-cols-2" }, [
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "appointment_date",
                            value: "Appointment Date",
                            class: "required"
                          }),
                          withDirectives(createVNode("input", {
                            id: "appointment_date",
                            "onUpdate:modelValue": ($event) => unref(form).appointment_date = $event,
                            type: "date",
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300",
                            required: ""
                          }, null, 8, ["onUpdate:modelValue"]), [
                            [vModelText, unref(form).appointment_date]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.appointment_date
                          }, null, 8, ["message"])
                        ]),
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "case",
                            value: "Case"
                          }),
                          withDirectives(createVNode("select", {
                            id: "case",
                            "onUpdate:modelValue": ($event) => unref(form).case = $event,
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300"
                          }, [
                            createVNode("option", { value: "" }, "Select"),
                            createVNode("option", { value: "new" }, "New Case"),
                            createVNode("option", { value: "followup" }, "Follow-up"),
                            createVNode("option", { value: "emergency" }, "Emergency")
                          ], 8, ["onUpdate:modelValue"]), [
                            [vModelSelect, unref(form).case]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.case
                          }, null, 8, ["message"])
                        ])
                      ]),
                      createVNode("div", { class: "grid grid-cols-1 gap-3 md:grid-cols-2" }, [
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "casualty",
                            value: "Casualty"
                          }),
                          withDirectives(createVNode("select", {
                            id: "casualty",
                            "onUpdate:modelValue": ($event) => unref(form).casualty = $event,
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300"
                          }, [
                            createVNode("option", { value: "no" }, "No"),
                            createVNode("option", { value: "yes" }, "Yes")
                          ], 8, ["onUpdate:modelValue"]), [
                            [vModelSelect, unref(form).casualty]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.casualty
                          }, null, 8, ["message"])
                        ]),
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "old_patient",
                            value: "Old Patient"
                          }),
                          withDirectives(createVNode("select", {
                            id: "old_patient",
                            "onUpdate:modelValue": ($event) => unref(form).old_patient = $event,
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300"
                          }, [
                            createVNode("option", { value: "no" }, "No"),
                            createVNode("option", { value: "yes" }, "Yes")
                          ], 8, ["onUpdate:modelValue"]), [
                            [vModelSelect, unref(form).old_patient]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.old_patient
                          }, null, 8, ["message"])
                        ])
                      ]),
                      createVNode("div", { class: "grid grid-cols-1 gap-3 md:grid-cols-2" }, [
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "reference",
                            value: "Reference"
                          }),
                          withDirectives(createVNode("input", {
                            id: "reference",
                            "onUpdate:modelValue": ($event) => unref(form).reference = $event,
                            type: "text",
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300",
                            placeholder: "Reference"
                          }, null, 8, ["onUpdate:modelValue"]), [
                            [vModelText, unref(form).reference]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.reference
                          }, null, 8, ["message"])
                        ]),
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "consultant_doctor_id",
                            value: "Consultant Doctor",
                            class: "required"
                          }),
                          createVNode(unref(Multiselect), {
                            modelValue: unref(form).consultant_doctor_id,
                            "onUpdate:modelValue": ($event) => unref(form).consultant_doctor_id = $event,
                            options: __props.doctors,
                            "track-by": "id",
                            label: "name",
                            placeholder: "Search and select a doctor",
                            class: "w-full text-sm rounded-md border border-slate-300"
                          }, null, 8, ["modelValue", "onUpdate:modelValue", "options"]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.consultant_doctor_id
                          }, null, 8, ["message"])
                        ])
                      ]),
                      createVNode("div", { class: "flex items-center mt-1" }, [
                        withDirectives(createVNode("input", {
                          id: "apply_tpa",
                          "onUpdate:modelValue": ($event) => unref(form).apply_tpa = $event,
                          type: "checkbox",
                          class: "w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelCheckbox, unref(form).apply_tpa]
                        ]),
                        createVNode(_sfc_main$3, {
                          for: "apply_tpa",
                          value: "Apply TPA",
                          class: "ml-2"
                        })
                      ]),
                      createVNode("div", { class: "grid grid-cols-1 gap-3 md:grid-cols-2" }, [
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "charge_type_id",
                            value: "Charge Category",
                            class: "required"
                          }),
                          withDirectives(createVNode("select", {
                            id: "charge_type_id",
                            "onUpdate:modelValue": ($event) => unref(form).charge_type_id = $event,
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300",
                            required: ""
                          }, [
                            createVNode("option", { value: "" }, "Select Charge Category"),
                            (openBlock(true), createBlock(Fragment, null, renderList(__props.chargeTypes, (data) => {
                              return openBlock(), createBlock("option", {
                                key: data.id,
                                value: data.id
                              }, toDisplayString(data.name), 9, ["value"]);
                            }), 128))
                          ], 8, ["onUpdate:modelValue"]), [
                            [vModelSelect, unref(form).charge_type_id]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.charge_type_id
                          }, null, 8, ["message"])
                        ]),
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "charge_id",
                            value: "Charge",
                            class: "required"
                          }),
                          withDirectives(createVNode("select", {
                            id: "charge_id",
                            "onUpdate:modelValue": ($event) => unref(form).charge_id = $event,
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300",
                            disabled: !unref(form).charge_type_id,
                            required: ""
                          }, [
                            createVNode("option", { value: "" }, toDisplayString(unref(form).charge_type_id ? "Select Charge" : "Select Charge Category First"), 1),
                            (openBlock(true), createBlock(Fragment, null, renderList(filteredCharges.value, (data) => {
                              return openBlock(), createBlock("option", {
                                key: data.id,
                                value: data.id
                              }, toDisplayString(data.name) + " - " + toDisplayString(data.standard_charge) + " Tk ", 9, ["value"]);
                            }), 128))
                          ], 8, ["onUpdate:modelValue", "disabled"]), [
                            [vModelSelect, unref(form).charge_id]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.charge_id
                          }, null, 8, ["message"])
                        ])
                      ]),
                      createVNode("div", { class: "grid grid-cols-1 gap-3 md:grid-cols-2" }, [
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "applied_charge",
                            value: "Applied Charge (Tk.)",
                            class: "required"
                          }),
                          withDirectives(createVNode("input", {
                            id: "applied_charge",
                            "onUpdate:modelValue": ($event) => unref(form).applied_charge = $event,
                            type: "number",
                            step: "0.01",
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300",
                            placeholder: "0",
                            required: ""
                          }, null, 8, ["onUpdate:modelValue"]), [
                            [vModelText, unref(form).applied_charge]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.applied_charge
                          }, null, 8, ["message"])
                        ]),
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "standard_charge",
                            value: "Standard Charge (Tk.)"
                          }),
                          withDirectives(createVNode("input", {
                            id: "standard_charge",
                            "onUpdate:modelValue": ($event) => unref(form).standard_charge = $event,
                            type: "number",
                            step: "0.01",
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300",
                            placeholder: "0",
                            readonly: ""
                          }, null, 8, ["onUpdate:modelValue"]), [
                            [vModelText, unref(form).standard_charge]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.standard_charge
                          }, null, 8, ["message"])
                        ])
                      ]),
                      createVNode("div", { class: "grid grid-cols-1 gap-3 md:grid-cols-2" }, [
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "tax",
                            value: "Tax"
                          }),
                          createVNode("div", { class: "relative" }, [
                            withDirectives(createVNode("input", {
                              id: "tax",
                              "onUpdate:modelValue": ($event) => unref(form).tax = $event,
                              type: "number",
                              step: "0.01",
                              class: "block w-full p-1.5 pr-8 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300",
                              placeholder: "0"
                            }, null, 8, ["onUpdate:modelValue"]), [
                              [vModelText, unref(form).tax]
                            ]),
                            createVNode("span", { class: "absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-500" }, "%")
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.tax
                          }, null, 8, ["message"])
                        ]),
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "discount",
                            value: "Discount"
                          }),
                          createVNode("div", { class: "relative" }, [
                            withDirectives(createVNode("input", {
                              id: "discount",
                              "onUpdate:modelValue": ($event) => unref(form).discount = $event,
                              type: "number",
                              step: "0.01",
                              class: "block w-full p-1.5 pr-8 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300",
                              placeholder: "0"
                            }, null, 8, ["onUpdate:modelValue"]), [
                              [vModelText, unref(form).discount]
                            ]),
                            createVNode("span", { class: "absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-500" }, "%")
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.discount
                          }, null, 8, ["message"])
                        ])
                      ]),
                      createVNode("div", { class: "grid grid-cols-1 gap-3 md:grid-cols-2" }, [
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "payment_mode",
                            value: "Payment Mode"
                          }),
                          withDirectives(createVNode("select", {
                            id: "payment_mode",
                            "onUpdate:modelValue": ($event) => unref(form).payment_mode = $event,
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300"
                          }, [
                            createVNode("option", { value: "cash" }, "Cash"),
                            createVNode("option", { value: "card" }, "Card"),
                            createVNode("option", { value: "online" }, "Online"),
                            createVNode("option", { value: "insurance" }, "Insurance")
                          ], 8, ["onUpdate:modelValue"]), [
                            [vModelSelect, unref(form).payment_mode]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.payment_mode
                          }, null, 8, ["message"])
                        ]),
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "amount",
                            value: "Amount (Tk.)",
                            class: "required"
                          }),
                          withDirectives(createVNode("input", {
                            id: "amount",
                            "onUpdate:modelValue": ($event) => unref(form).amount = $event,
                            type: "number",
                            step: "0.01",
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300",
                            placeholder: "0",
                            required: "",
                            readonly: ""
                          }, null, 8, ["onUpdate:modelValue"]), [
                            [vModelText, unref(form).amount]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.amount
                          }, null, 8, ["message"])
                        ])
                      ]),
                      createVNode("div", { class: "grid grid-cols-1 gap-3 md:grid-cols-2" }, [
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "live_consultation",
                            value: "Live Consultation"
                          }),
                          withDirectives(createVNode("select", {
                            id: "live_consultation",
                            "onUpdate:modelValue": ($event) => unref(form).live_consultation = $event,
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300"
                          }, [
                            createVNode("option", { value: "no" }, "No"),
                            createVNode("option", { value: "yes" }, "Yes")
                          ], 8, ["onUpdate:modelValue"]), [
                            [vModelSelect, unref(form).live_consultation]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.live_consultation
                          }, null, 8, ["message"])
                        ]),
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "paid_amount",
                            value: "Paid Amount (Tk.)",
                            class: "required"
                          }),
                          withDirectives(createVNode("input", {
                            id: "paid_amount",
                            "onUpdate:modelValue": ($event) => unref(form).paid_amount = $event,
                            type: "number",
                            step: "0.01",
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300",
                            placeholder: "0",
                            required: ""
                          }, null, 8, ["onUpdate:modelValue"]), [
                            [vModelText, unref(form).paid_amount]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.paid_amount
                          }, null, 8, ["message"])
                        ])
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "flex items-center justify-end mt-3" }, [
                    createVNode(_sfc_main$4, {
                      type: "submit",
                      class: ["ms-3", { "opacity-25": unref(form).processing }],
                      disabled: unref(form).processing
                    }, {
                      default: withCtx(() => [
                        createTextVNode(toDisplayString(props.id ?? false ? "Update" : "Create"), 1)
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
      _push(`<!--]-->`);
    };
  }
};
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/OpdPatient/Form.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const Form = /* @__PURE__ */ _export_sfc(_sfc_main, [["__scopeId", "data-v-ffaf9d12"]]);
export {
  Form as default
};
