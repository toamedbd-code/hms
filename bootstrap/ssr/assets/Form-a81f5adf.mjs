import { watch, ref, withCtx, unref, openBlock, createBlock, toDisplayString, createCommentVNode, createVNode, withDirectives, Fragment, renderList, vModelSelect, withModifiers, vModelText, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderList, ssrRenderAttr } from "vue/server-renderer";
import { usePage, useForm, router } from "@inertiajs/vue3";
import axios from "axios";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import "mitt";
import "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
const _sfc_main = {
  __name: "Form",
  __ssrInlineRender: true,
  props: {
    pageTitle: String,
    medicine: Object,
    suppliers: Array,
    medicineCategories: Array,
    isEdit: Boolean
  },
  setup(__props) {
    const props = __props;
    const flash = usePage().props.flash || {};
    const form = useForm({
      supplier_id: "",
      medicine_category_id: "",
      medicine_name: "",
      medicine_unit_purchase_price: "",
      medicine_unit_selling_price: "",
      medicine_quantity: "",
      status: "Active"
    });
    watch(
      () => props.medicine,
      (medicine) => {
        if (medicine) {
          form.supplier_id = medicine.supplier_id;
          form.medicine_category_id = medicine.medicine_category_id;
          form.medicine_name = medicine.medicine_name;
          form.medicine_unit_purchase_price = medicine.medicine_unit_purchase_price;
          form.medicine_unit_selling_price = medicine.medicine_unit_selling_price;
          form.medicine_quantity = medicine.medicine_quantity;
          form.status = medicine.status;
        }
      },
      { immediate: true }
    );
    const submit = () => {
      if (props.isEdit) {
        form.put(
          route("backend.medicineinventory.update", props.medicine.id),
          {
            preserveScroll: true,
            onSuccess: () => {
            }
          }
        );
      } else {
        form.post(
          route("backend.medicineinventory.store"),
          {
            preserveScroll: true
          }
        );
      }
    };
    const csv = ref({
      supplier_id: "",
      medicine_category_id: "",
      file: null
    });
    const csvLoading = ref(false);
    const handleCsvFile = (e) => {
      csv.value.file = e.target.files[0];
    };
    const uploadInventoryCsv = async () => {
      var _a, _b;
      if (!csv.value.supplier_id || !csv.value.medicine_category_id || !csv.value.file) {
        alert("Supplier, Category & CSV file required");
        return;
      }
      const formData = new FormData();
      formData.append("supplier_id", csv.value.supplier_id);
      formData.append("medicine_category_id", csv.value.medicine_category_id);
      formData.append("csv_file", csv.value.file);
      csvLoading.value = true;
      try {
        const res = await axios.post(
          route("backend.medicineinventory.import.csv"),
          formData
        );
        if (res.data.status) {
          alert(res.data.message || "CSV Imported Successfully");
          router.visit(route("backend.medicineinventory.index"));
        } else {
          alert(res.data.message || "CSV upload failed");
        }
      } catch (e) {
        alert(((_b = (_a = e.response) == null ? void 0 : _a.data) == null ? void 0 : _b.message) || "CSV upload failed");
      } finally {
        csvLoading.value = false;
      }
    };
    const goToList = () => {
      router.visit(route("backend.medicineinventory.index"));
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            if (unref(flash).successMessage) {
              _push2(`<div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded"${_scopeId}>${ssrInterpolate(unref(flash).successMessage)}</div>`);
            } else {
              _push2(`<!---->`);
            }
            if (unref(flash).errorMessage) {
              _push2(`<div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded"${_scopeId}>${ssrInterpolate(unref(flash).errorMessage)}</div>`);
            } else {
              _push2(`<!---->`);
            }
            if (unref(flash).infoMessage) {
              _push2(`<div class="mb-4 p-4 bg-blue-100 border border-blue-400 text-blue-700 rounded"${_scopeId}>${ssrInterpolate(unref(flash).infoMessage)}</div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<div class="bg-white rounded-md p-4"${_scopeId}><div class="flex justify-between items-center mb-4"${_scopeId}><h1 class="text-xl font-bold"${_scopeId}>${ssrInterpolate(__props.pageTitle)}</h1><button class="px-4 py-2 bg-gray-700 text-white rounded"${_scopeId}> Medicine Inventory List </button></div>`);
            if (!__props.isEdit) {
              _push2(`<div class="mb-6 p-4 border rounded bg-gray-50"${_scopeId}><h3 class="font-semibold mb-3"${_scopeId}>Bulk Medicine Upload (CSV)</h3><div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3"${_scopeId}><select class="border rounded p-2"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(csv.value.supplier_id) ? ssrLooseContain(csv.value.supplier_id, "") : ssrLooseEqual(csv.value.supplier_id, "")) ? " selected" : ""}${_scopeId}>Select Supplier</option><!--[-->`);
              ssrRenderList(__props.suppliers, (s) => {
                _push2(`<option${ssrRenderAttr("value", s.id)}${ssrIncludeBooleanAttr(Array.isArray(csv.value.supplier_id) ? ssrLooseContain(csv.value.supplier_id, s.id) : ssrLooseEqual(csv.value.supplier_id, s.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(s.name)}</option>`);
              });
              _push2(`<!--]--></select><select class="border rounded p-2"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(csv.value.medicine_category_id) ? ssrLooseContain(csv.value.medicine_category_id, "") : ssrLooseEqual(csv.value.medicine_category_id, "")) ? " selected" : ""}${_scopeId}>Select Category</option><!--[-->`);
              ssrRenderList(__props.medicineCategories, (c) => {
                _push2(`<option${ssrRenderAttr("value", c.id)}${ssrIncludeBooleanAttr(Array.isArray(csv.value.medicine_category_id) ? ssrLooseContain(csv.value.medicine_category_id, c.id) : ssrLooseEqual(csv.value.medicine_category_id, c.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(c.name)}</option>`);
              });
              _push2(`<!--]--></select><input type="file" accept=".csv"${_scopeId}></div><div class="flex gap-3"${_scopeId}><button class="px-4 py-2 bg-green-600 text-white rounded"${ssrIncludeBooleanAttr(csvLoading.value) ? " disabled" : ""}${_scopeId}>${ssrInterpolate(csvLoading.value ? "Uploading..." : "Upload Inventory CSV")}</button><a href="/sample/medicine_inventory_sample.csv" target="_blank" class="px-4 py-2 bg-gray-300 rounded"${_scopeId}> Download Sample CSV </a></div></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<form class="grid grid-cols-1 md:grid-cols-3 gap-4"${_scopeId}><select class="border p-2 rounded" required${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).supplier_id) ? ssrLooseContain(unref(form).supplier_id, "") : ssrLooseEqual(unref(form).supplier_id, "")) ? " selected" : ""}${_scopeId}>Select Supplier</option><!--[-->`);
            ssrRenderList(__props.suppliers, (s) => {
              _push2(`<option${ssrRenderAttr("value", s.id)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).supplier_id) ? ssrLooseContain(unref(form).supplier_id, s.id) : ssrLooseEqual(unref(form).supplier_id, s.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(s.name)}</option>`);
            });
            _push2(`<!--]--></select><select class="border p-2 rounded" required${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(unref(form).medicine_category_id) ? ssrLooseContain(unref(form).medicine_category_id, "") : ssrLooseEqual(unref(form).medicine_category_id, "")) ? " selected" : ""}${_scopeId}>Select Category</option><!--[-->`);
            ssrRenderList(__props.medicineCategories, (c) => {
              _push2(`<option${ssrRenderAttr("value", c.id)}${ssrIncludeBooleanAttr(Array.isArray(unref(form).medicine_category_id) ? ssrLooseContain(unref(form).medicine_category_id, c.id) : ssrLooseEqual(unref(form).medicine_category_id, c.id)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(c.name)}</option>`);
            });
            _push2(`<!--]--></select><input${ssrRenderAttr("value", unref(form).medicine_name)} type="text" placeholder="Medicine Name" class="border p-2 rounded" required${_scopeId}><input${ssrRenderAttr("value", unref(form).medicine_unit_purchase_price)} type="number" step="0.01" placeholder="Purchase Price" class="border p-2 rounded" required${_scopeId}><input${ssrRenderAttr("value", unref(form).medicine_unit_selling_price)} type="number" step="0.01" placeholder="Selling Price" class="border p-2 rounded" required${_scopeId}><input${ssrRenderAttr("value", unref(form).medicine_quantity)} type="number" placeholder="Quantity" class="border p-2 rounded" required${_scopeId}><select class="border p-2 rounded"${_scopeId}><option value="Active"${ssrIncludeBooleanAttr(Array.isArray(unref(form).status) ? ssrLooseContain(unref(form).status, "Active") : ssrLooseEqual(unref(form).status, "Active")) ? " selected" : ""}${_scopeId}>Active</option><option value="Inactive"${ssrIncludeBooleanAttr(Array.isArray(unref(form).status) ? ssrLooseContain(unref(form).status, "Inactive") : ssrLooseEqual(unref(form).status, "Inactive")) ? " selected" : ""}${_scopeId}>Inactive</option></select><div class="md:col-span-3"${_scopeId}><button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded"${ssrIncludeBooleanAttr(unref(form).processing) ? " disabled" : ""}${_scopeId}>${ssrInterpolate(__props.isEdit ? "Update Medicine" : "Save Medicine")}</button></div></form></div>`);
          } else {
            return [
              unref(flash).successMessage ? (openBlock(), createBlock("div", {
                key: 0,
                class: "mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded"
              }, toDisplayString(unref(flash).successMessage), 1)) : createCommentVNode("", true),
              unref(flash).errorMessage ? (openBlock(), createBlock("div", {
                key: 1,
                class: "mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded"
              }, toDisplayString(unref(flash).errorMessage), 1)) : createCommentVNode("", true),
              unref(flash).infoMessage ? (openBlock(), createBlock("div", {
                key: 2,
                class: "mb-4 p-4 bg-blue-100 border border-blue-400 text-blue-700 rounded"
              }, toDisplayString(unref(flash).infoMessage), 1)) : createCommentVNode("", true),
              createVNode("div", { class: "bg-white rounded-md p-4" }, [
                createVNode("div", { class: "flex justify-between items-center mb-4" }, [
                  createVNode("h1", { class: "text-xl font-bold" }, toDisplayString(__props.pageTitle), 1),
                  createVNode("button", {
                    class: "px-4 py-2 bg-gray-700 text-white rounded",
                    onClick: goToList
                  }, " Medicine Inventory List ")
                ]),
                !__props.isEdit ? (openBlock(), createBlock("div", {
                  key: 0,
                  class: "mb-6 p-4 border rounded bg-gray-50"
                }, [
                  createVNode("h3", { class: "font-semibold mb-3" }, "Bulk Medicine Upload (CSV)"),
                  createVNode("div", { class: "grid grid-cols-1 md:grid-cols-3 gap-3 mb-3" }, [
                    withDirectives(createVNode("select", {
                      "onUpdate:modelValue": ($event) => csv.value.supplier_id = $event,
                      class: "border rounded p-2"
                    }, [
                      createVNode("option", { value: "" }, "Select Supplier"),
                      (openBlock(true), createBlock(Fragment, null, renderList(__props.suppliers, (s) => {
                        return openBlock(), createBlock("option", {
                          key: s.id,
                          value: s.id
                        }, toDisplayString(s.name), 9, ["value"]);
                      }), 128))
                    ], 8, ["onUpdate:modelValue"]), [
                      [vModelSelect, csv.value.supplier_id]
                    ]),
                    withDirectives(createVNode("select", {
                      "onUpdate:modelValue": ($event) => csv.value.medicine_category_id = $event,
                      class: "border rounded p-2"
                    }, [
                      createVNode("option", { value: "" }, "Select Category"),
                      (openBlock(true), createBlock(Fragment, null, renderList(__props.medicineCategories, (c) => {
                        return openBlock(), createBlock("option", {
                          key: c.id,
                          value: c.id
                        }, toDisplayString(c.name), 9, ["value"]);
                      }), 128))
                    ], 8, ["onUpdate:modelValue"]), [
                      [vModelSelect, csv.value.medicine_category_id]
                    ]),
                    createVNode("input", {
                      type: "file",
                      accept: ".csv",
                      onChange: handleCsvFile
                    }, null, 32)
                  ]),
                  createVNode("div", { class: "flex gap-3" }, [
                    createVNode("button", {
                      class: "px-4 py-2 bg-green-600 text-white rounded",
                      disabled: csvLoading.value,
                      onClick: uploadInventoryCsv
                    }, toDisplayString(csvLoading.value ? "Uploading..." : "Upload Inventory CSV"), 9, ["disabled"]),
                    createVNode("a", {
                      href: "/sample/medicine_inventory_sample.csv",
                      target: "_blank",
                      class: "px-4 py-2 bg-gray-300 rounded"
                    }, " Download Sample CSV ")
                  ])
                ])) : createCommentVNode("", true),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"]),
                  class: "grid grid-cols-1 md:grid-cols-3 gap-4"
                }, [
                  withDirectives(createVNode("select", {
                    "onUpdate:modelValue": ($event) => unref(form).supplier_id = $event,
                    class: "border p-2 rounded",
                    required: ""
                  }, [
                    createVNode("option", { value: "" }, "Select Supplier"),
                    (openBlock(true), createBlock(Fragment, null, renderList(__props.suppliers, (s) => {
                      return openBlock(), createBlock("option", {
                        key: s.id,
                        value: s.id
                      }, toDisplayString(s.name), 9, ["value"]);
                    }), 128))
                  ], 8, ["onUpdate:modelValue"]), [
                    [vModelSelect, unref(form).supplier_id]
                  ]),
                  withDirectives(createVNode("select", {
                    "onUpdate:modelValue": ($event) => unref(form).medicine_category_id = $event,
                    class: "border p-2 rounded",
                    required: ""
                  }, [
                    createVNode("option", { value: "" }, "Select Category"),
                    (openBlock(true), createBlock(Fragment, null, renderList(__props.medicineCategories, (c) => {
                      return openBlock(), createBlock("option", {
                        key: c.id,
                        value: c.id
                      }, toDisplayString(c.name), 9, ["value"]);
                    }), 128))
                  ], 8, ["onUpdate:modelValue"]), [
                    [vModelSelect, unref(form).medicine_category_id]
                  ]),
                  withDirectives(createVNode("input", {
                    "onUpdate:modelValue": ($event) => unref(form).medicine_name = $event,
                    type: "text",
                    placeholder: "Medicine Name",
                    class: "border p-2 rounded",
                    required: ""
                  }, null, 8, ["onUpdate:modelValue"]), [
                    [vModelText, unref(form).medicine_name]
                  ]),
                  withDirectives(createVNode("input", {
                    "onUpdate:modelValue": ($event) => unref(form).medicine_unit_purchase_price = $event,
                    type: "number",
                    step: "0.01",
                    placeholder: "Purchase Price",
                    class: "border p-2 rounded",
                    required: ""
                  }, null, 8, ["onUpdate:modelValue"]), [
                    [vModelText, unref(form).medicine_unit_purchase_price]
                  ]),
                  withDirectives(createVNode("input", {
                    "onUpdate:modelValue": ($event) => unref(form).medicine_unit_selling_price = $event,
                    type: "number",
                    step: "0.01",
                    placeholder: "Selling Price",
                    class: "border p-2 rounded",
                    required: ""
                  }, null, 8, ["onUpdate:modelValue"]), [
                    [vModelText, unref(form).medicine_unit_selling_price]
                  ]),
                  withDirectives(createVNode("input", {
                    "onUpdate:modelValue": ($event) => unref(form).medicine_quantity = $event,
                    type: "number",
                    placeholder: "Quantity",
                    class: "border p-2 rounded",
                    required: ""
                  }, null, 8, ["onUpdate:modelValue"]), [
                    [vModelText, unref(form).medicine_quantity]
                  ]),
                  withDirectives(createVNode("select", {
                    "onUpdate:modelValue": ($event) => unref(form).status = $event,
                    class: "border p-2 rounded"
                  }, [
                    createVNode("option", { value: "Active" }, "Active"),
                    createVNode("option", { value: "Inactive" }, "Inactive")
                  ], 8, ["onUpdate:modelValue"]), [
                    [vModelSelect, unref(form).status]
                  ]),
                  createVNode("div", { class: "md:col-span-3" }, [
                    createVNode("button", {
                      type: "submit",
                      class: "px-6 py-2 bg-blue-600 text-white rounded",
                      disabled: unref(form).processing
                    }, toDisplayString(__props.isEdit ? "Update Medicine" : "Save Medicine"), 9, ["disabled"])
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/MedicineInventory/Form.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
