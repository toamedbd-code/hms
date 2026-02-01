import { ref, watch, onMounted, nextTick, withCtx, unref, createTextVNode, toDisplayString, createVNode, openBlock, createBlock, withModifiers, withDirectives, vModelText, Fragment, renderList, vModelSelect, withKeys, useSSRContext } from "vue";
import { ssrRenderComponent, ssrRenderAttr, ssrRenderList, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrInterpolate } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import { useForm, router } from "@inertiajs/vue3";
import { _ as _sfc_main$2 } from "./InputError-6aeb8d97.mjs";
import { _ as _sfc_main$3 } from "./InputLabel-70ca52d1.mjs";
import { _ as _sfc_main$4 } from "./PrimaryButton-b82fb16e.mjs";
import "./AlertMessage-0f422981.mjs";
import { a as displayWarning, d as displayResponse } from "./responseMessage-d505224b.mjs";
import { P as PatientModal } from "./PatientModal-85d06e3d.mjs";
import Multiselect from "vue-multiselect";
/* empty css                           */import { _ as _export_sfc } from "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "mitt";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
import "toastr";
import "sweetalert2";
import "date-fns";
const Form_vue_vue_type_style_index_0_scoped_1182fbf1_lang = "";
const _sfc_main = {
  __name: "Form",
  __ssrInlineRender: true,
  props: [
    "pharmacybill",
    "id",
    "patients",
    "medicines",
    "doctors",
    "billnumber",
    "pharmacyNo",
    "caseId",
    "categories"
  ],
  setup(__props) {
    var _a, _b, _c, _d, _e, _f, _g, _h, _i, _j, _k, _l, _m, _n, _o, _p, _q, _r;
    const props = __props;
    const createMedicineRow = () => ({
      id: Date.now() + Math.random(),
      medicineCategory: "",
      medicineName: null,
      batchNo: "",
      expiryDate: "",
      quantity: 1,
      availableQty: 0,
      salePrice: 0,
      tax: 0,
      amount: 0
    });
    const ensureNumber = (value, defaultValue = 0) => {
      const num = parseFloat(value);
      return isNaN(num) ? defaultValue : num;
    };
    const initializeMedicineRows = () => {
      var _a2;
      if (((_a2 = props.pharmacybill) == null ? void 0 : _a2.mapped_products) && Array.isArray(props.pharmacybill.mapped_products)) {
        return props.pharmacybill.mapped_products.map((item) => ({
          id: Date.now() + Math.random(),
          medicineCategory: item.medicineCategory || "",
          medicineName: item.medicine || null,
          batchNo: item.batchNo || "",
          expiryDate: item.expiryDate || "",
          quantity: ensureNumber(item.quantity, 1),
          availableQty: ensureNumber(item.availableQty, 0),
          salePrice: ensureNumber(item.rate, 0),
          tax: ensureNumber(item.tax, 0),
          amount: ensureNumber(item.amount, 0)
        }));
      }
      return [createMedicineRow()];
    };
    const medicineRows = ref(initializeMedicineRows());
    const selectedPatient = ref(
      ((_a = props.pharmacybill) == null ? void 0 : _a.patient_id) ? props.patients.find((p) => p.id === props.pharmacybill.patient_id) : null
    );
    const selectedDoctor = ref(
      ((_b = props.pharmacybill) == null ? void 0 : _b.doctor_id) ? props.doctors.find((d) => d.id === props.pharmacybill.doctor_id) : null
    );
    const form = useForm({
      pharmacy_no: ((_c = props.pharmacybill) == null ? void 0 : _c.pharmacy_no) ?? props.pharmacyNo,
      bill_no: ((_d = props.pharmacybill) == null ? void 0 : _d.bill_no) ?? props.billnumber,
      case_id: ((_e = props.pharmacybill) == null ? void 0 : _e.case_id) ?? props.caseId,
      patient_id: ((_f = props.pharmacybill) == null ? void 0 : _f.patient_id) ?? "",
      doctor_id: ((_g = props.pharmacybill) == null ? void 0 : _g.doctor_id) ?? "",
      date: ((_h = props.pharmacybill) == null ? void 0 : _h.date) ?? (/* @__PURE__ */ new Date()).toISOString().slice(0, 10),
      products: [],
      subtotal: ensureNumber((_i = props.pharmacybill) == null ? void 0 : _i.subtotal, 0),
      discount_percentage: ensureNumber((_j = props.pharmacybill) == null ? void 0 : _j.discount_percentage, 0),
      discount_amount: ensureNumber((_k = props.pharmacybill) == null ? void 0 : _k.discount_amount, 0),
      vat_percentage: ensureNumber((_l = props.pharmacybill) == null ? void 0 : _l.vat_percentage, 0),
      vat_amount: ensureNumber((_m = props.pharmacybill) == null ? void 0 : _m.vat_amount, 0),
      extra_discount: ensureNumber((_n = props.pharmacybill) == null ? void 0 : _n.extra_discount, 0),
      net_amount: ensureNumber((_o = props.pharmacybill) == null ? void 0 : _o.net_amount, 0),
      payment_mode: ((_p = props.pharmacybill) == null ? void 0 : _p.payment_mode) ?? "Cash",
      payment_amount: ensureNumber((_q = props.pharmacybill) == null ? void 0 : _q.payment_amount, 0),
      note: ((_r = props.pharmacybill) == null ? void 0 : _r.note) ?? "",
      _method: props.id ? "put" : "post"
    });
    const getFilteredMedicines = (categoryName) => {
      if (!categoryName)
        return props.medicines;
      return props.medicines.filter(
        (medicine) => {
          var _a2;
          return ((_a2 = medicine.category) == null ? void 0 : _a2.name) === categoryName;
        }
      );
    };
    const addMedicineRow = () => {
      medicineRows.value.push(createMedicineRow());
      nextTick(() => {
        const newRowIndex = medicineRows.value.length - 1;
        focusOnMedicineName(newRowIndex);
      });
    };
    const removeMedicineRow = (index) => {
      if (medicineRows.value.length > 1) {
        medicineRows.value.splice(index, 1);
        calculateTotal();
      }
    };
    const calculateTotal = () => {
      let subtotal = 0;
      if (!Array.isArray(medicineRows.value)) {
        console.error("medicineRows.value is not an array:", medicineRows.value);
        return;
      }
      medicineRows.value.forEach((row) => {
        const quantity = ensureNumber(row.quantity, 0);
        const salePrice = ensureNumber(row.salePrice, 0);
        const tax = ensureNumber(row.tax, 0);
        if (row.medicineName && quantity > 0 && salePrice > 0) {
          row.amount = quantity * salePrice + quantity * salePrice * tax / 100;
          subtotal += row.amount;
        } else {
          row.amount = 0;
        }
      });
      form.subtotal = subtotal;
      const discountPercentage = ensureNumber(form.discount_percentage, 0);
      const vatPercentage = ensureNumber(form.vat_percentage, 0);
      const extraDiscount = ensureNumber(form.extra_discount, 0);
      form.discount_amount = form.subtotal * discountPercentage / 100;
      form.vat_amount = form.subtotal * vatPercentage / 100;
      form.net_amount = form.subtotal - form.discount_amount + form.vat_amount - extraDiscount;
    };
    const handleMedicineSelection = (medicine, row, rowIndex) => {
      var _a2;
      if (medicine) {
        row.medicineName = medicine;
        row.medicineCategory = ((_a2 = medicine.category) == null ? void 0 : _a2.name) || "";
        row.salePrice = ensureNumber(medicine.sale_price, 0);
        row.availableQty = ensureNumber(medicine.medicine_quantity, 0);
        row.batchNo = medicine.batch_no || "";
        row.expiryDate = medicine.expiry_date || "";
        calculateTotal();
        nextTick(() => {
          focusOnQuantity(rowIndex);
        });
      }
    };
    const handleMedicineEnter = (rowIndex) => {
      nextTick(() => {
        focusOnQuantity(rowIndex);
      });
    };
    const handleCategorySelection = (category, row, rowIndex) => {
      row.medicineCategory = category;
      row.medicineName = null;
      row.batchNo = "";
      row.expiryDate = "";
      row.salePrice = 0;
      row.availableQty = 0;
      row.tax = 0;
      calculateTotal();
      nextTick(() => {
        focusOnMedicineName(rowIndex);
      });
    };
    const validateQuantity = (row) => {
      const quantity = ensureNumber(row.quantity, 0);
      const availableQty = ensureNumber(row.availableQty, 0);
      if (quantity > availableQty) {
        row.quantity = availableQty;
        displayWarning({ message: "Quantity cannot exceed available stock!" });
      }
      calculateTotal();
    };
    const handleQuantityEnter = (rowIndex) => {
      addMedicineRow();
    };
    const focusOnMedicineName = (rowIndex) => {
      nextTick(() => {
        const medicineMultiselect = document.querySelector(`#medicine-multiselect-${rowIndex} input`);
        if (medicineMultiselect) {
          medicineMultiselect.focus();
          medicineMultiselect.addEventListener("keydown", (e) => {
            if (e.key === "Enter") {
              e.preventDefault();
              const row = medicineRows.value[rowIndex];
              if (row.medicineName) {
                handleMedicineEnter(rowIndex);
              }
            }
          }, { once: true });
        }
      });
    };
    const focusOnQuantity = (rowIndex) => {
      nextTick(() => {
        const quantityInput = document.querySelector(`#quantity-${rowIndex}`);
        if (quantityInput) {
          quantityInput.focus();
          quantityInput.select();
        }
      });
    };
    const handlePatientSelection = (patient) => {
      selectedPatient.value = patient;
      form.patient_id = patient ? patient.id : "";
    };
    const handleDoctorSelection = (doctor) => {
      selectedDoctor.value = doctor;
      form.doctor_id = doctor ? doctor.id : "";
    };
    watch(
      () => medicineRows.value,
      (newValue) => {
        if (Array.isArray(newValue)) {
          form.products = newValue.map((row) => {
            var _a2, _b2;
            return {
              productId: ((_a2 = row.medicineName) == null ? void 0 : _a2.id) || "",
              productName: ((_b2 = row.medicineName) == null ? void 0 : _b2.medicine_name) || "",
              medicineCategory: row.medicineCategory,
              batchNo: row.batchNo,
              expiryDate: row.expiryDate,
              quantity: ensureNumber(row.quantity, 0),
              availableQty: ensureNumber(row.availableQty, 0),
              rate: ensureNumber(row.salePrice, 0),
              tax: ensureNumber(row.tax, 0),
              amount: ensureNumber(row.amount, 0)
            };
          });
          calculateTotal();
        }
      },
      { deep: true }
    );
    watch(
      () => [form.discount_percentage, form.vat_percentage, form.extra_discount],
      () => {
        calculateTotal();
      }
    );
    onMounted(() => {
      if (!Array.isArray(medicineRows.value)) {
        medicineRows.value = [createMedicineRow()];
      }
      calculateTotal();
      nextTick(() => {
        focusOnMedicineName(0);
      });
    });
    const submit = () => {
      form.products = medicineRows.value.map((row) => {
        var _a2, _b2;
        return {
          productId: ((_a2 = row.medicineName) == null ? void 0 : _a2.id) || "",
          productName: ((_b2 = row.medicineName) == null ? void 0 : _b2.medicine_name) || "",
          medicineCategory: row.medicineCategory,
          batchNo: row.batchNo,
          expiryDate: row.expiryDate,
          quantity: ensureNumber(row.quantity, 0),
          availableQty: ensureNumber(row.availableQty, 0),
          rate: ensureNumber(row.salePrice, 0),
          tax: ensureNumber(row.tax, 0),
          amount: ensureNumber(row.amount, 0)
        };
      });
      const routeName = props.id ? route("backend.pharmacybill.update", props.id) : route("backend.pharmacybill.store");
      form.post(routeName, {
        onSuccess: (response) => {
          if (!props.id) {
            form.reset();
            medicineRows.value = [createMedicineRow()];
            selectedPatient.value = null;
            selectedDoctor.value = null;
            calculateTotal();
            window.location.reload();
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
          if (!form.patient_id && newPatient.id) {
            form.patient_id = newPatient.id;
          }
        }
      });
    };
    const goToPharmacyBillList = () => {
      router.get(route("backend.pharmacybill.index"));
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(PatientModal, {
              isOpen: isPatientModalOpen.value,
              onClose: closePatientModal,
              onPatientCreated: handlePatientCreated
            }, null, _parent2, _scopeId));
            _push2(`<div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-lg shadow-lg" data-v-1182fbf1${_scopeId}><div class="flex items-center justify-between w-full px-4 py-2 bg-gray-100 border-b rounded-md border-gray-300" data-v-1182fbf1${_scopeId}><div class="flex items-center space-x-4" data-v-1182fbf1${_scopeId}><div class="relative min-w-[280px]" data-v-1182fbf1${_scopeId}><div class="relative" data-v-1182fbf1${_scopeId}><div class="col-span-1" data-v-1182fbf1${_scopeId}>`);
            _push2(ssrRenderComponent(unref(Multiselect), {
              modelValue: selectedPatient.value,
              "onUpdate:modelValue": ($event) => selectedPatient.value = $event,
              options: __props.patients,
              "track-by": "id",
              label: "name",
              placeholder: "Search and select a patient",
              class: "w-full text-sm h-[30px] rounded-md border border-slate-300",
              onSelect: handlePatientSelection
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-1",
              message: unref(form).errors.patient_id
            }, null, _parent2, _scopeId));
            _push2(`</div></div></div><button class="px-3 py-2.5 text-sm text-white bg-blue-600 rounded hover:bg-blue-700" data-v-1182fbf1${_scopeId}> + New Patient </button></div><div class="p-2 py-2 flex items-center space-x-2" data-v-1182fbf1${_scopeId}><div class="flex items-center space-x-3" data-v-1182fbf1${_scopeId}><button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2" data-v-1182fbf1${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" data-v-1182fbf1${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" data-v-1182fbf1${_scopeId}></path></svg> Pharmacy Bill List </button></div></div></div><form class="p-4" data-v-1182fbf1${_scopeId}><div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4" data-v-1182fbf1${_scopeId}><div data-v-1182fbf1${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "pharmacy_no",
              value: "Pharmacy No"
            }, null, _parent2, _scopeId));
            _push2(`<input id="pharmacy_no" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).pharmacy_no)} type="text" placeholder="Pharmacy No" readonly data-v-1182fbf1${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-2",
              message: unref(form).errors.pharmacy_no
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-1182fbf1${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "bill_no",
              value: "Bill No"
            }, null, _parent2, _scopeId));
            _push2(`<input id="bill_no" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).bill_no)} type="text" placeholder="Bill No" readonly data-v-1182fbf1${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-2",
              message: unref(form).errors.bill_no
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-1182fbf1${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "case_id",
              value: "Case ID"
            }, null, _parent2, _scopeId));
            _push2(`<input id="case_id" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).case_id)} type="text" placeholder="Case ID" data-v-1182fbf1${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-2",
              message: unref(form).errors.case_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-1182fbf1${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "date",
              value: "Date"
            }, null, _parent2, _scopeId));
            _push2(`<input id="date" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).date)} type="date" data-v-1182fbf1${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-2",
              message: unref(form).errors.date
            }, null, _parent2, _scopeId));
            _push2(`</div></div><hr class="my-4" data-v-1182fbf1${_scopeId}><div class="mt-4" data-v-1182fbf1${_scopeId}><div class="flex items-center justify-between mb-2" data-v-1182fbf1${_scopeId}><h2 class="text-lg font-semibold dark:text-white" data-v-1182fbf1${_scopeId}>Medicine Products</h2><button type="button" class="px-3 py-1 text-white bg-blue-500 rounded hover:bg-blue-600" data-v-1182fbf1${_scopeId}>+ Add</button></div><div class="overflow-x-auto" data-v-1182fbf1${_scopeId}><table class="w-full text-sm border-collapse table-auto" data-v-1182fbf1${_scopeId}><thead data-v-1182fbf1${_scopeId}><tr class="bg-gray-200" data-v-1182fbf1${_scopeId}><th class="p-2 text-left border border-gray-300 dark:border-gray-600" data-v-1182fbf1${_scopeId}>Medicine Category *</th><th class="p-2 text-left border border-gray-300 dark:border-gray-600" data-v-1182fbf1${_scopeId}>Medicine Name *</th><th class="p-2 text-left border border-gray-300 dark:border-gray-600" data-v-1182fbf1${_scopeId}>Batch No * </th><th class="p-2 text-left border border-gray-300 dark:border-gray-600" data-v-1182fbf1${_scopeId}>Expiry Date * </th><th class="p-2 text-left border border-gray-300 dark:border-gray-600" data-v-1182fbf1${_scopeId}>Quantity * | Available Qty </th><th class="p-2 text-left border border-gray-300 dark:border-gray-600" data-v-1182fbf1${_scopeId}>Sale Price (Tk.) * Tax </th><th class="p-2 text-left border border-gray-300 dark:border-gray-600" data-v-1182fbf1${_scopeId}>Amount (Tk.) * </th><th class="p-2 text-center border border-gray-300 dark:border-gray-600" data-v-1182fbf1${_scopeId}>Action</th></tr></thead><tbody data-v-1182fbf1${_scopeId}><!--[-->`);
            ssrRenderList(medicineRows.value, (row, index) => {
              _push2(`<tr class="hover:bg-gray-50 dark:hover:bg-gray-800" data-v-1182fbf1${_scopeId}><td class="p-1 border border-gray-300 dark:border-gray-600" data-v-1182fbf1${_scopeId}><select${ssrRenderAttr("id", `category-select-${index}`)} class="w-full p-1 text-sm border-none rounded focus:outline-none dark:bg-slate-700 dark:text-white" data-v-1182fbf1${_scopeId}><option value="" data-v-1182fbf1${ssrIncludeBooleanAttr(Array.isArray(row.medicineCategory) ? ssrLooseContain(row.medicineCategory, "") : ssrLooseEqual(row.medicineCategory, "")) ? " selected" : ""}${_scopeId}>Select</option><!--[-->`);
              ssrRenderList(props.categories, (category) => {
                _push2(`<option${ssrRenderAttr("value", category.name)} data-v-1182fbf1${ssrIncludeBooleanAttr(Array.isArray(row.medicineCategory) ? ssrLooseContain(row.medicineCategory, category.name) : ssrLooseEqual(row.medicineCategory, category.name)) ? " selected" : ""}${_scopeId}>${ssrInterpolate(category.name)}</option>`);
              });
              _push2(`<!--]--></select></td><td class="p-1 border border-gray-300 dark:border-gray-600 medicine-dropdown-cell" data-v-1182fbf1${_scopeId}><div${ssrRenderAttr("id", `medicine-multiselect-${index}`)} class="medicine-multiselect-wrapper" data-v-1182fbf1${_scopeId}>`);
              _push2(ssrRenderComponent(unref(Multiselect), {
                modelValue: row.medicineName,
                "onUpdate:modelValue": ($event) => row.medicineName = $event,
                options: getFilteredMedicines(row.medicineCategory),
                searchable: true,
                "close-on-select": true,
                "show-labels": false,
                label: "medicine_name",
                "track-by": "id",
                placeholder: "Select medicine",
                onSelect: ($event) => handleMedicineSelection($event, row, index),
                class: `text-xs medicine-multiselect medicine-multiselect-${index}`
              }, null, _parent2, _scopeId));
              _push2(`</div></td><td class="p-1 border border-gray-300 dark:border-gray-600" data-v-1182fbf1${_scopeId}><input${ssrRenderAttr("value", row.batchNo)} type="text" class="w-full p-1 text-sm border-none rounded focus:outline-none dark:bg-slate-700 dark:text-white" readonly data-v-1182fbf1${_scopeId}></td><td class="p-1 border border-gray-300 dark:border-gray-600" data-v-1182fbf1${_scopeId}><input type="date"${ssrRenderAttr("value", row.expiryDate)} class="w-full p-1 text-sm border-none rounded focus:outline-none dark:bg-slate-700 dark:text-white" readonly data-v-1182fbf1${_scopeId}></td><td class="p-1 border border-gray-300 dark:border-gray-600" data-v-1182fbf1${_scopeId}><div class="flex items-center space-x-1" data-v-1182fbf1${_scopeId}><input type="number"${ssrRenderAttr("value", row.quantity)}${ssrRenderAttr("id", `quantity-${index}`)} class="w-16 p-1 text-sm text-center border-none rounded focus:outline-none dark:bg-slate-700 dark:text-white" min="1"${ssrRenderAttr("max", row.availableQty)} data-v-1182fbf1${_scopeId}><span class="text-xs text-gray-500" data-v-1182fbf1${_scopeId}>| ${ssrInterpolate(ensureNumber(row.availableQty, 0))}</span></div></td><td class="p-1 border border-gray-300 dark:border-gray-600" data-v-1182fbf1${_scopeId}><div class="flex items-center space-x-1" data-v-1182fbf1${_scopeId}><input type="number"${ssrRenderAttr("value", row.salePrice)} class="w-20 p-1 text-sm border-none rounded focus:outline-none dark:bg-slate-700 dark:text-white" step="0.01" data-v-1182fbf1${_scopeId}><input type="number"${ssrRenderAttr("value", row.tax)} class="w-12 p-1 text-sm border-none rounded focus:outline-none dark:bg-slate-700 dark:text-white" placeholder="%" step="0.01" data-v-1182fbf1${_scopeId}><span class="text-xs" data-v-1182fbf1${_scopeId}>%</span></div></td><td class="p-1 text-center border border-gray-300 dark:border-gray-600 dark:text-white" data-v-1182fbf1${_scopeId}>${ssrInterpolate(ensureNumber(row.amount, 0).toFixed(2))}</td><td class="p-1 text-center border border-gray-300 dark:border-gray-600" data-v-1182fbf1${_scopeId}><button type="button" class="p-1 text-white bg-red-500 rounded hover:bg-red-600"${ssrIncludeBooleanAttr(medicineRows.value.length === 1) ? " disabled" : ""} data-v-1182fbf1${_scopeId}> × </button></td></tr>`);
            });
            _push2(`<!--]--></tbody></table></div></div><hr class="my-4" data-v-1182fbf1${_scopeId}><div class="grid grid-cols-1 gap-4 md:grid-cols-2" data-v-1182fbf1${_scopeId}><div class="space-y-4" data-v-1182fbf1${_scopeId}><div class="relative" data-v-1182fbf1${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "doctor_id",
              value: "Hospital Doctor"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(unref(Multiselect), {
              modelValue: selectedDoctor.value,
              "onUpdate:modelValue": ($event) => selectedDoctor.value = $event,
              options: props.doctors,
              searchable: true,
              "close-on-select": true,
              "show-labels": false,
              label: "name",
              "track-by": "id",
              placeholder: "Select a doctor",
              onSelect: handleDoctorSelection
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-2",
              message: unref(form).errors.doctor_id
            }, null, _parent2, _scopeId));
            _push2(`</div><div data-v-1182fbf1${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "note",
              value: "Note"
            }, null, _parent2, _scopeId));
            _push2(`<textarea rows="3" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-1182fbf1${_scopeId}>${ssrInterpolate(unref(form).note)}</textarea>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              class: "mt-2",
              message: unref(form).errors.note
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="p-4 bg-gray-100 rounded-md dark:bg-gray-800" data-v-1182fbf1${_scopeId}><div class="space-y-3" data-v-1182fbf1${_scopeId}><div class="flex justify-between items-center" data-v-1182fbf1${_scopeId}><span class="font-semibold dark:text-white" data-v-1182fbf1${_scopeId}>Total (Tk.)</span><span class="text-2xl font-bold dark:text-white" data-v-1182fbf1${_scopeId}>${ssrInterpolate(ensureNumber(unref(form).subtotal, 0).toFixed(2))}</span></div><div class="flex justify-between items-center" data-v-1182fbf1${_scopeId}><span class="dark:text-white" data-v-1182fbf1${_scopeId}>Discount (Tk.)</span><div class="flex items-center space-x-2" data-v-1182fbf1${_scopeId}><input${ssrRenderAttr("value", unref(form).discount_percentage)} type="number" step="0.01" class="w-16 p-1 text-right border rounded dark:bg-gray-700 dark:text-white text-sm" placeholder="%" data-v-1182fbf1${_scopeId}><span class="text-sm dark:text-white" data-v-1182fbf1${_scopeId}>%</span><span class="dark:text-white" data-v-1182fbf1${_scopeId}>${ssrInterpolate(ensureNumber(unref(form).discount_amount, 0).toFixed(2))}</span></div></div><div class="flex justify-between items-center" data-v-1182fbf1${_scopeId}><span class="dark:text-white" data-v-1182fbf1${_scopeId}>Tax (Tk.)</span><div class="flex items-center space-x-2" data-v-1182fbf1${_scopeId}><input${ssrRenderAttr("value", unref(form).vat_percentage)} type="number" step="0.01" class="w-16 p-1 text-right border rounded dark:bg-gray-700 dark:text-white text-sm" placeholder="%" data-v-1182fbf1${_scopeId}><span class="text-sm dark:text-white" data-v-1182fbf1${_scopeId}>%</span><span class="dark:text-white" data-v-1182fbf1${_scopeId}>${ssrInterpolate(ensureNumber(unref(form).vat_amount, 0).toFixed(2))}</span></div></div><div class="flex justify-between items-center border-t pt-2" data-v-1182fbf1${_scopeId}><span class="font-semibold dark:text-white" data-v-1182fbf1${_scopeId}>Net Amount (Tk.)</span><span class="text-xl font-bold text-green-600 dark:text-green-400" data-v-1182fbf1${_scopeId}>${ssrInterpolate(ensureNumber(unref(form).net_amount, 0).toFixed(2))}</span></div><div class="border-t pt-3 space-y-2" data-v-1182fbf1${_scopeId}><div data-v-1182fbf1${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "payment_mode",
              value: "Payment Mode"
            }, null, _parent2, _scopeId));
            _push2(`<select class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-1182fbf1${_scopeId}><option data-v-1182fbf1${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, null) : ssrLooseEqual(unref(form).payment_mode, null)) ? " selected" : ""}${_scopeId}>Cash</option><option data-v-1182fbf1${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, null) : ssrLooseEqual(unref(form).payment_mode, null)) ? " selected" : ""}${_scopeId}>Card</option><option data-v-1182fbf1${ssrIncludeBooleanAttr(Array.isArray(unref(form).payment_mode) ? ssrLooseContain(unref(form).payment_mode, null) : ssrLooseEqual(unref(form).payment_mode, null)) ? " selected" : ""}${_scopeId}>Bank Transfer</option></select></div><div data-v-1182fbf1${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              for: "payment_amount",
              value: "Payment Amount (Tk.)"
            }, null, _parent2, _scopeId));
            _push2(`<input${ssrRenderAttr("value", unref(form).payment_amount)} type="number" step="0.01" class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" data-v-1182fbf1${_scopeId}></div></div></div></div></div><div class="flex items-center justify-end mt-6 space-x-3" data-v-1182fbf1${_scopeId}><button type="button" class="px-6 py-2 text-white bg-blue-600 rounded hover:bg-blue-700" data-v-1182fbf1${_scopeId}>💾 Save &amp; Print</button>`);
            _push2(ssrRenderComponent(_sfc_main$4, {
              type: "submit",
              class: ["px-6 py-2", { "opacity-25": unref(form).processing }],
              disabled: unref(form).processing
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(` 💾 ${ssrInterpolate(props.id ? "Update" : "Save")}`);
                } else {
                  return [
                    createTextVNode(" 💾 " + toDisplayString(props.id ? "Update" : "Save"), 1)
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
                onClose: closePatientModal,
                onPatientCreated: handlePatientCreated
              }, null, 8, ["isOpen"]),
              createVNode("div", { class: "w-full transition duration-1000 ease-in-out transform bg-white rounded-lg shadow-lg" }, [
                createVNode("div", { class: "flex items-center justify-between w-full px-4 py-2 bg-gray-100 border-b rounded-md border-gray-300" }, [
                  createVNode("div", { class: "flex items-center space-x-4" }, [
                    createVNode("div", { class: "relative min-w-[280px]" }, [
                      createVNode("div", { class: "relative" }, [
                        createVNode("div", { class: "col-span-1" }, [
                          createVNode(unref(Multiselect), {
                            modelValue: selectedPatient.value,
                            "onUpdate:modelValue": ($event) => selectedPatient.value = $event,
                            options: __props.patients,
                            "track-by": "id",
                            label: "name",
                            placeholder: "Search and select a patient",
                            class: "w-full text-sm h-[30px] rounded-md border border-slate-300",
                            onSelect: handlePatientSelection
                          }, null, 8, ["modelValue", "onUpdate:modelValue", "options"]),
                          createVNode(_sfc_main$2, {
                            class: "mt-1",
                            message: unref(form).errors.patient_id
                          }, null, 8, ["message"])
                        ])
                      ])
                    ]),
                    createVNode("button", {
                      onClick: openPatientModal,
                      class: "px-3 py-2.5 text-sm text-white bg-blue-600 rounded hover:bg-blue-700"
                    }, " + New Patient ")
                  ]),
                  createVNode("div", { class: "p-2 py-2 flex items-center space-x-2" }, [
                    createVNode("div", { class: "flex items-center space-x-3" }, [
                      createVNode("button", {
                        onClick: goToPharmacyBillList,
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
                        createTextVNode(" Pharmacy Bill List ")
                      ])
                    ])
                  ])
                ]),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"]),
                  class: "p-4"
                }, [
                  createVNode("div", { class: "grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4" }, [
                    createVNode("div", null, [
                      createVNode(_sfc_main$3, {
                        for: "pharmacy_no",
                        value: "Pharmacy No"
                      }),
                      withDirectives(createVNode("input", {
                        id: "pharmacy_no",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).pharmacy_no = $event,
                        type: "text",
                        placeholder: "Pharmacy No",
                        readonly: ""
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).pharmacy_no]
                      ]),
                      createVNode(_sfc_main$2, {
                        class: "mt-2",
                        message: unref(form).errors.pharmacy_no
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$3, {
                        for: "bill_no",
                        value: "Bill No"
                      }),
                      withDirectives(createVNode("input", {
                        id: "bill_no",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).bill_no = $event,
                        type: "text",
                        placeholder: "Bill No",
                        readonly: ""
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).bill_no]
                      ]),
                      createVNode(_sfc_main$2, {
                        class: "mt-2",
                        message: unref(form).errors.bill_no
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$3, {
                        for: "case_id",
                        value: "Case ID"
                      }),
                      withDirectives(createVNode("input", {
                        id: "case_id",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).case_id = $event,
                        type: "text",
                        placeholder: "Case ID"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).case_id]
                      ]),
                      createVNode(_sfc_main$2, {
                        class: "mt-2",
                        message: unref(form).errors.case_id
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$3, {
                        for: "date",
                        value: "Date"
                      }),
                      withDirectives(createVNode("input", {
                        id: "date",
                        class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                        "onUpdate:modelValue": ($event) => unref(form).date = $event,
                        type: "date"
                      }, null, 8, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).date]
                      ]),
                      createVNode(_sfc_main$2, {
                        class: "mt-2",
                        message: unref(form).errors.date
                      }, null, 8, ["message"])
                    ])
                  ]),
                  createVNode("hr", { class: "my-4" }),
                  createVNode("div", { class: "mt-4" }, [
                    createVNode("div", { class: "flex items-center justify-between mb-2" }, [
                      createVNode("h2", { class: "text-lg font-semibold dark:text-white" }, "Medicine Products"),
                      createVNode("button", {
                        type: "button",
                        onClick: addMedicineRow,
                        class: "px-3 py-1 text-white bg-blue-500 rounded hover:bg-blue-600"
                      }, "+ Add")
                    ]),
                    createVNode("div", { class: "overflow-x-auto" }, [
                      createVNode("table", { class: "w-full text-sm border-collapse table-auto" }, [
                        createVNode("thead", null, [
                          createVNode("tr", { class: "bg-gray-200" }, [
                            createVNode("th", { class: "p-2 text-left border border-gray-300 dark:border-gray-600" }, "Medicine Category *"),
                            createVNode("th", { class: "p-2 text-left border border-gray-300 dark:border-gray-600" }, "Medicine Name *"),
                            createVNode("th", { class: "p-2 text-left border border-gray-300 dark:border-gray-600" }, "Batch No * "),
                            createVNode("th", { class: "p-2 text-left border border-gray-300 dark:border-gray-600" }, "Expiry Date * "),
                            createVNode("th", { class: "p-2 text-left border border-gray-300 dark:border-gray-600" }, "Quantity * | Available Qty "),
                            createVNode("th", { class: "p-2 text-left border border-gray-300 dark:border-gray-600" }, "Sale Price (Tk.) * Tax "),
                            createVNode("th", { class: "p-2 text-left border border-gray-300 dark:border-gray-600" }, "Amount (Tk.) * "),
                            createVNode("th", { class: "p-2 text-center border border-gray-300 dark:border-gray-600" }, "Action")
                          ])
                        ]),
                        createVNode("tbody", null, [
                          (openBlock(true), createBlock(Fragment, null, renderList(medicineRows.value, (row, index) => {
                            return openBlock(), createBlock("tr", {
                              key: row.id,
                              class: "hover:bg-gray-50 dark:hover:bg-gray-800"
                            }, [
                              createVNode("td", { class: "p-1 border border-gray-300 dark:border-gray-600" }, [
                                withDirectives(createVNode("select", {
                                  "onUpdate:modelValue": ($event) => row.medicineCategory = $event,
                                  onChange: ($event) => handleCategorySelection(row.medicineCategory, row, index),
                                  id: `category-select-${index}`,
                                  class: "w-full p-1 text-sm border-none rounded focus:outline-none dark:bg-slate-700 dark:text-white"
                                }, [
                                  createVNode("option", { value: "" }, "Select"),
                                  (openBlock(true), createBlock(Fragment, null, renderList(props.categories, (category) => {
                                    return openBlock(), createBlock("option", {
                                      key: category.id,
                                      value: category.name
                                    }, toDisplayString(category.name), 9, ["value"]);
                                  }), 128))
                                ], 40, ["onUpdate:modelValue", "onChange", "id"]), [
                                  [vModelSelect, row.medicineCategory]
                                ])
                              ]),
                              createVNode("td", { class: "p-1 border border-gray-300 dark:border-gray-600 medicine-dropdown-cell" }, [
                                createVNode("div", {
                                  id: `medicine-multiselect-${index}`,
                                  class: "medicine-multiselect-wrapper"
                                }, [
                                  createVNode(unref(Multiselect), {
                                    modelValue: row.medicineName,
                                    "onUpdate:modelValue": ($event) => row.medicineName = $event,
                                    options: getFilteredMedicines(row.medicineCategory),
                                    searchable: true,
                                    "close-on-select": true,
                                    "show-labels": false,
                                    label: "medicine_name",
                                    "track-by": "id",
                                    placeholder: "Select medicine",
                                    onSelect: ($event) => handleMedicineSelection($event, row, index),
                                    class: `text-xs medicine-multiselect medicine-multiselect-${index}`
                                  }, null, 8, ["modelValue", "onUpdate:modelValue", "options", "onSelect", "class"])
                                ], 8, ["id"])
                              ]),
                              createVNode("td", { class: "p-1 border border-gray-300 dark:border-gray-600" }, [
                                withDirectives(createVNode("input", {
                                  "onUpdate:modelValue": ($event) => row.batchNo = $event,
                                  type: "text",
                                  class: "w-full p-1 text-sm border-none rounded focus:outline-none dark:bg-slate-700 dark:text-white",
                                  readonly: ""
                                }, null, 8, ["onUpdate:modelValue"]), [
                                  [vModelText, row.batchNo]
                                ])
                              ]),
                              createVNode("td", { class: "p-1 border border-gray-300 dark:border-gray-600" }, [
                                withDirectives(createVNode("input", {
                                  type: "date",
                                  "onUpdate:modelValue": ($event) => row.expiryDate = $event,
                                  class: "w-full p-1 text-sm border-none rounded focus:outline-none dark:bg-slate-700 dark:text-white",
                                  readonly: ""
                                }, null, 8, ["onUpdate:modelValue"]), [
                                  [vModelText, row.expiryDate]
                                ])
                              ]),
                              createVNode("td", { class: "p-1 border border-gray-300 dark:border-gray-600" }, [
                                createVNode("div", { class: "flex items-center space-x-1" }, [
                                  withDirectives(createVNode("input", {
                                    type: "number",
                                    "onUpdate:modelValue": ($event) => row.quantity = $event,
                                    id: `quantity-${index}`,
                                    onInput: ($event) => validateQuantity(row),
                                    onKeydown: withKeys(withModifiers(($event) => handleQuantityEnter(), ["prevent"]), ["enter"]),
                                    class: "w-16 p-1 text-sm text-center border-none rounded focus:outline-none dark:bg-slate-700 dark:text-white",
                                    min: "1",
                                    max: row.availableQty
                                  }, null, 40, ["onUpdate:modelValue", "id", "onInput", "onKeydown", "max"]), [
                                    [vModelText, row.quantity]
                                  ]),
                                  createVNode("span", { class: "text-xs text-gray-500" }, "| " + toDisplayString(ensureNumber(row.availableQty, 0)), 1)
                                ])
                              ]),
                              createVNode("td", { class: "p-1 border border-gray-300 dark:border-gray-600" }, [
                                createVNode("div", { class: "flex items-center space-x-1" }, [
                                  withDirectives(createVNode("input", {
                                    type: "number",
                                    "onUpdate:modelValue": ($event) => row.salePrice = $event,
                                    onInput: calculateTotal,
                                    class: "w-20 p-1 text-sm border-none rounded focus:outline-none dark:bg-slate-700 dark:text-white",
                                    step: "0.01"
                                  }, null, 40, ["onUpdate:modelValue"]), [
                                    [vModelText, row.salePrice]
                                  ]),
                                  withDirectives(createVNode("input", {
                                    type: "number",
                                    "onUpdate:modelValue": ($event) => row.tax = $event,
                                    onInput: calculateTotal,
                                    class: "w-12 p-1 text-sm border-none rounded focus:outline-none dark:bg-slate-700 dark:text-white",
                                    placeholder: "%",
                                    step: "0.01"
                                  }, null, 40, ["onUpdate:modelValue"]), [
                                    [vModelText, row.tax]
                                  ]),
                                  createVNode("span", { class: "text-xs" }, "%")
                                ])
                              ]),
                              createVNode("td", { class: "p-1 text-center border border-gray-300 dark:border-gray-600 dark:text-white" }, toDisplayString(ensureNumber(row.amount, 0).toFixed(2)), 1),
                              createVNode("td", { class: "p-1 text-center border border-gray-300 dark:border-gray-600" }, [
                                createVNode("button", {
                                  type: "button",
                                  onClick: ($event) => removeMedicineRow(index),
                                  class: "p-1 text-white bg-red-500 rounded hover:bg-red-600",
                                  disabled: medicineRows.value.length === 1
                                }, " × ", 8, ["onClick", "disabled"])
                              ])
                            ]);
                          }), 128))
                        ])
                      ])
                    ])
                  ]),
                  createVNode("hr", { class: "my-4" }),
                  createVNode("div", { class: "grid grid-cols-1 gap-4 md:grid-cols-2" }, [
                    createVNode("div", { class: "space-y-4" }, [
                      createVNode("div", { class: "relative" }, [
                        createVNode(_sfc_main$3, {
                          for: "doctor_id",
                          value: "Hospital Doctor"
                        }),
                        createVNode(unref(Multiselect), {
                          modelValue: selectedDoctor.value,
                          "onUpdate:modelValue": ($event) => selectedDoctor.value = $event,
                          options: props.doctors,
                          searchable: true,
                          "close-on-select": true,
                          "show-labels": false,
                          label: "name",
                          "track-by": "id",
                          placeholder: "Select a doctor",
                          onSelect: handleDoctorSelection
                        }, null, 8, ["modelValue", "onUpdate:modelValue", "options"]),
                        createVNode(_sfc_main$2, {
                          class: "mt-2",
                          message: unref(form).errors.doctor_id
                        }, null, 8, ["message"])
                      ]),
                      createVNode("div", null, [
                        createVNode(_sfc_main$3, {
                          for: "note",
                          value: "Note"
                        }),
                        withDirectives(createVNode("textarea", {
                          "onUpdate:modelValue": ($event) => unref(form).note = $event,
                          rows: "3",
                          class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        }, null, 8, ["onUpdate:modelValue"]), [
                          [vModelText, unref(form).note]
                        ]),
                        createVNode(_sfc_main$2, {
                          class: "mt-2",
                          message: unref(form).errors.note
                        }, null, 8, ["message"])
                      ])
                    ]),
                    createVNode("div", { class: "p-4 bg-gray-100 rounded-md dark:bg-gray-800" }, [
                      createVNode("div", { class: "space-y-3" }, [
                        createVNode("div", { class: "flex justify-between items-center" }, [
                          createVNode("span", { class: "font-semibold dark:text-white" }, "Total (Tk.)"),
                          createVNode("span", { class: "text-2xl font-bold dark:text-white" }, toDisplayString(ensureNumber(unref(form).subtotal, 0).toFixed(2)), 1)
                        ]),
                        createVNode("div", { class: "flex justify-between items-center" }, [
                          createVNode("span", { class: "dark:text-white" }, "Discount (Tk.)"),
                          createVNode("div", { class: "flex items-center space-x-2" }, [
                            withDirectives(createVNode("input", {
                              "onUpdate:modelValue": ($event) => unref(form).discount_percentage = $event,
                              type: "number",
                              step: "0.01",
                              class: "w-16 p-1 text-right border rounded dark:bg-gray-700 dark:text-white text-sm",
                              placeholder: "%"
                            }, null, 8, ["onUpdate:modelValue"]), [
                              [vModelText, unref(form).discount_percentage]
                            ]),
                            createVNode("span", { class: "text-sm dark:text-white" }, "%"),
                            createVNode("span", { class: "dark:text-white" }, toDisplayString(ensureNumber(unref(form).discount_amount, 0).toFixed(2)), 1)
                          ])
                        ]),
                        createVNode("div", { class: "flex justify-between items-center" }, [
                          createVNode("span", { class: "dark:text-white" }, "Tax (Tk.)"),
                          createVNode("div", { class: "flex items-center space-x-2" }, [
                            withDirectives(createVNode("input", {
                              "onUpdate:modelValue": ($event) => unref(form).vat_percentage = $event,
                              type: "number",
                              step: "0.01",
                              class: "w-16 p-1 text-right border rounded dark:bg-gray-700 dark:text-white text-sm",
                              placeholder: "%"
                            }, null, 8, ["onUpdate:modelValue"]), [
                              [vModelText, unref(form).vat_percentage]
                            ]),
                            createVNode("span", { class: "text-sm dark:text-white" }, "%"),
                            createVNode("span", { class: "dark:text-white" }, toDisplayString(ensureNumber(unref(form).vat_amount, 0).toFixed(2)), 1)
                          ])
                        ]),
                        createVNode("div", { class: "flex justify-between items-center border-t pt-2" }, [
                          createVNode("span", { class: "font-semibold dark:text-white" }, "Net Amount (Tk.)"),
                          createVNode("span", { class: "text-xl font-bold text-green-600 dark:text-green-400" }, toDisplayString(ensureNumber(unref(form).net_amount, 0).toFixed(2)), 1)
                        ]),
                        createVNode("div", { class: "border-t pt-3 space-y-2" }, [
                          createVNode("div", null, [
                            createVNode(_sfc_main$3, {
                              for: "payment_mode",
                              value: "Payment Mode"
                            }),
                            withDirectives(createVNode("select", {
                              "onUpdate:modelValue": ($event) => unref(form).payment_mode = $event,
                              class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            }, [
                              createVNode("option", null, "Cash"),
                              createVNode("option", null, "Card"),
                              createVNode("option", null, "Bank Transfer")
                            ], 8, ["onUpdate:modelValue"]), [
                              [vModelSelect, unref(form).payment_mode]
                            ])
                          ]),
                          createVNode("div", null, [
                            createVNode(_sfc_main$3, {
                              for: "payment_amount",
                              value: "Payment Amount (Tk.)"
                            }),
                            withDirectives(createVNode("input", {
                              "onUpdate:modelValue": ($event) => unref(form).payment_amount = $event,
                              type: "number",
                              step: "0.01",
                              class: "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            }, null, 8, ["onUpdate:modelValue"]), [
                              [vModelText, unref(form).payment_amount]
                            ])
                          ])
                        ])
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "flex items-center justify-end mt-6 space-x-3" }, [
                    createVNode("button", {
                      type: "button",
                      class: "px-6 py-2 text-white bg-blue-600 rounded hover:bg-blue-700"
                    }, "💾 Save & Print"),
                    createVNode(_sfc_main$4, {
                      type: "submit",
                      class: ["px-6 py-2", { "opacity-25": unref(form).processing }],
                      disabled: unref(form).processing
                    }, {
                      default: withCtx(() => [
                        createTextVNode(" 💾 " + toDisplayString(props.id ? "Update" : "Save"), 1)
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/PharmacyBill/Form.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const Form = /* @__PURE__ */ _export_sfc(_sfc_main, [["__scopeId", "data-v-1182fbf1"]]);
export {
  Form as default
};
