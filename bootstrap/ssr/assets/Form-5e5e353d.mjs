import { mergeProps, unref, useSSRContext, ref, watch, withCtx, createVNode, createTextVNode, toDisplayString, openBlock, createBlock, withModifiers, withDirectives, vModelText, vModelSelect, Fragment, renderList } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderAttr, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderList, ssrInterpolate } from "vue/server-renderer";
import { _ as _sfc_main$4 } from "./BackendLayout-d755bdca.mjs";
import { useForm, router } from "@inertiajs/vue3";
import { _ as _sfc_main$3 } from "./InputError-6aeb8d97.mjs";
import { _ as _sfc_main$2 } from "./InputLabel-70ca52d1.mjs";
import { _ as _sfc_main$5 } from "./PrimaryButton-b82fb16e.mjs";
import "./AlertMessage-0f422981.mjs";
import { P as PatientModal } from "./PatientModal-85d06e3d.mjs";
import { d as displayResponse, a as displayWarning } from "./responseMessage-d505224b.mjs";
import Multiselect from "vue-multiselect";
/* empty css                           */import "mitt";
import "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
import "date-fns";
import "toastr";
import "sweetalert2";
const _sfc_main$1 = {
  __name: "DoctorModal",
  __ssrInlineRender: true,
  props: {
    isOpen: Boolean,
    // Assuming you'll pass these options from the backend
    designations: Array,
    departments: Array,
    specialists: Array
  },
  emits: ["close", "doctorCreated"],
  setup(__props, { emit: __emit }) {
    const form = useForm({
      name: "",
      email: "",
      phone: "",
      gender: "Male",
      doctor_charge: "",
      designation_id: null,
      department_id: null,
      specialist_id: null
    });
    return (_ctx, _push, _parent, _attrs) => {
      if (__props.isOpen) {
        _push(`<div${ssrRenderAttrs(mergeProps({ class: "fixed inset-0 z-50 overflow-y-auto" }, _attrs))}><div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0"><div class="fixed inset-0 transition-opacity" aria-hidden="true"><div class="absolute inset-0 bg-gray-500 opacity-75"></div></div><span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">​</span><div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full"><div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4"><div class="sm:flex sm:items-start"><div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full"><h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title"> Add New Doctor </h3><div class="mt-2"><form><div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4"><div>`);
        _push(ssrRenderComponent(_sfc_main$2, {
          for: "name",
          value: "Name"
        }, null, _parent));
        _push(`<input id="name"${ssrRenderAttr("value", unref(form).name)} type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" required>`);
        _push(ssrRenderComponent(_sfc_main$3, {
          class: "mt-2",
          message: unref(form).errors.name
        }, null, _parent));
        _push(`</div><div>`);
        _push(ssrRenderComponent(_sfc_main$2, {
          for: "email",
          value: "Email"
        }, null, _parent));
        _push(`<input id="email"${ssrRenderAttr("value", unref(form).email)} type="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" required>`);
        _push(ssrRenderComponent(_sfc_main$3, {
          class: "mt-2",
          message: unref(form).errors.email
        }, null, _parent));
        _push(`</div><div>`);
        _push(ssrRenderComponent(_sfc_main$2, {
          for: "phone",
          value: "Phone"
        }, null, _parent));
        _push(`<input id="phone"${ssrRenderAttr("value", unref(form).phone)} type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">`);
        _push(ssrRenderComponent(_sfc_main$3, {
          class: "mt-2",
          message: unref(form).errors.phone
        }, null, _parent));
        _push(`</div></div><div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4"><div>`);
        _push(ssrRenderComponent(_sfc_main$2, {
          for: "gender",
          value: "Gender"
        }, null, _parent));
        _push(`<select id="gender" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"><option value="Male"${ssrIncludeBooleanAttr(Array.isArray(unref(form).gender) ? ssrLooseContain(unref(form).gender, "Male") : ssrLooseEqual(unref(form).gender, "Male")) ? " selected" : ""}>Male</option><option value="Female"${ssrIncludeBooleanAttr(Array.isArray(unref(form).gender) ? ssrLooseContain(unref(form).gender, "Female") : ssrLooseEqual(unref(form).gender, "Female")) ? " selected" : ""}>Female</option><option value="Other"${ssrIncludeBooleanAttr(Array.isArray(unref(form).gender) ? ssrLooseContain(unref(form).gender, "Other") : ssrLooseEqual(unref(form).gender, "Other")) ? " selected" : ""}>Other</option></select>`);
        _push(ssrRenderComponent(_sfc_main$3, {
          class: "mt-2",
          message: unref(form).errors.gender
        }, null, _parent));
        _push(`</div><div>`);
        _push(ssrRenderComponent(_sfc_main$2, {
          for: "doctor_charge",
          value: "Doctor Charge"
        }, null, _parent));
        _push(`<input id="doctor_charge"${ssrRenderAttr("value", unref(form).doctor_charge)} type="number" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">`);
        _push(ssrRenderComponent(_sfc_main$3, {
          class: "mt-2",
          message: unref(form).errors.doctor_charge
        }, null, _parent));
        _push(`</div><div>`);
        _push(ssrRenderComponent(_sfc_main$2, {
          for: "designation",
          value: "Designation"
        }, null, _parent));
        _push(`<select id="designation" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"><option${ssrRenderAttr("value", null)} disabled${ssrIncludeBooleanAttr(Array.isArray(unref(form).designation_id) ? ssrLooseContain(unref(form).designation_id, null) : ssrLooseEqual(unref(form).designation_id, null)) ? " selected" : ""}>Select a Designation</option><!--[-->`);
        ssrRenderList(__props.designations, (designation) => {
          _push(`<option${ssrRenderAttr("value", designation.id)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).designation_id) ? ssrLooseContain(unref(form).designation_id, designation.id) : ssrLooseEqual(unref(form).designation_id, designation.id)) ? " selected" : ""}>${ssrInterpolate(designation.name)}</option>`);
        });
        _push(`<!--]--></select>`);
        _push(ssrRenderComponent(_sfc_main$3, {
          class: "mt-2",
          message: unref(form).errors.designation_id
        }, null, _parent));
        _push(`</div></div><div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4"><div>`);
        _push(ssrRenderComponent(_sfc_main$2, {
          for: "department",
          value: "Department"
        }, null, _parent));
        _push(`<select id="department" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"><option${ssrRenderAttr("value", null)} disabled${ssrIncludeBooleanAttr(Array.isArray(unref(form).department_id) ? ssrLooseContain(unref(form).department_id, null) : ssrLooseEqual(unref(form).department_id, null)) ? " selected" : ""}>Select a Department</option><!--[-->`);
        ssrRenderList(__props.departments, (department) => {
          _push(`<option${ssrRenderAttr("value", department.id)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).department_id) ? ssrLooseContain(unref(form).department_id, department.id) : ssrLooseEqual(unref(form).department_id, department.id)) ? " selected" : ""}>${ssrInterpolate(department.name)}</option>`);
        });
        _push(`<!--]--></select>`);
        _push(ssrRenderComponent(_sfc_main$3, {
          class: "mt-2",
          message: unref(form).errors.department_id
        }, null, _parent));
        _push(`</div><div>`);
        _push(ssrRenderComponent(_sfc_main$2, {
          for: "specialist",
          value: "Specialist"
        }, null, _parent));
        _push(`<select id="specialist" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"><option${ssrRenderAttr("value", null)} disabled${ssrIncludeBooleanAttr(Array.isArray(unref(form).specialist_id) ? ssrLooseContain(unref(form).specialist_id, null) : ssrLooseEqual(unref(form).specialist_id, null)) ? " selected" : ""}>Select a Specialist</option><!--[-->`);
        ssrRenderList(__props.specialists, (specialist) => {
          _push(`<option${ssrRenderAttr("value", specialist.id)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).specialist_id) ? ssrLooseContain(unref(form).specialist_id, specialist.id) : ssrLooseEqual(unref(form).specialist_id, specialist.id)) ? " selected" : ""}>${ssrInterpolate(specialist.name)}</option>`);
        });
        _push(`<!--]--></select>`);
        _push(ssrRenderComponent(_sfc_main$3, {
          class: "mt-2",
          message: unref(form).errors.specialist_id
        }, null, _parent));
        _push(`</div></div><div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense"><button type="submit"${ssrIncludeBooleanAttr(unref(form).processing) ? " disabled" : ""} class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:col-start-2 sm:text-sm"> Save </button><button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:col-start-1 sm:text-sm"> Cancel </button></div></form></div></div></div></div></div></div></div>`);
      } else {
        _push(`<!---->`);
      }
    };
  }
};
const _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/DoctorModal.vue");
  return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
const _sfc_main = {
  __name: "Form",
  __ssrInlineRender: true,
  props: [
    "appoinment",
    "id",
    "patients",
    "doctors",
    "tpas",
    "designations",
    "departments",
    "specialists"
  ],
  setup(__props) {
    var _a, _b, _c, _d, _e, _f, _g, _h, _i, _j, _k, _l, _m;
    const props = __props;
    const form = useForm({
      patient_id: ((_a = props.appoinment) == null ? void 0 : _a.patient_id) ?? "",
      doctor_id: ((_b = props.appoinment) == null ? void 0 : _b.doctor_id) ?? "",
      doctor_fee: ((_c = props.appoinment) == null ? void 0 : _c.doctor_fee) ?? "",
      shift: ((_d = props.appoinment) == null ? void 0 : _d.shift) ?? "",
      appoinment_date: ((_e = props.appoinment) == null ? void 0 : _e.appoinment_date) ?? "",
      slot: ((_f = props.appoinment) == null ? void 0 : _f.slot) ?? "",
      appointment_priority: ((_g = props.appoinment) == null ? void 0 : _g.appointment_priority) ?? "",
      payment_mode: ((_h = props.appoinment) == null ? void 0 : _h.payment_mode) ?? "",
      appoinment_status: ((_i = props.appoinment) == null ? void 0 : _i.appoinment_status) ?? "Pending",
      discount_percentage: ((_j = props.appoinment) == null ? void 0 : _j.discount_percentage) ?? "",
      message: ((_k = props.appoinment) == null ? void 0 : _k.message) ?? "",
      live_consultant: ((_l = props.appoinment) == null ? void 0 : _l.live_consultant) ?? "",
      _method: ((_m = props.appoinment) == null ? void 0 : _m.id) ? "put" : "post"
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
      closePatientModal();
      router.reload({
        only: ["patients"],
        preserveScroll: true,
        onSuccess: (page) => {
          patientsList.value = [...page.props.patients];
          form.patient_id = newPatient.id;
        }
      });
    };
    const isDoctorModalOpen = ref(false);
    const doctorsList = ref([...props.doctors]);
    const openDoctorModal = () => {
      isDoctorModalOpen.value = true;
    };
    const closeDoctorModal = () => {
      isDoctorModalOpen.value = false;
    };
    const handleDoctorCreated = (newDoctor) => {
      closeDoctorModal();
      router.reload({
        only: ["doctors"],
        preserveScroll: true,
        onSuccess: (page) => {
          doctorsList.value = [...page.props.doctors];
          form.doctor_id = newDoctor.id;
        }
      });
    };
    watch(() => form.doctor_id, (newDoctorId) => {
      if (newDoctorId) {
        const selectedDoctor = doctorsList.value.find(
          (doctor) => doctor.id === (typeof newDoctorId === "object" ? newDoctorId.id : newDoctorId)
        );
        if (selectedDoctor && selectedDoctor.doctor_charge) {
          form.doctor_fee = selectedDoctor.doctor_charge;
        }
      }
    }, { deep: true });
    const setCurrentDateTime = () => {
      if (!form.appoinment_date) {
        const now = /* @__PURE__ */ new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, "0");
        const day = String(now.getDate()).padStart(2, "0");
        const hours = String(now.getHours()).padStart(2, "0");
        const minutes = String(now.getMinutes()).padStart(2, "0");
        form.appoinment_date = `${year}-${month}-${day}T${hours}:${minutes}`;
      }
    };
    const timeSlotOptions = [
      { value: "Morning", label: "Morning (6:00 AM - 12:00 PM)" },
      { value: "Noon", label: "Noon (12:00 PM - 2:00 PM)" },
      { value: "Evening", label: "Evening (2:00 PM - 8:00 PM)" },
      { value: "Night", label: "Night (8:00 PM - 6:00 AM)" }
    ];
    const submit = () => {
      const formData = {
        ...form.data(),
        patient_id: typeof form.patient_id === "object" ? form.patient_id.id : form.patient_id,
        doctor_id: typeof form.doctor_id === "object" ? form.doctor_id.id : form.doctor_id
      };
      const routeName = props.id ? route("backend.appoinment.update", props.id) : route("backend.appoinment.store");
      form.transform((data) => ({
        ...formData,
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
    const goToAppoinmentList = () => {
      router.get(route("backend.appoinment.index"));
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$4, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md"${_scopeId}><div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md"${_scopeId}><div${_scopeId}><h1 class="p-4 text-xl font-bold dark:text-white"${_scopeId}>${ssrInterpolate(_ctx.$page.props.pageTitle)}</h1></div><div class="p-2 py-2 flex items-center space-x-2"${_scopeId}><div class="flex items-center space-x-3"${_scopeId}><button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2"${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"${_scopeId}></path></svg> Appoinment List </button></div></div></div><form class="p-4"${_scopeId}><div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4"${_scopeId}><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "patient_id",
              value: "Patient"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`<span class="text-red-500"${_scopeId2}>*</span>`);
                } else {
                  return [
                    createVNode("span", { class: "text-red-500" }, "*")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`<div class="flex space-x-2"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(Multiselect), {
              modelValue: unref(form).patient_id,
              "onUpdate:modelValue": ($event) => unref(form).patient_id = $event,
              options: patientsList.value,
              "track-by": "id",
              label: "name",
              placeholder: "Search and select a patient",
              class: "w-full text-sm h-[30px] rounded-md border border-slate-300",
              "custom-label": ({ name }) => name,
              "close-on-select": true,
              "preserve-search": false
            }, null, _parent2, _scopeId));
            _push2(`<button type="button" class="px-2 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"${_scopeId}><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"${_scopeId}></path></svg></button></div>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.patient_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "doctor_id",
              value: "Doctor"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`<span class="text-red-500"${_scopeId2}>*</span>`);
                } else {
                  return [
                    createVNode("span", { class: "text-red-500" }, "*")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`<div class="flex space-x-2"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(Multiselect), {
              modelValue: unref(form).doctor_id,
              "onUpdate:modelValue": ($event) => unref(form).doctor_id = $event,
              options: doctorsList.value,
              "track-by": "id",
              label: "name",
              placeholder: "Search and select a doctor",
              class: "w-full text-sm h-[30px] rounded-md border border-slate-300",
              "custom-label": ({ name }) => name,
              "close-on-select": true,
              "preserve-search": false
            }, null, _parent2, _scopeId));
            _push2(`<button type="button" class="px-2 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"${_scopeId}><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"${_scopeId}></path></svg></button></div>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.doctor_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "doctor_fee",
              value: "Doctor Fee"
            }, null, _parent2, _scopeId));
            _push2(`<input id="doctor_fee" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).doctor_fee)} type="number" placeholder="Doctor Fee" readonly${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.doctor_fee
            }, null, _parent2, _scopeId));
            _push2(`<p class="text-xs text-gray-500 mt-1"${_scopeId}>Auto-populated based on selected doctor</p></div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "shift",
              value: "Shift"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`<span class="text-red-500"${_scopeId2}>*</span>`);
                } else {
                  return [
                    createVNode("span", { class: "text-red-500" }, "*")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`<select id="shift" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).shift) ? ssrLooseContain(unref(form).shift, "") : ssrLooseEqual(unref(form).shift, "")) ? " selected" : ""}${_scopeId}>Select Shift</option><option value="Morning"${ssrIncludeBooleanAttr(Array.isArray(unref(form).shift) ? ssrLooseContain(unref(form).shift, "Morning") : ssrLooseEqual(unref(form).shift, "Morning")) ? " selected" : ""}${_scopeId}>Morning</option><option value="Evening"${ssrIncludeBooleanAttr(Array.isArray(unref(form).shift) ? ssrLooseContain(unref(form).shift, "Evening") : ssrLooseEqual(unref(form).shift, "Evening")) ? " selected" : ""}${_scopeId}>Evening</option><option value="Night"${ssrIncludeBooleanAttr(Array.isArray(unref(form).shift) ? ssrLooseContain(unref(form).shift, "Night") : ssrLooseEqual(unref(form).shift, "Night")) ? " selected" : ""}${_scopeId}>Night</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.shift
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "appoinment_date",
              value: "Appointment Date & Time"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`<span class="text-red-500"${_scopeId2}>*</span>`);
                } else {
                  return [
                    createVNode("span", { class: "text-red-500" }, "*")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`<input id="appoinment_date" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).appoinment_date)} type="datetime-local"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.appoinment_date
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "slot",
              value: "Time Slot"
            }, null, _parent2, _scopeId));
            _push2(`<select id="slot" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).slot) ? ssrLooseContain(unref(form).slot, "") : ssrLooseEqual(unref(form).slot, "")) ? " selected" : ""}${_scopeId}>Select Time Slot</option><!--[-->`);
            ssrRenderList(timeSlotOptions, (option) => {
              _push2(`<option${ssrRenderAttr("value", option.value)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).slot) ? ssrLooseContain(unref(form).slot, option.value) : ssrLooseEqual(unref(form).slot, option.value)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(option.label)}</option>`);
            });
            _push2(`<!--]--></select>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.slot
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "appointment_priority",
              value: "Priority"
            }, null, _parent2, _scopeId));
            _push2(`<select id="appointment_priority" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).appointment_priority) ? ssrLooseContain(unref(form).appointment_priority, "") : ssrLooseEqual(unref(form).appointment_priority, "")) ? " selected" : ""}${_scopeId}>Select Priority</option><option value="Normal"${ssrIncludeBooleanAttr(Array.isArray(unref(form).appointment_priority) ? ssrLooseContain(unref(form).appointment_priority, "Normal") : ssrLooseEqual(unref(form).appointment_priority, "Normal")) ? " selected" : ""}${_scopeId}>Normal</option><option value="Urgent"${ssrIncludeBooleanAttr(Array.isArray(unref(form).appointment_priority) ? ssrLooseContain(unref(form).appointment_priority, "Urgent") : ssrLooseEqual(unref(form).appointment_priority, "Urgent")) ? " selected" : ""}${_scopeId}>Urgent</option><option value="Very Urgent"${ssrIncludeBooleanAttr(Array.isArray(unref(form).appointment_priority) ? ssrLooseContain(unref(form).appointment_priority, "Very Urgent") : ssrLooseEqual(unref(form).appointment_priority, "Very Urgent")) ? " selected" : ""}${_scopeId}>Very Urgent</option><option value="Low"${ssrIncludeBooleanAttr(Array.isArray(unref(form).appointment_priority) ? ssrLooseContain(unref(form).appointment_priority, "Low") : ssrLooseEqual(unref(form).appointment_priority, "Low")) ? " selected" : ""}${_scopeId}>Low</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.appointment_priority
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "payment_mode",
              value: "Payment Mode"
            }, null, _parent2, _scopeId));
            _push2(`<select id="payment_mode" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, "") : ssrLooseEqual(unref(form).payment_mode, "")) ? " selected" : ""}${_scopeId}>Select Payment Method</option><option value="Cash"${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, "Cash") : ssrLooseEqual(unref(form).payment_mode, "Cash")) ? " selected" : ""}${_scopeId}>Cash</option><option value="Cheque"${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, "Cheque") : ssrLooseEqual(unref(form).payment_mode, "Cheque")) ? " selected" : ""}${_scopeId}>Cheque</option><option value="Transfer to Bank Account"${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, "Transfer to Bank Account") : ssrLooseEqual(unref(form).payment_mode, "Transfer to Bank Account")) ? " selected" : ""}${_scopeId}>Transfer to Bank Account</option><option value="Upi"${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, "Upi") : ssrLooseEqual(unref(form).payment_mode, "Upi")) ? " selected" : ""}${_scopeId}>Upi</option><option value="Online"${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, "Online") : ssrLooseEqual(unref(form).payment_mode, "Online")) ? " selected" : ""}${_scopeId}>Online</option><option value="Other"${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, "Other") : ssrLooseEqual(unref(form).payment_mode, "Other")) ? " selected" : ""}${_scopeId}>Other</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.payment_mode
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "appoinment_status",
              value: "Appoinment Status"
            }, null, _parent2, _scopeId));
            _push2(`<select id="appoinment_status" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).appoinment_status) ? ssrLooseContain(unref(form).appoinment_status, "") : ssrLooseEqual(unref(form).appoinment_status, "")) ? " selected" : ""}${_scopeId}>Select Status</option><option value="Pending"${ssrIncludeBooleanAttr(Array.isArray(unref(form).appoinment_status) ? ssrLooseContain(unref(form).appoinment_status, "Pending") : ssrLooseEqual(unref(form).appoinment_status, "Pending")) ? " selected" : ""}${_scopeId}>Pending</option><option value="Approved"${ssrIncludeBooleanAttr(Array.isArray(unref(form).appoinment_status) ? ssrLooseContain(unref(form).appoinment_status, "Approved") : ssrLooseEqual(unref(form).appoinment_status, "Approved")) ? " selected" : ""}${_scopeId}>Approved</option><option value="Cancelled"${ssrIncludeBooleanAttr(Array.isArray(unref(form).appoinment_status) ? ssrLooseContain(unref(form).appoinment_status, "Cancelled") : ssrLooseEqual(unref(form).appoinment_status, "Cancelled")) ? " selected" : ""}${_scopeId}>Cancelled</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.appoinment_status
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "discount_percentage",
              value: "Discount (%)"
            }, null, _parent2, _scopeId));
            _push2(`<input id="discount_percentage" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).discount_percentage)} type="number" min="0" max="100" placeholder="Discount Percentage"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.discount_percentage
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "live_consultant",
              value: "Live Consultant"
            }, null, _parent2, _scopeId));
            _push2(`<select id="live_consultant" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).live_consultant) ? ssrLooseContain(unref(form).live_consultant, "") : ssrLooseEqual(unref(form).live_consultant, "")) ? " selected" : ""}${_scopeId}>Choose Option</option><option value="Yes"${ssrIncludeBooleanAttr(Array.isArray(unref(form).live_consultant) ? ssrLooseContain(unref(form).live_consultant, "Yes") : ssrLooseEqual(unref(form).live_consultant, "Yes")) ? " selected" : ""}${_scopeId}>Yes</option><option value="No"${ssrIncludeBooleanAttr(Array.isArray(unref(form).live_consultant) ? ssrLooseContain(unref(form).live_consultant, "No") : ssrLooseEqual(unref(form).live_consultant, "No")) ? " selected" : ""}${_scopeId}>No</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.live_consultant
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1 md:col-span-4"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "message",
              value: "Message/Notes"
            }, null, _parent2, _scopeId));
            _push2(`<textarea id="message" rows="3" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600" placeholder="Any special notes or message"${_scopeId}>${ssrInterpolate(unref(form).message)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.message
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="flex items-center justify-end mt-4"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$5, {
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
            _push2(ssrRenderComponent(PatientModal, {
              isOpen: isPatientModalOpen.value,
              onClose: closePatientModal,
              onPatientCreated: handlePatientCreated
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$1, {
              isOpen: isDoctorModalOpen.value,
              onClose: closeDoctorModal,
              onDoctorCreated: handleDoctorCreated,
              designations: props.designations,
              departments: props.departments,
              specialists: props.specialists
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
                        onClick: goToAppoinmentList,
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
                        createTextVNode(" Appoinment List ")
                      ])
                    ])
                  ])
                ]),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"]),
                  class: "p-4"
                }, [
                  createVNode("div", { class: "grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4" }, [
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "patient_id",
                        value: "Patient"
                      }, {
                        default: withCtx(() => [
                          createVNode("span", { class: "text-red-500" }, "*")
                        ]),
                        _: 1
                      }),
                      createVNode("div", { class: "flex space-x-2" }, [
                        createVNode(unref(Multiselect), {
                          modelValue: unref(form).patient_id,
                          "onUpdate:modelValue": ($event) => unref(form).patient_id = $event,
                          options: patientsList.value,
                          "track-by": "id",
                          label: "name",
                          placeholder: "Search and select a patient",
                          class: "w-full text-sm h-[30px] rounded-md border border-slate-300",
                          "custom-label": ({ name }) => name,
                          "close-on-select": true,
                          "preserve-search": false
                        }, null, 8, ["modelValue", "onUpdate:modelValue", "options", "custom-label"]),
                        createVNode("button", {
                          type: "button",
                          onClick: openPatientModal,
                          class: "px-2 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
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
                        ])
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.patient_id
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "doctor_id",
                        value: "Doctor"
                      }, {
                        default: withCtx(() => [
                          createVNode("span", { class: "text-red-500" }, "*")
                        ]),
                        _: 1
                      }),
                      createVNode("div", { class: "flex space-x-2" }, [
                        createVNode(unref(Multiselect), {
                          modelValue: unref(form).doctor_id,
                          "onUpdate:modelValue": ($event) => unref(form).doctor_id = $event,
                          options: doctorsList.value,
                          "track-by": "id",
                          label: "name",
                          placeholder: "Search and select a doctor",
                          class: "w-full text-sm h-[30px] rounded-md border border-slate-300",
                          "custom-label": ({ name }) => name,
                          "close-on-select": true,
                          "preserve-search": false
                        }, null, 8, ["modelValue", "onUpdate:modelValue", "options", "custom-label"]),
                        createVNode("button", {
                          type: "button",
                          onClick: openDoctorModal,
                          class: "px-2 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
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
                        ])
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.doctor_id
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "doctor_fee",
                        value: "Doctor Fee"
                      }),
                      withDirectives(createVNode("input", {
                        id: "doctor_fee",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).doctor_fee = $event,
                        type: "number",
                        placeholder: "Doctor Fee",
                        readonly: ""
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).doctor_fee]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.doctor_fee
                      }, null, 8, ["message"]),
                      createVNode("p", { class: "text-xs text-gray-500 mt-1" }, "Auto-populated based on selected doctor")
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "shift",
                        value: "Shift"
                      }, {
                        default: withCtx(() => [
                          createVNode("span", { class: "text-red-500" }, "*")
                        ]),
                        _: 1
                      }),
                      withDirectives(createVNode("select", {
                        id: "shift",
                        "onUpdate:modelValue": ($event) => unref(form).shift = $event,
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600"
                      }, [
                        createVNode("option", { value: "" }, "Select Shift"),
                        createVNode("option", { value: "Morning" }, "Morning"),
                        createVNode("option", { value: "Evening" }, "Evening"),
                        createVNode("option", { value: "Night" }, "Night")
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).shift]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.shift
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "appoinment_date",
                        value: "Appointment Date & Time"
                      }, {
                        default: withCtx(() => [
                          createVNode("span", { class: "text-red-500" }, "*")
                        ]),
                        _: 1
                      }),
                      withDirectives(createVNode("input", {
                        id: "appoinment_date",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).appoinment_date = $event,
                        type: "datetime-local",
                        onClick: setCurrentDateTime
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).appoinment_date]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.appoinment_date
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "slot",
                        value: "Time Slot"
                      }),
                      withDirectives(createVNode("select", {
                        id: "slot",
                        "onUpdate:modelValue": ($event) => unref(form).slot = $event,
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600"
                      }, [
                        createVNode("option", { value: "" }, "Select Time Slot"),
                        (openBlock(), createBlock(Fragment, null, renderList(timeSlotOptions, (option) => {
                          return createVNode("option", {
                            key: option.value,
                            value: option.value
                          }, toDisplayString(option.label), 9, ["value"]);
                        }), 64))
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).slot]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.slot
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "appointment_priority",
                        value: "Priority"
                      }),
                      withDirectives(createVNode("select", {
                        id: "appointment_priority",
                        "onUpdate:modelValue": ($event) => unref(form).appointment_priority = $event,
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600"
                      }, [
                        createVNode("option", { value: "" }, "Select Priority"),
                        createVNode("option", { value: "Normal" }, "Normal"),
                        createVNode("option", { value: "Urgent" }, "Urgent"),
                        createVNode("option", { value: "Very Urgent" }, "Very Urgent"),
                        createVNode("option", { value: "Low" }, "Low")
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).appointment_priority]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.appointment_priority
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "payment_mode",
                        value: "Payment Mode"
                      }),
                      withDirectives(createVNode("select", {
                        id: "payment_mode",
                        "onUpdate:modelValue": ($event) => unref(form).payment_mode = $event,
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600"
                      }, [
                        createVNode("option", { value: "" }, "Select Payment Method"),
                        createVNode("option", { value: "Cash" }, "Cash"),
                        createVNode("option", { value: "Cheque" }, "Cheque"),
                        createVNode("option", { value: "Transfer to Bank Account" }, "Transfer to Bank Account"),
                        createVNode("option", { value: "Upi" }, "Upi"),
                        createVNode("option", { value: "Online" }, "Online"),
                        createVNode("option", { value: "Other" }, "Other")
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).payment_mode]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.payment_mode
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "appoinment_status",
                        value: "Appoinment Status"
                      }),
                      withDirectives(createVNode("select", {
                        id: "appoinment_status",
                        "onUpdate:modelValue": ($event) => unref(form).appoinment_status = $event,
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600"
                      }, [
                        createVNode("option", { value: "" }, "Select Status"),
                        createVNode("option", { value: "Pending" }, "Pending"),
                        createVNode("option", { value: "Approved" }, "Approved"),
                        createVNode("option", { value: "Cancelled" }, "Cancelled")
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).appoinment_status]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.appoinment_status
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "discount_percentage",
                        value: "Discount (%)"
                      }),
                      withDirectives(createVNode("input", {
                        id: "discount_percentage",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).discount_percentage = $event,
                        type: "number",
                        min: "0",
                        max: "100",
                        placeholder: "Discount Percentage"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).discount_percentage]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.discount_percentage
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1" }, [
                      createVNode(_sfc_main$2, {
                        for: "live_consultant",
                        value: "Live Consultant"
                      }),
                      withDirectives(createVNode("select", {
                        id: "live_consultant",
                        "onUpdate:modelValue": ($event) => unref(form).live_consultant = $event,
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600"
                      }, [
                        createVNode("option", { value: "" }, "Choose Option"),
                        createVNode("option", { value: "Yes" }, "Yes"),
                        createVNode("option", { value: "No" }, "No")
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).live_consultant]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.live_consultant
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", { class: "col-span-1 md:col-span-4" }, [
                      createVNode(_sfc_main$2, {
                        for: "message",
                        value: "Message/Notes"
                      }),
                      withDirectives(createVNode("textarea", {
                        id: "message",
                        rows: "3",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).message = $event,
                        placeholder: "Any special notes or message"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).message]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.message
                      }, null, 8, ["message"])
                    ])
                  ]),
                  createVNode("div", { class: "flex items-center justify-end mt-4" }, [
                    createVNode(_sfc_main$5, {
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
              createVNode(PatientModal, {
                isOpen: isPatientModalOpen.value,
                onClose: closePatientModal,
                onPatientCreated: handlePatientCreated
              }, null, 8, ["isOpen"]),
              createVNode(_sfc_main$1, {
                isOpen: isDoctorModalOpen.value,
                onClose: closeDoctorModal,
                onDoctorCreated: handleDoctorCreated,
                designations: props.designations,
                departments: props.departments,
                specialists: props.specialists
              }, null, 8, ["isOpen", "designations", "departments", "specialists"])
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/Appoinment/Form.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
