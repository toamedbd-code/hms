<script setup>
import { ref, computed, watch, nextTick, onMounted } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import InputLabel from "@/Components/InputLabel.vue";
import { Head } from "@inertiajs/vue3";
import PatientModal from "@/Components/PatientModal.vue";
import { displayResponse, displayWarning } from "@/responseMessage.js";
import { debounce } from 'lodash';

const props = defineProps({
  pageTitle: String,
  pathologyAndRadiologyTests: Array,
  medicineInventories: Array,
  doctors: Array,
  patients: Array,
  id: [String, Number],
  editData: Object,
  referrers: Array,
  authInfo: Object,
});

// Refs for focusable elements
const patientSearchInput = ref(null);
const doctorSelectRef = ref(null);
const patientMobileInput = ref(null);
const genderSelectRef = ref(null);
const payModeSelectRef = ref(null);
const cardNumberInputRef = ref(null);
const discountInputRef = ref(null);
const extraFlatDiscountInputRef = ref(null);
const takingAmtInputRef = ref(null);
const receivingAmtInputRef = ref(null);
const deliveryDateInputRef = ref(null);
const remarksTextareaRef = ref(null);
const referrerSelectRef = ref(null);
const saveBillButtonRef = ref(null);
const discountTypeSelectRef = ref(null);

// Track if dropdown is open
const isDoctorDropdownOpen = ref(false);
const isGenderDropdownOpen = ref(false);
const isDiscountTypeDropdownOpen = ref(false);

// Patient search and selection
const patientSearchQuery = ref("");
const patientSelectedIndex = ref(-1);
const showPatientDropdown = ref(false);
const filteredPatients = ref([]);
const isPatientModalOpen = ref(false);
const patientsList = ref([...props.patients]);

// Initialize all form refs first
const itemForm = ref({
  category: "",
  itemName: "",
  itemId: null,
  unitPrice: 0,
  quantity: 1.0,
  totalAmount: 0.0,
});

const patientForm = ref({
  patient_id: "",
  doctor_id: "",
  patientMobile: "",
  gender: "",
  cardType: "Cash",
  payMode: "Cash",
  cardNumber: "",
});

const summary = ref({
  total: 0,
  discount: 0,
  discountType: "percentage",
  extraFlatDiscount: 0,
  payableAmount: 0,
  paidAmt: 0,
  changeAmt: 0.0,
  dueAmount: 0.0,
  receivingAmt: 0.0,
  takingAmt: 0.0,
  returnAmt: 0.0,
  deliveryDate: "",
  remarks: "",
});

const commission = ref({
  total: 0.0,
  physystAmt: 0.0,
  slider: 0,
  referrer_id: "",
  commissionRate: 0,
});

const items = ref([]);
const isNewPatient = ref(false);
const newPatientForm = ref({
  name: "",
  phone: "",
  gender: "",
});

// Now initialize other refs
const itemNameInput = ref(null);
const quantityInput = ref(null);
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
  noCommissionMessage: "",
});

// Set current date as default for delivery date
onMounted(() => {
  if (!props.id || !props.editData) {
    const today = new Date();
    const formattedDate = today.toISOString().split('T')[0];
    summary.value.deliveryDate = formattedDate;
  }
});

// Now set up all watchers after all refs are initialized
// Watch for changes in patient form when in new patient mode
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

// Watch for changes in new patient form and sync with main patient form
watch(() => newPatientForm.value.phone, (newPhone) => {
  console.log('newPatientForm.phone changed to:', newPhone);
  if (isNewPatient.value && newPhone) {
    patientForm.value.patientMobile = newPhone;
  }
});

watch(() => newPatientForm.value.gender, (newGender) => {
  console.log('newPatientForm.gender changed to:', newGender);
  if (isNewPatient.value && newGender) {
    patientForm.value.gender = newGender;
  }
});

watch(() => newPatientForm.value.name, (newName) => {
  console.log('newPatientForm.name changed to:', newName);
  if (isNewPatient.value && newName) {
    patientSearchQuery.value = newName;
  }
});

// Watch for when new patient mode is toggled
watch(() => isNewPatient.value, (newValue) => {
  console.log('isNewPatient changed to:', newValue);
  if (!newValue) {
    // Reset forms when new patient mode is cancelled
    console.log('Resetting new patient form...');
    newPatientForm.value = {
      name: "",
      phone: "",
      gender: "",
    };
    if (!patientForm.value.patient_id) {
      patientForm.value.patientMobile = "";
      patientForm.value.gender = "";
      patientSearchQuery.value = "";
    }
  } else {
    // Entering new patient mode
    console.log('Entering new patient mode...');
    patientForm.value.patient_id = null; // Ensure patient_id is null
  }
});

// Method to handle patient search blur event
const handlePatientSearchBlur = () => {
  setTimeout(() => {
    showPatientDropdown.value = false;
  }, 200);
};

watch(patientSearchQuery, debounce((newQuery) => {
  if (newQuery.trim() === "") {
    filteredPatients.value = [];
    showPatientDropdown.value = false;
    return;
  }
  const query = newQuery.toLowerCase();
  filteredPatients.value = props.patients.filter(patient =>
    patient.name.toLowerCase().includes(query) ||
    patient.phone.toLowerCase().includes(query)
  );
  showPatientDropdown.value = filteredPatients.value.length > 0;
  patientSelectedIndex.value = -1;
}, 300));

const openPatientModal = () => {
  isPatientModalOpen.value = true;
};

const closePatientModal = () => {
  isPatientModalOpen.value = false;
};

const selectPatient = (patient) => {
  console.log('Selecting existing patient:', patient);
  patientForm.value.patient_id = patient.id;
  patientForm.value.patientMobile = patient.phone;
  patientForm.value.gender = patient.gender;
  patientSearchQuery.value = patient.name;
  showPatientDropdown.value = false;
  isNewPatient.value = false;
  // Clear any manually entered patient data
  newPatientForm.value = {
    name: "",
    phone: "",
    gender: "",
  };
  console.log('Patient selected, isNewPatient set to false');
  
  // Focus doctor field after selecting patient
  nextTick(() => {
    doctorSelectRef.value?.focus();
  });
};

const createNewPatient = () => {
  isNewPatient.value = true;
  patientForm.value.patient_id = null;
  newPatientForm.value = {
    name: patientSearchQuery.value.trim() || "",
    phone: "",
    gender: "",
  };
  // Clear main form fields that will be populated from new patient form
  patientForm.value.patientMobile = "";
  patientForm.value.gender = "";
  showPatientDropdown.value = false;
  
  // Focus doctor field after creating new patient
  nextTick(() => {
    doctorSelectRef.value?.focus();
  });
};

const handlePatientSearchKeyDown = (event) => {
  if (event.key === "ArrowDown") {
    event.preventDefault();
    patientSelectedIndex.value = Math.min(
      patientSelectedIndex.value + 1,
      filteredPatients.value.length - 1
    );
  } else if (event.key === "ArrowUp") {
    event.preventDefault();
    patientSelectedIndex.value = Math.max(patientSelectedIndex.value - 1, 0);
  } else if (event.key === "Enter") {
    event.preventDefault();
    if (patientSelectedIndex.value !== -1 && filteredPatients.value[patientSelectedIndex.value]) {
      selectPatient(filteredPatients.value[patientSelectedIndex.value]);
    } else if (patientSearchQuery.value.trim() && filteredPatients.value.length === 0) {
      // Create new patient when no matches found
      createNewPatientFromSearch();
    }
    nextTick(() => {
      // After selecting or creating a patient, focus and open the doctor dropdown
      if (doctorSelectRef.value) {
        doctorSelectRef.value.focus();
        doctorSelectRef.value.click();
      }
    });
  } else if (event.key === "Escape") {
    patientSelectedIndex.value = -1;
    showPatientDropdown.value = false;
  }
};

const createNewPatientFromSearch = () => {
  console.log('Creating new patient from search:', patientSearchQuery.value.trim());
  isNewPatient.value = true;
  patientForm.value.patient_id = null;
  // Initialize new patient form with search query
  newPatientForm.value = {
    name: patientSearchQuery.value.trim(),
    phone: "",
    gender: "",
  };
  // Clear main form fields that will be populated from new patient form
  patientForm.value.patientMobile = "";
  patientForm.value.gender = "";
  showPatientDropdown.value = false;
  console.log('New patient mode activated');
  
  // Focus doctor field after creating new patient
  nextTick(() => {
    doctorSelectRef.value?.focus();
  });
};

const handlePatientCreated = (newPatient) => {
  patientsList.value.push(newPatient);
  patientForm.value.patient_id = newPatient.id;
  patientForm.value.patientMobile = newPatient.phone;
  patientForm.value.gender = newPatient.gender;
  patientSearchQuery.value = newPatient.name;
  isNewPatient.value = false;
  
  // Refresh patients list
  router.reload({
    only: ["patients"],
    preserveState: true,
    preserveScroll: true,
    onSuccess: (page) => {
      patientsList.value = [...page.props.patients];
    },
  });
  
  // Focus doctor field after patient creation
  nextTick(() => {
    doctorSelectRef.value?.focus();
  });
};

// Keyboard navigation handlers
const handleDoctorKeyDown = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    if (!isDoctorDropdownOpen.value) {
      // Open dropdown
      if (doctorSelectRef.value) {
        doctorSelectRef.value.focus();
        doctorSelectRef.value.click(); // Using click() to open the dropdown
      }
      isDoctorDropdownOpen.value = true;
    } else {
      // Close dropdown and move to next field
      isDoctorDropdownOpen.value = false;
      if (isNewPatient.value) {
        nextTick(() => {
          patientMobileInput.value?.focus();
        });
      } else {
        nextTick(() => {
          payModeSelectRef.value?.focus();
        });
      }
    }
  } else if (event.key === "Escape") {
    isDoctorDropdownOpen.value = false;
  } else if (event.key === "Tab") {
    isDoctorDropdownOpen.value = false;
  }
};

const handleDoctorChange = () => {
  isDoctorDropdownOpen.value = false;
  if (isNewPatient.value) {
    nextTick(() => {
      patientMobileInput.value?.focus();
    });
  } else {
    nextTick(() => {
      payModeSelectRef.value?.focus();
    });
  }
};

const handlePatientMobileKeyDown = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    nextTick(() => {
      genderSelectRef.value?.focus();
    });
  }
};

const handleGenderKeyDown = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    if (!isGenderDropdownOpen.value) {
      // Open dropdown
      if (genderSelectRef.value) {
        genderSelectRef.value.focus();
        genderSelectRef.value.click();
      }
      isGenderDropdownOpen.value = true;
    } else {
      // Close dropdown and move to next field
      isGenderDropdownOpen.value = false;
      nextTick(() => {
        payModeSelectRef.value?.focus();
      });
    }
  } else if (event.key === "Escape") {
    isGenderDropdownOpen.value = false;
  } else if (event.key === "Tab") {
    isGenderDropdownOpen.value = false;
  }
};

const handleGenderChange = () => {
  isGenderDropdownOpen.value = false;
  nextTick(() => {
    payModeSelectRef.value?.focus();
  });
};

const handlePayModeKeyDown = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    if (patientForm.value.payMode !== 'Cash') {
      nextTick(() => {
        cardNumberInputRef.value?.focus();
      });
    } else {
      // Move to TOTAL SUMMARY section
      nextTick(() => {
        discountInputRef.value?.focus();
      });
    }
  }
};

const handleCardNumberKeyDown = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    // Move to TOTAL SUMMARY section
    nextTick(() => {
      discountInputRef.value?.focus();
    });
  }
};

// TOTAL SUMMARY keyboard navigation
const handleDiscountTypeKeyDown = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    if (!isDiscountTypeDropdownOpen.value) {
      // Open dropdown
      discountTypeSelectRef.value?.click();
      isDiscountTypeDropdownOpen.value = true;
    } else {
      // Close dropdown and move to next field
      isDiscountTypeDropdownOpen.value = false;
      nextTick(() => {
        discountInputRef.value?.focus();
      });
    }
  } else if (event.key === "Escape") {
    isDiscountTypeDropdownOpen.value = false;
  } else if (event.key === "Tab") {
    isDiscountTypeDropdownOpen.value = false;
  }
};

const handleDiscountTypeChange = () => {
  isDiscountTypeDropdownOpen.value = false;
  nextTick(() => {
    discountInputRef.value?.focus();
  });
};

const handleDiscountKeyDown = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    nextTick(() => {
      extraFlatDiscountInputRef.value?.focus();
    });
  }
};

const handleExtraFlatDiscountKeyDown = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    nextTick(() => {
      takingAmtInputRef.value?.focus();
    });
  }
};

const handleTakingAmtKeyDown = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    nextTick(() => {
      receivingAmtInputRef.value?.focus();
    });
  }
};

const handleReceivingAmtKeyDown = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    nextTick(() => {
      deliveryDateInputRef.value?.focus();
    });
  }
};

const handleDeliveryDateKeyDown = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    // Trigger the date picker
    if (deliveryDateInputRef.value) {
      deliveryDateInputRef.value.click();
    }
  }
};

const handleRemarksKeyDown = (event) => {
  if (event.key === "Enter" && !event.shiftKey) {
    event.preventDefault();
    nextTick(() => {
      referrerSelectRef.value?.focus();
    });
  }
};

const handleReferrerKeyDown = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    nextTick(() => {
      saveBillButtonRef.value?.focus();
    });
  }
};

// Add a debug function
const debugFormState = () => {
  console.log('=== FORM STATE DEBUG ===');
  console.log('isNewPatient:', isNewPatient.value);
  console.log('patientForm:', JSON.parse(JSON.stringify(patientForm.value)));
  console.log('newPatientForm:', JSON.parse(JSON.stringify(newPatientForm.value)));
  console.log('patientSearchQuery:', patientSearchQuery.value);
  console.log('========================');
};

const validateNewPatientForm = () => {
  if (!isNewPatient.value) return true;
  const errors = [];
  if (!patientSearchQuery.value?.trim()) errors.push('Patient name is required');
  if (!patientForm.value.patientMobile?.trim()) errors.push('Patient phone is required');
  if (!patientForm.value.gender) errors.push('Patient gender is required');
  if (errors.length > 0) {
    displayWarning({ message: errors.join(', ') });
    return false;
  }
  return true;
};

// Watch for item form changes
watch(
  () => itemForm.value.quantity,
  (newQuantity) => {
    if (newQuantity && itemForm.value.unitPrice) {
      itemForm.value.totalAmount = (newQuantity * itemForm.value.unitPrice).toFixed(2);
    }
  }
);

watch([() => itemForm.value.quantity, () => itemForm.value.unitPrice], () => {
  itemForm.value.totalAmount = (
    itemForm.value.quantity * itemForm.value.unitPrice
  ).toFixed(2);
});

watch(
  () => patientForm.value.patient_id,
  (newPatientId) => {
    if (newPatientId && props.patients) {
      const selectedPatient = props.patients.find(
        (patient) => patient.id == newPatientId
      );
      if (selectedPatient) {
        patientForm.value.patientMobile =
          selectedPatient.phone || selectedPatient.mobile || "";
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
    ...new Set(items.value.map((item) => item.category.toLowerCase())),
  ];
  const availableCommissions = {
    pathology: referrer.pathology_commission || 0,
    radiology: referrer.radiology_commission || 0,
    medicine: referrer.pharmacy_commission || 0,
  };
  commissionDetails.value.hasPathologyCommission =
    itemCategories.includes("pathology") && availableCommissions.pathology > 0;
  commissionDetails.value.hasRadiologyCommission =
    itemCategories.includes("radiology") && availableCommissions.radiology > 0;
  commissionDetails.value.hasMedicineCommission =
    itemCategories.includes("medicine") && availableCommissions.medicine > 0;
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
        items: [],
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
      totalCommissionAmount += (categoryAmount * rate) / 100;
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
  } else if (
    categoriesWithoutCommission.length > 0 &&
    categoriesWithCommission.length > 0
  ) {
    const totalBillAmount = summary.value.payableAmount || summary.value.total;
    const effectiveCommissionRate =
      totalBillAmount > 0 ? (totalCommissionAmount / totalBillAmount) * 100 : 0;
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
    const effectiveCommissionRate =
      totalBillAmount > 0 ? (totalCommissionAmount / totalBillAmount) * 100 : 0;
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
    noCommissionMessage: "",
  };
};

const commissionBreakdown = computed(() => {
  if (!commission.value.referrer_id || items.value.length === 0) return [];
  const breakdown = [];
  const selectedReferrer = props.referrers.find(
    (referrer) => referrer.id == commission.value.referrer_id
  );
  if (!selectedReferrer) return breakdown;
  const categoryTotals = items.value.reduce((acc, item) => {
    const category = item.category.toLowerCase();
    if (!acc[category]) {
      acc[category] = {
        total: 0,
        items: [],
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
    const commissionAmount = rate > 0 ? (categoryTotal * rate) / 100 : 0;
    breakdown.push({
      category: category.charAt(0).toUpperCase() + category.slice(1),
      rateName,
      rate,
      amount: categoryTotal,
      commission: commissionAmount,
      hasCommission: rate > 0,
    });
  });
  return breakdown;
});

const addItem = () => {
  if (!itemForm.value.itemName || itemForm.value.unitPrice <= 0) {
    displayWarning({
      message: "Please select a valid item and ensure price is greater than 0.",
    });
    return;
  }
  if (itemForm.value.category.toLowerCase() !== "medicine") {
    const existingItem = items.value.find(
      (item) =>
        item.name === itemForm.value.itemName && item.category === itemForm.value.category
    );
    if (existingItem) {
      displayWarning({ message: "This test has already been added to the cart." });
      itemForm.value = {
        category: "",
        itemName: "",
        itemId: null,
        unitPrice: 0,
        quantity: 1,
        totalAmount: 0,
      };
      itemNameInput.value.focus();
      return;
    }
  }
  if (itemForm.value.category.toLowerCase() === "medicine") {
    const selectedMedicine = props.medicineInventories.find(
      (medicine) => medicine.medicine_name === itemForm.value.itemName
    );
    if (selectedMedicine) {
      const totalInCart = items.value
        .filter((item) => item.id === selectedMedicine.id)
        .reduce((sum, item) => sum + item.quantity, 0);
      const requestedTotal = totalInCart + itemForm.value.quantity;
      if (requestedTotal > selectedMedicine.medicine_quantity) {
        const available = selectedMedicine.medicine_quantity - totalInCart;
        displayWarning({
          message: `Only ${available} units available in stock (${selectedMedicine.medicine_quantity} total, ${totalInCart} already in cart).`,
        });
        return;
      }
    }
  }
  items.value.push({
    id: itemForm.value.itemId,
    name: itemForm.value.itemName,
    category: itemForm.value.category,
    unitPrice: parseFloat(itemForm.value.unitPrice),
    quantity: parseFloat(itemForm.value.quantity),
    totalAmount: parseFloat(itemForm.value.totalAmount),
    discount: 0,
    rugound: 0,
    netAmount: parseFloat(itemForm.value.totalAmount),
  });
  itemForm.value = {
    category: "",
    itemName: "",
    itemId: null,
    unitPrice: 0,
    quantity: 1,
    totalAmount: 0,
  };
  updateSummary();
  nextTick(() => {
    itemNameInput.value.focus();
  });
};

const removeItem = (index) => {
  items.value.splice(index, 1);
  updateSummary();
};

const updateSummary = () => {
  const total = items.value.reduce((sum, item) => sum + item.totalAmount, 0);
  summary.value.total = parseFloat(total.toFixed(2));
  let discountAmount = 0;
  if (summary.value.discountType === "percentage") {
    discountAmount = (summary.value.total * summary.value.discount) / 100;
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
      netAmount: parseFloat((item.totalAmount - itemDistributedDiscount).toFixed(2)),
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
  // Calculate return amount (difference between taking amount and receiving amount)
  summary.value.returnAmt = Math.max(0, takingAmount - receivingAmount);
  
  // Calculate change and due
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
    const paidAmount = parseFloat(summary.value.paidAmt) || 0;
    if (props.id && props.editData) {
      // In edit mode, we need to consider the original paid amount
      const originalPaidAmount = parseFloat(props.editData.paid_amt) || 0;
      if (receivingAmount > 0) {
        // Calculate new paid amount as original paid + receiving amount
        const newPaidAmount = originalPaidAmount + receivingAmount;
        summary.value.paidAmt = Math.min(newPaidAmount, summary.value.payableAmount);
        summary.value.paidAmt = parseFloat(summary.value.paidAmt.toFixed(2));
      } else {
        // If receiving amount is empty or 0, keep the original paid amount
        summary.value.paidAmt = originalPaidAmount;
      }
    } else {
      // Normal mode behavior
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
      cardNumber: props.editData.card_number || "",
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
        netAmount: parseFloat(item.net_amount),
      }));
    }
    const paidAmount = parseFloat(props.editData.paid_amt || 0);
    const dueAmount = parseFloat(props.editData.due_amount || 0);
    const payableAmount = parseFloat(props.editData.payable_amount || 0);
    // Calculate receiving amount based on edit mode logic
    let receivingAmount = "";
    if (dueAmount > 0) {
      // If there's a due amount, receiving amount should be empty to allow adding more payment
      receivingAmount = "";
    } else if (paidAmount >= payableAmount) {
      // If fully paid, receiving amount should show the paid amount
      receivingAmount = paidAmount.toFixed(2);
    } else {
      // For partial payments, show the paid amount as receiving amount
      receivingAmount = paidAmount.toFixed(2);
    }
    summary.value = {
      total: parseFloat(props.editData.total || 0),
      discount: parseFloat(props.editData.discount || 0),
      discountType: props.editData.discount_type || "percentage",
      payableAmount: payableAmount,
      paidAmt: paidAmount,
      changeAmt: parseFloat(props.editData.change_amt || 0),
      dueAmount: dueAmount,
      receivingAmt: receivingAmount,
      deliveryDate: props.editData.delivery_date || "",
      remarks: props.editData.remarks || "",
    };
    commission.value = {
      total: parseFloat(props.editData.commission_total || 0),
      physystAmt: parseFloat(props.editData.physyst_amt || 0),
      slider: parseInt(props.editData.commission_slider || 0),
      referrer_id: props.editData.referrer_id || "",
      commissionRate: parseInt(props.editData.commission_slider || 0),
    };
  }
};

if (props.id && props.editData) {
  initializeEditMode();
}

const saveBill = () => {
  console.log('=== SAVE BILL DEBUG ===');
  console.log('isNewPatient:', isNewPatient.value);
  console.log('patientForm.patient_id:', patientForm.value.patient_id);
  console.log('newPatientForm:', newPatientForm.value);
  console.log('patientSearchQuery:', patientSearchQuery.value);
  if (items.value.length === 0) {
    displayWarning({ message: "Please add at least one item to the bill." });
    return;
  }
  const isCreatingNewPatient = !patientForm.value.patient_id && (isNewPatient.value || patientSearchQuery.value.trim());
  console.log('isCreatingNewPatient:', isCreatingNewPatient);
  if (isCreatingNewPatient) {
    console.log('Validating new patient data...');
    // Check patient details in the fields that the user is actually entering
    if (!patientSearchQuery.value.trim() || !patientForm.value.patientMobile?.trim() || !patientForm.value.gender) {
      console.log('New patient form validation failed');
      displayWarning({ message: "Please fill in all patient details (name, phone, gender)." });
      return;
    }
  } else if (!patientForm.value.patient_id) {
    displayWarning({ message: "Please select a patient or create a new one." });
    return;
  }
  if (!patientForm.value.gender) {
    displayWarning({ message: "Patient gender is required." });
    return;
  }
  let paymentStatus = "Pending";
  if (summary.value.paidAmt >= summary.value.payableAmount) {
    paymentStatus = "Paid";
  } else if (summary.value.paidAmt > 0) {
    paymentStatus = "Partial";
  }
  const itemsForBackend = items.value.map((item) => ({
    id: item.id,
    name: item.name,
    category: item.category,
    unit_price: item.unitPrice,
    quantity: item.quantity,
    total_amount: item.totalAmount,
    discount: item.discount,
    rugound: item.rugound || 0,
    net_amount: item.netAmount,
  }));
  const formData = {
    patient_id: patientForm.value.patient_id || null,
    doctor_id: patientForm.value.doctor_id || null,
    patient_mobile: patientForm.value.patientMobile,
    gender: patientForm.value.gender,
    card_type: patientForm.value.cardType,
    pay_mode: patientForm.value.payMode,
    card_number: patientForm.value.cardNumber || null,
    items: itemsForBackend,
    total: summary.value.total,
    discount: summary.value.discount,
    discount_type: summary.value.discountType,
    payable_amount: summary.value.payableAmount,
    paid_amt: summary.value.paidAmt,
    change_amt: summary.value.changeAmt,
    due_amount: summary.value.dueAmount,
    receiving_amt: summary.value.receivingAmt,
    delivery_date: summary.value.deliveryDate || null,
    remarks: summary.value.remarks || null,
    payment_status: paymentStatus,
    commission_total: commission.value.total,
    physyst_amt: commission.value.physystAmt,
    commission_slider: commission.value.commissionRate,
    referrer_id: commission.value.referrer_id || null,
  };
  // Add new patient data if creating new patient
  if (isCreatingNewPatient) {
    console.log('Adding new patient data to formData...');
    // Use the actual field values that the user entered
    formData.patient_name = patientSearchQuery.value.trim();
    formData.patient_phone = patientForm.value.patientMobile;
    formData.patient_gender = patientForm.value.gender;
    console.log('New Patient Data Added:', { patient_name: formData.patient_name, patient_phone: formData.patient_phone, patient_gender: formData.patient_gender });
  }
  console.log('Complete Form Data:', formData);
  const form = useForm(formData);
  // Open blank window synchronously to avoid popup blockers when navigating later
  let invoiceWindow = null;
  try {
    invoiceWindow = window.open('', '_blank');
  } catch (e) {
    invoiceWindow = null;
  }
  if (props.id) {
    form.put(route("backend.billing.update", props.id), {
      onSuccess: (response) => {
        displayResponse(response);
        const successMessage = response?.props?.flash?.successMessage;
        const billId = response?.props?.flash?.billId;
        if (billId) {
          resetAllForms();
          const invoiceUrl = route("backend.download.invoice", { id: billId, module: 'billing' });
          try {
            if (invoiceWindow && !invoiceWindow.closed) {
              invoiceWindow.location = invoiceUrl;
            } else {
              window.open(invoiceUrl, '_blank');
            }
          } catch (e) {
            window.open(invoiceUrl, '_blank');
          }
        }
      },
      onError: (errorObject) => {
        console.error('Validation Errors:', errorObject);
        displayWarning(errorObject);
      },
    });
  } else {
    form.post(route("backend.billing.store"), {
      onSuccess: (response) => {
        // resetAllForms();
        displayResponse(response);
        const successMessage = response?.props?.flash?.successMessage;
        const billId = response?.props?.flash?.billId;
        if (billId) {
          // for download pdf
          /* const link = document.createElement("a");
          link.href = route("backend.download.invoice", billId);
          link.setAttribute("download", "invoice.pdf");
          document.body.appendChild(link);
          document.body.removeChild(link); */
          // for view pdf in new tab
          resetAllForms();
          const invoiceUrl = route("backend.download.invoice", { id: billId, module: 'billing' });
          try {
            if (invoiceWindow && !invoiceWindow.closed) {
              invoiceWindow.location = invoiceUrl;
            } else {
              window.open(invoiceUrl, '_blank');
            }
          } catch (e) {
            window.open(invoiceUrl, '_blank');
          }
        }
      },
      onError: (errorObject) => {
        console.error('Validation Errors:', errorObject);
        displayWarning(errorObject);
      },
    });
  }
};

const resetAllForms = () => {
  items.value = [];
  patientForm.value = {
    patient_id: "",
    doctor_id: "",
    patientMobile: "",
    gender: "",
    cardType: "Cash",
    payMode: "Cash",
    cardNumber: "",
  };
  summary.value = {
    total: 0,
    discount: 0,
    discountType: "percentage",
    extraFlatDiscount: 0,
    payableAmount: 0,
    paidAmt: 0,
    changeAmt: 0.0,
    dueAmount: 0.0,
    receivingAmt: 0.0,
    takingAmt: 0.0,
    returnAmt: 0.0,
    deliveryDate: new Date().toISOString().split('T')[0], // Set to current date
    remarks: "",
  };
  commission.value = {
    total: 0.0,
    physystAmt: 0.0,
    slider: 0,
    referrer_id: "",
    commissionRate: 0,
  };
  // Reset new patient form
  isNewPatient.value = false;
  newPatientForm.value = {
    name: "",
    phone: "",
    gender: "",
  };
  patientSearchQuery.value = "";
  showPatientDropdown.value = false;
};

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
    type: "test",
  }));
  const medicines = props.medicineInventories
    .filter((medicine) => medicine.status === "Active")
    .map((medicine) => ({
      id: medicine.id,
      name: medicine.medicine_name,
      category: "Medicine",
      unitPrice: medicine.medicine_unit_selling_price,
      stock: medicine.medicine_quantity,
      type: "medicine",
    }));
  return [...tests, ...medicines];
});

const filteredItems = computed(() => {
  const query = searchQuery.value.toLowerCase();
  let itemsToFilter = [];
  if (itemForm.value.category) {
    if (itemForm.value.category.toLowerCase() === "medicine") {
      itemsToFilter = props.medicineInventories
        .filter((medicine) => medicine.status === "Active")
        .map((medicine) => ({
          id: medicine.id,
          name: medicine.medicine_name,
          category: "Medicine",
          unitPrice: medicine.medicine_unit_selling_price,
          stock: medicine.medicine_quantity,
          type: "medicine",
        }));
    } else {
      itemsToFilter = props.pathologyAndRadiologyTests
        .filter(
          (test) => test.category_type.toLowerCase() === itemForm.value.category.toLowerCase()
        )
        .map((test) => ({
          id: test.id,
          name: test.test_name,
          category: test.category_type,
          unitPrice: test.amount,
          type: "test",
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

const selectItem = (item) => {
  itemForm.value.category = item.category;
  itemForm.value.itemName = item.name;
  searchQuery.value = item.name; // Also update search query for display
  itemForm.value.itemId = item.id;
  itemForm.value.unitPrice = parseFloat(item.unitPrice);
  if (!itemForm.value.quantity || itemForm.value.quantity <= 0) {
    itemForm.value.quantity = 1;
  }
  itemForm.value.totalAmount = (
    itemForm.value.quantity * itemForm.value.unitPrice
  ).toFixed(2);
  searchQuery.value = "";
  selectedIndex.value = -1;
  nextTick(() => {
    quantityInput.value.focus();
    quantityInput.value.select();
  });
};

const handleKeyDown = (event, fieldName) => {
  if (fieldName === "itemName") {
    if (event.key === "ArrowDown") {
      event.preventDefault();
      selectedIndex.value = Math.min(
        selectedIndex.value + 1,
        filteredItems.value.length - 1
      );
    } else if (event.key === "ArrowUp") {
      event.preventDefault();
      selectedIndex.value = Math.max(selectedIndex.value - 1, 0);
    } else if (event.key === "Enter") {
      event.preventDefault();
      if (selectedIndex.value !== -1 && filteredItems.value[selectedIndex.value]) {
        selectItem(filteredItems.value[selectedIndex.value]);
      }
      searchQuery.value = "";
    } else if (event.key === "Escape") {
      selectedIndex.value = -1;
      searchQuery.value = "";
    }
  } else if (fieldName === "quantity" && event.key === "Enter") {
    event.preventDefault();
    addItem();
  }
};

const focusItemList = () => {
  // console.count("trigger from item list");
};

const cancelBill = () => {
  router.visit(route("backend.billing.Page"));
};

const openListBillButton = () => {
  router.visit(route("backend.billing.list"));
};
</script>

<template>
  <Head :title="$page.props.pageTitle" />
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900 overflow-y-auto">
    <div class="w-full p-2">
      <div class="bg-white rounded-lg shadow-lg dark:bg-slate-900 mb-4">
        <div class="mb-3">
          <div class="flex justify-between items-center bg-[#053855] text-white px-3 py-2 text-xs font-semibold rounded-t-lg">
            <div class="flex-1">ITEM DETAILS</div>
            <div class="flex items-center space-x-2">
              <button @click="openListBillButton" class="flex items-center px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-md text-xs transition-colors duration-200 shadow-sm" title="Billing List">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
                Billing List
              </button>
              <button @click="cancelBill" class="flex items-center justify-center w-6 h-6 bg-red-500 hover:bg-red-600 text-white rounded-full text-xs transition-colors duration-200" title="Cancel">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>
          <div class="border border-gray-300 border-t-0 p-3 bg-gray-50 dark:bg-slate-800 dark:border-gray-600">
            <div class="flex items-center space-x-4 text-xs mb-3">
              <span class="font-medium text-gray-700 dark:text-gray-300"><strong>UNIT:</strong> Toamedm Ltd.</span>
              <span class="font-medium text-gray-700 dark:text-gray-300"><strong>Counter:</strong> {{ authInfo?.department?.name ?? "" }} </span>
              <span class="font-medium text-gray-700 dark:text-gray-300"><strong>Sales Person:</strong> {{ authInfo?.admin?.name ?? "" }} </span>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-2 items-end">
              <div class="lg:col-span-2">
                <InputLabel for="category" value="Category" class="text-xs mb-1" />
                <select v-model="itemForm.category" id="category" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300">
                  <option value="" disabled>Select Category</option>
                  <option value="Pathology">Pathology</option>
                  <option value="Radiology">Radiology</option>
                  <option value="Medicine">Medicine</option>
                </select>
              </div>
              <div class="lg:col-span-5 relative">
                <InputLabel for="itemName" value="Item Name" class="text-xs mb-1" />
                <input
                  v-model="searchQuery"
                  @focus="showItemDropdown = true"
                  @blur="handleItemSearchBlur"
                  @keydown="handleKeyDown($event, 'itemName')"
                  type="text"
                  id="itemName"
                  ref="itemNameInput"
                  autocomplete="off"
                  class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300"
                  placeholder="Search for item..."
                />
                <ul
                  v-if="filteredItems.length > 0 && searchQuery.length > 0"
                  class="absolute z-10 w-full mt-1 max-h-48 overflow-y-auto bg-white border border-gray-300 rounded-md shadow-lg dark:bg-slate-800 dark:border-gray-600 dark:text-gray-300"
                >
                  <li
                    v-for="(item, index) in filteredItems"
                    :key="item.id"
                    :ref="index === selectedIndex ? 'selectedItemRef' : null"
                    @click="selectItem(item)"
                    @mouseover="selectedIndex = index"
                    class="p-2 text-xs cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-700"
                    :class="{ 'bg-blue-100 dark:bg-blue-800': index === selectedIndex }"
                  >
                    {{ item.name }}
                    <span v-if="item.category === 'Medicine'" class="text-gray-500 dark:text-gray-400">
                      - Stock: {{ item.stock }}
                    </span>
                  </li>
                </ul>
              </div>
              <div class="lg:col-span-1">
                <InputLabel for="unitPrice" value="Price" class="text-xs mb-1" />
                <input
                  v-model="itemForm.unitPrice"
                  type="number"
                  id="unitPrice"
                  class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300"
                  readonly
                />
              </div>
              <div class="lg:col-span-1">
                <InputLabel for="quantity" value="Qty" class="text-xs mb-1" />
                <input
                  v-model="itemForm.quantity"
                  @keydown="handleKeyDown($event, 'quantity')"
                  type="number"
                  step="any"
                  id="quantity"
                  ref="quantityInput"
                  min="0"
                  class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300"
                />
              </div>
              <div class="lg:col-span-2">
                <InputLabel for="totalAmount" value="Amount" class="text-xs mb-1" />
                <input
                  v-model="itemForm.totalAmount"
                  type="number"
                  id="totalAmount"
                  class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300"
                  readonly
                />
              </div>
              <div class="lg:col-span-1 flex items-center justify-center">
                <button
                  @click.prevent="addItem"
                  type="button"
                  class="mt-4 px-3 py-1.5 bg-green-500 hover:bg-green-600 text-white rounded-md text-xs transition-colors duration-200 shadow-sm"
                  title="Add Item"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>
        <div v-if="items.length > 0" class="max-h-custom overflow-y-auto mb-4 bg-white dark:bg-slate-900 border-b border-gray-300 dark:border-gray-600">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600 text-xs">
              <thead class="bg-gray-50 dark:bg-slate-800 sticky top-0">
                <tr>
                  <th scope="col" class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                    #
                  </th>
                  <th scope="col" class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                    Item Name
                  </th>
                  <th scope="col" class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                    Category
                  </th>
                  <th scope="col" class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                    Price
                  </th>
                  <th scope="col" class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                    Qty
                  </th>
                  <th scope="col" class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                    Amount
                  </th>
                  <th scope="col" class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                    Discount
                  </th>
                  <th scope="col" class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                    Net Amount
                  </th>
                  <th scope="col" class="px-3 py-2 text-center font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                    Action
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-slate-900 divide-y divide-gray-200 dark:divide-gray-600">
                <tr v-for="(item, index) in items" :key="index">
                  <td class="px-3 py-2 whitespace-nowrap">{{ index + 1 }}</td>
                  <td class="px-3 py-2 whitespace-nowrap">{{ item.name }}</td>
                  <td class="px-3 py-2 whitespace-nowrap">{{ item.category }}</td>
                  <td class="px-3 py-2 whitespace-nowrap">{{ item.unitPrice }}</td>
                  <td class="px-3 py-2 whitespace-nowrap">{{ item.quantity }}</td>
                  <td class="px-3 py-2 whitespace-nowrap">{{ item.totalAmount }}</td>
                  <td class="px-3 py-2 whitespace-nowrap">{{ item.discount }}</td>
                  <td class="px-3 py-2 whitespace-nowrap">{{ item.netAmount }}</td>
                  <td class="px-3 py-2 whitespace-nowrap text-center">
                    <button @click.prevent="removeItem(index)" class="text-red-600 hover:text-red-900 transition-colors duration-200" title="Remove Item">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2">
          <div class="bg-white rounded-lg shadow-lg dark:bg-slate-900 mb-4">
            <div class="mb-3">
              <div class="flex justify-between items-center bg-[#053855] text-white px-3 py-2 text-xs font-semibold rounded-t-lg">
                PATIENT DETAILS
              </div>
              <div class="border border-gray-300 border-t-0 p-3 bg-gray-50 dark:bg-slate-800 dark:border-gray-600">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-2 text-xs">
                  <div class="relative">
                    <InputLabel for="patientSearch" value="Patient Name" class="mb-1" />
                    <input
                      v-model="patientSearchQuery"
                      @keydown="handlePatientSearchKeyDown"
                      @blur="handlePatientSearchBlur"
                      @focus="showPatientDropdown = true"
                      type="text"
                      id="patientSearch"
                      ref="patientSearchInput"
                      autocomplete="off"
                      class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300"
                      placeholder="Search or create new patient..."
                    />
                    <ul
                      v-if="showPatientDropdown && filteredPatients.length > 0"
                      class="absolute z-10 w-full mt-1 max-h-48 overflow-y-auto bg-white border border-gray-300 rounded-md shadow-lg dark:bg-slate-800 dark:border-gray-600 dark:text-gray-300"
                    >
                      <li
                        v-for="(patient, index) in filteredPatients"
                        :key="patient.id"
                        @click="selectPatient(patient)"
                        @mouseover="patientSelectedIndex = index"
                        class="p-2 cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-700"
                        :class="{ 'bg-blue-100 dark:bg-blue-800': index === patientSelectedIndex }"
                      >
                        {{ patient.name }} ({{ patient.phone }})
                      </li>
                    </ul>
                    <div v-if="patientSearchQuery && filteredPatients.length === 0" class="mt-2 text-center">
                      <button @click="createNewPatient" type="button" class="px-2 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded-md text-xs transition-colors duration-200">
                        Create New Patient
                      </button>
                    </div>
                  </div>
                  <div>
                    <InputLabel for="doctor" value="Doctor" class="mb-1" />
                    <select
                      v-model="patientForm.doctor_id"
                      id="doctor"
                      ref="doctorSelectRef"
                      @keydown="handleDoctorKeyDown"
                      @change="handleDoctorChange"
                      @focus="isDoctorDropdownOpen = true"
                      @blur="isDoctorDropdownOpen = false"
                      class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300"
                    >
                      <option value="" disabled>Select Doctor</option>
                      <option v-for="doctor in doctors" :key="doctor.id" :value="doctor.id">
                        {{ doctor.name }}
                      </option>
                    </select>
                  </div>
                  <div>
                    <InputLabel for="patientMobile" value="Patient Phone" class="mb-1" />
                    <input
                      v-model="patientForm.patientMobile"
                      @keydown="handlePatientMobileKeyDown"
                      type="text"
                      id="patientMobile"
                      ref="patientMobileInput"
                      autocomplete="off"
                      class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300"
                      :readonly="patientForm.patient_id ? true : false"
                    />
                  </div>
                  <div>
                    <InputLabel for="gender" value="Gender" class="mb-1" />
                    <select
                      v-model="patientForm.gender"
                      id="gender"
                      ref="genderSelectRef"
                      @keydown="handleGenderKeyDown"
                      @change="handleGenderChange"
                      @focus="isGenderDropdownOpen = true"
                      @blur="isGenderDropdownOpen = false"
                      class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300"
                      :readonly="patientForm.patient_id ? true : false"
                    >
                      <option value="" disabled>Select Gender</option>
                      <option value="Male">Male</option>
                      <option value="Female">Female</option>
                      <option value="Other">Other</option>
                    </select>
                  </div>
                  <div>
                    <InputLabel for="payMode" value="Pay Mode" class="mb-1" />
                    <select
                      v-model="patientForm.payMode"
                      id="payMode"
                      ref="payModeSelectRef"
                      @keydown="handlePayModeKeyDown"
                      class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300"
                    >
                      <option value="Cash">Cash</option>
                      <option value="Card">Card</option>
                      <option value="Mobile Banking">Mobile Banking</option>
                    </select>
                  </div>
                  <div v-if="patientForm.payMode !== 'Cash'">
                    <InputLabel for="cardNumber" value="Card / Mobile No." class="mb-1" />
                    <input
                      v-model="patientForm.cardNumber"
                      @keydown="handleCardNumberKeyDown"
                      type="text"
                      id="cardNumber"
                      ref="cardNumberInputRef"
                      class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300"
                    />
                  </div>
                </div>
              </div>
            </div>
            <div class="mb-3">
              <div class="flex justify-between items-center bg-[#053855] text-white px-3 py-2 text-xs font-semibold">
                COMMISSION DETAILS
              </div>
              <div class="border border-gray-300 border-t-0 p-3 bg-gray-50 dark:bg-slate-800 dark:border-gray-600">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-2 text-xs">
                  <div>
                    <InputLabel for="referrer" value="Referrer" class="mb-1" />
                    <select
                      v-model="commission.referrer_id"
                      id="referrer"
                      ref="referrerSelectRef"
                      @keydown="handleReferrerKeyDown"
                      class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300"
                    >
                      <option value="">No Referrer</option>
                      <option v-for="referrer in referrers" :key="referrer.id" :value="referrer.id">
                        {{ referrer.name }}
                      </option>
                    </select>
                  </div>
                  <div>
                    <InputLabel for="physyst_amt" value="Physyst Amount" class="mb-1" />
                    <input
                      v-model="commission.physystAmt"
                      type="text"
                      id="physyst_amt"
                      class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 cursor-not-allowed"
                      readonly
                    />
                  </div>
                  <div>
                    <InputLabel for="commissionRate" value="Commission Rate (%)" class="mb-1" />
                    <input
                      v-model="commission.commissionRate"
                      type="number"
                      id="commissionRate"
                      class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300"
                      :readonly="!commissionDetails.manualCommissionEnabled"
                    />
                  </div>
                  <div class="col-span-1 md:col-span-2 lg:col-span-4">
                    <p v-if="commissionDetails.noCommissionMessage" class="text-red-500 text-xs mt-2">
                      {{ commissionDetails.noCommissionMessage }}
                    </p>
                    <p v-if="commissionBreakdown.length > 0" class="mt-2 text-xs text-gray-700 dark:text-gray-300">
                      <strong>Breakdown:</strong>
                      <span v-for="(item, index) in commissionBreakdown" :key="index" class="ml-2">
                        {{ item.category }}: {{ item.commission.toFixed(2) }} ({{ item.rate }}%)
                      </span>
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="lg:col-span-1">
          <div class="bg-white rounded-lg shadow-lg dark:bg-slate-900 mb-4">
            <div class="mb-3">
              <div class="flex justify-between items-center bg-[#053855] text-white px-3 py-2 text-xs font-semibold rounded-t-lg">
                TOTAL SUMMARY
              </div>
              <div class="border border-gray-300 border-t-0 p-3 bg-gray-50 dark:bg-slate-800 dark:border-gray-600">
                <div class="grid grid-cols-1 gap-2 text-xs">
                  <div class="flex justify-between items-center py-1">
                    <InputLabel for="total" value="Total Amount" />
                    <input
                      v-model="summary.total"
                      type="text"
                      id="total"
                      class="w-2/3 px-2 py-1.5 border border-gray-300 rounded text-xs text-right focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 cursor-not-allowed"
                      readonly
                    />
                  </div>
                  <div class="flex items-center gap-2">
                    <InputLabel for="discount" value="Discount" class="flex-1" />
                    <div class="flex-1">
                      <select v-model="summary.discountType" id="discountType" ref="discountTypeSelectRef" @keydown="handleDiscountTypeKeyDown" @change="handleDiscountTypeChange" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300">
                        <option value="percentage">%</option>
                        <option value="flat">Flat</option>
                      </select>
                    </div>
                    <div class="flex-1">
                      <input
                        v-model="summary.discount"
                        @keydown="handleDiscountKeyDown"
                        type="number"
                        min="0"
                        id="discount"
                        ref="discountInputRef"
                        class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs text-right focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300"
                      />
                    </div>
                  </div>
                  <div class="flex justify-between items-center py-1">
                    <InputLabel for="extraFlatDiscount" value="Extra Flat Discount" />
                    <input
                      v-model="summary.extraFlatDiscount"
                      @keydown="handleExtraFlatDiscountKeyDown"
                      type="number"
                      min="0"
                      id="extraFlatDiscount"
                      ref="extraFlatDiscountInputRef"
                      class="w-2/3 px-2 py-1.5 border border-gray-300 rounded text-xs text-right focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300"
                    />
                  </div>
                  <div class="flex justify-between items-center py-1 font-semibold text-base">
                    <InputLabel for="payableAmount" value="Payable Amount" />
                    <input
                      v-model="summary.payableAmount"
                      type="text"
                      id="payableAmount"
                      class="w-2/3 px-2 py-1.5 border border-gray-300 rounded text-xs text-right focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 cursor-not-allowed"
                      readonly
                    />
                  </div>
                  <div class="flex justify-between items-center py-1">
                    <InputLabel for="takingAmt" value="Taking Amount" />
                    <input
                      v-model="summary.takingAmt"
                      @keydown="handleTakingAmtKeyDown"
                      type="number"
                      min="0"
                      id="takingAmt"
                      ref="takingAmtInputRef"
                      class="w-2/3 px-2 py-1.5 border border-gray-300 rounded text-xs text-right focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300"
                    />
                  </div>
                  <div class="flex justify-between items-center py-1">
                    <InputLabel for="receivingAmt" value="Receiving Amount" />
                    <input
                      v-model="summary.receivingAmt"
                      @keydown="handleReceivingAmtKeyDown"
                      type="number"
                      min="0"
                      id="receivingAmt"
                      ref="receivingAmtInputRef"
                      class="w-2/3 px-2 py-1.5 border border-gray-300 rounded text-xs text-right focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300"
                      :readonly="props.id && props.editData && summary.dueAmount === 0"
                    />
                  </div>
                  <div class="flex justify-between items-center py-1">
                    <InputLabel for="changeAmt" value="Change Amount" />
                    <input
                      v-model="summary.changeAmt"
                      type="text"
                      id="changeAmt"
                      class="w-2/3 px-2 py-1.5 border border-gray-300 rounded text-xs text-right focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 cursor-not-allowed"
                      readonly
                    />
                  </div>
                  <div class="flex justify-between items-center py-1">
                    <InputLabel for="dueAmount" value="Due Amount" />
                    <input
                      v-model="summary.dueAmount"
                      type="text"
                      id="dueAmount"
                      class="w-2/3 px-2 py-1.5 border border-gray-300 rounded text-xs text-right focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 cursor-not-allowed"
                      readonly
                    />
                  </div>
                  <div class="flex justify-between items-center py-1">
                    <InputLabel for="returnAmt" value="Return Amount" />
                    <input
                      v-model="summary.returnAmt"
                      type="text"
                      id="returnAmt"
                      class="w-2/3 px-2 py-1.5 border border-gray-300 rounded text-xs text-right focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 cursor-not-allowed"
                      readonly
                    />
                  </div>
                  <div class="flex justify-between items-center py-1">
                    <InputLabel for="deliveryDate" value="Delivery Date" />
                    <input
                      v-model="summary.deliveryDate"
                      @keydown="handleDeliveryDateKeyDown"
                      type="date"
                      id="deliveryDate"
                      ref="deliveryDateInputRef"
                      class="w-2/3 px-2 py-1.5 border border-gray-300 rounded text-xs text-right focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300"
                    />
                  </div>
                  <div class="flex flex-col py-1">
                    <InputLabel for="remarks" value="Remarks" />
                    <textarea
                      v-model="summary.remarks"
                      @keydown="handleRemarksKeyDown"
                      id="remarks"
                      ref="remarksTextareaRef"
                      rows="2"
                      class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-300"
                    ></textarea>
                  </div>
                  <div class="mt-4">
                    <button @click.prevent="saveBill" type="button" class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-md transition-colors duration-200" ref="saveBillButtonRef">
                      Save Bill
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <PatientModal :show="isPatientModalOpen" @close="closePatientModal" @patient-created="handlePatientCreated" />
  </div>
</template>

<style scoped>
/* Scoped styles specific to this component */
.select-trigger {
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
}
/* You may need to add more styles to handle the custom dropdown appearance if not using a library */
/* Utility Classes for this project */
.text-xs {
  font-size: 0.75rem;
}
.rounded-lg {
  border-radius: 0.5rem;
}
.shadow-lg {
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}
.bg-gray-50 {
  background-color: #f9fafb;
}
.bg-white {
  background-color: #ffffff;
}
.bg-[#053855] {
  background-color: #053855;
}
.dark .bg-slate-900 {
  background-color: #0f172a;
}
.dark .bg-slate-800 {
  background-color: #1e293b;
}
.dark .bg-slate-700 {
  background-color: #334155;
}
.dark .bg-blue-800 {
  background-color: #1e40af !important;
}
.dark .border-gray-600 {
  border-color: #4b5563;
}
.dark .text-gray-300 {
  color: #d1d5db;
}
.dark .text-gray-400 {
  color: #9ca3af;
}
.dark .hover\:bg-slate-700:hover {
  background-color: #334155 !important;
}
.dark .bg-red-200 {
  background-color: #fca5a5 !important;
}
/* Hover effects */
button:hover {
  transform: translateY(-1px);
  transition: all 0.2s ease;
}
select:hover,
input:hover {
  border-color: #6b7280;
}
/* Scrollbar styling */
::-webkit-scrollbar {
  width: 10px;
  height: 10px;
}
::-webkit-scrollbar-track {
  background: color-mix(in srgb, var(--app-theme-soft) 26%, #e2e8f0);
  border-radius: 8px;
}
::-webkit-scrollbar-thumb {
  background: color-mix(in srgb, var(--app-theme-primary) 40%, #94a3b8);
  border-radius: 8px;
}
::-webkit-scrollbar-thumb:hover {
  background: color-mix(in srgb, var(--app-theme-primary) 56%, #64748b);
}
/* Dynamic height for item list */
.max-h-custom {
  max-height: calc(100vh - 500px);
  min-height: 100px;
}
/* Ensure main container allows scrolling */
.min-h-screen {
  overflow-y: auto;
  height: 100vh;
}
/* Make sure the grid layout doesn't restrict height */
.grid.grid-cols-1.lg\:grid-cols-3 {
  align-items: flex-start;
  min-height: 0;
}
</style>