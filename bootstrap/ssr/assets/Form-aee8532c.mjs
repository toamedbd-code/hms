import { withCtx, unref, createTextVNode, toDisplayString, createVNode, openBlock, createBlock, withModifiers, withDirectives, vModelText, Fragment, renderList, vModelSelect, vModelCheckbox, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrRenderAttr, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderList } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import { useForm, router } from "@inertiajs/vue3";
import { _ as _sfc_main$3 } from "./InputError-6aeb8d97.mjs";
import { _ as _sfc_main$2 } from "./InputLabel-70ca52d1.mjs";
import { _ as _sfc_main$4 } from "./PrimaryButton-b82fb16e.mjs";
import "./AlertMessage-0f422981.mjs";
import { d as displayResponse, a as displayWarning } from "./responseMessage-d505224b.mjs";
import { _ as _export_sfc } from "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "mitt";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
import "toastr";
import "sweetalert2";
const Form_vue_vue_type_style_index_0_scoped_934d9ba9_lang = "";
const _sfc_main = {
  __name: "Form",
  __ssrInlineRender: true,
  props: ["referralperson", "id", "categories"],
  setup(__props) {
    var _a, _b, _c, _d, _e, _f, _g, _h, _i, _j, _k, _l, _m, _n, _o, _p;
    const props = __props;
    const form = useForm({
      name: ((_a = props.referralperson) == null ? void 0 : _a.name) ?? "",
      phone: ((_b = props.referralperson) == null ? void 0 : _b.phone) ?? "",
      contact_person_name: ((_c = props.referralperson) == null ? void 0 : _c.contact_person_name) ?? "",
      contact_person_phone: ((_d = props.referralperson) == null ? void 0 : _d.contact_person_phone) ?? "",
      category_id: ((_e = props.referralperson) == null ? void 0 : _e.category_id) ?? "",
      address: ((_f = props.referralperson) == null ? void 0 : _f.address) ?? "",
      standard_commission: ((_g = props.referralperson) == null ? void 0 : _g.standard_commission) ?? "",
      opd_commission: ((_h = props.referralperson) == null ? void 0 : _h.opd_commission) ?? "",
      ipd_commission: ((_i = props.referralperson) == null ? void 0 : _i.ipd_commission) ?? "",
      pharmacy_commission: ((_j = props.referralperson) == null ? void 0 : _j.pharmacy_commission) ?? "",
      pathology_commission: ((_k = props.referralperson) == null ? void 0 : _k.pathology_commission) ?? "",
      radiology_commission: ((_l = props.referralperson) == null ? void 0 : _l.radiology_commission) ?? "",
      blood_bank_commission: ((_m = props.referralperson) == null ? void 0 : _m.blood_bank_commission) ?? "",
      ambulance_commission: ((_n = props.referralperson) == null ? void 0 : _n.ambulance_commission) ?? "",
      apply_to_all: ((_o = props.referralperson) == null ? void 0 : _o.apply_to_all) ?? false,
      _method: ((_p = props.referralperson) == null ? void 0 : _p.id) ? "put" : "post"
    });
    const submit = () => {
      const routeName = props.id ? route("backend.referralperson.update", props.id) : route("backend.referralperson.store");
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
    const applyStandardCommissionToAll = () => {
      if (form.apply_to_all && form.standard_commission) {
        form.opd_commission = form.standard_commission;
        form.ipd_commission = form.standard_commission;
        form.pharmacy_commission = form.standard_commission;
        form.pathology_commission = form.standard_commission;
        form.radiology_commission = form.standard_commission;
        form.blood_bank_commission = form.standard_commission;
        form.ambulance_commission = form.standard_commission;
      }
    };
    const goToRefferalList = () => {
      router.get(route("backend.referralperson.index"));
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md" data-v-934d9ba9${_scopeId}><div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md dark:bg-gray-800 dark:text-gray-200" data-v-934d9ba9${_scopeId}><div data-v-934d9ba9${_scopeId}><h1 class="p-4 text-xl font-bold dark:text-white" data-v-934d9ba9${_scopeId}>${ssrInterpolate(_ctx.$page.props.pageTitle)}</h1></div><div class="p-2 py-2 flex items-center space-x-2" data-v-934d9ba9${_scopeId}><div class="flex items-center space-x-3" data-v-934d9ba9${_scopeId}><button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2" data-v-934d9ba9${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" data-v-934d9ba9${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" data-v-934d9ba9${_scopeId}></path></svg> Refferal List </button></div></div></div><form class="p-2" data-v-934d9ba9${_scopeId}><div class="grid grid-cols-1 md:grid-cols-4 gap-2 text-xs" data-v-934d9ba9${_scopeId}><div class="md:col-span-3 grid grid-cols-1 md:grid-cols-2 gap-2" data-v-934d9ba9${_scopeId}><div class="mb-2" data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "name",
              value: "Referrer Name *",
              class: "text-xs"
            }, null, _parent2, _scopeId));
            _push2(`<input id="name" class="form-input text-xs p-1 h-8"${ssrRenderAttr("value", unref(form).name)} type="text" placeholder="Referrer Name" required data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-1 text-xs",
              message: unref(form).errors.name
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="mb-2" data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "phone",
              value: "Referrer Phone *",
              class: "text-xs"
            }, null, _parent2, _scopeId));
            _push2(`<input id="phone" class="form-input text-xs p-1 h-8"${ssrRenderAttr("value", unref(form).phone)} type="text" placeholder="Referrer phone" required data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-1 text-xs",
              message: unref(form).errors.phone
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="mb-2" data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "contact_person_name",
              value: "Contact Person",
              class: "text-xs"
            }, null, _parent2, _scopeId));
            _push2(`<input id="contact_person_name" class="form-input text-xs p-1 h-8"${ssrRenderAttr("value", unref(form).contact_person_name)} type="text" placeholder="Contact Person" data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-1 text-xs",
              message: unref(form).errors.contact_person_name
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="mb-2" data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "contact_person_phone",
              value: "Contact Phone",
              class: "text-xs"
            }, null, _parent2, _scopeId));
            _push2(`<input id="contact_person_phone" class="form-input text-xs p-1 h-8"${ssrRenderAttr("value", unref(form).contact_person_phone)} type="text" placeholder="Contact Phone" data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-1 text-xs",
              message: unref(form).errors.contact_person_phone
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="mb-2" data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "category_id",
              value: "Category *",
              class: "text-xs"
            }, null, _parent2, _scopeId));
            _push2(`<select id="category_id" class="form-input text-xs h-9" required data-v-934d9ba9${_scopeId}><option value="" data-v-934d9ba9${ssrIncludeBooleanAttr(Array.isArray(unref(form).category_id) ? ssrLooseContain(unref(form).category_id, "") : ssrLooseEqual(unref(form).category_id, "")) ? " selected" : ""}${_scopeId}>Select Category</option><!--[-->`);
            ssrRenderList(__props.categories, (data) => {
              _push2(`<option${ssrRenderAttr("value", data.id)} data-v-934d9ba9${ssrIncludeBooleanAttr(Array.isArray(unref(form).category_id) ? ssrLooseContain(unref(form).category_id, data.id) : ssrLooseEqual(unref(form).category_id, data.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(data.name)}</option>`);
            });
            _push2(`<!--]--></select>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-1 text-xs",
              message: unref(form).errors.category_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="md:col-span-2 mb-2" data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "address",
              value: "Address",
              class: "text-xs"
            }, null, _parent2, _scopeId));
            _push2(`<textarea id="address" class="form-input text-xs p-1 h-16" placeholder="Address" data-v-934d9ba9${_scopeId}>${ssrInterpolate(unref(form).address)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-1 text-xs",
              message: unref(form).errors.address
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="md:col-span-1 space-y-1" data-v-934d9ba9${_scopeId}><div data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "standard_commission",
              value: "Std Commission (%) *",
              class: "text-xs"
            }, null, _parent2, _scopeId));
            _push2(`<input id="standard_commission" class="form-input text-xs p-1 h-8"${ssrRenderAttr("value", unref(form).standard_commission)} type="number" step="0.01" required data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-1 text-xs",
              message: unref(form).errors.standard_commission
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="flex items-center" data-v-934d9ba9${_scopeId}><input id="apply_to_all" type="checkbox" class="form-checkbox h-3 w-3"${ssrIncludeBooleanAttr(Array.isArray(unref(form).apply_to_all) ? ssrLooseContain(unref(form).apply_to_all, null) : unref(form).apply_to_all) ? " checked" : ""} data-v-934d9ba9${_scopeId}><label for="apply_to_all" class="ml-1 text-xs" data-v-934d9ba9${_scopeId}>Apply To All</label></div><div data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "opd_commission",
              value: "OPD (%)",
              class: "text-xs"
            }, null, _parent2, _scopeId));
            _push2(`<input id="opd_commission" class="form-input text-xs p-1 h-8"${ssrRenderAttr("value", unref(form).opd_commission)} type="number" step="0.01" data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-1 text-xs",
              message: unref(form).errors.opd_commission
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "ipd_commission",
              value: "IPD (%)",
              class: "text-xs"
            }, null, _parent2, _scopeId));
            _push2(`<input id="ipd_commission" class="form-input text-xs p-1 h-8"${ssrRenderAttr("value", unref(form).ipd_commission)} type="number" step="0.01" data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-1 text-xs",
              message: unref(form).errors.ipd_commission
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "pharmacy_commission",
              value: "Pharmacy (%)",
              class: "text-xs"
            }, null, _parent2, _scopeId));
            _push2(`<input id="pharmacy_commission" class="form-input text-xs p-1 h-8"${ssrRenderAttr("value", unref(form).pharmacy_commission)} type="number" step="0.01" data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-1 text-xs",
              message: unref(form).errors.pharmacy_commission
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "pathology_commission",
              value: "Pathology (%)",
              class: "text-xs"
            }, null, _parent2, _scopeId));
            _push2(`<input id="pathology_commission" class="form-input text-xs p-1 h-8"${ssrRenderAttr("value", unref(form).pathology_commission)} type="number" step="0.01" data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-1 text-xs",
              message: unref(form).errors.pathology_commission
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "radiology_commission",
              value: "Radiology (%)",
              class: "text-xs"
            }, null, _parent2, _scopeId));
            _push2(`<input id="radiology_commission" class="form-input text-xs p-1 h-8"${ssrRenderAttr("value", unref(form).radiology_commission)} type="number" step="0.01" data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-1 text-xs",
              message: unref(form).errors.radiology_commission
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "blood_bank_commission",
              value: "Blood Bank (%)",
              class: "text-xs"
            }, null, _parent2, _scopeId));
            _push2(`<input id="blood_bank_commission" class="form-input text-xs p-1 h-8"${ssrRenderAttr("value", unref(form).blood_bank_commission)} type="number" step="0.01" data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-1 text-xs",
              message: unref(form).errors.blood_bank_commission
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "ambulance_commission",
              value: "Ambulance (%)",
              class: "text-xs"
            }, null, _parent2, _scopeId));
            _push2(`<input id="ambulance_commission" class="form-input text-xs p-1 h-8"${ssrRenderAttr("value", unref(form).ambulance_commission)} type="number" step="0.01" data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-1 text-xs",
              message: unref(form).errors.ambulance_commission
            }, null, _parent2, _scopeId));
            _push2(`</div></div></div><div class="grid grid-cols-1 md:grid-cols-4 gap-2 text-xs" data-v-934d9ba9${_scopeId}></div><div class="flex justify-end mt-2" data-v-934d9ba9${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              type: "submit",
              class: ["ms-2 text-xs px-3 py-1", { "opacity-25": unref(form).processing }],
              disabled: unref(form).processing
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
              createVNode("div", { class: "w-full transition duration-1000 ease-in-out transform bg-white rounded-md" }, [
                createVNode("div", { class: "flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md dark:bg-gray-800 dark:text-gray-200" }, [
                  createVNode("div", null, [
                    createVNode("h1", { class: "p-4 text-xl font-bold dark:text-white" }, toDisplayString(_ctx.$page.props.pageTitle), 1)
                  ]),
                  createVNode("div", { class: "p-2 py-2 flex items-center space-x-2" }, [
                    createVNode("div", { class: "flex items-center space-x-3" }, [
                      createVNode("button", {
                        onClick: goToRefferalList,
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
                        createTextVNode(" Refferal List ")
                      ])
                    ])
                  ])
                ]),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"]),
                  class: "p-2"
                }, [
                  createVNode("div", { class: "grid grid-cols-1 md:grid-cols-4 gap-2 text-xs" }, [
                    createVNode("div", { class: "md:col-span-3 grid grid-cols-1 md:grid-cols-2 gap-2" }, [
                      createVNode("div", { class: "mb-2" }, [
                        createVNode(_sfc_main$2, {
                          for: "name",
                          value: "Referrer Name *",
                          class: "text-xs"
                        }),
                        withDirectives(createVNode("input", {
                          id: "name",
                          class: "form-input text-xs p-1 h-8",
                          "onUpdate:modelValue": ($event) => unref(form).name = $event,
                          type: "text",
                          placeholder: "Referrer Name",
                          required: ""
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).name]
                        ]),
                        createVNode(_sfc_main$3, {
                          class: "mt-1 text-xs",
                          message: unref(form).errors.name
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "mb-2" }, [
                        createVNode(_sfc_main$2, {
                          for: "phone",
                          value: "Referrer Phone *",
                          class: "text-xs"
                        }),
                        withDirectives(createVNode("input", {
                          id: "phone",
                          class: "form-input text-xs p-1 h-8",
                          "onUpdate:modelValue": ($event) => unref(form).phone = $event,
                          type: "text",
                          placeholder: "Referrer phone",
                          required: ""
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).phone]
                        ]),
                        createVNode(_sfc_main$3, {
                          class: "mt-1 text-xs",
                          message: unref(form).errors.phone
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "mb-2" }, [
                        createVNode(_sfc_main$2, {
                          for: "contact_person_name",
                          value: "Contact Person",
                          class: "text-xs"
                        }),
                        withDirectives(createVNode("input", {
                          id: "contact_person_name",
                          class: "form-input text-xs p-1 h-8",
                          "onUpdate:modelValue": ($event) => unref(form).contact_person_name = $event,
                          type: "text",
                          placeholder: "Contact Person"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).contact_person_name]
                        ]),
                        createVNode(_sfc_main$3, {
                          class: "mt-1 text-xs",
                          message: unref(form).errors.contact_person_name
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "mb-2" }, [
                        createVNode(_sfc_main$2, {
                          for: "contact_person_phone",
                          value: "Contact Phone",
                          class: "text-xs"
                        }),
                        withDirectives(createVNode("input", {
                          id: "contact_person_phone",
                          class: "form-input text-xs p-1 h-8",
                          "onUpdate:modelValue": ($event) => unref(form).contact_person_phone = $event,
                          type: "text",
                          placeholder: "Contact Phone"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).contact_person_phone]
                        ]),
                        createVNode(_sfc_main$3, {
                          class: "mt-1 text-xs",
                          message: unref(form).errors.contact_person_phone
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "mb-2" }, [
                        createVNode(_sfc_main$2, {
                          for: "category_id",
                          value: "Category *",
                          class: "text-xs"
                        }),
                        withDirectives(createVNode("select", {
                          id: "category_id",
                          class: "form-input text-xs h-9",
                          "onUpdate:modelValue": ($event) => unref(form).category_id = $event,
                          required: ""
                        }, [
                          createVNode("option", { value: "" }, "Select Category"),
                          (openBlock(true), createBlock(Fragment, null, renderList(__props.categories, (data) => {
                            return openBlock(), createBlock("option", {
                              key: data.id,
                              value: data.id
                            }, toDisplayString(data.name), 9, ["value"]);
                          }), 128))
                        ], 8, ["onUpdate:modelValue"]), [
                          [vModelSelect, unref(form).category_id]
                        ]),
                        createVNode(_sfc_main$3, {
                          class: "mt-1 text-xs",
                          message: unref(form).errors.category_id
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "md:col-span-2 mb-2" }, [
                        createVNode(_sfc_main$2, {
                          for: "address",
                          value: "Address",
                          class: "text-xs"
                        }),
                        withDirectives(createVNode("textarea", {
                          id: "address",
                          class: "form-input text-xs p-1 h-16",
                          "onUpdate:modelValue": ($event) => unref(form).address = $event,
                          placeholder: "Address"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).address]
                        ]),
                        createVNode(_sfc_main$3, {
                          class: "mt-1 text-xs",
                          message: unref(form).errors.address
                        }, null, 8, ["message"])
                      ])
                    ]),
                    createVNode("div", { class: "md:col-span-1 space-y-1" }, [
                      createVNode("div", null, [
                        createVNode(_sfc_main$2, {
                          for: "standard_commission",
                          value: "Std Commission (%) *",
                          class: "text-xs"
                        }),
                        withDirectives(createVNode("input", {
                          id: "standard_commission",
                          class: "form-input text-xs p-1 h-8",
                          "onUpdate:modelValue": ($event) => unref(form).standard_commission = $event,
                          type: "number",
                          step: "0.01",
                          required: ""
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).standard_commission]
                        ]),
                        createVNode(_sfc_main$3, {
                          class: "mt-1 text-xs",
                          message: unref(form).errors.standard_commission
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", { class: "flex items-center" }, [
                        withDirectives(createVNode("input", {
                          id: "apply_to_all",
                          type: "checkbox",
                          class: "form-checkbox h-3 w-3",
                          "onUpdate:modelValue": ($event) => unref(form).apply_to_all = $event,
                          onChange: applyStandardCommissionToAll
                        }, null, 40, ["onUpdate:modelValue"]), [
                          [vModelCheckbox, unref(form).apply_to_all]
                        ]),
                        createVNode("label", {
                          for: "apply_to_all",
                          class: "ml-1 text-xs"
                        }, "Apply To All")
                      ]),
                      createVNode("div", null, [
                        createVNode(_sfc_main$2, {
                          for: "opd_commission",
                          value: "OPD (%)",
                          class: "text-xs"
                        }),
                        withDirectives(createVNode("input", {
                          id: "opd_commission",
                          class: "form-input text-xs p-1 h-8",
                          "onUpdate:modelValue": ($event) => unref(form).opd_commission = $event,
                          type: "number",
                          step: "0.01"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).opd_commission]
                        ]),
                        createVNode(_sfc_main$3, {
                          class: "mt-1 text-xs",
                          message: unref(form).errors.opd_commission
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", null, [
                        createVNode(_sfc_main$2, {
                          for: "ipd_commission",
                          value: "IPD (%)",
                          class: "text-xs"
                        }),
                        withDirectives(createVNode("input", {
                          id: "ipd_commission",
                          class: "form-input text-xs p-1 h-8",
                          "onUpdate:modelValue": ($event) => unref(form).ipd_commission = $event,
                          type: "number",
                          step: "0.01"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).ipd_commission]
                        ]),
                        createVNode(_sfc_main$3, {
                          class: "mt-1 text-xs",
                          message: unref(form).errors.ipd_commission
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", null, [
                        createVNode(_sfc_main$2, {
                          for: "pharmacy_commission",
                          value: "Pharmacy (%)",
                          class: "text-xs"
                        }),
                        withDirectives(createVNode("input", {
                          id: "pharmacy_commission",
                          class: "form-input text-xs p-1 h-8",
                          "onUpdate:modelValue": ($event) => unref(form).pharmacy_commission = $event,
                          type: "number",
                          step: "0.01"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).pharmacy_commission]
                        ]),
                        createVNode(_sfc_main$3, {
                          class: "mt-1 text-xs",
                          message: unref(form).errors.pharmacy_commission
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", null, [
                        createVNode(_sfc_main$2, {
                          for: "pathology_commission",
                          value: "Pathology (%)",
                          class: "text-xs"
                        }),
                        withDirectives(createVNode("input", {
                          id: "pathology_commission",
                          class: "form-input text-xs p-1 h-8",
                          "onUpdate:modelValue": ($event) => unref(form).pathology_commission = $event,
                          type: "number",
                          step: "0.01"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).pathology_commission]
                        ]),
                        createVNode(_sfc_main$3, {
                          class: "mt-1 text-xs",
                          message: unref(form).errors.pathology_commission
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", null, [
                        createVNode(_sfc_main$2, {
                          for: "radiology_commission",
                          value: "Radiology (%)",
                          class: "text-xs"
                        }),
                        withDirectives(createVNode("input", {
                          id: "radiology_commission",
                          class: "form-input text-xs p-1 h-8",
                          "onUpdate:modelValue": ($event) => unref(form).radiology_commission = $event,
                          type: "number",
                          step: "0.01"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).radiology_commission]
                        ]),
                        createVNode(_sfc_main$3, {
                          class: "mt-1 text-xs",
                          message: unref(form).errors.radiology_commission
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", null, [
                        createVNode(_sfc_main$2, {
                          for: "blood_bank_commission",
                          value: "Blood Bank (%)",
                          class: "text-xs"
                        }),
                        withDirectives(createVNode("input", {
                          id: "blood_bank_commission",
                          class: "form-input text-xs p-1 h-8",
                          "onUpdate:modelValue": ($event) => unref(form).blood_bank_commission = $event,
                          type: "number",
                          step: "0.01"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).blood_bank_commission]
                        ]),
                        createVNode(_sfc_main$3, {
                          class: "mt-1 text-xs",
                          message: unref(form).errors.blood_bank_commission
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", null, [
                        createVNode(_sfc_main$2, {
                          for: "ambulance_commission",
                          value: "Ambulance (%)",
                          class: "text-xs"
                        }),
                        withDirectives(createVNode("input", {
                          id: "ambulance_commission",
                          class: "form-input text-xs p-1 h-8",
                          "onUpdate:modelValue": ($event) => unref(form).ambulance_commission = $event,
                          type: "number",
                          step: "0.01"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).ambulance_commission]
                        ]),
                        createVNode(_sfc_main$3, {
                          class: "mt-1 text-xs",
                          message: unref(form).errors.ambulance_commission
                        }, null, 8, ["message"])
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "grid grid-cols-1 md:grid-cols-4 gap-2 text-xs" }),
                  createVNode("div", { class: "flex justify-end mt-2" }, [
                    createVNode(_sfc_main$4, {
                      type: "submit",
                      class: ["ms-2 text-xs px-3 py-1", { "opacity-25": unref(form).processing }],
                      disabled: unref(form).processing
                    }, {
                      default: withCtx(() => [
                        createTextVNode(toDisplayString(props.id ? "Update" : "Create"), 1)
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/ReferralPerson/Form.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const Form = /* @__PURE__ */ _export_sfc(_sfc_main, [["__scopeId", "data-v-934d9ba9"]]);
export {
  Form as default
};
