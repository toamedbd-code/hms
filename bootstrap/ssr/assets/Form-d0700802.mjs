import { ref, watch, nextTick, resolveComponent, withCtx, unref, createTextVNode, toDisplayString, createVNode, openBlock, createBlock, withModifiers, withDirectives, vModelText, vModelSelect, createCommentVNode, Fragment, renderList, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrRenderAttr, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderList } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import { useForm, router } from "@inertiajs/vue3";
import { _ as _sfc_main$3 } from "./InputError-6aeb8d97.mjs";
import { _ as _sfc_main$2 } from "./InputLabel-70ca52d1.mjs";
import { _ as _sfc_main$4 } from "./PrimaryButton-b82fb16e.mjs";
import { d as displayResponse, a as displayWarning } from "./responseMessage-d505224b.mjs";
import { parse, isValid, differenceInYears, subYears, differenceInMonths, subMonths, differenceInDays, subDays, format } from "date-fns";
import { _ as _export_sfc } from "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "mitt";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
import "toastr";
import "sweetalert2";
const Form_vue_vue_type_style_index_0_scoped_a512907b_lang = "";
const _sfc_main = {
  __name: "Form",
  __ssrInlineRender: true,
  props: ["patient", "id", "tpas"],
  setup(__props) {
    var _a, _b, _c, _d, _e, _f, _g, _h, _i, _j, _k, _l, _m, _n, _o, _p, _q;
    const props = __props;
    const ageYears = ref("");
    const ageMonths = ref("");
    const ageDays = ref("");
    const ageYearsInput = ref(null);
    const ageMonthsInput = ref(null);
    const ageDaysInput = ref(null);
    if ((_a = props.patient) == null ? void 0 : _a.age) {
      const ageParts = props.patient.age.match(/(\d+)\s*year|(\d+)\s*month|(\d+)\s*day/gi) || [];
      ageParts.forEach((part) => {
        if (part.includes("year"))
          ageYears.value = part.replace(/\D/g, "");
        if (part.includes("month"))
          ageMonths.value = part.replace(/\D/g, "");
        if (part.includes("day"))
          ageDays.value = part.replace(/\D/g, "");
      });
    }
    const form = useForm({
      name: ((_b = props.patient) == null ? void 0 : _b.name) ?? "",
      guardian_name: ((_c = props.patient) == null ? void 0 : _c.guardian_name) ?? "",
      gender: ((_d = props.patient) == null ? void 0 : _d.gender) ?? "",
      dob: ((_e = props.patient) == null ? void 0 : _e.dob) ?? "",
      blood_group: ((_f = props.patient) == null ? void 0 : _f.blood_group) ?? "",
      marital_status: ((_g = props.patient) == null ? void 0 : _g.marital_status) ?? "",
      photo: null,
      phone: ((_h = props.patient) == null ? void 0 : _h.phone) ?? "",
      email: ((_i = props.patient) == null ? void 0 : _i.email) ?? "",
      address: ((_j = props.patient) == null ? void 0 : _j.address) ?? "",
      remarks: ((_k = props.patient) == null ? void 0 : _k.remarks) ?? "",
      any_known_allergies: ((_l = props.patient) == null ? void 0 : _l.any_known_allergies) ?? "",
      tpa_id: ((_m = props.patient) == null ? void 0 : _m.tpa_id) ?? "",
      tpa_code: ((_n = props.patient) == null ? void 0 : _n.tpa_code) ?? "",
      tpa_validity: ((_o = props.patient) == null ? void 0 : _o.tpa_validity) ?? "",
      tpa_nid: ((_p = props.patient) == null ? void 0 : _p.tpa_nid) ?? "",
      age: ((_q = props.patient) == null ? void 0 : _q.age) ?? "",
      _method: props.id ? "put" : "post"
    });
    const handlePhotoChange = (event) => {
      const file = event.target.files[0];
      form.photo = file;
      const reader = new FileReader();
      reader.onload = (e) => {
        form.photoPreview = e.target.result;
      };
      reader.readAsDataURL(file);
    };
    const updatingFrom = ref(null);
    watch(() => props.isOpen, (newValue) => {
      if (!newValue) {
        form.reset();
        ageYears.value = "";
        ageMonths.value = "";
        ageDays.value = "";
      } else {
        nextTick(() => {
          var _a2;
          (_a2 = document.getElementById("name")) == null ? void 0 : _a2.focus();
        });
      }
    });
    watch(() => form.dob, (newDob) => {
      if (updatingFrom.value === "age")
        return;
      updatingFrom.value = "dob";
      if (newDob) {
        const birthDate = parse(newDob, "yyyy-MM-dd", /* @__PURE__ */ new Date());
        if (!isValid(birthDate))
          return;
        const today = /* @__PURE__ */ new Date();
        let years = differenceInYears(today, birthDate);
        let remainingDate = subYears(today, years);
        let months = differenceInMonths(remainingDate, birthDate);
        remainingDate = subMonths(remainingDate, months);
        let days = differenceInDays(remainingDate, birthDate);
        ageYears.value = years > 0 ? years.toString() : "";
        ageMonths.value = months > 0 ? months.toString() : "";
        ageDays.value = days > 0 ? days.toString() : "";
      } else {
        ageYears.value = "";
        ageMonths.value = "";
        ageDays.value = "";
      }
      updatingFrom.value = null;
    });
    watch([ageYears, ageMonths, ageDays], ([years, months, days]) => {
      if (updatingFrom.value === "dob")
        return;
      updatingFrom.value = "age";
      const yearsNum = parseInt(years) || 0;
      const monthsNum = parseInt(months) || 0;
      const daysNum = parseInt(days) || 0;
      if (yearsNum === 0 && monthsNum === 0 && daysNum === 0) {
        form.dob = "";
        updatingFrom.value = null;
        return;
      }
      let dobDate = /* @__PURE__ */ new Date();
      if (yearsNum > 0)
        dobDate = subYears(dobDate, yearsNum);
      if (monthsNum > 0)
        dobDate = subMonths(dobDate, monthsNum);
      if (daysNum > 0)
        dobDate = subDays(dobDate, daysNum);
      if (dobDate > /* @__PURE__ */ new Date()) {
        form.dob = "";
      } else {
        form.dob = format(dobDate, "yyyy-MM-dd");
      }
      updatingFrom.value = null;
    }, { deep: true });
    const handleAgeInput = (currentInput, nextInput) => {
      return (e) => {
        if (e.target.value.length >= 2 && nextInput) {
          nextTick(() => {
            nextInput.value.focus();
            nextInput.value.select();
          });
        }
      };
    };
    watch(() => form.tpa_id, (tpaId) => {
      if (tpaId) {
        const selectedTPA = props.tpas.find((tpa) => tpa.id === Number(tpaId));
        if (selectedTPA) {
          form.tpa_code = selectedTPA.code;
        } else {
          form.tpa_code = "";
        }
      } else {
        form.tpa_code = "";
      }
    });
    const submit = () => {
      const yearsNum = parseInt(ageYears.value) || 0;
      const monthsNum = parseInt(ageMonths.value) || 0;
      const daysNum = parseInt(ageDays.value) || 0;
      form.age = [
        yearsNum > 0 ? `${yearsNum} year${yearsNum !== 1 ? "s" : ""}` : "",
        monthsNum > 0 ? `${monthsNum} month${monthsNum !== 1 ? "s" : ""}` : "",
        daysNum > 0 ? `${daysNum} day${daysNum !== 1 ? "s" : ""}` : ""
      ].filter(Boolean).join(" ");
      const routeName = props.id ? route("backend.patient.update", props.id) : route("backend.patient.store");
      form.transform((data) => ({
        ...data,
        _method: props.id ? "put" : "post"
      })).post(routeName, {
        onSuccess: (response) => {
          if (!props.id) {
            form.reset();
            ageYears.value = "";
            ageMonths.value = "";
            ageDays.value = "";
          }
          displayResponse(response);
        },
        onError: (errorObject) => {
          displayWarning(errorObject);
        }
      });
    };
    const goToPatientList = () => {
      router.visit(route("backend.patient.index"));
    };
    return (_ctx, _push, _parent, _attrs) => {
      const _component_AlertMessage = resolveComponent("AlertMessage");
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="w-full bg-white border rounded-md" data-v-a512907b${_scopeId}><div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md" data-v-a512907b${_scopeId}><div data-v-a512907b${_scopeId}><h1 class="p-4 text-xl font-bold dark:text-white" data-v-a512907b${_scopeId}>${ssrInterpolate(_ctx.$page.props.pageTitle)}</h1></div><div class="p-2 py-2 flex items-center space-x-2" data-v-a512907b${_scopeId}><div class="flex items-center space-x-3" data-v-a512907b${_scopeId}><button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2" data-v-a512907b${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" data-v-a512907b${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" data-v-a512907b${_scopeId}></path></svg> Patient List </button></div></div></div><form class="p-3 space-y-4" data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_component_AlertMessage, null, null, _parent2, _scopeId));
            _push2(`<div class="grid grid-cols-4 md:grid-cols-4 lg:grid-cols-4 gap-4" data-v-a512907b${_scopeId}><div data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "name",
              value: "Full Name"
            }, null, _parent2, _scopeId));
            _push2(`<input id="name"${ssrRenderAttr("value", unref(form).name)} type="text" placeholder="Name" class="form-input" data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              message: unref(form).errors.name
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "guardian_name",
              value: "Guardian Name"
            }, null, _parent2, _scopeId));
            _push2(`<input id="guardian_name"${ssrRenderAttr("value", unref(form).guardian_name)} type="text" placeholder="Guardian Name" class="form-input" data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              message: unref(form).errors.guardian_name
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "gender",
              value: "Gender"
            }, null, _parent2, _scopeId));
            _push2(`<select id="gender" class="form-input" data-v-a512907b${_scopeId}><option value="" data-v-a512907b${ssrIncludeBooleanAttr(Array.isArray(unref(form).gender) ? ssrLooseContain(unref(form).gender, "") : ssrLooseEqual(unref(form).gender, "")) ? " selected" : ""}${_scopeId}>Select Gender</option><option value="Male" data-v-a512907b${ssrIncludeBooleanAttr(Array.isArray(unref(form).gender) ? ssrLooseContain(unref(form).gender, "Male") : ssrLooseEqual(unref(form).gender, "Male")) ? " selected" : ""}${_scopeId}>Male</option><option value="Female" data-v-a512907b${ssrIncludeBooleanAttr(Array.isArray(unref(form).gender) ? ssrLooseContain(unref(form).gender, "Female") : ssrLooseEqual(unref(form).gender, "Female")) ? " selected" : ""}${_scopeId}>Female</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              message: unref(form).errors.gender
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "photo",
              value: "Photo"
            }, null, _parent2, _scopeId));
            if (unref(form).photoPreview) {
              _push2(`<div class="mt-2" data-v-a512907b${_scopeId}><img${ssrRenderAttr("src", unref(form).photoPreview)} alt="Photo Preview" class="h-10 w-10 object-cover rounded" data-v-a512907b${_scopeId}></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<input id="photo" type="file" accept="image/*" class="form-input" data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              message: unref(form).errors.photo
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "dob",
              value: "Date of Birth"
            }, null, _parent2, _scopeId));
            _push2(`<input id="dob"${ssrRenderAttr("value", unref(form).dob)} type="date" class="form-input" data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              message: unref(form).errors.dob
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, { value: "Age" }, null, _parent2, _scopeId));
            _push2(`<div class="flex space-x-1" data-v-a512907b${_scopeId}><input${ssrRenderAttr("value", ageYears.value)} type="number" min="0" max="120" class="w-16 form-input" placeholder="Y" data-v-a512907b${_scopeId}><span class="self-center text-gray-500" data-v-a512907b${_scopeId}>y</span><input${ssrRenderAttr("value", ageMonths.value)} type="number" min="0" max="11" class="w-16 form-input" placeholder="M" data-v-a512907b${_scopeId}><span class="self-center text-gray-500" data-v-a512907b${_scopeId}>m</span><input${ssrRenderAttr("value", ageDays.value)} type="number" min="0" max="30" class="w-16 form-input" placeholder="D" data-v-a512907b${_scopeId}><span class="self-center text-gray-500" data-v-a512907b${_scopeId}>d</span></div></div><div data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "blood_group",
              value: "Blood Group"
            }, null, _parent2, _scopeId));
            _push2(`<select id="blood_group" class="form-input" data-v-a512907b${_scopeId}><option value="" data-v-a512907b${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "") : ssrLooseEqual(unref(form).blood_group, "")) ? " selected" : ""}${_scopeId}>Select Blood Group</option><option value="A+" data-v-a512907b${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "A+") : ssrLooseEqual(unref(form).blood_group, "A+")) ? " selected" : ""}${_scopeId}>A+</option><option value="B+" data-v-a512907b${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "B+") : ssrLooseEqual(unref(form).blood_group, "B+")) ? " selected" : ""}${_scopeId}>B+</option><option value="O+" data-v-a512907b${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "O+") : ssrLooseEqual(unref(form).blood_group, "O+")) ? " selected" : ""}${_scopeId}>O+</option><option value="AB+" data-v-a512907b${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "AB+") : ssrLooseEqual(unref(form).blood_group, "AB+")) ? " selected" : ""}${_scopeId}>AB+</option><option value="A-" data-v-a512907b${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "A-") : ssrLooseEqual(unref(form).blood_group, "A-")) ? " selected" : ""}${_scopeId}>A-</option><option value="B-" data-v-a512907b${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "B-") : ssrLooseEqual(unref(form).blood_group, "B-")) ? " selected" : ""}${_scopeId}>B-</option><option value="O-" data-v-a512907b${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "O-") : ssrLooseEqual(unref(form).blood_group, "O-")) ? " selected" : ""}${_scopeId}>O-</option><option value="AB-" data-v-a512907b${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "AB-") : ssrLooseEqual(unref(form).blood_group, "AB-")) ? " selected" : ""}${_scopeId}>AB-</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              message: unref(form).errors.blood_group
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "marital_status",
              value: "Marital Status"
            }, null, _parent2, _scopeId));
            _push2(`<select id="marital_status" class="form-input" data-v-a512907b${_scopeId}><option value="" data-v-a512907b${ssrIncludeBooleanAttr(Array.isArray(unref(form).marital_status) ? ssrLooseContain(unref(form).marital_status, "") : ssrLooseEqual(unref(form).marital_status, "")) ? " selected" : ""}${_scopeId}>Select Marital Status</option><option value="Single" data-v-a512907b${ssrIncludeBooleanAttr(Array.isArray(unref(form).marital_status) ? ssrLooseContain(unref(form).marital_status, "Single") : ssrLooseEqual(unref(form).marital_status, "Single")) ? " selected" : ""}${_scopeId}>Single</option><option value="Married" data-v-a512907b${ssrIncludeBooleanAttr(Array.isArray(unref(form).marital_status) ? ssrLooseContain(unref(form).marital_status, "Married") : ssrLooseEqual(unref(form).marital_status, "Married")) ? " selected" : ""}${_scopeId}>Married</option><option value="Widowed" data-v-a512907b${ssrIncludeBooleanAttr(Array.isArray(unref(form).marital_status) ? ssrLooseContain(unref(form).marital_status, "Widowed") : ssrLooseEqual(unref(form).marital_status, "Widowed")) ? " selected" : ""}${_scopeId}>Widowed</option><option value="Separated" data-v-a512907b${ssrIncludeBooleanAttr(Array.isArray(unref(form).marital_status) ? ssrLooseContain(unref(form).marital_status, "Separated") : ssrLooseEqual(unref(form).marital_status, "Separated")) ? " selected" : ""}${_scopeId}>Separated</option><option value="Not Specific" data-v-a512907b${ssrIncludeBooleanAttr(Array.isArray(unref(form).marital_status) ? ssrLooseContain(unref(form).marital_status, "Not Specific") : ssrLooseEqual(unref(form).marital_status, "Not Specific")) ? " selected" : ""}${_scopeId}>Not Specific</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              message: unref(form).errors.marital_status
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "phone",
              value: "Phone"
            }, null, _parent2, _scopeId));
            _push2(`<input id="phone"${ssrRenderAttr("value", unref(form).phone)} type="text" placeholder="Phone Number" class="form-input" data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              message: unref(form).errors.phone
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "email",
              value: "Email"
            }, null, _parent2, _scopeId));
            _push2(`<input id="email"${ssrRenderAttr("value", unref(form).email)} type="email" placeholder="Email" class="form-input" data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              message: unref(form).errors.email
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "address",
              value: "Address"
            }, null, _parent2, _scopeId));
            _push2(`<input id="address"${ssrRenderAttr("value", unref(form).address)} type="text" placeholder="Address" class="form-input" data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              message: unref(form).errors.address
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "remarks",
              value: "Remarks"
            }, null, _parent2, _scopeId));
            _push2(`<textarea id="remarks" rows="2" class="form-input" data-v-a512907b${_scopeId}>${ssrInterpolate(unref(form).remarks)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              message: unref(form).errors.remarks
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "any_known_allergies",
              value: "Known Allergies"
            }, null, _parent2, _scopeId));
            _push2(`<textarea id="any_known_allergies" rows="2" class="form-input" data-v-a512907b${_scopeId}>${ssrInterpolate(unref(form).any_known_allergies)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              message: unref(form).errors.any_known_allergies
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "tpa_id",
              value: "TPA ID"
            }, null, _parent2, _scopeId));
            _push2(`<select id="tpa_id" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" placeholder="Select Role" data-v-a512907b${_scopeId}><option value="" data-v-a512907b${ssrIncludeBooleanAttr(Array.isArray(unref(form).tpa_id) ? ssrLooseContain(unref(form).tpa_id, "") : ssrLooseEqual(unref(form).tpa_id, "")) ? " selected" : ""}${_scopeId}>--Select TPA--</option><!--[-->`);
            ssrRenderList(__props.tpas, (data) => {
              _push2(`<option${ssrRenderAttr("value", data.id)} data-v-a512907b${ssrIncludeBooleanAttr(Array.isArray(unref(form).tpa_id) ? ssrLooseContain(unref(form).tpa_id, data.id) : ssrLooseEqual(unref(form).tpa_id, data.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(data.name)}</option>`);
            });
            _push2(`<!--]--></select>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              message: unref(form).errors.tpa_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "tpa_code",
              value: "TPA ID"
            }, null, _parent2, _scopeId));
            _push2(`<input id="any_known_allergies" type="text"${ssrRenderAttr("value", unref(form).tpa_code)} class="form-input" data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              message: unref(form).errors.tpa_code
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "tpa_validity",
              value: "TPA Validity"
            }, null, _parent2, _scopeId));
            _push2(`<input id="tpa_validity" type="date"${ssrRenderAttr("value", unref(form).tpa_validity)} class="form-input" data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              message: unref(form).errors.tpa_validity
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "tpa_nid",
              value: "National Identification Number"
            }, null, _parent2, _scopeId));
            _push2(`<input id="tpa_nid" type="number"${ssrRenderAttr("value", unref(form).tpa_nid)} class="form-input" data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              message: unref(form).errors.tpa_nid
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="mt-6 flex justify-end" data-v-a512907b${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              type: "submit",
              disabled: unref(form).processing,
              class: { "opacity-25": unref(form).processing }
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`${ssrInterpolate(props.id ? "Update" : "Create")}`);
                } else {
                  return [
                    createTextVNode(toDisplayString(props.id ? "Update" : "Create"), 1)
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</div></form></div>`);
          } else {
            return [
              createVNode("div", { class: "w-full bg-white border rounded-md" }, [
                createVNode("div", { class: "flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md" }, [
                  createVNode("div", null, [
                    createVNode("h1", { class: "p-4 text-xl font-bold dark:text-white" }, toDisplayString(_ctx.$page.props.pageTitle), 1)
                  ]),
                  createVNode("div", { class: "p-2 py-2 flex items-center space-x-2" }, [
                    createVNode("div", { class: "flex items-center space-x-3" }, [
                      createVNode("button", {
                        onClick: goToPatientList,
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
                        createTextVNode(" Patient List ")
                      ])
                    ])
                  ])
                ]),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"]),
                  class: "p-3 space-y-4"
                }, [
                  createVNode(_component_AlertMessage),
                  createVNode("div", { class: "grid grid-cols-4 md:grid-cols-4 lg:grid-cols-4 gap-4" }, [
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "name",
                        value: "Full Name"
                      }),
                      withDirectives(createVNode("input", {
                        id: "name",
                        "onUpdate:modelValue": ($event) => unref(form).name = $event,
                        type: "text",
                        placeholder: "Name",
                        class: "form-input"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).name]
                      ]),
                      createVNode(_sfc_main$3, {
                        message: unref(form).errors.name
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "guardian_name",
                        value: "Guardian Name"
                      }),
                      withDirectives(createVNode("input", {
                        id: "guardian_name",
                        "onUpdate:modelValue": ($event) => unref(form).guardian_name = $event,
                        type: "text",
                        placeholder: "Guardian Name",
                        class: "form-input"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).guardian_name]
                      ]),
                      createVNode(_sfc_main$3, {
                        message: unref(form).errors.guardian_name
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "gender",
                        value: "Gender"
                      }),
                      withDirectives(createVNode("select", {
                        id: "gender",
                        "onUpdate:modelValue": ($event) => unref(form).gender = $event,
                        class: "form-input"
                      }, [
                        createVNode("option", { value: "" }, "Select Gender"),
                        createVNode("option", { value: "Male" }, "Male"),
                        createVNode("option", { value: "Female" }, "Female")
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).gender]
                      ]),
                      createVNode(_sfc_main$3, {
                        message: unref(form).errors.gender
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "photo",
                        value: "Photo"
                      }),
                      unref(form).photoPreview ? (openBlock(), createBlock("div", {
                        key: 0,
                        class: "mt-2"
                      }, [
                        createVNode("img", {
                          src: unref(form).photoPreview,
                          alt: "Photo Preview",
                          class: "h-10 w-10 object-cover rounded"
                        }, null, 8, ["src"])
                      ])) : createCommentVNode("", true),
                      createVNode("input", {
                        id: "photo",
                        type: "file",
                        accept: "image/*",
                        onChange: handlePhotoChange,
                        class: "form-input"
                      }, null, 32),
                      createVNode(_sfc_main$3, {
                        message: unref(form).errors.photo
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "dob",
                        value: "Date of Birth"
                      }),
                      withDirectives(createVNode("input", {
                        id: "dob",
                        "onUpdate:modelValue": ($event) => unref(form).dob = $event,
                        type: "date",
                        class: "form-input"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).dob]
                      ]),
                      createVNode(_sfc_main$3, {
                        message: unref(form).errors.dob
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, { value: "Age" }),
                      createVNode("div", { class: "flex space-x-1" }, [
                        withDirectives(createVNode("input", {
                          ref_key: "ageYearsInput",
                          ref: ageYearsInput,
                          "onUpdate:modelValue": ($event) => ageYears.value = $event,
                          onInput: ($event) => handleAgeInput(ageYearsInput.value, ageMonthsInput.value),
                          type: "number",
                          min: "0",
                          max: "120",
                          class: "w-16 form-input",
                          placeholder: "Y",
                          onFocus: ($event) => $event.target.select()
                        }, null, 40, ["onUpdate:modelValue", "onInput", "onFocus"]), [
                          [vModelText, ageYears.value]
                        ]),
                        createVNode("span", { class: "self-center text-gray-500" }, "y"),
                        withDirectives(createVNode("input", {
                          ref_key: "ageMonthsInput",
                          ref: ageMonthsInput,
                          "onUpdate:modelValue": ($event) => ageMonths.value = $event,
                          onInput: ($event) => handleAgeInput(ageMonthsInput.value, ageDaysInput.value),
                          type: "number",
                          min: "0",
                          max: "11",
                          class: "w-16 form-input",
                          placeholder: "M",
                          onFocus: ($event) => $event.target.select()
                        }, null, 40, ["onUpdate:modelValue", "onInput", "onFocus"]), [
                          [vModelText, ageMonths.value]
                        ]),
                        createVNode("span", { class: "self-center text-gray-500" }, "m"),
                        withDirectives(createVNode("input", {
                          ref_key: "ageDaysInput",
                          ref: ageDaysInput,
                          "onUpdate:modelValue": ($event) => ageDays.value = $event,
                          onInput: ($event) => handleAgeInput(ageDaysInput.value, null),
                          type: "number",
                          min: "0",
                          max: "30",
                          class: "w-16 form-input",
                          placeholder: "D",
                          onFocus: ($event) => $event.target.select()
                        }, null, 40, ["onUpdate:modelValue", "onInput", "onFocus"]), [
                          [vModelText, ageDays.value]
                        ]),
                        createVNode("span", { class: "self-center text-gray-500" }, "d")
                      ])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "blood_group",
                        value: "Blood Group"
                      }),
                      withDirectives(createVNode("select", {
                        id: "blood_group",
                        "onUpdate:modelValue": ($event) => unref(form).blood_group = $event,
                        class: "form-input"
                      }, [
                        createVNode("option", { value: "" }, "Select Blood Group"),
                        createVNode("option", { value: "A+" }, "A+"),
                        createVNode("option", { value: "B+" }, "B+"),
                        createVNode("option", { value: "O+" }, "O+"),
                        createVNode("option", { value: "AB+" }, "AB+"),
                        createVNode("option", { value: "A-" }, "A-"),
                        createVNode("option", { value: "B-" }, "B-"),
                        createVNode("option", { value: "O-" }, "O-"),
                        createVNode("option", { value: "AB-" }, "AB-")
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).blood_group]
                      ]),
                      createVNode(_sfc_main$3, {
                        message: unref(form).errors.blood_group
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "marital_status",
                        value: "Marital Status"
                      }),
                      withDirectives(createVNode("select", {
                        id: "marital_status",
                        "onUpdate:modelValue": ($event) => unref(form).marital_status = $event,
                        class: "form-input"
                      }, [
                        createVNode("option", { value: "" }, "Select Marital Status"),
                        createVNode("option", { value: "Single" }, "Single"),
                        createVNode("option", { value: "Married" }, "Married"),
                        createVNode("option", { value: "Widowed" }, "Widowed"),
                        createVNode("option", { value: "Separated" }, "Separated"),
                        createVNode("option", { value: "Not Specific" }, "Not Specific")
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).marital_status]
                      ]),
                      createVNode(_sfc_main$3, {
                        message: unref(form).errors.marital_status
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "phone",
                        value: "Phone"
                      }),
                      withDirectives(createVNode("input", {
                        id: "phone",
                        "onUpdate:modelValue": ($event) => unref(form).phone = $event,
                        type: "text",
                        placeholder: "Phone Number",
                        class: "form-input"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).phone]
                      ]),
                      createVNode(_sfc_main$3, {
                        message: unref(form).errors.phone
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "email",
                        value: "Email"
                      }),
                      withDirectives(createVNode("input", {
                        id: "email",
                        "onUpdate:modelValue": ($event) => unref(form).email = $event,
                        type: "email",
                        placeholder: "Email",
                        class: "form-input"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).email]
                      ]),
                      createVNode(_sfc_main$3, {
                        message: unref(form).errors.email
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "address",
                        value: "Address"
                      }),
                      withDirectives(createVNode("input", {
                        id: "address",
                        "onUpdate:modelValue": ($event) => unref(form).address = $event,
                        type: "text",
                        placeholder: "Address",
                        class: "form-input"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).address]
                      ]),
                      createVNode(_sfc_main$3, {
                        message: unref(form).errors.address
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "remarks",
                        value: "Remarks"
                      }),
                      withDirectives(createVNode("textarea", {
                        id: "remarks",
                        "onUpdate:modelValue": ($event) => unref(form).remarks = $event,
                        rows: "2",
                        class: "form-input"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).remarks]
                      ]),
                      createVNode(_sfc_main$3, {
                        message: unref(form).errors.remarks
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "any_known_allergies",
                        value: "Known Allergies"
                      }),
                      withDirectives(createVNode("textarea", {
                        id: "any_known_allergies",
                        "onUpdate:modelValue": ($event) => unref(form).any_known_allergies = $event,
                        rows: "2",
                        class: "form-input"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).any_known_allergies]
                      ]),
                      createVNode(_sfc_main$3, {
                        message: unref(form).errors.any_known_allergies
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "tpa_id",
                        value: "TPA ID"
                      }),
                      withDirectives(createVNode("select", {
                        id: "tpa_id",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).tpa_id = $event,
                        placeholder: "Select Role"
                      }, [
                        createVNode("option", { value: "" }, "--Select TPA--"),
                        (openBlock(true), createBlock(Fragment, null, renderList(__props.tpas, (data) => {
                          return openBlock(), createBlock("option", {
                            value: data.id
                          }, toDisplayString(data.name), 9, ["value"]);
                        }), 256))
                      ], 8, ["onUpdate:modelValue"]), [
                        [vModelSelect, unref(form).tpa_id]
                      ]),
                      createVNode(_sfc_main$3, {
                        message: unref(form).errors.tpa_id
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "tpa_code",
                        value: "TPA ID"
                      }),
                      withDirectives(createVNode("input", {
                        id: "any_known_allergies",
                        type: "text",
                        "onUpdate:modelValue": ($event) => unref(form).tpa_code = $event,
                        class: "form-input"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).tpa_code]
                      ]),
                      createVNode(_sfc_main$3, {
                        message: unref(form).errors.tpa_code
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "tpa_validity",
                        value: "TPA Validity"
                      }),
                      withDirectives(createVNode("input", {
                        id: "tpa_validity",
                        type: "date",
                        "onUpdate:modelValue": ($event) => unref(form).tpa_validity = $event,
                        class: "form-input"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).tpa_validity]
                      ]),
                      createVNode(_sfc_main$3, {
                        message: unref(form).errors.tpa_validity
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "tpa_nid",
                        value: "National Identification Number"
                      }),
                      withDirectives(createVNode("input", {
                        id: "tpa_nid",
                        type: "number",
                        "onUpdate:modelValue": ($event) => unref(form).tpa_nid = $event,
                        class: "form-input"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).tpa_nid]
                      ]),
                      createVNode(_sfc_main$3, {
                        message: unref(form).errors.tpa_nid
                      }, null, 8, ["message"])
                    ])
                  ]),
                  createVNode("div", { class: "mt-6 flex justify-end" }, [
                    createVNode(_sfc_main$4, {
                      type: "submit",
                      disabled: unref(form).processing,
                      class: { "opacity-25": unref(form).processing }
                    }, {
                      default: withCtx(() => [
                        createTextVNode(toDisplayString(props.id ? "Update" : "Create"), 1)
                      ]),
                      _: 1
                    }, 8, ["disabled", "class"])
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/Patient/Form.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const Form = /* @__PURE__ */ _export_sfc(_sfc_main, [["__scopeId", "data-v-a512907b"]]);
export {
  Form as default
};
