import { ref, watch, nextTick, unref, withCtx, createTextVNode, toDisplayString, useSSRContext } from "vue";
import { ssrRenderTeleport, ssrRenderComponent, ssrRenderAttr, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrInterpolate } from "vue/server-renderer";
import { useForm } from "@inertiajs/vue3";
import { _ as _sfc_main$2 } from "./InputError-6aeb8d97.mjs";
import { _ as _sfc_main$1 } from "./InputLabel-70ca52d1.mjs";
import { _ as _sfc_main$3 } from "./PrimaryButton-b82fb16e.mjs";
import "./responseMessage-d505224b.mjs";
import { parse, isValid, differenceInYears, subYears, differenceInMonths, subMonths, differenceInDays, subDays, format } from "date-fns";
import { _ as _export_sfc } from "./_plugin-vue_export-helper-cc2b3d55.mjs";
const PatientModal_vue_vue_type_style_index_0_scoped_e2bb1dd4_lang = "";
const _sfc_main = {
  __name: "PatientModal",
  __ssrInlineRender: true,
  props: ["isOpen"],
  emits: ["close", "patientCreated"],
  setup(__props, { emit: __emit }) {
    const props = __props;
    const form = useForm({
      name: "",
      gender: "",
      dob: "",
      blood_group: "",
      phone: "",
      email: "",
      address: "",
      remarks: "",
      age: ""
    });
    const ageYears = ref("");
    const ageMonths = ref("");
    const ageDays = ref("");
    ref(null);
    ref(null);
    ref(null);
    watch(() => props.isOpen, (newValue) => {
      if (!newValue) {
        form.reset();
        ageYears.value = "";
        ageMonths.value = "";
        ageDays.value = "";
      } else {
        nextTick(() => {
          var _a;
          (_a = document.getElementById("name")) == null ? void 0 : _a.focus();
        });
      }
    });
    const updatingFrom = ref(null);
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
    return (_ctx, _push, _parent, _attrs) => {
      ssrRenderTeleport(_push, (_push2) => {
        if (__props.isOpen) {
          _push2(`<div class="fixed inset-0 z-50 overflow-y-auto" data-v-e2bb1dd4><div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0" data-v-e2bb1dd4><div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" data-v-e2bb1dd4></div><div class="inline-block w-full max-w-4xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-lg dark:bg-slate-900" data-v-e2bb1dd4><div class="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700" data-v-e2bb1dd4><h3 class="text-lg font-medium text-gray-900 dark:text-white" data-v-e2bb1dd4> Add New Patient </h3><button class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" data-v-e2bb1dd4><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" data-v-e2bb1dd4><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" data-v-e2bb1dd4></path></svg></button></div><div class="mt-4" data-v-e2bb1dd4><form class="space-y-4" data-v-e2bb1dd4><div class="grid grid-cols-1 md:grid-cols-3 gap-4" data-v-e2bb1dd4><div data-v-e2bb1dd4>`);
          _push2(ssrRenderComponent(_sfc_main$1, {
            for: "name",
            value: "Full Name *"
          }, null, _parent));
          _push2(`<input id="name"${ssrRenderAttr("value", unref(form).name)} type="text" required class="form-input" data-v-e2bb1dd4>`);
          _push2(ssrRenderComponent(_sfc_main$2, {
            message: unref(form).errors.name
          }, null, _parent));
          _push2(`</div><div data-v-e2bb1dd4>`);
          _push2(ssrRenderComponent(_sfc_main$1, {
            for: "gender",
            value: "Gender *"
          }, null, _parent));
          _push2(`<select id="gender" required class="form-input" data-v-e2bb1dd4><option value="" data-v-e2bb1dd4${ssrIncludeBooleanAttr(Array.isArray(unref(form).gender) ? ssrLooseContain(unref(form).gender, "") : ssrLooseEqual(unref(form).gender, "")) ? " selected" : ""}>Select Gender</option><option value="Male" data-v-e2bb1dd4${ssrIncludeBooleanAttr(Array.isArray(unref(form).gender) ? ssrLooseContain(unref(form).gender, "Male") : ssrLooseEqual(unref(form).gender, "Male")) ? " selected" : ""}>Male</option><option value="Female" data-v-e2bb1dd4${ssrIncludeBooleanAttr(Array.isArray(unref(form).gender) ? ssrLooseContain(unref(form).gender, "Female") : ssrLooseEqual(unref(form).gender, "Female")) ? " selected" : ""}>Female</option><option value="Others" data-v-e2bb1dd4${ssrIncludeBooleanAttr(Array.isArray(unref(form).gender) ? ssrLooseContain(unref(form).gender, "Others") : ssrLooseEqual(unref(form).gender, "Others")) ? " selected" : ""}>Others</option></select>`);
          _push2(ssrRenderComponent(_sfc_main$2, {
            message: unref(form).errors.gender
          }, null, _parent));
          _push2(`</div><div data-v-e2bb1dd4>`);
          _push2(ssrRenderComponent(_sfc_main$1, {
            for: "phone",
            value: "Phone *"
          }, null, _parent));
          _push2(`<input id="phone"${ssrRenderAttr("value", unref(form).phone)} type="text" required class="form-input" data-v-e2bb1dd4>`);
          _push2(ssrRenderComponent(_sfc_main$2, {
            message: unref(form).errors.phone
          }, null, _parent));
          _push2(`</div><div data-v-e2bb1dd4>`);
          _push2(ssrRenderComponent(_sfc_main$1, {
            for: "dob",
            value: "Date of Birth"
          }, null, _parent));
          _push2(`<input id="dob"${ssrRenderAttr("value", unref(form).dob)} type="date" class="form-input" data-v-e2bb1dd4>`);
          _push2(ssrRenderComponent(_sfc_main$2, {
            message: unref(form).errors.dob
          }, null, _parent));
          _push2(`</div><div class="flex items-end space-x-2" data-v-e2bb1dd4><div data-v-e2bb1dd4>`);
          _push2(ssrRenderComponent(_sfc_main$1, { value: "Age" }, null, _parent));
          _push2(`<div class="flex space-x-1" data-v-e2bb1dd4><input${ssrRenderAttr("value", ageYears.value)} type="number" min="0" max="120" class="w-16 form-input" placeholder="Y" data-v-e2bb1dd4><span class="self-center text-gray-500" data-v-e2bb1dd4>y</span><input${ssrRenderAttr("value", ageMonths.value)} type="number" min="0" max="11" class="w-16 form-input" placeholder="M" data-v-e2bb1dd4><span class="self-center text-gray-500" data-v-e2bb1dd4>m</span><input${ssrRenderAttr("value", ageDays.value)} type="number" min="0" max="30" class="w-16 form-input" placeholder="D" data-v-e2bb1dd4><span class="self-center text-gray-500" data-v-e2bb1dd4>d</span></div></div></div></div><div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700" data-v-e2bb1dd4><button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200" data-v-e2bb1dd4> Cancel </button>`);
          _push2(ssrRenderComponent(_sfc_main$3, {
            type: "submit",
            disabled: unref(form).processing
          }, {
            default: withCtx((_, _push3, _parent2, _scopeId) => {
              if (_push3) {
                _push3(`${ssrInterpolate(unref(form).processing ? "Creating..." : "Create Patient")}`);
              } else {
                return [
                  createTextVNode(toDisplayString(unref(form).processing ? "Creating..." : "Create Patient"), 1)
                ];
              }
            }),
            _: 1
          }, _parent));
          _push2(`</div></form></div></div></div></div>`);
        } else {
          _push2(`<!---->`);
        }
      }, "body", false, _parent);
    };
  }
};
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/PatientModal.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const PatientModal = /* @__PURE__ */ _export_sfc(_sfc_main, [["__scopeId", "data-v-e2bb1dd4"]]);
export {
  PatientModal as P
};
