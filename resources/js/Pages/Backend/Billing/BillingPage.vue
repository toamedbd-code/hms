<script setup>
import { ref, computed, watch, nextTick, onMounted } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import InputLabel from "@/Components/InputLabel.vue";
import { Head } from "@inertiajs/vue3";
import PatientModal from "@/Components/PatientModal.vue";
import { displayResponse, displayWarning } from "@/responseMessage.js";
import { debounce } from 'lodash';
import { parse, format, isValid, differenceInYears, differenceInMonths, differenceInDays, subYears, subMonths, subDays } from 'date-fns';

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
  billing: Object,
  billingDoctors: Array,
});

const isPatientModalOpen = ref(false);
const patientsList = ref([...props.patients]);

// Refs for form fields to enable Enter key navigation
const patientSearchRef = ref(null);
const doctorSearchRef = ref(null);
const patientMobileRef = ref(null);
const genderSelectRef = ref(null);
const payModeRef = ref(null);
const cardNumberRef = ref(null);
const discountRef = ref(null);
const extraDiscountRef = ref(null);
const takingAmtRef = ref(null);
const receivingAmtRef = ref(null);
const deliveryDateRef = ref(null);
const deliveryTimeRef = ref(null);
const remarksRef = ref(null);
const referrerSelectRef = ref(null);
const commissionSliderRef = ref(null);
const ageYears = ref('');
const ageMonths = ref('');
const ageDays = ref('');
const ageYearsInput = ref(null);
const ageMonthsInput = ref(null);
const ageDaysInput = ref(null);
const dobInput = ref(null);
const updatingFrom = ref(null);
const isEditMode = ref(false);

const doctorSearchQuery = ref("");
const doctorSelectedIndex = ref(-1);
const showDoctorDropdown = ref(false);
const filteredDoctors = ref([]);
const isDoctorLoading = ref(false);
const isNewPatientFlag = ref(false);

// Initialize all reactive variables first
const patientSearchQuery = ref("");
const patientSelectedIndex = ref(-1);
const showPatientDropdown = ref(false);
const filteredPatients = ref([]);
const isNewPatient = ref(false);
const newPatientForm = ref({
  name: "",
  phone: "",
  gender: "",
  dob: "",
});

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
  patientMobile: "",
  gender: "",
  dob: "",
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
  deliveryTime: "",
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

const itemNameInput = ref(null);
const quantityInput = ref(null);
const selectedItemRef = ref(null);
const searchQuery = ref("");
const selectedIndex = ref(-1);

const getCurrentDateTime = () => {
  const now = new Date();
  const date = format(now, 'yyyy-MM-dd');
  const time = format(now, 'HH:mm');
  return { date, time };
};

const setCurrentDeliveryDateTime = () => {
  const now = new Date();

  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');
  const hours = String(now.getHours()).padStart(2, '0');
  const minutes = String(now.getMinutes()).padStart(2, '0');

  const datetimeLocal = `${year}-${month}-${day}T${hours}:${minutes}`;

  summary.value.deliveryDate = datetimeLocal;

  summary.value.deliveryTime = `${hours}:${minutes}`;
};

const openDropdown = (selectRef) => {
  if (selectRef && selectRef.value) {
    selectRef.value.focus();

    nextTick(() => {
      try {
        if (typeof selectRef.value.showPicker === 'function') {
          selectRef.value.showPicker();
          return;
        }
      } catch (error) {
        console.log('showPicker not available, trying alternative methods');
      }

      try {
        const event = new MouseEvent('mousedown', {
          bubbles: true,
          cancelable: true,
          view: window
        });
        selectRef.value.dispatchEvent(event);
      } catch (error) {
        console.log('MouseEvent dispatch failed');
      }

      try {
        const spaceEvent = new KeyboardEvent('keydown', {
          key: ' ',
          code: 'Space',
          keyCode: 32,
          which: 32,
          bubbles: true,
          cancelable: true
        });
        selectRef.value.dispatchEvent(spaceEvent);
      } catch (error) {
        console.log('Keyboard event simulation failed');
      }
    });
  }
};

const calculateAgeFromDOB = (dob) => {
  if (!dob) {
    ageYears.value = '';
    ageMonths.value = '';
    ageDays.value = '';
    return;
  }

  const birthDate = parse(dob, 'yyyy-MM-dd', new Date());
  if (!isValid(birthDate)) {
    ageYears.value = '';
    ageMonths.value = '';
    ageDays.value = '';
    return;
  }

  const today = new Date();

  let years = differenceInYears(today, birthDate);
  let remainingDate = subYears(today, years);

  let months = differenceInMonths(remainingDate, birthDate);
  remainingDate = subMonths(remainingDate, months);

  let days = differenceInDays(remainingDate, birthDate);

  ageYears.value = years > 0 ? years.toString() : '';
  ageMonths.value = months > 0 ? months.toString() : '';
  ageDays.value = days > 0 ? days.toString() : '';
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
  filteredPatients.value = props.patients.filter(patient =>
    patient.name.toLowerCase().includes(query) ||
    patient.phone.toLowerCase().includes(query)
  );

  showPatientDropdown.value = filteredPatients.value.length > 0;
  patientSelectedIndex.value = -1;
}, 300));

const handlePatientSearchFocus = () => {
  if (patientSearchQuery.value.trim() !== "" && filteredPatients.value.length > 0) {
    showPatientDropdown.value = true;
  }
};

const handlePatientSearchEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    if (patientSelectedIndex.value !== -1 && filteredPatients.value[patientSelectedIndex.value]) {
      selectPatient(filteredPatients.value[patientSelectedIndex.value]);
    } else if (patientSearchQuery.value.trim()) {
      createNewPatientFromSearch();
    } else {
      showPatientDropdown.value = false;
      nextTick(() => {
        setTimeout(() => {
          doctorSearchRef.value?.focus();
          doctorSearchRef.value?.select();
        }, 100);
      });
    }
  }
};

const handleDoctorEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    
    if (doctorSelectedIndex.value !== -1 && filteredDoctors.value[doctorSelectedIndex.value]) {
      selectDoctor(filteredDoctors.value[doctorSelectedIndex.value]);
    } else if (doctorSearchQuery.value.trim()) {
      showDoctorDropdown.value = false;
      nextTick(() => {
        patientMobileRef.value?.focus();
      });
    } else {
      showDoctorDropdown.value = false;
      nextTick(() => {
        patientMobileRef.value?.focus();
      });
    }
  }
};

const focusNextField = (currentRef, nextRef) => {
  nextTick(() => {
    if (nextRef && nextRef.value && typeof nextRef.value.focus === 'function') {
      nextRef.value.focus();
      
      if (nextRef.value.tagName === 'SELECT') {
        setTimeout(() => {
          try {
            if (typeof nextRef.value.showPicker === 'function') {
              nextRef.value.showPicker();
            }
          } catch (error) {
            console.log('Could not open dropdown programmatically');
          }
        }, 50);
      }
    }
  });
};

const handlePatientMobileEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    
    if (isNewPatient.value && patientForm.value.patientMobile) {
      newPatientForm.value.phone = patientForm.value.patientMobile;
    }
    
    nextTick(() => {
      genderSelectRef.value?.focus();
      setTimeout(() => openDropdown(genderSelectRef), 100);
    });
  }
};

const handleGenderEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    
    if (isNewPatient.value && patientForm.value.gender) {
      newPatientForm.value.gender = patientForm.value.gender;
    }
    
    nextTick(() => {
      if (dobInput.value && typeof dobInput.value.focus === 'function') {
        dobInput.value.focus();
      }
    });
  }
};

const handleDobEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    nextTick(() => {
      ageYearsInput.value?.focus();
    });
  }
};

const handleAgeYearsEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    nextTick(() => {
      ageMonthsInput.value?.focus();
    });
  }
};

const handleAgeMonthsEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    nextTick(() => {
      ageDaysInput.value?.focus();
    });
  }
};

const handleAgeDaysEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    nextTick(() => {
      payModeRef.value?.focus();
      setTimeout(() => openDropdown(payModeRef), 100);
    });
  }
};

const handlePayModeEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    if (patientForm.value.payMode !== 'Cash') {
      nextTick(() => {
        if (cardNumberRef.value && typeof cardNumberRef.value.focus === 'function') {
          cardNumberRef.value.focus();
        }
      });
    } else {
      nextTick(() => {
        if (discountRef.value && typeof discountRef.value.focus === 'function') {
          discountRef.value.focus();
        }
      });
    }
  }
};

const handleCardNumberEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    nextTick(() => {
      discountRef.value?.focus();
    });
  }
};

const handleDiscountEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    nextTick(() => {
      extraDiscountRef.value?.focus();
    });
  }
};

const handleExtraDiscountEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    nextTick(() => {
      takingAmtRef.value?.focus();
    });
  }
};

const handleTakingAmtEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    nextTick(() => {
      receivingAmtRef.value?.focus();
    });
  }
};

const handleReceivingAmtEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    nextTick(() => {
      deliveryDateRef.value?.focus();
    });
  }
};

const handleDeliveryDateEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    setCurrentDeliveryDateTime();
    nextTick(() => {
      if (remarksRef.value && typeof remarksRef.value.focus === 'function') {
        remarksRef.value.focus();
      }
    });
  }
};

const handleDeliveryTimeEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();

    setCurrentDeliveryDateTime();

    nextTick(() => {
      if (remarksRef.value && typeof remarksRef.value.focus === 'function') {
        remarksRef.value.focus();
      }
    });
  }
};

const handleRemarksEnter = (event) => {
  if (event.key === "Enter" && !event.shiftKey) {
    event.preventDefault();
    nextTick(() => {
      referrerSelectRef.value?.focus();
      setTimeout(() => openDropdown(referrerSelectRef), 100);
    });
  }
};

const handleReferrerEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    if (commissionDetails.value.manualCommissionEnabled) {
      nextTick(() => {
        if (commissionSliderRef.value && typeof commissionSliderRef.value.focus === 'function') {
          commissionSliderRef.value.focus();
        }
      });
    } else {
      saveBill();
    }
  }
};

const handleSelectClick = (selectRef) => {
  if (selectRef && selectRef.value && typeof selectRef.value.focus === 'function') {
    try {
      selectRef.value.focus();

      setTimeout(() => {
        try {
          if (typeof selectRef.value.showPicker === 'function') {
            selectRef.value.showPicker();
          }
        } catch (error) {
          console.log('Could not open dropdown programmatically');
        }
      }, 50);
    } catch (error) {
      console.log('Could not focus element:', error);
    }
  }
};

const handleSelectKeydown = (event, nextFieldRef) => {
  if (event.key === "Enter") {
    event.preventDefault();
    if (nextFieldRef && nextFieldRef.value && typeof nextFieldRef.value.focus === 'function') {
      nextTick(() => {
        nextFieldRef.value.focus();
      });
    }
  } else if (event.key === "Tab") {
    return;
  }
};

const handleCommissionSliderEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    saveBill();
  }
};

// Modal functions
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
  patientForm.value.dob = patient.dob || '';
  patientSearchQuery.value = patient.name;
  
  showPatientDropdown.value = false;
  patientSelectedIndex.value = -1;
  isNewPatient.value = false;

  calculateAgeFromDOB(patient.dob);

  newPatientForm.value = {
    name: "",
    phone: "",
    gender: "",
    dob: "",
  };

  nextTick(() => {
    setTimeout(() => {
      if (doctorSearchRef.value && typeof doctorSearchRef.value.focus === 'function') {
        doctorSearchRef.value.focus();
        doctorSearchRef.value.select(); 
      }
    }, 100);
  });
};

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

// DOB and Age watchers
watch(() => patientForm.value.dob, (newDob) => {
  if (updatingFrom.value === 'age') return;
  updatingFrom.value = 'dob';

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
      dob: "",
    };
  }
});

watch([ageYears, ageMonths, ageDays], ([years, months, days]) => {
  if (updatingFrom.value === 'dob') return;
  updatingFrom.value = 'age';

  const yearsNum = parseInt(years) || 0;
  const monthsNum = parseInt(months) || 0;
  const daysNum = parseInt(days) || 0;

  if (yearsNum === 0 && monthsNum === 0 && daysNum === 0) {
    patientForm.value.dob = '';
    updatingFrom.value = null;
    return;
  }

  let dobDate = new Date();

  if (yearsNum > 0) dobDate = subYears(dobDate, yearsNum);
  if (monthsNum > 0) dobDate = subMonths(dobDate, monthsNum);
  if (daysNum > 0) dobDate = subDays(dobDate, daysNum);

  if (dobDate > new Date()) {
    patientForm.value.dob = '';
  } else {
    patientForm.value.dob = format(dobDate, 'yyyy-MM-dd');
  }

  updatingFrom.value = null;
}, { deep: true });

const createNewPatient = () => {
  isNewPatient.value = true;
  patientForm.value.patient_id = null;
  newPatientForm.value = {
    name: patientSearchQuery.value.trim() || "",
    phone: "",
    gender: "",
    dob: "",
  };
  showPatientDropdown.value = false;
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
    handlePatientSearchEnter(event);
  } else if (event.key === "Escape") {
    patientSelectedIndex.value = -1;
    showPatientDropdown.value = false;
  }
};

const createNewPatientFromSearch = () => {
  console.log('Creating new patient from search:', patientSearchQuery.value.trim());

  isNewPatient.value = true;
  patientForm.value.patient_id = null;
  
  newPatientForm.value = {
    name: patientSearchQuery.value.trim(),
    phone: "",
    gender: "",
    dob: "",
  };

  patientForm.value.patientMobile = "";
  patientForm.value.gender = "";
  patientForm.value.dob = "";
  ageYears.value = '';
  ageMonths.value = '';
  ageDays.value = '';

  showPatientDropdown.value = false;

  console.log('New patient mode activated with data:', newPatientForm.value);

  nextTick(() => {
    setTimeout(() => {
      if (doctorSearchRef.value && typeof doctorSearchRef.value.focus === 'function') {
        doctorSearchRef.value.focus();
        doctorSearchRef.value.select();
      }
    }, 100);
  });
};

const handleDoctorSearchFocus = () => {
  const hasExactMatch = filteredDoctors.value.some(doctor => 
    doctor.name.toLowerCase() === doctorSearchQuery.value.trim().toLowerCase()
  );
  
  if (doctorSearchQuery.value.trim() !== "" && 
      filteredDoctors.value.length > 0 &&
      !hasExactMatch) {
    showDoctorDropdown.value = true;
  } else {
    showDoctorDropdown.value = false;
  }
};

const handlePatientCreated = (newPatient) => {
  console.log('Patient created:', newPatient);

  patientsList.value.push(newPatient);

  patientForm.value.patient_id = newPatient.id;
  patientForm.value.patientMobile = newPatient.phone;
  patientForm.value.gender = newPatient.gender;
  patientForm.value.dob = newPatient.dob || '';
  patientSearchQuery.value = newPatient.name;

  calculateAgeFromDOB(newPatient.dob);

  isNewPatient.value = false;
  newPatientForm.value = {
    name: "",
    phone: "",
    gender: "",
    dob: "",
  };

  nextTick(() => {
    setTimeout(() => {
      if (doctorSearchRef.value && typeof doctorSearchRef.value.focus === 'function') {
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
    },
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
  console.log('newPatientForm.phone changed to:', newPhone);
  if (isNewPatient.value) {
    patientForm.value.patientMobile = newPhone;
  }
});

watch(() => newPatientForm.value.gender, (newGender) => {
  console.log('newPatientForm.gender changed to:', newGender);
  if (isNewPatient.value) {
    patientForm.value.gender = newGender;
  }
});

watch(() => newPatientForm.value.dob, (newDob) => {
  console.log('newPatientForm.dob changed to:', newDob);
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
  console.log('newPatientForm.name changed to:', newName);
  if (isNewPatient.value && newName) {
    patientSearchQuery.value = newName;
  }
});

watch(() => isNewPatient.value, (newValue) => {
  console.log('isNewPatient changed to:', newValue);
  isNewPatientFlag.value = newValue;

  if (!newValue) {
    console.log('Resetting new patient form...');
    newPatientForm.value = {
      name: "",
      phone: "",
      gender: "",
      dob: "",
    };
    if (!patientForm.value.patient_id) {
      patientForm.value.patientMobile = "";
      patientForm.value.gender = "";
      patientForm.value.dob = "";
      patientSearchQuery.value = "";
      ageYears.value = '';
      ageMonths.value = '';
      ageDays.value = '';
    }
  } else {
    console.log('Entering new patient mode...');
    patientForm.value.patient_id = null;
  }
});

watch(() => itemForm.value.quantity, (newQuantity) => {
  if (newQuantity && itemForm.value.unitPrice) {
    itemForm.value.totalAmount = (newQuantity * itemForm.value.unitPrice).toFixed(2);
  }
});

watch([() => itemForm.value.quantity, () => itemForm.value.unitPrice], () => {
  itemForm.value.totalAmount = (
    itemForm.value.quantity * itemForm.value.unitPrice
  ).toFixed(2);
});

watch(() => patientForm.value.patient_id, (newPatientId) => {
  if (newPatientId && props.patients) {
    const selectedPatient = props.patients.find(
      (patient) => patient.id == newPatientId
    );
    if (selectedPatient) {
      patientForm.value.patientMobile =
        selectedPatient.phone || selectedPatient.mobile || "";
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
  const paidAmount = parseFloat(summary.value.paidAmt) || 0;

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
          (test) =>
            test.category_type.toLowerCase() === itemForm.value.category.toLowerCase()
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
    return {
      ...item,
      discount: 0,
      netAmount: item.totalAmount,
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

const selectItem = (item) => {
  itemForm.value.category = item.category;
  itemForm.value.itemName = item.name;
  searchQuery.value = item.name;
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

const validateNewPatientForm = () => {
  if (!isNewPatient.value) return true;

  const errors = [];
  if (!newPatientForm.value.name?.trim()) errors.push('Patient name is required');
  if (!newPatientForm.value.phone?.trim()) errors.push('Patient phone is required');
  if (!newPatientForm.value.gender) errors.push('Patient gender is required');

  if (errors.length > 0) {
    displayWarning({ message: errors.join(', ') });
    return false;
  }

  return true;
};

const debugFormState = () => {
  console.log('=== FORM STATE DEBUG ===');
  console.log('isNewPatient:', isNewPatient.value);
  console.log('patientForm:', JSON.parse(JSON.stringify(patientForm.value)));
  console.log('newPatientForm:', JSON.parse(JSON.stringify(newPatientForm.value)));
  console.log('patientSearchQuery:', patientSearchQuery.value);
  console.log('========================');
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
      cardNumber: props.editData.card_number || "",
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
        const adminDoctor = props.doctors.find(d => d.id == doctorId);
        if (adminDoctor) {
          doctorName = `${adminDoctor.first_name} ${adminDoctor.last_name}`;
          patientForm.value.doctor_id = `admin_${doctorId}`;
        }
      }

      if (!doctorName && props.billingDoctors && props.billingDoctors.length > 0) {
        const billingDoctor = props.billingDoctors.find(d => d.id == doctorId);
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
        console.log('Setting doctor name in edit mode:', doctorName);
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
        netAmount: parseFloat(item.net_amount),
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
      payableAmount: payableAmount,
      paidAmt: paidAmount,
      changeAmt: parseFloat(props.editData.change_amt || 0),
      dueAmount: dueAmount,
      receivingAmt: receivingAmount,
      takingAmt: parseFloat(props.editData.taking_amt || 0),
      returnAmt: parseFloat(props.editData.return_amt || 0),
      deliveryDate: props.editData.delivery_date || "",
      deliveryTime: props.editData.delivery_time || "",
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

const saveBill = () => {
  console.log('=== SAVE BILL DEBUG ===');
  console.log('isNewPatient:', isNewPatient.value);
  console.log('patientForm.patient_id:', patientForm.value.patient_id);
  console.log('patientSearchQuery:', patientSearchQuery.value);

  if (items.value.length === 0) {
    displayWarning({ message: "Please add at least one item to the bill." });
    itemNameInput.value?.focus();
    return;
  }

  if (summary.value.payableAmount <= 0) {
    displayWarning({ message: "Payable amount must be greater than 0." });
    return;
  }

  let paymentStatus = "Pending";
  const paidAmount = parseFloat(summary.value.paidAmt) || 0;
  const payableAmount = parseFloat(summary.value.payableAmount) || 0;

  if (paidAmount >= payableAmount) {
    paymentStatus = "Paid";
  } else if (paidAmount > 0) {
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

  // FIX: Calculate age string properly
  let ageString = '';
  const yearsNum = parseInt(ageYears.value) || 0;
  const monthsNum = parseInt(ageMonths.value) || 0;
  const daysNum = parseInt(ageDays.value) || 0;

  // Build age string only if any age component is provided
  if (yearsNum > 0 || monthsNum > 0 || daysNum > 0) {
    ageString = [
      yearsNum > 0 ? `${yearsNum} year${yearsNum !== 1 ? 's' : ''}` : '',
      monthsNum > 0 ? `${monthsNum} month${monthsNum !== 1 ? 's' : ''}` : '',
      daysNum > 0 ? `${daysNum} day${daysNum !== 1 ? 's' : ''}` : ''
    ].filter(Boolean).join(' ');
  }

  const formData = {
    is_new_patient: Boolean(isNewPatient.value),
    
    patient_id: patientForm.value.patient_id || null,
    patient_mobile: patientForm.value.patientMobile?.trim() || '',
    gender: patientForm.value.gender || '',
    dob: patientForm.value.dob || null,

    card_type: patientForm.value.cardType,
    pay_mode: patientForm.value.payMode,
    card_number: patientForm.value.cardNumber || null,

    items: itemsForBackend,

    total: summary.value.total,
    discount: summary.value.discount || 0,
    discount_type: summary.value.discountType,
    extra_flat_discount: summary.value.extraFlatDiscount || 0,
    payable_amount: summary.value.payableAmount,
    paid_amt: paidAmount,
    change_amt: summary.value.changeAmt || 0,
    due_amount: summary.value.dueAmount || 0,
    receiving_amt: summary.value.receivingAmt || 0,
    taking_amt: summary.value.takingAmt || 0,
    return_amt: summary.value.returnAmt || 0,

    delivery_date: summary.value.deliveryDate || null,
    delivery_time: summary.value.deliveryTime || null,
    remarks: summary.value.remarks || null,
    payment_status: paymentStatus,

    commission_total: commission.value.total || 0,
    physyst_amt: commission.value.physystAmt || 0,
    commission_slider: commission.value.commissionRate || 0,
    referrer_id: commission.value.referrer_id || null,

    doctor_name: doctorSearchQuery.value.trim() || null,
  };

  // FIX: Always include age data in the form
  formData.patient_age = ageString;

  if (isNewPatient.value && !patientForm.value.patient_id) {
    console.log('Creating NEW patient...');

    formData.patient_name = newPatientForm.value.name.trim();
    formData.patient_phone = newPatientForm.value.phone.trim();
    formData.patient_gender = newPatientForm.value.gender;
    formData.patient_dob = newPatientForm.value.dob || null;
    // Age is already set above

    formData.patient_mobile = newPatientForm.value.phone.trim();
    formData.gender = newPatientForm.value.gender;
    formData.dob = newPatientForm.value.dob || null;

    console.log('New Patient Data Added:', {
      patient_name: formData.patient_name,
      patient_phone: formData.patient_phone,
      patient_gender: formData.patient_gender,
      patient_dob: formData.patient_dob,
      patient_age: formData.patient_age
    });

  } else if (!isNewPatient.value && patientForm.value.patient_id) {
    console.log('Updating EXISTING patient...');
    
    const patientName = patientSearchQuery.value.trim();
    
    if (patientName) {
      formData.patient_name = patientName;
      console.log('Setting patient_name for existing patient:', patientName);
    } else {
      const selectedPatient = props.patients.find(p => p.id == patientForm.value.patient_id);
      if (selectedPatient) {
        formData.patient_name = selectedPatient.name;
        console.log('Found patient name from list:', selectedPatient.name);
      }
    }

    formData.patient_phone = patientForm.value.patientMobile?.trim() || '';
    formData.patient_gender = patientForm.value.gender || '';
    formData.patient_dob = patientForm.value.dob || null;
    // Age is already set above

    console.log('Existing Patient Update Data:', {
      patient_id: formData.patient_id,
      patient_name: formData.patient_name,
      patient_phone: formData.patient_phone,
      patient_gender: formData.patient_gender,
      patient_dob: formData.patient_dob,
      patient_age: formData.patient_age
    });

  } else if (!patientForm.value.patient_id && !isNewPatient.value) {
    console.log('Walk-in patient (no patient record)');
    formData.patient_name = patientSearchQuery.value.trim() || 'Walk-in Patient';
    formData.patient_phone = patientForm.value.patientMobile?.trim() || '';
    formData.patient_gender = patientForm.value.gender || '';
    formData.patient_dob = patientForm.value.dob || null;
    // Age is already set above
  }

  console.log('Complete Form Data for Submission:', formData);
  console.log('Age being sent:', formData.patient_age);
  console.log('is_new_patient value being sent:', formData.is_new_patient);

  const form = useForm(formData);

  const submitOptions = {
    onSuccess: (response) => {
      displayResponse(response);
      console.log('Save bill success:', response);

      const successMessage = response?.props?.flash?.successMessage;
      const billId = response?.props?.flash?.billId;

      if (successMessage && billId) {
        if (!props.id) {
          resetAllForms();
        }
        window.open(route("backend.download.invoice", { id: billId, module: 'billing' }), "_blank");
      }
    },
    onError: (errors) => {
      console.error('Save bill errors:', errors);
      
      if (errors.patient_name) {
        displayWarning({ message: errors.patient_name });
        patientSearchRef.value?.focus();
      } else if (errors.patient_phone) {
        displayWarning({ message: errors.patient_phone });
        patientMobileRef.value?.focus();
      } else if (errors.patient_gender) {
        displayWarning({ message: errors.patient_gender });
        genderSelectRef.value?.focus();
        setTimeout(() => openDropdown(genderSelectRef), 100);
      } else if (errors.patient_id) {
        displayWarning({ message: errors.patient_id });
        patientSearchRef.value?.focus();
      } else if (errors.items) {
        displayWarning({ message: errors.items });
        itemNameInput.value?.focus();
      } else {
        const errorMessage = typeof errors === 'string' 
          ? errors 
          : 'Please check the form for errors and try again.';
        displayWarning({ message: errorMessage });
      }
    },
    onFinish: () => {
      console.log('Save bill request finished');
    }
  };

  if (props.id) {
    console.log('Updating bill with ID:', props.id);
    form.put(route("backend.billing.update", props.id), submitOptions);
  } else {
    console.log('Creating new bill');
    form.post(route("backend.billing.store"), submitOptions);
  }
};

const resetAllForms = () => {
  items.value = [];
  patientForm.value = {
    patient_id: "",
    doctor_id: "",
    patientMobile: "",
    gender: "",
    dob: "",
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
    deliveryDate: "",
    deliveryTime: "",
    remarks: "",
  };
  commission.value = {
    total: 0.0,
    physystAmt: 0.0,
    slider: 0,
    referrer_id: "",
    commissionRate: 0,
  };

  isNewPatient.value = false;
  newPatientForm.value = {
    name: "",
    phone: "",
    gender: "",
    dob: "",
  };
  patientSearchQuery.value = "";
  showPatientDropdown.value = false;

  doctorSearchQuery.value = "";
  filteredDoctors.value = [];
  showDoctorDropdown.value = false;

  ageYears.value = '';
  ageMonths.value = '';
  ageDays.value = '';
};

const handlePatientSearchBlur = (event) => {
  setTimeout(() => {
    showPatientDropdown.value = false;
  }, 200);
};

const cancelBill = () => {
  router.visit(route("backend.billing.Page"));
};

const openListBillButton = () => {
  router.visit(route("backend.billing.list"));
};

const openAddBillButton = () => {
  router.visit(route("backend.billing.view"));
};

// Initialize edit mode if needed
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
  const hasExactMatch = filteredDoctors.value.some(doctor => 
    doctor.name.toLowerCase() === newQuery.trim().toLowerCase()
  );
  
  if (hasExactMatch) {
    showDoctorDropdown.value = false;
    return;
  }

  searchDoctors(newQuery);
}, 300));

const searchDoctors = async (query) => {
  if (query.length < 2) return;

  isDoctorLoading.value = true;
  try {
    const response = await axios.get(route('backend.billing.doctors.search'), {
      params: { search: query }
    });

    filteredDoctors.value = response.data;
    
    const hasExactMatch = filteredDoctors.value.some(doctor => 
      doctor.name.toLowerCase() === query.trim().toLowerCase()
    );
    
    if (filteredDoctors.value.length > 0 && !hasExactMatch) {
      showDoctorDropdown.value = true;
    } else {
      showDoctorDropdown.value = false;
    }
    
    doctorSelectedIndex.value = -1;
  } catch (error) {
    console.error('Error searching doctors:', error);
    filteredDoctors.value = [];
    showDoctorDropdown.value = false;
  } finally {
    isDoctorLoading.value = false;
  }
};

const selectDoctor = (doctor) => {
  console.log('Selecting doctor:', doctor.name);
  
  doctorSearchQuery.value = doctor.name;
  
  showDoctorDropdown.value = false;
  doctorSelectedIndex.value = -1;
  filteredDoctors.value = [];
  isDoctorLoading.value = false;

  nextTick(() => {
    setTimeout(() => {
      patientMobileRef.value?.focus();
    }, 50);
  });
};


const handleDoctorSearchKeyDown = (event) => {
  if (event.key === "ArrowDown") {
    event.preventDefault();
    doctorSelectedIndex.value = Math.min(
      doctorSelectedIndex.value + 1,
      filteredDoctors.value.length - 1
    );
  } else if (event.key === "ArrowUp") {
    event.preventDefault();
    doctorSelectedIndex.value = Math.max(doctorSelectedIndex.value - 1, 0);
  } else if (event.key === "Enter") {
    handleDoctorEnter(event);
  } else if (event.key === "Escape") {
    doctorSelectedIndex.value = -1;
    showDoctorDropdown.value = false;
  }
};

const createNewDoctor = async () => {
  if (!doctorSearchQuery.value.trim()) {
    displayWarning({ message: "Please enter a doctor name." });
    return;
  }

  try {
    const response = await axios.post(route('backend.billingdoctor.store'), {
      name: doctorSearchQuery.value.trim()
    });

    if (response.data.success) {
      selectDoctor(response.data.doctor);
      displayResponse({ message: "Doctor created successfully!" });
    } else {
      displayWarning({ message: response.data.message });
    }
  } catch (error) {
    console.error('Error creating doctor:', error);
    displayWarning({ message: "Failed to create doctor. Please try again." });
  }
};

const handleDoctorSearchBlur = (event) => {
  setTimeout(() => {
    showDoctorDropdown.value = false;
  }, 200);
};

const clearDoctorSelection = () => {
  doctorSearchQuery.value = "";
  filteredDoctors.value = [];
};

const handleDoctorSearchInput = (event) => {
  const query = event.target.value;
  
  const hasExactMatch = filteredDoctors.value.some(doctor => 
    doctor.name.toLowerCase() === query.trim().toLowerCase()
  );
  
  if (query.trim() !== "" && filteredDoctors.value.length > 0 && !hasExactMatch) {
    showDoctorDropdown.value = true;
  } else {
    showDoctorDropdown.value = false;
  }
};

</script>

<template>

  <Head :title="$page.props.pageTitle"/>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900 overflow-y-auto">
    <div class="w-full p-2">
      <div class="bg-white rounded-lg shadow-lg dark:bg-slate-900 mb-4">
        <div class="mb-3">
          <div
            class="flex justify-between items-center bg-[#053855] text-white px-3 py-2 text-xs font-semibold rounded-t-lg">
            <div class="flex-1">ITEM DETAILS</div>
            <div class="flex items-center space-x-2 mr-2">
              <a :href="route('backend.billing.view')" target="_blank" @click="openAddBillButton"
                class="flex items-center px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-md text-xs transition-colors duration-200 shadow-sm"
                title="Billing List">
                Billing Add
              </a>
            </div>
            <div class="flex items-center space-x-2">
              <a :href="route('backend.billing.list')" target="_blank" @click="openListBillButton"
                class="flex items-center px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-md text-xs transition-colors duration-200 shadow-sm"
                title="Billing List">
                Billing List
              </a>
              <button @click="cancelBill"
                class="flex items-center justify-center w-6 h-6 bg-red-500 hover:bg-red-600 text-white rounded-full text-xs transition-colors duration-200"
                title="Cancel">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>
          <div class="border border-gray-300 border-t-0 p-3 bg-gray-50 dark:bg-slate-800 dark:border-gray-600">
            <div class="flex items-center space-x-4 text-xs mb-3">
                <!-- ====== Billing Date & Time (Real-Time + Editable) ====== -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-2 mt-2">

  <!-- Billing Date -->
  <div class="lg:col-span-2">
    <InputLabel value="Billing Date" class="text-xs mb-1" />
    <input 
      type="date"
      v-model="billingDate"
      class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs
             focus:border-blue-500 focus:outline-none
             dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
    />
  </div>

  <!-- Billing Time -->
  <div class="lg:col-span-2">
    <InputLabel value="Billing Time" class="text-xs mb-1" />
    <input 
      type="time"
      v-model="billingTime"
      class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs
             focus:border-blue-500 focus:outline-none
             dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
    />
  </div>

</div>
              <span class="font-medium text-gray-700 dark:text-gray-300"><strong>UNIT:</strong> ToaMed.</span>
              <span class="font-medium text-gray-700 dark:text-gray-300"><strong>Counter:</strong> {{ authInfo?.department?.name ?? "" }}
              </span>
              <span class="font-medium text-gray-700 dark:text-gray-300"><strong>Sales Person:</strong> {{ authInfo?.admin?.name ?? "" }}
              </span>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-2 items-end">
              <div class="lg:col-span-2">
                <InputLabel for="category" value="Category" class="text-xs mb-1" />
                <select v-model="itemForm.category" id="category"
                  class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200">
                  <option value="">Select</option>
                  <option value="Pathology">Pathology</option>
                  <option value="Radiology">Radiology</option>
                  <option value="Medicine">Medicine</option>
                </select>
              </div>
              <div class="lg:col-span-2 relative">
                <InputLabel for="itemName" value="Item Name" class="text-xs mb-1" />
                <div class="relative">
                  <input v-model="itemForm.itemName" @input="searchQuery = $event.target.value; selectedIndex = -1"
                    @keydown="handleKeyDown($event, 'itemName')" @focus="selectedIndex = -1" id="itemName" type="text"
                    class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                    placeholder="Search items..." autocomplete="off" ref="itemNameInput" />
                  <div v-if="searchQuery || (itemForm.category && filteredItems.length > 0)"
                    class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto dark:bg-slate-700 dark:border-gray-600">
                    <ul>
                      <li v-for="(item, index) in filteredItems" :key="item.id" @click="selectItem(item)"
                        @keypress.enter="selectItem(item);" :class="['list-focus px-3 py-2 text-xs cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-600',
                          { 'bg-blue-100 dark:bg-blue-700': index === selectedIndex }]"
                        :ref="(el) => { if (index === selectedIndex) selectedItemRef = el }">
                        <div class="flex justify-between">
                          <span>{{ item.name }}</span>
                          <span class="text-gray-500 dark:text-gray-300">
                            {{ item.type === "medicine" ? "Medicine" : item.category }}
                            (৳{{ item.unitPrice }})
                          </span>
                        </div>
                        <div v-if="item.type === 'medicine'" class="text-xs text-gray-500 dark:text-gray-400">
                          Stock: {{ item.stock }}
                        </div>
                      </li>
                      <li v-if="filteredItems.length === 0" class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">
                        No items found.
                      </li>
                    </ul>
                  </div>
                </div>
              </div>

              <div class="lg:col-span-2">
                <InputLabel for="unitPrice" value="U/Price" class="text-xs mb-1" />
                <div class="flex">
                  <input v-model="itemForm.unitPrice" type="number" step="1" id="unitPrice" readonly
                    class="w-full px-2 py-1.5 border border-gray-300 rounded-l text-xs bg-yellow-100 focus:bg-yellow-200 focus:outline-none dark:bg-yellow-200 dark:text-gray-800" />
                  <span
                    class="px-2 py-1.5 bg-gray-200 border-t border-b border-r border-gray-300 rounded-r text-xs dark:bg-gray-600 dark:text-gray-200">৳</span>
                </div>
              </div>
              <div class="lg:col-span-1">
                <InputLabel for="quantity" value="Qty" class="text-xs mb-1" />
                <input v-model="itemForm.quantity" @keydown="handleKeyDown($event, 'quantity')" type="number" step="1"
                  min="1" id="quantity"
                  class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                  ref="quantityInput" />
              </div>
              <div class="lg:col-span-2">
                <InputLabel for="totalAmount" value="T.Amt" class="text-xs mb-1" />
                <input v-model="itemForm.totalAmount" type="number" step="1" id="totalAmount"
                  class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs bg-gray-100 dark:bg-gray-600 dark:text-gray-200"
                  readonly />
              </div>
              <div class="lg:col-span-1">
                <button @click="addItem"
                  class="w-full h-8 bg-teal-600 text-white rounded hover:bg-teal-700 flex items-center justify-center font-bold text-sm">
                  ✚
                </button>
              </div>
            </div>
          </div>
        </div>
        <div class="mb-0">
          <div class="bg-slate-700 text-white px-3 py-2 text-xs font-semibold">
            ITEM LIST
          </div>
          <div class="border border-gray-300 border-t-0">
            <div class="overflow-y-auto max-h-custom">
              <table class="w-full text-xs">
                <thead class="bg-teal-700 text-white sticky top-0">
                  <tr>
                    <th class="px-2 py-2 text-left font-semibold">Item Name</th>
                    <th class="px-2 py-2 text-center font-semibold">Category</th>
                    <th class="px-2 py-2 text-center font-semibold">U/Price</th>
                    <th class="px-2 py-2 text-center font-semibold">Qty</th>
                    <th class="px-2 py-2 text-center font-semibold">T.Amt</th>
                    <!-- <th class="px-2 py-2 text-center font-semibold">Disc%</th> -->
                    <th class="px-2 py-2 text-center font-semibold">Net Amt</th>
                    <th class="px-2 py-2 text-center font-semibold">Action</th>
                  </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800">
                  <tr v-for="(item, index) in items" :key="index"
                    class="border-b border-gray-200 hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-slate-700">
                    <td class="px-2 py-2 font-medium dark:text-gray-200">
                      {{ item.name }}
                    </td>
                    <td class="px-2 py-2 text-center dark:text-gray-200">
                      <span :class="{
                        'bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs':
                          item.category === 'Pathology',
                        'bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs':
                          item.category === 'Radiology',
                        'bg-purple-100 text-purple-800 px-2 py-1 rounded-full text-xs':
                          item.category === 'Medicine',
                      }">
                        {{ item.category }}
                      </span>
                    </td>
                    <td class="px-2 py-2 text-center dark:text-gray-200">
                      ৳{{ item.unitPrice.toFixed(2) }}
                    </td>
                    <td class="px-2 py-2 text-center dark:text-gray-200">
                      {{ item.quantity }}
                    </td>
                    <td class="px-2 py-2 text-center dark:text-gray-200">
                      ৳{{ item.totalAmount.toFixed(2) }}
                    </td>
                    <!-- <td class="px-2 py-2 text-center dark:text-gray-200">0%</td> -->
                    <td class="px-2 py-2 text-center font-semibold dark:text-gray-200">
                      ৳{{ item.netAmount.toFixed(2) }}
                    </td>
                    <td class="px-2 py-2 text-center">
                      <button @click="removeItem(index)"
                        class="bg-red-500 text-white px-1.5 py-0.5 rounded text-xs hover:bg-red-600">
                        🗑
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 p-3">
          <!-- PATIENT DETAILS section -->
          <div>
            <div
              class="bg-teal-600 text-white px-3 py-2 text-xs font-semibold rounded-t flex justify-between items-center">
              <span>PATIENT DETAILS</span>
            </div>
            <div
              class="border border-gray-300 border-t-0 rounded-b p-3 bg-white dark:bg-slate-800 dark:border-gray-600 space-y-2">
              <!-- Patient Search -->
              <div class="grid grid-cols-3 gap-2 items-center">
                <InputLabel for="patient_id" value="Patient" class="text-xs" />
                <div class="col-span-2 relative">
                  <input v-model="patientSearchQuery" @input="showPatientDropdown = patientSearchQuery.trim() !== ''"
                    @keydown="handlePatientSearchKeyDown" @focus="handlePatientSearchFocus"
                    @blur="handlePatientSearchBlur" id="patientSearch" type="text"
                    class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                    placeholder="Search patient by name or phone" ref="patientSearchRef" />

                  <!-- Patient dropdown list -->
                  <div v-if="showPatientDropdown && filteredPatients.length > 0"
                    class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto dark:bg-slate-700 dark:border-gray-600">
                    <ul>
                      <li v-for="(patient, index) in filteredPatients" :key="patient.id" @click="selectPatient(patient)"
                        :class="[
                          'px-3 py-2 text-xs cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-600',
                          { 'bg-blue-100 dark:bg-blue-700': index === patientSelectedIndex }
                        ]">
                        <div class="flex justify-between">
                          <span>{{ patient.name }}</span>
                          <span class="text-gray-500 dark:text-gray-300">
                            {{ patient.phone }}
                          </span>
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                          {{ patient.gender }}
                        </div>
                      </li>
                    </ul>
                  </div>

                  <!-- Add new patient button when no matches found -->
                  <div v-if="showPatientDropdown && filteredPatients.length === 0 && patientSearchQuery.trim()"
                    class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg dark:bg-slate-700 dark:border-gray-600">
                    <div class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">
                      No patient found.
                      <button type="button" @click="openPatientModal"
                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium">
                        Add new patient
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Ref. Doctor Search Field -->
              <div class="grid grid-cols-3 gap-2 items-center">
                <InputLabel for="doctor_search" value="Ref. Doctor" class="text-xs" />
                <div class="col-span-2 relative">
                  <div class="relative">
                    <input 
                      v-model="doctorSearchQuery" 
                      @input="handleDoctorSearchInput"
                      @keydown="handleDoctorSearchKeyDown"
                      @focus="handleDoctorSearchFocus"
                      @blur="handleDoctorSearchBlur" 
                      id="doctor_search" 
                      type="text"
                      class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                      placeholder="Type doctor name and press Enter" 
                      autocomplete="off" 
                      ref="doctorSearchRef"
                      @keydown.enter="handleDoctorEnter"
                    />

                    <!-- Clear button -->
                    <button v-if="doctorSearchQuery" @click="clearDoctorSelection"
                            class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            type="button">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                      </svg>
                    </button>
                  </div>

                  <!-- Loading indicator -->
                  <div v-if="isDoctorLoading"
                      class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg dark:bg-slate-700 dark:border-gray-600">
                    <div class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">
                      Searching...
                    </div>
                  </div>

                  <!-- Doctor dropdown list -->
                  <div v-else-if="showDoctorDropdown && filteredDoctors.length > 0"
                      class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto dark:bg-slate-700 dark:border-gray-600">
                    <ul>
                      <li v-for="(doctor, index) in filteredDoctors" 
                          :key="doctor.id" 
                          @mousedown.prevent="selectDoctor(doctor)" 
                          :class="[
                            'px-3 py-2 text-xs cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-600',
                            { 'bg-blue-100 dark:bg-blue-700': index === doctorSelectedIndex }
                          ]">
                        <div class="flex justify-between items-center">
                          <span>{{ doctor.name }}</span>
                        </div>
                      </li>
                    </ul>
                  </div>

                  <!-- No results message - just inform user to press Enter -->
                  <div v-else-if="showDoctorDropdown && filteredDoctors.length === 0 && doctorSearchQuery.trim()"
                      class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg dark:bg-slate-700 dark:border-gray-600">
                    <div class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">
                      Press Enter to use "{{ doctorSearchQuery }}" as doctor name
                    </div>
                  </div>
                </div>
              </div>

              <!-- Hidden field to store the selected doctor ID -->
              <input type="hidden" v-model="patientForm.doctor_id" />

              <!-- Patient Mobile -->
              <div class="grid grid-cols-3 gap-2 items-center">
                <InputLabel for="patientMobile" value="Patient Mobile" class="text-xs" />
                <input v-model="patientForm.patientMobile" type="text" id="patientMobile"
                  placeholder="Enter Patient Mobile"
                  class="col-span-2 px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                  ref="patientMobileRef" @keydown="handlePatientMobileEnter" />
              </div>

              <!-- Gender -->
              <div class="grid grid-cols-3 gap-2 items-center">
                <InputLabel for="gender" value="Gender" class="text-xs" />
                <select v-model="patientForm.gender" id="gender"
                  class="col-span-2 px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                  ref="genderSelectRef" @keydown="handleGenderEnter">
                  <option value="">Select</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                  <option value="Others">Others</option>
                </select>
              </div>

              <!-- Date of Birth -->
              <div class="grid grid-cols-3 gap-2 items-center">
                <InputLabel for="dob" value="Date of Birth" class="text-xs" />
                <input v-model="patientForm.dob" type="date" id="dob"
                  class="col-span-2 px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                  ref="dobInput" @keydown="handleDobEnter" />
              </div>

              <!-- Age -->
              <div class="grid grid-cols-3 gap-2 items-center">
                <InputLabel value="Age" class="text-xs" />
                <div class="col-span-2 flex items-center space-x-1">
                  <input ref="ageYearsInput" v-model="ageYears" @input="handleAgeInput(ageYearsInput, ageMonthsInput)"
                    @keydown="handleAgeYearsEnter" type="number" min="0" max="120"
                    class="w-12 px-1 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                    placeholder="Y" @focus="$event.target.select()" />
                  <span class="text-xs text-gray-500">y</span>

                  <input ref="ageMonthsInput" v-model="ageMonths" @input="handleAgeInput(ageMonthsInput, ageDaysInput)"
                    @keydown="handleAgeMonthsEnter" type="number" min="0" max="11"
                    class="w-12 px-1 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                    placeholder="M" @focus="$event.target.select()" />
                  <span class="text-xs text-gray-500">m</span>

                  <input ref="ageDaysInput" v-model="ageDays" @input="handleAgeInput(ageDaysInput, null)"
                    @keydown="handleAgeDaysEnter" type="number" min="0" max="30"
                    class="w-12 px-1 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                    placeholder="D" @focus="$event.target.select()" />
                  <span class="text-xs text-gray-500">d</span>
                </div>
              </div>

              <!-- Pay Mode -->
              <div class="grid grid-cols-3 gap-2 items-center">
                <InputLabel for="payMode" value="Pay Mode" class="text-xs" />
                <select v-model="patientForm.payMode" id="payMode"
                  class="col-span-2 px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                  ref="payModeRef" @keydown="handlePayModeEnter">
                  <option value="Cash">Cash</option>
                  <option value="Card">Card</option>
                  <option value="Mobile Banking">Mobile Banking</option>
                </select>
              </div>

              <!-- Card/Account Number (conditional) -->
              <div class="grid grid-cols-3 gap-2 items-center" v-if="patientForm.payMode !== 'Cash'">
                <InputLabel for="cardNumber" value="Card/Account No." class="text-xs" />
                <input v-model="patientForm.cardNumber" type="text" id="cardNumber"
                  placeholder="Enter card/account number"
                  class="col-span-2 px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                  ref="cardNumberRef" @keydown="handleCardNumberEnter" />
              </div>
            </div>
          </div>

          <!-- Patient Modal -->
          <PatientModal :isOpen="isPatientModalOpen" :tpas="props.tpas" @close="closePatientModal"
            @patientCreated="handlePatientCreated" />

          <!-- TOTAL SUMMARY section -->
          <div>
            <div class="bg-teal-600 text-white px-3 py-2 text-xs font-semibold rounded-t">
              TOTAL SUMMARY
            </div>
            <div
              class="border border-gray-300 border-t-0 rounded-b p-3 bg-white dark:bg-slate-800 dark:border-gray-600 space-y-2">
              <div class="grid grid-cols-2 gap-2 items-center">
                <InputLabel for="total" value="Total Amount" class="text-xs font-semibold" />
                <div class="flex">
                  <input v-model="summary.total" type="number" step="0.01" id="total" readonly
                    class="w-full px-2 py-1.5 border border-gray-300 rounded-l text-xs bg-gray-100 dark:bg-gray-600 dark:text-gray-200" />
                  <span
                    class="px-2 py-1.5 bg-gray-200 border-t border-b border-r border-gray-300 rounded-r text-xs dark:bg-gray-600 dark:text-gray-200">৳</span>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-2">
                <div class="grid grid-cols-2 gap-1 items-center">
                  <InputLabel for="discount" value="Discount" class="text-xs" />
                  <select v-model="summary.discountType"
                    class="px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200">
                    <option value="percentage">Percentage (%)</option>
                    <option value="flat">Flat Amount (৳)</option>
                  </select>
                </div>
                <div class="flex">
                  <input v-model="summary.discount" type="number" step="1" min="0" id="discount"
                    class="w-full px-2 py-1.5 border border-gray-300 rounded-l text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                    ref="discountRef" @keydown="handleDiscountEnter" />
                  <span
                    class="px-2 py-1.5 bg-gray-200 border-t border-b border-r border-gray-300 rounded-r text-xs dark:bg-gray-600 dark:text-gray-200">৳</span>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-2 items-center">
                <InputLabel for="extraFlatDiscount" value="Extra Discount" class="text-xs" />
                <div class="flex">
                  <input v-model="summary.extraFlatDiscount" @input="updateSummary" type="number" step="1" min="0"
                    id="extraFlatDiscount"
                    class="w-full px-2 py-1.5 border border-gray-300 rounded-l text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                    placeholder="Additional flat discount" ref="extraDiscountRef" @keydown="handleExtraDiscountEnter" />
                  <span
                    class="px-2 py-1.5 bg-gray-200 border-t border-b border-r border-gray-300 rounded-r text-xs dark:bg-gray-600 dark:text-gray-200">৳</span>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-2 items-center">
                <InputLabel for="payableAmount" value="Payable Amount"
                  class="text-xs font-semibold text-green-700 dark:text-green-400" />
                <div class="flex">
                  <input v-model="summary.payableAmount" type="number" step="0.01" id="payableAmount" readonly
                    class="w-full px-2 py-1.5 border border-green-500 rounded-l text-xs bg-green-50 font-semibold dark:bg-green-900 dark:text-green-100" />
                  <span
                    class="px-2 py-1.5 bg-green-200 border-t border-b border-r border-green-500 rounded-r text-xs font-semibold dark:bg-green-700 dark:text-green-100">৳</span>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-2 items-center">
                <InputLabel for="takingAmt" value="Taking Amount"
                  class="text-xs font-semibold text-indigo-700 dark:text-indigo-400" />
                <div class="flex">
                  <input v-model="summary.takingAmt" type="number" step="0.01" min="0" id="takingAmt"
                    class="w-full px-2 py-1.5 border border-indigo-500 rounded-l text-xs focus:border-indigo-700 focus:outline-none bg-indigo-50 dark:bg-indigo-900 dark:border-indigo-400 dark:text-indigo-100"
                    placeholder="Amount taken from customer" ref="takingAmtRef" @keydown="handleTakingAmtEnter" />
                  <span
                    class="px-2 py-1.5 bg-indigo-200 border-t border-b border-r border-indigo-500 rounded-r text-xs font-semibold dark:bg-indigo-700 dark:text-indigo-100">৳</span>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-2 items-center">
                <InputLabel for="receivingAmt" value="Receiving Amount"
                  class="text-xs font-semibold text-blue-700 dark:text-blue-400" />
                <div class="flex">
                  <input v-model="summary.receivingAmt" type="number" step="0.01" min="0" id="receivingAmt"
                    class="w-full px-2 py-1.5 border border-blue-500 rounded-l text-xs focus:border-blue-700 focus:outline-none bg-blue-50 dark:bg-blue-900 dark:border-blue-400 dark:text-blue-100"
                    placeholder="Amount given by customer" ref="receivingAmtRef" @keydown="handleReceivingAmtEnter" />
                  <span
                    class="px-2 py-1.5 bg-blue-200 border-t border-b border-r border-blue-500 rounded-r text-xs font-semibold dark:bg-blue-700 dark:text-blue-100">৳</span>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-2 items-center" v-if="summary.returnAmt > 0">
                <InputLabel for="returnAmt" value="Return Amount"
                  class="text-xs font-semibold text-amber-700 dark:text-amber-400" />
                <div class="flex">
                  <input v-model="summary.returnAmt" type="number" step="0.01" id="returnAmt" readonly
                    class="w-full px-2 py-1.5 border border-amber-500 rounded-l text-xs bg-amber-50 font-semibold dark:bg-amber-900 dark:text-amber-100" />
                  <span
                    class="px-2 py-1.5 bg-amber-200 border-t border-b border-r border-amber-500 rounded-r text-xs font-semibold dark:bg-amber-700 dark:text-amber-100">৳</span>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-2 items-center">
                <InputLabel for="paidAmt" value="Paid Amount" class="text-xs font-semibold" />
                <div class="flex">
                  <input v-model="summary.paidAmt" type="number" step="0.01" id="paidAmt" readonly
                    class="w-full px-2 py-1.5 border border-gray-300 rounded-l text-xs bg-gray-100 font-semibold dark:bg-gray-600 dark:text-gray-200" />
                  <span
                    class="px-2 py-1.5 bg-gray-200 border-t border-b border-r border-gray-300 rounded-r text-xs dark:bg-gray-600 dark:text-gray-200">৳</span>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-2 items-center" v-if="summary.changeAmt > 0">
                <InputLabel for="changeAmt" value="Change Amount"
                  class="text-xs font-semibold text-purple-700 dark:text-purple-400" />
                <div class="flex">
                  <input v-model="summary.changeAmt" type="number" step="0.01" id="changeAmt" readonly
                    class="w-full px-2 py-1.5 border border-purple-500 rounded-l text-xs bg-purple-50 font-semibold dark:bg-purple-900 dark:text-purple-100" />
                  <span
                    class="px-2 py-1.5 bg-purple-200 border-t border-b border-r border-purple-500 rounded-r text-xs font-semibold dark:bg-purple-700 dark:text-purple-100">৳</span>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-2 items-center" v-if="summary.dueAmount > 0">
                <InputLabel for="dueAmount" value="Due Amount"
                  class="text-xs font-semibold text-red-700 dark:text-red-400" />
                <div class="flex">
                  <input v-model="summary.dueAmount" type="number" step="0.01" id="dueAmount" readonly
                    class="w-full px-2 py-1.5 border border-red-500 rounded-l text-xs bg-red-50 font-semibold dark:bg-red-900 dark:text-red-100" />
                  <span
                    class="px-2 py-1.5 bg-red-200 border-t border-b border-r border-red-500 rounded-r text-xs font-semibold dark:bg-red-700 dark:text-red-100">৳</span>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-2 items-center">
                <InputLabel for="deliveryDate" value="Delivery Date" class="text-xs" />
                <input v-model="summary.deliveryDate" type="datetime-local" id="deliveryDate"
                  class="px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                  ref="deliveryDateRef" @keydown="handleDeliveryDateEnter" @click="setCurrentDeliveryDateTime" />
              </div>
              <div>
                <InputLabel for="remarks" value="Remarks" class="text-xs mb-1" />
                <textarea v-model="summary.remarks" id="remarks" rows="2"
                  class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                  placeholder="Additional notes or remarks" ref="remarksRef" @keydown="handleRemarksEnter"></textarea>
              </div>
            </div>
          </div>

          <!-- COMMISSION section -->
          <div class="flex flex-col">
            <div>
              <div class="bg-teal-600 text-white px-3 py-2 text-xs font-semibold rounded-t">
                COMMISSION FOR PC
              </div>
              <div
                class="border border-gray-300 border-t-0 rounded-b p-3 bg-white dark:bg-slate-800 dark:border-gray-600 space-y-3">
                <div class="flex justify-between items-center">
                  <InputLabel for="referrer_id" value="Referrer Name" class="text-xs" />
                  <select v-model="commission.referrer_id" id="referrer_id"
                    class="w-32 px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                    ref="referrerSelectRef" @keydown="handleReferrerEnter">
                    <option value="">Select Referrer</option>
                    <option v-for="data in referrers" :key="data.id" :value="data.id">
                      {{ data.name }}
                    </option>
                  </select>
                </div>
                <div v-if="commission.referrer_id && commissionBreakdown.length > 0"
                  class="bg-blue-50 border border-blue-200 rounded-lg p-2 text-xs">
                  <h4 class="font-medium text-blue-800 mb-2">Commission Breakdown:</h4>
                  <div class="space-y-1">
                    <div v-for="breakdown in commissionBreakdown" :key="breakdown.category"
                      class="flex justify-between items-center">
                      <span class="text-blue-700">
                        {{ breakdown.category }} ({{ breakdown.rate }}%):
                      </span>
                      <span :class="breakdown.hasCommission
                        ? 'text-green-600 font-medium'
                        : 'text-red-600'
                        ">
                        {{
                          breakdown.hasCommission
                            ? `৳${breakdown.commission.toFixed(2)}`
                            : "No Commission"
                        }}
                      </span>
                    </div>
                  </div>
                </div>
                <div class="flex justify-between items-center">
                  <InputLabel for="commissionTotal" value="Total:" class="text-xs" />
                  <input v-model="commission.total" type="number" step="1" id="commissionTotal"
                    class="w-24 px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-right dark:bg-gray-600 dark:text-gray-200"
                    readonly />
                </div>
                <div class="flex justify-between items-center">
                  <InputLabel for="physystAmt" value="Physyst Amt:" class="text-xs" />
                  <input v-model="commission.physystAmt" type="number" step="1" id="physystAmt"
                    class="w-24 px-2 py-1 border border-gray-300 rounded text-xs bg-green-100 text-right font-semibold dark:bg-green-200 dark:text-gray-800"
                    readonly />
                </div>
                <div class="space-y-1">
                  <div class="flex justify-between items-center">
                    <InputLabel for="slider" value="Commission %:" class="text-xs" />
                    <span v-if="commissionDetails.manualCommissionEnabled"
                      class="text-xs text-orange-600 font-medium">Manual</span>
                    <span v-else-if="commission.referrer_id && commissionBreakdown.length > 0"
                      class="text-xs text-green-600 font-medium">Auto</span>
                  </div>
                  <div class="flex items-center space-x-2">
                    <input v-model="commission.slider" type="range" min="0" max="100" step="1" id="slider" :disabled="!commissionDetails.manualCommissionEnabled &&
                      !commission.referrer_id
                      " :class="[
                        'flex-1 h-2 rounded-lg appearance-none cursor-pointer',
                        commissionDetails.manualCommissionEnabled
                          ? 'bg-orange-200'
                          : 'bg-gray-200',
                        'dark:bg-gray-700',
                      ]" ref="commissionSliderRef" @keydown="handleCommissionSliderEnter" />
                    <span class="text-xs font-semibold w-8" :class="commissionDetails.manualCommissionEnabled
                      ? 'text-orange-600'
                      : 'text-gray-700 dark:text-gray-300'
                      ">
                      {{ commission.slider }}%
                    </span>
                  </div>
                  <div v-if="commissionDetails.manualCommissionEnabled" class="text-xs text-orange-600 mt-1">
                    Manual commission enabled - adjust as needed
                  </div>
                </div>
              </div>
            </div>
            <div class="p-4 bg-white dark:bg-slate-800 dark:border-gray-600">
              <div class="flex justify-end space-x-3">
                <button @click="cancelBill"
                  class="px-4 py-2 text-gray-600 hover:text-gray-800 bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded text-sm font-medium dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:hover:bg-gray-600 dark:hover:text-gray-100 transition-colors">
                  Cancel
                </button>
                <button @click="saveBill"
                  class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm font-medium flex items-center space-x-2 transition-colors">
                  <span>💾</span>
                  <span>Save & Print Bill</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Custom Slider Styling */
.slider::-webkit-slider-thumb {
  appearance: none;
  height: 18px;
  width: 18px;
  border-radius: 50%;
  background: #10b981;
  cursor: pointer;
  border: 2px solid #ffffff;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.slider::-moz-range-thumb {
  height: 18px;
  width: 18px;
  border-radius: 50%;
  background: #10b981;
  cursor: pointer;
  border: 2px solid #ffffff;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

/* Input focus improvements */
input:focus,
select:focus,
textarea:focus {
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
}

/* Table styling improvements */
table {
  border-collapse: separate;
  border-spacing: 0;
}

th,
td {
  border-bottom: 1px solid #e5e7eb;
}

th:first-child,
td:first-child {
  border-left: 1px solid #e5e7eb;
}

th:last-child,
td:last-child {
  border-right: 1px solid #e5e7eb;
}

/* Professional color scheme matching the image */
.bg-yellow-100 {
  background-color: #fef3c7 !important;
}

.bg-green-200 {
  background-color: #bbf7d0 !important;
}

.bg-red-100 {
  background-color: #fee2e2 !important;
}

.bg-gray-100 {
  background-color: #f3f4f6 !important;
}

/* Dark mode colors */
.dark .bg-yellow-200 {
  background-color: #fbbf24 !important;
}

.dark .bg-green-300 {
  background-color: #6ee7b7 !important;
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
  width: 6px;
}

::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 3px;
}

::-webkit-scrollbar-thumb {
  background: #888;
  border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
  background: #555;
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
  /* Fix for grid item overflow */
}

/* Fix for commission column layout */
.flex-col {
  min-height: 0;
  /* Fix for flex item overflow */
}

/* Responsive improvements */
@media (max-width: 1024px) {
  .grid.lg\:grid-cols-12 {
    grid-template-columns: repeat(1, minmax(0, 1fr));
  }

  .lg\:col-span-2,
  .lg\:col-span-1 {
    grid-column: span 1 / span 1;
  }
}

@media (max-width: 768px) {
  .grid.lg\:grid-cols-3 {
    grid-template-columns: repeat(1, minmax(0, 1fr));
  }

  .flex.justify-end {
    justify-content: center;
  }

  .max-h-custom {
    max-height: 300px;
  }
}
</style>