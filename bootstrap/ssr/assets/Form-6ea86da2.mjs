import { ref, watch, computed, onMounted, withCtx, unref, createTextVNode, toDisplayString, createVNode, openBlock, createBlock, withModifiers, withDirectives, vModelSelect, vModelText, Fragment, renderList, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderAttr, ssrRenderList } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import { useForm, router } from "@inertiajs/vue3";
import { _ as _sfc_main$2 } from "./InputError-6aeb8d97.mjs";
import { _ as _sfc_main$3 } from "./InputLabel-70ca52d1.mjs";
import { _ as _sfc_main$4 } from "./PrimaryButton-b82fb16e.mjs";
import { A as AlertMessage } from "./AlertMessage-0f422981.mjs";
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
const Form_vue_vue_type_style_index_0_scoped_445dc6f5_lang = "";
const _sfc_main = {
  __name: "Form",
  __ssrInlineRender: true,
  props: ["ipdpatient", "id", "patients", "doctors", "bedGroups", "beds"],
  setup(__props) {
    var _a, _b, _c, _d, _e, _f, _g, _h, _i, _j, _k, _l, _m, _n, _o, _p, _q, _r;
    const props = __props;
    const form = useForm({
      patient_id: ((_a = props.ipdpatient) == null ? void 0 : _a.patient_id) ?? "",
      consultant_doctor_id: ((_b = props.ipdpatient) == null ? void 0 : _b.consultant_doctor_id) ?? "",
      // Left side fields
      symptom_type: ((_c = props.ipdpatient) == null ? void 0 : _c.symptom_type) ?? "",
      symptom_title: ((_d = props.ipdpatient) == null ? void 0 : _d.symptom_title) ?? "",
      symptom_description: ((_e = props.ipdpatient) == null ? void 0 : _e.symptom_description) ?? "",
      note: ((_f = props.ipdpatient) == null ? void 0 : _f.note) ?? "",
      // Right side fields
      admission_date: ((_g = props.ipdpatient) == null ? void 0 : _g.admission_date) ?? "",
      case: ((_h = props.ipdpatient) == null ? void 0 : _h.case) ?? "",
      tpa: ((_i = props.ipdpatient) == null ? void 0 : _i.tpa) ?? "",
      casualty: ((_j = props.ipdpatient) == null ? void 0 : _j.casualty) ?? "no",
      old_patient: ((_k = props.ipdpatient) == null ? void 0 : _k.old_patient) ?? "no",
      credit_limit: ((_l = props.ipdpatient) == null ? void 0 : _l.credit_limit) ?? "",
      reference: ((_m = props.ipdpatient) == null ? void 0 : _m.reference) ?? "",
      bed_group_id: ((_n = props.ipdpatient) == null ? void 0 : _n.bed_group_id) ?? "",
      bed_id: ((_o = props.ipdpatient) == null ? void 0 : _o.bed_id) ?? "",
      live_consultation: ((_p = props.ipdpatient) == null ? void 0 : _p.live_consultation) ?? "no",
      _method: ((_q = props.ipdpatient) == null ? void 0 : _q.id) ? "put" : "post"
    });
    const filteredBeds = ref(props.beds || []);
    watch(() => form.bed_group_id, (newBedGroupId) => {
      if (newBedGroupId) {
        filteredBeds.value = props.beds.filter((bed) => bed.bed_group_id == newBedGroupId);
      } else {
        filteredBeds.value = [];
      }
      form.bed_id = 0;
    });
    if ((_r = props.ipdpatient) == null ? void 0 : _r.bed_group_id) {
      filteredBeds.value = props.beds.filter((bed) => bed.bed_group_id == props.ipdpatient.bed_group_id);
    }
    const submit = () => {
      const routeName = props.id ? route("backend.ipdpatient.update", props.id) : route("backend.ipdpatient.store");
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
          if (!props.id)
            form.reset();
          displayResponse(response);
        },
        onError: (errorObject) => {
          displayWarning(errorObject);
        }
      });
    };
    const isPatientModalOpen = ref(false);
    const openPatientModal = () => {
      isPatientModalOpen.value = true;
    };
    const closePatientModal = () => {
      isPatientModalOpen.value = false;
    };
    const handlePatientCreated = (newPatient) => {
      props.patients.push(newPatient);
      form.patient_id = newPatient.id;
      router.reload({
        only: ["patients"],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
          props.patients = [...page.props.patients];
        }
      });
    };
    const patientSearchQuery = ref("");
    const filteredPatients = ref([]);
    ref(false);
    ref(-1);
    computed(() => {
      return patientSearchQuery.value.length > 0 && filteredPatients.value.length === 0;
    });
    onMounted(() => {
      filteredPatients.value = props.patients.slice(0, 10);
    });
    watch(patientSearchQuery, (newValue) => {
      if (newValue === "") {
        form.patient_id = "";
      }
    });
    const handleAdmissionDateFocus = (event) => {
      if (!form.admission_date) {
        const now = /* @__PURE__ */ new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, "0");
        const day = String(now.getDate()).padStart(2, "0");
        const hours = String(now.getHours()).padStart(2, "0");
        const minutes = String(now.getMinutes()).padStart(2, "0");
        form.admission_date = `${year}-${month}-${day}T${hours}:${minutes}`;
      }
    };
    const goToIpdList = () => {
      router.visit(route("backend.ipdpatient.index"));
    };
    const handlePatientSelect = (selectedPatient) => {
      form.patient_id = selectedPatient ? selectedPatient.id : "";
    };
    const handleDoctorSelect = (selectedDoctor) => {
      form.consultant_doctor_id = selectedDoctor ? selectedDoctor.id : "";
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
            _push2(`<div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md" data-v-445dc6f5${_scopeId}><div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md" data-v-445dc6f5${_scopeId}><div data-v-445dc6f5${_scopeId}><h1 class="p-4 text-xl font-bold" data-v-445dc6f5${_scopeId}>${ssrInterpolate(_ctx.$page.props.pageTitle)}</h1></div><div class="flex items-center p-3 py-2 space-x-1" data-v-445dc6f5${_scopeId}><div class="relative min-w-[280px]" data-v-445dc6f5${_scopeId}><div class="relative" data-v-445dc6f5${_scopeId}><div class="col-span-1" data-v-445dc6f5${_scopeId}>`);
            _push2(ssrRenderComponent(unref(Multiselect), {
              modelValue: props.patients.find((p) => p.id === unref(form).patient_id),
              "onUpdate:modelValue": handlePatientSelect,
              options: __props.patients,
              "track-by": "id",
              label: "name",
              placeholder: "Search and select a patient",
              class: "w-full text-sm rounded-md border border-slate-300"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.patient_id
            }, null, _parent2, _scopeId));
            _push2(`</div></div></div><div class="flex items-center space-x-1" data-v-445dc6f5${_scopeId}><button class="px-3 py-2.5 text-sm text-white bg-green-600 rounded hover:bg-green-700 transition-colors" data-v-445dc6f5${_scopeId}> + New Patient </button></div><div class="p-2 py-2 flex items-center space-x-2" data-v-445dc6f5${_scopeId}><div class="flex items-center space-x-3" data-v-445dc6f5${_scopeId}><button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2" data-v-445dc6f5${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" data-v-445dc6f5${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" data-v-445dc6f5${_scopeId}></path></svg> Ipd Patient List </button></div></div></div></div><form class="p-4" data-v-445dc6f5${_scopeId}>`);
            _push2(ssrRenderComponent(AlertMessage, null, null, _parent2, _scopeId));
            _push2(`<div class="grid grid-cols-1 gap-6 lg:grid-cols-2" data-v-445dc6f5${_scopeId}><div class="space-y-3" data-v-445dc6f5${_scopeId}><div class="grid grid-cols-1 gap-3 md:grid-cols-2" data-v-445dc6f5${_scopeId}><div data-v-445dc6f5${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "symptom_type",
              value: "Symptoms Type"
            }, null, _parent2, _scopeId));
            _push2(`<select id="symptom_type" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-445dc6f5${_scopeId}><option value="" data-v-445dc6f5${ssrIncludeBooleanAttr(Array.isArray(unref(form).symptom_type) ? ssrLooseContain(unref(form).symptom_type, "") : ssrLooseEqual(unref(form).symptom_type, "")) ? " selected" : ""}${_scopeId}>Select</option><option value="fever" data-v-445dc6f5${ssrIncludeBooleanAttr(Array.isArray(unref(form).symptom_type) ? ssrLooseContain(unref(form).symptom_type, "fever") : ssrLooseEqual(unref(form).symptom_type, "fever")) ? " selected" : ""}${_scopeId}>Fever</option><option value="cough" data-v-445dc6f5${ssrIncludeBooleanAttr(Array.isArray(unref(form).symptom_type) ? ssrLooseContain(unref(form).symptom_type, "cough") : ssrLooseEqual(unref(form).symptom_type, "cough")) ? " selected" : ""}${_scopeId}>Cough</option><option value="headache" data-v-445dc6f5${ssrIncludeBooleanAttr(Array.isArray(unref(form).symptom_type) ? ssrLooseContain(unref(form).symptom_type, "headache") : ssrLooseEqual(unref(form).symptom_type, "headache")) ? " selected" : ""}${_scopeId}>Headache</option><option value="body_pain" data-v-445dc6f5${ssrIncludeBooleanAttr(Array.isArray(unref(form).symptom_type) ? ssrLooseContain(unref(form).symptom_type, "body_pain") : ssrLooseEqual(unref(form).symptom_type, "body_pain")) ? " selected" : ""}${_scopeId}>Body Pain</option><option value="other" data-v-445dc6f5${ssrIncludeBooleanAttr(Array.isArray(unref(form).symptom_type) ? ssrLooseContain(unref(form).symptom_type, "other") : ssrLooseEqual(unref(form).symptom_type, "other")) ? " selected" : ""}${_scopeId}>Other</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.symptom_type
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-445dc6f5${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "symptom_title",
              value: "Symptoms Title"
            }, null, _parent2, _scopeId));
            _push2(`<input id="symptom_title"${ssrRenderAttr("value", unref(form).symptom_title)} type="text" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" placeholder="Enter symptom title" data-v-445dc6f5${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.symptom_title
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div data-v-445dc6f5${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "symptom_description",
              value: "Symptoms Description"
            }, null, _parent2, _scopeId));
            _push2(`<textarea id="symptom_description" rows="2" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" placeholder="Describe symptoms in detail" data-v-445dc6f5${_scopeId}>${ssrInterpolate(unref(form).symptom_description)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.symptom_description
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-445dc6f5${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "note",
              value: "Note"
            }, null, _parent2, _scopeId));
            _push2(`<textarea id="note" rows="2" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" placeholder="Additional notes" data-v-445dc6f5${_scopeId}>${ssrInterpolate(unref(form).note)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.note
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="space-y-3" data-v-445dc6f5${_scopeId}><div class="w-full grid grid-cols-1 gap-3 md:grid-cols-2" data-v-445dc6f5${_scopeId}><div class="col-span-2" data-v-445dc6f5${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "admission_date",
              value: "Admission Date",
              class: "required"
            }, null, _parent2, _scopeId));
            _push2(`<input id="admission_date"${ssrRenderAttr("value", unref(form).admission_date)} type="datetime-local" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" required data-v-445dc6f5${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.admission_date
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="grid grid-cols-1 gap-3 md:grid-cols-2" data-v-445dc6f5${_scopeId}><div data-v-445dc6f5${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "case",
              value: "Case"
            }, null, _parent2, _scopeId));
            _push2(`<select id="case" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-445dc6f5${_scopeId}><option value="" data-v-445dc6f5${ssrIncludeBooleanAttr(Array.isArray(unref(form).case) ? ssrLooseContain(unref(form).case, "") : ssrLooseEqual(unref(form).case, "")) ? " selected" : ""}${_scopeId}>Select</option><option value="new" data-v-445dc6f5${ssrIncludeBooleanAttr(Array.isArray(unref(form).case) ? ssrLooseContain(unref(form).case, "new") : ssrLooseEqual(unref(form).case, "new")) ? " selected" : ""}${_scopeId}>New Case</option><option value="followup" data-v-445dc6f5${ssrIncludeBooleanAttr(Array.isArray(unref(form).case) ? ssrLooseContain(unref(form).case, "followup") : ssrLooseEqual(unref(form).case, "followup")) ? " selected" : ""}${_scopeId}>Follow-up</option><option value="emergency" data-v-445dc6f5${ssrIncludeBooleanAttr(Array.isArray(unref(form).case) ? ssrLooseContain(unref(form).case, "emergency") : ssrLooseEqual(unref(form).case, "emergency")) ? " selected" : ""}${_scopeId}>Emergency</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.case
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-445dc6f5${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "tpa",
              value: "Tpa"
            }, null, _parent2, _scopeId));
            _push2(`<input id="tpa"${ssrRenderAttr("value", unref(form).tpa)} type="text" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" placeholder="tpa" data-v-445dc6f5${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.tpa
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="grid grid-cols-1 gap-3 md:grid-cols-2" data-v-445dc6f5${_scopeId}><div data-v-445dc6f5${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "casualty",
              value: "Casualty"
            }, null, _parent2, _scopeId));
            _push2(`<select id="casualty" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-445dc6f5${_scopeId}><option value="no" data-v-445dc6f5${ssrIncludeBooleanAttr(Array.isArray(unref(form).casualty) ? ssrLooseContain(unref(form).casualty, "no") : ssrLooseEqual(unref(form).casualty, "no")) ? " selected" : ""}${_scopeId}>No</option><option value="yes" data-v-445dc6f5${ssrIncludeBooleanAttr(Array.isArray(unref(form).casualty) ? ssrLooseContain(unref(form).casualty, "yes") : ssrLooseEqual(unref(form).casualty, "yes")) ? " selected" : ""}${_scopeId}>Yes</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.casualty
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-445dc6f5${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "old_patient",
              value: "Old Patient"
            }, null, _parent2, _scopeId));
            _push2(`<select id="old_patient" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-445dc6f5${_scopeId}><option value="no" data-v-445dc6f5${ssrIncludeBooleanAttr(Array.isArray(unref(form).old_patient) ? ssrLooseContain(unref(form).old_patient, "no") : ssrLooseEqual(unref(form).old_patient, "no")) ? " selected" : ""}${_scopeId}>No</option><option value="yes" data-v-445dc6f5${ssrIncludeBooleanAttr(Array.isArray(unref(form).old_patient) ? ssrLooseContain(unref(form).old_patient, "yes") : ssrLooseEqual(unref(form).old_patient, "yes")) ? " selected" : ""}${_scopeId}>Yes</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.old_patient
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="grid grid-cols-1 gap-3 md:grid-cols-2" data-v-445dc6f5${_scopeId}><div data-v-445dc6f5${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "credit_limit",
              value: "Credit Limit"
            }, null, _parent2, _scopeId));
            _push2(`<input id="credit_limit"${ssrRenderAttr("value", unref(form).credit_limit)} type="text" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" placeholder="Credit Limit" data-v-445dc6f5${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.credit_limit
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-445dc6f5${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "reference",
              value: "Reference"
            }, null, _parent2, _scopeId));
            _push2(`<input id="reference"${ssrRenderAttr("value", unref(form).reference)} type="text" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" placeholder="Reference" data-v-445dc6f5${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.reference
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="grid grid-cols-1 gap-3 md:grid-cols-2" data-v-445dc6f5${_scopeId}><div data-v-445dc6f5${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "consultant_doctor_id",
              value: "Consultant Doctor",
              class: "required"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(unref(Multiselect), {
              modelValue: props.doctors.find((d) => d.id === unref(form).consultant_doctor_id),
              "onUpdate:modelValue": handleDoctorSelect,
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
            _push2(`</div><div data-v-445dc6f5${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "bed_group_id",
              value: "Bed Group",
              class: "required"
            }, null, _parent2, _scopeId));
            _push2(`<select id="bed_group_id" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" required data-v-445dc6f5${_scopeId}><option value="" data-v-445dc6f5${ssrIncludeBooleanAttr(Array.isArray(unref(form).bed_group_id) ? ssrLooseContain(unref(form).bed_group_id, "") : ssrLooseEqual(unref(form).bed_group_id, "")) ? " selected" : ""}${_scopeId}>Select Group</option><!--[-->`);
            ssrRenderList(__props.bedGroups, (data) => {
              _push2(`<option${ssrRenderAttr("value", data.id)} data-v-445dc6f5${ssrIncludeBooleanAttr(Array.isArray(unref(form).bed_group_id) ? ssrLooseContain(unref(form).bed_group_id, data.id) : ssrLooseEqual(unref(form).bed_group_id, data.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(data.name)}</option>`);
            });
            _push2(`<!--]--></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.bed_group_id
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="grid grid-cols-1 gap-3 md:grid-cols-2" data-v-445dc6f5${_scopeId}><div data-v-445dc6f5${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "bed_id",
              value: "Bed Number",
              class: "required"
            }, null, _parent2, _scopeId));
            _push2(`<select id="bed_id" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" required${ssrIncludeBooleanAttr(!unref(form).bed_group_id) ? " disabled" : ""} data-v-445dc6f5${_scopeId}><option value="" data-v-445dc6f5${ssrIncludeBooleanAttr(Array.isArray(unref(form).bed_id) ? ssrLooseContain(unref(form).bed_id, "") : ssrLooseEqual(unref(form).bed_id, "")) ? " selected" : ""}${_scopeId}>Select Bed</option><!--[-->`);
            ssrRenderList(filteredBeds.value, (bed) => {
              _push2(`<option${ssrRenderAttr("value", bed.id)} data-v-445dc6f5${ssrIncludeBooleanAttr(Array.isArray(unref(form).bed_id) ? ssrLooseContain(unref(form).bed_id, bed.id) : ssrLooseEqual(unref(form).bed_id, bed.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(bed.name)}</option>`);
            });
            _push2(`<!--]--></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.bed_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-445dc6f5${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "live_consultation",
              value: "Live Consultation"
            }, null, _parent2, _scopeId));
            _push2(`<select id="live_consultation" class="block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-445dc6f5${_scopeId}><option value="no" data-v-445dc6f5${ssrIncludeBooleanAttr(Array.isArray(unref(form).live_consultation) ? ssrLooseContain(unref(form).live_consultation, "no") : ssrLooseEqual(unref(form).live_consultation, "no")) ? " selected" : ""}${_scopeId}>No</option><option value="yes" data-v-445dc6f5${ssrIncludeBooleanAttr(Array.isArray(unref(form).live_consultation) ? ssrLooseContain(unref(form).live_consultation, "yes") : ssrLooseEqual(unref(form).live_consultation, "yes")) ? " selected" : ""}${_scopeId}>Yes</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.live_consultation
            }, null, _parent2, _scopeId));
            _push2(`</div></div></div></div><div class="flex items-center justify-end mt-3" data-v-445dc6f5${_scopeId}>`);
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
                            modelValue: props.patients.find((p) => p.id === unref(form).patient_id),
                            "onUpdate:modelValue": handlePatientSelect,
                            options: __props.patients,
                            "track-by": "id",
                            label: "name",
                            placeholder: "Search and select a patient",
                            class: "w-full text-sm rounded-md border border-slate-300"
                          }, null, 8, ["modelValue", "options"]),
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
                          onClick: goToIpdList,
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
                          createTextVNode(" Ipd Patient List ")
                        ])
                      ])
                    ])
                  ])
                ]),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"]),
                  class: "p-4"
                }, [
                  createVNode(AlertMessage),
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
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
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
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
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
                          class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
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
                          class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                          placeholder: "Additional notes"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).note]
                        ]),
                        createVNode(_sfc_main$2, {
                          class: "mt-1",
                          message: unref(form).errors.note
                        }, null, 8, ["message"])
                      ])
                    ]),
                    createVNode("div", { class: "space-y-3" }, [
                      createVNode("div", { class: "w-full grid grid-cols-1 gap-3 md:grid-cols-2" }, [
                        createVNode("div", { class: "col-span-2" }, [
                          createVNode(_sfc_main$3, {
                            for: "admission_date",
                            value: "Admission Date",
                            class: "required"
                          }),
                          withDirectives(createVNode("input", {
                            id: "admission_date",
                            "onUpdate:modelValue": ($event) => unref(form).admission_date = $event,
                            type: "datetime-local",
                            onFocus: handleAdmissionDateFocus,
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                            required: ""
                          }, null, 40, ["onUpdate:modelValue"]), [
                            [vModelText, unref(form).admission_date]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.admission_date
                          }, null, 8, ["message"])
                        ])
                      ]),
                      createVNode("div", { class: "grid grid-cols-1 gap-3 md:grid-cols-2" }, [
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "case",
                            value: "Case"
                          }),
                          withDirectives(createVNode("select", {
                            id: "case",
                            "onUpdate:modelValue": ($event) => unref(form).case = $event,
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
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
                        ]),
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "tpa",
                            value: "Tpa"
                          }),
                          withDirectives(createVNode("input", {
                            id: "tpa",
                            "onUpdate:modelValue": ($event) => unref(form).tpa = $event,
                            type: "text",
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                            placeholder: "tpa"
                          }, null, 8, ["onUpdate:modelValue"]), [
                            [vModelText, unref(form).tpa]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.tpa
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
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
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
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
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
                            for: "credit_limit",
                            value: "Credit Limit"
                          }),
                          withDirectives(createVNode("input", {
                            id: "credit_limit",
                            "onUpdate:modelValue": ($event) => unref(form).credit_limit = $event,
                            type: "text",
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                            placeholder: "Credit Limit"
                          }, null, 8, ["onUpdate:modelValue"]), [
                            [vModelText, unref(form).credit_limit]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.credit_limit
                          }, null, 8, ["message"])
                        ]),
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "reference",
                            value: "Reference"
                          }),
                          withDirectives(createVNode("input", {
                            id: "reference",
                            "onUpdate:modelValue": ($event) => unref(form).reference = $event,
                            type: "text",
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                            placeholder: "Reference"
                          }, null, 8, ["onUpdate:modelValue"]), [
                            [vModelText, unref(form).reference]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.reference
                          }, null, 8, ["message"])
                        ])
                      ]),
                      createVNode("div", { class: "grid grid-cols-1 gap-3 md:grid-cols-2" }, [
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "consultant_doctor_id",
                            value: "Consultant Doctor",
                            class: "required"
                          }),
                          createVNode(unref(Multiselect), {
                            modelValue: props.doctors.find((d) => d.id === unref(form).consultant_doctor_id),
                            "onUpdate:modelValue": handleDoctorSelect,
                            options: __props.doctors,
                            "track-by": "id",
                            label: "name",
                            placeholder: "Search and select a doctor",
                            class: "w-full text-sm rounded-md border border-slate-300"
                          }, null, 8, ["modelValue", "options"]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.consultant_doctor_id
                          }, null, 8, ["message"])
                        ]),
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "bed_group_id",
                            value: "Bed Group",
                            class: "required"
                          }),
                          withDirectives(createVNode("select", {
                            id: "bed_group_id",
                            "onUpdate:modelValue": ($event) => unref(form).bed_group_id = $event,
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                            required: ""
                          }, [
                            createVNode("option", { value: "" }, "Select Group"),
                            (openBlock(true), createBlock(Fragment, null, renderList(__props.bedGroups, (data) => {
                              return openBlock(), createBlock("option", {
                                key: data.id,
                                value: data.id
                              }, toDisplayString(data.name), 9, ["value"]);
                            }), 128))
                          ], 8, ["onUpdate:modelValue"]), [
                            [vModelSelect, unref(form).bed_group_id]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.bed_group_id
                          }, null, 8, ["message"])
                        ])
                      ]),
                      createVNode("div", { class: "grid grid-cols-1 gap-3 md:grid-cols-2" }, [
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "bed_id",
                            value: "Bed Number",
                            class: "required"
                          }),
                          withDirectives(createVNode("select", {
                            id: "bed_id",
                            "onUpdate:modelValue": ($event) => unref(form).bed_id = $event,
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                            required: "",
                            disabled: !unref(form).bed_group_id
                          }, [
                            createVNode("option", { value: "" }, "Select Bed"),
                            (openBlock(true), createBlock(Fragment, null, renderList(filteredBeds.value, (bed) => {
                              return openBlock(), createBlock("option", {
                                key: bed.id,
                                value: bed.id
                              }, toDisplayString(bed.name), 9, ["value"]);
                            }), 128))
                          ], 8, ["onUpdate:modelValue", "disabled"]), [
                            [vModelSelect, unref(form).bed_id]
                          ]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.bed_id
                          }, null, 8, ["message"])
                        ]),
                        createVNode("div", null, [
                          createVNode(_sfc_main$3, {
                            for: "live_consultation",
                            value: "Live Consultation"
                          }),
                          withDirectives(createVNode("select", {
                            id: "live_consultation",
                            "onUpdate:modelValue": ($event) => unref(form).live_consultation = $event,
                            class: "block w-full p-1.5 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/IpdPatient/Form.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const Form = /* @__PURE__ */ _export_sfc(_sfc_main, [["__scopeId", "data-v-445dc6f5"]]);
export {
  Form as default
};
