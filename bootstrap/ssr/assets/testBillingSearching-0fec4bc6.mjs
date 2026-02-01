import { ref, onMounted, watch, computed, nextTick, unref, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderAttr, ssrRenderList, ssrRenderClass } from "vue/server-renderer";
import { Head, router } from "@inertiajs/vue3";
import { _ as _sfc_main$1 } from "./InputLabel-70ca52d1.mjs";
import { P as PatientModal } from "./PatientModal-85d06e3d.mjs";
import "./responseMessage-d505224b.mjs";
import { debounce } from "lodash";
import { _ as _export_sfc } from "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "./InputError-6aeb8d97.mjs";
import "./PrimaryButton-b82fb16e.mjs";
import "date-fns";
import "toastr";
import "sweetalert2";
const testBillingSearching_vue_vue_type_style_index_0_scoped_64dc99f2_lang = "";
const _sfc_main = {
  __name: "testBillingSearching",
  __ssrInlineRender: true,
  props: {
    pageTitle: String,
    pathologyAndRadiologyTests: Array,
    medicineInventories: Array,
    doctors: Array,
    patients: Array,
    id: [String, Number],
    editData: Object,
    referrers: Array,
    authInfo: Object
  },
  setup(__props) {
    const props = __props;
    ref(null);
    const doctorSelectRef = ref(null);
    ref(null);
    ref(null);
    ref(null);
    ref(null);
    ref(null);
    ref(null);
    ref(null);
    ref(null);
    ref(null);
    ref(null);
    ref(null);
    ref(null);
    ref(null);
    ref(false);
    ref(false);
    ref(false);
    const patientSearchQuery = ref("");
    const patientSelectedIndex = ref(-1);
    const showPatientDropdown = ref(false);
    const filteredPatients = ref([]);
    const isPatientModalOpen = ref(false);
    const patientsList = ref([...props.patients]);
    const itemForm = ref({
      category: "",
      itemName: "",
      itemId: null,
      unitPrice: 0,
      quantity: 1,
      totalAmount: 0
    });
    const patientForm = ref({
      patient_id: "",
      doctor_id: "",
      patientMobile: "",
      gender: "",
      cardType: "Cash",
      payMode: "Cash",
      cardNumber: ""
    });
    const summary = ref({
      total: 0,
      discount: 0,
      discountType: "percentage",
      extraFlatDiscount: 0,
      payableAmount: 0,
      paidAmt: 0,
      changeAmt: 0,
      dueAmount: 0,
      receivingAmt: 0,
      takingAmt: 0,
      returnAmt: 0,
      deliveryDate: "",
      remarks: ""
    });
    const commission = ref({
      total: 0,
      physystAmt: 0,
      slider: 0,
      referrer_id: "",
      commissionRate: 0
    });
    const items = ref([]);
    const isNewPatient = ref(false);
    const newPatientForm = ref({
      name: "",
      phone: "",
      gender: ""
    });
    ref(null);
    ref(null);
    const selectedItemRef = ref(null);
    const searchQuery = ref("");
    const selectedIndex = ref(-1);
    const commissionDetails = ref({
      hasPathologyCommission: false,
      hasRadiologyCommission: false,
      hasMedicineCommission: false,
      pathologyRate: 0,
      radiologyRate: 0,
      medicineRate: 0,
      manualCommissionEnabled: false,
      noCommissionMessage: ""
    });
    onMounted(() => {
      if (!props.id || !props.editData) {
        const today = /* @__PURE__ */ new Date();
        const formattedDate = today.toISOString().split("T")[0];
        summary.value.deliveryDate = formattedDate;
      }
    });
    watch(() => patientForm.value.patientMobile, (newMobile) => {
      if (isNewPatient.value) {
        newPatientForm.value.phone = newMobile;
      }
    });
    watch(() => patientForm.value.gender, (newGender) => {
      if (isNewPatient.value) {
        newPatientForm.value.gender = newGender;
      }
    });
    watch(() => newPatientForm.value.phone, (newPhone) => {
      console.log("newPatientForm.phone changed to:", newPhone);
      if (isNewPatient.value && newPhone) {
        patientForm.value.patientMobile = newPhone;
      }
    });
    watch(() => newPatientForm.value.gender, (newGender) => {
      console.log("newPatientForm.gender changed to:", newGender);
      if (isNewPatient.value && newGender) {
        patientForm.value.gender = newGender;
      }
    });
    watch(() => newPatientForm.value.name, (newName) => {
      console.log("newPatientForm.name changed to:", newName);
      if (isNewPatient.value && newName) {
        patientSearchQuery.value = newName;
      }
    });
    watch(() => isNewPatient.value, (newValue) => {
      console.log("isNewPatient changed to:", newValue);
      if (!newValue) {
        console.log("Resetting new patient form...");
        newPatientForm.value = {
          name: "",
          phone: "",
          gender: ""
        };
        if (!patientForm.value.patient_id) {
          patientForm.value.patientMobile = "";
          patientForm.value.gender = "";
          patientSearchQuery.value = "";
        }
      } else {
        console.log("Entering new patient mode...");
        patientForm.value.patient_id = null;
      }
    });
    watch(patientSearchQuery, debounce((newQuery) => {
      if (newQuery.trim() === "") {
        filteredPatients.value = [];
        showPatientDropdown.value = false;
        return;
      }
      const query = newQuery.toLowerCase();
      filteredPatients.value = props.patients.filter(
        (patient) => patient.name.toLowerCase().includes(query) || patient.phone.toLowerCase().includes(query)
      );
      showPatientDropdown.value = filteredPatients.value.length > 0;
      patientSelectedIndex.value = -1;
    }, 300));
    const closePatientModal = () => {
      isPatientModalOpen.value = false;
    };
    const handlePatientCreated = (newPatient) => {
      patientsList.value.push(newPatient);
      patientForm.value.patient_id = newPatient.id;
      patientForm.value.patientMobile = newPatient.phone;
      patientForm.value.gender = newPatient.gender;
      patientSearchQuery.value = newPatient.name;
      isNewPatient.value = false;
      router.reload({
        only: ["patients"],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
          patientsList.value = [...page.props.patients];
        }
      });
      nextTick(() => {
        var _a;
        (_a = doctorSelectRef.value) == null ? void 0 : _a.focus();
      });
    };
    watch(
      () => itemForm.value.quantity,
      (newQuantity) => {
        if (newQuantity && itemForm.value.unitPrice) {
          itemForm.value.totalAmount = (newQuantity * itemForm.value.unitPrice).toFixed(2);
        }
      }
    );
    watch([() => itemForm.value.quantity, () => itemForm.value.unitPrice], () => {
      itemForm.value.totalAmount = (itemForm.value.quantity * itemForm.value.unitPrice).toFixed(2);
    });
    watch(
      () => patientForm.value.patient_id,
      (newPatientId) => {
        if (newPatientId && props.patients) {
          const selectedPatient = props.patients.find(
            (patient) => patient.id == newPatientId
          );
          if (selectedPatient) {
            patientForm.value.patientMobile = selectedPatient.phone || selectedPatient.mobile || "";
            patientForm.value.gender = selectedPatient.gender || "";
          }
        }
      }
    );
    watch(
      () => commission.value.referrer_id,
      (newReferrerId) => {
        if (newReferrerId && props.referrers) {
          const selectedReferrer = props.referrers.find(
            (referrer) => referrer.id == newReferrerId
          );
          if (selectedReferrer) {
            updateCommissionRate(selectedReferrer);
          }
        } else {
          commission.value.commissionRate = 0;
          commission.value.slider = 0;
          resetCommissionDetails();
          updateCommission();
        }
      }
    );
    watch(
      () => commission.value.slider,
      (newValue) => {
        if (commissionDetails.value.manualCommissionEnabled) {
          commission.value.commissionRate = parseFloat(newValue);
        }
        updateCommission();
      }
    );
    const updateCommissionRate = (referrer) => {
      if (!referrer || items.value.length === 0) {
        commission.value.commissionRate = 0;
        commission.value.slider = 0;
        updateCommission();
        resetCommissionDetails();
        return;
      }
      const itemCategories = [
        ...new Set(items.value.map((item) => item.category.toLowerCase()))
      ];
      const availableCommissions = {
        pathology: referrer.pathology_commission || 0,
        radiology: referrer.radiology_commission || 0,
        medicine: referrer.pharmacy_commission || 0
      };
      commissionDetails.value.hasPathologyCommission = itemCategories.includes("pathology") && availableCommissions.pathology > 0;
      commissionDetails.value.hasRadiologyCommission = itemCategories.includes("radiology") && availableCommissions.radiology > 0;
      commissionDetails.value.hasMedicineCommission = itemCategories.includes("medicine") && availableCommissions.medicine > 0;
      commissionDetails.value.pathologyRate = availableCommissions.pathology;
      commissionDetails.value.radiologyRate = availableCommissions.radiology;
      commissionDetails.value.medicineRate = availableCommissions.medicine;
      let totalCommissionAmount = 0;
      let categoriesWithoutCommission = [];
      let categoriesWithCommission = [];
      const categoryData = {};
      items.value.forEach((item) => {
        const category = item.category.toLowerCase();
        if (!categoryData[category]) {
          categoryData[category] = {
            totalAmount: 0,
            items: []
          };
        }
        categoryData[category].totalAmount += item.netAmount;
        categoryData[category].items.push(item);
      });
      Object.keys(categoryData).forEach((category) => {
        let rate = 0;
        switch (category) {
          case "pathology":
            rate = availableCommissions.pathology;
            break;
          case "radiology":
            rate = availableCommissions.radiology;
            break;
          case "medicine":
            rate = availableCommissions.medicine;
            break;
        }
        const categoryAmount = categoryData[category].totalAmount;
        if (rate > 0) {
          categoriesWithCommission.push(category);
          totalCommissionAmount += categoryAmount * rate / 100;
        } else {
          categoriesWithoutCommission.push(category);
        }
      });
      if (categoriesWithoutCommission.length > 0 && categoriesWithCommission.length === 0) {
        commissionDetails.value.noCommissionMessage = `This referrer doesn't have commission for: ${categoriesWithoutCommission.join(
          ", "
        )}. You can set manual commission.`;
        commissionDetails.value.manualCommissionEnabled = true;
        commission.value.commissionRate = 0;
        commission.value.slider = 0;
      } else if (categoriesWithoutCommission.length > 0 && categoriesWithCommission.length > 0) {
        const totalBillAmount = summary.value.payableAmount || summary.value.total;
        const effectiveCommissionRate = totalBillAmount > 0 ? totalCommissionAmount / totalBillAmount * 100 : 0;
        commission.value.commissionRate = parseFloat(effectiveCommissionRate.toFixed(2));
        commission.value.slider = parseFloat(effectiveCommissionRate.toFixed(2));
        commissionDetails.value.manualCommissionEnabled = false;
        commissionDetails.value.noCommissionMessage = `Mixed commission: ${categoriesWithCommission.join(
          ", "
        )} have commission, ${categoriesWithoutCommission.join(
          ", "
        )} don't. Effective rate calculated.`;
      } else {
        const totalBillAmount = summary.value.payableAmount || summary.value.total;
        const effectiveCommissionRate = totalBillAmount > 0 ? totalCommissionAmount / totalBillAmount * 100 : 0;
        commission.value.commissionRate = parseFloat(effectiveCommissionRate.toFixed(2));
        commission.value.slider = parseFloat(effectiveCommissionRate.toFixed(2));
        commissionDetails.value.manualCommissionEnabled = false;
        commissionDetails.value.noCommissionMessage = "";
      }
      updateCommission();
    };
    const resetCommissionDetails = () => {
      commissionDetails.value = {
        hasPathologyCommission: false,
        hasRadiologyCommission: false,
        hasMedicineCommission: false,
        pathologyRate: 0,
        radiologyRate: 0,
        medicineRate: 0,
        manualCommissionEnabled: false,
        noCommissionMessage: ""
      };
    };
    const commissionBreakdown = computed(() => {
      if (!commission.value.referrer_id || items.value.length === 0)
        return [];
      const breakdown = [];
      const selectedReferrer = props.referrers.find(
        (referrer) => referrer.id == commission.value.referrer_id
      );
      if (!selectedReferrer)
        return breakdown;
      const categoryTotals = items.value.reduce((acc, item) => {
        const category = item.category.toLowerCase();
        if (!acc[category]) {
          acc[category] = {
            total: 0,
            items: []
          };
        }
        acc[category].total += item.netAmount;
        acc[category].items.push(item);
        return acc;
      }, {});
      Object.keys(categoryTotals).forEach((category) => {
        let rate = 0;
        let rateName = "";
        switch (category) {
          case "pathology":
            rate = selectedReferrer.pathology_commission || 0;
            rateName = "Pathology Commission";
            break;
          case "radiology":
            rate = selectedReferrer.radiology_commission || 0;
            rateName = "Radiology Commission";
            break;
          case "medicine":
            rate = selectedReferrer.pharmacy_commission || 0;
            rateName = "Pharmacy Commission";
            break;
        }
        const categoryTotal = categoryTotals[category].total;
        const commissionAmount = rate > 0 ? categoryTotal * rate / 100 : 0;
        breakdown.push({
          category: category.charAt(0).toUpperCase() + category.slice(1),
          rateName,
          rate,
          amount: categoryTotal,
          commission: commissionAmount,
          hasCommission: rate > 0
        });
      });
      return breakdown;
    });
    const updateSummary = () => {
      const total = items.value.reduce((sum, item) => sum + item.totalAmount, 0);
      summary.value.total = parseFloat(total.toFixed(2));
      let discountAmount = 0;
      if (summary.value.discountType === "percentage") {
        discountAmount = summary.value.total * summary.value.discount / 100;
      } else {
        discountAmount = parseFloat(summary.value.discount) || 0;
      }
      const totalDiscountAmount = discountAmount + parseFloat(summary.value.extraFlatDiscount || 0);
      const finalDiscountAmount = Math.min(totalDiscountAmount, summary.value.total);
      items.value = items.value.map((item) => {
        const itemProportion = total > 0 ? item.totalAmount / total : 0;
        const itemDistributedDiscount = finalDiscountAmount * itemProportion;
        return {
          ...item,
          discount: parseFloat(itemDistributedDiscount.toFixed(2)),
          netAmount: parseFloat((item.totalAmount - itemDistributedDiscount).toFixed(2))
        };
      });
      const newTotalAfterItemDiscounts = items.value.reduce(
        (sum, item) => sum + item.netAmount,
        0
      );
      summary.value.payableAmount = Math.max(
        0,
        parseFloat(newTotalAfterItemDiscounts.toFixed(2))
      );
      calculateChangeAndDue();
      commission.value.total = summary.value.payableAmount;
      if (commission.value.referrer_id && props.referrers) {
        const selectedReferrer = props.referrers.find(
          (referrer) => referrer.id == commission.value.referrer_id
        );
        if (selectedReferrer) {
          updateCommissionRate(selectedReferrer);
        }
      } else {
        updateCommission();
      }
    };
    watch(() => summary.value.extraFlatDiscount, () => {
      updateSummary();
    });
    const calculateChangeAndDue = () => {
      const payableAmount = summary.value.payableAmount;
      let paidAmount = parseFloat(summary.value.paidAmt) || 0;
      let receivingAmount = parseFloat(summary.value.receivingAmt) || 0;
      let takingAmount = parseFloat(summary.value.takingAmt) || 0;
      if (props.id && props.editData) {
        const originalPaidAmount = parseFloat(props.editData.paid_amt) || 0;
        if (receivingAmount > 0) {
          paidAmount = originalPaidAmount + receivingAmount;
          summary.value.paidAmt = Math.min(paidAmount, payableAmount);
        } else {
          paidAmount = originalPaidAmount;
        }
      }
      summary.value.returnAmt = Math.max(0, takingAmount - receivingAmount);
      if (paidAmount >= payableAmount) {
        summary.value.changeAmt = parseFloat((paidAmount - payableAmount).toFixed(2));
        summary.value.dueAmount = 0;
      } else {
        summary.value.changeAmt = 0;
        summary.value.dueAmount = parseFloat((payableAmount - paidAmount).toFixed(2));
      }
    };
    watch(
      () => summary.value.takingAmt,
      () => {
        calculateChangeAndDue();
      }
    );
    watch(
      () => summary.value.receivingAmt,
      () => {
        calculateChangeAndDue();
      }
    );
    const updateCommission = () => {
      const totalAmount = summary.value.payableAmount || commission.value.total;
      const commissionPercent = commission.value.commissionRate / 100;
      commission.value.physystAmt = parseFloat((totalAmount * commissionPercent).toFixed(2));
    };
    watch([() => summary.value.discount, () => summary.value.discountType], () => {
      updateSummary();
    });
    watch(
      () => commission.value.slider,
      () => {
        commission.value.commissionRate = commission.value.slider;
        updateCommission();
      }
    );
    watch(
      () => summary.value.paidAmt,
      () => {
        calculateChangeAndDue();
      }
    );
    watch(
      () => summary.value.receivingAmt,
      () => {
        const receivingAmount = parseFloat(summary.value.receivingAmt) || 0;
        parseFloat(summary.value.paidAmt) || 0;
        if (props.id && props.editData) {
          const originalPaidAmount = parseFloat(props.editData.paid_amt) || 0;
          if (receivingAmount > 0) {
            const newPaidAmount = originalPaidAmount + receivingAmount;
            summary.value.paidAmt = Math.min(newPaidAmount, summary.value.payableAmount);
            summary.value.paidAmt = parseFloat(summary.value.paidAmt.toFixed(2));
          } else {
            summary.value.paidAmt = originalPaidAmount;
          }
        } else {
          if (receivingAmount > 0) {
            summary.value.paidAmt = Math.min(receivingAmount, summary.value.payableAmount);
            summary.value.paidAmt = parseFloat(summary.value.paidAmt.toFixed(2));
          } else {
            summary.value.paidAmt = 0;
          }
        }
        calculateChangeAndDue();
      }
    );
    const initializeEditMode = () => {
      if (props.id && props.editData) {
        patientForm.value = {
          patient_id: props.editData.patient_id || "",
          doctor_id: props.editData.doctor_id || "",
          patientMobile: props.editData.patient_mobile || "",
          gender: props.editData.gender || "",
          cardType: props.editData.card_type || "Cash",
          payMode: props.editData.pay_mode || "Cash",
          cardNumber: props.editData.card_number || ""
        };
        if (props.editData.items) {
          items.value = props.editData.items.map((item) => ({
            id: item.id,
            name: item.name,
            category: item.category,
            unitPrice: parseFloat(item.unit_price),
            quantity: parseFloat(item.quantity),
            totalAmount: parseFloat(item.total_amount),
            discount: item.discount || 0,
            rugound: item.rugound || 0,
            netAmount: parseFloat(item.net_amount)
          }));
        }
        const paidAmount = parseFloat(props.editData.paid_amt || 0);
        const dueAmount = parseFloat(props.editData.due_amount || 0);
        const payableAmount = parseFloat(props.editData.payable_amount || 0);
        let receivingAmount = "";
        if (dueAmount > 0) {
          receivingAmount = "";
        } else if (paidAmount >= payableAmount) {
          receivingAmount = paidAmount.toFixed(2);
        } else {
          receivingAmount = paidAmount.toFixed(2);
        }
        summary.value = {
          total: parseFloat(props.editData.total || 0),
          discount: parseFloat(props.editData.discount || 0),
          discountType: props.editData.discount_type || "percentage",
          payableAmount,
          paidAmt: paidAmount,
          changeAmt: parseFloat(props.editData.change_amt || 0),
          dueAmount,
          receivingAmt: receivingAmount,
          deliveryDate: props.editData.delivery_date || "",
          remarks: props.editData.remarks || ""
        };
        commission.value = {
          total: parseFloat(props.editData.commission_total || 0),
          physystAmt: parseFloat(props.editData.physyst_amt || 0),
          slider: parseInt(props.editData.commission_slider || 0),
          referrer_id: props.editData.referrer_id || "",
          commissionRate: parseInt(props.editData.commission_slider || 0)
        };
      }
    };
    if (props.id && props.editData) {
      initializeEditMode();
    }
    let previousQuery = "";
    watch(searchQuery, (newQuery) => {
      if (newQuery !== previousQuery) {
        selectedIndex.value = -1;
      }
      previousQuery = newQuery;
    });
    const allAvailableItems = computed(() => {
      const tests = props.pathologyAndRadiologyTests.map((test) => ({
        id: test.id,
        name: test.test_name,
        category: test.category_type,
        unitPrice: test.amount,
        type: "test"
      }));
      const medicines = props.medicineInventories.filter((medicine) => medicine.status === "Active").map((medicine) => ({
        id: medicine.id,
        name: medicine.medicine_name,
        category: "Medicine",
        unitPrice: medicine.medicine_unit_selling_price,
        stock: medicine.medicine_quantity,
        type: "medicine"
      }));
      return [...tests, ...medicines];
    });
    const filteredItems = computed(() => {
      const query = searchQuery.value.toLowerCase();
      let itemsToFilter = [];
      if (itemForm.value.category) {
        if (itemForm.value.category.toLowerCase() === "medicine") {
          itemsToFilter = props.medicineInventories.filter((medicine) => medicine.status === "Active").map((medicine) => ({
            id: medicine.id,
            name: medicine.medicine_name,
            category: "Medicine",
            unitPrice: medicine.medicine_unit_selling_price,
            stock: medicine.medicine_quantity,
            type: "medicine"
          }));
        } else {
          itemsToFilter = props.pathologyAndRadiologyTests.filter(
            (test) => test.category_type.toLowerCase() === itemForm.value.category.toLowerCase()
          ).map((test) => ({
            id: test.id,
            name: test.test_name,
            category: test.category_type,
            unitPrice: test.amount,
            type: "test"
          }));
        }
      } else {
        itemsToFilter = allAvailableItems.value;
      }
      if (query) {
        return itemsToFilter.filter((item) => item.name.toLowerCase().includes(query));
      } else {
        return itemsToFilter;
      }
    });
    watch(selectedIndex, (newIndex) => {
      if (newIndex !== -1) {
        nextTick(() => {
          if (selectedItemRef.value) {
            selectedItemRef.value.scrollIntoView({ block: "nearest", behavior: "smooth" });
          }
        });
      }
    });
    return (_ctx, _push, _parent, _attrs) => {
      var _a, _b, _c, _d;
      _push(`<!--[-->`);
      _push(ssrRenderComponent(unref(Head), {
        title: _ctx.$page.props.pageTitle
      }, null, _parent));
      _push(`<div class="min-h-screen bg-gray-50 dark:bg-gray-900 overflow-y-auto" data-v-64dc99f2><div class="w-full p-2" data-v-64dc99f2><div class="bg-white rounded-lg shadow-lg dark:bg-slate-900 mb-4" data-v-64dc99f2><div class="mb-3" data-v-64dc99f2><div class="flex justify-between items-center bg-[#053855] text-white px-3 py-2 text-xs font-semibold rounded-t-lg" data-v-64dc99f2><div class="flex-1" data-v-64dc99f2>ITEM DETAILS</div><div class="flex items-center space-x-2" data-v-64dc99f2><button class="flex items-center px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-md text-xs transition-colors duration-200 shadow-sm" title="Billing List" data-v-64dc99f2><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" data-v-64dc99f2><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" data-v-64dc99f2></path></svg> Billing List </button><button class="flex items-center justify-center w-6 h-6 bg-red-500 hover:bg-red-600 text-white rounded-full text-xs transition-colors duration-200" title="Cancel" data-v-64dc99f2><svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-v-64dc99f2><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" data-v-64dc99f2></path></svg></button></div></div><div class="border border-gray-300 border-t-0 p-3 bg-gray-50 dark:bg-slate-800 dark:border-gray-600" data-v-64dc99f2><div class="flex items-center space-x-4 text-xs mb-3" data-v-64dc99f2><span class="font-medium text-gray-700 dark:text-gray-300" data-v-64dc99f2><strong data-v-64dc99f2>UNIT:</strong> Toamedm Ltd.</span><span class="font-medium text-gray-700 dark:text-gray-300" data-v-64dc99f2><strong data-v-64dc99f2>Counter:</strong> ${ssrInterpolate(((_b = (_a = __props.authInfo) == null ? void 0 : _a.department) == null ? void 0 : _b.name) ?? "")}</span><span class="font-medium text-gray-700 dark:text-gray-300" data-v-64dc99f2><strong data-v-64dc99f2>Sales Person:</strong> ${ssrInterpolate(((_d = (_c = __props.authInfo) == null ? void 0 : _c.admin) == null ? void 0 : _d.name) ?? "")}</span></div><div class="grid grid-cols-1 lg:grid-cols-12 gap-2 items-end" data-v-64dc99f2><div class="lg:col-span-2" data-v-64dc99f2>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "category",
        value: "Category",
        class: "text-xs mb-1"
      }, null, _parent));
      _push(`<select id="category" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300" data-v-64dc99f2><option value="" disabled data-v-64dc99f2${ssrIncludeBooleanAttr(Array.isArray(itemForm.value.category) ? ssrLooseContain(itemForm.value.category, "") : ssrLooseEqual(itemForm.value.category, "")) ? " selected" : ""}>Select Category</option><option value="Pathology" data-v-64dc99f2${ssrIncludeBooleanAttr(Array.isArray(itemForm.value.category) ? ssrLooseContain(itemForm.value.category, "Pathology") : ssrLooseEqual(itemForm.value.category, "Pathology")) ? " selected" : ""}>Pathology</option><option value="Radiology" data-v-64dc99f2${ssrIncludeBooleanAttr(Array.isArray(itemForm.value.category) ? ssrLooseContain(itemForm.value.category, "Radiology") : ssrLooseEqual(itemForm.value.category, "Radiology")) ? " selected" : ""}>Radiology</option><option value="Medicine" data-v-64dc99f2${ssrIncludeBooleanAttr(Array.isArray(itemForm.value.category) ? ssrLooseContain(itemForm.value.category, "Medicine") : ssrLooseEqual(itemForm.value.category, "Medicine")) ? " selected" : ""}>Medicine</option></select></div><div class="lg:col-span-5 relative" data-v-64dc99f2>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "itemName",
        value: "Item Name",
        class: "text-xs mb-1"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", searchQuery.value)} type="text" id="itemName" autocomplete="off" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300" placeholder="Search for item..." data-v-64dc99f2>`);
      if (filteredItems.value.length > 0 && searchQuery.value.length > 0) {
        _push(`<ul class="absolute z-10 w-full mt-1 max-h-48 overflow-y-auto bg-white border border-gray-300 rounded-md shadow-lg dark:bg-slate-800 dark:border-gray-600 dark:text-gray-300" data-v-64dc99f2><!--[-->`);
        ssrRenderList(filteredItems.value, (item, index) => {
          _push(`<li class="${ssrRenderClass([{ "bg-blue-100 dark:bg-blue-800": index === selectedIndex.value }, "p-2 text-xs cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-700"])}" data-v-64dc99f2>${ssrInterpolate(item.name)} `);
          if (item.category === "Medicine") {
            _push(`<span class="text-gray-500 dark:text-gray-400" data-v-64dc99f2> - Stock: ${ssrInterpolate(item.stock)}</span>`);
          } else {
            _push(`<!---->`);
          }
          _push(`</li>`);
        });
        _push(`<!--]--></ul>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</div><div class="lg:col-span-1" data-v-64dc99f2>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "unitPrice",
        value: "Price",
        class: "text-xs mb-1"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", itemForm.value.unitPrice)} type="number" id="unitPrice" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300" readonly data-v-64dc99f2></div><div class="lg:col-span-1" data-v-64dc99f2>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "quantity",
        value: "Qty",
        class: "text-xs mb-1"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", itemForm.value.quantity)} type="number" step="any" id="quantity" min="0" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300" data-v-64dc99f2></div><div class="lg:col-span-2" data-v-64dc99f2>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "totalAmount",
        value: "Amount",
        class: "text-xs mb-1"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", itemForm.value.totalAmount)} type="number" id="totalAmount" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300" readonly data-v-64dc99f2></div><div class="lg:col-span-1 flex items-center justify-center" data-v-64dc99f2><button type="button" class="mt-4 px-3 py-1.5 bg-green-500 hover:bg-green-600 text-white rounded-md text-xs transition-colors duration-200 shadow-sm" title="Add Item" data-v-64dc99f2><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-v-64dc99f2><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" data-v-64dc99f2></path></svg></button></div></div></div></div>`);
      if (items.value.length > 0) {
        _push(`<div class="max-h-custom overflow-y-auto mb-4 bg-white dark:bg-slate-900 border-b border-gray-300 dark:border-gray-600" data-v-64dc99f2><div class="overflow-x-auto" data-v-64dc99f2><table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600 text-xs" data-v-64dc99f2><thead class="bg-gray-50 dark:bg-slate-800 sticky top-0" data-v-64dc99f2><tr data-v-64dc99f2><th scope="col" class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 tracking-wider" data-v-64dc99f2> # </th><th scope="col" class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 tracking-wider" data-v-64dc99f2> Item Name </th><th scope="col" class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 tracking-wider" data-v-64dc99f2> Category </th><th scope="col" class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 tracking-wider" data-v-64dc99f2> Price </th><th scope="col" class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 tracking-wider" data-v-64dc99f2> Qty </th><th scope="col" class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 tracking-wider" data-v-64dc99f2> Amount </th><th scope="col" class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 tracking-wider" data-v-64dc99f2> Discount </th><th scope="col" class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 tracking-wider" data-v-64dc99f2> Net Amount </th><th scope="col" class="px-3 py-2 text-center font-medium text-gray-500 dark:text-gray-400 tracking-wider" data-v-64dc99f2> Action </th></tr></thead><tbody class="bg-white dark:bg-slate-900 divide-y divide-gray-200 dark:divide-gray-600" data-v-64dc99f2><!--[-->`);
        ssrRenderList(items.value, (item, index) => {
          _push(`<tr data-v-64dc99f2><td class="px-3 py-2 whitespace-nowrap" data-v-64dc99f2>${ssrInterpolate(index + 1)}</td><td class="px-3 py-2 whitespace-nowrap" data-v-64dc99f2>${ssrInterpolate(item.name)}</td><td class="px-3 py-2 whitespace-nowrap" data-v-64dc99f2>${ssrInterpolate(item.category)}</td><td class="px-3 py-2 whitespace-nowrap" data-v-64dc99f2>${ssrInterpolate(item.unitPrice)}</td><td class="px-3 py-2 whitespace-nowrap" data-v-64dc99f2>${ssrInterpolate(item.quantity)}</td><td class="px-3 py-2 whitespace-nowrap" data-v-64dc99f2>${ssrInterpolate(item.totalAmount)}</td><td class="px-3 py-2 whitespace-nowrap" data-v-64dc99f2>${ssrInterpolate(item.discount)}</td><td class="px-3 py-2 whitespace-nowrap" data-v-64dc99f2>${ssrInterpolate(item.netAmount)}</td><td class="px-3 py-2 whitespace-nowrap text-center" data-v-64dc99f2><button class="text-red-600 hover:text-red-900 transition-colors duration-200" title="Remove Item" data-v-64dc99f2><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-v-64dc99f2><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" data-v-64dc99f2></path></svg></button></td></tr>`);
        });
        _push(`<!--]--></tbody></table></div></div>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</div><div class="grid grid-cols-1 lg:grid-cols-3 gap-4" data-v-64dc99f2><div class="lg:col-span-2" data-v-64dc99f2><div class="bg-white rounded-lg shadow-lg dark:bg-slate-900 mb-4" data-v-64dc99f2><div class="mb-3" data-v-64dc99f2><div class="flex justify-between items-center bg-[#053855] text-white px-3 py-2 text-xs font-semibold rounded-t-lg" data-v-64dc99f2> PATIENT DETAILS </div><div class="border border-gray-300 border-t-0 p-3 bg-gray-50 dark:bg-slate-800 dark:border-gray-600" data-v-64dc99f2><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-2 text-xs" data-v-64dc99f2><div class="relative" data-v-64dc99f2>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "patientSearch",
        value: "Patient Name",
        class: "mb-1"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", patientSearchQuery.value)} type="text" id="patientSearch" autocomplete="off" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300" placeholder="Search or create new patient..." data-v-64dc99f2>`);
      if (showPatientDropdown.value && filteredPatients.value.length > 0) {
        _push(`<ul class="absolute z-10 w-full mt-1 max-h-48 overflow-y-auto bg-white border border-gray-300 rounded-md shadow-lg dark:bg-slate-800 dark:border-gray-600 dark:text-gray-300" data-v-64dc99f2><!--[-->`);
        ssrRenderList(filteredPatients.value, (patient, index) => {
          _push(`<li class="${ssrRenderClass([{ "bg-blue-100 dark:bg-blue-800": index === patientSelectedIndex.value }, "p-2 cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-700"])}" data-v-64dc99f2>${ssrInterpolate(patient.name)} (${ssrInterpolate(patient.phone)}) </li>`);
        });
        _push(`<!--]--></ul>`);
      } else {
        _push(`<!---->`);
      }
      if (patientSearchQuery.value && filteredPatients.value.length === 0) {
        _push(`<div class="mt-2 text-center" data-v-64dc99f2><button type="button" class="px-2 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded-md text-xs transition-colors duration-200" data-v-64dc99f2> Create New Patient </button></div>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</div><div data-v-64dc99f2>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "doctor",
        value: "Doctor",
        class: "mb-1"
      }, null, _parent));
      _push(`<select id="doctor" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300" data-v-64dc99f2><option value="" disabled data-v-64dc99f2${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.doctor_id) ? ssrLooseContain(patientForm.value.doctor_id, "") : ssrLooseEqual(patientForm.value.doctor_id, "")) ? " selected" : ""}>Select Doctor</option><!--[-->`);
      ssrRenderList(__props.doctors, (doctor) => {
        _push(`<option${ssrRenderAttr("value", doctor.id)} data-v-64dc99f2${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.doctor_id) ? ssrLooseContain(patientForm.value.doctor_id, doctor.id) : ssrLooseEqual(patientForm.value.doctor_id, doctor.id)) ? " selected" : ""}>${ssrInterpolate(doctor.name)}</option>`);
      });
      _push(`<!--]--></select></div><div data-v-64dc99f2>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "patientMobile",
        value: "Patient Phone",
        class: "mb-1"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", patientForm.value.patientMobile)} type="text" id="patientMobile" autocomplete="off" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300"${ssrIncludeBooleanAttr(patientForm.value.patient_id ? true : false) ? " readonly" : ""} data-v-64dc99f2></div><div data-v-64dc99f2>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "gender",
        value: "Gender",
        class: "mb-1"
      }, null, _parent));
      _push(`<select id="gender" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300"${ssrIncludeBooleanAttr(patientForm.value.patient_id ? true : false) ? " readonly" : ""} data-v-64dc99f2><option value="" disabled data-v-64dc99f2${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.gender) ? ssrLooseContain(patientForm.value.gender, "") : ssrLooseEqual(patientForm.value.gender, "")) ? " selected" : ""}>Select Gender</option><option value="Male" data-v-64dc99f2${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.gender) ? ssrLooseContain(patientForm.value.gender, "Male") : ssrLooseEqual(patientForm.value.gender, "Male")) ? " selected" : ""}>Male</option><option value="Female" data-v-64dc99f2${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.gender) ? ssrLooseContain(patientForm.value.gender, "Female") : ssrLooseEqual(patientForm.value.gender, "Female")) ? " selected" : ""}>Female</option><option value="Other" data-v-64dc99f2${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.gender) ? ssrLooseContain(patientForm.value.gender, "Other") : ssrLooseEqual(patientForm.value.gender, "Other")) ? " selected" : ""}>Other</option></select></div><div data-v-64dc99f2>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "payMode",
        value: "Pay Mode",
        class: "mb-1"
      }, null, _parent));
      _push(`<select id="payMode" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300" data-v-64dc99f2><option value="Cash" data-v-64dc99f2${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.payMode) ? ssrLooseContain(patientForm.value.payMode, "Cash") : ssrLooseEqual(patientForm.value.payMode, "Cash")) ? " selected" : ""}>Cash</option><option value="Card" data-v-64dc99f2${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.payMode) ? ssrLooseContain(patientForm.value.payMode, "Card") : ssrLooseEqual(patientForm.value.payMode, "Card")) ? " selected" : ""}>Card</option><option value="Mobile Banking" data-v-64dc99f2${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.payMode) ? ssrLooseContain(patientForm.value.payMode, "Mobile Banking") : ssrLooseEqual(patientForm.value.payMode, "Mobile Banking")) ? " selected" : ""}>Mobile Banking</option></select></div>`);
      if (patientForm.value.payMode !== "Cash") {
        _push(`<div data-v-64dc99f2>`);
        _push(ssrRenderComponent(_sfc_main$1, {
          for: "cardNumber",
          value: "Card / Mobile No.",
          class: "mb-1"
        }, null, _parent));
        _push(`<input${ssrRenderAttr("value", patientForm.value.cardNumber)} type="text" id="cardNumber" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300" data-v-64dc99f2></div>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</div></div></div><div class="mb-3" data-v-64dc99f2><div class="flex justify-between items-center bg-[#053855] text-white px-3 py-2 text-xs font-semibold" data-v-64dc99f2> COMMISSION DETAILS </div><div class="border border-gray-300 border-t-0 p-3 bg-gray-50 dark:bg-slate-800 dark:border-gray-600" data-v-64dc99f2><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-2 text-xs" data-v-64dc99f2><div data-v-64dc99f2>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "referrer",
        value: "Referrer",
        class: "mb-1"
      }, null, _parent));
      _push(`<select id="referrer" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300" data-v-64dc99f2><option value="" data-v-64dc99f2${ssrIncludeBooleanAttr(Array.isArray(commission.value.referrer_id) ? ssrLooseContain(commission.value.referrer_id, "") : ssrLooseEqual(commission.value.referrer_id, "")) ? " selected" : ""}>No Referrer</option><!--[-->`);
      ssrRenderList(__props.referrers, (referrer) => {
        _push(`<option${ssrRenderAttr("value", referrer.id)} data-v-64dc99f2${ssrIncludeBooleanAttr(Array.isArray(commission.value.referrer_id) ? ssrLooseContain(commission.value.referrer_id, referrer.id) : ssrLooseEqual(commission.value.referrer_id, referrer.id)) ? " selected" : ""}>${ssrInterpolate(referrer.name)}</option>`);
      });
      _push(`<!--]--></select></div><div data-v-64dc99f2>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "physyst_amt",
        value: "Physyst Amount",
        class: "mb-1"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", commission.value.physystAmt)} type="text" id="physyst_amt" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 cursor-not-allowed" readonly data-v-64dc99f2></div><div data-v-64dc99f2>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "commissionRate",
        value: "Commission Rate (%)",
        class: "mb-1"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", commission.value.commissionRate)} type="number" id="commissionRate" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300"${ssrIncludeBooleanAttr(!commissionDetails.value.manualCommissionEnabled) ? " readonly" : ""} data-v-64dc99f2></div><div class="col-span-1 md:col-span-2 lg:col-span-4" data-v-64dc99f2>`);
      if (commissionDetails.value.noCommissionMessage) {
        _push(`<p class="text-red-500 text-xs mt-2" data-v-64dc99f2>${ssrInterpolate(commissionDetails.value.noCommissionMessage)}</p>`);
      } else {
        _push(`<!---->`);
      }
      if (commissionBreakdown.value.length > 0) {
        _push(`<p class="mt-2 text-xs text-gray-700 dark:text-gray-300" data-v-64dc99f2><strong data-v-64dc99f2>Breakdown:</strong><!--[-->`);
        ssrRenderList(commissionBreakdown.value, (item, index) => {
          _push(`<span class="ml-2" data-v-64dc99f2>${ssrInterpolate(item.category)}: ${ssrInterpolate(item.commission.toFixed(2))} (${ssrInterpolate(item.rate)}%) </span>`);
        });
        _push(`<!--]--></p>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</div></div></div></div></div></div><div class="lg:col-span-1" data-v-64dc99f2><div class="bg-white rounded-lg shadow-lg dark:bg-slate-900 mb-4" data-v-64dc99f2><div class="mb-3" data-v-64dc99f2><div class="flex justify-between items-center bg-[#053855] text-white px-3 py-2 text-xs font-semibold rounded-t-lg" data-v-64dc99f2> TOTAL SUMMARY </div><div class="border border-gray-300 border-t-0 p-3 bg-gray-50 dark:bg-slate-800 dark:border-gray-600" data-v-64dc99f2><div class="grid grid-cols-1 gap-2 text-xs" data-v-64dc99f2><div class="flex justify-between items-center py-1" data-v-64dc99f2>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "total",
        value: "Total Amount"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", summary.value.total)} type="text" id="total" class="w-2/3 px-2 py-1.5 border border-gray-300 rounded text-xs text-right focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 cursor-not-allowed" readonly data-v-64dc99f2></div><div class="flex items-center gap-2" data-v-64dc99f2>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "discount",
        value: "Discount",
        class: "flex-1"
      }, null, _parent));
      _push(`<div class="flex-1" data-v-64dc99f2><select id="discountType" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300" data-v-64dc99f2><option value="percentage" data-v-64dc99f2${ssrIncludeBooleanAttr(Array.isArray(summary.value.discountType) ? ssrLooseContain(summary.value.discountType, "percentage") : ssrLooseEqual(summary.value.discountType, "percentage")) ? " selected" : ""}>%</option><option value="flat" data-v-64dc99f2${ssrIncludeBooleanAttr(Array.isArray(summary.value.discountType) ? ssrLooseContain(summary.value.discountType, "flat") : ssrLooseEqual(summary.value.discountType, "flat")) ? " selected" : ""}>Flat</option></select></div><div class="flex-1" data-v-64dc99f2><input${ssrRenderAttr("value", summary.value.discount)} type="number" min="0" id="discount" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs text-right focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300" data-v-64dc99f2></div></div><div class="flex justify-between items-center py-1" data-v-64dc99f2>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "extraFlatDiscount",
        value: "Extra Flat Discount"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", summary.value.extraFlatDiscount)} type="number" min="0" id="extraFlatDiscount" class="w-2/3 px-2 py-1.5 border border-gray-300 rounded text-xs text-right focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300" data-v-64dc99f2></div><div class="flex justify-between items-center py-1 font-semibold text-base" data-v-64dc99f2>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "payableAmount",
        value: "Payable Amount"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", summary.value.payableAmount)} type="text" id="payableAmount" class="w-2/3 px-2 py-1.5 border border-gray-300 rounded text-xs text-right focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 cursor-not-allowed" readonly data-v-64dc99f2></div><div class="flex justify-between items-center py-1" data-v-64dc99f2>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "takingAmt",
        value: "Taking Amount"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", summary.value.takingAmt)} type="number" min="0" id="takingAmt" class="w-2/3 px-2 py-1.5 border border-gray-300 rounded text-xs text-right focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300" data-v-64dc99f2></div><div class="flex justify-between items-center py-1" data-v-64dc99f2>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "receivingAmt",
        value: "Receiving Amount"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", summary.value.receivingAmt)} type="number" min="0" id="receivingAmt" class="w-2/3 px-2 py-1.5 border border-gray-300 rounded text-xs text-right focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300"${ssrIncludeBooleanAttr(props.id && props.editData && summary.value.dueAmount === 0) ? " readonly" : ""} data-v-64dc99f2></div><div class="flex justify-between items-center py-1" data-v-64dc99f2>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "changeAmt",
        value: "Change Amount"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", summary.value.changeAmt)} type="text" id="changeAmt" class="w-2/3 px-2 py-1.5 border border-gray-300 rounded text-xs text-right focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 cursor-not-allowed" readonly data-v-64dc99f2></div><div class="flex justify-between items-center py-1" data-v-64dc99f2>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "dueAmount",
        value: "Due Amount"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", summary.value.dueAmount)} type="text" id="dueAmount" class="w-2/3 px-2 py-1.5 border border-gray-300 rounded text-xs text-right focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 cursor-not-allowed" readonly data-v-64dc99f2></div><div class="flex justify-between items-center py-1" data-v-64dc99f2>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "returnAmt",
        value: "Return Amount"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", summary.value.returnAmt)} type="text" id="returnAmt" class="w-2/3 px-2 py-1.5 border border-gray-300 rounded text-xs text-right focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 cursor-not-allowed" readonly data-v-64dc99f2></div><div class="flex justify-between items-center py-1" data-v-64dc99f2>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "deliveryDate",
        value: "Delivery Date"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", summary.value.deliveryDate)} type="date" id="deliveryDate" class="w-2/3 px-2 py-1.5 border border-gray-300 rounded text-xs text-right focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300" data-v-64dc99f2></div><div class="flex flex-col py-1" data-v-64dc99f2>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "remarks",
        value: "Remarks"
      }, null, _parent));
      _push(`<textarea id="remarks" rows="2" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300" data-v-64dc99f2>${ssrInterpolate(summary.value.remarks)}</textarea></div><div class="mt-4" data-v-64dc99f2><button type="button" class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-md transition-colors duration-200" data-v-64dc99f2> Save Bill </button></div></div></div></div></div></div></div></div>`);
      _push(ssrRenderComponent(PatientModal, {
        show: isPatientModalOpen.value,
        onClose: closePatientModal,
        onPatientCreated: handlePatientCreated
      }, null, _parent));
      _push(`</div><!--]-->`);
    };
  }
};
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/Billing/testBillingSearching.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const testBillingSearching = /* @__PURE__ */ _export_sfc(_sfc_main, [["__scopeId", "data-v-64dc99f2"]]);
export {
  testBillingSearching as default
};
