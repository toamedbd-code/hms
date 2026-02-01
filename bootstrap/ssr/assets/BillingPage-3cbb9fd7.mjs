import { ref, watch, nextTick, computed, onMounted, unref, useSSRContext } from "vue";
import { ssrRenderComponent, ssrRenderAttr, ssrInterpolate, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderList, ssrRenderClass } from "vue/server-renderer";
import { Head, router } from "@inertiajs/vue3";
import { _ as _sfc_main$1 } from "./InputLabel-70ca52d1.mjs";
import { P as PatientModal } from "./PatientModal-85d06e3d.mjs";
import "./responseMessage-d505224b.mjs";
import { debounce } from "lodash";
import { subYears, subMonths, subDays, format, parse, isValid, differenceInYears, differenceInMonths, differenceInDays } from "date-fns";
import { _ as _export_sfc } from "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "./InputError-6aeb8d97.mjs";
import "./PrimaryButton-b82fb16e.mjs";
import "toastr";
import "sweetalert2";
const BillingPage_vue_vue_type_style_index_0_scoped_b27ed2fa_lang = "";
const _sfc_main = {
  __name: "BillingPage",
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
    authInfo: Object,
    billing: Object,
    billingDoctors: Array
  },
  setup(__props) {
    const props = __props;
    const isPatientModalOpen = ref(false);
    const patientsList = ref([...props.patients]);
    ref(null);
    const doctorSearchRef = ref(null);
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
    const ageYears = ref("");
    const ageMonths = ref("");
    const ageDays = ref("");
    ref(null);
    ref(null);
    ref(null);
    ref(null);
    const updatingFrom = ref(null);
    const isEditMode = ref(false);
    const doctorSearchQuery = ref("");
    const doctorSelectedIndex = ref(-1);
    const showDoctorDropdown = ref(false);
    const filteredDoctors = ref([]);
    const isDoctorLoading = ref(false);
    const isNewPatientFlag = ref(false);
    const patientSearchQuery = ref("");
    const patientSelectedIndex = ref(-1);
    const showPatientDropdown = ref(false);
    const filteredPatients = ref([]);
    const isNewPatient = ref(false);
    const newPatientForm = ref({
      name: "",
      phone: "",
      gender: "",
      dob: ""
    });
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
      patientMobile: "",
      gender: "",
      dob: "",
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
      deliveryTime: "",
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
    ref(null);
    ref(null);
    const selectedItemRef = ref(null);
    const searchQuery = ref("");
    const selectedIndex = ref(-1);
    const calculateAgeFromDOB = (dob) => {
      if (!dob) {
        ageYears.value = "";
        ageMonths.value = "";
        ageDays.value = "";
        return;
      }
      const birthDate = parse(dob, "yyyy-MM-dd", /* @__PURE__ */ new Date());
      if (!isValid(birthDate)) {
        ageYears.value = "";
        ageMonths.value = "";
        ageDays.value = "";
        return;
      }
      const today = /* @__PURE__ */ new Date();
      let years = differenceInYears(today, birthDate);
      let remainingDate = subYears(today, years);
      let months = differenceInMonths(remainingDate, birthDate);
      remainingDate = subMonths(remainingDate, months);
      let days = differenceInDays(remainingDate, birthDate);
      ageYears.value = years > 0 ? years.toString() : "";
      ageMonths.value = months > 0 ? months.toString() : "";
      ageDays.value = days > 0 ? days.toString() : "";
    };
    watch(patientSearchQuery, debounce((newQuery) => {
      if (newQuery.trim() == "") {
        filteredPatients.value = [];
        showPatientDropdown.value = false;
        return;
      }
      if (isEditMode.value && !showPatientDropdown.value) {
        isEditMode.value = false;
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
    watch(() => patientForm.value.dob, (newDob) => {
      if (updatingFrom.value === "age")
        return;
      updatingFrom.value = "dob";
      calculateAgeFromDOB(newDob);
      updatingFrom.value = null;
    });
    watch(patientSearchQuery, (newQuery) => {
      if (newQuery.trim() === "" && isNewPatient.value) {
        isNewPatient.value = false;
        newPatientForm.value = {
          name: "",
          phone: "",
          gender: "",
          dob: ""
        };
      }
    });
    watch([ageYears, ageMonths, ageDays], ([years, months, days]) => {
      if (updatingFrom.value === "dob")
        return;
      updatingFrom.value = "age";
      const yearsNum = parseInt(years) || 0;
      const monthsNum = parseInt(months) || 0;
      const daysNum = parseInt(days) || 0;
      if (yearsNum === 0 && monthsNum === 0 && daysNum === 0) {
        patientForm.value.dob = "";
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
        patientForm.value.dob = "";
      } else {
        patientForm.value.dob = format(dobDate, "yyyy-MM-dd");
      }
      updatingFrom.value = null;
    }, { deep: true });
    const handlePatientCreated = (newPatient) => {
      console.log("Patient created:", newPatient);
      patientsList.value.push(newPatient);
      patientForm.value.patient_id = newPatient.id;
      patientForm.value.patientMobile = newPatient.phone;
      patientForm.value.gender = newPatient.gender;
      patientForm.value.dob = newPatient.dob || "";
      patientSearchQuery.value = newPatient.name;
      calculateAgeFromDOB(newPatient.dob);
      isNewPatient.value = false;
      newPatientForm.value = {
        name: "",
        phone: "",
        gender: "",
        dob: ""
      };
      nextTick(() => {
        setTimeout(() => {
          if (doctorSearchRef.value && typeof doctorSearchRef.value.focus === "function") {
            doctorSearchRef.value.focus();
            doctorSearchRef.value.select();
          }
        }, 100);
      });
      router.reload({
        only: ["patients"],
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
          patientsList.value = [...page.props.patients];
        }
      });
    };
    watch(() => newPatientForm.value, (newPatientData) => {
      if (isNewPatient.value) {
        if (newPatientData.phone) {
          patientForm.value.patientMobile = newPatientData.phone;
        }
        if (newPatientData.gender) {
          patientForm.value.gender = newPatientData.gender;
        }
        if (newPatientData.name && newPatientData.name !== patientSearchQuery.value) {
          patientSearchQuery.value = newPatientData.name;
        }
        if (newPatientData.dob) {
          patientForm.value.dob = newPatientData.dob;
        }
      }
    }, { deep: true });
    watch(() => newPatientForm.value.phone, (newPhone) => {
      console.log("newPatientForm.phone changed to:", newPhone);
      if (isNewPatient.value) {
        patientForm.value.patientMobile = newPhone;
      }
    });
    watch(() => newPatientForm.value.gender, (newGender) => {
      console.log("newPatientForm.gender changed to:", newGender);
      if (isNewPatient.value) {
        patientForm.value.gender = newGender;
      }
    });
    watch(() => newPatientForm.value.dob, (newDob) => {
      console.log("newPatientForm.dob changed to:", newDob);
      if (isNewPatient.value) {
        patientForm.value.dob = newDob;
        calculateAgeFromDOB(newDob);
      }
    });
    watch(() => patientForm.value.patientMobile, (newPhone) => {
      if (isNewPatient.value) {
        newPatientForm.value.phone = newPhone;
      }
    });
    watch(() => patientForm.value.gender, (newGender) => {
      if (isNewPatient.value) {
        newPatientForm.value.gender = newGender;
      }
    });
    watch(() => patientForm.value.dob, (newDob) => {
      if (isNewPatient.value && newDob !== newPatientForm.value.dob) {
        newPatientForm.value.dob = newDob;
        calculateAgeFromDOB(newDob);
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
      isNewPatientFlag.value = newValue;
      if (!newValue) {
        console.log("Resetting new patient form...");
        newPatientForm.value = {
          name: "",
          phone: "",
          gender: "",
          dob: ""
        };
        if (!patientForm.value.patient_id) {
          patientForm.value.patientMobile = "";
          patientForm.value.gender = "";
          patientForm.value.dob = "";
          patientSearchQuery.value = "";
          ageYears.value = "";
          ageMonths.value = "";
          ageDays.value = "";
        }
      } else {
        console.log("Entering new patient mode...");
        patientForm.value.patient_id = null;
      }
    });
    watch(() => itemForm.value.quantity, (newQuantity) => {
      if (newQuantity && itemForm.value.unitPrice) {
        itemForm.value.totalAmount = (newQuantity * itemForm.value.unitPrice).toFixed(2);
      }
    });
    watch([() => itemForm.value.quantity, () => itemForm.value.unitPrice], () => {
      itemForm.value.totalAmount = (itemForm.value.quantity * itemForm.value.unitPrice).toFixed(2);
    });
    watch(() => patientForm.value.patient_id, (newPatientId) => {
      if (newPatientId && props.patients) {
        const selectedPatient = props.patients.find(
          (patient) => patient.id == newPatientId
        );
        if (selectedPatient) {
          patientForm.value.patientMobile = selectedPatient.phone || selectedPatient.mobile || "";
          patientForm.value.gender = selectedPatient.gender || "";
          patientForm.value.dob = selectedPatient.dob || "";
          patientSearchQuery.value = selectedPatient.name || "";
          calculateAgeFromDOB(selectedPatient.dob);
        }
      }
    });
    watch(() => commission.value.referrer_id, (newReferrerId) => {
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
    });
    watch(() => commission.value.slider, (newValue) => {
      if (commissionDetails.value.manualCommissionEnabled) {
        commission.value.commissionRate = parseFloat(newValue);
      }
      updateCommission();
    });
    watch(() => summary.value.extraFlatDiscount, () => {
      updateSummary();
    });
    watch(() => summary.value.takingAmt, () => {
      calculateChangeAndDue();
    });
    watch(() => summary.value.receivingAmt, () => {
      calculateChangeAndDue();
    });
    watch([() => summary.value.discount, () => summary.value.discountType], () => {
      updateSummary();
    });
    watch(() => commission.value.slider, () => {
      commission.value.commissionRate = commission.value.slider;
      updateCommission();
    });
    watch(() => summary.value.paidAmt, () => {
      calculateChangeAndDue();
    });
    watch(() => summary.value.receivingAmt, () => {
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
    let previousQuery = "";
    watch(searchQuery, (newQuery) => {
      if (newQuery !== previousQuery) {
        selectedIndex.value = -1;
      }
      previousQuery = newQuery;
    });
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
    const updateCommission = () => {
      const totalAmount = summary.value.payableAmount || commission.value.total;
      const commissionPercent = commission.value.commissionRate / 100;
      commission.value.physystAmt = parseFloat((totalAmount * commissionPercent).toFixed(2));
    };
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
        return {
          ...item,
          discount: 0,
          netAmount: item.totalAmount
        };
      });
      summary.value.payableAmount = Math.max(
        0,
        parseFloat((summary.value.total - finalDiscountAmount).toFixed(2))
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
    const initializeEditMode = () => {
      if (props.id && props.editData) {
        isEditMode.value = true;
        patientForm.value = {
          patient_id: props.editData.patient_id || "",
          doctor_id: props.editData.doctor_id || "",
          patientMobile: props.editData.patient_mobile || "",
          gender: props.editData.gender || "",
          dob: props.editData.dob || "",
          cardType: props.editData.card_type || "Cash",
          payMode: props.editData.pay_mode || "Cash",
          cardNumber: props.editData.card_number || ""
        };
        if (props.editData.patient_id && props.patients) {
          const selectedPatient = props.patients.find(
            (patient) => patient.id == props.editData.patient_id
          );
          if (selectedPatient) {
            patientSearchQuery.value = selectedPatient.name || "";
          }
        }
        calculateAgeFromDOB(props.editData.dob);
        if (props.editData.doctor_id) {
          const doctorId = props.editData.doctor_id;
          let doctorName = "";
          if (props.doctors && props.doctors.length > 0) {
            const adminDoctor = props.doctors.find((d) => d.id == doctorId);
            if (adminDoctor) {
              doctorName = `${adminDoctor.first_name} ${adminDoctor.last_name}`;
              patientForm.value.doctor_id = `admin_${doctorId}`;
            }
          }
          if (!doctorName && props.billingDoctors && props.billingDoctors.length > 0) {
            const billingDoctor = props.billingDoctors.find((d) => d.id == doctorId);
            if (billingDoctor) {
              doctorName = billingDoctor.name;
              patientForm.value.doctor_id = `billing_${doctorId}`;
            }
          }
          if (!doctorName && props.billing && props.billing.doctor_name) {
            doctorName = props.billing.doctor_name;
          }
          if (!doctorName && props.editData.doctor_name) {
            doctorName = props.editData.doctor_name;
          }
          if (doctorName) {
            doctorSearchQuery.value = doctorName;
            console.log("Setting doctor name in edit mode:", doctorName);
          }
        }
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
          extraFlatDiscount: parseFloat(props.editData.extra_flat_discount || 0),
          payableAmount,
          paidAmt: paidAmount,
          changeAmt: parseFloat(props.editData.change_amt || 0),
          dueAmount,
          receivingAmt: receivingAmount,
          takingAmt: parseFloat(props.editData.taking_amt || 0),
          returnAmt: parseFloat(props.editData.return_amt || 0),
          deliveryDate: props.editData.delivery_date || "",
          deliveryTime: props.editData.delivery_time || "",
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
    onMounted(() => {
      if (props.id && props.editData) {
        initializeEditMode();
      }
    });
    watch(doctorSearchQuery, debounce((newQuery) => {
      if (newQuery.trim() == "") {
        filteredDoctors.value = [];
        showDoctorDropdown.value = false;
        return;
      }
      const hasExactMatch = filteredDoctors.value.some(
        (doctor) => doctor.name.toLowerCase() === newQuery.trim().toLowerCase()
      );
      if (hasExactMatch) {
        showDoctorDropdown.value = false;
        return;
      }
      searchDoctors(newQuery);
    }, 300));
    const searchDoctors = async (query) => {
      if (query.length < 2)
        return;
      isDoctorLoading.value = true;
      try {
        const response = await axios.get(route("backend.billing.doctors.search"), {
          params: { search: query }
        });
        filteredDoctors.value = response.data;
        const hasExactMatch = filteredDoctors.value.some(
          (doctor) => doctor.name.toLowerCase() === query.trim().toLowerCase()
        );
        if (filteredDoctors.value.length > 0 && !hasExactMatch) {
          showDoctorDropdown.value = true;
        } else {
          showDoctorDropdown.value = false;
        }
        doctorSelectedIndex.value = -1;
      } catch (error) {
        console.error("Error searching doctors:", error);
        filteredDoctors.value = [];
        showDoctorDropdown.value = false;
      } finally {
        isDoctorLoading.value = false;
      }
    };
    return (_ctx, _push, _parent, _attrs) => {
      var _a, _b, _c, _d;
      _push(`<!--[-->`);
      _push(ssrRenderComponent(unref(Head), {
        title: _ctx.$page.props.pageTitle
      }, null, _parent));
      _push(`<div class="min-h-screen bg-gray-50 dark:bg-gray-900 overflow-y-auto" data-v-b27ed2fa><div class="w-full p-2" data-v-b27ed2fa><div class="bg-white rounded-lg shadow-lg dark:bg-slate-900 mb-4" data-v-b27ed2fa><div class="mb-3" data-v-b27ed2fa><div class="flex justify-between items-center bg-[#053855] text-white px-3 py-2 text-xs font-semibold rounded-t-lg" data-v-b27ed2fa><div class="flex-1" data-v-b27ed2fa>ITEM DETAILS</div><div class="flex items-center space-x-2 mr-2" data-v-b27ed2fa><a${ssrRenderAttr("href", _ctx.route("backend.billing.view"))} target="_blank" class="flex items-center px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-md text-xs transition-colors duration-200 shadow-sm" title="Billing List" data-v-b27ed2fa> Billing Add </a></div><div class="flex items-center space-x-2" data-v-b27ed2fa><a${ssrRenderAttr("href", _ctx.route("backend.billing.list"))} target="_blank" class="flex items-center px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-md text-xs transition-colors duration-200 shadow-sm" title="Billing List" data-v-b27ed2fa> Billing List </a><button class="flex items-center justify-center w-6 h-6 bg-red-500 hover:bg-red-600 text-white rounded-full text-xs transition-colors duration-200" title="Cancel" data-v-b27ed2fa><svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-v-b27ed2fa><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" data-v-b27ed2fa></path></svg></button></div></div><div class="border border-gray-300 border-t-0 p-3 bg-gray-50 dark:bg-slate-800 dark:border-gray-600" data-v-b27ed2fa><div class="flex items-center space-x-4 text-xs mb-3" data-v-b27ed2fa><div class="grid grid-cols-1 lg:grid-cols-12 gap-2 mt-2" data-v-b27ed2fa><div class="lg:col-span-2" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        value: "Billing Date",
        class: "text-xs mb-1"
      }, null, _parent));
      _push(`<input type="date"${ssrRenderAttr("value", _ctx.billingDate)} class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" data-v-b27ed2fa></div><div class="lg:col-span-2" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        value: "Billing Time",
        class: "text-xs mb-1"
      }, null, _parent));
      _push(`<input type="time"${ssrRenderAttr("value", _ctx.billingTime)} class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" data-v-b27ed2fa></div></div><span class="font-medium text-gray-700 dark:text-gray-300" data-v-b27ed2fa><strong data-v-b27ed2fa>UNIT:</strong> ToaMed.</span><span class="font-medium text-gray-700 dark:text-gray-300" data-v-b27ed2fa><strong data-v-b27ed2fa>Counter:</strong> ${ssrInterpolate(((_b = (_a = __props.authInfo) == null ? void 0 : _a.department) == null ? void 0 : _b.name) ?? "")}</span><span class="font-medium text-gray-700 dark:text-gray-300" data-v-b27ed2fa><strong data-v-b27ed2fa>Sales Person:</strong> ${ssrInterpolate(((_d = (_c = __props.authInfo) == null ? void 0 : _c.admin) == null ? void 0 : _d.name) ?? "")}</span></div><div class="grid grid-cols-1 lg:grid-cols-12 gap-2 items-end" data-v-b27ed2fa><div class="lg:col-span-2" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "category",
        value: "Category",
        class: "text-xs mb-1"
      }, null, _parent));
      _push(`<select id="category" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" data-v-b27ed2fa><option value="" data-v-b27ed2fa${ssrIncludeBooleanAttr(Array.isArray(itemForm.value.category) ? ssrLooseContain(itemForm.value.category, "") : ssrLooseEqual(itemForm.value.category, "")) ? " selected" : ""}>Select</option><option value="Pathology" data-v-b27ed2fa${ssrIncludeBooleanAttr(Array.isArray(itemForm.value.category) ? ssrLooseContain(itemForm.value.category, "Pathology") : ssrLooseEqual(itemForm.value.category, "Pathology")) ? " selected" : ""}>Pathology</option><option value="Radiology" data-v-b27ed2fa${ssrIncludeBooleanAttr(Array.isArray(itemForm.value.category) ? ssrLooseContain(itemForm.value.category, "Radiology") : ssrLooseEqual(itemForm.value.category, "Radiology")) ? " selected" : ""}>Radiology</option><option value="Medicine" data-v-b27ed2fa${ssrIncludeBooleanAttr(Array.isArray(itemForm.value.category) ? ssrLooseContain(itemForm.value.category, "Medicine") : ssrLooseEqual(itemForm.value.category, "Medicine")) ? " selected" : ""}>Medicine</option></select></div><div class="lg:col-span-2 relative" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "itemName",
        value: "Item Name",
        class: "text-xs mb-1"
      }, null, _parent));
      _push(`<div class="relative" data-v-b27ed2fa><input${ssrRenderAttr("value", itemForm.value.itemName)} id="itemName" type="text" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" placeholder="Search items..." autocomplete="off" data-v-b27ed2fa>`);
      if (searchQuery.value || itemForm.value.category && filteredItems.value.length > 0) {
        _push(`<div class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto dark:bg-slate-700 dark:border-gray-600" data-v-b27ed2fa><ul data-v-b27ed2fa><!--[-->`);
        ssrRenderList(filteredItems.value, (item, index) => {
          _push(`<li class="${ssrRenderClass([
            "list-focus px-3 py-2 text-xs cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-600",
            { "bg-blue-100 dark:bg-blue-700": index === selectedIndex.value }
          ])}" data-v-b27ed2fa><div class="flex justify-between" data-v-b27ed2fa><span data-v-b27ed2fa>${ssrInterpolate(item.name)}</span><span class="text-gray-500 dark:text-gray-300" data-v-b27ed2fa>${ssrInterpolate(item.type === "medicine" ? "Medicine" : item.category)} (৳${ssrInterpolate(item.unitPrice)}) </span></div>`);
          if (item.type === "medicine") {
            _push(`<div class="text-xs text-gray-500 dark:text-gray-400" data-v-b27ed2fa> Stock: ${ssrInterpolate(item.stock)}</div>`);
          } else {
            _push(`<!---->`);
          }
          _push(`</li>`);
        });
        _push(`<!--]-->`);
        if (filteredItems.value.length === 0) {
          _push(`<li class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400" data-v-b27ed2fa> No items found. </li>`);
        } else {
          _push(`<!---->`);
        }
        _push(`</ul></div>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</div></div><div class="lg:col-span-2" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "unitPrice",
        value: "U/Price",
        class: "text-xs mb-1"
      }, null, _parent));
      _push(`<div class="flex" data-v-b27ed2fa><input${ssrRenderAttr("value", itemForm.value.unitPrice)} type="number" step="1" id="unitPrice" readonly class="w-full px-2 py-1.5 border border-gray-300 rounded-l text-xs bg-yellow-100 focus:bg-yellow-200 focus:outline-none dark:bg-yellow-200 dark:text-gray-800" data-v-b27ed2fa><span class="px-2 py-1.5 bg-gray-200 border-t border-b border-r border-gray-300 rounded-r text-xs dark:bg-gray-600 dark:text-gray-200" data-v-b27ed2fa>৳</span></div></div><div class="lg:col-span-1" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "quantity",
        value: "Qty",
        class: "text-xs mb-1"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", itemForm.value.quantity)} type="number" step="1" min="1" id="quantity" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" data-v-b27ed2fa></div><div class="lg:col-span-2" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "totalAmount",
        value: "T.Amt",
        class: "text-xs mb-1"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", itemForm.value.totalAmount)} type="number" step="1" id="totalAmount" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs bg-gray-100 dark:bg-gray-600 dark:text-gray-200" readonly data-v-b27ed2fa></div><div class="lg:col-span-1" data-v-b27ed2fa><button class="w-full h-8 bg-teal-600 text-white rounded hover:bg-teal-700 flex items-center justify-center font-bold text-sm" data-v-b27ed2fa> ✚ </button></div></div></div></div><div class="mb-0" data-v-b27ed2fa><div class="bg-slate-700 text-white px-3 py-2 text-xs font-semibold" data-v-b27ed2fa> ITEM LIST </div><div class="border border-gray-300 border-t-0" data-v-b27ed2fa><div class="overflow-y-auto max-h-custom" data-v-b27ed2fa><table class="w-full text-xs" data-v-b27ed2fa><thead class="bg-teal-700 text-white sticky top-0" data-v-b27ed2fa><tr data-v-b27ed2fa><th class="px-2 py-2 text-left font-semibold" data-v-b27ed2fa>Item Name</th><th class="px-2 py-2 text-center font-semibold" data-v-b27ed2fa>Category</th><th class="px-2 py-2 text-center font-semibold" data-v-b27ed2fa>U/Price</th><th class="px-2 py-2 text-center font-semibold" data-v-b27ed2fa>Qty</th><th class="px-2 py-2 text-center font-semibold" data-v-b27ed2fa>T.Amt</th><th class="px-2 py-2 text-center font-semibold" data-v-b27ed2fa>Net Amt</th><th class="px-2 py-2 text-center font-semibold" data-v-b27ed2fa>Action</th></tr></thead><tbody class="bg-white dark:bg-slate-800" data-v-b27ed2fa><!--[-->`);
      ssrRenderList(items.value, (item, index) => {
        _push(`<tr class="border-b border-gray-200 hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-slate-700" data-v-b27ed2fa><td class="px-2 py-2 font-medium dark:text-gray-200" data-v-b27ed2fa>${ssrInterpolate(item.name)}</td><td class="px-2 py-2 text-center dark:text-gray-200" data-v-b27ed2fa><span class="${ssrRenderClass({
          "bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs": item.category === "Pathology",
          "bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs": item.category === "Radiology",
          "bg-purple-100 text-purple-800 px-2 py-1 rounded-full text-xs": item.category === "Medicine"
        })}" data-v-b27ed2fa>${ssrInterpolate(item.category)}</span></td><td class="px-2 py-2 text-center dark:text-gray-200" data-v-b27ed2fa> ৳${ssrInterpolate(item.unitPrice.toFixed(2))}</td><td class="px-2 py-2 text-center dark:text-gray-200" data-v-b27ed2fa>${ssrInterpolate(item.quantity)}</td><td class="px-2 py-2 text-center dark:text-gray-200" data-v-b27ed2fa> ৳${ssrInterpolate(item.totalAmount.toFixed(2))}</td><td class="px-2 py-2 text-center font-semibold dark:text-gray-200" data-v-b27ed2fa> ৳${ssrInterpolate(item.netAmount.toFixed(2))}</td><td class="px-2 py-2 text-center" data-v-b27ed2fa><button class="bg-red-500 text-white px-1.5 py-0.5 rounded text-xs hover:bg-red-600" data-v-b27ed2fa> 🗑 </button></td></tr>`);
      });
      _push(`<!--]--></tbody></table></div></div></div><div class="grid grid-cols-1 lg:grid-cols-3 gap-3 p-3" data-v-b27ed2fa><div data-v-b27ed2fa><div class="bg-teal-600 text-white px-3 py-2 text-xs font-semibold rounded-t flex justify-between items-center" data-v-b27ed2fa><span data-v-b27ed2fa>PATIENT DETAILS</span></div><div class="border border-gray-300 border-t-0 rounded-b p-3 bg-white dark:bg-slate-800 dark:border-gray-600 space-y-2" data-v-b27ed2fa><div class="grid grid-cols-3 gap-2 items-center" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "patient_id",
        value: "Patient",
        class: "text-xs"
      }, null, _parent));
      _push(`<div class="col-span-2 relative" data-v-b27ed2fa><input${ssrRenderAttr("value", patientSearchQuery.value)} id="patientSearch" type="text" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" placeholder="Search patient by name or phone" data-v-b27ed2fa>`);
      if (showPatientDropdown.value && filteredPatients.value.length > 0) {
        _push(`<div class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto dark:bg-slate-700 dark:border-gray-600" data-v-b27ed2fa><ul data-v-b27ed2fa><!--[-->`);
        ssrRenderList(filteredPatients.value, (patient, index) => {
          _push(`<li class="${ssrRenderClass([
            "px-3 py-2 text-xs cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-600",
            { "bg-blue-100 dark:bg-blue-700": index === patientSelectedIndex.value }
          ])}" data-v-b27ed2fa><div class="flex justify-between" data-v-b27ed2fa><span data-v-b27ed2fa>${ssrInterpolate(patient.name)}</span><span class="text-gray-500 dark:text-gray-300" data-v-b27ed2fa>${ssrInterpolate(patient.phone)}</span></div><div class="text-xs text-gray-500 dark:text-gray-400" data-v-b27ed2fa>${ssrInterpolate(patient.gender)}</div></li>`);
        });
        _push(`<!--]--></ul></div>`);
      } else {
        _push(`<!---->`);
      }
      if (showPatientDropdown.value && filteredPatients.value.length === 0 && patientSearchQuery.value.trim()) {
        _push(`<div class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg dark:bg-slate-700 dark:border-gray-600" data-v-b27ed2fa><div class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400" data-v-b27ed2fa> No patient found. <button type="button" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium" data-v-b27ed2fa> Add new patient </button></div></div>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</div></div><div class="grid grid-cols-3 gap-2 items-center" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "doctor_search",
        value: "Ref. Doctor",
        class: "text-xs"
      }, null, _parent));
      _push(`<div class="col-span-2 relative" data-v-b27ed2fa><div class="relative" data-v-b27ed2fa><input${ssrRenderAttr("value", doctorSearchQuery.value)} id="doctor_search" type="text" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" placeholder="Type doctor name and press Enter" autocomplete="off" data-v-b27ed2fa>`);
      if (doctorSearchQuery.value) {
        _push(`<button class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600" type="button" data-v-b27ed2fa><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" data-v-b27ed2fa><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" data-v-b27ed2fa></path></svg></button>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</div>`);
      if (isDoctorLoading.value) {
        _push(`<div class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg dark:bg-slate-700 dark:border-gray-600" data-v-b27ed2fa><div class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400" data-v-b27ed2fa> Searching... </div></div>`);
      } else if (showDoctorDropdown.value && filteredDoctors.value.length > 0) {
        _push(`<div class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto dark:bg-slate-700 dark:border-gray-600" data-v-b27ed2fa><ul data-v-b27ed2fa><!--[-->`);
        ssrRenderList(filteredDoctors.value, (doctor, index) => {
          _push(`<li class="${ssrRenderClass([
            "px-3 py-2 text-xs cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-600",
            { "bg-blue-100 dark:bg-blue-700": index === doctorSelectedIndex.value }
          ])}" data-v-b27ed2fa><div class="flex justify-between items-center" data-v-b27ed2fa><span data-v-b27ed2fa>${ssrInterpolate(doctor.name)}</span></div></li>`);
        });
        _push(`<!--]--></ul></div>`);
      } else if (showDoctorDropdown.value && filteredDoctors.value.length === 0 && doctorSearchQuery.value.trim()) {
        _push(`<div class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg dark:bg-slate-700 dark:border-gray-600" data-v-b27ed2fa><div class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400" data-v-b27ed2fa> Press Enter to use &quot;${ssrInterpolate(doctorSearchQuery.value)}&quot; as doctor name </div></div>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</div></div><input type="hidden"${ssrRenderAttr("value", patientForm.value.doctor_id)} data-v-b27ed2fa><div class="grid grid-cols-3 gap-2 items-center" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "patientMobile",
        value: "Patient Mobile",
        class: "text-xs"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", patientForm.value.patientMobile)} type="text" id="patientMobile" placeholder="Enter Patient Mobile" class="col-span-2 px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" data-v-b27ed2fa></div><div class="grid grid-cols-3 gap-2 items-center" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "gender",
        value: "Gender",
        class: "text-xs"
      }, null, _parent));
      _push(`<select id="gender" class="col-span-2 px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" data-v-b27ed2fa><option value="" data-v-b27ed2fa${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.gender) ? ssrLooseContain(patientForm.value.gender, "") : ssrLooseEqual(patientForm.value.gender, "")) ? " selected" : ""}>Select</option><option value="Male" data-v-b27ed2fa${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.gender) ? ssrLooseContain(patientForm.value.gender, "Male") : ssrLooseEqual(patientForm.value.gender, "Male")) ? " selected" : ""}>Male</option><option value="Female" data-v-b27ed2fa${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.gender) ? ssrLooseContain(patientForm.value.gender, "Female") : ssrLooseEqual(patientForm.value.gender, "Female")) ? " selected" : ""}>Female</option><option value="Others" data-v-b27ed2fa${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.gender) ? ssrLooseContain(patientForm.value.gender, "Others") : ssrLooseEqual(patientForm.value.gender, "Others")) ? " selected" : ""}>Others</option></select></div><div class="grid grid-cols-3 gap-2 items-center" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "dob",
        value: "Date of Birth",
        class: "text-xs"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", patientForm.value.dob)} type="date" id="dob" class="col-span-2 px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" data-v-b27ed2fa></div><div class="grid grid-cols-3 gap-2 items-center" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        value: "Age",
        class: "text-xs"
      }, null, _parent));
      _push(`<div class="col-span-2 flex items-center space-x-1" data-v-b27ed2fa><input${ssrRenderAttr("value", ageYears.value)} type="number" min="0" max="120" class="w-12 px-1 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" placeholder="Y" data-v-b27ed2fa><span class="text-xs text-gray-500" data-v-b27ed2fa>y</span><input${ssrRenderAttr("value", ageMonths.value)} type="number" min="0" max="11" class="w-12 px-1 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" placeholder="M" data-v-b27ed2fa><span class="text-xs text-gray-500" data-v-b27ed2fa>m</span><input${ssrRenderAttr("value", ageDays.value)} type="number" min="0" max="30" class="w-12 px-1 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" placeholder="D" data-v-b27ed2fa><span class="text-xs text-gray-500" data-v-b27ed2fa>d</span></div></div><div class="grid grid-cols-3 gap-2 items-center" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "payMode",
        value: "Pay Mode",
        class: "text-xs"
      }, null, _parent));
      _push(`<select id="payMode" class="col-span-2 px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" data-v-b27ed2fa><option value="Cash" data-v-b27ed2fa${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.payMode) ? ssrLooseContain(patientForm.value.payMode, "Cash") : ssrLooseEqual(patientForm.value.payMode, "Cash")) ? " selected" : ""}>Cash</option><option value="Card" data-v-b27ed2fa${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.payMode) ? ssrLooseContain(patientForm.value.payMode, "Card") : ssrLooseEqual(patientForm.value.payMode, "Card")) ? " selected" : ""}>Card</option><option value="Mobile Banking" data-v-b27ed2fa${ssrIncludeBooleanAttr(Array.isArray(patientForm.value.payMode) ? ssrLooseContain(patientForm.value.payMode, "Mobile Banking") : ssrLooseEqual(patientForm.value.payMode, "Mobile Banking")) ? " selected" : ""}>Mobile Banking</option></select></div>`);
      if (patientForm.value.payMode !== "Cash") {
        _push(`<div class="grid grid-cols-3 gap-2 items-center" data-v-b27ed2fa>`);
        _push(ssrRenderComponent(_sfc_main$1, {
          for: "cardNumber",
          value: "Card/Account No.",
          class: "text-xs"
        }, null, _parent));
        _push(`<input${ssrRenderAttr("value", patientForm.value.cardNumber)} type="text" id="cardNumber" placeholder="Enter card/account number" class="col-span-2 px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" data-v-b27ed2fa></div>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</div></div>`);
      _push(ssrRenderComponent(PatientModal, {
        isOpen: isPatientModalOpen.value,
        tpas: props.tpas,
        onClose: closePatientModal,
        onPatientCreated: handlePatientCreated
      }, null, _parent));
      _push(`<div data-v-b27ed2fa><div class="bg-teal-600 text-white px-3 py-2 text-xs font-semibold rounded-t" data-v-b27ed2fa> TOTAL SUMMARY </div><div class="border border-gray-300 border-t-0 rounded-b p-3 bg-white dark:bg-slate-800 dark:border-gray-600 space-y-2" data-v-b27ed2fa><div class="grid grid-cols-2 gap-2 items-center" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "total",
        value: "Total Amount",
        class: "text-xs font-semibold"
      }, null, _parent));
      _push(`<div class="flex" data-v-b27ed2fa><input${ssrRenderAttr("value", summary.value.total)} type="number" step="0.01" id="total" readonly class="w-full px-2 py-1.5 border border-gray-300 rounded-l text-xs bg-gray-100 dark:bg-gray-600 dark:text-gray-200" data-v-b27ed2fa><span class="px-2 py-1.5 bg-gray-200 border-t border-b border-r border-gray-300 rounded-r text-xs dark:bg-gray-600 dark:text-gray-200" data-v-b27ed2fa>৳</span></div></div><div class="grid grid-cols-2 gap-2" data-v-b27ed2fa><div class="grid grid-cols-2 gap-1 items-center" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "discount",
        value: "Discount",
        class: "text-xs"
      }, null, _parent));
      _push(`<select class="px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" data-v-b27ed2fa><option value="percentage" data-v-b27ed2fa${ssrIncludeBooleanAttr(Array.isArray(summary.value.discountType) ? ssrLooseContain(summary.value.discountType, "percentage") : ssrLooseEqual(summary.value.discountType, "percentage")) ? " selected" : ""}>Percentage (%)</option><option value="flat" data-v-b27ed2fa${ssrIncludeBooleanAttr(Array.isArray(summary.value.discountType) ? ssrLooseContain(summary.value.discountType, "flat") : ssrLooseEqual(summary.value.discountType, "flat")) ? " selected" : ""}>Flat Amount (৳)</option></select></div><div class="flex" data-v-b27ed2fa><input${ssrRenderAttr("value", summary.value.discount)} type="number" step="1" min="0" id="discount" class="w-full px-2 py-1.5 border border-gray-300 rounded-l text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" data-v-b27ed2fa><span class="px-2 py-1.5 bg-gray-200 border-t border-b border-r border-gray-300 rounded-r text-xs dark:bg-gray-600 dark:text-gray-200" data-v-b27ed2fa>৳</span></div></div><div class="grid grid-cols-2 gap-2 items-center" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "extraFlatDiscount",
        value: "Extra Discount",
        class: "text-xs"
      }, null, _parent));
      _push(`<div class="flex" data-v-b27ed2fa><input${ssrRenderAttr("value", summary.value.extraFlatDiscount)} type="number" step="1" min="0" id="extraFlatDiscount" class="w-full px-2 py-1.5 border border-gray-300 rounded-l text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" placeholder="Additional flat discount" data-v-b27ed2fa><span class="px-2 py-1.5 bg-gray-200 border-t border-b border-r border-gray-300 rounded-r text-xs dark:bg-gray-600 dark:text-gray-200" data-v-b27ed2fa>৳</span></div></div><div class="grid grid-cols-2 gap-2 items-center" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "payableAmount",
        value: "Payable Amount",
        class: "text-xs font-semibold text-green-700 dark:text-green-400"
      }, null, _parent));
      _push(`<div class="flex" data-v-b27ed2fa><input${ssrRenderAttr("value", summary.value.payableAmount)} type="number" step="0.01" id="payableAmount" readonly class="w-full px-2 py-1.5 border border-green-500 rounded-l text-xs bg-green-50 font-semibold dark:bg-green-900 dark:text-green-100" data-v-b27ed2fa><span class="px-2 py-1.5 bg-green-200 border-t border-b border-r border-green-500 rounded-r text-xs font-semibold dark:bg-green-700 dark:text-green-100" data-v-b27ed2fa>৳</span></div></div><div class="grid grid-cols-2 gap-2 items-center" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "takingAmt",
        value: "Taking Amount",
        class: "text-xs font-semibold text-indigo-700 dark:text-indigo-400"
      }, null, _parent));
      _push(`<div class="flex" data-v-b27ed2fa><input${ssrRenderAttr("value", summary.value.takingAmt)} type="number" step="0.01" min="0" id="takingAmt" class="w-full px-2 py-1.5 border border-indigo-500 rounded-l text-xs focus:border-indigo-700 focus:outline-none bg-indigo-50 dark:bg-indigo-900 dark:border-indigo-400 dark:text-indigo-100" placeholder="Amount taken from customer" data-v-b27ed2fa><span class="px-2 py-1.5 bg-indigo-200 border-t border-b border-r border-indigo-500 rounded-r text-xs font-semibold dark:bg-indigo-700 dark:text-indigo-100" data-v-b27ed2fa>৳</span></div></div><div class="grid grid-cols-2 gap-2 items-center" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "receivingAmt",
        value: "Receiving Amount",
        class: "text-xs font-semibold text-blue-700 dark:text-blue-400"
      }, null, _parent));
      _push(`<div class="flex" data-v-b27ed2fa><input${ssrRenderAttr("value", summary.value.receivingAmt)} type="number" step="0.01" min="0" id="receivingAmt" class="w-full px-2 py-1.5 border border-blue-500 rounded-l text-xs focus:border-blue-700 focus:outline-none bg-blue-50 dark:bg-blue-900 dark:border-blue-400 dark:text-blue-100" placeholder="Amount given by customer" data-v-b27ed2fa><span class="px-2 py-1.5 bg-blue-200 border-t border-b border-r border-blue-500 rounded-r text-xs font-semibold dark:bg-blue-700 dark:text-blue-100" data-v-b27ed2fa>৳</span></div></div>`);
      if (summary.value.returnAmt > 0) {
        _push(`<div class="grid grid-cols-2 gap-2 items-center" data-v-b27ed2fa>`);
        _push(ssrRenderComponent(_sfc_main$1, {
          for: "returnAmt",
          value: "Return Amount",
          class: "text-xs font-semibold text-amber-700 dark:text-amber-400"
        }, null, _parent));
        _push(`<div class="flex" data-v-b27ed2fa><input${ssrRenderAttr("value", summary.value.returnAmt)} type="number" step="0.01" id="returnAmt" readonly class="w-full px-2 py-1.5 border border-amber-500 rounded-l text-xs bg-amber-50 font-semibold dark:bg-amber-900 dark:text-amber-100" data-v-b27ed2fa><span class="px-2 py-1.5 bg-amber-200 border-t border-b border-r border-amber-500 rounded-r text-xs font-semibold dark:bg-amber-700 dark:text-amber-100" data-v-b27ed2fa>৳</span></div></div>`);
      } else {
        _push(`<!---->`);
      }
      _push(`<div class="grid grid-cols-2 gap-2 items-center" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "paidAmt",
        value: "Paid Amount",
        class: "text-xs font-semibold"
      }, null, _parent));
      _push(`<div class="flex" data-v-b27ed2fa><input${ssrRenderAttr("value", summary.value.paidAmt)} type="number" step="0.01" id="paidAmt" readonly class="w-full px-2 py-1.5 border border-gray-300 rounded-l text-xs bg-gray-100 font-semibold dark:bg-gray-600 dark:text-gray-200" data-v-b27ed2fa><span class="px-2 py-1.5 bg-gray-200 border-t border-b border-r border-gray-300 rounded-r text-xs dark:bg-gray-600 dark:text-gray-200" data-v-b27ed2fa>৳</span></div></div>`);
      if (summary.value.changeAmt > 0) {
        _push(`<div class="grid grid-cols-2 gap-2 items-center" data-v-b27ed2fa>`);
        _push(ssrRenderComponent(_sfc_main$1, {
          for: "changeAmt",
          value: "Change Amount",
          class: "text-xs font-semibold text-purple-700 dark:text-purple-400"
        }, null, _parent));
        _push(`<div class="flex" data-v-b27ed2fa><input${ssrRenderAttr("value", summary.value.changeAmt)} type="number" step="0.01" id="changeAmt" readonly class="w-full px-2 py-1.5 border border-purple-500 rounded-l text-xs bg-purple-50 font-semibold dark:bg-purple-900 dark:text-purple-100" data-v-b27ed2fa><span class="px-2 py-1.5 bg-purple-200 border-t border-b border-r border-purple-500 rounded-r text-xs font-semibold dark:bg-purple-700 dark:text-purple-100" data-v-b27ed2fa>৳</span></div></div>`);
      } else {
        _push(`<!---->`);
      }
      if (summary.value.dueAmount > 0) {
        _push(`<div class="grid grid-cols-2 gap-2 items-center" data-v-b27ed2fa>`);
        _push(ssrRenderComponent(_sfc_main$1, {
          for: "dueAmount",
          value: "Due Amount",
          class: "text-xs font-semibold text-red-700 dark:text-red-400"
        }, null, _parent));
        _push(`<div class="flex" data-v-b27ed2fa><input${ssrRenderAttr("value", summary.value.dueAmount)} type="number" step="0.01" id="dueAmount" readonly class="w-full px-2 py-1.5 border border-red-500 rounded-l text-xs bg-red-50 font-semibold dark:bg-red-900 dark:text-red-100" data-v-b27ed2fa><span class="px-2 py-1.5 bg-red-200 border-t border-b border-r border-red-500 rounded-r text-xs font-semibold dark:bg-red-700 dark:text-red-100" data-v-b27ed2fa>৳</span></div></div>`);
      } else {
        _push(`<!---->`);
      }
      _push(`<div class="grid grid-cols-2 gap-2 items-center" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "deliveryDate",
        value: "Delivery Date",
        class: "text-xs"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", summary.value.deliveryDate)} type="datetime-local" id="deliveryDate" class="px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" data-v-b27ed2fa></div><div data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "remarks",
        value: "Remarks",
        class: "text-xs mb-1"
      }, null, _parent));
      _push(`<textarea id="remarks" rows="2" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" placeholder="Additional notes or remarks" data-v-b27ed2fa>${ssrInterpolate(summary.value.remarks)}</textarea></div></div></div><div class="flex flex-col" data-v-b27ed2fa><div data-v-b27ed2fa><div class="bg-teal-600 text-white px-3 py-2 text-xs font-semibold rounded-t" data-v-b27ed2fa> COMMISSION FOR PC </div><div class="border border-gray-300 border-t-0 rounded-b p-3 bg-white dark:bg-slate-800 dark:border-gray-600 space-y-3" data-v-b27ed2fa><div class="flex justify-between items-center" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "referrer_id",
        value: "Referrer Name",
        class: "text-xs"
      }, null, _parent));
      _push(`<select id="referrer_id" class="w-32 px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200" data-v-b27ed2fa><option value="" data-v-b27ed2fa${ssrIncludeBooleanAttr(Array.isArray(commission.value.referrer_id) ? ssrLooseContain(commission.value.referrer_id, "") : ssrLooseEqual(commission.value.referrer_id, "")) ? " selected" : ""}>Select Referrer</option><!--[-->`);
      ssrRenderList(__props.referrers, (data) => {
        _push(`<option${ssrRenderAttr("value", data.id)} data-v-b27ed2fa${ssrIncludeBooleanAttr(Array.isArray(commission.value.referrer_id) ? ssrLooseContain(commission.value.referrer_id, data.id) : ssrLooseEqual(commission.value.referrer_id, data.id)) ? " selected" : ""}>${ssrInterpolate(data.name)}</option>`);
      });
      _push(`<!--]--></select></div>`);
      if (commission.value.referrer_id && commissionBreakdown.value.length > 0) {
        _push(`<div class="bg-blue-50 border border-blue-200 rounded-lg p-2 text-xs" data-v-b27ed2fa><h4 class="font-medium text-blue-800 mb-2" data-v-b27ed2fa>Commission Breakdown:</h4><div class="space-y-1" data-v-b27ed2fa><!--[-->`);
        ssrRenderList(commissionBreakdown.value, (breakdown) => {
          _push(`<div class="flex justify-between items-center" data-v-b27ed2fa><span class="text-blue-700" data-v-b27ed2fa>${ssrInterpolate(breakdown.category)} (${ssrInterpolate(breakdown.rate)}%): </span><span class="${ssrRenderClass(
            breakdown.hasCommission ? "text-green-600 font-medium" : "text-red-600"
          )}" data-v-b27ed2fa>${ssrInterpolate(breakdown.hasCommission ? `৳${breakdown.commission.toFixed(2)}` : "No Commission")}</span></div>`);
        });
        _push(`<!--]--></div></div>`);
      } else {
        _push(`<!---->`);
      }
      _push(`<div class="flex justify-between items-center" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "commissionTotal",
        value: "Total:",
        class: "text-xs"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", commission.value.total)} type="number" step="1" id="commissionTotal" class="w-24 px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-right dark:bg-gray-600 dark:text-gray-200" readonly data-v-b27ed2fa></div><div class="flex justify-between items-center" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "physystAmt",
        value: "Physyst Amt:",
        class: "text-xs"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", commission.value.physystAmt)} type="number" step="1" id="physystAmt" class="w-24 px-2 py-1 border border-gray-300 rounded text-xs bg-green-100 text-right font-semibold dark:bg-green-200 dark:text-gray-800" readonly data-v-b27ed2fa></div><div class="space-y-1" data-v-b27ed2fa><div class="flex justify-between items-center" data-v-b27ed2fa>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        for: "slider",
        value: "Commission %:",
        class: "text-xs"
      }, null, _parent));
      if (commissionDetails.value.manualCommissionEnabled) {
        _push(`<span class="text-xs text-orange-600 font-medium" data-v-b27ed2fa>Manual</span>`);
      } else if (commission.value.referrer_id && commissionBreakdown.value.length > 0) {
        _push(`<span class="text-xs text-green-600 font-medium" data-v-b27ed2fa>Auto</span>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</div><div class="flex items-center space-x-2" data-v-b27ed2fa><input${ssrRenderAttr("value", commission.value.slider)} type="range" min="0" max="100" step="1" id="slider"${ssrIncludeBooleanAttr(
        !commissionDetails.value.manualCommissionEnabled && !commission.value.referrer_id
      ) ? " disabled" : ""} class="${ssrRenderClass([
        "flex-1 h-2 rounded-lg appearance-none cursor-pointer",
        commissionDetails.value.manualCommissionEnabled ? "bg-orange-200" : "bg-gray-200",
        "dark:bg-gray-700"
      ])}" data-v-b27ed2fa><span class="${ssrRenderClass([
        commissionDetails.value.manualCommissionEnabled ? "text-orange-600" : "text-gray-700 dark:text-gray-300",
        "text-xs font-semibold w-8"
      ])}" data-v-b27ed2fa>${ssrInterpolate(commission.value.slider)}% </span></div>`);
      if (commissionDetails.value.manualCommissionEnabled) {
        _push(`<div class="text-xs text-orange-600 mt-1" data-v-b27ed2fa> Manual commission enabled - adjust as needed </div>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</div></div></div><div class="p-4 bg-white dark:bg-slate-800 dark:border-gray-600" data-v-b27ed2fa><div class="flex justify-end space-x-3" data-v-b27ed2fa><button class="px-4 py-2 text-gray-600 hover:text-gray-800 bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded text-sm font-medium dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:hover:bg-gray-600 dark:hover:text-gray-100 transition-colors" data-v-b27ed2fa> Cancel </button><button class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm font-medium flex items-center space-x-2 transition-colors" data-v-b27ed2fa><span data-v-b27ed2fa>💾</span><span data-v-b27ed2fa>Save &amp; Print Bill</span></button></div></div></div></div></div></div></div><!--]-->`);
    };
  }
};
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/Billing/BillingPage.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const BillingPage = /* @__PURE__ */ _export_sfc(_sfc_main, [["__scopeId", "data-v-b27ed2fa"]]);
export {
  BillingPage as default
};
