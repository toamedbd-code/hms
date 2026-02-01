import { mergeProps, withCtx, createTextVNode, createVNode, toDisplayString, withDirectives, withKeys, vModelText, useSSRContext, ref, watch, unref, openBlock, createBlock, createCommentVNode, withModifiers, Fragment, renderList, vModelSelect, vModelDynamic } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrRenderAttr, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderList, ssrRenderDynamicModel } from "vue/server-renderer";
import { _ as _sfc_main$7 } from "./BackendLayout-d755bdca.mjs";
import { useForm, router as router$1 } from "@inertiajs/vue3";
import { _ as _sfc_main$4 } from "./InputError-6aeb8d97.mjs";
import { _ as _sfc_main$3 } from "./InputLabel-70ca52d1.mjs";
import { _ as _sfc_main$6 } from "./PrimaryButton-b82fb16e.mjs";
import "./AlertMessage-0f422981.mjs";
import { d as displayResponse, a as displayWarning } from "./responseMessage-d505224b.mjs";
import { _ as _sfc_main$2 } from "./Modal-452973b5.mjs";
import { _ as _sfc_main$5 } from "./SecondaryButton-0974b11b.mjs";
import "mitt";
import "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
import "toastr";
import "sweetalert2";
const _sfc_main$1 = {
  __name: "AddItemModal",
  __ssrInlineRender: true,
  props: {
    show: Boolean,
    title: String,
    inputLabel: String,
    inputId: String,
    form: Object,
    routeName: String,
    reloadOnly: Array
  },
  emits: ["close"],
  setup(__props, { emit: __emit }) {
    const props = __props;
    const emit = __emit;
    const closeModal = () => {
      emit("close");
    };
    const submit = () => {
      props.form.post(route(props.routeName), {
        onSuccess: (response) => {
          props.form.reset();
          closeModal();
          displayResponse(response);
          if (props.reloadOnly) {
            router.reload({ only: props.reloadOnly });
          }
        }
      });
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$2, mergeProps({
        show: __props.show,
        onClose: closeModal
      }, _attrs), {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="p-6"${_scopeId}><h2 class="text-lg font-medium text-gray-900 dark:text-gray-100"${_scopeId}> Add New ${ssrInterpolate(__props.title)}</h2><div class="mt-4"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: __props.inputId,
              value: __props.inputLabel
            }, null, _parent2, _scopeId));
            _push2(`<input${ssrRenderAttr("id", __props.inputId)}${ssrRenderAttr("value", __props.form.name)} type="text" class="block w-full mt-1 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: __props.form.errors.name
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="flex justify-end mt-6"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$5, { onClick: closeModal }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`Cancel`);
                } else {
                  return [
                    createTextVNode("Cancel")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$6, {
              class: "ml-3",
              onClick: submit,
              disabled: __props.form.processing
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(` Save `);
                } else {
                  return [
                    createTextVNode(" Save ")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</div></div>`);
          } else {
            return [
              createVNode("div", { class: "p-6" }, [
                createVNode("h2", { class: "text-lg font-medium text-gray-900 dark:text-gray-100" }, " Add New " + toDisplayString(__props.title), 1),
                createVNode("div", { class: "mt-4" }, [
                  createVNode(_sfc_main$3, {
                    for: __props.inputId,
                    value: __props.inputLabel
                  }, null, 8, ["for", "value"]),
                  withDirectives(createVNode("input", {
                    id: __props.inputId,
                    "onUpdate:modelValue": ($event) => __props.form.name = $event,
                    type: "text",
                    class: "block w-full mt-1 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50",
                    onKeyup: withKeys(submit, ["enter"])
                  }, null, 40, ["id", "onUpdate:modelValue"]), [
                    [vModelText, __props.form.name]
                  ]),
                  createVNode(_sfc_main$4, {
                    class: "mt-2",
                    message: __props.form.errors.name
                  }, null, 8, ["message"])
                ]),
                createVNode("div", { class: "flex justify-end mt-6" }, [
                  createVNode(_sfc_main$5, { onClick: closeModal }, {
                    default: withCtx(() => [
                      createTextVNode("Cancel")
                    ]),
                    _: 1
                  }),
                  createVNode(_sfc_main$6, {
                    class: "ml-3",
                    onClick: submit,
                    disabled: __props.form.processing
                  }, {
                    default: withCtx(() => [
                      createTextVNode(" Save ")
                    ]),
                    _: 1
                  }, 8, ["disabled"])
                ])
              ])
            ];
          }
        }),
        _: 1
      }, _parent));
    };
  }
};
const _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/AddItemModal.vue");
  return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
const _sfc_main = {
  __name: "Form",
  __ssrInlineRender: true,
  props: ["user", "id", "roles", "designations", "departments", "specialists", "adminDetails"],
  setup(__props) {
    var _a, _b, _c, _d, _e, _f, _g, _h, _i, _j, _k, _l, _m, _n, _o, _p, _q, _r, _s, _t, _u, _v, _w, _x, _y, _z, _A, _B, _C, _D, _E, _F, _G, _H, _I, _J, _K, _L, _M, _N, _O, _P, _Q, _R, _S, _T, _U, _V, _W;
    const props = __props;
    const showPassword = ref(false);
    const showDesignationModal = ref(false);
    const showDepartmentModal = ref(false);
    const showSpecialistModal = ref(false);
    const designationForm = useForm({
      name: ""
    });
    const departmentForm = useForm({
      name: ""
    });
    const specialistForm = useForm({
      name: ""
    });
    const form = useForm({
      // Basic Information
      staff_id: ((_a = props.adminDetails) == null ? void 0 : _a.staff_id) ?? "",
      first_name: ((_b = props.user) == null ? void 0 : _b.first_name) ?? "",
      last_name: ((_c = props.user) == null ? void 0 : _c.last_name) ?? "",
      father_name: ((_d = props.adminDetails) == null ? void 0 : _d.father_name) ?? "",
      mother_name: ((_e = props.adminDetails) == null ? void 0 : _e.mother_name) ?? "",
      gender: ((_f = props.adminDetails) == null ? void 0 : _f.gender) ?? "",
      marital_status: ((_g = props.adminDetails) == null ? void 0 : _g.marital_status) ?? "",
      blood_group: ((_h = props.adminDetails) == null ? void 0 : _h.blood_group) ?? "",
      date_of_birth: ((_i = props.adminDetails) == null ? void 0 : _i.date_of_birth) ?? "",
      date_of_joining: ((_j = props.adminDetails) == null ? void 0 : _j.date_of_joining) ?? "",
      phone: ((_k = props.user) == null ? void 0 : _k.phone) ?? "",
      emergency_contact: ((_l = props.adminDetails) == null ? void 0 : _l.emergency_contact) ?? "",
      email: ((_m = props.user) == null ? void 0 : _m.email) ?? "",
      password: ((_n = props.user) == null ? void 0 : _n.password) ?? "",
      photo: "",
      photoPreview: ((_o = props.user) == null ? void 0 : _o.photo) ?? "",
      role_id: ((_p = props.user) == null ? void 0 : _p.role_id) ?? "",
      doctor_charge: ((_q = props.user) == null ? void 0 : _q.doctor_charge) ?? "",
      designation_id: ((_r = props.adminDetails) == null ? void 0 : _r.designation_id) ?? "",
      department_id: ((_s = props.adminDetails) == null ? void 0 : _s.department_id) ?? "",
      specialist_id: ((_t = props.adminDetails) == null ? void 0 : _t.specialist_id) ?? "",
      password: "",
      current_address: ((_u = props.adminDetails) == null ? void 0 : _u.current_address) ?? "",
      permanent_address: ((_v = props.adminDetails) == null ? void 0 : _v.permanent_address) ?? "",
      pan_number: ((_w = props.adminDetails) == null ? void 0 : _w.pan_number) ?? "",
      national_id_number: ((_x = props.adminDetails) == null ? void 0 : _x.national_id_number) ?? "",
      local_id_number: ((_y = props.adminDetails) == null ? void 0 : _y.local_id_number) ?? "",
      qualification: ((_z = props.adminDetails) == null ? void 0 : _z.qualification) ?? "",
      work_experience: ((_A = props.adminDetails) == null ? void 0 : _A.work_experience) ?? "",
      specialization: ((_B = props.adminDetails) == null ? void 0 : _B.specialization) ?? "",
      note: ((_C = props.adminDetails) == null ? void 0 : _C.note) ?? "",
      // Payroll
      epf_no: ((_D = props.adminDetails) == null ? void 0 : _D.epf_no) ?? "",
      basic_salary: ((_E = props.adminDetails) == null ? void 0 : _E.basic_salary) ?? "",
      contract_type: ((_F = props.adminDetails) == null ? void 0 : _F.contract_type) ?? "",
      work_shift: ((_G = props.adminDetails) == null ? void 0 : _G.work_shift) ?? "",
      work_location: ((_H = props.adminDetails) == null ? void 0 : _H.work_location) ?? "",
      // Leave
      number_of_leaves: ((_I = props.adminDetails) == null ? void 0 : _I.number_of_leaves) ?? "",
      // Bank Details
      bank_account_title: ((_J = props.adminDetails) == null ? void 0 : _J.bank_account_title) ?? "",
      bank_account_no: ((_K = props.adminDetails) == null ? void 0 : _K.bank_account_no) ?? "",
      bank_name: ((_L = props.adminDetails) == null ? void 0 : _L.bank_name) ?? "",
      bank_branch_name: ((_M = props.adminDetails) == null ? void 0 : _M.bank_branch_name) ?? "",
      ifsc_code: ((_N = props.adminDetails) == null ? void 0 : _N.ifsc_code) ?? "",
      // Social Media
      facebook_url: ((_O = props.adminDetails) == null ? void 0 : _O.facebook_url) ?? "",
      linkedin_url: ((_P = props.adminDetails) == null ? void 0 : _P.linkedin_url) ?? "",
      twitter_url: ((_Q = props.adminDetails) == null ? void 0 : _Q.twitter_url) ?? "",
      instagram_url: ((_R = props.adminDetails) == null ? void 0 : _R.instagram_url) ?? "",
      // Documents
      resume: null,
      resumePreview: ((_S = props.adminDetails) == null ? void 0 : _S.resume_path) ?? "",
      resignation_letter: null,
      resignation_letterPreview: ((_T = props.adminDetails) == null ? void 0 : _T.resignation_letter_path) ?? "",
      joining_letter: null,
      joining_letterPreview: ((_U = props.adminDetails) == null ? void 0 : _U.joining_letter_path) ?? "",
      other_documents: null,
      other_documentsPreview: ((_V = props.adminDetails) == null ? void 0 : _V.other_documents_path) ?? "",
      _method: ((_W = props.user) == null ? void 0 : _W.id) ? "put" : "post"
    });
    watch(() => form.role_id, (newRoleId) => {
      if (newRoleId != "2") {
        form.doctor_charge = "";
        form.specialist_id = "";
      }
    });
    const handleFileChange = (event, field) => {
      const file = event.target.files[0];
      if (file) {
        form[field] = file;
        if (file.type.startsWith("image/")) {
          const reader = new FileReader();
          reader.onload = (e) => {
            form[`${field}Preview`] = e.target.result;
          };
          reader.readAsDataURL(file);
        } else {
          form[`${field}Preview`] = file.name;
        }
      }
    };
    const handlePhotoChange = (event) => {
      handleFileChange(event, "photo");
    };
    const handleResumeChange = (event) => {
      handleFileChange(event, "resume");
    };
    const handleResignationLetterChange = (event) => {
      handleFileChange(event, "resignation_letter");
    };
    const handleJoiningLetterChange = (event) => {
      handleFileChange(event, "joining_letter");
    };
    const handleOtherDocumentsChange = (event) => {
      handleFileChange(event, "other_documents");
    };
    const submit = () => {
      const routeName = props.id ? route("backend.admin.update", props.id) : route("backend.admin.store");
      form.transform((data) => {
        const transformedData = { ...data };
        delete transformedData.photoPreview;
        delete transformedData.resumePreview;
        delete transformedData.restoration_letterPreview;
        delete transformedData.joining_letterPreview;
        delete transformedData.other_documentsPreview;
        const fileFields = ["photo", "resume", "resignation_letter", "joining_letter", "other_documents"];
        fileFields.forEach((field) => {
          if (!(transformedData[field] instanceof File)) {
            delete transformedData[field];
          }
        });
        return {
          ...transformedData,
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
    const goToAdminList = () => {
      router$1.visit(route("backend.admin.index"));
    };
    const goToRoleList = () => {
      router$1.visit(route("backend.role.index"));
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$7, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="w-full transition duration-1000 ease-in-out transform bg-white border rounded-md dark:bg-slate-500"${_scopeId}><div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md shadow-md dark:bg-gray-800 dark:text-gray-200"${_scopeId}><div${_scopeId}><h1 class="p-4 text-xl font-bold dark:text-white"${_scopeId}>${ssrInterpolate(_ctx.$page.props.pageTitle)}</h1></div><div class="p-2 py-2 flex items-center space-x-2"${_scopeId}><div class="flex items-center space-x-3"${_scopeId}><button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700"${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"${_scopeId}></path></svg> Staff List </button><button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2"${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"${_scopeId}></path></svg> Role List </button></div></div></div><form class="p-1"${_scopeId}><div class="p-2 mb-6 border border-gray-200 rounded-md dark:border-gray-700"${_scopeId}><div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4"${_scopeId}><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "staff_id",
              value: "Staff ID"
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
            _push2(`<input id="staff_id"${ssrRenderAttr("value", unref(form).staff_id)} type="text" placeholder="Staff ID" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.staff_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "role_id",
              value: "Role"
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
            _push2(`<select id="role_id" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).role_id) ? ssrLooseContain(unref(form).role_id, "") : ssrLooseEqual(unref(form).role_id, "")) ? " selected" : ""}${_scopeId}>Select Role</option><!--[-->`);
            ssrRenderList(__props.roles, (role) => {
              _push2(`<option${ssrRenderAttr("value", role.id)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).role_id) ? ssrLooseContain(unref(form).role_id, role.id) : ssrLooseEqual(unref(form).role_id, role.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(role.name)}</option>`);
            });
            _push2(`<!--]--></select>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.role_id
            }, null, _parent2, _scopeId));
            _push2(`</div>`);
            if (unref(form).role_id == "2") {
              _push2(`<div class="col-span-1"${_scopeId}>`);
              _push2(ssrRenderComponent(_sfc_main$3, {
                for: "doctor_charge",
                value: "Doctor Charge "
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
              _push2(`<input id="doctor_charge"${ssrRenderAttr("value", unref(form).doctor_charge)} type="number" placeholder="Doctor Charge" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
              _push2(ssrRenderComponent(_sfc_main$4, {
                class: "mt-2",
                message: unref(form).errors.doctor_charge
              }, null, _parent2, _scopeId));
              _push2(`</div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "designation_id",
              value: "Designation"
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
            _push2(`<div class="flex items-center justify-between"${_scopeId}><select id="designation_id" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).designation_id) ? ssrLooseContain(unref(form).designation_id, "") : ssrLooseEqual(unref(form).designation_id, "")) ? " selected" : ""}${_scopeId}>Select Designation</option><!--[-->`);
            ssrRenderList(__props.designations, (designation) => {
              _push2(`<option${ssrRenderAttr("value", designation.id)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).designation_id) ? ssrLooseContain(unref(form).designation_id, designation.id) : ssrLooseEqual(unref(form).designation_id, designation.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(designation.name)}</option>`);
            });
            _push2(`<!--]--></select><button type="button" class="ml-1 inline-flex items-center px-1 py-2.5 bg-blue-500 hover:bg-blue-600 text-white rounded-md shadow-sm transition-colors duration-200 dark:bg-blue-600 dark:hover:bg-blue-700"${_scopeId}><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"${_scopeId}><path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"${_scopeId}></path></svg></button></div>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.designation_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "department_id",
              value: "Department"
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
            _push2(`<div class="flex items-center justify-between"${_scopeId}><select id="department_id" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).department_id) ? ssrLooseContain(unref(form).department_id, "") : ssrLooseEqual(unref(form).department_id, "")) ? " selected" : ""}${_scopeId}>Select Department</option><!--[-->`);
            ssrRenderList(__props.departments, (department) => {
              _push2(`<option${ssrRenderAttr("value", department.id)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).department_id) ? ssrLooseContain(unref(form).department_id, department.id) : ssrLooseEqual(unref(form).department_id, department.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(department.name)}</option>`);
            });
            _push2(`<!--]--></select><button type="button" class="ml-1 inline-flex items-center px-1 py-2.5 bg-blue-500 hover:bg-blue-600 text-white rounded-md shadow-sm transition-colors duration-200 dark:bg-blue-600 dark:hover:bg-blue-700"${_scopeId}><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"${_scopeId}><path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"${_scopeId}></path></svg></button></div>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.department_id
            }, null, _parent2, _scopeId));
            _push2(`</div>`);
            if (unref(form).role_id == "2") {
              _push2(`<div class="col-span-1"${_scopeId}>`);
              _push2(ssrRenderComponent(_sfc_main$3, {
                for: "specialist_id",
                value: "Specialist"
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
              _push2(`<div class="flex items-center justify-between"${_scopeId}><select id="specialist_id" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).specialist_id) ? ssrLooseContain(unref(form).specialist_id, "") : ssrLooseEqual(unref(form).specialist_id, "")) ? " selected" : ""}${_scopeId}>Select Specialist</option><!--[-->`);
              ssrRenderList(__props.specialists, (specialist) => {
                _push2(`<option${ssrRenderAttr("value", specialist.id)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).specialist_id) ? ssrLooseContain(unref(form).specialist_id, specialist.id) : ssrLooseEqual(unref(form).specialist_id, specialist.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(specialist.name)}</option>`);
              });
              _push2(`<!--]--></select><button type="button" class="ml-1 inline-flex items-center px-1 py-2.5 bg-blue-500 hover:bg-blue-600 text-white rounded-md shadow-sm transition-colors duration-200 dark:bg-blue-600 dark:hover:bg-blue-700"${_scopeId}><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"${_scopeId}><path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"${_scopeId}></path></svg></button></div>`);
              _push2(ssrRenderComponent(_sfc_main$4, {
                class: "mt-2",
                message: unref(form).errors.specialist_id
              }, null, _parent2, _scopeId));
              _push2(`</div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "first_name",
              value: "First Name "
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
            _push2(`<input id="first_name"${ssrRenderAttr("value", unref(form).first_name)} type="text" placeholder="First Name" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.first_name
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "last_name",
              value: "Last Name"
            }, null, _parent2, _scopeId));
            _push2(`<input id="last_name"${ssrRenderAttr("value", unref(form).last_name)} type="text" placeholder="Last Name" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.last_name
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "father_name",
              value: "Father Name"
            }, null, _parent2, _scopeId));
            _push2(`<input id="father_name"${ssrRenderAttr("value", unref(form).father_name)} type="text" placeholder="Father Name" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.father_name
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "mother_name",
              value: "Mother Name"
            }, null, _parent2, _scopeId));
            _push2(`<input id="mother_name"${ssrRenderAttr("value", unref(form).mother_name)} type="text" placeholder="Mother Name" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.mother_name
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "gender",
              value: "Gender"
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
            _push2(`<select id="gender" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).gender) ? ssrLooseContain(unref(form).gender, "") : ssrLooseEqual(unref(form).gender, "")) ? " selected" : ""}${_scopeId}>Select Gender</option><option value="Male"${ssrIncludeBooleanAttr(Array.isArray(unref(form).gender) ? ssrLooseContain(unref(form).gender, "Male") : ssrLooseEqual(unref(form).gender, "Male")) ? " selected" : ""}${_scopeId}>Male</option><option value="Female"${ssrIncludeBooleanAttr(Array.isArray(unref(form).gender) ? ssrLooseContain(unref(form).gender, "Female") : ssrLooseEqual(unref(form).gender, "Female")) ? " selected" : ""}${_scopeId}>Female</option><option value="Other"${ssrIncludeBooleanAttr(Array.isArray(unref(form).gender) ? ssrLooseContain(unref(form).gender, "Other") : ssrLooseEqual(unref(form).gender, "Other")) ? " selected" : ""}${_scopeId}>Other</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.gender
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "marital_status",
              value: "Marital Status"
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
            _push2(`<select id="marital_status" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).marital_status) ? ssrLooseContain(unref(form).marital_status, "") : ssrLooseEqual(unref(form).marital_status, "")) ? " selected" : ""}${_scopeId}>Select Status</option><option value="Single"${ssrIncludeBooleanAttr(Array.isArray(unref(form).marital_status) ? ssrLooseContain(unref(form).marital_status, "Single") : ssrLooseEqual(unref(form).marital_status, "Single")) ? " selected" : ""}${_scopeId}>Single</option><option value="Married"${ssrIncludeBooleanAttr(Array.isArray(unref(form).marital_status) ? ssrLooseContain(unref(form).marital_status, "Married") : ssrLooseEqual(unref(form).marital_status, "Married")) ? " selected" : ""}${_scopeId}>Married</option><option value="Divorced"${ssrIncludeBooleanAttr(Array.isArray(unref(form).marital_status) ? ssrLooseContain(unref(form).marital_status, "Divorced") : ssrLooseEqual(unref(form).marital_status, "Divorced")) ? " selected" : ""}${_scopeId}>Divorced</option><option value="Widowed"${ssrIncludeBooleanAttr(Array.isArray(unref(form).marital_status) ? ssrLooseContain(unref(form).marital_status, "Widowed") : ssrLooseEqual(unref(form).marital_status, "Widowed")) ? " selected" : ""}${_scopeId}>Widowed</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.marital_status
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "blood_group",
              value: "Blood Group"
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
            _push2(`<select id="blood_group" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "") : ssrLooseEqual(unref(form).blood_group, "")) ? " selected" : ""}${_scopeId}>Select Blood Group</option><option value="A+"${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "A+") : ssrLooseEqual(unref(form).blood_group, "A+")) ? " selected" : ""}${_scopeId}>A+</option><option value="A-"${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "A-") : ssrLooseEqual(unref(form).blood_group, "A-")) ? " selected" : ""}${_scopeId}>A-</option><option value="B+"${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "B+") : ssrLooseEqual(unref(form).blood_group, "B+")) ? " selected" : ""}${_scopeId}>B+</option><option value="B-"${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "B-") : ssrLooseEqual(unref(form).blood_group, "B-")) ? " selected" : ""}${_scopeId}>B-</option><option value="AB+"${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "AB+") : ssrLooseEqual(unref(form).blood_group, "AB+")) ? " selected" : ""}${_scopeId}>AB+</option><option value="AB-"${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "AB-") : ssrLooseEqual(unref(form).blood_group, "AB-")) ? " selected" : ""}${_scopeId}>AB-</option><option value="O+"${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "O+") : ssrLooseEqual(unref(form).blood_group, "O+")) ? " selected" : ""}${_scopeId}>O+</option><option value="O-"${ssrIncludeBooleanAttr(Array.isArray(unref(form).blood_group) ? ssrLooseContain(unref(form).blood_group, "O-") : ssrLooseEqual(unref(form).blood_group, "O-")) ? " selected" : ""}${_scopeId}>O-</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.blood_group
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "date_of_birth",
              value: "Date Of Birth "
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
            _push2(`<input id="date_of_birth"${ssrRenderAttr("value", unref(form).date_of_birth)} type="date" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.date_of_birth
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "date_of_joining",
              value: "Date Of Joining"
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
            _push2(`<input id="date_of_joining"${ssrRenderAttr("value", unref(form).date_of_joining)} type="date" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.date_of_joining
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "phone",
              value: "Phone"
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
            _push2(`<input id="phone"${ssrRenderAttr("value", unref(form).phone)} type="text" placeholder="Phone" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.phone
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "emergency_contact",
              value: "Emergency Contact"
            }, null, _parent2, _scopeId));
            _push2(`<input id="emergency_contact"${ssrRenderAttr("value", unref(form).emergency_contact)} type="text" placeholder="Emergency Contact" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.emergency_contact
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "email",
              value: "Email"
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
            _push2(`<input id="email"${ssrRenderAttr("value", unref(form).email)} type="email" placeholder="Email" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.email
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1 relative"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "password",
              value: "Password"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  if (!props.id) {
                    _push3(`<span class="text-red-500"${_scopeId2}>*</span>`);
                  } else {
                    _push3(`<!---->`);
                  }
                } else {
                  return [
                    !props.id ? (openBlock(), createBlock("span", {
                      key: 0,
                      class: "text-red-500"
                    }, "*")) : createCommentVNode("", true)
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`<div class="relative"${_scopeId}><input id="password"${ssrRenderDynamicModel(showPassword.value ? "text" : "password", unref(form).password, null)}${ssrRenderAttr("type", showPassword.value ? "text" : "password")} placeholder="Password" class="block w-full p-2 pr-10 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><button type="button" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"${_scopeId}>`);
            if (!showPassword.value) {
              _push2(`<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"${_scopeId}><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"${_scopeId}></path><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"${_scopeId}></path></svg>`);
            } else {
              _push2(`<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"${_scopeId}><path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd"${_scopeId}></path><path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z"${_scopeId}></path></svg>`);
            }
            _push2(`</button></div>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.password
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1 md:col-span-2"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "photo",
              value: "Photo"
            }, null, _parent2, _scopeId));
            if (unref(form).photoPreview) {
              _push2(`<div${_scopeId}><img${ssrRenderAttr("src", unref(form).photoPreview)} alt="Photo Preview" class="max-w-xs mt-2" height="60" width="60"${_scopeId}></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<input id="photo" type="file" accept="image/*" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.photo
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4"${_scopeId}><div class="col-span-1 sm:col-span-2"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "current_address",
              value: "Current Address"
            }, null, _parent2, _scopeId));
            _push2(`<textarea id="current_address" rows="2" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>${ssrInterpolate(unref(form).current_address)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.current_address
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1 sm:col-span-2"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "permanent_address",
              value: "Permanent Address"
            }, null, _parent2, _scopeId));
            _push2(`<textarea id="permanent_address" rows="2" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>${ssrInterpolate(unref(form).permanent_address)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.permanent_address
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "qualification",
              value: "Qualification"
            }, null, _parent2, _scopeId));
            _push2(`<textarea id="qualification" type="text" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>${ssrInterpolate(unref(form).qualification)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.qualification
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "work_experience",
              value: "Work Experience"
            }, null, _parent2, _scopeId));
            _push2(`<textarea id="work_experience" type="text" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>${ssrInterpolate(unref(form).work_experience)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.work_experience
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "specialization",
              value: "Specialization"
            }, null, _parent2, _scopeId));
            _push2(`<textarea id="specialization" type="text" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>${ssrInterpolate(unref(form).specialization)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.specialization
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "note",
              value: "Note"
            }, null, _parent2, _scopeId));
            _push2(`<textarea id="note" type="text" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>${ssrInterpolate(unref(form).note)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.note
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "pan_number",
              value: "Pan Number"
            }, null, _parent2, _scopeId));
            _push2(`<input id="pan_number"${ssrRenderAttr("value", unref(form).pan_number)} type="text" placeholder="Pan Number" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.pan_number
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "national_id_number",
              value: "National Identification Number"
            }, null, _parent2, _scopeId));
            _push2(`<input id="national_id_number"${ssrRenderAttr("value", unref(form).national_id_number)} type="text" placeholder="National ID" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.national_id_number
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "local_id_number",
              value: "Local Identification Number"
            }, null, _parent2, _scopeId));
            _push2(`<input id="local_id_number"${ssrRenderAttr("value", unref(form).local_id_number)} type="text" placeholder="Local ID" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.local_id_number
            }, null, _parent2, _scopeId));
            _push2(`</div></div></div><div class="p-4 mb-6 border border-gray-200 rounded-md dark:border-gray-700"${_scopeId}><h2 class="mb-4 text-lg font-semibold"${_scopeId}>Payroll</h2><div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4"${_scopeId}><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "epf_no",
              value: "EPF No"
            }, null, _parent2, _scopeId));
            _push2(`<input id="epf_no"${ssrRenderAttr("value", unref(form).epf_no)} type="text" placeholder="EPF Number" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.epf_no
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "basic_salary",
              value: "Basic Salary"
            }, null, _parent2, _scopeId));
            _push2(`<input id="basic_salary"${ssrRenderAttr("value", unref(form).basic_salary)} type="number" placeholder="Basic Salary" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.basic_salary
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "contract_type",
              value: "Contract Type"
            }, null, _parent2, _scopeId));
            _push2(`<select id="contract_type" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).contract_type) ? ssrLooseContain(unref(form).contract_type, "") : ssrLooseEqual(unref(form).contract_type, "")) ? " selected" : ""}${_scopeId}>Select Contract Type</option><option value="Permanent"${ssrIncludeBooleanAttr(Array.isArray(unref(form).contract_type) ? ssrLooseContain(unref(form).contract_type, "Permanent") : ssrLooseEqual(unref(form).contract_type, "Permanent")) ? " selected" : ""}${_scopeId}>Permanent</option><option value="Probation"${ssrIncludeBooleanAttr(Array.isArray(unref(form).contract_type) ? ssrLooseContain(unref(form).contract_type, "Probation") : ssrLooseEqual(unref(form).contract_type, "Probation")) ? " selected" : ""}${_scopeId}>Probation</option></select>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.contract_type
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "work_shift",
              value: "Work Shift"
            }, null, _parent2, _scopeId));
            _push2(`<input id="work_shift"${ssrRenderAttr("value", unref(form).leave_level)} type="text" placeholder="Work Shift" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.work_shift
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "work_location",
              value: "Work Location"
            }, null, _parent2, _scopeId));
            _push2(`<input id="work_location"${ssrRenderAttr("value", unref(form).leave_level)} type="text" placeholder="Work Location" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.work_location
            }, null, _parent2, _scopeId));
            _push2(`</div></div></div><div class="p-4 mb-6 border border-gray-200 rounded-md dark:border-gray-700"${_scopeId}><h2 class="mb-4 text-lg font-semibold"${_scopeId}>Leave</h2><div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4"${_scopeId}><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "number_of_leaves",
              value: "Number of Leaves"
            }, null, _parent2, _scopeId));
            _push2(`<input id="number_of_leaves"${ssrRenderAttr("value", unref(form).number_of_leaves)} type="number" placeholder="Number of Leaves" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.number_of_leaves
            }, null, _parent2, _scopeId));
            _push2(`</div></div></div><div class="p-4 mb-6 border border-gray-200 rounded-md dark:border-gray-700"${_scopeId}><h2 class="mb-4 text-lg font-semibold"${_scopeId}>Bank Account Details</h2><div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4"${_scopeId}><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "bank_account_title",
              value: "Account Title"
            }, null, _parent2, _scopeId));
            _push2(`<input id="bank_account_title"${ssrRenderAttr("value", unref(form).bank_account_title)} type="text" placeholder="Account Title" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.bank_account_title
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "bank_account_no",
              value: "Bank Account No."
            }, null, _parent2, _scopeId));
            _push2(`<input id="bank_account_no"${ssrRenderAttr("value", unref(form).bank_account_no)} type="text" placeholder="Account Number" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.bank_account_no
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "bank_name",
              value: "Bank Name"
            }, null, _parent2, _scopeId));
            _push2(`<input id="bank_name"${ssrRenderAttr("value", unref(form).bank_name)} type="text" placeholder="Bank Name" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.bank_name
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "ifsc_code",
              value: "IFSC Code"
            }, null, _parent2, _scopeId));
            _push2(`<input id="ifsc_code"${ssrRenderAttr("value", unref(form).ifsc_code)} type="text" placeholder="IFSC Code" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.ifsc_code
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "bank_branch_name",
              value: "Bank Branch Name"
            }, null, _parent2, _scopeId));
            _push2(`<input id="bank_branch_name"${ssrRenderAttr("value", unref(form).bank_branch_name)} type="text" placeholder="Branch Name" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.bank_branch_name
            }, null, _parent2, _scopeId));
            _push2(`</div></div></div><div class="p-4 mb-6 border border-gray-200 rounded-md dark:border-gray-700"${_scopeId}><h2 class="mb-4 text-lg font-semibold"${_scopeId}>Social Media Links</h2><div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4"${_scopeId}><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "facebook_url",
              value: "Facebook URL"
            }, null, _parent2, _scopeId));
            _push2(`<input id="facebook_url"${ssrRenderAttr("value", unref(form).facebook_url)} type="url" placeholder="Facebook URL" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.facebook_url
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "linkedin_url",
              value: "LinkedIn URL"
            }, null, _parent2, _scopeId));
            _push2(`<input id="linkedin_url"${ssrRenderAttr("value", unref(form).linkedin_url)} type="url" placeholder="LinkedIn URL" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.linkedin_url
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "twitter_url",
              value: "Twitter URL"
            }, null, _parent2, _scopeId));
            _push2(`<input id="twitter_url"${ssrRenderAttr("value", unref(form).twitter_url)} type="url" placeholder="Twitter URL" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.twitter_url
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "instagram_url",
              value: "Instagram URL"
            }, null, _parent2, _scopeId));
            _push2(`<input id="instagram_url"${ssrRenderAttr("value", unref(form).instagram_url)} type="url" placeholder="Instagram URL" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.instagram_url
            }, null, _parent2, _scopeId));
            _push2(`</div></div></div><div class="p-4 mb-6 border border-gray-200 rounded-md dark:border-gray-700"${_scopeId}><h2 class="mb-4 text-lg font-semibold"${_scopeId}>Upload Documents</h2><div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4"${_scopeId}><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "resume",
              value: "Resume"
            }, null, _parent2, _scopeId));
            _push2(`<input id="resume" type="file" accept=".pdf,.doc,.docx" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.resume
            }, null, _parent2, _scopeId));
            if (unref(form).resumePreview) {
              _push2(`<div class="mt-2 text-sm text-blue-500"${_scopeId}><a${ssrRenderAttr("href", unref(form).resumePreview)} target="_blank"${_scopeId}>View Current Resume</a></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "joining_letter",
              value: "Joining Letter"
            }, null, _parent2, _scopeId));
            _push2(`<input id="joining_letter" type="file" accept=".pdf,.doc,.docx" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.joining_letter
            }, null, _parent2, _scopeId));
            if (unref(form).joining_letterPreview) {
              _push2(`<div class="mt-2 text-sm text-blue-500"${_scopeId}><a${ssrRenderAttr("href", unref(form).joining_letterPreview)} target="_blank"${_scopeId}>View Joining Letter</a></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "resignation_letter",
              value: "Resignation Letter"
            }, null, _parent2, _scopeId));
            _push2(`<input id="resignation_letter" type="file" accept=".pdf,.doc,.docx" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.resignation_letter
            }, null, _parent2, _scopeId));
            if (unref(form).resignation_letterPreview) {
              _push2(`<div class="mt-2 text-sm text-blue-500"${_scopeId}><a${ssrRenderAttr("href", unref(form).resignation_letterPreview)} target="_blank"${_scopeId}>View Resignation Letter</a></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div><div class="col-span-1"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "other_documents",
              value: "Other Letter"
            }, null, _parent2, _scopeId));
            _push2(`<input id="other_documents" type="file" accept=".pdf,.doc,.docx" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.other_documents
            }, null, _parent2, _scopeId));
            if (unref(form).other_documentsPreview) {
              _push2(`<div class="mt-2 text-sm text-blue-500"${_scopeId}><a${ssrRenderAttr("href", unref(form).other_documentsPreview)} target="_blank"${_scopeId}>View Other Documents</a></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div></div></div><div class="flex items-center justify-end mt-4"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$6, {
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
            _push2(ssrRenderComponent(_sfc_main$1, {
              show: showDesignationModal.value,
              onClose: ($event) => showDesignationModal.value = false,
              title: "Designation",
              inputLabel: "Designation Name",
              inputId: "designation_name",
              form: unref(designationForm),
              routeName: "backend.designation.store",
              reloadOnly: ["designations"]
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$1, {
              show: showDepartmentModal.value,
              onClose: ($event) => showDepartmentModal.value = false,
              title: "Department",
              inputLabel: "Department Name",
              inputId: "department_name",
              form: unref(departmentForm),
              routeName: "backend.department.store",
              reloadOnly: ["departments"]
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$1, {
              show: showSpecialistModal.value,
              onClose: ($event) => showSpecialistModal.value = false,
              title: "Specialist",
              inputLabel: "Specialist Name",
              inputId: "specialist_name",
              form: unref(specialistForm),
              routeName: "backend.specialist.store",
              reloadOnly: ["specialists"]
            }, null, _parent2, _scopeId));
          } else {
            return [
              createVNode("div", { class: "w-full transition duration-1000 ease-in-out transform bg-white border rounded-md dark:bg-slate-500" }, [
                createVNode("div", { class: "flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md shadow-md dark:bg-gray-800 dark:text-gray-200" }, [
                  createVNode("div", null, [
                    createVNode("h1", { class: "p-4 text-xl font-bold dark:text-white" }, toDisplayString(_ctx.$page.props.pageTitle), 1)
                  ]),
                  createVNode("div", { class: "p-2 py-2 flex items-center space-x-2" }, [
                    createVNode("div", { class: "flex items-center space-x-3" }, [
                      createVNode("button", {
                        onClick: goToAdminList,
                        class: "inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700"
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
                        createTextVNode(" Staff List ")
                      ]),
                      createVNode("button", {
                        onClick: goToRoleList,
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
                        createTextVNode(" Role List ")
                      ])
                    ])
                  ])
                ]),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"]),
                  class: "p-1"
                }, [
                  createVNode("div", { class: "p-2 mb-6 border border-gray-200 rounded-md dark:border-gray-700" }, [
                    createVNode("div", { class: "grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4" }, [
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "staff_id",
                          value: "Staff ID"
                        }, {
                          default: withCtx(() => [
                            createVNode("span", { class: "text-red-500" }, "*")
                          ]),
                          _: 1
                        }),
                        withDirectives(createVNode("input", {
                          id: "staff_id",
                          "onUpdate:modelValue": ($event) => unref(form).staff_id = $event,
                          type: "text",
                          placeholder: "Staff ID",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).staff_id]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.staff_id
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "role_id",
                          value: "Role"
                        }, {
                          default: withCtx(() => [
                            createVNode("span", { class: "text-red-500" }, "*")
                          ]),
                          _: 1
                        }),
                        withDirectives(createVNode("select", {
                          id: "role_id",
                          "onUpdate:modelValue": ($event) => unref(form).role_id = $event,
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, [
                          createVNode("option", { value: "" }, "Select Role"),
                          (openBlock(true), createBlock(Fragment, null, renderList(__props.roles, (role) => {
                            return openBlock(), createBlock("option", {
                              key: role.id,
                              value: role.id
                            }, toDisplayString(role.name), 9, ["value"]);
                          }), 128))
                        ], 8, ["onUpdate:modelValue"]), [
                          [vModelSelect, unref(form).role_id]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.role_id
                        }, null, 8, ["message"])
                      ]),
                      unref(form).role_id == "2" ? (openBlock(), createBlock("div", {
                        key: 0,
                        class: "col-span-1"
                      }, [
                        createVNode(_sfc_main$3, {
                          for: "doctor_charge",
                          value: "Doctor Charge "
                        }, {
                          default: withCtx(() => [
                            createVNode("span", { class: "text-red-500" }, "*")
                          ]),
                          _: 1
                        }),
                        withDirectives(createVNode("input", {
                          id: "doctor_charge",
                          "onUpdate:modelValue": ($event) => unref(form).doctor_charge = $event,
                          type: "number",
                          placeholder: "Doctor Charge",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).doctor_charge]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.doctor_charge
                        }, null, 8, ["message"])
                      ])) : createCommentVNode("", true),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "designation_id",
                          value: "Designation"
                        }, {
                          default: withCtx(() => [
                            createVNode("span", { class: "text-red-500" }, "*")
                          ]),
                          _: 1
                        }),
                        createVNode("div", { class: "flex items-center justify-between" }, [
                          withDirectives(createVNode("select", {
                            id: "designation_id",
                            "onUpdate:modelValue": ($event) => unref(form).designation_id = $event,
                            class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                          }, [
                            createVNode("option", { value: "" }, "Select Designation"),
                            (openBlock(true), createBlock(Fragment, null, renderList(__props.designations, (designation) => {
                              return openBlock(), createBlock("option", {
                                key: designation.id,
                                value: designation.id
                              }, toDisplayString(designation.name), 9, ["value"]);
                            }), 128))
                          ], 8, ["onUpdate:modelValue"]), [
                            [vModelSelect, unref(form).designation_id]
                          ]),
                          createVNode("button", {
                            type: "button",
                            onClick: ($event) => showDesignationModal.value = true,
                            class: "ml-1 inline-flex items-center px-1 py-2.5 bg-blue-500 hover:bg-blue-600 text-white rounded-md shadow-sm transition-colors duration-200 dark:bg-blue-600 dark:hover:bg-blue-700"
                          }, [
                            (openBlock(), createBlock("svg", {
                              xmlns: "http://www.w3.org/2000/svg",
                              class: "h-4 w-4",
                              viewBox: "0 0 20 20",
                              fill: "currentColor"
                            }, [
                              createVNode("path", {
                                "fill-rule": "evenodd",
                                d: "M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z",
                                "clip-rule": "evenodd"
                              })
                            ]))
                          ], 8, ["onClick"])
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.designation_id
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "department_id",
                          value: "Department"
                        }, {
                          default: withCtx(() => [
                            createVNode("span", { class: "text-red-500" }, "*")
                          ]),
                          _: 1
                        }),
                        createVNode("div", { class: "flex items-center justify-between" }, [
                          withDirectives(createVNode("select", {
                            id: "department_id",
                            "onUpdate:modelValue": ($event) => unref(form).department_id = $event,
                            class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                          }, [
                            createVNode("option", { value: "" }, "Select Department"),
                            (openBlock(true), createBlock(Fragment, null, renderList(__props.departments, (department) => {
                              return openBlock(), createBlock("option", {
                                key: department.id,
                                value: department.id
                              }, toDisplayString(department.name), 9, ["value"]);
                            }), 128))
                          ], 8, ["onUpdate:modelValue"]), [
                            [vModelSelect, unref(form).department_id]
                          ]),
                          createVNode("button", {
                            type: "button",
                            onClick: ($event) => showDepartmentModal.value = true,
                            class: "ml-1 inline-flex items-center px-1 py-2.5 bg-blue-500 hover:bg-blue-600 text-white rounded-md shadow-sm transition-colors duration-200 dark:bg-blue-600 dark:hover:bg-blue-700"
                          }, [
                            (openBlock(), createBlock("svg", {
                              xmlns: "http://www.w3.org/2000/svg",
                              class: "h-4 w-4",
                              viewBox: "0 0 20 20",
                              fill: "currentColor"
                            }, [
                              createVNode("path", {
                                "fill-rule": "evenodd",
                                d: "M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z",
                                "clip-rule": "evenodd"
                              })
                            ]))
                          ], 8, ["onClick"])
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.department_id
                        }, null, 8, ["message"])
                      ]),
                      unref(form).role_id == "2" ? (openBlock(), createBlock("div", {
                        key: 1,
                        class: "col-span-1"
                      }, [
                        createVNode(_sfc_main$3, {
                          for: "specialist_id",
                          value: "Specialist"
                        }, {
                          default: withCtx(() => [
                            createVNode("span", { class: "text-red-500" }, "*")
                          ]),
                          _: 1
                        }),
                        createVNode("div", { class: "flex items-center justify-between" }, [
                          withDirectives(createVNode("select", {
                            id: "specialist_id",
                            "onUpdate:modelValue": ($event) => unref(form).specialist_id = $event,
                            class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                          }, [
                            createVNode("option", { value: "" }, "Select Specialist"),
                            (openBlock(true), createBlock(Fragment, null, renderList(__props.specialists, (specialist) => {
                              return openBlock(), createBlock("option", {
                                key: specialist.id,
                                value: specialist.id
                              }, toDisplayString(specialist.name), 9, ["value"]);
                            }), 128))
                          ], 8, ["onUpdate:modelValue"]), [
                            [vModelSelect, unref(form).specialist_id]
                          ]),
                          createVNode("button", {
                            type: "button",
                            onClick: ($event) => showSpecialistModal.value = true,
                            class: "ml-1 inline-flex items-center px-1 py-2.5 bg-blue-500 hover:bg-blue-600 text-white rounded-md shadow-sm transition-colors duration-200 dark:bg-blue-600 dark:hover:bg-blue-700"
                          }, [
                            (openBlock(), createBlock("svg", {
                              xmlns: "http://www.w3.org/2000/svg",
                              class: "h-4 w-4",
                              viewBox: "0 0 20 20",
                              fill: "currentColor"
                            }, [
                              createVNode("path", {
                                "fill-rule": "evenodd",
                                d: "M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z",
                                "clip-rule": "evenodd"
                              })
                            ]))
                          ], 8, ["onClick"])
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.specialist_id
                        }, null, 8, ["message"])
                      ])) : createCommentVNode("", true),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "first_name",
                          value: "First Name "
                        }, {
                          default: withCtx(() => [
                            createVNode("span", { class: "text-red-500" }, "*")
                          ]),
                          _: 1
                        }),
                        withDirectives(createVNode("input", {
                          id: "first_name",
                          "onUpdate:modelValue": ($event) => unref(form).first_name = $event,
                          type: "text",
                          placeholder: "First Name",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).first_name]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.first_name
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "last_name",
                          value: "Last Name"
                        }),
                        withDirectives(createVNode("input", {
                          id: "last_name",
                          "onUpdate:modelValue": ($event) => unref(form).last_name = $event,
                          type: "text",
                          placeholder: "Last Name",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).last_name]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.last_name
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "father_name",
                          value: "Father Name"
                        }),
                        withDirectives(createVNode("input", {
                          id: "father_name",
                          "onUpdate:modelValue": ($event) => unref(form).father_name = $event,
                          type: "text",
                          placeholder: "Father Name",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).father_name]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.father_name
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "mother_name",
                          value: "Mother Name"
                        }),
                        withDirectives(createVNode("input", {
                          id: "mother_name",
                          "onUpdate:modelValue": ($event) => unref(form).mother_name = $event,
                          type: "text",
                          placeholder: "Mother Name",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).mother_name]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.mother_name
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "gender",
                          value: "Gender"
                        }, {
                          default: withCtx(() => [
                            createVNode("span", { class: "text-red-500" }, "*")
                          ]),
                          _: 1
                        }),
                        withDirectives(createVNode("select", {
                          id: "gender",
                          "onUpdate:modelValue": ($event) => unref(form).gender = $event,
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, [
                          createVNode("option", { value: "" }, "Select Gender"),
                          createVNode("option", { value: "Male" }, "Male"),
                          createVNode("option", { value: "Female" }, "Female"),
                          createVNode("option", { value: "Other" }, "Other")
                        ], 8, ["onUpdate:modelValue"]), [
                          [vModelSelect, unref(form).gender]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.gender
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "marital_status",
                          value: "Marital Status"
                        }, {
                          default: withCtx(() => [
                            createVNode("span", { class: "text-red-500" }, "*")
                          ]),
                          _: 1
                        }),
                        withDirectives(createVNode("select", {
                          id: "marital_status",
                          "onUpdate:modelValue": ($event) => unref(form).marital_status = $event,
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, [
                          createVNode("option", { value: "" }, "Select Status"),
                          createVNode("option", { value: "Single" }, "Single"),
                          createVNode("option", { value: "Married" }, "Married"),
                          createVNode("option", { value: "Divorced" }, "Divorced"),
                          createVNode("option", { value: "Widowed" }, "Widowed")
                        ], 8, ["onUpdate:modelValue"]), [
                          [vModelSelect, unref(form).marital_status]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.marital_status
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "blood_group",
                          value: "Blood Group"
                        }, {
                          default: withCtx(() => [
                            createVNode("span", { class: "text-red-500" }, "*")
                          ]),
                          _: 1
                        }),
                        withDirectives(createVNode("select", {
                          id: "blood_group",
                          "onUpdate:modelValue": ($event) => unref(form).blood_group = $event,
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, [
                          createVNode("option", { value: "" }, "Select Blood Group"),
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
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.blood_group
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "date_of_birth",
                          value: "Date Of Birth "
                        }, {
                          default: withCtx(() => [
                            createVNode("span", { class: "text-red-500" }, "*")
                          ]),
                          _: 1
                        }),
                        withDirectives(createVNode("input", {
                          id: "date_of_birth",
                          "onUpdate:modelValue": ($event) => unref(form).date_of_birth = $event,
                          type: "date",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).date_of_birth]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.date_of_birth
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "date_of_joining",
                          value: "Date Of Joining"
                        }, {
                          default: withCtx(() => [
                            createVNode("span", { class: "text-red-500" }, "*")
                          ]),
                          _: 1
                        }),
                        withDirectives(createVNode("input", {
                          id: "date_of_joining",
                          "onUpdate:modelValue": ($event) => unref(form).date_of_joining = $event,
                          type: "date",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).date_of_joining]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.date_of_joining
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "phone",
                          value: "Phone"
                        }, {
                          default: withCtx(() => [
                            createVNode("span", { class: "text-red-500" }, "*")
                          ]),
                          _: 1
                        }),
                        withDirectives(createVNode("input", {
                          id: "phone",
                          "onUpdate:modelValue": ($event) => unref(form).phone = $event,
                          type: "text",
                          placeholder: "Phone",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).phone]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.phone
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "emergency_contact",
                          value: "Emergency Contact"
                        }),
                        withDirectives(createVNode("input", {
                          id: "emergency_contact",
                          "onUpdate:modelValue": ($event) => unref(form).emergency_contact = $event,
                          type: "text",
                          placeholder: "Emergency Contact",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).emergency_contact]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.emergency_contact
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "email",
                          value: "Email"
                        }, {
                          default: withCtx(() => [
                            createVNode("span", { class: "text-red-500" }, "*")
                          ]),
                          _: 1
                        }),
                        withDirectives(createVNode("input", {
                          id: "email",
                          "onUpdate:modelValue": ($event) => unref(form).email = $event,
                          type: "email",
                          placeholder: "Email",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).email]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.email
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1 relative" }, [
                        createVNode(_sfc_main$3, {
                          for: "password",
                          value: "Password"
                        }, {
                          default: withCtx(() => [
                            !props.id ? (openBlock(), createBlock("span", {
                              key: 0,
                              class: "text-red-500"
                            }, "*")) : createCommentVNode("", true)
                          ]),
                          _: 1
                        }),
                        createVNode("div", { class: "relative" }, [
                          withDirectives(createVNode("input", {
                            id: "password",
                            "onUpdate:modelValue": ($event) => unref(form).password = $event,
                            type: showPassword.value ? "text" : "password",
                            placeholder: "Password",
                            class: "block w-full p-2 pr-10 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                          }, null, 8, ["onUpdate:modelValue", "type"]), [
                            [vModelDynamic, unref(form).password]
                          ]),
                          createVNode("button", {
                            type: "button",
                            onClick: ($event) => showPassword.value = !showPassword.value,
                            class: "absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                          }, [
                            !showPassword.value ? (openBlock(), createBlock("svg", {
                              key: 0,
                              xmlns: "http://www.w3.org/2000/svg",
                              class: "h-5 w-5",
                              viewBox: "0 0 20 20",
                              fill: "currentColor"
                            }, [
                              createVNode("path", { d: "M10 12a2 2 0 100-4 2 2 0 000 4z" }),
                              createVNode("path", {
                                "fill-rule": "evenodd",
                                d: "M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z",
                                "clip-rule": "evenodd"
                              })
                            ])) : (openBlock(), createBlock("svg", {
                              key: 1,
                              xmlns: "http://www.w3.org/2000/svg",
                              class: "h-5 w-5",
                              viewBox: "0 0 20 20",
                              fill: "currentColor"
                            }, [
                              createVNode("path", {
                                "fill-rule": "evenodd",
                                d: "M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z",
                                "clip-rule": "evenodd"
                              }),
                              createVNode("path", { d: "M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" })
                            ]))
                          ], 8, ["onClick"])
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.password
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1 md:col-span-2" }, [
                        createVNode(_sfc_main$3, {
                          for: "photo",
                          value: "Photo"
                        }),
                        unref(form).photoPreview ? (openBlock(), createBlock("div", { key: 0 }, [
                          createVNode("img", {
                            src: unref(form).photoPreview,
                            alt: "Photo Preview",
                            class: "max-w-xs mt-2",
                            height: "60",
                            width: "60"
                          }, null, 8, ["src"])
                        ])) : createCommentVNode("", true),
                        createVNode("input", {
                          id: "photo",
                          type: "file",
                          accept: "image/*",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                          onChange: handlePhotoChange
                        }, null, 32),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.photo
                        }, null, 8, ["message"])
                      ])
                    ]),
                    createVNode("div", { class: "grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4" }, [
                      createVNode("div", { class: "col-span-1 sm:col-span-2" }, [
                        createVNode(_sfc_main$3, {
                          for: "current_address",
                          value: "Current Address"
                        }),
                        withDirectives(createVNode("textarea", {
                          id: "current_address",
                          "onUpdate:modelValue": ($event) => unref(form).current_address = $event,
                          rows: "2",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).current_address]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.current_address
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1 sm:col-span-2" }, [
                        createVNode(_sfc_main$3, {
                          for: "permanent_address",
                          value: "Permanent Address"
                        }),
                        withDirectives(createVNode("textarea", {
                          id: "permanent_address",
                          "onUpdate:modelValue": ($event) => unref(form).permanent_address = $event,
                          rows: "2",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).permanent_address]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.permanent_address
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "qualification",
                          value: "Qualification"
                        }),
                        withDirectives(createVNode("textarea", {
                          id: "qualification",
                          "onUpdate:modelValue": ($event) => unref(form).qualification = $event,
                          type: "text",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).qualification]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.qualification
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "work_experience",
                          value: "Work Experience"
                        }),
                        withDirectives(createVNode("textarea", {
                          id: "work_experience",
                          "onUpdate:modelValue": ($event) => unref(form).work_experience = $event,
                          type: "text",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).work_experience]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.work_experience
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "specialization",
                          value: "Specialization"
                        }),
                        withDirectives(createVNode("textarea", {
                          id: "specialization",
                          "onUpdate:modelValue": ($event) => unref(form).specialization = $event,
                          type: "text",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).specialization]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.specialization
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "note",
                          value: "Note"
                        }),
                        withDirectives(createVNode("textarea", {
                          id: "note",
                          "onUpdate:modelValue": ($event) => unref(form).note = $event,
                          type: "text",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).note]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.note
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "pan_number",
                          value: "Pan Number"
                        }),
                        withDirectives(createVNode("input", {
                          id: "pan_number",
                          "onUpdate:modelValue": ($event) => unref(form).pan_number = $event,
                          type: "text",
                          placeholder: "Pan Number",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).pan_number]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.pan_number
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "national_id_number",
                          value: "National Identification Number"
                        }),
                        withDirectives(createVNode("input", {
                          id: "national_id_number",
                          "onUpdate:modelValue": ($event) => unref(form).national_id_number = $event,
                          type: "text",
                          placeholder: "National ID",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).national_id_number]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.national_id_number
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "local_id_number",
                          value: "Local Identification Number"
                        }),
                        withDirectives(createVNode("input", {
                          id: "local_id_number",
                          "onUpdate:modelValue": ($event) => unref(form).local_id_number = $event,
                          type: "text",
                          placeholder: "Local ID",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).local_id_number]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.local_id_number
                        }, null, 8, ["message"])
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "p-4 mb-6 border border-gray-200 rounded-md dark:border-gray-700" }, [
                    createVNode("h2", { class: "mb-4 text-lg font-semibold" }, "Payroll"),
                    createVNode("div", { class: "grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4" }, [
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "epf_no",
                          value: "EPF No"
                        }),
                        withDirectives(createVNode("input", {
                          id: "epf_no",
                          "onUpdate:modelValue": ($event) => unref(form).epf_no = $event,
                          type: "text",
                          placeholder: "EPF Number",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).epf_no]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.epf_no
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "basic_salary",
                          value: "Basic Salary"
                        }),
                        withDirectives(createVNode("input", {
                          id: "basic_salary",
                          "onUpdate:modelValue": ($event) => unref(form).basic_salary = $event,
                          type: "number",
                          placeholder: "Basic Salary",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).basic_salary]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.basic_salary
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "contract_type",
                          value: "Contract Type"
                        }),
                        withDirectives(createVNode("select", {
                          id: "contract_type",
                          "onUpdate:modelValue": ($event) => unref(form).contract_type = $event,
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, [
                          createVNode("option", { value: "" }, "Select Contract Type"),
                          createVNode("option", { value: "Permanent" }, "Permanent"),
                          createVNode("option", { value: "Probation" }, "Probation")
                        ], 8, ["onUpdate:modelValue"]), [
                          [vModelSelect, unref(form).contract_type]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.contract_type
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "work_shift",
                          value: "Work Shift"
                        }),
                        withDirectives(createVNode("input", {
                          id: "work_shift",
                          "onUpdate:modelValue": ($event) => unref(form).leave_level = $event,
                          type: "text",
                          placeholder: "Work Shift",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).leave_level]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.work_shift
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "work_location",
                          value: "Work Location"
                        }),
                        withDirectives(createVNode("input", {
                          id: "work_location",
                          "onUpdate:modelValue": ($event) => unref(form).leave_level = $event,
                          type: "text",
                          placeholder: "Work Location",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).leave_level]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.work_location
                        }, null, 8, ["message"])
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "p-4 mb-6 border border-gray-200 rounded-md dark:border-gray-700" }, [
                    createVNode("h2", { class: "mb-4 text-lg font-semibold" }, "Leave"),
                    createVNode("div", { class: "grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4" }, [
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "number_of_leaves",
                          value: "Number of Leaves"
                        }),
                        withDirectives(createVNode("input", {
                          id: "number_of_leaves",
                          "onUpdate:modelValue": ($event) => unref(form).number_of_leaves = $event,
                          type: "number",
                          placeholder: "Number of Leaves",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).number_of_leaves]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.number_of_leaves
                        }, null, 8, ["message"])
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "p-4 mb-6 border border-gray-200 rounded-md dark:border-gray-700" }, [
                    createVNode("h2", { class: "mb-4 text-lg font-semibold" }, "Bank Account Details"),
                    createVNode("div", { class: "grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4" }, [
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "bank_account_title",
                          value: "Account Title"
                        }),
                        withDirectives(createVNode("input", {
                          id: "bank_account_title",
                          "onUpdate:modelValue": ($event) => unref(form).bank_account_title = $event,
                          type: "text",
                          placeholder: "Account Title",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).bank_account_title]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.bank_account_title
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "bank_account_no",
                          value: "Bank Account No."
                        }),
                        withDirectives(createVNode("input", {
                          id: "bank_account_no",
                          "onUpdate:modelValue": ($event) => unref(form).bank_account_no = $event,
                          type: "text",
                          placeholder: "Account Number",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).bank_account_no]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.bank_account_no
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "bank_name",
                          value: "Bank Name"
                        }),
                        withDirectives(createVNode("input", {
                          id: "bank_name",
                          "onUpdate:modelValue": ($event) => unref(form).bank_name = $event,
                          type: "text",
                          placeholder: "Bank Name",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).bank_name]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.bank_name
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "ifsc_code",
                          value: "IFSC Code"
                        }),
                        withDirectives(createVNode("input", {
                          id: "ifsc_code",
                          "onUpdate:modelValue": ($event) => unref(form).ifsc_code = $event,
                          type: "text",
                          placeholder: "IFSC Code",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).ifsc_code]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.ifsc_code
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "bank_branch_name",
                          value: "Bank Branch Name"
                        }),
                        withDirectives(createVNode("input", {
                          id: "bank_branch_name",
                          "onUpdate:modelValue": ($event) => unref(form).bank_branch_name = $event,
                          type: "text",
                          placeholder: "Branch Name",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).bank_branch_name]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.bank_branch_name
                        }, null, 8, ["message"])
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "p-4 mb-6 border border-gray-200 rounded-md dark:border-gray-700" }, [
                    createVNode("h2", { class: "mb-4 text-lg font-semibold" }, "Social Media Links"),
                    createVNode("div", { class: "grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4" }, [
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "facebook_url",
                          value: "Facebook URL"
                        }),
                        withDirectives(createVNode("input", {
                          id: "facebook_url",
                          "onUpdate:modelValue": ($event) => unref(form).facebook_url = $event,
                          type: "url",
                          placeholder: "Facebook URL",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).facebook_url]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.facebook_url
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "linkedin_url",
                          value: "LinkedIn URL"
                        }),
                        withDirectives(createVNode("input", {
                          id: "linkedin_url",
                          "onUpdate:modelValue": ($event) => unref(form).linkedin_url = $event,
                          type: "url",
                          placeholder: "LinkedIn URL",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).linkedin_url]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.linkedin_url
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "twitter_url",
                          value: "Twitter URL"
                        }),
                        withDirectives(createVNode("input", {
                          id: "twitter_url",
                          "onUpdate:modelValue": ($event) => unref(form).twitter_url = $event,
                          type: "url",
                          placeholder: "Twitter URL",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).twitter_url]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.twitter_url
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "instagram_url",
                          value: "Instagram URL"
                        }),
                        withDirectives(createVNode("input", {
                          id: "instagram_url",
                          "onUpdate:modelValue": ($event) => unref(form).instagram_url = $event,
                          type: "url",
                          placeholder: "Instagram URL",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).instagram_url]
                        ]),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.instagram_url
                        }, null, 8, ["message"])
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "p-4 mb-6 border border-gray-200 rounded-md dark:border-gray-700" }, [
                    createVNode("h2", { class: "mb-4 text-lg font-semibold" }, "Upload Documents"),
                    createVNode("div", { class: "grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4" }, [
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "resume",
                          value: "Resume"
                        }),
                        createVNode("input", {
                          id: "resume",
                          type: "file",
                          accept: ".pdf,.doc,.docx",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                          onChange: handleResumeChange
                        }, null, 32),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.resume
                        }, null, 8, ["message"]),
                        unref(form).resumePreview ? (openBlock(), createBlock("div", {
                          key: 0,
                          class: "mt-2 text-sm text-blue-500"
                        }, [
                          createVNode("a", {
                            href: unref(form).resumePreview,
                            target: "_blank"
                          }, "View Current Resume", 8, ["href"])
                        ])) : createCommentVNode("", true)
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "joining_letter",
                          value: "Joining Letter"
                        }),
                        createVNode("input", {
                          id: "joining_letter",
                          type: "file",
                          accept: ".pdf,.doc,.docx",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                          onChange: handleJoiningLetterChange
                        }, null, 32),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.joining_letter
                        }, null, 8, ["message"]),
                        unref(form).joining_letterPreview ? (openBlock(), createBlock("div", {
                          key: 0,
                          class: "mt-2 text-sm text-blue-500"
                        }, [
                          createVNode("a", {
                            href: unref(form).joining_letterPreview,
                            target: "_blank"
                          }, "View Joining Letter", 8, ["href"])
                        ])) : createCommentVNode("", true)
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "resignation_letter",
                          value: "Resignation Letter"
                        }),
                        createVNode("input", {
                          id: "resignation_letter",
                          type: "file",
                          accept: ".pdf,.doc,.docx",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                          onChange: handleResignationLetterChange
                        }, null, 32),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.resignation_letter
                        }, null, 8, ["message"]),
                        unref(form).resignation_letterPreview ? (openBlock(), createBlock("div", {
                          key: 0,
                          class: "mt-2 text-sm text-blue-500"
                        }, [
                          createVNode("a", {
                            href: unref(form).resignation_letterPreview,
                            target: "_blank"
                          }, "View Resignation Letter", 8, ["href"])
                        ])) : createCommentVNode("", true)
                      ]),
                      createVNode("div", { class: "col-span-1" }, [
                        createVNode(_sfc_main$3, {
                          for: "other_documents",
                          value: "Other Letter"
                        }),
                        createVNode("input", {
                          id: "other_documents",
                          type: "file",
                          accept: ".pdf,.doc,.docx",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                          onChange: handleOtherDocumentsChange
                        }, null, 32),
                        createVNode(_sfc_main$4, {
                          class: "mt-2",
                          message: unref(form).errors.other_documents
                        }, null, 8, ["message"]),
                        unref(form).other_documentsPreview ? (openBlock(), createBlock("div", {
                          key: 0,
                          class: "mt-2 text-sm text-blue-500"
                        }, [
                          createVNode("a", {
                            href: unref(form).other_documentsPreview,
                            target: "_blank"
                          }, "View Other Documents", 8, ["href"])
                        ])) : createCommentVNode("", true)
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "flex items-center justify-end mt-4" }, [
                    createVNode(_sfc_main$6, {
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
              createVNode(_sfc_main$1, {
                show: showDesignationModal.value,
                onClose: ($event) => showDesignationModal.value = false,
                title: "Designation",
                inputLabel: "Designation Name",
                inputId: "designation_name",
                form: unref(designationForm),
                routeName: "backend.designation.store",
                reloadOnly: ["designations"]
              }, null, 8, ["show", "onClose", "form"]),
              createVNode(_sfc_main$1, {
                show: showDepartmentModal.value,
                onClose: ($event) => showDepartmentModal.value = false,
                title: "Department",
                inputLabel: "Department Name",
                inputId: "department_name",
                form: unref(departmentForm),
                routeName: "backend.department.store",
                reloadOnly: ["departments"]
              }, null, 8, ["show", "onClose", "form"]),
              createVNode(_sfc_main$1, {
                show: showSpecialistModal.value,
                onClose: ($event) => showSpecialistModal.value = false,
                title: "Specialist",
                inputLabel: "Specialist Name",
                inputId: "specialist_name",
                form: unref(specialistForm),
                routeName: "backend.specialist.store",
                reloadOnly: ["specialists"]
              }, null, 8, ["show", "onClose", "form"])
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/Admin/Form.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
